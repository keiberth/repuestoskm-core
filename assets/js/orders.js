document.addEventListener("DOMContentLoaded", function () {
    const cards = document.querySelectorAll(".rkm-product-card");
    const summaryContainer = document.querySelector(".rkm-order-summary");
    const summaryCard = summaryContainer ? summaryContainer.querySelector(".rkm-card") : null;
    const ORDER_DRAFT_STORAGE_KEY = "rkm_order_draft";

    let orderItems = {};
    let pendingHighlightItemId = null;
    let shouldScrollSummaryIntoView = false;

    // ========================
    // UTILIDADES
    // ========================

    function formatPrice(value) {
        return new Intl.NumberFormat("es-AR", {
            style: "currency",
            currency: "ARS",
            minimumFractionDigits: 0,
        }).format(value);
    }

    function getVisibleStock(card) {
        const stockEl = card?.querySelector(".rkm-stock-value");
        if (!stockEl) return 0;
        return parseInt(stockEl.textContent, 10) || 0;
    }

    function setVisibleStock(card, value) {
        const stockEl = card?.querySelector(".rkm-stock-value");
        if (stockEl) {
            stockEl.textContent = value;
        }
    }

    function showInlineError(message) {
        alert(message);
    }

    // ========================
    // REPETIR PEDIDO
    // ========================

    function saveRepeatCartKey() {
        return "rkm_repeat_order_cart";
    }

    function normalizeDraftItems(items) {
        if (!Array.isArray(items)) return [];

        return items
            .map((item) => ({
                id: String(item.id || ""),
                name: item.name || "",
                price: Number(item.price || 0),
                sku: item.sku || "",
                image: item.image || "",
                description: item.description || "",
                quantity: Math.max(1, Number(item.quantity || item.qty || 1)),
            }))
            .filter((item) => item.id && Number.isFinite(item.price) && item.quantity > 0);
    }

    function normalizeRepeatItems(items) {
        if (!Array.isArray(items)) return [];

        return items
            .map((item) => ({
                id: String(item.id || item.product_id || ""),
                name: item.name || "",
                price: Number(item.price || item.price_raw || 0),
                sku: item.sku || "",
                quantity: Math.max(1, Number(item.quantity || item.qty || 1)),
            }))
            .filter((item) => item.id);
    }

    function saveOrderDraft() {
        try {
            const draftItems = normalizeDraftItems(Object.values(orderItems));

            if (!draftItems.length) {
                localStorage.removeItem(ORDER_DRAFT_STORAGE_KEY);
                return;
            }

            localStorage.setItem(ORDER_DRAFT_STORAGE_KEY, JSON.stringify(draftItems));
        } catch (e) {
            // Ignore storage errors silently so the order flow keeps working.
        }
    }

    function loadOrderDraftIfExists() {
        if (!summaryCard) return;

        let rawDraft = null;

        try {
            rawDraft = localStorage.getItem(ORDER_DRAFT_STORAGE_KEY);
        } catch (e) {
            rawDraft = null;
        }

        if (!rawDraft) {
            return;
        }

        let draftItems = [];

        try {
            draftItems = normalizeDraftItems(JSON.parse(rawDraft));
        } catch (e) {
            draftItems = [];
        }

        if (!draftItems.length) {
            try {
                localStorage.removeItem(ORDER_DRAFT_STORAGE_KEY);
            } catch (e) {
                // Ignore storage cleanup errors.
            }
            return;
        }

        let restoredAnyItem = false;

        draftItems.forEach((item) => {
            const restoreResult = addItemToOrder(item, item.quantity);

            if (restoreResult?.success) {
                restoredAnyItem = true;
                return;
            }

            if (restoreResult?.reason === "partial_stock" && restoreResult.available > 0) {
                const partialRestoreResult = addItemToOrder(item, restoreResult.available);

                if (partialRestoreResult?.success) {
                    restoredAnyItem = true;
                }
            }
        });

        if (restoredAnyItem) {
            renderSummary();
            return;
        }

        try {
            localStorage.removeItem(ORDER_DRAFT_STORAGE_KEY);
        } catch (e) {
            // Ignore storage cleanup errors.
        }
    }

    function addItemToOrder(productData, qtyToAdd = 1) {
        const id = String(productData.id);
        const card = document.querySelector(`.rkm-product-card[data-id="${id}"]`);

        if (!card) {
            return {
                success: false,
                reason: "not_found",
                name: productData.name || "Producto",
            };
        }

        const stock = getVisibleStock(card);
        const qty = Number(qtyToAdd) || 1;

        if (qty <= 0) {
            return {
                success: false,
                reason: "invalid_qty",
                name: productData.name || "Producto",
            };
        }

        if (stock <= 0) {
            return {
                success: false,
                reason: "no_stock",
                name: productData.name || card.dataset.name || "Producto",
            };
        }

        if (qty > stock) {
            return {
                success: false,
                reason: "partial_stock",
                name: productData.name || card.dataset.name || "Producto",
                requested: qty,
                available: stock,
            };
        }

        if (!orderItems[id]) {
            orderItems[id] = {
                id,
                name: productData.name || card.dataset.name || "",
                price: Number(productData.price || card.dataset.price || 0),
                sku: productData.sku || card.dataset.sku || "",
                image: card.dataset.image || "",
                description: card.dataset.description || "",
                quantity: 0,
            };
        }

        orderItems[id].quantity += qty;
        setVisibleStock(card, stock - qty);

        return { success: true };
    }

    function loadRepeatOrderIfExists() {
        if (!summaryCard) return;

        const storageKey = saveRepeatCartKey();

        let raw = null;
        try {
            raw = localStorage.getItem(storageKey);
        } catch (e) {
            raw = null;
        }

        if (!raw) return;

        let repeatItems = [];
        try {
            repeatItems = normalizeRepeatItems(JSON.parse(raw));
        } catch (e) {
            repeatItems = [];
        }

        if (!repeatItems.length) {
            localStorage.removeItem(storageKey);
            return;
        }

        const warnings = [];
        let addedCount = 0;

        repeatItems.forEach((item) => {
            const result = addItemToOrder(item, item.quantity);

            if (result?.success) {
                addedCount++;
                return;
            }

            if (!result) return;

            if (result.reason === "not_found") {
                warnings.push(`"${result.name}" no se encontró entre los productos disponibles.`);
            }

            if (result.reason === "no_stock") {
                warnings.push(`"${result.name}" ya no tiene stock disponible.`);
            }

            if (result.reason === "partial_stock") {
                warnings.push(`"${result.name}" no tiene stock suficiente. Solicitado: ${result.requested}, disponible: ${result.available}.`);
            }
        });

        renderSummary();
        localStorage.removeItem(storageKey);

        const feedbackBox = document.getElementById("rkm-order-feedback");
        if (!feedbackBox) return;

        let warningHtml = "";

        if (warnings.length) {
            warningHtml = `
                <div class="rkm-order-feedback__warnings">
                    <strong>Revisá estos productos:</strong>
                    <ul class="rkm-order-feedback__warning-list">
                        ${warnings.map((warning) => `<li>${warning}</li>`).join("")}
                    </ul>
                </div>
            `;
        }

        let title = "Se cargaron productos de un pedido anterior";
        let text = "Revisá cantidades, disponibilidad y confirmá la nueva orden cuando quieras.";

        if (!addedCount) {
            title = "No se pudieron cargar productos del pedido anterior";
            text = "Ningún producto pudo agregarse automáticamente a la nueva orden.";
        } else if (warnings.length) {
            title = "Se cargó el pedido con observaciones";
            text = "Algunos productos se agregaron correctamente, pero otros requieren revisión.";
        }

        feedbackBox.innerHTML = `
            <div class="rkm-order-feedback__inner">
                <div class="rkm-order-feedback__icon">↺</div>

                <div class="rkm-order-feedback__content">
                    <div class="rkm-order-feedback__eyebrow">Pedido reutilizado</div>
                    <div class="rkm-order-feedback__title">${title}</div>
                    <div class="rkm-order-feedback__text">${text}</div>
                    ${warningHtml}
                </div>

                <button type="button" class="rkm-order-feedback__close" id="rkm-close-feedback" aria-label="Cerrar">×</button>
            </div>
        `;

        feedbackBox.style.display = "block";
        feedbackBox.classList.add("is-visible");

        const closeBtn = document.getElementById("rkm-close-feedback");
        if (closeBtn) {
            closeBtn.addEventListener("click", () => {
                feedbackBox.style.display = "none";
                feedbackBox.classList.remove("is-visible");
                feedbackBox.innerHTML = "";
            });
        }
    }

    // ========================
    // RESUMEN / NUEVA ORDEN
    // ========================

    function getSelectedProductsLabel(count) {
        if (count === 1) {
            return "1 producto seleccionado";
        }

        return `${count} productos seleccionados`;
    }

    function highlightSummaryItem(itemId) {
        if (!itemId) return;

        const summaryItem = summaryCard?.querySelector(`.rkm-summary-item [data-id="${itemId}"]`)?.closest(".rkm-summary-item");

        if (!summaryItem) {
            return;
        }

        summaryItem.classList.add("is-highlighted");

        window.setTimeout(() => {
            summaryItem.classList.remove("is-highlighted");
        }, 1600);
    }

    function revealSummaryIfNeeded() {
        if (!shouldScrollSummaryIntoView || !summaryContainer) {
            shouldScrollSummaryIntoView = false;
            return;
        }

        shouldScrollSummaryIntoView = false;

        summaryContainer.scrollIntoView({
            behavior: "smooth",
            block: "start",
        });
    }

    function renderSummary() {
        if (!summaryCard) return;

        const items = Object.values(orderItems);
        const selectedProductsCount = items.length;

        if (!items.length) {
            summaryCard.innerHTML = `
                <div class="rkm-order-summary__header">
                    <div>
                        <span class="rkm-order-summary__eyebrow">Pedido</span>
                        <h3>Resumen del pedido</h3>
                        <p class="rkm-order-summary__count">${getSelectedProductsLabel(selectedProductsCount)}</p>
                    </div>
                </div>

                <div class="rkm-order-summary__empty-state">
                    <p class="rkm-order-summary__empty">No hay productos en el pedido</p>
                    <p class="rkm-order-summary__empty-text">Agrega productos desde la grilla para ver cantidades y totales aca.</p>
                </div>

                <div class="rkm-summary-totals">
                    <div class="rkm-summary-total rkm-summary-total--subtle">
                        <span>Subtotal</span>
                        <strong>${formatPrice(0)}</strong>
                    </div>

                    <div class="rkm-summary-total rkm-summary-total--grand">
                        <span>Total</span>
                        <strong>${formatPrice(0)}</strong>
                    </div>
                </div>

                <button class="rkm-btn rkm-btn--primary rkm-btn-block" disabled>Continuar</button>
            `;
            saveOrderDraft();
            return;
        }

        let subtotalGeneral = 0;

        const rows = items.map((item) => {
            const subtotal = item.price * item.quantity;
            subtotalGeneral += subtotal;

            return `
                <div class="rkm-summary-item">
                    <div class="rkm-summary-item__info">
                        <strong>${item.name}</strong>
                        ${item.sku ? `<div class="rkm-summary-meta">SKU: ${item.sku}</div>` : ""}

                        <div class="rkm-summary-item__meta-actions">
                            <div class="rkm-summary-pricing">
                                <div class="rkm-summary-pricing__row">
                                    <span>Unitario</span>
                                    <strong>${formatPrice(item.price)}</strong>
                                </div>

                                <div class="rkm-summary-pricing__row">
                                    <span>Subtotal</span>
                                    <strong>${formatPrice(subtotal)}</strong>
                                </div>
                            </div>

                            <button class="rkm-remove-item" data-id="${item.id}" aria-label="Eliminar producto">&times;</button>
                        </div>
                    </div>

                    <div class="rkm-summary-item__controls">
                        <button class="rkm-qty-minus" data-id="${item.id}">-</button>
                        <span>${item.quantity}</span>
                        <button class="rkm-qty-plus" data-id="${item.id}">+</button>
                    </div>
                </div>
            `;
        }).join("");

        summaryCard.innerHTML = `
            <div class="rkm-order-summary__header">
                <div>
                    <span class="rkm-order-summary__eyebrow">Pedido</span>
                    <h3>Resumen del pedido</h3>
                    <p class="rkm-order-summary__count">${getSelectedProductsLabel(selectedProductsCount)}</p>
                </div>
            </div>

            <div class="rkm-summary-list">
                ${rows}
            </div>

            <div class="rkm-summary-totals">
                <div class="rkm-summary-total rkm-summary-total--subtle">
                    <span>Subtotal</span>
                    <strong>${formatPrice(subtotalGeneral)}</strong>
                </div>

                <div class="rkm-summary-total rkm-summary-total--grand">
                    <span>Total</span>
                    <strong>${formatPrice(subtotalGeneral)}</strong>
                </div>
            </div>

            <button class="rkm-btn rkm-btn--primary rkm-btn-block" id="rkm-confirm-order">
                Confirmar pedido
            </button>
        `;

        bindSummaryEvents();
        highlightSummaryItem(pendingHighlightItemId);
        pendingHighlightItemId = null;
        revealSummaryIfNeeded();
        saveOrderDraft();
    }
    function bindSummaryEvents() {
        document.querySelectorAll(".rkm-qty-plus").forEach((btn) => {
            btn.addEventListener("click", () => {
                const id = btn.dataset.id;
                const card = document.querySelector(`.rkm-product-card[data-id="${id}"]`);
                const stock = getVisibleStock(card);

                if (stock <= 0) {
                    showInlineError("Sin stock");
                    return;
                }

                orderItems[id].quantity += 1;
                setVisibleStock(card, stock - 1);
                renderSummary();
            });
        });

        document.querySelectorAll(".rkm-qty-minus").forEach((btn) => {
            btn.addEventListener("click", () => {
                const id = btn.dataset.id;
                const card = document.querySelector(`.rkm-product-card[data-id="${id}"]`);

                orderItems[id].quantity -= 1;
                setVisibleStock(card, getVisibleStock(card) + 1);

                if (orderItems[id].quantity <= 0) {
                    delete orderItems[id];
                }

                renderSummary();
            });
        });

        document.querySelectorAll(".rkm-remove-item").forEach((btn) => {
            btn.addEventListener("click", () => {
                const id = btn.dataset.id;
                const card = document.querySelector(`.rkm-product-card[data-id="${id}"]`);
                const qty = orderItems[id].quantity;

                setVisibleStock(card, getVisibleStock(card) + qty);
                delete orderItems[id];
                renderSummary();
            });
        });

        const confirmBtn = document.getElementById("rkm-confirm-order");
        if (confirmBtn) {
            confirmBtn.addEventListener("click", async () => {
                const items = Object.values(orderItems);

                if (!items.length) {
                    showInlineError("No hay productos en el pedido.");
                    return;
                }

                confirmBtn.disabled = true;
                confirmBtn.textContent = "Generando pedido...";

                try {
                    const formData = new FormData();
                    formData.append("action", "rkm_create_order");
                    formData.append("nonce", rkmOrders.nonce);
                    formData.append("items", JSON.stringify(items));

                    const response = await fetch(rkmOrders.ajax_url, {
                        method: "POST",
                        body: formData,
                        credentials: "same-origin",
                    });

                    const result = await response.json();

                    if (!result.success) {
                        throw new Error(result.data?.message || "No se pudo generar el pedido.");
                    }

                    orderItems = {};
                    renderSummary();

                    const feedbackBox = document.getElementById("rkm-order-feedback");

                    if (feedbackBox) {
                        feedbackBox.innerHTML = `
                            <div class="rkm-order-feedback__inner">
                                <div class="rkm-order-feedback__icon">✓</div>

                                <div class="rkm-order-feedback__content">
                                    <div class="rkm-order-feedback__eyebrow">Pedido confirmado</div>
                                    <div class="rkm-order-feedback__title">Pedido #${result.data.order_id} generado correctamente</div>
                                    <div class="rkm-order-feedback__text">
                                        Tu pedido fue enviado con éxito y ya se encuentra disponible en la sección <strong>Pedidos</strong>.
                                    </div>

                                    <div class="rkm-order-feedback__actions">
                                        <a href="${result.data.redirect}" class="rkm-btn rkm-btn--primary">
                                            Ver mis pedidos
                                        </a>
                                        <button type="button" class="rkm-btn rkm-btn--secondary" id="rkm-new-order-again">
                                            Crear otra orden
                                        </button>
                                    </div>
                                </div>

                                <button type="button" class="rkm-order-feedback__close" id="rkm-close-feedback" aria-label="Cerrar">×</button>
                            </div>
                        `;

                        feedbackBox.style.display = "block";
                        feedbackBox.classList.add("is-visible");
                        feedbackBox.scrollIntoView({ behavior: "smooth", block: "start" });

                        const closeBtn = document.getElementById("rkm-close-feedback");
                        if (closeBtn) {
                            closeBtn.addEventListener("click", () => {
                                feedbackBox.style.display = "none";
                                feedbackBox.classList.remove("is-visible");
                                feedbackBox.innerHTML = "";
                            });
                        }

                        const newOrderBtn = document.getElementById("rkm-new-order-again");
                        if (newOrderBtn) {
                            newOrderBtn.addEventListener("click", () => {
                                feedbackBox.style.display = "none";
                                feedbackBox.classList.remove("is-visible");
                                feedbackBox.innerHTML = "";
                                window.scrollTo({ top: 0, behavior: "smooth" });
                            });
                        }
                    }
                } catch (error) {
                    console.error(error);
                    showInlineError(error.message || "Ocurrió un error al generar el pedido.");
                } finally {
                    confirmBtn.disabled = false;
                    confirmBtn.textContent = "Confirmar pedido";
                }
            });
        }
    }

    if (summaryCard) {
        cards.forEach((card) => {
            const btn = card.querySelector(".rkm-add-to-summary");
            const qtyInput = card.querySelector(".rkm-qty-input");

            if (!btn || !qtyInput) return;

            btn.addEventListener("click", () => {
                const id = card.dataset.id;
                const name = card.dataset.name;
                const price = parseFloat(card.dataset.price || 0);
                const sku = card.dataset.sku || "";
                const image = card.dataset.image || "";
                const description = card.dataset.description || "";

                const qty = parseInt(qtyInput.value, 10) || 1;
                const stock = getVisibleStock(card);

                if (qty <= 0) return;

                if (qty > stock) {
                    showInlineError("No hay suficiente stock disponible");
                    return;
                }

                if (!orderItems[id]) {
                    orderItems[id] = {
                        id,
                        name,
                        price,
                        sku,
                        image,
                        description,
                        quantity: 0,
                    };
                }

                const hadItemsBefore = Object.keys(orderItems).length > 0;
                orderItems[id].quantity += qty;
                setVisibleStock(card, stock - qty);
                qtyInput.value = 1;
                pendingHighlightItemId = id;
                shouldScrollSummaryIntoView = !hadItemsBefore;
                renderSummary();
            });
        });

        let hasRepeatDraft = false;

        try {
            hasRepeatDraft = !!localStorage.getItem(saveRepeatCartKey());
        } catch (e) {
            hasRepeatDraft = false;
        }

        if (hasRepeatDraft) {
            loadRepeatOrderIfExists();
        } else {
            loadOrderDraftIfExists();
        }
    }

    // ========================
    // MODALES DIRECCIONES
    // ========================

    function openModal(id) {
        const modal = document.getElementById(id);
        if (!modal) return;

        modal.classList.add("is-active");
        document.body.classList.add("rkm-modal-open");
    }

    function closeModalElement(modal) {
        if (!modal) return;

        modal.classList.remove("is-active");
        document.body.classList.remove("rkm-modal-open");
    }

    document.querySelectorAll(".rkm-open-billing-modal").forEach((btn) => {
        btn.addEventListener("click", () => openModal("rkmBillingModal"));
    });

    document.querySelectorAll(".rkm-open-shipping-modal").forEach((btn) => {
        btn.addEventListener("click", () => openModal("rkmShippingModal"));
    });

    document.querySelectorAll(".rkm-modal").forEach((modal) => {
        const overlay = modal.querySelector(".rkm-modal__overlay");
        const closeBtn = modal.querySelector(".rkm-modal__close");

        if (overlay) {
            overlay.addEventListener("click", () => closeModalElement(modal));
        }

        if (closeBtn) {
            closeBtn.addEventListener("click", () => closeModalElement(modal));
        }
    });

    function saveAddress(action, fields, onSuccess) {
        const formData = new FormData();
        formData.append("action", action);
        formData.append("nonce", rkmOrders.nonce);

        Object.entries(fields).forEach(([key, value]) => {
            formData.append(key, value);
        });

        return fetch(rkmOrders.ajax_url, {
            method: "POST",
            body: formData,
            credentials: "same-origin",
        })
            .then((res) => res.json())
            .then((result) => {
                if (!result.success) {
                    throw new Error(result.data?.message || "No se pudo guardar.");
                }

                onSuccess(result.data.data);
            });
    }

    const saveBillingBtn = document.getElementById("rkmSaveBilling");
    if (saveBillingBtn) {
        saveBillingBtn.addEventListener("click", function () {
            const modal = document.getElementById("rkmBillingModal");

            saveAddress(
                "rkm_save_billing_address",
                {
                    billing_first_name: document.getElementById("billing_first_name")?.value || "",
                    billing_last_name: document.getElementById("billing_last_name")?.value || "",
                    billing_phone: document.getElementById("billing_phone")?.value || "",
                    billing_address_1: document.getElementById("billing_address_1")?.value || "",
                    billing_city: document.getElementById("billing_city")?.value || "",
                },
                (data) => {
                    const summary = document.getElementById("rkmBillingSummary");
                    const empty = document.getElementById("rkmBillingEmpty");
                    const editBtnWrap = document.querySelector(".rkm-address-card .rkm-address-summary__header .rkm-open-billing-modal");

                    if (summary) {
                        summary.innerHTML = `
                            ${data.name ? `<strong>${data.name}</strong>` : ""}
                            ${data.address ? `<p>${data.address}</p>` : ""}
                            ${data.city ? `<p>${data.city}</p>` : ""}
                            ${data.phone ? `<p>Tel: ${data.phone}</p>` : ""}
                        `;
                        summary.style.display = "block";
                    }

                    if (empty) {
                        empty.style.display = "none";
                    }

                    if (!editBtnWrap) {
                        const billingHeader = document.querySelectorAll(".rkm-address-summary__header")[0];
                        if (billingHeader) {
                            const btn = document.createElement("button");
                            btn.type = "button";
                            btn.className = "rkm-link-edit rkm-open-billing-modal";
                            btn.textContent = "Editar";
                            btn.addEventListener("click", () => openModal("rkmBillingModal"));
                            billingHeader.appendChild(btn);
                        }
                    }

                    closeModalElement(modal);
                }
            ).catch((error) => {
                showInlineError(error.message);
            });
        });
    }

    const saveShippingBtn = document.getElementById("rkmSaveShipping");
    if (saveShippingBtn) {
        saveShippingBtn.addEventListener("click", function () {
            const modal = document.getElementById("rkmShippingModal");

            saveAddress(
                "rkm_save_shipping_address",
                {
                    shipping_first_name: document.getElementById("shipping_first_name")?.value || "",
                    shipping_last_name: document.getElementById("shipping_last_name")?.value || "",
                    shipping_address_1: document.getElementById("shipping_address_1")?.value || "",
                    shipping_city: document.getElementById("shipping_city")?.value || "",
                },
                (data) => {
                    const summary = document.getElementById("rkmShippingSummary");
                    const empty = document.getElementById("rkmShippingEmpty");
                    const editBtnWrap = document.querySelectorAll(".rkm-address-summary__header .rkm-open-shipping-modal")[0];

                    if (summary) {
                        summary.innerHTML = `
                            ${data.name ? `<strong>${data.name}</strong>` : ""}
                            ${data.address ? `<p>${data.address}</p>` : ""}
                            ${data.city ? `<p>${data.city}</p>` : ""}
                        `;
                        summary.style.display = "block";
                    }

                    if (empty) {
                        empty.style.display = "none";
                    }

                    if (!editBtnWrap) {
                        const shippingHeader = document.querySelectorAll(".rkm-address-summary__header")[1];
                        if (shippingHeader) {
                            const btn = document.createElement("button");
                            btn.type = "button";
                            btn.className = "rkm-link-edit rkm-open-shipping-modal";
                            btn.textContent = "Editar";
                            btn.addEventListener("click", () => openModal("rkmShippingModal"));
                            shippingHeader.appendChild(btn);
                        }
                    }

                    closeModalElement(modal);
                }
            ).catch((error) => {
                showInlineError(error.message);
            });
        });
    }
});

