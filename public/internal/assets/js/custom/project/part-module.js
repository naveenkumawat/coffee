/**
 * Part Module - JavaScript Component for Part Management
 * Handles form validation and submission for part CRUD operations
 * Supports both jQuery Validate and FormValidation libraries
 */
(function () {
    "use strict";

    // Global configuration
    window.PartModule = {
        // Element Selectors
        selectors: {
            createForm: "#createPartForm",
            updateForm: "#updatePartForm",
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
                create: "Creating Part...",
                update: "Updating Part...",
            },
        },

        // Initialize the module
        init: function () {
            this.initValidation();
            this.bindEvents();
        },

        // Initialize form validation
        initValidation: function () {
            if (!this.validation.enabled) return;

            const createForm = document.querySelector(
                this.selectors.createForm
            );
            const updateForm = document.querySelector(
                this.selectors.updateForm
            );

            if (createForm) {
                this.setupFormValidation(createForm, "create");
            }

            if (updateForm) {
                this.setupFormValidation(updateForm, "update");
            }
        },

        // Setup validation for a specific form
        setupFormValidation: function (form, type) {
            // Auto-detect validation library
            if (this.validation.library === "auto") {
                if (typeof $.fn.validate !== "undefined") {
                    this.setupJQueryValidation(form, type);
                } else if (typeof FormValidation !== "undefined") {
                    this.setupFormValidation(form, type);
                } else {
                    console.warn(
                        "No validation library detected for Part module"
                    );
                }
            } else if (
                this.validation.library === "jquery" &&
                typeof $.fn.validate !== "undefined"
            ) {
                this.setupJQueryValidation(form, type);
            } else if (
                this.validation.library === "formvalidation" &&
                typeof FormValidation !== "undefined"
            ) {
                this.setupFormValidationLibrary(form, type);
            }
        },

        // jQuery Validate setup
        setupJQueryValidation: function (form, type) {
            const self = this;
            const $form = $(form);

            $form.validate({
                rules: {
                    name: {
                        required: true,
                        minlength: 2,
                        maxlength: 100,
                    },
                    code: {
                        required: true,
                        minlength: 2,
                        maxlength: 50,
                    },
                    user_type_id: {
                        required: true,
                    },
                    part_category_id: {
                        required: true,
                    },
                    description: {
                        maxlength: 500,
                    },
                    status: {
                        required: true,
                    },
                },
                messages: {
                    name: {
                        required: "Please enter part name",
                        minlength: "Part name must be at least 2 characters",
                        maxlength: "Part name cannot exceed 100 characters",
                    },
                    code: {
                        required: "Please enter part code",
                        minlength: "Part code must be at least 2 characters",
                        maxlength: "Part code cannot exceed 50 characters",
                    },
                    user_type_id: {
                        required: "Please select a user type",
                    },
                    part_category_id: {
                        required: "Please select a part category",
                    },
                    description: {
                        maxlength: "Description cannot exceed 500 characters",
                    },
                    status: {
                        required: "Please select status",
                    },
                },
                errorElement: "span",
                errorPlacement: function (error, element) {
                    error.addClass("invalid-feedback");
                    element.closest(".form-group, .fv-row").append(error);
                },
                highlight: function (element) {
                    $(element).addClass("is-invalid");
                },
                unhighlight: function (element) {
                    $(element).removeClass("is-invalid");
                },
                submitHandler: function (form) {
                    self.handleFormSubmission(form, type);
                },
            });
        },

        // FormValidation library setup
        setupFormValidationLibrary: function (form, type) {
            const self = this;

            const validation = FormValidation.formValidation(form, {
                fields: {
                    name: {
                        validators: {
                            notEmpty: {
                                message: "Please enter part name",
                            },
                            stringLength: {
                                min: 2,
                                max: 100,
                                message:
                                    "Part name must be between 2 and 100 characters",
                            },
                        },
                    },
                    code: {
                        validators: {
                            notEmpty: {
                                message: "Please enter part code",
                            },
                            stringLength: {
                                min: 2,
                                max: 50,
                                message:
                                    "Part code must be between 2 and 50 characters",
                            },
                        },
                    },
                    user_type_id: {
                        validators: {
                            notEmpty: {
                                message: "Please select a user type",
                            },
                        },
                    },
                    part_category_id: {
                        validators: {
                            notEmpty: {
                                message: "Please select a part category",
                            },
                        },
                    },
                    description: {
                        validators: {
                            stringLength: {
                                max: 500,
                                message:
                                    "Description cannot exceed 500 characters",
                            },
                        },
                    },
                    status: {
                        validators: {
                            notEmpty: {
                                message: "Please select status",
                            },
                        },
                    },
                },
                plugins: {
                    trigger: new FormValidation.plugins.Trigger(),
                    bootstrap: new FormValidation.plugins.Bootstrap5({
                        rowSelector: ".fv-row",
                    }),
                    submitButton: new FormValidation.plugins.SubmitButton(),
                },
            });

            validation.on("core.form.valid", function () {
                self.handleFormSubmission(form, type);
            });
        },

        // Handle form submission
        handleFormSubmission: function (form, type) {
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;

            // Show loading state
            submitBtn.innerHTML = this.validation.loadingText[type];
            submitBtn.disabled = true;

            // Add spinner
            if (!submitBtn.querySelector(".spinner-border")) {
                const spinner = document.createElement("span");
                spinner.className = "spinner-border spinner-border-sm me-2";
                submitBtn.insertBefore(spinner, submitBtn.firstChild);
            }

            // Submit the form
            form.submit();
        },

        // Bind additional events
        bindEvents: function () {
            // User type selection change event for dependent dropdowns
            const userTypeSelect = document.querySelector(
                'select[name="user_type_id"]'
            );
            if (userTypeSelect) {
                userTypeSelect.addEventListener("change", function () {
                    // Handle dependent dropdowns if needed
                });
            }

            // Part category selection change event
            const partCategorySelect = document.querySelector(
                'select[name="part_category_id"]'
            );
            if (partCategorySelect) {
                partCategorySelect.addEventListener("change", function () {
                    // Handle dependent dropdowns if needed
                });
            }

            // Status toggle events
            document.addEventListener("click", function (e) {
                if (e.target.matches(".status-toggle")) {
                    e.preventDefault();
                    // Handle status toggle
                    if (window.StatusToggle) {
                        window.StatusToggle.handleToggle(e.target);
                    }
                }
            });
        },

        // Utility methods
        utils: {
            showAlert: function (type, message) {
                // Show alert using your preferred method
                if (typeof toastr !== "undefined") {
                    toastr[type](message);
                } else if (typeof Swal !== "undefined") {
                    Swal.fire({
                        icon: type === "success" ? "success" : "error",
                        text: message,
                    });
                } else {
                    alert(message);
                }
            },

            resetForm: function (formSelector) {
                const form = document.querySelector(formSelector);
                if (form) {
                    form.reset();
                    // Clear validation errors if using jQuery Validate
                    if (typeof $ !== "undefined" && $.fn.validate) {
                        $(form).validate().resetForm();
                    }
                }
            },
        },
    };

    // Auto-initialize when DOM is ready
    document.addEventListener("DOMContentLoaded", function () {
        if (window.PartModule) {
            window.PartModule.init();
        }
    });

    // jQuery ready fallback
    if (typeof $ !== "undefined") {
        $(document).ready(function () {
            if (window.PartModule) {
                window.PartModule.init();
            }
        });
    }
})();
