<?php
/**
 * Plugin Name: Repuestos KM Core
 * Description: Núcleo funcional para panel privado, login, dashboard y módulos personalizados de Repuestos-KM.
 * Version: 1.0.0
 * Author: Repuestos-KM
 * Text Domain: repuestoskm-core
 */

if (!defined('ABSPATH')) {
    exit;
}

define('RKM_CORE_PATH', plugin_dir_path(__FILE__));
define('RKM_CORE_URL', plugin_dir_url(__FILE__));

require_once RKM_CORE_PATH . 'includes/class-rkm-loader.php';
require_once RKM_CORE_PATH . 'includes/class-rkm-permissions.php';
require_once RKM_CORE_PATH . 'includes/class-rkm-auth.php';
require_once RKM_CORE_PATH . 'includes/class-rkm-routes.php';
require_once RKM_CORE_PATH . 'includes/class-rkm-dashboard.php';
require_once RKM_CORE_PATH . 'includes/class-rkm-orders.php';

function rkm_core_init() {
    $loader = new RKM_Loader();
    $loader->run();
}
add_action('plugins_loaded', 'rkm_core_init');

function rkm_core_activate() {
    require_once RKM_CORE_PATH . 'includes/class-rkm-routes.php';
    require_once RKM_CORE_PATH . 'includes/class-rkm-current-account.php';
    $routes = new RKM_Routes();
    $routes->register_endpoints();
    RKM_Current_Account::install_schema();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'rkm_core_activate');

function rkm_core_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'rkm_core_deactivate');

function rkm_enqueue_assets() {
    if (function_exists('is_account_page') && is_account_page()) {
        return;
    }

    wp_enqueue_style(
        'rkm-orders',
        plugin_dir_url(__FILE__) . 'assets/css/orders.css',
        [],
        '1.0'
    );
}
add_action('wp_enqueue_scripts', 'rkm_enqueue_assets');

add_filter('woocommerce_my_account_my_orders_actions', 'rkm_remove_pay_button', 10, 2);

function rkm_remove_pay_button($actions, $order) {

    if (isset($actions['pay'])) {
        unset($actions['pay']);
    }

    return $actions;
}


add_action('init', 'rkm_override_orders_endpoint');

function rkm_override_orders_endpoint() {
    remove_action('woocommerce_account_orders_endpoint', 'woocommerce_account_orders');
    add_action('woocommerce_account_orders_endpoint', 'rkm_render_custom_orders_endpoint');
}

function rkm_render_custom_orders_endpoint() {
    if (!is_user_logged_in()) {
        echo '<p>Debes iniciar sesión para ver tus pedidos.</p>';
        return;
    }

    $customer_id = get_current_user_id();

    $orders = wc_get_orders([
        'customer_id' => $customer_id,
        'limit'       => -1,
        'orderby'     => 'date',
        'order'       => 'DESC',
    ]);

    $template = plugin_dir_path(__FILE__) . 'templates/pedidos.php';

    if (file_exists($template)) {
        include $template;
    }
}

add_filter('wc_order_statuses', 'rkm_rename_pending_status_label', 20);

function rkm_rename_pending_status_label($statuses) {
    if (isset($statuses['wc-pending'])) {
        $statuses['wc-pending'] = 'En revisión';
    }

    return $statuses;
}
require_once plugin_dir_path(__FILE__) . 'includes/enqueue.php';

add_action('wp_enqueue_scripts', 'rkm_remove_woocommerce_styles', 100);

function rkm_remove_woocommerce_styles() {
    wp_dequeue_style('woocommerce-general');
    wp_dequeue_style('woocommerce-layout');
    wp_dequeue_style('woocommerce-smallscreen');

    wp_deregister_style('woocommerce-general');
    wp_deregister_style('woocommerce-layout');
    wp_deregister_style('woocommerce-smallscreen');
}
