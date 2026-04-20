<?php
if (!defined('ABSPATH')) {
    exit;
}

$current = 'nueva-orden';

$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

$args = [
    'status' => 'publish',
    'limit'  => 100,
];

if (!empty($search)) {
    $args['s'] = $search;
}

$products = wc_get_products($args);


$repeat_items = [];
$user_id = get_current_user_id();
$shipping_address = [
    'name'     => trim(get_user_meta($user_id, 'shipping_first_name', true) . ' ' . get_user_meta($user_id, 'shipping_last_name', true)),
    'address'  => get_user_meta($user_id, 'shipping_address_1', true),
    'city'     => get_user_meta($user_id, 'shipping_city', true),
];

if (isset($_GET['repeat_order']) && function_exists('wc_get_order')) {
    $repeat_order_id = absint($_GET['repeat_order']);
    $repeat_order = wc_get_order($repeat_order_id);

    if ($repeat_order && (int) $repeat_order->get_customer_id() === get_current_user_id()) {
        foreach ($repeat_order->get_items() as $item) {
            $product = $item->get_product();

            if (!$product) {
                continue;
            }

            $repeat_items[] = [
                'id'       => (string) $product->get_id(),
                'name'     => $product->get_name(),
                'price'    => (float) $product->get_price(),
                'sku'      => $product->get_sku(),
                'quantity' => (int) $item->get_quantity(),
            ];
        }
    }
}
?>

<div class="rkm-app">
    <?php include plugin_dir_path(__FILE__) . 'partials/private-header.php'; ?>

    <div class="rkm-container">

        <div class="rkm-page-header">
            <h1>Nueva orden</h1>
            <p>Selecciona productos y arma tu pedido</p>
        </div>

        <?php include plugin_dir_path(__FILE__) . 'partials/subnav.php'; ?>

        <div id="rkm-order-feedback" class="rkm-order-feedback" style="display:none;"></div>

        <div class="rkm-toolbar">
            <form method="get" class="rkm-search-form">
                <input type="hidden" name="section" value="nueva-orden">

                <input
                    type="text"
                    name="s"
                    value="<?php echo esc_attr($search); ?>"
                    placeholder="Buscar producto..."
                    class="rkm-input"
                >

                <button type="submit" class="rkm-btn rkm-btn--secondary">Buscar</button>
            </form>
        </div>

        <div class="rkm-order-layout">

            <section class="rkm-order-products">
                <div class="rkm-catalog-grid">
                    <?php foreach ($products as $product) : ?>
                        <?php
                        $id          = $product->get_id();
                        $name        = $product->get_name();
                        $price       = (float) $product->get_price();
                        $price_html  = $product->get_price_html();
                        $sku         = $product->get_sku();
                        $stock_quantity = $product->get_stock_quantity();
                        $in_stock    = $product->is_in_stock();
                        $stock_label = $in_stock
                            ? (is_null($stock_quantity) ? 'Disponible' : $stock_quantity . ' unidades')
                            : 'Sin stock';
                        $stock       = $stock_quantity;
                        $image       = get_the_post_thumbnail_url($id, 'medium');
                        $description = trim(wp_strip_all_tags($product->get_short_description()));
                        $product_url = home_url('/mi-cuenta/panel') . '?section=nueva-orden&add_product=' . $id;
                        $gallery_items = [];
                        $image_ids = array_filter(array_unique(array_merge(
                            $product->get_image_id() ? [$product->get_image_id()] : [],
                            $product->get_gallery_image_ids()
                        )));

                        if (!$image) {
                            $image = wc_placeholder_img_src();
                        }

                        if (!empty($image_ids)) {
                            foreach ($image_ids as $image_id) {
                                $full_image = wp_get_attachment_image_url($image_id, 'large');
                                $thumb_image = wp_get_attachment_image_url($image_id, 'thumbnail');
                                $image_alt = get_post_meta($image_id, '_wp_attachment_image_alt', true);

                                if (!$full_image) {
                                    continue;
                                }

                                $gallery_items[] = [
                                    'full'  => $full_image,
                                    'thumb' => $thumb_image ?: $full_image,
                                    'alt'   => $image_alt ?: $name,
                                ];
                            }
                        }

                        if (empty($gallery_items)) {
                            $gallery_items[] = [
                                'full'  => $image,
                                'thumb' => $image,
                                'alt'   => $name,
                            ];
                        }

                        if (is_null($stock)) {
                            $stock = 9999;
                        }
                        ?>

                        <article
                            class="rkm-product-card rkm-product-card--interactive"
                            data-id="<?php echo esc_attr($id); ?>"
                            data-name="<?php echo esc_attr($name); ?>"
                            data-price="<?php echo esc_attr($price); ?>"
                            data-sku="<?php echo esc_attr($sku); ?>"
                            data-stock="<?php echo esc_attr($stock); ?>"
                            data-image="<?php echo esc_url($image); ?>"
                            data-description="<?php echo esc_attr($description); ?>"
                            data-product-name="<?php echo esc_attr($name); ?>"
                            data-product-sku="<?php echo esc_attr($sku ?: 'Sin SKU'); ?>"
                            data-product-price="<?php echo esc_attr(wp_strip_all_tags($price_html ?: 'Sin precio')); ?>"
                            data-product-stock="<?php echo esc_attr($stock_label); ?>"
                            data-product-description="<?php echo esc_attr($description ?: 'Este producto no tiene descripcion corta.'); ?>"
                            data-product-url="<?php echo esc_url($product_url); ?>"
                            data-product-gallery="<?php echo esc_attr(wp_json_encode($gallery_items)); ?>"
                            tabindex="0"
                            aria-haspopup="dialog"
                            aria-controls="rkmProductQuickView"
                        >
                            <div class="rkm-product-card__image">
                                <img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr($name); ?>">
                            </div>

                            <div class="rkm-product-card__body">
                                <h3 class="rkm-product-card__title"><?php echo esc_html($name); ?></h3>

                                <?php if ($sku) : ?>
                                    <p class="rkm-product-card__meta">
                                        <strong>SKU:</strong> <?php echo esc_html($sku); ?>
                                    </p>
                                <?php endif; ?>

                                <p class="rkm-product-card__price">
                                    <?php echo wp_kses_post($price_html ?: 'Sin precio'); ?>
                                </p>

                                <p class="rkm-product-card__stock">
                                    Stock: <span class="rkm-stock-value"><?php echo esc_html($stock); ?></span>
                                </p>

                                <div class="rkm-product-card__controls">
                                    <input
                                        type="number"
                                        min="1"
                                        value="1"
                                        class="rkm-qty-input"
                                    >

                                    <button type="button" class="rkm-btn rkm-btn--primary rkm-add-to-summary">
                                        Agregar
                                    </button>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <aside class="rkm-order-summary">
                <div class="rkm-card">
                    <h3>Resumen del pedido</h3>
                    <p class="rkm-order-summary__empty">Todavía no agregaste productos.</p>
                    <button class="rkm-btn rkm-btn--primary rkm-btn-block" disabled>Continuar</button>
                </div>
                <div class="rkm-card rkm-order-summary__shipping">
                    <div class="rkm-order-summary__shipping-header">
                        <span class="rkm-order-summary__shipping-eyebrow">Envio</span>
                        <a href="<?php echo esc_url(home_url('/mi-cuenta/panel/?section=direcciones')); ?>" class="rkm-order-summary__shipping-edit">
                            Editar
                        </a>
                    </div>

                    <h4 class="rkm-order-summary__shipping-title">Direccion de envio</h4>

                    <?php if (!empty($shipping_address['address'])) : ?>
                        <div class="rkm-order-summary__shipping-body">
                            <?php if (!empty($shipping_address['name'])) : ?>
                                <strong><?php echo esc_html($shipping_address['name']); ?></strong>
                            <?php endif; ?>
                            <p><?php echo esc_html($shipping_address['address']); ?></p>
                            <?php if (!empty($shipping_address['city'])) : ?>
                                <p><?php echo esc_html($shipping_address['city']); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php else : ?>
                        <div class="rkm-order-summary__shipping-empty">
                            <p>No tenes una direccion configurada.</p>
                            <a href="<?php echo esc_url(home_url('/mi-cuenta/panel/?section=direcciones')); ?>" class="rkm-order-summary__shipping-edit">
                                Cargar direccion
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </aside>

        </div>
        <div class="rkm-product-quick-view" id="rkmProductQuickView" aria-hidden="true">
            <div class="rkm-product-quick-view__overlay" data-rkm-product-quick-view-close></div>

            <div
                class="rkm-product-quick-view__dialog"
                role="dialog"
                aria-modal="true"
                aria-labelledby="rkmProductQuickViewTitle"
            >
                <button
                    type="button"
                    class="rkm-product-quick-view__close"
                    data-rkm-product-quick-view-close
                    aria-label="Cerrar vista rapida"
                >
                    &times;
                </button>

                <div class="rkm-product-quick-view__media">
                    <div class="rkm-product-quick-view__main-image-wrap">
                        <img
                            id="rkmProductQuickViewMainImage"
                            class="rkm-product-quick-view__main-image"
                            src=""
                            alt=""
                        >
                    </div>

                    <div
                        id="rkmProductQuickViewThumbs"
                        class="rkm-product-quick-view__thumbs"
                        aria-label="Galeria de imagenes del producto"
                    ></div>
                </div>

                <div class="rkm-product-quick-view__content">
                    <div class="rkm-product-quick-view__eyebrow">Vista rapida</div>
                    <h2 id="rkmProductQuickViewTitle" class="rkm-product-quick-view__title">Producto</h2>

                    <div class="rkm-product-quick-view__meta">
                        <div class="rkm-product-quick-view__meta-item">
                            <span class="rkm-product-quick-view__meta-label">SKU</span>
                            <strong id="rkmProductQuickViewSku"></strong>
                        </div>

                        <div class="rkm-product-quick-view__meta-item">
                            <span class="rkm-product-quick-view__meta-label">Precio</span>
                            <strong id="rkmProductQuickViewPrice"></strong>
                        </div>

                        <div class="rkm-product-quick-view__meta-item">
                            <span class="rkm-product-quick-view__meta-label">Stock</span>
                            <strong id="rkmProductQuickViewStock"></strong>
                        </div>
                    </div>

                    <div class="rkm-product-quick-view__description">
                        <p id="rkmProductQuickViewDescription"></p>
                    </div>

                    <div class="rkm-product-quick-view__actions">
                        <a
                            id="rkmProductQuickViewPrimaryAction"
                            href="<?php echo esc_url(home_url('/mi-cuenta/panel/?section=nueva-orden')); ?>"
                            class="rkm-btn rkm-btn--primary"
                        >
                            Ir a nueva orden
                        </a>
                    </div>
                </div>
            </div>
        </div>

    </div>
    <script>
        window.rkmRepeatOrderItems = <?php echo wp_json_encode($repeat_items); ?>;
    </script>
</div>
