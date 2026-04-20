<?php

if (!defined('ABSPATH')) {
    exit;
}

class RKM_Routes {

    public function init() {
        add_action('init', [$this, 'register_endpoints']);
        add_filter('query_vars', [$this, 'register_query_vars']);
    }

    public function register_endpoints() {
        add_rewrite_endpoint('panel', EP_ROOT | EP_PAGES);
    }

    public function register_query_vars($vars) {
        $vars[] = 'panel';
        return $vars;
    }
}