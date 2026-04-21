<?php
/**
 * UI custom del login sobre autenticacion nativa de WooCommerce.
 */

if (!defined('ABSPATH')) {
    exit;
}

$registration_enabled = 'yes' === get_option('woocommerce_enable_myaccount_registration');
$generate_username = 'no' === get_option('woocommerce_registration_generate_username');
$generate_password = 'no' === get_option('woocommerce_registration_generate_password');
$theme_button_class = function_exists('wc_wp_theme_get_element_class_name')
    ? wc_wp_theme_get_element_class_name('button')
    : '';
$login_value = (!empty($_POST['username']) && is_string($_POST['username']))
    ? wp_unslash($_POST['username'])
    : '';
$register_username_value = (!empty($_POST['username']) && is_string($_POST['username']))
    ? wp_unslash($_POST['username'])
    : '';
$register_email_value = (!empty($_POST['email']) && is_string($_POST['email']))
    ? wp_unslash($_POST['email'])
    : '';

do_action('woocommerce_before_customer_login_form');
?>

<div class="rkm-login-screen">
    <section class="rkm-login-screen__brand" aria-label="<?php esc_attr_e('Presentacion del sistema', 'repuestoskm-core'); ?>">
        <div class="rkm-login-screen__brand-inner">
            <div class="rkm-login-screen__badge">
                <img
                    src="<?php echo esc_url(RKM_CORE_URL . 'assets/img/logo.png'); ?>"
                    alt="<?php esc_attr_e('Logo Repuestos KM', 'repuestoskm-core'); ?>"
                    class="rkm-login-screen__logo"
                >
                <span><?php esc_html_e('ERP privado', 'repuestoskm-core'); ?></span>
            </div>

            <div class="rkm-login-screen__copy">
                <p class="rkm-login-screen__eyebrow"><?php esc_html_e('Acceso seguro', 'repuestoskm-core'); ?></p>
                <h1><?php esc_html_e('Entrá al sistema y seguí operando sin fricción.', 'repuestoskm-core'); ?></h1>
                <p class="rkm-login-screen__lead">
                    <?php esc_html_e('Una experiencia de acceso alineada con el panel: clara, rápida y enfocada en pedidos, cuenta y operación comercial.', 'repuestoskm-core'); ?>
                </p>
            </div>

            <div class="rkm-login-screen__highlights" aria-hidden="true">
                <article class="rkm-login-screen__highlight">
                    <strong><?php esc_html_e('Pedidos', 'repuestoskm-core'); ?></strong>
                    <span><?php esc_html_e('Seguimiento inmediato del flujo comercial.', 'repuestoskm-core'); ?></span>
                </article>
                <article class="rkm-login-screen__highlight">
                    <strong><?php esc_html_e('Cuenta', 'repuestoskm-core'); ?></strong>
                    <span><?php esc_html_e('Datos, direcciones y estado del cliente en un solo lugar.', 'repuestoskm-core'); ?></span>
                </article>
                <article class="rkm-login-screen__highlight">
                    <strong><?php esc_html_e('ERP', 'repuestoskm-core'); ?></strong>
                    <span><?php esc_html_e('Coherencia visual con el sistema privado de Repuestos KM.', 'repuestoskm-core'); ?></span>
                </article>
            </div>
        </div>
    </section>

    <section class="rkm-login-screen__auth" aria-label="<?php esc_attr_e('Acceso al sistema', 'repuestoskm-core'); ?>">
        <div class="rkm-login-panel">
            <div class="rkm-login-card">
                <div class="rkm-login-card__header">
                    <p class="rkm-login-card__eyebrow"><?php esc_html_e('Mi cuenta', 'repuestoskm-core'); ?></p>
                    <h2><?php esc_html_e('Iniciar sesión', 'repuestoskm-core'); ?></h2>
                    <p><?php esc_html_e('Usá tu usuario o correo y tu contraseña para entrar al panel.', 'repuestoskm-core'); ?></p>
                </div>

                <form class="woocommerce-form woocommerce-form-login login rkm-login-form" method="post" novalidate>
                    <?php do_action('woocommerce_login_form_start'); ?>

                    <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide rkm-login-form__row">
                        <label for="username"><?php esc_html_e('Usuario o correo', 'repuestoskm-core'); ?></label>
                        <input
                            type="text"
                            class="woocommerce-Input woocommerce-Input--text input-text"
                            name="username"
                            id="username"
                            autocomplete="username"
                            value="<?php echo esc_attr($login_value); ?>"
                            placeholder="<?php esc_attr_e('usuario@empresa.com', 'repuestoskm-core'); ?>"
                            required
                            aria-required="true"
                        />
                    </p>

                    <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide rkm-login-form__row">
                        <label for="password"><?php esc_html_e('Contraseña', 'repuestoskm-core'); ?></label>
                        <span class="rkm-login-form__password-wrap">
                            <input
                                class="woocommerce-Input woocommerce-Input--text input-text"
                                type="password"
                                name="password"
                                id="password"
                                autocomplete="current-password"
                                placeholder="<?php esc_attr_e('Ingresá tu contraseña', 'repuestoskm-core'); ?>"
                                required
                                aria-required="true"
                            />
                            <button
                                class="rkm-login-form__password-toggle"
                                type="button"
                                data-rkm-password-toggle
                                data-target="password"
                                aria-controls="password"
                                aria-pressed="false"
                                aria-label="<?php esc_attr_e('Mostrar contraseña', 'repuestoskm-core'); ?>"
                            >
                                <span class="rkm-login-form__password-toggle-icon" aria-hidden="true"></span>
                                <span class="rkm-login-form__password-toggle-label"><?php esc_html_e('Mostrar', 'repuestoskm-core'); ?></span>
                            </button>
                        </span>
                    </p>

                    <?php do_action('woocommerce_login_form'); ?>

                    <div class="rkm-login-form__actions">
                        <label class="woocommerce-form__label woocommerce-form__label-for-checkbox woocommerce-form-login__rememberme rkm-login-form__remember">
                            <input class="woocommerce-form__input woocommerce-form__input-checkbox" name="rememberme" type="checkbox" id="rememberme" value="forever" />
                            <span><?php esc_html_e('Mantener sesión iniciada', 'repuestoskm-core'); ?></span>
                        </label>

                        <a class="rkm-login-form__lost" href="<?php echo esc_url(wp_lostpassword_url()); ?>">
                            <?php esc_html_e('Recuperar contraseña', 'repuestoskm-core'); ?>
                        </a>
                    </div>

                    <p class="form-row rkm-login-form__submit-row">
                        <?php wp_nonce_field('woocommerce-login', 'woocommerce-login-nonce'); ?>
                        <button
                            type="submit"
                            class="woocommerce-button button woocommerce-form-login__submit rkm-login-form__submit<?php echo esc_attr($theme_button_class ? ' ' . $theme_button_class : ''); ?>"
                            name="login"
                            value="<?php esc_attr_e('Iniciar sesión', 'repuestoskm-core'); ?>"
                            data-rkm-submit-label="<?php esc_attr_e('Ingresando...', 'repuestoskm-core'); ?>"
                        >
                            <?php esc_html_e('Iniciar sesión', 'repuestoskm-core'); ?>
                        </button>
                    </p>

                    <?php do_action('woocommerce_login_form_end'); ?>
                </form>
            </div>

            <?php if ($registration_enabled) : ?>
                <div class="rkm-login-card rkm-login-card--secondary">
                    <div class="rkm-login-card__header rkm-login-card__header--compact">
                        <p class="rkm-login-card__eyebrow"><?php esc_html_e('Nuevo acceso', 'repuestoskm-core'); ?></p>
                        <h2><?php esc_html_e('Registro', 'repuestoskm-core'); ?></h2>
                        <p><?php esc_html_e('Si la tienda permite registro, completá tus datos para crear una cuenta.', 'repuestoskm-core'); ?></p>
                    </div>

                    <form method="post" class="woocommerce-form woocommerce-form-register register rkm-register-form" <?php do_action('woocommerce_register_form_tag'); ?>>
                        <?php do_action('woocommerce_register_form_start'); ?>

                        <?php if ($generate_username) : ?>
                            <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide rkm-login-form__row">
                                <label for="reg_username"><?php esc_html_e('Usuario', 'repuestoskm-core'); ?></label>
                                <input
                                    type="text"
                                    class="woocommerce-Input woocommerce-Input--text input-text"
                                    name="username"
                                    id="reg_username"
                                    autocomplete="username"
                                    value="<?php echo esc_attr($register_username_value); ?>"
                                    placeholder="<?php esc_attr_e('Elegí un usuario', 'repuestoskm-core'); ?>"
                                    required
                                    aria-required="true"
                                />
                            </p>
                        <?php endif; ?>

                        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide rkm-login-form__row">
                            <label for="reg_email"><?php esc_html_e('Correo electrónico', 'repuestoskm-core'); ?></label>
                            <input
                                type="email"
                                class="woocommerce-Input woocommerce-Input--text input-text"
                                name="email"
                                id="reg_email"
                                autocomplete="email"
                                value="<?php echo esc_attr($register_email_value); ?>"
                                placeholder="<?php esc_attr_e('tu-correo@empresa.com', 'repuestoskm-core'); ?>"
                                required
                                aria-required="true"
                            />
                        </p>

                        <?php if ($generate_password) : ?>
                            <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide rkm-login-form__row">
                                <label for="reg_password"><?php esc_html_e('Contraseña', 'repuestoskm-core'); ?></label>
                                <span class="rkm-login-form__password-wrap">
                                    <input
                                        type="password"
                                        class="woocommerce-Input woocommerce-Input--text input-text"
                                        name="password"
                                        id="reg_password"
                                        autocomplete="new-password"
                                        placeholder="<?php esc_attr_e('Creá una contraseña segura', 'repuestoskm-core'); ?>"
                                        required
                                        aria-required="true"
                                    />
                                    <button
                                        class="rkm-login-form__password-toggle"
                                        type="button"
                                        data-rkm-password-toggle
                                        data-target="reg_password"
                                        aria-controls="reg_password"
                                        aria-pressed="false"
                                        aria-label="<?php esc_attr_e('Mostrar contraseña', 'repuestoskm-core'); ?>"
                                    >
                                        <span class="rkm-login-form__password-toggle-icon" aria-hidden="true"></span>
                                        <span class="rkm-login-form__password-toggle-label"><?php esc_html_e('Mostrar', 'repuestoskm-core'); ?></span>
                                    </button>
                                </span>
                            </p>
                        <?php else : ?>
                            <p class="rkm-login-form__helper">
                                <?php esc_html_e('Te enviaremos un enlace por correo para definir tu contraseña.', 'repuestoskm-core'); ?>
                            </p>
                        <?php endif; ?>

                        <?php do_action('woocommerce_register_form'); ?>

                        <p class="woocommerce-form-row form-row rkm-login-form__submit-row">
                            <?php wp_nonce_field('woocommerce-register', 'woocommerce-register-nonce'); ?>
                            <button
                                type="submit"
                                class="woocommerce-Button woocommerce-button button woocommerce-form-register__submit rkm-login-form__submit<?php echo esc_attr($theme_button_class ? ' ' . $theme_button_class : ''); ?>"
                                name="register"
                                value="<?php esc_attr_e('Crear cuenta', 'repuestoskm-core'); ?>"
                                data-rkm-submit-label="<?php esc_attr_e('Enviando...', 'repuestoskm-core'); ?>"
                            >
                                <?php esc_html_e('Crear cuenta', 'repuestoskm-core'); ?>
                            </button>
                        </p>

                        <?php do_action('woocommerce_register_form_end'); ?>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<?php do_action('woocommerce_after_customer_login_form'); ?>
