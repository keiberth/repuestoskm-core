<?php
if (!defined('ABSPATH')) {
    exit;
}

$is_edit = ($data['view'] ?? '') === 'edit';
$form_action = $data['form_action'] ?? 'create_product';
$product_form_data = $is_edit && isset($data['product_form_data']) && is_array($data['product_form_data'])
    ? $data['product_form_data']
    : [
        'id' => 0,
        'name' => '',
        'sku' => '',
        'category_ids' => [],
        'short_description' => '',
        'description' => '',
        'regular_price' => '',
        'cost_price' => '',
        'stock' => 0,
        'status' => 'publish',
        'image_id' => 0,
    ];
$image_url = !empty($product_form_data['image_id']) ? wp_get_attachment_image_url((int) $product_form_data['image_id'], 'medium') : '';
?>

<form method="post" action="<?php echo esc_url($section_url); ?>" enctype="multipart/form-data" class="rkm-admin-products-form rkm-admin-products-form--wide" data-rkm-products-form>
    <input type="hidden" name="rkm_products_action" value="<?php echo esc_attr($form_action); ?>">
    <?php if ($is_edit) : ?>
        <input type="hidden" name="product_id" value="<?php echo esc_attr((string) $product_form_data['id']); ?>">
    <?php endif; ?>
    <?php wp_nonce_field('rkm_products_update', 'rkm_products_nonce'); ?>

    <div class="rkm-admin-products-editor-grid">
        <section class="rkm-admin-products-editor-section">
            <h4>Datos principales</h4>

            <label class="rkm-admin-products-field">
                <span>Nombre</span>
                <input type="text" name="name" required value="<?php echo esc_attr($product_form_data['name']); ?>" placeholder="Ej: Filtro de aceite">
            </label>

            <div class="rkm-admin-products-form__row">
                <label class="rkm-admin-products-field">
                    <span>SKU</span>
                    <input type="text" name="sku" required value="<?php echo esc_attr($product_form_data['sku']); ?>" placeholder="SKU interno">
                </label>

                <label class="rkm-admin-products-field">
                    <span>Estado</span>
                    <select name="status">
                        <?php foreach ($status_options as $status_key => $status_label) : ?>
                            <option value="<?php echo esc_attr($status_key); ?>" <?php selected($product_form_data['status'], $status_key); ?>>
                                <?php echo esc_html($status_label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>

            <label class="rkm-admin-products-field">
                <span>Categoria</span>
                <select name="category_ids[]" <?php echo empty($categories) ? 'disabled' : ''; ?>>
                    <option value="">Sin categoria</option>
                    <?php foreach ($categories as $category) : ?>
                        <option value="<?php echo esc_attr((string) $category->term_id); ?>" <?php echo in_array((int) $category->term_id, array_map('intval', $product_form_data['category_ids']), true) ? 'selected' : ''; ?>>
                            <?php echo esc_html($category->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($categories)) : ?>
                    <small>No hay categorias de producto creadas.</small>
                <?php endif; ?>
            </label>
        </section>

        <section class="rkm-admin-products-editor-section">
            <h4>Precio y stock</h4>

            <div class="rkm-admin-products-form__row">
                <label class="rkm-admin-products-field">
                    <span>Precio de venta</span>
                    <input type="number" name="regular_price" min="0" step="0.01" required value="<?php echo esc_attr((string) $product_form_data['regular_price']); ?>">
                </label>

                <label class="rkm-admin-products-field">
                    <span>Precio de costo</span>
                    <input type="number" name="cost_price" min="0" step="0.01" value="<?php echo esc_attr((string) $product_form_data['cost_price']); ?>">
                </label>
            </div>

            <label class="rkm-admin-products-field">
                <span>Stock</span>
                <input type="number" name="stock" min="0" step="1" required value="<?php echo esc_attr((string) $product_form_data['stock']); ?>">
            </label>
        </section>

        <section class="rkm-admin-products-editor-section rkm-admin-products-editor-section--full">
            <h4>Descripcion</h4>

            <label class="rkm-admin-products-field">
                <span>Descripcion corta</span>
                <textarea name="short_description" rows="4" placeholder="Resumen visible en listados"><?php echo esc_textarea($product_form_data['short_description']); ?></textarea>
            </label>

            <label class="rkm-admin-products-field">
                <span>Descripcion completa</span>
                <textarea name="description" rows="7" placeholder="Detalle tecnico y comercial"><?php echo esc_textarea($product_form_data['description']); ?></textarea>
            </label>
        </section>

        <section class="rkm-admin-products-editor-section">
            <h4>Imagen principal</h4>

            <?php if ($image_url !== '') : ?>
                <div class="rkm-admin-products-current-image">
                    <img src="<?php echo esc_url($image_url); ?>" alt="">
                    <span>Imagen actual</span>
                </div>
            <?php endif; ?>

            <label class="rkm-admin-products-field rkm-admin-products-file">
                <span><?php echo $is_edit ? 'Reemplazar imagen' : 'Imagen'; ?></span>
                <input type="file" name="product_image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" data-rkm-product-image>
                <small data-rkm-product-image-label>JPG, PNG o WEBP. Maximo 5 MB.</small>
            </label>
        </section>
    </div>

    <div class="rkm-admin-products-form__feedback" data-rkm-products-feedback></div>

    <div class="rkm-admin-products-editor-actions">
        <button type="submit" class="rkm-btn rkm-btn--primary">
            <?php echo $is_edit ? 'Guardar cambios' : 'Crear publicacion'; ?>
        </button>
        <a class="rkm-admin-products-link" href="<?php echo esc_url($data['list_url'] ?? $section_url); ?>">Cancelar</a>
    </div>
</form>
