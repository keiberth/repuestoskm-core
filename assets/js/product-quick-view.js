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
            return;
        }

        mainImage.src = item.full || item.thumb || "";
        mainImage.alt = item.alt || title.textContent || "Producto";

        thumbs.querySelectorAll(".rkm-product-quick-view__thumb").forEach((thumbButton, index) => {
            const isActive = index === activeIndex;
            thumbButton.classList.toggle("is-active", isActive);
            thumbButton.setAttribute("aria-pressed", isActive ? "true" : "false");
        });
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
        document.body.classList.add("rkm-product-quick-view-open");

        const closeButton = modal.querySelector(".rkm-product-quick-view__close");
        if (closeButton) {
            closeButton.focus();
        }
    }

    function closeModal() {
        modal.classList.remove("is-active");
        modal.setAttribute("aria-hidden", "true");
        document.body.classList.remove("rkm-product-quick-view-open");

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
