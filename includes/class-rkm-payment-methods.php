<?php

if (!defined('ABSPATH')) {
    exit;
}

class RKM_Payment_Methods {

    const SECTION_KEY = 'formas-pago';
    const OPTION_KEY = 'rkm_payment_methods';
    const NOTICE_TRANSIENT_PREFIX = 'rkm_payment_methods_notice_';

    public function init() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('template_redirect', [$this, 'handle_submission'], 5);
    }

    public static function can_access($user = null) {
        return class_exists('RKM_Permissions') && RKM_Permissions::is_rkm_admin($user);
    }

    public static function get_section_key() {
        return self::SECTION_KEY;
    }

    public static function get_section_url() {
        return home_url('/mi-cuenta/panel/?section=' . self::SECTION_KEY);
    }

    public static function get_page_title() {
        return 'Formas de pago';
    }

    public static function get_page_subtitle() {
        return 'Gestiona los metodos que clientes y vendedores pueden seleccionar al crear pedidos.';
    }

    public static function get_methods() {
        $methods = get_option(self::OPTION_KEY, []);

        if (!is_array($methods)) {
            return [];
        }

        $methods = array_map([self::class, 'sanitize_method'], $methods);
        $methods = array_filter($methods, static function ($method) {
            return !empty($method['id']) && !empty($method['name']);
        });

        usort($methods, static function ($left, $right) {
            $priority_compare = (int) $left['priority'] <=> (int) $right['priority'];

            if ($priority_compare !== 0) {
                return $priority_compare;
            }

            return strcmp($left['name'], $right['name']);
        });

        return array_values($methods);
    }

    public static function get_active_methods() {
        return array_values(array_filter(self::get_methods(), static function ($method) {
            return !empty($method['active']);
        }));
    }

    public static function get_method($method_id) {
        $method_id = sanitize_key($method_id);

        if ($method_id === '') {
            return null;
        }

        foreach (self::get_methods() as $method) {
            if ($method['id'] === $method_id) {
                return $method;
            }
        }

        return null;
    }

    public static function get_active_method($method_id) {
        $method = self::get_method($method_id);

        if (!$method || empty($method['active'])) {
            return null;
        }

        return $method;
    }

    public static function sanitize_method($method) {
        $method = is_array($method) ? $method : [];
        $type = isset($method['type']) ? sanitize_key($method['type']) : 'otro';
        $allowed_types = ['transferencia', 'pago_movil', 'efectivo', 'tarjeta', 'credito', 'otro'];

        if (!in_array($type, $allowed_types, true)) {
            $type = 'otro';
        }

        return [
            'id'          => isset($method['id']) ? sanitize_key($method['id']) : '',
            'name'        => isset($method['name']) ? sanitize_text_field($method['name']) : '',
            'type'        => $type,
            'description' => isset($method['description']) ? sanitize_textarea_field($method['description']) : '',
            'active'      => !empty($method['active']) ? 1 : 0,
            'priority'    => isset($method['priority']) ? (int) $method['priority'] : 10,
        ];
    }

    public function enqueue_assets() {
        if (!$this->is_active_section()) {
            return;
        }

        wp_enqueue_style(
            'rkm-admin-payment-methods-css',
            RKM_CORE_URL . 'assets/css/admin-payment-methods.css',
            ['rkm-dashboard-css'],
            '1.0.0'
        );

        wp_enqueue_script(
            'rkm-admin-payment-methods-js',
            RKM_CORE_URL . 'assets/js/admin-payment-methods.js',
            [],
            '1.0.0',
            true
        );
    }

    public function render_page($data = []) {
        if (!self::can_access()) {
            wp_safe_redirect(class_exists('RKM_Auth') ? RKM_Auth::get_redirect_url_for_user() : home_url('/mi-cuenta/panel/'));
            exit;
        }

        $view_data = array_merge($data, [
            'page_title' => self::get_page_title(),
            'page_subtitle' => self::get_page_subtitle(),
            'current_section' => self::get_section_key(),
            'payment_methods' => self::get_methods(),
            'payment_methods_notice' => $this->consume_flash_notice(),
            'payment_method_types' => $this->get_type_options(),
        ]);

        $template = RKM_CORE_PATH . 'templates/admin/payment-methods.php';

        if (file_exists($template)) {
            $data = $view_data;
            include $template;
        }
    }

    public function handle_submission() {
        if (!$this->is_active_section() || !$this->is_post_request()) {
            return;
        }

        if (!isset($_POST['rkm_payment_methods_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['rkm_payment_methods_nonce'])), 'rkm_payment_methods_update')) {
            $this->set_flash_notice('error', 'La solicitud no es valida. Recarga la pagina e intenta nuevamente.');
            $this->redirect_back();
        }

        $action = isset($_POST['rkm_payment_methods_action']) ? sanitize_key(wp_unslash($_POST['rkm_payment_methods_action'])) : '';

        if ($action === 'save_method') {
            $this->save_method();
        }

        if ($action === 'toggle_method') {
            $this->toggle_method();
        }

        if ($action === 'delete_method') {
            $this->delete_method();
        }

        $this->set_flash_notice('error', 'Accion no reconocida.');
        $this->redirect_back();
    }

    private function save_method() {
        $method_id = isset($_POST['method_id']) ? sanitize_key(wp_unslash($_POST['method_id'])) : '';
        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';

        if ($name === '') {
            $this->set_flash_notice('error', 'El nombre de la forma de pago es obligatorio.');
            $this->redirect_back();
        }

        $methods = self::get_methods();
        $is_new = $method_id === '';

        if ($is_new) {
            $method_id = $this->generate_method_id($name, $methods);
        }

        $saved_method = self::sanitize_method([
            'id'          => $method_id,
            'name'        => $name,
            'type'        => isset($_POST['type']) ? wp_unslash($_POST['type']) : 'otro',
            'description' => isset($_POST['description']) ? wp_unslash($_POST['description']) : '',
            'active'      => isset($_POST['active']) ? 1 : 0,
            'priority'    => isset($_POST['priority']) ? absint($_POST['priority']) : 10,
        ]);

        $updated = false;

        foreach ($methods as $index => $method) {
            if ($method['id'] === $method_id) {
                $methods[$index] = $saved_method;
                $updated = true;
                break;
            }
        }

        if (!$updated) {
            $methods[] = $saved_method;
        }

        $this->update_methods($methods);
        $this->set_flash_notice('success', $is_new ? 'Forma de pago creada correctamente.' : 'Forma de pago actualizada correctamente.');
        $this->redirect_back();
    }

    private function toggle_method() {
        $method_id = isset($_POST['method_id']) ? sanitize_key(wp_unslash($_POST['method_id'])) : '';
        $methods = self::get_methods();
        $updated = false;

        foreach ($methods as $index => $method) {
            if ($method['id'] === $method_id) {
                $methods[$index]['active'] = empty($method['active']) ? 1 : 0;
                $updated = true;
                break;
            }
        }

        if (!$updated) {
            $this->set_flash_notice('error', 'No se encontro la forma de pago seleccionada.');
            $this->redirect_back();
        }

        $this->update_methods($methods);
        $this->set_flash_notice('success', 'Estado actualizado correctamente.');
        $this->redirect_back();
    }

    private function delete_method() {
        $method_id = isset($_POST['method_id']) ? sanitize_key(wp_unslash($_POST['method_id'])) : '';
        $methods = array_values(array_filter(self::get_methods(), static function ($method) use ($method_id) {
            return $method['id'] !== $method_id;
        }));

        $this->update_methods($methods);
        $this->set_flash_notice('success', 'Forma de pago eliminada correctamente.');
        $this->redirect_back();
    }

    private function update_methods($methods) {
        $methods = array_map([self::class, 'sanitize_method'], is_array($methods) ? $methods : []);
        update_option(self::OPTION_KEY, array_values($methods), false);
    }

    private function generate_method_id($name, $methods) {
        $base_id = sanitize_title($name);
        $base_id = $base_id !== '' ? $base_id : 'forma-pago';
        $existing_ids = array_map(static function ($method) {
            return $method['id'];
        }, $methods);

        $candidate = $base_id;
        $suffix = 2;

        while (in_array($candidate, $existing_ids, true)) {
            $candidate = $base_id . '-' . $suffix;
            $suffix++;
        }

        return sanitize_key($candidate);
    }

    private function get_type_options() {
        return [
            'transferencia' => 'Transferencia',
            'pago_movil'    => 'Pago movil',
            'efectivo'      => 'Efectivo',
            'tarjeta'       => 'Tarjeta',
            'credito'       => 'Credito',
            'otro'          => 'Otro',
        ];
    }

    private function is_active_section() {
        if (!is_user_logged_in() || !self::can_access()) {
            return false;
        }

        if (!(function_exists('is_account_page') && is_account_page())) {
            return false;
        }

        $section = isset($_GET['section']) ? sanitize_key(wp_unslash($_GET['section'])) : 'panel';

        return $section === self::SECTION_KEY;
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

    private function redirect_back() {
        wp_safe_redirect(self::get_section_url());
        exit;
    }
}
