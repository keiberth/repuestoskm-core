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

        <div class="rkm-card rkm-sellers-dashboard">
            <div class="rkm-sellers-dashboard__hero">
                <span class="rkm-sellers-dashboard__eyebrow">Acceso vendedor</span>
                <h2 class="rkm-sellers-dashboard__title">Base comercial lista para operar</h2>
                <p class="rkm-sellers-dashboard__text">
                    Este dashboard ya separa la experiencia del vendedor del resto del sistema y deja una primera capa
                    util para seguimiento comercial, accesos rapidos y futuras asignaciones reales.
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
        </div>

        <div class="rkm-sellers-dashboard__layout">
            <section class="rkm-card rkm-sellers-panel">
                <div class="rkm-sellers-panel__header">
                    <h3>Accesos rapidos</h3>
                    <p>Atajos operativos del vendedor sobre la base actual del sistema.</p>
                </div>

                <div class="rkm-sellers-actions">
                    <?php foreach ($data['seller_quick_actions'] as $action) : ?>
                        <?php if ($action['kind'] === 'placeholder') : ?>
                            <button type="button" class="rkm-sellers-action-card rkm-sellers-action-card--button" data-rkm-sellers-action="<?php echo esc_attr($action['action']); ?>">
                                <strong><?php echo esc_html($action['label']); ?></strong>
                                <span><?php echo esc_html($action['description']); ?></span>
                            </button>
                        <?php else : ?>
                            <a class="rkm-sellers-action-card" href="<?php echo esc_url($action['url']); ?>">
                                <strong><?php echo esc_html($action['label']); ?></strong>
                                <span><?php echo esc_html($action['description']); ?></span>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="rkm-card rkm-sellers-panel">
                <div class="rkm-sellers-panel__header">
                    <h3>Estado del modulo</h3>
                    <p>Checklist inicial para seguir construyendo el dashboard comercial.</p>
                </div>

                <div class="rkm-sellers-stack">
                    <?php foreach ($data['seller_pipeline'] as $item) : ?>
                        <article class="rkm-sellers-stack__item">
                            <strong><?php echo esc_html($item['title']); ?></strong>
                            <p><?php echo esc_html($item['description']); ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>

        <section class="rkm-card rkm-sellers-note" id="rkm-sellers-note" data-rkm-sellers-note hidden>
            <div class="rkm-sellers-note__content">
                <span class="rkm-sellers-note__eyebrow">Placeholder preparado</span>
                <h3 data-rkm-sellers-note-title><?php echo esc_html($data['seller_placeholder_notice']['title']); ?></h3>
                <p data-rkm-sellers-note-message><?php echo esc_html($data['seller_placeholder_notice']['message']); ?></p>
            </div>

            <button type="button" class="rkm-btn-secondary rkm-btn-sm" data-rkm-sellers-close>Cerrar</button>
        </section>

        <div class="rkm-sellers-disclaimer">
            <p>
                Las metricas actuales usan una base global compatible con WooCommerce mientras se define la asignacion
                real por vendedor. La separacion de vistas, estilos y comportamiento ya quedo lista para esa conexion.
            </p>
        </div>
    </div>
</div>
