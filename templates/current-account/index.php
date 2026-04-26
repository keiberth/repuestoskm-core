<?php
if (!defined('ABSPATH')) {
    exit;
}

$page_title = $data['page_title'] ?? 'Cuenta corriente';
$page_subtitle = $data['page_subtitle'] ?? '';
$pending_orders = isset($data['pending_orders']) && is_array($data['pending_orders']) ? $data['pending_orders'] : [];
$payment_reports = isset($data['payment_reports']) && is_array($data['payment_reports']) ? $data['payment_reports'] : [];
$payment_methods = isset($data['payment_methods']) && is_array($data['payment_methods']) ? $data['payment_methods'] : [];
$notice = $data['current_account_notice'] ?? null;
$section_url = $data['section_url'] ?? home_url('/mi-cuenta/panel/?section=cuenta-corriente');
$status_labels = isset($data['status_labels']) && is_array($data['status_labels']) ? $data['status_labels'] : [];
$current_account = class_exists('RKM_Current_Account') ? new RKM_Current_Account() : null;
$pending_total = $data['pending_total'] ?? 0;
$is_vendor_context = !empty($data['is_vendor_context']);
$today = function_exists('wp_date') ? wp_date('Y-m-d', current_time('timestamp')) : date('Y-m-d');
?>

<div class="rkm-app rkm-module-app">
    <div class="rkm-container rkm-current-account-page">
        <?php include plugin_dir_path(__FILE__) . '../partials/private-header.php'; ?>

        <div class="rkm-page-header rkm-current-account-page__header">
            <div>
                <h1><?php echo esc_html($page_title); ?></h1>
                <p><?php echo esc_html($page_subtitle); ?></p>
            </div>
        </div>

        <?php
            $current = 'cuenta-corriente';
            include plugin_dir_path(__FILE__) . '../partials/subnav.php';
        ?>

        <div class="rkm-module-shell rkm-current-account-shell">
            <?php if (!empty($notice['message'])) : ?>
                <div class="rkm-current-account-notice rkm-current-account-notice--<?php echo esc_attr($notice['type']); ?>">
                    <p><?php echo esc_html($notice['message']); ?></p>
                </div>
            <?php endif; ?>

            <section class="rkm-current-account-summary">
                <div>
                    <span class="rkm-current-account-summary__eyebrow">Saldo pendiente total</span>
                    <strong><?php echo $current_account ? wp_kses_post($current_account->format_money($pending_total)) : esc_html((string) $pending_total); ?></strong>
                    <p><?php echo esc_html(count($pending_orders) . ' pedido(s) con saldo pendiente.'); ?></p>
                </div>
            </section>

            <div class="rkm-current-account-grid">
                <section class="rkm-card rkm-current-account-panel">
                    <div class="rkm-current-account-panel__header">
                        <h2>Informar pago</h2>
                        <p>El pago queda pendiente hasta que administracion lo valide.</p>
                    </div>

                    <?php if (empty($pending_orders)) : ?>
                        <div class="rkm-current-account-empty">
                            <strong>No tenes saldos pendientes</strong>
                            <p>Cuando un pedido tenga saldo a credito, vas a poder informar pagos desde aca.</p>
                        </div>
                    <?php elseif (empty($payment_methods)) : ?>
                        <div class="rkm-current-account-empty">
                            <strong>No hay formas de pago disponibles</strong>
                            <p>Administracion debe activar al menos una forma de pago para registrar informes.</p>
                        </div>
                    <?php else : ?>
                        <form method="post" action="<?php echo esc_url($section_url); ?>" enctype="multipart/form-data" class="rkm-current-account-form" data-rkm-current-account-form>
                            <input type="hidden" name="rkm_current_account_action" value="report_payment">
                            <?php wp_nonce_field('rkm_current_account_report', 'rkm_current_account_nonce'); ?>

                            <label class="rkm-current-account-field">
                                <span>Pedido relacionado</span>
                                <select name="order_id" required data-rkm-payment-order>
                                    <option value="">Seleccionar pedido</option>
                                    <?php foreach ($pending_orders as $order) : ?>
                                        <?php
                                        $balance = $current_account ? $current_account->get_order_credit_balance($order) : 0;
                                        $date = $order->get_date_created() ? $order->get_date_created()->date_i18n('d/m/Y') : '-';
                                        $customer_label = $current_account ? $current_account->get_order_customer_label($order) : '';
                                        ?>
                                        <option value="<?php echo esc_attr((string) $order->get_id()); ?>" data-balance="<?php echo esc_attr((string) $balance); ?>">
                                            #<?php echo esc_html($order->get_order_number()); ?><?php echo $is_vendor_context && $customer_label !== '' ? ' - ' . esc_html($customer_label) : ''; ?> - <?php echo esc_html($date); ?> - saldo <?php echo esc_html($current_account ? wp_strip_all_tags($current_account->format_money($balance)) : (string) $balance); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small data-rkm-payment-balance>Selecciona un pedido para ver su saldo.</small>
                            </label>

                            <div class="rkm-current-account-form__row">
                                <label class="rkm-current-account-field">
                                    <span>Monto pagado</span>
                                    <input type="number" name="amount" min="0.01" step="0.01" required placeholder="0,00" data-rkm-payment-amount>
                                </label>

                                <label class="rkm-current-account-field">
                                    <span>Fecha de pago</span>
                                    <input type="date" name="payment_date" max="<?php echo esc_attr($today); ?>" value="<?php echo esc_attr($today); ?>" required>
                                </label>
                            </div>

                            <div class="rkm-current-account-form__row">
                                <label class="rkm-current-account-field">
                                    <span>Forma de pago</span>
                                    <select name="payment_method_id" required>
                                        <option value="">Seleccionar</option>
                                        <?php foreach ($payment_methods as $method) : ?>
                                            <option value="<?php echo esc_attr($method['id']); ?>"><?php echo esc_html($method['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                            </div>

                            <label class="rkm-current-account-field">
                                <span>Referencia / comprobante</span>
                                <input type="text" name="reference" maxlength="160" placeholder="Ej: numero de transferencia o captura enviada">
                            </label>

                            <label class="rkm-current-account-field rkm-current-account-file">
                                <span>Comprobante adjunto</span>
                                <input
                                    type="file"
                                    name="receipt"
                                    required
                                    accept="<?php echo esc_attr($current_account ? $current_account->get_receipt_accept_attribute() : '.jpg,.jpeg,.png,.pdf'); ?>"
                                    data-rkm-payment-receipt
                                >
                                <small>Formatos permitidos: JPG, PNG o PDF. Tamano maximo: 5 MB.</small>
                            </label>

                            <label class="rkm-current-account-field">
                                <span>Observacion</span>
                                <textarea name="note" rows="4" placeholder="Detalle adicional para administracion"></textarea>
                            </label>

                            <div class="rkm-current-account-form__feedback" data-rkm-current-account-feedback></div>

                            <button type="submit" class="rkm-btn rkm-btn--primary">Informar pago</button>
                        </form>
                    <?php endif; ?>
                </section>

                <section class="rkm-card rkm-current-account-panel">
                    <div class="rkm-current-account-panel__header">
                        <h2>Pedidos con saldo</h2>
                        <p>Base calculada desde el saldo pendiente guardado en cada pedido.</p>
                    </div>

                    <?php if (empty($pending_orders)) : ?>
                        <div class="rkm-current-account-empty">
                            <strong>Sin deuda pendiente</strong>
                            <p>No hay pedidos con saldo a credito para mostrar.</p>
                        </div>
                    <?php else : ?>
                        <div class="rkm-current-account-order-list">
                            <?php foreach ($pending_orders as $order) : ?>
                                <?php
                                $balance = $current_account ? $current_account->get_order_credit_balance($order) : 0;
                                $date = $order->get_date_created() ? $order->get_date_created()->date_i18n('d/m/Y') : '-';
                                ?>
                                <article class="rkm-current-account-order">
                                    <div>
                                        <span>Pedido #<?php echo esc_html($order->get_order_number()); ?></span>
                                        <small><?php echo esc_html($date); ?></small>
                                    </div>
                                    <strong><?php echo $current_account ? wp_kses_post($current_account->format_money($balance)) : esc_html((string) $balance); ?></strong>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </div>

            <section class="rkm-card rkm-current-account-panel rkm-current-account-history">
                <div class="rkm-current-account-panel__header">
                    <h2>Historial de pagos informados</h2>
                    <p>Seguimiento de pagos pendientes, aprobados y rechazados.</p>
                </div>

                <?php if (empty($payment_reports)) : ?>
                    <div class="rkm-current-account-empty">
                        <strong>Sin pagos informados</strong>
                        <p>Todavia no registraste pagos para validar.</p>
                    </div>
                <?php else : ?>
                    <div class="rkm-current-account-table-wrap">
                        <table class="rkm-current-account-table">
                            <thead>
                                <tr>
                                    <th>Pedido</th>
                                    <th>Monto</th>
                                    <th>Fecha pago</th>
                                    <th>Forma</th>
                                    <th>Referencia</th>
                                    <th>Comprobante</th>
                                    <th>Estado</th>
                                    <th>Fecha</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payment_reports as $report) : ?>
                                    <?php $status = $report['status'] ?: 'pending'; ?>
                                    <tr>
                                        <td>#<?php echo esc_html($report['order_number']); ?></td>
                                        <td><?php echo $current_account ? wp_kses_post($current_account->format_money($report['amount'])) : esc_html((string) $report['amount']); ?></td>
                                        <td><?php echo esc_html($current_account ? $current_account->format_payment_date($report['payment_date']) : $report['payment_date']); ?></td>
                                        <td><?php echo esc_html($report['payment_method_label']); ?></td>
                                        <td><?php echo esc_html($report['reference'] !== '' ? $report['reference'] : '-'); ?></td>
                                        <td>
                                            <?php if (!empty($report['receipt_url'])) : ?>
                                                <a class="rkm-current-account-receipt-link" href="<?php echo esc_url($report['receipt_url']); ?>" target="_blank" rel="noopener">Ver comprobante</a>
                                            <?php else : ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="rkm-current-account-badge rkm-current-account-badge--<?php echo esc_attr($status); ?>">
                                                <?php echo esc_html($status_labels[$status] ?? ucfirst($status)); ?>
                                            </span>
                                        </td>
                                        <td><?php echo esc_html($current_account ? $current_account->format_date($report['created_at']) : $report['created_at']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </div>
</div>
