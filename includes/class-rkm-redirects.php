<?php

if (!defined('ABSPATH')) {
    exit;
}

class RKM_Redirects {

    public function init() {
        add_action('template_redirect', [$this, 'handle_home_redirect']);
    }

    public function handle_home_redirect() {
        if (!self::is_system_entry_request()) {
            return;
        }

        if (!is_user_logged_in()) {
            $this->debug_log('Front/home login rendered.');
            $this->render_system_entry_login();
            exit;
        }

        $target = RKM_Auth::get_redirect_url_for_user();

        $this->debug_log('Front/home redirect fired.', [
            'is_front_page' => is_front_page(),
            'is_home'       => is_home(),
            'target'        => $target,
        ]);

        wp_safe_redirect($target);
        exit;
    }

    public static function is_system_entry_request() {
        return !is_admin() && (is_front_page() || is_home());
    }

    private function render_system_entry_login() {
        status_header(200);
        nocache_headers();

        get_header();

        echo '<main id="primary" class="site-main rkm-system-entry">';

        if (function_exists('wc_print_notices')) {
            wc_print_notices();
        }

        if (function_exists('wc_get_template')) {
            wc_get_template('myaccount/form-login.php');
        } else {
            $template = RKM_CORE_PATH . 'templates/auth/login.php';

            if (file_exists($template)) {
                include $template;
            }
        }

        echo '</main>';

        get_footer();
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
