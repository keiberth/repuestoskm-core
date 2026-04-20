<?php

if (!defined('ABSPATH')) {
    exit;
}

class RKM_Account {

    public function __construct() {
        add_action('wp_ajax_rkm_save_billing_address', [$this, 'save_billing_address']);
        add_action('wp_ajax_rkm_save_shipping_address', [$this, 'save_shipping_address']);
    }

    // =========================
    // GUARDAR FACTURACIÓN
    // =========================
    public function save_billing_address() {

        check_ajax_referer('rkm_orders_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'No autorizado.']);
        }

        $user_id = get_current_user_id();

        $first_name = sanitize_text_field($_POST['billing_first_name'] ?? '');
        $last_name  = sanitize_text_field($_POST['billing_last_name'] ?? '');
        $phone      = sanitize_text_field($_POST['billing_phone'] ?? '');
        $address_1  = sanitize_text_field($_POST['billing_address_1'] ?? '');
        $city       = sanitize_text_field($_POST['billing_city'] ?? '');

        update_user_meta($user_id, 'billing_first_name', $first_name);
        update_user_meta($user_id, 'billing_last_name', $last_name);
        update_user_meta($user_id, 'billing_phone', $phone);
        update_user_meta($user_id, 'billing_address_1', $address_1);
        update_user_meta($user_id, 'billing_city', $city);

        wp_send_json_success([
            'message' => 'Dirección de facturación guardada correctamente.',
            'data' => [
                'name'    => trim($first_name . ' ' . $last_name),
                'address' => $address_1,
                'city'    => $city,
                'phone'   => $phone,
            ]
        ]);
    }

    // =========================
    // GUARDAR ENVÍO
    // =========================
    public function save_shipping_address() {

        check_ajax_referer('rkm_orders_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'No autorizado.']);
        }

        $user_id = get_current_user_id();

        $first_name = sanitize_text_field($_POST['shipping_first_name'] ?? '');
        $last_name  = sanitize_text_field($_POST['shipping_last_name'] ?? '');
        $address_1  = sanitize_text_field($_POST['shipping_address_1'] ?? '');
        $city       = sanitize_text_field($_POST['shipping_city'] ?? '');

        update_user_meta($user_id, 'shipping_first_name', $first_name);
        update_user_meta($user_id, 'shipping_last_name', $last_name);
        update_user_meta($user_id, 'shipping_address_1', $address_1);
        update_user_meta($user_id, 'shipping_city', $city);

        wp_send_json_success([
            'message' => 'Dirección de envío guardada correctamente.',
            'data' => [
                'name'    => trim($first_name . ' ' . $last_name),
                'address' => $address_1,
                'city'    => $city,
            ]
        ]);
    }
}