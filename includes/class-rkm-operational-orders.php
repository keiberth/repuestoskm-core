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
        return [
            class_exists('RKM_Order_Statuses') ? RKM_Order_Statuses::REVIEW : 'rkm-review',
            self::LEGACY_PENDING,
            self::LEGACY_REVIEW,
        ];
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
            'total' => wp_strip_all_tags($order->get_formatted_order_total()),
            'status' => $status,
            'status_label' => function_exists('wc_get_order_status_name') ? wc_get_order_status_name($status) : ucfirst($status),
            'payment_term' => $this->get_order_meta_label($order, '_rkm_payment_term_label', '_rkm_payment_term'),
            'payment_method' => $this->get_order_meta_label($order, '_rkm_payment_method_label', '_rkm_payment_method_id'),
            'payment_note' => (string) $order->get_meta('_rkm_payment_note', true),
            'customer_note' => (string) $order->get_customer_note(),
            'internal_notes' => $this->get_internal_notes($order),
            'items' => $this->format_order_items($order),
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

        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $quantity = max(1, (int) $item->get_quantity());
            $line_total = (float) $item->get_total();
            $unit_price = $quantity > 0 ? $line_total / $quantity : 0;

            $items[] = [
                'name' => $item->get_name(),
                'sku' => $product ? $product->get_sku() : '',
                'quantity' => $quantity,
                'unit_price' => wp_strip_all_tags(wc_price($unit_price)),
                'total' => wp_strip_all_tags(wc_price($line_total)),
            ];
        }

        return $items;
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

        return array_map(static function ($note) {
            return [
                'date' => !empty($note->date_created) ? $note->date_created->date_i18n('d/m/Y H:i') : '',
                'content' => wp_strip_all_tags((string) $note->content),
            ];
        }, $notes);
    }
}
