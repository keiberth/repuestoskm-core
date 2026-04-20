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
            'message' => 'Debes iniciar sesión para realizar un pedido.'
        ], 403);
    }

    public function ajax_create_order() {
        if (!is_user_logged_in()) {
            wp_send_json_error([
                'message' => 'Usuario no autenticado.'
            ], 403);
        }

        check_ajax_referer('rkm_orders_nonce', 'nonce');

        $items = isset($_POST['items']) ? json_decode(stripslashes($_POST['items']), true) : [];

        if (empty($items) || !is_array($items)) {
            wp_send_json_error([
                'message' => 'No se recibieron productos válidos.'
            ], 400);
        }

        $user_id = get_current_user_id();

        if (!function_exists('wc_create_order')) {
            wp_send_json_error([
                'message' => 'WooCommerce no está disponible.'
            ], 500);
        }

        try {
            $order = wc_create_order([
                'customer_id' => $user_id,
            ]);

            $user = get_user_by('id', $user_id);

            $billing_address = [
                'first_name' => get_user_meta($user_id, 'billing_first_name', true),
                'last_name'  => get_user_meta($user_id, 'billing_last_name', true),
                'company'    => get_user_meta($user_id, 'billing_company', true),
                'email'      => $user ? $user->user_email : '',
                'phone'      => get_user_meta($user_id, 'billing_phone', true),
                'address_1'  => get_user_meta($user_id, 'billing_address_1', true),
                'address_2'  => get_user_meta($user_id, 'billing_address_2', true),
                'city'       => get_user_meta($user_id, 'billing_city', true),
                'state'      => get_user_meta($user_id, 'billing_state', true),
                'postcode'   => get_user_meta($user_id, 'billing_postcode', true),
                'country'    => get_user_meta($user_id, 'billing_country', true),
            ];

            $shipping_address = [
                'first_name' => get_user_meta($user_id, 'shipping_first_name', true),
                'last_name'  => get_user_meta($user_id, 'shipping_last_name', true),
                'company'    => get_user_meta($user_id, 'shipping_company', true),
                'address_1'  => get_user_meta($user_id, 'shipping_address_1', true),
                'address_2'  => get_user_meta($user_id, 'shipping_address_2', true),
                'city'       => get_user_meta($user_id, 'shipping_city', true),
                'state'      => get_user_meta($user_id, 'shipping_state', true),
                'postcode'   => get_user_meta($user_id, 'shipping_postcode', true),
                'country'    => get_user_meta($user_id, 'shipping_country', true),
            ];

            $order->set_address($billing_address, 'billing');
            $order->set_address($shipping_address, 'shipping');

            foreach ($items as $item) {
                $product_id = isset($item['id']) ? absint($item['id']) : 0;
                $quantity   = isset($item['quantity']) ? absint($item['quantity']) : 0;

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
                    'message' => 'No se pudieron agregar productos al pedido.'
                ], 400);
            }

            $order->calculate_totals();
            $order->update_status('pending', 'Pedido generado desde Portal del Cliente');

            wp_send_json_success([
                'message'  => 'Pedido generado correctamente.',
                'order_id' => $order->get_id(),
                'redirect' => home_url('/mi-cuenta/panel/?section=pedidos'),
            ]);

        } catch (Exception $e) {
            wp_send_json_error([
                'message' => 'Error al generar el pedido: ' . $e->getMessage()
            ], 500);
        }
    }
}