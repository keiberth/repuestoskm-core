<?php
if (!defined('ABSPATH')) {
    exit;
}

$page_title = $data['page_title'] ?? 'Productos';
$page_subtitle = $data['page_subtitle'] ?? '';
$notice = $data['products_notice'] ?? null;
$products = isset($data['products']) && is_array($data['products']) ? $data['products'] : [];
$products_total = isset($data['products_total']) ? (int) $data['products_total'] : 0;
$products_page = isset($data['products_page']) ? max(1, (int) $data['products_page']) : 1;
$products_max_pages = isset($data['products_max_pages']) ? max(1, (int) $data['products_max_pages']) : 1;
$product_search = $data['product_search'] ?? '';
$section_url = $data['section_url'] ?? home_url('/mi-cuenta/panel/?section=productos');
$status_options = isset($data['status_options']) && is_array($data['status_options']) ? $data['status_options'] : [];
?>

<div class="rkm-app rkm-module-app">
    <div class="rkm-container rkm-admin-products-page">
        <?php include plugin_dir_path(__FILE__) . '../partials/private-header.php'; ?>

        <div class="rkm-page-header rkm-admin-products-page__header">
            <div>
                <h1><?php echo esc_html($page_title); ?></h1>
                <p><?php echo esc_html($page_subtitle); ?></p>
            </div>

            <a class="rkm-admin-products-page__back" href="<?php echo esc_url(home_url('/mi-cuenta/panel/')); ?>">
                Volver al panel admin
            </a>
        </div>

        <div class="rkm-module-shell rkm-admin-products-shell">
            <section class="rkm-admin-products-hero">
                <div>
                    <span class="rkm-admin-products-hero__eyebrow">Catalogo WooCommerce</span>
                    <h2>Gestion operativa de productos</h2>
                    <p>Los cambios se guardan sobre productos WooCommerce existentes y quedan disponibles para catalogo, nueva orden y pedidos.</p>
                </div>

                <div class="rkm-admin-products-hero__stats">
                    <span><?php echo esc_html((string) $products_total); ?></span>
                    <strong>productos</strong>
                </div>
            </section>

            <?php if (!empty($notice['message'])) : ?>
                <div class="rkm-admin-products-notice rkm-admin-products-notice--<?php echo esc_attr($notice['type']); ?>">
                    <p><?php echo esc_html($notice['message']); ?></p>
                </div>
            <?php endif; ?>

            <div class="rkm-admin-products-grid">
                <section class="rkm-card rkm-admin-products-form-card">
                    <div class="rkm-admin-products-panel__header">
                        <h3>Nuevo producto</h3>
                        <p>Crea un producto simple en WooCommerce sin salir del panel RKM.</p>
                    </div>

                    <form method="post" action="<?php echo esc_url($section_url); ?>" enctype="multipart/form-data" class="rkm-admin-products-form" data-rkm-products-form>
                        <input type="hidden" name="rkm_products_action" value="create_product">
                        <?php wp_nonce_field('rkm_products_update', 'rkm_products_nonce'); ?>

                        <label class="rkm-admin-products-field">
                            <span>Nombre</span>
                            <input type="text" name="name" required placeholder="Ej: Filtro de aceite">
                        </label>

                        <div class="rkm-admin-products-form__row">
                            <label class="rkm-admin-products-field">
                                <span>Precio</span>
                                <input type="number" name="price" min="0" step="0.01" required placeholder="0,00">
                            </label>

                            <label class="rkm-admin-products-field">
                                <span>Stock</span>
                                <input type="number" name="stock" min="0" step="1" required value="0">
                            </label>
                        </div>

                        <label class="rkm-admin-products-field">
                            <span>Estado</span>
                            <select name="status">
                                <?php foreach ($status_options as $status_key => $status_label) : ?>
                                    <option value="<?php echo esc_attr($status_key); ?>"><?php echo esc_html($status_label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>

                        <label class="rkm-admin-products-field">
                            <span>Descripcion</span>
                            <textarea name="description" rows="5" placeholder="Descripcion comercial del producto"></textarea>
                        </label>

                        <label class="rkm-admin-products-field rkm-admin-products-file">
                            <span>Imagen</span>
                            <input type="file" name="product_image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" data-rkm-product-image>
                            <small data-rkm-product-image-label>JPG, PNG o WEBP. Maximo 5 MB.</small>
                        </label>

                        <div class="rkm-admin-products-form__feedback" data-rkm-products-feedback></div>

                        <button type="submit" class="rkm-btn rkm-btn--primary">Crear producto</button>
                    </form>
                </section>

                <section class="rkm-card rkm-admin-products-list-card">
                    <div class="rkm-admin-products-panel__header">
                        <h3>Productos registrados</h3>
                        <p>Busca y actualiza precio, stock o estado sin modificar otros datos del producto.</p>
                    </div>

                    <form method="get" action="<?php echo esc_url(home_url('/mi-cuenta/panel/')); ?>" class="rkm-admin-products-search">
                        <input type="hidden" name="section" value="productos">
                        <input type="search" name="product_search" value="<?php echo esc_attr($product_search); ?>" placeholder="Buscar por nombre">
                        <button type="submit" class="rkm-btn rkm-btn--secondary">Buscar</button>
                        <?php if ($product_search !== '') : ?>
                            <a class="rkm-admin-products-link" href="<?php echo esc_url($section_url); ?>">Limpiar</a>
                        <?php endif; ?>
                    </form>

                    <?php if (empty($products)) : ?>
                        <div class="rkm-admin-products-empty">
                            <strong>No se encontraron productos</strong>
                            <p>Ajusta la busqueda o crea un producto nuevo.</p>
                        </div>
                    <?php else : ?>
                        <div class="rkm-admin-products-list">
                            <?php foreach ($products as $product) : ?>
                                <?php
                                if (!$product instanceof WC_Product) {
                                    continue;
                                }

                                $product_id = $product->get_id();
                                $image_id = $product->get_image_id();
                                $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : '';
                                $status = $product->get_status();
                                $stock_quantity = $product->get_manage_stock() ? (int) $product->get_stock_quantity() : 0;
                                ?>
                                <article class="rkm-admin-products-item">
                                    <div class="rkm-admin-products-item__media">
                                        <?php if ($image_url) : ?>
                                            <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($product->get_name()); ?>">
                                        <?php else : ?>
                                            <span><?php echo esc_html(strtoupper(substr($product->get_name(), 0, 1))); ?></span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="rkm-admin-products-item__main">
                                        <div class="rkm-admin-products-item__heading">
                                            <div>
                                                <h4><?php echo esc_html($product->get_name()); ?></h4>
                                                <p>ID <?php echo esc_html((string) $product_id); ?></p>
                                            </div>

                                            <mark class="rkm-admin-products-status rkm-admin-products-status--<?php echo esc_attr($status); ?>">
                                                <?php echo esc_html($status_options[$status] ?? ucfirst($status)); ?>
                                            </mark>
                                        </div>

                                        <form method="post" action="<?php echo esc_url($section_url); ?>" class="rkm-admin-products-edit-form">
                                            <input type="hidden" name="rkm_products_action" value="update_product">
                                            <input type="hidden" name="product_id" value="<?php echo esc_attr((string) $product_id); ?>">
                                            <?php wp_nonce_field('rkm_products_update', 'rkm_products_nonce'); ?>

                                            <label>
                                                <span>Precio</span>
                                                <input type="number" name="price" min="0" step="0.01" value="<?php echo esc_attr((string) $product->get_regular_price()); ?>" required>
                                            </label>

                                            <label>
                                                <span>Stock</span>
                                                <input type="number" name="stock" min="0" step="1" value="<?php echo esc_attr((string) $stock_quantity); ?>" required>
                                            </label>

                                            <label>
                                                <span>Estado</span>
                                                <select name="status">
                                                    <?php foreach ($status_options as $status_key => $status_label) : ?>
                                                        <option value="<?php echo esc_attr($status_key); ?>" <?php selected($status, $status_key); ?>>
                                                            <?php echo esc_html($status_label); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </label>

                                            <button type="submit" class="rkm-admin-products-link">Guardar</button>
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
                                ];

                                if ($product_search !== '') {
                                    $base_args['product_search'] = $product_search;
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
            </div>
        </div>
    </div>
</div>
