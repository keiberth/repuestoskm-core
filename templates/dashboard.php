<?php
if (!defined('ABSPATH')) {
    exit;
}

$panel_url = home_url('/mi-cuenta/panel/');
$current_section = 'panel';
?>

<div class="rkm-app rkm-module-app">
    <div class="rkm-container rkm-dashboard-wrapper">
        <?php include plugin_dir_path(__FILE__) . 'partials/private-header.php'; ?>

        <div class="rkm-dashboard-header">
            <div>
                <h1 class="rkm-title">Panel de cuenta</h1>
                <p class="rkm-subtitle">Bienvenido, <?php echo esc_html(wp_get_current_user()->display_name); ?></p>
            </div>
        </div>

        <div class="rkm-module-shell">
        <div class="rkm-dashboard">
            <aside class="rkm-sidebar-card">
                <div class="rkm-sidebar-card__header">
                    <h3>Mi cuenta</h3>
                    <p>Accesos rapidos</p>
                </div>

                <ul class="rkm-sidebar-menu">
                    <li>
                        <a href="<?php echo esc_url($panel_url); ?>" class="rkm-sidebar-link <?php echo ($current_section === 'panel') ? 'is-active' : ''; ?>">
                            <span class="rkm-sidebar-link__icon">P</span>
                            <span>Panel</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo esc_url($panel_url . '?section=pedidos'); ?>" class="rkm-sidebar-link <?php echo ($current_section === 'pedidos') ? 'is-active' : ''; ?>">
                            <span class="rkm-sidebar-link__icon">O</span>
                            <span>Pedidos</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo esc_url($panel_url . '?section=catalogo'); ?>" class="rkm-sidebar-link <?php echo ($current_section === 'catalogo') ? 'is-active' : ''; ?>">
                            <span class="rkm-sidebar-link__icon">C</span>
                            <span>Catalogo</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo esc_url($panel_url . '?section=historial'); ?>" class="rkm-sidebar-link <?php echo ($current_section === 'historial') ? 'is-active' : ''; ?>">
                            <span class="rkm-sidebar-link__icon">H</span>
                            <span>Historial</span>
                        </a>
                    </li>
                    <?php if (class_exists('RKM_Sellers') && RKM_Sellers::can_access()) : ?>
                        <li>
                            <a href="<?php echo esc_url(RKM_Sellers::get_section_url()); ?>" class="rkm-sidebar-link <?php echo ($current_section === RKM_Sellers::get_section_key()) ? 'is-active' : ''; ?>">
                                <span class="rkm-sidebar-link__icon">V</span>
                                <span>Panel vendedor</span>
                            </a>
                        </li>
                    <?php endif; ?>
                    <li>
                        <a href="<?php echo esc_url(class_exists('RKM_Auth') ? RKM_Auth::get_logout_url() : wc_logout_url(home_url('/mi-cuenta/'))); ?>" class="rkm-sidebar-link rkm-sidebar-link--danger">
                            <span class="rkm-sidebar-link__icon">X</span>
                            <span>Cerrar sesion</span>
                        </a>
                    </li>
                </ul>

                <div class="rkm-sidebar-card__footer">
                    <a class="rkm-btn-primary rkm-btn-block rkm-sidebar-card__cta" href="<?php echo esc_url($panel_url . '?section=nueva-orden'); ?>">
                        <span class="rkm-sidebar-card__cta-label">Nueva orden</span>
                    </a>
                </div>
            </aside>

            <div class="rkm-dashboard-cards">
                <?php foreach ($data['dashboard_cards'] as $card) : ?>
                    <div class="rkm-stat-card">
                        <h3><?php echo esc_html($card['label']); ?></h3>
                        <p><?php echo is_string($card['value']) ? wp_kses_post($card['value']) : esc_html((string) $card['value']); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        </div>
    </div>
</div>
