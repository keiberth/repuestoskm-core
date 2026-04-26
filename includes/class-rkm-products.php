<?php

if (!defined('ABSPATH')) {
    exit;
}

class RKM_Products {

    const SECTION_KEY = 'productos';
    const NOTICE_TRANSIENT_PREFIX = 'rkm_products_notice_';
    const IMAGE_FIELD = 'product_image';
    const IMAGE_MAX_BYTES = 5242880;

    public function init() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('template_redirect', [$this, 'handle_submission'], 5);
    }

    public static function can_access($user = null) {
        return class_exists('RKM_Permissions') && RKM_Permissions::is_rkm_admin($user);
    }

    public static function get_section_key() {
        return self::SECTION_KEY;
    }

    public static function get_section_url() {
        return home_url('/mi-cuenta/panel/?section=' . self::SECTION_KEY);
    }

    public static function get_page_title() {
        return 'Productos';
    }

    public static function get_page_subtitle() {
        return 'Gestiona productos WooCommerce desde el panel RKM sin entrar al administrador de WordPress.';
    }

    public function enqueue_assets() {
        if (!$this->is_active_section()) {
            return;
        }

        wp_enqueue_style(
            'rkm-admin-products-css',
            RKM_CORE_URL . 'assets/css/admin-products.css',
            ['rkm-dashboard-css'],
            '1.0.0'
        );

        wp_enqueue_script(
            'rkm-admin-products-js',
            RKM_CORE_URL . 'assets/js/admin-products.js',
            [],
            '1.0.0',
            true
        );
    }

    public function render_page($data = []) {
        if (!self::can_access()) {
            wp_safe_redirect(class_exists('RKM_Auth') ? RKM_Auth::get_redirect_url_for_user() : home_url('/mi-cuenta/panel/'));
            exit;
        }

        $page = isset($_GET['products_page']) ? max(1, absint($_GET['products_page'])) : 1;
        $search = isset($_GET['product_search']) ? sanitize_text_field(wp_unslash($_GET['product_search'])) : '';
        $products_data = $this->get_products_data($page, $search);

        $view_data = array_merge($data, [
            'page_title' => self::get_page_title(),
            'page_subtitle' => self::get_page_subtitle(),
            'current_section' => self::get_section_key(),
            'products_notice' => $this->consume_flash_notice(),
            'products' => $products_data['products'],
            'products_total' => $products_data['total'],
            'products_max_pages' => $products_data['max_num_pages'],
            'products_page' => $page,
            'product_search' => $search,
            'section_url' => self::get_section_url(),
            'status_options' => $this->get_status_options(),
        ]);

        $template = RKM_CORE_PATH . 'templates/admin/products.php';

        if (file_exists($template)) {
            $data = $view_data;
            include $template;
        }
    }

    public function handle_submission() {
        if (!$this->is_active_section() || !$this->is_post_request()) {
            return;
        }

        if (!isset($_POST['rkm_products_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['rkm_products_nonce'])), 'rkm_products_update')) {
            $this->set_flash_notice('error', 'La solicitud no es valida. Recarga la pagina e intenta nuevamente.');
            $this->redirect_back();
        }

        $action = isset($_POST['rkm_products_action']) ? sanitize_key(wp_unslash($_POST['rkm_products_action'])) : '';

        if ($action === 'create_product') {
            $this->create_product();
        }

        if ($action === 'update_product') {
            $this->update_product();
        }

        $this->set_flash_notice('error', 'Accion no reconocida.');
        $this->redirect_back();
    }

    private function get_products_data($page, $search) {
        if (!function_exists('wc_get_products')) {
            return [
                'products' => [],
                'total' => 0,
                'max_num_pages' => 0,
            ];
        }

        $args = [
            'type' => ['simple'],
            'limit' => 12,
            'page' => max(1, (int) $page),
            'paginate' => true,
            'orderby' => 'date',
            'order' => 'DESC',
            'status' => ['publish', 'draft', 'pending', 'private'],
        ];

        if ($search !== '') {
            $args['s'] = $search;
        }

        $result = wc_get_products($args);

        if (is_object($result) && isset($result->products)) {
            return [
                'products' => is_array($result->products) ? $result->products : [],
                'total' => isset($result->total) ? (int) $result->total : 0,
                'max_num_pages' => isset($result->max_num_pages) ? (int) $result->max_num_pages : 0,
            ];
        }

        return [
            'products' => is_array($result) ? $result : [],
            'total' => is_array($result) ? count($result) : 0,
            'max_num_pages' => 1,
        ];
    }

    private function create_product() {
        if (!class_exists('WC_Product_Simple')) {
            $this->set_flash_notice('error', 'WooCommerce no esta disponible para crear productos.');
            $this->redirect_back();
        }

        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $price = $this->parse_price($_POST['price'] ?? '');
        $stock = isset($_POST['stock']) ? max(0, absint($_POST['stock'])) : 0;
        $description = isset($_POST['description']) ? wp_kses_post(wp_unslash($_POST['description'])) : '';
        $status = $this->sanitize_product_status($_POST['status'] ?? 'publish');

        if ($name === '') {
            $this->set_flash_notice('error', 'El nombre del producto es obligatorio.');
            $this->redirect_back();
        }

        if ($price < 0) {
            $this->set_flash_notice('error', 'El precio no puede ser negativo.');
            $this->redirect_back();
        }

        if ($this->product_name_exists($name)) {
            $this->set_flash_notice('error', 'Ya existe un producto con ese nombre.');
            $this->redirect_back();
        }

        $image_id = 0;

        if ($this->has_uploaded_image()) {
            $image_id = $this->handle_product_image_upload();

            if (is_wp_error($image_id)) {
                $this->set_flash_notice('error', $image_id->get_error_message());
                $this->redirect_back();
            }
        }

        $product = new WC_Product_Simple();
        $product->set_name($name);
        $product->set_status($status);
        $product->set_regular_price((string) $price);
        $product->set_price((string) $price);
        $product->set_manage_stock(true);
        $product->set_stock_quantity($stock);
        $product->set_stock_status($stock > 0 ? 'instock' : 'outofstock');
        $product->set_description($description);

        if ($image_id > 0) {
            $product->set_image_id($image_id);
        }

        $product_id = $product->save();

        if (!$product_id) {
            if ($image_id > 0) {
                wp_delete_attachment($image_id, true);
            }

            $this->set_flash_notice('error', 'No se pudo crear el producto.');
            $this->redirect_back();
        }

        $this->set_flash_notice('success', 'Producto creado correctamente.');
        $this->redirect_back();
    }

    private function update_product() {
        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $product = $product_id > 0 && function_exists('wc_get_product') ? wc_get_product($product_id) : null;

        if (!$product || !is_a($product, 'WC_Product')) {
            $this->set_flash_notice('error', 'No se encontro el producto seleccionado.');
            $this->redirect_back();
        }

        $price = $this->parse_price($_POST['price'] ?? '');
        $stock = isset($_POST['stock']) ? max(0, absint($_POST['stock'])) : 0;
        $status = $this->sanitize_product_status($_POST['status'] ?? $product->get_status());

        if ($price < 0) {
            $this->set_flash_notice('error', 'El precio no puede ser negativo.');
            $this->redirect_back();
        }

        $product->set_regular_price((string) $price);
        $product->set_price((string) $price);
        $product->set_manage_stock(true);
        $product->set_stock_quantity($stock);
        $product->set_stock_status($stock > 0 ? 'instock' : 'outofstock');
        $product->set_status($status);
        $product->save();

        $this->set_flash_notice('success', 'Producto actualizado correctamente.');
        $this->redirect_back();
    }

    private function has_uploaded_image() {
        return !empty($_FILES[self::IMAGE_FIELD])
            && is_array($_FILES[self::IMAGE_FIELD])
            && !empty($_FILES[self::IMAGE_FIELD]['name']);
    }

    private function handle_product_image_upload() {
        $validation = $this->validate_product_image_upload();

        if (is_wp_error($validation)) {
            return $validation;
        }

        if (!function_exists('media_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }

        $attachment_id = media_handle_upload(self::IMAGE_FIELD, 0);

        if (is_wp_error($attachment_id)) {
            return new WP_Error('rkm_product_image_save_failed', 'No se pudo guardar la imagen del producto.');
        }

        return (int) $attachment_id;
    }

    private function validate_product_image_upload() {
        $file = $_FILES[self::IMAGE_FIELD] ?? null;

        if (!is_array($file) || empty($file['name'])) {
            return true;
        }

        if (!empty($file['error'])) {
            return new WP_Error('rkm_product_image_upload_error', 'No se pudo leer la imagen adjunta.');
        }

        if ((int) ($file['size'] ?? 0) > self::IMAGE_MAX_BYTES) {
            return new WP_Error('rkm_product_image_too_large', 'La imagen no puede superar 5 MB.');
        }

        $allowed_mimes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
        ];
        $filetype = wp_check_filetype_and_ext($file['tmp_name'], $file['name'], $allowed_mimes);

        if (empty($filetype['ext']) || empty($filetype['type']) || !in_array($filetype['type'], $allowed_mimes, true)) {
            return new WP_Error('rkm_product_image_invalid_type', 'La imagen debe ser JPG, PNG o WEBP.');
        }

        return true;
    }

    private function product_name_exists($name) {
        if (!function_exists('wc_get_products')) {
            return false;
        }

        $products = wc_get_products([
            'limit' => 10,
            's' => $name,
            'status' => ['publish', 'draft', 'pending', 'private'],
            'return' => 'objects',
        ]);

        foreach ((array) $products as $product) {
            if ($product instanceof WC_Product && mb_strtolower($product->get_name()) === mb_strtolower($name)) {
                return true;
            }
        }

        return false;
    }

    private function parse_price($value) {
        if (function_exists('wc_format_decimal')) {
            return (float) wc_format_decimal(wp_unslash($value));
        }

        $value = str_replace(',', '.', (string) wp_unslash($value));
        $value = preg_replace('/[^0-9\.]/', '', $value);

        return (float) $value;
    }

    private function sanitize_product_status($status) {
        $status = sanitize_key(wp_unslash($status));

        return array_key_exists($status, $this->get_status_options()) ? $status : 'publish';
    }

    private function get_status_options() {
        return [
            'publish' => 'Publicado',
            'draft' => 'Borrador',
            'private' => 'Privado',
        ];
    }

    private function is_active_section() {
        if (!is_user_logged_in() || !self::can_access()) {
            return false;
        }

        if (!(function_exists('is_account_page') && is_account_page())) {
            return false;
        }

        $section = isset($_GET['section']) ? sanitize_key(wp_unslash($_GET['section'])) : 'panel';

        return $section === self::SECTION_KEY;
    }

    private function is_post_request() {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }

    private function get_notice_transient_key() {
        return self::NOTICE_TRANSIENT_PREFIX . get_current_user_id();
    }

    private function set_flash_notice($type, $message) {
        set_transient($this->get_notice_transient_key(), [
            'type' => $type,
            'message' => $message,
        ], MINUTE_IN_SECONDS * 5);
    }

    private function consume_flash_notice() {
        $notice = get_transient($this->get_notice_transient_key());

        if ($notice) {
            delete_transient($this->get_notice_transient_key());
        }

        return is_array($notice) ? $notice : null;
    }

    private function redirect_back() {
        wp_safe_redirect(self::get_section_url());
        exit;
    }
}
