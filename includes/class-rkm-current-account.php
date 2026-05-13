<?php

if (!defined('ABSPATH')) {
    exit;
}

class RKM_Current_Account {

    const CUSTOMER_SECTION_KEY = 'cuenta-corriente';
    const ADMIN_SECTION_KEY = 'pagos-clientes';
    const NOTICE_TRANSIENT_PREFIX = 'rkm_current_account_notice_';
    const TABLE_SCHEMA_VERSION = '1.0.0';
    const TABLE_SCHEMA_OPTION = 'rkm_payment_reports_schema_version';
    const MIGRATION_OPTION = 'rkm_payment_reports_migrated';
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const ACCOUNT_STATUS_PENDING = 'pending';
    const ACCOUNT_STATUS_OVERDUE = 'overdue';
    const ACCOUNT_STATUS_PAID = 'paid';
    const RECEIPT_MAX_BYTES = 5242880;
    const RECEIPT_FIELD = 'receipt';

    public function init() {
        add_action('init', [$this, 'maybe_install_schema']);
        add_action('init', [$this, 'maybe_migrate_cpt_payment_reports'], 20);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('template_redirect', [$this, 'handle_submission'], 5);
        add_action('wp_ajax_rkm_current_account_receipt', [$this, 'serve_receipt']);
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
            if (RKM_Permissions::is_rkm_admin($user)) {
                return false;
            }

            return RKM_Permissions::is_rkm_customer($user) || RKM_Permissions::is_rkm_vendor($user);
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
            'voided'              => 'Anulado',
        ];
    }

    public static function get_table_name() {
        global $wpdb;

        return $wpdb->prefix . 'rkm_payment_reports';
    }

    public static function install_schema() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table_name = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            customer_id BIGINT UNSIGNED NOT NULL,
            order_id BIGINT UNSIGNED NOT NULL,
            reported_by BIGINT UNSIGNED NOT NULL,
            reported_by_role VARCHAR(40) NOT NULL,
            amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
            payment_date DATE NOT NULL,
            payment_method_id VARCHAR(120) NOT NULL,
            payment_method_label VARCHAR(190) NOT NULL,
            reference VARCHAR(190) NOT NULL DEFAULT '',
            note TEXT NULL,
            receipt_attachment_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            status VARCHAR(40) NOT NULL DEFAULT 'pending',
            reviewed_by BIGINT UNSIGNED NULL,
            reviewed_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY customer_id (customer_id),
            KEY order_id (order_id),
            KEY reported_by (reported_by),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset_collate};";

        dbDelta($sql);
        update_option(self::TABLE_SCHEMA_OPTION, self::TABLE_SCHEMA_VERSION, false);
    }

    public function maybe_install_schema() {
        if (get_option(self::TABLE_SCHEMA_OPTION) !== self::TABLE_SCHEMA_VERSION) {
            self::install_schema();
        }
    }

    public function maybe_migrate_cpt_payment_reports() {
        if (get_option(self::MIGRATION_OPTION) === '1') {
            return;
        }

        self::install_schema();
        $result = $this->migrate_cpt_payment_reports();
        update_option(self::MIGRATION_OPTION, '1', false);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[RKM Current Account] Migracion CPT ejecutada. Migrados: %d. Omitidos: %d. Errores: %d.',
                $result['migrated'],
                $result['skipped'],
                $result['failed']
            ));
        }
    }

    public function enqueue_assets() {
        if (!$this->is_module_section()) {
            return;
        }

        wp_enqueue_style(
            'rkm-current-account-css',
            RKM_CORE_URL . 'assets/css/current-account.css',
            ['rkm-dashboard-css'],
            rkm_core_asset_version('assets/css/current-account.css')
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

        $actor = wp_get_current_user();
        $pending_orders = $this->get_actor_pending_orders($actor);
        $account_summary = $this->get_orders_account_summary($pending_orders);
        $view_data = array_merge($data, [
            'page_title' => 'Cuenta corriente',
            'page_subtitle' => 'Tenes 20 dias de credito desde la entrega del pedido.',
            'current_section' => self::CUSTOMER_SECTION_KEY,
            'pending_orders' => $pending_orders,
            'pending_total' => $account_summary['pending_total'],
            'current_account_summary' => $account_summary,
            'payment_reports' => $this->get_actor_payment_reports($actor),
            'payment_methods' => $this->get_report_payment_methods(),
            'current_account_notice' => $this->consume_flash_notice(),
            'section_url' => self::get_customer_section_url(),
            'status_labels' => self::get_status_labels(),
            'is_vendor_context' => $this->is_vendor($actor),
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

        $actor = wp_get_current_user();
        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        $amount = $this->parse_amount($_POST['amount'] ?? '');
        $payment_date = isset($_POST['payment_date']) ? sanitize_text_field(wp_unslash($_POST['payment_date'])) : '';
        $payment_method_id = isset($_POST['payment_method_id']) ? sanitize_key(wp_unslash($_POST['payment_method_id'])) : '';
        $reference = isset($_POST['reference']) ? sanitize_text_field(wp_unslash($_POST['reference'])) : '';
        $note = isset($_POST['note']) ? sanitize_textarea_field(wp_unslash($_POST['note'])) : '';

        $order = $order_id > 0 && function_exists('wc_get_order') ? wc_get_order($order_id) : null;

        if (!$order || !$this->can_actor_report_order($actor, $order)) {
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

        if (!$this->is_valid_payment_date($payment_date)) {
            $this->set_flash_notice('error', 'Indica una fecha de pago valida.');
            $this->redirect_to(self::get_customer_section_url());
        }

        $receipt_validation = $this->validate_receipt_upload(self::RECEIPT_FIELD);

        if (is_wp_error($receipt_validation)) {
            $this->set_flash_notice('error', $receipt_validation->get_error_message());
            $this->redirect_to(self::get_customer_section_url());
        }

        $receipt_attachment_id = $this->handle_receipt_upload(self::RECEIPT_FIELD);

        if (is_wp_error($receipt_attachment_id)) {
            $this->set_flash_notice('error', $receipt_attachment_id->get_error_message());
            $this->redirect_to(self::get_customer_section_url());
        }

        if (!class_exists('RKM_Current_Account_Transactions')) {
            wp_delete_attachment((int) $receipt_attachment_id, true);
            $this->set_flash_notice('error', 'El registro formal de cobranza no esta disponible.');
            $this->redirect_to(self::get_customer_section_url());
        }

        $customer_id = (int) $order->get_customer_id();
        $transaction_note = $note;
        if ($payment_date !== '') {
            $transaction_note = trim(sprintf(
                "Fecha de pago informada: %s\n%s",
                $payment_date,
                $transaction_note
            ));
        }

        $transaction_id = RKM_Current_Account_Transactions::add_transaction([
            'customer_id'          => $customer_id,
            'order_id'             => $order_id,
            'type'                 => RKM_Current_Account_Transactions::TYPE_PAYMENT,
            'amount'               => $this->round_money($amount),
            'status'               => RKM_Current_Account_Transactions::STATUS_PENDING,
            'method_id'            => is_numeric($method['id']) ? absint($method['id']) : null,
            'method_label'         => $method['name'],
            'reference'            => $reference,
            'note'                 => $transaction_note,
            'receipt_attachment_id' => (int) $receipt_attachment_id,
            'created_by'           => (int) $actor->ID,
        ]);

        if (is_wp_error($transaction_id)) {
            wp_delete_attachment((int) $receipt_attachment_id, true);
            $this->set_flash_notice('error', $transaction_id->get_error_message());
            $this->redirect_to(self::get_customer_section_url());
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

    public function approve_payment_report($report_id) {
        if (!class_exists('RKM_Current_Account_Transactions')) {
            $this->set_flash_notice('error', 'El registro formal de cobranza no esta disponible.');
            $this->redirect_to(self::get_admin_section_url());
        }

        $report = RKM_Current_Account_Transactions::get_transaction($report_id);

        if (!$report) {
            $this->set_flash_notice('error', 'No se encontro el pago informado.');
            $this->redirect_to(self::get_admin_section_url());
        }

        if ($report['status'] !== RKM_Current_Account_Transactions::STATUS_PENDING) {
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

        $result = RKM_Current_Account_Transactions::approve_transaction($report_id);

        if (is_wp_error($result)) {
            $this->set_flash_notice('error', $result->get_error_message());
            $this->redirect_to(self::get_admin_section_url());
        }

        $this->set_flash_notice('success', 'Pago aprobado y saldo pendiente actualizado.');
        $this->redirect_to(self::get_admin_section_url());
    }

    public function reject_payment_report($report_id) {
        if (!class_exists('RKM_Current_Account_Transactions')) {
            $this->set_flash_notice('error', 'El registro formal de cobranza no esta disponible.');
            $this->redirect_to(self::get_admin_section_url());
        }

        $report = RKM_Current_Account_Transactions::get_transaction($report_id);

        if (!$report) {
            $this->set_flash_notice('error', 'No se encontro el pago informado.');
            $this->redirect_to(self::get_admin_section_url());
        }

        if ($report['status'] !== RKM_Current_Account_Transactions::STATUS_PENDING) {
            $this->set_flash_notice('error', 'Este pago ya fue revisado.');
            $this->redirect_to(self::get_admin_section_url());
        }

        $result = RKM_Current_Account_Transactions::reject_transaction($report_id);

        if (is_wp_error($result)) {
            $this->set_flash_notice('error', $result->get_error_message());
            $this->redirect_to(self::get_admin_section_url());
        }

        $this->set_flash_notice('success', 'Pago rechazado. El saldo del pedido no fue modificado.');
        $this->redirect_to(self::get_admin_section_url());
    }

    private function transition_pending_report($report_id, $status) {
        global $wpdb;

        $updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE " . self::get_table_name() . "
                SET status = %s, reviewed_by = %d, reviewed_at = %s, updated_at = %s
                WHERE id = %d AND status = %s",
                sanitize_key($status),
                get_current_user_id(),
                current_time('mysql'),
                current_time('mysql'),
                absint($report_id),
                self::STATUS_PENDING
            )
        );

        return $updated === 1;
    }

    public function create_payment_report($data) {
        global $wpdb;

        $now = current_time('mysql');
        $inserted = $wpdb->insert(
            self::get_table_name(),
            [
                'customer_id'           => absint($data['customer_id'] ?? 0),
                'order_id'              => absint($data['order_id'] ?? 0),
                'reported_by'           => absint($data['reported_by'] ?? 0),
                'reported_by_role'      => sanitize_key($data['reported_by_role'] ?? ''),
                'amount'                => $this->round_money($data['amount'] ?? 0),
                'payment_date'          => sanitize_text_field($data['payment_date'] ?? ''),
                'payment_method_id'     => sanitize_key($data['payment_method_id'] ?? ''),
                'payment_method_label'  => sanitize_text_field($data['payment_method_label'] ?? ''),
                'reference'             => sanitize_text_field($data['reference'] ?? ''),
                'note'                  => sanitize_textarea_field($data['note'] ?? ''),
                'receipt_attachment_id' => absint($data['receipt_attachment_id'] ?? 0),
                'status'                => self::STATUS_PENDING,
                'created_at'            => $now,
                'updated_at'            => $now,
            ],
            [
                '%d',
                '%d',
                '%d',
                '%s',
                '%f',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%d',
                '%s',
                '%s',
                '%s',
            ]
        );

        return $inserted ? (int) $wpdb->insert_id : 0;
    }

    public function migrate_cpt_payment_reports() {
        $posts = get_posts([
            'post_type'      => 'rkm_payment_report',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'orderby'        => 'ID',
            'order'          => 'ASC',
        ]);

        $result = [
            'migrated' => 0,
            'skipped'  => 0,
            'failed'   => 0,
        ];

        foreach ($posts as $post) {
            if (!$post instanceof WP_Post) {
                $result['failed']++;
                continue;
            }

            $migrated_report_id = (int) get_post_meta($post->ID, '_rkm_migrated_report_id', true);

            if ($migrated_report_id > 0 && $this->payment_report_id_exists($migrated_report_id)) {
                $result['skipped']++;
                continue;
            }

            $data = $this->map_cpt_payment_report($post);

            if ($data['customer_id'] <= 0 || $data['order_id'] <= 0 || $data['amount'] <= 0) {
                $result['failed']++;
                continue;
            }

            if ($this->payment_report_exists($data)) {
                update_post_meta($post->ID, '_rkm_migrated', 1);
                $result['skipped']++;
                continue;
            }

            $report_id = $this->insert_migrated_payment_report($data);

            if ($report_id <= 0) {
                $result['failed']++;
                continue;
            }

            update_post_meta($post->ID, '_rkm_migrated', 1);
            update_post_meta($post->ID, '_rkm_migrated_report_id', $report_id);
            $result['migrated']++;
        }

        return $result;
    }

    private function map_cpt_payment_report($post) {
        $post_id = (int) $post->ID;
        $payment_method_id = (string) get_post_meta($post_id, 'payment_method_id', true);
        $legacy_payment_method = (string) get_post_meta($post_id, 'payment_method', true);
        $payment_method_label = (string) get_post_meta($post_id, 'payment_method_label', true);
        $payment_date = (string) get_post_meta($post_id, 'payment_date', true);
        $created_at = (string) get_post_meta($post_id, 'created_at', true);
        $reviewed_at = (string) get_post_meta($post_id, 'reviewed_at', true);
        $receipt_attachment_id = (int) get_post_meta($post_id, 'receipt_attachment_id', true);
        $reported_by = (int) get_post_meta($post_id, 'reported_by', true);
        $reported_by_role = (string) get_post_meta($post_id, 'reported_by_role', true);

        if ($payment_method_id === '' && $legacy_payment_method !== '') {
            $payment_method_id = sanitize_key($legacy_payment_method);
        }

        if ($payment_method_label === '' && $legacy_payment_method !== '') {
            $payment_method_label = $legacy_payment_method;
        }

        if ($payment_date === '') {
            $payment_date = mysql2date('Y-m-d', $post->post_date, false);
        }

        if ($created_at === '') {
            $created_at = $post->post_date ?: current_time('mysql');
        }

        if ($receipt_attachment_id <= 0) {
            $receipt_attachment_id = (int) get_post_meta($post_id, '_rkm_payment_receipt_attachment_id', true);
        }

        if ($reported_by <= 0) {
            $reported_by = (int) $post->post_author;
        }

        if ($reported_by_role === '') {
            $reported_user = $reported_by > 0 ? get_user_by('id', $reported_by) : null;
            $reported_by_role = $this->get_reported_by_role($reported_user);
        }

        return [
            'customer_id'           => (int) get_post_meta($post_id, 'customer_id', true),
            'order_id'              => (int) get_post_meta($post_id, 'order_id', true),
            'reported_by'           => $reported_by,
            'reported_by_role'      => $reported_by_role,
            'amount'                => $this->round_money(get_post_meta($post_id, 'amount', true)),
            'payment_date'          => $payment_date,
            'payment_method_id'     => $payment_method_id,
            'payment_method_label'  => $payment_method_label,
            'reference'             => (string) get_post_meta($post_id, 'reference', true),
            'note'                  => (string) get_post_meta($post_id, 'note', true),
            'receipt_attachment_id' => $receipt_attachment_id,
            'status'                => $this->normalize_report_status((string) get_post_meta($post_id, 'status', true)),
            'reviewed_by'           => (int) get_post_meta($post_id, 'reviewed_by', true),
            'reviewed_at'           => $reviewed_at !== '' ? $reviewed_at : null,
            'created_at'            => $created_at,
            'updated_at'            => $created_at,
        ];
    }

    private function insert_migrated_payment_report($data) {
        global $wpdb;

        $inserted = $wpdb->insert(
            self::get_table_name(),
            [
                'customer_id'           => absint($data['customer_id']),
                'order_id'              => absint($data['order_id']),
                'reported_by'           => absint($data['reported_by']),
                'reported_by_role'      => sanitize_key($data['reported_by_role']),
                'amount'                => $this->round_money($data['amount']),
                'payment_date'          => sanitize_text_field($data['payment_date']),
                'payment_method_id'     => sanitize_key($data['payment_method_id']),
                'payment_method_label'  => sanitize_text_field($data['payment_method_label']),
                'reference'             => sanitize_text_field($data['reference']),
                'note'                  => sanitize_textarea_field($data['note']),
                'receipt_attachment_id' => absint($data['receipt_attachment_id']),
                'status'                => $this->normalize_report_status($data['status']),
                'reviewed_by'           => !empty($data['reviewed_by']) ? absint($data['reviewed_by']) : null,
                'reviewed_at'           => !empty($data['reviewed_at']) ? sanitize_text_field($data['reviewed_at']) : null,
                'created_at'            => sanitize_text_field($data['created_at']),
                'updated_at'            => sanitize_text_field($data['updated_at']),
            ],
            [
                '%d',
                '%d',
                '%d',
                '%s',
                '%f',
                '%s',
                '%s',
                '%s',
                '%s',
                '%s',
                '%d',
                '%s',
                '%d',
                '%s',
                '%s',
                '%s',
            ]
        );

        return $inserted ? (int) $wpdb->insert_id : 0;
    }

    private function payment_report_exists($data) {
        global $wpdb;

        $existing_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM " . self::get_table_name() . "
                WHERE customer_id = %d
                    AND order_id = %d
                    AND amount = %f
                    AND payment_date = %s
                    AND payment_method_id = %s
                    AND reference = %s
                    AND receipt_attachment_id = %d
                LIMIT 1",
                absint($data['customer_id']),
                absint($data['order_id']),
                $this->round_money($data['amount']),
                sanitize_text_field($data['payment_date']),
                sanitize_key($data['payment_method_id']),
                sanitize_text_field($data['reference']),
                absint($data['receipt_attachment_id'])
            )
        );

        return !empty($existing_id);
    }

    private function payment_report_id_exists($report_id) {
        global $wpdb;

        $existing_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM " . self::get_table_name() . " WHERE id = %d LIMIT 1",
                absint($report_id)
            )
        );

        return !empty($existing_id);
    }

    private function normalize_report_status($status) {
        $status = sanitize_key($status);

        if (in_array($status, [self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_REJECTED], true)) {
            return $status;
        }

        return self::STATUS_PENDING;
    }

    public function serve_receipt() {
        $report_id = isset($_GET['report_id']) ? absint($_GET['report_id']) : 0;

        if ($report_id <= 0 || !isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'rkm_current_account_receipt_' . $report_id)) {
            wp_die('Solicitud no valida.', '', ['response' => 403]);
        }

        $report = $this->get_payment_report($report_id);

        if (!$report || !$this->can_current_user_view_report($report)) {
            wp_die('No autorizado.', '', ['response' => 403]);
        }

        $attachment_id = (int) $report['receipt_attachment_id'];
        $attachment_url = $attachment_id > 0 ? wp_get_attachment_url($attachment_id) : '';

        if ($attachment_url === '') {
            wp_die('Comprobante no disponible.', '', ['response' => 404]);
        }

        wp_safe_redirect($attachment_url);
        exit;
    }

    public function get_actor_pending_orders($actor = null) {
        $actor = $actor instanceof WP_User ? $actor : wp_get_current_user();

        if (!$actor instanceof WP_User || empty($actor->ID)) {
            return [];
        }

        if ($this->is_customer($actor)) {
            return $this->get_customer_pending_orders((int) $actor->ID);
        }

        if ($this->is_vendor($actor)) {
            $orders = [];

            foreach ($this->get_assigned_customer_ids((int) $actor->ID) as $customer_id) {
                $orders = array_merge($orders, $this->get_customer_pending_orders($customer_id));
            }

            return $orders;
        }

        return [];
    }

    public function get_actor_payment_reports($actor = null) {
        $actor = $actor instanceof WP_User ? $actor : wp_get_current_user();

        if (!$actor instanceof WP_User || empty($actor->ID)) {
            return [];
        }

        if (!class_exists('RKM_Current_Account_Transactions')) {
            return [];
        }

        if ($this->is_customer($actor)) {
            return $this->get_customer_payment_reports((int) $actor->ID);
        }

        if ($this->is_vendor($actor)) {
            $customer_ids = $this->get_assigned_customer_ids((int) $actor->ID);

            if (empty($customer_ids)) {
                return [];
            }

            return $this->get_payment_reports_for_customers($customer_ids);
        }

        return [];
    }

    private function get_payment_reports_for_customers($customer_ids) {
        $customer_ids = array_values(array_filter(array_map('absint', (array) $customer_ids)));

        if (empty($customer_ids)) {
            return [];
        }

        if (!class_exists('RKM_Current_Account_Transactions')) {
            return [];
        }

        return $this->format_transactions_as_payment_reports(
            RKM_Current_Account_Transactions::get_transactions_by_customers($customer_ids)
        );
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
            $context = self::get_order_current_account_context($order);

            return !empty($context['enabled']) && (float) $context['balance'] > 0;
        }));
    }

    public function get_customer_payment_reports($customer_id) {
        if (!class_exists('RKM_Current_Account_Transactions')) {
            return [];
        }

        return $this->format_transactions_as_payment_reports(
            RKM_Current_Account_Transactions::get_transactions_by_customer($customer_id)
        );
    }

    public function get_admin_payment_reports() {
        if (!class_exists('RKM_Current_Account_Transactions')) {
            return [];
        }

        return $this->format_transactions_as_payment_reports(
            RKM_Current_Account_Transactions::get_all_transactions(200)
        );
    }

    private function format_transactions_as_payment_reports($transactions) {
        $reports = [];

        foreach ((array) $transactions as $transaction) {
            $report = $this->format_transaction_as_payment_report($transaction);

            if ($report) {
                $reports[] = $report;
            }
        }

        return $reports;
    }

    private function format_transaction_as_payment_report($transaction) {
        if (!is_array($transaction) || empty($transaction['id'])) {
            return null;
        }

        $customer_id = (int) ($transaction['customer_id'] ?? 0);
        $order_id = (int) ($transaction['order_id'] ?? 0);
        $reported_by = (int) ($transaction['created_by'] ?? 0);
        $customer = $customer_id > 0 ? get_user_by('id', $customer_id) : null;
        $reported_by_user = $reported_by > 0 ? get_user_by('id', $reported_by) : null;
        $order = function_exists('wc_get_order') && $order_id > 0 ? wc_get_order($order_id) : null;
        $created_at = (string) ($transaction['created_at'] ?? '');

        return [
            'id' => (int) $transaction['id'],
            'customer_id' => $customer_id,
            'customer_name' => $customer instanceof WP_User ? ($customer->display_name ?: $customer->user_login) : 'Cliente eliminado',
            'order_id' => $order_id,
            'order_number' => $order ? $order->get_order_number() : (string) $order_id,
            'order_balance' => $order ? $this->get_order_credit_balance($order) : 0,
            'amount' => (float) ($transaction['amount'] ?? 0),
            'payment_date' => $created_at !== '' ? substr($created_at, 0, 10) : '',
            'payment_method_id' => (string) ($transaction['method_id'] ?? ''),
            'payment_method_label' => (string) ($transaction['method_label'] ?? ''),
            'reference' => (string) ($transaction['reference'] ?? ''),
            'note' => (string) ($transaction['note'] ?? ''),
            'status' => (string) ($transaction['status'] ?? self::STATUS_PENDING),
            'created_at' => $created_at,
            'updated_at' => (string) ($transaction['updated_at'] ?? ''),
            'reported_by' => $reported_by,
            'reported_by_name' => $reported_by_user instanceof WP_User ? ($reported_by_user->display_name ?: $reported_by_user->user_login) : '-',
            'reported_by_role' => $reported_by_user instanceof WP_User ? $this->get_reported_by_role($reported_by_user) : 'customer',
            'receipt_attachment_id' => (int) ($transaction['receipt_attachment_id'] ?? 0),
            'receipt_url' => (string) ($transaction['receipt_url'] ?? ''),
            'reviewed_by' => (int) ($transaction['approved_by'] ?? 0),
            'reviewed_at' => (string) ($transaction['approved_at'] ?? ''),
        ];
    }

    public static function maybe_generate_order_debt($order, $delivered_at = '', $user = null) {
        if (is_numeric($order)) {
            $order = function_exists('wc_get_order') ? wc_get_order(absint($order)) : null;
        }

        if (!$order || !method_exists($order, 'get_meta')) {
            return null;
        }

        if (method_exists($order, 'get_status') && $order->get_status() !== 'completed') {
            return null;
        }

        $payment_term = (string) $order->get_meta('_rkm_payment_term', true);
        $credit_balance = self::round_money_static((float) $order->get_meta('_rkm_credit_balance', true));

        if (!in_array($payment_term, ['credit', 'mixed'], true) || $credit_balance <= 0) {
            return null;
        }

        if ((string) $order->get_meta('_rkm_current_account_enabled', true) === 'yes') {
            return null;
        }

        $delivered_at = (string) $delivered_at;
        if ($delivered_at === '') {
            $delivered_at = (string) $order->get_meta('_rkm_delivered_at', true);
        }
        if ($delivered_at === '' && method_exists($order, 'get_date_completed')) {
            $completed_date = $order->get_date_completed();
            if ($completed_date) {
                $delivered_at = $completed_date->date('Y-m-d H:i:s');
            }
        }
        if ($delivered_at === '') {
            $delivered_at = current_time('mysql');
        }

        $delivered_timestamp = strtotime($delivered_at);
        if ($delivered_timestamp <= 0) {
            $delivered_timestamp = current_time('timestamp');
            $delivered_at = current_time('mysql');
        }

        $due_at = (string) $order->get_meta('_rkm_credit_due_at', true);
        if ($due_at === '') {
            $due_at = wp_date('Y-m-d H:i:s', $delivered_timestamp + (DAY_IN_SECONDS * 20));
        }

        $amount = self::round_money_static((float) $order->get_meta('_rkm_final_total', true));
        if ($amount <= 0 && method_exists($order, 'get_total')) {
            $amount = self::round_money_static((float) $order->get_total());
        }

        $paid_amount = self::round_money_static((float) $order->get_meta('_rkm_upfront_amount', true));
        if ($payment_term === 'credit') {
            $paid_amount = 0;
        }
        if ($payment_term === 'mixed' && $paid_amount <= 0 && $amount > $credit_balance) {
            $paid_amount = self::round_money_static($amount - $credit_balance);
        }

        $created_at = current_time('mysql');
        $previous = self::get_order_current_account_context($order);

        $order->update_meta_data('_rkm_current_account_enabled', 'yes');
        $order->update_meta_data('_rkm_current_account_status', self::ACCOUNT_STATUS_PENDING);
        $order->update_meta_data('_rkm_current_account_amount', $amount);
        $order->update_meta_data('_rkm_current_account_paid_amount', $paid_amount);
        $order->update_meta_data('_rkm_current_account_balance', $credit_balance);
        $order->update_meta_data('_rkm_current_account_due_at', $due_at);
        $order->update_meta_data('_rkm_current_account_created_at', $created_at);
        $order->save();

        $context = self::get_order_current_account_context($order);
        $payment_label = (string) $order->get_meta('_rkm_payment_term_label', true);
        if ($payment_label === '') {
            $payment_label = $payment_term;
        }

        if (class_exists('RKM_Order_Audit_Log')) {
            RKM_Order_Audit_Log::add_event(
                $order->get_id(),
                'Cuenta corriente generada',
                'Cuenta corriente generada',
                sprintf(
                    'Cuenta corriente generada con saldo %s. Vencimiento: %s. Condicion de pago: %s.',
                    wp_strip_all_tags(self::format_money_static($credit_balance)),
                    $context['due_label'] !== '' ? $context['due_label'] : '-',
                    $payment_label
                ),
                $previous,
                $context
            );
        }

        return $context;
    }

    public static function get_order_current_account_context($order) {
        if (is_numeric($order)) {
            $order = function_exists('wc_get_order') ? wc_get_order(absint($order)) : null;
        }

        if (!$order || !method_exists($order, 'get_meta')) {
            return [
                'enabled' => false,
                'status' => '',
                'status_label' => '',
                'balance' => 0,
            ];
        }

        $enabled = (string) $order->get_meta('_rkm_current_account_enabled', true) === 'yes';
        $amount = self::round_money_static((float) $order->get_meta('_rkm_current_account_amount', true));
        $paid_amount = self::round_money_static((float) $order->get_meta('_rkm_current_account_paid_amount', true));
        $balance = self::round_money_static((float) $order->get_meta('_rkm_current_account_balance', true));
        $due_at = (string) $order->get_meta('_rkm_current_account_due_at', true);
        $created_at = (string) $order->get_meta('_rkm_current_account_created_at', true);
        $delivered_at = (string) $order->get_meta('_rkm_delivered_at', true);
        if ($delivered_at === '' && method_exists($order, 'get_date_completed')) {
            $completed_date = $order->get_date_completed();
            if ($completed_date) {
                $delivered_at = $completed_date->date('Y-m-d H:i:s');
            }
        }
        $status = sanitize_key((string) $order->get_meta('_rkm_current_account_status', true));
        $status = $status !== '' ? $status : self::ACCOUNT_STATUS_PENDING;
        $due_timestamp = $due_at !== '' ? strtotime($due_at) : 0;
        $now = current_time('timestamp');

        if ($balance <= 0) {
            $status = self::ACCOUNT_STATUS_PAID;
        } elseif ($due_timestamp > 0 && $due_timestamp < $now && $status !== self::ACCOUNT_STATUS_PAID) {
            $status = self::ACCOUNT_STATUS_OVERDUE;
        }

        $remaining_days = 0;
        if ($due_timestamp > 0) {
            $remaining_days = (int) floor(($due_timestamp - $now) / DAY_IN_SECONDS);
        }

        return [
            'enabled' => $enabled,
            'status' => $status,
            'status_label' => self::get_account_status_label($status),
            'amount' => $amount,
            'amount_display' => self::format_money_static($amount),
            'paid_amount' => $paid_amount,
            'paid_amount_display' => self::format_money_static($paid_amount),
            'balance' => $balance,
            'balance_display' => self::format_money_static($balance),
            'due_at' => $due_at,
            'due_label' => self::format_date_static($due_at, 'd/m/Y'),
            'created_at' => $created_at,
            'created_label' => self::format_date_static($created_at, 'd/m/Y H:i'),
            'delivered_at' => $delivered_at,
            'delivered_label' => self::format_date_static($delivered_at, 'd/m/Y'),
            'remaining_days' => $remaining_days,
            'remaining_label' => self::get_remaining_days_label($remaining_days, $due_timestamp),
        ];
    }

    public static function get_account_status_label($status) {
        $labels = [
            self::ACCOUNT_STATUS_PENDING => 'Pendiente',
            self::ACCOUNT_STATUS_OVERDUE => 'Vencido',
            self::ACCOUNT_STATUS_PAID => 'Pagado',
        ];

        return $labels[$status] ?? ucfirst((string) $status);
    }

    public function get_pending_payment_reports() {
        if (!class_exists('RKM_Current_Account_Transactions')) {
            return [];
        }

        $reports = array_filter($this->get_admin_payment_reports(), static function ($report) {
            return ($report['status'] ?? '') === RKM_Current_Account_Transactions::STATUS_PENDING;
        });

        return array_values($reports);
    }

    private function get_payment_report($report_id) {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM " . self::get_table_name() . " WHERE id = %d",
                absint($report_id)
            ),
            ARRAY_A
        );

        if (!$row) {
            return null;
        }

        return $this->hydrate_payment_report($row);
    }

    private function hydrate_payment_reports($rows) {
        $reports = [];

        foreach ((array) $rows as $row) {
            $report = $this->hydrate_payment_report($row);

            if ($report) {
                $reports[] = $report;
            }
        }

        return $reports;
    }

    private function hydrate_payment_report($row) {
        if (!is_array($row) || empty($row['id'])) {
            return null;
        }

        $report_id = (int) $row['id'];
        $customer_id = (int) $row['customer_id'];
        $order_id = (int) $row['order_id'];
        $reported_by = (int) $row['reported_by'];
        $reported_by_user = $reported_by > 0 ? get_user_by('id', $reported_by) : null;
        $receipt_attachment_id = (int) $row['receipt_attachment_id'];
        $customer = $customer_id > 0 ? get_user_by('id', $customer_id) : null;
        $order = function_exists('wc_get_order') && $order_id > 0 ? wc_get_order($order_id) : null;

        return [
            'id'                   => (int) $report_id,
            'customer_id'          => $customer_id,
            'customer_name'        => $customer instanceof WP_User ? ($customer->display_name ?: $customer->user_login) : 'Cliente eliminado',
            'order_id'             => $order_id,
            'order_number'         => $order ? $order->get_order_number() : (string) $order_id,
            'order_balance'        => $order ? $this->get_order_credit_balance($order) : 0,
            'amount'               => (float) $row['amount'],
            'payment_date'         => (string) $row['payment_date'],
            'payment_method_id'    => (string) $row['payment_method_id'],
            'payment_method_label' => (string) $row['payment_method_label'],
            'reference'            => (string) $row['reference'],
            'note'                 => (string) $row['note'],
            'status'               => (string) $row['status'],
            'created_at'           => (string) $row['created_at'],
            'updated_at'           => (string) $row['updated_at'],
            'reported_by'          => $reported_by,
            'reported_by_name'     => $reported_by_user instanceof WP_User ? ($reported_by_user->display_name ?: $reported_by_user->user_login) : '-',
            'reported_by_role'     => (string) $row['reported_by_role'],
            'receipt_attachment_id' => $receipt_attachment_id,
            'receipt_url'          => $this->get_receipt_url((int) $report_id),
            'reviewed_by'          => (int) $row['reviewed_by'],
            'reviewed_at'          => (string) $row['reviewed_at'],
        ];
    }

    public function get_order_credit_balance($order) {
        if (!$order || !is_callable([$order, 'get_meta'])) {
            return 0;
        }

        if ((string) $order->get_meta('_rkm_current_account_enabled', true) === 'yes') {
            return $this->round_money((float) $order->get_meta('_rkm_current_account_balance', true));
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

    public function get_orders_account_summary($orders) {
        $pending_total = 0;
        $overdue_count = 0;
        $next_due_timestamp = 0;
        $next_due_label = '';

        foreach ((array) $orders as $order) {
            $context = self::get_order_current_account_context($order);

            if (empty($context['enabled']) || (float) $context['balance'] <= 0) {
                continue;
            }

            $pending_total += (float) $context['balance'];

            if ($context['status'] === self::ACCOUNT_STATUS_OVERDUE) {
                $overdue_count++;
            }

            $due_timestamp = !empty($context['due_at']) ? strtotime($context['due_at']) : 0;
            if ($due_timestamp > 0 && ($next_due_timestamp === 0 || $due_timestamp < $next_due_timestamp)) {
                $next_due_timestamp = $due_timestamp;
                $next_due_label = $context['due_label'];
            }
        }

        return [
            'pending_total' => $this->round_money($pending_total),
            'next_due_label' => $next_due_label,
            'overdue_count' => $overdue_count,
        ];
    }

    public function format_money($amount) {
        if (function_exists('wc_price')) {
            return wc_price($amount);
        }

        return '$' . number_format((float) $amount, 2, ',', '.');
    }

    public static function format_money_static($amount) {
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

    public static function format_date_static($mysql_date, $format = 'd/m/Y H:i') {
        if ((string) $mysql_date === '') {
            return '';
        }

        $timestamp = strtotime((string) $mysql_date);

        if (!$timestamp) {
            return '';
        }

        return wp_date($format, $timestamp);
    }

    public function format_payment_date($date) {
        if ($date === '') {
            return '-';
        }

        $timestamp = strtotime($date . ' 00:00:00');

        if (!$timestamp) {
            return '-';
        }

        return wp_date('d/m/Y', $timestamp);
    }

    public function get_order_customer_label($order) {
        if (!$order || !is_callable([$order, 'get_customer_id'])) {
            return '';
        }

        $customer = get_user_by('id', (int) $order->get_customer_id());

        if (!$customer instanceof WP_User) {
            return '';
        }

        return $customer->display_name ?: $customer->user_login;
    }

    public function get_order_detail_url($order) {
        return home_url('/mi-cuenta/panel/?section=pedidos');
    }

    public function get_receipt_accept_attribute() {
        return '.jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf';
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

    private function can_actor_report_order($actor, $order) {
        if (!$actor instanceof WP_User || empty($actor->ID) || !$order) {
            return false;
        }

        $customer_id = (int) $order->get_customer_id();

        if ($this->is_customer($actor)) {
            return $customer_id === (int) $actor->ID;
        }

        if ($this->is_vendor($actor)) {
            return in_array($customer_id, $this->get_assigned_customer_ids((int) $actor->ID), true);
        }

        return false;
    }

    private function can_current_user_view_report($report) {
        $user = wp_get_current_user();

        if (!$user instanceof WP_User || empty($user->ID)) {
            return false;
        }

        if (self::can_admin_access($user)) {
            return true;
        }

        if ($this->is_customer($user)) {
            return (int) $report['customer_id'] === (int) $user->ID;
        }

        if ($this->is_vendor($user)) {
            return in_array((int) $report['customer_id'], $this->get_assigned_customer_ids((int) $user->ID), true);
        }

        return false;
    }

    private function get_assigned_customer_ids($vendor_id) {
        if (!class_exists('RKM_Assignments')) {
            return [];
        }

        return array_map('intval', RKM_Assignments::get_assigned_customer_ids($vendor_id));
    }

    private function is_customer($user) {
        return class_exists('RKM_Permissions') && RKM_Permissions::is_rkm_customer($user);
    }

    private function is_vendor($user) {
        return class_exists('RKM_Permissions') && RKM_Permissions::is_rkm_vendor($user);
    }

    private function get_reported_by_role($user) {
        if (!$user instanceof WP_User) {
            return 'user';
        }

        if ($this->is_vendor($user)) {
            return 'vendor';
        }

        if ($this->is_customer($user)) {
            return 'customer';
        }

        return 'user';
    }

    private function is_valid_payment_date($date) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date)) {
            return false;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $date));

        if (!checkdate($month, $day, $year)) {
            return false;
        }

        return strtotime($date . ' 00:00:00') <= current_time('timestamp');
    }

    private function validate_receipt_upload($field) {
        if (empty($_FILES[$field]) || !is_array($_FILES[$field])) {
            return new WP_Error('rkm_receipt_required', 'Adjunta el comprobante de pago.');
        }

        $file = $_FILES[$field];

        if (!empty($file['error'])) {
            return new WP_Error('rkm_receipt_upload_error', 'No se pudo leer el comprobante adjunto.');
        }

        if (empty($file['tmp_name']) || empty($file['name'])) {
            return new WP_Error('rkm_receipt_required', 'Adjunta el comprobante de pago.');
        }

        if ((int) ($file['size'] ?? 0) <= 0) {
            return new WP_Error('rkm_receipt_empty', 'El comprobante adjunto esta vacio.');
        }

        if ((int) $file['size'] > self::RECEIPT_MAX_BYTES) {
            return new WP_Error('rkm_receipt_too_large', 'El comprobante no puede superar 5 MB.');
        }

        $allowed_mimes = $this->get_allowed_receipt_mimes();
        $filetype = wp_check_filetype_and_ext($file['tmp_name'], $file['name'], $allowed_mimes);

        if (empty($filetype['ext']) || empty($filetype['type']) || !in_array($filetype['type'], $allowed_mimes, true)) {
            return new WP_Error('rkm_receipt_invalid_type', 'El comprobante debe ser JPG, PNG o PDF.');
        }

        return true;
    }

    private function handle_receipt_upload($field) {
        if (!function_exists('media_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }

        $attachment_id = media_handle_upload($field, 0);

        if (is_wp_error($attachment_id)) {
            return new WP_Error('rkm_receipt_save_failed', 'No se pudo guardar el comprobante en la biblioteca de medios.');
        }

        return (int) $attachment_id;
    }

    private function get_allowed_receipt_mimes() {
        return [
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'pdf'  => 'application/pdf',
        ];
    }

    private function get_receipt_url($report_id) {
        if ((int) $report_id <= 0) {
            return '';
        }

        return wp_nonce_url(
            admin_url('admin-ajax.php?action=rkm_current_account_receipt&report_id=' . (int) $report_id),
            'rkm_current_account_receipt_' . (int) $report_id
        );
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

    private static function round_money_static($amount) {
        $decimals = function_exists('wc_get_price_decimals') ? wc_get_price_decimals() : 2;

        return round((float) $amount, $decimals);
    }

    private static function get_remaining_days_label($remaining_days, $due_timestamp) {
        if ($due_timestamp <= 0) {
            return '';
        }

        if ($remaining_days > 0) {
            return sprintf('%d dias restantes', $remaining_days);
        }

        if ($remaining_days === 0) {
            return 'Vence hoy';
        }

        return sprintf('Credito vencido hace %d dias', abs($remaining_days));
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
