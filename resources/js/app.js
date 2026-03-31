import flatpickr from "flatpickr";
import { French } from "flatpickr/dist/l10n/fr.js";
import intlTelInput from "intl-tel-input";

window.intlTelInput = intlTelInput;

flatpickr.localize(French);

document.documentElement.classList.add("js");

function setToggleState(buttons, activeValue) {
    buttons.forEach((button) => {
        const isActive = button.dataset.personaValue === activeValue;
        button.setAttribute("aria-selected", String(isActive));
        button.classList.toggle("bg-primary", isActive);
        button.classList.toggle("text-accent", isActive);
        button.classList.toggle("text-slate-600", !isActive);
        button.classList.toggle("hover:text-primary", !isActive);
    });
}

function bindNavigation() {
    const toggle = document.querySelector("[data-nav-toggle]");
    const menu = document.querySelector("[data-nav-menu]");

    if (!toggle || !menu || toggle.dataset.bound === "true") {
        return;
    }

    toggle.dataset.bound = "true";

    toggle.addEventListener("click", () => {
        const expanded = toggle.getAttribute("aria-expanded") === "true";
        toggle.setAttribute("aria-expanded", String(!expanded));
        toggle.textContent = expanded ? "☰" : "×";
        menu.classList.toggle("max-h-0", expanded);
        menu.classList.toggle("max-h-96", !expanded);
    });

    menu.querySelectorAll("a").forEach((link) => {
        link.addEventListener("click", () => {
            toggle.setAttribute("aria-expanded", "false");
            toggle.textContent = "☰";
            menu.classList.add("max-h-0");
            menu.classList.remove("max-h-96");
        });
    });
}

function bindHomeHero() {
    const root = document.querySelector("[data-home-persona]");

    if (!root || root.dataset.bound === "true") {
        return;
    }

    root.dataset.bound = "true";

    const data = JSON.parse(root.getAttribute("data-home-persona") || "{}");
    const buttons = root.querySelectorAll("[data-persona-value]");
    const badgeWrapper = root.querySelector("[data-persona-badge-wrapper]");
    const badge = root.querySelector("[data-persona-field='badge']");
    const eyebrow = root.querySelector("[data-persona-field='eyebrow']");
    const subtitle = root.querySelector("[data-persona-field='subtitle']");
    const proof = root.querySelector("[data-persona-field='proof']");
    const statTitle = root.querySelector("[data-persona-field='stat-title']");
    const statText = root.querySelector("[data-persona-field='stat-text']");
    const image = root.querySelector("[data-persona-field='image']");
    const primaryLink = root.querySelector("[data-persona-link='primary']");
    const secondaryLink = root.querySelector("[data-persona-link='secondary']");

    function renderPersona(persona) {
        const content = data[persona];

        if (!content) {
            return;
        }

        if (badgeWrapper) {
            badgeWrapper.classList.toggle("hidden", !content.badge);
        }
        if (badge) badge.textContent = content.badge;
        if (eyebrow) eyebrow.textContent = content.eyebrow;
        if (subtitle) subtitle.textContent = content.subtitle;
        if (proof) proof.textContent = `✅ ${content.proof}`;
        if (statTitle) statTitle.textContent = content.statTitle;
        if (statText) statText.textContent = content.statText;
        if (image) image.setAttribute("src", content.image);
        if (primaryLink) {
            primaryLink.setAttribute("href", content.primaryCta.href);
            primaryLink.textContent = content.primaryCta.label;
        }
        if (secondaryLink) {
            secondaryLink.setAttribute("href", content.secondaryCta.href);
            secondaryLink.textContent = content.secondaryCta.label;
        }

        setToggleState(buttons, persona);
    }

    buttons.forEach((button) => {
        button.addEventListener("click", () => renderPersona(button.dataset.personaValue));
    });

    renderPersona("entreprise");
}

function bindPricingPersona() {
    const root = document.querySelector("[data-pricing-persona]");

    if (!root || root.dataset.bound === "true") {
        return;
    }

    root.dataset.bound = "true";

    const buttons = root.querySelectorAll("[data-persona-value]");
    const description = root.querySelector("[data-pricing-description]");

    function renderPersona(persona) {
        if (persona === "expert") {
            window.location.href = "/experts-comptables/rejoindre";
            return;
        }

        if (description) {
            description.textContent =
                "Choisissez un plan Fayeku adapté à votre rythme de facturation, à vos relances et à votre besoin de pilotage cash.";
        }

        setToggleState(buttons, persona);
    }

    buttons.forEach((button) => {
        button.addEventListener("click", () => renderPersona(button.dataset.personaValue));
    });

    renderPersona("entreprise");
}

function bindAccordion() {
    document.querySelectorAll("[data-accordion]").forEach((accordion) => {
        if (accordion.dataset.bound === "true") {
            return;
        }

        accordion.dataset.bound = "true";
        const items = accordion.querySelectorAll("[data-accordion-item]");

        items.forEach((item) => {
            const button = item.querySelector("[data-accordion-button]");
            const panel = item.querySelector("[data-accordion-panel]");
            const icon = item.querySelector("[data-accordion-icon]");

            if (!button || !panel || !icon) {
                return;
            }

            button.addEventListener("click", () => {
                const isOpen = button.getAttribute("aria-expanded") === "true";

                items.forEach((entry) => {
                    const entryButton = entry.querySelector("[data-accordion-button]");
                    const entryPanel = entry.querySelector("[data-accordion-panel]");
                    const entryIcon = entry.querySelector("[data-accordion-icon]");

                    if (!entryButton || !entryPanel || !entryIcon) {
                        return;
                    }

                    entryButton.setAttribute("aria-expanded", "false");
                    entryPanel.hidden = true;
                    entryIcon.textContent = "+";
                });

                if (!isOpen) {
                    button.setAttribute("aria-expanded", "true");
                    panel.hidden = false;
                    icon.textContent = "−";
                }
            });
        });
    });
}

function bindForms() {
    document.querySelectorAll("[data-demo-form]").forEach((form) => {
        if (form.dataset.bound === "true") {
            return;
        }

        form.dataset.bound = "true";
        const success = form.querySelector("[data-form-success]");

        form.addEventListener("submit", (event) => {
            event.preventDefault();

            if (success) {
                success.hidden = false;
            }
        });
    });
}

function digitsOnly(value) {
    return value.replace(/\D+/g, "");
}

function formatPhoneValue(country, value) {
    const digits = digitsOnly(value);

    if (country === "SN") {
        const normalized = digits.slice(0, 9);

        if (normalized.length <= 2) return normalized;
        if (normalized.length <= 5) return `${normalized.slice(0, 2)} ${normalized.slice(2)}`;
        if (normalized.length <= 7) return `${normalized.slice(0, 2)} ${normalized.slice(2, 5)} ${normalized.slice(5)}`;

        return `${normalized.slice(0, 2)} ${normalized.slice(2, 5)} ${normalized.slice(5, 7)} ${normalized.slice(7)}`;
    }

    if (country === "CI") {
        const normalized = digits.slice(0, 10);
        const groups = normalized.match(/.{1,2}/g) || [];
        return groups.join(" ");
    }

    return digits;
}

function bindPhoneFields() {
    const placeholders = {
        SN: "XX XXX XX XX",
        CI: "XX XX XX XX XX",
        FR: "X XX XX XX XX",
        ES: "XXX XXX XXX",
        MA: "XX XX XX XX XX",
        TN: "XX XXX XXX",
        BE: "XXX XX XX XX",
    };

    document.querySelectorAll("[data-phone-field]").forEach((field) => {
        if (field.dataset.bound === "true") {
            return;
        }

        field.dataset.bound = "true";
        // Support both a <select data-phone-country> (multi-country) and a
        // <input type="hidden" data-phone-country-static> (single-country mode).
        const countrySelect = field.querySelector("[data-phone-country]");
        const countryHidden = field.querySelector("[data-phone-country-static]");
        const country = countrySelect ?? countryHidden;
        const input = field.querySelector("[data-phone-input]");

        if (!country || !input) {
            return;
        }

        function render() {
            input.placeholder = placeholders[country.value] || "Numéro de téléphone";
            input.value = formatPhoneValue(country.value, input.value);
        }

        input.addEventListener("input", () => {
            const pos = input.selectionStart;
            const before = input.value;
            input.value = formatPhoneValue(country.value, input.value);
            // Preserve cursor — if the formatted string grew (space inserted), nudge forward.
            if (input.value.length > before.length) {
                input.setSelectionRange(pos + 1, pos + 1);
            } else {
                input.setSelectionRange(pos, pos);
            }
        });

        country.addEventListener("change", () => {
            // Clear phone when country changes so old format doesn't bleed over.
            input.value = "";
            render();
        });

        // Format on initial bind — covers the case where Livewire's deferred
        // wire:model initialises the input from its snapshot (raw digits).
        render();
    });
}

function reFormatPhoneFields() {
    const placeholders = {
        SN: "XX XXX XX XX",
        CI: "XX XX XX XX XX",
        FR: "X XX XX XX XX",
        ES: "XXX XXX XXX",
        MA: "XX XX XX XX XX",
        TN: "XX XXX XXX",
        BE: "XXX XX XX XX",
    };

    document.querySelectorAll("[data-phone-field]").forEach((field) => {
        const country = field.querySelector("[data-phone-country]")
                     ?? field.querySelector("[data-phone-country-static]");
        const input = field.querySelector("[data-phone-input]");

        // Skip fields the user is currently typing in.
        if (!country || !input || document.activeElement === input) {
            return;
        }

        input.placeholder = placeholders[country.value] || "Numéro de téléphone";
        input.value = formatPhoneValue(country.value, input.value);
    });
}

function bindAppShell() {
    const root = document.querySelector("[data-app-shell]");

    if (!root || root.dataset.bound === "true") {
        return;
    }

    root.dataset.bound = "true";

    const sidebar = root.querySelector("[data-app-shell-sidebar]");
    const overlay = root.querySelector("[data-app-shell-overlay]");
    const toggles = root.querySelectorAll("[data-app-shell-toggle]");
    const closers = root.querySelectorAll("[data-app-shell-close]");
    const desktopMedia = window.matchMedia("(min-width: 1024px)");

    function setSidebarState(isOpen) {
        root.dataset.sidebarOpen = String(isOpen);

        // Only manipulate translate classes on mobile — on desktop the sidebar
        // is always visible (via lg:translate-x-0) or collapsed via CSS.
        if (!desktopMedia.matches) {
            if (overlay) {
                overlay.classList.toggle("hidden", !isOpen);
            }

            if (sidebar) {
                sidebar.classList.toggle("-translate-x-full", !isOpen);
                sidebar.classList.toggle("translate-x-0", isOpen);
            }

            document.body.classList.toggle("overflow-hidden", isOpen);
        }
    }

    toggles.forEach((toggle) => {
        toggle.addEventListener("click", () => {
            const isOpen = root.dataset.sidebarOpen === "true";
            setSidebarState(!isOpen);
        });
    });

    closers.forEach((closer) => {
        closer.addEventListener("click", () => setSidebarState(false));
    });

    desktopMedia.addEventListener("change", (event) => {
        if (event.matches) {
            setSidebarState(false);
        }
    });

    setSidebarState(false);

    // Sidebar collapse (desktop icon-only mode)
    const collapseToggle = root.querySelector("[data-app-shell-collapse]");

    function setCollapsedState(isCollapsed) {
        document.documentElement.classList.toggle("sidebar-collapsed", isCollapsed);
        localStorage.setItem("sidebar-collapsed", String(isCollapsed));
    }

    // Re-apply collapsed class — Livewire wire:navigate replaces <html> classes
    const savedCollapsed = localStorage.getItem("sidebar-collapsed") === "true";
    setCollapsedState(savedCollapsed);

    if (collapseToggle) {
        collapseToggle.addEventListener("click", () => {
            const isCollapsed = document.documentElement.classList.contains("sidebar-collapsed");
            setCollapsedState(!isCollapsed);
        });
    }
}

function initializePage() {
    bindAppShell();
    bindNavigation();
    bindHomeHero();
    bindPricingPersona();
    bindAccordion();
    bindForms();
    bindPhoneFields();
}

initializePage();

// Re-apply sidebar collapsed class early — Livewire wire:navigate replaces
// <html> attributes, stripping our class before livewire:navigated fires.
document.addEventListener("livewire:navigated", () => {
    if (localStorage.getItem("sidebar-collapsed") === "true") {
        document.documentElement.classList.add("sidebar-collapsed");
    }
});

document.addEventListener("livewire:navigated", initializePage);

// After every Livewire network round-trip: bind any newly rendered phone fields
// (e.g. a modal just became visible) and re-format existing ones.
document.addEventListener("livewire:commit", () => {
    bindPhoneFields();
    reFormatPhoneFields();
});
