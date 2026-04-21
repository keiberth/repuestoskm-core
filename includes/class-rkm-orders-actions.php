<?php

if (!defined('ABSPATH')) {
    exit;
}

class RKM_Orders_Actions {

    public static function get_cancelable_statuses() {
        return ['pending', 'on-hold', 'processing'];
    }

    public function init() {
        add_action('wp_ajax_rkm_cancel_order', [$this, 'cancel_order']);
    }

    public function cancel_order() {
        check_ajax_referer('rkm_orders_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'No autorizado.']);
        }

        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;

        if (!$order_id) {
            wp_send_json_error(['message' => 'Pedido inválido.']);
        }

        $order = wc_get_order($order_id);

        if (!$order) {
            wp_send_json_error(['message' => 'Pedido no encontrado.']);
        }

        if ((int) $order->get_user_id() !== get_current_user_id()) {
            wp_send_json_error(['message' => 'No podés cancelar este pedido.']);
        }

        $allowed_statuses = self::get_cancelable_statuses();

        if (!in_array($order->get_status(), $allowed_statuses, true)) {
            wp_send_json_error(['message' => 'Este pedido ya no puede cancelarse.']);
        }

        $order->update_status('cancelled', 'Pedido cancelado por el cliente desde su panel.');

        wp_send_json_success([
            'message' => 'Tu pedido fue cancelado correctamente.'
        ]);
    }
}
