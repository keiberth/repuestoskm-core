<?php

if (!defined('ABSPATH')) {
    exit;
}

class RKM_Order_Audit_Log {

    const TABLE_SCHEMA_VERSION = '1.0.0';
    const TABLE_SCHEMA_OPTION = 'rkm_order_audit_log_schema_version';

    public static function get_table_name() {
        global $wpdb;

        return $wpdb->prefix . 'rkm_order_audit_log';
    }

    public static function install_schema() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table_name = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NULL,
            user_name VARCHAR(190) NOT NULL DEFAULT '',
            user_role VARCHAR(100) NOT NULL DEFAULT '',
            action VARCHAR(100) NOT NULL DEFAULT '',
            title VARCHAR(190) NOT NULL DEFAULT '',
            details LONGTEXT NULL,
            old_value LONGTEXT NULL,
            new_value LONGTEXT NULL,
            ip_address VARCHAR(100) NULL,
            user_agent TEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY order_id (order_id),
            KEY user_id (user_id),
            KEY action (action),
            KEY created_at (created_at)
        ) {$charset_collate};";

        dbDelta($sql);
        update_option(self::TABLE_SCHEMA_OPTION, self::TABLE_SCHEMA_VERSION, false);
    }

    public static function maybe_install_schema() {
        if (get_option(self::TABLE_SCHEMA_OPTION) !== self::TABLE_SCHEMA_VERSION) {
            self::install_schema();
        }
    }

    public static function add_event($order_id, $action, $title, $details = '', $old_value = null, $new_value = null) {
        global $wpdb;

        $order_id = absint($order_id);

        if ($order_id <= 0) {
            return 0;
        }

        $user = wp_get_current_user();
        $user_id = $user instanceof WP_User && !empty($user->ID) ? (int) $user->ID : null;
        $user_name = $user instanceof WP_User && !empty($user->ID) && $user->display_name !== ''
            ? $user->display_name
            : ($user instanceof WP_User && !empty($user->ID) && $user->user_login !== '' ? $user->user_login : 'Sistema');
        $user_role = self::get_user_role_label($user);
        $created_at = current_time('mysql');
        $ip_address = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';

        $payload = [
            'order_id' => $order_id,
            'user_id' => $user_id,
            'user_name' => self::clean_text($user_name),
            'user_role' => self::clean_text($user_role),
            'action' => self::clean_text($action),
            'title' => self::clean_text($title),
            'details' => self::clean_text($details),
            'old_value' => self::normalize_value($old_value),
            'new_value' => self::normalize_value($new_value),
            'ip_address' => $ip_address,
            'user_agent' => $user_agent,
            'created_at' => $created_at,
        ];

        $inserted = $wpdb->insert(
            self::get_table_name(),
            $payload,
            ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        if ($inserted === false) {
            return 0;
        }

        return (int) $wpdb->insert_id;
    }

    public static function get_events($order_id) {
        global $wpdb;

        $order_id = absint($order_id);

        if ($order_id <= 0) {
            return [];
        }

        $events = [];
        $table_name = self::get_table_name();
        $table_exists = (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));

        if ($table_exists) {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table_name} WHERE order_id = %d ORDER BY created_at DESC, id DESC",
                    $order_id
                ),
                ARRAY_A
            );

            if (!empty($rows)) {
                foreach ($rows as $row) {
                    $events[] = self::format_audit_row($row, 'audit_log');
                }
            }
        }

        $legacy_events = self::get_legacy_order_notes($order_id);

        if (!empty($legacy_events)) {
            $events = array_merge($events, $legacy_events);
        }

        $events = self::dedupe_events($events);

        usort($events, static function ($left, $right) {
            $left_ts = isset($left['timestamp']) ? (int) $left['timestamp'] : 0;
            $right_ts = isset($right['timestamp']) ? (int) $right['timestamp'] : 0;

            if ($left_ts === $right_ts) {
                $left_id = isset($left['id']) ? (int) $left['id'] : 0;
                $right_id = isset($right['id']) ? (int) $right['id'] : 0;

                return $right_id <=> $left_id;
            }

            return $right_ts <=> $left_ts;
        });

        return array_values($events);
    }

    private static function dedupe_events(array $events) {
        $seen = [];
        $unique = [];

        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }

            $fingerprint = self::event_fingerprint($event);

            if (isset($seen[$fingerprint])) {
                continue;
            }

            $seen[$fingerprint] = true;
            $unique[] = $event;
        }

        return $unique;
    }

    private static function event_fingerprint(array $event) {
        $source = isset($event['source']) ? (string) $event['source'] : '';
        $timestamp = isset($event['timestamp']) ? (string) (int) $event['timestamp'] : '';
        $action = isset($event['action']) ? self::clean_text($event['action']) : '';
        $title = isset($event['title']) ? self::clean_text($event['title']) : '';
        $detail = isset($event['detail']) ? self::clean_text($event['detail']) : '';
        $user = isset($event['user']) ? self::clean_text($event['user']) : '';
        $role = isset($event['role']) ? self::clean_text($event['role']) : '';

        return md5(implode('|', [$source, $timestamp, $action, $title, $detail, $user, $role]));
    }

    private static function format_audit_row(array $row, $source = 'audit_log') {
        $created_at = isset($row['created_at']) ? (string) $row['created_at'] : '';
        $timestamp = $created_at !== '' ? strtotime($created_at) : 0;

        return [
            'id' => isset($row['id']) ? (int) $row['id'] : 0,
            'order_id' => isset($row['order_id']) ? (int) $row['order_id'] : 0,
            'user_id' => isset($row['user_id']) ? (int) $row['user_id'] : 0,
            'user_name' => isset($row['user_name']) ? self::clean_text($row['user_name']) : 'Sistema',
            'user_role' => isset($row['user_role']) ? self::clean_text($row['user_role']) : 'Sistema',
            'user' => isset($row['user_name']) ? self::clean_text($row['user_name']) : 'Sistema',
            'role' => isset($row['user_role']) ? self::clean_text($row['user_role']) : 'Sistema',
            'action' => isset($row['action']) ? self::clean_text($row['action']) : 'Movimiento operativo',
            'title' => isset($row['title']) ? self::clean_text($row['title']) : 'Movimiento operativo',
            'details' => isset($row['details']) ? self::clean_text($row['details']) : '',
            'old_value' => isset($row['old_value']) ? self::maybe_decode_value($row['old_value']) : null,
            'new_value' => isset($row['new_value']) ? self::maybe_decode_value($row['new_value']) : null,
            'ip_address' => isset($row['ip_address']) ? self::clean_text($row['ip_address']) : '',
            'user_agent' => isset($row['user_agent']) ? self::clean_text($row['user_agent']) : '',
            'created_at' => $created_at,
            'timestamp' => $timestamp,
            'date' => $timestamp > 0 ? wp_date('d/m/Y H:i', $timestamp) : $created_at,
            'source' => $source,
            'detail' => isset($row['details']) ? self::clean_text($row['details']) : '',
        ];
    }

    private static function get_legacy_order_notes($order_id) {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT comment_ID, comment_date, comment_content, comment_author, user_id
                 FROM {$wpdb->comments}
                 WHERE comment_post_ID = %d
                   AND comment_type = 'order_note'
                   AND comment_approved = '1'
                 ORDER BY comment_date DESC, comment_ID DESC",
                $order_id
            ),
            ARRAY_A
        );

        if (empty($rows)) {
            return [];
        }

        $events = [];

        foreach ($rows as $row) {
            $content = self::clean_text($row['comment_content'] ?? '');
            $timestamp = !empty($row['comment_date']) ? strtotime((string) $row['comment_date']) : 0;
            $title = self::classify_legacy_note($content);

            $events[] = [
                'id' => isset($row['comment_ID']) ? (int) $row['comment_ID'] : 0,
                'order_id' => $order_id,
                'user_id' => isset($row['user_id']) ? (int) $row['user_id'] : 0,
                'user_name' => self::clean_text($row['comment_author'] ?? 'WooCommerce'),
                'user_role' => 'Sistema',
                'user' => self::clean_text($row['comment_author'] ?? 'WooCommerce'),
                'role' => 'Sistema',
                'action' => $title,
                'title' => $title,
                'details' => $content,
                'old_value' => null,
                'new_value' => null,
                'ip_address' => '',
                'user_agent' => '',
                'created_at' => !empty($row['comment_date']) ? (string) $row['comment_date'] : '',
                'timestamp' => $timestamp,
                'date' => $timestamp > 0 ? wp_date('d/m/Y H:i', $timestamp) : '',
                'source' => 'legacy_note',
                'detail' => $content,
            ];
        }

        return $events;
    }

    private static function classify_legacy_note($content) {
        $text = strtolower($content);

        if ($text === '') {
            return 'Nota heredada';
        }

        if (strpos($text, 'pedido confirmado') !== false) {
            return 'Pedido confirmado';
        }

        if (strpos($text, 'pedido despachado') !== false) {
            return 'Pedido despachado';
        }

        if (strpos($text, 'pedido entregado') !== false) {
            return 'Pedido entregado';
        }

        if (strpos($text, 'inicio de credito') !== false || strpos($text, 'plazo de credito') !== false) {
            return 'Inicio de credito';
        }

        if (strpos($text, 'pedido creado') !== false || strpos($text, 'pedido generado') !== false) {
            return 'Pedido creado';
        }

        if (strpos($text, 'pedido editado') !== false) {
            return 'Pedido editado';
        }

        if (strpos($text, 'cantidades actualizadas') !== false || strpos($text, 'cantidad') !== false) {
            return 'Cantidades modificadas';
        }

        if (strpos($text, 'condicion de pago') !== false || strpos($text, 'forma de pago') !== false || strpos($text, 'monto inicial') !== false) {
            return 'Condicion de pago modificada';
        }

        if (strpos($text, 'stock descontado') !== false) {
            return 'Stock descontado';
        }

        if (strpos($text, 'enviado a almacen') !== false) {
            return 'Enviado a almacen';
        }

        if (strpos($text, 'incidencia de picking resuelta') !== false) {
            return 'Incidencia de picking resuelta';
        }

        if (strpos($text, 'incidencia de picking') !== false) {
            return 'Incidencia de picking registrada';
        }

        if (strpos($text, 'estado cambiado') !== false) {
            return 'Estado cambiado';
        }

        return 'Nota heredada';
    }

    private static function normalize_value($value) {
        if ($value === null) {
            return null;
        }

        if (is_array($value) || is_object($value)) {
            return wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return self::clean_text((string) $value);
    }

    private static function maybe_decode_value($value) {
        if ($value === null || $value === '') {
            return null;
        }

        $decoded = maybe_unserialize($value);

        if (is_array($decoded) || is_object($decoded)) {
            return $decoded;
        }

        return is_string($decoded) ? $decoded : (string) $decoded;
    }

    private static function clean_text($value) {
        $value = html_entity_decode(wp_strip_all_tags((string) $value), ENT_QUOTES, 'UTF-8');
        $value = str_replace("\xc2\xa0", ' ', $value);

        return trim(preg_replace('/\s+/', ' ', $value));
    }

    private static function get_user_role_label($user) {
        if (!$user instanceof WP_User || empty($user->ID)) {
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
            'warehouse' => 'Almacen',
            'almacen' => 'Almacen',
            'inventory_manager' => 'Almacen',
            'stock_manager' => 'Almacen',
        ];

        if (isset($map[$role])) {
            return $map[$role];
        }

        return ucwords(str_replace(['-', '_'], ' ', $role));
    }
}
