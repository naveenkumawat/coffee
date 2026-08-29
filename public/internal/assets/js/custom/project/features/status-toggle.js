/**
 * Generic Status Toggle Feature
 *
 * Handles status toggle functionality for any entity type (users, products, orders, etc.)
 * Uses data attributes for configuration to be completely entity-agnostic
 *
 * @author ZYLM Development Team
 * @version 2.0.0
 * @since 2025-01-21
 */

class StatusToggle {
    constructor() {
        this.isProcessing = false;
        this.init();
    }

    /**
     * Initialize status toggle functionality
     */
    init() {
        this.bindEvents();
        console.log("StatusToggle: Generic status toggle system initialized");
    }

    /**
     * Bind click events to all status toggle elements
     */
    bindEvents() {
        // Use event delegation for dynamic content
        document.addEventListener("click", (e) => {
            const toggle = e.target.closest('[data-action="toggle-status"]');
            if (toggle) {
                e.preventDefault();
                this.handleToggle(toggle);
            }
        });
    }

    /**
     * Handle status toggle click
     * @param {HTMLElement} element - The clicked toggle element
     */
    async handleToggle(element) {
        if (this.isProcessing) {
            return;
        }

        // Extract configuration from data attributes
        const config = this.extractConfig(element);

        if (!this.validateConfig(config)) {
            return;
        }

        // Confirm action if required
        if (config.confirm && !(await this.confirmAction(config))) {
            return;
        }

        this.isProcessing = true;

        try {
            await this.performToggle(element, config);
        } catch (error) {
            console.error("StatusToggle: Toggle failed", error);
            this.showError("Failed to update status. Please try again.");
        } finally {
            this.isProcessing = false;
        }
    }

    /**
     * Extract configuration from element data attributes
     * @param {HTMLElement} element - The toggle element
     * @returns {Object} Configuration object
     */
    extractConfig(element) {
        return {
            id: element.dataset.id,
            currentStatus: parseInt(element.dataset.currentStatus),
            url: element.dataset.url,
            entityType: element.dataset.entityType || "record",
            confirm: element.dataset.confirm === "true",
            confirmMessage: element.dataset.confirmMessage,
            successMessage: element.dataset.successMessage,
            errorMessage: element.dataset.errorMessage,
        };
    }

    /**
     * Validate configuration object
     * @param {Object} config - Configuration to validate
     * @returns {boolean} Whether config is valid
     */
    validateConfig(config) {
        if (!config.id) {
            console.error("StatusToggle: Missing required data-id attribute");
            return false;
        }

        if (!config.url) {
            console.error("StatusToggle: Missing required data-url attribute");
            return false;
        }

        if (
            config.currentStatus === undefined ||
            config.currentStatus === null
        ) {
            console.error(
                "StatusToggle: Missing required data-current-status attribute"
            );
            return false;
        }

        return true;
    }

    /**
     * Show confirmation dialog
     * @param {Object} config - Configuration object
     * @returns {Promise<boolean>} User confirmation result
     */
    async confirmAction(config) {
        const action = config.currentStatus === 1 ? "deactivate" : "activate";
        const message =
            config.confirmMessage ||
            `Are you sure you want to ${action} this ${config.entityType}?`;

        return new Promise((resolve) => {
            if (window.Swal) {
                // Use SweetAlert2 if available
                Swal.fire({
                    title: "Confirm Action",
                    text: message,
                    icon: "question",
                    showCancelButton: true,
                    confirmButtonColor: "#3085d6",
                    cancelButtonColor: "#d33",
                    confirmButtonText: `Yes, ${action}!`,
                    cancelButtonText: "Cancel",
                }).then((result) => {
                    resolve(result.isConfirmed);
                });
            } else {
                // Fallback to native confirm
                resolve(confirm(message));
            }
        });
    }

    /**
     * Perform the actual status toggle
     * @param {HTMLElement} element - The toggle element
     * @param {Object} config - Configuration object
     */
    async performToggle(element, config) {
        // Show loading state
        this.setLoadingState(element, true);

        try {
            const response = await fetch(config.url, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN":
                        document.querySelector('meta[name="csrf-token"]')
                            ?.content || "",
                    Accept: "application/json",
                },
                body: JSON.stringify({
                    id: config.id,
                    status: config.currentStatus === 1 ? 0 : 1,
                }),
            });

            const data = await response.json();

            if (response.ok && data.success) {
                this.handleSuccess(element, config, data);
            } else {
                throw new Error(data.message || "Unknown error occurred");
            }
        } catch (error) {
            this.handleError(element, config, error);
            throw error;
        } finally {
            this.setLoadingState(element, false);
        }
    }

    /**
     * Handle successful toggle
     * @param {HTMLElement} element - The toggle element
     * @param {Object} config - Configuration object
     * @param {Object} data - Response data
     */
    handleSuccess(element, config, data) {
        // Update element attributes and appearance
        const newStatus = config.currentStatus === 1 ? 0 : 1;
        element.dataset.currentStatus = newStatus;

        // Update visual state
        this.updateElementAppearance(element, newStatus);

        // Update any related UI elements
        this.updateRelatedElements(config.id, newStatus);

        // Show success message
        const message =
            config.successMessage ||
            data.message ||
            `${config.entityType} status updated successfully`;
        this.showSuccess(message);
    }

    /**
     * Handle toggle error
     * @param {HTMLElement} element - The toggle element
     * @param {Object} config - Configuration object
     * @param {Error} error - The error object
     */
    handleError(element, config, error) {
        const message =
            config.errorMessage ||
            error.message ||
            "Failed to update status. Please try again.";
        this.showError(message);
    }

    /**
     * Update element appearance based on status
     * @param {HTMLElement} element - The toggle element
     * @param {number} status - New status (0 or 1)
     */
    updateElementAppearance(element, status) {
        // Remove existing status classes
        element.classList.remove(
            "btn-success",
            "btn-danger",
            "btn-light-success",
            "btn-light-danger"
        );

        // Add appropriate classes based on status
        if (status === 1) {
            element.classList.add("btn-light-success");
            element.innerHTML = '<i class="fas fa-check-circle"></i> Active';
            element.title = "Click to deactivate";
        } else {
            element.classList.add("btn-light-danger");
            element.innerHTML = '<i class="fas fa-times-circle"></i> Inactive';
            element.title = "Click to activate";
        }
    }

    /**
     * Update related UI elements (badges, status indicators, etc.)
     * @param {string} entityId - Entity ID
     * @param {number} status - New status
     */
    updateRelatedElements(entityId, status) {
        // Update status badges in tables
        const statusBadges = document.querySelectorAll(
            `[data-entity-id="${entityId}"][data-type="status-badge"]`
        );
        statusBadges.forEach((badge) => {
            badge.className = `badge ${
                status === 1 ? "badge-success" : "badge-danger"
            }`;
            badge.textContent = status === 1 ? "Active" : "Inactive";
        });

        // Update any custom status indicators
        const customIndicators = document.querySelectorAll(
            `[data-entity-id="${entityId}"][data-type="status-indicator"]`
        );
        customIndicators.forEach((indicator) => {
            indicator.dataset.status = status;
            // Trigger custom event for additional handling
            indicator.dispatchEvent(
                new CustomEvent("statusChanged", {
                    detail: { entityId, status },
                })
            );
        });
    }

    /**
     * Set loading state on element
     * @param {HTMLElement} element - The toggle element
     * @param {boolean} loading - Whether to show loading state
     */
    setLoadingState(element, loading) {
        if (loading) {
            element.disabled = true;
            element.innerHTML =
                '<i class="fas fa-spinner fa-spin"></i> Processing...';
            element.classList.add("disabled");
        } else {
            element.disabled = false;
            element.classList.remove("disabled");
            // Appearance will be updated by updateElementAppearance
        }
    }

    /**
     * Show success message
     * @param {string} message - Success message
     */
    showSuccess(message) {
        if (window.Swal) {
            Swal.fire({
                icon: "success",
                title: "Success!",
                text: message,
                timer: 3000,
                showConfirmButton: false,
            });
        } else if (window.toastr) {
            toastr.success(message);
        } else {
            alert(message);
        }
    }

    /**
     * Show error message
     * @param {string} message - Error message
     */
    showError(message) {
        if (window.Swal) {
            Swal.fire({
                icon: "error",
                title: "Error!",
                text: message,
            });
        } else if (window.toastr) {
            toastr.error(message);
        } else {
            alert("Error: " + message);
        }
    }
}

// Auto-initialize when DOM is ready
if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => {
        window.statusToggle = new StatusToggle();
    });
} else {
    window.statusToggle = new StatusToggle();
}

// Export for module systems
if (typeof module !== "undefined" && module.exports) {
    module.exports = StatusToggle;
}

// Legacy support - maintain backward compatibility
window.toggleStatus = function (element) {
    console.warn(
        'toggleStatus function is deprecated. Use data-action="toggle-status" with data attributes instead.'
    );
    if (window.statusToggle) {
        window.statusToggle.handleToggle(element);
    }
};
