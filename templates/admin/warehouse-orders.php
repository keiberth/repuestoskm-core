<?php
if (!defined('ABSPATH')) {
    exit;
}

$panel_base_url = function_exists('wc_get_account_endpoint_url') ? wc_get_account_endpoint_url('panel') : home_url('/mi-cuenta/panel/');
$panel_url = function_exists('rkm_get_panel_url') ? rkm_get_panel_url() : $panel_base_url;

$page_title = $data['page_title'] ?? 'Almacen';
$page_subtitle = $data['page_subtitle'] ?? '';
$orders = isset($data['warehouse_orders']) && is_array($data['warehouse_orders']) ? $data['warehouse_orders'] : [];
$can_manage_warehouse = !empty($data['can_manage_warehouse']);
$warehouse_count = count(array_filter($orders, static function ($order) {
    return (($order['status'] ?? '') === 'rkm-warehouse');
}));
?>

<div class="rkm-app rkm-module-app rkm-warehouse">
    <div class="rkm-container rkm-warehouse-page rkm-branded-layout">
        <?php include plugin_dir_path(__FILE__) . '../partials/private-header.php'; ?>

        <div class="rkm-page-header rkm-warehouse-page__header">
            <div>
                <h1><?php echo esc_html($page_title); ?></h1>
                <p><?php echo esc_html($page_subtitle); ?></p>
            </div>

            <a class="rkm-warehouse-back" href="<?php echo esc_url($panel_url); ?>">
                Volver al panel
            </a>
        </div>

        <?php include plugin_dir_path(__FILE__) . '../partials/subnav.php'; ?>

        <div class="rkm-module-shell rkm-warehouse-shell">
            <section class="rkm-card rkm-warehouse-alert">
                <div class="rkm-warehouse-alert__header">
                    <div>
                        <h2>Pedidos en preparacion</h2>
                        <p>Almacen solo prepara pedidos confirmados y deja el pedido listo para la siguiente etapa.</p>
                    </div>
                    <span class="rkm-warehouse-alert__count"><?php echo esc_html((string) $warehouse_count); ?></span>
                </div>
                <div class="rkm-warehouse-alert__body">
                    <?php if ($warehouse_count > 0) : ?>
                        <strong><?php echo esc_html('Tenes ' . (int) $warehouse_count . ' pedidos en preparacion.'); ?></strong>
                        <p>Usa el filtro En preparacion para trabajar la cola activa.</p>
                    <?php else : ?>
                        <strong>No hay pedidos en preparacion.</strong>
                        <p>Los pedidos listos para despacho seguiran apareciendo en la vista.</p>
                    <?php endif; ?>
                </div>
            </section>

            <section class="rkm-card rkm-warehouse-panel">
                <div class="rkm-warehouse-panel__header">
                    <div>
                        <h2>Pedidos de almacen</h2>
                        <p>Visualizacion operativa para inventario, observaciones y preparado de pedidos.</p>
                    </div>
                </div>

                <div class="rkm-warehouse-filters" data-rkm-warehouse-filters>
                    <button type="button" class="rkm-warehouse-filter is-active" data-rkm-warehouse-filter="warehouse">
                        <span>En preparacion</span>
                        <strong data-rkm-warehouse-filter-count="warehouse">0</strong>
                    </button>
                    <button type="button" class="rkm-warehouse-filter" data-rkm-warehouse-filter="ready">
                        <span>Listos</span>
                        <strong data-rkm-warehouse-filter-count="ready">0</strong>
                    </button>
                    <button type="button" class="rkm-warehouse-filter" data-rkm-warehouse-filter="dispatched">
                        <span>Despachados</span>
                        <strong data-rkm-warehouse-filter-count="dispatched">0</strong>
                    </button>
                    <button type="button" class="rkm-warehouse-filter" data-rkm-warehouse-filter="completed">
                        <span>Entregados</span>
                        <strong data-rkm-warehouse-filter-count="completed">0</strong>
                    </button>
                </div>

                <div class="rkm-warehouse-empty" data-rkm-warehouse-empty hidden>
                    No hay pedidos para este filtro.
                </div>

                <?php if (empty($orders)) : ?>
                    <div class="rkm-warehouse-state-empty">
                        <strong>No hay pedidos en almacen</strong>
                        <p>Cuando existan pedidos en estado En preparacion o Listo apareceran aqui.</p>
                    </div>
                <?php else : ?>
                    <div class="rkm-warehouse-table-wrap">
                        <table class="rkm-warehouse-table">
                            <thead>
                                <tr>
                                    <th>Pedido</th>
                                    <th>Cliente</th>
                                    <th>Fecha</th>
                                    <th>Productos</th>
                                    <th>Estado</th>
                                    <th>Accion</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order) : ?>
                                    <tr
                                        data-rkm-warehouse-row
                                        data-order-id="<?php echo esc_attr((string) $order['id']); ?>"
                                        data-rkm-order-status="<?php echo esc_attr($order['status']); ?>"
                                    >
                                        <td data-label="Pedido">
                                            <strong>#<?php echo esc_html($order['number']); ?></strong>
                                        </td>
                                        <td data-label="Cliente"><?php echo esc_html($order['customer_name']); ?></td>
                                        <td data-label="Fecha"><?php echo esc_html($order['date']); ?></td>
                                        <td data-label="Productos"><?php echo esc_html($order['items_summary']); ?></td>
                                        <td data-label="Estado">
                                            <span
                                                class="rkm-warehouse-status rkm-warehouse-status--<?php echo esc_attr(sanitize_html_class($order['status'])); ?>"
                                                data-rkm-warehouse-status-badge
                                            >
                                                <?php echo esc_html($order['status_label']); ?>
                                            </span>
                                        </td>
                                        <td data-label="Accion">
                                            <button
                                                type="button"
                                                class="rkm-warehouse__btn rkm-warehouse__btn--secondary"
                                                data-rkm-warehouse-detail
                                                data-order-id="<?php echo esc_attr((string) $order['id']); ?>"
                                            >
                                                Ver detalle
                                            </button>
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

    <div class="rkm-warehouse-modal" id="rkmWarehouseOrderModal" aria-hidden="true">
        <div class="rkm-warehouse-modal__overlay" data-rkm-warehouse-close></div>
        <div class="rkm-warehouse-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="rkmWarehouseModalTitle">
            <button type="button" class="rkm-warehouse-modal__close" data-rkm-warehouse-close aria-label="Cerrar detalle">&times;</button>

            <div class="rkm-warehouse-modal__header">
                <div>
                    <span class="rkm-warehouse-modal__eyebrow">Detalle de almacen</span>
                    <h2 id="rkmWarehouseModalTitle">Pedido</h2>
                    <p id="rkmWarehouseModalMeta"></p>
                </div>
                <span id="rkmWarehouseModalStatus" class="rkm-warehouse-status"></span>
            </div>

            <div class="rkm-warehouse-modal__body">
                <section class="rkm-warehouse-modal__grid">
                    <div class="rkm-warehouse-modal__box">
                        <span>Cliente</span>
                        <strong id="rkmWarehouseModalCustomer"></strong>
                        <small id="rkmWarehouseModalCustomerMeta"></small>
                    </div>
                    <div class="rkm-warehouse-modal__box">
                        <span>Observaciones de almacen</span>
                        <small id="rkmWarehouseModalCurrentNote"></small>
                    </div>
                </section>

                <section class="rkm-warehouse-modal__section">
                    <div class="rkm-warehouse-modal__section-head">
                        <h3>Checklist de picking</h3>
                    </div>
                    <div class="rkm-warehouse-modal__items" id="rkmWarehouseModalItems"></div>
                </section>

                <section class="rkm-warehouse-modal__section">
                    <div class="rkm-warehouse-modal__section-head">
                        <h3>Evidencia fotografica</h3>
                    </div>
                    <div class="rkm-warehouse-evidence" id="rkmWarehouseEvidence"></div>
                </section>

                <section class="rkm-warehouse-modal__section">
                    <div class="rkm-warehouse-modal__section-head">
                        <h3>Cierre operativo</h3>
                    </div>
                    <div class="rkm-warehouse-closure" id="rkmWarehouseClosure"></div>
                </section>

                <section class="rkm-warehouse-modal__section">
                    <div class="rkm-warehouse-modal__section-head">
                        <h3>Historial operativo</h3>
                    </div>
                    <div class="rkm-warehouse-modal__notes" id="rkmWarehouseModalNotes"></div>
                </section>

                <section class="rkm-warehouse-modal__section">
                    <div class="rkm-warehouse-modal__section-head">
                        <h3>Observacion de almacen</h3>
                    </div>
                    <label class="rkm-warehouse-modal__note-form">
                        <span>Agrega un comentario operativo</span>
                        <textarea id="rkmWarehouseModalNoteInput" rows="3" placeholder="Ej: Falta filtro de aire, se dejo pendiente de reposicion."></textarea>
                    </label>
                </section>

                <?php if ($can_manage_warehouse) : ?>
                    <section class="rkm-warehouse-modal__actions">
                        <span class="rkm-warehouse-progress-status" id="rkmWarehousePickingSaveStatus"></span>
                        <button type="button" class="rkm-warehouse__btn rkm-warehouse__btn--secondary" id="rkmWarehouseModalSaveProgressBtn">
                            Guardar avance
                        </button>
                        <button type="button" class="rkm-warehouse__btn rkm-warehouse__btn--secondary" id="rkmWarehouseModalNoteBtn">
                            Agregar observacion
                        </button>
                        <button type="button" class="rkm-warehouse__btn rkm-warehouse__btn--primary" id="rkmWarehouseModalReadyBtn">
                            Marcar como preparado
                        </button>
                        <button type="button" class="rkm-warehouse__btn rkm-warehouse__btn--primary" id="rkmWarehouseModalDispatchBtn">
                            Marcar como despachado
                        </button>
                        <button type="button" class="rkm-warehouse__btn rkm-warehouse__btn--primary" id="rkmWarehouseModalDeliverBtn">
                            Confirmar entrega
                        </button>
                    </section>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    window.rkmWarehouseOrders = {
        ajax_url: <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>,
        nonce: <?php echo wp_json_encode(wp_create_nonce(RKM_Warehouse::get_nonce_action())); ?>,
        can_manage: <?php echo $can_manage_warehouse ? 'true' : 'false'; ?>,
        warehouse_status: 'rkm-warehouse',
        ready_status: 'rkm-ready',
        dispatched_status: 'rkm-dispatched',
        completed_status: 'completed',
        orders: <?php echo wp_json_encode($orders); ?>
    };
</script>
