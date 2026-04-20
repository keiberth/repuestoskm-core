const REPEAT_ORDER_STORAGE_KEY = "rkm_repeat_order_cart";
const REPEAT_ORDER_REDIRECT_DELAY = 800;

function buildRepeatOrderItems(items) {
    if (!Array.isArray(items)) {
        return [];
    }

    return items.map((item) => ({
        id: String(item.product_id || item.id || ""),
        name: item.name || "",
        price: Number(item.price_raw || 0),
        sku: item.sku || "",
        quantity: Math.max(1, Number(item.qty || 1))
    })).filter((item) => item.id);
}

function repeatOrder(items, redirectUrl) {
    try {
        const cartItems = buildRepeatOrderItems(items);

        if (!cartItems.length) {
            showOrderNotice("No se pudieron cargar los productos del pedido.", "error");
            return;
        }

        localStorage.setItem(REPEAT_ORDER_STORAGE_KEY, JSON.stringify(cartItems));
        showOrderNotice("Se cargaron los productos del pedido. Redirigiendo a nueva orden...", "success");

        window.setTimeout(() => {
            window.location.href = redirectUrl;
        }, REPEAT_ORDER_REDIRECT_DELAY);
    } catch (error) {
        console.error(error);
        showOrderNotice("No se pudo repetir el pedido.", "error");
    }
}

document.addEventListener("DOMContentLoaded", function () {
    const modal = document.getElementById("rkmOrderModal");
    if (!modal) return;

    const overlay = modal.querySelector(".rkm-modal__overlay");
    const closeBtn = modal.querySelector(".rkm-modal__close");

    const titleEl = document.getElementById("rkmOrderModalTitle");
    const metaEl = document.getElementById("rkmOrderModalMeta");
    const statusEl = document.getElementById("rkmOrderModalStatus");
    const statusDescriptionEl = document.getElementById("rkmOrderModalStatusDescription");
    const itemsEl = document.getElementById("rkmOrderModalItems");
    const totalEl = document.getElementById("rkmOrderModalTotal");
    const timelineEl = document.getElementById("rkmOrderTimeline");
    const actionsEl = document.getElementById("rkmOrderModalActions");

    const timelineSteps = [
        { key: "draft", label: "Pedido cargado" },
        { key: "pending", label: "En revisión" },
        { key: "confirmed", label: "Confirmado" },
        { key: "packing", label: "Pendiente de empaquetar" },
        { key: "dispatch_pending", label: "Pendiente de despacho" },
        { key: "shipped", label: "Despachado" },
        { key: "completed", label: "Entregado" }
    ];

    function getStepIndex(status) {
        const map = {
            "draft": 0,
            "pending": 1,
            "on-hold": 1,
            "en-revision": 1,
            "processing": 2,
            "confirmed": 2,
            "packing": 3,
            "dispatch_pending": 4,
            "shipped": 5,
            "completed": 6,
            "cancelled": -1,
            "refunded": -1,
            "failed": -1
        };

        return Object.prototype.hasOwnProperty.call(map, status) ? map[status] : 1;
    }

    function getStatusMessage(status) {
        const messages = {
            "draft": "Tu pedido fue cargado y está pendiente de revisión.",
            "pending": "Tu pedido está siendo revisado por nuestro equipo.",
            "on-hold": "Tu pedido está en espera momentáneamente.",
            "en-revision": "Tu pedido está en revisión por nuestro equipo.",
            "processing": "Tu pedido fue confirmado y se encuentra en preparación.",
            "confirmed": "Tu pedido fue confirmado correctamente.",
            "packing": "Tu pedido está pendiente de empaquetado.",
            "dispatch_pending": "Tu pedido está pendiente de despacho.",
            "shipped": "Tu pedido ya fue despachado.",
            "completed": "Tu pedido fue entregado correctamente.",
            "cancelled": "Este pedido fue cancelado.",
            "refunded": "Este pedido fue reintegrado.",
            "failed": "Este pedido no pudo procesarse correctamente."
        };

        return messages[status] || "Estado actualizado del pedido.";
    }

    function renderTimeline(status) {
        const currentIndex = getStepIndex(status);

        if (status === "cancelled") {
            timelineEl.innerHTML = `
                <div class="rkm-timeline__cancelled">Este pedido fue cancelado.</div>
            `;
            return;
        }

        if (status === "refunded") {
            timelineEl.innerHTML = `
                <div class="rkm-timeline__cancelled">Este pedido fue reintegrado.</div>
            `;
            return;
        }

        if (status === "failed") {
            timelineEl.innerHTML = `
                <div class="rkm-timeline__cancelled">Este pedido no pudo completarse.</div>
            `;
            return;
        }

        timelineEl.innerHTML = timelineSteps.map((step, index) => {
            let stateClass = "is-upcoming";

            if (index < currentIndex) stateClass = "is-done";
            if (index === currentIndex) stateClass = "is-current";

            return `
                <div class="rkm-timeline__step ${stateClass}">
                    <div class="rkm-timeline__dot"></div>
                    <div class="rkm-timeline__label">${step.label}</div>
                </div>
            `;
        }).join("");
    }

    function canRepeatOrder(status) {
        return ["completed", "cancelled", "refunded", "failed"].includes(status);
    }

    function renderActions(button, status, items) {
        if (!actionsEl) return;

        const panelNuevaOrdenUrl = `${window.location.origin}/mi-cuenta/panel/?section=nueva-orden`;

        if (!canRepeatOrder(status)) {
            actionsEl.innerHTML = "";
            return;
        }

        actionsEl.innerHTML = `
            <button type="button" class="rkm-btn rkm-btn--primary" id="rkmRepeatOrderBtn">
                Repetir pedido
            </button>
        `;

        const repeatBtn = document.getElementById("rkmRepeatOrderBtn");
        if (!repeatBtn) return;

        repeatBtn.addEventListener("click", () => {
            repeatOrder(items, panelNuevaOrdenUrl);
        });
    }

    function openModal(button) {
        const number = button.dataset.orderNumber;
        const date = button.dataset.orderDate;
        const status = button.dataset.orderStatus;
        const statusLabel = button.dataset.orderStatusLabel;
        const total = button.dataset.orderTotal;

        let items = [];
        try {
            items = JSON.parse(button.dataset.orderItems || "[]");
        } catch (e) {
            items = [];
        }

        titleEl.textContent = `Pedido #${number}`;
        metaEl.textContent = `Fecha: ${date}`;
        totalEl.textContent = total;

        statusEl.className = "rkm-order-badge";
        statusEl.classList.add(`status-${status}`);
        statusEl.textContent = statusLabel;

        if (statusDescriptionEl) {
            statusDescriptionEl.textContent = getStatusMessage(status);
        }

        if (!items.length) {
            itemsEl.innerHTML = `<p class="rkm-modal__empty">No hay productos para mostrar.</p>`;
        } else {
            itemsEl.innerHTML = items.map(item => `
                <div class="rkm-modal__item">
                    <div class="rkm-modal__item-main">
                        <div class="rkm-modal__item-name">${item.name}</div>
                        <div class="rkm-modal__item-meta">Cantidad: ${item.qty}</div>
                    </div>
                    <div class="rkm-modal__item-side">
                        <div class="rkm-modal__item-label">Subtotal</div>
                        <div class="rkm-modal__item-price">${item.subtotal}</div>
                    </div>
                </div>
            `).join("");
        }

        renderTimeline(status);
        renderActions(button, status, items);

        modal.classList.add("is-active");
        document.body.classList.add("rkm-modal-open");
    }

    function closeModal() {
        modal.classList.remove("is-active");
        document.body.classList.remove("rkm-modal-open");
    }

    document.querySelectorAll(".rkm-open-order-modal").forEach(button => {
        button.addEventListener("click", function () {
            openModal(this);
        });
    });

    overlay.addEventListener("click", closeModal);
    closeBtn.addEventListener("click", closeModal);

    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape" && modal.classList.contains("is-active")) {
            closeModal();
        }
    });
});

document.querySelectorAll(".rkm-repeat-order-btn").forEach(button => {
    button.addEventListener("click", function () {
        try {
            const items = JSON.parse(this.dataset.orderItems || "[]");
            const redirectUrl = `${window.location.origin}/mi-cuenta/panel/?section=nueva-orden`;

            repeatOrder(items, redirectUrl);
        } catch (e) {
            console.error(e);
            showOrderNotice("No se pudo repetir el pedido.", "error");
        }
    });
});

let cancelOrderId = null;

const cancelModal = document.getElementById("rkmCancelModal");

function openCancelModal(orderId) {
    if (!cancelModal) return;
    cancelOrderId = orderId;
    cancelModal.classList.add("is-active");
    document.body.classList.add("rkm-modal-open");
}

function closeCancelModal() {
    if (!cancelModal) return;
    cancelModal.classList.remove("is-active");
    document.body.classList.remove("rkm-modal-open");
    cancelOrderId = null;
}

function showOrderNotice(message, type = "success") {
    let box = document.getElementById("rkm-orders-feedback");

    if (!box) {
        box = document.createElement("div");
        box.id = "rkm-orders-feedback";
        box.className = "rkm-orders-feedback";

        const container = document.querySelector(".rkm-container");
        const subnav = container ? container.querySelector(".rkm-subnav") : null;

        if (subnav) {
            subnav.insertAdjacentElement("afterend", box);
        } else if (container) {
            container.prepend(box);
        }
    }

    box.className = `rkm-orders-feedback is-${type}`;
    box.innerHTML = `
        <div class="rkm-orders-feedback__inner">
            <div class="rkm-orders-feedback__text">${message}</div>
            <button type="button" class="rkm-orders-feedback__close" aria-label="Cerrar">×</button>
        </div>
    `;

    const closeNotice = box.querySelector(".rkm-orders-feedback__close");
    if (closeNotice) {
        closeNotice.addEventListener("click", () => box.remove());
    }
}

if (cancelModal) {
    const overlay = cancelModal.querySelector(".rkm-modal__overlay");
    const closeBtn = cancelModal.querySelector(".rkm-modal__close");
    const cancelBtn = document.getElementById("rkmCancelModalClose");
    const confirmBtn = document.getElementById("rkmConfirmCancelOrder");

    if (overlay) overlay.addEventListener("click", closeCancelModal);
    if (closeBtn) closeBtn.addEventListener("click", closeCancelModal);
    if (cancelBtn) cancelBtn.addEventListener("click", closeCancelModal);

    if (confirmBtn) {
        confirmBtn.addEventListener("click", function () {
            if (!cancelOrderId) return;

            const formData = new FormData();
            formData.append("action", "rkm_cancel_order");
            formData.append("order_id", cancelOrderId);
            formData.append("nonce", rkmOrders.nonce);

            fetch(rkmOrders.ajax_url, {
                method: "POST",
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                closeCancelModal();

                if (data.success) {
                    window.location.reload();
                } else {
                    showOrderNotice(data.data?.message || "No se pudo cancelar el pedido.", "error");
                }
            })
            .catch(() => {
                closeCancelModal();
                showOrderNotice("Ocurrió un error al cancelar el pedido.", "error");
            });
        });
    }
}

document.querySelectorAll(".rkm-cancel-order-btn").forEach(button => {
    button.addEventListener("click", function () {
        const orderId = this.dataset.orderId;
        if (!orderId) return;

        openCancelModal(orderId);
    });
});
