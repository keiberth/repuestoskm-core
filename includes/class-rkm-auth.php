<?php

if (!defined('ABSPATH')) {
    exit;
}

class RKM_Auth {

    public function init() {
        add_filter('login_redirect', [$this, 'redirect_after_login'], 10, 3);
        add_filter('woocommerce_login_redirect', [$this, 'redirect_after_woocommerce_login'], 10, 2);
        add_filter('logout_redirect', [$this, 'redirect_after_logout'], 10, 3);
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

    public static function get_vendor_redirect_url() {
        return apply_filters(
            'rkm_vendor_redirect_url',
            home_url('/mi-cuenta/panel/?section=panel-vendedor')
        );
    }

    public static function get_admin_redirect_url() {
        return apply_filters('rkm_admin_redirect_url', admin_url());
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
            return self::get_redirect_url_for_user($user);
        }

        return $redirect_to;
    }

    public function redirect_after_woocommerce_login($redirect_to, $user) {
        if ($user instanceof WP_User) {
            return self::get_redirect_url_for_user($user);
        }

        return $redirect_to;
    }

    public function redirect_after_logout($redirect_to, $requested_redirect_to, $user) {
        return self::get_logout_redirect_url();
    }
}
