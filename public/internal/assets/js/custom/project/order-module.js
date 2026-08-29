/**
 * Order Module - Client-side validation and functionality
 *
 * Handles order form validation, dependent dropdowns, and AJAX interactions
 * Following the same pattern as UserModule for consistency
 */

(function (global) {
    "use strict";

    // Order Module Constructor
    const OrderModule = function () {
        this.initialized = false;
        this.formType = null; // 'create' or 'update'
        this.formElement = null;
        this.validationRules = {};
        this.validationMessages = {};
        this.deliveryLocationIndex = 1; // Next index when adding delivery location (0 is initial block)
    };

    // Initialize the module
    OrderModule.prototype.init = function () {
        if (this.initialized) {
            console.log("🎯 OrderModule already initialized");
            return;
        }

        console.log("🎯 Initializing OrderModule");
        this.detectFormType();
        this.setupValidationRules();
        this.setupDependentDropdowns();
        this.setupDatePickers();
        this.initializeFormValidation();
        this.setupFormSubmission();
        this.initializeDeliveryLocations();

        this.initialized = true;
        console.log("✅ OrderModule initialization complete");
    };

    // Quick initialization method
    OrderModule.prototype.quickInit = function () {
        this.init();
    };

    // Detect form type (create or update)
    OrderModule.prototype.detectFormType = function () {
        if (document.getElementById("createOrderForm")) {
            this.formType = "create";
            this.formElement = document.getElementById("createOrderForm");
            console.log("🎯 Detected CREATE order form");
        } else if (document.getElementById("updateOrderForm")) {
            this.formType = "update";
            this.formElement = document.getElementById("updateOrderForm");
            console.log("🎯 Detected UPDATE order form");
        } else {
            console.log("⚠️ No order form detected");
            return;
        }
    };

    // Setup validation rules (field names must match form input names)
    OrderModule.prototype.setupValidationRules = function () {
        if (!this.formElement) return;

        this.validationRules = {
            user_id: { required: true },
            product_id: { required: true },
            production_unit_id: { required: true },
            maintenance_type: { required: true },
            order_date: { required: true },
            delivery_date: { required: true },
            batch_number: { maxlength: 50 },
            quantity: { required: true, number: true, min: 1 },
            gst_number: { required: true },
            pan_number: { required: true },
            "delivery_details[0][quantity]": { required: true, number: true, min: 1 },
            "delivery_details[0][contact_name]": { required: true },
            "delivery_details[0][contact_number]": { required: true },
            "delivery_details[0][address]": { required: true },
            "delivery_details[0][state_id]": { required: true },
            "delivery_details[0][city]": { required: true },
            "delivery_details[0][zipcode]": { required: true },
        };

        this.validationMessages = {
            user_id: { required: "Please select a customer" },
            product_id: { required: "Please select a product" },
            production_unit_id: { required: "Please select a production unit" },
            maintenance_type: { required: "Please select maintenance type" },
            order_date: { required: "Order date is required" },
            delivery_date: { required: "Delivery date is required" },
            batch_number: { maxlength: "Batch number must not exceed 50 characters" },
            quantity: { required: "Quantity is required", number: "Enter a valid number", min: "Quantity must be at least 1" },
            gst_number: { required: "GST number is required" },
            pan_number: { required: "PAN number is required" },
            "delivery_details[0][quantity]": { required: "Quantity is required", number: "Enter a valid number", min: "Quantity must be at least 1" },
            "delivery_details[0][contact_name]": { required: "Contact name is required" },
            "delivery_details[0][contact_number]": { required: "Contact number is required" },
            "delivery_details[0][address]": { required: "Address is required" },
            "delivery_details[0][state_id]": { required: "Please select a state" },
            "delivery_details[0][city]": { required: "City is required" },
            "delivery_details[0][zipcode]": { required: "Zipcode is required" },
        };
    };

    // Setup dependent dropdowns
    OrderModule.prototype.setupDependentDropdowns = function () {
        const customerSelect = document.getElementById("customer_id");
        const productTypeSelect = document.getElementById("product_type_id");
        const productSelect = document.getElementById("product_id");
        const stateSelect = document.getElementById("state_id");

        // Customer -> Product Types
        if (customerSelect && productTypeSelect) {
            customerSelect.addEventListener("change", (e) => {
                const customerId = e.target.value;
                console.log("🎯 Customer changed:", customerId);

                if (customerId) {
                    this.loadProductTypes(customerId);
                } else {
                    this.clearSelect(productTypeSelect, "Select Product Type");
                    this.clearSelect(productSelect, "Select Product");
                }
            });
        }

        // Product Type -> Products
        if (productTypeSelect && productSelect) {
            productTypeSelect.addEventListener("change", (e) => {
                const productTypeId = e.target.value;
                console.log("🎯 Product type changed:", productTypeId);

                if (productTypeId) {
                    this.loadProducts(productTypeId);
                } else {
                    this.clearSelect(productSelect, "Select Product");
                }
            });
        }

        // State change handling removed - states are pre-loaded in components
    };

    // Load product types based on customer
    OrderModule.prototype.loadProductTypes = function (customerId) {
        console.log("🔄 Loading product types for customer:", customerId);

        fetch(`/api/v1/customers/${customerId}/product-types`, {
            method: "GET",
            headers: {
                Accept: "application/json",
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": document
                    .querySelector('meta[name="csrf-token"]')
                    ?.getAttribute("content"),
            },
        })
            .then((response) => response.json())
            .then((data) => {
                if (data.success) {
                    this.populateSelect(
                        "product_type_id",
                        data.data,
                        "Select Product Type"
                    );
                    console.log("✅ Product types loaded successfully");
                } else {
                    console.error(
                        "❌ Error loading product types:",
                        data.message
                    );
                }
            })
            .catch((error) => {
                console.error("❌ Network error loading product types:", error);
            });
    };

    // Load products based on product type
    OrderModule.prototype.loadProducts = function (productTypeId) {
        console.log("🔄 Loading products for product type:", productTypeId);

        fetch(`/api/v1/product-types/${productTypeId}/products`, {
            method: "GET",
            headers: {
                Accept: "application/json",
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": document
                    .querySelector('meta[name="csrf-token"]')
                    ?.getAttribute("content"),
            },
        })
            .then((response) => response.json())
            .then((data) => {
                if (data.success) {
                    this.populateSelect(
                        "product_id",
                        data.data,
                        "Select Product"
                    );
                    console.log("✅ Products loaded successfully");
                } else {
                    console.error("❌ Error loading products:", data.message);
                }
            })
            .catch((error) => {
                console.error("❌ Network error loading products:", error);
            });
    };

    // Load all active states
    // DEPRECATED: States are now pre-loaded in the component
    // This method is kept for backward compatibility but does nothing
    OrderModule.prototype.loadStates = function () {
        // States are pre-loaded in the component, no API call needed
        // The state dropdown already has all states loaded from the PHP component
        return;
    };

    // Clear select options
    OrderModule.prototype.clearSelect = function (selectElement, placeholder) {
        selectElement.innerHTML = `<option value="">${placeholder}</option>`;

        // Trigger Select2 update if available
        if (typeof $(selectElement).select2 === "function") {
            $(selectElement).trigger("change");
        }
    };

    // Populate select with options
    OrderModule.prototype.populateSelect = function (
        selectId,
        options,
        placeholder
    ) {
        const selectElement = document.getElementById(selectId);
        if (!selectElement) return;

        let html = `<option value="">${placeholder}</option>`;
        options.forEach((option) => {
            html += `<option value="${option.id}">${option.name}</option>`;
        });

        selectElement.innerHTML = html;

        // Trigger Select2 update if available
        if (typeof $(selectElement).select2 === "function") {
            $(selectElement).trigger("change");
        }
    };

    // Setup date pickers
    OrderModule.prototype.setupDatePickers = function () {
        const orderDatePicker = document.getElementById("order_date");
        const deliveryDatePicker = document.getElementById("delivery_date");
        const dispatchDatePicker = document.getElementById("dispatch_date");
        const testDatePicker = document.getElementById("test_date");

        // Initialize date pickers if Flatpickr is available
        if (typeof flatpickr !== "undefined") {
            if (orderDatePicker) {
                flatpickr(orderDatePicker, {
                    dateFormat: "Y-m-d",
                    maxDate: "today",
                });
            }

            if (deliveryDatePicker) {
                flatpickr(deliveryDatePicker, {
                    dateFormat: "Y-m-d",
                    minDate: "today",
                });
            }

            if (dispatchDatePicker) {
                flatpickr(dispatchDatePicker, {
                    dateFormat: "Y-m-d",
                    minDate: "today",
                });
            }

            if (testDatePicker) {
                flatpickr(testDatePicker, {
                    dateFormat: "Y-m-d",
                });
            }
        }
    };

    // Initialize form validation (jQuery Validate - disables HTML5 validation when form has novalidate)
    OrderModule.prototype.initializeFormValidation = function () {
        if (!this.formElement) return;
        var self = this;

        if (typeof global.$ !== "undefined" && global.$ && typeof global.$.fn.validate === "function") {
            global.$(this.formElement).validate({
                rules: this.validationRules,
                messages: this.validationMessages,
                errorElement: "div",
                errorClass: "invalid-feedback",
                validClass: "is-valid",
                ignore: "",
                errorPlacement: function (error, element) {
                    error.addClass("invalid-feedback d-block");
                    var row = element.closest(".fv-row");
                    if (row && row.length) {
                        row.append(error);
                    } else {
                        element.after(error);
                    }
                },
                highlight: function (element) {
                    global.$(element).addClass("is-invalid").removeClass("is-valid");
                },
                unhighlight: function (element) {
                    global.$(element).removeClass("is-invalid").addClass("is-valid");
                },
                submitHandler: function (form) {
                    console.log("✅ Order form validation passed");
                    self.setFormLoading(true);
                    form.submit();
                },
            });
            console.log("✅ jQuery validation initialized for order form");
        }
    };

    // Setup form submission handling (loading state is set in jQuery Validate submitHandler when valid)
    OrderModule.prototype.setupFormSubmission = function () {
        if (!this.formElement) return;
        // jQuery Validate intercepts submit; loading is set only in submitHandler when validation passes
    };

    // Set form loading state
    OrderModule.prototype.setFormLoading = function (loading) {
        const submitButton = this.formElement.querySelector(
            'button[type="submit"]'
        );

        if (loading) {
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.innerHTML =
                    '<i class="spinner-border spinner-border-sm me-2"></i>Saving...';
            }
        } else {
            if (submitButton) {
                submitButton.disabled = false;
                const isUpdate = this.formType === "update";
                submitButton.innerHTML = `<i class="ki-duotone ki-check fs-2"><span class="path1"></span><span class="path2"></span></i>${
                    isUpdate ? "Update Order" : "Create Order"
                }`;
            }
        }
    };

    // --- Delivery locations (Add more address) ---
    OrderModule.prototype.initializeDeliveryLocations = function () {
        if (!this.formElement) return;
        var self = this;
        var container = document.getElementById("delivery-locations-container");
        if (container) {
            var nextIdx = container.getAttribute("data-next-index");
            if (nextIdx !== null && nextIdx !== "") {
                this.deliveryLocationIndex = parseInt(nextIdx, 10) || 1;
            }
            // Add validation rules for restored blocks (e.g. after validation error when old() had multiple indices)
            var items = container.querySelectorAll(".delivery-location-item");
            for (var i = 0; i < items.length; i++) {
                var idx = items[i].getAttribute("data-location-index");
                if (idx !== null && parseInt(idx, 10) > 0) {
                    this.addDeliveryValidationRules(parseInt(idx, 10));
                }
            }
            // Event delegation: one listener for all Remove Location buttons (server-rendered and dynamically added)
            container.addEventListener("click", function (e) {
                var removeBtn = e.target && e.target.closest && e.target.closest(".remove-location");
                if (!removeBtn) return;
                e.preventDefault();
                var item = removeBtn.closest(".delivery-location-item");
                if (item) {
                    item.remove();
                    self.updateQuantitySummary();
                    self.updateRemoveButtons();
                }
            });
        }
        var quantityInput = document.getElementById("quantity");
        if (quantityInput) {
            quantityInput.addEventListener("input", () => {
                this.totalOrderQuantity = parseInt(quantityInput.value, 10) || 0;
                this.updateQuantitySummary();
            });
            this.totalOrderQuantity = parseInt(quantityInput.value, 10) || 0;
            this.updateQuantitySummary();
        }
        var addLocationBtn = document.getElementById("add-delivery-location");
        if (addLocationBtn) {
            addLocationBtn.addEventListener("click", () => this.addDeliveryLocation());
        }
        this.updateRemoveButtons();
    };

    OrderModule.prototype.addDeliveryLocation = function () {
        const container = document.getElementById("delivery-locations-container");
        if (!container) return;
        const index = this.deliveryLocationIndex;
        container.insertAdjacentHTML("beforeend", this.createDeliveryLocationTemplate(index));
        this.copyStateOptionsToNewLocation(index);
        this.initializeSelect2ForLocation(index);
        this.setupLocationEventsForItem(index);
        this.addDeliveryValidationRules(index);
        this.deliveryLocationIndex += 1;
        this.updateRemoveButtons();
    };

    /** Add jQuery Validate rules for a dynamically added delivery location */
    OrderModule.prototype.addDeliveryValidationRules = function (index) {
        if (!this.formElement || typeof global.$ === "undefined" || !global.$) return;
        var $form = global.$(this.formElement);
        var validator = $form.data("validator");
        if (!validator) return;
        var namePrefix = "delivery_details[" + index + "]";
        var pairs = [
            { key: "[quantity]", rules: { required: true, number: true, min: 1 }, msg: { required: "Quantity is required", number: "Enter a valid number", min: "Quantity must be at least 1" } },
            { key: "[contact_name]", rules: { required: true }, msg: { required: "Contact name is required" } },
            { key: "[contact_number]", rules: { required: true }, msg: { required: "Contact number is required" } },
            { key: "[address]", rules: { required: true }, msg: { required: "Address is required" } },
            { key: "[state_id]", rules: { required: true }, msg: { required: "Please select a state" } },
            { key: "[city]", rules: { required: true }, msg: { required: "City is required" } },
            { key: "[zipcode]", rules: { required: true }, msg: { required: "Zipcode is required" } },
        ];
        var name, $el, i;
        validator.settings.messages = validator.settings.messages || {};
        for (i = 0; i < pairs.length; i++) {
            name = namePrefix + pairs[i].key;
            $el = $form.find('[name="' + name + '"]');
            if ($el.length) {
                $el.rules("add", pairs[i].rules);
                validator.settings.messages[name] = pairs[i].msg;
            }
        }
    };

    OrderModule.prototype.createDeliveryLocationTemplate = function (index) {
        const num = index + 1;
        return `
    <div class="delivery-location-item border rounded p-5 mb-5" data-location-index="${index}">
      <div class="d-flex justify-content-between align-items-center mb-5">
        <h5 class="text-gray-800">Delivery Location #${num}</h5>
        <button type="button" class="btn btn-light-danger btn-sm remove-location">
          <i class="ki-duotone ki-trash fs-5"><span class="path1"></span><span class="path2"></span><span class="path3"></span><span class="path4"></span><span class="path5"></span></i>
          Remove Location
        </button>
      </div>
      <div class="row">
        <div class="col-md-4 fv-row mb-7">
          <label class="fs-6 fw-semibold form-label mb-2 required">Quantity</label>
          <input type="number" name="delivery_details[${index}][quantity]" class="form-control delivery-quantity" placeholder="Enter quantity" min="1">
        </div>
        <div class="col-md-4 fv-row mb-7">
          <label class="fs-6 fw-semibold form-label mb-2 required">Contact Name</label>
          <input type="text" name="delivery_details[${index}][contact_name]" class="form-control" placeholder="Enter contact name">
        </div>
        <div class="col-md-4 fv-row mb-7">
          <label class="fs-6 fw-semibold form-label mb-2 required">Contact Number</label>
          <input type="tel" name="delivery_details[${index}][contact_number]" class="form-control" placeholder="Enter contact number">
        </div>
        <div class="col-12 fv-row mb-7">
          <label class="fs-6 fw-semibold form-label mb-2 required">Address</label>
          <textarea name="delivery_details[${index}][address]" class="form-control" placeholder="Enter complete address" rows="2"></textarea>
        </div>
        <div class="col-12 fv-row mb-7">
          <label class="fs-6 fw-semibold form-label mb-2">Street</label>
          <input type="text" name="delivery_details[${index}][street]" class="form-control" placeholder="Enter street">
        </div>
        <div class="col-md-4 fv-row mb-7">
          <label class="fs-6 fw-semibold form-label mb-2 required">State</label>
          <select name="delivery_details[${index}][state_id]" class="form-select state-select" data-control="select2" data-placeholder="Select state" data-allow-clear="true">
            <option value="">Select State</option>
          </select>
        </div>
        <div class="col-md-4 fv-row mb-7">
          <label class="fs-6 fw-semibold form-label mb-2 required">City</label>
          <input type="text" name="delivery_details[${index}][city]" class="form-control" placeholder="Enter city">
        </div>
        <div class="col-md-4 fv-row mb-7">
          <label class="fs-6 fw-semibold form-label mb-2 required">Zipcode</label>
          <input type="text" name="delivery_details[${index}][zipcode]" class="form-control" placeholder="Enter zipcode">
        </div>
      </div>
    </div>`;
    };

    OrderModule.prototype.updateQuantitySummary = function () {
        const quantityInputs = document.querySelectorAll(".delivery-quantity");
        let assigned = 0;
        quantityInputs.forEach(function (input) {
            assigned += parseInt(input.value, 10) || 0;
        });
        const total = typeof this.totalOrderQuantity !== "undefined" ? this.totalOrderQuantity : 0;
        const remaining = total - assigned;
        const totalEl = document.getElementById("total-order-quantity");
        const assignedEl = document.getElementById("assigned-quantity");
        const remainingEl = document.getElementById("remaining-quantity");
        if (totalEl) totalEl.textContent = total;
        if (assignedEl) assignedEl.textContent = assigned;
        if (remainingEl) {
            remainingEl.textContent = remaining;
            remainingEl.className = remaining < 0 ? "text-danger" : (remaining === 0 ? "text-success" : "text-warning");
        }
    };

    OrderModule.prototype.updateRemoveButtons = function () {
        const locations = document.querySelectorAll(".delivery-location-item");
        locations.forEach(function (loc) {
            const removeBtn = loc.querySelector(".remove-location");
            if (removeBtn) removeBtn.style.display = locations.length > 1 ? "inline-block" : "none";
        });
    };

    OrderModule.prototype.setupLocationEventsForItem = function (index) {
        const self = this;
        const quantityInput = document.querySelector('[name="delivery_details[' + index + '][quantity]"]');
        if (quantityInput) quantityInput.addEventListener("input", function () { self.updateQuantitySummary(); });
        const removeBtn = document.querySelector('[data-location-index="' + index + '"] .remove-location');
        if (removeBtn) {
            removeBtn.addEventListener("click", function () {
                const item = document.querySelector('[data-location-index="' + index + '"]');
                if (item) {
                    item.remove();
                    self.updateQuantitySummary();
                    self.updateRemoveButtons();
                }
            });
        }
    };

    /** Copy state dropdown options from the first delivery location into a newly added one */
    OrderModule.prototype.copyStateOptionsToNewLocation = function (index) {
        const container = document.getElementById("delivery-locations-container");
        if (!container) return;
        const firstStateSelect = container.querySelector(".state-select");
        const newStateSelect = container.querySelector('[data-location-index="' + index + '"] .state-select');
        if (!firstStateSelect || !newStateSelect || firstStateSelect === newStateSelect) return;
        // Clear the placeholder-only option in the new select, then clone all options from the first
        newStateSelect.innerHTML = "";
        var i, opt;
        for (i = 0; i < firstStateSelect.options.length; i++) {
            opt = firstStateSelect.options[i];
            newStateSelect.appendChild(new Option(opt.text, opt.value));
        }
    };

    OrderModule.prototype.initializeSelect2ForLocation = function (index) {
        const stateSelect = document.querySelector('[data-location-index="' + index + '"] .state-select');
        if (stateSelect && typeof global.$ !== "undefined" && global.$ && typeof global.$.fn.select2 === "function") {
            setTimeout(function () { global.$(stateSelect).select2(); }, 100);
        }
    };

    // Create and expose global instance
    global.OrderModule = new OrderModule();

    // Auto-initialize if DOM is ready
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", function () {
            console.log("🎯 DOM loaded - OrderModule ready for initialization");
        });
    } else {
        console.log(
            "🎯 DOM already loaded - OrderModule ready for initialization"
        );
    }
})(window);
