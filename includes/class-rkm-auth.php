<?php

if (!defined('ABSPATH')) {
    exit;
}

class RKM_Auth {

    public function init() {
        add_filter('login_redirect', [$this, 'redirect_after_login'], 10, 3);
    }

    public function redirect_after_login($redirect_to, $request, $user) {
        if (isset($user->roles) && is_array($user->roles)) {
            return home_url('/mi-cuenta/panel');
        }

        return $redirect_to;
    }
}