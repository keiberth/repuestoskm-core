<?php
if (!defined('ABSPATH')) {
    exit;
}

$current = 'catalogo';

$search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
$current_page = isset($_GET['rkm_page']) ? max(1, absint($_GET['rkm_page'])) : 1;
$products_per_page = 24;
$vehicle_filters = class_exists('RKM_Vehicle_Compatibility')
    ? RKM_Vehicle_Compatibility::get_catalog_filters()
    : [
        'brand' => 0,
        'model' => 0,
        'part_category' => 0,
        'year' => '',
    ];
$rkm_vehicle_brands = class_exists('RKM_Vehicle_Compatibility') ? RKM_Vehicle_Compatibility::get_terms(RKM_Vehicle_Compatibility::TAX_BRAND) : [];
$rkm_vehicle_models = class_exists('RKM_Vehicle_Compatibility') ? RKM_Vehicle_Compatibility::get_terms(RKM_Vehicle_Compatibility::TAX_MODEL) : [];
$rkm_part_categories = class_exists('RKM_Vehicle_Compatibility') ? RKM_Vehicle_Compatibility::get_terms(RKM_Vehicle_Compatibility::TAX_PART_CATEGORY) : [];
$bcv_rate = isset($data['bcv_rate']) && is_array($data['bcv_rate']) ? $data['bcv_rate'] : null;

$args = [
    'status'   => 'publish',
    'limit'    => $products_per_page,
    'paginate' => true,
    'page'     => $current_page,
    'orderby'  => 'date',
    'order'    => 'DESC',
];

if ($search !== '') {
    $args['s'] = $search;
}

if (class_exists('RKM_Vehicle_Compatibility')) {
    $args = RKM_Vehicle_Compatibility::apply_catalog_query_filters($args, $vehicle_filters);
}

$products_query = function_exists('wc_get_products') ? wc_get_products($args) : null;
$products = is_object($products_query) && !empty($products_query->products) ? $products_query->products : [];
$total_products = is_object($products_query) && isset($products_query->total) ? (int) $products_query->total : count($products);
$total_pages = is_object($products_query) && isset($products_query->max_num_pages) ? max(1, (int) $products_query->max_num_pages) : 1;
$current_page = min($current_page, $total_pages);
$panel_base_url = home_url('/mi-cuenta/panel/');
$pagination_args = [
    'section' => 'catalogo',
];

if ($search !== '') {
    $pagination_args['s'] = $search;
}

if (!empty($vehicle_filters['brand'])) {
    $pagination_args['rkm_vehicle_brand'] = (int) $vehicle_filters['brand'];
}

if (!empty($vehicle_filters['model'])) {
    $pagination_args['rkm_vehicle_model'] = (int) $vehicle_filters['model'];
}

if (!empty($vehicle_filters['part_category'])) {
    $pagination_args['rkm_part_category'] = (int) $vehicle_filters['part_category'];
}

if ($vehicle_filters['year'] !== '') {
    $pagination_args['rkm_year'] = $vehicle_filters['year'];
}
?>

<div class="rkm-app">
    <div class="rkm-container rkm-dashboard-wrapper rkm-branded-layout">
        <?php include RKM_CORE_PATH . 'templates/partials/private-header.php'; ?>

        <div class="rkm-dashboard-header">
            <div>
                <h1 class="rkm-title">Catalogo</h1>
                <p class="rkm-subtitle">Explora productos disponibles para tu negocio</p>
            </div>
        </div>

        <div class="rkm-sidebar-layout rkm-dashboard-grid">
            <aside class="rkm-sidebar-card">
                <?php include RKM_CORE_PATH . 'templates/partials/subnav.php'; ?>
            </aside>

            <main class="rkm-main-card rkm-catalog-page">
                <div class="rkm-toolbar rkm-catalog-filters">
                    <form method="get" class="rkm-search-form rkm-catalog-filters__form">
                        <input type="hidden" name="section" value="catalogo">

                        <label class="rkm-catalog-filter">
                            <span>Buscar</span>
                            <input
                                type="text"
                                name="s"
                                value="<?php echo esc_attr($search); ?>"
                                placeholder="Nombre o palabra clave"
                                class="rkm-input"
                                autocomplete="off"
                            >
                        </label>

                        <label class="rkm-catalog-filter">
                            <span>Marca</span>
                            <select name="rkm_vehicle_brand" class="rkm-input">
                                <option value="">Todas</option>
                                <?php foreach ($rkm_vehicle_brands as $vehicle_brand) : ?>
                                    <option value="<?php echo esc_attr((string) $vehicle_brand->term_id); ?>" <?php selected((int) $vehicle_filters['brand'], (int) $vehicle_brand->term_id); ?>>
                                        <?php echo esc_html($vehicle_brand->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label class="rkm-catalog-filter">
                            <span>Modelo</span>
                            <select name="rkm_vehicle_model" class="rkm-input">
                                <option value="">Todos</option>
                                <?php foreach ($rkm_vehicle_models as $vehicle_model) : ?>
                                    <option value="<?php echo esc_attr((string) $vehicle_model->term_id); ?>" <?php selected((int) $vehicle_filters['model'], (int) $vehicle_model->term_id); ?>>
                                        <?php echo esc_html($vehicle_model->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label class="rkm-catalog-filter">
                            <span>Ano</span>
                            <input
                                type="number"
                                name="rkm_year"
                                min="1900"
                                max="2100"
                                step="1"
                                value="<?php echo esc_attr((string) $vehicle_filters['year']); ?>"
                                placeholder="Ej: 2020"
                                class="rkm-input"
                            >
                        </label>

                        <label class="rkm-catalog-filter">
                            <span>Categoria</span>
                            <select name="rkm_part_category" class="rkm-input">
                                <option value="">Todas</option>
                                <?php foreach ($rkm_part_categories as $part_category) : ?>
                                    <option value="<?php echo esc_attr((string) $part_category->term_id); ?>" <?php selected((int) $vehicle_filters['part_category'], (int) $part_category->term_id); ?>>
                                        <?php echo esc_html($part_category->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <div class="rkm-catalog-filters__actions">
                            <button type="submit" class="rkm-btn rkm-btn--secondary">Filtrar</button>
                            <a class="rkm-catalog-filters__clear" href="<?php echo esc_url(add_query_arg(['section' => 'catalogo'], $panel_base_url)); ?>">Limpiar</a>
                        </div>
                    </form>
                </div>

                <?php if (!empty($products)) : ?>
                    <div class="rkm-catalog-grid">
                        <?php foreach ($products as $product) : ?>
                            <?php
                            $product_id  = $product->get_id();
                            $name        = $product->get_name();
                            $price_html  = $product->get_price_html();
                            $sku         = $product->get_sku();
                            $in_stock    = $product->is_in_stock();
                            $image       = get_the_post_thumbnail_url($product_id, 'medium');
                            $modal_image = get_the_post_thumbnail_url($product_id, 'large');
                            $description = trim(wp_strip_all_tags($product->get_short_description()));
                            $price_text  = wp_strip_all_tags($price_html ?: 'Sin precio');
                            $price_bs    = function_exists('rkm_get_product_bcv_price') ? rkm_get_product_bcv_price($product, $bcv_rate) : '';
                            $stock_label = $in_stock ? 'En stock' : 'Sin stock';

                            if (!$image) {
                                $image = wc_placeholder_img_src();
                            }

                            if (!$modal_image) {
                                $modal_image = $image;
                            }
                            ?>
                            <article class="rkm-product-card">
                                <div class="rkm-product-card__image">
                                    <img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr($name); ?>">
                                </div>

                                <div class="rkm-product-card__body">
                                    <div class="rkm-product-card__meta-row">
                                        <p class="rkm-product-card__meta">
                                            <span class="rkm-product-card__meta-label">SKU</span>
                                            <span class="rkm-product-card__meta-value"><?php echo esc_html($sku !== '' ? $sku : 'Sin SKU'); ?></span>
                                        </p>

                                        <p class="rkm-product-card__stock <?php echo $in_stock ? 'is-in-stock' : 'is-out-of-stock'; ?>">
                                            <?php echo esc_html($stock_label); ?>
                                        </p>
                                    </div>

                                    <h3 class="rkm-product-card__title"><?php echo esc_html($name); ?></h3>

                                    <p class="rkm-product-card__excerpt">
                                        <?php echo esc_html($description !== '' ? $description : 'Producto disponible para consultar.'); ?>
                                    </p>

                                    <p class="rkm-product-card__price">
                                        <?php echo wp_kses_post($price_html ?: 'Sin precio'); ?>
                                    </p>

                                    <?php if ($price_bs !== '') : ?>
                                        <p class="rkm-product-card__price-bs"><?php echo esc_html($price_bs); ?></p>
                                    <?php else : ?>
                                        <p class="rkm-product-card__price-bs rkm-product-card__price-bs--muted">Tasa BCV no disponible</p>
                                    <?php endif; ?>
                                </div>

                                <div class="rkm-product-card__actions">
                                    <button
                                        type="button"
                                        class="rkm-btn rkm-btn--primary rkm-catalog-view-product"
                                        data-product-name="<?php echo esc_attr($name); ?>"
                                        data-product-sku="<?php echo esc_attr($sku !== '' ? $sku : 'Sin SKU'); ?>"
                                        data-product-price="<?php echo esc_attr($price_text); ?>"
                                        data-product-price-bs="<?php echo esc_attr($price_bs); ?>"
                                        data-product-stock="<?php echo esc_attr($stock_label); ?>"
                                        data-product-description="<?php echo esc_attr($description !== '' ? $description : 'Producto disponible para consultar.'); ?>"
                                        data-product-image="<?php echo esc_url($modal_image); ?>"
                                    >
                                        Ver producto
                                    </button>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($total_pages > 1) : ?>
                        <nav class="rkm-catalog-pagination" aria-label="Paginacion de catalogo">
                            <?php if ($current_page > 1) : ?>
                                <a
                                    class="rkm-catalog-pagination__link rkm-catalog-pagination__link--nav"
                                    href="<?php echo esc_url(add_query_arg(array_merge($pagination_args, ['rkm_page' => $current_page - 1]), $panel_base_url)); ?>"
                                >
                                    Anterior
                                </a>
                            <?php endif; ?>

                            <div class="rkm-catalog-pagination__pages">
                                <?php foreach (range(1, $total_pages) as $page_number) : ?>
                                    <a
                                        class="rkm-catalog-pagination__link <?php echo $page_number === $current_page ? 'is-current' : ''; ?>"
                                        href="<?php echo esc_url(add_query_arg(array_merge($pagination_args, ['rkm_page' => $page_number]), $panel_base_url)); ?>"
                                        <?php echo $page_number === $current_page ? 'aria-current="page"' : ''; ?>
                                    >
                                        <?php echo esc_html((string) $page_number); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>

                            <?php if ($current_page < $total_pages) : ?>
                                <a
                                    class="rkm-catalog-pagination__link rkm-catalog-pagination__link--nav"
                                    href="<?php echo esc_url(add_query_arg(array_merge($pagination_args, ['rkm_page' => $current_page + 1]), $panel_base_url)); ?>"
                                >
                                    Siguiente
                                </a>
                            <?php endif; ?>
                        </nav>
                    <?php endif; ?>
                <?php else : ?>
                    <div class="rkm-empty-state">
                        No se encontraron productos.
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <div class="rkm-catalog-modal" id="rkmCatalogProductModal" aria-hidden="true">
        <div class="rkm-catalog-modal__overlay" data-rkm-catalog-modal-close></div>

        <div
            class="rkm-catalog-modal__dialog"
            role="dialog"
            aria-modal="true"
            aria-labelledby="rkmCatalogModalTitle"
        >
            <button
                type="button"
                class="rkm-catalog-modal__close"
                data-rkm-catalog-modal-close
                aria-label="Cerrar producto"
            >
                &times;
            </button>

            <div class="rkm-catalog-modal__media">
                <img id="rkmCatalogModalImage" class="rkm-catalog-modal__image" src="" alt="">
            </div>

            <div class="rkm-catalog-modal__content">
                <span class="rkm-catalog-modal__eyebrow">Vista rapida</span>
                <h2 id="rkmCatalogModalTitle" class="rkm-catalog-modal__title">Producto</h2>

                <div class="rkm-catalog-modal__meta">
                    <div class="rkm-catalog-modal__meta-item">
                        <span>SKU</span>
                        <strong id="rkmCatalogModalSku"></strong>
                    </div>

                    <div class="rkm-catalog-modal__meta-item">
                        <span>Precio</span>
                        <strong id="rkmCatalogModalPrice"></strong>
                        <small id="rkmCatalogModalPriceBs" class="rkm-product-card__price-bs"></small>
                    </div>

                    <div class="rkm-catalog-modal__meta-item">
                        <span>Stock</span>
                        <strong id="rkmCatalogModalStock"></strong>
                    </div>
                </div>

                <p id="rkmCatalogModalDescription" class="rkm-catalog-modal__description"></p>
            </div>
        </div>
    </div>
</div>
