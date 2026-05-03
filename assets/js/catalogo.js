document.addEventListener("DOMContentLoaded", function () {
    const modal = document.getElementById("rkmCatalogProductModal");
    const triggers = document.querySelectorAll(".rkm-catalog-view-product");

    if (!modal || !triggers.length) {
        return;
    }

    const closeButtons = modal.querySelectorAll("[data-rkm-catalog-modal-close]");
    const image = document.getElementById("rkmCatalogModalImage");
    const title = document.getElementById("rkmCatalogModalTitle");
    const sku = document.getElementById("rkmCatalogModalSku");
    const price = document.getElementById("rkmCatalogModalPrice");
    const stock = document.getElementById("rkmCatalogModalStock");
    const description = document.getElementById("rkmCatalogModalDescription");
    let lastTrigger = null;

    function setText(element, value, fallback) {
        if (!element) {
            return;
        }

        element.textContent = value || fallback || "";
    }

    function populateModal(trigger) {
        const productName = trigger.dataset.productName || "Producto";
        const productImage = trigger.dataset.productImage || "";

        setText(title, productName, "Producto");
        setText(sku, trigger.dataset.productSku, "Sin SKU");
        setText(price, trigger.dataset.productPrice, "Sin precio");
        setText(stock, trigger.dataset.productStock, "Sin stock");
        setText(description, trigger.dataset.productDescription, "Producto disponible para consultar.");

        if (image) {
            image.src = productImage;
            image.alt = productName;
        }
    }

    function openModal(trigger) {
        lastTrigger = trigger;
        populateModal(trigger);
        modal.classList.add("is-active");
        modal.setAttribute("aria-hidden", "false");
        document.body.classList.add("rkm-catalog-modal-open");

        const closeButton = modal.querySelector(".rkm-catalog-modal__close");
        if (closeButton) {
            closeButton.focus();
        }
    }

    function closeModal() {
        modal.classList.remove("is-active");
        modal.setAttribute("aria-hidden", "true");
        document.body.classList.remove("rkm-catalog-modal-open");

        if (lastTrigger) {
            lastTrigger.focus();
        }
    }

    triggers.forEach(function (trigger) {
        trigger.addEventListener("click", function () {
            openModal(trigger);
        });
    });

    closeButtons.forEach(function (button) {
        button.addEventListener("click", closeModal);
    });

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape" && modal.classList.contains("is-active")) {
            closeModal();
        }
    });
});
