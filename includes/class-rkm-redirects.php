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
            $target = is_user_logged_in()
                ? RKM_Auth::get_redirect_url_for_user()
                : RKM_Auth::get_login_url();

            $this->debug_log('Front/home redirect fired.', [
                'is_front_page' => is_front_page(),
                'is_home'       => is_home(),
                'target'        => $target,
            ]);

            wp_safe_redirect($target);

            exit;
        }
    }

    private function debug_log($message, $context = []) {
        if (!(isset($_GET['rkm_login_debug']) && sanitize_text_field(wp_unslash($_GET['rkm_login_debug'])) === '1')
            && !(isset($_COOKIE['rkm_login_debug']) && sanitize_text_field(wp_unslash($_COOKIE['rkm_login_debug'])) === '1')) {
            return;
        }

        $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        $line = sprintf(
            "[%s] %s %s\n",
            gmdate('c'),
            $message,
            wp_json_encode(array_merge([
                'request_uri' => $request_uri,
                'logged_in'   => is_user_logged_in(),
            ], $context), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        @file_put_contents(WP_CONTENT_DIR . '/rkm-login-debug.log', $line, FILE_APPEND | LOCK_EX);
    }
}
