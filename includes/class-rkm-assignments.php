<?php

if (!defined('ABSPATH')) {
    exit;
}

class RKM_Assignments {

    const SECTION_KEY = 'asignaciones';
    const CUSTOMER_VENDOR_META_KEY = 'assigned_vendor_id';
    const NOTICE_TRANSIENT_PREFIX = 'rkm_assignments_notice_';

    public function init() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('template_redirect', [$this, 'handle_assignment_submission'], 5);
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

    public static function get_assignment_meta_key() {
        return self::CUSTOMER_VENDOR_META_KEY;
    }

    public static function get_assigned_vendor_id($customer = null) {
        $customer = RKM_Permissions::get_user($customer);

        if (!$customer instanceof WP_User || empty($customer->ID) || !RKM_Permissions::is_rkm_customer($customer)) {
            return 0;
        }

        return (int) get_user_meta($customer->ID, self::get_assignment_meta_key(), true);
    }

    public static function get_assigned_customer_ids($vendor = null) {
        $vendor = RKM_Permissions::get_user($vendor);

        if (!$vendor instanceof WP_User || empty($vendor->ID) || !RKM_Permissions::is_rkm_vendor($vendor)) {
            return [];
        }

        $customers = get_users([
            'role__in'   => RKM_Permissions::get_customer_role_candidates(),
            'fields'     => 'ID',
            'number'     => -1,
            'orderby'    => 'display_name',
            'order'      => 'ASC',
            'meta_key'   => self::get_assignment_meta_key(),
            'meta_value' => $vendor->ID,
        ]);

        return array_map('intval', is_array($customers) ? $customers : []);
    }

    public static function get_assigned_customer_query_args($vendor = null) {
        $vendor = RKM_Permissions::get_user($vendor);

        if (!$vendor instanceof WP_User || empty($vendor->ID) || !RKM_Permissions::is_rkm_vendor($vendor)) {
            return [
                'include' => [0],
            ];
        }

        return [
            'role__in'   => RKM_Permissions::get_customer_role_candidates(),
            'meta_key'   => self::get_assignment_meta_key(),
            'meta_value' => $vendor->ID,
        ];
    }

    public static function get_page_title() {
        return 'Asignacion de clientes a vendedores';
    }

    public static function get_page_subtitle() {
        return 'Relaciona clientes con vendedores para preparar carteras comerciales, vistas filtradas y futuros reportes.';
    }

    public function enqueue_assets() {
        if (!$this->is_active_section()) {
            return;
        }

        wp_enqueue_style(
            'rkm-admin-assignments-css',
            RKM_CORE_URL . 'assets/css/admin-assignments.css',
            ['rkm-dashboard-css'],
            '1.0.0'
        );

        wp_enqueue_script(
            'rkm-admin-assignments-js',
            RKM_CORE_URL . 'assets/js/admin-assignments.js',
            [],
            '1.0.0',
            true
        );
    }

    public function handle_assignment_submission() {
        if (!$this->is_active_section() || !$this->is_update_request()) {
            return;
        }

        if (!isset($_POST['rkm_assignments_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['rkm_assignments_nonce'])), 'rkm_assignments_update')) {
            $this->set_flash_notice('error', 'La solicitud no es valida. Recarga la pagina e intenta nuevamente.');
            $this->redirect_back();
        }

        $customer_id = isset($_POST['customer_id']) ? absint($_POST['customer_id']) : 0;
        $vendor_id = isset($_POST['assigned_vendor_id']) ? absint($_POST['assigned_vendor_id']) : 0;

        $validation = $this->validate_assignment($customer_id, $vendor_id);

        if (is_wp_error($validation)) {
            $this->set_flash_notice('error', $validation->get_error_message());
            $this->redirect_back();
        }

        if ($vendor_id > 0) {
            update_user_meta($customer_id, self::get_assignment_meta_key(), $vendor_id);
            $vendor = get_user_by('id', $vendor_id);
            $vendor_name = $vendor instanceof WP_User ? ($vendor->display_name ?: $vendor->user_login) : 'el vendedor seleccionado';

            $this->set_flash_notice('success', sprintf('Cliente asignado correctamente a %s.', $vendor_name));
            $this->redirect_back();
        }

        delete_user_meta($customer_id, self::get_assignment_meta_key());
        $this->set_flash_notice('success', 'Asignacion removida correctamente.');
        $this->redirect_back();
    }

    public function render_page($data = []) {
        if (!self::can_access()) {
            wp_safe_redirect(RKM_Auth::get_redirect_url_for_user());
            exit;
        }

        $view_data = array_merge($data, [
            'page_title'             => self::get_page_title(),
            'page_subtitle'          => self::get_page_subtitle(),
            'current_section'        => self::get_section_key(),
            'assignments_notice'     => $this->consume_flash_notice(),
            'assignments_rows'       => $this->get_assignment_rows(),
            'assignments_vendors'    => $this->get_vendor_options(),
            'assignments_summary'    => $this->get_summary_cards(),
            'assignment_meta_key'    => self::get_assignment_meta_key(),
        ]);

        $template = RKM_CORE_PATH . 'templates/admin/assignments.php';

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

    private function is_update_request() {
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return false;
        }

        return isset($_POST['rkm_assignments_action'])
            && sanitize_key(wp_unslash($_POST['rkm_assignments_action'])) === 'save_assignment';
    }

    private function validate_assignment($customer_id, $vendor_id) {
        $customer = get_user_by('id', $customer_id);

        if (!$customer instanceof WP_User || !RKM_Permissions::is_rkm_customer($customer)) {
            return new WP_Error('invalid_customer', 'El cliente seleccionado no es valido.');
        }

        if ($vendor_id <= 0) {
            return true;
        }

        $vendor = get_user_by('id', $vendor_id);

        if (!$vendor instanceof WP_User || !RKM_Permissions::is_rkm_vendor($vendor)) {
            return new WP_Error('invalid_vendor', 'El vendedor seleccionado no es valido.');
        }

        return true;
    }

    private function get_vendor_options() {
        $vendors = get_users([
            'role__in' => RKM_Permissions::get_vendor_role_candidates(),
            'orderby'  => 'display_name',
            'order'    => 'ASC',
        ]);

        $options = [
            [
                'id'    => 0,
                'label' => 'Sin asignar',
            ],
        ];

        foreach ($vendors as $vendor) {
            if (!$vendor instanceof WP_User) {
                continue;
            }

            $options[] = [
                'id'    => (int) $vendor->ID,
                'label' => $vendor->display_name ? $vendor->display_name : $vendor->user_login,
            ];
        }

        return $options;
    }

    private function get_assignment_rows() {
        $customers = get_users([
            'role__in' => RKM_Permissions::get_customer_role_candidates(),
            'orderby'  => 'display_name',
            'order'    => 'ASC',
        ]);

        $rows = [];

        foreach ($customers as $customer) {
            if (!$customer instanceof WP_User) {
                continue;
            }

            $assigned_vendor_id = self::get_assigned_vendor_id($customer);
            $assigned_vendor = $assigned_vendor_id > 0 ? get_user_by('id', $assigned_vendor_id) : null;
            $name = trim($customer->first_name . ' ' . $customer->last_name);

            $rows[] = [
                'id'                    => (int) $customer->ID,
                'name'                  => $name !== '' ? $name : ($customer->display_name ?: $customer->user_login),
                'username'              => $customer->user_login,
                'email'                 => $customer->user_email,
                'assigned_vendor_id'    => $assigned_vendor_id,
                'assigned_vendor_label' => $assigned_vendor instanceof WP_User
                    ? ($assigned_vendor->display_name ?: $assigned_vendor->user_login)
                    : 'Sin asignar',
                'status'                => ((int) $customer->user_status === 0) ? 'Activo' : 'Inactivo',
                'registered'            => mysql2date('d/m/Y', $customer->user_registered, true),
            ];
        }

        return $rows;
    }

    private function get_summary_cards() {
        $customers = count(get_users([
            'role__in' => RKM_Permissions::get_customer_role_candidates(),
            'fields'   => 'ID',
        ]));

        $vendors = count(get_users([
            'role__in' => RKM_Permissions::get_vendor_role_candidates(),
            'fields'   => 'ID',
        ]));

        $assigned = count(get_users([
            'role__in'    => RKM_Permissions::get_customer_role_candidates(),
            'fields'      => 'ID',
            'meta_query'  => [
                [
                    'key'     => self::get_assignment_meta_key(),
                    'compare' => 'EXISTS',
                ],
            ],
        ]));

        return [
            [
                'label' => 'Clientes listados',
                'value' => $customers,
                'meta'  => 'Base actual de usuarios con rol cliente',
            ],
            [
                'label' => 'Vendedores disponibles',
                'value' => $vendors,
                'meta'  => 'Usuarios comerciales elegibles para asignacion',
            ],
            [
                'label' => 'Clientes asignados',
                'value' => $assigned,
                'meta'  => 'Listos para futuras vistas filtradas por vendedor',
            ],
        ];
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
