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

$brand_logo = defined('RKM_CORE_URL') ? RKM_CORE_URL . 'assets/img/logo.png' : '';
$brand_name = 'Repuestos-KM';
$bcv_rate = isset($data['bcv_rate']) && is_array($data['bcv_rate']) ? $data['bcv_rate'] : null;
$section = isset($_GET['section']) ? sanitize_key(wp_unslash($_GET['section'])) : 'panel';
$module_labels = [
    'panel'          => 'Panel de cuenta',
    'catalogo'       => 'Catalogo',
    'nueva-orden'    => 'Nueva orden',
    'pedidos'        => 'Pedidos',
    'historial'      => 'Historial',
    'cuenta-corriente' => 'Cuenta corriente',
    'panel-vendedor' => 'Panel vendedor',
    'admin'          => 'Administracion',
    'usuarios'       => 'Usuarios',
    'asignaciones'   => 'Asignaciones',
    'formas-pago'    => 'Formas de pago',
    'condiciones-pago' => 'Condiciones de pago',
    'productos'      => 'Productos',
    'pagos-clientes' => 'Pagos clientes',
    'mi-cuenta'      => 'Mi perfil',
];
$module_label = isset($module_labels[$section]) ? $module_labels[$section] : 'Sistema RKM';
?>

<div class="rkm-private-header-pro">
    <div class="rkm-private-header-pro__inner">
        <div class="rkm-private-header-pro__brand">
            <a href="<?php echo esc_url(home_url('/mi-cuenta/panel')); ?>" class="rkm-private-header-pro__brand-link">
                <?php if ($brand_logo !== '') : ?>
                    <img
                        src="<?php echo esc_url($brand_logo); ?>"
                        alt="<?php echo esc_attr($brand_name); ?>"
                        class="rkm-private-header-pro__logo"
                        onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-flex';"
                    >
                <?php endif; ?>
                <span class="rkm-private-header-pro__brand-fallback" <?php echo $brand_logo !== '' ? 'style="display:none;"' : ''; ?>>
                    <?php echo esc_html($brand_name); ?>
                </span>
            </a>
        </div>

        <div class="rkm-private-header-pro__context" aria-label="Modulo actual">
            <span class="rkm-private-header-pro__context-label">Modulo</span>
            <strong><?php echo esc_html($module_label); ?></strong>
        </div>

        <div class="rkm-private-header-pro__right">
            <?php if (!empty($bcv_rate['value_display'])) : ?>
                <div class="rkm-private-header-pro__rate" aria-label="Tasa oficial BCV del dolar">
                    <span class="rkm-private-header-pro__rate-label">
                        <?php echo esc_html($bcv_rate['label']); ?>
                    </span>
                    <strong class="rkm-private-header-pro__rate-value">
                        <?php echo esc_html($bcv_rate['value_display']); ?>
                    </strong>
                    <?php if (!empty($bcv_rate['effective_date'])) : ?>
                        <small class="rkm-private-header-pro__rate-date">
                            <?php echo esc_html($bcv_rate['effective_date']); ?>
                        </small>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="rkm-private-header-pro__user">
                <button
                    type="button"
                    class="rkm-private-header-pro__toggle"
                    id="rkmUserMenuToggle"
                    aria-haspopup="true"
                    aria-expanded="false"
                    aria-controls="rkmUserMenuDropdown"
                >
                    <span class="rkm-private-header-pro__avatar"><?php echo esc_html($initial); ?></span>

                    <span class="rkm-private-header-pro__meta">
                        <strong><?php echo esc_html($user_name); ?></strong>
                        <small class="rkm-private-header-pro__role"><?php echo esc_html($user_role); ?></small>
                    </span>

                    <span class="rkm-private-header-pro__caret" aria-hidden="true">&#9662;</span>
                </button>

                <div class="rkm-private-header-pro__dropdown" id="rkmUserMenuDropdown">
                    <?php if (class_exists('RKM_Admin_Users') && RKM_Admin_Users::can_access()) : ?>
                        <a href="<?php echo esc_url(RKM_Admin_Users::get_section_url()); ?>">Usuarios</a>
                    <?php endif; ?>
                    <?php if (class_exists('RKM_Assignments') && RKM_Assignments::can_access()) : ?>
                        <a href="<?php echo esc_url(RKM_Assignments::get_section_url()); ?>">Asignaciones</a>
                    <?php endif; ?>
                    <?php if (class_exists('RKM_Payment_Methods') && RKM_Payment_Methods::can_access()) : ?>
                        <a href="<?php echo esc_url(RKM_Payment_Methods::get_section_url()); ?>">Formas de pago</a>
                    <?php endif; ?>
                    <?php if (class_exists('RKM_Payment_Terms') && RKM_Payment_Terms::can_access()) : ?>
                        <a href="<?php echo esc_url(RKM_Payment_Terms::get_section_url()); ?>">Condiciones de pago</a>
                    <?php endif; ?>
                    <?php if (class_exists('RKM_Products') && RKM_Products::can_access()) : ?>
                        <a href="<?php echo esc_url(RKM_Products::get_section_url()); ?>">Productos</a>
                    <?php endif; ?>
                    <?php if (class_exists('RKM_Current_Account') && RKM_Current_Account::can_admin_access()) : ?>
                        <a href="<?php echo esc_url(RKM_Current_Account::get_admin_section_url()); ?>">Pagos clientes</a>
                    <?php endif; ?>
                    <?php if (class_exists('RKM_Current_Account') && RKM_Current_Account::can_customer_access()) : ?>
                        <a href="<?php echo esc_url(RKM_Current_Account::get_customer_section_url()); ?>">Cuenta corriente</a>
                    <?php endif; ?>
                    <a href="<?php echo esc_url(home_url('/mi-cuenta/panel/?section=mi-cuenta')); ?>">Mi perfil</a>
                    <a href="<?php echo esc_url(home_url('/mi-cuenta/panel/?section=pedidos')); ?>">Pedidos</a>
                    <a class="rkm-private-header-pro__dropdown-danger" href="<?php echo esc_url(class_exists('RKM_Auth') ? RKM_Auth::get_logout_url() : wc_logout_url(home_url('/mi-cuenta/'))); ?>">Cerrar sesion</a>
                </div>
            </div>
        </div>
    </div>
</div>
