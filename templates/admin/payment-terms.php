<?php
if (!defined('ABSPATH')) {
    exit;
}

$page_title = isset($data['page_title']) ? $data['page_title'] : 'Condiciones de pago';
$page_subtitle = isset($data['page_subtitle']) ? $data['page_subtitle'] : '';
$notice = isset($data['payment_terms_notice']) ? $data['payment_terms_notice'] : null;
$settings = isset($data['payment_terms_settings']) && is_array($data['payment_terms_settings'])
    ? $data['payment_terms_settings']
    : (class_exists('RKM_Payment_Terms') ? RKM_Payment_Terms::get_settings() : []);
$terms = isset($settings['terms']) && is_array($settings['terms']) ? $settings['terms'] : [];
$discount = isset($settings['cash_discount_percent']) ? (float) $settings['cash_discount_percent'] : 0;
$section_url = class_exists('RKM_Payment_Terms') ? RKM_Payment_Terms::get_section_url() : home_url('/mi-cuenta/panel/?section=condiciones-pago');
?>

<div class="rkm-app rkm-module-app">
    <div class="rkm-container rkm-admin-payment-terms-page">
        <?php include plugin_dir_path(__FILE__) . '../partials/private-header.php'; ?>

        <div class="rkm-page-header rkm-admin-payment-terms-page__header">
            <div>
                <h1><?php echo esc_html($page_title); ?></h1>
                <p><?php echo esc_html($page_subtitle); ?></p>
            </div>

            <a class="rkm-admin-payment-terms-page__back" href="<?php echo esc_url(home_url('/mi-cuenta/panel/')); ?>">
                Volver al panel admin
            </a>
        </div>

        <div class="rkm-module-shell rkm-admin-payment-terms-shell">
            <section class="rkm-admin-payment-terms-hero">
                <span class="rkm-admin-payment-terms-hero__eyebrow">Condiciones comerciales</span>
                <h2>Define como queda financieramente cada pedido</h2>
                <p>Estas opciones controlan el descuento de contado y el saldo pendiente que se guardara en la orden.</p>
            </section>

            <?php if (!empty($notice['message'])) : ?>
                <div class="rkm-admin-payment-terms-notice rkm-admin-payment-terms-notice--<?php echo esc_attr($notice['type']); ?>">
                    <p><?php echo esc_html($notice['message']); ?></p>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url($section_url); ?>" class="rkm-admin-payment-terms-form">
                <?php wp_nonce_field('rkm_payment_terms_update', 'rkm_payment_terms_nonce'); ?>

                <section class="rkm-card rkm-admin-payment-terms-discount">
                    <div class="rkm-admin-payment-terms-panel__header">
                        <h3>Descuento por pago contado</h3>
                        <p>Este porcentaje se aplica solo cuando el cliente selecciona la condicion Contado.</p>
                    </div>

                    <label class="rkm-admin-payment-terms-field rkm-admin-payment-terms-field--compact">
                        <span>Porcentaje de descuento</span>
                        <input type="number" name="cash_discount_percent" min="0" max="100" step="0.01" value="<?php echo esc_attr((string) $discount); ?>" placeholder="0">
                        <small>El calculo final se valida siempre desde backend.</small>
                    </label>
                </section>

                <section class="rkm-admin-payment-terms-grid">
                    <?php foreach ($terms as $term_key => $term) : ?>
                        <article class="rkm-card rkm-admin-payment-terms-card">
                            <div class="rkm-admin-payment-terms-card__header">
                                <span class="rkm-admin-payment-terms-card__badge"><?php echo esc_html($term['label']); ?></span>
                                <label class="rkm-admin-payment-terms-toggle">
                                    <input
                                        type="checkbox"
                                        name="terms[<?php echo esc_attr($term_key); ?>][active]"
                                        value="1"
                                        <?php checked(!empty($term['active'])); ?>
                                    >
                                    <span>Activa</span>
                                </label>
                            </div>

                            <label class="rkm-admin-payment-terms-field">
                                <span>Texto visible para el cliente</span>
                                <textarea name="terms[<?php echo esc_attr($term_key); ?>][instructions]" rows="5"><?php echo esc_textarea($term['instructions']); ?></textarea>
                            </label>
                        </article>
                    <?php endforeach; ?>
                </section>

                <div class="rkm-admin-payment-terms-actions">
                    <button type="submit" class="rkm-btn rkm-btn--primary">Guardar condiciones</button>
                </div>
            </form>
        </div>
    </div>
</div>
