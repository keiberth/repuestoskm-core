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
                        $stock       = $product->get_stock_quantity();
                        $image       = get_the_post_thumbnail_url($id, 'medium');
                        $description = $product->get_short_description();

                        if (!$image) {
                            $image = wc_placeholder_img_src();
                        }

                        if (is_null($stock)) {
                            $stock = 9999;
                        }
                        ?>

                        <article
                            class="rkm-product-card"
                            data-id="<?php echo esc_attr($id); ?>"
                            data-name="<?php echo esc_attr($name); ?>"
                            data-price="<?php echo esc_attr($price); ?>"
                            data-sku="<?php echo esc_attr($sku); ?>"
                            data-stock="<?php echo esc_attr($stock); ?>"
                            data-image="<?php echo esc_url($image); ?>"
                            data-description="<?php echo esc_attr(wp_strip_all_tags($description)); ?>"
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
            </aside>

        </div>
        <?php
        $user_id = get_current_user_id();

        $billing_address = [
            'name'     => get_user_meta($user_id, 'billing_first_name', true) . ' ' . get_user_meta($user_id, 'billing_last_name', true),
            'address'  => get_user_meta($user_id, 'billing_address_1', true),
            'city'     => get_user_meta($user_id, 'billing_city', true),
            'phone'    => get_user_meta($user_id, 'billing_phone', true),
        ];

        $shipping_address = [
            'name'     => get_user_meta($user_id, 'shipping_first_name', true) . ' ' . get_user_meta($user_id, 'shipping_last_name', true),
            'address'  => get_user_meta($user_id, 'shipping_address_1', true),
            'city'     => get_user_meta($user_id, 'shipping_city', true),
        ];
        ?>

        <div class="rkm-card rkm-address-summary">

            <div class="rkm-address-summary__header">
                <h3>Dirección de envío</h3>
                <a href="<?php echo esc_url(home_url('/mi-cuenta/panel/?section=direcciones')); ?>" class="rkm-link-edit">
                    Editar
                </a>
            </div>

            <?php if (!empty($shipping_address['address'])) : ?>

                <div class="rkm-address-summary__content">
                    <strong><?php echo esc_html($shipping_address['name']); ?></strong>
                    <p><?php echo esc_html($shipping_address['address']); ?></p>
                    <p><?php echo esc_html($shipping_address['city']); ?></p>
                </div>

            <?php else : ?>

                <div class="rkm-address-summary__empty">
                    <p>No tenés una dirección configurada.</p>
                    <a href="<?php echo esc_url(home_url('/mi-cuenta/panel/?section=direcciones')); ?>" class="rkm-btn-secondary">
                        Cargar dirección
                    </a>
                </div>

            <?php endif; ?>

</div>

    </div>
    <script>
        window.rkmRepeatOrderItems = <?php echo wp_json_encode($repeat_items); ?>;
    </script>
</div>