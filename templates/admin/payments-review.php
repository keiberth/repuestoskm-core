<?php
if (!defined('ABSPATH')) {
    exit;
}

$page_title = $data['page_title'] ?? 'Pagos clientes';
$page_subtitle = $data['page_subtitle'] ?? '';
$payment_reports = isset($data['payment_reports']) && is_array($data['payment_reports']) ? $data['payment_reports'] : [];
$notice = $data['current_account_notice'] ?? null;
$section_url = $data['section_url'] ?? home_url('/mi-cuenta/panel/?section=pagos-clientes');
$status_labels = isset($data['status_labels']) && is_array($data['status_labels']) ? $data['status_labels'] : [];
$current_account = class_exists('RKM_Current_Account') ? new RKM_Current_Account() : null;
$pending_count = count(array_filter($payment_reports, static function ($report) {
    return ($report['status'] ?? '') === 'pending';
}));
?>

<div class="rkm-app rkm-module-app">
    <div class="rkm-container rkm-current-account-page rkm-payments-review-page">
        <?php include plugin_dir_path(__FILE__) . '../partials/private-header.php'; ?>

        <div class="rkm-page-header rkm-current-account-page__header">
            <div>
                <h1><?php echo esc_html($page_title); ?></h1>
                <p><?php echo esc_html($page_subtitle); ?></p>
            </div>

            <a class="rkm-current-account-back" href="<?php echo esc_url(home_url('/mi-cuenta/panel/')); ?>">
                Volver al panel admin
            </a>
        </div>

        <div class="rkm-module-shell rkm-current-account-shell">
            <section class="rkm-current-account-summary">
                <div>
                    <span class="rkm-current-account-summary__eyebrow">Revision administrativa</span>
                    <strong><?php echo esc_html((string) $pending_count); ?></strong>
                    <p>pagos pendientes de validacion</p>
                </div>
            </section>

            <?php if (!empty($notice['message'])) : ?>
                <div class="rkm-current-account-notice rkm-current-account-notice--<?php echo esc_attr($notice['type']); ?>">
                    <p><?php echo esc_html($notice['message']); ?></p>
                </div>
            <?php endif; ?>

            <section class="rkm-card rkm-current-account-panel rkm-current-account-history">
                <div class="rkm-current-account-panel__header">
                    <h2>Pagos informados</h2>
                    <p>Al aprobar, el sistema descuenta el monto del saldo pendiente del pedido.</p>
                </div>

                <?php if (empty($payment_reports)) : ?>
                    <div class="rkm-current-account-empty">
                        <strong>No hay pagos informados</strong>
                        <p>Cuando un cliente informe un pago, aparecera en esta revision.</p>
                    </div>
                <?php else : ?>
                    <div class="rkm-current-account-table-wrap">
                        <table class="rkm-current-account-table rkm-payments-review-table">
                            <thead>
                                <tr>
                                    <th>Cliente</th>
                                    <th>Pedido</th>
                                    <th>Monto</th>
                                    <th>Forma</th>
                                    <th>Referencia</th>
                                    <th>Estado</th>
                                    <th>Fecha</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payment_reports as $report) : ?>
                                    <?php $status = $report['status'] ?: 'pending'; ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo esc_html($report['customer_name']); ?></strong>
                                        </td>
                                        <td>
                                            #<?php echo esc_html($report['order_number']); ?>
                                            <small>Saldo: <?php echo esc_html($current_account ? wp_strip_all_tags($current_account->format_money($report['order_balance'])) : (string) $report['order_balance']); ?></small>
                                        </td>
                                        <td><?php echo $current_account ? wp_kses_post($current_account->format_money($report['amount'])) : esc_html((string) $report['amount']); ?></td>
                                        <td><?php echo esc_html($report['payment_method_label']); ?></td>
                                        <td>
                                            <?php echo esc_html($report['reference'] !== '' ? $report['reference'] : '-'); ?>
                                            <?php if ($report['note'] !== '') : ?>
                                                <small><?php echo esc_html($report['note']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="rkm-current-account-badge rkm-current-account-badge--<?php echo esc_attr($status); ?>">
                                                <?php echo esc_html($status_labels[$status] ?? ucfirst($status)); ?>
                                            </span>
                                        </td>
                                        <td><?php echo esc_html($current_account ? $current_account->format_date($report['created_at']) : $report['created_at']); ?></td>
                                        <td>
                                            <?php if ($status === 'pending') : ?>
                                                <div class="rkm-payments-review-actions">
                                                    <form method="post" action="<?php echo esc_url($section_url); ?>">
                                                        <input type="hidden" name="rkm_current_account_action" value="approve_payment">
                                                        <input type="hidden" name="report_id" value="<?php echo esc_attr((string) $report['id']); ?>">
                                                        <?php wp_nonce_field('rkm_current_account_review', 'rkm_current_account_nonce'); ?>
                                                        <button type="submit" class="rkm-current-account-action rkm-current-account-action--approve">Aprobar</button>
                                                    </form>

                                                    <form method="post" action="<?php echo esc_url($section_url); ?>">
                                                        <input type="hidden" name="rkm_current_account_action" value="reject_payment">
                                                        <input type="hidden" name="report_id" value="<?php echo esc_attr((string) $report['id']); ?>">
                                                        <?php wp_nonce_field('rkm_current_account_review', 'rkm_current_account_nonce'); ?>
                                                        <button type="submit" class="rkm-current-account-action rkm-current-account-action--reject">Rechazar</button>
                                                    </form>
                                                </div>
                                            <?php else : ?>
                                                <span class="rkm-current-account-reviewed">Revisado</span>
                                            <?php endif; ?>
                                        </td>
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
