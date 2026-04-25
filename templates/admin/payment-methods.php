<?php
if (!defined('ABSPATH')) {
    exit;
}

$page_title = isset($data['page_title']) ? $data['page_title'] : 'Formas de pago';
$page_subtitle = isset($data['page_subtitle']) ? $data['page_subtitle'] : '';
$notice = isset($data['payment_methods_notice']) ? $data['payment_methods_notice'] : null;
$methods = isset($data['payment_methods']) && is_array($data['payment_methods']) ? $data['payment_methods'] : [];
$types = isset($data['payment_method_types']) && is_array($data['payment_method_types']) ? $data['payment_method_types'] : [];
$section_url = class_exists('RKM_Payment_Methods') ? RKM_Payment_Methods::get_section_url() : home_url('/mi-cuenta/panel/?section=formas-pago');
$active_count = count(array_filter($methods, static function ($method) {
    return !empty($method['active']);
}));
?>

<div class="rkm-app rkm-module-app">
    <div class="rkm-container rkm-admin-payment-methods-page">
        <?php include plugin_dir_path(__FILE__) . '../partials/private-header.php'; ?>

        <div class="rkm-page-header rkm-admin-payment-methods-page__header">
            <div>
                <h1><?php echo esc_html($page_title); ?></h1>
                <p><?php echo esc_html($page_subtitle); ?></p>
            </div>

            <a class="rkm-admin-payment-methods-page__back" href="<?php echo esc_url(home_url('/mi-cuenta/panel/')); ?>">
                Volver al panel admin
            </a>
        </div>

        <div class="rkm-module-shell rkm-admin-payment-methods-shell">
            <section class="rkm-admin-payment-methods-hero">
                <div>
                    <span class="rkm-admin-payment-methods-hero__eyebrow">Cobranza</span>
                    <h2>Metodos disponibles en nueva orden</h2>
                    <p>Los metodos activos se muestran a clientes y vendedores antes de confirmar un pedido.</p>
                </div>

                <div class="rkm-admin-payment-methods-hero__stats">
                    <span><?php echo esc_html((string) count($methods)); ?></span>
                    <strong>registradas</strong>
                    <span><?php echo esc_html((string) $active_count); ?></span>
                    <strong>activas</strong>
                </div>
            </section>

            <?php if (!empty($notice['message'])) : ?>
                <div class="rkm-admin-payment-methods-notice rkm-admin-payment-methods-notice--<?php echo esc_attr($notice['type']); ?>">
                    <p><?php echo esc_html($notice['message']); ?></p>
                </div>
            <?php endif; ?>

            <div class="rkm-admin-payment-methods-grid">
                <section class="rkm-card rkm-admin-payment-methods-form-card">
                    <div class="rkm-admin-payment-methods-panel__header">
                        <h3 id="rkmPaymentMethodFormTitle">Nueva forma de pago</h3>
                        <p>Define como se mostrara esta opcion durante la creacion de pedidos.</p>
                    </div>

                    <form method="post" action="<?php echo esc_url($section_url); ?>" class="rkm-admin-payment-methods-form" data-rkm-payment-method-form>
                        <input type="hidden" name="rkm_payment_methods_action" value="save_method">
                        <input type="hidden" name="method_id" value="" data-rkm-payment-method-id>
                        <?php wp_nonce_field('rkm_payment_methods_update', 'rkm_payment_methods_nonce'); ?>

                        <label class="rkm-admin-payment-methods-field">
                            <span>Nombre</span>
                            <input type="text" name="name" required placeholder="Ej: Transferencia bancaria" data-rkm-payment-method-name>
                        </label>

                        <div class="rkm-admin-payment-methods-form__row">
                            <label class="rkm-admin-payment-methods-field">
                                <span>Tipo</span>
                                <select name="type" data-rkm-payment-method-type>
                                    <?php foreach ($types as $type_key => $type_label) : ?>
                                        <option value="<?php echo esc_attr($type_key); ?>"><?php echo esc_html($type_label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <label class="rkm-admin-payment-methods-field">
                                <span>Orden de visualizacion</span>
                                <input type="number" name="priority" min="0" step="1" value="10" placeholder="10" data-rkm-payment-method-priority>
                                <small>Menor numero = aparece primero</small>
                            </label>
                        </div>

                        <label class="rkm-admin-payment-methods-field">
                            <span>Descripcion / instrucciones</span>
                            <textarea name="description" rows="5" placeholder="Instrucciones visibles para el cliente o vendedor." data-rkm-payment-method-description></textarea>
                        </label>

                        <label class="rkm-admin-payment-methods-toggle">
                            <input type="checkbox" name="active" value="1" checked data-rkm-payment-method-active>
                            <span>Activo para nueva orden</span>
                        </label>

                        <div class="rkm-admin-payment-methods-form__actions">
                            <button type="submit" class="rkm-btn rkm-btn--primary" data-rkm-payment-method-submit>Guardar forma de pago</button>
                            <button type="button" class="rkm-btn rkm-btn--secondary" data-rkm-payment-method-reset>Limpiar</button>
                        </div>
                    </form>
                </section>

                <section class="rkm-card rkm-admin-payment-methods-list-card">
                    <div class="rkm-admin-payment-methods-panel__header">
                        <h3>Formas registradas</h3>
                        <p>Activa, edita o elimina metodos sin cambiar el flujo de pedidos.</p>
                    </div>

                    <?php if (!empty($methods)) : ?>
                        <div class="rkm-admin-payment-methods-list">
                            <?php foreach ($methods as $method) : ?>
                                <?php
                                $method_type = isset($types[$method['type']]) ? $types[$method['type']] : ucfirst($method['type']);
                                $method_payload = [
                                    'id'          => $method['id'],
                                    'name'        => $method['name'],
                                    'type'        => $method['type'],
                                    'description' => $method['description'],
                                    'active'      => !empty($method['active']),
                                    'priority'    => (int) $method['priority'],
                                ];
                                ?>
                                <article class="rkm-admin-payment-methods-item <?php echo !empty($method['active']) ? 'is-active' : 'is-inactive'; ?>">
                                    <div class="rkm-admin-payment-methods-item__main">
                                        <div>
                                            <span class="rkm-admin-payment-methods-item__type"><?php echo esc_html($method_type); ?></span>
                                            <h4><?php echo esc_html($method['name']); ?></h4>
                                            <?php if (!empty($method['description'])) : ?>
                                                <p><?php echo esc_html($method['description']); ?></p>
                                            <?php else : ?>
                                                <p class="is-muted">Sin instrucciones cargadas.</p>
                                            <?php endif; ?>
                                        </div>

                                        <div class="rkm-admin-payment-methods-item__meta">
                                            <span class="rkm-admin-payment-methods-status <?php echo !empty($method['active']) ? 'is-active' : 'is-inactive'; ?>">
                                                <?php echo !empty($method['active']) ? 'Activa' : 'Inactiva'; ?>
                                            </span>
                                            <span>Orden <?php echo esc_html((string) $method['priority']); ?></span>
                                        </div>
                                    </div>

                                    <div class="rkm-admin-payment-methods-item__actions">
                                        <button
                                            type="button"
                                            class="rkm-admin-payment-methods-link"
                                            data-rkm-payment-method-edit
                                            data-method="<?php echo esc_attr(wp_json_encode($method_payload)); ?>"
                                        >
                                            Editar
                                        </button>

                                        <form method="post" action="<?php echo esc_url($section_url); ?>">
                                            <input type="hidden" name="rkm_payment_methods_action" value="toggle_method">
                                            <input type="hidden" name="method_id" value="<?php echo esc_attr($method['id']); ?>">
                                            <?php wp_nonce_field('rkm_payment_methods_update', 'rkm_payment_methods_nonce'); ?>
                                            <button type="submit" class="rkm-admin-payment-methods-link">
                                                <?php echo !empty($method['active']) ? 'Desactivar' : 'Activar'; ?>
                                            </button>
                                        </form>

                                        <form method="post" action="<?php echo esc_url($section_url); ?>" data-rkm-payment-method-delete>
                                            <input type="hidden" name="rkm_payment_methods_action" value="delete_method">
                                            <input type="hidden" name="method_id" value="<?php echo esc_attr($method['id']); ?>">
                                            <?php wp_nonce_field('rkm_payment_methods_update', 'rkm_payment_methods_nonce'); ?>
                                            <button type="submit" class="rkm-admin-payment-methods-link rkm-admin-payment-methods-link--danger">
                                                Eliminar
                                            </button>
                                        </form>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php else : ?>
                        <div class="rkm-admin-payment-methods-empty">
                            <strong>No hay formas de pago registradas</strong>
                            <p>Crea la primera forma para que aparezca como opcion en Nueva orden.</p>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </div>
    </div>
</div>
