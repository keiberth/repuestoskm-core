(function () {
    const form = document.querySelector('[data-rkm-products-form]');

    if (!form) {
        return;
    }

    const fileInput = form.querySelector('[data-rkm-product-image]');
    const fileLabel = form.querySelector('[data-rkm-product-image-label]');
    const galleryInput = form.querySelector('[data-rkm-product-gallery]');
    const galleryLabel = form.querySelector('[data-rkm-product-gallery-label]');
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

    if (galleryInput) {
        galleryInput.addEventListener('change', () => {
            setFeedback('');

            if (!galleryInput.files.length) {
                if (galleryLabel) {
                    galleryLabel.textContent = 'JPG, PNG o WEBP. Maximo 5 MB por imagen.';
                }

                return;
            }

            if (galleryLabel) {
                galleryLabel.textContent = `${galleryInput.files.length} imagen(es) seleccionada(s)`;
            }
        });
    }

    form.addEventListener('submit', (event) => {
        const selectedFiles = [];

        if (fileInput && fileInput.files.length) {
            selectedFiles.push(fileInput.files[0]);
        }

        if (galleryInput && galleryInput.files.length) {
            selectedFiles.push(...galleryInput.files);
        }

        if (!selectedFiles.length) {
            return;
        }

        for (const file of selectedFiles) {
            if (!allowedTypes.includes(file.type)) {
                event.preventDefault();
                setFeedback('Las imagenes deben ser JPG, PNG o WEBP.');
                return;
            }

            if (file.size > maxSize) {
                event.preventDefault();
                setFeedback('Cada imagen debe pesar como maximo 5 MB.');
                return;
            }
        }
    });
})();
