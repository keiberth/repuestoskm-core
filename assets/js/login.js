document.addEventListener('DOMContentLoaded', function () {
    var toggleButtons = document.querySelectorAll('[data-rkm-password-toggle]');

    toggleButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            var targetId = button.getAttribute('data-target');
            var input = targetId ? document.getElementById(targetId) : null;

            if (!input) {
                return;
            }

            var isPassword = input.getAttribute('type') === 'password';

            input.setAttribute('type', isPassword ? 'text' : 'password');
            button.setAttribute('aria-pressed', isPassword ? 'true' : 'false');
            button.setAttribute('aria-label', isPassword ? 'Ocultar contraseña' : 'Mostrar contraseña');
            button.classList.toggle('is-active', isPassword);

            var label = button.querySelector('.rkm-login-form__password-toggle-label');

            if (label) {
                label.textContent = isPassword ? 'Ocultar' : 'Mostrar';
            }
        });
    });

    var authForms = document.querySelectorAll('.rkm-login-form, .rkm-register-form');

    authForms.forEach(function (form) {
        form.addEventListener('submit', function () {
            var submit = form.querySelector('[data-rkm-submit-label]');

            if (!submit) {
                return;
            }

            submit.classList.add('is-loading');
            submit.disabled = true;
            submit.dataset.originalLabel = submit.textContent;
            submit.textContent = submit.getAttribute('data-rkm-submit-label') || submit.textContent;
        });
    });
});
