<?php

if (!defined('ABSPATH')) {
    exit;
}

class RKM_Login_UI {

    public function init() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_filter('body_class', [$this, 'add_body_class']);
        add_filter('woocommerce_locate_template', [$this, 'override_login_template'], 10, 3);
    }

    public function enqueue_assets() {
        if (!$this->is_login_screen()) {
            return;
        }

        wp_enqueue_style(
            'rkm-base-css',
            RKM_CORE_URL . 'assets/css/base.css',
            [],
            '1.0.0'
        );

        wp_enqueue_style(
            'rkm-login-css',
            RKM_CORE_URL . 'assets/css/login.css',
            ['rkm-base-css'],
            '1.0.0'
        );

        wp_enqueue_script(
            'rkm-login-js',
            RKM_CORE_URL . 'assets/js/login.js',
            [],
            '1.0.0',
            true
        );
    }

    public function add_body_class($classes) {
        if ($this->is_login_screen()) {
            $classes[] = 'rkm-login-ui';
        }

        return $classes;
    }

    public function override_login_template($template, $template_name, $template_path) {
        if ($template_name !== 'myaccount/form-login.php' || !$this->is_login_screen()) {
            return $template;
        }

        $custom_template = RKM_CORE_PATH . 'templates/auth/login.php';

        return file_exists($custom_template) ? $custom_template : $template;
    }

    private function is_login_screen() {
        return !is_user_logged_in()
            && function_exists('is_account_page')
            && is_account_page();
    }
}
