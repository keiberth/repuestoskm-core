<?php

if (!defined('ABSPATH')) {
    exit;
}

class RKM_Vehicle_Compatibility {

    const TAX_BRAND = 'rkm_vehicle_brand';
    const TAX_MODEL = 'rkm_vehicle_model';
    const TAX_PART_CATEGORY = 'rkm_part_category';
    const META_YEAR_FROM = '_rkm_year_from';
    const META_YEAR_TO = '_rkm_year_to';
    const META_ENGINE_VERSION = '_rkm_engine_version';

    public function init() {
        add_action('init', [$this, 'register_taxonomies']);
    }

    public function register_taxonomies() {
        $this->register_product_taxonomy(self::TAX_BRAND, 'Marca vehiculo', 'Marcas vehiculo');
        $this->register_product_taxonomy(self::TAX_MODEL, 'Modelo vehiculo', 'Modelos vehiculo');
        $this->register_product_taxonomy(self::TAX_PART_CATEGORY, 'Categoria de repuesto', 'Categorias de repuesto');
    }

    private function register_product_taxonomy($taxonomy, $singular, $plural) {
        register_taxonomy($taxonomy, ['product'], [
            'labels' => [
                'name' => $plural,
                'singular_name' => $singular,
                'search_items' => 'Buscar ' . strtolower($plural),
                'all_items' => 'Todas las ' . strtolower($plural),
                'edit_item' => 'Editar ' . strtolower($singular),
                'update_item' => 'Actualizar ' . strtolower($singular),
                'add_new_item' => 'Agregar ' . strtolower($singular),
                'new_item_name' => 'Nueva ' . strtolower($singular),
                'menu_name' => $plural,
            ],
            'public' => false,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_quick_edit' => false,
            'show_in_rest' => true,
            'hierarchical' => false,
            'rewrite' => false,
            'query_var' => true,
            'capabilities' => [
                'manage_terms' => 'manage_woocommerce',
                'edit_terms' => 'manage_woocommerce',
                'delete_terms' => 'manage_woocommerce',
                'assign_terms' => 'edit_products',
            ],
        ]);
    }

    public static function get_taxonomies() {
        return [
            self::TAX_BRAND,
            self::TAX_MODEL,
            self::TAX_PART_CATEGORY,
        ];
    }

    public static function get_terms($taxonomy) {
        if (!taxonomy_exists($taxonomy)) {
            return [];
        }

        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
        ]);

        return is_wp_error($terms) ? [] : $terms;
    }

    public static function get_product_data($product) {
        $is_product = is_a($product, 'WC_Product');
        $product_id = $is_product ? (int) $product->get_id() : 0;

        return [
            'vehicle_brand' => self::get_first_term_name($product_id, self::TAX_BRAND),
            'vehicle_model' => self::get_first_term_name($product_id, self::TAX_MODEL),
            'part_category' => self::get_first_term_name($product_id, self::TAX_PART_CATEGORY),
            'year_from' => $is_product ? (string) $product->get_meta(self::META_YEAR_FROM, true) : '',
            'year_to' => $is_product ? (string) $product->get_meta(self::META_YEAR_TO, true) : '',
            'engine_version' => $is_product ? (string) $product->get_meta(self::META_ENGINE_VERSION, true) : '',
        ];
    }

    private static function get_first_term_name($product_id, $taxonomy) {
        if ($product_id <= 0 || !taxonomy_exists($taxonomy)) {
            return '';
        }

        $terms = get_the_terms($product_id, $taxonomy);

        if (empty($terms) || is_wp_error($terms)) {
            return '';
        }

        $term = reset($terms);

        return $term && isset($term->name) ? (string) $term->name : '';
    }

    public static function get_submitted_data($source = null) {
        $source = is_array($source) ? $source : $_POST;

        return [
            'vehicle_brand' => isset($source['rkm_vehicle_brand']) ? sanitize_text_field(wp_unslash($source['rkm_vehicle_brand'])) : '',
            'vehicle_model' => isset($source['rkm_vehicle_model']) ? sanitize_text_field(wp_unslash($source['rkm_vehicle_model'])) : '',
            'part_category' => isset($source['rkm_part_category']) ? sanitize_text_field(wp_unslash($source['rkm_part_category'])) : '',
            'year_from' => self::sanitize_year($source['rkm_year_from'] ?? ''),
            'year_to' => self::sanitize_year($source['rkm_year_to'] ?? ''),
            'engine_version' => isset($source['rkm_engine_version']) ? sanitize_text_field(wp_unslash($source['rkm_engine_version'])) : '',
        ];
    }

    public static function sanitize_year($value) {
        $value = is_scalar($value) ? trim((string) wp_unslash($value)) : '';

        if ($value === '') {
            return '';
        }

        $year = absint($value);

        return $year >= 1900 && $year <= 2100 ? (string) $year : '';
    }

    public static function validate_data($data) {
        $year_from = isset($data['year_from']) && $data['year_from'] !== '' ? (int) $data['year_from'] : 0;
        $year_to = isset($data['year_to']) && $data['year_to'] !== '' ? (int) $data['year_to'] : 0;

        if ($year_from > 0 && $year_to > 0 && $year_from > $year_to) {
            return new WP_Error('rkm_vehicle_year_range_invalid', 'El ano desde no puede ser mayor que el ano hasta.');
        }

        return true;
    }

    public static function save_product_data($product_id, $data) {
        $product_id = absint($product_id);

        if ($product_id <= 0) {
            return;
        }

        self::set_single_term($product_id, self::TAX_BRAND, $data['vehicle_brand'] ?? '');
        self::set_single_term($product_id, self::TAX_MODEL, $data['vehicle_model'] ?? '');
        self::set_single_term($product_id, self::TAX_PART_CATEGORY, $data['part_category'] ?? '');
        self::update_meta($product_id, self::META_YEAR_FROM, $data['year_from'] ?? '');
        self::update_meta($product_id, self::META_YEAR_TO, $data['year_to'] ?? '');
        self::update_meta($product_id, self::META_ENGINE_VERSION, $data['engine_version'] ?? '');
    }

    private static function set_single_term($product_id, $taxonomy, $term_name) {
        if (!taxonomy_exists($taxonomy)) {
            return;
        }

        $term_name = trim((string) $term_name);

        if ($term_name === '') {
            wp_set_object_terms($product_id, [], $taxonomy, false);
            return;
        }

        wp_set_object_terms($product_id, $term_name, $taxonomy, false);
    }

    private static function update_meta($product_id, $meta_key, $value) {
        $value = trim((string) $value);

        if ($value === '') {
            delete_post_meta($product_id, $meta_key);
            return;
        }

        update_post_meta($product_id, $meta_key, $value);
    }

    public static function get_catalog_filters() {
        return [
            'brand' => isset($_GET['rkm_vehicle_brand']) ? absint($_GET['rkm_vehicle_brand']) : 0,
            'model' => isset($_GET['rkm_vehicle_model']) ? absint($_GET['rkm_vehicle_model']) : 0,
            'part_category' => isset($_GET['rkm_part_category']) ? absint($_GET['rkm_part_category']) : 0,
            'year' => self::sanitize_year($_GET['rkm_year'] ?? ''),
        ];
    }

    public static function apply_catalog_query_filters($args, $filters) {
        $tax_query = [];

        if (!empty($filters['brand'])) {
            $tax_query[] = self::get_term_query(self::TAX_BRAND, (int) $filters['brand']);
        }

        if (!empty($filters['model'])) {
            $tax_query[] = self::get_term_query(self::TAX_MODEL, (int) $filters['model']);
        }

        if (!empty($filters['part_category'])) {
            $tax_query[] = self::get_term_query(self::TAX_PART_CATEGORY, (int) $filters['part_category']);
        }

        $tax_query = array_values(array_filter($tax_query));

        if (!empty($tax_query)) {
            $args['tax_query'] = array_merge(['relation' => 'AND'], $tax_query);
        }

        if (!empty($filters['year'])) {
            $year = (int) $filters['year'];
            $args['meta_query'] = [
                'relation' => 'OR',
                [
                    'relation' => 'AND',
                    [
                        'key' => self::META_YEAR_FROM,
                        'value' => $year,
                        'compare' => '<=',
                        'type' => 'NUMERIC',
                    ],
                    [
                        'key' => self::META_YEAR_TO,
                        'value' => $year,
                        'compare' => '>=',
                        'type' => 'NUMERIC',
                    ],
                ],
                [
                    'relation' => 'AND',
                    [
                        'relation' => 'OR',
                        [
                            'key' => self::META_YEAR_FROM,
                            'compare' => 'NOT EXISTS',
                        ],
                        [
                            'key' => self::META_YEAR_FROM,
                            'value' => '',
                            'compare' => '=',
                        ],
                    ],
                    [
                        'relation' => 'OR',
                        [
                            'key' => self::META_YEAR_TO,
                            'compare' => 'NOT EXISTS',
                        ],
                        [
                            'key' => self::META_YEAR_TO,
                            'value' => '',
                            'compare' => '=',
                        ],
                    ],
                ],
                [
                    'relation' => 'AND',
                    [
                        'key' => self::META_YEAR_FROM,
                        'value' => $year,
                        'compare' => '<=',
                        'type' => 'NUMERIC',
                    ],
                    [
                        'relation' => 'OR',
                        [
                            'key' => self::META_YEAR_TO,
                            'compare' => 'NOT EXISTS',
                        ],
                        [
                            'key' => self::META_YEAR_TO,
                            'value' => '',
                            'compare' => '=',
                        ],
                    ],
                ],
                [
                    'relation' => 'AND',
                    [
                        'relation' => 'OR',
                        [
                            'key' => self::META_YEAR_FROM,
                            'compare' => 'NOT EXISTS',
                        ],
                        [
                            'key' => self::META_YEAR_FROM,
                            'value' => '',
                            'compare' => '=',
                        ],
                    ],
                    [
                        'key' => self::META_YEAR_TO,
                        'value' => $year,
                        'compare' => '>=',
                        'type' => 'NUMERIC',
                    ],
                ],
            ];
        }

        return $args;
    }

    private static function get_term_query($taxonomy, $term_id) {
        if ($term_id <= 0 || !taxonomy_exists($taxonomy)) {
            return null;
        }

        return [
            'taxonomy' => $taxonomy,
            'field' => 'term_id',
            'terms' => [$term_id],
        ];
    }
}
