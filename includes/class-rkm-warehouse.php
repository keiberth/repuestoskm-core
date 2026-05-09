<?php

if (!defined('ABSPATH')) {
    exit;
}

class RKM_Warehouse {

    const SECTION_KEY = 'almacen';
    const NONCE_ACTION = 'rkm_warehouse_nonce';
    const ORDERS_LIMIT = 100;

    public function init() {
        add_action('wp_ajax_rkm_add_warehouse_note', [$this, 'ajax_add_note']);
        add_action('wp_ajax_rkm_mark_order_ready', [$this, 'ajax_mark_ready']);
    }

    public static function get_section_key() {
        return self::SECTION_KEY;
    }

    public static function get_section_url() {
        return home_url('/mi-cuenta/panel/?section=' . self::SECTION_KEY);
    }

    public static function can_access($user = null) {
        if (!class_exists('RKM_Permissions')) {
            return false;
        }

        return RKM_Permissions::can_access_warehouse($user);
    }

    public static function can_manage($user = null) {
        return self::can_access($user);
    }

    public static function get_nonce_action() {
        return self::NONCE_ACTION;
    }

    public static function get_queue_statuses() {
        return ['rkm-warehouse'];
    }

    public static function get_ready_statuses() {
        return ['rkm-ready'];
    }

    public static function get_visible_statuses() {
        return array_merge(self::get_queue_statuses(), self::get_ready_statuses());
    }

    public static function get_status_label($status) {
        $status = (string) $status;

        if ($status === 'rkm-warehouse') {
            return 'En preparacion';
        }

        if ($status === 'rkm-ready') {
            return 'Listo';
        }

        if (function_exists('wc_get_order_status_name')) {
            return wc_get_order_status_name($status);
        }

        return ucfirst($status);
    }

    public function render_page($data = []) {
        if (!self::can_access()) {
            wp_safe_redirect(class_exists('RKM_Auth') ? RKM_Auth::get_redirect_url_for_user() : home_url('/mi-cuenta/panel/'));
            exit;
        }

        $user = wp_get_current_user();
        $view_data = array_merge($data, [
            'page_title' => 'Almacen',
            'page_subtitle' => 'Preparacion y control logistico de pedidos confirmados.',
            'current_section' => self::SECTION_KEY,
            'section_url' => self::get_section_url(),
            'warehouse_orders' => $this->get_warehouse_orders(),
            'can_manage_warehouse' => self::can_manage($user),
        ]);

        $template = RKM_CORE_PATH . 'templates/admin/warehouse-orders.php';

        if (file_exists($template)) {
            $data = $view_data;
            include $template;
        }
    }

    private function get_warehouse_orders() {
        if (!function_exists('wc_get_orders')) {
            return [];
        }

        $orders = wc_get_orders([
            'limit'   => self::ORDERS_LIMIT,
            'orderby' => 'date',
            'order'   => 'DESC',
            'status'  => self::get_visible_statuses(),
        ]);

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

        $items = $this->format_order_items($order);

        return [
            'id' => (int) $order->get_id(),
            'number' => $order->get_order_number(),
            'customer_name' => $customer_name,
            'customer_email' => $order->get_billing_email() ?: '-',
            'date' => $order->get_date_created() ? $order->get_date_created()->date_i18n('d/m/Y H:i') : 'Sin fecha',
            'status' => $order->get_status(),
            'status_label' => self::get_status_label($order->get_status()),
            'items_count' => count($items),
            'items_summary' => $this->build_items_summary($items),
            'items' => $items,
            'audit_timeline' => class_exists('RKM_Order_Audit_Log') ? RKM_Order_Audit_Log::get_events($order->get_id()) : [],
        ];
    }

    private function build_items_summary(array $items) {
        if (empty($items)) {
            return 'Sin productos';
        }

        $count = count($items);

        if ($count === 1) {
            return '1 producto';
        }

        return sprintf('%d productos', $count);
    }

    private function format_order_items($order) {
        $items = [];

        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            $quantity = max(1, (int) $item->get_quantity());

            $items[] = [
                'item_id' => (int) $item_id,
                'product_id' => $product ? (int) $product->get_id() : 0,
                'name' => $item->get_name(),
                'sku' => $product ? (string) $product->get_sku() : '',
                'quantity' => $quantity,
                'line_total' => function_exists('wc_price') ? html_entity_decode(wp_strip_all_tags(wc_price((float) $item->get_total())), ENT_QUOTES, 'UTF-8') : (string) $item->get_total(),
            ];
        }

        return $items;
    }

    public function ajax_add_note() {
        if (!check_ajax_referer(self::NONCE_ACTION, 'nonce', false)) {
            wp_send_json_error(['message' => 'Solicitud invalida.'], 403);
        }

        if (!is_user_logged_in() || !self::can_manage()) {
            wp_send_json_error(['message' => 'No tenes permiso para operar almacen.'], 403);
        }

        if (!function_exists('wc_get_order') || !class_exists('RKM_Order_Audit_Log')) {
            wp_send_json_error(['message' => 'WooCommerce o auditoria no estan disponibles.'], 500);
        }

        $order_id = isset($_POST['order_id']) ? absint(wp_unslash($_POST['order_id'])) : 0;
        $note = isset($_POST['note']) ? sanitize_textarea_field(wp_unslash($_POST['note'])) : '';

        if ($order_id <= 0 || $note === '') {
            wp_send_json_error(['message' => 'Debes indicar el pedido y una observacion valida.'], 400);
        }

        $order = wc_get_order($order_id);

        if (!$order) {
            wp_send_json_error(['message' => 'Pedido no encontrado.'], 404);
        }

        if (!in_array($order->get_status(), self::get_visible_statuses(), true)) {
            wp_send_json_error(['message' => 'Solo se pueden registrar observaciones en pedidos de almacen.'], 409);
        }

        RKM_Order_Audit_Log::add_event(
            $order_id,
            'Observacion de almacen',
            'Observacion de almacen',
            $note
        );

        wp_send_json_success([
            'message' => 'Observacion registrada correctamente.',
            'timeline' => RKM_Order_Audit_Log::get_events($order_id),
        ]);
    }

    public function ajax_mark_ready() {
        if (!check_ajax_referer(self::NONCE_ACTION, 'nonce', false)) {
            wp_send_json_error(['message' => 'Solicitud invalida.'], 403);
        }

        if (!is_user_logged_in() || !self::can_manage()) {
            wp_send_json_error(['message' => 'No tenes permiso para operar almacen.'], 403);
        }

        if (!function_exists('wc_get_order') || !class_exists('RKM_Order_Audit_Log')) {
            wp_send_json_error(['message' => 'WooCommerce o auditoria no estan disponibles.'], 500);
        }

        $order_id = isset($_POST['order_id']) ? absint(wp_unslash($_POST['order_id'])) : 0;

        if ($order_id <= 0) {
            wp_send_json_error(['message' => 'Pedido invalido.'], 400);
        }

        $order = wc_get_order($order_id);

        if (!$order) {
            wp_send_json_error(['message' => 'Pedido no encontrado.'], 404);
        }

        if ($order->get_status() !== 'rkm-warehouse') {
            wp_send_json_error(['message' => 'Solo se puede marcar como preparado un pedido en almacen.'], 409);
        }

        $user = wp_get_current_user();
        $actor = $user instanceof WP_User && $user->display_name !== '' ? $user->display_name : 'Sistema';

        RKM_Order_Audit_Log::add_event(
            $order_id,
            'Pedido preparado',
            'Pedido preparado por almacen.',
            sprintf('Pedido preparado por %s.', $actor),
            'rkm-warehouse',
            'rkm-ready'
        );

        $order->update_status('rkm-ready', '');

        wp_send_json_success([
            'message' => 'Pedido marcado como preparado.',
            'order' => $this->format_order_row($order),
        ]);
    }
}
