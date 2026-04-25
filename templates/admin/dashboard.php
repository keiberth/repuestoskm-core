<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="rkm-app rkm-module-app">
    <div class="rkm-container rkm-admin-dashboard-page">
        <?php include plugin_dir_path(__FILE__) . '../partials/private-header.php'; ?>

        <div class="rkm-page-header rkm-admin-dashboard__header">
            <h1><?php echo esc_html($page_title); ?></h1>
            <p><?php echo esc_html($page_subtitle); ?></p>
        </div>

        <div class="rkm-module-shell">
        <section class="rkm-admin-dashboard__hero">
            <div class="rkm-admin-dashboard__hero-copy">
                <span class="rkm-admin-dashboard__eyebrow">Vision ejecutiva</span>
                <h2>Panel base para decisiones administrativas</h2>
                <p>
                    Este espacio deja atras los indicadores de cliente y pasa a una lectura operativa del negocio,
                    con metricas ejecutivas, accesos administrativos y placeholders listos para analitica real.
                </p>
            </div>
        </section>

        <section class="rkm-admin-dashboard__metrics">
            <?php foreach ($data['admin_metrics'] as $metric) : ?>
                <article class="rkm-admin-metric-card rkm-admin-metric-card--<?php echo esc_attr($metric['tone']); ?>">
                    <span class="rkm-admin-metric-card__label"><?php echo esc_html($metric['label']); ?></span>
                    <strong class="rkm-admin-metric-card__value"><?php echo esc_html((string) $metric['value']); ?></strong>
                    <p class="rkm-admin-metric-card__meta"><?php echo esc_html($metric['meta']); ?></p>
                </article>
            <?php endforeach; ?>
        </section>

        <div class="rkm-admin-dashboard__grid">
            <section class="rkm-card rkm-admin-panel">
                <div class="rkm-admin-panel__header">
                    <h3>Accesos administrativos</h3>
                    <p>Entradas rapidas para operar sin mezclar flujos de cliente.</p>
                </div>

                <div class="rkm-admin-actions">
                    <?php foreach ($data['admin_quick_actions'] as $action) : ?>
                        <a class="rkm-admin-action-card" href="<?php echo esc_url($action['url']); ?>">
                            <strong><?php echo esc_html($action['label']); ?></strong>
                            <span><?php echo esc_html($action['description']); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="rkm-card rkm-admin-panel">
                <div class="rkm-admin-panel__header">
                    <h3>Base futura</h3>
                    <p>Capas listas para crecer hacia analisis ejecutivo del negocio.</p>
                </div>

                <ul class="rkm-admin-list">
                    <?php foreach ($data['admin_future_blocks'] as $item) : ?>
                        <li><?php echo esc_html($item); ?></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        </div>

        <section class="rkm-card rkm-admin-panel rkm-admin-panel--notes">
            <div class="rkm-admin-panel__header">
                <h3>Estado de la implementacion</h3>
                <p>Resumen de la base administrativa que queda lista para evolucionar.</p>
            </div>

            <div class="rkm-admin-notes">
                <?php foreach ($data['admin_operational_notes'] as $note) : ?>
                    <div class="rkm-admin-note">
                        <p><?php echo esc_html($note); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        </div>
    </div>
</div>
