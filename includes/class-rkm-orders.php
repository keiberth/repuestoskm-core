<?php

if (!defined('ABSPATH')) {
    exit;
}

class RKM_Orders {

    public function init() {
        add_action('wp_ajax_rkm_create_order', [$this, 'ajax_create_order']);
        add_action('wp_ajax_nopriv_rkm_create_order', [$this, 'ajax_no_privileges']);
    }

    public function ajax_no_privileges() {
        wp_send_json_error([
            'message' => 'Debes iniciar sesion para realizar un pedido.',
        ], 403);
    }

    public function ajax_create_order() {
        if (!is_user_logged_in()) {
            wp_send_json_error([
                'message' => 'Usuario no autenticado.',
            ], 403);
        }

        check_ajax_referer('rkm_orders_nonce', 'nonce');

        $items = isset($_POST['items']) ? json_decode(stripslashes($_POST['items']), true) : [];

        if (empty($items) || !is_array($items)) {
            wp_send_json_error([
                'message' => 'No se recibieron productos validos.',
            ], 400);
        }

        if (!function_exists('wc_create_order')) {
            wp_send_json_error([
                'message' => 'WooCommerce no esta disponible.',
            ], 500);
        }

        $actor_user_id = get_current_user_id();
        $actor_user = get_user_by('id', $actor_user_id);
        $requested_customer_id = isset($_POST['customer_id']) ? absint($_POST['customer_id']) : 0;
        $order_customer_id = $this->resolve_order_customer_id($actor_user, $requested_customer_id);
        $order_customer = get_user_by('id', $order_customer_id);

        try {
            $order = wc_create_order([
                'customer_id' => $order_customer_id,
            ]);

            $order->set_address($this->get_customer_billing_address($order_customer_id, $order_customer), 'billing');
            $order->set_address($this->get_customer_shipping_address($order_customer_id), 'shipping');

            foreach ($items as $item) {
                $product_id = isset($item['id']) ? absint($item['id']) : 0;
                $quantity = isset($item['quantity']) ? absint($item['quantity']) : 0;

                if (!$product_id || !$quantity) {
                    continue;
                }

                $product = wc_get_product($product_id);

                if (!$product) {
                    continue;
                }

                $order->add_product($product, $quantity);
            }

            if (!$order->get_items()) {
                wp_send_json_error([
                    'message' => 'No se pudieron agregar productos al pedido.',
                ], 400);
            }

            $order->calculate_totals();
            $payment_context = $this->resolve_payment_context_from_request($order);

            if (is_wp_error($payment_context)) {
                wp_send_json_error([
                    'message' => $payment_context->get_error_message(),
                ], 400);
            }

            $this->apply_payment_context_to_order($order, $payment_context);
            $order->update_status('pending', 'Pedido generado desde Portal del Cliente');

            if ($actor_user_id !== $order_customer_id && $actor_user instanceof WP_User) {
                $actor_name = $actor_user->display_name ? $actor_user->display_name : $actor_user->user_login;
                $customer_name = $order_customer instanceof WP_User
                    ? ($order_customer->display_name ? $order_customer->display_name : $order_customer->user_login)
                    : 'cliente seleccionado';

                $order->add_order_note(sprintf('Pedido generado por %s para %s.', $actor_name, $customer_name));
            }

            wp_send_json_success($this->build_success_response($order, $actor_user, $order_customer_id, $order_customer));
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => 'Error al generar el pedido: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function resolve_order_customer_id($actor_user, $requested_customer_id) {
        $actor_user_id = $actor_user instanceof WP_User ? (int) $actor_user->ID : get_current_user_id();

        if (
            $requested_customer_id > 0
            && $actor_user instanceof WP_User
            && class_exists('RKM_Permissions')
            && class_exists('RKM_Assignments')
            && RKM_Permissions::is_rkm_vendor($actor_user)
        ) {
            $assigned_customer_ids = array_map('intval', RKM_Assignments::get_assigned_customer_ids($actor_user_id));

            if (in_array($requested_customer_id, $assigned_customer_ids, true)) {
                $requested_customer = get_user_by('id', $requested_customer_id);

                if ($requested_customer instanceof WP_User && RKM_Permissions::is_rkm_customer($requested_customer)) {
                    return (int) $requested_customer->ID;
                }
            }
        }

        return $actor_user_id;
    }

    private function resolve_payment_context_from_request($order) {
        if (!class_exists('RKM_Payment_Terms')) {
            return new WP_Error('rkm_payment_terms_unavailable', 'Las condiciones de pago no estan disponibles.');
        }

        $active_terms = RKM_Payment_Terms::get_active_terms();

        if (empty($active_terms)) {
            return new WP_Error('rkm_payment_terms_empty', 'No hay condiciones de pago activas para confirmar pedidos.');
        }

        $payment_term_key = isset($_POST['payment_term'])
            ? sanitize_key(wp_unslash($_POST['payment_term']))
            : '';
        $payment_term = RKM_Payment_Terms::get_active_term($payment_term_key);

        if (!$payment_term) {
            return new WP_Error('rkm_payment_term_invalid', 'Selecciona una condicion de pago valida.');
        }

        $original_total = (float) $order->get_total();
        $cash_discount_percent = 0;
        $cash_discount_amount = 0;
        $upfront_amount = 0;
        $credit_balance = 0;
        $final_total = $original_total;

        if ($payment_term_key === 'cash') {
            $cash_discount_percent = RKM_Payment_Terms::get_cash_discount_percent();
            $cash_discount_amount = $this->round_money($original_total * ($cash_discount_percent / 100));
            $cash_discount_amount = min($original_total, max(0, $cash_discount_amount));
            $final_total = max(0, $original_total - $cash_discount_amount);
        }

        if ($payment_term_key === 'mixed') {
            $upfront_amount = isset($_POST['upfront_amount'])
                ? $this->round_money((float) wc_clean(wp_unslash($_POST['upfront_amount'])))
                : 0;

            if ($upfront_amount <= 0) {
                return new WP_Error('rkm_upfront_amount_required', 'Indica el monto inicial para la condicion de pago mixta.');
            }

            if ($upfront_amount > $final_total) {
                return new WP_Error('rkm_upfront_amount_invalid', 'El monto inicial no puede ser mayor al total del pedido.');
            }
        }

        if ($payment_term_key === 'credit') {
            $credit_balance = $final_total;
        } elseif ($payment_term_key === 'mixed') {
            $credit_balance = max(0, $final_total - $upfront_amount);
        }

        $needs_payment_method = in_array($payment_term_key, ['cash', 'mixed'], true);
        $payment_method = $needs_payment_method ? $this->resolve_payment_method_from_request() : null;

        if (is_wp_error($payment_method)) {
            return $payment_method;
        }

        return [
            'term_key'              => $payment_term_key,
            'term_label'            => $payment_term['label'],
            'original_total'        => $original_total,
            'final_total'           => $final_total,
            'cash_discount_percent' => $cash_discount_percent,
            'cash_discount_amount'  => $cash_discount_amount,
            'upfront_amount'        => $upfront_amount,
            'credit_balance'        => $credit_balance,
            'payment_method'        => $payment_method,
            'payment_note'          => $this->get_payment_note_from_request(),
        ];
    }

    private function resolve_payment_method_from_request() {
        if (!class_exists('RKM_Payment_Methods')) {
            return null;
        }

        $active_methods = RKM_Payment_Methods::get_active_methods();

        if (empty($active_methods)) {
            return null;
        }

        $payment_method_id = isset($_POST['payment_method_id'])
            ? sanitize_key(wp_unslash($_POST['payment_method_id']))
            : '';

        if ($payment_method_id === '') {
            return new WP_Error('rkm_payment_method_required', 'Selecciona una forma de pago para confirmar el pedido.');
        }

        $payment_method = RKM_Payment_Methods::get_active_method($payment_method_id);

        if (!$payment_method) {
            return new WP_Error('rkm_payment_method_invalid', 'La forma de pago seleccionada no esta disponible.');
        }

        return $payment_method;
    }

    private function get_payment_note_from_request() {
        return isset($_POST['payment_note'])
            ? sanitize_textarea_field(wp_unslash($_POST['payment_note']))
            : '';
    }

    private function apply_payment_context_to_order($order, $payment_context) {
        $payment_method = isset($payment_context['payment_method']) && is_array($payment_context['payment_method'])
            ? $payment_context['payment_method']
            : null;
        $payment_note = isset($payment_context['payment_note']) ? $payment_context['payment_note'] : '';
        $method_id = $payment_method && isset($payment_method['id']) ? sanitize_key($payment_method['id']) : '';
        $method_label = $payment_method && isset($payment_method['name']) ? sanitize_text_field($payment_method['name']) : '';

        if (!empty($payment_context['cash_discount_amount'])) {
            $fee = new WC_Order_Item_Fee();
            $fee->set_name('Descuento pago contado');
            $fee->set_amount(-1 * (float) $payment_context['cash_discount_amount']);
            $fee->set_total(-1 * (float) $payment_context['cash_discount_amount']);
            $order->add_item($fee);
            $order->calculate_totals();
            $payment_context['final_total'] = (float) $order->get_total();
        }

        $order->update_meta_data('_rkm_payment_term', $payment_context['term_key']);
        $order->update_meta_data('_rkm_payment_term_label', $payment_context['term_label']);
        $order->update_meta_data('_rkm_cash_discount_percent', $payment_context['cash_discount_percent']);
        $order->update_meta_data('_rkm_cash_discount_amount', $payment_context['cash_discount_amount']);
        $order->update_meta_data('_rkm_original_total', $payment_context['original_total']);
        $order->update_meta_data('_rkm_final_total', $payment_context['final_total']);
        $order->update_meta_data('_rkm_upfront_amount', $payment_context['upfront_amount']);
        $order->update_meta_data('_rkm_credit_balance', $payment_context['credit_balance']);

        $order->update_meta_data('_rkm_payment_method_id', $method_id);
        $order->update_meta_data('_rkm_payment_method_label', $method_label);
        $order->update_meta_data('_rkm_payment_note', $payment_note);

        $note_lines = [
            sprintf('Condicion de pago: %s.', $payment_context['term_label']),
        ];

        if (!empty($payment_context['cash_discount_amount'])) {
            $note_lines[] = sprintf(
                'Descuento contado: %s%% (%s).',
                $payment_context['cash_discount_percent'],
                wc_price($payment_context['cash_discount_amount'])
            );
        }

        if (!empty($payment_context['upfront_amount'])) {
            $note_lines[] = sprintf('Monto inicial: %s.', wc_price($payment_context['upfront_amount']));
        }

        if (!empty($payment_context['credit_balance'])) {
            $note_lines[] = sprintf('Saldo a credito: %s.', wc_price($payment_context['credit_balance']));
        }

        if ($method_label !== '') {
            $note_lines[] = sprintf('Forma de pago seleccionada: %s.', $method_label);
        }

        if ($payment_note !== '') {
            $note_lines[] = 'Observacion de pago: ' . $payment_note;
        }

        $order->add_order_note(implode("\n", $note_lines));
        $order->save();
    }

    private function round_money($amount) {
        $decimals = function_exists('wc_get_price_decimals') ? wc_get_price_decimals() : 2;

        return round((float) $amount, $decimals);
    }

    private function get_customer_billing_address($customer_id, $customer) {
        return [
            'first_name' => get_user_meta($customer_id, 'billing_first_name', true),
            'last_name'  => get_user_meta($customer_id, 'billing_last_name', true),
            'company'    => get_user_meta($customer_id, 'billing_company', true),
            'email'      => $customer instanceof WP_User ? $customer->user_email : '',
            'phone'      => get_user_meta($customer_id, 'billing_phone', true),
            'address_1'  => get_user_meta($customer_id, 'billing_address_1', true),
            'address_2'  => get_user_meta($customer_id, 'billing_address_2', true),
            'city'       => get_user_meta($customer_id, 'billing_city', true),
            'state'      => get_user_meta($customer_id, 'billing_state', true),
            'postcode'   => get_user_meta($customer_id, 'billing_postcode', true),
            'country'    => get_user_meta($customer_id, 'billing_country', true),
        ];
    }

    private function get_customer_shipping_address($customer_id) {
        return [
            'first_name' => get_user_meta($customer_id, 'shipping_first_name', true),
            'last_name'  => get_user_meta($customer_id, 'shipping_last_name', true),
            'company'    => get_user_meta($customer_id, 'shipping_company', true),
            'address_1'  => get_user_meta($customer_id, 'shipping_address_1', true),
            'address_2'  => get_user_meta($customer_id, 'shipping_address_2', true),
            'city'       => get_user_meta($customer_id, 'shipping_city', true),
            'state'      => get_user_meta($customer_id, 'shipping_state', true),
            'postcode'   => get_user_meta($customer_id, 'shipping_postcode', true),
            'country'    => get_user_meta($customer_id, 'shipping_country', true),
        ];
    }

    private function build_success_response($order, $actor_user, $order_customer_id, $order_customer) {
        $response = [
            'order_id' => $order->get_id(),
        ];

        if (
            $actor_user instanceof WP_User
            && class_exists('RKM_Permissions')
            && RKM_Permissions::is_rkm_vendor($actor_user)
            && (int) $actor_user->ID !== $order_customer_id
        ) {
            $customer_name = $order_customer instanceof WP_User
                ? ($order_customer->display_name ? $order_customer->display_name : $order_customer->user_login)
                : 'el cliente seleccionado';

            $response['message'] = sprintf('El pedido #%d fue creado correctamente para %s.', $order->get_id(), $customer_name);
            $response['success_title'] = 'Pedido creado para la cartera';
            $response['redirect'] = home_url('/mi-cuenta/panel/?section=panel-vendedor');
            $response['redirect_label'] = 'Volver al panel vendedor';

            return $response;
        }

        $response['message'] = sprintf('Tu pedido #%d fue enviado con exito y ya esta disponible en la seccion Pedidos.', $order->get_id());
        $response['success_title'] = 'Pedido enviado correctamente';
        $response['redirect'] = home_url('/mi-cuenta/panel/?section=pedidos');
        $response['redirect_label'] = 'Ver mis pedidos';

        return $response;
    }
}
