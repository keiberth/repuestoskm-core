(function () {
    const form = document.querySelector('[data-rkm-products-form]');

    if (!form) {
        return;
    }

    const fileInput = form.querySelector('[data-rkm-product-image]');
    const fileLabel = form.querySelector('[data-rkm-product-image-label]');
    const feedback = form.querySelector('[data-rkm-products-feedback]');
    const maxSize = 5 * 1024 * 1024;
    const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];

    const setFeedback = (message) => {
        if (!feedback) {
            return;
        }

        feedback.textContent = message || '';
        feedback.classList.toggle('is-visible', Boolean(message));
    };

    if (fileInput) {
        fileInput.addEventListener('change', () => {
            setFeedback('');

            if (!fileInput.files.length) {
                if (fileLabel) {
                    fileLabel.textContent = 'JPG, PNG o WEBP. Maximo 5 MB.';
                }

                return;
            }

            const file = fileInput.files[0];

            if (fileLabel) {
                fileLabel.textContent = file.name;
            }
        });
    }

    form.addEventListener('submit', (event) => {
        if (!fileInput || !fileInput.files.length) {
            return;
        }

        const file = fileInput.files[0];

        if (!allowedTypes.includes(file.type)) {
            event.preventDefault();
            setFeedback('La imagen debe ser JPG, PNG o WEBP.');
            return;
        }

        if (file.size > maxSize) {
            event.preventDefault();
            setFeedback('La imagen no puede superar 5 MB.');
        }
    });
})();
