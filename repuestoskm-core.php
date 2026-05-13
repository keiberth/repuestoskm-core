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

if (!defined('RKM_CORE_VERSION')) {
    define('RKM_CORE_VERSION', '1.0.0');
}

if (!defined('RKM_CORE_PATH')) {
    define('RKM_CORE_PATH', plugin_dir_path(__FILE__));
}

if (!defined('RKM_CORE_URL')) {
    define('RKM_CORE_URL', plugin_dir_url(__FILE__));
}

if (!function_exists('rkm_core_asset_version')) {
    function rkm_core_asset_version($relative_path) {
        $file = RKM_CORE_PATH . ltrim($relative_path, '/');

        if (file_exists($file)) {
            return filemtime($file);
        }

        return RKM_CORE_VERSION;
    }
}

if (!function_exists('rkm_parse_localized_decimal')) {
    function rkm_parse_localized_decimal($value) {
        if (is_numeric($value)) {
            return (float) $value;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return 0;
        }

        $value = preg_replace('/[^0-9\.,]/', '', $value);

        if (strpos($value, ',') !== false && strpos($value, '.') !== false) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif (strpos($value, ',') !== false) {
            $value = str_replace(',', '.', $value);
        }

        return is_numeric($value) ? (float) $value : 0;
    }
}

if (!function_exists('rkm_get_bcv_rate')) {
    function rkm_get_bcv_rate($rate_data = null) {
        if (!is_array($rate_data) && class_exists('RKM_Dashboard')) {
            $rate_data = (new RKM_Dashboard())->get_bcv_usd_rate();
        }

        if (!is_array($rate_data) || empty($rate_data['value'])) {
            return 0;
        }

        return rkm_parse_localized_decimal($rate_data['value']);
    }
}

if (!function_exists('rkm_format_bolivares')) {
    function rkm_format_bolivares($amount) {
        return 'Bs. ' . number_format((float) $amount, 2, ',', '.');
    }
}

if (!function_exists('rkm_get_product_bcv_price')) {
    function rkm_get_product_bcv_price($product, $rate_data = null) {
        if (!$product || !is_object($product) || !method_exists($product, 'get_price')) {
            return '';
        }

        $usd_price = (float) $product->get_price();
        $bcv_rate = rkm_get_bcv_rate($rate_data);

        if ($usd_price <= 0 || $bcv_rate <= 0) {
            return '';
        }

        return rkm_format_bolivares($usd_price * $bcv_rate);
    }
}

if (!function_exists('rkm_get_panel_url')) {
    function rkm_get_panel_url($args = []) {
        $base_url = function_exists('wc_get_account_endpoint_url')
            ? wc_get_account_endpoint_url('panel')
            : home_url('/mi-cuenta/panel/');

        $args = is_array($args) ? array_filter($args, static function ($value) {
            return $value !== null && $value !== '';
        }) : [];

        return !empty($args) ? add_query_arg($args, $base_url) : $base_url;
    }
}

require_once RKM_CORE_PATH . 'includes/class-rkm-loader.php';
require_once RKM_CORE_PATH . 'includes/class-rkm-permissions.php';
require_once RKM_CORE_PATH . 'includes/class-rkm-auth.php';
require_once RKM_CORE_PATH . 'includes/class-rkm-routes.php';
require_once RKM_CORE_PATH . 'includes/class-rkm-dashboard.php';
require_once RKM_CORE_PATH . 'includes/class-rkm-order-audit-log.php';
require_once RKM_CORE_PATH . 'includes/class-rkm-orders.php';

function rkm_core_init() {
    $loader = new RKM_Loader();
    $loader->run();
}
add_action('plugins_loaded', 'rkm_core_init');

function rkm_core_activate() {
    require_once RKM_CORE_PATH . 'includes/class-rkm-routes.php';
    require_once RKM_CORE_PATH . 'includes/class-rkm-current-account-transactions.php';
    require_once RKM_CORE_PATH . 'includes/class-rkm-current-account.php';
    require_once RKM_CORE_PATH . 'includes/class-rkm-order-audit-log.php';
    $routes = new RKM_Routes();
    $routes->register_endpoints();
    RKM_Current_Account_Transactions::install_schema();
    RKM_Current_Account::install_schema();
    RKM_Order_Audit_Log::install_schema();
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
        RKM_CORE_URL . 'assets/css/orders.css',
        [],
        rkm_core_asset_version('assets/css/orders.css')
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
