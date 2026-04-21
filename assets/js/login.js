document.addEventListener('DOMContentLoaded', function () {
    function cleanupWooPasswordToggles() {
        var wrappers = document.querySelectorAll('.rkm-login-form__password-input .password-input');

        wrappers.forEach(function (wrapper) {
            var rogueButtons = wrapper.querySelectorAll('.show-password-input');

            rogueButtons.forEach(function (button) {
                button.remove();
            });

            var input = wrapper.querySelector('input');
            var parent = wrapper.parentElement;

            if (!input || !parent || !parent.classList.contains('rkm-login-form__password-input')) {
                return;
            }

            parent.insertBefore(input, wrapper);
            wrapper.remove();
        });
    }

    cleanupWooPasswordToggles();
    window.setTimeout(cleanupWooPasswordToggles, 0);

    var observer = new MutationObserver(function () {
        cleanupWooPasswordToggles();
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true
    });

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
            submit.setAttribute('aria-disabled', 'true');
            submit.dataset.originalLabel = submit.textContent;
            submit.textContent = submit.getAttribute('data-rkm-submit-label') || submit.textContent;
        });
    });
});
