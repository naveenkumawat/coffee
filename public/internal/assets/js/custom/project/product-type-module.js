/**
 * Product Type Module - Form Validation and Handling
 * Based on Part Category Module architecture
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
     * Main Product Type Module Handler
     */
    class ProductTypeModuleHandler {
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
                    create: "#createProductTypeForm",
                    update: "#updateProductTypeForm",
                },
                validation: {
                    library: "auto", // 'auto', 'jquery', 'formvalidation'
                    loadingText: {
                        create: "Creating Product Type...",
                        update: "Updating Product Type...",
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
            const output = Object.assign({}, target);
            if (this.isObject(target) && this.isObject(source)) {
                Object.keys(source).forEach((key) => {
                    if (this.isObject(source[key])) {
                        if (!(key in target))
                            Object.assign(output, { [key]: source[key] });
                        else
                            output[key] = this.deepMerge(
                                target[key],
                                source[key]
                            );
                    } else {
                        Object.assign(output, { [key]: source[key] });
                    }
                });
            }
            return output;
        }

        isObject(item) {
            return item && typeof item === "object" && !Array.isArray(item);
        }

        /**
         * Initialize the module
         */
        init() {
            console.log("🎯 Initializing Product Type Module");

            this.detectValidationLibrary();
            this.initializeForms();

            console.log("✅ Product Type Module initialized successfully");
        }

        /**
         * Detect available validation library
         */
        detectValidationLibrary() {
            if (this.config.validation.library !== "auto") {
                this.validationLibrary = this.config.validation.library;
                return;
            }

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
                console.log("📝 Initializing Product Type creation form");
                this.initializeCreateForm(createFormElement);
            }

            if (updateFormElement) {
                console.log("✏️ Initializing Product Type update form");
                this.initializeUpdateForm(updateFormElement);
            }
        }

        /**
         * Initialize product type creation form
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
                this.createForm.validator = this.initializeFormValidation(
                    formElement,
                    validationRules,
                    submitButton,
                    "create"
                );
            } else if (this.validationLibrary === "jquery") {
                this.createForm.validator = this.initializeJQueryValidation(
                    formElement,
                    validationRules,
                    submitButton,
                    "create"
                );
            }
        }

        /**
         * Initialize product type update form
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
                this.updateForm.validator = this.initializeFormValidation(
                    formElement,
                    validationRules,
                    submitButton,
                    "update"
                );
            } else if (this.validationLibrary === "jquery") {
                this.updateForm.validator = this.initializeJQueryValidation(
                    formElement,
                    validationRules,
                    submitButton,
                    "update"
                );
            }
        }

        /**
         * Get validation rules for product type forms
         */
        getValidationRules() {
            return {
                name: {
                    validators: {
                        notEmpty: {
                            message: "Product Type name is required",
                        },
                        stringLength: {
                            min: 2,
                            max: 100,
                            message:
                                "Product Type name must be between 2 and 100 characters",
                        },
                        regexp: {
                            regexp: /^[a-zA-Z0-9\s\-_.,()&]+$/,
                            message:
                                "Product Type name contains invalid characters",
                        },
                    },
                },
                code: {
                    validators: {
                        notEmpty: {
                            message: "Product Type code is required",
                        },
                        stringLength: {
                            min: 2,
                            max: 50,
                            message:
                                "Product Type code must be between 2 and 50 characters",
                        },
                        regexp: {
                            regexp: /^[A-Z0-9\-_]+$/,
                            message:
                                "Product Type code can only contain uppercase letters, numbers, hyphens, and underscores",
                        },
                    },
                },
                status: {
                    validators: {
                        notEmpty: {
                            message: "Status selection is required",
                        },
                        choice: {
                            min: 1,
                            max: 1,
                            message: "Please select a valid status",
                        },
                    },
                },
                description: {
                    validators: {
                        stringLength: {
                            max: 500,
                            message: "Description cannot exceed 500 characters",
                        },
                    },
                },
            };
        }

        /**
         * Initialize FormValidation library
         */
        initializeFormValidation(
            formElement,
            validationRules,
            submitButton,
            formType
        ) {
            const validator = FormValidation.formValidation(formElement, {
                fields: validationRules,
                plugins: {
                    trigger: new FormValidation.plugins.Trigger(),
                    bootstrap: new FormValidation.plugins.Bootstrap5({
                        rowSelector:
                            ".form-floating, .form-group, .mb-3, .mb-5, .mb-10",
                        eleInvalidClass: "",
                        eleValidClass: "",
                    }),
                },
            });

            // Handle form submission
            validator.on("core.form.valid", () => {
                this.handleFormSubmission(
                    formElement,
                    submitButton,
                    this.config.validation.loadingText[formType] ||
                        this.config.validation.loadingText.default
                );
            });

            return validator;
        }

        /**
         * Initialize jQuery Validation
         */
        initializeJQueryValidation(
            formElement,
            validationRules,
            submitButton,
            formType
        ) {
            const $form = $(formElement);

            // Convert FormValidation rules to jQuery Validate format
            const jqueryRules = {};
            const jqueryMessages = {};

            Object.keys(validationRules).forEach((field) => {
                const fieldRules = validationRules[field].validators;
                jqueryRules[field] = {};
                jqueryMessages[field] = {};

                Object.keys(fieldRules).forEach((rule) => {
                    switch (rule) {
                        case "notEmpty":
                            jqueryRules[field].required = true;
                            jqueryMessages[field].required =
                                fieldRules[rule].message;
                            break;
                        case "stringLength":
                            if (fieldRules[rule].min)
                                jqueryRules[field].minlength =
                                    fieldRules[rule].min;
                            if (fieldRules[rule].max)
                                jqueryRules[field].maxlength =
                                    fieldRules[rule].max;
                            jqueryMessages[field].minlength =
                                fieldRules[rule].message;
                            jqueryMessages[field].maxlength =
                                fieldRules[rule].message;
                            break;
                        case "regexp":
                            jqueryRules[field].pattern =
                                fieldRules[rule].regexp;
                            jqueryMessages[field].pattern =
                                fieldRules[rule].message;
                            break;
                    }
                });
            });

            const validator = $form.validate({
                rules: jqueryRules,
                messages: jqueryMessages,
                errorElement: "div",
                errorClass: "invalid-feedback",
                validClass: "is-valid",
                errorClass: "is-invalid",
                highlight: function (element, errorClass, validClass) {
                    $(element).addClass("is-invalid").removeClass("is-valid");
                },
                unhighlight: function (element, errorClass, validClass) {
                    $(element).addClass("is-valid").removeClass("is-invalid");
                },
                submitHandler: (form) => {
                    this.handleFormSubmission(
                        form,
                        submitButton,
                        this.config.validation.loadingText[formType] ||
                            this.config.validation.loadingText.default
                    );
                },
            });

            return validator;
        }

        /**
         * Handle form submission with loading state
         */
        handleFormSubmission(formElement, submitButton, loadingText) {
            console.log("📤 Submitting product type form");

            if (submitButton) {
                const originalText = submitButton.innerHTML;

                // Set loading state
                submitButton.disabled = true;
                submitButton.innerHTML = `
                    <span class="indicator-progress">
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                        ${loadingText}
                    </span>
                `;

                // Submit after a short delay to show loading state
                setTimeout(() => {
                    formElement.submit();
                }, 100);
            } else {
                formElement.submit();
            }
        }

        /**
         * Quick initialization for simple usage
         */
        static quickInit(config = {}) {
            return new ProductTypeModuleHandler(config);
        }

        /**
         * Get or create singleton instance
         */
        static getInstance(key = "default", config = {}) {
            if (!this._instances) this._instances = {};
            if (!this._instances[key]) {
                this._instances[key] = new ProductTypeModuleHandler(config);
            }
            return this._instances[key];
        }

        /**
         * Destroy instance
         */
        destroy() {
            if (this.createForm && this.createForm.validator) {
                if (typeof this.createForm.validator.destroy === "function") {
                    this.createForm.validator.destroy();
                }
            }

            if (this.updateForm && this.updateForm.validator) {
                if (typeof this.updateForm.validator.destroy === "function") {
                    this.updateForm.validator.destroy();
                }
            }

            console.log("🧹 Product Type Module destroyed");
        }
    }

    // Expose to global scope
    if (typeof window !== "undefined") {
        window.ProductTypeModule = ProductTypeModuleHandler;
        console.log("🌍 Product Type Module exposed to global scope");
    }

    // Auto-initialize if in a supportive environment
    if (typeof document !== "undefined" && document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", () => {
            if (
                typeof window.AUTO_INIT_PRODUCT_TYPE_MODULE !== "undefined" &&
                window.AUTO_INIT_PRODUCT_TYPE_MODULE
            ) {
                new ProductTypeModuleHandler();
            }
        });
    }
})();
