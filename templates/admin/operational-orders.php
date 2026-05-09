<?php
if (!defined('ABSPATH')) {
    exit;
}

$page_title = $data['page_title'] ?? 'Pedidos operativos';
$page_subtitle = $data['page_subtitle'] ?? '';
$orders = isset($data['operational_orders']) && is_array($data['operational_orders']) ? $data['operational_orders'] : [];
$current = $data['current_section'] ?? 'pedidos-operativos';
$can_confirm_orders = !empty($data['can_confirm_orders']);
$confirmable_statuses = class_exists('RKM_Operational_Orders') ? RKM_Operational_Orders::get_confirmable_statuses() : ['rkm-review', 'pending', 'en-revision'];
$pending_review_count = count(array_filter($orders, static function ($order) {
    return in_array(($order['status'] ?? ''), ['rkm-review', 'pending', 'en-revision'], true);
}));
?>

<div class="rkm-app rkm-module-app rkm-admin-orders">
    <div class="rkm-container rkm-admin-orders-page rkm-branded-layout">
        <?php include plugin_dir_path(__FILE__) . '../partials/private-header.php'; ?>

        <div class="rkm-page-header rkm-admin-orders-page__header">
            <div>
                <h1><?php echo esc_html($page_title); ?></h1>
                <p><?php echo esc_html($page_subtitle); ?></p>
            </div>

            <a class="rkm-admin-orders-back" href="<?php echo esc_url(home_url('/mi-cuenta/panel/')); ?>">
                Volver al panel
            </a>
        </div>

        <?php include plugin_dir_path(__FILE__) . '../partials/subnav.php'; ?>

        <div class="rkm-module-shell rkm-admin-orders-shell">
            <section class="rkm-admin-orders-summary">
                <div>
                    <span class="rkm-admin-orders-summary__eyebrow">Flujo ERP RKM</span>
                    <strong><?php echo esc_html((string) count($orders)); ?></strong>
                    <p>pedidos operativos activos</p>
                </div>

                <div>
                    <span class="rkm-admin-orders-summary__eyebrow">Revision</span>
                    <strong data-rkm-review-count><?php echo esc_html((string) $pending_review_count); ?></strong>
                    <p>pendientes de validacion</p>
                </div>
            </section>

            <section class="rkm-card rkm-admin-orders-panel">
                <div class="rkm-admin-orders-panel__header">
                    <div>
                        <h2>Listado operativo</h2>
                        <p>Pedidos en estados RKM con detalle operativo y confirmacion administrativa controlada.</p>
                    </div>
                </div>

                <?php if (empty($orders)) : ?>
                    <div class="rkm-admin-orders-empty">
                        <strong>No hay pedidos operativos</strong>
                        <p>Cuando existan pedidos en estados RKM apareceran en esta consola.</p>
                    </div>
                <?php else : ?>
                    <div class="rkm-admin-orders-table-wrap">
                        <table class="rkm-admin-orders-table">
                            <thead>
                                <tr>
                                    <th>Pedido</th>
                                    <th>Cliente</th>
                                    <th>Fecha</th>
                                    <th>Total</th>
                                    <th>Estado</th>
                                    <th>Forma / condicion de pago</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order) : ?>
                                    <tr data-rkm-operational-order-row data-order-id="<?php echo esc_attr((string) $order['id']); ?>">
                                        <td data-label="Pedido">
                                            <strong>#<?php echo esc_html($order['number']); ?></strong>
                                        </td>
                                        <td data-label="Cliente"><?php echo esc_html($order['customer_name']); ?></td>
                                        <td data-label="Fecha"><?php echo esc_html($order['date']); ?></td>
                                        <td data-label="Total"><?php echo esc_html($order['total']); ?></td>
                                        <td data-label="Estado">
                                            <span
                                                class="rkm-admin-orders-status rkm-admin-orders-status--<?php echo esc_attr(sanitize_html_class($order['status'])); ?>"
                                                data-rkm-order-status-badge
                                            >
                                                <?php echo esc_html($order['status_label']); ?>
                                            </span>
                                        </td>
                                        <td data-label="Pago">
                                            <span>Condicion: <?php echo esc_html($order['payment_term']); ?></span>
                                            <small>Forma: <?php echo esc_html($order['payment_method']); ?></small>
                                        </td>
                                        <td data-label="Acciones">
                                            <div class="rkm-admin-orders-actions">
                                                <button
                                                    type="button"
                                                    class="rkm-admin-orders__btn rkm-admin-orders__btn--secondary rkm-admin-orders-detail-btn"
                                                    data-rkm-operational-order-detail
                                                    data-order-id="<?php echo esc_attr((string) $order['id']); ?>"
                                                >
                                                    Ver detalle
                                                </button>

                                                <?php if ($can_confirm_orders && in_array(($order['status'] ?? ''), $confirmable_statuses, true)) : ?>
                                                    <button
                                                        type="button"
                                                        class="rkm-admin-orders__btn rkm-admin-orders__btn--confirm rkm-admin-orders-confirm-btn"
                                                        data-rkm-confirm-operational-order
                                                        data-order-id="<?php echo esc_attr((string) $order['id']); ?>"
                                                    >
                                                        Confirmar
                                                    </button>
                                                <?php endif; ?>

                                                <?php if ($can_confirm_orders && ($order['status'] ?? '') === 'rkm-confirmed') : ?>
                                                    <button
                                                        type="button"
                                                        class="rkm-admin-orders__btn rkm-admin-orders__btn--primary rkm-admin-orders-warehouse-btn"
                                                        data-rkm-send-operational-order-warehouse
                                                        data-order-id="<?php echo esc_attr((string) $order['id']); ?>"
                                                    >
                                                        Enviar a almacen
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </div>

    <div class="rkm-admin-order-modal" id="rkmOperationalOrderModal" aria-hidden="true">
        <div class="rkm-admin-order-modal__overlay" data-rkm-operational-order-close></div>
        <div class="rkm-admin-order-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="rkmOperationalOrderModalTitle">
            <button type="button" class="rkm-admin-order-modal__close" data-rkm-operational-order-close aria-label="Cerrar detalle">&times;</button>

            <div class="rkm-admin-order-modal__header">
                <div>
                    <span class="rkm-admin-order-modal__eyebrow">Detalle operativo</span>
                    <h2 id="rkmOperationalOrderModalTitle">Pedido</h2>
                    <p id="rkmOperationalOrderModalMeta"></p>
                </div>
                <span id="rkmOperationalOrderModalStatus" class="rkm-admin-orders-status"></span>
            </div>

            <div class="rkm-admin-order-modal__body">
                <section class="rkm-admin-order-modal__grid">
                    <div class="rkm-admin-order-modal__box">
                        <span>Cliente</span>
                        <strong id="rkmOperationalOrderCustomer"></strong>
                        <small id="rkmOperationalOrderCustomerMeta"></small>
                    </div>
                    <div class="rkm-admin-order-modal__box">
                        <span>Pago</span>
                        <strong id="rkmOperationalOrderPaymentTerm"></strong>
                        <small id="rkmOperationalOrderPaymentMethod"></small>
                    </div>
                    <div class="rkm-admin-order-modal__box">
                        <span>Total</span>
                        <strong id="rkmOperationalOrderTotal"></strong>
                        <small>Solo lectura</small>
                    </div>
                </section>

                <section class="rkm-admin-order-modal__section">
                    <div class="rkm-admin-order-modal__section-head">
                        <h3>Productos</h3>
                    </div>
                    <div class="rkm-admin-order-modal__items" id="rkmOperationalOrderItems"></div>
                </section>

                <section class="rkm-admin-order-modal__section">
                    <div class="rkm-admin-order-modal__section-head">
                        <h3>Notas internas</h3>
                    </div>
                    <div class="rkm-admin-order-modal__notes" id="rkmOperationalOrderNotes"></div>
                </section>

                <?php if ($can_confirm_orders) : ?>
                    <section class="rkm-admin-order-modal__actions" id="rkmOperationalOrderActions">
                        <button type="button" class="rkm-admin-orders__btn rkm-admin-orders__btn--primary rkm-admin-orders-confirm-btn" id="rkmOperationalOrderConfirmBtn">
                            Confirmar pedido
                        </button>
                        <button type="button" class="rkm-admin-orders__btn rkm-admin-orders__btn--secondary rkm-admin-orders-warehouse-btn" id="rkmOperationalOrderWarehouseBtn">
                            Enviar a almacen
                        </button>
                    </section>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    window.rkmOperationalOrders = {
        ajax_url: <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>,
        nonce: <?php echo wp_json_encode(wp_create_nonce(RKM_Operational_Orders::get_nonce_action())); ?>,
        can_confirm: <?php echo $can_confirm_orders ? 'true' : 'false'; ?>,
        review_status: 'rkm-review',
        confirmable_statuses: <?php echo wp_json_encode($confirmable_statuses); ?>,
        confirmed_status: 'rkm-confirmed',
        warehouse_status: 'rkm-warehouse',
        orders: <?php echo wp_json_encode($orders); ?>
    };
</script>
