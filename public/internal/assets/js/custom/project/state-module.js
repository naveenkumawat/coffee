/**
 * State Module - JavaScript Component for State Management
 * Handles form validation and submission for state CRUD operations
 * Supports both jQuery Validate and FormValidation libraries
 */
(function () {
    "use strict";

    // Global configuration
    window.StateModule = {
        // Element Selectors
        selectors: {
            createForm: "#createStateForm",
            updateForm: "#updateStateForm",
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
                create: "Creating State...",
                update: "Updating State...",
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
                        "No validation library detected for State module"
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
                        maxlength: 10,
                    },
                    status: {
                        required: true,
                    },
                },
                messages: {
                    name: {
                        required: "Please enter state name",
                        minlength: "State name must be at least 2 characters",
                        maxlength: "State name cannot exceed 100 characters",
                    },
                    code: {
                        required: "Please enter state code",
                        minlength: "State code must be at least 2 characters",
                        maxlength: "State code cannot exceed 10 characters",
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
                                message: "Please enter state name",
                            },
                            stringLength: {
                                min: 2,
                                max: 100,
                                message:
                                    "State name must be between 2 and 100 characters",
                            },
                        },
                    },
                    code: {
                        validators: {
                            notEmpty: {
                                message: "Please enter state code",
                            },
                            stringLength: {
                                min: 2,
                                max: 10,
                                message:
                                    "State code must be between 2 and 10 characters",
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
                        eleInvalidClass: "",
                        eleValidClass: "",
                    }),
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

            // Status toggle events
            document.addEventListener("click", function (e) {
                if (e.target.matches(".status-toggle")) {
                    e.preventDefault();
                    // Handle status toggle
                    window.StatusToggle.handleToggle(e.target);
                }
            });
        },

        // Utility methods
        utils: {
            showMessage: function (message, type = "success") {
                if (typeof toastr !== "undefined") {
                    toastr[type](message);
                } else if (typeof Swal !== "undefined") {
                    Swal.fire({
                        text: message,
                        icon: type,
                        buttonsStyling: false,
                        confirmButtonText: "Ok, got it!",
                        customClass: {
                            confirmButton: "btn fw-bold btn-primary",
                        },
                    });
                } else {
                    alert(message);
                }
            },

            resetForm: function (formSelector) {
                const form = document.querySelector(formSelector);
                if (form) {
                    form.reset();
                    // Clear validation states
                    $(form).find(".is-invalid").removeClass("is-invalid");
                    $(form).find(".invalid-feedback").remove();
                }
            },
        },
    };

    // Auto-initialize when DOM is ready
    document.addEventListener("DOMContentLoaded", function () {
        if (
            document.querySelector("#createStateForm") ||
            document.querySelector("#updateStateForm")
        ) {
            StateModule.init();
        }
    });

    // jQuery ready fallback
    if (typeof $ !== "undefined") {
        $(document).ready(function () {
            if ($("#createStateForm").length || $("#updateStateForm").length) {
                StateModule.init();
            }
        });
    }
})();

// Export for module systems
if (typeof module !== "undefined" && module.exports) {
    module.exports = StateModule;
}
