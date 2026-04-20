document.addEventListener('DOMContentLoaded', function () {
    const STORAGE_KEY = 'rkm_order_cart';

    const addButtons = document.querySelectorAll('.rkm-add-product');
    const orderItems = document.getElementById('rkm-order-items');
    const emptyBox = document.getElementById('rkm-order-empty');
    const contentBox = document.getElementById('rkm-order-content');
    const totalBox = document.getElementById('rkm-order-total');
    const clearButton = document.getElementById('rkm-clear-order');
    const confirmButton = document.getElementById('rkm-confirm-order');

    if (!orderItems || !emptyBox || !contentBox || !totalBox) {
        return;
    }

    function loadCart() {
        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            return raw ? JSON.parse(raw) : [];
        } catch (e) {
            return [];
        }
    }

    function saveCart(cart) {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(cart));
    }

    function formatPrice(value) {
        return new Intl.NumberFormat('es-AR', {
            style: 'currency',
            currency: 'ARS',
            minimumFractionDigits: 0,
            maximumFractionDigits: 2
        }).format(value || 0);
    }

    function renderCart() {
        const cart = loadCart();

        orderItems.innerHTML = '';

        if (!cart.length) {
            emptyBox.style.display = 'block';
            contentBox.style.display = 'none';
            totalBox.textContent = formatPrice(0);
            return;
        }

        emptyBox.style.display = 'none';
        contentBox.style.display = 'block';

        let total = 0;

        cart.forEach((item) => {
            const subtotal = Number(item.price) * Number(item.quantity);
            total += subtotal;

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    <div class="rkm-order-item-name">${escapeHtml(item.name)}</div>
                    ${item.sku ? `<div class="rkm-order-item-meta">SKU: ${escapeHtml(item.sku)}</div>` : ''}
                </td>
                <td>
                    <input 
                        type="number" 
                        min="1" 
                        value="${Number(item.quantity)}" 
                        class="rkm-order-qty"
                        data-id="${item.id}"
                    >
                </td>
                <td>${formatPrice(Number(item.price))}</td>
                <td>${formatPrice(subtotal)}</td>
                <td>
                    <button type="button" class="rkm-remove-product" data-id="${item.id}">
                        Quitar
                    </button>
                </td>
            `;
            orderItems.appendChild(tr);
        });

        totalBox.textContent = formatPrice(total);

        bindQtyEvents();
        bindRemoveEvents();
    }

    function addToCart(product) {
        const cart = loadCart();
        const existing = cart.find((item) => String(item.id) === String(product.id));

        if (existing) {
            existing.quantity += 1;
        } else {
            cart.push({
                id: String(product.id),
                name: product.name,
                price: Number(product.price || 0),
                sku: product.sku || '',
                quantity: 1
            });
        }

        saveCart(cart);
        renderCart();
    }

    function updateQty(productId, quantity) {
        let cart = loadCart();

        cart = cart.map((item) => {
            if (String(item.id) === String(productId)) {
                return {
                    ...item,
                    quantity: Math.max(1, Number(quantity) || 1)
                };
            }
            return item;
        });

        saveCart(cart);
        renderCart();
    }

    function removeFromCart(productId) {
        const cart = loadCart().filter((item) => String(item.id) !== String(productId));
        saveCart(cart);
        renderCart();
    }

    function clearCart() {
        localStorage.removeItem(STORAGE_KEY);
        renderCart();
    }

    function bindQtyEvents() {
        document.querySelectorAll('.rkm-order-qty').forEach((input) => {
            input.addEventListener('change', function () {
                updateQty(this.dataset.id, this.value);
            });
        });
    }

    function bindRemoveEvents() {
        document.querySelectorAll('.rkm-remove-product').forEach((button) => {
            button.addEventListener('click', function () {
                removeFromCart(this.dataset.id);
            });
        });
    }

    function escapeHtml(str) {
        return String(str)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    addButtons.forEach((button) => {
        button.addEventListener('click', function () {
            addToCart({
                id: this.dataset.id,
                name: this.dataset.name,
                price: this.dataset.price,
                sku: this.dataset.sku
            });
        });
    });

    if (clearButton) {
        clearButton.addEventListener('click', clearCart);
    }

    if (confirmButton) {
        confirmButton.addEventListener('click', function () {
            const cart = loadCart();

            if (!cart.length) {
                alert('No hay productos en el pedido.');
                return;
            }

            alert('El carrito funciona correctamente. El próximo paso es guardar esta orden en WordPress.');
        });
    }

    renderCart();
});