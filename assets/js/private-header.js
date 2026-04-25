document.addEventListener("DOMContentLoaded", function () {
    const toggle = document.getElementById("rkmUserMenuToggle");
    const dropdown = document.getElementById("rkmUserMenuDropdown");

    if (!toggle || !dropdown) return;

    const closeDropdown = function () {
        dropdown.classList.remove("is-open");
        toggle.setAttribute("aria-expanded", "false");
    };

    const toggleDropdown = function () {
        const isOpen = dropdown.classList.toggle("is-open");
        toggle.setAttribute("aria-expanded", isOpen ? "true" : "false");
    };

    toggle.addEventListener("click", function (e) {
        e.stopPropagation();
        toggleDropdown();
    });

    document.addEventListener("click", function (e) {
        if (!dropdown.contains(e.target) && !toggle.contains(e.target)) {
            closeDropdown();
        }
    });

    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape") {
            closeDropdown();
        }
    });
});
