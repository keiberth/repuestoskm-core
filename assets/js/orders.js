document.addEventListener("DOMContentLoaded", function () {
    const cards = document.querySelectorAll(".rkm-product-card");
    const summaryContainer = document.querySelector(".rkm-order-summary");
    const summaryCard = summaryContainer ? summaryContainer.querySelector(".rkm-card") : null;
    const LEGACY_ORDER_DRAFT_STORAGE_KEY = "rkm_order_draft";
    const LEGACY_REPEAT_ORDER_STORAGE_KEY = "rkm_repeat_order_cart";
    const activeCustomerId = window.rkmOrderContext && window.rkmOrderContext.active_customer_id
        ? String(window.rkmOrderContext.active_customer_id)
        : "0";
    const storageScope = window.rkmOrders && window.rkmOrders.current_user_id
        ? `${String(window.rkmOrders.current_user_id)}_${activeCustomerId}`
        : "0";
    const ORDER_DRAFT_STORAGE_KEY = `rkm_order_draft_${storageScope}`;
    const paymentMethods = Array.isArray(window.rkmPaymentMethods) ? window.rkmPaymentMethods : [];
    const paymentTermsConfig = window.rkmPaymentTerms || {};
    const paymentTerms = Array.isArray(paymentTermsConfig.terms) ? paymentTermsConfig.terms : [];
    const cashDiscountPercent = Number(paymentTermsConfig.cash_discount_percent || 0);

    let orderItems = {};
    let pendingHighlightItemId = null;
    let shouldScrollSummaryIntoView = false;
    let checkoutModalOpen = false;

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

    function escapeHtml(value) {
        return String(value || "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
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

    function getFeedbackBox() {
        return document.getElementById("rkm-order-feedback");
    }

    function hideFeedback() {
        const feedbackBox = getFeedbackBox();

        if (!feedbackBox) return;

        feedbackBox.style.display = "none";
        feedbackBox.classList.remove("is-visible", "rkm-order-feedback--error");
        feedbackBox.innerHTML = "";
    }

    function showFeedback({ type = "success", icon = "&#10003;", eyebrow = "", title = "", text = "", actionsHtml = "" }) {
        const feedbackBox = getFeedbackBox();

        if (!feedbackBox) return;

        feedbackBox.classList.remove("rkm-order-feedback--error");

        if (type === "error") {
            feedbackBox.classList.add("rkm-order-feedback--error");
        }

        feedbackBox.innerHTML = `
            <div class="rkm-order-feedback__inner">
                <div class="rkm-order-feedback__icon">${icon}</div>

                <div class="rkm-order-feedback__content">
                    ${eyebrow ? `<div class="rkm-order-feedback__eyebrow">${eyebrow}</div>` : ""}
                    ${title ? `<div class="rkm-order-feedback__title">${title}</div>` : ""}
                    ${text ? `<div class="rkm-order-feedback__text">${text}</div>` : ""}
                    ${actionsHtml}
                </div>

                <button type="button" class="rkm-order-feedback__close" id="rkm-close-feedback" aria-label="Cerrar">&times;</button>
            </div>
        `;

        feedbackBox.style.display = "block";
        feedbackBox.classList.add("is-visible");
        feedbackBox.scrollIntoView({ behavior: "smooth", block: "start" });

        const closeBtn = document.getElementById("rkm-close-feedback");
        if (closeBtn) {
            closeBtn.addEventListener("click", hideFeedback);
        }
    }

    function showInlineError(message, title = "No se pudo continuar") {
        showFeedback({
            type: "error",
            icon: "!",
            eyebrow: "Revisa el pedido",
            title,
            text: message,
        });
    }

    function renderActiveCustomerContext() {
        if (!window.rkmOrderContext || !window.rkmOrderContext.is_vendor_customer_context) {
            return "";
        }

        const customerName = window.rkmOrderContext.active_customer_name || "Cliente seleccionado";
        const customerEmail = window.rkmOrderContext.active_customer_email || "";

        return `
            <div class="rkm-order-summary__customer">
                <span class="rkm-order-summary__customer-label">Pedido a nombre de</span>
                <strong>${customerName}</strong>
                ${customerEmail ? `<p>${customerEmail}</p>` : ""}
            </div>
        `;
    }

    function getPaymentState() {
        const select = document.getElementById("rkmPaymentMethod");
        const note = document.getElementById("rkmPaymentNote");

        return {
            methodId: select ? select.value : "",
            note: note ? note.value : "",
        };
    }

    function getPaymentTermState() {
        const checked = document.querySelector('[data-rkm-payment-term-input]:checked');
        const upfrontInput = document.getElementById("rkmUpfrontAmount");

        return {
            term: checked ? checked.value : "",
            upfrontAmount: upfrontInput ? Number(upfrontInput.value || 0) : 0,
        };
    }

    function getTermLabel(termKey) {
        const term = paymentTerms.find((item) => item.key === termKey);

        return term ? term.label : "";
    }

    function getPaymentCalculations(subtotal) {
        const termState = getPaymentTermState();
        const discountPercent = termState.term === "cash" ? Math.max(0, cashDiscountPercent) : 0;
        const discountAmount = Math.min(subtotal, subtotal * discountPercent / 100);
        const finalTotal = Math.max(0, subtotal - discountAmount);
        const upfrontAmount = termState.term === "mixed"
            ? Math.max(0, Math.min(finalTotal, Number(termState.upfrontAmount || 0)))
            : 0;
        const creditBalance = termState.term === "credit"
            ? finalTotal
            : (termState.term === "mixed" ? Math.max(0, finalTotal - upfrontAmount) : 0);

        return {
            term: termState.term,
            discountPercent,
            discountAmount,
            finalTotal,
            upfrontAmount,
            creditBalance,
        };
    }

    function renderPaymentTermSection(subtotal) {
        const termState = getPaymentTermState();
        const calculations = getPaymentCalculations(subtotal);

        if (!paymentTerms.length) {
            return `
                <div class="rkm-order-payment-term" data-rkm-payment-terms>
                    <div class="rkm-order-payment-term__header">
                        <span class="rkm-order-payment-term__eyebrow">Condicion</span>
                        <strong>Condicion de pago</strong>
                    </div>
                    <div class="rkm-order-payment-term__empty">
                        No hay condiciones de pago activas. Contacta al administrador para confirmar pedidos.
                    </div>
                </div>
            `;
        }

        const options = paymentTerms.map((term) => {
            const checked = termState.term === term.key ? "checked" : "";
            const instructions = term.instructions
                ? `<small>${escapeHtml(term.instructions)}</small>`
                : "";

            return `
                <label class="rkm-order-payment-term__option">
                    <input type="radio" name="rkm_payment_term" value="${escapeHtml(term.key)}" data-rkm-payment-term-input ${checked}>
                    <span>
                        <strong>${escapeHtml(term.label)}</strong>
                        ${instructions}
                    </span>
                </label>
            `;
        }).join("");

        let details = "";

        if (calculations.term === "cash") {
            details = `
                <div class="rkm-order-payment-term__details" data-rkm-payment-term-details>
                    <strong>Descuento contado: ${cashDiscountPercent}%</strong>
                    <span>Descuento aplicado: ${formatPrice(calculations.discountAmount)}</span>
                </div>
            `;
        } else if (calculations.term === "credit") {
            details = `
                <div class="rkm-order-payment-term__details" data-rkm-payment-term-details>
                    <strong>El pedido quedara sujeto a aprobacion de credito.</strong>
                    <span>Saldo pendiente estimado: ${formatPrice(calculations.creditBalance)}</span>
                </div>
            `;
        } else if (calculations.term === "mixed") {
            details = `
                <div class="rkm-order-payment-term__details" data-rkm-payment-term-details>
                    <label class="rkm-order-payment-term__field" for="rkmUpfrontAmount">
                        <span>Monto a pagar ahora</span>
                        <input id="rkmUpfrontAmount" type="number" min="0" step="0.01" value="${termState.upfrontAmount || ""}" data-rkm-upfront-amount>
                    </label>
                    <span>Saldo restante a credito: ${formatPrice(calculations.creditBalance)}</span>
                </div>
            `;
        } else {
            details = '<div class="rkm-order-payment-term__details" data-rkm-payment-term-details hidden></div>';
        }

        return `
            <div class="rkm-order-payment-term" data-rkm-payment-terms>
                <div class="rkm-order-payment-term__header">
                    <span class="rkm-order-payment-term__eyebrow">Condicion</span>
                    <strong>Condicion de pago</strong>
                </div>
                <div class="rkm-order-payment-term__options">${options}</div>
                ${details}
            </div>
        `;
    }

    function renderPaymentSection() {
        const paymentState = getPaymentState();
        const termState = getPaymentTermState();

        if (!paymentTerms.length || !termState.term) {
            return "";
        }

        if (termState.term === "credit") {
            return "";
        }

        if (!paymentMethods.length) {
            return `
                <div class="rkm-order-payment" data-rkm-payment-methods>
                    <div class="rkm-order-payment__header">
                        <span class="rkm-order-payment__eyebrow">Pago</span>
                        <strong>Forma de pago</strong>
                    </div>
                    <div class="rkm-order-payment__empty">
                        No hay formas de pago activas por ahora. El pedido se puede crear sin seleccion.
                    </div>
                </div>
            `;
        }

        const options = paymentMethods.map((method) => {
            const id = escapeHtml(method.id || "");
            const name = escapeHtml(method.name || "Forma de pago");
            const description = escapeHtml(method.description || "");
            const selected = paymentState.methodId === (method.id || "") ? "selected" : "";

            return `<option value="${id}" data-description="${description}" ${selected}>${name}</option>`;
        }).join("");

        return `
            <div class="rkm-order-payment" data-rkm-payment-methods>
                <div class="rkm-order-payment__header">
                    <span class="rkm-order-payment__eyebrow">Pago</span>
                    <strong>${termState.term === "mixed" ? "Forma de pago inicial" : "Forma de pago"}</strong>
                </div>

                <label class="rkm-order-payment__field" for="rkmPaymentMethod">
                    <span>Selecciona una opcion</span>
                    <select id="rkmPaymentMethod" data-rkm-payment-method-select>
                        <option value="">Seleccionar forma de pago</option>
                        ${options}
                    </select>
                </label>

                <div id="rkmPaymentMethodDescription" class="rkm-order-payment__description" data-rkm-payment-method-description hidden></div>

                <label class="rkm-order-payment__field" for="rkmPaymentNote">
                    <span>Observacion de pago</span>
                    <textarea id="rkmPaymentNote" data-rkm-payment-note rows="3" placeholder="Referencia, banco, condiciones o comentario opcional.">${escapeHtml(paymentState.note)}</textarea>
                </label>
            </div>
        `;
    }

    function updatePaymentDescription() {
        const select = document.getElementById("rkmPaymentMethod");
        const description = document.getElementById("rkmPaymentMethodDescription");

        if (!select || !description) {
            return;
        }

        const selectedOption = select.options[select.selectedIndex];
        const text = selectedOption ? selectedOption.getAttribute("data-description") || "" : "";

        description.textContent = text;
        description.hidden = text.trim() === "";
    }

    function bindPaymentSectionEvents() {
        const select = document.getElementById("rkmPaymentMethod");

        if (select) {
            select.addEventListener("change", updatePaymentDescription);
        }

        updatePaymentDescription();
    }

    function bindPaymentTermEvents() {
        document.querySelectorAll("[data-rkm-payment-term-input]").forEach((input) => {
            input.addEventListener("change", () => {
                if (checkoutModalOpen) {
                    renderCheckoutModal();
                    return;
                }

                renderSummary();
            });
        });

        const upfrontInput = document.getElementById("rkmUpfrontAmount");

        if (upfrontInput) {
            upfrontInput.addEventListener("change", () => {
                if (checkoutModalOpen) {
                    renderCheckoutModal();
                    return;
                }

                renderSummary();
            });
        }
    }

    // ========================
    // REPETIR PEDIDO
    // ========================

    function saveRepeatCartKey() {
        return `rkm_repeat_order_cart_${storageScope}`;
    }

    function cleanupLegacyStorageKeys() {
        try {
            localStorage.removeItem(LEGACY_ORDER_DRAFT_STORAGE_KEY);
            localStorage.removeItem(LEGACY_REPEAT_ORDER_STORAGE_KEY);
        } catch (e) {
            // Ignore storage cleanup errors.
        }
    }

    function clearOrderDraft() {
        try {
            localStorage.removeItem(ORDER_DRAFT_STORAGE_KEY);
        } catch (e) {
            // Ignore storage cleanup errors.
        }
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
                clearOrderDraft();
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
            clearOrderDraft();
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

        clearOrderDraft();
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

        let warningHtml = "";

        if (warnings.length) {
            warningHtml = `
                <div class="rkm-order-feedback__warnings">
                    <strong>Revisa estos productos:</strong>
                    <ul class="rkm-order-feedback__warning-list">
                        ${warnings.map((warning) => `<li>${warning}</li>`).join("")}
                    </ul>
                </div>
            `;
        }

        let title = "Se cargaron productos de un pedido anterior";
        let text = "Revisa cantidades, disponibilidad y confirma la nueva orden cuando quieras.";

        if (!addedCount) {
            title = "No se pudieron cargar productos del pedido anterior";
            text = "Ningun producto pudo agregarse automaticamente a la nueva orden.";
        } else if (warnings.length) {
            title = "Se cargo el pedido con observaciones";
            text = "Algunos productos se agregaron correctamente, pero otros requieren revision.";
        }

        showFeedback({
            icon: "&#8635;",
            eyebrow: "Pedido reutilizado",
            title,
            text,
            actionsHtml: warningHtml,
        });
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

    function getOrderItemsSubtotal(items = Object.values(orderItems)) {
        return items.reduce((total, item) => total + (Number(item.price || 0) * Number(item.quantity || 0)), 0);
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

    function renderPaymentTotals(subtotal) {
        const calculations = getPaymentCalculations(subtotal);
        const rows = [];

        rows.push(`
            <div class="rkm-summary-total rkm-summary-total--subtle">
                <span>Subtotal</span>
                <strong>${formatPrice(subtotal)}</strong>
            </div>
        `);

        if (calculations.discountAmount > 0) {
            rows.push(`
                <div class="rkm-summary-total rkm-summary-total--discount">
                    <span>Descuento contado (${calculations.discountPercent}%)</span>
                    <strong>-${formatPrice(calculations.discountAmount)}</strong>
                </div>
            `);
        }

        if (calculations.term === "mixed") {
            rows.push(`
                <div class="rkm-summary-total rkm-summary-total--subtle">
                    <span>Monto inicial</span>
                    <strong>${formatPrice(calculations.upfrontAmount)}</strong>
                </div>
                <div class="rkm-summary-total rkm-summary-total--credit">
                    <span>Saldo a credito</span>
                    <strong>${formatPrice(calculations.creditBalance)}</strong>
                </div>
            `);
        } else if (calculations.term === "credit") {
            rows.push(`
                <div class="rkm-summary-total rkm-summary-total--credit">
                    <span>Saldo pendiente</span>
                    <strong>${formatPrice(calculations.creditBalance)}</strong>
                </div>
            `);
        }

        rows.push(`
            <div class="rkm-summary-total rkm-summary-total--grand">
                <span>Total</span>
                <strong>${formatPrice(calculations.finalTotal)}</strong>
            </div>
        `);

        return rows.join("");
    }

    function getCheckoutModal() {
        let modal = document.getElementById("rkmCheckoutModal");

        if (!modal) {
            modal = document.createElement("div");
            modal.id = "rkmCheckoutModal";
            document.body.appendChild(modal);
        }

        return modal;
    }

    function openCheckoutModal() {
        checkoutModalOpen = true;
        renderCheckoutModal();
        document.body.classList.add("rkm-checkout-modal-open");

        const modal = getCheckoutModal();
        const closeBtn = modal.querySelector("[data-rkm-checkout-close]");

        if (closeBtn) {
            closeBtn.focus({ preventScroll: true });
        }
    }

    function closeCheckoutModal() {
        const modal = document.getElementById("rkmCheckoutModal");

        checkoutModalOpen = false;
        document.body.classList.remove("rkm-checkout-modal-open");

        if (modal) {
            modal.innerHTML = "";
            modal.classList.remove("is-active");
        }
    }

    function renderCheckoutModal() {
        if (!checkoutModalOpen) {
            return;
        }

        const items = Object.values(orderItems);
        const subtotal = getOrderItemsSubtotal(items);
        const modal = getCheckoutModal();

        modal.className = "rkm-checkout-modal is-active";
        modal.innerHTML = `
            <div class="rkm-checkout-modal__overlay" data-rkm-checkout-close></div>

            <div class="rkm-checkout-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="rkmCheckoutModalTitle">
                <div class="rkm-checkout-modal__header">
                    <div>
                        <span class="rkm-checkout-modal__eyebrow">Paso final</span>
                        <h2 id="rkmCheckoutModalTitle">Confirmar pedido</h2>
                        <p>${getSelectedProductsLabel(items.length)} en esta orden.</p>
                    </div>
                    <button type="button" class="rkm-checkout-modal__close" data-rkm-checkout-close aria-label="Cerrar">&times;</button>
                </div>

                <div class="rkm-checkout-modal__body">
                    <section class="rkm-checkout-modal__summary">
                        <div class="rkm-checkout-modal__summary-row">
                            <span>Productos</span>
                            <strong>${items.length}</strong>
                        </div>
                        <div class="rkm-checkout-modal__summary-row">
                            <span>Total de productos</span>
                            <strong>${items.reduce((total, item) => total + Number(item.quantity || 0), 0)}</strong>
                        </div>
                    </section>

                    ${renderPaymentTermSection(subtotal)}
                    ${renderPaymentSection()}

                    <div class="rkm-checkout-modal__error" id="rkmCheckoutModalError" hidden></div>

                    <section class="rkm-summary-totals rkm-checkout-modal__totals">
                        ${renderPaymentTotals(subtotal)}
                    </section>
                </div>

                <div class="rkm-checkout-modal__footer">
                    <button type="button" class="rkm-btn rkm-btn--secondary" data-rkm-checkout-close>Volver</button>
                    <button type="button" class="rkm-btn rkm-btn--primary" id="rkm-submit-order">
                        Enviar pedido
                    </button>
                </div>
            </div>
        `;

        bindCheckoutModalEvents();
        bindPaymentTermEvents();
        bindPaymentSectionEvents();
    }

    function bindCheckoutModalEvents() {
        const modal = getCheckoutModal();

        modal.querySelectorAll("[data-rkm-checkout-close]").forEach((button) => {
            button.addEventListener("click", closeCheckoutModal);
        });

        const submitBtn = document.getElementById("rkm-submit-order");

        if (submitBtn) {
            submitBtn.addEventListener("click", () => submitOrder(submitBtn));
        }
    }

    function showCheckoutModalError(message) {
        const errorBox = document.getElementById("rkmCheckoutModalError");

        if (!errorBox) {
            showInlineError(message);
            return;
        }

        errorBox.textContent = message;
        errorBox.hidden = false;
        errorBox.scrollIntoView({ behavior: "smooth", block: "center" });
    }

    async function submitOrder(submitBtn) {
        if (submitBtn.disabled) {
            return;
        }

        const items = Object.values(orderItems);

        if (!items.length) {
            closeCheckoutModal();
            showInlineError("No hay productos en el pedido.", "Pedido vacio");
            return;
        }

        const subtotal = getOrderItemsSubtotal(items);
        const paymentState = getPaymentState();
        const paymentTermState = getPaymentTermState();
        const paymentCalculations = getPaymentCalculations(subtotal);
        const rawUpfrontAmount = Number(paymentTermState.upfrontAmount || 0);
        const selectedTermIsActive = paymentTerms.some((term) => term.key === paymentTermState.term);
        const needsPaymentMethod = paymentTermState.term === "cash" || paymentTermState.term === "mixed";

        if (!paymentTerms.length) {
            showCheckoutModalError("No hay condiciones de pago activas para confirmar pedidos.");
            return;
        }

        if (!selectedTermIsActive) {
            showCheckoutModalError("Selecciona una condicion de pago valida antes de confirmar el pedido.");
            return;
        }

        if (paymentTermState.term === "mixed" && rawUpfrontAmount <= 0) {
            showCheckoutModalError("Indica el monto inicial para la condicion de pago mixta.");
            return;
        }

        if (paymentTermState.term === "mixed" && rawUpfrontAmount > paymentCalculations.finalTotal) {
            showCheckoutModalError("El monto inicial no puede ser mayor al total del pedido.");
            return;
        }

        if (needsPaymentMethod && paymentMethods.length && !paymentState.methodId) {
            showCheckoutModalError("Selecciona una forma de pago antes de confirmar el pedido.");
            return;
        }

        submitBtn.disabled = true;
        submitBtn.textContent = "Enviando...";

        try {
            const formData = new FormData();
            formData.append("action", "rkm_create_order");
            formData.append("nonce", rkmOrders.nonce);
            formData.append("items", JSON.stringify(items));
            formData.append("payment_term", paymentTermState.term);
            formData.append("cash_discount", String(paymentCalculations.discountPercent));
            formData.append("upfront_amount", String(paymentCalculations.upfrontAmount));
            formData.append("payment_method_id", needsPaymentMethod ? paymentState.methodId : "");
            formData.append("payment_note", needsPaymentMethod ? paymentState.note : "");

            if (window.rkmOrderContext && window.rkmOrderContext.active_customer_id) {
                formData.append("customer_id", String(window.rkmOrderContext.active_customer_id));
            }

            const response = await fetch(rkmOrders.ajax_url, {
                method: "POST",
                body: formData,
                credentials: "same-origin",
            });

            const result = await response.json();

            if (!result.success) {
                throw new Error(result.data?.message || "No se pudo generar el pedido.");
            }

            closeCheckoutModal();
            orderItems = {};
            clearOrderDraft();
            renderSummary();

            showFeedback({
                icon: "&#10003;",
                eyebrow: "Pedido confirmado",
                title: result.data?.success_title || "Pedido enviado correctamente",
                text: result.data?.message || `Tu pedido #${result.data.order_id} fue enviado con exito y ya esta disponible en la seccion Pedidos.`,
                actionsHtml: `
                    <div class="rkm-order-feedback__actions">
                        <a href="${result.data.redirect}" class="rkm-btn rkm-btn--primary">
                            ${result.data?.redirect_label || "Ver mis pedidos"}
                        </a>
                        <button type="button" class="rkm-btn rkm-btn--secondary" id="rkm-new-order-again">
                            Crear otra orden
                        </button>
                    </div>
                `,
            });

            const newOrderBtn = document.getElementById("rkm-new-order-again");
            if (newOrderBtn) {
                newOrderBtn.addEventListener("click", () => {
                    hideFeedback();
                    window.scrollTo({ top: 0, behavior: "smooth" });
                });
            }
        } catch (error) {
            console.error(error);
            showCheckoutModalError(error.message || "Ocurrio un error al generar el pedido.");
            submitBtn.disabled = false;
            submitBtn.textContent = "Enviar pedido";
        }
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

                ${renderActiveCustomerContext()}

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

            ${renderActiveCustomerContext()}

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
                Continuar
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
            confirmBtn.addEventListener("click", () => {
                if (confirmBtn.disabled) {
                    return;
                }

                const items = Object.values(orderItems);

                if (!items.length) {
                    showInlineError("No hay productos en el pedido.", "Pedido vacio");
                    return;
                }

                openCheckoutModal();
            });
        }
    }

    if (summaryCard) {
        cleanupLegacyStorageKeys();

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

    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape" && checkoutModalOpen) {
            closeCheckoutModal();
        }
    });

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

