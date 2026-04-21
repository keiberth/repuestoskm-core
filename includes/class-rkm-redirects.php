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
                wp_safe_redirect(RKM_Auth::get_redirect_url_for_user());
            } else {
                wp_safe_redirect(RKM_Auth::get_login_url());
            }

            exit;
        }
    }
}
