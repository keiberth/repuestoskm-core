<?php

if (!defined('ABSPATH')) {
    exit;
}

class RKM_Operational_Orders {

    const SECTION_KEY = 'pedidos-operativos';
    const ORDERS_LIMIT = 100;
    const NONCE_ACTION = 'rkm_operational_orders_nonce';
    const LEGACY_PENDING = 'pending';
    const LEGACY_REVIEW = 'en-revision';
    const LEGACY_PROCESSING = 'processing';

    public function init() {
        add_action('wp_ajax_rkm_confirm_operational_order', [$this, 'ajax_confirm_order']);
        add_action('wp_ajax_rkm_send_operational_order_to_warehouse', [$this, 'ajax_send_to_warehouse']);
        add_action('wp_ajax_rkm_update_operational_order', [$this, 'ajax_update_order']);
    }

    public static function can_access($user = null) {
        if (!class_exists('RKM_Permissions')) {
            return false;
        }

        return RKM_Permissions::is_rkm_admin($user) || RKM_Permissions::is_rkm_vendor($user);
    }

    public static function get_section_key() {
        return self::SECTION_KEY;
    }

    public static function get_section_url() {
        return home_url('/mi-cuenta/panel/?section=' . self::SECTION_KEY);
    }

    public static function can_confirm($user = null) {
        return class_exists('RKM_Permissions') && RKM_Permissions::is_rkm_admin($user);
    }

    public static function get_nonce_action() {
        return self::NONCE_ACTION;
    }

    public static function get_list_statuses() {
        if (!class_exists('RKM_Order_Statuses')) {
            return [
                'rkm-review',
                'rkm-confirmed',
                'rkm-warehouse',
                'rkm-ready',
                'rkm-dispatched',
                self::LEGACY_PENDING,
                self::LEGACY_REVIEW,
                self::LEGACY_PROCESSING,
            ];
        }

        return array_values(array_unique(array_merge(
            RKM_Order_Statuses::get_operational_statuses(),
            [
                self::LEGACY_PENDING,
                self::LEGACY_REVIEW,
                self::LEGACY_PROCESSING,
            ]
        )));
    }

    public static function get_confirmable_statuses() {
        return self::get_review_statuses();
    }

    public static function get_editable_statuses() {
        return self::get_confirmable_statuses();
    }

    public static function get_review_statuses() {
        return [
            class_exists('RKM_Order_Statuses') ? RKM_Order_Statuses::REVIEW : 'rkm-review',
            self::LEGACY_PENDING,
            self::LEGACY_REVIEW,
        ];
    }

    public static function can_edit($user = null) {
        return self::can_confirm($user);
    }

    public function ajax_confirm_order() {
        if (!check_ajax_referer(self::NONCE_ACTION, 'nonce', false)) {
            wp_send_json_error(['message' => 'Solicitud invalida. Actualiza la pagina e intenta nuevamente.'], 403);
        }

        if (!is_user_logged_in() || !self::can_confirm()) {
            wp_send_json_error(['message' => 'No tenes permiso para confirmar pedidos.'], 403);
        }

        if (!function_exists('wc_get_order') || !class_exists('RKM_Order_Statuses')) {
            wp_send_json_error(['message' => 'WooCommerce no esta disponible.'], 500);
        }

        $order_id = isset($_POST['order_id']) ? absint(wp_unslash($_POST['order_id'])) : 0;

        if ($order_id <= 0) {
            wp_send_json_error(['message' => 'Pedido invalido.'], 400);
        }

        $order = wc_get_order($order_id);

        if (!$order) {
            wp_send_json_error(['message' => 'Pedido no encontrado.'], 404);
        }

        if (!in_array($order->get_status(), self::get_confirmable_statuses(), true)) {
            wp_send_json_error(['message' => 'Solo se pueden confirmar pedidos en revision o pendientes.'], 409);
        }

        $stock_result = $this->reduce_stock_for_confirmation($order);

        if (is_wp_error($stock_result)) {
            wp_send_json_error(['message' => $stock_result->get_error_message()], 500);
        }

        $user = wp_get_current_user();
        $user_label = $user instanceof WP_User && $user->display_name !== ''
            ? $user->display_name
            : ('Usuario #' . get_current_user_id());
        $confirmed_at = current_time('mysql');
        $note = sprintf(
            'Pedido confirmado por %s el %s.',
            $user_label,
            wp_date('d/m/Y H:i', current_time('timestamp'))
        );

        $order->update_status(RKM_Order_Statuses::CONFIRMED, $note);

        wp_send_json_success([
            'message' => 'Pedido confirmado correctamente.',
            'order' => $this->format_order_row($order),
            'confirmed_at' => $confirmed_at,
        ]);
    }

    public function ajax_send_to_warehouse() {
        if (!check_ajax_referer(self::NONCE_ACTION, 'nonce', false)) {
            wp_send_json_error(['message' => 'Solicitud invalida. Actualiza la pagina e intenta nuevamente.'], 403);
        }

        if (!is_user_logged_in() || !self::can_confirm()) {
            wp_send_json_error(['message' => 'No tenes permiso para enviar pedidos a almacen.'], 403);
        }

        if (!function_exists('wc_get_order') || !class_exists('RKM_Order_Statuses')) {
            wp_send_json_error(['message' => 'WooCommerce no esta disponible.'], 500);
        }

        $order_id = isset($_POST['order_id']) ? absint(wp_unslash($_POST['order_id'])) : 0;

        if ($order_id <= 0) {
            wp_send_json_error(['message' => 'Pedido invalido.'], 400);
        }

        $order = wc_get_order($order_id);

        if (!$order) {
            wp_send_json_error(['message' => 'Pedido no encontrado.'], 404);
        }

        if ($order->get_status() !== RKM_Order_Statuses::CONFIRMED) {
            wp_send_json_error(['message' => 'Solo se pueden enviar a almacen pedidos confirmados.'], 409);
        }

        $user = wp_get_current_user();
        $user_label = $user instanceof WP_User && $user->display_name !== ''
            ? $user->display_name
            : ('Usuario #' . get_current_user_id());
        $sent_at = current_time('mysql');
        $sent_at_label = wp_date('d/m/Y H:i', current_time('timestamp'));
        $note = sprintf(
            'Pedido enviado a almacen por %s el %s.',
            $user_label,
            $sent_at_label
        );

        $order->update_meta_data('_rkm_sent_to_warehouse_at', $sent_at);
        $order->update_meta_data('_rkm_sent_to_warehouse_by', get_current_user_id());
        $order->save();
        $order->update_status(RKM_Order_Statuses::WAREHOUSE, $note);

        wp_send_json_success([
            'message' => 'Pedido enviado a almacen correctamente.',
            'order' => $this->format_order_row($order),
            'sent_at' => $sent_at,
        ]);
    }

    public function ajax_update_order() {
        if (!check_ajax_referer(self::NONCE_ACTION, 'nonce', false)) {
            wp_send_json_error(['message' => 'Solicitud invalida. Actualiza la pagina e intenta nuevamente.'], 403);
        }

        if (!is_user_logged_in() || !self::can_edit()) {
            wp_send_json_error(['message' => 'No tenes permiso para editar pedidos operativos.'], 403);
        }

        if (!function_exists('wc_get_order')) {
            wp_send_json_error(['message' => 'WooCommerce no esta disponible.'], 500);
        }

        $order_id = isset($_POST['order_id']) ? absint(wp_unslash($_POST['order_id'])) : 0;

        if ($order_id <= 0) {
            wp_send_json_error(['message' => 'Pedido invalido.'], 400);
        }

        $order = wc_get_order($order_id);

        if (!$order) {
            wp_send_json_error(['message' => 'Pedido no encontrado.'], 404);
        }

        if (!in_array($order->get_status(), self::get_editable_statuses(), true)) {
            wp_send_json_error(['message' => 'Solo se pueden editar pedidos en revision o pendientes.'], 409);
        }

        $items_payload = isset($_POST['items']) ? wp_unslash($_POST['items']) : [];
        $items_payload = is_array($items_payload) ? $items_payload : [];
        $quantity_result = $this->update_order_item_quantities($order, $items_payload);

        if (is_wp_error($quantity_result)) {
            wp_send_json_error(['message' => $quantity_result->get_error_message()], 400);
        }

        $order->calculate_totals();

        $payment_update_enabled = isset($_POST['payment_update_enabled']) && wp_unslash($_POST['payment_update_enabled']) === '1';
        $payment_context = null;

        if ($payment_update_enabled) {
            $this->remove_cash_discount_fees($order);
            $order->calculate_totals();

            $payment_context = $this->resolve_payment_context_from_request($order);

            if (is_wp_error($payment_context)) {
                wp_send_json_error(['message' => $payment_context->get_error_message()], 400);
            }

            $this->apply_payment_context_to_order($order, $payment_context);
        } else {
            $order->save();
        }

        $user = wp_get_current_user();
        $user_label = $user instanceof WP_User && $user->display_name !== ''
            ? $user->display_name
            : ('Usuario #' . get_current_user_id());
        $changes = isset($quantity_result['changes']) && is_array($quantity_result['changes'])
            ? $quantity_result['changes']
            : [];
        $note_lines = [
            sprintf(
                'Pedido editado por %s el %s.',
                $user_label,
                wp_date('d/m/Y H:i', current_time('timestamp'))
            ),
        ];

        if (!empty($changes)) {
            $note_lines[] = 'Cantidades actualizadas: ' . implode('; ', $changes) . '.';
        }

        if ($payment_update_enabled && is_array($payment_context)) {
            $note_lines[] = sprintf('Condicion de pago: %s.', $payment_context['term_label']);

            if (!empty($payment_context['payment_method_label'])) {
                $note_lines[] = sprintf('Forma de pago: %s.', $payment_context['payment_method_label']);
            }

            if (!empty($payment_context['upfront_amount'])) {
                $note_lines[] = sprintf('Monto inicial: %s.', wc_price($payment_context['upfront_amount']));
            }

            if (!empty($payment_context['credit_balance'])) {
                $note_lines[] = sprintf('Saldo a credito: %s.', wc_price($payment_context['credit_balance']));
            }
        } else {
            $note_lines[] = 'Condicion de pago sin cambios.';
        }

        $order->add_order_note(implode("\n", $note_lines));
        $order->save();

        wp_send_json_success([
            'message' => 'Pedido actualizado correctamente.',
            'order' => $this->format_order_row($order),
        ]);
    }

    private function reduce_stock_for_confirmation($order) {
        if ($order->get_meta('_rkm_stock_reduced', true) === 'yes') {
            $order->add_order_note('Stock no descontado nuevamente: _rkm_stock_reduced ya estaba marcado como yes.');
            return true;
        }

        if (!function_exists('wc_reduce_stock_levels')) {
            return new WP_Error('rkm_stock_function_missing', 'No se pudo descontar stock porque WooCommerce no esta disponible.');
        }

        try {
            wc_reduce_stock_levels($order->get_id());
        } catch (Throwable $exception) {
            return new WP_Error(
                'rkm_stock_reduce_failed',
                'No se pudo descontar stock: ' . $exception->getMessage()
            );
        }

        $order->update_meta_data('_rkm_stock_reduced', 'yes');
        $order->save();
        $order->add_order_note('Stock descontado al confirmar pedido RKM.');

        return true;
    }

    public function render_page($data = []) {
        if (!self::can_access()) {
            wp_safe_redirect(class_exists('RKM_Auth') ? RKM_Auth::get_redirect_url_for_user() : home_url('/mi-cuenta/panel/'));
            exit;
        }

        $user = wp_get_current_user();
        $view_data = array_merge($data, [
            'page_title' => 'Pedidos operativos',
            'page_subtitle' => 'Consola de revision para pedidos en flujo ERP RKM.',
            'current_section' => self::SECTION_KEY,
            'section_url' => self::get_section_url(),
            'operational_orders' => $this->get_operational_orders($user),
            'is_admin_context' => class_exists('RKM_Permissions') && RKM_Permissions::is_rkm_admin($user),
            'is_vendor_context' => class_exists('RKM_Permissions') && RKM_Permissions::is_rkm_vendor($user),
            'can_confirm_orders' => self::can_confirm($user),
            'can_edit_orders' => self::can_edit($user),
            'payment_terms' => class_exists('RKM_Payment_Terms') ? RKM_Payment_Terms::get_active_terms() : [],
            'payment_methods' => class_exists('RKM_Payment_Methods') ? RKM_Payment_Methods::get_active_methods() : [],
            'payment_terms_settings' => class_exists('RKM_Payment_Terms') ? RKM_Payment_Terms::get_settings() : [],
        ]);

        $template = RKM_CORE_PATH . 'templates/admin/operational-orders.php';

        if (file_exists($template)) {
            $data = $view_data;
            include $template;
        }
    }

    private function get_operational_orders($user) {
        if (!function_exists('wc_get_orders') || !class_exists('RKM_Order_Statuses')) {
            return [];
        }

        $query_args = [
            'limit'   => self::ORDERS_LIMIT,
            'orderby' => 'date',
            'order'   => 'DESC',
            'status'  => self::get_list_statuses(),
        ];

        if (
            class_exists('RKM_Permissions')
            && RKM_Permissions::is_rkm_vendor($user)
            && !RKM_Permissions::is_rkm_admin($user)
        ) {
            $customer_ids = class_exists('RKM_Assignments')
                ? array_values(array_filter(array_map('intval', RKM_Assignments::get_assigned_customer_ids((int) $user->ID))))
                : [];

            if (empty($customer_ids)) {
                return [];
            }

            $query_args['customer_id'] = $customer_ids;
        }

        $orders = wc_get_orders($query_args);

        if (empty($orders)) {
            return [];
        }

        return array_map([$this, 'format_order_row'], $orders);
    }

    private function format_order_row($order) {
        $customer_name = trim($order->get_formatted_billing_full_name());

        if ($customer_name === '') {
            $customer = $order->get_user();
            $customer_name = $customer instanceof WP_User && $customer->display_name
                ? $customer->display_name
                : ($order->get_billing_email() ?: 'Cliente sin nombre');
        }

        $date_created = $order->get_date_created();
        $status = $order->get_status();

        return [
            'id' => (int) $order->get_id(),
            'number' => $order->get_order_number(),
            'customer_name' => $customer_name,
            'customer_email' => $order->get_billing_email() ?: '-',
            'customer_phone' => $order->get_billing_phone() ?: '-',
            'date' => $date_created ? $date_created->date_i18n('d/m/Y H:i') : 'Sin fecha',
            'total' => $this->clean_money_text($order->get_formatted_order_total()),
            'status' => $status,
            'status_label' => function_exists('wc_get_order_status_name') ? wc_get_order_status_name($status) : ucfirst($status),
            'payment_term' => $this->get_order_meta_label($order, '_rkm_payment_term_label', '_rkm_payment_term'),
            'payment_term_key' => (string) $order->get_meta('_rkm_payment_term', true),
            'payment_method' => $this->get_order_meta_label($order, '_rkm_payment_method_label', '_rkm_payment_method_id'),
            'payment_method_id' => (string) $order->get_meta('_rkm_payment_method_id', true),
            'upfront_amount' => (float) $order->get_meta('_rkm_upfront_amount', true),
            'upfront_amount_display' => $this->clean_money_text(wc_price((float) $order->get_meta('_rkm_upfront_amount', true))),
            'credit_balance' => (float) $order->get_meta('_rkm_credit_balance', true),
            'credit_balance_display' => $this->clean_money_text(wc_price((float) $order->get_meta('_rkm_credit_balance', true))),
            'cash_discount_amount' => (float) $order->get_meta('_rkm_cash_discount_amount', true),
            'cash_discount_display' => $this->clean_money_text(wc_price((float) $order->get_meta('_rkm_cash_discount_amount', true))),
            'payment_note' => (string) $order->get_meta('_rkm_payment_note', true),
            'customer_note' => (string) $order->get_customer_note(),
            'internal_notes' => $this->get_internal_notes($order),
            'items' => $this->format_order_items($order),
            'is_editable' => in_array($status, self::get_editable_statuses(), true),
        ];
    }

    private function get_order_meta_label($order, $label_key, $fallback_key) {
        $label = (string) $order->get_meta($label_key, true);

        if ($label !== '') {
            return $label;
        }

        $fallback = (string) $order->get_meta($fallback_key, true);

        return $fallback !== '' ? $fallback : '-';
    }

    private function format_order_items($order) {
        $items = [];

        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            $quantity = max(1, (int) $item->get_quantity());
            $line_subtotal = (float) $item->get_subtotal();
            $line_total = (float) $item->get_total();
            $unit_price = $quantity > 0 ? $line_total / $quantity : 0;
            $unit_subtotal = $quantity > 0 ? $line_subtotal / $quantity : $unit_price;
            $stock_quantity = $product ? $product->get_stock_quantity() : null;
            $max_quantity = $product && $product->managing_stock() && $stock_quantity !== null ? max(0, (int) $stock_quantity) : null;

            $items[] = [
                'item_id' => (int) $item_id,
                'product_id' => $product ? (int) $product->get_id() : 0,
                'name' => $item->get_name(),
                'sku' => $product ? $product->get_sku() : '',
                'quantity' => $quantity,
                'max_quantity' => $max_quantity,
                'stock_label' => $max_quantity !== null ? ('Stock disponible: ' . $max_quantity) : '',
                'unit_price_raw' => $unit_price,
                'unit_subtotal_raw' => $unit_subtotal,
                'total_raw' => $line_total,
                'unit_price' => $this->clean_money_text(wc_price($unit_price)),
                'total' => $this->clean_money_text(wc_price($line_total)),
            ];
        }

        return $items;
    }

    private function clean_money_text($value) {
        return html_entity_decode(wp_strip_all_tags((string) $value), ENT_QUOTES, 'UTF-8');
    }

    private function get_internal_notes($order) {
        if (!function_exists('wc_get_order_notes')) {
            return [];
        }

        $notes = wc_get_order_notes([
            'order_id' => $order->get_id(),
            'type' => 'internal',
            'limit' => 5,
            'orderby' => 'date_created',
            'order' => 'DESC',
        ]);

        if (empty($notes)) {
            return [];
        }

        return array_map(function ($note) {
            return [
                'date' => !empty($note->date_created) ? $this->clean_text($note->date_created->date_i18n('d/m/Y H:i')) : '',
                'content' => $this->clean_text($note->content),
            ];
        }, $notes);
    }

    private function clean_text($value) {
        $value = html_entity_decode(wp_strip_all_tags((string) $value), ENT_QUOTES, 'UTF-8');
        $value = str_replace("\xc2\xa0", ' ', $value);

        return trim(preg_replace('/\s+/', ' ', $value));
    }

    private function update_order_item_quantities($order, $items_payload) {
        if (empty($items_payload)) {
            return new WP_Error('rkm_items_empty', 'El pedido debe tener al menos un producto.');
        }

        $posted_quantities = [];

        foreach ($items_payload as $item_id => $quantity) {
            $item_id = absint($item_id);
            $quantity = absint($quantity);

            if ($item_id <= 0) {
                continue;
            }

            if ($quantity < 1) {
                return new WP_Error('rkm_quantity_invalid', 'Las cantidades deben ser mayores o iguales a 1.');
            }

            $posted_quantities[$item_id] = $quantity;
        }

        if (empty($posted_quantities)) {
            return new WP_Error('rkm_items_invalid', 'No se recibieron cantidades validas.');
        }

        $changes = [];

        foreach ($order->get_items() as $item_id => $item) {
            if (!array_key_exists((int) $item_id, $posted_quantities)) {
                return new WP_Error('rkm_item_missing', 'Faltan productos del pedido en la solicitud.');
            }

            $quantity = (int) $posted_quantities[(int) $item_id];
            $current_quantity = max(1, (int) $item->get_quantity());
            $product = $item->get_product();

            if ($product && $product->managing_stock()) {
                $stock_quantity = $product->get_stock_quantity();

                if ($stock_quantity !== null && $quantity > (int) $stock_quantity) {
                    return new WP_Error(
                        'rkm_quantity_stock_invalid',
                        sprintf('No hay stock suficiente para %s. Disponible: %d.', $item->get_name(), max(0, (int) $stock_quantity))
                    );
                }
            }

            $unit_subtotal = $current_quantity > 0 ? ((float) $item->get_subtotal() / $current_quantity) : 0;
            $unit_total = $current_quantity > 0 ? ((float) $item->get_total() / $current_quantity) : $unit_subtotal;

            if ($unit_subtotal <= 0 && $product) {
                $unit_subtotal = (float) $product->get_price();
            }

            if ($unit_total <= 0) {
                $unit_total = $unit_subtotal;
            }

            if ($quantity !== $current_quantity) {
                $changes[] = sprintf('%s de %d a %d', $item->get_name(), $current_quantity, $quantity);
            }

            $item->set_quantity($quantity);
            $item->set_subtotal($unit_subtotal * $quantity);
            $item->set_total($unit_total * $quantity);
            $item->save();
        }

        return ['changes' => $changes];
    }

    private function resolve_payment_context_from_request($order) {
        if (!class_exists('RKM_Payment_Terms')) {
            return new WP_Error('rkm_payment_terms_unavailable', 'Las condiciones de pago no estan disponibles.');
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

        $method_id = is_array($payment_method) && isset($payment_method['id']) ? sanitize_key($payment_method['id']) : '';
        $method_label = is_array($payment_method) && isset($payment_method['name']) ? sanitize_text_field($payment_method['name']) : '';

        return [
            'term_key'              => $payment_term_key,
            'term_label'            => $payment_term['label'],
            'original_total'        => $original_total,
            'final_total'           => $final_total,
            'cash_discount_percent' => $cash_discount_percent,
            'cash_discount_amount'  => $cash_discount_amount,
            'upfront_amount'        => $upfront_amount,
            'credit_balance'        => $credit_balance,
            'payment_method_id'     => $method_id,
            'payment_method_label'  => $method_label,
            'payment_note'          => isset($_POST['payment_note']) ? sanitize_textarea_field(wp_unslash($_POST['payment_note'])) : '',
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
            return new WP_Error('rkm_payment_method_required', 'Selecciona una forma de pago para esta condicion.');
        }

        $payment_method = RKM_Payment_Methods::get_active_method($payment_method_id);

        if (!$payment_method) {
            return new WP_Error('rkm_payment_method_invalid', 'La forma de pago seleccionada no esta disponible.');
        }

        return $payment_method;
    }

    private function apply_payment_context_to_order($order, $payment_context) {
        $this->remove_cash_discount_fees($order);

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
        $order->update_meta_data('_rkm_payment_method_id', $payment_context['payment_method_id']);
        $order->update_meta_data('_rkm_payment_method_label', $payment_context['payment_method_label']);
        $order->update_meta_data('_rkm_payment_note', $payment_context['payment_note']);
        $order->save();
    }

    private function remove_cash_discount_fees($order) {
        foreach ($order->get_items('fee') as $item_id => $item) {
            if ($item->get_name() === 'Descuento pago contado') {
                $order->remove_item($item_id);
            }
        }

        $order->calculate_totals();
    }

    private function round_money($amount) {
        $decimals = function_exists('wc_get_price_decimals') ? wc_get_price_decimals() : 2;

        return round((float) $amount, $decimals);
    }
}
