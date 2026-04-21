<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="rkm-app">
    <div class="rkm-container">
        <?php include plugin_dir_path(__FILE__) . 'partials/private-header.php'; ?>
        <div class="rkm-page-header">
            <h1>Mis pedidos</h1>
        </div>
        <?php
            $current = 'pedidos';
            include plugin_dir_path(__FILE__) . 'partials/subnav.php';
        ?>

        <?php if (empty($orders)) : ?>
            <div class="rkm-card">
                <p>No tenés pedidos todavía.</p>
            </div>
        <?php else : ?>
            <div class="rkm-orders-list">
                <?php foreach ($orders as $order) : ?>
                    <?php
                    $order_id     = $order->get_id();
                    $order_number = $order->get_order_number();
                    $order_date   = $order->get_date_created() ? $order->get_date_created()->date_i18n('d/m/Y') : '-';
                    $order_total  = $order->get_total();
                    $order_subtotal = $order->get_subtotal();
                    $item_count   = $order->get_item_count();
                    $item_count_label = $item_count === 1 ? '1 producto' : $item_count . ' productos';
                    $status_key   = $order->get_status();
                    $status_label = wc_get_order_status_name($status_key);
                    $status_slug  = sanitize_html_class('status-' . $status_key);
                    $cancelable_statuses = class_exists('RKM_Orders_Actions')
                        ? RKM_Orders_Actions::get_cancelable_statuses()
                        : ['pending', 'on-hold', 'processing'];
                    $can_cancel = in_array($status_key, $cancelable_statuses, true);

                    $items_data = [];
                        foreach ($order->get_items() as $item) {
                            $product = $item->get_product();

                            $items_data[] = [
                                'id'        => $product ? $product->get_id() : 0,
                                'product_id'=> $product ? $product->get_id() : 0,
                                'name'      => $item->get_name(),
                                'qty'       => $item->get_quantity(),
                                'subtotal'  => wp_strip_all_tags(wc_price($item->get_total())),
                                'price_raw' => (float) $item->get_total() / max(1, (int) $item->get_quantity()),
                                'sku'       => $product ? $product->get_sku() : '',
                            ];
                        }
                    ?>
                    <div class="rkm-order-card rkm-order-card--current">
                        <div class="rkm-order-card__header">
                            <div class="rkm-order-card__heading">
                                <span class="rkm-order-card__eyebrow">Pedido activo</span>
                                <div class="rkm-order-card__number">
                                    <strong>#<?php echo esc_html($order_number); ?></strong>
                                </div>
                                <div class="rkm-order-card__date">
                                    Creado el <?php echo esc_html($order_date); ?>
                                </div>
                            </div>

                            <div class="rkm-order-card__status-wrap">
                                <span class="rkm-order-card__label">Estado</span>
                                <mark class="rkm-order-badge <?php echo esc_attr($status_slug); ?>">
                                    <?php echo esc_html($status_label); ?>
                                </mark>
                            </div>
                        </div>

                        <div class="rkm-order-card__body">
                            <div class="rkm-order-card__metric rkm-order-card__metric--total">
                                <span class="rkm-order-card__label">Total del pedido</span>
                                <strong><?php echo wp_kses_post(wc_price($order_total)); ?></strong>
                            </div>

                            <div class="rkm-order-card__metric">
                                <span class="rkm-order-card__label">Productos</span>
                                <span><?php echo esc_html($item_count_label); ?></span>
                            </div>

                            <div class="rkm-order-card__metric">
                                <span class="rkm-order-card__label">Seguimiento</span>
                                <span>Podés ver detalle y estado actual del pedido.</span>
                            </div>
                        </div>

                        <div class="rkm-order-card__footer">
                            <div class="rkm-order-card__summary">
                                <span class="rkm-order-card__summary-dot"></span>
                                <?php echo esc_html($item_count_label); ?> listos para revisar
                            </div>

                            <div class="rkm-order-card__actions">
                                <button
                                    type="button"
                                    class="rkm-btn-primary rkm-btn-sm rkm-open-order-modal"
                                    data-order-id="<?php echo esc_attr($order_id); ?>"
                                    data-order-number="<?php echo esc_attr($order_number); ?>"
                                    data-order-date="<?php echo esc_attr($order_date); ?>"
                                    data-order-status="<?php echo esc_attr($status_key); ?>"
                                    data-order-status-label="<?php echo esc_attr($status_label); ?>"
                                    data-order-subtotal="<?php echo esc_attr(wp_strip_all_tags(wc_price($order_subtotal))); ?>"
                                    data-order-total="<?php echo esc_attr(wp_strip_all_tags(wc_price($order_total))); ?>"
                                    data-order-items='<?php echo wp_json_encode($items_data); ?>'
                                >
                                    Ver pedido
                                </button>

                                <?php if ($can_cancel) : ?>
                                    <button
                                        type="button"
                                        class="rkm-btn-danger rkm-btn-sm rkm-cancel-order-btn"
                                        data-order-id="<?php echo esc_attr($order_id); ?>"
                                    >
                                        Cancelar
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <div class="rkm-modal" id="rkmOrderModal">
        <div class="rkm-modal__overlay"></div>

        <div class="rkm-modal__content rkm-order-modal">
            <button type="button" class="rkm-modal__close">&times;</button>

            <div class="rkm-modal__header rkm-order-modal__header">
                <div class="rkm-order-modal__heading">
                    <span class="rkm-order-modal__eyebrow">Detalle del pedido</span>
                    <h2 id="rkmOrderModalTitle">Pedido</h2>
                    <p id="rkmOrderModalMeta"></p>
                </div>

                <div class="rkm-order-modal__status">
                    <span class="rkm-order-modal__status-label">Estado</span>
                    <mark id="rkmOrderModalStatus" class="rkm-order-badge"></mark>
                </div>
            </div>

            <div class="rkm-modal__body">
                <div class="rkm-modal__section rkm-order-modal__section">
                    <div class="rkm-order-modal__section-head">
                        <h3>Estado actual</h3>
                        <span class="rkm-order-modal__section-kicker">Seguimiento general</span>
                    </div>
                    <div class="rkm-modal__status-box rkm-order-modal__status-box">
                        <p id="rkmOrderModalStatusDescription" class="rkm-modal__status-description"></p>
                    </div>
                </div>

                <div class="rkm-modal__section rkm-order-modal__section">
                    <div class="rkm-order-modal__section-head">
                        <h3>Productos</h3>
                        <span class="rkm-order-modal__section-kicker">Detalle del pedido</span>
                    </div>
                    <div id="rkmOrderModalItems" class="rkm-modal__items rkm-order-modal__items"></div>
                </div>

                <div class="rkm-modal__section rkm-order-modal__section">
                    <div class="rkm-order-modal__section-head">
                        <h3>Seguimiento del pedido</h3>
                        <span class="rkm-order-modal__section-kicker">Estado por etapas</span>
                    </div>
                    <div id="rkmOrderTimeline" class="rkm-timeline rkm-order-modal__timeline"></div>
                </div>

                <div class="rkm-modal__section rkm-order-modal__section">
                    <div class="rkm-order-modal__summary">
                        <div class="rkm-order-modal__total-row">
                            <span>Subtotal</span>
                            <strong id="rkmOrderModalSubtotal"></strong>
                        </div>
                        <div class="rkm-order-modal__total-row rkm-order-modal__total-row--final">
                            <span>Total</span>
                            <strong id="rkmOrderModalTotal"></strong>
                        </div>
                    </div>
                </div>

                <div class="rkm-modal__section rkm-order-modal__section">
                    <div id="rkmOrderModalActions" class="rkm-order-modal__actions"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="rkmCancelModal" class="rkm-modal">
    <div class="rkm-modal__overlay"></div>

    <div class="rkm-modal__content rkm-modal__content--sm">
        <button class="rkm-modal__close" type="button">&times;</button>

        <div class="rkm-modal__header">
            <h2>Cancelar pedido</h2>
        </div>

        <div class="rkm-modal__section">
            <p>¿Querés cancelar este pedido?</p>
            <p class="rkm-modal__hint">Esta acción no se puede deshacer.</p>
        </div>

        <div class="rkm-modal__footer-actions">
            <button type="button" class="rkm-btn-secondary" id="rkmCancelModalClose">
                Volver
            </button>

            <button type="button" class="rkm-btn-danger" id="rkmConfirmCancelOrder">
                Sí, cancelar pedido
            </button>
        </div>
    </div>
</div>
</div>




