<?php
if (!defined('ABSPATH')) {
    exit;
}

$categories = isset($data['categories']) && is_array($data['categories']) ? $data['categories'] : [];
$status_options = isset($data['status_options']) && is_array($data['status_options']) ? $data['status_options'] : [];
$panel_base_url = function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('panel') : home_url('/mi-cuenta/panel/');
$section_url = $data['section_url'] ?? (function_exists('rkm_get_panel_url') ? rkm_get_panel_url(['section' => 'productos']) : add_query_arg('section', 'productos', $panel_base_url));
$list_url = $data['list_url'] ?? $section_url;
$form_url = class_exists('RKM_Products') ? RKM_Products::get_section_url(['view' => 'create']) : $section_url;
$form_action = 'create_product';
$is_edit = false;
$product_form_data = [];
?>

<section class="rkm-card rkm-admin-products-form-card rkm-admin-products-editor">
    <div class="rkm-admin-products-panel__header">
        <span class="rkm-admin-products-panel__eyebrow">Nueva publicacion</span>
        <h3>Crear producto</h3>
        <p>Completa los datos comerciales y logisticos del producto simple de WooCommerce.</p>
    </div>

    <?php include RKM_CORE_PATH . 'templates/admin/products/form-fields.php'; ?>
</section>
