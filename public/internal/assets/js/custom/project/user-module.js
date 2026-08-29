/**
 * User Module - Unified JavaScript Component
 * Combines form handling, validation, and state functionality
 * Supports both jQuery Validate and FormValidation libraries
 */
(function () {
    "use strict";

    console.log("🚀 UserModule script loaded successfully");

    // Global configuration
    window.UserModule = {
        // API Configuration - Module-specific endpoints only
        api: {
            endpoints: {
                // States are pre-loaded in the component, no API URL needed
                cities: "/locations/cities/:state_id",
            },
            routes: {}, // Backward compatibility
        },

        // Element Selectors
        selectors: {
            stateSelect: "select[name='state_id']",
            createForm: "#createUserForm",
            updateForm: "#updateUserForm",
        },

        // UI Text Configuration
        texts: {
            loading: "Loading states...",
            selectState: "Select State",
            errorLoading: "Error loading states",
            noStates: "No states available",
        },

        // Validation Configuration
        validation: {
            enabled: true,
            library: "auto", // auto, jquery, formvalidation
            loadingText: {
                create: "Creating...",
                update: "Updating...",
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
     * Unified User Module Handler
     */
    class UserModuleHandler {
        constructor(options = {}) {
            this.config = { ...window.UserModule, ...options };
            this.stateSelect = null;
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
                console.log("🔄 UserModule already initialized, skipping...");
                return this;
            }

            console.log("🚀 Initializing UserModule...");

            this.initializeElements();
            // State change handling removed - states are pre-loaded in components
            this.initializeValidation();
            // Restore state_id from data-old-value (after validation redirect or edit form prefill)
            this.restoreStateSelectValues();

            this.initialized = true;
            window.UserModule._instances.formHandler = this;

            console.log("✅ UserModule initialization complete");
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

            console.log("🔍 Validation libraries detected:", libraries);

            // Auto-select validation library
            if (this.config.validation.library === "auto") {
                if (libraries.formvalidation) {
                    this.validationLibrary = "formvalidation";
                } else if (libraries.jquery) {
                    this.validationLibrary = "jquery";
                } else {
                    this.validationLibrary = null;
                    console.warn("⚠️ No validation libraries found");
                }
            } else {
                this.validationLibrary = this.config.validation.library;
            }

            console.log("🎯 Using validation library:", this.validationLibrary);
        }

        /**
         * Initialize DOM elements
         */
        initializeElements() {
            console.log("🔍 Looking for elements...");

            // Find state select (any of: state_select_*, or first state_id by name)
            this.stateSelect =
                document.querySelector(this.config.selectors.stateSelect) ||
                document.querySelector("#state_select_customer, #state_select_standard, #state_select_regional") ||
                document.querySelector('[name="state_id"]') ||
                document.querySelector('select[id*="state"]');

            // Find forms
            this.createForm = document.querySelector(
                this.config.selectors.createForm
            );
            this.updateForm = document.querySelector(
                this.config.selectors.updateForm
            );

            // Log findings
            console.log("🔍 Element Detection Results:");
            console.log(
                "  State select:",
                !!this.stateSelect,
                this.stateSelect
            );
            console.log("  Create form:", !!this.createForm, this.createForm);
            console.log("  Update form:", !!this.updateForm, this.updateForm);

            // Debug: List all select elements
            const allSelects = document.querySelectorAll("select");
            console.log("🔍 All select elements on page:");
            allSelects.forEach((select, index) => {
                console.log(`  Select ${index + 1}:`, {
                    id: select.id || "no-id",
                    name: select.name || "no-name",
                    element: select,
                });
            });
        }


        /**
         * Initialize validation for forms
         */
        initializeValidation() {
            if (!this.config.validation.enabled) {
                console.log("⚠️ Validation disabled in config");
                return;
            }

            if (!this.validationLibrary) {
                console.log("⚠️ No validation library available");
                return;
            }

            console.log(
                `🔧 Initializing ${this.validationLibrary} validation...`
            );

            // Initialize validation for found forms
            if (this.createForm) {
                this.setupFormValidation(this.createForm, "create");
            }

            if (this.updateForm) {
                this.setupFormValidation(this.updateForm, "update");
            }

            if (!this.createForm && !this.updateForm) {
                console.log("ℹ️ No user forms found for validation");
            }
        }

        /**
         * Setup validation for a specific form
         */
        setupFormValidation(form, type) {
            const roleType =
                form.dataset.roleType ||
                form.querySelector('[name="role_type"]')?.value ||
                "default";

            console.log(
                `🔧 Setting up ${type} form validation for role:`,
                roleType
            );
            console.log(`🔧 Form element:`, form);
            console.log(`🔧 Form ID:`, form.id);
            console.log(`🔧 Validation Library:`, this.validationLibrary);

            if (this.validationLibrary === "jquery") {
                this.setupJQueryValidation(form, type, roleType);
            } else if (this.validationLibrary === "formvalidation") {
                this.setupFormValidation_FV(form, type, roleType);
            } else {
                console.error("❌ No valid validation library selected");
            }
        }

        /**
         * Setup jQuery Validation
         */
        setupJQueryValidation(form, type, roleType) {
            console.log(`🔧 Starting jQuery validation setup...`);
            console.log(`🔧 Form:`, form);
            console.log(`🔧 Form ID:`, form.id);
            console.log(`🔧 Form fields count:`, form.elements.length);
            console.log(`🔧 jQuery available:`, typeof $ !== "undefined");

            // Debug: List all form fields
            console.log("🔍 Form fields analysis:");
            Array.from(form.elements).forEach((element, index) => {
                if (element.name) {
                    console.log(
                        `  Field ${index + 1}: ${element.name} (${
                            element.type
                        }) - ID: ${element.id || "no-id"}`
                    );
                }
            });

            if (typeof $ === "undefined") {
                console.error("❌ jQuery not available for validation");
                return;
            }

            console.log(`🔧 jQuery.fn.validate available:`, !!$.fn.validate);
            if (!$.fn.validate) {
                console.error("❌ jQuery Validate plugin not found");
                console.log("🔍 Available jQuery plugins:", Object.keys($.fn));
                return;
            }

            try {
                console.log(`🔧 Initializing custom validation methods...`);
                // Initialize custom validation methods
                this.initJQueryCustomMethods();

                console.log(`🔧 Getting validation configuration...`);
                // Get validation configuration
                const config = this.getJQueryValidationConfig(form, type);
                console.log(
                    `🔧 Validation rules count:`,
                    Object.keys(config.rules).length
                );
                console.log(`🔧 Validation rules:`, Object.keys(config.rules));

                console.log(`🔧 Applying validation to form...`);
                // Initialize validation
                const validator = $(form).validate(config);
                console.log(`🔧 Validator instance:`, validator);

                console.log(`🔧 Binding real-time validation events...`);
                // Bind real-time validation events
                this.bindJQueryRealTimeValidation();

                console.log(`✅ jQuery validation initialized for #${form.id}`);

                // Test validation immediately
                console.log(`🧪 Testing validation setup...`);
                const isValid = $(form).valid();
                console.log(`🧪 Form initially valid:`, isValid);

                // Test individual field validation
                console.log(`🧪 Testing individual field validation...`);
                Object.keys(config.rules).forEach((fieldName) => {
                    const field = form.querySelector(`[name="${fieldName}"]`);
                    if (field) {
                        const fieldValid = $(field).valid();
                        console.log(
                            `🧪 Field '${fieldName}' valid:`,
                            fieldValid
                        );
                    } else {
                        console.log(
                            `⚠️ Field '${fieldName}' not found in form`
                        );
                    }
                });
            } catch (error) {
                console.error(
                    "❌ Error initializing jQuery validation:",
                    error
                );
                console.error("❌ Error stack:", error.stack);
            }
        }

        /**
         * Initialize jQuery custom validation methods
         */
        initJQueryCustomMethods() {
            if (!$.validator) return;

            // International Phone validation (for Sales module)
            $.validator.addMethod(
                "internationalPhone",
                function (value, element) {
                    return (
                        this.optional(element) ||
                        /^[\+]?[1-9][\d]{0,15}$/.test(value)
                    );
                },
                "Please enter a valid international phone number"
            );

            // Indian Phone validation (for Administrator module)
            $.validator.addMethod(
                "indianPhone",
                function (value, element) {
                    return this.optional(element) || /^[6-9]\d{9}$/.test(value);
                },
                "Please enter a valid Indian phone number (10 digits starting with 6-9)"
            );

            // Pattern validation method
            $.validator.addMethod(
                "pattern",
                function (value, element, param) {
                    return this.optional(element) || param.test(value);
                },
                "Please enter a value in the correct format"
            );

            // GST Number validation
            $.validator.addMethod(
                "gstNumber",
                function (value, element) {
                    if (this.optional(element)) return true;
                    const gstRegex =
                        /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/;
                    if (!gstRegex.test(value)) return false;
                    const stateCode = parseInt(value.substring(0, 2));
                    return stateCode >= 1 && stateCode <= 37;
                },
                "Please enter a valid GST number"
            );

            // PAN Number validation
            $.validator.addMethod(
                "panNumber",
                function (value, element) {
                    return (
                        this.optional(element) ||
                        /^[A-Z]{5}[0-9]{4}[A-Z]{1}$/.test(value)
                    );
                },
                "Please enter a valid PAN number (AAAAA9999A format)"
            );

            // Aadhar Number validation
            const self = this; // Capture class instance
            $.validator.addMethod(
                "aadharNumber",
                function (value, element) {
                    if (this.optional(element)) return true;
                    const cleanAadhar = value.replace(/\s/g, "");
                    if (!/^\d{12}$/.test(cleanAadhar)) return false;

                    // Verhoeff algorithm for proper Aadhar validation
                    return self.verifyAadharChecksum(cleanAadhar);
                },
                "Please enter a valid Aadhar number"
            );

            // File size validation
            $.validator.addMethod(
                "fileSize",
                function (value, element, param) {
                    if (element.files.length === 0) return true;
                    return element.files[0].size <= param;
                },
                "File size must be less than 2MB"
            );

            // File type validation
            $.validator.addMethod(
                "fileType",
                function (value, element, param) {
                    if (element.files.length === 0) return true;
                    const allowedTypes = param.split(",");
                    const fileType = element.files[0].type;
                    return allowedTypes.includes(fileType);
                },
                "Please select a valid image file"
            );

            // Range validation for status field
            $.validator.addMethod(
                "range",
                function (value, element, param) {
                    const intValue = parseInt(value);
                    return (
                        this.optional(element) ||
                        (intValue >= param[0] && intValue <= param[1])
                    );
                },
                "Please enter a value within the valid range"
            );

            console.log(
                "✅ jQuery custom validation methods initialized with Sales/Administrator support"
            );
        }

        /**
         * Verhoeff algorithm for Aadhar validation
         */
        verifyAadharChecksum(aadhar) {
            const d = [
                [0, 1, 2, 3, 4, 5, 6, 7, 8, 9],
                [1, 2, 3, 4, 0, 6, 7, 8, 9, 5],
                [2, 3, 4, 0, 1, 7, 8, 9, 5, 6],
                [3, 4, 0, 1, 2, 8, 9, 5, 6, 7],
                [4, 0, 1, 2, 3, 9, 5, 6, 7, 8],
                [5, 9, 8, 7, 6, 0, 4, 3, 2, 1],
                [6, 5, 9, 8, 7, 1, 0, 4, 3, 2],
                [7, 6, 5, 9, 8, 2, 1, 0, 4, 3],
                [8, 7, 6, 5, 9, 3, 2, 1, 0, 4],
                [9, 8, 7, 6, 5, 4, 3, 2, 1, 0],
            ];

            const p = [
                [0, 1, 2, 3, 4, 5, 6, 7, 8, 9],
                [1, 5, 7, 6, 2, 8, 3, 0, 9, 4],
                [5, 8, 0, 3, 7, 9, 6, 1, 4, 2],
                [8, 9, 1, 6, 0, 4, 3, 5, 2, 7],
                [9, 4, 5, 3, 1, 2, 6, 8, 7, 0],
                [4, 2, 8, 6, 5, 7, 3, 9, 0, 1],
                [2, 7, 9, 3, 8, 0, 6, 4, 1, 5],
                [7, 0, 4, 6, 9, 1, 3, 2, 5, 8],
            ];

            let c = 0;
            const myArray = aadhar.split("").reverse();
            for (let i = 0; i < myArray.length; i++) {
                c = d[c][p[i % 8][parseInt(myArray[i])]];
            }
            return c === 0;
        }

        /**
         * Get jQuery validation configuration
         */
        getJQueryValidationConfig(form, type) {
            const loadingText =
                this.config.validation.loadingText[type] ||
                this.config.validation.loadingText.default;

            return {
                rules: this.getValidationRules(),
                messages: this.getValidationMessages(),
                errorElement: "div",
                errorClass: "invalid-feedback",
                validClass: "is-valid",
                errorPlacement: function (error, element) {
                    if (
                        element.hasClass("form-select") ||
                        element.hasClass("form-control")
                    ) {
                        error.insertAfter(element);
                    } else {
                        error.insertAfter(element.parent());
                    }
                },
                highlight: function (element, errorClass, validClass) {
                    $(element).addClass("is-invalid").removeClass("is-valid");
                },
                unhighlight: function (element, errorClass, validClass) {
                    $(element).removeClass("is-invalid").addClass("is-valid");
                },
                submitHandler: function (form) {
                    const submitBtn = $(form).find('button[type="submit"]');
                    const originalText = submitBtn.html();
                    submitBtn
                        .html(
                            '<span class="spinner-border spinner-border-sm me-2"></span>' +
                                loadingText
                        )
                        .prop("disabled", true);
                    form.submit();
                },
                invalidHandler: function (event, validator) {
                    if (validator.numberOfInvalids() > 0) {
                        $("html, body").animate(
                            {
                                scrollTop:
                                    $(validator.errorList[0].element).offset()
                                        .top - 100,
                            },
                            500
                        );
                    }
                },
            };
        }

        /**
         * Bind jQuery real-time validation events
         */
        bindJQueryRealTimeValidation() {
            console.log("🔄 Binding jQuery real-time validation events...");

            // Phone number fields - format and validate
            $(
                "#phone_number, #office_phone_number, #alternate_phone_number, #emergency_contact_phone"
            ).on("input", function () {
                let value = $(this).val().replace(/\D/g, "");
                if (value.length > 10) value = value.substr(0, 10);
                $(this).val(value);
                $(this).valid();
                console.log(`📱 Phone validation for ${this.id}: ${value}`);
            });

            // Format GST and PAN numbers (uppercase)
            $("#gst_number, #pan_number").on("input", function () {
                $(this).val($(this).val().toUpperCase());
                $(this).valid();
                console.log(`🏢 GST/PAN validation for ${this.id}`);
            });

            // Format Aadhar numbers (digits only)
            $("#aadhar_card").on("input", function () {
                let value = $(this).val().replace(/\D/g, "");
                if (value.length > 12) value = value.substr(0, 12);
                $(this).val(value);
                $(this).valid();
                console.log(`🆔 Aadhar validation: ${value}`);
            });

            // Format postal codes (digits only)
            $("#pincode, #zipcode").on("input", function () {
                let value = $(this).val().replace(/\D/g, "");
                if (value.length > 6) value = value.substr(0, 6);
                $(this).val(value);
                $(this).valid();
                console.log(`📮 Zipcode validation for ${this.id}: ${value}`);
            });

            // Validate on change for select fields
            $("#state_id, #status, #user_type_id").on(
                "change",
                function () {
                    $(this).valid();
                    console.log(`📋 Select field validation for ${this.id}`);
                }
            );

            // Real-time validation for text fields (on blur for better UX)
            $("#name, #email, #contact_name, #address, #street, #city").on(
                "blur",
                function () {
                    $(this).valid();
                    console.log(`✏️ Text field validation for ${this.id}`);
                }
            );

            // Real-time validation for email fields
            $("#email, #alternate_email").on("blur", function () {
                $(this).valid();
                console.log(`📧 Email validation for ${this.id}`);
            });

            // Real-time validation for URL fields
            $("#website").on("blur", function () {
                $(this).valid();
                console.log(`🌐 URL validation for ${this.id}`);
            });

            // File upload validation
            $("#image").on("change", function () {
                $(this).valid();
                console.log(`📷 File validation for ${this.id}`);
            });

            console.log(
                "✅ jQuery real-time validation events bound for all fields"
            );
        }

        /**
         * Setup FormValidation library
         */
        setupFormValidation_FV(form, type, roleType) {
            if (typeof FormValidation === "undefined") {
                console.error("❌ FormValidation library not available");
                return;
            }

            try {
                // Register custom validators
                this.registerFVCustomValidators();

                // Create configuration
                const config = this.getFVValidationConfig(form, type);

                // Initialize FormValidation
                this.validationInstance = FormValidation.formValidation(
                    form,
                    config
                );
                window.UserModule._instances.validationInstance =
                    this.validationInstance;

                // When validation succeeds, submit the form (SubmitButton disables button but doesn't submit by default)
                this.validationInstance.on("core.form.valid", () => {
                    this.handleFormSubmission(form, type);
                });

                // Re-enable submit button when validation fails (SubmitButton plugin may leave it disabled)
                this.validationInstance.on("core.form.invalid", () => {
                    this.reEnableSubmitButton(form);
                });

                // Ensure submit button is enabled on init (e.g. after redirect back with validation errors)
                this.reEnableSubmitButton(form);

                console.log(`✅ FormValidation initialized for #${form.id}`);
            } catch (error) {
                console.error("❌ Error initializing FormValidation:", error);
            }
        }

        /**
         * Handle form submission when validation succeeds (submit form so page navigates)
         */
        handleFormSubmission(form, type) {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                const loadingText = this.config.validation.loadingText[type] || this.config.validation.loadingText.default;
                submitBtn.disabled = true;
                submitBtn.setAttribute("data-kt-indicator", "on");
                const originalHtml = submitBtn.innerHTML;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>' + (loadingText || "Processing...");
            }
            form.submit();
        }

        /**
         * Restore state_id select value from data-old-value (after validation redirect or edit prefilled)
         */
        restoreStateSelectValues() {
            document.querySelectorAll('select[name="state_id"]').forEach((sel) => {
                const oldVal = sel.getAttribute("data-old-value");
                if (oldVal && (sel.value === "" || sel.value !== oldVal)) {
                    const option = sel.querySelector(`option[value="${oldVal}"]`);
                    if (option) {
                        sel.value = oldVal;
                        if (typeof $ !== "undefined" && $(sel).data("select2")) {
                            $(sel).val(oldVal).trigger("change");
                        }
                    }
                }
            });
        }

        /**
         * Re-enable submit button and remove loading state (after validation fail or on page load)
         */
        reEnableSubmitButton(form) {
            if (!form) return;
            const submitBtn = form.querySelector('button[type="submit"]');
            if (!submitBtn) return;
            submitBtn.disabled = false;
            submitBtn.removeAttribute("disabled");
            submitBtn.removeAttribute("data-kt-indicator");
        }

        /**
         * Register FormValidation custom validators - Role-aware validators
         */
        registerFVCustomValidators() {
            if (!FormValidation.validators) return;

            // International Phone validation (for Sales module)
            FormValidation.validators.internationalPhone = {
                validate: function (input) {
                    if (input.value === "") return { valid: true };
                    return {
                        valid: /^[\+]?[1-9][\d]{0,15}$/.test(input.value),
                        message:
                            "Please enter a valid international phone number",
                    };
                },
            };

            // Indian Phone validation (for Administrator module)
            FormValidation.validators.indianPhone = {
                validate: function (input) {
                    if (input.value === "") return { valid: true };
                    return {
                        valid: /^[6-9]\d{9}$/.test(input.value),
                        message:
                            "Please enter a valid Indian phone number (10 digits starting with 6-9)",
                    };
                },
            };

            // Administrator phone pattern validation
            FormValidation.validators.administratorPhone = {
                validate: function (input) {
                    if (input.value === "") return { valid: true };
                    const cleanValue = input.value.replace(/[^0-9+]/g, "");
                    return {
                        valid:
                            /^([0-9\s\-\+\(\)]*)$/.test(input.value) &&
                            cleanValue.length >= 10 &&
                            cleanValue.length <= 15,
                        message:
                            "Please provide a valid phone number (10-15 digits)",
                    };
                },
            };

            // Name pattern validation
            FormValidation.validators.namePattern = {
                validate: function (input) {
                    if (input.value === "") return { valid: true };
                    return {
                        valid: /^[a-zA-Z\s\.\-']+$/.test(input.value),
                        message:
                            "Name can only contain letters, spaces, hyphens, dots and apostrophes.",
                    };
                },
            };

            FormValidation.validators.gstNumber = {
                validate: function (input) {
                    if (input.value === "") return { valid: true };
                    const gstRegex =
                        /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/;
                    if (!gstRegex.test(input.value)) {
                        return {
                            valid: false,
                            message: "Please enter a valid GST number format.",
                        };
                    }
                    const stateCode = parseInt(input.value.substring(0, 2));
                    return {
                        valid: stateCode >= 1 && stateCode <= 37,
                        message:
                            "Please enter a valid GST number with valid state code",
                    };
                },
            };

            FormValidation.validators.panNumber = {
                validate: function (input) {
                    if (input.value === "") return { valid: true };
                    return {
                        valid: /^[A-Z]{5}[0-9]{4}[A-Z]{1}$/.test(input.value),
                        message:
                            "Please enter a valid PAN number (AAAAA9999A format)",
                    };
                },
            };

            FormValidation.validators.aadharNumber = {
                validate: function (input) {
                    if (input.value === "") return { valid: true };
                    const cleanAadhar = input.value.replace(/\s/g, "");
                    if (!/^\d{12}$/.test(cleanAadhar)) {
                        return {
                            valid: false,
                            message:
                                "Please enter a valid Aadhar number (12 digits)",
                        };
                    }
                    // Use the same Verhoeff algorithm
                    const isValid =
                        window.UserModule._instances.formHandler.verifyAadharChecksum(
                            cleanAadhar
                        );
                    return {
                        valid: isValid,
                        message: "Please enter a valid Aadhar number",
                    };
                },
            };

            // Postal code validation
            FormValidation.validators.postalCode = {
                validate: function (input) {
                    if (input.value === "") return { valid: true };
                    return {
                        valid: /^[0-9]{6}$/.test(input.value),
                        message: "Please enter a valid 6-digit postal code.",
                    };
                },
            };

            // City name validation
            FormValidation.validators.cityName = {
                validate: function (input) {
                    if (input.value === "") return { valid: true };
                    return {
                        valid: /^[a-zA-Z\s]+$/.test(input.value),
                        message:
                            "City name can only contain letters and spaces.",
                    };
                },
            };

            // User type / Production unit - required only when container is visible and field has required
            FormValidation.validators.userTypeRequired = {
                validate: function (input) {
                    const container = document.getElementById("user_type_field_container");
                    const isVisible = container && container.style.display !== "none";
                    const isRequired = input.element.hasAttribute("required");
                    if (!isVisible || !isRequired) return { valid: true };
                    if (input.value) return { valid: true };
                    const label = document.getElementById("production_unit_label")?.textContent?.trim() || "User Type";
                    return { valid: false, message: "Please select " + label + "." };
                },
            };

            // Business contact name/number - required only when field has required (e.g. customer role)
            FormValidation.validators.contactRequired = {
                validate: function (input) {
                    if (!input.element.hasAttribute("required")) return { valid: true };
                    const v = (input.value || "").trim();
                    if (v) return { valid: true };
                    const label = input.element.id === "contact_number" ? "Business contact number" : "Business contact name";
                    return { valid: false, message: label + " is required." };
                },
            };

            // GST number - required only when field has required (customer)
            FormValidation.validators.gstRequired = {
                validate: function (input) {
                    if (!input.element.hasAttribute("required")) return { valid: true };
                    if ((input.value || "").trim()) return { valid: true };
                    return { valid: false, message: "GST number is required." };
                },
            };

            console.log(
                "✅ FormValidation custom validators registered with Sales/Administrator support"
            );
        }

        /**
         * Get FormValidation configuration
         */
        getFVValidationConfig(form, type) {
            return {
                fields: this.getFVValidationFields(form),
                plugins: {
                    trigger: new FormValidation.plugins.Trigger(),
                    submitButton: new FormValidation.plugins.SubmitButton(),
                    bootstrap: new FormValidation.plugins.Bootstrap5({
                        rowSelector: ".fv-row",
                        eleInvalidClass: "is-invalid",
                        eleValidClass: "is-valid",
                    }),
                    icon: new FormValidation.plugins.Icon({
                        valid: "fa fa-check",
                        invalid: "fa fa-times",
                        validating: "fa fa-refresh",
                    }),
                },
            };
        }

        /**
         * Get validation rules for fields - Role-specific validation
         */
        getValidationRules() {
            // Detect current form context
            const isCustomerForm = $('[data-role-type="customer"]').length > 0;
            const isSalesModule = window.location.pathname.includes("/sales/");

            // Base rules that apply to all forms
            const baseRules = {
                // User Basic Fields - Core validation matching Sales UserUpdateRequest
                name: {
                    required: true,
                    minlength: 2,
                    maxlength: 255,
                    pattern: /^[a-zA-Z\s\.\-']+$/, // Match Sales regex pattern
                },
                email: {
                    required: true,
                    email: true,
                    maxlength: 255,
                    // Note: uniqueness check handled server-side
                },
                phone_number: {
                    required: true,
                    internationalPhone: true, // Use international format for Sales
                },
                status: {
                    required: true,
                    // Status values are strings: 'active', 'inactive', 'suspended', 'pending_approval'
                    // Validation handled by dropdown options and server-side validation
                },

                // Customer-specific fields for Sales module
                company_name: {
                    required: isCustomerForm && isSalesModule,
                    minlength: 2,
                    maxlength: 255,
                },
                gst_number: {
                    gstNumber: true, // GST validation when provided
                },

                // Address fields - required for Sales customers, conditional for others
                address: {
                    required: isCustomerForm && isSalesModule,
                    maxlength: 500,
                },
                street: { maxlength: 255 },
                city: {
                    required: isCustomerForm && isSalesModule,
                    minlength: 2,
                    maxlength: 100,
                    pattern: /^[a-zA-Z\s]+$/, // Only letters and spaces
                },
                state: {
                    required: isCustomerForm && isSalesModule,
                    minlength: 2,
                    maxlength: 100,
                },
                postal_code: {
                    required: isCustomerForm && isSalesModule,
                    pattern: /^[0-9]{6}$/, // 6-digit postal code
                },

                // Contact Details - matching Sales UserUpdateRequest
                alternate_phone: {
                    internationalPhone: true, // Match international format
                },
                alternate_email: {
                    email: true,
                    maxlength: 255,
                    // Different from primary email validation handled server-side
                },
            };

            // Administrator-specific rules (when not in sales module)
            const administratorRules = {
                // Override with Administrator-specific validation
                phone_number: {
                    required: true,
                    pattern: /^([0-9\s\-\+\(\)]*)$/, // Administrator complex pattern
                    minlength: 10,
                    maxlength: 15,
                },
                email: {
                    required: true,
                    email: true,
                    maxlength: 255,
                    // RFC/DNS validation handled server-side
                },

                // Administrator required fields
                aadhar_card: {
                    required: !isSalesModule,
                    aadharNumber: true,
                },
                state_id: {
                    required: !isSalesModule,
                },
                zipcode: {
                    required: !isSalesModule,
                    digits: true,
                    minlength: 6,
                    maxlength: 6,
                },

                // Administrator optional fields
                pan_number: { panNumber: true },
                image: {
                    fileSize: 2097152, // 2MB
                    fileType: "image/jpeg,image/jpg,image/png,image/webp",
                },
                emergency_contact_phone: {
                    pattern: /^[6-9]\d{9}$/, // Indian phone for Administrator
                },
                website: { url: true },
            };

            // Merge rules based on context
            return isSalesModule
                ? baseRules
                : { ...baseRules, ...administratorRules };
        }

        /**
         * Get validation messages - Role-specific messages
         */
        getValidationMessages() {
            const isCustomerForm = $('[data-role-type="customer"]').length > 0;
            const isSalesModule = window.location.pathname.includes("/sales/");

            return {
                // User Basic Fields - matching Sales UserUpdateRequest messages
                name: {
                    required: isCustomerForm
                        ? "Customer name is required."
                        : "Please enter the full name",
                    minlength: "Name must be at least 2 characters",
                    maxlength: "Name must not exceed 255 characters",
                    pattern:
                        "Name can only contain letters, spaces, hyphens, dots and apostrophes.",
                },
                email: {
                    required: "Email address is required.",
                    email: "Please enter a valid email address",
                    maxlength: "Email must not exceed 255 characters",
                },
                phone_number: {
                    required: "Phone number is required.",
                    internationalPhone: "Please enter a valid phone number.",
                    pattern: "Please provide a valid phone number.",
                },
                status: {
                    required: "Status selection is required.",
                },

                // Customer-specific fields (Sales module)
                company_name: {
                    required: "Company name is required.",
                    minlength: "Company name must be at least 2 characters",
                    maxlength: "Company name must not exceed 255 characters",
                },
                gst_number: {
                    gstNumber: "Please enter a valid GST number format.",
                },

                // Address fields - matching Sales validation messages
                address: {
                    required: "Address line 1 is required.",
                    maxlength: "Address cannot exceed 500 characters",
                },
                street: {
                    maxlength: "Address line 2 must not exceed 255 characters",
                },
                city: {
                    required: "City name is required.",
                    minlength: "City name must be at least 2 characters",
                    maxlength: "City name must not exceed 100 characters",
                    pattern: "City name can only contain letters and spaces.",
                },
                state: {
                    required: "State is required.",
                    minlength: "State must be at least 2 characters",
                    maxlength: "State must not exceed 100 characters",
                },
                postal_code: {
                    required: "Postal code is required.",
                    pattern: "Please enter a valid 6-digit postal code.",
                },

                // Contact Details - matching Sales validation messages
                alternate_phone: {
                    internationalPhone:
                        "Please enter a valid alternate phone number.",
                },
                alternate_email: {
                    email: "Please enter a valid alternate email address.",
                    maxlength: "Alternate email must not exceed 255 characters",
                },

                // Administrator-specific fields (when not in Sales module)
                aadhar_card: {
                    required: "Aadhar card number is required.",
                    aadharNumber: "Please enter a valid 12-digit Aadhar number",
                },
                state_id: {
                    required: "Please select a state",
                },
                zipcode: {
                    required: "Please enter the zipcode",
                    digits: "Please enter a valid 6-digit zipcode",
                    minlength: "Zipcode must be 6 digits",
                    maxlength: "Zipcode must be 6 digits",
                },
                pan_number: {
                    panNumber: "Please enter a valid PAN number",
                },
                image: {
                    fileSize: "Avatar may not be greater than 2MB.",
                    fileType:
                        "Avatar must be a file of type: jpeg, jpg, png, webp.",
                },
                emergency_contact_phone: {
                    pattern: "Please enter a valid Indian phone number.",
                },
                website: {
                    url: "Please enter a valid website URL",
                },
            };
        }

        /**
         * Get FormValidation field configuration
         */
        /**
         * Get FormValidation field configuration - Role-aware fields
         */
        getFVValidationFields(form) {
            const fields = {};
            const formEl = form || document.querySelector("#createUserForm") || document.querySelector("#updateUserForm");
            if (!formEl) return fields;
            const roleType = formEl.getAttribute("data-role-type") || "";
            const path = window.location.pathname || "";
            const inForm = (name) => formEl.querySelector(`[name="${name}"]`);
            // Customer form: data-role-type, URL, or form has gst_number (only on customer form)
            const isCustomerForm = roleType === "customer" || /\/customers\/create|\/users\/create\/customer/.test(path) || !!inForm("gst_number");
            const isSalesModule = path.includes("/sales/");

            // Basic user fields
            if (document.querySelector('[name="name"]')) {
                fields.name = {
                    validators: {
                        notEmpty: {
                            message: isCustomerForm
                                ? "Customer name is required."
                                : "Please enter the full name",
                        },
                        stringLength: {
                            min: 2,
                            max: 255,
                            message:
                                "Name must be between 2 and 255 characters",
                        },
                        namePattern: {
                            message:
                                "Name can only contain letters, spaces, hyphens, dots and apostrophes.",
                        },
                    },
                };
            }

            if (document.querySelector('[name="email"]')) {
                fields.email = {
                    validators: {
                        notEmpty: { message: "Email address is required." },
                        emailAddress: {
                            message: "Please enter a valid email address",
                        },
                        stringLength: {
                            max: 255,
                            message: "Email must not exceed 255 characters",
                        },
                    },
                };
            }

            if (document.querySelector('[name="phone_number"]')) {
                const phoneValidator = isSalesModule
                    ? "internationalPhone"
                    : "administratorPhone";
                fields.phone_number = {
                    validators: {
                        notEmpty: { message: "Phone number is required." },
                        [phoneValidator]: {
                            message: isSalesModule
                                ? "Please enter a valid phone number."
                                : "Please provide a valid phone number.",
                        },
                    },
                };
            }

            // Status: only notEmpty (options are strings: 'active', 'inactive') - no 'in' or numeric 0/1 check
            if (inForm("status")) {
                fields.status = {
                    validators: {
                        notEmpty: { message: "Status selection is required." },
                    },
                };
            }

            if (document.querySelector('[name="role"]')) {
                const roleSelect = document.querySelector('[name="role"]');
                const isHiddenRole = roleSelect && roleSelect.type === "hidden";
                if (!isHiddenRole && roleSelect && roleSelect.tagName === "SELECT") {
                    const validRoleValues = Array.from(roleSelect.options)
                        .map((opt) => opt.value)
                        .filter((v) => v !== "");
                    fields.role = {
                        validators: {
                            notEmpty: { message: "Please select a role." },
                            in: {
                                values: validRoleValues,
                                message: "Please select a valid role.",
                            },
                        },
                    };
                }
            }

            const userTypeIdEl = document.querySelector('[name="user_type_id"]');
            if (userTypeIdEl) {
                fields.user_type_id = {
                    validators: {
                        userTypeRequired: {
                            message: "Please select User Type or Production Unit.",
                        },
                    },
                };
            }

            // Customer-specific fields (Sales module)
            if (
                isCustomerForm &&
                isSalesModule &&
                document.querySelector('[name="company_name"]')
            ) {
                fields.company_name = {
                    validators: {
                        notEmpty: { message: "Company name is required." },
                        stringLength: {
                            min: 2,
                            max: 255,
                            message:
                                "Company name must be between 2 and 255 characters",
                        },
                    },
                };
            }

            // Customer form: Business Contact Name, Contact Number, GST, Aadhar (required) - use notEmpty, no selector
            if (isCustomerForm && inForm("contact_name")) {
                fields.contact_name = {
                    validators: {
                        notEmpty: { message: "Business contact name is required." },
                        stringLength: {
                            min: 2,
                            max: 100,
                            message: "Contact name must be between 2 and 100 characters",
                        },
                        regexp: {
                            regexp: /^[a-zA-Z\s\.]+$/,
                            message: "Contact name can only contain letters, spaces and dots.",
                        },
                    },
                };
            }
            if (isCustomerForm && inForm("contact_number")) {
                fields.contact_number = {
                    validators: {
                        notEmpty: { message: "Business contact number is required." },
                        stringLength: { min: 10, max: 15, message: "Contact number must be 10–15 digits." },
                        regexp: {
                            regexp: /^([0-9\s\-\+\(\)]*)$/,
                            message: "Please enter a valid contact number.",
                        },
                    },
                };
            }
            if (isCustomerForm && inForm("gst_number")) {
                fields.gst_number = {
                    validators: {
                        notEmpty: { message: "GST number is required." },
                        gstNumber: {
                            message: "Please enter a valid GST number format.",
                        },
                    },
                };
            }
            if (isCustomerForm && inForm("aadhar_card")) {
                fields.aadhar_card = {
                    validators: {
                        notEmpty: { message: "Aadhar card number is required." },
                        aadharNumber: {
                            message: "Please enter a valid 12-digit Aadhar number",
                        },
                    },
                };
            }
            // Non-customer form: optional contact / gst (custom validators when required attr set)
            if (!isCustomerForm) {
                if (inForm("gst_number")) {
                    fields.gst_number = {
                        validators: {
                            gstRequired: { message: "GST number is required." },
                            gstNumber: { message: "Please enter a valid GST number format." },
                        },
                    };
                }
                if (inForm("contact_name")) {
                    fields.contact_name = {
                        validators: {
                            contactRequired: { message: "Business contact name is required." },
                            stringLength: { min: 2, max: 100, message: "Contact name must be between 2 and 100 characters" },
                            regexp: { regexp: /^[a-zA-Z\s\.]+$/, message: "Contact name can only contain letters, spaces and dots." },
                        },
                    };
                }
                if (inForm("contact_number")) {
                    fields.contact_number = {
                        validators: {
                            contactRequired: { message: "Business contact number is required." },
                            stringLength: { min: 10, max: 15, message: "Contact number must be 10–15 digits." },
                            regexp: { regexp: /^([0-9\s\-\+\(\)]*)$/, message: "Please enter a valid contact number." },
                        },
                    };
                }
            }

            // Fallback: if any of these have HTML required attribute, ensure notEmpty is used
            const requiredWhenHasAttr = [
                { name: "contact_name", notEmptyMsg: "Business contact name is required.", stringLength: { min: 2, max: 100, message: "Contact name must be between 2 and 100 characters" }, regexp: { regexp: /^[a-zA-Z\s\.]+$/, message: "Contact name can only contain letters, spaces and dots." } },
                { name: "contact_number", notEmptyMsg: "Business contact number is required.", stringLength: { min: 10, max: 15, message: "Contact number must be 10–15 digits." }, regexp: { regexp: /^([0-9\s\-\+\(\)]*)$/, message: "Please enter a valid contact number." } },
                { name: "gst_number", notEmptyMsg: "GST number is required.", gstNumber: { message: "Please enter a valid GST number format." } },
                { name: "aadhar_card", notEmptyMsg: "Aadhar card number is required.", aadharNumber: { message: "Please enter a valid 12-digit Aadhar number" } },
                { name: "user_type_id", notEmptyMsg: "Please select User Type." },
                { name: "status", notEmptyMsg: "Status selection is required." },
            ];
            requiredWhenHasAttr.forEach((def) => {
                const el = inForm(def.name);
                if (!el || !el.hasAttribute("required")) return;
                const validators = { notEmpty: { message: def.notEmptyMsg } };
                if (def.stringLength) validators.stringLength = def.stringLength;
                if (def.regexp) validators.regexp = def.regexp;
                if (def.gstNumber) validators.gstNumber = def.gstNumber;
                if (def.aadharNumber) validators.aadharNumber = def.aadharNumber;
                fields[def.name] = { validators };
            });

            // Address fields (required for customer and administrator user forms)
            if (document.querySelector('[name="address"]')) {
                const addressValidators = {
                    notEmpty: { message: "Address is required." },
                    stringLength: {
                        max: 500,
                        message: "Address cannot exceed 500 characters",
                    },
                };
                fields.address = { validators: addressValidators };
            }

            if (document.querySelector('[name="street"]')) {
                fields.street = {
                    validators: {
                        stringLength: {
                            max: 255,
                            message:
                                "Address line 2 must not exceed 255 characters",
                        },
                    },
                };
            }

            if (document.querySelector('[name="city"]')) {
                const cityValidators = {
                    notEmpty: { message: "City name is required." },
                    stringLength: {
                        min: 2,
                        max: 100,
                        message:
                            "City name must be between 2 and 100 characters",
                    },
                    cityName: {
                        message:
                            "City name can only contain letters and spaces.",
                    },
                };
                fields.city = { validators: cityValidators };
            }

            if (document.querySelector('[name="state"]')) {
                const stateValidators = {
                    stringLength: {
                        min: 2,
                        max: 100,
                        message: "State must be between 2 and 100 characters",
                    },
                };
                if (isCustomerForm && isSalesModule) {
                    stateValidators.notEmpty = { message: "State is required." };
                }
                fields.state = { validators: stateValidators };
            }

            // state_id (select) - required when element exists (customer + administrator)
            if (document.querySelector('[name="state_id"]')) {
                fields.state_id = {
                    validators: {
                        notEmpty: { message: "Please select a state." },
                    },
                };
            }

            // zipcode - required when element exists
            if (document.querySelector('[name="zipcode"]')) {
                fields.zipcode = {
                    validators: {
                        notEmpty: { message: "Please enter the zipcode." },
                        regexp: {
                            regexp: /^[0-9]{6}$/,
                            message: "Please enter a valid 6-digit zipcode.",
                        },
                    },
                };
            }

            if (document.querySelector('[name="postal_code"]')) {
                const postalValidators = {
                    postalCode: {
                        message: "Please enter a valid 6-digit postal code.",
                    },
                };
                if (isCustomerForm && isSalesModule) {
                    postalValidators.notEmpty = {
                        message: "Postal code is required.",
                    };
                }
                fields.postal_code = { validators: postalValidators };
            }


            // Contact details
            if (document.querySelector('[name="alternate_phone"]')) {
                fields.alternate_phone = {
                    validators: {
                        internationalPhone: {
                            message:
                                "Please enter a valid alternate phone number.",
                        },
                    },
                };
            }

            if (document.querySelector('[name="alternate_email"]')) {
                fields.alternate_email = {
                    validators: {
                        emailAddress: {
                            message:
                                "Please enter a valid alternate email address.",
                        },
                        stringLength: {
                            max: 255,
                            message:
                                "Alternate email must not exceed 255 characters",
                        },
                    },
                };
            }

            // Aadhar card - required when element exists (standard section / non-customer)
            if (!isCustomerForm && inForm("aadhar_card")) {
                fields.aadhar_card = {
                    validators: {
                        notEmpty: { message: "Aadhar card number is required." },
                        aadharNumber: {
                            message: "Please enter a valid 12-digit Aadhar number",
                        },
                    },
                };
            }

            // PAN number - format validation when element exists
            if (document.querySelector('[name="pan_number"]')) {
                fields.pan_number = {
                    validators: {
                        panNumber: {
                            message: "Please enter a valid PAN number",
                        },
                    },
                };
            }

            // Administrator-specific fields (when not in Sales module)
            if (!isSalesModule) {
                if (document.querySelector('[name="image"]')) {
                    fields.image = {
                        validators: {
                            file: {
                                extension: "jpeg,jpg,png,webp",
                                maxSize: 2097152, // 2MB
                                message:
                                    "Avatar must be a file of type: jpeg, jpg, png, webp and not exceed 2MB",
                            },
                        },
                    };
                }

                if (
                    document.querySelector('[name="emergency_contact_phone"]')
                ) {
                    fields.emergency_contact_phone = {
                        validators: {
                            indianPhone: {
                                message:
                                    "Please enter a valid Indian phone number.",
                            },
                        },
                    };
                }

                if (document.querySelector('[name="website"]')) {
                    fields.website = {
                        validators: {
                            uri: {
                                message: "Please enter a valid website URL",
                            },
                        },
                    };
                }
            }

            console.log(
                `🔧 FormValidation fields configured for ${
                    isSalesModule ? "Sales" : "Administrator"
                } module:`,
                Object.keys(fields)
            );

            return fields;
        }

        /**
         * Public API methods
         */
        destroy() {
            if (this.validationInstance) {
                this.validationInstance.destroy();
                this.validationInstance = null;
            }


            this.initialized = false;
            window.UserModule._instances.formHandler = null;
            window.UserModule._instances.validationInstance = null;

            console.log("🗑️ UserModule destroyed");
        }

        validate() {
            if (
                this.validationInstance &&
                this.validationLibrary === "formvalidation"
            ) {
                return this.validationInstance.validate();
            }

            if (this.validationLibrary === "jquery") {
                const form = this.createForm || this.updateForm;
                return form ? $(form).valid() : false;
            }

            return true;
        }

        resetForm() {
            if (
                this.validationInstance &&
                this.validationLibrary === "formvalidation"
            ) {
                this.validationInstance.resetForm(true);
            }

            if (this.validationLibrary === "jquery") {
                const form = this.createForm || this.updateForm;
                if (form) {
                    $(form).validate().resetForm();
                    $(form)
                        .find(".is-invalid, .is-valid")
                        .removeClass("is-invalid is-valid");
                }
            }
        }
    }

    // Global API
    window.UserModule.init = function (options = {}) {
        console.log("🚀 UserModule.init called with options:", options);
        const handler = new UserModuleHandler(options);
        return handler.init();
    };

    window.UserModule.setRoutes = function (routes) {
        this.api.routes = { ...this.api.routes, ...routes };
        console.log("🔧 API routes configured:", this.api.routes);
    };

    window.UserModule.getInstance = function () {
        return this._instances.formHandler;
    };

    window.UserModule.getValidationInstance = function () {
        return this._instances.validationInstance;
    };

    // Simple initialization method for templates
    window.UserModule.quickInit = function (apiRoutes = {}) {
        console.log("🎯 QuickInit called with routes:", apiRoutes);
        this.setRoutes(apiRoutes);
        return window.UserModule.init();
    };

    // Auto-detect and log available libraries on load
    document.addEventListener("DOMContentLoaded", function () {
        setTimeout(function () {
            console.log("🔍 UserModule: Checking available libraries...");
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
