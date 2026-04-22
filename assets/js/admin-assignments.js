document.addEventListener('DOMContentLoaded', function () {
    var forms = document.querySelectorAll('[data-rkm-assignment-form]');

    forms.forEach(function (form) {
        form.addEventListener('submit', function () {
            var submit = form.querySelector('[data-rkm-assignment-submit]');

            if (!submit) {
                return;
            }

            submit.classList.add('is-loading');
            submit.setAttribute('aria-disabled', 'true');
            submit.textContent = submit.getAttribute('data-loading-label') || submit.textContent;
        });
    });
});
