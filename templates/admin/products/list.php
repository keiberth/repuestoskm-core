<?php
if (!defined('ABSPATH')) {
    exit;
}

$products = isset($data['products']) && is_array($data['products']) ? $data['products'] : [];
$products_total = isset($data['products_total']) ? (int) $data['products_total'] : 0;
$products_page = isset($data['products_page']) ? max(1, (int) $data['products_page']) : 1;
$products_max_pages = isset($data['products_max_pages']) ? max(1, (int) $data['products_max_pages']) : 1;
$product_search = $data['product_search'] ?? '';
$product_status = $data['product_status'] ?? '';
$product_cat = isset($data['product_cat']) ? (int) $data['product_cat'] : 0;
$section_url = $data['section_url'] ?? home_url('/mi-cuenta/panel/?section=productos');
$status_options = isset($data['status_options']) && is_array($data['status_options']) ? $data['status_options'] : [];
$categories = isset($data['categories']) && is_array($data['categories']) ? $data['categories'] : [];
$products_module = class_exists('RKM_Products') ? new RKM_Products() : null;
?>

<section class="rkm-admin-products-hero">
    <div>
        <span class="rkm-admin-products-hero__eyebrow">Publicaciones</span>
        <h2>Catalogo publicado</h2>
        <p>Administra precio, stock y estado de productos WooCommerce como publicaciones comerciales.</p>
    </div>

    <div class="rkm-admin-products-hero__stats">
        <span><?php echo esc_html((string) $products_total); ?></span>
        <strong>resultados</strong>
    </div>
</section>

<section class="rkm-card rkm-admin-products-list-card">
    <form method="get" action="<?php echo esc_url(home_url('/mi-cuenta/panel/')); ?>" class="rkm-admin-products-filters">
        <input type="hidden" name="section" value="productos">
        <input type="hidden" name="view" value="list">

        <label>
            <span>Buscar</span>
            <input type="search" name="product_search" value="<?php echo esc_attr($product_search); ?>" placeholder="Nombre o SKU">
        </label>

        <label>
            <span>Estado</span>
            <select name="product_status">
                <option value="">Todos</option>
                <?php foreach ($status_options as $status_key => $status_label) : ?>
                    <option value="<?php echo esc_attr($status_key); ?>" <?php selected($product_status, $status_key); ?>>
                        <?php echo esc_html($status_label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            <span>Categoria</span>
            <select name="product_cat">
                <option value="0">Todas</option>
                <?php foreach ($categories as $category) : ?>
                    <option value="<?php echo esc_attr((string) $category->term_id); ?>" <?php selected($product_cat, (int) $category->term_id); ?>>
                        <?php echo esc_html($category->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <button type="submit" class="rkm-btn rkm-btn--secondary">Filtrar</button>
        <a class="rkm-admin-products-link" href="<?php echo esc_url($section_url); ?>">Limpiar</a>
    </form>

    <?php if (empty($categories)) : ?>
        <div class="rkm-admin-products-notice rkm-admin-products-notice--error">
            <p>No hay categorias de producto creadas. Puedes publicar sin categoria, pero conviene configurarlas en WooCommerce.</p>
        </div>
    <?php endif; ?>

    <?php if (empty($products)) : ?>
        <div class="rkm-admin-products-empty">
            <strong>No se encontraron publicaciones</strong>
            <p>Ajusta los filtros o crea una nueva publicacion.</p>
        </div>
    <?php else : ?>
        <div class="rkm-admin-products-publications">
            <?php foreach ($products as $product) : ?>
                <?php
                $row = $products_module ? $products_module->get_publication_row($product) : null;

                if (!$row) {
                    continue;
                }
                ?>
                <article class="rkm-admin-products-publication">
                    <div class="rkm-admin-products-publication__image">
                        <?php if ($row['image_url'] !== '') : ?>
                            <img src="<?php echo esc_url($row['image_url']); ?>" alt="<?php echo esc_attr($row['name']); ?>">
                        <?php else : ?>
                            <span><?php echo esc_html(strtoupper(substr($row['name'], 0, 1))); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="rkm-admin-products-publication__content">
                        <div class="rkm-admin-products-publication__top">
                            <div>
                                <h3><?php echo esc_html($row['name']); ?></h3>
                                <p><?php echo esc_html($row['category']); ?> · SKU <?php echo esc_html($row['sku'] !== '' ? $row['sku'] : 'sin SKU'); ?></p>
                            </div>

                            <span class="rkm-admin-products-status rkm-admin-products-status--<?php echo esc_attr($row['status']); ?>">
                                <?php echo esc_html($status_options[$row['status']] ?? ucfirst($row['status'])); ?>
                            </span>
                        </div>

                        <div class="rkm-admin-products-publication__metrics">
                            <div>
                                <span>Venta</span>
                                <strong class="rkm-admin-products-money">
                                    <span class="rkm-admin-products-money__symbol">$</span>
                                    <span class="rkm-admin-products-money__amount"><?php echo esc_html(number_format((float) $row['regular_price'], 2, ',', '.')); ?></span>
                                </strong>
                            </div>
                            <div>
                                <span>Costo</span>
                                <strong class="rkm-admin-products-money">
                                    <span class="rkm-admin-products-money__symbol">$</span>
                                    <span class="rkm-admin-products-money__amount"><?php echo esc_html(number_format((float) $row['cost_price'], 2, ',', '.')); ?></span>
                                </strong>
                            </div>
                            <div>
                                <span>Stock</span>
                                <strong><?php echo esc_html((string) $row['stock']); ?></strong>
                            </div>
                        </div>
                    </div>

                    <div class="rkm-admin-products-publication__actions">
                        <a class="rkm-admin-products-action rkm-admin-products-action--edit" href="<?php echo esc_url($row['edit_url']); ?>">Editar</a>

                        <?php if ($row['view_url']) : ?>
                            <a class="rkm-admin-products-action rkm-admin-products-action--view" href="<?php echo esc_url($row['view_url']); ?>">Ver</a>
                        <?php endif; ?>

                        <form method="post" action="<?php echo esc_url($section_url); ?>">
                            <input type="hidden" name="product_id" value="<?php echo esc_attr((string) $row['id']); ?>">
                            <?php wp_nonce_field('rkm_products_update', 'rkm_products_nonce'); ?>

                            <?php if ($row['status'] === 'publish') : ?>
                                <input type="hidden" name="rkm_products_action" value="pause_product">
                                <button type="submit" class="rkm-admin-products-action rkm-admin-products-action--pause">Pausar</button>
                            <?php else : ?>
                                <input type="hidden" name="rkm_products_action" value="activate_product">
                                <button type="submit" class="rkm-admin-products-action rkm-admin-products-action--activate">Activar</button>
                            <?php endif; ?>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($products_max_pages > 1) : ?>
            <nav class="rkm-admin-products-pagination">
                <?php
                $base_args = [
                    'section' => 'productos',
                    'view' => 'list',
                ];

                if ($product_search !== '') {
                    $base_args['product_search'] = $product_search;
                }

                if ($product_status !== '') {
                    $base_args['product_status'] = $product_status;
                }

                if ($product_cat > 0) {
                    $base_args['product_cat'] = $product_cat;
                }
                ?>

                <?php if ($products_page > 1) : ?>
                    <a href="<?php echo esc_url(add_query_arg(array_merge($base_args, ['products_page' => $products_page - 1]), home_url('/mi-cuenta/panel/'))); ?>">Anterior</a>
                <?php endif; ?>

                <span>Pagina <?php echo esc_html((string) $products_page); ?> de <?php echo esc_html((string) $products_max_pages); ?></span>

                <?php if ($products_page < $products_max_pages) : ?>
                    <a href="<?php echo esc_url(add_query_arg(array_merge($base_args, ['products_page' => $products_page + 1]), home_url('/mi-cuenta/panel/'))); ?>">Siguiente</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
