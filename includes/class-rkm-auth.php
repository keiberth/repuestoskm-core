<?php

if (!defined('ABSPATH')) {
    exit;
}

class RKM_Auth {

    public function init() {
        add_action('init', [$this, 'handle_debug_toggle'], 1);
        add_action('admin_init', [$this, 'redirect_admin_dashboard_entry'], 1);
        add_action('template_redirect', [$this, 'debug_frontend_request_state'], 1);
        add_action('admin_init', [$this, 'debug_admin_request_state'], 1);
        add_action('wp_login', [$this, 'debug_wp_login'], 10, 2);
        add_action('wp_login_failed', [$this, 'debug_wp_login_failed'], 10, 2);
        add_action('set_logged_in_cookie', [$this, 'debug_set_logged_in_cookie'], 10, 6);
        add_action('clear_auth_cookie', [$this, 'debug_clear_auth_cookie']);
        add_filter('login_redirect', [$this, 'redirect_after_login'], 10, 3);
        add_filter('woocommerce_login_redirect', [$this, 'redirect_after_woocommerce_login'], 10, 2);
        add_filter('woocommerce_process_login_errors', [$this, 'debug_woocommerce_login_errors'], 9999, 3);
        add_filter('logout_redirect', [$this, 'redirect_after_logout'], 10, 3);
    }

    public function handle_debug_toggle() {
        if (!isset($_GET['rkm_login_debug'])) {
            return;
        }

        $enabled = sanitize_text_field(wp_unslash($_GET['rkm_login_debug'])) === '1';
        $path = defined('COOKIEPATH') && COOKIEPATH ? COOKIEPATH : '/';

        setcookie('rkm_login_debug', $enabled ? '1' : '0', time() + 3600, $path, COOKIE_DOMAIN, is_ssl(), true);

        $this->debug_log('Debug toggle changed.', [
            'enabled' => $enabled,
        ]);
    }

    public static function get_login_url() {
        if (function_exists('wc_get_page_permalink')) {
            $account_url = wc_get_page_permalink('myaccount');

            if (!empty($account_url)) {
                return $account_url;
            }
        }

        return home_url('/mi-cuenta/');
    }

    public static function get_customer_redirect_url() {
        return home_url('/mi-cuenta/panel');
    }

    public static function get_admin_panel_url() {
        return apply_filters('rkm_admin_panel_url', home_url('/mi-cuenta/panel/'));
    }

    public static function get_vendor_redirect_url() {
        return apply_filters(
            'rkm_vendor_redirect_url',
            home_url('/mi-cuenta/panel/?section=panel-vendedor')
        );
    }

    public static function get_admin_redirect_url() {
        return apply_filters('rkm_admin_redirect_url', self::get_admin_panel_url());
    }

    public static function get_redirect_url_for_user($user = null) {
        $user = RKM_Permissions::get_user($user);

        if (!$user instanceof WP_User || empty($user->ID)) {
            return self::get_login_url();
        }

        if (RKM_Permissions::is_rkm_admin($user)) {
            return self::get_admin_redirect_url();
        }

        if (RKM_Permissions::is_rkm_vendor($user)) {
            return self::get_vendor_redirect_url();
        }

        if (RKM_Permissions::is_rkm_customer($user)) {
            return self::get_customer_redirect_url();
        }

        return self::get_customer_redirect_url();
    }

    public static function get_logout_redirect_url() {
        return apply_filters('rkm_logout_redirect_url', self::get_login_url());
    }

    public static function get_logout_url() {
        if (function_exists('wc_logout_url')) {
            return wc_logout_url(self::get_logout_redirect_url());
        }

        return wp_logout_url(self::get_logout_redirect_url());
    }

    public function redirect_after_login($redirect_to, $request, $user) {
        if ($user instanceof WP_User) {
            $target = self::get_redirect_url_for_user($user);

            $this->debug_log('login_redirect fired.', [
                'incoming_redirect_to' => $redirect_to,
                'request'              => $request,
                'target'               => $target,
                'user_id'              => $user->ID,
                'user_login'           => $user->user_login,
                'roles'                => $user->roles,
            ]);

            return $target;
        }

        $this->debug_log('login_redirect skipped because user is not a WP_User.', [
            'incoming_redirect_to' => $redirect_to,
            'request'              => $request,
            'user_type'            => is_object($user) ? get_class($user) : gettype($user),
        ]);

        return $redirect_to;
    }

    public function redirect_after_woocommerce_login($redirect_to, $user) {
        if ($user instanceof WP_User) {
            $target = self::get_redirect_url_for_user($user);

            $this->debug_log('woocommerce_login_redirect fired.', [
                'incoming_redirect_to' => $redirect_to,
                'target'               => $target,
                'user_id'              => $user->ID,
                'user_login'           => $user->user_login,
                'roles'                => $user->roles,
            ]);

            return $target;
        }

        $this->debug_log('woocommerce_login_redirect skipped because user is not a WP_User.', [
            'incoming_redirect_to' => $redirect_to,
            'user_type'            => is_object($user) ? get_class($user) : gettype($user),
        ]);

        return $redirect_to;
    }

    public function redirect_after_logout($redirect_to, $requested_redirect_to, $user) {
        $target = self::get_logout_redirect_url();

        $this->debug_log('logout_redirect fired.', [
            'incoming_redirect_to'  => $redirect_to,
            'requested_redirect_to' => $requested_redirect_to,
            'target'                => $target,
            'user_type'             => is_object($user) ? get_class($user) : gettype($user),
        ]);

        return $target;
    }

    public function redirect_admin_dashboard_entry() {
        if (!$this->should_redirect_admin_dashboard_entry()) {
            return;
        }

        $target = self::get_admin_redirect_url();

        $this->debug_log('Admin dashboard entry redirected to custom panel.', [
            'target' => $target,
        ]);

        wp_safe_redirect($target);
        exit;
    }

    public function debug_frontend_request_state() {
        if (!$this->is_debug_enabled()) {
            return;
        }

        if (!function_exists('is_account_page') || !is_account_page()) {
            return;
        }

        $this->debug_log('Frontend account request observed.', [
            'login_url'   => self::get_login_url(),
            'is_account'  => true,
            'account_url' => function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : '',
        ]);
    }

    public function debug_admin_request_state() {
        if (!$this->is_debug_enabled()) {
            return;
        }

        $this->debug_log('Admin request observed.', [
            'admin_url'      => admin_url(),
            'redirect_match' => $this->should_redirect_admin_dashboard_entry(),
        ]);
    }

    public function debug_wp_login($user_login, $user) {
        $this->debug_log('wp_login action fired.', [
            'user_login' => $user_login,
            'user_id'    => $user instanceof WP_User ? $user->ID : null,
            'roles'      => $user instanceof WP_User ? $user->roles : [],
            'admin_url'  => admin_url(),
        ]);
    }

    public function debug_wp_login_failed($username, $error = null) {
        $this->debug_log('wp_login_failed action fired.', [
            'username'       => $username,
            'error_code'     => $error instanceof WP_Error ? $error->get_error_code() : null,
            'error_messages' => $error instanceof WP_Error ? $error->get_error_messages() : [],
        ]);
    }

    public function debug_set_logged_in_cookie($logged_in_cookie, $expire, $expiration, $user_id, $scheme, $token) {
        $this->debug_log('set_logged_in_cookie action fired.', [
            'user_id'          => $user_id,
            'scheme'           => $scheme,
            'expire'           => $expire,
            'expiration'       => $expiration,
            'cookie_length'    => is_string($logged_in_cookie) ? strlen($logged_in_cookie) : 0,
            'token_present'    => $token !== '',
        ]);
    }

    public function debug_clear_auth_cookie() {
        $this->debug_log('clear_auth_cookie action fired.');
    }

    public function debug_woocommerce_login_errors($validation_error, $username, $password) {
        $this->debug_log('woocommerce_process_login_errors filter fired.', [
            'username'       => $username,
            'has_errors'     => $validation_error instanceof WP_Error && $validation_error->has_errors(),
            'error_codes'    => $validation_error instanceof WP_Error ? $validation_error->get_error_codes() : [],
            'error_messages' => $validation_error instanceof WP_Error ? $validation_error->get_error_messages() : [],
            'password_sent'  => $password !== '',
        ]);

        return $validation_error;
    }

    private function debug_log($message, $context = []) {
        if (!$this->is_debug_enabled()) {
            return;
        }

        $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        $current_user = wp_get_current_user();

        $payload = array_merge([
            'request_uri' => $request_uri,
            'logged_in'   => is_user_logged_in(),
        ], $context);

        if ($current_user instanceof WP_User && !empty($current_user->ID)) {
            $payload['current_user'] = [
                'id'    => $current_user->ID,
                'login' => $current_user->user_login,
                'roles' => $current_user->roles,
            ];
        }

        $line = sprintf(
            "[%s] %s %s\n",
            gmdate('c'),
            $message,
            wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        @file_put_contents(WP_CONTENT_DIR . '/rkm-login-debug.log', $line, FILE_APPEND | LOCK_EX);
    }

    private function is_debug_enabled() {
        if (isset($_GET['rkm_login_debug'])) {
            return sanitize_text_field(wp_unslash($_GET['rkm_login_debug'])) === '1';
        }

        return isset($_COOKIE['rkm_login_debug']) && sanitize_text_field(wp_unslash($_COOKIE['rkm_login_debug'])) === '1';
    }

    private function should_redirect_admin_dashboard_entry() {
        if (!is_admin() || !is_user_logged_in() || !RKM_Permissions::is_rkm_admin()) {
            return false;
        }

        if (wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST) || (defined('DOING_CRON') && DOING_CRON)) {
            return false;
        }

        if (is_network_admin() || is_user_admin()) {
            return false;
        }

        if (isset($_GET['rkm_keep_wp_admin']) && sanitize_text_field(wp_unslash($_GET['rkm_keep_wp_admin'])) === '1') {
            return false;
        }

        global $pagenow;

        return $pagenow === 'index.php';
    }
}
