(function () {
    const form = document.querySelector('[data-rkm-current-account-form]');

    if (!form) {
        return;
    }

    const orderSelect = form.querySelector('[data-rkm-payment-order]');
    const amountInput = form.querySelector('[data-rkm-payment-amount]');
    const balanceHint = form.querySelector('[data-rkm-payment-balance]');
    const feedback = form.querySelector('[data-rkm-current-account-feedback]');

    const formatAmount = (value) => {
        const amount = Number(value || 0);

        return amount.toLocaleString('es-AR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    };

    const getSelectedBalance = () => {
        if (!orderSelect || !orderSelect.selectedOptions.length) {
            return 0;
        }

        return Number(orderSelect.selectedOptions[0].dataset.balance || 0);
    };

    const setFeedback = (message) => {
        if (!feedback) {
            return;
        }

        feedback.textContent = message || '';
        feedback.classList.toggle('is-visible', Boolean(message));
    };

    const syncBalanceHint = () => {
        const balance = getSelectedBalance();

        if (balanceHint) {
            balanceHint.textContent = balance > 0
                ? `Saldo pendiente del pedido: ${formatAmount(balance)}`
                : 'Selecciona un pedido para ver su saldo.';
        }

        if (amountInput && balance > 0) {
            amountInput.max = String(balance);
        }

        setFeedback('');
    };

    if (orderSelect) {
        orderSelect.addEventListener('change', syncBalanceHint);
        syncBalanceHint();
    }

    form.addEventListener('submit', (event) => {
        const balance = getSelectedBalance();
        const amount = Number(amountInput ? amountInput.value : 0);

        if (balance <= 0) {
            event.preventDefault();
            setFeedback('Selecciona un pedido con saldo pendiente.');
            return;
        }

        if (amount <= 0) {
            event.preventDefault();
            setFeedback('Ingresa un monto mayor a cero.');
            return;
        }

        if (amount > balance) {
            event.preventDefault();
            setFeedback('El monto no puede superar el saldo pendiente del pedido.');
        }
    });
})();
