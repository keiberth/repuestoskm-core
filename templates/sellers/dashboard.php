<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="rkm-app">
    <div class="rkm-container">
        <?php include plugin_dir_path(__FILE__) . '../partials/private-header.php'; ?>

        <div class="rkm-page-header">
            <h1><?php echo esc_html($page_title); ?></h1>
            <p><?php echo esc_html($page_subtitle); ?></p>
        </div>

        <?php include plugin_dir_path(__FILE__) . '../partials/subnav.php'; ?>

        <section class="rkm-card rkm-sellers-dashboard">
            <div class="rkm-sellers-dashboard__hero">
                <span class="rkm-sellers-dashboard__eyebrow">Panel comercial</span>
                <h2 class="rkm-sellers-dashboard__title">Resumen operativo del vendedor</h2>
                <p class="rkm-sellers-dashboard__text">
                    Esta vista ahora usa la cartera asignada al vendedor para mostrar solo clientes y pedidos
                    relacionados, sin depender de datos globales del sistema.
                </p>
            </div>

            <div class="rkm-sellers-dashboard__grid">
                <?php foreach ($data['seller_metrics'] as $metric) : ?>
                    <article class="rkm-sellers-dashboard__item rkm-sellers-dashboard__item--<?php echo esc_attr($metric['tone']); ?>">
                        <span class="rkm-sellers-dashboard__item-label"><?php echo esc_html($metric['label']); ?></span>
                        <strong class="rkm-sellers-dashboard__item-value"><?php echo esc_html((string) $metric['value']); ?></strong>
                        <p class="rkm-sellers-dashboard__item-meta"><?php echo esc_html($metric['meta']); ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <div class="rkm-sellers-dashboard__layout">
            <section class="rkm-card rkm-sellers-panel">
                <div class="rkm-sellers-panel__header">
                    <h3>Acciones rapidas</h3>
                    <p>Atajos directos a los flujos comerciales ya existentes.</p>
                </div>

                <div class="rkm-sellers-actions">
                    <?php foreach ($data['seller_quick_actions'] as $action) : ?>
                        <a class="rkm-sellers-action-card" href="<?php echo esc_url($action['url']); ?>">
                            <strong><?php echo esc_html($action['label']); ?></strong>
                            <span><?php echo esc_html($action['description']); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="rkm-card rkm-sellers-panel">
                <div class="rkm-sellers-panel__header">
                    <h3>Clientes</h3>
                    <p>Clientes asignados al vendedor actual desde el modulo de asignaciones.</p>
                </div>

                <?php if (!empty($data['seller_recent_customers'])) : ?>
                    <div class="rkm-sellers-list">
                        <?php foreach ($data['seller_recent_customers'] as $customer) : ?>
                            <article class="rkm-sellers-list__item">
                                <strong><?php echo esc_html($customer['name']); ?></strong>
                                <span><?php echo esc_html($customer['email']); ?></span>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <div class="rkm-sellers-empty-state">
                        <p><?php echo esc_html($data['seller_empty_message'] ?? 'No tenes clientes asignados'); ?></p>
                    </div>
                <?php endif; ?>
            </section>
        </div>

        <section class="rkm-card rkm-sellers-panel rkm-sellers-panel--orders">
            <div class="rkm-sellers-panel__header">
                <h3>Pedidos recientes</h3>
                <p>Solo se muestran pedidos relacionados con los clientes asignados a esta cartera.</p>
            </div>

            <?php if (!empty($data['seller_recent_orders'])) : ?>
                <div class="rkm-sellers-orders-table-wrap">
                    <table class="rkm-sellers-orders-table">
                        <thead>
                            <tr>
                                <th>Pedido</th>
                                <th>Cliente</th>
                                <th>Estado</th>
                                <th>Total</th>
                                <th>Fecha</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data['seller_recent_orders'] as $order) : ?>
                                <tr>
                                    <td data-label="Pedido">#<?php echo esc_html($order['number']); ?></td>
                                    <td data-label="Cliente"><?php echo esc_html($order['customer_name']); ?></td>
                                    <td data-label="Estado">
                                        <span class="rkm-sellers-status rkm-sellers-status--<?php echo esc_attr($order['status_slug']); ?>">
                                            <?php echo esc_html($order['status']); ?>
                                        </span>
                                    </td>
                                    <td data-label="Total"><?php echo esc_html($order['total']); ?></td>
                                    <td data-label="Fecha"><?php echo esc_html($order['date']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else : ?>
                <div class="rkm-sellers-empty-state">
                    <p>
                        <?php echo !empty($data['seller_has_assigned_customers'])
                            ? esc_html('No hay pedidos recientes de tu cartera.')
                            : esc_html($data['seller_empty_message'] ?? 'No tenes clientes asignados'); ?>
                    </p>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>
