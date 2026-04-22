<?php
if (!defined('ABSPATH')) {
    exit;
}

$page_title = isset($data['page_title']) ? $data['page_title'] : 'Asignacion de clientes a vendedores';
$page_subtitle = isset($data['page_subtitle']) ? $data['page_subtitle'] : '';
$notice = isset($data['assignments_notice']) ? $data['assignments_notice'] : null;
$rows = isset($data['assignments_rows']) && is_array($data['assignments_rows']) ? $data['assignments_rows'] : [];
$vendors = isset($data['assignments_vendors']) && is_array($data['assignments_vendors']) ? $data['assignments_vendors'] : [];
$summary = isset($data['assignments_summary']) && is_array($data['assignments_summary']) ? $data['assignments_summary'] : [];
?>

<div class="rkm-app">
    <div class="rkm-container rkm-admin-assignments-page">
        <?php include plugin_dir_path(__FILE__) . '../partials/private-header.php'; ?>

        <div class="rkm-page-header rkm-admin-assignments-page__header">
            <div>
                <h1><?php echo esc_html($page_title); ?></h1>
                <p><?php echo esc_html($page_subtitle); ?></p>
            </div>

            <div class="rkm-admin-assignments-page__actions">
                <a class="rkm-admin-assignments-page__back" href="<?php echo esc_url(home_url('/mi-cuenta/panel/')); ?>">
                    Volver al panel admin
                </a>
            </div>
        </div>

        <section class="rkm-card rkm-admin-assignments-hero">
            <div class="rkm-admin-assignments-hero__copy">
                <span class="rkm-admin-assignments-hero__eyebrow">Cartera comercial</span>
                <h2>Base simple para relacionar clientes y vendedores</h2>
                <p>
                    Esta pantalla guarda la asignacion directamente sobre cada cliente y deja lista la base para
                    futuras vistas filtradas por vendedor, clientes asociados y pedidos de su cartera.
                </p>
            </div>
        </section>

        <?php if (!empty($summary)) : ?>
            <section class="rkm-admin-assignments-metrics">
                <?php foreach ($summary as $metric) : ?>
                    <article class="rkm-admin-assignments-metric">
                        <span><?php echo esc_html($metric['label']); ?></span>
                        <strong><?php echo esc_html((string) $metric['value']); ?></strong>
                        <p><?php echo esc_html($metric['meta']); ?></p>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>

        <?php if (!empty($notice['message'])) : ?>
            <div class="rkm-admin-assignments-notice rkm-admin-assignments-notice--<?php echo esc_attr($notice['type']); ?>">
                <p><?php echo esc_html($notice['message']); ?></p>
            </div>
        <?php endif; ?>

        <section class="rkm-card rkm-admin-assignments-panel">
            <div class="rkm-admin-assignments-panel__header">
                <h3>Clientes y vendedor asignado</h3>
                <p>Cada fila guarda la asignacion del cliente al vendedor seleccionado.</p>
            </div>

            <div class="rkm-admin-assignments-table-wrap">
                <table class="rkm-admin-assignments-table">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Email</th>
                            <th>Vendedor</th>
                            <th>Estado</th>
                            <th>Accion</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($rows)) : ?>
                            <?php foreach ($rows as $row) : ?>
                                <tr>
                                    <td data-label="Cliente">
                                        <strong><?php echo esc_html($row['name']); ?></strong>
                                        <span>@<?php echo esc_html($row['username']); ?></span>
                                    </td>
                                    <td data-label="Email"><?php echo esc_html($row['email']); ?></td>
                                    <td data-label="Vendedor">
                                        <form class="rkm-admin-assignments-form" method="post" data-rkm-assignment-form>
                                            <input type="hidden" name="rkm_assignments_action" value="save_assignment">
                                            <input type="hidden" name="customer_id" value="<?php echo esc_attr((string) $row['id']); ?>">
                                            <?php wp_nonce_field('rkm_assignments_update', 'rkm_assignments_nonce'); ?>

                                            <div class="rkm-admin-assignments-form__controls">
                                                <select name="assigned_vendor_id">
                                                    <?php foreach ($vendors as $vendor) : ?>
                                                        <option value="<?php echo esc_attr((string) $vendor['id']); ?>" <?php selected((int) $row['assigned_vendor_id'], (int) $vendor['id']); ?>>
                                                            <?php echo esc_html($vendor['label']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit" class="rkm-admin-assignments-form__submit" data-rkm-assignment-submit data-loading-label="Guardando...">
                                                    Guardar
                                                </button>
                                            </div>

                                            <small class="rkm-admin-assignments-form__current">
                                                Actual: <?php echo esc_html($row['assigned_vendor_label']); ?>
                                            </small>
                                        </form>
                                    </td>
                                    <td data-label="Estado">
                                        <span class="rkm-admin-assignments-status rkm-admin-assignments-status--<?php echo esc_attr(strtolower($row['status'])); ?>">
                                            <?php echo esc_html($row['status']); ?>
                                        </span>
                                    </td>
                                    <td data-label="Accion">
                                        <span class="rkm-admin-assignments-action-copy">Guardar asignacion</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="5" class="rkm-admin-assignments-table__empty">
                                    No hay clientes disponibles para asignar en esta vista.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
