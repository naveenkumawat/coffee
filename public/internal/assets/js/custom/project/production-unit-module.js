/**
 * Product Unit Module - Form Validation and Handling
 * Based on Product Type Module architecture
 *
 * Features:
 * - Dual validation library support (jQuery Validate & FormValidation)
 * - Auto-detection of available validation libraries
 * - Form submission handling with loading states
 * - Bootstrap 5 styling integration
 * - Comprehensive field validation
 * - State functionality
 */

(function () {
    "use strict";

    /**
     * Main Product Unit Module Handler
     */
    class ProductionUnitModuleHandler {
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
                    create: "#createProductionUnitForm",
                    update: "#updateProductionUnitForm",
                },
                validation: {
                    library: "auto", // 'auto', 'jquery', 'formvalidation'
                    loadingText: {
                        create: "Creating Production Unit...",
                        update: "Updating Production Unit...",
                        default: "Processing...",
                    },
                },
                _instances: {},
            };

            return this.deepMerge(defaultConfig, userConfig);
        }

        /**
         * Deep merge two objects
         */
        deepMerge(target, source) {
            const result = { ...target };
            for (const key in source) {
                if (
                    source[key] &&
                    typeof source[key] === "object" &&
                    !Array.isArray(source[key])
                ) {
                    result[key] = this.deepMerge(
                        result[key] || {},
                        source[key]
                    );
                } else {
                    result[key] = source[key];
                }
            }
            return result;
        }

        /**
         * Initialize module
         */
        init() {
            console.log("🚀 Initializing Product Unit Module Handler");
            this.detectValidationLibrary();
            this.initializeForms();
            // State change handling removed - states are pre-loaded in components
        }

        /**
         * Auto-detect available validation library
         */
        detectValidationLibrary() {
            if (this.config.validation.library !== "auto") {
                this.validationLibrary = this.config.validation.library;
                return;
            }

            // Check for FormValidation first (preferred)
            if (window.FormValidation && window.FormValidation.formValidation) {
                this.validationLibrary = "formvalidation";
                console.log("📋 Detected FormValidation library");
                return;
            }

            // Fallback to jQuery Validate
            if (window.jQuery && window.jQuery.fn.validate) {
                this.validationLibrary = "jquery";
                console.log("📋 Detected jQuery Validate library");
                return;
            }

            console.warn("⚠️ No validation library detected");
            this.validationLibrary = null;
        }

        /**
         * Initialize all forms
         */
        initializeForms() {
            this.initializeCreateForm();
            this.initializeUpdateForm();
        }

        /**
         * Initialize Create Form
         */
        initializeCreateForm() {
            const formSelector = this.config.forms.create;
            const formElement = document.querySelector(formSelector);

            if (!formElement) {
                console.log(`📝 Create form not found: ${formSelector}`);
                return;
            }

            console.log(`📝 Initializing Create Form: ${formSelector}`);

            if (this.validationLibrary === "formvalidation") {
                this.createForm = this.initFormValidation(
                    formElement,
                    "create"
                );
            } else if (this.validationLibrary === "jquery") {
                this.createForm = this.initJQueryValidation(
                    formElement,
                    "create"
                );
            }
        }

        /**
         * Initialize Update Form
         */
        initializeUpdateForm() {
            const formSelector = this.config.forms.update;
            const formElement = document.querySelector(formSelector);

            if (!formElement) {
                console.log(`📝 Update form not found: ${formSelector}`);
                return;
            }

            console.log(`📝 Initializing Update Form: ${formSelector}`);

            if (this.validationLibrary === "formvalidation") {
                this.updateForm = this.initFormValidation(
                    formElement,
                    "update"
                );
            } else if (this.validationLibrary === "jquery") {
                this.updateForm = this.initJQueryValidation(
                    formElement,
                    "update"
                );
            }
        }

        /**
         * Initialize FormValidation library
         */
        initFormValidation(formElement, formType) {
            return FormValidation.formValidation(formElement, {
                fields: this.getValidationRules(),
                plugins: {
                    trigger: new FormValidation.plugins.Trigger(),
                    bootstrap: new FormValidation.plugins.Bootstrap5({
                        rowSelector: ".mb-10",
                        eleInvalidClass: "",
                        eleValidClass: "",
                    }),
                },
            });
        }

        /**
         * Initialize jQuery Validation
         */
        initJQueryValidation(formElement, formType) {
            return $(formElement).validate({
                rules: this.getJQueryValidationRules(),
                messages: this.getJQueryValidationMessages(),
                errorElement: "span",
                errorClass: "error-message",
                validClass: "success",
                errorPlacement: function (error, element) {
                    error.addClass("invalid-feedback");
                    element.closest(".mb-10").append(error);
                },
                highlight: function (element, errorClass, validClass) {
                    $(element).addClass("is-invalid");
                },
                unhighlight: function (element, errorClass, validClass) {
                    $(element).removeClass("is-invalid");
                },
                submitHandler: (form) => this.handleFormSubmit(form, formType),
            });
        }

        /**
         * Get validation rules for FormValidation
         */
        getValidationRules() {
            return {
                name: {
                    validators: {
                        notEmpty: {
                            message: "Production Unit name is required",
                        },
                        stringLength: {
                            min: 2,
                            max: 100,
                            message:
                                "Name must be between 2 and 100 characters",
                        },
                        regexp: {
                            regexp: /^[a-zA-Z0-9\s\-_.,()&]+$/,
                            message: "Name contains invalid characters",
                        },
                    },
                },
                email: {
                    validators: {
                        notEmpty: { message: "Email is required" },
                        emailAddress: {
                            message: "Please enter a valid email address",
                        },
                        stringLength: {
                            max: 255,
                            message: "Email cannot exceed 255 characters",
                        },
                    },
                },
                phone_number: {
                    validators: {
                        notEmpty: { message: "Phone number is required" },
                        stringLength: {
                            max: 20,
                            message: "Phone number cannot exceed 20 characters",
                        },
                        regexp: {
                            regexp: /^[\+]?[0-9\-\(\)\s]+$/,
                            message: "Please enter a valid phone number",
                        },
                    },
                },
                office_phone_number: {
                    validators: {
                        stringLength: {
                            max: 20,
                            message: "Office phone cannot exceed 20 characters",
                        },
                        regexp: {
                            regexp: /^[\+]?[0-9\-\(\)\s]+$/,
                            message: "Please enter a valid office phone number",
                        },
                    },
                },
                address: {
                    validators: {
                        notEmpty: { message: "Address is required" },
                        stringLength: {
                            max: 500,
                            message: "Address cannot exceed 500 characters",
                        },
                    },
                },
                street: {
                    validators: {
                        stringLength: {
                            max: 255,
                            message: "Street cannot exceed 255 characters",
                        },
                    },
                },
                state_id: {
                    validators: {
                        notEmpty: { message: "Please select a state" },
                    },
                },
                city: {
                    validators: {
                        notEmpty: { message: "City is required" },
                        stringLength: {
                            max: 100,
                            message: "City cannot exceed 100 characters",
                        },
                    },
                },
                zipcode: {
                    validators: {
                        notEmpty: { message: "Zipcode is required" },
                        stringLength: {
                            max: 10,
                            message: "Zipcode cannot exceed 10 characters",
                        },
                    },
                },
                status: {
                    validators: {
                        notEmpty: { message: "Please select status" },
                    },
                },
            };
        }

        /**
         * Get jQuery validation rules
         */
        getJQueryValidationRules() {
            return {
                name: {
                    required: true,
                    minlength: 2,
                    maxlength: 100,
                },
                email: {
                    required: true,
                    email: true,
                    maxlength: 255,
                },
                phone_number: {
                    required: true,
                    maxlength: 20,
                },
                office_phone_number: {
                    maxlength: 20,
                },
                address: {
                    required: true,
                    maxlength: 500,
                },
                street: {
                    maxlength: 255,
                },
                state_id: {
                    required: true,
                },
                city: {
                    required: true,
                    maxlength: 100,
                },
                zipcode: {
                    required: true,
                    maxlength: 10,
                },
                status: {
                    required: true,
                },
            };
        }

        /**
         * Get jQuery validation messages
         */
        getJQueryValidationMessages() {
            return {
                name: {
                    required: "Production Unit name is required",
                    minlength: "Name must be at least 2 characters",
                    maxlength: "Name cannot exceed 100 characters",
                },
                email: {
                    required: "Email is required",
                    email: "Please enter a valid email address",
                    maxlength: "Email cannot exceed 255 characters",
                },
                phone_number: {
                    required: "Phone number is required",
                    maxlength: "Phone number cannot exceed 20 characters",
                },
                office_phone_number: {
                    maxlength: "Office phone cannot exceed 20 characters",
                },
                address: {
                    required: "Address is required",
                    maxlength: "Address cannot exceed 500 characters",
                },
                street: {
                    maxlength: "Street cannot exceed 255 characters",
                },
                state_id: {
                    required: "Please select a state",
                },
                city: {
                    required: "City is required",
                    maxlength: "City cannot exceed 100 characters",
                },
                zipcode: {
                    required: "Zipcode is required",
                    maxlength: "Zipcode cannot exceed 10 characters",
                },
                status: {
                    required: "Please select status",
                },
            };
        }

        /**
         * State handling removed - states are pre-loaded in components
         */
        initializeStateHandlers() {
            // State handling removed - states are pre-loaded in components
            return;
        }

        /**
         * Handle form submission
         */
        handleFormSubmit(form, formType) {
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            const loadingText =
                this.config.validation.loadingText[formType] ||
                this.config.validation.loadingText.default;

            // Set loading state
            submitBtn.innerHTML = loadingText;
            submitBtn.disabled = true;

            // Submit form
            form.submit();
        }

        /**
         * Build API URL for states endpoint
         * DEPRECATED: States are now pre-loaded in the component
         * @returns {string|null} - Returns null as states are pre-loaded
         */
        buildStatesApiUrl() {
            // DEPRECATED: States are now pre-loaded in the component
            // This method is kept for backward compatibility but returns null
            return null;
        }

        /**
         * Fetch states data from API
         * Uses AppConfig.utils.apiRequest if available, otherwise falls back to fetch
         * @param {string} apiUrl - Complete API URL
         * @returns {Promise} - Promise that resolves to response data
         */
        async fetchStates(apiUrl) {
            // Use centralized API request method from AppConfig if available
            if (
                window.AppConfig &&
                window.AppConfig.utils &&
                window.AppConfig.utils.apiRequest
            ) {
                console.log("🔧 Using AppConfig.utils.apiRequest");
                return await window.AppConfig.utils.apiRequest(apiUrl);
            }

            // Fallback to manual fetch if AppConfig is not available
            console.log("🔧 Using fallback fetch method");
            const response = await fetch(apiUrl, {
                method: "GET",
                headers: {
                    Accept: "application/json",
                    "Content-Type": "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                },
            });

            console.log(`📊 API Response Status: ${response.status}`);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            return await response.json();
        }

        /**
         * Quick initialization method
         */
        static quickInit(config = {}) {
            return new ProductionUnitModuleHandler(config);
        }
    }

    // Global exposure
    window.ProductionUnitModuleHandler = ProductionUnitModuleHandler;
    window.ProductionUnitModule = ProductionUnitModuleHandler;

    console.log("✅ Product Unit Module loaded successfully");
})();
