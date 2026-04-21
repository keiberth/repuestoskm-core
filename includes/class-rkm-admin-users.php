<?php

if (!defined('ABSPATH')) {
    exit;
}

class RKM_Admin_Users {

    const SECTION_KEY = 'usuarios';
    const NOTICE_TRANSIENT_PREFIX = 'rkm_admin_users_notice_';
    const FORM_TRANSIENT_PREFIX = 'rkm_admin_users_form_';

    public function init() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('template_redirect', [$this, 'handle_create_user_submission'], 5);
    }

    public static function can_access($user = null) {
        return RKM_Permissions::is_rkm_admin($user);
    }

    public static function get_section_key() {
        return self::SECTION_KEY;
    }

    public static function get_section_url() {
        return home_url('/mi-cuenta/panel/?section=' . self::SECTION_KEY);
    }

    public static function get_page_title() {
        return 'Gestion de usuarios';
    }

    public static function get_page_subtitle() {
        return 'Crear usuarios internos y asignar roles operativos sin salir del sistema RKM.';
    }

    public function enqueue_assets() {
        if (!$this->is_active_section()) {
            return;
        }

        wp_enqueue_style(
            'rkm-admin-users-css',
            RKM_CORE_URL . 'assets/css/admin-users.css',
            ['rkm-dashboard-css'],
            '1.0.0'
        );

        wp_enqueue_script(
            'rkm-admin-users-js',
            RKM_CORE_URL . 'assets/js/admin-users.js',
            [],
            '1.0.0',
            true
        );
    }

    public function handle_create_user_submission() {
        if (!$this->is_active_section() || !$this->is_create_request()) {
            return;
        }

        if (!isset($_POST['rkm_admin_users_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['rkm_admin_users_nonce'])), 'rkm_admin_users_create')) {
            $this->set_flash_notice('error', 'La solicitud no es valida. Recarga la pagina e intenta nuevamente.');
            $this->redirect_back();
        }

        $form_data = $this->sanitize_form_data($_POST);
        $validation = $this->validate_form_data($form_data);

        if (is_wp_error($validation)) {
            $this->set_flash_notice('error', $validation->get_error_message());
            $this->set_form_state($form_data);
            $this->redirect_back();
        }

        $user_id = wp_insert_user([
            'user_login'   => $form_data['username'],
            'user_pass'    => $form_data['password'],
            'user_email'   => $form_data['email'],
            'first_name'   => $form_data['first_name'],
            'last_name'    => $form_data['last_name'],
            'display_name' => trim($form_data['first_name'] . ' ' . $form_data['last_name']) ?: $form_data['username'],
            'role'         => $form_data['role'],
        ]);

        if (is_wp_error($user_id)) {
            $this->set_flash_notice('error', $user_id->get_error_message());
            $this->set_form_state($form_data);
            $this->redirect_back();
        }

        $this->set_flash_notice('success', sprintf('Usuario creado correctamente con rol %s.', RKM_Permissions::get_role_label($form_data['role'])));
        $this->clear_form_state();
        $this->redirect_back();
    }

    public function render_page($data = []) {
        if (!self::can_access()) {
            wp_safe_redirect(RKM_Auth::get_redirect_url_for_user());
            exit;
        }

        $view_data = array_merge($data, [
            'page_title'       => self::get_page_title(),
            'page_subtitle'    => self::get_page_subtitle(),
            'current_section'  => self::get_section_key(),
            'admin_users_rows' => $this->get_users_rows(),
            'admin_users_roles'=> $this->get_role_options_for_view(),
            'admin_users_notice' => $this->consume_flash_notice(),
            'admin_users_form' => $this->consume_form_state(),
        ]);

        $template = RKM_CORE_PATH . 'templates/admin/users.php';

        if (file_exists($template)) {
            $data = $view_data;
            include $template;
        }
    }

    private function is_active_section() {
        if (!self::can_access() || !is_user_logged_in()) {
            return false;
        }

        if (!(function_exists('is_account_page') && is_account_page())) {
            return false;
        }

        $section = isset($_GET['section']) ? sanitize_key(wp_unslash($_GET['section'])) : 'panel';

        return $section === self::SECTION_KEY;
    }

    private function is_create_request() {
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return false;
        }

        return isset($_POST['rkm_admin_users_action'])
            && sanitize_key(wp_unslash($_POST['rkm_admin_users_action'])) === 'create_user';
    }

    private function sanitize_form_data($source) {
        return [
            'first_name' => sanitize_text_field($source['first_name'] ?? ''),
            'last_name'  => sanitize_text_field($source['last_name'] ?? ''),
            'email'      => sanitize_email($source['email'] ?? ''),
            'username'   => sanitize_user($source['username'] ?? '', true),
            'password'   => is_string($source['password'] ?? '') ? trim(wp_unslash($source['password'])) : '',
            'role'       => sanitize_key($source['role'] ?? ''),
        ];
    }

    private function validate_form_data($form_data) {
        if ($form_data['first_name'] === '' || $form_data['last_name'] === '' || $form_data['email'] === '' || $form_data['username'] === '' || $form_data['password'] === '' || $form_data['role'] === '') {
            return new WP_Error('missing_fields', 'Completa todos los campos antes de crear el usuario.');
        }

        if (!is_email($form_data['email'])) {
            return new WP_Error('invalid_email', 'Ingresa un correo electronico valido.');
        }

        if (username_exists($form_data['username'])) {
            return new WP_Error('duplicate_username', 'Ya existe un usuario con ese nombre de acceso.');
        }

        if (email_exists($form_data['email'])) {
            return new WP_Error('duplicate_email', 'Ya existe un usuario registrado con ese correo.');
        }

        if (!array_key_exists($form_data['role'], RKM_Permissions::get_assignable_user_roles())) {
            return new WP_Error('invalid_role', 'Selecciona un rol valido para el nuevo usuario.');
        }

        return true;
    }

    private function get_role_options_for_view() {
        $options = [];

        foreach (RKM_Permissions::get_assignable_user_roles() as $role => $label) {
            $options[] = [
                'value'       => $role,
                'label'       => $label,
                'description' => $this->get_role_description($role),
            ];
        }

        return $options;
    }

    private function get_role_description($role) {
        if ($role === 'administrator') {
            return 'Acceso total al panel administrativo del sistema RKM.';
        }

        if ($role === 'customer') {
            return 'Acceso al panel de cliente para pedidos, cuenta e historial.';
        }

        return 'Acceso al panel comercial para operar como vendedor.';
    }

    private function get_users_rows() {
        $query = new WP_User_Query([
            'number'  => 50,
            'orderby' => 'registered',
            'order'   => 'DESC',
        ]);

        $users = $query->get_results();
        $rows = [];

        foreach ($users as $user) {
            if (!$user instanceof WP_User) {
                continue;
            }

            $primary_role = !empty($user->roles) ? $user->roles[0] : '';
            $name = trim($user->first_name . ' ' . $user->last_name);

            $rows[] = [
                'name'       => $name !== '' ? $name : ($user->display_name ?: $user->user_login),
                'username'   => $user->user_login,
                'email'      => $user->user_email,
                'role'       => RKM_Permissions::get_role_label($primary_role),
                'role_slug'  => $primary_role,
                'status'     => ((int) $user->user_status === 0) ? 'Activo' : 'Inactivo',
                'registered' => mysql2date('d/m/Y', $user->user_registered, true),
            ];
        }

        return $rows;
    }

    private function get_notice_transient_key() {
        return self::NOTICE_TRANSIENT_PREFIX . get_current_user_id();
    }

    private function get_form_transient_key() {
        return self::FORM_TRANSIENT_PREFIX . get_current_user_id();
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

    private function set_form_state($form_data) {
        unset($form_data['password']);
        set_transient($this->get_form_transient_key(), $form_data, MINUTE_IN_SECONDS * 5);
    }

    private function consume_form_state() {
        $defaults = [
            'first_name' => '',
            'last_name'  => '',
            'email'      => '',
            'username'   => '',
            'role'       => 'customer',
        ];

        $form_state = get_transient($this->get_form_transient_key());

        if ($form_state) {
            delete_transient($this->get_form_transient_key());
        }

        return is_array($form_state) ? array_merge($defaults, $form_state) : $defaults;
    }

    private function clear_form_state() {
        delete_transient($this->get_form_transient_key());
    }

    private function redirect_back() {
        wp_safe_redirect(self::get_section_url());
        exit;
    }
}
