<?php

if (!defined('ABSPATH')) {
    exit;
}

class RKM_Warehouse {

    const SECTION_KEY = 'almacen';
    const NONCE_ACTION = 'rkm_warehouse_nonce';
    const ORDERS_LIMIT = 100;
    const PICKING_CHECKLIST_META = '_rkm_warehouse_picking_checklist';
    const PICKING_COMPLETED_META = '_rkm_warehouse_picking_completed';
    const PICKING_INCIDENTS_META = '_rkm_warehouse_picking_incidents';
    const EVIDENCE_ATTACHMENT_IDS_META = '_rkm_warehouse_evidence_attachment_ids';
    const EVIDENCE_COUNT_META = '_rkm_warehouse_evidence_count';
    const EVIDENCE_MIN_FILES = 2;
    const EVIDENCE_MAX_FILE_SIZE = 5242880;
    const CREDIT_DAYS = 20;

    public function init() {
        add_action('wp_ajax_rkm_add_warehouse_note', [$this, 'ajax_add_note']);
        add_action('wp_ajax_rkm_save_warehouse_picking_progress', [$this, 'ajax_save_picking_progress']);
        add_action('wp_ajax_rkm_report_warehouse_picking_incident', [$this, 'ajax_report_picking_incident']);
        add_action('wp_ajax_rkm_mark_order_ready', [$this, 'ajax_mark_ready']);
        add_action('wp_ajax_rkm_mark_order_dispatched', [$this, 'ajax_mark_dispatched']);
        add_action('wp_ajax_rkm_mark_order_delivered', [$this, 'ajax_mark_delivered']);
    }

    public static function get_section_key() {
        return self::SECTION_KEY;
    }

    public static function get_section_url() {
        if (function_exists('rkm_get_panel_url')) {
            return rkm_get_panel_url(['section' => self::SECTION_KEY]);
        }

        $base_url = function_exists('wc_get_account_endpoint_url')
            ? wc_get_account_endpoint_url('panel')
            : home_url('/mi-cuenta/panel/');

        return add_query_arg('section', self::SECTION_KEY, $base_url);
    }

    public static function can_access($user = null) {
        if (!class_exists('RKM_Permissions')) {
            return false;
        }

        return RKM_Permissions::can_access_warehouse($user);
    }

    public static function can_manage($user = null) {
        return self::can_access($user);
    }

    public static function get_nonce_action() {
        return self::NONCE_ACTION;
    }

    public static function get_queue_statuses() {
        return ['rkm-warehouse'];
    }

    public static function get_ready_statuses() {
        return ['rkm-ready'];
    }

    public static function get_dispatched_statuses() {
        return ['rkm-dispatched'];
    }

    public static function get_visible_statuses() {
        return array_merge(self::get_queue_statuses(), self::get_ready_statuses(), self::get_dispatched_statuses(), ['completed']);
    }

    public static function get_status_label($status) {
        $status = (string) $status;

        if ($status === 'rkm-warehouse') {
            return 'En preparacion';
        }

        if ($status === 'rkm-ready') {
            return 'Listo';
        }

        if ($status === 'rkm-dispatched') {
            return 'Despachado';
        }

        if ($status === 'completed') {
            return 'Entregado';
        }

        if (function_exists('wc_get_order_status_name')) {
            return wc_get_order_status_name($status);
        }

        return ucfirst($status);
    }

    public function render_page($data = []) {
        if (!self::can_access()) {
            wp_safe_redirect(class_exists('RKM_Auth') ? RKM_Auth::get_redirect_url_for_user() : home_url('/mi-cuenta/panel/'));
            exit;
        }

        $user = wp_get_current_user();
        $view_data = array_merge($data, [
            'page_title' => 'Almacen',
            'page_subtitle' => 'Preparacion y control logistico de pedidos confirmados.',
            'current_section' => self::SECTION_KEY,
            'section_url' => self::get_section_url(),
            'warehouse_orders' => $this->get_warehouse_orders(),
            'can_manage_warehouse' => self::can_manage($user),
        ]);

        $template = RKM_CORE_PATH . 'templates/admin/warehouse-orders.php';

        if (file_exists($template)) {
            $data = $view_data;
            include $template;
        }
    }

    private function get_warehouse_orders() {
        if (!function_exists('wc_get_orders')) {
            return [];
        }

        $orders = wc_get_orders([
            'limit'   => self::ORDERS_LIMIT,
            'orderby' => 'date',
            'order'   => 'DESC',
            'status'  => self::get_visible_statuses(),
        ]);

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

        $items = $this->format_order_items($order);
        $picking_checklist = $this->get_order_picking_checklist($order, $items);
        $evidence = $this->get_order_evidence($order);
        $incidents = $this->get_order_picking_incidents($order);

        return [
            'id' => (int) $order->get_id(),
            'number' => $order->get_order_number(),
            'customer_name' => $customer_name,
            'customer_email' => $order->get_billing_email() ?: '-',
            'date' => $order->get_date_created() ? $order->get_date_created()->date_i18n('d/m/Y H:i') : 'Sin fecha',
            'status' => $order->get_status(),
            'status_label' => self::get_status_label($order->get_status()),
            'items_count' => count($items),
            'items_summary' => $this->build_items_summary($items),
            'items' => $items,
            'picking_checklist' => $picking_checklist,
            'picking_completed' => $order->get_meta(self::PICKING_COMPLETED_META) === 'yes',
            'picking_incidents' => $incidents,
            'has_open_picking_incidents' => $this->has_open_picking_incidents($incidents),
            'evidence' => $evidence,
            'evidence_count' => count($evidence),
            'evidence_min_required' => self::EVIDENCE_MIN_FILES,
            'operational_closure' => $this->get_operational_closure($order),
            'credit_context' => class_exists('RKM_Operational_Orders') ? RKM_Operational_Orders::get_credit_context($order) : $this->get_basic_credit_context($order),
            'audit_timeline' => class_exists('RKM_Order_Audit_Log') ? RKM_Order_Audit_Log::get_events($order->get_id()) : [],
        ];
    }

    private function get_operational_closure($order) {
        $dispatched_at = (string) $order->get_meta('_rkm_dispatched_at', true);
        $delivered_at = (string) $order->get_meta('_rkm_delivered_at', true);

        return [
            'dispatched_at' => $dispatched_at,
            'dispatched_label' => $this->format_datetime_label($dispatched_at),
            'dispatched_by' => (int) $order->get_meta('_rkm_dispatched_by', true),
            'delivered_at' => $delivered_at,
            'delivered_label' => $this->format_datetime_label($delivered_at),
            'delivered_by' => (int) $order->get_meta('_rkm_delivered_by', true),
        ];
    }

    private function get_basic_credit_context($order) {
        $payment_term_key = (string) $order->get_meta('_rkm_payment_term', true);
        $credit_balance = (float) $order->get_meta('_rkm_credit_balance', true);
        $due_at = (string) $order->get_meta('_rkm_credit_due_at', true);

        return [
            'has_credit' => in_array($payment_term_key, ['credit', 'mixed'], true) && $credit_balance > 0,
            'days' => self::CREDIT_DAYS,
            'due_at' => $due_at,
            'due_label' => $this->format_date_label($due_at),
        ];
    }

    private function format_datetime_label($value) {
        $timestamp = $value !== '' ? strtotime((string) $value) : 0;

        return $timestamp > 0 ? wp_date('d/m/Y H:i', $timestamp) : '';
    }

    private function format_date_label($value) {
        $timestamp = $value !== '' ? strtotime((string) $value) : 0;

        return $timestamp > 0 ? wp_date('d/m/Y', $timestamp) : '';
    }

    private function build_items_summary(array $items) {
        if (empty($items)) {
            return 'Sin productos';
        }

        $count = count($items);

        if ($count === 1) {
            return '1 producto';
        }

        return sprintf('%d productos', $count);
    }

    private function format_order_items($order) {
        $items = [];

        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            $quantity = max(1, (int) $item->get_quantity());

            $items[] = [
                'item_id' => (int) $item_id,
                'product_id' => $product ? (int) $product->get_id() : 0,
                'name' => $item->get_name(),
                'sku' => $product ? (string) $product->get_sku() : '',
                'quantity' => $quantity,
                'line_total' => function_exists('wc_price') ? html_entity_decode(wp_strip_all_tags(wc_price((float) $item->get_total())), ENT_QUOTES, 'UTF-8') : (string) $item->get_total(),
            ];
        }

        return $items;
    }

    private function get_order_picking_checklist($order, array $items) {
        $saved = $order->get_meta(self::PICKING_CHECKLIST_META);

        if (!is_array($saved)) {
            $saved = [];
        }

        $saved_by_item = [];
        foreach ($saved as $entry) {
            if (!is_array($entry) || empty($entry['item_id'])) {
                continue;
            }

            $saved_by_item[(int) $entry['item_id']] = $entry;
        }

        $checklist = [];
        foreach ($items as $item) {
            $item_id = isset($item['item_id']) ? (int) $item['item_id'] : 0;
            $saved_entry = isset($saved_by_item[$item_id]) && is_array($saved_by_item[$item_id]) ? $saved_by_item[$item_id] : [];
            $ordered_quantity = isset($item['quantity']) ? (float) $item['quantity'] : 0;

            $checklist[] = [
                'item_id' => $item_id,
                'product_id' => isset($saved_entry['product_id']) ? (int) $saved_entry['product_id'] : (int) ($item['product_id'] ?? 0),
                'sku' => isset($saved_entry['sku']) ? (string) $saved_entry['sku'] : (string) ($item['sku'] ?? ''),
                'name' => isset($saved_entry['name']) ? (string) $saved_entry['name'] : (string) ($item['name'] ?? ''),
                'ordered_quantity' => isset($saved_entry['ordered_quantity']) ? (float) $saved_entry['ordered_quantity'] : $ordered_quantity,
                'prepared_quantity' => isset($saved_entry['prepared_quantity']) ? (float) $saved_entry['prepared_quantity'] : 0,
                'prepared' => !empty($saved_entry['prepared']),
                'note' => isset($saved_entry['note']) ? (string) $saved_entry['note'] : '',
                'prepared_at' => isset($saved_entry['prepared_at']) ? (string) $saved_entry['prepared_at'] : '',
                'prepared_by' => isset($saved_entry['prepared_by']) ? (int) $saved_entry['prepared_by'] : 0,
            ];
        }

        return $checklist;
    }

    private function build_validated_picking_snapshot($order, $raw_checklist, $user_id) {
        if (!is_array($raw_checklist)) {
            return new WP_Error('invalid_checklist', 'Checklist de picking invalido.');
        }

        $order_items = $order->get_items();

        if (empty($order_items)) {
            return new WP_Error('empty_order', 'El pedido no tiene productos para preparar.');
        }

        $submitted_by_item = [];
        foreach ($raw_checklist as $entry) {
            if (!is_array($entry) || !isset($entry['item_id']) || !is_scalar($entry['item_id'])) {
                return new WP_Error('invalid_checklist_item', 'Cada producto del checklist debe ser valido.');
            }

            $item_id = absint($entry['item_id']);
            if ($item_id <= 0 || isset($submitted_by_item[$item_id])) {
                return new WP_Error('invalid_checklist_item', 'El checklist contiene productos duplicados o invalidos.');
            }

            if (!isset($order_items[$item_id])) {
                return new WP_Error('unknown_checklist_item', 'El checklist contiene productos que no pertenecen al pedido.');
            }

            $submitted_by_item[$item_id] = $entry;
        }

        if (count($submitted_by_item) !== count($order_items)) {
            return new WP_Error('missing_checklist_items', 'Debes validar todos los productos del pedido.');
        }

        $prepared_at = current_time('mysql');
        $snapshot = [];

        foreach ($order_items as $item_id => $item) {
            if (!isset($submitted_by_item[(int) $item_id])) {
                return new WP_Error('missing_checklist_items', 'Debes validar todos los productos del pedido.');
            }

            $entry = $submitted_by_item[(int) $item_id];
            $product = $item->get_product();
            $ordered_quantity = (float) $item->get_quantity();
            if (!isset($entry['prepared_quantity']) || !is_scalar($entry['prepared_quantity'])) {
                return new WP_Error('invalid_quantity', 'La cantidad preparada debe ser valida.');
            }

            $prepared_quantity = (float) wc_format_decimal(wp_unslash($entry['prepared_quantity']));
            if (!isset($entry['prepared']) || (!is_bool($entry['prepared']) && !is_scalar($entry['prepared']))) {
                return new WP_Error('unprepared_item', 'Todos los productos deben estar marcados como preparados.');
            }

            $prepared = !empty($entry['prepared']) && $entry['prepared'] !== 'false' && $entry['prepared'] !== '0';
            $note = isset($entry['note']) && is_scalar($entry['note']) ? sanitize_text_field(wp_unslash($entry['note'])) : '';
            $note = function_exists('mb_substr') ? mb_substr($note, 0, 140) : substr($note, 0, 140);

            if (!$prepared) {
                return new WP_Error('unprepared_item', 'Todos los productos deben estar marcados como preparados.');
            }

            if ($prepared_quantity <= 0 || abs($prepared_quantity - $ordered_quantity) > 0.0001) {
                return new WP_Error('invalid_quantity', 'La cantidad preparada debe coincidir con la cantidad pedida.');
            }

            $snapshot[] = [
                'item_id' => (int) $item_id,
                'product_id' => $product ? (int) $product->get_id() : 0,
                'sku' => $product ? (string) $product->get_sku() : '',
                'name' => $item->get_name(),
                'ordered_quantity' => $ordered_quantity,
                'prepared_quantity' => $prepared_quantity,
                'prepared' => true,
                'note' => $note,
                'prepared_at' => $prepared_at,
                'prepared_by' => (int) $user_id,
            ];
        }

        return $snapshot;
    }

    private function build_partial_picking_snapshot($order, $raw_checklist, $user_id) {
        if (!is_array($raw_checklist)) {
            return new WP_Error('invalid_checklist', 'Checklist de picking invalido.');
        }

        $order_items = $order->get_items();
        $submitted_by_item = [];

        foreach ($raw_checklist as $entry) {
            if (!is_array($entry) || !isset($entry['item_id']) || !is_scalar($entry['item_id'])) {
                return new WP_Error('invalid_checklist_item', 'Cada producto del checklist debe ser valido.');
            }

            $item_id = absint($entry['item_id']);
            if ($item_id <= 0 || isset($submitted_by_item[$item_id])) {
                return new WP_Error('invalid_checklist_item', 'El checklist contiene productos duplicados o invalidos.');
            }

            if (!isset($order_items[$item_id])) {
                return new WP_Error('unknown_checklist_item', 'El checklist contiene productos que no pertenecen al pedido.');
            }

            $submitted_by_item[$item_id] = $entry;
        }

        $updated_at = current_time('mysql');
        $snapshot = [];

        foreach ($order_items as $item_id => $item) {
            $entry = isset($submitted_by_item[(int) $item_id]) ? $submitted_by_item[(int) $item_id] : [];
            $product = $item->get_product();
            $ordered_quantity = (float) $item->get_quantity();
            $prepared_quantity = 0;
            $prepared = false;
            $note = '';

            if (!empty($entry)) {
                if (isset($entry['prepared_quantity'])) {
                    if (!is_scalar($entry['prepared_quantity'])) {
                        return new WP_Error('invalid_quantity', 'La cantidad preparada debe ser valida.');
                    }

                    $prepared_quantity = (float) wc_format_decimal(wp_unslash($entry['prepared_quantity']));

                    if ($prepared_quantity < 0) {
                        return new WP_Error('invalid_quantity', 'La cantidad preparada no puede ser negativa.');
                    }
                }

                if (isset($entry['prepared'])) {
                    if (!is_bool($entry['prepared']) && !is_scalar($entry['prepared'])) {
                        return new WP_Error('invalid_prepared', 'El estado preparado debe ser valido.');
                    }

                    $prepared = !empty($entry['prepared']) && $entry['prepared'] !== 'false' && $entry['prepared'] !== '0';
                }

                $note = isset($entry['note']) && is_scalar($entry['note']) ? sanitize_text_field(wp_unslash($entry['note'])) : '';
                $note = function_exists('mb_substr') ? mb_substr($note, 0, 140) : substr($note, 0, 140);
            }

            $snapshot[] = [
                'item_id' => (int) $item_id,
                'product_id' => $product ? (int) $product->get_id() : 0,
                'sku' => $product ? (string) $product->get_sku() : '',
                'name' => $item->get_name(),
                'ordered_quantity' => $ordered_quantity,
                'prepared_quantity' => $prepared_quantity,
                'prepared' => $prepared,
                'note' => $note,
                'prepared_at' => $prepared ? $updated_at : '',
                'prepared_by' => $prepared ? (int) $user_id : 0,
            ];
        }

        return $snapshot;
    }

    private function get_order_picking_incidents($order) {
        $incidents = $order->get_meta(self::PICKING_INCIDENTS_META);

        if (!is_array($incidents)) {
            return [];
        }

        return array_values(array_filter($incidents, static function ($incident) {
            return is_array($incident) && !empty($incident['item_id']);
        }));
    }

    private function has_open_picking_incidents(array $incidents) {
        foreach ($incidents as $incident) {
            if (is_array($incident) && (($incident['status'] ?? '') === 'open')) {
                return true;
            }
        }

        return false;
    }

    private function get_incident_type_label($type) {
        $labels = [
            'missing' => 'Producto faltante',
            'insufficient_stock' => 'Cantidad insuficiente',
            'damaged' => 'Producto dañado',
            'wrong_item' => 'Producto equivocado',
            'other' => 'Observación general',
        ];

        return $labels[$type] ?? 'Observación general';
    }

    private function build_picking_incident($order, $raw_data, $user_id) {
        if (!is_array($raw_data)) {
            return new WP_Error('invalid_incident', 'Incidencia invalida.');
        }

        $order_items = $order->get_items();
        $item_id = isset($raw_data['item_id']) && is_scalar($raw_data['item_id']) ? absint(wp_unslash($raw_data['item_id'])) : 0;

        if ($item_id <= 0 || !isset($order_items[$item_id])) {
            return new WP_Error('invalid_incident_item', 'El producto de la incidencia no pertenece al pedido.');
        }

        $allowed_types = ['missing', 'insufficient_stock', 'damaged', 'wrong_item', 'other'];
        $type = isset($raw_data['type']) && is_scalar($raw_data['type']) ? sanitize_key(wp_unslash($raw_data['type'])) : '';

        if (!in_array($type, $allowed_types, true)) {
            return new WP_Error('invalid_incident_type', 'Tipo de incidencia invalido.');
        }

        $available_quantity = isset($raw_data['available_quantity']) && is_scalar($raw_data['available_quantity'])
            ? (float) wc_format_decimal(wp_unslash($raw_data['available_quantity']))
            : 0;

        if ($available_quantity < 0) {
            return new WP_Error('invalid_incident_quantity', 'La cantidad disponible no puede ser negativa.');
        }

        $note = isset($raw_data['note']) && is_scalar($raw_data['note']) ? sanitize_textarea_field(wp_unslash($raw_data['note'])) : '';
        $note = function_exists('mb_substr') ? mb_substr($note, 0, 500) : substr($note, 0, 500);

        if ($note === '') {
            return new WP_Error('missing_incident_note', 'La nota de incidencia es obligatoria.');
        }

        $item = $order_items[$item_id];
        $product = $item->get_product();

        return [
            'item_id' => (int) $item_id,
            'product_id' => $product ? (int) $product->get_id() : 0,
            'sku' => $product ? (string) $product->get_sku() : '',
            'name' => $item->get_name(),
            'type' => $type,
            'requested_quantity' => (float) $item->get_quantity(),
            'available_quantity' => $available_quantity,
            'note' => $note,
            'status' => 'open',
            'created_at' => current_time('mysql'),
            'created_by' => (int) $user_id,
        ];
    }

    private function get_order_evidence($order) {
        $attachment_ids = $order->get_meta(self::EVIDENCE_ATTACHMENT_IDS_META);

        if (!is_array($attachment_ids)) {
            $attachment_ids = [];
        }

        $evidence = [];
        foreach ($attachment_ids as $attachment_id) {
            $attachment_id = absint($attachment_id);

            if ($attachment_id <= 0 || get_post_type($attachment_id) !== 'attachment') {
                continue;
            }

            $thumbnail = wp_get_attachment_image_url($attachment_id, 'thumbnail');
            $full = wp_get_attachment_url($attachment_id);

            if (!$thumbnail && !$full) {
                continue;
            }

            $evidence[] = [
                'id' => $attachment_id,
                'url' => $full ?: $thumbnail,
                'thumbnail' => $thumbnail ?: $full,
                'thumbnail_url' => $thumbnail ?: $full,
                'filename' => basename((string) get_attached_file($attachment_id)),
                'mime_type' => get_post_mime_type($attachment_id),
                'title' => get_the_title($attachment_id),
            ];
        }

        return $evidence;
    }

    private function normalize_evidence_files() {
        $files_key = '';

        if (!empty($_FILES['evidence_photos']) && is_array($_FILES['evidence_photos'])) {
            $files_key = 'evidence_photos';
        } elseif (!empty($_FILES['evidence_files']) && is_array($_FILES['evidence_files'])) {
            $files_key = 'evidence_files';
        }

        if ($files_key === '') {
            return [];
        }

        $files = $_FILES[$files_key];
        $normalized = [];

        if (isset($files['name']) && is_array($files['name'])) {
            foreach ($files['name'] as $index => $name) {
                if ($name === '' && isset($files['error'][$index]) && (int) $files['error'][$index] === UPLOAD_ERR_NO_FILE) {
                    continue;
                }

                $normalized[] = [
                    'name' => sanitize_file_name((string) $name),
                    'type' => isset($files['type'][$index]) ? (string) $files['type'][$index] : '',
                    'tmp_name' => isset($files['tmp_name'][$index]) ? (string) $files['tmp_name'][$index] : '',
                    'error' => isset($files['error'][$index]) ? (int) $files['error'][$index] : UPLOAD_ERR_NO_FILE,
                    'size' => isset($files['size'][$index]) ? (int) $files['size'][$index] : 0,
                ];
            }
        }

        return $normalized;
    }

    private function get_valid_evidence_attachment_ids($order) {
        $attachment_ids = $order->get_meta(self::EVIDENCE_ATTACHMENT_IDS_META);

        if (!is_array($attachment_ids)) {
            return [];
        }

        $valid_ids = [];
        foreach ($attachment_ids as $attachment_id) {
            $attachment_id = absint($attachment_id);
            $mime_type = $attachment_id > 0 ? get_post_mime_type($attachment_id) : '';

            if ($attachment_id > 0 && in_array($mime_type, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                $valid_ids[] = $attachment_id;
            }
        }

        return $valid_ids;
    }

    private function validate_evidence_files(array $files, $existing_count = 0) {
        if (((int) $existing_count + count($files)) < self::EVIDENCE_MIN_FILES) {
            return new WP_Error('missing_evidence', sprintf('Debes cargar al menos %d fotos de evidencia.', self::EVIDENCE_MIN_FILES));
        }

        $allowed_mimes = [
            'image/jpeg',
            'image/png',
            'image/webp',
        ];

        foreach ($files as $file) {
            if (!is_array($file) || empty($file['name'])) {
                return new WP_Error('invalid_evidence', 'Las fotos de evidencia no son validas.');
            }

            if (!empty($file['error'])) {
                return new WP_Error('upload_error', 'No se pudo leer una de las fotos de evidencia.');
            }

            if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
                return new WP_Error('invalid_evidence', 'Una de las fotos de evidencia no es valida.');
            }

            if ((int) $file['size'] <= 0 || (int) $file['size'] > self::EVIDENCE_MAX_FILE_SIZE) {
                return new WP_Error('invalid_evidence_size', 'Cada foto debe pesar 5 MB o menos.');
            }

            $filetype = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);
            $mime_type = isset($filetype['type']) ? (string) $filetype['type'] : '';

            if (!in_array($mime_type, $allowed_mimes, true)) {
                return new WP_Error('invalid_evidence_type', 'Solo se permiten imagenes JPG, PNG o WebP.');
            }
        }

        return true;
    }

    private function upload_evidence_files(array $files, $order_id) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attachment_ids = [];

        foreach ($files as $file) {
            $attachment_id = media_handle_sideload(
                $file,
                $order_id,
                sprintf('Evidencia de preparacion pedido #%d', (int) $order_id)
            );

            if (is_wp_error($attachment_id)) {
                foreach ($attachment_ids as $created_attachment_id) {
                    wp_delete_attachment($created_attachment_id, true);
                }

                return $attachment_id;
            }

            $attachment_ids[] = (int) $attachment_id;
        }

        return $attachment_ids;
    }

    private function save_uploaded_evidence_to_order($order, array $files, $user_id, $require_minimum = false) {
        $existing_attachment_ids = $this->get_valid_evidence_attachment_ids($order);
        $evidence_validation = $require_minimum
            ? $this->validate_evidence_files($files, count($existing_attachment_ids))
            : $this->validate_evidence_file_payload($files);

        if (is_wp_error($evidence_validation)) {
            return $evidence_validation;
        }

        $uploaded_attachment_ids = !empty($files) ? $this->upload_evidence_files($files, $order->get_id()) : [];

        if (is_wp_error($uploaded_attachment_ids)) {
            return $uploaded_attachment_ids;
        }

        $attachment_ids = array_values(array_unique(array_merge($existing_attachment_ids, $uploaded_attachment_ids)));
        $evidence_count = count($attachment_ids);

        $order->update_meta_data(self::EVIDENCE_ATTACHMENT_IDS_META, $attachment_ids);
        $order->update_meta_data(self::EVIDENCE_COUNT_META, $evidence_count);

        if (!empty($uploaded_attachment_ids)) {
            $order->update_meta_data('_rkm_warehouse_evidence_uploaded_at', current_time('mysql'));
            $order->update_meta_data('_rkm_warehouse_evidence_uploaded_by', (int) $user_id);
        }

        return [
            'all_attachment_ids' => $attachment_ids,
            'uploaded_attachment_ids' => $uploaded_attachment_ids,
            'count' => $evidence_count,
        ];
    }

    private function validate_evidence_file_payload(array $files) {
        if (empty($files)) {
            return true;
        }

        return $this->validate_evidence_files($files, self::EVIDENCE_MIN_FILES);
    }

    public function validate_ready_for_dispatch($order) {
        if (!$order || !method_exists($order, 'get_items')) {
            return new WP_Error('invalid_order', 'Pedido no encontrado.');
        }

        if ($this->has_open_picking_incidents($this->get_order_picking_incidents($order))) {
            return new WP_Error('open_incidents', 'No se puede despachar un pedido con incidencias abiertas.');
        }

        $checklist = $order->get_meta(self::PICKING_CHECKLIST_META);
        $order_items = $order->get_items();

        if (!is_array($checklist) || empty($checklist) || count($checklist) !== count($order_items)) {
            return new WP_Error('incomplete_picking', 'No se puede despachar sin checklist completo.');
        }

        $items_by_id = [];
        foreach ($order_items as $item_id => $item) {
            $items_by_id[(int) $item_id] = (float) $item->get_quantity();
        }

        $seen = [];
        foreach ($checklist as $entry) {
            if (!is_array($entry) || empty($entry['item_id'])) {
                return new WP_Error('invalid_picking', 'El checklist guardado no es valido.');
            }

            $item_id = (int) $entry['item_id'];
            if (!array_key_exists($item_id, $items_by_id) || isset($seen[$item_id])) {
                return new WP_Error('invalid_picking', 'El checklist guardado no coincide con el pedido.');
            }

            $seen[$item_id] = true;
            $prepared_quantity = isset($entry['prepared_quantity']) ? (float) $entry['prepared_quantity'] : 0;

            if (empty($entry['prepared']) || $prepared_quantity <= 0 || abs($prepared_quantity - $items_by_id[$item_id]) > 0.0001) {
                return new WP_Error('incomplete_picking', 'No se puede despachar sin checklist completo.');
            }
        }

        if ($order->get_meta(self::PICKING_COMPLETED_META) !== 'yes') {
            return new WP_Error('incomplete_picking', 'No se puede despachar sin picking completado.');
        }

        if (count($this->get_valid_evidence_attachment_ids($order)) < self::EVIDENCE_MIN_FILES) {
            return new WP_Error('missing_evidence', 'No se puede despachar sin evidencia fotografica suficiente.');
        }

        return true;
    }

    private function order_has_credit_balance($order) {
        $payment_term = (string) $order->get_meta('_rkm_payment_term', true);
        $credit_balance = (float) $order->get_meta('_rkm_credit_balance', true);

        return in_array($payment_term, ['credit', 'mixed'], true) && $credit_balance > 0;
    }

    private function start_credit_term_if_needed($order, $delivered_at, $user_id) {
        if (!$this->order_has_credit_balance($order)) {
            return null;
        }

        $previous = [
            'started_at' => (string) $order->get_meta('_rkm_credit_started_at', true),
            'due_at' => (string) $order->get_meta('_rkm_credit_due_at', true),
            'days' => (string) $order->get_meta('_rkm_credit_days', true),
        ];

        if ($previous['started_at'] !== '' && $previous['due_at'] !== '') {
            return null;
        }

        $delivered_timestamp = strtotime((string) $delivered_at);
        if ($delivered_timestamp <= 0) {
            $delivered_timestamp = current_time('timestamp');
        }

        $due_timestamp = $delivered_timestamp + (DAY_IN_SECONDS * self::CREDIT_DAYS);
        $started_at = wp_date('Y-m-d H:i:s', $delivered_timestamp);
        $due_at = wp_date('Y-m-d H:i:s', $due_timestamp);

        $order->update_meta_data('_rkm_credit_started_at', $started_at);
        $order->update_meta_data('_rkm_credit_due_at', $due_at);
        $order->update_meta_data('_rkm_credit_days', self::CREDIT_DAYS);

        return [
            'old' => $previous,
            'new' => [
                'started_at' => $started_at,
                'due_at' => $due_at,
                'days' => self::CREDIT_DAYS,
                'started_by' => (int) $user_id,
            ],
            'due_label' => wp_date('d/m/Y', $due_timestamp),
        ];
    }

    public function dispatch_order($order, $origin = 'Almacen') {
        if (!$order || !method_exists($order, 'get_status')) {
            return new WP_Error('invalid_order', 'Pedido no encontrado.');
        }

        if ($order->get_status() !== 'rkm-ready') {
            return new WP_Error('invalid_status', 'Solo se pueden despachar pedidos listos.');
        }

        $dispatch_validation = $this->validate_ready_for_dispatch($order);
        if (is_wp_error($dispatch_validation)) {
            return $dispatch_validation;
        }

        $user = wp_get_current_user();
        $user_id = $user instanceof WP_User ? (int) $user->ID : 0;
        $actor = $user instanceof WP_User && $user->display_name !== '' ? $user->display_name : 'Sistema';
        $dispatched_at = current_time('mysql');
        $origin = sanitize_text_field((string) $origin);
        $origin = $origin !== '' ? $origin : 'Almacen';

        $order->update_meta_data('_rkm_dispatched_at', $dispatched_at);
        $order->update_meta_data('_rkm_dispatched_by', $user_id);
        $order->save();

        RKM_Order_Audit_Log::add_event(
            $order->get_id(),
            'Pedido despachado',
            'Pedido despachado',
            sprintf('Pedido despachado por %s desde %s el %s.', $actor, $origin, $this->format_datetime_label($dispatched_at)),
            'rkm-ready',
            'rkm-dispatched'
        );

        $order->update_status('rkm-dispatched', '');

        return $order;
    }

    public function deliver_order($order, $origin = 'Almacen') {
        if (!$order || !method_exists($order, 'get_status')) {
            return new WP_Error('invalid_order', 'Pedido no encontrado.');
        }

        if ($order->get_status() !== 'rkm-dispatched') {
            return new WP_Error('invalid_status', 'Solo se pueden entregar pedidos despachados.');
        }

        $user = wp_get_current_user();
        $user_id = $user instanceof WP_User ? (int) $user->ID : 0;
        $actor = $user instanceof WP_User && $user->display_name !== '' ? $user->display_name : 'Sistema';
        $delivered_at = current_time('mysql');
        $origin = sanitize_text_field((string) $origin);
        $origin = $origin !== '' ? $origin : 'Almacen';
        $credit_result = $this->start_credit_term_if_needed($order, $delivered_at, $user_id);

        $order->update_meta_data('_rkm_delivered_at', $delivered_at);
        $order->update_meta_data('_rkm_delivered_by', $user_id);
        $order->save();

        RKM_Order_Audit_Log::add_event(
            $order->get_id(),
            'Pedido entregado',
            'Pedido entregado',
            sprintf('Pedido entregado por %s desde %s el %s.', $actor, $origin, $this->format_datetime_label($delivered_at)),
            'rkm-dispatched',
            'completed'
        );

        if (is_array($credit_result)) {
            RKM_Order_Audit_Log::add_event(
                $order->get_id(),
                'Inicio de credito',
                'Inicio de credito',
                sprintf('Se inicio plazo de credito de %d dias. Vencimiento: %s.', self::CREDIT_DAYS, $credit_result['due_label']),
                $credit_result['old'],
                $credit_result['new']
            );
        }

        $order->update_status('completed', '');

        return $order;
    }

    public function ajax_add_note() {
        if (!check_ajax_referer(self::NONCE_ACTION, 'nonce', false)) {
            wp_send_json_error(['message' => 'Solicitud invalida.'], 403);
        }

        if (!is_user_logged_in() || !self::can_manage()) {
            wp_send_json_error(['message' => 'No tenes permiso para operar almacen.'], 403);
        }

        if (!function_exists('wc_get_order') || !class_exists('RKM_Order_Audit_Log')) {
            wp_send_json_error(['message' => 'WooCommerce o auditoria no estan disponibles.'], 500);
        }

        $order_id = isset($_POST['order_id']) ? absint(wp_unslash($_POST['order_id'])) : 0;
        $note = isset($_POST['note']) ? sanitize_textarea_field(wp_unslash($_POST['note'])) : '';

        if ($order_id <= 0 || $note === '') {
            wp_send_json_error(['message' => 'Debes indicar el pedido y una observacion valida.'], 400);
        }

        $order = wc_get_order($order_id);

        if (!$order) {
            wp_send_json_error(['message' => 'Pedido no encontrado.'], 404);
        }

        if (!in_array($order->get_status(), self::get_visible_statuses(), true)) {
            wp_send_json_error(['message' => 'Solo se pueden registrar observaciones en pedidos de almacen.'], 409);
        }

        RKM_Order_Audit_Log::add_event(
            $order_id,
            'Observacion de almacen',
            'Observacion de almacen',
            $note
        );

        wp_send_json_success([
            'message' => 'Observacion registrada correctamente.',
            'timeline' => RKM_Order_Audit_Log::get_events($order_id),
        ]);
    }

    public function ajax_save_picking_progress() {
        if (!check_ajax_referer(self::NONCE_ACTION, 'nonce', false)) {
            wp_send_json_error(['message' => 'Solicitud invalida.'], 403);
        }

        if (!is_user_logged_in() || !self::can_manage()) {
            wp_send_json_error(['message' => 'No tenes permiso para operar almacen.'], 403);
        }

        if (!function_exists('wc_get_order') || !class_exists('RKM_Order_Audit_Log')) {
            wp_send_json_error(['message' => 'WooCommerce o auditoria no estan disponibles.'], 500);
        }

        $order_id = isset($_POST['order_id']) ? absint(wp_unslash($_POST['order_id'])) : 0;
        $raw_checklist_json = isset($_POST['checklist']) ? wp_unslash($_POST['checklist']) : '';

        if ($order_id <= 0) {
            wp_send_json_error(['message' => 'Pedido invalido.'], 400);
        }

        $order = wc_get_order($order_id);

        if (!$order) {
            wp_send_json_error(['message' => 'Pedido no encontrado.'], 404);
        }

        if ($order->get_status() !== 'rkm-warehouse') {
            wp_send_json_error(['message' => 'Solo se puede guardar avance en pedidos en almacen.'], 409);
        }

        $user = wp_get_current_user();
        $user_id = $user instanceof WP_User ? (int) $user->ID : 0;
        $actor = $user instanceof WP_User && $user->display_name !== '' ? $user->display_name : 'Sistema';
        $old_value = $order->get_meta(self::PICKING_CHECKLIST_META);
        $raw_checklist = json_decode((string) $raw_checklist_json, true);
        $picking_snapshot = $this->build_partial_picking_snapshot($order, $raw_checklist, $user_id);

        if (is_wp_error($picking_snapshot)) {
            wp_send_json_error(['message' => $picking_snapshot->get_error_message()], 400);
        }

        $prepared_count = 0;
        foreach ($picking_snapshot as $entry) {
            if (!empty($entry['prepared'])) {
                $prepared_count++;
            }
        }

        $total_count = count($order->get_items());
        $evidence_files = $this->normalize_evidence_files();
        $evidence_result = $this->save_uploaded_evidence_to_order($order, $evidence_files, $user_id, false);

        if (is_wp_error($evidence_result)) {
            wp_send_json_error(['message' => $evidence_result->get_error_message()], 400);
        }

        $order->update_meta_data(self::PICKING_CHECKLIST_META, $picking_snapshot);
        $order->update_meta_data('_rkm_warehouse_picking_progress', 'partial');
        $order->update_meta_data('_rkm_warehouse_picking_progress_updated_at', current_time('mysql'));
        $order->update_meta_data('_rkm_warehouse_picking_progress_updated_by', $user_id);
        $order->save();

        if (!empty($evidence_result['uploaded_attachment_ids'])) {
            RKM_Order_Audit_Log::add_event(
                $order_id,
                'Evidencia de preparación cargada',
                'Evidencia de preparación cargada',
                sprintf(
                    'Evidencia de preparacion cargada por %s. Fotos cargadas: %d. Attachments: %s. Fecha/hora: %s.',
                    $actor,
                    count($evidence_result['uploaded_attachment_ids']),
                    implode(', ', array_map('strval', $evidence_result['uploaded_attachment_ids'])),
                    current_time('mysql')
                ),
                null,
                $evidence_result['all_attachment_ids']
            );
        }

        RKM_Order_Audit_Log::add_event(
            $order_id,
            'Avance de picking guardado',
            'Avance de picking guardado',
            sprintf('Avance guardado por %s. Productos preparados: %d/%d.', $actor, $prepared_count, $total_count),
            is_array($old_value) ? $old_value : null,
            $picking_snapshot
        );

        wp_send_json_success([
            'message' => 'Avance de picking guardado.',
            'order' => $this->format_order_row($order),
        ]);
    }

    public function ajax_report_picking_incident() {
        if (!check_ajax_referer(self::NONCE_ACTION, 'nonce', false)) {
            wp_send_json_error(['message' => 'Solicitud invalida.'], 403);
        }

        if (!is_user_logged_in() || !self::can_manage()) {
            wp_send_json_error(['message' => 'No tenes permiso para operar almacen.'], 403);
        }

        if (!function_exists('wc_get_order') || !class_exists('RKM_Order_Audit_Log')) {
            wp_send_json_error(['message' => 'WooCommerce o auditoria no estan disponibles.'], 500);
        }

        $order_id = isset($_POST['order_id']) ? absint(wp_unslash($_POST['order_id'])) : 0;
        $raw_checklist_json = isset($_POST['checklist']) ? wp_unslash($_POST['checklist']) : '';

        if ($order_id <= 0) {
            wp_send_json_error(['message' => 'Pedido invalido.'], 400);
        }

        $order = wc_get_order($order_id);

        if (!$order) {
            wp_send_json_error(['message' => 'Pedido no encontrado.'], 404);
        }

        if ($order->get_status() !== 'rkm-warehouse') {
            wp_send_json_error(['message' => 'Solo se pueden reportar incidencias en pedidos en almacen.'], 409);
        }

        $user = wp_get_current_user();
        $user_id = $user instanceof WP_User ? (int) $user->ID : 0;
        $raw_checklist = json_decode((string) $raw_checklist_json, true);

        if (is_array($raw_checklist)) {
            $picking_snapshot = $this->build_partial_picking_snapshot($order, $raw_checklist, $user_id);

            if (is_wp_error($picking_snapshot)) {
                wp_send_json_error(['message' => $picking_snapshot->get_error_message()], 400);
            }

            $order->update_meta_data(self::PICKING_CHECKLIST_META, $picking_snapshot);
            $order->update_meta_data('_rkm_warehouse_picking_progress', 'partial');
            $order->update_meta_data('_rkm_warehouse_picking_progress_updated_at', current_time('mysql'));
            $order->update_meta_data('_rkm_warehouse_picking_progress_updated_by', $user_id);
        }

        $incident = $this->build_picking_incident($order, $_POST, $user_id);

        if (is_wp_error($incident)) {
            wp_send_json_error(['message' => $incident->get_error_message()], 400);
        }

        $old_incidents = $this->get_order_picking_incidents($order);
        $incidents = $old_incidents;
        $incidents[] = $incident;

        $order->update_meta_data(self::PICKING_INCIDENTS_META, $incidents);
        $order->save();

        RKM_Order_Audit_Log::add_event(
            $order_id,
            'Incidencia de picking registrada',
            'Incidencia de picking registrada',
            sprintf(
                'Producto: %s. SKU: %s. Tipo: %s. Cantidad solicitada: %s. Cantidad disponible: %s. Nota: %s',
                $incident['name'],
                $incident['sku'] !== '' ? $incident['sku'] : '-',
                $this->get_incident_type_label($incident['type']),
                rtrim(rtrim((string) $incident['requested_quantity'], '0'), '.'),
                rtrim(rtrim((string) $incident['available_quantity'], '0'), '.'),
                $incident['note']
            ),
            $old_incidents,
            $incidents
        );

        wp_send_json_success([
            'message' => 'Incidencia de picking registrada.',
            'order' => $this->format_order_row($order),
        ]);
    }

    public function ajax_mark_ready() {
        if (!check_ajax_referer(self::NONCE_ACTION, 'nonce', false)) {
            wp_send_json_error(['message' => 'Solicitud invalida.'], 403);
        }

        if (!is_user_logged_in() || !self::can_manage()) {
            wp_send_json_error(['message' => 'No tenes permiso para operar almacen.'], 403);
        }

        if (!function_exists('wc_get_order') || !class_exists('RKM_Order_Audit_Log')) {
            wp_send_json_error(['message' => 'WooCommerce o auditoria no estan disponibles.'], 500);
        }

        $order_id = isset($_POST['order_id']) ? absint(wp_unslash($_POST['order_id'])) : 0;
        $raw_checklist_json = isset($_POST['checklist']) ? wp_unslash($_POST['checklist']) : '';

        if ($order_id <= 0) {
            wp_send_json_error(['message' => 'Pedido invalido.'], 400);
        }

        $order = wc_get_order($order_id);

        if (!$order) {
            wp_send_json_error(['message' => 'Pedido no encontrado.'], 404);
        }

        if ($order->get_status() !== 'rkm-warehouse') {
            wp_send_json_error(['message' => 'Solo se puede marcar como preparado un pedido en almacen.'], 409);
        }

        $user = wp_get_current_user();
        $user_id = $user instanceof WP_User ? (int) $user->ID : 0;
        $actor = $user instanceof WP_User && $user->display_name !== '' ? $user->display_name : 'Sistema';
        $raw_checklist = json_decode((string) $raw_checklist_json, true);
        $picking_snapshot = $this->build_validated_picking_snapshot($order, $raw_checklist, $user_id);

        if (is_wp_error($picking_snapshot)) {
            wp_send_json_error(['message' => $picking_snapshot->get_error_message()], 400);
        }

        if ($this->has_open_picking_incidents($this->get_order_picking_incidents($order))) {
            wp_send_json_error(['message' => 'Este pedido tiene incidencias pendientes de resolver.'], 409);
        }

        $evidence_files = $this->normalize_evidence_files();
        $evidence_result = $this->save_uploaded_evidence_to_order($order, $evidence_files, $user_id, true);

        if (is_wp_error($evidence_result)) {
            wp_send_json_error(['message' => $evidence_result->get_error_message()], 400);
        }

        $prepared_count = count($picking_snapshot);
        $total_count = count($order->get_items());
        $prepared_at = current_time('mysql');
        $attachment_ids = $evidence_result['all_attachment_ids'];
        $uploaded_attachment_ids = $evidence_result['uploaded_attachment_ids'];
        $evidence_count = (int) $evidence_result['count'];

        $order->update_meta_data(self::PICKING_CHECKLIST_META, $picking_snapshot);
        $order->update_meta_data('_rkm_warehouse_prepared_at', $prepared_at);
        $order->update_meta_data('_rkm_warehouse_prepared_by', $user_id);
        $order->update_meta_data(self::PICKING_COMPLETED_META, 'yes');
        $order->save();

        if (!empty($uploaded_attachment_ids)) {
            RKM_Order_Audit_Log::add_event(
                $order_id,
                'Evidencia de preparación cargada',
                'Evidencia de preparación cargada',
                sprintf(
                    'Evidencia de preparacion cargada por %s. Fotos cargadas: %d. Attachments: %s. Fecha/hora: %s.',
                    $actor,
                    count($uploaded_attachment_ids),
                    implode(', ', array_map('strval', $uploaded_attachment_ids)),
                    current_time('mysql')
                ),
                null,
                $attachment_ids
            );
        }

        RKM_Order_Audit_Log::add_event(
            $order_id,
            'Pedido preparado',
            'Pedido preparado',
            sprintf(
                'Pedido preparado por %s. Checklist completo: %d/%d productos. Fotos guardadas: %d. Attachments: %s.',
                $actor,
                $prepared_count,
                $total_count,
                $evidence_count,
                implode(', ', array_map('strval', $attachment_ids))
            ),
            'rkm-warehouse',
            'rkm-ready'
        );

        $order->update_status('rkm-ready', '');

        wp_send_json_success([
            'message' => 'Pedido marcado como preparado.',
            'order' => $this->format_order_row($order),
        ]);
    }

    public function ajax_mark_dispatched() {
        if (!check_ajax_referer(self::NONCE_ACTION, 'nonce', false)) {
            wp_send_json_error(['message' => 'Solicitud invalida.'], 403);
        }

        if (!is_user_logged_in() || !self::can_manage()) {
            wp_send_json_error(['message' => 'No tenes permiso para operar almacen.'], 403);
        }

        if (!function_exists('wc_get_order') || !class_exists('RKM_Order_Audit_Log')) {
            wp_send_json_error(['message' => 'WooCommerce o auditoria no estan disponibles.'], 500);
        }

        $order_id = isset($_POST['order_id']) ? absint(wp_unslash($_POST['order_id'])) : 0;

        if ($order_id <= 0) {
            wp_send_json_error(['message' => 'Pedido invalido.'], 400);
        }

        $order = wc_get_order($order_id);

        if (!$order) {
            wp_send_json_error(['message' => 'Pedido no encontrado.'], 404);
        }

        if ($order->get_status() !== 'rkm-ready') {
            wp_send_json_error(['message' => 'Solo se pueden despachar pedidos listos.'], 409);
        }

        $result = $this->dispatch_order($order, 'Almacen');

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()], 409);
        }

        wp_send_json_success([
            'message' => 'Pedido marcado como despachado.',
            'order' => $this->format_order_row($result),
        ]);
    }

    public function ajax_mark_delivered() {
        if (!check_ajax_referer(self::NONCE_ACTION, 'nonce', false)) {
            wp_send_json_error(['message' => 'Solicitud invalida.'], 403);
        }

        if (!is_user_logged_in() || !self::can_manage()) {
            wp_send_json_error(['message' => 'No tenes permiso para operar almacen.'], 403);
        }

        if (!function_exists('wc_get_order') || !class_exists('RKM_Order_Audit_Log')) {
            wp_send_json_error(['message' => 'WooCommerce o auditoria no estan disponibles.'], 500);
        }

        $order_id = isset($_POST['order_id']) ? absint(wp_unslash($_POST['order_id'])) : 0;

        if ($order_id <= 0) {
            wp_send_json_error(['message' => 'Pedido invalido.'], 400);
        }

        $order = wc_get_order($order_id);

        if (!$order) {
            wp_send_json_error(['message' => 'Pedido no encontrado.'], 404);
        }

        if ($order->get_status() !== 'rkm-dispatched') {
            wp_send_json_error(['message' => 'Solo se pueden entregar pedidos despachados.'], 409);
        }

        $result = $this->deliver_order($order, 'Almacen');

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()], 409);
        }

        wp_send_json_success([
            'message' => 'Pedido marcado como entregado.',
            'order' => $this->format_order_row($result),
        ]);
    }
}
