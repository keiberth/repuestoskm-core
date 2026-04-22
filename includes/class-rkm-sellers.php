<?php

if (!defined('ABSPATH')) {
    exit;
}

class RKM_Sellers {

    const SECTION_KEY = 'panel-vendedor';
    const RECENT_ORDERS_LIMIT = 6;
    const RECENT_CUSTOMERS_LIMIT = 8;

    private $assigned_customer_ids_cache = null;

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
        $assigned_customer_ids = $this->get_assigned_customer_ids();

        return [
            'seller_has_assigned_customers' => !empty($assigned_customer_ids),
            'seller_empty_message' => 'No tenes clientes asignados',
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
                'meta'  => 'Pedidos de clientes asignados a tu cartera',
                'tone'  => 'primary',
            ],
            [
                'label' => 'Pedidos activos',
                'value' => $this->get_active_orders_count(),
                'meta'  => 'Estados pending y processing de tu cartera',
                'tone'  => 'warning',
            ],
            [
                'label' => 'Total clientes',
                'value' => $this->get_total_customers_count(),
                'meta'  => 'Clientes asignados a tu usuario',
                'tone'  => 'neutral',
            ],
        ];
    }

    private function get_recent_orders() {
        if (!function_exists('wc_get_orders') || !$this->has_assigned_customers()) {
            return [];
        }

        $orders = wc_get_orders($this->get_customer_orders_query_args([
            'limit'   => self::RECENT_ORDERS_LIMIT,
            'orderby' => 'date',
            'order'   => 'DESC',
        ]));

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
        $assigned_customer_ids = $this->get_assigned_customer_ids();

        if (empty($assigned_customer_ids)) {
            return [];
        }

        $users = get_users([
            'include' => $assigned_customer_ids,
            'fields'  => ['ID', 'display_name', 'user_email', 'first_name', 'last_name', 'user_registered'],
        ]);

        if (empty($users)) {
            return [];
        }

        usort($users, static function ($left, $right) {
            return strcmp($right->user_registered, $left->user_registered);
        });

        $users = array_slice($users, 0, self::RECENT_CUSTOMERS_LIMIT);
        $customers = [];

        foreach ($users as $user) {
            $name = trim($user->first_name . ' ' . $user->last_name);

            $customers[] = [
                'name'  => $name !== '' ? $name : ($user->display_name ? $user->display_name : 'Cliente sin nombre'),
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
        if (!function_exists('wc_get_orders') || !$this->has_assigned_customers()) {
            return 0;
        }

        $orders = wc_get_orders($this->get_customer_orders_query_args([
            'limit'    => 1,
            'paginate' => true,
        ]));

        return isset($orders->total) ? (int) $orders->total : 0;
    }

    private function get_active_orders_count() {
        if (!function_exists('wc_get_orders') || !$this->has_assigned_customers()) {
            return 0;
        }

        $orders = wc_get_orders($this->get_customer_orders_query_args([
            'status'   => ['pending', 'processing'],
            'limit'    => 1,
            'paginate' => true,
        ]));

        return isset($orders->total) ? (int) $orders->total : 0;
    }

    private function get_total_customers_count() {
        return count($this->get_assigned_customer_ids());
    }

    private function get_assigned_customer_ids() {
        if (is_array($this->assigned_customer_ids_cache)) {
            return $this->assigned_customer_ids_cache;
        }

        if (!class_exists('RKM_Assignments')) {
            return [];
        }

        $vendor_id = get_current_user_id();

        $this->assigned_customer_ids_cache = array_values(
            array_filter(array_map('intval', RKM_Assignments::get_assigned_customer_ids($vendor_id)))
        );

        return $this->assigned_customer_ids_cache;
    }

    private function has_assigned_customers() {
        return !empty($this->get_assigned_customer_ids());
    }

    private function get_customer_orders_query_args($args = []) {
        return array_merge([
            'customer_id' => $this->get_assigned_customer_ids(),
        ], $args);
    }
}
