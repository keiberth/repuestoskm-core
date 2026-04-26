<?php
if (!defined('ABSPATH')) {
    exit;
}

$page_title = $data['page_title'] ?? 'Productos';
$page_subtitle = $data['page_subtitle'] ?? '';
$notice = $data['products_notice'] ?? null;
$view = $data['view'] ?? 'list';
$list_url = $data['list_url'] ?? home_url('/mi-cuenta/panel/?section=productos');
$create_url = $data['create_url'] ?? home_url('/mi-cuenta/panel/?section=productos&view=create');
$subtemplate = RKM_CORE_PATH . 'templates/admin/products/list.php';

if ($view === 'create') {
    $subtemplate = RKM_CORE_PATH . 'templates/admin/products/create.php';
} elseif ($view === 'edit') {
    $subtemplate = RKM_CORE_PATH . 'templates/admin/products/edit.php';
} elseif ($view === 'detail') {
    $subtemplate = RKM_CORE_PATH . 'templates/admin/products/detail.php';
}
?>

<div class="rkm-app rkm-module-app">
    <div class="rkm-container rkm-admin-products-page">
        <?php include plugin_dir_path(__FILE__) . '../partials/private-header.php'; ?>

        <div class="rkm-page-header rkm-admin-products-page__header">
            <div>
                <h1><?php echo esc_html($page_title); ?></h1>
                <p><?php echo esc_html($page_subtitle); ?></p>
            </div>

            <div class="rkm-admin-products-page__actions">
                <a class="rkm-admin-products-page__back" href="<?php echo esc_url(home_url('/mi-cuenta/panel/')); ?>">Panel admin</a>
                <a class="rkm-admin-products-page__back <?php echo $view === 'list' ? 'is-active' : ''; ?>" href="<?php echo esc_url($list_url); ?>">Publicaciones</a>
                <a class="rkm-admin-products-page__primary" href="<?php echo esc_url($create_url); ?>">Nueva publicacion</a>
            </div>
        </div>

        <div class="rkm-module-shell rkm-admin-products-shell">
            <?php if (!empty($notice['message'])) : ?>
                <div class="rkm-admin-products-notice rkm-admin-products-notice--<?php echo esc_attr($notice['type']); ?>">
                    <p><?php echo esc_html($notice['message']); ?></p>
                </div>
            <?php endif; ?>

            <?php if (file_exists($subtemplate)) : ?>
                <?php include $subtemplate; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
