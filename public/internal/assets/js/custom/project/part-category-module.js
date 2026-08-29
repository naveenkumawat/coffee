/**
 * Part Category Module - Form Validation and Handling
 * Based on standard module architecture
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
     * Main Part Category Module Handler
     */
    class PartCategoryModuleHandler {
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
                    create: "#createPartCategoryForm",
                    update: "#updatePartCategoryForm",
                },
                validation: {
                    library: "auto", // 'auto', 'jquery', 'formvalidation'
                    loadingText: {
                        create: "Creating Part Category...",
                        update: "Updating Part Category...",
                        default: "Processing...",
                    },
                },
                _instances: {},
            };

            return this.deepMerge(defaultConfig, userConfig);
        }

        /**
         * Deep merge utility
         */
        deepMerge(target, source) {
            for (const key in source) {
                if (source[key] && typeof source[key] === "object") {
                    target[key] = target[key] || {};
                    this.deepMerge(target[key], source[key]);
                } else {
                    target[key] = source[key];
                }
            }
            return target;
        }

        /**
         * Quick initialization method
         */
        static quickInit(config = {}) {
            return new PartCategoryModuleHandler(config);
        }

        /**
         * Initialize the module
         */
        init() {
            console.log("🚀 Initializing Part Category Module...");
            this.detectValidationLibrary();
            this.setupForms();
            this.setupValidation();
            console.log("✅ Part Category Module initialized successfully");
        }

        /**
         * Detect available validation library
         */
        detectValidationLibrary() {
            if (this.config.validation.library !== "auto") {
                this.validationLibrary = this.config.validation.library;
                console.log(
                    `📚 Using configured validation library: ${this.validationLibrary}`
                );
                return;
            }

            // Auto-detect available libraries
            if (typeof FormValidation !== "undefined") {
                this.validationLibrary = "formvalidation";
                console.log("📚 Auto-detected FormValidation library");
            } else if (typeof $ !== "undefined" && $.fn.validate) {
                this.validationLibrary = "jquery";
                console.log("📚 Auto-detected jQuery Validate library");
            } else {
                console.warn("⚠️ No validation library detected");
            }
        }

        /**
         * Setup form references
         */
        setupForms() {
            this.createForm = document.querySelector(this.config.forms.create);
            this.updateForm = document.querySelector(this.config.forms.update);

            if (this.createForm)
                console.log("📝 Create form found:", this.config.forms.create);
            if (this.updateForm)
                console.log("📝 Update form found:", this.config.forms.update);
        }

        /**
         * Setup validation based on detected library
         */
        setupValidation() {
            if (this.validationLibrary === "jquery") {
                this.setupJQueryValidation();
            } else if (this.validationLibrary === "formvalidation") {
                this.setupFormValidation();
            } else {
                console.log(
                    "⚠️ Skipping validation setup - no library available"
                );
            }
        }

        /**
         * Setup jQuery validation
         */
        setupJQueryValidation() {
            console.log("🔧 Setting up jQuery validation for Part Category...");

            const commonRules = {
                name: {
                    required: true,
                    minlength: 2,
                    maxlength: 255,
                },
                description: {
                    maxlength: 1000,
                },
                status: {
                    required: true,
                },
            };

            const commonMessages = {
                name: {
                    required: "Please enter a part category name",
                    minlength:
                        "Part category name must be at least 2 characters",
                    maxlength:
                        "Part category name cannot exceed 255 characters",
                },
                description: {
                    maxlength: "Description cannot exceed 1000 characters",
                },
                status: {
                    required: "Please select a status",
                },
            };

            // Setup create form validation
            if (this.createForm && typeof $ !== "undefined") {
                $(this.createForm).validate({
                    rules: commonRules,
                    messages: commonMessages,
                    errorElement: "div",
                    errorClass: "is-invalid",
                    validClass: "is-valid",
                    errorPlacement: function (error, element) {
                        error.addClass("invalid-feedback");
                        // Check if error already exists to prevent duplicates
                        const existingError =
                            element.siblings(".invalid-feedback");
                        if (existingError.length) {
                            existingError.remove();
                        }
                        error.insertAfter(element);
                    },
                    highlight: function (element) {
                        $(element)
                            .addClass("is-invalid")
                            .removeClass("is-valid");
                    },
                    unhighlight: function (element) {
                        $(element)
                            .removeClass("is-invalid")
                            .addClass("is-valid");
                    },
                    submitHandler: (form) => {
                        this.handleFormSubmission(form, "create");
                    },
                });

                console.log("✅ jQuery validation setup for create form");
            }

            // Setup update form validation
            if (this.updateForm && typeof $ !== "undefined") {
                $(this.updateForm).validate({
                    rules: commonRules,
                    messages: commonMessages,
                    errorElement: "div",
                    errorClass: "is-invalid",
                    validClass: "is-valid",
                    errorPlacement: function (error, element) {
                        error.addClass("invalid-feedback");
                        // Check if error already exists to prevent duplicates
                        const existingError =
                            element.siblings(".invalid-feedback");
                        if (existingError.length) {
                            existingError.remove();
                        }
                        error.insertAfter(element);
                    },
                    highlight: function (element) {
                        $(element)
                            .addClass("is-invalid")
                            .removeClass("is-valid");
                    },
                    unhighlight: function (element) {
                        $(element)
                            .removeClass("is-invalid")
                            .addClass("is-valid");
                    },
                    submitHandler: (form) => {
                        this.handleFormSubmission(form, "update");
                    },
                });

                console.log("✅ jQuery validation setup for update form");
            }
        }

        /**
         * Setup FormValidation library
         */
        setupFormValidation() {
            console.log("🔧 Setting up FormValidation for Part Category...");

            const commonValidators = {
                name: {
                    validators: {
                        notEmpty: {
                            message: "Please enter a part category name",
                        },
                        stringLength: {
                            min: 2,
                            max: 255,
                            message:
                                "Part category name must be between 2 and 255 characters",
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
                            message: "Please select a status",
                        },
                    },
                },
            };

            // Setup create form validation
            if (this.createForm) {
                this.config._instances.createValidation =
                    FormValidation.formValidation(this.createForm, {
                        fields: commonValidators,
                        plugins: {
                            trigger: new FormValidation.plugins.Trigger(),
                            bootstrap: new FormValidation.plugins.Bootstrap5({
                                rowSelector: ".mb-10",
                            }),
                            submitButton:
                                new FormValidation.plugins.SubmitButton(),
                            icon: new FormValidation.plugins.Icon({
                                valid: "fa fa-check",
                                invalid: "fa fa-times",
                                validating: "fa fa-refresh",
                            }),
                        },
                    });

                this.config._instances.createValidation.on(
                    "core.form.valid",
                    () => {
                        this.handleFormSubmission(this.createForm, "create");
                    }
                );

                console.log("✅ FormValidation setup for create form");
            }

            // Setup update form validation
            if (this.updateForm) {
                this.config._instances.updateValidation =
                    FormValidation.formValidation(this.updateForm, {
                        fields: commonValidators,
                        plugins: {
                            trigger: new FormValidation.plugins.Trigger(),
                            bootstrap: new FormValidation.plugins.Bootstrap5({
                                rowSelector: ".mb-10",
                            }),
                            submitButton:
                                new FormValidation.plugins.SubmitButton(),
                            icon: new FormValidation.plugins.Icon({
                                valid: "fa fa-check",
                                invalid: "fa fa-times",
                                validating: "fa fa-refresh",
                            }),
                        },
                    });

                this.config._instances.updateValidation.on(
                    "core.form.valid",
                    () => {
                        this.handleFormSubmission(this.updateForm, "update");
                    }
                );

                console.log("✅ FormValidation setup for update form");
            }
        }

        /**
         * Handle form submission with loading states
         */
        handleFormSubmission(form, type) {
            const submitButton = form.querySelector('button[type="submit"]');
            const originalText = submitButton.textContent;
            const loadingText =
                this.config.validation.loadingText[type] ||
                this.config.validation.loadingText.default;

            // Set loading state
            submitButton.disabled = true;
            submitButton.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span>${loadingText}`;

            // Submit form
            form.submit();

            // Reset button after a delay (in case of validation errors)
            setTimeout(() => {
                submitButton.disabled = false;
                submitButton.textContent = originalText;
            }, 3000);
        }

        /**
         * Public API methods
         */
        validateForm(formType) {
            const form =
                formType === "create" ? this.createForm : this.updateForm;
            if (!form) return false;

            if (
                this.validationLibrary === "jquery" &&
                typeof $ !== "undefined"
            ) {
                return $(form).valid();
            } else if (this.validationLibrary === "formvalidation") {
                const validator =
                    formType === "create"
                        ? this.config._instances.createValidation
                        : this.config._instances.updateValidation;
                return validator ? validator.validate() : false;
            }

            return true;
        }

        resetForm(formType) {
            const form =
                formType === "create" ? this.createForm : this.updateForm;
            if (form) {
                form.reset();

                if (
                    this.validationLibrary === "jquery" &&
                    typeof $ !== "undefined"
                ) {
                    $(form).validate().resetForm();
                } else if (this.validationLibrary === "formvalidation") {
                    const validator =
                        formType === "create"
                            ? this.config._instances.createValidation
                            : this.config._instances.updateValidation;
                    if (validator) validator.resetForm();
                }
            }
        }
    }

    // Auto-initialize on DOM ready
    document.addEventListener("DOMContentLoaded", function () {
        // Only initialize if part category forms are present
        if (
            document.querySelector("#createPartCategoryForm") ||
            document.querySelector("#updatePartCategoryForm")
        ) {
            console.log("🎯 Auto-initializing Part Category Module...");
            PartCategoryModule.quickInit();
        }
    });

    // Export for global access
    window.PartCategoryModuleHandler = PartCategoryModuleHandler;

    // Add quick init method
    window.PartCategoryModule = {
        quickInit: PartCategoryModuleHandler.quickInit,
    };
})();
