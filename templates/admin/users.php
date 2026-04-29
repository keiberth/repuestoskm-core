<?php
if (!defined('ABSPATH')) {
    exit;
}

$page_title = isset($data['page_title']) ? $data['page_title'] : 'Gestion de usuarios';
$page_subtitle = isset($data['page_subtitle']) ? $data['page_subtitle'] : '';
$notice = isset($data['admin_users_notice']) ? $data['admin_users_notice'] : null;
$form = isset($data['admin_users_form']) && is_array($data['admin_users_form']) ? $data['admin_users_form'] : [];
$users = isset($data['admin_users_rows']) && is_array($data['admin_users_rows']) ? $data['admin_users_rows'] : [];
$roles = isset($data['admin_users_roles']) && is_array($data['admin_users_roles']) ? $data['admin_users_roles'] : [];
$edit_user = isset($data['admin_users_edit_user']) && is_array($data['admin_users_edit_user']) ? $data['admin_users_edit_user'] : null;
$is_editing = !empty($edit_user);
$selected_role = isset($form['role']) ? $form['role'] : 'customer';
$selected_role_description = '';

foreach ($roles as $role_option) {
    if ($role_option['value'] === $selected_role) {
        $selected_role_description = $role_option['description'];
        break;
    }
}
?>

<div class="rkm-app">
    <div class="rkm-container rkm-admin-users-page">
        <?php include plugin_dir_path(__FILE__) . '../partials/private-header.php'; ?>

        <div class="rkm-page-header rkm-admin-users-page__header">
            <div>
                <h1><?php echo esc_html($page_title); ?></h1>
                <p><?php echo esc_html($page_subtitle); ?></p>
            </div>

            <div class="rkm-admin-users-page__actions">
                <a class="rkm-admin-users-page__back" href="<?php echo esc_url(home_url('/mi-cuenta/panel/')); ?>">
                    Volver al panel admin
                </a>
            </div>
        </div>

        <section class="rkm-card rkm-admin-users-hero">
            <div class="rkm-admin-users-hero__copy">
                <span class="rkm-admin-users-hero__eyebrow">Administracion interna</span>
                <h2>Alta rapida de usuarios y roles</h2>
                <p>
                    Crea clientes, vendedores o administradores desde el sistema RKM y mantene visible la base
                    operativa del equipo en una sola pantalla.
                </p>
            </div>
        </section>

        <?php if (!empty($notice['message'])) : ?>
            <div class="rkm-admin-users-notice rkm-admin-users-notice--<?php echo esc_attr($notice['type']); ?>">
                <p><?php echo esc_html($notice['message']); ?></p>
            </div>
        <?php endif; ?>

        <div class="rkm-admin-users-layout">
            <section class="rkm-card rkm-admin-users-panel">
                <div class="rkm-admin-users-panel__header">
                    <h3>Crear usuario</h3>
                    <p>
                        El alta usa funciones nativas de WordPress y asigna el rol en el momento de crear la cuenta.
                    </p>
                </div>

                <form class="rkm-admin-users-form" method="post" data-rkm-admin-users-form>
                    <input type="hidden" name="rkm_admin_users_action" value="create_user">
                    <?php wp_nonce_field('rkm_admin_users_create', 'rkm_admin_users_nonce'); ?>

                    <div class="rkm-admin-users-form__grid">
                        <label class="rkm-admin-users-form__field">
                            <span>Nombre</span>
                            <input type="text" name="first_name" value="<?php echo esc_attr($form['first_name'] ?? ''); ?>" required>
                        </label>

                        <label class="rkm-admin-users-form__field">
                            <span>Apellido</span>
                            <input type="text" name="last_name" value="<?php echo esc_attr($form['last_name'] ?? ''); ?>" required>
                        </label>

                        <label class="rkm-admin-users-form__field">
                            <span>Correo electronico</span>
                            <input type="email" name="email" value="<?php echo esc_attr($form['email'] ?? ''); ?>" required>
                        </label>

                        <label class="rkm-admin-users-form__field">
                            <span>Usuario</span>
                            <input type="text" name="username" value="<?php echo esc_attr($form['username'] ?? ''); ?>" required>
                        </label>

                        <label class="rkm-admin-users-form__field">
                            <span>Contrasena</span>
                            <input type="password" name="password" autocomplete="new-password" required>
                        </label>

                        <label class="rkm-admin-users-form__field">
                            <span>Rol</span>
                            <select name="role" id="rkm_admin_user_role" required>
                                <?php foreach ($roles as $role_option) : ?>
                                    <option
                                        value="<?php echo esc_attr($role_option['value']); ?>"
                                        data-description="<?php echo esc_attr($role_option['description']); ?>"
                                        <?php selected($selected_role, $role_option['value']); ?>
                                    >
                                        <?php echo esc_html($role_option['label']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>

                    <p class="rkm-admin-users-form__hint" data-rkm-admin-role-hint>
                        <?php echo esc_html($selected_role_description); ?>
                    </p>

                    <div class="rkm-admin-users-form__footer">
                        <button
                            type="submit"
                            class="rkm-admin-users-form__submit"
                            data-rkm-admin-users-submit
                            data-loading-label="Creando usuario..."
                        >
                            Crear usuario
                        </button>
                    </div>
                </form>
            </section>

            <section class="rkm-card rkm-admin-users-panel rkm-admin-users-panel--list">
                <div class="rkm-admin-users-panel__header">
                    <h3>Usuarios existentes</h3>
                    <p>Vista simple de la base actual para verificar nombres, roles y estado operativo.</p>
                </div>

                <div class="rkm-admin-users-table-wrap">
                    <table class="rkm-admin-users-table">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Email</th>
                                <th>Rol</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($users)) : ?>
                                <?php foreach ($users as $row) : ?>
                                    <tr class="<?php echo $is_editing && (int) $edit_user['id'] === (int) $row['id'] ? 'is-editing' : ''; ?>">
                                        <td data-label="Nombre">
                                            <strong><?php echo esc_html($row['name']); ?></strong>
                                            <span>@<?php echo esc_html($row['username']); ?></span>
                                        </td>
                                        <td data-label="Email"><?php echo esc_html($row['email']); ?></td>
                                        <td data-label="Rol">
                                            <span class="rkm-admin-users-role rkm-admin-users-role--<?php echo esc_attr(sanitize_html_class($row['role_slug'])); ?>">
                                                <?php echo esc_html($row['role']); ?>
                                            </span>
                                        </td>
                                        <td data-label="Estado">
                                            <span class="rkm-admin-users-status rkm-admin-users-status--<?php echo esc_attr(strtolower($row['status'])); ?>">
                                                <?php echo esc_html($row['status']); ?>
                                            </span>
                                        </td>
                                        <td data-label="Acciones">
                                            <button
                                                type="button"
                                                class="rkm-admin-users-table__action"
                                                data-rkm-edit-user
                                                data-user-id="<?php echo esc_attr($row['id']); ?>"
                                                data-first-name="<?php echo esc_attr($row['first_name']); ?>"
                                                data-last-name="<?php echo esc_attr($row['last_name']); ?>"
                                                data-email="<?php echo esc_attr($row['email']); ?>"
                                                data-username="<?php echo esc_attr($row['username']); ?>"
                                                data-role="<?php echo esc_attr($row['role_slug']); ?>"
                                            >
                                                Editar
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <tr>
                                    <td colspan="5" class="rkm-admin-users-table__empty">
                                        Todavia no hay usuarios para mostrar en esta vista.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>

    <div
        class="rkm-admin-users-modal<?php echo $is_editing ? ' is-open' : ''; ?>"
        data-rkm-edit-modal
        aria-hidden="<?php echo $is_editing ? 'false' : 'true'; ?>"
    >
        <div class="rkm-admin-users-modal__overlay" data-rkm-edit-modal-close></div>
        <section
            class="rkm-admin-users-modal__dialog"
            role="dialog"
            aria-modal="true"
            aria-labelledby="rkm-admin-users-edit-title"
        >
            <button
                type="button"
                class="rkm-admin-users-modal__close"
                data-rkm-edit-modal-close
                aria-label="Cerrar edicion"
            >
                &times;
            </button>

            <div class="rkm-admin-users-modal__header">
                <span class="rkm-admin-users-modal__eyebrow">Administrador</span>
                <h3 id="rkm-admin-users-edit-title">Editar usuario</h3>
                <p>Actualiza datos principales, rol y contrasena opcional usando usuarios nativos de WordPress.</p>
            </div>

            <form class="rkm-admin-users-form" method="post" data-rkm-admin-users-edit-form>
                <input type="hidden" name="rkm_admin_users_action" value="update_user">
                <input type="hidden" name="user_id" data-rkm-edit-user-id value="<?php echo esc_attr($edit_user['id'] ?? ''); ?>">
                <?php wp_nonce_field('rkm_admin_users_update', 'rkm_admin_users_nonce'); ?>

                <div class="rkm-admin-users-form__grid">
                    <label class="rkm-admin-users-form__field">
                        <span>Nombre</span>
                        <input type="text" name="first_name" data-rkm-edit-first-name value="<?php echo esc_attr($edit_user['first_name'] ?? ''); ?>" required>
                    </label>

                    <label class="rkm-admin-users-form__field">
                        <span>Apellido</span>
                        <input type="text" name="last_name" data-rkm-edit-last-name value="<?php echo esc_attr($edit_user['last_name'] ?? ''); ?>" required>
                    </label>

                    <label class="rkm-admin-users-form__field">
                        <span>Correo electronico</span>
                        <input type="email" name="email" data-rkm-edit-email value="<?php echo esc_attr($edit_user['email'] ?? ''); ?>" required>
                    </label>

                    <label class="rkm-admin-users-form__field">
                        <span>Usuario</span>
                        <input type="text" name="username" data-rkm-edit-username value="<?php echo esc_attr($edit_user['username'] ?? ''); ?>" readonly aria-readonly="true">
                    </label>

                    <label class="rkm-admin-users-form__field">
                        <span>Nueva contrasena</span>
                        <input type="password" name="password" autocomplete="new-password" placeholder="Dejar vacio para mantenerla">
                    </label>

                    <label class="rkm-admin-users-form__field">
                        <span>Rol</span>
                        <select name="role" data-rkm-edit-role required>
                            <?php foreach ($roles as $role_option) : ?>
                                <option
                                    value="<?php echo esc_attr($role_option['value']); ?>"
                                    data-description="<?php echo esc_attr($role_option['description']); ?>"
                                    <?php selected($edit_user['role'] ?? 'customer', $role_option['value']); ?>
                                >
                                    <?php echo esc_html($role_option['label']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>

                <p class="rkm-admin-users-form__hint" data-rkm-admin-edit-role-hint></p>

                <div class="rkm-admin-users-form__footer">
                    <button
                        type="submit"
                        class="rkm-admin-users-form__submit"
                        data-rkm-admin-users-submit
                        data-loading-label="Guardando cambios..."
                    >
                        Guardar cambios
                    </button>
                    <button type="button" class="rkm-admin-users-form__cancel" data-rkm-edit-modal-close>
                        Cancelar
                    </button>
                </div>
            </form>
        </section>
    </div>
</div>
