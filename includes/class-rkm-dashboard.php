<?php

if (!defined('ABSPATH')) {
    exit;
}

class RKM_Dashboard {

    public function init() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('woocommerce_account_panel_endpoint', [$this, 'render_dashboard']);

        add_filter('woocommerce_account_menu_items', [$this, 'remove_woocommerce_menu']);
    }

    public function remove_woocommerce_menu($items) {
        return [];
    }

    public function enqueue_assets() {
        if (!is_user_logged_in()) {
            return;
        }

        if (!(function_exists('is_account_page') && is_account_page())) {
            return;
        }

        $section = isset($_GET['section']) ? sanitize_key($_GET['section']) : 'panel';

        wp_enqueue_style(
            'rkm-base-css',
            RKM_CORE_URL . 'assets/css/base.css',
            [],
            '1.0.0'
        );

        wp_enqueue_style(
            'rkm-layout-css',
            RKM_CORE_URL . 'assets/css/layout.css',
            ['rkm-base-css'],
            '1.0.0'
        );

        wp_enqueue_style(
            'rkm-components-css',
            RKM_CORE_URL . 'assets/css/components.css',
            ['rkm-layout-css'],
            '1.0.0'
        );

        wp_enqueue_style(
            'rkm-responsive-css',
            RKM_CORE_URL . 'assets/css/responsive.css',
            ['rkm-components-css'],
            '1.0.0'
        );

        wp_enqueue_style(
            'rkm-dashboard-css',
            RKM_CORE_URL . 'assets/css/dashboard.css',
            ['rkm-responsive-css'],
            '1.0.0'
        );

        wp_enqueue_style(
            'rkm-orders-css',
            RKM_CORE_URL . 'assets/css/orders.css',
            ['rkm-dashboard-css'],
            '1.0.0'
        );

        wp_enqueue_style(
            'rkm-pedidos-css',
            RKM_CORE_URL . 'assets/css/pedidos.css',
            ['rkm-orders-css'],
            '1.0.0'
        );

        wp_enqueue_style(
            'rkm-catalogo-css',
            RKM_CORE_URL . 'assets/css/catalogo.css',
            ['rkm-pedidos-css'],
            '1.0.0'
        );

        wp_enqueue_script(
            'rkm-orders-js',
            RKM_CORE_URL . 'assets/js/orders.js',
            [],
            '1.0.0',
            true
        );

        wp_localize_script(
            'rkm-orders-js',
            'rkmOrders',
            [
                'ajax_url'   => admin_url('admin-ajax.php'),
                'nonce'      => wp_create_nonce('rkm_orders_nonce'),
                'orders_url' => wc_get_account_endpoint_url('orders'),
            ]
        );

        wp_enqueue_script(
            'rkm-pedidos-js',
            RKM_CORE_URL . 'assets/js/pedidos.js',
            [],
            '1.0.0',
            true
        );

        wp_localize_script(
            'rkm-pedidos-js',
            'rkmPedidos',
            [
                'cancelable_statuses' => class_exists('RKM_Orders_Actions')
                    ? RKM_Orders_Actions::get_cancelable_statuses()
                    : ['pending', 'on-hold', 'processing'],
            ]
        );

        wp_enqueue_script(
            'rkm-private-header-js',
            RKM_CORE_URL . 'assets/js/private-header.js',
            [],
            '1.0.0',
            true
        );

        if ($section === 'nueva-orden') {
            wp_enqueue_script(
                'rkm-catalog-filters-js',
                RKM_CORE_URL . 'assets/js/catalog-filters.js',
                [],
                '1.0.0',
                true
            );

            wp_enqueue_script(
                'rkm-product-quick-view-js',
                RKM_CORE_URL . 'assets/js/product-quick-view.js',
                [],
                '1.0.0',
                true
            );
        }
    }

    public function render_dashboard() {
        $user = wp_get_current_user();

        $data = [
            'user_name'          => $user->display_name,
            'user_role_label'    => $this->get_role_label($user),
            'pending_total'      => $this->get_pending_total($user->ID),
            'balance_favor'      => $this->get_balance_favor($user->ID),
            'last_purchase_date' => $this->get_last_purchase_date($user->ID),
            'returns_count'      => $this->get_returns_count($user->ID),
        ];

        $section = isset($_GET['section']) ? sanitize_key($_GET['section']) : 'panel';

        switch ($section) {
            case 'mi-cuenta':
                $template = RKM_CORE_PATH . 'templates/mi-cuenta.php';
                break;
                
            case 'nueva-orden':
                $template = RKM_CORE_PATH . 'templates/nueva-orden.php';
                break;

            case 'pedidos':
                $orders = function_exists('wc_get_orders') ? wc_get_orders([
                    'customer_id' => $user->ID,
                    'limit'       => -1,
                    'orderby'     => 'date',
                    'order'       => 'DESC',
                    'status'      => ['pending', 'on-hold', 'processing', 'en-revision'],
                ]) : [];
                $template = RKM_CORE_PATH . 'templates/pedidos.php';
                break;

            case 'historial':
                $orders = function_exists('wc_get_orders') ? wc_get_orders([
                    'customer_id' => $user->ID,
                    'limit'       => -1,
                    'orderby'     => 'date',
                    'order'       => 'DESC',
                    'status'      => ['completed', 'cancelled', 'refunded', 'failed'],
                ]) : [];
                $template = RKM_CORE_PATH . 'templates/historial.php';
                break;

            case 'panel':
            default:
                $template = RKM_CORE_PATH . 'templates/dashboard.php';
                break;
        }

        if (file_exists($template)) {
            include $template;
        }
    }

    private function get_role_label($user) {
        if (empty($user->roles)) {
            return 'Cliente';
        }

        $role = $user->roles[0];

        $labels = [
            'administrator' => 'Administrador',
            'customer'      => 'Cliente',
            'subscriber'    => 'Suscriptor',
            'shop_manager'  => 'Encargado de tienda',
        ];

        return $labels[$role] ?? ucfirst($role);
    }

    private function get_pending_total($user_id) {
        if (!function_exists('wc_get_orders')) {
            return '$0';
        }

        $orders = wc_get_orders([
            'customer_id' => $user_id,
            'status'      => ['pending'],
            'limit'       => -1,
        ]);

        $total = 0;

        foreach ($orders as $order) {
            $total += (float) $order->get_total();
        }

        return function_exists('wc_price') ? wc_price($total) : '$' . number_format($total, 2, ',', '.');
    }

    private function get_balance_favor($user_id) {
        $saldo = get_user_meta($user_id, 'saldo_favor', true);
        $saldo = $saldo ? (float) $saldo : 0;

        return function_exists('wc_price') ? wc_price($saldo) : '$' . number_format($saldo, 2, ',', '.');
    }

    private function get_last_purchase_date($user_id) {
        if (!function_exists('wc_get_orders')) {
            return 'Sin compras';
        }

        $orders = wc_get_orders([
            'customer_id' => $user_id,
            'limit'       => 1,
            'orderby'     => 'date',
            'order'       => 'DESC',
            'status'      => ['completed', 'processing', 'on-hold'],
        ]);

        if (empty($orders)) {
            return 'Sin compras';
        }

        $order = $orders[0];
        $date = $order->get_date_created();

        return $date ? $date->date_i18n('d/m/Y') : 'Sin fecha';
    }

    private function get_returns_count($user_id) {
        if (!function_exists('wc_get_orders')) {
            return 0;
        }

        $orders = wc_get_orders([
            'customer_id' => $user_id,
            'limit'       => -1,
        ]);

        $returns = 0;

        foreach ($orders as $order) {
            if ((float) $order->get_total_refunded() > 0) {
                $returns++;
            }
        }

        return $returns;
    }

}
