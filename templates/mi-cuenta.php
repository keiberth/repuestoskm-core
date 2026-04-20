<?php
if (!defined('ABSPATH')) {
    exit;
}

$current = 'mi-cuenta';
$page_title = 'Mi cuenta';
$page_subtitle = 'Actualiza tus datos personales y tu contraseña';

$user = wp_get_current_user();
$user_id = get_current_user_id();

$billing_name = trim(
    get_user_meta($user_id, 'billing_first_name', true) . ' ' .
    get_user_meta($user_id, 'billing_last_name', true)
);

$billing_address_1 = get_user_meta($user_id, 'billing_address_1', true);
$billing_city      = get_user_meta($user_id, 'billing_city', true);
$billing_phone     = get_user_meta($user_id, 'billing_phone', true);

$shipping_name = trim(
    get_user_meta($user_id, 'shipping_first_name', true) . ' ' .
    get_user_meta($user_id, 'shipping_last_name', true)
);

$shipping_address_1 = get_user_meta($user_id, 'shipping_address_1', true);
$shipping_city      = get_user_meta($user_id, 'shipping_city', true);
?>

<div class="rkm-app">
    <?php include plugin_dir_path(__FILE__) . 'partials/private-header.php'; ?>

    <div class="rkm-container">
        <div class="rkm-page-header">
            <h1><?php echo esc_html($page_title); ?></h1>
            <p><?php echo esc_html($page_subtitle); ?></p>
        </div>

        <?php include plugin_dir_path(__FILE__) . 'partials/subnav.php'; ?>

        <div class="rkm-card rkm-account-card">
            <?php
            if (function_exists('wc_get_template')) {
                wc_get_template(
                    'myaccount/form-edit-account.php',
                    array(
                        'user' => $user,
                    )
                );
            }
            ?>
        </div>

        <div class="rkm-grid rkm-grid-2" style="margin-top: 24px;">

            <div class="rkm-card rkm-address-card">
                <div class="rkm-address-summary__header">
                    <h3>Dirección de facturación</h3>

                    <?php if (!empty($billing_address_1)) : ?>
                        <button type="button" class="rkm-link-edit rkm-open-billing-modal">
                            Editar
                        </button>
                    <?php endif; ?>
                </div>

                <div class="rkm-address-summary__content" id="rkmBillingSummary" <?php if (empty($billing_address_1)) echo 'style="display:none;"'; ?>>
                    <?php if (!empty($billing_name)) : ?>
                        <strong><?php echo esc_html($billing_name); ?></strong>
                    <?php endif; ?>

                    <?php if (!empty($billing_address_1)) : ?>
                        <p><?php echo esc_html($billing_address_1); ?></p>
                    <?php endif; ?>

                    <?php if (!empty($billing_city)) : ?>
                        <p><?php echo esc_html($billing_city); ?></p>
                    <?php endif; ?>

                    <?php if (!empty($billing_phone)) : ?>
                        <p>Tel: <?php echo esc_html($billing_phone); ?></p>
                    <?php endif; ?>
                </div>

                <div class="rkm-address-summary__empty" id="rkmBillingEmpty" <?php if (!empty($billing_address_1)) echo 'style="display:none;"'; ?>>
                    <p>No tenés una dirección de facturación configurada.</p>
                    <button type="button" class="rkm-btn-secondary rkm-open-billing-modal">
                        Cargar dirección
                    </button>
                </div>
            </div>

            <div class="rkm-card rkm-address-card">
                <div class="rkm-address-summary__header">
                    <h3>Dirección de envío</h3>

                    <?php if (!empty($shipping_address_1)) : ?>
                        <button type="button" class="rkm-link-edit rkm-open-shipping-modal">
                            Editar
                        </button>
                    <?php endif; ?>
                </div>

                <div class="rkm-address-summary__content" id="rkmShippingSummary" <?php if (empty($shipping_address_1)) echo 'style="display:none;"'; ?>>
                    <?php if (!empty($shipping_name)) : ?>
                        <strong><?php echo esc_html($shipping_name); ?></strong>
                    <?php endif; ?>

                    <?php if (!empty($shipping_address_1)) : ?>
                        <p><?php echo esc_html($shipping_address_1); ?></p>
                    <?php endif; ?>

                    <?php if (!empty($shipping_city)) : ?>
                        <p><?php echo esc_html($shipping_city); ?></p>
                    <?php endif; ?>
                </div>

                <div class="rkm-address-summary__empty" id="rkmShippingEmpty" <?php if (!empty($shipping_address_1)) echo 'style="display:none;"'; ?>>
                    <p>No tenés una dirección de envío configurada.</p>
                    <button type="button" class="rkm-btn-secondary rkm-open-shipping-modal">
                        Cargar dirección
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>

<div class="rkm-modal" id="rkmBillingModal">
    <div class="rkm-modal__overlay"></div>

    <div class="rkm-modal__content rkm-modal__content--sm">
        <button class="rkm-modal__close" type="button">&times;</button>

        <h3>Editar facturación</h3>

        <input
            type="text"
            id="billing_first_name"
            placeholder="Nombre"
            value="<?php echo esc_attr(get_user_meta($user_id, 'billing_first_name', true)); ?>"
        >

        <input
            type="text"
            id="billing_last_name"
            placeholder="Apellido"
            value="<?php echo esc_attr(get_user_meta($user_id, 'billing_last_name', true)); ?>"
        >

        <input
            type="text"
            id="billing_phone"
            placeholder="Teléfono"
            value="<?php echo esc_attr(get_user_meta($user_id, 'billing_phone', true)); ?>"
        >

        <input
            type="text"
            id="billing_address_1"
            placeholder="Dirección"
            value="<?php echo esc_attr($billing_address_1); ?>"
        >

        <input
            type="text"
            id="billing_city"
            placeholder="Ciudad"
            value="<?php echo esc_attr($billing_city); ?>"
        >

        <button class="rkm-btn-primary" id="rkmSaveBilling">Guardar</button>
    </div>
</div>

<div class="rkm-modal" id="rkmShippingModal">
    <div class="rkm-modal__overlay"></div>

    <div class="rkm-modal__content rkm-modal__content--sm">
        <button class="rkm-modal__close" type="button">&times;</button>

        <h3>Editar envío</h3>

        <input
            type="text"
            id="shipping_first_name"
            placeholder="Nombre"
            value="<?php echo esc_attr(get_user_meta($user_id, 'shipping_first_name', true)); ?>"
        >

        <input
            type="text"
            id="shipping_last_name"
            placeholder="Apellido"
            value="<?php echo esc_attr(get_user_meta($user_id, 'shipping_last_name', true)); ?>"
        >

        <input
            type="text"
            id="shipping_address_1"
            placeholder="Dirección"
            value="<?php echo esc_attr($shipping_address_1); ?>"
        >

        <input
            type="text"
            id="shipping_city"
            placeholder="Ciudad"
            value="<?php echo esc_attr($shipping_city); ?>"
        >

        <button class="rkm-btn-primary" id="rkmSaveShipping">Guardar</button>
    </div>
</div>