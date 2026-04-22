<?php
if (!defined('ABSPATH')) {
    exit;
}

$current = isset($current) ? $current : '';
$panel_url = home_url('/mi-cuenta/panel/');
$current_user = wp_get_current_user();
$is_vendor_user = class_exists('RKM_Permissions') && RKM_Permissions::is_rkm_vendor($current_user);
?>

<nav class="rkm-subnav">
    <?php if (!$is_vendor_user) : ?>
        <a href="<?php echo esc_url($panel_url); ?>"
           class="rkm-subnav__link <?php echo ($current === 'panel') ? 'is-active' : ''; ?>">
            Panel
        </a>
    <?php endif; ?>

    <a href="<?php echo esc_url($panel_url . '?section=nueva-orden'); ?>"
       class="rkm-subnav__link <?php echo ($current === 'nueva-orden') ? 'is-active' : ''; ?>">
        Nueva orden
    </a>

    <a href="<?php echo esc_url($panel_url . '?section=pedidos'); ?>"
       class="rkm-subnav__link <?php echo ($current === 'pedidos') ? 'is-active' : ''; ?>">
        Pedidos
    </a>

    <a href="<?php echo esc_url($panel_url . '?section=historial'); ?>"
       class="rkm-subnav__link <?php echo ($current === 'historial') ? 'is-active' : ''; ?>">
        Historial
    </a>

    <?php if (class_exists('RKM_Sellers') && RKM_Sellers::can_access()) : ?>
        <a href="<?php echo esc_url(RKM_Sellers::get_section_url()); ?>"
           class="rkm-subnav__link <?php echo ($current === RKM_Sellers::get_section_key()) ? 'is-active' : ''; ?>">
            Panel vendedor
        </a>
    <?php endif; ?></nav>
