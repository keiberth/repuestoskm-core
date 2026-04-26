<?php
if (!defined('ABSPATH')) {
    exit;
}

$product = $data['product'] ?? null;
$product_form_data = isset($data['product_form_data']) && is_array($data['product_form_data']) ? $data['product_form_data'] : [];
$categories = isset($data['categories']) && is_array($data['categories']) ? $data['categories'] : [];
$status_options = isset($data['status_options']) && is_array($data['status_options']) ? $data['status_options'] : [];
$section_url = $data['section_url'] ?? home_url('/mi-cuenta/panel/?section=productos');
$list_url = $data['list_url'] ?? $section_url;
$form_url = class_exists('RKM_Products') && $product
    ? RKM_Products::get_section_url(['view' => 'edit', 'product_id' => (int) $product->get_id()])
    : $section_url;
$form_action = 'update_product';
$is_edit = true;
?>

<section class="rkm-card rkm-admin-products-form-card rkm-admin-products-editor">
    <div class="rkm-admin-products-panel__header">
        <span class="rkm-admin-products-panel__eyebrow">Editar publicacion</span>
        <h3><?php echo esc_html($product ? $product->get_name() : 'Producto'); ?></h3>
        <p>Actualiza datos comerciales, categoria, stock, imagen y estado de la publicacion.</p>
    </div>

    <?php include RKM_CORE_PATH . 'templates/admin/products/form-fields.php'; ?>
</section>
