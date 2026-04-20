<?php

if (!defined('ABSPATH')) {
    exit;
}

class RKM_Redirects {

    public function init() {
        add_action('template_redirect', [$this, 'handle_home_redirect']);
    }

    public function handle_home_redirect() {
        if (is_admin()) {
            return;
        }

        if (is_front_page() || is_home()) {

            if (is_user_logged_in()) {
                wp_redirect(home_url('/mi-cuenta/panel/'));
            } else {
                wp_redirect(wp_login_url());
            }

            exit;
        }
    }
}