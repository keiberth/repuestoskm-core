<?php

if (!defined('ABSPATH')) {
    exit;
}

class RKM_Sellers {

    const SECTION_KEY = 'panel-vendedor';
    const RECENT_ORDERS_LIMIT = 6;
    const RECENT_CUSTOMERS_LIMIT = 8;

    public function init() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public static function can_access($user = null) {
        return RKM_Permissions::is_rkm_vendor($user) || RKM_Permissions::is_rkm_admin($user);
    }

    public static function get_section_key() {
        return self::SECTION_KEY;
    }

    public static function get_section_url() {
        return home_url('/mi-cuenta/panel/?section=' . self::SECTION_KEY);
    }

    public static function get_page_title() {
        return 'Panel de vendedores';
    }

    public static function get_page_subtitle() {
        return 'Vista comercial inicial con pedidos, clientes y accesos rapidos del vendedor.';
    }

    public function enqueue_assets() {
        if (!is_user_logged_in()) {
            return;
        }

        if (!(function_exists('is_account_page') && is_account_page())) {
            return;
        }

        $section = isset($_GET['section']) ? sanitize_key($_GET['section']) : 'panel';

        if ($section !== self::SECTION_KEY || !self::can_access()) {
            return;
        }

        wp_enqueue_style(
            'rkm-sellers-css',
            RKM_CORE_URL . 'assets/css/sellers.css',
            ['rkm-catalogo-css'],
            '1.0.0'
        );

        wp_enqueue_script(
            'rkm-sellers-js',
            RKM_CORE_URL . 'assets/js/sellers.js',
            [],
            '1.0.0',
            true
        );

        wp_localize_script(
            'rkm-sellers-js',
            'rkmSellers',
            [
                'section' => self::SECTION_KEY,
                'can_access' => true,
            ]
        );
    }

    public function render_dashboard($data = []) {
        if (!self::can_access()) {
            wp_safe_redirect(RKM_Auth::get_redirect_url_for_user());
            exit;
        }

        $data = array_merge($data, $this->get_dashboard_data());
        $current = self::SECTION_KEY;
        $page_title = self::get_page_title();
        $page_subtitle = self::get_page_subtitle();
        $template = RKM_CORE_PATH . 'templates/sellers/dashboard.php';

        if (file_exists($template)) {
            include $template;
        }
    }

    private function get_dashboard_data() {
        return [
            'seller_metrics' => $this->get_metrics(),
            'seller_quick_actions' => $this->get_quick_actions(),
            'seller_recent_orders' => $this->get_recent_orders(),
            'seller_recent_customers' => $this->get_recent_customers(),
        ];
    }

    private function get_metrics() {
        return [
            [
                'label' => 'Total pedidos',
                'value' => $this->get_total_orders_count(),
                'meta'  => 'Conteo general del sistema',
                'tone'  => 'primary',
            ],
            [
                'label' => 'Pedidos activos',
                'value' => $this->get_active_orders_count(),
                'meta'  => 'Estados pending y processing',
                'tone'  => 'warning',
            ],
            [
                'label' => 'Total clientes',
                'value' => $this->get_total_customers_count(),
                'meta'  => 'Usuarios con rol customer',
                'tone'  => 'neutral',
            ],
        ];
    }

    private function get_recent_orders() {
        if (!function_exists('wc_get_orders')) {
            return [];
        }

        $orders = wc_get_orders([
            'limit'   => self::RECENT_ORDERS_LIMIT,
            'orderby' => 'date',
            'order'   => 'DESC',
        ]);

        if (empty($orders)) {
            return [];
        }

        return array_map([$this, 'format_order_row'], $orders);
    }

    private function format_order_row($order) {
        $customer_name = trim($order->get_formatted_billing_full_name());

        if ($customer_name === '') {
            $customer_name = $order->get_billing_email() ? $order->get_billing_email() : 'Cliente sin nombre';
        }

        $date_created = $order->get_date_created();

        return [
            'number'        => $order->get_order_number(),
            'customer_name' => $customer_name,
            'status'        => wc_get_order_status_name($order->get_status()),
            'status_slug'   => sanitize_html_class($order->get_status()),
            'total'         => wp_strip_all_tags($order->get_formatted_order_total()),
            'date'          => $date_created ? $date_created->date_i18n('d/m/Y') : 'Sin fecha',
        ];
    }

    private function get_recent_customers() {
        $users = get_users([
            'role'    => 'customer',
            'number'  => self::RECENT_CUSTOMERS_LIMIT,
            'orderby' => 'registered',
            'order'   => 'DESC',
            'fields'  => ['ID', 'display_name', 'user_email'],
        ]);

        if (empty($users)) {
            return [];
        }

        $customers = [];

        foreach ($users as $user) {
            $customers[] = [
                'name'  => $user->display_name ? $user->display_name : 'Cliente sin nombre',
                'email' => $user->user_email ? $user->user_email : 'Sin email',
            ];
        }

        return $customers;
    }

    private function get_quick_actions() {
        return [
            [
                'label'       => 'Cargar pedido',
                'description' => 'Ir directo al flujo actual de nueva orden.',
                'url'         => home_url('/mi-cuenta/panel/?section=nueva-orden'),
            ],
            [
                'label'       => 'Ver pedidos',
                'description' => 'Abrir el listado actual de pedidos del sistema.',
                'url'         => home_url('/mi-cuenta/panel/?section=pedidos'),
            ],
        ];
    }

    private function get_total_orders_count() {
        if (!function_exists('wc_get_orders')) {
            return 0;
        }

        $orders = wc_get_orders([
            'limit'    => 1,
            'paginate' => true,
        ]);

        return isset($orders->total) ? (int) $orders->total : 0;
    }

    private function get_active_orders_count() {
        if (!function_exists('wc_get_orders')) {
            return 0;
        }

        $orders = wc_get_orders([
            'status'   => ['pending', 'processing'],
            'limit'    => 1,
            'paginate' => true,
        ]);

        return isset($orders->total) ? (int) $orders->total : 0;
    }

    private function get_total_customers_count() {
        $counts = count_users();

        return isset($counts['avail_roles']['customer']) ? (int) $counts['avail_roles']['customer'] : 0;
    }
}
