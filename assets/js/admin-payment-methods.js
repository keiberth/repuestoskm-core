document.addEventListener("DOMContentLoaded", function () {
    const form = document.querySelector("[data-rkm-payment-method-form]");

    if (!form) {
        return;
    }

    const title = document.getElementById("rkmPaymentMethodFormTitle");
    const methodId = form.querySelector("[data-rkm-payment-method-id]");
    const name = form.querySelector("[data-rkm-payment-method-name]");
    const type = form.querySelector("[data-rkm-payment-method-type]");
    const priority = form.querySelector("[data-rkm-payment-method-priority]");
    const description = form.querySelector("[data-rkm-payment-method-description]");
    const active = form.querySelector("[data-rkm-payment-method-active]");
    const submit = form.querySelector("[data-rkm-payment-method-submit]");
    const reset = document.querySelector("[data-rkm-payment-method-reset]");

    function resetForm() {
        form.reset();
        if (methodId) methodId.value = "";
        if (priority) priority.value = "10";
        if (active) active.checked = true;
        if (title) title.textContent = "Nueva forma de pago";
        if (submit) submit.textContent = "Guardar forma de pago";
    }

    document.querySelectorAll("[data-rkm-payment-method-edit]").forEach(function (button) {
        button.addEventListener("click", function () {
            let method = {};

            try {
                method = JSON.parse(button.getAttribute("data-method") || "{}");
            } catch (error) {
                method = {};
            }

            if (methodId) methodId.value = method.id || "";
            if (name) name.value = method.name || "";
            if (type) type.value = method.type || "otro";
            if (priority) priority.value = method.priority || 10;
            if (description) description.value = method.description || "";
            if (active) active.checked = !!method.active;
            if (title) title.textContent = "Editar forma de pago";
            if (submit) submit.textContent = "Actualizar forma de pago";

            form.scrollIntoView({ behavior: "smooth", block: "start" });
            if (name) name.focus({ preventScroll: true });
        });
    });

    if (reset) {
        reset.addEventListener("click", resetForm);
    }

    document.querySelectorAll("[data-rkm-payment-method-delete]").forEach(function (deleteForm) {
        deleteForm.addEventListener("submit", function (event) {
            if (!window.confirm("Eliminar esta forma de pago?")) {
                event.preventDefault();
            }
        });
    });
});
