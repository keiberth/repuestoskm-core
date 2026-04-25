<?php

if (!defined('ABSPATH')) {
    exit;
}

class RKM_Payment_Terms {

    const SECTION_KEY = 'condiciones-pago';
    const OPTION_KEY = 'rkm_payment_terms';
    const NOTICE_TRANSIENT_PREFIX = 'rkm_payment_terms_notice_';

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
        return 'Condiciones de pago';
    }

    public static function get_page_subtitle() {
        return 'Configura contado, credito y pago mixto para el flujo de nueva orden.';
    }

    public static function get_term_labels() {
        return [
            'cash'   => 'Contado',
            'credit' => 'Credito',
            'mixed'  => 'Mixto',
        ];
    }

    public static function get_default_settings() {
        return [
            'cash_discount_percent' => 0,
            'terms' => [
                'cash' => [
                    'key'          => 'cash',
                    'label'        => 'Contado',
                    'active'       => 1,
                    'instructions' => 'Pago completo al momento de confirmar el pedido.',
                ],
                'credit' => [
                    'key'          => 'credit',
                    'label'        => 'Credito',
                    'active'       => 1,
                    'instructions' => 'El pedido quedara sujeto a aprobacion de credito.',
                ],
                'mixed' => [
                    'key'          => 'mixed',
                    'label'        => 'Mixto',
                    'active'       => 1,
                    'instructions' => 'Paga una parte ahora y deja el saldo restante a credito.',
                ],
            ],
        ];
    }

    public static function get_settings() {
        return self::sanitize_settings(get_option(self::OPTION_KEY, self::get_default_settings()));
    }

    public static function get_active_terms() {
        $settings = self::get_settings();

        return array_values(array_filter($settings['terms'], static function ($term) {
            return !empty($term['active']);
        }));
    }

    public static function get_active_term($term_key) {
        $term_key = sanitize_key($term_key);

        foreach (self::get_active_terms() as $term) {
            if ($term['key'] === $term_key) {
                return $term;
            }
        }

        return null;
    }

    public static function get_cash_discount_percent() {
        $settings = self::get_settings();

        return (float) $settings['cash_discount_percent'];
    }

    public static function sanitize_settings($settings) {
        $settings = is_array($settings) ? $settings : [];
        $defaults = self::get_default_settings();
        $labels = self::get_term_labels();
        $sanitized_terms = [];

        foreach ($labels as $key => $label) {
            $raw_term = isset($settings['terms'][$key]) && is_array($settings['terms'][$key])
                ? $settings['terms'][$key]
                : $defaults['terms'][$key];

            $sanitized_terms[$key] = [
                'key'          => $key,
                'label'        => $label,
                'active'       => !empty($raw_term['active']) ? 1 : 0,
                'instructions' => isset($raw_term['instructions']) ? sanitize_textarea_field($raw_term['instructions']) : '',
            ];
        }

        $discount = isset($settings['cash_discount_percent']) ? (float) $settings['cash_discount_percent'] : 0;
        $discount = max(0, min(100, $discount));

        return [
            'cash_discount_percent' => $discount,
            'terms'                 => $sanitized_terms,
        ];
    }

    public function enqueue_assets() {
        if (!$this->is_active_section()) {
            return;
        }

        wp_enqueue_style(
            'rkm-admin-payment-terms-css',
            RKM_CORE_URL . 'assets/css/admin-payment-terms.css',
            ['rkm-dashboard-css'],
            '1.0.0'
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
            'payment_terms_settings' => self::get_settings(),
            'payment_terms_notice' => $this->consume_flash_notice(),
        ]);

        $template = RKM_CORE_PATH . 'templates/admin/payment-terms.php';

        if (file_exists($template)) {
            $data = $view_data;
            include $template;
        }
    }

    public function handle_submission() {
        if (!$this->is_active_section() || !$this->is_post_request()) {
            return;
        }

        if (!isset($_POST['rkm_payment_terms_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['rkm_payment_terms_nonce'])), 'rkm_payment_terms_update')) {
            $this->set_flash_notice('error', 'La solicitud no es valida. Recarga la pagina e intenta nuevamente.');
            $this->redirect_back();
        }

        $settings = [
            'cash_discount_percent' => isset($_POST['cash_discount_percent']) ? wp_unslash($_POST['cash_discount_percent']) : 0,
            'terms' => [],
        ];

        foreach (array_keys(self::get_term_labels()) as $term_key) {
            $settings['terms'][$term_key] = [
                'key'          => $term_key,
                'active'       => isset($_POST['terms'][$term_key]['active']) ? 1 : 0,
                'instructions' => isset($_POST['terms'][$term_key]['instructions']) ? wp_unslash($_POST['terms'][$term_key]['instructions']) : '',
            ];
        }

        update_option(self::OPTION_KEY, self::sanitize_settings($settings), false);
        $this->set_flash_notice('success', 'Condiciones de pago actualizadas correctamente.');
        $this->redirect_back();
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
