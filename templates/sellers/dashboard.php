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
                <h2 class="rkm-sellers-dashboard__title">Módulo de vendedores en preparación</h2>
                <p class="rkm-sellers-dashboard__text">
                    Esta sección ya quedó reservada para el futuro panel comercial. La base de acceso y navegación
                    está lista para sumar métricas, clientes, pedidos y herramientas específicas para vendedores.
                </p>
            </div>

            <div class="rkm-sellers-dashboard__grid">
                <div class="rkm-sellers-dashboard__item">
                    <span class="rkm-sellers-dashboard__item-label">Estado</span>
                    <strong class="rkm-sellers-dashboard__item-value">Base estructural lista</strong>
                </div>

                <div class="rkm-sellers-dashboard__item">
                    <span class="rkm-sellers-dashboard__item-label">Acceso</span>
                    <strong class="rkm-sellers-dashboard__item-value">Solo vendedor y admin</strong>
                </div>

                <div class="rkm-sellers-dashboard__item">
                    <span class="rkm-sellers-dashboard__item-label">Próximo paso</span>
                    <strong class="rkm-sellers-dashboard__item-value">Definir herramientas del módulo</strong>
                </div>
            </div>
        </div>
    </div>
</div>
