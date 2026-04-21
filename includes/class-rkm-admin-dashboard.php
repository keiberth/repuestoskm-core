<?php

if (!defined('ABSPATH')) {
    exit;
}

class RKM_Admin_Dashboard {

    const ACTIVE_ORDER_STATUSES = ['pending', 'on-hold', 'processing', 'en-revision'];
    const SALES_ORDER_STATUSES = ['processing', 'completed', 'on-hold'];
    const ACTIVE_CUSTOMERS_LOOKBACK_DAYS = 90;

    public static function can_access($user = null) {
        return RKM_Permissions::is_rkm_admin($user);
    }

    public function render_dashboard($data = []) {
        if (!self::can_access()) {
            wp_safe_redirect(RKM_Auth::get_redirect_url_for_user());
            exit;
        }

        $page_title = 'Panel administrativo';
        $page_subtitle = 'Base ejecutiva del negocio para monitorear ventas, operacion comercial y crecimiento.';
        $data = array_merge($data, $this->get_dashboard_data());
        $template = RKM_CORE_PATH . 'templates/admin/dashboard.php';

        if (file_exists($template)) {
            include $template;
        }
    }

    private function get_dashboard_data() {
        return [
            'admin_metrics' => [
                [
                    'label' => 'Ventas del periodo',
                    'value' => $this->get_period_sales_total(),
                    'meta'  => $this->get_current_period_label(),
                    'tone'  => 'primary',
                ],
                [
                    'label' => 'Pedidos activos',
                    'value' => $this->get_active_orders_count(),
                    'meta'  => 'Pendientes, en revision o en proceso',
                    'tone'  => 'warning',
                ],
                [
                    'label' => 'Clientes activos',
                    'value' => $this->get_active_customers_count(),
                    'meta'  => 'Actividad comercial en los ultimos 90 dias',
                    'tone'  => 'success',
                ],
                [
                    'label' => 'Vendedores activos',
                    'value' => $this->get_active_sellers_count(),
                    'meta'  => 'Usuarios con rol comercial habilitado',
                    'tone'  => 'neutral',
                ],
                [
                    'label' => 'Rentabilidad',
                    'value' => 'Placeholder',
                    'meta'  => 'Listo para conectar costos, margenes y utilidad',
                    'tone'  => 'neutral',
                ],
                [
                    'label' => 'Desempeno comercial',
                    'value' => 'Placeholder',
                    'meta'  => 'Listo para conectar ranking, conversiones y seguimiento',
                    'tone'  => 'neutral',
                ],
            ],
            'admin_quick_actions' => [
                [
                    'label'       => 'Nueva orden',
                    'description' => 'Registrar una orden manual desde el frontend comercial.',
                    'url'         => home_url('/mi-cuenta/panel/?section=nueva-orden'),
                ],
                [
                    'label'       => 'Panel vendedor',
                    'description' => 'Revisar la base operativa del modulo comercial.',
                    'url'         => class_exists('RKM_Sellers') ? RKM_Sellers::get_section_url() : home_url('/mi-cuenta/panel/'),
                ],
                [
                    'label'       => 'Mi cuenta',
                    'description' => 'Gestionar los datos personales del usuario actual.',
                    'url'         => home_url('/mi-cuenta/panel/?section=mi-cuenta'),
                ],
                [
                    'label'       => 'WordPress Admin',
                    'description' => 'Entrar al backoffice para configuracion avanzada y gestion total.',
                    'url'         => admin_url(),
                ],
            ],
            'admin_future_blocks' => [
                'Rentabilidad por linea, pedido y periodo.',
                'Desempeno de vendedores y trabajadores por cartera.',
                'Analisis de clientes con frecuencia, ticket y recurrencia.',
            ],
            'admin_operational_notes' => [
                'Las metricas ejecutivas ya no reutilizan bloques orientados a cliente.',
                'Rentabilidad y desempeno comercial quedan como placeholders preparados para datos reales.',
                'La estructura ya esta lista para sumar reportes, filtros y series temporales.',
            ],
        ];
    }

    private function get_period_sales_total() {
        if (!function_exists('wc_get_orders')) {
            return $this->format_money(0);
        }

        $period_start = strtotime(date_i18n('Y-m-01 00:00:00', current_time('timestamp')));
        $orders = wc_get_orders([
            'status' => self::SALES_ORDER_STATUSES,
            'limit'  => -1,
        ]);

        $total = 0;

        foreach ($orders as $order) {
            $date_created = $order->get_date_created();

            if (!$date_created || $date_created->getTimestamp() < $period_start) {
                continue;
            }

            $total += (float) $order->get_total();
        }

        return $this->format_money($total);
    }

    private function get_active_orders_count() {
        if (!function_exists('wc_get_orders')) {
            return 0;
        }

        $orders = wc_get_orders([
            'status'   => self::ACTIVE_ORDER_STATUSES,
            'limit'    => 1,
            'paginate' => true,
        ]);

        return isset($orders->total) ? (int) $orders->total : 0;
    }

    private function get_active_customers_count() {
        if (!function_exists('wc_get_orders')) {
            return $this->get_registered_customers_count();
        }

        $lookback_timestamp = current_time('timestamp') - (DAY_IN_SECONDS * self::ACTIVE_CUSTOMERS_LOOKBACK_DAYS);
        $orders = wc_get_orders([
            'status' => array_merge(self::ACTIVE_ORDER_STATUSES, ['completed']),
            'limit'  => -1,
        ]);
        $customer_ids = [];

        foreach ($orders as $order) {
            $customer_id = (int) $order->get_customer_id();
            $date_created = $order->get_date_created();

            if ($customer_id <= 0 || !$date_created || $date_created->getTimestamp() < $lookback_timestamp) {
                continue;
            }

            $customer_ids[$customer_id] = true;
        }

        if (!empty($customer_ids)) {
            return count($customer_ids);
        }

        return $this->get_registered_customers_count();
    }

    private function get_active_sellers_count() {
        $query = new WP_User_Query([
            'role__in' => ['seller', 'vendor', 'vendedor', 'shop_manager'],
            'fields'   => 'ID',
        ]);
        $results = $query->get_results();

        return is_array($results) ? count(array_unique($results)) : 0;
    }

    private function get_registered_customers_count() {
        $counts = count_users();

        return isset($counts['avail_roles']['customer']) ? (int) $counts['avail_roles']['customer'] : 0;
    }

    private function get_current_period_label() {
        return sprintf('Acumulado de %s', wp_date('F Y', current_time('timestamp')));
    }

    private function format_money($amount) {
        if (function_exists('wc_price')) {
            return wp_strip_all_tags(wc_price($amount));
        }

        return '$' . number_format((float) $amount, 2, ',', '.');
    }
}
