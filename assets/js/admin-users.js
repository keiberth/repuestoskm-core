document.addEventListener('DOMContentLoaded', function () {
    var page = document.querySelector('.rkm-app .rkm-admin-users-page');

    if (!page) {
        return;
    }

    var forms = page.querySelectorAll('[data-rkm-admin-users-form], [data-rkm-admin-users-edit-form]');
    var roleSelect = page.querySelector('#rkm_admin_user_role');
    var roleHint = page.querySelector('[data-rkm-admin-role-hint]');
    var modal = page.querySelector('[data-rkm-edit-modal]');
    var editButtons = page.querySelectorAll('[data-rkm-edit-user]');
    var editRoleSelect = modal ? modal.querySelector('[data-rkm-edit-role]') : null;
    var editRoleHint = modal ? modal.querySelector('[data-rkm-admin-edit-role-hint]') : null;

    function syncHint(select, hint) {
        if (!select || !hint) {
            return;
        }

        var selectedOption = select.options[select.selectedIndex];

        if (!selectedOption) {
            return;
        }

        hint.textContent = selectedOption.getAttribute('data-description') || '';
    }

    function openModal() {
        if (!modal) {
            return;
        }

        modal.hidden = false;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.documentElement.classList.add('rkm-admin-users-modal-open');
        document.body.classList.add('rkm-admin-users-modal-open');

        var firstInput = modal.querySelector('input:not([type="hidden"]):not([readonly])');

        if (firstInput) {
            firstInput.focus();
        }
    }

    function closeModal() {
        if (!modal) {
            return;
        }

        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        modal.hidden = true;
        document.documentElement.classList.remove('rkm-admin-users-modal-open');
        document.body.classList.remove('rkm-admin-users-modal-open');
    }

    function setField(selector, value) {
        if (!modal) {
            return;
        }

        var field = modal.querySelector(selector);

        if (field) {
            field.value = value || '';
        }
    }

    syncHint(roleSelect, roleHint);
    syncHint(editRoleSelect, editRoleHint);

    if (roleSelect) {
        roleSelect.addEventListener('change', function () {
            syncHint(roleSelect, roleHint);
        });
    }

    if (editRoleSelect) {
        editRoleSelect.addEventListener('change', function () {
            syncHint(editRoleSelect, editRoleHint);
        });
    }

    editButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            setField('[data-rkm-edit-user-id]', button.getAttribute('data-user-id'));
            setField('[data-rkm-edit-first-name]', button.getAttribute('data-first-name'));
            setField('[data-rkm-edit-last-name]', button.getAttribute('data-last-name'));
            setField('[data-rkm-edit-email]', button.getAttribute('data-email'));
            setField('[data-rkm-edit-username]', button.getAttribute('data-username'));

            if (editRoleSelect) {
                editRoleSelect.value = button.getAttribute('data-role') || 'customer';
                syncHint(editRoleSelect, editRoleHint);
            }

            openModal();
        });
    });

    if (modal && modal.classList.contains('is-open')) {
        openModal();
        syncHint(editRoleSelect, editRoleHint);
    }

    page.querySelectorAll('[data-rkm-edit-modal-close]').forEach(function (button) {
        button.addEventListener('click', closeModal);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal && modal.classList.contains('is-open')) {
            closeModal();
        }
    });

    forms.forEach(function (form) {
        form.addEventListener('submit', function () {
            var submit = form.querySelector('[data-rkm-admin-users-submit]');

            if (!submit) {
                return;
            }

            submit.classList.add('is-loading');
            submit.setAttribute('aria-disabled', 'true');
            submit.textContent = submit.getAttribute('data-loading-label') || submit.textContent;
        });
    });
});
