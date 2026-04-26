<?php
if (!defined('ABSPATH')) {
    exit;
}

$categories = isset($data['categories']) && is_array($data['categories']) ? $data['categories'] : [];
$status_options = isset($data['status_options']) && is_array($data['status_options']) ? $data['status_options'] : [];
$section_url = $data['section_url'] ?? home_url('/mi-cuenta/panel/?section=productos');
?>

<section class="rkm-card rkm-admin-products-form-card rkm-admin-products-editor">
    <div class="rkm-admin-products-panel__header">
        <span class="rkm-admin-products-panel__eyebrow">Nueva publicacion</span>
        <h3>Crear producto</h3>
        <p>Completa los datos comerciales y logisticos del producto simple de WooCommerce.</p>
    </div>

    <?php include RKM_CORE_PATH . 'templates/admin/products/form-fields.php'; ?>
</section>
