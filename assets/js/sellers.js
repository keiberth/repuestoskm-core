document.addEventListener("DOMContentLoaded", function () {
    document.documentElement.classList.add("rkm-sellers-ready");

    var note = document.querySelector("[data-rkm-sellers-note]");
    var closeButton = document.querySelector("[data-rkm-sellers-close]");
    var actionButtons = document.querySelectorAll("[data-rkm-sellers-action]");

    if (!note || !actionButtons.length) {
        return;
    }

    var titleNode = note.querySelector("[data-rkm-sellers-note-title]");
    var messageNode = note.querySelector("[data-rkm-sellers-note-message]");
    var localizedMessages = window.rkmSellers && window.rkmSellers.messages ? window.rkmSellers.messages : {};

    actionButtons.forEach(function (button) {
        button.addEventListener("click", function (event) {
            event.preventDefault();

            var action = button.getAttribute("data-rkm-sellers-action");
            var payload = localizedMessages[action] || localizedMessages.clients || null;

            if (payload && titleNode) {
                titleNode.textContent = payload.title;
            }

            if (payload && messageNode) {
                messageNode.textContent = payload.message;
            }

            note.hidden = false;
            note.scrollIntoView({ behavior: "smooth", block: "nearest" });
        });
    });

    if (closeButton) {
        closeButton.addEventListener("click", function () {
            note.hidden = true;
        });
    }
});
