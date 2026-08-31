/**
 * User Form Module - Administrator Domain
 * Handles all user form functionality including validation, dynamic fields, and role-based behavior
 * Based on marketing tender form architecture
 *
 * @module UserForm
 * @version 1.0.0
 * @author Coffee Team
 */

class UserForm {
    constructor(config = {}) {
        this.config = {
            formId: config.formId || "createUserForm",
            roleSelectId: config.roleSelectId || "role",
            productionUnitWrapperId:
                config.productionUnitWrapperId || "production_unit_wrapper",
            userTypeSelectId: config.userTypeSelectId || "user_type_id",
            statesApiUrl: config.statesApiUrl || null, // Will be set from view or use fallback
            csrfToken:
                config.csrfToken ||
                document
                    .querySelector('meta[name="csrf-token"]')
                    ?.getAttribute("content"),
            ...config,
        };

        this.validator = null;
        this.roleSelect = null;
        this.productionUnitWrapper = null;
        this.userTypeSelect = null;

        // Try to find the form automatically if not specified
        if (!document.getElementById(this.config.formId)) {
            const alternativeIds = [
                "createUserForm",
                "updateUserForm",
                "editUserForm",
            ];
            for (const id of alternativeIds) {
                if (document.getElementById(id)) {
                    this.config.formId = id;
                    console.log(`🔍 Auto-detected form ID: ${id}`);
                    break;
                }
            }
        }

        console.log("🚀 UserForm initialized with config:", this.config);
    }

    /**
     * Initialize the user form
     */
    init() {
        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", () =>
                this.initializeForm()
            );
        } else {
            this.initializeForm();
        }
    }

    /**
     * Initialize all form functionality
     */
    initializeForm() {
        console.log("🎯 Initializing User Form...");

        this.initializeElements();
        this.initializeFormValidation();
        this.restoreStateSelectValues();
        this.initializeRegionalManagerStates();
        this.initializeRealTimeValidation();

        // Initialize role handling after a delay to ensure Select2 is ready
        setTimeout(() => {
            this.initializeRoleHandling();
            this.initializeUserTypeHandling();
        }, 1000);

        console.log("✅ User Form initialization complete");
    }

    /**
     * Initialize DOM elements
     */
    initializeElements() {
        const form = document.getElementById(this.config.formId);

        if (!form) {
            console.warn(`🔍 Form with ID "${this.config.formId}" not found`);
            return;
        }

        console.log("✅ Form found:", form);

        // Try multiple attempts to find elements
        this.roleSelect = document.getElementById(this.config.roleSelectId);
        this.productionUnitWrapper = document.getElementById(
            this.config.productionUnitWrapperId
        );
        this.userTypeSelect = document.getElementById(
            this.config.userTypeSelectId
        );

        console.log("🔍 Element Detection Results:");
        console.log(
            "  Role select:",
            !!this.roleSelect,
            this.roleSelect ? this.roleSelect.id : "null"
        );
        console.log(
            "  Production unit wrapper:",
            !!this.productionUnitWrapper,
            this.productionUnitWrapper
        );
        console.log(
            "  User type select:",
            !!this.userTypeSelect,
            this.userTypeSelect
        );

        // Debug: List all form elements with IDs
        if (form) {
            console.log("🔍 All form elements with IDs:");
            const elementsWithIds = form.querySelectorAll("[id]");
            elementsWithIds.forEach((el) => {
                console.log(`  - ${el.tagName}#${el.id}`);
            });
        }

        // Debug: Show all form elements
        console.log("🔍 All form elements:");
        const allInputs = form.querySelectorAll("input, select, textarea");
        allInputs.forEach((element, index) => {
            console.log(
                `  ${index + 1}. ${element.tagName} - ID: ${
                    element.id || "none"
                } - Name: ${element.name || "none"}`
            );
        });

        // Check for role options
        if (this.roleSelect) {
            console.log("🔍 Role options:");
            Array.from(this.roleSelect.options).forEach((option, index) => {
                console.log(
                    `  ${index}. Value: ${option.value} - Text: ${option.text}`
                );
            });
        }
    }

    /**
     * Initialize role-based field handling
     */
    initializeRoleHandling() {
        // Check if we're in edit mode by looking for role_type hidden field
        const roleTypeField = document.getElementById("role_type");
        const isEditMode = !!roleTypeField;

        if (isEditMode) {
            console.log(
                "📝 Edit mode detected - fields are conditionally rendered"
            );
            const roleType = roleTypeField.value;
            console.log("🎯 Current role type:", roleType);

            // Initialize role-specific functionality
            this.initializeRoleSpecificFeatures(roleType);
            return;
        }

        // Create mode - check for role select dropdown
        if (!this.roleSelect) {
            console.warn(
                "⚠️ Role select not found - attempting to find it again"
            );

            // Try to find the role select again
            this.roleSelect = document.getElementById(this.config.roleSelectId);

            if (!this.roleSelect) {
                console.warn(
                    "⚠️ Role select still not found - skipping role handling"
                );
                return;
            }
        }

        console.log("✅ Setting up role-based field handling for create mode");
        console.log("🔍 jQuery available:", typeof $ !== "undefined");
        console.log(
            "🔍 Select2 available:",
            typeof $ !== "undefined" && $.fn && $.fn.select2
        );

        // Function to handle role change
        const handleRoleChange = () => {
            const roleId = this.roleSelect.value;
            console.log("🔄 Role changed to:", roleId, "Type:", typeof roleId);

            // Clear any Select2 selection if it exists
            if (
                typeof $ !== "undefined" &&
                $.fn.select2 &&
                this.userTypeSelect
            ) {
                $("#" + this.config.userTypeSelectId)
                    .val("")
                    .trigger("change");
                console.log("🔄 Cleared production unit selection");
            }

            // Handle role-specific field visibility
            this.handleRoleSpecificFields(roleId);
        };

        // Add event listeners for both regular change and Select2 change
        this.roleSelect.addEventListener("change", handleRoleChange);
        console.log("✅ Added regular change event listener");

        // Also listen for Select2 specific change event with proper scope
        if (typeof $ !== "undefined" && $.fn.select2) {
            const self = this;

            // Check if Select2 is already initialized
            if (
                $("#" + this.config.roleSelectId).hasClass(
                    "select2-hidden-accessible"
                )
            ) {
                // Select2 is already initialized
                $("#" + this.config.roleSelectId).on(
                    "select2:select",
                    function (e) {
                        console.log("🔄 Select2 change event triggered");
                        self.handleRoleSpecificFields(e.params.data.id);
                    }
                );
                console.log("✅ Added Select2 change event listener");
            } else {
                // Select2 not yet initialized, try again after delay
                setTimeout(() => {
                    $("#" + this.config.roleSelectId).on(
                        "select2:select",
                        function (e) {
                            console.log("🔄 Select2 change event triggered");
                            self.handleRoleSpecificFields(e.params.data.id);
                        }
                    );
                }, 1000);
            }
        }

        // Initial call to set correct state
        if (this.roleSelect) {
            const currentValue = this.roleSelect.value;
            console.log(`🚀 Initial role select value: "${currentValue}"`);

            if (currentValue) {
                console.log("🎯 Handling initial role selection");
                this.handleRoleSpecificFields(currentValue);
            } else {
                console.log("🔄 No initial role selected - hiding all fields");
                this.hideAllRoleSpecificFields();
            }
        } else {
            console.log("⚠️ Role select element not found");
        }
    }

    /**
     * Initialize role-specific features for conditionally rendered fields
     * @param {string} roleType - The role type from role_type field
     */
    initializeRoleSpecificFeatures(roleType) {
        console.log(`🎯 Initializing features for role type: ${roleType}`);

        // Initialize based on which fields are actually present
        switch (roleType) {
            case "customer":
                this.initializeCustomerFeatures();
                break;

            case "production_supervisor":
            case "production-supervisor":
                this.initializeProductionSupervisorFeatures();
                break;

            case "production_manager":
            case "production-manager":
                this.initializeProductionManagerFeatures();
                break;

            default:
                this.initializeDefaultFeatures();
                break;
        }
    }

    /**
     * Initialize customer-specific features
     */
    initializeCustomerFeatures() {
        console.log("🏢 Initializing customer features");
        // Customer fields are already rendered, just initialize any specific functionality
        // State dropdowns should work automatically
    }

    /**
     * Initialize production supervisor features
     */
    initializeProductionSupervisorFeatures() {
        console.log("👷 Initializing production supervisor features");

        // User types are already populated server-side in Blade template
        // No need for AJAX loading since options are provided by UserBasic component
        const userTypeSelect = document.getElementById("user_type_id");
        if (userTypeSelect) {
            console.log(
                "✅ User type select found - options already populated server-side"
            );
        }
    }

    /**
     * Initialize production manager features
     */
    initializeProductionManagerFeatures() {
        console.log("📦 Initializing production manager features");

        // Initialize Select2 for production unit dropdown if present
        const productionUnitSelect = document.getElementById("user_type_id");
        if (productionUnitSelect) {
            console.log("🏭 Initializing production unit Select2");

            // Initialize Select2 for production units
            if (window.$ && window.$.fn.select2) {
                window.$(productionUnitSelect).select2({
                    placeholder: "Select Production Unit",
                    allowClear: true,
                    width: "100%",
                });
            }
        }
    }

    /**
     * Initialize default features for other roles
     */
    initializeDefaultFeatures() {
        console.log("🔧 Initializing default features");
        // Default initialization for other roles
    }

    /**
     * Handle role-specific field visibility and requirements
     * @param {string} roleId - The selected role ID
     */
    handleRoleSpecificFields(roleId) {
        console.log("🔄 Handling role-specific fields for role:", roleId);

        // Check if we're in create mode (by checking URL or absence of role-specific fields)
        const isCreateMode =
            window.location.pathname.includes("/create") ||
            window.location.pathname.includes("/add") ||
            this.isCreateMode();

        console.log(`🔍 Mode detected: ${isCreateMode ? "CREATE" : "EDIT"}`);

        if (isCreateMode) {
            // CREATE MODE: Use JavaScript show/hide for dynamic field visibility
            this.handleCreateModeFields(roleId);
        } else {
            // EDIT MODE: Fields are conditionally rendered, just initialize features
            this.handleEditModeFields(roleId);
        }
    }

    /**
     * Check if we're in create mode by examining the DOM and URL
     */
    isCreateMode() {
        // Check URL patterns
        const urlPatterns = ["/create", "/add"];
        const currentPath = window.location.pathname;
        const urlIndicatesCreate = urlPatterns.some((pattern) =>
            currentPath.includes(pattern)
        );

        if (urlIndicatesCreate) {
            console.log("🆕 Create mode detected from URL:", currentPath);
            return true;
        }

        // Check if all role-specific field containers exist but are hidden (create mode pattern)
        const roleContainers = [
            document.getElementById("customer_fields"),
            document.getElementById("production_supervisor_fields"),
            document.getElementById("production_manager_fields"),
            document.getElementById("default_fields"),
            document.getElementById("general_address_fields"),
        ];

        const existingContainers = roleContainers.filter(
            (container) => container !== null
        );
        const hiddenContainers = existingContainers.filter(
            (container) =>
                container.style.display === "none" ||
                container.style.display === ""
        );

        // If most containers exist but are hidden, we're likely in create mode
        const isCreateMode =
            existingContainers.length >= 3 && hiddenContainers.length >= 2;

        console.log(
            `🔍 DOM analysis: ${existingContainers.length} containers exist, ${hiddenContainers.length} hidden`
        );
        console.log(`🔍 Create mode from DOM: ${isCreateMode}`);

        return isCreateMode;
    }

    /**
     * Handle field visibility for create mode using show/hide
     */
    handleCreateModeFields(roleId) {
        console.log("🆕 Handling CREATE mode fields for role:", roleId);

        // Hide all role-specific fields first
        this.hideAllRoleSpecificFields();

        switch (roleId) {
            case "3": // Production Manager
                console.log(
                    "� Production Manager selected - Showing production unit field"
                );
                this.showProductionUnitField();
                this.showDefaultFields();
                this.showGeneralAddressFields();
                break;

            case "4": // Production Supervisor
                console.log(
                    "👷 Production Supervisor selected - Showing user type field"
                );
                this.showProductionSupervisorFields();
                break;

            case "14": // Customer
                console.log("🏢 Customer selected - Showing customer fields");
                this.showCustomerFields();
                break;

            default:
                console.log("🔧 Other role selected - Showing default fields");
                this.showDefaultFields();
                this.showGeneralAddressFields();
                break;
        }
    }

    /**
     * Handle field initialization for edit mode (fields already rendered)
     */
    handleEditModeFields(roleId) {
        console.log("✏️ Handling EDIT mode fields for role:", roleId);

        switch (roleId) {
            case "3": // Production Manager
                console.log(
                    "📦 Production Manager - Initializing production manager features"
                );
                this.initializeRoleSpecificFeatures("production_manager");
                break;

            case "4": // Production Supervisor
                console.log(
                    "👷 Production Supervisor - Initializing production supervisor features"
                );
                this.initializeRoleSpecificFeatures("production_supervisor");
                break;

            case "14": // Customer
                console.log("🏢 Customer - Initializing customer features");
                this.initializeRoleSpecificFeatures("customer");
                break;

            default:
                console.log("🔧 Other role - Initializing default features");
                this.initializeRoleSpecificFeatures("default");
                break;
        }
    }

    /**
     * Hide all role-specific fields
     */
    hideAllRoleSpecificFields() {
        this.hideProductionUnitField();
        this.hideUserTypeField();
        this.hideCustomerFields();
        this.hideProductionSupervisorFields();
        this.hideGeneralAddressFields();
        // Don't automatically show default fields here - let the role handler decide
    }

    /**
     * Show user type field for Production Supervisor
     */
    showUserTypeField() {
        const userTypeWrapper = document.getElementById("user_type_wrapper");
        const userTypeSelect = document.getElementById("user_type_id");

        if (userTypeWrapper) {
            userTypeWrapper.style.display = "";
            console.log("✅ User type wrapper shown");
        }

        if (userTypeSelect) {
            userTypeSelect.required = true;

            // User types are already populated server-side in Blade template
            // No need for AJAX loading since options are provided by UserBasic component
            console.log(
                "✅ User type field shown and set as required - options already populated server-side"
            );
        }
    }

    /**
     * Hide user type field
     */
    hideUserTypeField() {
        const userTypeWrapper = document.getElementById("user_type_wrapper");
        const userTypeSelect = document.getElementById("user_type_id");

        if (userTypeWrapper) {
            userTypeWrapper.style.display = "none";
        }

        if (userTypeSelect) {
            userTypeSelect.required = false;
            userTypeSelect.value = "";
        }

        console.log("✅ User type field hidden");
    }

    /**
     * Show customer-specific fields
     */
    showCustomerFields() {
        // Show customer fields container
        const customerFieldsContainer =
            document.getElementById("customer_fields");
        if (customerFieldsContainer) {
            customerFieldsContainer.style.display = "";
            console.log("✅ Customer fields container shown");
        }

        // Hide default fields container for customer role
        const defaultFieldsContainer =
            document.getElementById("default_fields");
        if (defaultFieldsContainer) {
            defaultFieldsContainer.style.display = "none";
            console.log("✅ Default fields container hidden for customer role");
        }

        // Set customer fields as required
        const customerFields = [
            "contact_name",
            "contact_number",
            "contact_email",
            "gst_number",
        ];

        customerFields.forEach((fieldName) => {
            const field = document.getElementById(fieldName);
            if (field) {
                field.required = true;
                console.log(`✅ ${fieldName} set as required`);
            }
        });

        console.log("✅ Customer fields shown and set as required");
    }

    /**
     * Hide customer-specific fields
     */
    hideCustomerFields() {
        // Hide customer fields container
        const customerFieldsContainer =
            document.getElementById("customer_fields");
        if (customerFieldsContainer) {
            customerFieldsContainer.style.display = "none";
            console.log("✅ Customer fields container hidden");
        }

        // Remove required attribute from customer fields
        const customerFields = [
            "contact_name",
            "contact_number",
            "contact_email",
            "gst_number",
            "website",
        ];

        customerFields.forEach((fieldName) => {
            const field = document.getElementById(fieldName);
            if (field) {
                field.required = false;
                console.log(`✅ ${fieldName} requirement removed`);
            }
        });

        console.log("✅ Customer fields hidden");
    }

    /**
     * Show production supervisor-specific fields
     */
    showProductionSupervisorFields() {
        // Show production supervisor fields container
        const supervisorFieldsContainer = document.getElementById(
            "production_supervisor_fields"
        );
        if (supervisorFieldsContainer) {
            supervisorFieldsContainer.style.display = "";
            console.log("✅ Production supervisor fields container shown");
        }

        // Show default fields container for production supervisor
        const defaultFieldsContainer =
            document.getElementById("default_fields");
        if (defaultFieldsContainer) {
            defaultFieldsContainer.style.display = "";
            console.log(
                "✅ Default fields container shown for production supervisor"
            );
        }

        // Show general address fields for production supervisor
        this.showGeneralAddressFields();

        // Show user type field
        this.showUserTypeField();
    }

    /**
     * Hide production supervisor-specific fields
     */
    hideProductionSupervisorFields() {
        // Hide production supervisor fields container
        const supervisorFieldsContainer = document.getElementById(
            "production_supervisor_fields"
        );
        if (supervisorFieldsContainer) {
            supervisorFieldsContainer.style.display = "none";
            console.log("✅ Production supervisor fields container hidden");
        }
    }

    /**
     * Show default fields for other roles
     */
    showDefaultFields() {
        // Show default fields container
        const defaultFieldsContainer =
            document.getElementById("default_fields");
        if (defaultFieldsContainer) {
            defaultFieldsContainer.style.display = "";
            console.log("✅ Default fields container shown");
        }
    }

    /**
     * Show general address fields
     */
    showGeneralAddressFields() {
        const generalAddressContainer = document.getElementById(
            "general_address_fields"
        );
        if (generalAddressContainer) {
            generalAddressContainer.style.display = "";
            console.log("✅ General address fields container shown");
        }
    }

    /**
     * Hide general address fields
     */
    hideGeneralAddressFields() {
        const generalAddressContainer = document.getElementById(
            "general_address_fields"
        );
        if (generalAddressContainer) {
            generalAddressContainer.style.display = "none";
            console.log("✅ General address fields container hidden");
        }
    }

    /**
     * Load user types from API for specific role
     * @deprecated - User types are now populated server-side in Blade template
     * This method is no longer needed since UserBasic component provides options
     * based on role type from URL parameter
     */
    async loadUserTypes(roleId) {
        console.log(
            `ℹ️ loadUserTypes(${roleId}) called but skipped - options already populated server-side`
        );
        return; // Skip AJAX call since options are provided server-side

        // Legacy AJAX code - no longer needed since options are provided server-side
    }

    // Legacy method for backward compatibility
    showProductionUnitField() {
        console.log("🏭 Showing production unit field");

        if (this.productionUnitWrapper) {
            this.productionUnitWrapper.style.display = "";
            console.log("✅ Production unit wrapper shown");
        } else {
            console.warn("⚠️ Production unit wrapper not found");
        }

        if (this.userTypeSelect) {
            this.userTypeSelect.required = true;
            console.log("✅ User type select set as required");

            // Production units are already populated server-side via UserBasic component
            // No need to load them via AJAX
        } else {
            console.warn("⚠️ User type select not found");
        }
    }

    /**
     * Show production unit field and make it required
     */
    showProductionUnitField() {
        console.log("🏭 Showing production unit field for Production Manager");

        // Show the production unit wrapper from user-basic.blade.php
        if (this.productionUnitWrapper) {
            this.productionUnitWrapper.style.display = "";
            console.log("✅ Production unit wrapper shown");
        } else {
            console.warn("⚠️ Production unit wrapper not found");
        }

        // Also show the production manager fields container from user-details.blade.php
        const productionManagerFields = document.getElementById(
            "production_manager_fields"
        );
        if (productionManagerFields) {
            productionManagerFields.style.display = "";
            console.log("✅ Production manager fields container shown");
        }

        if (this.userTypeSelect) {
            this.userTypeSelect.required = true;

            // Production units are already populated server-side via UserBasic component
            // No need to load them via AJAX

            // Reinitialize Select2 if available
            if (typeof $ !== "undefined" && $.fn.select2) {
                setTimeout(() => {
                    $("#" + this.config.userTypeSelectId).select2({
                        placeholder: "Select Production Unit",
                        allowClear: true,
                    });
                    console.log("🔄 Select2 initialized for production units");
                }, 100);
            }
        } else {
            console.warn("⚠️ User type select not found");
        }

        // Update field labels and help text
        const productionUnitLabel = document.getElementById(
            "production_unit_label"
        );
        const productionUnitHelp = document.getElementById(
            "production_unit_help"
        );

        if (productionUnitLabel)
            productionUnitLabel.textContent = "Production Unit";
        if (productionUnitHelp)
            productionUnitHelp.textContent =
                "Production Manager must be assigned to a specific production unit.";

        console.log("✅ Production unit field shown and set as required");
    }

    /**
     * Hide production unit field and remove requirement
     */
    hideProductionUnitField() {
        console.log("🏭 Hiding production unit field");

        // Hide the production unit wrapper from user-basic.blade.php
        if (this.productionUnitWrapper) {
            this.productionUnitWrapper.style.display = "none";
            console.log("✅ Production unit wrapper hidden");
        }

        // Also hide the production manager fields container from user-details.blade.php
        const productionManagerFields = document.getElementById(
            "production_manager_fields"
        );
        if (productionManagerFields) {
            productionManagerFields.style.display = "none";
            console.log("✅ Production manager fields container hidden");
        }

        if (this.userTypeSelect) {
            this.userTypeSelect.required = false;
            this.userTypeSelect.value = "";
        }

        console.log("✅ Production unit field hidden and not required");
    }

    /**
     * Load production units from API
     * @deprecated This method is deprecated as production units are now populated server-side
     * in the UserBasic Blade component via productionUnitOptions() method.
     * No AJAX call is needed since options are already available in the dropdown.
     */
    async loadProductionUnits() {
        console.info(
            "ℹ️ loadProductionUnits() called but skipped - production units are already populated server-side via UserBasic component"
        );
        return;
    }

    /**
     * Populate production units dropdown
     */
    populateProductionUnits(data) {
        if (!this.userTypeSelect) return;

        // Reset to normal state
        this.userTypeSelect.disabled = false;
        this.userTypeSelect.innerHTML =
            '<option value="">Select Production Unit</option>';

        if (data.success && data.data && data.data.length > 0) {
            let unitCount = 0;
            data.data.forEach((unit) => {
                const option = document.createElement("option");
                option.value = unit.id;
                option.textContent = unit.name;
                this.userTypeSelect.appendChild(option);
                unitCount++;
                console.log(`➕ Added production unit: ${unit.name}`);
            });
            console.log(`✅ Loaded ${unitCount} production units successfully`);
        } else {
            console.log("⚠️ No production units data available");
            this.userTypeSelect.innerHTML =
                '<option value="">No production units available</option>';
        }
    }

    /**
     * Handle production units loading error
     */
    handleProductionUnitsError(error) {
        if (!this.userTypeSelect) return;

        this.userTypeSelect.disabled = false;
        this.userTypeSelect.innerHTML =
            '<option value="">Error loading production units</option>';

        // Add retry option
        const retryOption = document.createElement("option");
        retryOption.value = "";
        retryOption.textContent = "Click to retry";
        retryOption.style.color = "#666";
        this.userTypeSelect.appendChild(retryOption);

        // Add click event for retry
        this.userTypeSelect.addEventListener("change", (e) => {
            // Production units are already populated server-side via UserBasic component
            // No retry mechanism needed for AJAX calls
            console.log("� User type selection changed:", e.target.value);
        });
    }

    /**
     * Initialize user type select handling
     */
    initializeUserTypeHandling() {
        if (!this.userTypeSelect) return;

        this.userTypeSelect.addEventListener("change", (e) => {
            const selectedValue = e.target.value;
            console.log("📋 Production unit selected:", selectedValue);

            if (selectedValue) {
                console.log(
                    `✅ Production unit "${
                        e.target.options[e.target.selectedIndex].textContent
                    }" selected`
                );
            }
        });
    }

    /**
     * Initialize form validation using FormValidation library
     */
    initializeFormValidation() {
        const form = document.getElementById(this.config.formId);

        if (!form) {
            console.warn(`Form with ID "${this.config.formId}" not found`);
            return;
        }

        console.log("🔧 Checking FormValidation availability...");
        console.log(
            "  FormValidation available:",
            typeof FormValidation !== "undefined"
        );

        // Check if FormValidation is available, if not, use fallback validation
        if (typeof FormValidation === "undefined") {
            console.warn(
                "FormValidation library not loaded, using fallback validation"
            );
            this.initializeFallbackValidation(form);
            return;
        }

        console.log("✅ FormValidation library found, initializing...");

        // Remove browser validation to prevent conflicts
        form.setAttribute("novalidate", "novalidate");

        try {
            this.validator = FormValidation.formValidation(form, {
                fields: this.getValidationFields(),
                plugins: {
                    trigger: new FormValidation.plugins.Trigger(),
                    bootstrap: new FormValidation.plugins.Bootstrap5({
                        rowSelector: ".fv-row",
                        eleInvalidClass: "is-invalid",
                        eleValidClass: "is-valid",
                    }),
                    icon: new FormValidation.plugins.Icon({
                        valid: "ki-duotone ki-check fs-2",
                        invalid: "ki-duotone ki-cross fs-2",
                        validating: "ki-duotone ki-loading fs-2",
                        onPlaced: function (e) {
                            // Position the icon better by placing it right after the input field
                            const input = e.element;
                            const icon = e.iconElement;

                            // Remove any existing FormValidation error messages
                            const existingFeedback =
                                input.parentElement.querySelector(
                                    ".fv-plugins-message-container.invalid-feedback"
                                );
                            if (existingFeedback) {
                                existingFeedback.remove();
                            }

                            // Position icon properly
                            icon.style.position = "absolute";
                            icon.style.right = "15px";
                            icon.style.top = "50%";
                            icon.style.transform = "translateY(-50%)";
                            icon.style.zIndex = "5";

                            // Make sure the input container is positioned relative
                            input.parentElement.style.position = "relative";
                        },
                    }),
                },
            });

            // Add custom validation for fields
            this.validator.on("core.element.validated", (e) => {
                // Remove FormValidation's automatic error messages since we use server-side errors
                const formValidationFeedback =
                    e.element.parentElement.querySelector(
                        ".fv-plugins-message-container.invalid-feedback"
                    );
                if (formValidationFeedback) {
                    formValidationFeedback.remove();
                }

                if (e.valid) {
                    const invalidFeedback =
                        e.element.parentElement.querySelector(
                            ".invalid-feedback"
                        );
                    if (
                        invalidFeedback &&
                        !invalidFeedback.querySelector(".fw-semibold")
                    ) {
                        // Only hide FormValidation messages, not our custom server-side errors
                        invalidFeedback.style.display = "none";
                    }
                }
            });

            // Remove any existing FormValidation error messages on page load
            document
                .querySelectorAll(
                    ".fv-plugins-message-container.invalid-feedback"
                )
                .forEach((el) => {
                    el.remove();
                });

            // Submit button handler
            const submitButton = document.querySelector(
                'button[type="submit"]'
            );
            if (submitButton) {
                submitButton.addEventListener("click", (e) =>
                    this.handleFormSubmit(e, form)
                );
            }

            // Ensure submit button is enabled on init (e.g. after redirect back with validation errors)
            this.reEnableSubmitButton(form);

            console.log("✅ FormValidation initialized successfully");
        } catch (error) {
            console.error("❌ Error initializing FormValidation:", error);
            console.log("🔄 Falling back to basic validation");
            this.initializeFallbackValidation(form);
        }
    }

    /**
     * Initialize fallback validation when FormValidation library is not available
     */
    initializeFallbackValidation(form) {
        form.setAttribute("novalidate", "novalidate");

        const submitButton = document.querySelector('button[type="submit"]');
        if (submitButton) {
            submitButton.addEventListener("click", (e) => {
                e.preventDefault();
                console.log("🧪 Fallback validation triggered");

                if (this.validateFormFallback(form)) {
                    console.log("✅ Fallback validation passed");
                    this.submitForm(form);
                } else {
                    console.log("❌ Fallback validation failed");
                    this.showValidationErrors(form);
                }
            });
        }

        // Initialize real-time validation for immediate feedback
        this.initializeRealTimeValidation();

        console.log("✅ Fallback validation initialized");
    }

    /**
     * Enhanced fallback form validation with comprehensive field validation
     */
    validateFormFallback(form) {
        let isValid = true;
        const validationRules = this.getValidationRules();

        // Check if user_type_id is required based on role selection
        const roleField = form.querySelector('[name="role"]');
        const roleValue = roleField ? roleField.value : "";
        const isProductionManager = roleValue === "production_manager" || roleValue === "production-manager";
        const isCustomerOrSupervisor = roleValue === "customer" || roleValue === "production_supervisor" || roleValue === "production-supervisor";

        if (validationRules.user_type_id && (isProductionManager || isCustomerOrSupervisor)) {
            validationRules.user_type_id.required = true;
        }

        console.log("🧪 Running enhanced fallback validation...");
        console.log(
            "🏭 Production Manager selected:",
            isProductionManager ? "YES" : "NO"
        );

        // Validate each field based on rules
        Object.keys(validationRules).forEach((fieldName) => {
            const field = form.querySelector(`[name="${fieldName}"]`);
            if (!field) return;

            const rules = validationRules[fieldName];
            const value = field.value.trim();

            console.log(
                `🔍 Validating ${fieldName}:`,
                value ? "✅ has value" : "❌ empty"
            );

            // Check required validation
            if (rules.required && !value) {
                field.classList.add("is-invalid");
                this.showFieldError(
                    field,
                    rules.messages.required || "This field is required"
                );
                isValid = false;
                console.log(`❌ ${fieldName} failed required validation`);
            }
            // Check other validations if field has value
            else if (value) {
                let fieldValid = true;
                let errorMessage = "";

                // Length validation
                if (rules.minLength && value.length < rules.minLength) {
                    fieldValid = false;
                    errorMessage =
                        rules.messages.minLength ||
                        `Minimum ${rules.minLength} characters required`;
                }
                if (rules.maxLength && value.length > rules.maxLength) {
                    fieldValid = false;
                    errorMessage =
                        rules.messages.maxLength ||
                        `Maximum ${rules.maxLength} characters allowed`;
                }

                // Pattern validation
                if (rules.pattern && !rules.pattern.test(value)) {
                    fieldValid = false;
                    errorMessage = rules.messages.pattern || "Invalid format";
                }

                // Email validation
                if (rules.email && !this.isValidEmail(value)) {
                    fieldValid = false;
                    errorMessage =
                        rules.messages.email ||
                        "Please enter a valid email address";
                }

                // Phone validation
                if (rules.phone && !this.isValidPhone(value)) {
                    fieldValid = false;
                    errorMessage =
                        rules.messages.phone ||
                        "Please enter a valid phone number";
                }

                if (fieldValid) {
                    field.classList.remove("is-invalid");
                    this.hideFieldError(field);
                    console.log(`✅ ${fieldName} passed validation`);
                } else {
                    field.classList.add("is-invalid");
                    this.showFieldError(field, errorMessage);
                    isValid = false;
                    console.log(
                        `❌ ${fieldName} failed validation: ${errorMessage}`
                    );
                }
            } else {
                // Field is not required and empty - clear any errors
                field.classList.remove("is-invalid");
                this.hideFieldError(field);
                console.log(
                    `✅ ${fieldName} passed validation (optional field)`
                );
            }
        });

        console.log(
            "🧪 Enhanced validation result:",
            isValid ? "✅ PASSED" : "❌ FAILED"
        );
        return isValid;
    }

    /**
     * Get comprehensive validation rules for all user detail fields
     */
    getValidationRules() {
        return {
            name: {
                required: true,
                minLength: 2,
                maxLength: 255,
                pattern: /^[a-zA-Z\s\.\-']+$/,
                messages: {
                    required: "Full name is required",
                    minLength: "Name must be at least 2 characters long",
                    maxLength: "Name cannot exceed 255 characters",
                    pattern:
                        "Name can only contain letters, spaces, dots, hyphens and apostrophes",
                },
            },
            email: {
                required: true,
                email: true,
                maxLength: 255,
                messages: {
                    required: "Email address is required",
                    email: "Please enter a valid email address",
                    maxLength: "Email cannot exceed 255 characters",
                },
            },
            phone_number: {
                required: true,
                phone: true,
                minLength: 10,
                maxLength: 15,
                pattern: /^[\+]?[1-9][\d]{0,15}$/,
                messages: {
                    required: "Phone number is required",
                    phone: "Please enter a valid phone number",
                    minLength: "Phone number must be at least 10 digits",
                    maxLength: "Phone number cannot exceed 15 digits",
                    pattern:
                        "Phone number can only contain digits and optional + prefix",
                },
            },
            alternative_phone_number: {
                required: false,
                phone: true,
                minLength: 10,
                maxLength: 15,
                pattern: /^[\+]?[1-9][\d]{0,15}$/,
                messages: {
                    phone: "Please enter a valid alternative phone number",
                    minLength:
                        "Alternative phone number must be at least 10 digits",
                    maxLength:
                        "Alternative phone number cannot exceed 15 digits",
                    pattern:
                        "Phone number can only contain digits and optional + prefix",
                },
            },
            aadhar_card: {
                required: true,
                pattern: /^[0-9]{12}$/,
                messages: {
                    required: "Aadhar card is required",
                    pattern: "Aadhar card must be exactly 12 digits",
                },
            },
            pan_number: {
                required: true,
                pattern: /^[A-Z]{5}[0-9]{4}[A-Z]{1}$/,
                messages: {
                    required: "PAN number is required",
                    pattern:
                        "PAN number must be in format: ABCDE1234F (5 letters, 4 digits, 1 letter)",
                },
            },
            address: {
                required: true,
                minLength: 5,
                maxLength: 500,
                messages: {
                    required: "Address is required",
                    minLength: "Address must be at least 5 characters long",
                    maxLength: "Address cannot exceed 500 characters",
                },
            },
            city: {
                required: true,
                minLength: 2,
                maxLength: 100,
                pattern: /^[a-zA-Z\s\.\-']+$/,
                messages: {
                    required: "City is required",
                    minLength: "City name must be at least 2 characters long",
                    maxLength: "City name cannot exceed 100 characters",
                    pattern:
                        "City name can only contain letters, spaces, dots, hyphens and apostrophes",
                },
            },
            zipcode: {
                required: true,
                pattern: /^[0-9]{6}$/,
                messages: {
                    required: "Zipcode is required",
                    pattern: "Zipcode must be exactly 6 digits",
                },
            },
            state_id: {
                required: true,
                messages: {
                    required: "Please select a state",
                },
            },
            role: {
                required: true,
                messages: {
                    required: "Please select a role",
                },
            },
            user_type_id: {
                required: false, // Will be conditionally required based on role
                messages: {
                    required: "Please select a user type",
                },
            },
            // Customer-specific fields
            contact_name: {
                required: false, // Will be conditionally required for customers
                minLength: 2,
                maxLength: 100,
                pattern: /^[a-zA-Z\s\.\-']+$/,
                messages: {
                    required: "Contact name is required",
                    minLength:
                        "Contact name must be at least 2 characters long",
                    maxLength: "Contact name cannot exceed 100 characters",
                    pattern:
                        "Contact name can only contain letters, spaces, dots, hyphens and apostrophes",
                },
            },
            contact_number: {
                required: false, // Will be conditionally required for customers
                pattern: /^[\+]?[1-9][\d]{9,14}$/,
                messages: {
                    required: "Contact number is required",
                    pattern:
                        "Please enter a valid contact number (10-15 digits)",
                },
            },
            contact_email: {
                required: false, // Will be conditionally required for customers
                email: true,
                maxLength: 255,
                messages: {
                    required: "Contact email is required",
                    email: "Please enter a valid contact email address",
                    maxLength: "Contact email cannot exceed 255 characters",
                },
            },
            gst_number: {
                required: false, // Will be conditionally required for customers
                pattern:
                    /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/,
                messages: {
                    required: "GST number is required",
                    pattern: "Please enter a valid GST number (15 characters)",
                },
            },
            website: {
                required: false, // Optional for customers
                url: true,
                maxLength: 255,
                messages: {
                    url: "Please enter a valid website URL",
                    maxLength: "Website URL cannot exceed 255 characters",
                },
            },
            status: {
                required: true,
                messages: {
                    required: "Please select a status",
                },
            },
        };
    }

    /**
     * Show field error message below the field
     */
    showFieldError(field, message) {
        // Remove existing error message
        this.hideFieldError(field);

        // Create error element
        const errorElement = document.createElement("div");
        errorElement.className = "invalid-feedback";
        errorElement.textContent = message;
        errorElement.style.display = "block";

        // Find the best position to insert error message
        let insertPosition = field.nextSibling;

        // If field is in a Select2 container, insert after the container
        const select2Container =
            field.parentNode.querySelector(".select2-container");
        if (select2Container) {
            insertPosition = select2Container.nextSibling;
            field.parentNode.insertBefore(errorElement, insertPosition);
        } else {
            // Insert after the field
            field.parentNode.insertBefore(errorElement, insertPosition);
        }

        field.classList.add("is-invalid");
    }

    /**
     * Hide field error message with stable layout
     */
    hideFieldError(field) {
        const errorElement =
            field.parentNode.querySelector(".invalid-feedback");
        if (errorElement) {
            errorElement.remove();
        }
        field.classList.remove("is-invalid");
    }

    /**
     * Get validation field configuration
     */
    getValidationFields() {
        const fields = {};

        // Basic user fields
        if (document.querySelector('[name="name"]')) {
            fields.name = {
                validators: {
                    notEmpty: { message: "Full name is required" },
                    stringLength: {
                        min: 2,
                        max: 255,
                        message: "Name must be between 2 and 255 characters",
                    },
                    regexp: {
                        regexp: /^[a-zA-Z\s\.\-']+$/,
                        message:
                            "Name can only contain letters, spaces, hyphens, dots and apostrophes.",
                    },
                },
            };
        }

        if (document.querySelector('[name="email"]')) {
            fields.email = {
                validators: {
                    notEmpty: { message: "Email address is required" },
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
            fields.phone_number = {
                validators: {
                    notEmpty: { message: "Phone number is required" },
                    regexp: {
                        regexp: /^([0-9\s\-\+\(\)]*)$/,
                        message: "Please provide a valid phone number",
                    },
                    stringLength: {
                        min: 10,
                        max: 15,
                        message:
                            "Phone number must be between 10 and 15 digits",
                    },
                },
            };
        }

        if (document.querySelector('[name="role"]')) {
            fields.role = {
                validators: {
                    notEmpty: { message: "Please select a role" },
                },
            };
        }

        if (document.querySelector('[name="status"]')) {
            fields.status = {
                validators: {
                    notEmpty: { message: "Please select a status" },
                },
            };
        }

        // Address fields (required on server for all user forms)
        if (document.querySelector('[name="address"]')) {
            fields.address = {
                validators: {
                    notEmpty: { message: "Address is required." },
                    stringLength: {
                        max: 500,
                        message: "Address cannot exceed 500 characters",
                    },
                },
            };
        }

        if (document.querySelector('[name="city"]')) {
            fields.city = {
                validators: {
                    notEmpty: { message: "City name is required." },
                    stringLength: {
                        min: 2,
                        max: 100,
                        message:
                            "City name must be between 2 and 100 characters",
                    },
                    regexp: {
                        regexp: /^[a-zA-Z\s]+$/,
                        message:
                            "City name can only contain letters and spaces",
                    },
                },
            };
        }

        if (document.querySelector('[name="state_id"]')) {
            fields.state_id = {
                validators: {
                    notEmpty: { message: "Please select a state" },
                },
            };
        }

        if (document.querySelector('[name="zipcode"]')) {
            fields.zipcode = {
                validators: {
                    notEmpty: { message: "Please enter the zipcode" },
                    regexp: {
                        regexp: /^[0-9]{6}$/,
                        message: "Please enter a valid 6-digit zipcode",
                    },
                },
            };
        }

        // Administrator-specific fields
        if (document.querySelector('[name="aadhar_card"]')) {
            fields.aadhar_card = {
                validators: {
                    notEmpty: { message: "Aadhar card number is required" },
                    regexp: {
                        regexp: /^[0-9]{12}$/,
                        message: "Please enter a valid 12-digit Aadhar number",
                    },
                },
            };
        }

        if (document.querySelector('[name="pan_number"]')) {
            fields.pan_number = {
                validators: {
                    regexp: {
                        regexp: /^[A-Z]{5}[0-9]{4}[A-Z]{1}$/,
                        message:
                            "Please enter a valid PAN number (AAAAA9999A format)",
                    },
                },
            };
        }

        if (document.querySelector('[name="gst_number"]')) {
            fields.gst_number = {
                validators: {
                    regexp: {
                        regexp: /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/,
                        message: "Please enter a valid GST number format",
                    },
                },
            };
        }

        // Production unit / user type field (required when role is production_manager)
        if (document.querySelector('[name="user_type_id"]')) {
            fields.user_type_id = {
                validators: {
                    callback: {
                        message:
                            "Production unit / User type is required for this role",
                        callback: (input) => {
                            const roleSelect = document.getElementById(
                                this.config.roleSelectId
                            ) || document.querySelector('[name="role"]');
                            const roleValue = roleSelect ? roleSelect.value : "";
                            const isProductionManager = roleValue === "production_manager" || roleValue === "production-manager";
                            const isCustomerOrSupervisor = roleValue === "customer" || roleValue === "production_supervisor" || roleValue === "production-supervisor";
                            if (roleSelect && (isProductionManager || isCustomerOrSupervisor)) {
                                return input.value !== "" && input.value !== null;
                            }
                            return true;
                        },
                    },
                },
            };
        }

        console.log("🔧 Validation fields configured:", Object.keys(fields));
        return fields;
    }

    /**
     * Initialize real-time validation with enhanced error clearing
     */
    initializeRealTimeValidation() {
        const form = document.getElementById(this.config.formId);
        if (!form) return;

        // Real-time validation for required fields
        const requiredFields = form.querySelectorAll(
            "input[required], select[required], textarea[required]"
        );
        requiredFields.forEach((field) => {
            field.addEventListener("blur", (e) => {
                if (e.target.value.trim() === "") {
                    e.target.classList.add("is-invalid");
                    this.showFieldError(e.target, "This field is required");
                } else {
                    e.target.classList.remove("is-invalid");
                    this.hideFieldError(e.target);
                }
            });

            field.addEventListener("input", (e) => {
                if (e.target.value.trim() !== "") {
                    e.target.classList.remove("is-invalid");
                    this.hideFieldError(e.target);
                }
            });

            // For select fields, also listen to change events
            if (field.tagName.toLowerCase() === "select") {
                field.addEventListener("change", (e) => {
                    if (e.target.value.trim() !== "") {
                        e.target.classList.remove("is-invalid");
                        this.hideFieldError(e.target);
                    } else {
                        e.target.classList.add("is-invalid");
                        this.showFieldError(
                            e.target,
                            "Please select an option"
                        );
                    }
                });
            }
        });

        // Real-time validation for email fields
        const emailFields = form.querySelectorAll('input[type="email"]');
        emailFields.forEach((field) => {
            field.addEventListener("blur", (e) => {
                if (e.target.value && !this.isValidEmail(e.target.value)) {
                    e.target.classList.add("is-invalid");
                    this.showFieldError(
                        e.target,
                        "Please enter a valid email address"
                    );
                } else {
                    e.target.classList.remove("is-invalid");
                    this.hideFieldError(e.target);
                }
            });
        });

        // Real-time validation for phone fields
        const phoneFields = form.querySelectorAll('input[name*="phone"]');
        phoneFields.forEach((field) => {
            field.addEventListener("blur", (e) => {
                if (e.target.value && !this.isValidPhone(e.target.value)) {
                    e.target.classList.add("is-invalid");
                    this.showFieldError(
                        e.target,
                        "Please enter a valid phone number"
                    );
                } else {
                    e.target.classList.remove("is-invalid");
                    this.hideFieldError(e.target);
                }
            });

            // Format phone numbers on input
            field.addEventListener("input", (e) => {
                let value = e.target.value.replace(/\D/g, "");
                if (value.length > 15) value = value.substr(0, 15);
                e.target.value = value;
            });
        });

        // Format uppercase fields
        const uppercaseFields = form.querySelectorAll(
            'input[name="gst_number"], input[name="pan_number"]'
        );
        uppercaseFields.forEach((field) => {
            field.addEventListener("input", (e) => {
                e.target.value = e.target.value.toUpperCase();
            });
        });

        // Format Aadhar numbers (digits only)
        const aadharField = form.querySelector('input[name="aadhar_card"]');
        if (aadharField) {
            aadharField.addEventListener("input", (e) => {
                let value = e.target.value.replace(/\D/g, "");
                if (value.length > 12) value = value.substr(0, 12);
                e.target.value = value;
            });
        }

        // Format postal codes (digits only)
        const postalFields = form.querySelectorAll(
            'input[name="zipcode"], input[name="pincode"]'
        );
        postalFields.forEach((field) => {
            field.addEventListener("input", (e) => {
                let value = e.target.value.replace(/\D/g, "");
                if (value.length > 6) value = value.substr(0, 6);
                e.target.value = value;
            });
        });

        console.log("✅ Real-time validation initialized");
    }

    /**
     * Handle form submission
     */
    handleFormSubmit(e, form) {
        e.preventDefault();

        if (this.validator) {
            // Use FormValidation library
            this.validator
                .validate()
                .then((result) => {
                    if (result === "Valid") {
                        this.submitForm(form);
                    } else {
                        this.reEnableSubmitButton(form);
                        this.showValidationErrors(form);
                    }
                })
                .catch((error) => {
                    console.error("Validation error:", error);
                    this.handleValidationError(error);
                });
        } else {
            // Fallback validation
            if (this.validateFormFallback(form)) {
                this.submitForm(form);
            } else {
                this.reEnableSubmitButton(form);
                this.showValidationErrors(form);
            }
        }
    }

    /**
     * Re-enable submit button after validation fail or on page load
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
     * Enhanced validation error display without popup
     */
    showValidationErrors(form) {
        const firstInvalidField = form.querySelector(".is-invalid");
        if (firstInvalidField) {
            firstInvalidField.scrollIntoView({
                behavior: "smooth",
                block: "center",
            });
            firstInvalidField.focus();
        }

        console.log("🎯 Scrolled to first invalid field for user attention");
    }

    /**
     * Handle validation error
     */
    handleValidationError(error) {
        console.error("Form validation error:", error);

        if (typeof Swal !== "undefined") {
            Swal.fire({
                title: "Error",
                text: "An error occurred during form validation. Please try again.",
                icon: "error",
                confirmButtonText: "Ok, got it!",
                customClass: { confirmButton: "btn btn-primary" },
            });
        }

        const submitButton = document.querySelector('button[type="submit"]');
        if (submitButton) {
            submitButton.removeAttribute("data-kt-indicator");
            submitButton.disabled = false;
        }
    }

    /**
     * Submit the form
     */
    submitForm(form) {
        const submitButton = document.querySelector('button[type="submit"]');
        if (submitButton) {
            submitButton.setAttribute("data-kt-indicator", "on");
            submitButton.disabled = true;
        }

        console.log("✅ Form validation passed - submitting form");
        form.submit();
    }

    /**
     * Utility methods
     */
    isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    isValidPhone(phone) {
        const phoneRegex = /^[0-9\s\-\+\(\)]{10,15}$/;
        return phoneRegex.test(phone);
    }

    /**
     * Public API methods
     */
    destroy() {
        if (this.validator) {
            this.validator.destroy();
        }
        console.log("🧹 UserForm destroyed");
    }

    validate() {
        if (this.validator) {
            return this.validator.validate();
        }
        return Promise.resolve("Valid");
    }

    resetForm() {
        const form = document.getElementById(this.config.formId);
        if (form) {
            form.reset();

            // Clear validation states
            const invalidFields = form.querySelectorAll(".is-invalid");
            invalidFields.forEach((field) => {
                field.classList.remove("is-invalid");
                this.hideFieldError(field);
            });

            console.log("🔄 Form reset complete");
        }
    }

    /**
     * Initialize regional manager states checkboxes
     * Loads all states directly from the form
     */
    initializeRegionalManagerStates() {
        const statesSection = document.getElementById(
            "regional_manager_states_section"
        );
        if (!statesSection) {
            // Not a regional manager form, skip
            return;
        }

        console.log("✅ Initializing regional manager states checkboxes");

        // Load all states directly from the form
        this.loadRegionalManagerStates();

        // Handle checkbox changes to update hidden input
        const checkboxesContainer = document.getElementById(
            "regional_manager_states_checkboxes"
        );
        if (checkboxesContainer) {
            checkboxesContainer.addEventListener("change", (e) => {
                if (e.target.type === "checkbox") {
                    this.updateRegionalManagerStatesInput();
                }
            });
        }
    }

    /**
     * Load states for regional manager checkboxes
     * Uses existing state list from the form (no AJAX call needed)
     */
    async loadRegionalManagerStates() {
        const loadingDiv = document.getElementById(
            "regional_manager_states_loading"
        );
        const checkboxesDiv = document.getElementById(
            "regional_manager_states_checkboxes"
        );
        const statesContainer = document.getElementById(
            "regional_manager_states_container"
        );

        if (!loadingDiv || !checkboxesDiv || !statesContainer) {
            return;
        }

        // Show loading
        loadingDiv.style.display = "block";
        checkboxesDiv.style.display = "none";
        loadingDiv.innerHTML = `
            <i class="ki-duotone ki-loading fs-3">
                <span class="path1"></span>
                <span class="path2"></span>
            </i>
            <div class="mt-2">Loading states...</div>
        `;

        try {
            // Extract states from the existing state select dropdown in the form
            // Try multiple selectors to find the state dropdown
            const stateSelect =
                document.querySelector('[name="state_id"]') ||
                document.querySelector('select[name*="state"]') ||
                document.querySelector("#state_id") ||
                document.querySelector(".state-select");

            if (stateSelect && stateSelect.options.length > 1) {
                const states = {};
                Array.from(stateSelect.options).forEach((option) => {
                    if (option.value && option.value !== "") {
                        states[option.value] = option.text.trim();
                    }
                });

                if (Object.keys(states).length > 0) {
                    console.log(
                        `✅ Found ${
                            Object.keys(states).length
                        } states from form dropdown`
                    );
                    const data = {
                        success: true,
                        states: states,
                    };
                    this.populateRegionalManagerStates(data);
                    return;
                }
            }

            // If state select not found or empty, show error
            throw new Error(
                "State select dropdown not found or empty in the form. Please ensure the form has a state dropdown."
            );
        } catch (error) {
            console.error("❌ Error loading regional manager states:", error);
            loadingDiv.innerHTML = `
                <div class="text-danger">
                    <i class="ki-duotone ki-information fs-3">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    <div class="mt-2">Error loading states. Please ensure the form has a state dropdown.</div>
                    <div class="mt-1 text-muted small">${error.message}</div>
                </div>
            `;
        }
    }

    /**
     * Populate regional manager states checkboxes
     */
    populateRegionalManagerStates(data) {
        const loadingDiv = document.getElementById(
            "regional_manager_states_loading"
        );
        const checkboxesDiv = document.getElementById(
            "regional_manager_states_checkboxes"
        );

        if (!loadingDiv || !checkboxesDiv) {
            return;
        }

        // Get selected states from hidden input
        const hiddenInput = document.getElementById(
            "regional_manager_states_json"
        );
        const selectedStates = hiddenInput
            ? JSON.parse(hiddenInput.value || "[]") || []
            : [];

        // Clear existing checkboxes
        checkboxesDiv.innerHTML = "";

        if (
            data.success &&
            data.states &&
            Object.keys(data.states).length > 0
        ) {
            // Create checkbox grid
            const row = document.createElement("div");
            row.className = "row g-3";

            Object.entries(data.states).forEach(([id, name]) => {
                const col = document.createElement("div");
                col.className = "col-md-4 col-lg-3";

                const checkboxWrapper = document.createElement("div");
                checkboxWrapper.className = "form-check";

                const checkbox = document.createElement("input");
                checkbox.type = "checkbox";
                checkbox.className = "form-check-input";
                checkbox.id = `regional_manager_state_${id}`;
                checkbox.name = "regional_manager_states[]";
                checkbox.value = id;
                checkbox.checked = selectedStates.includes(parseInt(id));

                const label = document.createElement("label");
                label.className = "form-check-label";
                label.htmlFor = `regional_manager_state_${id}`;
                label.textContent = name;

                checkboxWrapper.appendChild(checkbox);
                checkboxWrapper.appendChild(label);
                col.appendChild(checkboxWrapper);
                row.appendChild(col);
            });

            checkboxesDiv.appendChild(row);
            loadingDiv.style.display = "none";
            checkboxesDiv.style.display = "block";
        } else {
            loadingDiv.innerHTML = `
                <div class="text-muted">
                    <i class="ki-duotone ki-information fs-3">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>
                    <div class="mt-2">No states available</div>
                </div>
            `;
        }
    }

    /**
     * Clear regional manager states checkboxes
     */
    clearRegionalManagerStates() {
        const loadingDiv = document.getElementById(
            "regional_manager_states_loading"
        );
        const checkboxesDiv = document.getElementById(
            "regional_manager_states_checkboxes"
        );

        if (loadingDiv) {
            loadingDiv.style.display = "block";
            loadingDiv.innerHTML = `
                <i class="ki-duotone ki-information fs-3">
                    <span class="path1"></span>
                    <span class="path2"></span>
                    <span class="path3"></span>
                </i>
                <div class="mt-2">No states available</div>
            `;
        }

        if (checkboxesDiv) {
            checkboxesDiv.style.display = "none";
            checkboxesDiv.innerHTML = "";
        }

        // Clear hidden input
        const hiddenInput = document.getElementById(
            "regional_manager_states_json"
        );
        if (hiddenInput) {
            hiddenInput.value = "[]";
        }
    }

    /**
     * Update hidden input with selected states
     */
    updateRegionalManagerStatesInput() {
        const checkboxes = document.querySelectorAll(
            '#regional_manager_states_checkboxes input[type="checkbox"]:checked'
        );
        const selectedStates = Array.from(checkboxes).map((cb) =>
            parseInt(cb.value)
        );

        const hiddenInput = document.getElementById(
            "regional_manager_states_json"
        );
        if (hiddenInput) {
            hiddenInput.value = JSON.stringify(selectedStates);
        }

        console.log("✅ Updated regional manager states:", selectedStates);
    }
}

// Export for use in other modules or global access
window.UserForm = UserForm;

// Quick initialization helper
window.UserForm.quickInit = function (config = {}) {
    const userForm = new UserForm(config);
    userForm.init();
    return userForm;
};

console.log("✅ UserForm module loaded successfully");
