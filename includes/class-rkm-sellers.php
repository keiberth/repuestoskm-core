<?php

if (!defined('ABSPATH')) {
    exit;
}

class RKM_Sellers {

    const SECTION_KEY = 'panel-vendedor';

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
        return 'Espacio de trabajo comercial separado del panel de cliente y preparado para crecer.';
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
                'messages' => [
                    'clients' => [
                        'title' => 'Modulo de clientes preparado',
                        'message' => 'Todavia no existe una cartera individual conectada. Esta accion queda lista para enlazar clientes asignados por vendedor sin mezclar logica administrativa.',
                    ],
                ],
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
        $page_subtitle = 'Dashboard comercial inicial con metricas base y accesos listos para conectar asignaciones reales.';
        $template = RKM_CORE_PATH . 'templates/sellers/dashboard.php';

        if (file_exists($template)) {
            include $template;
        }
    }

    private function get_dashboard_data() {
        return [
            'seller_metrics' => [
                [
                    'label' => 'Pedidos asignados',
                    'value' => $this->get_provisional_orders_count(),
                    'meta'  => 'Base global temporal hasta conectar asignacion por vendedor',
                    'tone'  => 'warning',
                ],
                [
                    'label' => 'Clientes asignados',
                    'value' => $this->get_provisional_clients_count(),
                    'meta'  => 'Clientes con actividad reciente, sin filtro individual aun',
                    'tone'  => 'primary',
                ],
                [
                    'label' => 'Pendientes de seguimiento',
                    'value' => $this->get_provisional_follow_up_count(),
                    'meta'  => 'Pedidos que requieren contacto o definicion comercial',
                    'tone'  => 'neutral',
                ],
            ],
            'seller_quick_actions' => [
                [
                    'label'       => 'Ver pedidos',
                    'description' => 'Entrar al listado actual de pedidos para seguimiento operativo.',
                    'url'         => home_url('/mi-cuenta/panel/?section=pedidos'),
                    'kind'        => 'link',
                ],
                [
                    'label'       => 'Cargar pedido',
                    'description' => 'Crear una nueva orden desde el flujo comercial existente.',
                    'url'         => home_url('/mi-cuenta/panel/?section=nueva-orden'),
                    'kind'        => 'link',
                ],
                [
                    'label'       => 'Ver clientes',
                    'description' => 'Placeholder listo para conectar cartera y seguimiento por vendedor.',
                    'url'         => '#rkm-sellers-note',
                    'kind'        => 'placeholder',
                    'action'      => 'clients',
                ],
            ],
            'seller_pipeline' => [
                [
                    'title'       => 'Base del modulo',
                    'description' => 'La vista ya separa metricas, accesos y pendientes del vendedor sin reutilizar paneles de cliente.',
                ],
                [
                    'title'       => 'Asignaciones futuras',
                    'description' => 'El siguiente paso es conectar pedidos y clientes reales por vendedor con filtros dedicados.',
                ],
                [
                    'title'       => 'Seguimiento comercial',
                    'description' => 'La estructura ya esta lista para sumar estados de contacto, recordatorios y cartera activa.',
                ],
            ],
            'seller_placeholder_notice' => [
                'title'   => 'Modulo de clientes preparado',
                'message' => 'Todavia no existe una cartera individual conectada. Esta accion queda lista para enlazar clientes asignados por vendedor sin mezclar logica administrativa.',
            ],
        ];
    }

    private function get_provisional_orders_count() {
        if (!function_exists('wc_get_orders')) {
            return 0;
        }

        $orders = wc_get_orders([
            'status'   => ['pending', 'on-hold', 'processing', 'en-revision'],
            'limit'    => 1,
            'paginate' => true,
        ]);

        return isset($orders->total) ? (int) $orders->total : 0;
    }

    private function get_provisional_clients_count() {
        if (!function_exists('wc_get_orders')) {
            return 0;
        }

        $lookback_timestamp = current_time('timestamp') - (DAY_IN_SECONDS * 90);
        $orders = wc_get_orders([
            'status' => ['pending', 'on-hold', 'processing', 'completed', 'en-revision'],
            'limit'  => -1,
        ]);
        $customers = [];

        foreach ($orders as $order) {
            $customer_id = (int) $order->get_customer_id();
            $date_created = $order->get_date_created();

            if ($customer_id <= 0 || !$date_created || $date_created->getTimestamp() < $lookback_timestamp) {
                continue;
            }

            $customers[$customer_id] = true;
        }

        return count($customers);
    }

    private function get_provisional_follow_up_count() {
        if (!function_exists('wc_get_orders')) {
            return 0;
        }

        $orders = wc_get_orders([
            'status'   => ['pending', 'on-hold', 'en-revision'],
            'limit'    => 1,
            'paginate' => true,
        ]);

        return isset($orders->total) ? (int) $orders->total : 0;
    }
}
