/**
 * User Type Module - JavaScript Component for User Type Management
 * Handles form validation and submission for user type CRUD operations
 * Supports both jQuery Validate and FormValidation libraries
 */
(function () {
    "use strict";

    // Global configuration
    window.UserTypeModule = {
        // Element Selectors
        selectors: {
            createForm: "#createUserTypeForm",
            updateForm: "#updateUserTypeForm",
        },

        // UI Text Configuration
        texts: {
            loading: "Processing...",
            success: "Operation completed successfully",
            error: "An error occurred",
        },

        // Validation Configuration
        validation: {
            enabled: true,
            library: "auto", // auto, jquery, formvalidation
            loadingText: {
                create: "Creating User Type...",
                update: "Updating User Type...",
                default: "Processing...",
            },
        },

        // Instance tracking
        _instances: {
            formHandler: null,
            validationInstance: null,
        },
    };

    /**
     * User Type Module Handler
     */
    class UserTypeModuleHandler {
        constructor(options = {}) {
            this.config = { ...window.UserTypeModule, ...options };
            this.createForm = null;
            this.updateForm = null;
            this.validationLibrary = null;
            this.validationInstance = null;
            this.initialized = false;

            // Detect available validation libraries
            this.detectValidationLibraries();
        }

        /**
         * Initialize the module
         */
        init() {
            if (this.initialized) {
                console.log(
                    "🔄 UserTypeModule already initialized, skipping..."
                );
                return this;
            }

            console.log("🚀 Initializing UserTypeModule...");

            this.initializeElements();
            this.initializeValidation();

            this.initialized = true;
            window.UserTypeModule._instances.formHandler = this;

            console.log("✅ UserTypeModule initialization complete");
            return this;
        }

        /**
         * Detect available validation libraries
         */
        detectValidationLibraries() {
            const libraries = {
                jquery: typeof $ !== "undefined" && $.fn && $.fn.validate,
                formvalidation:
                    typeof FormValidation !== "undefined" &&
                    FormValidation.formValidation,
            };

            console.log(
                "🔍 UserType validation libraries detected:",
                libraries
            );

            // Auto-select validation library
            if (this.config.validation.library === "auto") {
                if (libraries.formvalidation) {
                    this.validationLibrary = "formvalidation";
                } else if (libraries.jquery) {
                    this.validationLibrary = "jquery";
                } else {
                    this.validationLibrary = null;
                    console.warn(
                        "⚠️ No validation libraries found for UserType"
                    );
                }
            } else {
                this.validationLibrary = this.config.validation.library;
            }

            console.log(
                "🎯 UserType using validation library:",
                this.validationLibrary
            );
        }

        /**
         * Initialize DOM elements
         */
        initializeElements() {
            console.log("🔍 Looking for UserType elements...");

            // Find forms
            this.createForm = document.querySelector(
                this.config.selectors.createForm
            );
            this.updateForm = document.querySelector(
                this.config.selectors.updateForm
            );

            // Log findings
            console.log("🔍 UserType Element Detection Results:");
            console.log("  Create form:", !!this.createForm, this.createForm);
            console.log("  Update form:", !!this.updateForm, this.updateForm);

            // Initialize Select2 if available
            this.initializeSelect2();
        }

        /**
         * Initialize Select2 for dropdowns
         */
        initializeSelect2() {
            if (typeof $ !== "undefined" && $.fn.select2) {
                console.log(
                    "🔧 Initializing Select2 for UserType dropdowns..."
                );

                // Initialize Select2 for role and status dropdowns
                $('select[data-control="select2"]').each(function () {
                    const $select = $(this);
                    if (!$select.hasClass("select2-hidden-accessible")) {
                        $select.select2({
                            placeholder:
                                $select.data("placeholder") ||
                                "Select an option",
                            allowClear: false,
                            minimumResultsForSearch: -1, // Disable search for short lists
                        });
                    }
                });

                console.log("✅ Select2 initialization complete for UserType");
            } else {
                console.log("ℹ️ Select2 not available, using standard selects");
            }
        }

        /**
         * Initialize validation for forms
         */
        initializeValidation() {
            if (!this.config.validation.enabled) {
                console.log("⚠️ UserType validation disabled in config");
                return;
            }

            if (!this.validationLibrary) {
                console.log("⚠️ No validation library available for UserType");
                return;
            }

            console.log(
                `🔧 Initializing ${this.validationLibrary} validation for UserType...`
            );

            // Initialize validation for found forms
            if (this.createForm) {
                this.setupFormValidation(this.createForm, "create");
            }

            if (this.updateForm) {
                this.setupFormValidation(this.updateForm, "update");
            }

            if (!this.createForm && !this.updateForm) {
                console.log("ℹ️ No user type forms found for validation");
            }
        }

        /**
         * Setup validation for a specific form
         */
        setupFormValidation(form, type) {
            console.log(`🔧 Setting up ${type} UserType form validation`);
            console.log(`🔧 Form element:`, form);
            console.log(`🔧 Form ID:`, form.id);
            console.log(`🔧 Validation Library:`, this.validationLibrary);

            if (this.validationLibrary === "jquery") {
                this.setupJQueryValidation(form, type);
            } else if (this.validationLibrary === "formvalidation") {
                this.setupFormValidation_FV(form, type);
            } else {
                console.error(
                    "❌ No valid validation library selected for UserType"
                );
            }
        }

        /**
         * Setup jQuery Validation
         */
        setupJQueryValidation(form, type) {
            console.log(`🔧 Starting jQuery validation setup for UserType...`);

            if (typeof $ === "undefined" || !$.fn.validate) {
                console.error("❌ jQuery Validate not available for UserType");
                return;
            }

            try {
                const $form = $(form);
                const config = {
                    rules: this.getValidationRules(),
                    messages: this.getValidationMessages(),
                    errorElement: "div",
                    errorClass: "invalid-feedback",
                    validClass: "valid-feedback",
                    ignore: "", // Don't ignore hidden elements (needed for Select2)
                    errorPlacement: function (error, element) {
                        error.addClass("invalid-feedback");

                        // Handle Select2 elements
                        if (element.hasClass("select2-hidden-accessible")) {
                            error.insertAfter(
                                element.next(".select2-container")
                            );
                        } else {
                            element
                                .closest(".form-group, .mb-3, .fv-row")
                                .append(error);
                        }
                    },
                    highlight: function (element) {
                        $(element)
                            .addClass("is-invalid")
                            .removeClass("is-valid");

                        // Handle Select2 styling
                        if ($(element).hasClass("select2-hidden-accessible")) {
                            $(element)
                                .next(".select2-container")
                                .addClass("is-invalid");
                        }

                        $(element)
                            .closest(".form-group, .mb-3, .fv-row")
                            .find(".form-text, .text-muted")
                            .hide();
                    },
                    unhighlight: function (element) {
                        $(element)
                            .addClass("is-valid")
                            .removeClass("is-invalid");

                        // Handle Select2 styling
                        if ($(element).hasClass("select2-hidden-accessible")) {
                            $(element)
                                .next(".select2-container")
                                .removeClass("is-invalid")
                                .addClass("is-valid");
                        }

                        $(element)
                            .closest(".form-group, .mb-3, .fv-row")
                            .find(".form-text, .text-muted")
                            .show();
                    },
                    submitHandler: (form) => {
                        this.handleFormSubmission(form, type);
                    },
                };

                console.log(
                    `🔧 UserType validation rules:`,
                    Object.keys(config.rules)
                );

                const validator = $form.validate(config);
                this.validationInstance = validator;
                window.UserTypeModule._instances.validationInstance = validator;

                // Add Select2 change event handlers for validation
                $form
                    .find('select[data-control="select2"]')
                    .on("change", function () {
                        $(this).valid();
                    });

                console.log("✅ jQuery validation setup complete for UserType");
            } catch (error) {
                console.error(
                    "❌ jQuery validation setup failed for UserType:",
                    error
                );
            }
        }

        /**
         * Setup FormValidation Library
         */
        setupFormValidation_FV(form, type) {
            console.log(`🔧 Starting FormValidation setup for UserType...`);

            if (typeof FormValidation === "undefined") {
                console.error(
                    "❌ FormValidation library not available for UserType"
                );
                return;
            }

            try {
                const fields = this.getFormValidationFields();

                const fv = FormValidation.formValidation(form, {
                    fields: fields,
                    plugins: {
                        trigger: new FormValidation.plugins.Trigger(),
                        bootstrap: new FormValidation.plugins.Bootstrap5({
                            rowSelector: ".fv-row",
                            eleInvalidClass: "is-invalid",
                            eleValidClass: "is-valid",
                        }),
                        submitButton: new FormValidation.plugins.SubmitButton(),
                        icon: new FormValidation.plugins.Icon({
                            valid: "fa fa-check",
                            invalid: "fa fa-times",
                            validating: "fa fa-refresh",
                        }),
                    },
                }).on("core.form.valid", () => {
                    this.handleFormSubmission(form, type);
                });

                this.validationInstance = fv;
                window.UserTypeModule._instances.validationInstance = fv;

                console.log("✅ FormValidation setup complete for UserType");
            } catch (error) {
                console.error(
                    "❌ FormValidation setup failed for UserType:",
                    error
                );
            }
        }

        /**
         * Handle form submission
         */
        handleFormSubmission(form, type) {
            console.log(`📤 Handling ${type} UserType form submission`);

            const $form = $(form);
            const submitBtn = $form.find('button[type="submit"]');
            const originalText = submitBtn.html();
            const loadingText =
                this.config.validation.loadingText[type] ||
                this.config.validation.loadingText.default;

            // Show loading state
            this.setLoadingState(submitBtn, loadingText, true);

            // Add a small delay to show loading state before submission
            setTimeout(() => {
                try {
                    // Submit the form
                    form.submit();
                } catch (error) {
                    console.error("❌ Form submission error:", error);
                    this.setLoadingState(submitBtn, originalText, false);
                }
            }, 100);
        }

        /**
         * Set loading state for buttons
         */
        setLoadingState(button, text, isLoading) {
            if (isLoading) {
                button
                    .prop("disabled", true)
                    .html(
                        `<span class="spinner-border spinner-border-sm me-2"></span>${text}`
                    );
            } else {
                button.prop("disabled", false).html(text);
            }
        }

        /**
         * Reset form validation
         */
        resetFormValidation(form) {
            if (this.validationInstance) {
                if (this.validationLibrary === "jquery") {
                    this.validationInstance.resetForm();
                } else if (this.validationLibrary === "formvalidation") {
                    this.validationInstance.resetForm();
                }

                // Reset visual states
                $(form)
                    .find(".is-invalid, .is-valid")
                    .removeClass("is-invalid is-valid");
                $(form).find(".invalid-feedback").remove();
                $(form)
                    .find(".select2-container")
                    .removeClass("is-invalid is-valid");
            }
        }

        /**
         * Get validation rules for UserType fields
         */
        getValidationRules() {
            return {
                name: {
                    required: true,
                    minlength: 2,
                    maxlength: 100,
                },
                role_id: {
                    required: function (element) {
                        return (
                            $(element).is(":visible") && $(element).val() !== ""
                        );
                    },
                },
                status: {
                    required: true,
                },
            };
        }

        /**
         * Get validation messages for UserType fields
         */
        getValidationMessages() {
            return {
                name: {
                    required: "Please enter a user type name",
                    minlength:
                        "User type name must be at least 2 characters long",
                    maxlength: "User type name cannot exceed 100 characters",
                },
                role_id: {
                    required: "Please select a role",
                },
                status: {
                    required: "Please select a status",
                },
            };
        }

        /**
         * Get FormValidation fields configuration
         */
        getFormValidationFields() {
            return {
                name: {
                    validators: {
                        notEmpty: {
                            message: "Please enter a user type name",
                        },
                        stringLength: {
                            min: 2,
                            max: 100,
                            message:
                                "User type name must be between 2 and 100 characters",
                        },
                    },
                },
                role_id: {
                    validators: {
                        notEmpty: {
                            message: "Please select a role",
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
        }
    }

    // Expose the module
    window.UserTypeModule.init = function () {
        const handler = new UserTypeModuleHandler();
        return handler.init();
    };

    window.UserTypeModule.getInstance = function () {
        return this._instances.formHandler;
    };

    window.UserTypeModule.getValidationInstance = function () {
        return this._instances.validationInstance;
    };

    // Simple initialization method for templates
    window.UserTypeModule.quickInit = function () {
        return this.init();
    };

    // Auto-detect and log available libraries on load
    document.addEventListener("DOMContentLoaded", function () {
        setTimeout(function () {
            console.log("🔍 UserTypeModule: Checking available libraries...");
            console.log("  jQuery:", typeof $ !== "undefined" ? "✅" : "❌");
            console.log(
                "  jQuery Validate:",
                typeof $ !== "undefined" && $.fn && $.fn.validate ? "✅" : "❌"
            );
            console.log(
                "  FormValidation:",
                typeof FormValidation !== "undefined" ? "✅" : "❌"
            );
        }, 100);
    });
})();
