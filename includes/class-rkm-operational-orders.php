<?php

if (!defined('ABSPATH')) {
    exit;
}

class RKM_Operational_Orders {

    const SECTION_KEY = 'pedidos-operativos';
    const ORDERS_LIMIT = 100;
    const NONCE_ACTION = 'rkm_operational_orders_nonce';
    const LEGACY_PENDING = 'pending';
    const LEGACY_REVIEW = 'en-revision';
    const LEGACY_PROCESSING = 'processing';

    public function init() {
        add_action('wp_ajax_rkm_confirm_operational_order', [$this, 'ajax_confirm_order']);
        add_action('wp_ajax_rkm_send_operational_order_to_warehouse', [$this, 'ajax_send_to_warehouse']);
        add_action('wp_ajax_rkm_update_operational_order', [$this, 'ajax_update_order']);
        add_action('woocommerce_order_status_changed', [$this, 'maybe_start_credit_term'], 20, 4);
        add_action('woocommerce_order_status_changed', [$this, 'maybe_log_operational_status_transition'], 30, 4);
    }

    public static function can_access($user = null) {
        if (!class_exists('RKM_Permissions')) {
            return false;
        }

        return RKM_Permissions::is_rkm_admin($user) || RKM_Permissions::is_rkm_vendor($user);
    }

    public static function get_section_key() {
        return self::SECTION_KEY;
    }

    public static function get_section_url() {
        return home_url('/mi-cuenta/panel/?section=' . self::SECTION_KEY);
    }

    public static function can_confirm($user = null) {
        return class_exists('RKM_Permissions') && RKM_Permissions::is_rkm_admin($user);
    }

    public static function get_nonce_action() {
        return self::NONCE_ACTION;
    }

    public static function get_credit_days() {
        return 20;
    }

    public static function get_audit_actor_context($user = null) {
        if (!$user instanceof WP_User) {
            $user = wp_get_current_user();
        }

        if (!$user instanceof WP_User) {
            return [
                'user_label' => 'Sistema',
                'role_label' => 'Sistema',
            ];
        }

        $user_label = $user->display_name !== ''
            ? $user->display_name
            : ($user->user_login !== '' ? $user->user_login : ('Usuario #' . (int) $user->ID));

        return [
            'user_label' => $user_label,
            'role_label' => self::get_user_role_label_from_user($user),
        ];
    }

    public static function add_audit_event($order, $action, $details = '', $user = null, $timestamp = null, $old_value = null, $new_value = null) {
        if (!$order || !method_exists($order, 'get_id')) {
            return '';
        }

        $action = self::clean_audit_text($action);
        $details = self::clean_audit_text($details);
        $title = $action !== '' ? $action : 'Movimiento operativo';
        $message = $details !== '' ? $details : $title;

        if (class_exists('RKM_Order_Audit_Log')) {
            $inserted = RKM_Order_Audit_Log::add_event(
                $order->get_id(),
                $title,
                $title,
                $message,
                $old_value,
                $new_value
            );

            if ($inserted) {
                return $message;
            }
        }

        if (method_exists($order, 'add_order_note')) {
            $context = self::get_audit_actor_context($user);
            $timestamp = is_numeric($timestamp) ? (int) $timestamp : current_time('timestamp');
            $legacy_message = sprintf(
                '[RKM AUDIT] %s por %s (%s) el %s.',
                $title,
                $context['user_label'],
                $context['role_label'],
                wp_date('d/m/Y H:i', $timestamp)
            );

            if ($message !== '') {
                $legacy_message .= "\n" . $message;
            }

            $order->add_order_note($legacy_message, false, true);
        }

        return $message;
    }

    private static function clean_audit_text($value) {
        $value = html_entity_decode(wp_strip_all_tags((string) $value), ENT_QUOTES, 'UTF-8');
        $value = str_replace("\xc2\xa0", ' ', $value);

        return trim(preg_replace('/\s+/', ' ', $value));
    }

    private static function get_user_role_label_from_user($user) {
        if (!$user instanceof WP_User) {
            return 'Sistema';
        }

        $roles = is_array($user->roles) ? $user->roles : [];

        if (empty($roles)) {
            return 'Usuario';
        }

        $role = (string) reset($roles);
        $map = [
            'administrator' => 'Administrador',
            'shop_manager' => 'Encargado',
            'seller' => 'Vendedor',
            'vendor' => 'Vendedor',
            'vendedor' => 'Vendedor',
            'customer' => 'Cliente',
        ];

        if (isset($map[$role])) {
            return $map[$role];
        }

        return ucwords(str_replace(['-', '_'], ' ', $role));
    }

    public static function get_credit_context($order) {
        if (is_numeric($order)) {
            $order = function_exists('wc_get_order') ? wc_get_order(absint($order)) : null;
        }

        if (!$order || !method_exists($order, 'get_meta')) {
            return [
                'has_credit' => false,
                'days' => self::get_credit_days(),
            ];
        }

        $payment_term_key = (string) $order->get_meta('_rkm_payment_term', true);
        $credit_balance = (float) $order->get_meta('_rkm_credit_balance', true);
        $started_at_raw = (string) $order->get_meta('_rkm_credit_started_at', true);
        $due_at_raw = (string) $order->get_meta('_rkm_credit_due_at', true);
        $days = absint($order->get_meta('_rkm_credit_days', true));
        $days = $days > 0 ? $days : self::get_credit_days();
        $is_credit_term = in_array($payment_term_key, ['credit', 'mixed'], true);
        $has_credit = $is_credit_term && $credit_balance > 0;
        $started_timestamp = $started_at_raw !== '' ? strtotime($started_at_raw) : 0;
        $due_timestamp = $due_at_raw !== '' ? strtotime($due_at_raw) : 0;
        $completed = method_exists($order, 'get_date_completed') ? $order->get_date_completed() : null;
        $delivery_timestamp = $completed ? (int) $completed->getTimestamp() : 0;

        if ($started_timestamp <= 0 && $delivery_timestamp > 0 && $has_credit) {
            $started_timestamp = $delivery_timestamp;
        }

        if ($due_timestamp <= 0 && $started_timestamp > 0 && $has_credit) {
            $due_timestamp = $started_timestamp + (DAY_IN_SECONDS * $days);
        }

        $now = current_time('timestamp');
        $remaining_days = 0;
        if ($due_timestamp > 0) {
            $remaining_seconds = $due_timestamp - $now;
            $remaining_days = (int) floor($remaining_seconds / DAY_IN_SECONDS);
        }

        $started_label = $started_timestamp > 0 ? wp_date('d/m/Y', $started_timestamp) : '';
        $due_label = $due_timestamp > 0 ? wp_date('d/m/Y', $due_timestamp) : '';
        $delivery_label = $delivery_timestamp > 0 ? wp_date('d/m/Y', $delivery_timestamp) : '';

        $status_label = '';

        if (!$has_credit) {
            $status_label = '';
        } elseif ($started_timestamp <= 0) {
            $status_label = 'El plazo de 20 dias comenzara a correr cuando el pedido sea marcado como entregado.';
        } elseif ($remaining_days >= 0) {
            $status_label = $remaining_days === 0
                ? 'Vence hoy'
                : sprintf('%d dias restantes', $remaining_days);
        } else {
            $status_label = sprintf('Vencido hace %d dias', abs($remaining_days));
        }

        $payment_term_label = (string) $order->get_meta('_rkm_payment_term_label', true);
        if ($payment_term_label === '') {
            $payment_term_label = (string) $order->get_meta('_rkm_payment_term', true);
        }
        if ($payment_term_label === '') {
            $payment_term_label = '-';
        }

        return [
            'has_credit' => $has_credit,
            'days' => $days,
            'payment_term_key' => $payment_term_key,
            'payment_term_label' => $payment_term_label,
            'credit_balance' => $credit_balance,
            'started_at' => $started_at_raw !== '' ? $started_at_raw : '',
            'started_label' => $started_label,
            'due_at' => $due_at_raw !== '' ? $due_at_raw : ($due_timestamp > 0 ? wp_date('Y-m-d H:i:s', $due_timestamp) : ''),
            'due_label' => $due_label,
            'delivery_label' => $delivery_label,
            'remaining_days' => $remaining_days,
            'status_label' => $status_label,
            'notice' => $started_timestamp <= 0
                ? 'El cliente dispone de 20 dias de credito desde la fecha de entrega del pedido.'
                : sprintf('El cliente dispone de 20 dias de credito desde la entrega del pedido. Vence el %s.', $due_label),
        ];
    }

    public function maybe_start_credit_term($order_id, $old_status, $new_status, $order) {
        if ($new_status !== 'completed' || !$order) {
            return;
        }

        $credit_context = self::get_credit_context($order);
        $user = wp_get_current_user();
        $delivery_label = wp_date('d/m/Y', current_time('timestamp'));
        $previous_credit_started_at = $order->get_meta('_rkm_credit_started_at', true);
        $previous_credit_due_at = $order->get_meta('_rkm_credit_due_at', true);
        $previous_credit_days = $order->get_meta('_rkm_credit_days', true);

        self::add_audit_event(
            $order,
            'Pedido entregado',
            'Pedido marcado como entregado.',
            $user,
            current_time('timestamp'),
            $old_status,
            'completed'
        );

        if (empty($credit_context['has_credit'])) {
            return;
        }

        if ($order->get_meta('_rkm_credit_started_at', true) !== '' && $order->get_meta('_rkm_credit_due_at', true) !== '') {
            return;
        }

        $started_timestamp = current_time('timestamp');
        $days = !empty($credit_context['days']) ? absint($credit_context['days']) : self::get_credit_days();
        $due_timestamp = $started_timestamp + (DAY_IN_SECONDS * $days);
        $started_at = wp_date('Y-m-d H:i:s', $started_timestamp);
        $due_at = wp_date('Y-m-d H:i:s', $due_timestamp);
        $due_label = wp_date('d/m/Y', $due_timestamp);

        $order->update_meta_data('_rkm_credit_started_at', $started_at);
        $order->update_meta_data('_rkm_credit_due_at', $due_at);
        $order->update_meta_data('_rkm_credit_days', $days);
        $order->save();

        self::add_audit_event(
            $order,
            sprintf('Plazo de credito de %d dias iniciado', $days),
            sprintf(
                'Se inicio plazo de credito de %d dias desde la entrega. Entregado: %s. Vencimiento: %s.',
                $days,
                $delivery_label,
                $due_label
            ),
            $user,
            $started_timestamp,
            [
                'started_at' => $previous_credit_started_at,
                'due_at' => $previous_credit_due_at,
                'days' => $previous_credit_days,
            ],
            [
                'started_at' => $started_at,
                'due_at' => $due_at,
                'days' => $days,
            ]
        );
    }

    public function maybe_log_operational_status_transition($order_id, $old_status, $new_status, $order) {
        if (!$order || $old_status === $new_status) {
            return;
        }

        $tracked_statuses = [
            class_exists('RKM_Order_Statuses') ? RKM_Order_Statuses::READY : 'rkm-ready',
            class_exists('RKM_Order_Statuses') ? RKM_Order_Statuses::DISPATCHED : 'rkm-dispatched',
            'cancelled',
        ];

        if (!in_array($new_status, $tracked_statuses, true)) {
            return;
        }

        $user = wp_get_current_user();
        $status_label = self::get_operational_status_label($new_status);
        $old_label = self::get_operational_status_label($old_status);

        self::add_audit_event(
            $order,
            $status_label !== '' ? $status_label : 'Estado cambiado',
            sprintf('Estado cambiado de %s a %s.', $old_label, $status_label),
            $user,
            current_time('timestamp'),
            $old_status,
            $new_status
        );
    }

    public static function get_list_statuses() {
        if (!class_exists('RKM_Order_Statuses')) {
            return [
                'rkm-review',
                'rkm-confirmed',
                'rkm-warehouse',
                'rkm-ready',
                'rkm-dispatched',
                self::LEGACY_PENDING,
                self::LEGACY_REVIEW,
                self::LEGACY_PROCESSING,
            ];
        }

        return array_values(array_unique(array_merge(
            RKM_Order_Statuses::get_operational_statuses(),
            [
                self::LEGACY_PENDING,
                self::LEGACY_REVIEW,
                self::LEGACY_PROCESSING,
            ]
        )));
    }

    public static function get_confirmable_statuses() {
        return self::get_review_statuses();
    }

    public static function get_editable_statuses() {
        return self::get_confirmable_statuses();
    }

    public static function get_review_statuses() {
        return [
            class_exists('RKM_Order_Statuses') ? RKM_Order_Statuses::REVIEW : 'rkm-review',
            self::LEGACY_PENDING,
            self::LEGACY_REVIEW,
        ];
    }

    public static function can_edit($user = null) {
        return self::can_confirm($user);
    }

    public function ajax_confirm_order() {
        if (!check_ajax_referer(self::NONCE_ACTION, 'nonce', false)) {
            wp_send_json_error(['message' => 'Solicitud invalida. Actualiza la pagina e intenta nuevamente.'], 403);
        }

        if (!is_user_logged_in() || !self::can_confirm()) {
            wp_send_json_error(['message' => 'No tenes permiso para confirmar pedidos.'], 403);
        }

        if (!function_exists('wc_get_order') || !class_exists('RKM_Order_Statuses')) {
            wp_send_json_error(['message' => 'WooCommerce no esta disponible.'], 500);
        }

        $order_id = isset($_POST['order_id']) ? absint(wp_unslash($_POST['order_id'])) : 0;

        if ($order_id <= 0) {
            wp_send_json_error(['message' => 'Pedido invalido.'], 400);
        }

        $order = wc_get_order($order_id);

        if (!$order) {
            wp_send_json_error(['message' => 'Pedido no encontrado.'], 404);
        }

        if (!in_array($order->get_status(), self::get_confirmable_statuses(), true)) {
            wp_send_json_error(['message' => 'Solo se pueden confirmar pedidos en revision o pendientes.'], 409);
        }

        $stock_result = $this->reduce_stock_for_confirmation($order);

        if (is_wp_error($stock_result)) {
            wp_send_json_error(['message' => $stock_result->get_error_message()], 500);
        }

        $user = wp_get_current_user();
        $confirmed_at = current_time('mysql');
        self::add_audit_event(
            $order,
            'Pedido confirmado',
            'Pedido confirmado correctamente. Stock descontado correctamente.',
            $user,
            current_time('timestamp'),
            $order->get_status(),
            RKM_Order_Statuses::CONFIRMED
        );

        $order->update_status(RKM_Order_Statuses::CONFIRMED, '');

        wp_send_json_success([
            'message' => 'Pedido confirmado correctamente.',
            'order' => $this->format_order_row($order),
            'confirmed_at' => $confirmed_at,
        ]);
    }

    public function ajax_send_to_warehouse() {
        if (!check_ajax_referer(self::NONCE_ACTION, 'nonce', false)) {
            wp_send_json_error(['message' => 'Solicitud invalida. Actualiza la pagina e intenta nuevamente.'], 403);
        }

        if (!is_user_logged_in() || !self::can_confirm()) {
            wp_send_json_error(['message' => 'No tenes permiso para enviar pedidos a almacen.'], 403);
        }

        if (!function_exists('wc_get_order') || !class_exists('RKM_Order_Statuses')) {
            wp_send_json_error(['message' => 'WooCommerce no esta disponible.'], 500);
        }

        $order_id = isset($_POST['order_id']) ? absint(wp_unslash($_POST['order_id'])) : 0;

        if ($order_id <= 0) {
            wp_send_json_error(['message' => 'Pedido invalido.'], 400);
        }

        $order = wc_get_order($order_id);

        if (!$order) {
            wp_send_json_error(['message' => 'Pedido no encontrado.'], 404);
        }

        if ($order->get_status() !== RKM_Order_Statuses::CONFIRMED) {
            wp_send_json_error(['message' => 'Solo se pueden enviar a almacen pedidos confirmados.'], 409);
        }

        $user = wp_get_current_user();
        $sent_at = current_time('mysql');
        self::add_audit_event(
            $order,
            'Enviado a almacen',
            'Pedido enviado a almacen correctamente.',
            $user,
            current_time('timestamp'),
            $order->get_status(),
            RKM_Order_Statuses::WAREHOUSE
        );

        $order->update_meta_data('_rkm_sent_to_warehouse_at', $sent_at);
        $order->update_meta_data('_rkm_sent_to_warehouse_by', get_current_user_id());
        $order->save();
        $order->update_status(RKM_Order_Statuses::WAREHOUSE, '');

        wp_send_json_success([
            'message' => 'Pedido enviado a almacen correctamente.',
            'order' => $this->format_order_row($order),
            'sent_at' => $sent_at,
        ]);
    }

    public function ajax_update_order() {
        if (!check_ajax_referer(self::NONCE_ACTION, 'nonce', false)) {
            wp_send_json_error(['message' => 'Solicitud invalida. Actualiza la pagina e intenta nuevamente.'], 403);
        }

        if (!is_user_logged_in() || !self::can_edit()) {
            wp_send_json_error(['message' => 'No tenes permiso para editar pedidos operativos.'], 403);
        }

        if (!function_exists('wc_get_order')) {
            wp_send_json_error(['message' => 'WooCommerce no esta disponible.'], 500);
        }

        $order_id = isset($_POST['order_id']) ? absint(wp_unslash($_POST['order_id'])) : 0;

        if ($order_id <= 0) {
            wp_send_json_error(['message' => 'Pedido invalido.'], 400);
        }

        $order = wc_get_order($order_id);

        if (!$order) {
            wp_send_json_error(['message' => 'Pedido no encontrado.'], 404);
        }

        if (!in_array($order->get_status(), self::get_editable_statuses(), true)) {
            wp_send_json_error(['message' => 'Solo se pueden editar pedidos en revision o pendientes.'], 409);
        }

        $previous_payment_snapshot = [
            'term_key' => (string) $order->get_meta('_rkm_payment_term', true),
            'term_label' => $this->get_order_meta_label($order, '_rkm_payment_term_label', '_rkm_payment_term'),
            'payment_method_id' => (string) $order->get_meta('_rkm_payment_method_id', true),
            'payment_method_label' => $this->get_order_meta_label($order, '_rkm_payment_method_label', '_rkm_payment_method_id'),
            'upfront_amount' => (float) $order->get_meta('_rkm_upfront_amount', true),
            'credit_balance' => (float) $order->get_meta('_rkm_credit_balance', true),
            'cash_discount_amount' => (float) $order->get_meta('_rkm_cash_discount_amount', true),
            'payment_note' => (string) $order->get_meta('_rkm_payment_note', true),
        ];
        $items_payload = isset($_POST['items']) ? wp_unslash($_POST['items']) : [];
        $items_payload = is_array($items_payload) ? $items_payload : [];
        $quantity_result = $this->update_order_item_quantities($order, $items_payload);

        if (is_wp_error($quantity_result)) {
            wp_send_json_error(['message' => $quantity_result->get_error_message()], 400);
        }

        $order->calculate_totals();

        $payment_update_enabled = isset($_POST['payment_update_enabled']) && wp_unslash($_POST['payment_update_enabled']) === '1';
        $payment_context = null;

        if ($payment_update_enabled) {
            $this->remove_cash_discount_fees($order);
            $order->calculate_totals();

            $payment_context = $this->resolve_payment_context_from_request($order);

            if (is_wp_error($payment_context)) {
                wp_send_json_error(['message' => $payment_context->get_error_message()], 400);
            }

            $this->apply_payment_context_to_order($order, $payment_context);
        } else {
            $order->save();
        }

        $user = wp_get_current_user();
        $changes = isset($quantity_result['changes']) && is_array($quantity_result['changes'])
            ? $quantity_result['changes']
            : [];
        $quantity_items = isset($quantity_result['items']) && is_array($quantity_result['items'])
            ? $quantity_result['items']
            : [];

        if (!empty($changes)) {
            $quantity_old = [];
            $quantity_new = [];
            foreach ($quantity_items as $change) {
                if (!is_array($change)) {
                    continue;
                }

                $item_name = isset($change['name']) ? $this->clean_text($change['name']) : '';
                $old_quantity = isset($change['old_quantity']) ? (int) $change['old_quantity'] : 0;
                $new_quantity = isset($change['new_quantity']) ? (int) $change['new_quantity'] : 0;
                $quantity_old[] = [
                    'name' => $item_name,
                    'quantity' => $old_quantity,
                ];
                $quantity_new[] = [
                    'name' => $item_name,
                    'quantity' => $new_quantity,
                ];
            }

            self::add_audit_event(
                $order,
                'Cantidades modificadas',
                'Cantidades actualizadas: ' . implode('; ', $changes) . '.',
                $user,
                current_time('timestamp'),
                $quantity_old,
                $quantity_new
            );
        }

        if ($payment_update_enabled && is_array($payment_context)) {
            $payment_old = [
                'term_key' => $previous_payment_snapshot['term_key'],
                'term_label' => $previous_payment_snapshot['term_label'],
                'payment_method_id' => $previous_payment_snapshot['payment_method_id'],
                'payment_method_label' => $previous_payment_snapshot['payment_method_label'],
                'upfront_amount' => $previous_payment_snapshot['upfront_amount'],
                'credit_balance' => $previous_payment_snapshot['credit_balance'],
                'cash_discount_amount' => $previous_payment_snapshot['cash_discount_amount'],
                'payment_note' => $previous_payment_snapshot['payment_note'],
            ];
            $payment_new = [
                'term_key' => $payment_context['term_key'],
                'term_label' => $payment_context['term_label'],
                'payment_method_id' => $payment_context['payment_method_id'],
                'payment_method_label' => $payment_context['payment_method_label'],
                'upfront_amount' => $payment_context['upfront_amount'],
                'credit_balance' => $payment_context['credit_balance'],
                'cash_discount_amount' => $payment_context['cash_discount_amount'],
                'payment_note' => $payment_context['payment_note'],
            ];

            $payment_changed = $payment_old !== $payment_new;

            if ($payment_changed) {
                $payment_details = [];

                if ($payment_old['term_label'] !== $payment_new['term_label']) {
                    $payment_details[] = sprintf(
                        'Condicion de pago modificada: %s -> %s.',
                        $payment_old['term_label'],
                        $payment_new['term_label']
                    );
                }

                if ($payment_old['payment_method_label'] !== $payment_new['payment_method_label']) {
                    $payment_details[] = sprintf(
                        'Forma de pago modificada: %s -> %s.',
                        $payment_old['payment_method_label'] !== '' ? $payment_old['payment_method_label'] : 'Sin forma de pago',
                        $payment_new['payment_method_label'] !== '' ? $payment_new['payment_method_label'] : 'Sin forma de pago'
                    );
                }

                if ((float) $payment_old['upfront_amount'] !== (float) $payment_new['upfront_amount']) {
                    $payment_details[] = sprintf(
                        'Monto inicial: %s -> %s.',
                        $this->clean_money_text(wc_price((float) $payment_old['upfront_amount'])),
                        $this->clean_money_text(wc_price((float) $payment_new['upfront_amount']))
                    );
                }

                if ((float) $payment_old['credit_balance'] !== (float) $payment_new['credit_balance']) {
                    $payment_details[] = sprintf(
                        'Saldo a credito: %s -> %s.',
                        $this->clean_money_text(wc_price((float) $payment_old['credit_balance'])),
                        $this->clean_money_text(wc_price((float) $payment_new['credit_balance']))
                    );
                }

                if ((float) $payment_old['cash_discount_amount'] !== (float) $payment_new['cash_discount_amount']) {
                    $payment_details[] = sprintf(
                        'Descuento contado: %s -> %s.',
                        $this->clean_money_text(wc_price((float) $payment_old['cash_discount_amount'])),
                        $this->clean_money_text(wc_price((float) $payment_new['cash_discount_amount']))
                    );
                }

                if ($payment_old['payment_note'] !== $payment_new['payment_note']) {
                    $payment_details[] = sprintf(
                        'Nota de pago: %s -> %s.',
                        $payment_old['payment_note'] !== '' ? $payment_old['payment_note'] : 'Sin nota',
                        $payment_new['payment_note'] !== '' ? $payment_new['payment_note'] : 'Sin nota'
                    );
                }

                self::add_audit_event(
                    $order,
                    'Condicion de pago modificada',
                    implode("\n", $payment_details),
                    $user,
                    current_time('timestamp'),
                    $payment_old,
                    $payment_new
                );
            }
        }

        $order->save();

        wp_send_json_success([
            'message' => 'Pedido actualizado correctamente.',
            'order' => $this->format_order_row($order),
        ]);
    }

    private function reduce_stock_for_confirmation($order) {
        if ($order->get_meta('_rkm_stock_reduced', true) === 'yes') {
            self::add_audit_event(
                $order,
                'Stock descontado',
                'Stock no descontado nuevamente: _rkm_stock_reduced ya estaba marcado como yes.',
                null,
                null,
                'yes',
                'yes'
            );
            return true;
        }

        if (!function_exists('wc_reduce_stock_levels')) {
            return new WP_Error('rkm_stock_function_missing', 'No se pudo descontar stock porque WooCommerce no esta disponible.');
        }

        try {
            wc_reduce_stock_levels($order->get_id());
        } catch (Throwable $exception) {
            return new WP_Error(
                'rkm_stock_reduce_failed',
                'No se pudo descontar stock: ' . $exception->getMessage()
            );
        }

        $order->update_meta_data('_rkm_stock_reduced', 'yes');
        $order->save();
        self::add_audit_event(
            $order,
            'Stock descontado',
            'Stock descontado al confirmar pedido RKM.',
            null,
            null,
            'no',
            'yes'
        );

        return true;
    }

    public function render_page($data = []) {
        if (!self::can_access()) {
            wp_safe_redirect(class_exists('RKM_Auth') ? RKM_Auth::get_redirect_url_for_user() : home_url('/mi-cuenta/panel/'));
            exit;
        }

        $user = wp_get_current_user();
        $view_data = array_merge($data, [
            'page_title' => 'Pedidos operativos',
            'page_subtitle' => 'Consola de revision para pedidos en flujo ERP RKM.',
            'current_section' => self::SECTION_KEY,
            'section_url' => self::get_section_url(),
            'operational_orders' => $this->get_operational_orders($user),
            'is_admin_context' => class_exists('RKM_Permissions') && RKM_Permissions::is_rkm_admin($user),
            'is_vendor_context' => class_exists('RKM_Permissions') && RKM_Permissions::is_rkm_vendor($user),
            'can_confirm_orders' => self::can_confirm($user),
            'can_edit_orders' => self::can_edit($user),
            'payment_terms' => class_exists('RKM_Payment_Terms') ? RKM_Payment_Terms::get_active_terms() : [],
            'payment_methods' => class_exists('RKM_Payment_Methods') ? RKM_Payment_Methods::get_active_methods() : [],
            'payment_terms_settings' => class_exists('RKM_Payment_Terms') ? RKM_Payment_Terms::get_settings() : [],
        ]);

        $template = RKM_CORE_PATH . 'templates/admin/operational-orders.php';

        if (file_exists($template)) {
            $data = $view_data;
            include $template;
        }
    }

    private function get_operational_orders($user) {
        if (!function_exists('wc_get_orders') || !class_exists('RKM_Order_Statuses')) {
            return [];
        }

        $query_args = [
            'limit'   => self::ORDERS_LIMIT,
            'orderby' => 'date',
            'order'   => 'DESC',
            'status'  => self::get_list_statuses(),
        ];

        if (
            class_exists('RKM_Permissions')
            && RKM_Permissions::is_rkm_vendor($user)
            && !RKM_Permissions::is_rkm_admin($user)
        ) {
            $customer_ids = class_exists('RKM_Assignments')
                ? array_values(array_filter(array_map('intval', RKM_Assignments::get_assigned_customer_ids((int) $user->ID))))
                : [];

            if (empty($customer_ids)) {
                return [];
            }

            $query_args['customer_id'] = $customer_ids;
        }

        $orders = wc_get_orders($query_args);

        if (empty($orders)) {
            return [];
        }

        return array_map([$this, 'format_order_row'], $orders);
    }

    private function format_order_row($order) {
        $customer_name = trim($order->get_formatted_billing_full_name());

        if ($customer_name === '') {
            $customer = $order->get_user();
            $customer_name = $customer instanceof WP_User && $customer->display_name
                ? $customer->display_name
                : ($order->get_billing_email() ?: 'Cliente sin nombre');
        }

        $date_created = $order->get_date_created();
        $status = $order->get_status();
        $assigned_vendor = $this->get_assigned_vendor_data($order);
        $operational_history = $this->get_operational_history($order);
        $credit_context = self::get_credit_context($order);

        return [
            'id' => (int) $order->get_id(),
            'number' => $order->get_order_number(),
            'customer_name' => $customer_name,
            'customer_email' => $order->get_billing_email() ?: '-',
            'customer_phone' => $order->get_billing_phone() ?: '-',
            'date' => $date_created ? $date_created->date_i18n('d/m/Y H:i') : 'Sin fecha',
            'total' => $this->clean_money_text($order->get_formatted_order_total()),
            'status' => $status,
            'status_label' => $this->get_operational_status_label($status),
            'payment_term' => $this->get_order_meta_label($order, '_rkm_payment_term_label', '_rkm_payment_term'),
            'payment_term_key' => (string) $order->get_meta('_rkm_payment_term', true),
            'payment_method' => $this->get_order_meta_label($order, '_rkm_payment_method_label', '_rkm_payment_method_id'),
            'payment_method_id' => (string) $order->get_meta('_rkm_payment_method_id', true),
            'upfront_amount' => (float) $order->get_meta('_rkm_upfront_amount', true),
            'upfront_amount_display' => $this->clean_money_text(wc_price((float) $order->get_meta('_rkm_upfront_amount', true))),
            'credit_balance' => (float) $order->get_meta('_rkm_credit_balance', true),
            'credit_balance_display' => $this->clean_money_text(wc_price((float) $order->get_meta('_rkm_credit_balance', true))),
            'cash_discount_amount' => (float) $order->get_meta('_rkm_cash_discount_amount', true),
            'cash_discount_display' => $this->clean_money_text(wc_price((float) $order->get_meta('_rkm_cash_discount_amount', true))),
            'payment_note' => (string) $order->get_meta('_rkm_payment_note', true),
            'customer_note' => (string) $order->get_customer_note(),
            'assigned_vendor_id' => (int) $assigned_vendor['id'],
            'assigned_vendor_name' => $assigned_vendor['name'],
            'assigned_vendor_email' => $assigned_vendor['email'],
            'assigned_vendor_role' => $assigned_vendor['role'],
            'assigned_vendor_label' => $assigned_vendor['label'],
            'credit_context' => $credit_context,
            'credit_has_balance' => !empty($credit_context['has_credit']),
            'credit_days' => isset($credit_context['days']) ? (int) $credit_context['days'] : self::get_credit_days(),
            'credit_started_at' => $credit_context['started_at'] ?? '',
            'credit_started_label' => $credit_context['started_label'] ?? '',
            'credit_due_at' => $credit_context['due_at'] ?? '',
            'credit_due_label' => $credit_context['due_label'] ?? '',
            'credit_delivery_label' => $credit_context['delivery_label'] ?? '',
            'credit_remaining_days' => isset($credit_context['remaining_days']) ? (int) $credit_context['remaining_days'] : 0,
            'credit_status_label' => $credit_context['status_label'] ?? '',
            'credit_notice' => $credit_context['notice'] ?? '',
            'operational_history' => $operational_history,
            'audit_timeline' => $operational_history,
            'internal_notes' => $operational_history,
            'items' => $this->format_order_items($order),
            'is_editable' => in_array($status, self::get_editable_statuses(), true),
        ];
    }

    private function get_operational_status_label($status) {
        $status = (string) $status;

        if (in_array($status, self::get_review_statuses(), true)) {
            return 'En revision';
        }

        if (function_exists('wc_get_order_status_name')) {
            return wc_get_order_status_name($status);
        }

        return ucfirst($status);
    }

    private function get_assigned_vendor_data($order) {
        $default = [
            'id' => 0,
            'name' => '',
            'email' => '',
            'role' => '',
            'label' => 'Sin vendedor asignado',
        ];

        if (!class_exists('RKM_Assignments')) {
            return $default;
        }

        $customer = $this->get_order_customer_user($order);

        if (!$customer instanceof WP_User || empty($customer->ID)) {
            return $default;
        }

        $assigned_vendor_id = (int) RKM_Assignments::get_assigned_vendor_id($customer);

        if ($assigned_vendor_id <= 0) {
            return $default;
        }

        $vendor = get_user_by('id', $assigned_vendor_id);

        if (!$vendor instanceof WP_User) {
            return $default;
        }

        $vendor_name = $vendor->display_name !== '' ? $vendor->display_name : $vendor->user_login;
        $vendor_email = $vendor->user_email !== '' ? $vendor->user_email : '';
        $vendor_role = $this->get_user_role_label($vendor);

        return [
            'id' => (int) $vendor->ID,
            'name' => $vendor_name,
            'email' => $vendor_email,
            'role' => $vendor_role,
            'label' => $vendor_email !== ''
                ? sprintf('%s · %s', $vendor_name, $vendor_email)
                : $vendor_name,
        ];
    }

    private function get_order_customer_user($order) {
        if (method_exists($order, 'get_user')) {
            $customer = $order->get_user();
            if ($customer instanceof WP_User) {
                return $customer;
            }
        }

        $billing_email = $order->get_billing_email();

        if ($billing_email !== '') {
            $customer = get_user_by('email', $billing_email);

            if ($customer instanceof WP_User) {
                return $customer;
            }
        }

        return null;
    }

    private function get_user_role_label($user) {
        if (!$user instanceof WP_User) {
            return 'Sistema';
        }

        $roles = is_array($user->roles) ? $user->roles : [];

        if (empty($roles)) {
            return 'Usuario';
        }

        $role = (string) reset($roles);
        $map = [
            'administrator' => 'Administrador',
            'shop_manager' => 'Encargado',
            'seller' => 'Vendedor',
            'vendor' => 'Vendedor',
            'vendedor' => 'Vendedor',
            'customer' => 'Cliente',
        ];

        if (isset($map[$role])) {
            return $map[$role];
        }

        return ucwords(str_replace(['-', '_'], ' ', $role));
    }

    private function get_order_meta_label($order, $label_key, $fallback_key) {
        $label = (string) $order->get_meta($label_key, true);

        if ($label !== '') {
            return $label;
        }

        $fallback = (string) $order->get_meta($fallback_key, true);

        return $fallback !== '' ? $fallback : '-';
    }

    private function format_order_items($order) {
        $items = [];

        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            $quantity = max(1, (int) $item->get_quantity());
            $line_subtotal = (float) $item->get_subtotal();
            $line_total = (float) $item->get_total();
            $unit_price = $quantity > 0 ? $line_total / $quantity : 0;
            $unit_subtotal = $quantity > 0 ? $line_subtotal / $quantity : $unit_price;
            $stock_quantity = $product ? $product->get_stock_quantity() : null;
            $max_quantity = $product && $product->managing_stock() && $stock_quantity !== null ? max(0, (int) $stock_quantity) : null;

            $items[] = [
                'item_id' => (int) $item_id,
                'product_id' => $product ? (int) $product->get_id() : 0,
                'name' => $item->get_name(),
                'sku' => $product ? $product->get_sku() : '',
                'quantity' => $quantity,
                'max_quantity' => $max_quantity,
                'stock_label' => $max_quantity !== null ? ('Stock disponible: ' . $max_quantity) : '',
                'unit_price_raw' => $unit_price,
                'unit_subtotal_raw' => $unit_subtotal,
                'total_raw' => $line_total,
                'unit_price' => $this->clean_money_text(wc_price($unit_price)),
                'total' => $this->clean_money_text(wc_price($line_total)),
            ];
        }

        return $items;
    }

    private function clean_money_text($value) {
        return html_entity_decode(wp_strip_all_tags((string) $value), ENT_QUOTES, 'UTF-8');
    }

    private function get_internal_notes($order) {
        return $this->get_operational_history($order);
    }

    private function get_operational_history($order) {
        if (!$order || !method_exists($order, 'get_id')) {
            return [];
        }

        if (class_exists('RKM_Order_Audit_Log')) {
            $events = RKM_Order_Audit_Log::get_events($order->get_id());

            if (!empty($events)) {
                return $events;
            }
        }

        if (!function_exists('wc_get_order_notes')) {
            return [];
        }

        $notes = wc_get_order_notes([
            'order_id' => $order->get_id(),
            'type' => 'internal',
            'limit' => -1,
            'orderby' => 'date_created',
            'order' => 'DESC',
        ]);

        if (empty($notes)) {
            return [];
        }

        $history = array_map(function ($note) {
            return $this->format_operational_history_note($note);
        }, $notes);

        $history = array_filter($history, static function ($entry) {
            return is_array($entry);
        });

        return array_values($history);
    }

    private function format_operational_history_note($note) {
        $content = $this->clean_text($note->content ?? '');
        $author = $this->clean_text($note->added_by ?? $note->author ?? '');
        $author_user = null;

        if ($author !== '') {
            $author_user = get_user_by('login', sanitize_user($author, true));

            if (!$author_user instanceof WP_User && is_numeric($author)) {
                $author_user = get_user_by('id', absint($author));
            }

            if (!$author_user instanceof WP_User) {
                $author_user = get_user_by('slug', sanitize_title($author));
            }
        }

        $user_label = $author_user instanceof WP_User
            ? ($author_user->display_name !== '' ? $author_user->display_name : $author_user->user_login)
            : ($author !== '' ? $author : 'Sistema');
        $role_label = $this->get_user_role_label($author_user);
        $action_label = $this->classify_operational_history_action($content);
        $detail = $this->build_operational_history_detail($content, $action_label);
        $date_label = !empty($note->date_created)
            ? $this->clean_text($note->date_created->date_i18n('d/m/Y H:i'))
            : '';

        return [
            'date' => $date_label,
            'timestamp' => !empty($note->date_created) ? (int) $note->date_created->getTimestamp() : 0,
            'user' => $user_label,
            'role' => $role_label,
            'action' => $action_label,
            'detail' => $detail,
        ];
    }

    private function classify_operational_history_action($content) {
        $text = strtolower($this->clean_text($content));

        if ($text === '') {
            return 'Movimiento operativo';
        }

        if (strpos($text, 'pedido confirmado') !== false || strpos($text, 'confirmado por') !== false) {
            return 'Pedido confirmado';
        }

        if (strpos($text, 'pedido entregado') !== false || strpos($text, 'entregado por') !== false) {
            return 'Entregado';
        }

        if (strpos($text, 'pedido enviado a almacen') !== false || strpos($text, 'enviado a almacen') !== false) {
            return 'Enviado a almacén';
        }

        if (strpos($text, 'estado cambiado a listo para despacho') !== false || strpos($text, 'listo para despacho') !== false) {
            return 'Listo para despacho';
        }

        if (strpos($text, 'estado cambiado a despachado') !== false || strpos($text, 'despachado') !== false) {
            return 'Despachado';
        }

        if (strpos($text, 'stock descontado') !== false) {
            return 'Stock descontado';
        }

        if (strpos($text, 'pedido editado') !== false) {
            return 'Pedido editado';
        }

        if (strpos($text, 'pago configurado') !== false) {
            return 'Pago configurado';
        }

        if (strpos($text, 'plazo de credito') !== false || strpos($text, 'credito iniciado') !== false) {
            return 'Credito iniciado';
        }

        if (strpos($text, 'cantidades actualizadas') !== false || strpos($text, 'cantidad') !== false) {
            return 'Cantidades modificadas';
        }

        if (strpos($text, 'condicion de pago') !== false || strpos($text, 'forma de pago') !== false || strpos($text, 'monto inicial') !== false) {
            return 'Condición de pago modificada';
        }

        if (strpos($text, 'pedido cancelado') !== false || strpos($text, 'cancelado') !== false) {
            return 'Pedido cancelado';
        }

        if (strpos($text, 'pedido generado') !== false || strpos($text, 'pedido creado') !== false || strpos($text, 'pedido creacion') !== false) {
            return 'Pedido creado';
        }

        if (strpos($text, 'estado') !== false && strpos($text, 'cambi') !== false) {
            return 'Estado cambiado';
        }

        return 'Actualización operativa';
    }

    private function build_operational_history_detail($content, $action_label) {
        $detail = trim($content);

        if ($detail === '') {
            return $action_label;
        }

        return $detail;
    }

    private function clean_text($value) {
        $value = html_entity_decode(wp_strip_all_tags((string) $value), ENT_QUOTES, 'UTF-8');
        $value = str_replace("\xc2\xa0", ' ', $value);

        return trim(preg_replace('/\s+/', ' ', $value));
    }

    private function update_order_item_quantities($order, $items_payload) {
        if (empty($items_payload)) {
            return new WP_Error('rkm_items_empty', 'El pedido debe tener al menos un producto.');
        }

        $posted_quantities = [];

        foreach ($items_payload as $item_id => $quantity) {
            $item_id = absint($item_id);
            $quantity = absint($quantity);

            if ($item_id <= 0) {
                continue;
            }

            if ($quantity < 1) {
                return new WP_Error('rkm_quantity_invalid', 'Las cantidades deben ser mayores o iguales a 1.');
            }

            $posted_quantities[$item_id] = $quantity;
        }

        if (empty($posted_quantities)) {
            return new WP_Error('rkm_items_invalid', 'No se recibieron cantidades validas.');
        }

        $changes = [];
        $items = [];

        foreach ($order->get_items() as $item_id => $item) {
            if (!array_key_exists((int) $item_id, $posted_quantities)) {
                return new WP_Error('rkm_item_missing', 'Faltan productos del pedido en la solicitud.');
            }

            $quantity = (int) $posted_quantities[(int) $item_id];
            $current_quantity = max(1, (int) $item->get_quantity());
            $product = $item->get_product();

            if ($product && $product->managing_stock()) {
                $stock_quantity = $product->get_stock_quantity();

                if ($stock_quantity !== null && $quantity > (int) $stock_quantity) {
                    return new WP_Error(
                        'rkm_quantity_stock_invalid',
                        sprintf('No hay stock suficiente para %s. Disponible: %d.', $item->get_name(), max(0, (int) $stock_quantity))
                    );
                }
            }

            $unit_subtotal = $current_quantity > 0 ? ((float) $item->get_subtotal() / $current_quantity) : 0;
            $unit_total = $current_quantity > 0 ? ((float) $item->get_total() / $current_quantity) : $unit_subtotal;

            if ($unit_subtotal <= 0 && $product) {
                $unit_subtotal = (float) $product->get_price();
            }

            if ($unit_total <= 0) {
                $unit_total = $unit_subtotal;
            }

            if ($quantity !== $current_quantity) {
                $changes[] = sprintf('%s de %d a %d', $item->get_name(), $current_quantity, $quantity);
                $items[] = [
                    'item_id' => (int) $item_id,
                    'name' => $item->get_name(),
                    'old_quantity' => $current_quantity,
                    'new_quantity' => $quantity,
                ];
            }

            $item->set_quantity($quantity);
            $item->set_subtotal($unit_subtotal * $quantity);
            $item->set_total($unit_total * $quantity);
            $item->save();
        }

        return [
            'changes' => $changes,
            'items' => $items,
        ];
    }

    private function resolve_payment_context_from_request($order) {
        if (!class_exists('RKM_Payment_Terms')) {
            return new WP_Error('rkm_payment_terms_unavailable', 'Las condiciones de pago no estan disponibles.');
        }

        $payment_term_key = isset($_POST['payment_term'])
            ? sanitize_key(wp_unslash($_POST['payment_term']))
            : '';
        $payment_term = RKM_Payment_Terms::get_active_term($payment_term_key);

        if (!$payment_term) {
            return new WP_Error('rkm_payment_term_invalid', 'Selecciona una condicion de pago valida.');
        }

        $original_total = (float) $order->get_total();
        $cash_discount_percent = 0;
        $cash_discount_amount = 0;
        $upfront_amount = 0;
        $credit_balance = 0;
        $final_total = $original_total;

        if ($payment_term_key === 'cash') {
            $cash_discount_percent = RKM_Payment_Terms::get_cash_discount_percent();
            $cash_discount_amount = $this->round_money($original_total * ($cash_discount_percent / 100));
            $cash_discount_amount = min($original_total, max(0, $cash_discount_amount));
            $final_total = max(0, $original_total - $cash_discount_amount);
        }

        if ($payment_term_key === 'mixed') {
            $upfront_amount = isset($_POST['upfront_amount'])
                ? $this->round_money((float) wc_clean(wp_unslash($_POST['upfront_amount'])))
                : 0;

            if ($upfront_amount <= 0) {
                return new WP_Error('rkm_upfront_amount_required', 'Indica el monto inicial para la condicion de pago mixta.');
            }

            if ($upfront_amount > $final_total) {
                return new WP_Error('rkm_upfront_amount_invalid', 'El monto inicial no puede ser mayor al total del pedido.');
            }
        }

        if ($payment_term_key === 'credit') {
            $credit_balance = $final_total;
        } elseif ($payment_term_key === 'mixed') {
            $credit_balance = max(0, $final_total - $upfront_amount);
        }

        $needs_payment_method = in_array($payment_term_key, ['cash', 'mixed'], true);
        $payment_method = $needs_payment_method ? $this->resolve_payment_method_from_request() : null;

        if (is_wp_error($payment_method)) {
            return $payment_method;
        }

        $method_id = is_array($payment_method) && isset($payment_method['id']) ? sanitize_key($payment_method['id']) : '';
        $method_label = is_array($payment_method) && isset($payment_method['name']) ? sanitize_text_field($payment_method['name']) : '';

        return [
            'term_key'              => $payment_term_key,
            'term_label'            => $payment_term['label'],
            'original_total'        => $original_total,
            'final_total'           => $final_total,
            'cash_discount_percent' => $cash_discount_percent,
            'cash_discount_amount'  => $cash_discount_amount,
            'upfront_amount'        => $upfront_amount,
            'credit_balance'        => $credit_balance,
            'payment_method_id'     => $method_id,
            'payment_method_label'  => $method_label,
            'payment_note'          => isset($_POST['payment_note']) ? sanitize_textarea_field(wp_unslash($_POST['payment_note'])) : '',
        ];
    }

    private function resolve_payment_method_from_request() {
        if (!class_exists('RKM_Payment_Methods')) {
            return null;
        }

        $active_methods = RKM_Payment_Methods::get_active_methods();

        if (empty($active_methods)) {
            return null;
        }

        $payment_method_id = isset($_POST['payment_method_id'])
            ? sanitize_key(wp_unslash($_POST['payment_method_id']))
            : '';

        if ($payment_method_id === '') {
            return new WP_Error('rkm_payment_method_required', 'Selecciona una forma de pago para esta condicion.');
        }

        $payment_method = RKM_Payment_Methods::get_active_method($payment_method_id);

        if (!$payment_method) {
            return new WP_Error('rkm_payment_method_invalid', 'La forma de pago seleccionada no esta disponible.');
        }

        return $payment_method;
    }

    private function apply_payment_context_to_order($order, $payment_context) {
        $this->remove_cash_discount_fees($order);

        if (!empty($payment_context['cash_discount_amount'])) {
            $fee = new WC_Order_Item_Fee();
            $fee->set_name('Descuento pago contado');
            $fee->set_amount(-1 * (float) $payment_context['cash_discount_amount']);
            $fee->set_total(-1 * (float) $payment_context['cash_discount_amount']);
            $order->add_item($fee);
            $order->calculate_totals();
            $payment_context['final_total'] = (float) $order->get_total();
        }

        $order->update_meta_data('_rkm_payment_term', $payment_context['term_key']);
        $order->update_meta_data('_rkm_payment_term_label', $payment_context['term_label']);
        $order->update_meta_data('_rkm_cash_discount_percent', $payment_context['cash_discount_percent']);
        $order->update_meta_data('_rkm_cash_discount_amount', $payment_context['cash_discount_amount']);
        $order->update_meta_data('_rkm_original_total', $payment_context['original_total']);
        $order->update_meta_data('_rkm_final_total', $payment_context['final_total']);
        $order->update_meta_data('_rkm_upfront_amount', $payment_context['upfront_amount']);
        $order->update_meta_data('_rkm_credit_balance', $payment_context['credit_balance']);
        $order->update_meta_data('_rkm_payment_method_id', $payment_context['payment_method_id']);
        $order->update_meta_data('_rkm_payment_method_label', $payment_context['payment_method_label']);
        $order->update_meta_data('_rkm_payment_note', $payment_context['payment_note']);
        $order->save();
    }

    private function remove_cash_discount_fees($order) {
        foreach ($order->get_items('fee') as $item_id => $item) {
            if ($item->get_name() === 'Descuento pago contado') {
                $order->remove_item($item_id);
            }
        }

        $order->calculate_totals();
    }

    private function round_money($amount) {
        $decimals = function_exists('wc_get_price_decimals') ? wc_get_price_decimals() : 2;

        return round((float) $amount, $decimals);
    }
}
