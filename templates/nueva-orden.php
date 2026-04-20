<?php
if (!defined('ABSPATH')) {
    exit;
}

$current = 'nueva-orden';

$search = isset($_GET['rkm_search']) ? sanitize_text_field(wp_unslash($_GET['rkm_search'])) : '';
$stock_filter = isset($_GET['rkm_stock']) ? sanitize_key(wp_unslash($_GET['rkm_stock'])) : 'all';
$current_page = isset($_GET['rkm_page']) ? max(1, absint($_GET['rkm_page'])) : 1;
$products_per_page = 12;
$allowed_stock_filters = ['all', 'in', 'out'];

if (!in_array($stock_filter, $allowed_stock_filters, true)) {
    $stock_filter = 'all';
}

$args = [
    'status'   => 'publish',
    'limit'    => $products_per_page,
    'paginate' => true,
    'page'     => $current_page,
];

if ($stock_filter === 'in') {
    $args['stock_status'] = 'instock';
} elseif ($stock_filter === 'out') {
    $args['stock_status'] = 'outofstock';
}

if ($search !== '') {
    global $wpdb;

    $search_like = '%' . $wpdb->esc_like($search) . '%';
    $lookup_join = '';
    $stock_where = '';

    if ($stock_filter !== 'all') {
        $lookup_table = $wpdb->prefix . 'wc_product_meta_lookup';
        $lookup_join = " INNER JOIN {$lookup_table} AS lookup ON posts.ID = lookup.product_id ";
        $stock_status = $stock_filter === 'in' ? 'instock' : 'outofstock';
        $stock_where = $wpdb->prepare(' AND lookup.stock_status = %s ', $stock_status);
    }

    $text_matches = $wpdb->get_col($wpdb->prepare(
        "
        SELECT posts.ID
        FROM {$wpdb->posts} AS posts
        {$lookup_join}
        WHERE posts.post_type = 'product'
          AND posts.post_status = 'publish'
          {$stock_where}
          AND (
              post_title LIKE %s
              OR post_excerpt LIKE %s
              OR post_content LIKE %s
          )
        ORDER BY posts.post_date DESC
        ",
        $search_like,
        $search_like,
        $search_like
    ));

    $sku_matches = $wpdb->get_col($wpdb->prepare(
        "
        SELECT posts.ID
        FROM {$wpdb->posts} AS posts
        INNER JOIN {$wpdb->postmeta} AS postmeta
            ON posts.ID = postmeta.post_id
        {$lookup_join}
        WHERE posts.post_type = 'product'
          AND posts.post_status = 'publish'
          AND postmeta.meta_key = '_sku'
          AND postmeta.meta_value LIKE %s
          {$stock_where}
        ORDER BY posts.post_date DESC
        ",
        $search_like
    ));

    $matched_product_ids = array_values(array_unique(array_merge(
        $text_matches,
        $sku_matches
    )));

    $total_products = count($matched_product_ids);
    $total_pages = max(1, (int) ceil($total_products / $products_per_page));
    $current_page = min($current_page, $total_pages);
    $products_start = $total_products > 0 ? (($current_page - 1) * $products_per_page) + 1 : 0;
    $products_end = $total_products > 0 ? min($total_products, $current_page * $products_per_page) : 0;

    $paged_product_ids = array_slice(
        $matched_product_ids,
        ($current_page - 1) * $products_per_page,
        $products_per_page
    );

    if (!empty($paged_product_ids)) {
        $fetched_products = wc_get_products([
            'status'  => 'publish',
            'include' => $paged_product_ids,
            'limit'   => count($paged_product_ids),
        ]);

        $products_map = [];

        foreach ($fetched_products as $product) {
            $products_map[$product->get_id()] = $product;
        }

        $products = array_values(array_filter(array_map(function ($product_id) use ($products_map) {
            return $products_map[$product_id] ?? null;
        }, $paged_product_ids)));
    } else {
        $products = [];
    }
} else {
    $products_query = wc_get_products($args);
    $products = !empty($products_query->products) ? $products_query->products : [];
    $total_products = !empty($products_query->total) ? (int) $products_query->total : count($products);
    $total_pages = !empty($products_query->max_num_pages) ? (int) $products_query->max_num_pages : 1;
    $products_start = $total_products > 0 ? (($current_page - 1) * $products_per_page) + 1 : 0;
    $products_end = $total_products > 0 ? min($total_products, $current_page * $products_per_page) : 0;
}

$panel_base_url = home_url('/mi-cuenta/panel/');

$pagination_args = [
    'section' => 'nueva-orden',
];

if ($search !== '') {
    $pagination_args['rkm_search'] = $search;
}

if ($stock_filter !== 'all') {
    $pagination_args['rkm_stock'] = $stock_filter;
}

if (isset($_GET['repeat_order'])) {
    $pagination_args['repeat_order'] = absint($_GET['repeat_order']);
}

$pagination_base_url = $panel_base_url;


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
    <div class="rkm-container">
        <?php include plugin_dir_path(__FILE__) . 'partials/private-header.php'; ?>

        <div class="rkm-page-header">
            <h1>Nueva orden</h1>
            <p>Selecciona productos y arma tu pedido</p>
        </div>

        <?php include plugin_dir_path(__FILE__) . 'partials/subnav.php'; ?>

        <div id="rkm-order-feedback" class="rkm-order-feedback" style="display:none;"></div>

        <div class="rkm-toolbar rkm-catalog-toolbar">
            <form
                id="rkmCatalogSearchForm"
                method="get"
                action="<?php echo esc_url($panel_base_url); ?>"
                class="rkm-search-form rkm-catalog-search-form"
            >
                <input type="hidden" name="section" value="nueva-orden">
                <input type="hidden" name="rkm_page" value="1">
                <?php if (isset($_GET['repeat_order'])) : ?>
                    <input type="hidden" name="repeat_order" value="<?php echo esc_attr(absint($_GET['repeat_order'])); ?>">
                <?php endif; ?>

                <input
                    type="text"
                    name="rkm_search"
                    value="<?php echo esc_attr($search); ?>"
                    placeholder="Buscar producto..."
                    class="rkm-input"
                    data-rkm-live-search
                    autocomplete="off"
                >

                <button type="submit" class="rkm-btn rkm-btn--secondary">Buscar</button>
            </form>

            <div class="rkm-catalog-filter-group">
                <label for="rkmStockFilter" class="rkm-catalog-filter-group__label">Stock</label>
                <select
                    id="rkmStockFilter"
                    name="rkm_stock"
                    form="rkmCatalogSearchForm"
                    class="rkm-catalog-filter-group__select"
                    data-rkm-stock-filter
                >
                    <option value="all" <?php selected($stock_filter, 'all'); ?>>Todos</option>
                    <option value="in" <?php selected($stock_filter, 'in'); ?>>Con stock</option>
                    <option value="out" <?php selected($stock_filter, 'out'); ?>>Sin stock</option>
                </select>
            </div>
        </div>

        <div class="rkm-order-layout">

            <section class="rkm-order-products">
                <?php if (!empty($products)) : ?>
                    <div class="rkm-catalog-meta">
                        <p class="rkm-catalog-meta__results">
                            Mostrando <?php echo esc_html($products_start); ?>-<?php echo esc_html($products_end); ?>
                            de <?php echo esc_html($total_products); ?> productos
                        </p>

                        <p class="rkm-catalog-meta__visible" data-rkm-visible-count>
                            <?php echo esc_html(count($products)); ?> productos visibles en esta página
                        </p>
                    </div>

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

                            $card_description = $description ?: 'Producto disponible para agregar a tu pedido.';
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
                                    <div class="rkm-product-card__meta-row">
                                        <?php if ($sku) : ?>
                                            <p class="rkm-product-card__meta">
                                                <span class="rkm-product-card__meta-label">SKU</span>
                                                <span class="rkm-product-card__meta-value"><?php echo esc_html($sku); ?></span>
                                            </p>
                                        <?php endif; ?>

                                        <p class="rkm-product-card__stock <?php echo $in_stock ? 'is-in-stock' : 'is-out-of-stock'; ?>">
                                            <span class="rkm-product-card__stock-label">Stock</span>
                                            <span class="rkm-stock-value"><?php echo esc_html($stock); ?></span>
                                        </p>
                                    </div>

                                    <h3 class="rkm-product-card__title"><?php echo esc_html($name); ?></h3>

                                    <p class="rkm-product-card__excerpt">
                                        <?php echo esc_html($card_description); ?>
                                    </p>

                                    <p class="rkm-product-card__price">
                                        <?php echo wp_kses_post($price_html ?: 'Sin precio'); ?>
                                    </p>

                                    <div class="rkm-product-card__controls">
                                        <label class="rkm-product-card__qty" aria-label="Cantidad a agregar">
                                            <span class="rkm-product-card__qty-label">Cantidad</span>
                                            <input
                                                type="number"
                                                min="1"
                                                value="1"
                                                class="rkm-qty-input"
                                            >
                                        </label>

                                        <button type="button" class="rkm-btn rkm-btn--primary rkm-add-to-summary">
                                            Agregar
                                        </button>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <div class="rkm-catalog-empty" data-rkm-empty-state hidden>
                        <h3 class="rkm-catalog-empty__title">No se encontraron productos con ese criterio</h3>
                        <p class="rkm-catalog-empty__text">Probá con otro nombre, SKU o ajustá el filtro de stock.</p>
                    </div>

                    <?php if ($total_pages > 1) : ?>
                        <nav class="rkm-pagination" aria-label="Paginacion de productos">
                            <?php if ($current_page > 1) : ?>
                                <a
                                    class="rkm-pagination__link rkm-pagination__link--nav"
                                    href="<?php echo esc_url(add_query_arg(array_merge($pagination_args, ['rkm_page' => $current_page - 1]), $pagination_base_url)); ?>"
                                >
                                    Anterior
                                </a>
                            <?php endif; ?>

                            <div class="rkm-pagination__pages">
                                <?php foreach (range(1, $total_pages) as $page_number) : ?>
                                    <a
                                        class="rkm-pagination__link <?php echo $page_number === $current_page ? 'is-current' : ''; ?>"
                                        href="<?php echo esc_url(add_query_arg(array_merge($pagination_args, ['rkm_page' => $page_number]), $pagination_base_url)); ?>"
                                        <?php echo $page_number === $current_page ? 'aria-current="page"' : ''; ?>
                                    >
                                        <?php echo esc_html($page_number); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>

                            <?php if ($current_page < $total_pages) : ?>
                                <a
                                    class="rkm-pagination__link rkm-pagination__link--nav"
                                    href="<?php echo esc_url(add_query_arg(array_merge($pagination_args, ['rkm_page' => $current_page + 1]), $pagination_base_url)); ?>"
                                >
                                    Siguiente
                                </a>
                            <?php endif; ?>
                        </nav>
                    <?php endif; ?>
                <?php else : ?>
                    <div class="rkm-empty-state">
                        No se encontraron productos para esta búsqueda.
                    </div>
                <?php endif; ?>
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
