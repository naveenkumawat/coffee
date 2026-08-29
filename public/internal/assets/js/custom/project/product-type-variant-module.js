/**
 * Product Type Variant Module - Form Validation and Handling
 * Based on Product Type Module architecture
 *
 * Features:
 * - Dual validation library support (jQuery Validate & FormValidation)
 * - Auto-detection of available validation libraries
 * - Form submission handling with loading states
 * - Bootstrap 5 styling integration
 * - Comprehensive field validation
 */

(function () {
    "use strict";

    /**
     * Main Product Type Variant Module Handler
     */
    class ProductTypeVariantModuleHandler {
        constructor(config = {}) {
            this.config = this.mergeConfig(config);
            this.validationLibrary = null;
            this.createForm = null;
            this.updateForm = null;

            this.init();
        }

        /**
         * Merge user config with defaults
         */
        mergeConfig(userConfig) {
            const defaultConfig = {
                forms: {
                    create: "#createProductTypeVariantForm",
                    update: "#updateProductTypeVariantForm",
                },
                validation: {
                    library: "auto", // 'auto', 'jquery', 'formvalidation'
                    loadingText: {
                        create: "Creating Product Type Variant...",
                        update: "Updating Product Type Variant...",
                        default: "Processing...",
                    },
                },
                _instances: {},
            };

            return this.deepMerge(defaultConfig, userConfig);
        }

        /**
         * Deep merge objects
         */
        deepMerge(target, source) {
            const result = { ...target };
            for (const key in source) {
                if (source.hasOwnProperty(key)) {
                    if (
                        typeof source[key] === "object" &&
                        !Array.isArray(source[key]) &&
                        source[key] !== null
                    ) {
                        result[key] = this.deepMerge(
                            target[key] || {},
                            source[key]
                        );
                    } else {
                        result[key] = source[key];
                    }
                }
            }
            return result;
        }

        /**
         * Initialize the module
         */
        init() {
            console.log("🚀 Initializing Product Type Variant Module");

            this.detectValidationLibrary();
            this.initializeForms();

            console.log(
                "✅ Product Type Variant Module initialized successfully"
            );
        }

        /**
         * Detect available validation library
         */
        detectValidationLibrary() {
            if (this.config.validation.library !== "auto") {
                this.validationLibrary = this.config.validation.library;
                console.log(
                    `📋 Using configured validation library: ${this.validationLibrary}`
                );
                return;
            }

            // Auto-detect available validation library
            if (
                typeof FormValidation !== "undefined" &&
                FormValidation.formValidation
            ) {
                this.validationLibrary = "formvalidation";
                console.log("📋 Using FormValidation library");
            } else if (typeof $ !== "undefined" && $.fn.validate) {
                this.validationLibrary = "jquery";
                console.log("📋 Using jQuery Validate library");
            } else {
                console.warn("⚠️ No validation library detected");
                this.validationLibrary = "none";
            }
        }

        /**
         * Initialize form handlers
         */
        initializeForms() {
            const createFormElement = document.querySelector(
                this.config.forms.create
            );
            const updateFormElement = document.querySelector(
                this.config.forms.update
            );

            if (createFormElement) {
                console.log(
                    "📝 Initializing Product Type Variant creation form"
                );
                this.initializeCreateForm(createFormElement);
            }

            if (updateFormElement) {
                console.log("✏️ Initializing Product Type Variant update form");
                this.initializeUpdateForm(updateFormElement);
            }
        }

        /**
         * Initialize product type variant creation form
         */
        initializeCreateForm(formElement) {
            this.createForm = {
                element: formElement,
                validator: null,
            };

            const validationRules = this.getValidationRules();
            const submitButton = formElement.querySelector(
                'button[type="submit"]'
            );

            if (this.validationLibrary === "formvalidation") {
                this.createForm.validator = this.setupFormValidation(
                    formElement,
                    validationRules
                );
            } else if (this.validationLibrary === "jquery") {
                this.createForm.validator = this.setupJQueryValidation(
                    formElement,
                    validationRules
                );
            }

            // Handle form submission
            if (submitButton) {
                submitButton.addEventListener("click", (e) => {
                    this.handleFormSubmission(e, this.createForm, "create");
                });
            }
        }

        /**
         * Initialize product type variant update form
         */
        initializeUpdateForm(formElement) {
            this.updateForm = {
                element: formElement,
                validator: null,
            };

            const validationRules = this.getValidationRules();
            const submitButton = formElement.querySelector(
                'button[type="submit"]'
            );

            if (this.validationLibrary === "formvalidation") {
                this.updateForm.validator = this.setupFormValidation(
                    formElement,
                    validationRules
                );
            } else if (this.validationLibrary === "jquery") {
                this.updateForm.validator = this.setupJQueryValidation(
                    formElement,
                    validationRules
                );
            }

            // Handle form submission
            if (submitButton) {
                submitButton.addEventListener("click", (e) => {
                    this.handleFormSubmission(e, this.updateForm, "update");
                });
            }
        }

        /**
         * Get validation rules for product type variant forms
         */
        getValidationRules() {
            return {
                name: {
                    validators: {
                        notEmpty: {
                            message: "Product Type Variant name is required",
                        },
                        stringLength: {
                            min: 2,
                            max: 100,
                            message:
                                "Product Type Variant name must be between 2 and 100 characters",
                        },
                        regexp: {
                            regexp: /^[a-zA-Z0-9\s\-\_\.]+$/,
                            message:
                                "Product Type Variant name can only contain letters, numbers, spaces, hyphens, underscores, and periods",
                        },
                    },
                },
                code: {
                    validators: {
                        notEmpty: {
                            message: "Product Type Variant code is required",
                        },
                        stringLength: {
                            min: 1,
                            max: 50,
                            message:
                                "Product Type Variant code must be between 1 and 50 characters",
                        },
                        regexp: {
                            regexp: /^[A-Z0-9\-\_]+$/,
                            message:
                                "Product Type Variant code can only contain uppercase letters, numbers, hyphens, and underscores",
                        },
                    },
                },
                product_type_id: {
                    validators: {
                        notEmpty: {
                            message: "Product Type selection is required",
                        },
                    },
                },
                description: {
                    validators: {
                        stringLength: {
                            max: 1000,
                            message:
                                "Description cannot exceed 1000 characters",
                        },
                    },
                },
                status: {
                    validators: {
                        notEmpty: {
                            message: "Status selection is required",
                        },
                    },
                },
            };
        }

        /**
         * Setup FormValidation library
         */
        setupFormValidation(formElement, validationRules) {
            try {
                return FormValidation.formValidation(formElement, {
                    fields: validationRules,
                    plugins: {
                        trigger: new FormValidation.plugins.Trigger(),
                        bootstrap: new FormValidation.plugins.Bootstrap5({
                            rowSelector: ".mb-10",
                            eleInvalidClass: "",
                            eleValidClass: "",
                        }),
                    },
                });
            } catch (error) {
                console.error("❌ FormValidation setup error:", error);
                return null;
            }
        }

        /**
         * Setup jQuery Validation
         */
        setupJQueryValidation(formElement, validationRules) {
            if (typeof $ === "undefined" || !$.fn.validate) {
                console.warn("⚠️ jQuery Validate not available");
                return null;
            }

            try {
                const jQueryRules = this.convertToJQueryRules(validationRules);
                return $(formElement).validate({
                    rules: jQueryRules.rules,
                    messages: jQueryRules.messages,
                    errorElement: "div",
                    errorClass: "invalid-feedback",
                    validClass: "is-valid",
                    errorClass: "is-invalid",
                    highlight: function (element) {
                        $(element)
                            .addClass("is-invalid")
                            .removeClass("is-valid");
                    },
                    unhighlight: function (element) {
                        $(element)
                            .addClass("is-valid")
                            .removeClass("is-invalid");
                    },
                    errorPlacement: function (error, element) {
                        error.addClass("invalid-feedback");
                        element.closest(".mb-10").append(error);
                    },
                });
            } catch (error) {
                console.error("❌ jQuery Validation setup error:", error);
                return null;
            }
        }

        /**
         * Convert FormValidation rules to jQuery Validate format
         */
        convertToJQueryRules(validationRules) {
            const rules = {};
            const messages = {};

            Object.keys(validationRules).forEach((field) => {
                rules[field] = {};
                messages[field] = {};

                const validators = validationRules[field].validators;
                Object.keys(validators).forEach((validator) => {
                    const config = validators[validator];

                    switch (validator) {
                        case "notEmpty":
                            rules[field].required = true;
                            messages[field].required = config.message;
                            break;
                        case "stringLength":
                            if (config.min) {
                                rules[field].minlength = config.min;
                                messages[field].minlength = config.message;
                            }
                            if (config.max) {
                                rules[field].maxlength = config.max;
                                messages[field].maxlength = config.message;
                            }
                            break;
                        case "regexp":
                            rules[field].pattern = config.regexp;
                            messages[field].pattern = config.message;
                            break;
                    }
                });
            });

            return { rules, messages };
        }

        /**
         * Handle form submission
         */
        async handleFormSubmission(event, formData, action) {
            event.preventDefault();

            const { element: form, validator } = formData;
            const submitButton = event.target;

            // Validate form based on library
            let isValid = true;
            if (this.validationLibrary === "formvalidation" && validator) {
                isValid = await this.validateWithFormValidation(validator);
            } else if (this.validationLibrary === "jquery" && validator) {
                isValid = validator.form();
            }

            if (!isValid) {
                console.log("❌ Form validation failed");
                return false;
            }

            // Show loading state
            this.setSubmitButtonLoading(submitButton, action, true);

            try {
                // Submit form
                form.submit();
            } catch (error) {
                console.error("❌ Form submission error:", error);
                this.setSubmitButtonLoading(submitButton, action, false);
            }
        }

        /**
         * Validate using FormValidation library
         */
        validateWithFormValidation(validator) {
            return new Promise((resolve) => {
                validator.validate().then((status) => {
                    resolve(status === "Valid");
                });
            });
        }

        /**
         * Set submit button loading state
         */
        setSubmitButtonLoading(button, action, isLoading) {
            if (!button) return;

            const loadingText =
                this.config.validation.loadingText[action] ||
                this.config.validation.loadingText.default;
            const originalText = button.innerHTML;

            if (isLoading) {
                button.innerHTML = `
                    <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                    ${loadingText}
                `;
                button.disabled = true;
            } else {
                button.innerHTML = originalText;
                button.disabled = false;
            }
        }

        /**
         * Cleanup method
         */
        destroy() {
            if (this.createForm?.validator) {
                if (this.validationLibrary === "formvalidation") {
                    this.createForm.validator.destroy();
                }
                this.createForm = null;
            }

            if (this.updateForm?.validator) {
                if (this.validationLibrary === "formvalidation") {
                    this.updateForm.validator.destroy();
                }
                this.updateForm = null;
            }

            console.log("🧹 Product Type Variant Module cleaned up");
        }

        /**
         * Quick initialization method for simple usage
         */
        static quickInit(config = {}) {
            return new ProductTypeVariantModuleHandler(config);
        }
    }

    // Make globally available
    if (typeof window !== "undefined") {
        window.ProductTypeVariantModule = ProductTypeVariantModuleHandler;
        console.log("🌐 Product Type Variant Module registered globally");
    }

    // Auto-initialize if DOM is ready
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", () => {
            // Auto-initialization can be enabled by adding data attribute
            if (
                document.querySelector("[data-auto-init-product-type-variant]")
            ) {
                ProductTypeVariantModuleHandler.quickInit();
            }
        });
    }
})();
