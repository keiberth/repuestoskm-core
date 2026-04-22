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
