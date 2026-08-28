/**
 * User Management JavaScript Module - Unified System
 * Handles all user form functionality, table interactions, and role-based features
 *
 * Features:
 * - Role-based form handling with dynamic fields
 * - State dropdown functionality
 * - Operations domain field management
 * - Form validation with conditional rules
 * - Table interactions and bulk actions
 * - Unified mode support for cross-domain management
 *
 * @module UserManagement
 * @version 2.0.0
 * @author ZYLM Development Team
 */

(function () {
    "use strict";

    // Global configuration and constants
    window.UserManagement = {
        // API Configuration
        api: {
            endpoints: {
                // States are pre-loaded in the component, no API URL needed
                userTypes: "/api/v1/user-types?role_id=:role_id",
                operationStates: "/api/v1/operation-states",
                productionOperations: "/api/v1/production-operations",
                toggleStatus: "/users/:id/toggle-status",
            },
            timeout: 30000,
            retryCount: 3,
        },

        // Element Selectors
        selectors: {
            // Form Elements
            createForm: "#createUserForm",
            editForm: "#editUserForm",
            roleSelect: "#role_type",
            stateSelect: "#state_id",
            userTypeSelect: "#user_type_id",
            operationStateSelect: "#operation_state_id",
            productionOperationSelect: "#production_operation_id",

            // Table Elements
            userTable: '[id*="users-table"]',
            checkboxMaster: '[data-action="master-checkbox"]',
            checkboxSingle: ".single-checkbox",
            statusToggle: '[data-action="toggle-status"]',
            deleteButton: '[data-action="delete-entity"]',

            // UI Elements
            submitButton: "#submit_button",
            loadingSpinner: ".spinner-border",
            alertContainer: ".alert-container",
        },

        // UI Text Configuration
        texts: {
            loading: "Loading...",
            selectState: "Select State",
            selectUserType: "Select User Type",
            selectOperationState: "Select Operation State",
            selectProductionOperation: "Select Production Operation",
            errorLoading: "Error loading data",
            confirmDelete: "Are you sure you want to delete this user?",
            confirmBulkDelete:
                "Are you sure you want to delete :count selected users?",
            confirmToggleStatus:
                "Are you sure you want to change the status of this user?",
            success: "Operation completed successfully",
            error: "An error occurred. Please try again.",
        },

        // Validation Configuration
        validation: {
            enabled: true,
            realTime: true,
            rules: {
                name: { required: true, minlength: 2, maxlength: 255 },
                email: { required: true, email: true, maxlength: 255 },
                phone_number: { required: true, minlength: 10, maxlength: 15 },
                status: { required: true },
                role_type: { required: true },
                user_type_id: { required: false }, // Conditional based on role
                operation_state_id: { required: false }, // Conditional based on role
                production_operation_id: { required: false }, // Conditional based on role
                company_name: { required: false, maxlength: 255 }, // For customers
                contact_name: { required: false, maxlength: 255 }, // For customers
            },
        },
    };

    /**
     * Main User Management Handler Class
     */
    class UserManagementHandler {
        constructor(options = {}) {
            this.config = Object.assign({}, window.UserManagement, options);
            this.isInitialized = false;
            this.activeForm = null;
            this.validationInstance = null;
            this.currentRole = null;
            this.isUnifiedMode = false;

            // Bind methods
            // State change handling removed - states are pre-loaded in components
            this.handleRoleChange = this.handleRoleChange.bind(this);
            this.handleFormSubmit = this.handleFormSubmit.bind(this);
            this.handleStatusToggle = this.handleStatusToggle.bind(this);
        }

        /**
         * Initialize the User Management system
         */
        init() {
            console.log("🚀 Initializing User Management System...");

            try {
                this.detectEnvironment();
                this.initializeForms();
                this.initializeTables();
                this.initializeEventHandlers();
                this.initializeValidation();

                this.isInitialized = true;
                console.log(
                    "✅ User Management System initialized successfully"
                );

                // Trigger custom event
                document.dispatchEvent(
                    new CustomEvent("userManagementReady", {
                        detail: { handler: this },
                    })
                );
            } catch (error) {
                console.error(
                    "❌ Failed to initialize User Management System:",
                    error
                );
            }
        }

        /**
         * Detect current environment and form context
         */
        detectEnvironment() {
            // Detect active form
            const createForm = document.querySelector(
                this.config.selectors.createForm
            );
            const editForm = document.querySelector(
                this.config.selectors.editForm
            );

            if (createForm) {
                this.activeForm = createForm;
                this.currentRole =
                    createForm.dataset.roleType || "administrator";
                this.isUnifiedMode = createForm.dataset.unifiedMode === "true";
                console.log("📝 Create form detected:", this.currentRole);
            } else if (editForm) {
                this.activeForm = editForm;
                this.currentRole = editForm.dataset.roleType || "administrator";
                this.isUnifiedMode = editForm.dataset.unifiedMode === "true";
                console.log("✏️ Edit form detected:", this.currentRole);
            }

            // Detect unified mode from table
            const userTable = document.querySelector(
                this.config.selectors.userTable
            );
            if (userTable) {
                this.isUnifiedMode = userTable.dataset.unifiedMode === "true";
                console.log(
                    "📊 Table detected, unified mode:",
                    this.isUnifiedMode
                );
            }
        }

        /**
         * Initialize form functionality
         */
        initializeForms() {
            if (!this.activeForm) {
                console.log(
                    "ℹ️ No active form found, skipping form initialization"
                );
                return;
            }

            console.log("🎯 Initializing form functionality...");

            // Initialize role-based field visibility
            this.initializeRoleFields();

            // Initialize location dropdowns (state)
            // State dropdowns initialization removed - states are pre-loaded in components

            // Initialize conditional field requirements
            this.initializeConditionalFields();

            console.log("✅ Form initialization complete");
        }

        /**
         * Initialize role-based field handling
         */
        initializeRoleFields() {
            const roleSelect = this.activeForm.querySelector(
                this.config.selectors.roleSelect
            );

            if (!roleSelect) {
                console.log(
                    "ℹ️ No role selector found, using fixed role:",
                    this.currentRole
                );
                this.updateFieldsForRole(this.currentRole);
                return;
            }

            // Set up role change handler
            roleSelect.addEventListener("change", this.handleRoleChange);

            // Set initial state
            if (roleSelect.value) {
                this.handleRoleChange({ target: roleSelect });
            }

            console.log("✅ Role-based fields initialized");
        }

        /**
         * Handle role selection change
         */
        handleRoleChange(event) {
            const selectedRole = event.target.value;
            console.log("🔄 Role changed to:", selectedRole);

            this.currentRole = selectedRole;
            this.updateFieldsForRole(selectedRole);
        }

        /**
         * Update form fields based on selected role
         */
        updateFieldsForRole(roleType) {
            if (!roleType || !this.activeForm) return;

            console.log("🎨 Updating fields for role:", roleType);

            // Define role-based field visibility
            const fieldVisibility = this.getRoleFieldVisibility(roleType);

            // Update field visibility and requirements
            Object.entries(fieldVisibility).forEach(
                ([fieldGroup, isVisible]) => {
                    this.toggleFieldGroup(fieldGroup, isVisible);
                }
            );

            // Update validation rules
            this.updateValidationRules(roleType);

            // Load role-specific data
            this.loadRoleSpecificData(roleType);
        }

        /**
         * Get field visibility configuration for role
         */
        getRoleFieldVisibility(roleType) {
            const operationRoles = [
                "operations_manager",
                "regional_manager",
                "technical_supervisor",
                "machine_operator",
                "grievance_associate",
                "security_officer",
            ];
            const productionRoles = [
                "production_manager",
                "production_supervisor",
            ];
            const customerRoles = ["customer"];
            const userTypeRoles = ["customer", "production_manager"];

            return {
                operationFields: operationRoles.includes(roleType),
                productionFields:
                    productionRoles.includes(roleType) ||
                    operationRoles.includes(roleType),
                customerFields: customerRoles.includes(roleType),
                userTypeField: userTypeRoles.includes(roleType),
            };
        }

        /**
         * Toggle field group visibility
         */
        toggleFieldGroup(groupName, isVisible) {
            const selectors = {
                operationFields: [
                    "#operation_state_id",
                    '[data-field-group="operation"]',
                ],
                productionFields: [
                    "#production_operation_id",
                    '[data-field-group="production"]',
                ],
                customerFields: [
                    "#company_name",
                    "#contact_name",
                    '[data-field-group="customer"]',
                ],
                userTypeField: ["#user_type_id", "#user_type_wrapper"],
            };

            const groupSelectors = selectors[groupName] || [];

            groupSelectors.forEach((selector) => {
                const elements = this.activeForm.querySelectorAll(selector);
                elements.forEach((element) => {
                    const container =
                        element.closest(
                            '.row, .col-md-6, .col-md-4, .col-md-12, [id*="wrapper"]'
                        ) || element.closest("div");
                    if (container) {
                        container.style.display = isVisible ? "block" : "none";

                        // Update required attribute
                        const input = container.querySelector(
                            "input, select, textarea"
                        );
                        if (input) {
                            if (
                                isVisible &&
                                this.isFieldRequired(
                                    input.name,
                                    this.currentRole
                                )
                            ) {
                                input.required = true;
                            } else {
                                input.required = false;
                            }
                        }
                    }
                });
            });
        }

        /**
         * Check if field is required for current role
         */
        isFieldRequired(fieldName, roleType) {
            const requiredFields = {
                administrator: ["name", "email", "phone_number", "status"],
                sales_manager: ["name", "email", "phone_number", "status"],
                production_manager: [
                    "name",
                    "email",
                    "phone_number",
                    "status",
                    "user_type_id",
                ],
                operations_manager: [
                    "name",
                    "email",
                    "phone_number",
                    "status",
                    "operation_state_id",
                ],
                marketing_manager: ["name", "email", "phone_number", "status"],
                hr_manager: ["name", "email", "phone_number", "status"],
                production_supervisor: [
                    "name",
                    "email",
                    "phone_number",
                    "status",
                    "production_operation_id",
                ],
                regional_manager: [
                    "name",
                    "email",
                    "phone_number",
                    "status",
                    "operation_state_id",
                ],
                technical_supervisor: [
                    "name",
                    "email",
                    "phone_number",
                    "status",
                    "operation_state_id",
                ],
                machine_operator: [
                    "name",
                    "email",
                    "phone_number",
                    "status",
                    "operation_state_id",
                    "production_operation_id",
                ],
                grievance_associate: [
                    "name",
                    "email",
                    "phone_number",
                    "status",
                    "operation_state_id",
                ],
                security_officer: [
                    "name",
                    "email",
                    "phone_number",
                    "status",
                    "operation_state_id",
                ],
                hr_executive: ["name", "email", "phone_number", "status"],
                hr_consultant: ["name", "email", "phone_number", "status"],
                customer: [
                    "name",
                    "email",
                    "phone_number",
                    "status",
                    "user_type_id",
                    "company_name",
                    "contact_name",
                ],
            };

            return (requiredFields[roleType] || []).includes(fieldName);
        }

        /**
         * Load role-specific data via AJAX
         */
        async loadRoleSpecificData(roleType) {
            try {
                // Load user types for specific roles
                if (["customer", "production_manager"].includes(roleType)) {
                    await this.loadUserTypes(roleType);
                }

                // Load operation states for operation roles
                if (this.getRoleFieldVisibility(roleType).operationFields) {
                    await this.loadOperationStates();
                }

                // Load production operations for production roles
                if (this.getRoleFieldVisibility(roleType).productionFields) {
                    await this.loadProductionOperations();
                }
            } catch (error) {
                console.error("❌ Failed to load role-specific data:", error);
            }
        }

        /**
         * Load user types for dropdown
         */
        async loadUserTypes(roleType) {
            const userTypeSelect = this.activeForm.querySelector(
                this.config.selectors.userTypeSelect
            );
            if (!userTypeSelect) return;

            try {
                const roleId = this.getRoleId(roleType);
                const url = this.config.api.endpoints.userTypes.replace(
                    ":role_id",
                    roleId
                );

                userTypeSelect.innerHTML =
                    '<option value="">Loading...</option>';
                userTypeSelect.disabled = true;

                const response = await fetch(url, {
                    headers: {
                        Accept: "application/json",
                        "X-Requested-With": "XMLHttpRequest",
                    },
                });

                if (!response.ok) {
                    throw new Error(
                        `HTTP ${response.status}: ${response.statusText}`
                    );
                }

                const data = await response.json();
                this.populateUserTypes(data);
            } catch (error) {
                console.error("❌ Failed to load user types:", error);
                this.handleUserTypesError();
            }
        }

        /**
         * Populate user types dropdown
         */
        populateUserTypes(data) {
            const userTypeSelect = this.activeForm.querySelector(
                this.config.selectors.userTypeSelect
            );
            if (!userTypeSelect) return;

            userTypeSelect.disabled = false;
            userTypeSelect.innerHTML =
                '<option value="">Select User Type</option>';

            if (
                data.success &&
                data.userTypes &&
                Object.keys(data.userTypes).length > 0
            ) {
                Object.entries(data.userTypes).forEach(([id, name]) => {
                    const option = document.createElement("option");
                    option.value = id;
                    option.textContent = name;
                    userTypeSelect.appendChild(option);
                });
                console.log("✅ User types loaded successfully");
            } else {
                userTypeSelect.innerHTML =
                    '<option value="">No user types available</option>';
            }
        }

        /**
         * Handle user types loading error
         */
        handleUserTypesError() {
            const userTypeSelect = this.activeForm.querySelector(
                this.config.selectors.userTypeSelect
            );
            if (userTypeSelect) {
                userTypeSelect.disabled = false;
                userTypeSelect.innerHTML =
                    '<option value="">Error loading user types</option>';
            }
        }


        /**
         * Initialize conditional field requirements
         */
        initializeConditionalFields() {
            if (!this.activeForm) return;

            // Set up real-time validation for conditional fields
            const conditionalFields =
                this.activeForm.querySelectorAll("[data-conditional]");
            conditionalFields.forEach((field) => {
                field.addEventListener("change", () => {
                    this.updateConditionalRequirements();
                });
            });
        }

        /**
         * Update conditional field requirements
         */
        updateConditionalRequirements() {
            // Update requirements based on current form state
            this.updateFieldsForRole(this.currentRole);
        }

        /**
         * Initialize table functionality
         */
        initializeTables() {
            const tables = document.querySelectorAll(
                this.config.selectors.userTable
            );
            if (tables.length === 0) {
                console.log(
                    "ℹ️ No user tables found, skipping table initialization"
                );
                return;
            }

            console.log("📊 Initializing table functionality...");

            tables.forEach((table) => {
                this.initializeTableActions(table);
                this.initializeTableCheckboxes(table);
            });

            console.log("✅ Table initialization complete");
        }

        /**
         * Initialize table action handlers
         */
        initializeTableActions(table) {
            // Status toggle handlers
            const statusButtons = table.querySelectorAll(
                this.config.selectors.statusToggle
            );
            statusButtons.forEach((button) => {
                button.addEventListener("click", this.handleStatusToggle);
            });

            // Delete handlers
            const deleteButtons = table.querySelectorAll(
                this.config.selectors.deleteButton
            );
            deleteButtons.forEach((button) => {
                button.addEventListener("click", this.handleDelete);
            });
        }

        /**
         * Initialize table checkbox functionality
         */
        initializeTableCheckboxes(table) {
            const masterCheckbox = table.querySelector(
                this.config.selectors.checkboxMaster
            );
            const singleCheckboxes = table.querySelectorAll(
                this.config.selectors.checkboxSingle
            );

            if (masterCheckbox && singleCheckboxes.length > 0) {
                // Master checkbox handler
                masterCheckbox.addEventListener("change", (e) => {
                    singleCheckboxes.forEach((checkbox) => {
                        checkbox.checked = e.target.checked;
                    });
                });

                // Single checkbox handlers
                singleCheckboxes.forEach((checkbox) => {
                    checkbox.addEventListener("change", () => {
                        this.updateMasterCheckbox(table);
                    });
                });
            }
        }

        /**
         * Update master checkbox state
         */
        updateMasterCheckbox(table) {
            const masterCheckbox = table.querySelector(
                this.config.selectors.checkboxMaster
            );
            const singleCheckboxes = table.querySelectorAll(
                this.config.selectors.checkboxSingle
            );

            if (!masterCheckbox || singleCheckboxes.length === 0) return;

            const checkedCount = Array.from(singleCheckboxes).filter(
                (cb) => cb.checked
            ).length;

            masterCheckbox.checked = checkedCount === singleCheckboxes.length;
            masterCheckbox.indeterminate =
                checkedCount > 0 && checkedCount < singleCheckboxes.length;
        }

        /**
         * Handle status toggle
         */
        async handleStatusToggle(event) {
            event.preventDefault();

            const button = event.currentTarget;
            const userId = button.dataset.id;
            const currentStatus = parseInt(button.dataset.currentStatus);
            const newStatus = currentStatus === 1 ? 0 : 1;

            if (button.dataset.confirm === "true") {
                const message =
                    button.dataset.confirmMessage ||
                    this.config.texts.confirmToggleStatus;
                if (!confirm(message)) {
                    return;
                }
            }

            try {
                button.disabled = true;

                const response = await fetch(button.dataset.url, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": this.getCSRFToken(),
                        Accept: "application/json",
                        "X-Requested-With": "XMLHttpRequest",
                    },
                    body: JSON.stringify({ status: newStatus }),
                });

                if (!response.ok) {
                    throw new Error(
                        `HTTP ${response.status}: ${response.statusText}`
                    );
                }

                const result = await response.json();

                if (result.success) {
                    // Update button state
                    button.dataset.currentStatus = newStatus;
                    button.className = button.className.replace(
                        currentStatus === 1
                            ? "btn-light-success"
                            : "btn-light-danger",
                        newStatus === 1
                            ? "btn-light-success"
                            : "btn-light-danger"
                    );

                    const icon = button.querySelector("i");
                    if (icon) {
                        icon.className = `fas fa-${
                            newStatus === 1 ? "check-circle" : "times-circle"
                        }`;
                    }

                    // Update status badge if it exists
                    const statusBadge = button.querySelector(".badge");
                    if (statusBadge) {
                        statusBadge.textContent =
                            newStatus === 1 ? "Active" : "Inactive";
                        statusBadge.className = `badge badge-${
                            newStatus === 1 ? "success" : "secondary"
                        }`;
                    }

                    this.showAlert("success", this.config.texts.success);
                } else {
                    throw new Error(result.message || "Status toggle failed");
                }
            } catch (error) {
                console.error("❌ Status toggle failed:", error);
                this.showAlert(
                    "error",
                    error.message || this.config.texts.error
                );
            } finally {
                button.disabled = false;
            }
        }

        /**
         * Initialize validation
         */
        initializeValidation() {
            if (!this.activeForm || !this.config.validation.enabled) {
                console.log("ℹ️ Form validation disabled or no form found");
                return;
            }

            console.log("✅ Setting up form validation...");

            // Use FormValidation if available
            if (typeof FormValidation !== "undefined") {
                this.initializeFormValidation();
            }
            // Fallback to jQuery Validate if available
            else if (typeof $ !== "undefined" && $.fn.validate) {
                this.initializeJQueryValidation();
            }
            // Basic HTML5 validation
            else {
                this.initializeHTML5Validation();
            }

            // Set up form submission handler
            this.activeForm.addEventListener("submit", this.handleFormSubmit);
        }

        /**
         * Initialize FormValidation library
         */
        initializeFormValidation() {
            try {
                this.validationInstance = FormValidation.formValidation(
                    this.activeForm,
                    {
                        fields: this.getFormValidationFields(),
                        plugins: {
                            trigger: new FormValidation.plugins.Trigger(),
                            bootstrap: new FormValidation.plugins.Bootstrap5(),
                            submitButton:
                                new FormValidation.plugins.SubmitButton(),
                            icon: new FormValidation.plugins.Icon({
                                valid: "fa fa-check",
                                invalid: "fa fa-times",
                                validating: "fa fa-refresh",
                            }),
                        },
                    }
                );

                console.log("✅ FormValidation initialized");
            } catch (error) {
                console.error(
                    "❌ FormValidation initialization failed:",
                    error
                );
            }
        }

        /**
         * Get FormValidation field configuration
         */
        getFormValidationFields() {
            const fields = {};
            const rules = this.config.validation.rules;

            Object.entries(rules).forEach(([fieldName, rule]) => {
                if (this.activeForm.querySelector(`[name="${fieldName}"]`)) {
                    fields[fieldName] = { validators: {} };

                    if (rule.required) {
                        fields[fieldName].validators.notEmpty = {
                            message: `${this.getFieldLabel(
                                fieldName
                            )} is required`,
                        };
                    }

                    if (rule.email) {
                        fields[fieldName].validators.emailAddress = {
                            message: "Please enter a valid email address",
                        };
                    }

                    if (rule.minlength) {
                        fields[fieldName].validators.stringLength = {
                            min: rule.minlength,
                            message: `Must be at least ${rule.minlength} characters`,
                        };
                    }

                    if (rule.maxlength) {
                        fields[fieldName].validators.stringLength =
                            Object.assign(
                                fields[fieldName].validators.stringLength || {},
                                {
                                    max: rule.maxlength,
                                    message: `Must not exceed ${rule.maxlength} characters`,
                                }
                            );
                    }
                }
            });

            return fields;
        }

        /**
         * Initialize jQuery Validation
         */
        initializeJQueryValidation() {
            try {
                $(this.activeForm).validate({
                    rules: this.getJQueryValidationRules(),
                    messages: this.getJQueryValidationMessages(),
                    errorElement: "div",
                    errorClass: "text-danger fs-7 mt-2",
                    highlight: function (element) {
                        $(element).addClass("is-invalid");
                    },
                    unhighlight: function (element) {
                        $(element).removeClass("is-invalid");
                    },
                });

                console.log("✅ jQuery Validation initialized");
            } catch (error) {
                console.error(
                    "❌ jQuery Validation initialization failed:",
                    error
                );
            }
        }

        /**
         * Get jQuery validation rules
         */
        getJQueryValidationRules() {
            const rules = {};
            const configRules = this.config.validation.rules;

            Object.entries(configRules).forEach(([fieldName, rule]) => {
                if (this.activeForm.querySelector(`[name="${fieldName}"]`)) {
                    rules[fieldName] = {};

                    if (rule.required) rules[fieldName].required = true;
                    if (rule.email) rules[fieldName].email = true;
                    if (rule.minlength)
                        rules[fieldName].minlength = rule.minlength;
                    if (rule.maxlength)
                        rules[fieldName].maxlength = rule.maxlength;
                }
            });

            return rules;
        }

        /**
         * Get jQuery validation messages
         */
        getJQueryValidationMessages() {
            const messages = {};
            const rules = this.config.validation.rules;

            Object.entries(rules).forEach(([fieldName, rule]) => {
                if (this.activeForm.querySelector(`[name="${fieldName}"]`)) {
                    messages[fieldName] = {};

                    if (rule.required) {
                        messages[fieldName].required = `${this.getFieldLabel(
                            fieldName
                        )} is required`;
                    }
                    if (rule.email) {
                        messages[fieldName].email =
                            "Please enter a valid email address";
                    }
                    if (rule.minlength) {
                        messages[
                            fieldName
                        ].minlength = `Must be at least ${rule.minlength} characters`;
                    }
                    if (rule.maxlength) {
                        messages[
                            fieldName
                        ].maxlength = `Must not exceed ${rule.maxlength} characters`;
                    }
                }
            });

            return messages;
        }

        /**
         * Initialize HTML5 validation
         */
        initializeHTML5Validation() {
            console.log("ℹ️ Using HTML5 validation");

            // Add novalidate to prevent browser validation
            this.activeForm.noValidate = true;

            // Set up custom validation
            const inputs = this.activeForm.querySelectorAll(
                "input, select, textarea"
            );
            inputs.forEach((input) => {
                input.addEventListener("blur", () => {
                    this.validateField(input);
                });
            });
        }

        /**
         * Validate individual field
         */
        validateField(field) {
            const fieldName = field.name;
            const value = field.value.trim();
            const rules = this.config.validation.rules[fieldName];

            if (!rules) return true;

            let isValid = true;
            let errorMessage = "";

            // Required validation
            if (
                rules.required &&
                this.isFieldRequired(fieldName, this.currentRole) &&
                !value
            ) {
                isValid = false;
                errorMessage = `${this.getFieldLabel(fieldName)} is required`;
            }

            // Email validation
            if (isValid && rules.email && value && !this.isValidEmail(value)) {
                isValid = false;
                errorMessage = "Please enter a valid email address";
            }

            // Length validation
            if (isValid && rules.minlength && value.length < rules.minlength) {
                isValid = false;
                errorMessage = `Must be at least ${rules.minlength} characters`;
            }

            if (isValid && rules.maxlength && value.length > rules.maxlength) {
                isValid = false;
                errorMessage = `Must not exceed ${rules.maxlength} characters`;
            }

            // Update field state
            this.updateFieldValidationState(field, isValid, errorMessage);

            return isValid;
        }

        /**
         * Update field validation state
         */
        updateFieldValidationState(field, isValid, errorMessage) {
            // Remove existing validation classes and messages
            field.classList.remove("is-valid", "is-invalid");

            const existingError =
                field.parentNode.querySelector(".validation-error");
            if (existingError) {
                existingError.remove();
            }

            // Add new validation state
            if (isValid) {
                field.classList.add("is-valid");
            } else {
                field.classList.add("is-invalid");

                if (errorMessage) {
                    const errorDiv = document.createElement("div");
                    errorDiv.className =
                        "validation-error text-danger fs-7 mt-1";
                    errorDiv.textContent = errorMessage;
                    field.parentNode.appendChild(errorDiv);
                }
            }
        }

        /**
         * Handle form submission
         */
        async handleFormSubmit(event) {
            console.log("📤 Form submission initiated");

            // Prevent default submission
            event.preventDefault();

            const submitButton = this.activeForm.querySelector(
                this.config.selectors.submitButton
            );

            try {
                // Show loading state
                this.setSubmitButtonLoading(true);

                // Validate form
                if (!this.validateForm()) {
                    throw new Error("Form validation failed");
                }

                // Submit form
                const formData = new FormData(this.activeForm);
                const response = await fetch(this.activeForm.action, {
                    method: this.activeForm.method,
                    body: formData,
                    headers: {
                        "X-Requested-With": "XMLHttpRequest",
                        "X-CSRF-TOKEN": this.getCSRFToken(),
                    },
                });

                if (!response.ok) {
                    throw new Error(
                        `HTTP ${response.status}: ${response.statusText}`
                    );
                }

                const result = await response.json();

                if (result.success) {
                    this.showAlert(
                        "success",
                        result.message || this.config.texts.success
                    );

                    // Redirect if specified
                    if (result.redirect) {
                        setTimeout(() => {
                            window.location.href = result.redirect;
                        }, 1000);
                    }
                } else {
                    throw new Error(result.message || "Form submission failed");
                }
            } catch (error) {
                console.error("❌ Form submission failed:", error);
                this.showAlert(
                    "error",
                    error.message || this.config.texts.error
                );
                this.handleFormErrors(error);
            } finally {
                this.setSubmitButtonLoading(false);
            }
        }

        /**
         * Validate entire form
         */
        validateForm() {
            if (!this.activeForm) return false;

            let isValid = true;

            // Use validation library if available
            if (this.validationInstance && this.validationInstance.validate) {
                const validationResult = this.validationInstance.validate();
                return validationResult === "Valid";
            }

            // Fallback to manual validation
            const inputs = this.activeForm.querySelectorAll(
                "input, select, textarea"
            );
            inputs.forEach((input) => {
                if (!this.validateField(input)) {
                    isValid = false;
                }
            });

            return isValid;
        }

        /**
         * Set submit button loading state
         */
        setSubmitButtonLoading(isLoading) {
            const submitButton = this.activeForm.querySelector(
                this.config.selectors.submitButton
            );
            if (!submitButton) return;

            const indicator = submitButton.querySelector(".indicator-label");
            const progress = submitButton.querySelector(".indicator-progress");

            if (isLoading) {
                submitButton.disabled = true;
                if (indicator) indicator.style.display = "none";
                if (progress) progress.style.display = "inline-block";
            } else {
                submitButton.disabled = false;
                if (indicator) indicator.style.display = "inline-block";
                if (progress) progress.style.display = "none";
            }
        }

        /**
         * Initialize event handlers
         */
        initializeEventHandlers() {
            // Global error handler
            window.addEventListener("error", (event) => {
                console.error("Global error:", event.error);
            });

            // AJAX error handler
            document.addEventListener("ajaxError", (event) => {
                console.error("AJAX error:", event.detail);
                this.showAlert(
                    "error",
                    "An error occurred while communicating with the server"
                );
            });
        }

        /**
         * Helper Methods
         */

        /**
         * Get CSRF token from meta tag
         */
        getCSRFToken() {
            const token = document.querySelector('meta[name="csrf-token"]');
            return token ? token.getAttribute("content") : "";
        }

        /**
         * Get field label for error messages
         */
        getFieldLabel(fieldName) {
            const field = this.activeForm.querySelector(
                `[name="${fieldName}"]`
            );
            if (field) {
                const label = this.activeForm.querySelector(
                    `label[for="${field.id}"]`
                );
                if (label) {
                    return label.textContent.replace("*", "").trim();
                }
            }

            // Fallback to formatted field name
            return fieldName
                .replace("_", " ")
                .replace(/\b\w/g, (l) => l.toUpperCase());
        }

        /**
         * Validate email format
         */
        isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }

        /**
         * Get role ID for API calls
         */
        getRoleId(roleType) {
            const roleMap = {
                administrator: 0,
                marketing_manager: 1,
                sales_manager: 2,
                production_manager: 3,
                customer: 4,
                operations_manager: 5,
                production_supervisor: 6,
                regional_manager: 7,
                technical_supervisor: 8,
                machine_operator: 9,
                grievance_associate: 10,
                hr_manager: 11,
                security_officer: 12,
                hr_executive: 13,
                hr_consultant: 14,
            };

            return roleMap[roleType] || 0;
        }

        /**
         * Show alert message
         */
        showAlert(type, message) {
            // Try to use SweetAlert2 if available
            if (typeof Swal !== "undefined") {
                Swal.fire({
                    icon: type === "error" ? "error" : "success",
                    title: type === "error" ? "Error" : "Success",
                    text: message,
                    timer: 3000,
                    showConfirmButton: false,
                });
                return;
            }

            // Fallback to browser alert
            alert(message);
        }

        /**
         * Handle form errors
         */
        handleFormErrors(error) {
            // Handle validation errors
            if (error.details && error.details.errors) {
                Object.entries(error.details.errors).forEach(
                    ([field, messages]) => {
                        const fieldElement = this.activeForm.querySelector(
                            `[name="${field}"]`
                        );
                        if (
                            fieldElement &&
                            Array.isArray(messages) &&
                            messages.length > 0
                        ) {
                            this.updateFieldValidationState(
                                fieldElement,
                                false,
                                messages[0]
                            );
                        }
                    }
                );
            }
        }

        /**
         * Load operation states
         */
        async loadOperationStates() {
            // Implementation for loading operation states
            console.log("🔧 Loading operation states...");
            // This would be implemented based on API availability
        }

        /**
         * Load production operations
         */
        async loadProductionOperations() {
            // Implementation for loading production operations
            console.log("🏭 Loading production operations...");
            // This would be implemented based on API availability
        }

        /**
         * Handle delete action
         */
        handleDelete(event) {
            event.preventDefault();

            const button = event.currentTarget;
            const entityName = button.dataset.entityName || "this user";
            const message =
                button.dataset.confirmMessage ||
                `Are you sure you want to delete ${entityName}?`;

            if (!confirm(message)) {
                return;
            }

            // Proceed with deletion
            console.log("🗑️ Deleting user:", entityName);
            // Implementation would depend on the specific deletion API
        }

        /**
         * Update validation rules for role
         */
        updateValidationRules(roleType) {
            if (!this.validationInstance) return;

            // Update required fields based on role
            const requiredFields = this.getRequiredFieldsForRole(roleType);

            // Revalidate if needed
            if (this.config.validation.realTime) {
                this.validationInstance.revalidateField(roleType);
            }
        }

        /**
         * Get required fields for specific role
         */
        getRequiredFieldsForRole(roleType) {
            const requiredFields = {
                administrator: ["name", "email", "phone_number", "status"],
                customer: [
                    "name",
                    "email",
                    "phone_number",
                    "status",
                    "user_type_id",
                    "company_name",
                    "contact_name",
                ],
                // Add other roles as needed
            };

            return (
                requiredFields[roleType] || [
                    "name",
                    "email",
                    "phone_number",
                    "status",
                ]
            );
        }

        /**
         * Destroy the handler
         */
        destroy() {
            if (this.validationInstance && this.validationInstance.destroy) {
                this.validationInstance.destroy();
            }

            // Remove event listeners
            if (this.activeForm) {
                this.activeForm.removeEventListener(
                    "submit",
                    this.handleFormSubmit
                );

                // State change handling removed - states are pre-loaded in components

                const roleSelect = this.activeForm.querySelector(
                    this.config.selectors.roleSelect
                );
                if (roleSelect) {
                    roleSelect.removeEventListener(
                        "change",
                        this.handleRoleChange
                    );
                }
            }

            console.log("🧹 User Management handler destroyed");
        }
    }

    // Export UserManagementHandler to global scope
    window.UserManagementHandler = UserManagementHandler;

    // Auto-initialize if DOM is ready
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", () => {
            window.userManagementHandler = new UserManagementHandler();
            window.userManagementHandler.init();
        });
    } else {
        window.userManagementHandler = new UserManagementHandler();
        window.userManagementHandler.init();
    }

    // Global initialization function for manual use
    window.UserManagement.init = function (options = {}) {
        if (window.userManagementHandler) {
            window.userManagementHandler.destroy();
        }

        window.userManagementHandler = new UserManagementHandler(options);
        window.userManagementHandler.init();

        return window.userManagementHandler;
    };

    console.log("✅ User Management module loaded successfully");
})();
