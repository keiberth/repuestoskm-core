<?php

if (!defined('ABSPATH')) {
    exit;
}

class RKM_Loader {

    public function run() {
        require_once RKM_CORE_PATH . 'includes/class-rkm-routes.php';
        require_once RKM_CORE_PATH . 'includes/class-rkm-permissions.php';
        require_once RKM_CORE_PATH . 'includes/class-rkm-auth.php';
        require_once RKM_CORE_PATH . 'includes/class-rkm-login-ui.php';
        require_once RKM_CORE_PATH . 'includes/class-rkm-dashboard.php';
        require_once RKM_CORE_PATH . 'includes/class-rkm-admin-dashboard.php';
        require_once RKM_CORE_PATH . 'includes/class-rkm-sellers.php';
        require_once RKM_CORE_PATH . 'includes/class-rkm-orders.php';
        require_once RKM_CORE_PATH . 'includes/class-rkm-redirects.php';
        require_once RKM_CORE_PATH . 'includes/class-rkm-orders-actions.php';
        require_once RKM_CORE_PATH . 'includes/class-rkm-account.php';

        (new RKM_Routes())->init();
        (new RKM_Auth())->init();
        (new RKM_Login_UI())->init();
        (new RKM_Dashboard())->init();
        (new RKM_Sellers())->init();
        (new RKM_Orders())->init();
        $redirects = new RKM_Redirects();
        $redirects->init();
        $order_actions = new RKM_Orders_Actions();
        $order_actions->init();
        $account = new RKM_Account();
        
    }
}
