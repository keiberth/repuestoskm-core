<?php

if (!defined('ABSPATH')) {
    exit;
}

class RKM_Products {

    const SECTION_KEY = 'productos';
    const NOTICE_TRANSIENT_PREFIX = 'rkm_products_notice_';
    const IMAGE_FIELD = 'product_image';
    const GALLERY_FIELD = 'gallery_images';
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

    public static function get_section_url($args = []) {
        return add_query_arg(array_merge(['section' => self::SECTION_KEY], $args), self::get_panel_base_url());
    }

    public static function get_list_url($args = []) {
        return self::get_section_url(array_merge(['view' => 'list'], $args));
    }

    private static function get_panel_base_url() {
        if (function_exists('wc_get_account_endpoint_url')) {
            $panel_url = wc_get_account_endpoint_url('panel');

            if (!empty($panel_url)) {
                return trailingslashit($panel_url);
            }
        }

        return home_url('/mi-cuenta/panel/');
    }

    public static function get_page_title() {
        return 'Productos';
    }

    public static function get_page_subtitle() {
        return 'Gestiona publicaciones WooCommerce desde el panel RKM sin entrar al administrador de WordPress.';
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

        $view = $this->get_current_view();
        $view_data = array_merge($data, [
            'page_title' => self::get_page_title(),
            'page_subtitle' => self::get_page_subtitle(),
            'current_section' => self::get_section_key(),
            'products_notice' => $this->consume_flash_notice(),
            'section_url' => self::get_section_url(),
            'list_url' => self::get_list_url(),
            'create_url' => self::get_section_url(['view' => 'create']),
            'view' => $view,
            'status_options' => $this->get_status_options(),
            'categories' => $this->get_product_categories(),
        ]);

        if ($view === 'create') {
            $view_data['form_action'] = 'create_product';
            $view_data['product'] = null;
        } elseif ($view === 'edit' || $view === 'detail') {
            $product_id = isset($_GET['product_id']) ? absint($_GET['product_id']) : 0;
            $product = $this->get_editable_product($product_id);

            if (!$product) {
                $this->set_flash_notice('error', 'No se encontro el producto seleccionado.');
                wp_safe_redirect(self::get_list_url());
                exit;
            }

            $view_data['product'] = $product;
            $view_data['product_form_data'] = $this->get_product_form_data($product);

            if ($view === 'edit') {
                $view_data['form_action'] = 'update_product';
            }
        } else {
            $page = isset($_GET['products_page']) ? max(1, absint($_GET['products_page'])) : 1;
            $search = isset($_GET['product_search']) ? sanitize_text_field(wp_unslash($_GET['product_search'])) : '';
            $status = isset($_GET['product_status']) ? sanitize_key(wp_unslash($_GET['product_status'])) : '';
            $category_id = isset($_GET['product_cat']) ? absint($_GET['product_cat']) : 0;
            $products_data = $this->get_products_data($page, $search, $status, $category_id);

            $view_data = array_merge($view_data, [
                'products' => $products_data['products'],
                'products_total' => $products_data['total'],
                'products_max_pages' => $products_data['max_num_pages'],
                'products_page' => $page,
                'product_search' => $search,
                'product_status' => $status,
                'product_cat' => $category_id,
            ]);
        }

        $template = RKM_CORE_PATH . 'templates/admin/products.php';

        if (file_exists($template)) {
            $data = $view_data;
            include $template;
        }
    }

    public function handle_submission() {
        if (!$this->is_products_post_request()) {
            return;
        }

        if (!self::can_access()) {
            $this->redirect_to(class_exists('RKM_Auth') ? RKM_Auth::get_redirect_url_for_user() : home_url('/mi-cuenta/panel/'));
        }

        if (!isset($_POST['rkm_products_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['rkm_products_nonce'])), 'rkm_products_update')) {
            $this->set_flash_notice('error', 'La solicitud no es valida. Recarga la pagina e intenta nuevamente.');
            $this->redirect_to(self::get_section_url());
        }

        $action = isset($_POST['rkm_products_action']) ? sanitize_key(wp_unslash($_POST['rkm_products_action'])) : '';

        if ($action === 'create_product') {
            $this->save_product(null);
        }

        if ($action === 'update_product') {
            $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
            $this->save_product($product_id);
        }

        if ($action === 'pause_product') {
            $this->change_product_status('draft');
        }

        if ($action === 'activate_product') {
            $this->change_product_status('publish');
        }

        $this->set_flash_notice('error', 'Accion no reconocida.');
        $this->redirect_to(self::get_section_url());
    }

    private function get_products_data($page, $search, $status, $category_id) {
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
            'status' => $status !== '' ? [$this->sanitize_product_status($status)] : ['publish', 'draft', 'private'],
        ];

        if ($search !== '') {
            $args['s'] = $search;
        }

        if ($category_id > 0) {
            $term = get_term($category_id, 'product_cat');

            if ($term && !is_wp_error($term)) {
                $args['category'] = [$term->slug];
            }
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

    private function save_product($product_id = null) {
        if (!class_exists('WC_Product_Simple')) {
            $this->set_flash_notice('error', 'WooCommerce no esta disponible para guardar productos.');
            $this->redirect_to(self::get_section_url());
        }

        $is_edit = $product_id !== null;
        $product = $is_edit ? $this->get_editable_product($product_id) : new WC_Product_Simple();

        if (!$product) {
            $this->set_flash_notice('error', 'No se encontro el producto seleccionado.');
            $this->redirect_to(self::get_section_url());
        }

        $form_data = $this->get_submitted_product_data();
        $validation = $this->validate_product_data($form_data, $is_edit ? (int) $product->get_id() : 0);

        if (is_wp_error($validation)) {
            $this->set_flash_notice('error', $validation->get_error_message());
            $this->redirect_to($is_edit ? self::get_section_url(['view' => 'edit', 'product_id' => (int) $product->get_id()]) : self::get_section_url(['view' => 'create']));
        }

        $image_id = 0;
        $gallery_image_ids = [];

        if ($this->has_uploaded_image()) {
            $image_id = $this->handle_product_image_upload();

            if (is_wp_error($image_id)) {
                $this->set_flash_notice('error', $image_id->get_error_message());
                $this->redirect_to($is_edit ? self::get_section_url(['view' => 'edit', 'product_id' => (int) $product->get_id()]) : self::get_section_url(['view' => 'create']));
            }
        }

        if ($this->has_uploaded_gallery_images()) {
            $gallery_image_ids = $this->handle_gallery_image_uploads();

            if (is_wp_error($gallery_image_ids)) {
                if ($image_id > 0) {
                    wp_delete_attachment($image_id, true);
                }

                $this->set_flash_notice('error', $gallery_image_ids->get_error_message());
                $this->redirect_to($is_edit ? self::get_section_url(['view' => 'edit', 'product_id' => (int) $product->get_id()]) : self::get_section_url(['view' => 'create']));
            }
        }

        try {
            $current_gallery_ids = $is_edit ? array_map('absint', (array) $product->get_gallery_image_ids()) : [];
            $gallery_ids = array_values(array_diff($current_gallery_ids, $form_data['remove_gallery_image_ids']));
            $gallery_ids = array_values(array_unique(array_merge($gallery_ids, $gallery_image_ids)));

            $product->set_name($form_data['name']);
            $product->set_sku($form_data['sku']);
            $product->set_status($form_data['status']);
            $product->set_regular_price((string) $form_data['regular_price']);
            $product->set_price((string) $form_data['regular_price']);
            $product->set_manage_stock(true);
            $product->set_stock_quantity($form_data['stock']);
            $product->set_stock_status($form_data['stock'] > 0 ? 'instock' : 'outofstock');
            $product->set_short_description($form_data['short_description']);
            $product->set_description($form_data['description']);
            $product->set_category_ids($form_data['category_ids']);
            $product->set_gallery_image_ids($gallery_ids);
            $product->update_meta_data('_rkm_cost_price', $form_data['cost_price']);

            if ($image_id > 0) {
                $product->set_image_id($image_id);
            }

            $saved_id = $product->save();
        } catch (Exception $exception) {
            if ($image_id > 0) {
                wp_delete_attachment($image_id, true);
            }

            $this->delete_attachments($gallery_image_ids);
            $this->set_flash_notice('error', 'No se pudo guardar el producto: ' . $exception->getMessage());
            $this->redirect_to($is_edit ? self::get_section_url(['view' => 'edit', 'product_id' => (int) $product->get_id()]) : self::get_section_url(['view' => 'create']));
        }

        if (!$saved_id) {
            if ($image_id > 0) {
                wp_delete_attachment($image_id, true);
            }

            $this->delete_attachments($gallery_image_ids);
            $this->set_flash_notice('error', 'No se pudo guardar el producto.');
            $this->redirect_to($is_edit ? self::get_section_url(['view' => 'edit', 'product_id' => (int) $product->get_id()]) : self::get_section_url(['view' => 'create']));
        }

        $this->set_flash_notice('success', $is_edit ? 'Producto actualizado correctamente.' : 'Producto creado correctamente.');
        $this->redirect_to(self::get_list_url(['updated' => 1]));
    }

    private function change_product_status($status) {
        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $product = $this->get_editable_product($product_id);

        if (!$product) {
            $this->set_flash_notice('error', 'No se encontro el producto seleccionado.');
            $this->redirect_to(self::get_section_url());
        }

        $product->set_status($this->sanitize_product_status($status));
        $product->save();

        $this->set_flash_notice('success', $status === 'publish' ? 'Publicacion activada correctamente.' : 'Publicacion pausada correctamente.');
        $this->redirect_to(self::get_section_url());
    }

    private function get_submitted_product_data() {
        $category_ids = isset($_POST['category_ids']) ? array_map('absint', (array) wp_unslash($_POST['category_ids'])) : [];
        $category_ids = array_values(array_filter($category_ids));

        return [
            'name' => isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '',
            'sku' => isset($_POST['sku']) ? sanitize_text_field(wp_unslash($_POST['sku'])) : '',
            'category_ids' => $category_ids,
            'short_description' => isset($_POST['short_description']) ? wp_kses_post(wp_unslash($_POST['short_description'])) : '',
            'description' => isset($_POST['description']) ? wp_kses_post(wp_unslash($_POST['description'])) : '',
            'regular_price' => $this->parse_price($_POST['regular_price'] ?? ''),
            'cost_price' => $this->parse_price($_POST['cost_price'] ?? ''),
            'stock' => isset($_POST['stock']) ? max(0, absint($_POST['stock'])) : 0,
            'status' => $this->sanitize_product_status($_POST['status'] ?? 'publish'),
            'remove_gallery_image_ids' => $this->get_submitted_gallery_remove_ids(),
        ];
    }

    private function validate_product_data($data, $current_product_id = 0) {
        if ($data['name'] === '') {
            return new WP_Error('rkm_product_name_required', 'El nombre del producto es obligatorio.');
        }

        if ($data['sku'] === '') {
            return new WP_Error('rkm_product_sku_required', 'El SKU es obligatorio.');
        }

        if ($this->sku_exists($data['sku'], $current_product_id)) {
            return new WP_Error('rkm_product_sku_exists', 'Ya existe un producto con ese SKU.');
        }

        if ($data['regular_price'] < 0 || $data['cost_price'] < 0) {
            return new WP_Error('rkm_product_price_invalid', 'Los precios no pueden ser negativos.');
        }

        foreach ($data['category_ids'] as $category_id) {
            $term = get_term($category_id, 'product_cat');

            if (!$term || is_wp_error($term)) {
                return new WP_Error('rkm_product_category_invalid', 'Selecciona una categoria valida.');
            }
        }

        return true;
    }

    private function get_editable_product($product_id) {
        $product = $product_id > 0 && function_exists('wc_get_product') ? wc_get_product($product_id) : null;

        if (!$product || !is_a($product, 'WC_Product')) {
            return null;
        }

        return $product;
    }

    private function get_product_form_data($product) {
        return [
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'sku' => $product->get_sku(),
            'category_ids' => $product->get_category_ids(),
            'short_description' => wp_strip_all_tags($product->get_short_description()),
            'description' => $product->get_description(),
            'regular_price' => $product->get_regular_price(),
            'cost_price' => $product->get_meta('_rkm_cost_price', true),
            'stock' => $product->get_manage_stock() ? (int) $product->get_stock_quantity() : 0,
            'status' => $product->get_status(),
            'image_id' => $product->get_image_id(),
            'gallery_image_ids' => $product->get_gallery_image_ids(),
        ];
    }

    private function get_product_categories() {
        $terms = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
        ]);

        return is_wp_error($terms) ? [] : $terms;
    }

    private function get_product_category_label($product) {
        $category_ids = $product instanceof WC_Product ? $product->get_category_ids() : [];

        if (empty($category_ids)) {
            return 'Sin categoria';
        }

        $term = get_term((int) $category_ids[0], 'product_cat');

        return $term && !is_wp_error($term) ? $term->name : 'Sin categoria';
    }

    public function get_publication_row($product) {
        if (!$product instanceof WC_Product) {
            return null;
        }

        $image_id = $product->get_image_id();

        return [
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'sku' => $product->get_sku(),
            'category' => $this->get_product_category_label($product),
            'regular_price' => $product->get_regular_price(),
            'cost_price' => $product->get_meta('_rkm_cost_price', true),
            'stock' => $product->get_manage_stock() ? (int) $product->get_stock_quantity() : 0,
            'status' => $product->get_status(),
            'image_url' => $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : '',
            'edit_url' => self::get_section_url(['view' => 'edit', 'product_id' => $product->get_id()]),
            'view_url' => self::get_section_url(['view' => 'detail', 'product_id' => $product->get_id()]),
        ];
    }

    private function sku_exists($sku, $current_product_id = 0) {
        if (!function_exists('wc_get_product_id_by_sku')) {
            return false;
        }

        $existing_id = wc_get_product_id_by_sku($sku);

        return $existing_id > 0 && (int) $existing_id !== (int) $current_product_id;
    }

    private function has_uploaded_image() {
        return !empty($_FILES[self::IMAGE_FIELD])
            && is_array($_FILES[self::IMAGE_FIELD])
            && !empty($_FILES[self::IMAGE_FIELD]['name']);
    }

    private function has_uploaded_gallery_images() {
        if (empty($_FILES[self::GALLERY_FIELD]) || !is_array($_FILES[self::GALLERY_FIELD])) {
            return false;
        }

        $names = $_FILES[self::GALLERY_FIELD]['name'] ?? [];

        if (!is_array($names)) {
            return !empty($names);
        }

        return count(array_filter($names)) > 0;
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

    private function handle_gallery_image_uploads() {
        $files = $this->normalize_uploaded_gallery_files();
        $attachment_ids = [];

        if (empty($files)) {
            return [];
        }

        if (!function_exists('wp_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        if (!function_exists('wp_generate_attachment_metadata')) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        foreach ($files as $file) {
            $validation = $this->validate_gallery_image_file($file);

            if (is_wp_error($validation)) {
                $this->delete_attachments($attachment_ids);
                return $validation;
            }

            $upload = wp_handle_upload($file, [
                'test_form' => false,
                'mimes' => $this->get_allowed_image_mimes(),
            ]);

            if (!empty($upload['error']) || empty($upload['file'])) {
                $this->delete_attachments($attachment_ids);
                return new WP_Error('rkm_product_gallery_save_failed', 'No se pudo guardar una imagen de la galeria.');
            }

            $attachment_id = wp_insert_attachment([
                'post_mime_type' => $upload['type'] ?? $file['type'],
                'post_title' => sanitize_file_name(pathinfo($upload['file'], PATHINFO_FILENAME)),
                'post_content' => '',
                'post_status' => 'inherit',
            ], $upload['file']);

            if (is_wp_error($attachment_id) || !$attachment_id) {
                wp_delete_file($upload['file']);
                $this->delete_attachments($attachment_ids);
                return new WP_Error('rkm_product_gallery_attachment_failed', 'No se pudo registrar una imagen de la galeria.');
            }

            $metadata = wp_generate_attachment_metadata((int) $attachment_id, $upload['file']);

            if (!is_wp_error($metadata) && !empty($metadata)) {
                wp_update_attachment_metadata((int) $attachment_id, $metadata);
            }

            $attachment_ids[] = (int) $attachment_id;
        }

        return $attachment_ids;
    }

    private function normalize_uploaded_gallery_files() {
        $gallery = $_FILES[self::GALLERY_FIELD] ?? [];
        $names = $gallery['name'] ?? [];

        if (!is_array($names)) {
            return [];
        }

        $files = [];

        foreach ($names as $index => $name) {
            if ($name === '') {
                continue;
            }

            $files[] = [
                'name' => $name,
                'type' => $gallery['type'][$index] ?? '',
                'tmp_name' => $gallery['tmp_name'][$index] ?? '',
                'error' => $gallery['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                'size' => $gallery['size'][$index] ?? 0,
            ];
        }

        return $files;
    }

    private function validate_gallery_image_file($file) {
        if (!is_array($file) || empty($file['name'])) {
            return new WP_Error('rkm_product_gallery_empty_file', 'Selecciona imagenes validas para la galeria.');
        }

        if (!empty($file['error'])) {
            return new WP_Error('rkm_product_gallery_upload_error', 'No se pudo leer una imagen de la galeria.');
        }

        if ((int) ($file['size'] ?? 0) > self::IMAGE_MAX_BYTES) {
            return new WP_Error('rkm_product_gallery_too_large', 'Cada imagen de galeria debe pesar como maximo 5 MB.');
        }

        $filetype = wp_check_filetype_and_ext($file['tmp_name'], $file['name'], $this->get_allowed_image_mimes());

        if (empty($filetype['ext']) || empty($filetype['type']) || !in_array($filetype['type'], $this->get_allowed_image_mimes(), true)) {
            return new WP_Error('rkm_product_gallery_invalid_type', 'Las imagenes de galeria deben ser JPG, PNG o WEBP.');
        }

        return true;
    }

    private function get_submitted_gallery_remove_ids() {
        $ids = isset($_POST['remove_gallery_image_ids']) ? (array) wp_unslash($_POST['remove_gallery_image_ids']) : [];
        $ids = array_map('absint', $ids);

        return array_values(array_filter($ids));
    }

    private function delete_attachments($attachment_ids) {
        foreach ((array) $attachment_ids as $attachment_id) {
            if ((int) $attachment_id > 0) {
                wp_delete_attachment((int) $attachment_id, true);
            }
        }
    }

    private function get_allowed_image_mimes() {
        return [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
        ];
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
            'publish' => 'Activa',
            'draft' => 'Pausada',
            'private' => 'Privada',
        ];
    }

    private function get_current_view() {
        $view = isset($_GET['view']) ? sanitize_key(wp_unslash($_GET['view'])) : 'list';

        return in_array($view, ['list', 'create', 'edit', 'detail'], true) ? $view : 'list';
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

    private function is_products_post_request() {
        if (!$this->is_post_request()) {
            return false;
        }

        if (empty($_POST['rkm_products_action'])) {
            return false;
        }

        $section = isset($_REQUEST['section']) ? sanitize_key(wp_unslash($_REQUEST['section'])) : '';

        return $section === self::SECTION_KEY;
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

    private function redirect_to($url) {
        wp_safe_redirect($url, 303);
        exit;
    }
}
