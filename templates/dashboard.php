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

        <div class="rkm-module-shell rkm-client-dashboard-shell">
        <div class="rkm-dashboard rkm-client-dashboard">
            <aside class="rkm-sidebar-card">
                <div class="rkm-sidebar-card__header">
                    <h3>Mi cuenta</h3>
                    <p>Accesos rapidos</p>
                </div>

                <ul class="rkm-sidebar-menu">
                    <li>
                        <a href="<?php echo esc_url($panel_url); ?>" class="rkm-sidebar-link <?php echo ($current_section === 'panel') ? 'is-active' : ''; ?>">
                            <span class="rkm-sidebar-link__icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" focusable="false">
                                    <path d="M3 3h8v8H3z"></path>
                                    <path d="M13 3h8v5h-8z"></path>
                                    <path d="M13 10h8v11h-8z"></path>
                                    <path d="M3 13h8v8H3z"></path>
                                </svg>
                            </span>
                            <span>Panel</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo esc_url($panel_url . '?section=pedidos'); ?>" class="rkm-sidebar-link <?php echo ($current_section === 'pedidos') ? 'is-active' : ''; ?>">
                            <span class="rkm-sidebar-link__icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" focusable="false">
                                    <path d="M9 5h6"></path>
                                    <path d="M9 9h6"></path>
                                    <path d="M9 13h4"></path>
                                    <path d="M5 3h14v18H5z"></path>
                                </svg>
                            </span>
                            <span>Pedidos</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo esc_url($panel_url . '?section=catalogo'); ?>" class="rkm-sidebar-link <?php echo ($current_section === 'catalogo') ? 'is-active' : ''; ?>">
                            <span class="rkm-sidebar-link__icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" focusable="false">
                                    <path d="M4 7l8-4 8 4-8 4z"></path>
                                    <path d="M4 7v10l8 4 8-4V7"></path>
                                    <path d="M12 11v10"></path>
                                </svg>
                            </span>
                            <span>Catalogo</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo esc_url($panel_url . '?section=historial'); ?>" class="rkm-sidebar-link <?php echo ($current_section === 'historial') ? 'is-active' : ''; ?>">
                            <span class="rkm-sidebar-link__icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" focusable="false">
                                    <path d="M3 12a9 9 0 1 0 3-6.7"></path>
                                    <path d="M3 4v5h5"></path>
                                    <path d="M12 7v5l3 2"></path>
                                </svg>
                            </span>
                            <span>Historial</span>
                        </a>
                    </li>
                    <?php if (class_exists('RKM_Sellers') && RKM_Sellers::can_access()) : ?>
                        <li>
                            <a href="<?php echo esc_url(RKM_Sellers::get_section_url()); ?>" class="rkm-sidebar-link <?php echo ($current_section === RKM_Sellers::get_section_key()) ? 'is-active' : ''; ?>">
                                <span class="rkm-sidebar-link__icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" focusable="false">
                                        <path d="M10 6V5a2 2 0 0 1 2-2h0a2 2 0 0 1 2 2v1"></path>
                                        <path d="M4 7h16v12H4z"></path>
                                        <path d="M4 12h16"></path>
                                    </svg>
                                </span>
                                <span>Panel vendedor</span>
                            </a>
                        </li>
                    <?php endif; ?>
                    <li>
                        <a href="<?php echo esc_url(class_exists('RKM_Auth') ? RKM_Auth::get_logout_url() : wc_logout_url(home_url('/mi-cuenta/'))); ?>" class="rkm-sidebar-link rkm-sidebar-link--danger">
                            <span class="rkm-sidebar-link__icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" focusable="false">
                                    <path d="M10 17l5-5-5-5"></path>
                                    <path d="M15 12H3"></path>
                                    <path d="M14 4h5v16h-5"></path>
                                </svg>
                            </span>
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
