<?php

if (!defined('ABSPATH')) {
    exit;
}

class RKM_Current_Account_Transactions {

    const TABLE_SCHEMA_VERSION = '1.0.0';
    const TABLE_SCHEMA_OPTION = 'rkm_current_account_transactions_schema_version';
    const TYPE_PAYMENT = 'payment';
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_VOIDED = 'voided';

    public function init() {
        add_action('init', [__CLASS__, 'maybe_install_schema']);
    }

    public static function get_table_name() {
        global $wpdb;

        return $wpdb->prefix . 'rkm_current_account_transactions';
    }

    public static function install_schema() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table_name = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id BIGINT UNSIGNED NOT NULL,
            customer_id BIGINT UNSIGNED NOT NULL,
            type VARCHAR(50) NOT NULL DEFAULT 'payment',
            amount DECIMAL(18,2) NOT NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'pending',
            method_id BIGINT UNSIGNED NULL,
            method_label VARCHAR(190) NULL,
            reference VARCHAR(190) NULL,
            note LONGTEXT NULL,
            receipt_attachment_id BIGINT UNSIGNED NULL,
            created_by BIGINT UNSIGNED NULL,
            approved_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL,
            approved_at DATETIME NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY  (id),
            KEY order_id (order_id),
            KEY customer_id (customer_id),
            KEY status (status),
            KEY type (type),
            KEY created_at (created_at),
            KEY approved_at (approved_at)
        ) {$charset_collate};";

        dbDelta($sql);
        update_option(self::TABLE_SCHEMA_OPTION, self::TABLE_SCHEMA_VERSION, false);
    }

    public static function maybe_install_schema() {
        if (get_option(self::TABLE_SCHEMA_OPTION) !== self::TABLE_SCHEMA_VERSION) {
            self::install_schema();
        }
    }

    public static function add_transaction($data) {
        global $wpdb;

        $order_id = absint($data['order_id'] ?? 0);
        $order = $order_id > 0 && function_exists('wc_get_order') ? wc_get_order($order_id) : null;

        if (!$order) {
            return new WP_Error('rkm_transaction_invalid_order', 'Pedido no encontrado.');
        }

        $customer_id = absint($data['customer_id'] ?? 0);
        if ($customer_id <= 0 && method_exists($order, 'get_customer_id')) {
            $customer_id = (int) $order->get_customer_id();
        }

        if ($customer_id <= 0) {
            return new WP_Error('rkm_transaction_invalid_customer', 'Cliente no encontrado.');
        }

        $amount = self::round_money((float) ($data['amount'] ?? 0));
        if ($amount <= 0) {
            return new WP_Error('rkm_transaction_invalid_amount', 'El monto debe ser mayor a cero.');
        }

        $type = self::normalize_type($data['type'] ?? self::TYPE_PAYMENT);
        $status = self::normalize_status($data['status'] ?? self::STATUS_PENDING);
        $now = current_time('mysql');
        $approved_by = !empty($data['approved_by']) ? absint($data['approved_by']) : null;
        $approved_at = !empty($data['approved_at']) ? sanitize_text_field((string) $data['approved_at']) : null;

        if ($status === self::STATUS_APPROVED) {
            $approved_by = $approved_by ?: get_current_user_id();
            $approved_at = $approved_at ?: $now;
        }

        $inserted = $wpdb->insert(
            self::get_table_name(),
            [
                'order_id' => $order_id,
                'customer_id' => $customer_id,
                'type' => $type,
                'amount' => $amount,
                'status' => $status,
                'method_id' => !empty($data['method_id']) ? absint($data['method_id']) : null,
                'method_label' => isset($data['method_label']) ? sanitize_text_field((string) $data['method_label']) : null,
                'reference' => isset($data['reference']) ? sanitize_text_field((string) $data['reference']) : null,
                'note' => isset($data['note']) ? sanitize_textarea_field((string) $data['note']) : null,
                'receipt_attachment_id' => !empty($data['receipt_attachment_id']) ? absint($data['receipt_attachment_id']) : null,
                'created_by' => !empty($data['created_by']) ? absint($data['created_by']) : get_current_user_id(),
                'approved_by' => $approved_by,
                'created_at' => !empty($data['created_at']) ? sanitize_text_field((string) $data['created_at']) : $now,
                'approved_at' => $approved_at,
                'updated_at' => $now,
            ],
            [
                '%d',
                '%d',
                '%s',
                '%f',
                '%s',
                '%d',
                '%s',
                '%s',
                '%s',
                '%d',
                '%d',
                '%d',
                '%s',
                '%s',
                '%s',
            ]
        );

        if (!$inserted) {
            return new WP_Error('rkm_transaction_insert_failed', 'No se pudo registrar la transaccion.');
        }

        $transaction_id = (int) $wpdb->insert_id;
        $created_by_user = get_user_by('id', !empty($data['created_by']) ? absint($data['created_by']) : get_current_user_id());
        $created_by_label = $created_by_user instanceof WP_User ? ($created_by_user->display_name ?: $created_by_user->user_login) : 'Sistema';

        self::add_audit_event(
            $order,
            $status === self::STATUS_APPROVED ? 'Pago externo registrado y aprobado' : 'Pago externo informado',
            sprintf(
                'Transaccion #%d por %s. Estado: %s. Informado por: %s.',
                $transaction_id,
                wp_strip_all_tags(self::format_money($amount)),
                self::get_status_label($status),
                $created_by_label
            ),
            null,
            self::get_transaction($transaction_id)
        );

        if ($status === self::STATUS_APPROVED) {
            self::sync_order_balance($order_id);
        }

        return $transaction_id;
    }

    public static function get_transactions_by_order($order_id) {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM " . self::get_table_name() . " WHERE order_id = %d ORDER BY created_at DESC, id DESC",
                absint($order_id)
            ),
            ARRAY_A
        );

        return self::hydrate_transactions($rows);
    }

    public static function get_transactions_by_customer($customer_id) {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM " . self::get_table_name() . " WHERE customer_id = %d ORDER BY created_at DESC, id DESC",
                absint($customer_id)
            ),
            ARRAY_A
        );

        return self::hydrate_transactions($rows);
    }

    public static function get_transactions_by_customers($customer_ids) {
        global $wpdb;

        $customer_ids = array_values(array_filter(array_map('absint', (array) $customer_ids)));

        if (empty($customer_ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($customer_ids), '%d'));
        $sql = $wpdb->prepare(
            "SELECT * FROM " . self::get_table_name() . " WHERE customer_id IN ({$placeholders}) ORDER BY created_at DESC, id DESC LIMIT 200",
            $customer_ids
        );

        return self::hydrate_transactions($wpdb->get_results($sql, ARRAY_A));
    }

    public static function get_all_transactions($limit = 200) {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM " . self::get_table_name() . " ORDER BY created_at DESC, id DESC LIMIT %d",
                max(1, absint($limit))
            ),
            ARRAY_A
        );

        return self::hydrate_transactions($rows);
    }

    public static function approve_transaction($transaction_id) {
        return self::transition_transaction($transaction_id, self::STATUS_APPROVED);
    }

    public static function reject_transaction($transaction_id, $reason = '') {
        return self::transition_transaction($transaction_id, self::STATUS_REJECTED, $reason);
    }

    public static function get_approved_total_by_order($order_id) {
        global $wpdb;

        $total = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COALESCE(SUM(amount), 0) FROM " . self::get_table_name() . "
                WHERE order_id = %d AND status = %s",
                absint($order_id),
                self::STATUS_APPROVED
            )
        );

        return self::round_money((float) $total);
    }

    public static function sync_order_balance($order_id) {
        $order_id = absint($order_id);
        $order = $order_id > 0 && function_exists('wc_get_order') ? wc_get_order($order_id) : null;

        if (!$order) {
            return new WP_Error('rkm_sync_invalid_order', 'Pedido no encontrado.');
        }

        if ((string) $order->get_meta('_rkm_current_account_enabled', true) !== 'yes') {
            return new WP_Error('rkm_sync_account_disabled', 'El pedido no tiene cuenta corriente activa.');
        }

        $previous = class_exists('RKM_Current_Account')
            ? RKM_Current_Account::get_order_current_account_context($order)
            : self::get_basic_account_context($order);

        $amount = self::round_money((float) $order->get_meta('_rkm_current_account_amount', true));
        if ($amount <= 0 && method_exists($order, 'get_total')) {
            $amount = self::round_money((float) $order->get_total());
        }

        $initial_paid = self::round_money((float) $order->get_meta('_rkm_upfront_amount', true));
        if ((string) $order->get_meta('_rkm_payment_term', true) === 'credit') {
            $initial_paid = 0;
        }

        $approved_total = self::get_approved_total_by_order($order_id);
        $paid_amount = self::round_money($initial_paid + $approved_total);
        $balance = self::round_money(max(0, $amount - $paid_amount));
        $status = $balance > 0 ? self::get_pending_or_overdue_status($order) : 'paid';

        $order->update_meta_data('_rkm_current_account_paid_amount', $paid_amount);
        $order->update_meta_data('_rkm_current_account_balance', $balance);
        $order->update_meta_data('_rkm_current_account_status', $status);
        $order->save();

        $current = class_exists('RKM_Current_Account')
            ? RKM_Current_Account::get_order_current_account_context($order)
            : self::get_basic_account_context($order);

        if (
            (float) ($previous['balance'] ?? -1) !== (float) ($current['balance'] ?? -1)
            || (string) ($previous['status'] ?? '') !== (string) ($current['status'] ?? '')
        ) {
            self::add_audit_event(
                $order,
                'Saldo de cuenta corriente actualizado',
                sprintf(
                    'Saldo actualizado. Pagos aprobados: %s. Saldo restante: %s. Estado: %s.',
                    wp_strip_all_tags(self::format_money($approved_total)),
                    wp_strip_all_tags(self::format_money($balance)),
                    self::get_status_label($status)
                ),
                $previous,
                $current
            );
        }

        return $current;
    }

    private static function transition_transaction($transaction_id, $new_status, $review_note = '') {
        global $wpdb;

        $transaction_id = absint($transaction_id);
        $transaction = self::get_transaction($transaction_id);

        if (!$transaction) {
            return new WP_Error('rkm_transaction_not_found', 'Transaccion no encontrada.');
        }

        if ($transaction['status'] !== self::STATUS_PENDING) {
            return new WP_Error('rkm_transaction_not_pending', 'Solo se pueden revisar transacciones pendientes.');
        }

        $new_status = self::normalize_status($new_status);
        if (!in_array($new_status, [self::STATUS_APPROVED, self::STATUS_REJECTED], true)) {
            return new WP_Error('rkm_transaction_invalid_status', 'Estado de transaccion invalido.');
        }

        $now = current_time('mysql');
        $review_note = sanitize_textarea_field((string) $review_note);
        $updated_note = (string) ($transaction['note'] ?? '');

        if ($new_status === self::STATUS_REJECTED && $review_note !== '') {
            $updated_note = trim($updated_note . "\nMotivo de rechazo: " . $review_note);
        }

        $updated = $wpdb->update(
            self::get_table_name(),
            [
                'status' => $new_status,
                'note' => $updated_note,
                'approved_by' => get_current_user_id(),
                'approved_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => $transaction_id,
                'status' => self::STATUS_PENDING,
            ],
            [
                '%s',
                '%s',
                '%d',
                '%s',
                '%s',
            ],
            [
                '%d',
                '%s',
            ]
        );

        if ($updated !== 1) {
            return new WP_Error('rkm_transaction_transition_failed', 'No se pudo actualizar la transaccion.');
        }

        $updated_transaction = self::get_transaction($transaction_id);
        $order = function_exists('wc_get_order') ? wc_get_order((int) $transaction['order_id']) : null;

        if ($order) {
            $reviewer = wp_get_current_user();
            $reviewer_label = $reviewer instanceof WP_User && !empty($reviewer->ID) ? ($reviewer->display_name ?: $reviewer->user_login) : 'Sistema';
            $details = sprintf(
                'Transaccion #%d %s por %s. Revisado por: %s.',
                $transaction_id,
                $new_status === self::STATUS_APPROVED ? 'aprobada' : 'rechazada',
                wp_strip_all_tags(self::format_money((float) $transaction['amount'])),
                $reviewer_label
            );

            if ($new_status === self::STATUS_REJECTED && $review_note !== '') {
                $details .= ' Motivo: ' . $review_note;
            }

            self::add_audit_event(
                $order,
                $new_status === self::STATUS_APPROVED ? 'Pago externo aprobado' : 'Pago externo rechazado',
                $details,
                $transaction,
                $updated_transaction
            );
        }

        if ($new_status === self::STATUS_APPROVED) {
            self::sync_order_balance((int) $transaction['order_id']);
        }

        return $updated_transaction;
    }

    public static function get_transaction($transaction_id) {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM " . self::get_table_name() . " WHERE id = %d",
                absint($transaction_id)
            ),
            ARRAY_A
        );

        return $row ? self::hydrate_transaction($row) : null;
    }

    private static function hydrate_transactions($rows) {
        $transactions = [];

        foreach ((array) $rows as $row) {
            $transaction = self::hydrate_transaction($row);

            if ($transaction) {
                $transactions[] = $transaction;
            }
        }

        return $transactions;
    }

    private static function hydrate_transaction($row) {
        if (!is_array($row) || empty($row['id'])) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'order_id' => (int) $row['order_id'],
            'customer_id' => (int) $row['customer_id'],
            'type' => (string) $row['type'],
            'amount' => (float) $row['amount'],
            'amount_display' => self::format_money((float) $row['amount']),
            'status' => (string) $row['status'],
            'status_label' => self::get_status_label((string) $row['status']),
            'method_id' => isset($row['method_id']) ? (int) $row['method_id'] : 0,
            'method_label' => (string) ($row['method_label'] ?? ''),
            'reference' => (string) ($row['reference'] ?? ''),
            'note' => (string) ($row['note'] ?? ''),
            'receipt_attachment_id' => isset($row['receipt_attachment_id']) ? (int) $row['receipt_attachment_id'] : 0,
            'receipt_url' => !empty($row['receipt_attachment_id']) ? wp_get_attachment_url((int) $row['receipt_attachment_id']) : '',
            'created_by' => isset($row['created_by']) ? (int) $row['created_by'] : 0,
            'approved_by' => isset($row['approved_by']) ? (int) $row['approved_by'] : 0,
            'created_at' => (string) $row['created_at'],
            'approved_at' => (string) ($row['approved_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    private static function normalize_type($type) {
        $type = sanitize_key((string) $type);

        return $type !== '' ? $type : self::TYPE_PAYMENT;
    }

    private static function normalize_status($status) {
        $status = sanitize_key((string) $status);
        $valid = [
            self::STATUS_PENDING,
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
            self::STATUS_VOIDED,
        ];

        return in_array($status, $valid, true) ? $status : self::STATUS_PENDING;
    }

    private static function get_status_label($status) {
        $labels = [
            self::STATUS_PENDING => 'Pendiente de validacion',
            self::STATUS_APPROVED => 'Aprobado',
            self::STATUS_REJECTED => 'Rechazado',
            self::STATUS_VOIDED => 'Anulado',
            'paid' => 'Pagado',
            'overdue' => 'Vencido',
        ];

        return $labels[$status] ?? ucfirst((string) $status);
    }

    private static function get_pending_or_overdue_status($order) {
        $due_at = $order ? (string) $order->get_meta('_rkm_current_account_due_at', true) : '';
        $due_timestamp = $due_at !== '' ? strtotime($due_at) : 0;

        if ($due_timestamp > 0 && $due_timestamp < current_time('timestamp')) {
            return 'overdue';
        }

        return 'pending';
    }

    private static function get_basic_account_context($order) {
        return [
            'enabled' => (string) $order->get_meta('_rkm_current_account_enabled', true) === 'yes',
            'status' => (string) $order->get_meta('_rkm_current_account_status', true),
            'balance' => self::round_money((float) $order->get_meta('_rkm_current_account_balance', true)),
        ];
    }

    private static function add_audit_event($order, $title, $details, $old_value = null, $new_value = null) {
        if (!$order || !method_exists($order, 'get_id') || !class_exists('RKM_Order_Audit_Log')) {
            return false;
        }

        return RKM_Order_Audit_Log::add_event(
            $order->get_id(),
            $title,
            $title,
            $details,
            $old_value,
            $new_value
        );
    }

    private static function format_money($amount) {
        if (function_exists('wc_price')) {
            return wc_price($amount);
        }

        return '$' . number_format((float) $amount, 2, ',', '.');
    }

    private static function round_money($amount) {
        $decimals = function_exists('wc_get_price_decimals') ? wc_get_price_decimals() : 2;

        return round((float) $amount, $decimals);
    }
}
