document.addEventListener("DOMContentLoaded", function () {
    const modal = document.getElementById("rkmProductQuickView");
    const cards = document.querySelectorAll(".rkm-product-card--interactive");

    if (!modal || !cards.length) {
        return;
    }

    const closeButtons = modal.querySelectorAll("[data-rkm-product-quick-view-close]");
    const mainImage = document.getElementById("rkmProductQuickViewMainImage");
    const thumbs = document.getElementById("rkmProductQuickViewThumbs");
    const title = document.getElementById("rkmProductQuickViewTitle");
    const sku = document.getElementById("rkmProductQuickViewSku");
    const price = document.getElementById("rkmProductQuickViewPrice");
    const stock = document.getElementById("rkmProductQuickViewStock");
    const description = document.getElementById("rkmProductQuickViewDescription");
    const primaryAction = document.getElementById("rkmProductQuickViewPrimaryAction");

    let lastTrigger = null;
    let scrollPosition = 0;
    let imageTransitionToken = 0;

    function parseGallery(rawValue) {
        if (!rawValue) {
            return [];
        }

        try {
            const items = JSON.parse(rawValue);

            if (!Array.isArray(items)) {
                return [];
            }

            return items.filter((item) => item && (item.full || item.thumb));
        } catch (error) {
            return [];
        }
    }

    function setActiveImage(gallery, activeIndex) {
        const item = gallery[activeIndex] || gallery[0];

        if (!item) {
            mainImage.removeAttribute("src");
            mainImage.alt = "";
            mainImage.classList.remove("is-changing");
            return;
        }

        const currentToken = ++imageTransitionToken;
        const nextSrc = item.full || item.thumb || "";
        const nextAlt = item.alt || title.textContent || "Producto";

        mainImage.classList.add("is-changing");

        mainImage.src = nextSrc;
        mainImage.alt = nextAlt;

        const finishImageTransition = function () {
            if (currentToken !== imageTransitionToken) {
                return;
            }

            window.setTimeout(function () {
                window.requestAnimationFrame(function () {
                    if (currentToken === imageTransitionToken) {
                        mainImage.classList.remove("is-changing");
                    }
                });
            }, 90);
        };

        if (mainImage.complete) {
            finishImageTransition();
        } else {
            mainImage.addEventListener("load", finishImageTransition, { once: true });
            mainImage.addEventListener("error", finishImageTransition, { once: true });
        }

        thumbs.querySelectorAll(".rkm-product-quick-view__thumb").forEach((thumbButton, index) => {
            const isActive = index === activeIndex;
            thumbButton.classList.toggle("is-active", isActive);
            thumbButton.setAttribute("aria-pressed", isActive ? "true" : "false");
        });
    }

    function lockBodyScroll() {
        scrollPosition = window.scrollY || window.pageYOffset || 0;
        document.body.style.top = `-${scrollPosition}px`;
        document.body.classList.add("rkm-product-quick-view-open");
    }

    function unlockBodyScroll() {
        document.body.classList.remove("rkm-product-quick-view-open");
        document.body.style.top = "";
        window.scrollTo(0, scrollPosition);
    }

    function renderThumbs(gallery) {
        thumbs.innerHTML = "";

        gallery.forEach((item, index) => {
            const button = document.createElement("button");
            const image = document.createElement("img");

            button.type = "button";
            button.className = "rkm-product-quick-view__thumb";
            button.setAttribute("aria-label", `Ver imagen ${index + 1}`);

            image.src = item.thumb || item.full || "";
            image.alt = item.alt || title.textContent || "Producto";

            button.appendChild(image);
            button.addEventListener("click", function () {
                setActiveImage(gallery, index);
            });

            thumbs.appendChild(button);
        });

        setActiveImage(gallery, 0);
    }

    function populateModal(card) {
        const gallery = parseGallery(card.dataset.productGallery);

        title.textContent = card.dataset.productName || "Producto";
        sku.textContent = card.dataset.productSku || "Sin SKU";
        price.textContent = card.dataset.productPrice || "Sin precio";
        stock.textContent = card.dataset.productStock || "Sin stock";
        description.textContent = card.dataset.productDescription || "Este producto no tiene descripcion corta.";
        primaryAction.href = card.dataset.productUrl || primaryAction.href;

        renderThumbs(gallery);
    }

    function openModal(card) {
        lastTrigger = card;
        populateModal(card);

        modal.classList.add("is-active");
        modal.setAttribute("aria-hidden", "false");
        lockBodyScroll();

        const closeButton = modal.querySelector(".rkm-product-quick-view__close");
        if (closeButton) {
            closeButton.focus();
        }
    }

    function closeModal() {
        modal.classList.remove("is-active");
        modal.setAttribute("aria-hidden", "true");
        unlockBodyScroll();

        if (lastTrigger) {
            lastTrigger.focus();
        }
    }

    function shouldIgnoreTrigger(target) {
        return Boolean(target.closest("a, button, input, select, textarea, label"));
    }

    cards.forEach((card) => {
        card.addEventListener("click", function (event) {
            if (shouldIgnoreTrigger(event.target)) {
                return;
            }

            openModal(card);
        });

        card.addEventListener("keydown", function (event) {
            if (event.key !== "Enter" && event.key !== " ") {
                return;
            }

            if (shouldIgnoreTrigger(event.target) && event.target !== card) {
                return;
            }

            event.preventDefault();
            openModal(card);
        });
    });

    closeButtons.forEach((button) => {
        button.addEventListener("click", closeModal);
    });

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape" && modal.classList.contains("is-active")) {
            closeModal();
        }
    });
});
