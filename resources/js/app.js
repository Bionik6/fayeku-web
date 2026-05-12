import collapse from "@alpinejs/collapse";
import flatpickr from "flatpickr";
import { French } from "flatpickr/dist/l10n/fr.js";
import intlTelInput from "intl-tel-input";

window.intlTelInput = intlTelInput;

// Livewire 4 (and Flux) bundle their own Alpine and start it themselves.
// Importing alpinejs separately spawns a second instance whose directive
// registry is empty — `wire:click` then silently fails to bind. Plug into
// Livewire's Alpine instead via the alpine:init hook.
document.addEventListener("alpine:init", () => {
    window.Alpine.plugin(collapse);
});

flatpickr.localize(French);

document.documentElement.classList.add("js");

window.phoneInput = function ({ initialCountry, initialPhone, countries, countryModel, phoneModel }) {
    return {
        open: false,
        search: "",
        country: initialCountry,
        phone: initialPhone,
        countries: countries,

        get filtered() {
            const q = this.search.toLowerCase().trim();
            if (!q) return this.countries;
            return this.countries.filter(
                (c) =>
                    c.name.toLowerCase().includes(q) ||
                    c.prefix.includes(q) ||
                    c.code.toLowerCase().includes(q) ||
                    c.label.toLowerCase().includes(q),
            );
        },

        get selected() {
            return this.countries.find((c) => c.code === this.country) ?? this.countries[0];
        },

        // Only these countries get auto-formatting; others accept raw digits.
        formattedCountries: ["SN", "CI"],

        get localFormat() {
            if (!this.formattedCountries.includes(this.country)) return "";
            const fmt = this.selected?.format ?? "";
            return fmt.replace(/^\+\d+\s*/, "");
        },

        get maxDigits() {
            if (!this.formattedCountries.includes(this.country)) return 15;
            return (this.localFormat.match(/X/g) ?? []).length;
        },

        get placeholder() {
            return this.localFormat || "Numéro de téléphone";
        },

        formatPhone(raw) {
            const digits = raw.replace(/\D/g, "").slice(0, this.maxDigits);
            const pattern = this.localFormat;

            if (!pattern) return digits;

            let result = "";
            let di = 0;

            for (let i = 0; i < pattern.length && di < digits.length; i++) {
                result += pattern[i] === "X" ? digits[di++] : pattern[i];
            }

            return result;
        },

        selectCountry(code) {
            this.country = code;
            this.open = false;
            this.search = "";
            this.phone = "";

            // Batch both updates so the server re-renders with the new country AND an
            // empty phone — preventing the old formatted value from being restored.
            if (this.$wire) {
                try {
                    if (countryModel) this.$wire.set(countryModel, code);
                    if (phoneModel) this.$wire.set(phoneModel, "");
                } catch (_) {}
            }

            this.$nextTick(() => this.$refs.phoneInput?.focus());
        },

        onPhoneInput(event) {
            const pos = event.target.selectionStart;
            const before = event.target.value;
            const formatted = this.formatPhone(event.target.value);

            event.target.value = formatted;
            this.phone = formatted;

            // Preserve cursor position; nudge forward when a separator was inserted.
            const nudge = formatted.length > before.length ? 1 : 0;
            event.target.setSelectionRange(pos + nudge, pos + nudge);
        },

        init() {
            if (this.phone) {
                const formatted = this.formatPhone(this.phone);
                this.phone = formatted;

                if (this.$refs.phoneInput) {
                    this.$refs.phoneInput.value = formatted;
                }
            }

            // Auto-focus search field when dropdown opens.
            this.$watch("open", (val) => {
                if (val) {
                    this.$nextTick(() => this.$refs.searchInput?.focus());
                }
            });
        },
    };
};

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

