<?php

if (!defined('ABSPATH')) {
    exit;
}

class RKM_Current_Account {

    const CUSTOMER_SECTION_KEY = 'cuenta-corriente';
    const ADMIN_SECTION_KEY = 'pagos-clientes';
    const POST_TYPE = 'rkm_payment_report';
    const NOTICE_TRANSIENT_PREFIX = 'rkm_current_account_notice_';
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    public function init() {
        add_action('init', [$this, 'register_post_type']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('template_redirect', [$this, 'handle_submission'], 5);
    }

    public static function can_admin_access($user = null) {
        return class_exists('RKM_Permissions') && RKM_Permissions::is_rkm_admin($user);
    }

    public static function can_customer_access($user = null) {
        $user = $user instanceof WP_User ? $user : wp_get_current_user();

        if (!$user || empty($user->ID)) {
            return false;
        }

        if (class_exists('RKM_Permissions')) {
            if (RKM_Permissions::is_rkm_vendor($user) || RKM_Permissions::is_rkm_admin($user)) {
                return false;
            }

            return RKM_Permissions::is_rkm_customer($user);
        }

        return true;
    }

    public static function get_customer_section_url() {
        return home_url('/mi-cuenta/panel/?section=' . self::CUSTOMER_SECTION_KEY);
    }

    public static function get_admin_section_url() {
        return home_url('/mi-cuenta/panel/?section=' . self::ADMIN_SECTION_KEY);
    }

    public static function get_status_labels() {
        return [
            self::STATUS_PENDING  => 'Pendiente',
            self::STATUS_APPROVED => 'Aprobado',
            self::STATUS_REJECTED => 'Rechazado',
        ];
    }

    public function register_post_type() {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name'          => 'Pagos informados',
                'singular_name' => 'Pago informado',
            ],
            'public'       => false,
            'show_ui'      => false,
            'show_in_menu' => false,
            'supports'     => ['title'],
            'capability_type' => 'post',
        ]);
    }

    public function enqueue_assets() {
        if (!$this->is_module_section()) {
            return;
        }

        wp_enqueue_style(
            'rkm-current-account-css',
            RKM_CORE_URL . 'assets/css/current-account.css',
            ['rkm-dashboard-css'],
            '1.0.0'
        );

        wp_enqueue_script(
            'rkm-current-account-js',
            RKM_CORE_URL . 'assets/js/current-account.js',
            [],
            '1.0.0',
            true
        );
    }

    public function render_customer_page($data = []) {
        if (!self::can_customer_access()) {
            wp_safe_redirect(class_exists('RKM_Auth') ? RKM_Auth::get_redirect_url_for_user() : home_url('/mi-cuenta/panel/'));
            exit;
        }

        $customer_id = get_current_user_id();
        $pending_orders = $this->get_customer_pending_orders($customer_id);
        $view_data = array_merge($data, [
            'page_title' => 'Cuenta corriente',
            'page_subtitle' => 'Consulta tus saldos pendientes e informa pagos realizados.',
            'current_section' => self::CUSTOMER_SECTION_KEY,
            'pending_orders' => $pending_orders,
            'pending_total' => $this->sum_order_balances($pending_orders),
            'payment_reports' => $this->get_customer_payment_reports($customer_id),
            'payment_methods' => $this->get_report_payment_methods(),
            'current_account_notice' => $this->consume_flash_notice(),
            'section_url' => self::get_customer_section_url(),
            'status_labels' => self::get_status_labels(),
        ]);

        $template = RKM_CORE_PATH . 'templates/current-account/index.php';

        if (file_exists($template)) {
            $data = $view_data;
            include $template;
        }
    }

    public function render_admin_page($data = []) {
        if (!self::can_admin_access()) {
            wp_safe_redirect(class_exists('RKM_Auth') ? RKM_Auth::get_redirect_url_for_user() : home_url('/mi-cuenta/panel/'));
            exit;
        }

        $view_data = array_merge($data, [
            'page_title' => 'Pagos clientes',
            'page_subtitle' => 'Revisa, aprueba o rechaza los pagos informados por clientes.',
            'current_section' => self::ADMIN_SECTION_KEY,
            'payment_reports' => $this->get_admin_payment_reports(),
            'current_account_notice' => $this->consume_flash_notice(),
            'section_url' => self::get_admin_section_url(),
            'status_labels' => self::get_status_labels(),
        ]);

        $template = RKM_CORE_PATH . 'templates/admin/payments-review.php';

        if (file_exists($template)) {
            $data = $view_data;
            include $template;
        }
    }

    public function handle_submission() {
        if (!$this->is_module_section() || !$this->is_post_request()) {
            return;
        }

        $section = $this->get_current_section();

        if ($section === self::CUSTOMER_SECTION_KEY) {
            $this->handle_customer_submission();
        }

        if ($section === self::ADMIN_SECTION_KEY) {
            $this->handle_admin_submission();
        }
    }

    private function handle_customer_submission() {
        if (!self::can_customer_access()) {
            $this->set_flash_notice('error', 'No tenes permiso para informar pagos.');
            $this->redirect_to(self::get_customer_section_url());
        }

        if (!$this->verify_nonce('rkm_current_account_report', 'rkm_current_account_nonce')) {
            $this->set_flash_notice('error', 'La solicitud no es valida. Recarga la pagina e intenta nuevamente.');
            $this->redirect_to(self::get_customer_section_url());
        }

        $action = isset($_POST['rkm_current_account_action']) ? sanitize_key(wp_unslash($_POST['rkm_current_account_action'])) : '';

        if ($action !== 'report_payment') {
            $this->set_flash_notice('error', 'Accion no reconocida.');
            $this->redirect_to(self::get_customer_section_url());
        }

        $customer_id = get_current_user_id();
        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        $amount = $this->parse_amount($_POST['amount'] ?? '');
        $payment_method_id = isset($_POST['payment_method_id']) ? sanitize_key(wp_unslash($_POST['payment_method_id'])) : '';
        $reference = isset($_POST['reference']) ? sanitize_text_field(wp_unslash($_POST['reference'])) : '';
        $note = isset($_POST['note']) ? sanitize_textarea_field(wp_unslash($_POST['note'])) : '';

        $order = $order_id > 0 && function_exists('wc_get_order') ? wc_get_order($order_id) : null;

        if (!$order || (int) $order->get_customer_id() !== $customer_id) {
            $this->set_flash_notice('error', 'El pedido seleccionado no corresponde a tu cuenta.');
            $this->redirect_to(self::get_customer_section_url());
        }

        $balance = $this->get_order_credit_balance($order);

        if ($balance <= 0) {
            $this->set_flash_notice('error', 'El pedido seleccionado no tiene saldo pendiente.');
            $this->redirect_to(self::get_customer_section_url());
        }

        if ($amount <= 0) {
            $this->set_flash_notice('error', 'El monto informado debe ser mayor a cero.');
            $this->redirect_to(self::get_customer_section_url());
        }

        if ($amount > $balance) {
            $this->set_flash_notice('error', 'El monto informado no puede superar el saldo pendiente del pedido.');
            $this->redirect_to(self::get_customer_section_url());
        }

        $method = $this->get_payment_method_for_report($payment_method_id);

        if (!$method) {
            $this->set_flash_notice('error', 'Selecciona una forma de pago valida.');
            $this->redirect_to(self::get_customer_section_url());
        }

        $report_id = wp_insert_post([
            'post_type'   => self::POST_TYPE,
            'post_status' => 'publish',
            'post_author' => $customer_id,
            'post_title'  => sprintf('Pago informado #%d - Pedido #%s', $order_id, $order->get_order_number()),
        ], true);

        if (is_wp_error($report_id)) {
            $this->set_flash_notice('error', 'No se pudo registrar el pago informado. Intenta nuevamente.');
            $this->redirect_to(self::get_customer_section_url());
        }

        $meta = [
            'customer_id'          => $customer_id,
            'order_id'             => $order_id,
            'amount'               => $this->round_money($amount),
            'payment_method_id'    => $method['id'],
            'payment_method_label' => $method['name'],
            'reference'            => $reference,
            'note'                 => $note,
            'status'               => self::STATUS_PENDING,
            'created_at'           => current_time('mysql'),
            'reviewed_by'          => 0,
            'reviewed_at'          => '',
        ];

        foreach ($meta as $key => $value) {
            update_post_meta($report_id, $key, $value);
        }

        $this->set_flash_notice('success', 'Pago informado correctamente. Queda pendiente de validacion administrativa.');
        $this->redirect_to(self::get_customer_section_url());
    }

    private function handle_admin_submission() {
        if (!self::can_admin_access()) {
            $this->set_flash_notice('error', 'No tenes permiso para revisar pagos.');
            $this->redirect_to(self::get_admin_section_url());
        }

        if (!$this->verify_nonce('rkm_current_account_review', 'rkm_current_account_nonce')) {
            $this->set_flash_notice('error', 'La solicitud no es valida. Recarga la pagina e intenta nuevamente.');
            $this->redirect_to(self::get_admin_section_url());
        }

        $action = isset($_POST['rkm_current_account_action']) ? sanitize_key(wp_unslash($_POST['rkm_current_account_action'])) : '';
        $report_id = isset($_POST['report_id']) ? absint($_POST['report_id']) : 0;

        if ($action === 'approve_payment') {
            $this->approve_payment_report($report_id);
        }

        if ($action === 'reject_payment') {
            $this->reject_payment_report($report_id);
        }

        $this->set_flash_notice('error', 'Accion no reconocida.');
        $this->redirect_to(self::get_admin_section_url());
    }

    private function approve_payment_report($report_id) {
        $report = $this->get_payment_report($report_id);

        if (!$report) {
            $this->set_flash_notice('error', 'No se encontro el pago informado.');
            $this->redirect_to(self::get_admin_section_url());
        }

        if ($report['status'] !== self::STATUS_PENDING) {
            $this->set_flash_notice('error', 'Este pago ya fue revisado y no puede aprobarse nuevamente.');
            $this->redirect_to(self::get_admin_section_url());
        }

        $order = function_exists('wc_get_order') ? wc_get_order($report['order_id']) : null;

        if (!$order) {
            $this->set_flash_notice('error', 'No se encontro el pedido relacionado.');
            $this->redirect_to(self::get_admin_section_url());
        }

        $current_balance = $this->get_order_credit_balance($order);
        $amount = $this->round_money($report['amount']);

        if ($current_balance <= 0) {
            $this->set_flash_notice('error', 'El pedido ya no tiene saldo pendiente.');
            $this->redirect_to(self::get_admin_section_url());
        }

        if ($amount <= 0 || $amount > $current_balance) {
            $this->set_flash_notice('error', 'El monto informado ya no es compatible con el saldo pendiente actual.');
            $this->redirect_to(self::get_admin_section_url());
        }

        $new_balance = $this->round_money(max(0, $current_balance - $amount));
        $order->update_meta_data('_rkm_credit_balance', $new_balance);
        $order->add_order_note(sprintf(
            "Pago informado aprobado.\nMonto: %s.\nForma de pago: %s.\nReferencia: %s.\nSaldo anterior: %s.\nSaldo actualizado: %s.",
            wp_strip_all_tags($this->format_money($amount)),
            $report['payment_method_label'],
            $report['reference'] !== '' ? $report['reference'] : 'Sin referencia',
            wp_strip_all_tags($this->format_money($current_balance)),
            wp_strip_all_tags($this->format_money($new_balance))
        ));
        $order->save();

        $this->mark_report_reviewed($report_id, self::STATUS_APPROVED);
        $this->set_flash_notice('success', 'Pago aprobado y saldo pendiente actualizado.');
        $this->redirect_to(self::get_admin_section_url());
    }

    private function reject_payment_report($report_id) {
        $report = $this->get_payment_report($report_id);

        if (!$report) {
            $this->set_flash_notice('error', 'No se encontro el pago informado.');
            $this->redirect_to(self::get_admin_section_url());
        }

        if ($report['status'] !== self::STATUS_PENDING) {
            $this->set_flash_notice('error', 'Este pago ya fue revisado.');
            $this->redirect_to(self::get_admin_section_url());
        }

        $order = function_exists('wc_get_order') ? wc_get_order($report['order_id']) : null;

        if ($order) {
            $order->add_order_note(sprintf(
                "Pago informado rechazado.\nMonto: %s.\nForma de pago: %s.\nReferencia: %s.",
                wp_strip_all_tags($this->format_money($report['amount'])),
                $report['payment_method_label'],
                $report['reference'] !== '' ? $report['reference'] : 'Sin referencia'
            ));
            $order->save();
        }

        $this->mark_report_reviewed($report_id, self::STATUS_REJECTED);
        $this->set_flash_notice('success', 'Pago rechazado. El saldo del pedido no fue modificado.');
        $this->redirect_to(self::get_admin_section_url());
    }

    private function mark_report_reviewed($report_id, $status) {
        update_post_meta($report_id, 'status', $status);
        update_post_meta($report_id, 'reviewed_by', get_current_user_id());
        update_post_meta($report_id, 'reviewed_at', current_time('mysql'));
    }

    public function get_customer_pending_orders($customer_id) {
        if (!function_exists('wc_get_orders')) {
            return [];
        }

        $query_args = [
            'customer_id' => $customer_id,
            'limit'       => -1,
            'orderby'     => 'date',
            'order'       => 'DESC',
        ];

        if (function_exists('wc_get_order_statuses')) {
            $query_args['status'] = array_keys(wc_get_order_statuses());
        }

        $orders = wc_get_orders($query_args);

        return array_values(array_filter($orders, function ($order) {
            return $this->get_order_credit_balance($order) > 0;
        }));
    }

    public function get_customer_payment_reports($customer_id) {
        return $this->get_payment_reports([
            [
                'key'     => 'customer_id',
                'value'   => (string) $customer_id,
                'compare' => '=',
            ],
        ]);
    }

    public function get_admin_payment_reports() {
        return $this->get_payment_reports([]);
    }

    private function get_payment_reports($meta_query) {
        $query_args = [
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => 100,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        if (!empty($meta_query)) {
            $query_args['meta_query'] = $meta_query;
        }

        $posts = get_posts($query_args);
        $reports = [];

        foreach ($posts as $post) {
            $report = $this->get_payment_report($post->ID);

            if ($report) {
                $reports[] = $report;
            }
        }

        return $reports;
    }

    private function get_payment_report($report_id) {
        $post = get_post($report_id);

        if (!$post || $post->post_type !== self::POST_TYPE) {
            return null;
        }

        $customer_id = (int) get_post_meta($report_id, 'customer_id', true);
        $order_id = (int) get_post_meta($report_id, 'order_id', true);
        $customer = $customer_id > 0 ? get_user_by('id', $customer_id) : null;
        $order = function_exists('wc_get_order') && $order_id > 0 ? wc_get_order($order_id) : null;

        return [
            'id'                   => (int) $report_id,
            'customer_id'          => $customer_id,
            'customer_name'        => $customer instanceof WP_User ? ($customer->display_name ?: $customer->user_login) : 'Cliente eliminado',
            'order_id'             => $order_id,
            'order_number'         => $order ? $order->get_order_number() : (string) $order_id,
            'order_balance'        => $order ? $this->get_order_credit_balance($order) : 0,
            'amount'               => (float) get_post_meta($report_id, 'amount', true),
            'payment_method_id'    => (string) get_post_meta($report_id, 'payment_method_id', true),
            'payment_method_label' => (string) get_post_meta($report_id, 'payment_method_label', true),
            'reference'            => (string) get_post_meta($report_id, 'reference', true),
            'note'                 => (string) get_post_meta($report_id, 'note', true),
            'status'               => (string) get_post_meta($report_id, 'status', true),
            'created_at'           => (string) get_post_meta($report_id, 'created_at', true),
            'reviewed_by'          => (int) get_post_meta($report_id, 'reviewed_by', true),
            'reviewed_at'          => (string) get_post_meta($report_id, 'reviewed_at', true),
        ];
    }

    public function get_order_credit_balance($order) {
        if (!$order || !is_callable([$order, 'get_meta'])) {
            return 0;
        }

        return $this->round_money((float) $order->get_meta('_rkm_credit_balance', true));
    }

    public function sum_order_balances($orders) {
        $total = 0;

        foreach ($orders as $order) {
            $total += $this->get_order_credit_balance($order);
        }

        return $this->round_money($total);
    }

    public function format_money($amount) {
        if (function_exists('wc_price')) {
            return wc_price($amount);
        }

        return '$' . number_format((float) $amount, 2, ',', '.');
    }

    public function format_date($mysql_date) {
        if ($mysql_date === '') {
            return '-';
        }

        $timestamp = strtotime($mysql_date);

        if (!$timestamp) {
            return '-';
        }

        return wp_date('d/m/Y H:i', $timestamp);
    }

    private function get_report_payment_methods() {
        if (class_exists('RKM_Payment_Methods')) {
            return RKM_Payment_Methods::get_active_methods();
        }

        return [];
    }

    private function get_payment_method_for_report($method_id) {
        if (class_exists('RKM_Payment_Methods')) {
            return RKM_Payment_Methods::get_active_method($method_id);
        }

        return null;
    }

    private function parse_amount($value) {
        $value = is_string($value) ? wp_unslash($value) : $value;

        if (function_exists('wc_format_decimal')) {
            return $this->round_money((float) wc_format_decimal($value));
        }

        $value = str_replace(',', '.', (string) $value);
        $value = preg_replace('/[^0-9\.]/', '', $value);

        return $this->round_money((float) $value);
    }

    private function round_money($amount) {
        $decimals = function_exists('wc_get_price_decimals') ? wc_get_price_decimals() : 2;

        return round((float) $amount, $decimals);
    }

    private function verify_nonce($action, $field) {
        return isset($_POST[$field]) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[$field])), $action);
    }

    private function is_module_section() {
        if (!is_user_logged_in()) {
            return false;
        }

        if (!(function_exists('is_account_page') && is_account_page())) {
            return false;
        }

        return in_array($this->get_current_section(), [self::CUSTOMER_SECTION_KEY, self::ADMIN_SECTION_KEY], true);
    }

    private function get_current_section() {
        return isset($_GET['section']) ? sanitize_key(wp_unslash($_GET['section'])) : 'panel';
    }

    private function is_post_request() {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }

    private function get_notice_transient_key() {
        return self::NOTICE_TRANSIENT_PREFIX . get_current_user_id();
    }

    private function set_flash_notice($type, $message) {
        set_transient($this->get_notice_transient_key(), [
            'type'    => $type,
            'message' => $message,
        ], MINUTE_IN_SECONDS * 5);
    }

    private function consume_flash_notice() {
        $notice = get_transient($this->get_notice_transient_key());

        if ($notice) {
            delete_transient($this->get_notice_transient_key());
        }

        return is_array($notice) ? $notice : null;
    }

    private function redirect_to($url) {
        wp_safe_redirect($url);
        exit;
    }
}
