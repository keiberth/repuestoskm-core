<?php
if (!defined('ABSPATH')) {
    exit;
}

$user = wp_get_current_user();
$user_name = $user->display_name ? $user->display_name : $user->user_login;

$user_role = 'Cliente';
if (!empty($user->roles) && is_array($user->roles)) {
    $role = $user->roles[0];
    $user_role = class_exists('RKM_Permissions')
        ? RKM_Permissions::get_role_label($role)
        : ucfirst($role);
}

$initial = strtoupper(mb_substr($user_name, 0, 1));

$brand_logo = 'http://repuestos-km.local/wp-content/uploads/2026/03/ChatGPT-Image-14-mar-2026-02_22_59-p.m-e1773612976421.png';
$brand_name = 'Repuestos-KM';
$bcv_rate = isset($data['bcv_rate']) && is_array($data['bcv_rate']) ? $data['bcv_rate'] : null;
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

    <?php if (!empty($bcv_rate['value_display'])) : ?>
        <div class="rkm-private-header-pro__rate" aria-label="Tasa oficial BCV del dólar">
            <span class="rkm-private-header-pro__rate-label">
                <?php echo esc_html($bcv_rate['label']); ?>
            </span>
            <strong class="rkm-private-header-pro__rate-value">
                <?php echo esc_html($bcv_rate['value_display']); ?>
            </strong>
            <?php if (!empty($bcv_rate['effective_date'])) : ?>
                <small class="rkm-private-header-pro__rate-date">
                    Fecha valor: <?php echo esc_html($bcv_rate['effective_date']); ?>
                </small>
            <?php endif; ?>
        </div>
    <?php endif; ?>

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
            <?php if (class_exists('RKM_Admin_Users') && RKM_Admin_Users::can_access()) : ?>
                <a href="<?php echo esc_url(RKM_Admin_Users::get_section_url()); ?>">Usuarios</a>
            <?php endif; ?>
            <a href="<?php echo esc_url(home_url('/mi-cuenta/panel/?section=mi-cuenta')); ?>">Mi cuenta</a>
            <a href="<?php echo esc_url(home_url('/mi-cuenta/panel/?section=pedidos')); ?>">Pedidos</a>
            <a href="<?php echo esc_url(class_exists('RKM_Auth') ? RKM_Auth::get_logout_url() : wc_logout_url(home_url('/mi-cuenta/'))); ?>">Cerrar sesión</a>
        </div>
    </div>
</div>

