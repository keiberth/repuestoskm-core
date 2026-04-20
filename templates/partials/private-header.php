<?php
if (!defined('ABSPATH')) {
    exit;
}

$user = wp_get_current_user();
$user_name = $user->display_name ? $user->display_name : $user->user_login;

$user_role = 'Cliente';
if (!empty($user->roles) && is_array($user->roles)) {
    $role = $user->roles[0];

    $labels = [
        'administrator' => 'Administrador',
        'customer'      => 'Cliente',
        'subscriber'    => 'Suscriptor',
        'shop_manager'  => 'Encargado de tienda',
    ];

    $user_role = $labels[$role] ?? ucfirst($role);
}

$initial = strtoupper(mb_substr($user_name, 0, 1));

$brand_logo = 'http://repuestos-km.local/wp-content/uploads/2026/03/ChatGPT-Image-14-mar-2026-02_22_59-p.m-e1773612976421.png';
$brand_name = 'Repuestos-KM';
?>

<div class="rkm-private-header-pro">
    <!--<a href="<?php echo esc_url(home_url('/mi-cuenta/panel')); ?>" class="rkm-private-header-pro__brand-link">
        <img
            src="<?php echo esc_url($brand_logo); ?>"
            alt="<?php echo esc_attr($brand_name); ?>"
            class="rkm-private-header-pro__logo"
            onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-flex';"
        >
        <span class="rkm-private-header-pro__brand-fallback" style="display:none;">
            <?php echo esc_html($brand_name); ?>
        </span>
    </a>-->

    <div class="rkm-private-header-pro__user">
        <button type="button" class="rkm-private-header-pro__toggle" id="rkmUserMenuToggle">
            <span class="rkm-private-header-pro__avatar"><?php echo esc_html($initial); ?></span>

            <span class="rkm-private-header-pro__meta">
                <strong><?php echo esc_html($user_name); ?></strong>
                <small><?php echo esc_html($user_role); ?></small>
            </span>

            <span class="rkm-private-header-pro__caret">▾</span>
        </button>

        <div class="rkm-private-header-pro__dropdown" id="rkmUserMenuDropdown">
            <a href="<?php echo esc_url(home_url('/mi-cuenta/panel/?section=mi-cuenta')); ?>">Mi cuenta</a>
            <a href="<?php echo esc_url(home_url('/mi-cuenta/panel/?section=pedidos')); ?>">Pedidos</a>
            <a href="<?php echo esc_url(wc_logout_url(wp_login_url())); ?>">Cerrar sesión</a>
        </div>
    </div>
</div>