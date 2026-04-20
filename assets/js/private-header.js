document.addEventListener("DOMContentLoaded", function () {
    const toggle = document.getElementById("rkmUserMenuToggle");
    const dropdown = document.getElementById("rkmUserMenuDropdown");

    if (!toggle || !dropdown) return;

    toggle.addEventListener("click", function (e) {
        e.stopPropagation();
        dropdown.classList.toggle("is-open");
    });

    document.addEventListener("click", function (e) {
        if (!dropdown.contains(e.target) && !toggle.contains(e.target)) {
            dropdown.classList.remove("is-open");
        }
    });

    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape") {
            dropdown.classList.remove("is-open");
        }
    });
});