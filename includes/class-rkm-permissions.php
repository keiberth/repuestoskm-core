<?php

if (!defined('ABSPATH')) {
    exit;
}

class RKM_Permissions {

    public static function get_customer_role_candidates() {
        return ['customer'];
    }

    public static function get_vendor_role_candidates() {
        return ['seller', 'vendor', 'vendedor', 'shop_manager'];
    }

    public static function get_vendor_role_for_assignment() {
        foreach (self::get_vendor_role_candidates() as $role) {
            if (get_role($role)) {
                return $role;
            }
        }

        return 'shop_manager';
    }

    public static function get_assignable_user_roles() {
        return [
            self::get_customer_role_candidates()[0] => 'Cliente',
            self::get_vendor_role_for_assignment() => 'Vendedor',
            'administrator'               => 'Administrador',
        ];
    }

    public static function get_role_label($role) {
        $labels = [
            'administrator' => 'Administrador',
            'customer'      => 'Cliente',
            'subscriber'    => 'Suscriptor',
            'seller'        => 'Vendedor',
            'vendor'        => 'Vendedor',
            'vendedor'      => 'Vendedor',
            'shop_manager'  => 'Vendedor',
        ];

        if (isset($labels[$role])) {
            return $labels[$role];
        }

        return ucwords(str_replace(['_', '-'], ' ', (string) $role));
    }

    public static function get_user($user = null) {
        if ($user instanceof WP_User) {
            return $user;
        }

        if (is_numeric($user) && $user > 0) {
            return get_user_by('id', (int) $user);
        }

        return wp_get_current_user();
    }

    public static function user_has_role($user, $roles) {
        $user = self::get_user($user);

        if (!$user instanceof WP_User || empty($user->ID) || empty($user->roles) || !is_array($user->roles)) {
            return false;
        }

        return (bool) array_intersect($roles, $user->roles);
    }

    public static function is_rkm_customer($user = null) {
        return self::user_has_role($user, self::get_customer_role_candidates());
    }

    public static function is_rkm_vendor($user = null) {
        return self::user_has_role($user, self::get_vendor_role_candidates());
    }

    public static function is_rkm_admin($user = null) {
        $user = self::get_user($user);

        if (!$user instanceof WP_User || empty($user->ID)) {
            return false;
        }

        return user_can($user, 'manage_options') || self::user_has_role($user, ['administrator']);
    }

    public static function can_access_rkm_panel($user = null) {
        return self::is_rkm_customer($user) || self::is_rkm_vendor($user) || self::is_rkm_admin($user);
    }
}
