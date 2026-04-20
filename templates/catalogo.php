<?php
if (!defined('ABSPATH')) {
    exit;
}

$current = 'catalogo';

$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

$args = [
    'status' => 'publish',
    'limit'  => 20,
    'orderby'=> 'date',
    'order'  => 'DESC',
];

if (!empty($search)) {
    $args['s'] = $search;
}

$products = wc_get_products($args);
?>

<div class="rkm-app">

    <?php include RKM_CORE_PATH . 'templates/partials/private-header.php'; ?>

    <div class="rkm-container rkm-dashboard-wrapper">

        <div class="rkm-dashboard-header">
            <div>
                <h1 class="rkm-title">Catálogo</h1>
                <p class="rkm-subtitle">Explora productos y agrégalos a tu pedido</p>
            </div>
        </div>

        <div class="rkm-sidebar-layout rkm-dashboard-grid">

            <aside class="rkm-sidebar-card">
                <?php include RKM_CORE_PATH . 'templates/partials/subnav.php'; ?>
            </aside>

            <main class="rkm-main-card">

                <div class="rkm-toolbar">
                    <form method="get" class="rkm-search-form">
                        <input type="hidden" name="section" value="catalogo">

                        <input
                            type="text"
                            name="s"
                            value="<?php echo esc_attr($search); ?>"
                            placeholder="Buscar por nombre o palabra clave"
                            class="rkm-input"
                        >

                        <button type="submit" class="rkm-btn rkm-btn--secondary">Buscar</button>
                    </form>
                </div>

                <?php if (!empty($products)) : ?>
                    <div class="rkm-catalog-grid">
                        <?php foreach ($products as $product) : ?>
                            <?php
                            $product_id = $product->get_id();
                            $name       = $product->get_name();
                            $price_html = $product->get_price_html();
                            $sku        = $product->get_sku();
                            $stock_qty  = $product->get_stock_quantity();
                            $in_stock   = $product->is_in_stock();
                            $image      = get_the_post_thumbnail_url($product_id, 'medium');
                            $product_url = home_url('/mi-cuenta/panel') . '?section=nueva-orden&add_product=' . $product_id;
                            $stock_label = $in_stock
                                ? (is_null($stock_qty) ? 'Disponible' : $stock_qty . ' unidades')
                                : 'Sin stock';

                            if (!$image) {
                                $image = wc_placeholder_img_src();
                            }
                            ?>
                            <article class="rkm-product-card">
                                <div class="rkm-product-card__image">
                                    <img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr($name); ?>">
                                </div>

                                <div class="rkm-product-card__body">
                                    <h3 class="rkm-product-card__title">
                                        <?php echo esc_html($name); ?>
                                    </h3>

                                    <?php if (!empty($sku)) : ?>
                                        <p class="rkm-product-card__meta">
                                            <strong>SKU:</strong> <?php echo esc_html($sku); ?>
                                        </p>
                                    <?php endif; ?>

                                    <p class="rkm-product-card__price">
                                        <?php echo wp_kses_post($price_html ?: 'Sin precio'); ?>
                                    </p>

                                    <p class="rkm-product-card__stock">
                                        <strong>Stock:</strong>
                                        <?php echo esc_html($stock_label); ?>
                                    </p>
                                </div>

                                <div class="rkm-product-card__actions">
                                    <a href="<?php echo esc_url($product_url); ?>"
                                       class="rkm-btn rkm-btn--primary">
                                        Agregar
                                    </a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <div class="rkm-empty-state">
                        No se encontraron productos.
                    </div>
                <?php endif; ?>

            </main>
        </div>
    </div>
</div>
