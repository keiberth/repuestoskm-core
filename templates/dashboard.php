<?php
if (!defined('ABSPATH')) {
    exit;
}

$panel_url = home_url('/mi-cuenta/panel/');
$current_section = 'panel';

?>

<div class="rkm-app">
    <div class="rkm-container rkm-dashboard-wrapper">

        <?php include plugin_dir_path(__FILE__) . 'partials/private-header.php'; ?>

        <div class="rkm-dashboard-header">
            <div>
                <h1 class="rkm-title">Panel de cuenta</h1>
                <p class="rkm-subtitle">Bienvenido, <?php echo esc_html(wp_get_current_user()->display_name); ?></p>
            </div>
        </div>

        <div class="rkm-dashboard">
            <aside class="rkm-sidebar-card">
                <div class="rkm-sidebar-card__header">
                    <h3>Mi cuenta</h3>
                    <p>Accesos rápidos</p>
                </div>

                <ul class="rkm-sidebar-menu">
                    <li>
                        <a href="<?php echo esc_url($panel_url); ?>"
                           class="rkm-sidebar-link <?php echo ($current_section === 'panel') ? 'is-active' : ''; ?>">
                            <span class="rkm-sidebar-link__icon">🏠</span>
                            <span>Panel</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo esc_url($panel_url . '?section=pedidos'); ?>"
                           class="rkm-sidebar-link <?php echo ($current_section === 'pedidos') ? 'is-active' : ''; ?>">
                            <span class="rkm-sidebar-link__icon">📦</span>
                            <span>Pedidos</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo esc_url($panel_url . '?section=catalogo'); ?>"
                           class="rkm-sidebar-link <?php echo ($current_section === 'catalogo') ? 'is-active' : ''; ?>">
                            <span class="rkm-sidebar-link__icon">&#128214;</span>
                            <span>Catalogo</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo esc_url($panel_url . '?section=historial'); ?>"
                           class="rkm-sidebar-link <?php echo ($current_section === 'historial') ? 'is-active' : ''; ?>">
                            <span class="rkm-sidebar-link__icon">🕘</span>
                            <span>Historial</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo esc_url(wc_logout_url()); ?>" class="rkm-sidebar-link rkm-sidebar-link--danger">
                            <span class="rkm-sidebar-link__icon">↩</span>
                            <span>Cerrar sesión</span>
                        </a>
                    </li>
                </ul>

                <div class="rkm-sidebar-card__footer">
                    <a class="rkm-btn-primary rkm-btn-block rkm-sidebar-card__cta" href="<?php echo esc_url($panel_url . '?section=nueva-orden'); ?>">
                        <span class="rkm-sidebar-card__cta-label">Nueva Orden</span>
                    </a>
                </div>
            </aside>

            <div class="rkm-dashboard-cards">
                <div class="rkm-stat-card">
                    <div class="rkm-stat-card__icon">💳</div>
                    <h3>Pendiente por pagar</h3>
                    <p><?php echo wp_kses_post($data['pending_total']); ?></p>
                </div>

                <div class="rkm-stat-card">
                    <div class="rkm-stat-card__icon">🧾</div>
                    <h3>Saldo a favor</h3>
                    <p><?php echo wp_kses_post($data['balance_favor']); ?></p>
                </div>

                <div class="rkm-stat-card">
                    <div class="rkm-stat-card__icon">🛒</div>
                    <h3>Última compra</h3>
                    <p><?php echo esc_html($data['last_purchase_date']); ?></p>
                </div>

                <div class="rkm-stat-card">
                    <div class="rkm-stat-card__icon">↩</div>
                    <h3>Devoluciones</h3>
                    <p><?php echo esc_html($data['returns_count']); ?></p>
                </div>
            </div>
        </div>

    </div>
</div>
