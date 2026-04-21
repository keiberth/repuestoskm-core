document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('[data-rkm-admin-users-form]');
    var roleSelect = document.getElementById('rkm_admin_user_role');
    var roleHint = document.querySelector('[data-rkm-admin-role-hint]');

    function syncRoleHint() {
        if (!roleSelect || !roleHint) {
            return;
        }

        var selectedOption = roleSelect.options[roleSelect.selectedIndex];

        if (!selectedOption) {
            return;
        }

        roleHint.textContent = selectedOption.getAttribute('data-description') || '';
    }

    syncRoleHint();

    if (roleSelect) {
        roleSelect.addEventListener('change', syncRoleHint);
    }

    if (!form) {
        return;
    }

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
