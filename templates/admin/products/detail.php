<?php
if (!defined('ABSPATH')) {
    exit;
}

$product = $data['product'] ?? null;
$product_form_data = isset($data['product_form_data']) && is_array($data['product_form_data']) ? $data['product_form_data'] : [];
$status_options = isset($data['status_options']) && is_array($data['status_options']) ? $data['status_options'] : [];
$list_url = $data['list_url'] ?? home_url('/mi-cuenta/panel/?section=productos&view=list');

if (!$product instanceof WC_Product) {
    ?>
    <section class="rkm-card rkm-admin-products-detail">
        <div class="rkm-admin-products-empty">
            <strong>Producto no disponible</strong>
            <p>No se pudo cargar la publicacion solicitada.</p>
        </div>
    </section>
    <?php
    return;
}

$image_id = (int) ($product_form_data['image_id'] ?? 0);
$image_url = $image_id > 0 ? wp_get_attachment_image_url($image_id, 'large') : '';
$gallery_image_ids = array_values(array_filter(array_map('intval', (array) ($product_form_data['gallery_image_ids'] ?? []))));
$gallery_images = [];
$category_names = [];

foreach ($gallery_image_ids as $gallery_image_id) {
    $gallery_image_url = wp_get_attachment_image_url($gallery_image_id, 'medium');

    if ($gallery_image_url) {
        $gallery_images[] = $gallery_image_url;
    }
}

foreach ((array) ($product_form_data['category_ids'] ?? []) as $category_id) {
    $term = get_term((int) $category_id, 'product_cat');

    if ($term && !is_wp_error($term)) {
        $category_names[] = $term->name;
    }
}

$category_label = !empty($category_names) ? implode(', ', $category_names) : 'Sin categoria';
$status = (string) ($product_form_data['status'] ?? $product->get_status());
$status_label = $status_options[$status] ?? ucfirst($status);
$regular_price = number_format((float) ($product_form_data['regular_price'] ?? 0), 2, ',', '.');
$cost_price = number_format((float) ($product_form_data['cost_price'] ?? 0), 2, ',', '.');
$short_description = wp_strip_all_tags((string) ($product_form_data['short_description'] ?? ''));
$description = (string) ($product_form_data['description'] ?? '');
?>

<section class="rkm-card rkm-admin-products-detail">
    <div class="rkm-admin-products-detail__header">
        <div>
            <span class="rkm-admin-products-panel__eyebrow">Detalle de publicacion</span>
            <h3><?php echo esc_html($product->get_name()); ?></h3>
            <p><?php echo esc_html($category_label); ?> &middot; SKU <?php echo esc_html($product->get_sku() !== '' ? $product->get_sku() : 'sin SKU'); ?></p>
        </div>

        <div class="rkm-admin-products-detail__actions">
            <a class="rkm-admin-products-link" href="<?php echo esc_url($list_url); ?>">Volver</a>
            <a class="rkm-admin-products-page__primary" href="<?php echo esc_url(RKM_Products::get_section_url(['view' => 'edit', 'product_id' => $product->get_id()])); ?>">Editar</a>
        </div>
    </div>

    <div class="rkm-admin-products-detail__grid">
        <div class="rkm-admin-products-detail__media">
            <?php if ($image_url !== '') : ?>
                <img class="rkm-admin-products-detail__main-image" src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($product->get_name()); ?>">
            <?php else : ?>
                <div class="rkm-admin-products-detail__placeholder">
                    <?php echo esc_html(strtoupper(substr($product->get_name(), 0, 1))); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($gallery_images)) : ?>
                <div class="rkm-admin-products-detail__gallery">
                    <?php foreach ($gallery_images as $gallery_image_url) : ?>
                        <img src="<?php echo esc_url($gallery_image_url); ?>" alt="">
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="rkm-admin-products-detail__content">
            <div class="rkm-admin-products-detail__metrics">
                <div>
                    <span>Precio venta</span>
                    <strong>$ <?php echo esc_html($regular_price); ?></strong>
                </div>
                <div>
                    <span>Precio costo</span>
                    <strong>$ <?php echo esc_html($cost_price); ?></strong>
                </div>
                <div>
                    <span>Stock</span>
                    <strong><?php echo esc_html((string) ($product_form_data['stock'] ?? 0)); ?></strong>
                </div>
                <div>
                    <span>Estado</span>
                    <strong><?php echo esc_html($status_label); ?></strong>
                </div>
            </div>

            <div class="rkm-admin-products-detail__block">
                <h4>Descripcion corta</h4>
                <p><?php echo esc_html($short_description !== '' ? $short_description : 'Sin descripcion corta.'); ?></p>
            </div>

            <div class="rkm-admin-products-detail__block">
                <h4>Descripcion completa</h4>
                <div><?php echo $description !== '' ? wp_kses_post(wpautop($description)) : '<p>Sin descripcion completa.</p>'; ?></div>
            </div>
        </div>
    </div>
</section>
