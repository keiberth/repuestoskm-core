<?php
if (!defined('ABSPATH')) {
    exit;
}

$page_title = $data['page_title'] ?? 'Pedidos operativos';
$page_subtitle = $data['page_subtitle'] ?? '';
$orders = isset($data['operational_orders']) && is_array($data['operational_orders']) ? $data['operational_orders'] : [];
$current = $data['current_section'] ?? 'pedidos-operativos';
$can_confirm_orders = !empty($data['can_confirm_orders']);
$can_edit_orders = !empty($data['can_edit_orders']);
$can_close_logistics = !empty($data['can_close_logistics']);
$confirmable_statuses = class_exists('RKM_Operational_Orders') ? RKM_Operational_Orders::get_confirmable_statuses() : ['rkm-review', 'pending', 'en-revision'];
$editable_statuses = class_exists('RKM_Operational_Orders') ? RKM_Operational_Orders::get_editable_statuses() : ['rkm-review', 'pending', 'en-revision'];
$payment_terms = isset($data['payment_terms']) && is_array($data['payment_terms']) ? $data['payment_terms'] : [];
$payment_methods = isset($data['payment_methods']) && is_array($data['payment_methods']) ? $data['payment_methods'] : [];
$payment_terms_settings = isset($data['payment_terms_settings']) && is_array($data['payment_terms_settings']) ? $data['payment_terms_settings'] : [];
$review_statuses = class_exists('RKM_Operational_Orders')
    ? RKM_Operational_Orders::get_review_statuses()
    : ['rkm-review', 'pending', 'en-revision'];
$pending_review_count = count(array_filter($orders, static function ($order) use ($review_statuses) {
    return in_array(($order['status'] ?? ''), $review_statuses, true);
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
            <section class="rkm-card rkm-admin-orders-review-card">
                <div class="rkm-admin-orders-review-card__header">
                    <div>
                        <h2>Pedidos pendientes de revision</h2>
                        <p>Estos pedidos necesitan validacion comercial antes de enviarse a almacen.</p>
                    </div>
                    <span class="rkm-admin-orders-review-card__count" data-rkm-review-count>
                        <?php echo esc_html((string) $pending_review_count); ?>
                    </span>
                </div>

                <div class="rkm-admin-orders-review-card__body">
                    <?php if ($pending_review_count > 0) : ?>
                        <strong><?php echo esc_html('Tenes ' . (int) $pending_review_count . ' pedidos pendientes por revisar.'); ?></strong>
                        <p>Filtralo por estado En revision para gestionarlos.</p>
                    <?php else : ?>
                        <strong>No hay pedidos pendientes por revisar.</strong>
                        <p>Revisa el listado general cuando ingresen nuevos pedidos.</p>
                    <?php endif; ?>
                </div>
            </section>

            <section class="rkm-card rkm-admin-orders-panel">
                <div class="rkm-admin-orders-panel__header">
                    <div>
                        <h2>Listado operativo</h2>
                        <p>Pedidos en estados RKM con detalle operativo y confirmacion administrativa controlada.</p>
                    </div>
                </div>

                <div class="rkm-admin-orders-filters rkm-admin-orders__filters" data-rkm-order-filters>
                    <button type="button" class="rkm-admin-orders-filter rkm-admin-orders__filter is-active" data-rkm-order-filter="all">
                        <span>Todos</span>
                        <strong data-rkm-filter-count="all">0</strong>
                    </button>
                    <button type="button" class="rkm-admin-orders-filter rkm-admin-orders__filter" data-rkm-order-filter="pending">
                        <span>Pendientes</span>
                        <strong data-rkm-filter-count="pending">0</strong>
                    </button>
                    <button type="button" class="rkm-admin-orders-filter rkm-admin-orders__filter" data-rkm-order-filter="confirmed">
                        <span>Confirmados</span>
                        <strong data-rkm-filter-count="confirmed">0</strong>
                    </button>
                    <button type="button" class="rkm-admin-orders-filter rkm-admin-orders__filter" data-rkm-order-filter="warehouse">
                        <span>En almacén</span>
                        <strong data-rkm-filter-count="warehouse">0</strong>
                    </button>
                    <button type="button" class="rkm-admin-orders-filter rkm-admin-orders__filter" data-rkm-order-filter="ready">
                        <span>Listos</span>
                        <strong data-rkm-filter-count="ready">0</strong>
                    </button>
                    <button type="button" class="rkm-admin-orders-filter rkm-admin-orders__filter" data-rkm-order-filter="dispatched">
                        <span>Despachados</span>
                        <strong data-rkm-filter-count="dispatched">0</strong>
                    </button>
                    <button type="button" class="rkm-admin-orders-filter rkm-admin-orders__filter" data-rkm-order-filter="completed">
                        <span>Entregados</span>
                        <strong data-rkm-filter-count="completed">0</strong>
                    </button>
                </div>

                <div class="rkm-admin-orders-filter-empty" data-rkm-order-filter-empty hidden>
                    No hay pedidos para este filtro.
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
                                    <tr data-rkm-operational-order-row data-order-id="<?php echo esc_attr((string) $order['id']); ?>" data-rkm-order-status="<?php echo esc_attr($order['status']); ?>">
                                        <td data-label="Pedido">
                                            <strong>#<?php echo esc_html($order['number']); ?></strong>
                                        </td>
                                        <td data-label="Cliente"><?php echo esc_html($order['customer_name']); ?></td>
                                        <td data-label="Fecha"><?php echo esc_html($order['date']); ?></td>
                                        <td data-label="Total" data-rkm-order-row-total><?php echo esc_html($order['total']); ?></td>
                                        <td data-label="Estado">
                                            <span
                                                class="rkm-admin-orders-status rkm-admin-orders-status--<?php echo esc_attr(sanitize_html_class($order['status'])); ?>"
                                                data-rkm-order-status-badge
                                            >
                                                <?php echo esc_html($order['status_label']); ?>
                                            </span>
                                        </td>
                                        <td data-label="Pago" data-rkm-order-row-payment>
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
                                                        class="rkm-admin-orders__btn rkm-admin-orders__btn--warehouse rkm-admin-orders-warehouse-btn"
                                                        data-rkm-send-operational-order-warehouse
                                                        data-order-id="<?php echo esc_attr((string) $order['id']); ?>"
                                                    >
                                                        Enviar a almacen
                                                    </button>
                                                <?php endif; ?>

                                                <?php if ($can_close_logistics && ($order['status'] ?? '') === 'rkm-ready') : ?>
                                                    <button
                                                        type="button"
                                                        class="rkm-admin-orders__btn rkm-admin-orders__btn--dispatch rkm-admin-orders-dispatch-btn"
                                                        data-rkm-mark-operational-dispatched
                                                        data-order-id="<?php echo esc_attr((string) $order['id']); ?>"
                                                    >
                                                        Marcar despachado
                                                    </button>
                                                <?php endif; ?>

                                                <?php if ($can_close_logistics && ($order['status'] ?? '') === 'rkm-dispatched') : ?>
                                                    <button
                                                        type="button"
                                                        class="rkm-admin-orders__btn rkm-admin-orders__btn--deliver rkm-admin-orders-deliver-btn"
                                                        data-rkm-mark-operational-delivered
                                                        data-order-id="<?php echo esc_attr((string) $order['id']); ?>"
                                                    >
                                                        Confirmar entrega
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
                    <div class="rkm-admin-order-modal__box rkm-admin-order-modal__box--seller">
                        <span>Vendedor asignado</span>
                        <strong id="rkmOperationalOrderSeller"></strong>
                        <small id="rkmOperationalOrderSellerMeta"></small>
                    </div>
                    <div class="rkm-admin-order-modal__box rkm-admin-order-modal__box--payment">
                        <span>Pago</span>
                        <div id="rkmOperationalOrderPaymentReadonly" class="rkm-admin-order-modal__payment-summary">
                            <strong id="rkmOperationalOrderPaymentTerm"></strong>
                            <div class="rkm-admin-order-modal__payment-summary-lines" id="rkmOperationalOrderPaymentSummaryLines"></div>
                        </div>

                        <?php if ($can_edit_orders) : ?>
                        <label class="rkm-admin-order-modal__payment-toggle" id="rkmOperationalOrderPaymentToggleWrap" hidden>
                            <input type="checkbox" id="rkmOperationalOrderPaymentEditToggle">
                            <span>Editar condicion de pago</span>
                        </label>

                        <div class="rkm-admin-order-modal__payment-form rkm-admin-order-modal__payment-edit-fields" id="rkmOperationalOrderEditPanel" hidden aria-hidden="true">
                            <label>
                                <span>Condicion</span>
                                <select id="rkmOperationalOrderPaymentTermInput">
                                    <?php foreach ($payment_terms as $term) : ?>
                                        <option value="<?php echo esc_attr($term['key']); ?>">
                                            <?php echo esc_html($term['label']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <label>
                                <span>Forma de pago</span>
                                <select id="rkmOperationalOrderPaymentMethodInput">
                                    <option value="">Sin forma de pago</option>
                                    <?php foreach ($payment_methods as $method) : ?>
                                        <option value="<?php echo esc_attr($method['id']); ?>">
                                            <?php echo esc_html($method['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <label>
                                <span>Monto inicial</span>
                                <input type="number" id="rkmOperationalOrderUpfrontInput" min="0" step="0.01" inputmode="decimal">
                            </label>

                            <label>
                                <span>Saldo a credito</span>
                                <input type="text" id="rkmOperationalOrderCreditBalanceInput" readonly>
                            </label>

                            <label class="rkm-admin-order-modal__payment-note">
                                <span>Nota de pago</span>
                                <textarea id="rkmOperationalOrderPaymentNoteInput" rows="2"></textarea>
                            </label>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="rkm-admin-order-modal__box">
                        <span>Total</span>
                        <strong id="rkmOperationalOrderTotal"></strong>
                        <small id="rkmOperationalOrderTotalHint" hidden></small>
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
                        <h3>Estado logistico</h3>
                    </div>
                    <div class="rkm-admin-order-modal__logistics" id="rkmOperationalOrderLogistics"></div>
                </section>

                <section class="rkm-admin-order-modal__section">
                    <div class="rkm-admin-order-modal__section-head">
                        <h3>Evidencia de preparacion</h3>
                    </div>
                    <div class="rkm-admin-order-modal__warehouse-evidence" id="rkmOperationalOrderWarehouseEvidence"></div>
                </section>

                <section class="rkm-admin-order-modal__section">
                    <div class="rkm-admin-order-modal__section-head">
                        <h3>Incidencias de almacén</h3>
                    </div>
                    <div class="rkm-admin-order-modal__warehouse-incidents" id="rkmOperationalOrderWarehouseIncidents"></div>
                </section>

                <section class="rkm-admin-order-modal__section">
                    <div class="rkm-admin-order-modal__section-head">
                        <h3>Historial operativo</h3>
                    </div>
                    <div class="rkm-admin-order-modal__notes" id="rkmOperationalOrderNotes"></div>
                </section>

                <?php if ($can_confirm_orders || $can_close_logistics) : ?>
                    <section class="rkm-admin-order-modal__actions" id="rkmOperationalOrderActions">
                        <?php if ($can_edit_orders) : ?>
                            <button type="button" class="rkm-admin-orders__btn rkm-admin-orders__btn--secondary rkm-admin-orders-save-btn" id="rkmOperationalOrderSaveBtn">
                                Guardar cambios
                            </button>
                        <?php endif; ?>
                        <?php if ($can_confirm_orders) : ?>
                            <button type="button" class="rkm-admin-orders__btn rkm-admin-orders__btn--primary rkm-admin-orders-confirm-btn" id="rkmOperationalOrderConfirmBtn">
                                Confirmar pedido
                            </button>
                            <button type="button" class="rkm-admin-orders__btn rkm-admin-orders__btn--warehouse rkm-admin-orders-warehouse-btn" id="rkmOperationalOrderWarehouseBtn">
                                Enviar a almacen
                            </button>
                        <?php endif; ?>
                        <?php if ($can_close_logistics) : ?>
                            <button type="button" class="rkm-admin-orders__btn rkm-admin-orders__btn--dispatch rkm-admin-orders-dispatch-btn" id="rkmOperationalOrderDispatchBtn">
                                Marcar despachado
                            </button>
                            <button type="button" class="rkm-admin-orders__btn rkm-admin-orders__btn--deliver rkm-admin-orders-deliver-btn" id="rkmOperationalOrderDeliverBtn">
                                Confirmar entrega
                            </button>
                        <?php endif; ?>
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
        can_edit: <?php echo $can_edit_orders ? 'true' : 'false'; ?>,
        can_close_logistics: <?php echo $can_close_logistics ? 'true' : 'false'; ?>,
        can_resolve_incidents: <?php echo (class_exists('RKM_Operational_Orders') && RKM_Operational_Orders::can_resolve_warehouse_incidents()) ? 'true' : 'false'; ?>,
        review_status: 'rkm-review',
        confirmable_statuses: <?php echo wp_json_encode($confirmable_statuses); ?>,
        editable_statuses: <?php echo wp_json_encode($editable_statuses); ?>,
        confirmed_status: 'rkm-confirmed',
        warehouse_status: 'rkm-warehouse',
        ready_status: 'rkm-ready',
        dispatched_status: 'rkm-dispatched',
        completed_status: 'completed',
        currency_symbol: <?php echo wp_json_encode(function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '$'); ?>,
        currency_decimals: <?php echo wp_json_encode(function_exists('wc_get_price_decimals') ? wc_get_price_decimals() : 2); ?>,
        cash_discount_percent: <?php echo wp_json_encode(isset($payment_terms_settings['cash_discount_percent']) ? (float) $payment_terms_settings['cash_discount_percent'] : 0); ?>,
        payment_terms: <?php echo wp_json_encode($payment_terms); ?>,
        payment_methods: <?php echo wp_json_encode($payment_methods); ?>,
        orders: <?php echo wp_json_encode($orders); ?>
    };
</script>
