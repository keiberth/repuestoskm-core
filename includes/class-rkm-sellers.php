<?php

if (!defined('ABSPATH')) {
    exit;
}

class RKM_Sellers {

    const SECTION_KEY = 'panel-vendedor';

    public function init() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public static function can_access($user = null) {
        return RKM_Permissions::is_rkm_vendor($user) || RKM_Permissions::is_rkm_admin($user);
    }

    public static function get_section_key() {
        return self::SECTION_KEY;
    }

    public static function get_section_url() {
        return home_url('/mi-cuenta/panel/?section=' . self::SECTION_KEY);
    }

    public static function get_page_title() {
        return 'Panel de vendedores';
    }

    public static function get_page_subtitle() {
        return 'Espacio de trabajo para vendedores, preparado para futuras herramientas comerciales.';
    }

    public function enqueue_assets() {
        if (!is_user_logged_in()) {
            return;
        }

        if (!(function_exists('is_account_page') && is_account_page())) {
            return;
        }

        $section = isset($_GET['section']) ? sanitize_key($_GET['section']) : 'panel';

        if ($section !== self::SECTION_KEY || !self::can_access()) {
            return;
        }

        wp_enqueue_style(
            'rkm-sellers-css',
            RKM_CORE_URL . 'assets/css/sellers.css',
            ['rkm-catalogo-css'],
            '1.0.0'
        );

        wp_enqueue_script(
            'rkm-sellers-js',
            RKM_CORE_URL . 'assets/js/sellers.js',
            [],
            '1.0.0',
            true
        );

        wp_localize_script(
            'rkm-sellers-js',
            'rkmSellers',
            [
                'section' => self::SECTION_KEY,
                'can_access' => true,
            ]
        );
    }

    public function render_dashboard($data = []) {
        if (!self::can_access()) {
            wp_safe_redirect(RKM_Auth::get_redirect_url_for_user());
            exit;
        }

        $current = self::SECTION_KEY;
        $page_title = self::get_page_title();
        $page_subtitle = self::get_page_subtitle();
        $template = RKM_CORE_PATH . 'templates/sellers/dashboard.php';

        if (file_exists($template)) {
            include $template;
        }
    }
}
