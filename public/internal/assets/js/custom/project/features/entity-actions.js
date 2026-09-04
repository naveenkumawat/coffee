/**
 * Entity Actions Feature
 *
 * Handles common entity actions like avatar removal and deletion
 * Generic and reusable across all modules
 *
 * @package Coffee Internal Panel
 * @version 2.0
 */

class EntityActions {
    constructor() {
        this.init();
    }

    init() {
        this.bindEvents();
        console.log("Entity Actions: Generic entity action system initialized");
    }

    /**
     * Bind event listeners using event delegation
     */
    bindEvents() {
        // Avatar removal handler - primary data attribute selector
        document.addEventListener("click", (e) => {
            if (
                e.target.matches('[data-action="remove-avatar"]') ||
                e.target.closest('[data-action="remove-avatar"]')
            ) {
                e.preventDefault();
                const button = e.target.matches('[data-action="remove-avatar"]')
                    ? e.target
                    : e.target.closest('[data-action="remove-avatar"]');
                this.handleAvatarRemoval(button);
            }
        });

        // Entity deletion handler - primary data attribute selector
        document.addEventListener("click", (e) => {
            if (
                e.target.matches('[data-action="delete-entity"]') ||
                e.target.closest('[data-action="delete-entity"]')
            ) {
                e.preventDefault();
                const button = e.target.matches('[data-action="delete-entity"]')
                    ? e.target
                    : e.target.closest('[data-action="delete-entity"]');
                this.handleEntityDeletion(button);
            }
        });

        // Generic data attribute selectors for different action types
        document.addEventListener("click", (e) => {
            const genericDataSelectors = [
                '[data-action="remove-avatar"]',
                '[data-action="delete-entity"]',
                '[data-action="remove-entity"]',
                '[data-action="delete-record"]',
                '[data-entity-action="delete"]',
                '[data-entity-action="remove"]',
                "[data-delete]",
                "[data-remove]",
            ];

            let matchedButton = null;
            let actionType = null;

            for (const selector of genericDataSelectors) {
                if (e.target.matches(selector) || e.target.closest(selector)) {
                    matchedButton = e.target.matches(selector)
                        ? e.target
                        : e.target.closest(selector);

                    // Determine action type from data attribute
                    if (
                        selector.includes("avatar") ||
                        selector.includes("remove")
                    ) {
                        actionType = "avatar";
                    } else if (
                        selector.includes("delete") ||
                        selector.includes("entity")
                    ) {
                        actionType = "delete";
                    }
                    break;
                }
            }

            if (matchedButton) {
                e.preventDefault();
                if (actionType === "avatar") {
                    this.handleAvatarRemoval(matchedButton);
                } else if (actionType === "delete") {
                    this.handleEntityDeletion(matchedButton);
                }
            }
        });

        // Legacy ID support for backward compatibility (deprecated)
        document.addEventListener("click", (e) => {
            const legacySelectors = [
                "removeAvatar",
                "deleteEntity",
                "deleteAdministrator",
                "deleteSalesManager",
                "deleteCustomer",
                "deleteUser",
                "deleteRecord",
            ];

            for (const id of legacySelectors) {
                if (e.target.id === id || e.target.closest(`#${id}`)) {
                    console.warn(
                        `Using legacy ID selector '#${id}' is deprecated. Please use data attributes like data-action="delete-entity" or data-action="remove-avatar"`
                    );
                    e.preventDefault();

                    const button =
                        e.target.id === id
                            ? e.target
                            : e.target.closest(`#${id}`);

                    if (id === "removeAvatar") {
                        this.handleAvatarRemoval(button);
                    } else {
                        this.handleEntityDeletion(button);
                    }
                    break;
                }
            }
        });
    }

    /**
     * Handle avatar removal
     */
    handleAvatarRemoval(button) {
        if (!button) return;

        const removeUrl = button.dataset.removeUrl;
        const entityType =
            button.dataset.entityType || this.getEntityTypeFromButton(button);
        const entityName = button.dataset.entityName || entityType;

        if (!removeUrl) {
            console.error("Avatar removal: No remove URL found");
            return;
        }

        const title = button.dataset.confirmTitle || "Remove Avatar";
        const text =
            button.dataset.confirmMessage ||
            `Are you sure you want to remove the ${entityName} avatar?`;

        const proceed = () => {
            window.location.href = removeUrl;
        };

        if (window.InternalConfirm) {
            window.InternalConfirm.open({
                title,
                body: text,
                confirmLabel: "Remove",
                confirmClass: "btn-danger",
            }).then((result) => {
                if (result.confirmed) {
                    proceed();
                }
            });

            return;
        }

        if (typeof Swal !== "undefined") {
            Swal.fire({
                title: title,
                text: text,
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#d33",
                cancelButtonColor: "#3085d6",
                confirmButtonText: "Yes, remove it!",
                cancelButtonText: "Cancel",
            }).then((result) => {
                if (result.isConfirmed) {
                    proceed();
                }
            });
        }
    }

    /**
     * Handle entity deletion
     */
    handleEntityDeletion(button) {
        if (!button) return;

        const deleteUrl = button.dataset.deleteUrl;
        const entityType =
            button.dataset.entityType || this.getEntityTypeFromButton(button);
        const entityName = button.dataset.entityName || entityType;
        const useForm = button.dataset.useForm !== "false";

        if (!deleteUrl) {
            console.error("Entity deletion: No delete URL found");
            return;
        }

        const title = button.dataset.confirmTitle || `Delete ${entityName}`;
        const text =
            button.dataset.confirmMessage ||
            `Are you sure you want to delete this ${entityName}? This action cannot be undone!`;

        const proceed = () => {
            if (useForm) {
                this.submitDeleteForm(deleteUrl);
            } else {
                window.location.href = deleteUrl;
            }
        };

        if (window.InternalConfirm) {
            window.InternalConfirm.open({
                title,
                body: text,
                confirmLabel: "Delete",
                confirmClass: "btn-danger",
            }).then((result) => {
                if (result.confirmed) {
                    proceed();
                }
            });

            return;
        }

        if (typeof Swal !== "undefined") {
            Swal.fire({
                title: title,
                text: text,
                icon: "error",
                showCancelButton: true,
                confirmButtonColor: "#d33",
                cancelButtonColor: "#3085d6",
                confirmButtonText: "Yes, delete it!",
                cancelButtonText: "Cancel",
            }).then((result) => {
                if (result.isConfirmed) {
                    proceed();
                }
            });
        }
    }

    /**
     * Submit delete form with CSRF token
     */
    submitDeleteForm(deleteUrl) {
        const form = document.createElement("form");
        form.method = "POST";
        form.action = deleteUrl;

        // Add DELETE method
        const methodInput = document.createElement("input");
        methodInput.type = "hidden";
        methodInput.name = "_method";
        methodInput.value = "DELETE";
        form.appendChild(methodInput);

        // Add CSRF token
        const csrfToken = document.querySelector('meta[name="csrf-token"]');
        if (csrfToken) {
            const tokenInput = document.createElement("input");
            tokenInput.type = "hidden";
            tokenInput.name = "_token";
            tokenInput.value = csrfToken.content;
            form.appendChild(tokenInput);
        }

        document.body.appendChild(form);
        form.submit();
    }

    /**
     * Extract entity type from button context
     */
    getEntityTypeFromButton(button) {
        // Try to extract from ID
        if (button.id) {
            if (button.id.includes("Administrator")) return "Administrator";
            if (button.id.includes("SalesManager")) return "Sales Manager";
            if (button.id.includes("Customer")) return "Customer";
            if (button.id.includes("User")) return "User";
        }

        // Try to extract from URL
        const url = button.dataset.deleteUrl || button.dataset.removeUrl || "";
        if (url.includes("administrator")) return "Administrator";
        if (url.includes("sales_manager")) return "Sales Manager";
        if (url.includes("customer")) return "Customer";

        // Default fallback
        return "Entity";
    }
}

// Auto-initialize when DOM is ready
if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => {
        window.entityActions = new EntityActions();
    });
} else {
    window.entityActions = new EntityActions();
}

// Export for module systems
if (typeof module !== "undefined" && module.exports) {
    module.exports = EntityActions;
}

// Legacy support functions for backward compatibility
window.initializeAvatarRemoval = function () {
    console.warn(
        "initializeAvatarRemoval is deprecated. Entity actions are now handled automatically."
    );
};

window.initializeDelete = function () {
    console.warn(
        "initializeDelete is deprecated. Entity actions are now handled automatically."
    );
};

window.initializeStatusToggle = function () {
    console.warn(
        "initializeStatusToggle is deprecated. Status toggle is now handled automatically."
    );
};
