/**
 * UI Interactions Feature
 *
 * Handles modal dialogs, confirmations, tooltips, and other UI interactions
 * Extracted from custom.js and enhanced
 *
 * @package Coffee Internal Panel
 * @version 2.0
 */

class UIInteractions {
    constructor() {
        this.init();
    }

    init() {
        this.bindEvents();
        this.initializeTooltips();
        this.initializePopovers();
        console.log("UI Interactions module initialized");
    }

    /**
     * Bind event listeners
     */
    bindEvents() {
        // Modal handlers
        document.addEventListener("click", (e) => {
            if (e.target.matches("[data-modal-action]")) {
                this.handleModalAction(e.target);
            }
        });

        // Confirmation dialogs
        document.addEventListener("click", (e) => {
            if (e.target.matches("[data-confirm]")) {
                e.preventDefault();
                this.showConfirmation(e.target);
            }
        });

        // Clipboard operations
        document.addEventListener("click", (e) => {
            if (e.target.matches("[data-clipboard]")) {
                this.copyToClipboard(e.target);
            }
        });

        // Collapsible sections
        document.addEventListener("click", (e) => {
            if (e.target.matches('[data-toggle="collapse"]')) {
                this.toggleCollapse(e.target);
            }
        });

        // Tab switching
        document.addEventListener("click", (e) => {
            if (e.target.matches("[data-tab-target]")) {
                e.preventDefault();
                this.switchTab(e.target);
            }
        });

        // Auto-hide notifications
        this.autoHideNotifications();
    }

    /**
     * Handle modal actions
     */
    handleModalAction(trigger) {
        const action = trigger.dataset.modalAction;
        const modalId = trigger.dataset.modalTarget;
        const modal = document.querySelector(modalId);

        if (!modal) return;

        switch (action) {
            case "show":
                this.showModal(modal, trigger);
                break;
            case "hide":
                this.hideModal(modal);
                break;
            case "toggle":
                this.toggleModal(modal, trigger);
                break;
        }
    }

    /**
     * Show modal
     */
    showModal(modal, trigger = null) {
        // Populate modal with data if provided
        if (trigger && trigger.dataset.modalData) {
            try {
                const data = JSON.parse(trigger.dataset.modalData);
                this.populateModal(modal, data);
            } catch (e) {
                console.error("Invalid modal data:", e);
            }
        }

        // Show modal using Bootstrap or custom logic
        if (typeof bootstrap !== "undefined" && bootstrap.Modal) {
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
        } else {
            modal.classList.add("show");
            modal.style.display = "block";
            document.body.classList.add("modal-open");
        }

        // Focus management
        const firstInput = modal.querySelector("input, textarea, select");
        if (firstInput) {
            setTimeout(() => firstInput.focus(), 300);
        }
    }

    /**
     * Hide modal
     */
    hideModal(modal) {
        if (typeof bootstrap !== "undefined" && bootstrap.Modal) {
            const bsModal = bootstrap.Modal.getInstance(modal);
            if (bsModal) {
                bsModal.hide();
            }
        } else {
            modal.classList.remove("show");
            modal.style.display = "none";
            document.body.classList.remove("modal-open");
        }
    }

    /**
     * Toggle modal
     */
    toggleModal(modal, trigger = null) {
        const isVisible = modal.classList.contains("show");
        if (isVisible) {
            this.hideModal(modal);
        } else {
            this.showModal(modal, trigger);
        }
    }

    /**
     * Populate modal with data
     */
    populateModal(modal, data) {
        Object.keys(data).forEach((key) => {
            const element = modal.querySelector(`[data-field="${key}"]`);
            if (element) {
                if (
                    element.tagName === "INPUT" ||
                    element.tagName === "TEXTAREA" ||
                    element.tagName === "SELECT"
                ) {
                    element.value = data[key];
                } else {
                    element.textContent = data[key];
                }
            }
        });
    }

    /**
     * Show confirmation dialog
     */
    showConfirmation(trigger) {
        const message = trigger.dataset.confirm;
        const href = trigger.href || trigger.dataset.href;
        const form = trigger.closest("form");

        const confirmAction = () => {
            if (href) {
                window.location.href = href;
            } else if (form) {
                form.submit();
            } else {
                trigger.click();
            }
        };

        if (window.InternalConfirm) {
            window.InternalConfirm.open({
                title: trigger.dataset.confirmTitle || "Confirm",
                body: message || "Continue with this action?",
                confirmLabel: trigger.dataset.confirmLabel || "Confirm",
                confirmClass: trigger.dataset.confirmClass || "btn-primary",
            }).then((result) => {
                if (result.confirmed) {
                    confirmAction();
                }
            });

            return;
        }

        if (typeof Swal !== "undefined") {
            Swal.fire({
                title: "Are you sure?",
                text: message,
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#3085d6",
                cancelButtonColor: "#d33",
                confirmButtonText: "Yes, proceed!",
                cancelButtonText: "Cancel",
            }).then((result) => {
                if (result.isConfirmed) {
                    confirmAction();
                }
            });
        }
    }

    /**
     * Copy to clipboard
     */
    copyToClipboard(trigger) {
        const text = trigger.dataset.clipboard || trigger.textContent;

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard
                .writeText(text)
                .then(() => {
                    this.showCopySuccess(trigger);
                })
                .catch(() => {
                    this.fallbackCopyToClipboard(text, trigger);
                });
        } else {
            this.fallbackCopyToClipboard(text, trigger);
        }
    }

    /**
     * Fallback clipboard copy
     */
    fallbackCopyToClipboard(text, trigger) {
        const textArea = document.createElement("textarea");
        textArea.value = text;
        textArea.style.position = "fixed";
        textArea.style.left = "-999999px";
        textArea.style.top = "-999999px";
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();

        try {
            document.execCommand("copy");
            this.showCopySuccess(trigger);
        } catch (err) {
            console.error("Failed to copy text: ", err);
            this.showCopyError(trigger);
        }

        document.body.removeChild(textArea);
    }

    /**
     * Show copy success feedback
     */
    showCopySuccess(trigger) {
        const originalText = trigger.textContent;
        trigger.textContent = "Copied!";
        trigger.classList.add("text-success");

        setTimeout(() => {
            trigger.textContent = originalText;
            trigger.classList.remove("text-success");
        }, 2000);
    }

    /**
     * Show copy error feedback
     */
    showCopyError(trigger) {
        const originalText = trigger.textContent;
        trigger.textContent = "Copy failed";
        trigger.classList.add("text-danger");

        setTimeout(() => {
            trigger.textContent = originalText;
            trigger.classList.remove("text-danger");
        }, 2000);
    }

    /**
     * Toggle collapse
     */
    toggleCollapse(trigger) {
        const targetSelector =
            trigger.dataset.target || trigger.getAttribute("href");
        const target = document.querySelector(targetSelector);

        if (!target) return;

        const isCollapsed = !target.classList.contains("show");

        if (isCollapsed) {
            target.classList.add("show");
            target.style.height = target.scrollHeight + "px";
            trigger.setAttribute("aria-expanded", "true");
        } else {
            target.style.height = "0px";
            target.classList.remove("show");
            trigger.setAttribute("aria-expanded", "false");
        }
    }

    /**
     * Switch tabs
     */
    switchTab(trigger) {
        const targetSelector = trigger.dataset.tabTarget;
        const target = document.querySelector(targetSelector);

        if (!target) return;

        // Hide all tab panes in the same group
        const tabGroup = target.closest(".tab-content");
        if (tabGroup) {
            tabGroup.querySelectorAll(".tab-pane").forEach((pane) => {
                pane.classList.remove("show", "active");
            });
        }

        // Deactivate all tab triggers in the same group
        const triggerGroup =
            trigger.closest(".nav-tabs") || trigger.closest(".nav-pills");
        if (triggerGroup) {
            triggerGroup
                .querySelectorAll("[data-tab-target]")
                .forEach((tab) => {
                    tab.classList.remove("active");
                    tab.setAttribute("aria-selected", "false");
                });
        }

        // Activate selected tab
        target.classList.add("show", "active");
        trigger.classList.add("active");
        trigger.setAttribute("aria-selected", "true");
    }

    /**
     * Initialize tooltips
     */
    initializeTooltips() {
        if (typeof bootstrap !== "undefined" && bootstrap.Tooltip) {
            const tooltipTriggerList = [].slice.call(
                document.querySelectorAll('[data-bs-toggle="tooltip"]')
            );
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }
    }

    /**
     * Initialize popovers
     */
    initializePopovers() {
        if (typeof bootstrap !== "undefined" && bootstrap.Popover) {
            const popoverTriggerList = [].slice.call(
                document.querySelectorAll('[data-bs-toggle="popover"]')
            );
            popoverTriggerList.map(function (popoverTriggerEl) {
                return new bootstrap.Popover(popoverTriggerEl);
            });
        }
    }

    /**
     * Auto-hide notifications
     */
    autoHideNotifications() {
        document.querySelectorAll(".alert[data-auto-hide]").forEach((alert) => {
            const delay = parseInt(alert.dataset.autoHide) || 5000;
            setTimeout(() => {
                this.fadeOut(alert);
            }, delay);
        });
    }

    /**
     * Fade out element
     */
    fadeOut(element, duration = 300) {
        element.style.transition = `opacity ${duration}ms`;
        element.style.opacity = "0";

        setTimeout(() => {
            element.remove();
        }, duration);
    }

    /**
     * Fade in element
     */
    fadeIn(element, duration = 300) {
        element.style.opacity = "0";
        element.style.transition = `opacity ${duration}ms`;

        setTimeout(() => {
            element.style.opacity = "1";
        }, 10);
    }

    /**
     * Show notification
     */
    showNotification(type, message, duration = 5000) {
        const notification = document.createElement("div");
        notification.className = `alert alert-${type} alert-dismissible fade show`;
        notification.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        // Add to notification container or body
        const container =
            document.querySelector(".notification-container") || document.body;
        container.appendChild(notification);

        // Auto-hide
        if (duration > 0) {
            setTimeout(() => {
                this.fadeOut(notification);
            }, duration);
        }

        return notification;
    }
}

// Initialize global UI interactions
window.UIInteractions = new UIInteractions();

// Global functions for backward compatibility
function showModal(modalId, data = null) {
    const modal = document.querySelector(modalId);
    if (modal) {
        if (data) {
            window.UIInteractions.populateModal(modal, data);
        }
        window.UIInteractions.showModal(modal);
    }
}

function hideModal(modalId) {
    const modal = document.querySelector(modalId);
    if (modal) {
        window.UIInteractions.hideModal(modal);
    }
}

function showNotification(type, message, duration = 5000) {
    return window.UIInteractions.showNotification(type, message, duration);
}

function copyToClipboard(text) {
    const fakeElement = { dataset: { clipboard: text }, textContent: text };
    window.UIInteractions.copyToClipboard(fakeElement);
}
