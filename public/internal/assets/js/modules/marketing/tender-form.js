/**
 * Tender Form Module
 * Handles all tender form functionality including validation, file uploads, and dynamic fields
 *
 * @module TenderForm
 * @version 1.0.0
 * @author ZYLM Marketing Team
 */

class TenderForm {
    constructor(config = {}) {
        this.config = {
            formId: config.formId || "kt_tender_add_form",
            attachmentDocumentTypes: config.attachmentDocumentTypes || {},
            uploadUrl:
                config.uploadUrl || "/marketing/tenders/upload-temp-file",
            deleteAttachmentUrl:
                config.deleteAttachmentUrl ||
                "/marketing/tenders/delete-attachment/ATTACHMENT_ID",
            // States are pre-loaded in the component, no API URL needed
            csrfToken:
                config.csrfToken ||
                document
                    .querySelector('meta[name="csrf-token"]')
                    ?.getAttribute("content"),
            ...config,
        };

        this.attachmentFiles = [];
        this.contactCounter = 1;
        this.validator = null;

        // Try to find the form automatically if not specified
        if (!document.getElementById(this.config.formId)) {
            const alternativeIds = [
                "kt_tender_add_form",
                "kt_tender_edit_form",
            ];
            for (const id of alternativeIds) {
                if (document.getElementById(id)) {
                    this.config.formId = id;
                    break;
                }
            }
        }

        this.init();
    }

    /**
     * Initialize the tender form
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
        const form = document.getElementById(this.config.formId);
        if (form) {
            form.dataset.tenderFormInitialized = "true";
        }
        this.initializeToggleVisibility();
        this.initializeDropzone();
        this.initializeContacts();
        this.initializeFormValidation();
        this.initializeRealTimeValidation();
        this.initializeDateTimePickers();
        this.initExistingAttachmentHandlers();
    }

    /**
     * Initialize toggle visibility for conditional fields
     */
    initializeToggleVisibility() {
        // Toggle dealer details visibility
        const isDealerSwitch = document.getElementById("is_dealer_switch");
        const dealerDetailsContainer = document.querySelector(
            ".dealer-details-container"
        );

        if (isDealerSwitch && dealerDetailsContainer) {
            isDealerSwitch.addEventListener("change", function () {
                dealerDetailsContainer.style.display = this.checked
                    ? "flex"
                    : "none";
            });
        }

        // Toggle TReDS remarks visibility
        const isTredsSwitch = document.getElementById("is_treds_switch");
        const tredsRemarksContainer = document.querySelector(
            ".treds-remarks-container"
        );

        if (isTredsSwitch && tredsRemarksContainer) {
            isTredsSwitch.addEventListener("change", function () {
                tredsRemarksContainer.style.display = this.checked
                    ? "block"
                    : "none";
            });
        }

        // Toggle prebid meeting details visibility
        const hasPrebidMeetingSwitch = document.getElementById(
            "has_prebid_meeting_switch"
        );
        const prebidDetailsContainer = document.querySelector(
            ".prebid-details-container"
        );

        if (hasPrebidMeetingSwitch && prebidDetailsContainer) {
            hasPrebidMeetingSwitch.addEventListener("change", function () {
                prebidDetailsContainer.style.display = this.checked
                    ? "block"
                    : "none";
            });
        }
    }

    /**
     * Initialize Dropzone for file uploads
     */
    initializeDropzone() {
        if (!document.getElementById("tender_documents_dropzone")) return;

        const self = this; // Store reference to the TenderForm instance
        const dropzone = new Dropzone("#tender_documents_dropzone", {
            url: this.config.uploadUrl,
            paramName: "file",
            maxFilesize: 10, // MB
            acceptedFiles: ".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png",
            addRemoveLinks: true,
            autoProcessQueue: true,
            maxFiles: 10,
            headers: {
                "X-CSRF-TOKEN": this.config.csrfToken,
            },
            init: function () {
                // Use regular function to access 'this' (Dropzone instance)
                this.on("addedfile", (file) => self.handleFileAdded(file));
                this.on("success", (file, response) =>
                    self.handleFileSuccess(file, response)
                );
                this.on("error", (file, errorMessage) =>
                    self.handleFileError(file, errorMessage)
                );
                this.on("removedfile", (file) => self.handleFileRemoved(file));
            },
        });
    }

    /**
     * Handle file added to dropzone
     */
    handleFileAdded(file) {
        file.tempId = Date.now() + Math.random();

        // Add attachment type selector to each file
        const attachmentTypeSelect = document.createElement("select");
        attachmentTypeSelect.className = "form-select form-select-sm mt-2";

        // Generate options from server data
        let optionsHtml = "";
        Object.entries(this.config.attachmentDocumentTypes).forEach(
            ([value, label]) => {
                const selected = value === "other" ? "selected" : "";
                optionsHtml += `<option value="${value}" ${selected}>${label}</option>`;
            }
        );
        attachmentTypeSelect.innerHTML = optionsHtml;

        file.previewElement.appendChild(attachmentTypeSelect);

        // Add event listener for attachment type change
        attachmentTypeSelect.addEventListener("change", () => {
            const fileIndex = this.attachmentFiles.findIndex(
                (f) => f.tempId === file.tempId
            );
            if (fileIndex !== -1) {
                this.attachmentFiles[fileIndex].attachmentType =
                    attachmentTypeSelect.value;
                this.updateAttachmentsData();
            }
        });
    }

    /**
     * Handle successful file upload
     */
    handleFileSuccess(file, response) {
        if (response.success) {
            file.uploadResponse = response.file;
            this.attachmentFiles.push({
                tempId: file.tempId,
                fileName: response.file.name,
                filePath: response.file.path,
                fileSize: response.file.size,
                fileType: response.file.type,
                attachmentType:
                    file.previewElement.querySelector("select").value ||
                    "other",
            });
            this.updateAttachmentsData();
        }
    }

    /**
     * Handle file upload error
     */
    handleFileError(file, errorMessage) {
        console.error("File upload failed:", errorMessage);
        alert(
            "Failed to upload file: " +
                file.name +
                ". Error: " +
                (errorMessage.message || errorMessage)
        );
    }

    /**
     * Handle file removal
     */
    handleFileRemoved(file) {
        if (file.uploadResponse) {
            this.attachmentFiles = this.attachmentFiles.filter(
                (f) => f.tempId !== file.tempId
            );
            this.updateAttachmentsData();
        }
    }

    /**
     * Update hidden input with attachments data
     */
    updateAttachmentsData() {
        const attachmentsData = this.attachmentFiles.map((file) => ({
            tempId: file.tempId,
            fileName: file.fileName,
            filePath: file.filePath,
            fileSize: file.fileSize,
            fileType: file.fileType,
            attachmentType: file.attachmentType,
        }));

        const hiddenInput = document.getElementById("tender_attachments_data");
        if (hiddenInput) {
            hiddenInput.value = JSON.stringify(attachmentsData);
        }
    }

    /**
     * Initialize contact management functionality
     */
    initializeContacts() {
        this.contactCounter = parseInt(
            document.getElementById("contact_counter")?.value || 1
        );
        const addContactBtn = document.getElementById("add_contact_btn");
        const contactsContainer = document.getElementById(
            "tender_contacts_container"
        );

        if (addContactBtn && contactsContainer) {
            addContactBtn.addEventListener("click", () => {
                const newContactRow = this.createContactRow(
                    this.contactCounter
                );
                contactsContainer.appendChild(newContactRow);
                this.contactCounter++;
                this.updateContactVisibility();

                // Initialize Select2 for new row
                const newSelect = newContactRow.querySelector(
                    ".contact-type-select"
                );
                if (newSelect && window.$ && $.fn.select2) {
                    $(newSelect).select2();
                }
            });
        }

        // Initial contact visibility update
        this.updateContactVisibility();

        // Add remove functionality to existing contact rows
        document.querySelectorAll(".remove-contact-btn").forEach((btn) => {
            btn.addEventListener("click", () => {
                btn.closest(".contact-row").remove();
                this.updateContactVisibility();
            });
        });

        // Primary contact validation - only one can be primary
        document.addEventListener("change", (e) => {
            if (
                e.target.type === "checkbox" &&
                e.target.name &&
                e.target.name.includes("[is_primary]")
            ) {
                if (e.target.checked) {
                    // Uncheck all other primary checkboxes
                    document
                        .querySelectorAll(
                            'input[type="checkbox"][name*="[is_primary]"]'
                        )
                        .forEach((checkbox) => {
                            if (checkbox !== e.target) {
                                checkbox.checked = false;
                            }
                        });
                }
            }
        });
    }

    /**
     * Create new contact row
     */
    createContactRow(index) {
        const contactRow = document.createElement("div");
        contactRow.className =
            "contact-row border border-gray-300 rounded p-4 mb-4";
        contactRow.setAttribute("data-index", index);

        contactRow.innerHTML = `
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="fs-6 fw-semibold mb-2">Contact Name</label>
                    <input type="text" name="tender_contacts[${index}][name]" class="form-control" placeholder="Contact Name" />
                </div>
                <div class="col-md-3">
                    <label class="fs-6 fw-semibold mb-2">Email</label>
                    <input type="email" name="tender_contacts[${index}][email]" class="form-control" placeholder="Email address" />
                </div>
                <div class="col-md-3">
                    <label class="fs-6 fw-semibold mb-2">Phone</label>
                    <input type="tel" name="tender_contacts[${index}][phone]" class="form-control" placeholder="Phone number" />
                </div>
                <div class="col-md-2">
                    <div class="form-check form-switch form-check-custom form-check-solid">
                        <input class="form-check-input" type="checkbox" name="tender_contacts[${index}][is_primary]" value="1" id="primary_contact_${index}" />
                        <label class="form-check-label" for="primary_contact_${index}">Primary</label>
                    </div>
                </div>
                <div class="col-md-1">
                    <button type="button" class="btn btn-icon btn-light-danger remove-contact-btn">
                        <i class="ki-duotone ki-trash fs-3">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                            <span class="path4"></span>
                            <span class="path5"></span>
                        </i>
                    </button>
                </div>
            </div>
            <div class="row g-3 mt-2">
                <div class="col-md-12">
                    <label class="fs-6 fw-semibold mb-2">Additional Details</label>
                    <textarea name="tender_contacts[${index}][details]" class="form-control" rows="2" placeholder="Additional contact details"></textarea>
                </div>
            </div>
        `;

        // Add remove functionality
        const removeBtn = contactRow.querySelector(".remove-contact-btn");
        removeBtn.addEventListener("click", () => {
            contactRow.remove();
            this.updateContactVisibility();
        });

        return contactRow;
    }

    /**
     * Update contact row visibility
     */
    updateContactVisibility() {
        const contactRows = document.querySelectorAll(".contact-row");
        contactRows.forEach((row, index) => {
            const removeBtn = row.querySelector(".remove-contact-btn");
            if (removeBtn) {
                removeBtn.style.display =
                    contactRows.length > 1 ? "block" : "none";
            }
        });

        const counterInput = document.getElementById("contact_counter");
        if (counterInput) {
            counterInput.value = contactRows.length;
        }
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

        // Check if FormValidation is available, if not, use fallback validation
        if (typeof FormValidation === "undefined") {
            console.warn(
                "FormValidation library not loaded, using fallback validation"
            );
            this.initializeFallbackValidation(form);
            return;
        }

        // Remove browser validation to prevent conflicts
        form.setAttribute("novalidate", "novalidate");

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
                }),
            },
        });

        // Add custom validation for contact fields
        this.validator.on("core.element.validated", (e) => {
            if (e.valid) {
                const invalidFeedback =
                    e.element.parentElement.querySelector(".invalid-feedback");
                if (invalidFeedback) {
                    invalidFeedback.style.display = "none";
                }
            }
        });

        // Submit button handler
        const submitButton = document.getElementById("kt_tender_submit");
        if (submitButton) {
            submitButton.addEventListener("click", (e) =>
                this.handleFormSubmit(e, form)
            );
        }
    }

    /**
     * Initialize fallback validation when FormValidation library is not available
     */
    initializeFallbackValidation(form) {
        form.setAttribute("novalidate", "novalidate");

        const submitButton = document.getElementById("kt_tender_submit");
        if (submitButton) {
            submitButton.addEventListener("click", (e) => {
                e.preventDefault();

                if (this.validateFormFallback(form)) {
                    this.submitForm(form);
                } else {
                    this.showValidationErrors(form);
                }
            });
        }
    }

    /**
     * Fallback form validation
     */
    validateFormFallback(form) {
        let isValid = true;
        const requiredFields = form.querySelectorAll("[required]");

        requiredFields.forEach((field) => {
            const value = field.value.trim();
            if (!value) {
                field.classList.add("is-invalid");
                this.showFieldError(field, "This field is required");
                isValid = false;
            } else {
                field.classList.remove("is-invalid");
                this.hideFieldError(field);
            }
        });

        // Validate email fields
        const emailFields = form.querySelectorAll('input[type="email"]');
        emailFields.forEach((field) => {
            if (field.value && !this.isValidEmail(field.value)) {
                field.classList.add("is-invalid");
                this.showFieldError(
                    field,
                    "Please enter a valid email address"
                );
                isValid = false;
            }
        });

        return isValid;
    }

    /**
     * Show field error message with stable layout - handles both custom and FormValidation
     */
    showFieldError(field, message) {
        let existingError = field.parentElement.querySelector(
            ".invalid-feedback, .fv-plugins-message-container"
        );

        if (existingError) {
            existingError.textContent = message;
            existingError.classList.remove("hide");
            existingError.classList.add("show");
            existingError.style.visibility = "visible";
            existingError.style.opacity = "1";
        } else {
            // Create error container if it doesn't exist
            const errorDiv = document.createElement("div");
            errorDiv.className = "invalid-feedback show";
            errorDiv.textContent = message;
            errorDiv.style.visibility = "visible";
            errorDiv.style.opacity = "1";
            field.parentElement.appendChild(errorDiv);
        }
    }

    /**
     * Hide field error message with stable layout - handles both custom and FormValidation
     */
    hideFieldError(field) {
        const errorDiv = field.parentElement.querySelector(
            ".invalid-feedback, .fv-plugins-message-container"
        );
        if (errorDiv) {
            errorDiv.classList.remove("show");
            errorDiv.classList.add("hide");
            errorDiv.style.visibility = "hidden";
            errorDiv.style.opacity = "0";
            // Don't remove the element, just hide it to maintain layout
        }
    }

    /**
     * Get validation field configuration
     */
    getValidationFields() {
        return {
            // Basic Information (Required)
            batch_number: {
                validators: {
                    notEmpty: { message: "The batch number is required" },
                    stringLength: {
                        max: 50,
                        message:
                            "The batch number must not exceed 50 characters",
                    },
                },
            },
            document_type: {
                validators: {
                    notEmpty: { message: "Please select a document type" },
                },
            },
            tender_identity: {
                validators: {
                    notEmpty: { message: "The tender identity is required" },
                    stringLength: {
                        max: 255,
                        message:
                            "The tender identity must not exceed 255 characters",
                    },
                },
            },
            portal_type: {
                validators: {
                    notEmpty: { message: "Please select a portal type" },
                },
            },

            // Client Information (Required)
            buyer: {
                validators: {
                    notEmpty: { message: "The buyer name is required" },
                    stringLength: {
                        max: 255,
                        message:
                            "The buyer name must not exceed 255 characters",
                    },
                },
            },
            location: {
                validators: {
                    notEmpty: { message: "The location is required" },
                    stringLength: {
                        max: 255,
                        message: "The location must not exceed 255 characters",
                    },
                },
            },
            state_id: {
                validators: { notEmpty: { message: "Please select a state" } },
            },
            pin_code: {
                validators: {
                    notEmpty: { message: "The PIN code is required" },
                    stringLength: {
                        max: 20,
                        message: "The PIN code must not exceed 20 characters",
                    },
                    regexp: {
                        regexp: /^[0-9]{6}$/,
                        message: "The PIN code must be exactly 6 digits",
                    },
                },
            },

            // Tender Details (Required)
            tender_description: {
                validators: {
                    notEmpty: { message: "The tender description is required" },
                },
            },
            product_id: {
                validators: {
                    notEmpty: { message: "Please select a product" },
                },
            },
            quantity: {
                validators: {
                    notEmpty: { message: "The quantity is required" },
                    between: {
                        min: 1,
                        max: 999999,
                        message: "The quantity must be between 1 and 999,999",
                    },
                },
            },

            // Filing Information (Required)
            filing_method: {
                validators: {
                    notEmpty: { message: "Please select a filing method" },
                },
            },
            filing_date: {
                validators: {
                    notEmpty: { message: "The filing date is required" },
                    callback: {
                        message: "Please enter a valid date and time",
                        callback: this.validateDateTime.bind(this),
                    },
                },
            },
            last_date_submission: {
                validators: {
                    notEmpty: {
                        message: "The last date of submission is required",
                    },
                    callback: {
                        message:
                            "Please enter a valid date and time after the filing date",
                        callback: this.validateSubmissionDate.bind(this),
                    },
                },
            },

            // Payment & Banking Information (Required fields only)
            tender_value: {
                validators: {
                    notEmpty: { message: "The tender value is required" },
                    callback: {
                        message: "Please enter a valid amount greater than 0",
                        callback: this.validateMonetaryAmount.bind(this),
                    },
                },
            },
            bid_value: {
                validators: {
                    notEmpty: { message: "The bid value is required" },
                    callback: {
                        message: "Please enter a valid amount greater than 0",
                        callback: this.validateMonetaryAmount.bind(this),
                    },
                },
            },
            payment_timeline: {
                validators: {
                    notEmpty: { message: "The payment timeline is required" },
                    stringLength: {
                        max: 255,
                        message:
                            "The payment timeline must not exceed 255 characters",
                    },
                },
            },

            // Status Information (Required)
            status: {
                validators: { notEmpty: { message: "Please select a status" } },
            },

            // Optional fields validation
            quotation_value: {
                validators: {
                    callback: {
                        message:
                            "Please enter a valid monetary amount (e.g., 1000.50)",
                        callback:
                            this.validateOptionalMonetaryAmount.bind(this),
                    },
                },
            },
            earnest_money_deposit: {
                validators: {
                    callback: {
                        message:
                            "Please enter a valid monetary amount (e.g., 1000.50)",
                        callback:
                            this.validateOptionalMonetaryAmount.bind(this),
                    },
                },
            },
            performance_bank_guarantee: {
                validators: {
                    callback: {
                        message:
                            "Please enter a valid percentage between 0 and 100",
                        callback: this.validatePercentage.bind(this),
                    },
                },
            },
            tender_link: {
                validators: {
                    callback: {
                        message: "Please enter a valid URL",
                        callback: this.validateOptionalURL.bind(this),
                    },
                },
            },
            prebid_meeting_datetime: {
                validators: {
                    callback: {
                        message: "Please enter a valid date and time",
                        callback: this.validateOptionalDateTime.bind(this),
                    },
                },
            },
        };
    }

    /**
     * Validation helper methods
     */
    validateDateTime(value) {
        if (!value || value.trim() === "") return false;
        const dateTimeRegex = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/;
        if (!dateTimeRegex.test(value)) return false;
        return !isNaN(new Date(value).getTime());
    }

    validateOptionalDateTime(value) {
        if (!value || value.trim() === "") return true;
        return this.validateDateTime(value);
    }

    validateSubmissionDate(value, validator) {
        if (!this.validateDateTime(value)) return false;
        const filingDate = validator
            .getForm()
            .querySelector('input[name="filing_date"]').value;
        if (filingDate && value) {
            return new Date(value) > new Date(filingDate);
        }
        return true;
    }

    validateMonetaryAmount(value) {
        if (!value || value.trim() === "") return false;
        const cleanValue = value.toString().trim();
        const numValue = parseFloat(cleanValue);
        if (isNaN(numValue) || numValue <= 0) return false;
        return /^\d+(\.\d{1,2})?$/.test(cleanValue);
    }

    validateOptionalMonetaryAmount(value) {
        if (!value || value.trim() === "") return true;
        const regex = /^\d+(\.\d{1,2})?$/;
        if (!regex.test(value)) return false;
        const numValue = parseFloat(value);
        return !isNaN(numValue) && numValue >= 0;
    }

    validatePercentage(value) {
        if (!value || value.trim() === "") return true;
        const numValue = parseFloat(value);
        return !isNaN(numValue) && numValue >= 0 && numValue <= 100;
    }

    validateOptionalInteger(value) {
        if (!value || value.trim() === "") return true;
        const numValue = parseInt(value);
        return !isNaN(numValue) && numValue > 0 && Number.isInteger(numValue);
    }

    validateOptionalURL(value) {
        if (!value || value.trim() === "") return true;
        try {
            new URL(value);
            return true;
        } catch (e) {
            return false;
        }
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
                        // Additional contact validation
                        const contactValidation = this.validateContacts();
                        if (contactValidation.valid) {
                            this.submitForm(form);
                        } else {
                            this.showContactErrors(contactValidation.errors);
                        }
                    } else {
                        console.warn("Form validation failed:", result);
                        this.showValidationErrors(form);
                    }
                })
                .catch((error) => {
                    console.error("Validation error:", error);
                    this.handleValidationError(error);
                    // Even on error, show validation errors and allow user to see what's wrong
                    this.showValidationErrors(form);
                });
        } else {
            // Fallback validation
            if (this.validateFormFallback(form)) {
                const contactValidation = this.validateContacts();
                if (contactValidation.valid) {
                    this.submitForm(form);
                } else {
                    this.showContactErrors(contactValidation.errors);
                }
            } else {
                console.warn("Fallback validation failed");
                this.showValidationErrors(form);
            }
        }
    }

    /**
     * Validate contacts
     */
    validateContacts() {
        const contactRows = document.querySelectorAll(".contact-row");
        let contactsValid = true;
        let hasValidContact = false;
        let primaryCount = 0;
        let contactErrors = [];

        if (contactRows.length === 0) {
            contactsValid = false;
            contactErrors.push("At least one contact is required");
        }

        contactRows.forEach((row, index) => {
            const nameInput = row.querySelector('input[name*="[name]"]');
            const emailInput = row.querySelector('input[name*="[email]"]');
            const phoneInput = row.querySelector('input[name*="[phone]"]');
            const primaryInput = row.querySelector(
                'input[name*="[is_primary]"]'
            );

            if (emailInput.value || phoneInput.value) {
                hasValidContact = true;

                if (!nameInput.value.trim()) {
                    contactsValid = false;
                    contactErrors.push(
                        `Contact ${
                            index + 1
                        }: Name is required when email or phone is provided`
                    );
                    nameInput.classList.add("is-invalid");
                } else {
                    nameInput.classList.remove("is-invalid");
                }

                if (emailInput.value && !this.isValidEmail(emailInput.value)) {
                    contactsValid = false;
                    contactErrors.push(
                        `Contact ${
                            index + 1
                        }: Please enter a valid email address`
                    );
                    emailInput.classList.add("is-invalid");
                } else {
                    emailInput.classList.remove("is-invalid");
                }

                if (phoneInput.value && !this.isValidPhone(phoneInput.value)) {
                    contactsValid = false;
                    contactErrors.push(
                        `Contact ${
                            index + 1
                        }: Please enter a valid phone number`
                    );
                    phoneInput.classList.add("is-invalid");
                } else {
                    phoneInput.classList.remove("is-invalid");
                }
            }

            if (primaryInput && primaryInput.checked) {
                primaryCount++;
            }
        });

        if (!hasValidContact) {
            contactsValid = false;
            contactErrors.push(
                "At least one contact must have either email or phone number"
            );
        }

        if (primaryCount > 1) {
            contactsValid = false;
            contactErrors.push("Only one contact can be marked as primary");
        }

        return { valid: contactsValid, errors: contactErrors };
    }

    /**
     * Show contact validation errors inline
     */
    showContactErrors(errors) {
        // Create or update error display for contacts section
        const contactsContainer = document.getElementById(
            "tender_contacts_container"
        );
        if (contactsContainer) {
            // Remove any existing error display
            const existingError = contactsContainer.querySelector(
                ".contacts-error-message"
            );
            if (existingError) {
                existingError.remove();
            }

            // Create new error display
            const errorDiv = document.createElement("div");
            errorDiv.className =
                "contacts-error-message alert alert-danger mt-3";
            errorDiv.innerHTML = `
                <strong>Contact Information Errors:</strong><br>
                ${errors.map((error) => `• ${error}`).join("<br>")}
            `;

            // Insert at the top of contacts container
            contactsContainer.insertBefore(
                errorDiv,
                contactsContainer.firstChild
            );

            // Scroll to the error
            errorDiv.scrollIntoView({
                behavior: "smooth",
                block: "center",
            });
        }
    }

    /**
     * Enhanced validation error display - Show errors inline without SweetAlert
     */
    showValidationErrors(form) {
        const invalidFields = form.querySelectorAll(".is-invalid");

        // Always check for required fields that are empty
        const requiredFields = form.querySelectorAll("[required]");

        requiredFields.forEach((field) => {
            if (!field.value.trim()) {
                field.classList.add("is-invalid");
                this.showFieldError(field, "This field is required");
            }
        });

        // Focus on first invalid field and scroll to it
        const firstInvalidField = form.querySelector(".is-invalid");
        if (firstInvalidField) {
            firstInvalidField.focus();
            firstInvalidField.scrollIntoView({
                behavior: "smooth",
                block: "center",
            });
        }
    }

    /**
     * Get field label for display in error messages
     */
    getFieldLabel(field) {
        // Try to find label by for attribute
        const label = document.querySelector(`label[for="${field.id}"]`);
        if (label) {
            return label.textContent.trim();
        }

        // Try to find label in same fv-row
        const fvRow = field.closest(".fv-row");
        if (fvRow) {
            const rowLabel = fvRow.querySelector("label");
            if (rowLabel) {
                return rowLabel.textContent.trim();
            }
        }

        // Fallback to field name or placeholder
        return (
            field.getAttribute("placeholder") || field.name || "Unknown field"
        );
    }

    /**
     * Handle validation error
     */
    handleValidationError(error) {
        console.error("Form validation error:", error);
        Swal.fire({
            title: "Error",
            text: "An error occurred during form validation. Please try again.",
            icon: "error",
            confirmButtonText: "Ok, got it!",
            customClass: { confirmButton: "btn btn-primary" },
        });

        const submitButton = document.getElementById("kt_tender_submit");
        if (submitButton) {
            submitButton.removeAttribute("data-kt-indicator");
            submitButton.disabled = false;
        }
    }

    /**
     * Submit the form
     */
    submitForm(form) {
        const submitButton = document.getElementById("kt_tender_submit");
        if (submitButton) {
            submitButton.setAttribute("data-kt-indicator", "on");
            submitButton.disabled = true;
        }
        form.submit();
    }


    /**
     * Clear states dropdown
     */
    clearStates() {
        const stateSelect = document.getElementById("state_select");
        stateSelect.innerHTML = '<option value="">Select State</option>';
        stateSelect.disabled = false;

        if (window.$ && $(stateSelect).hasClass("select2-hidden-accessible")) {
            $(stateSelect).val("").trigger("change");
        }
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

        // Real-time validation for numeric fields
        const numericFields = form.querySelectorAll('input[type="number"]');
        numericFields.forEach((field) => {
            field.addEventListener("input", (e) => {
                const value = parseFloat(e.target.value);
                const min = parseFloat(e.target.getAttribute("min")) || 0;
                const max = parseFloat(e.target.getAttribute("max"));

                if (isNaN(value) || value < min || (max && value > max)) {
                    e.target.classList.add("is-invalid");
                    this.showFieldError(
                        e.target,
                        "Please enter a valid number"
                    );
                } else {
                    e.target.classList.remove("is-invalid");
                    this.hideFieldError(e.target);
                }
            });
        });
    }

    /**
     * Initialize date-time pickers for filing and submission dates
     */
    initializeDateTimePickers() {
        const dateTimeFields = [
            "filing_date",
            "last_date_submission",
            "opening_date",
            "prebid_meeting_date",
        ];

        dateTimeFields.forEach((fieldName) => {
            const field = document.querySelector(`input[name="${fieldName}"]`);
            if (field && field.type === "datetime-local") {
                this.enhanceDateTimeField(field);
            }
        });
    }

    /**
     * Enhance datetime-local field with better UX
     */
    enhanceDateTimeField(field) {
        // Filing Date and Last Date of Submission allow past dates (no min restriction)

        // Add change event for validation
        field.addEventListener("change", () => {
            if (field.value) {
                field.classList.remove("is-invalid");

                // Cross-validate filing vs submission dates
                if (field.name === "filing_date") {
                    this.validateDateSequence();
                } else if (field.name === "last_date_submission") {
                    this.validateDateSequence();
                }
            }
        });

        // Add focus/blur events for better UX
        field.addEventListener("focus", () => {
            field.classList.add("datetime-focused");
        });

        field.addEventListener("blur", () => {
            field.classList.remove("datetime-focused");
            if (!field.value.trim()) {
                field.classList.add("is-invalid");
                this.showFieldError(field, "This date and time is required");
            }
        });
    }

    /**
     * Validate date sequence (filing date should be before submission date)
     */
    validateDateSequence() {
        const filingDateField = document.querySelector(
            'input[name="filing_date"]'
        );
        const submissionDateField = document.querySelector(
            'input[name="last_date_submission"]'
        );

        if (
            filingDateField &&
            submissionDateField &&
            filingDateField.value &&
            submissionDateField.value
        ) {
            const filingDate = new Date(filingDateField.value);
            const submissionDate = new Date(submissionDateField.value);

            if (filingDate >= submissionDate) {
                submissionDateField.classList.add("is-invalid");
                this.showFieldError(
                    submissionDateField,
                    "Submission date must be after filing date"
                );
                return false;
            } else {
                submissionDateField.classList.remove("is-invalid");
                this.hideFieldError(submissionDateField);
                return true;
            }
        }

        return true;
    }

    /**
     * Initialize existing attachment removal functionality
     */
    initExistingAttachmentHandlers() {
        const removeButtons = document.querySelectorAll(
            ".remove-attachment-btn"
        );

        removeButtons.forEach((button) => {
            button.addEventListener("click", (e) => {
                e.preventDefault();
                const attachmentId = button.getAttribute("data-attachment-id");
                const row = button.closest("tr");

                this.removeExistingAttachment(attachmentId, row);
            });
        });
    }

    /**
     * Remove an existing attachment
     */
    removeExistingAttachment(attachmentId, row) {
        if (!attachmentId || !row) {
            console.error("Invalid attachment ID or row element");
            return;
        }

        // Check if SweetAlert is available, otherwise use native confirm
        if (typeof Swal !== "undefined") {
            // Show SweetAlert confirmation dialog
            Swal.fire({
                title: "Remove Document?",
                text: "Are you sure you want to remove this document? This action cannot be undone.",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#d33",
                cancelButtonColor: "#3085d6",
                confirmButtonText: "Yes, remove it!",
                cancelButtonText: "Cancel",
            }).then((result) => {
                if (result.isConfirmed) {
                    this.performAttachmentRemoval(attachmentId, row);
                }
            });
        } else {
            // Fallback to native confirm if SweetAlert is not available
            if (
                confirm(
                    "Are you sure you want to remove this document? This action cannot be undone."
                )
            ) {
                this.performAttachmentRemoval(attachmentId, row);
            }
        }
    }

    /**
     * Perform the actual attachment removal
     */
    performAttachmentRemoval(attachmentId, row) {
        // Show loading state
        const button = row.querySelector(".remove-attachment-btn");
        const originalHtml = button.innerHTML;
        button.innerHTML =
            '<span class="spinner-border spinner-border-sm" role="status"></span>';
        button.disabled = true;

        // Make AJAX request to delete the attachment
        const deleteUrl = this.config.deleteAttachmentUrl.replace(
            "ATTACHMENT_ID",
            attachmentId
        );
        fetch(deleteUrl, {
            method: "DELETE",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": this.config.csrfToken,
                Accept: "application/json",
            },
        })
            .then((response) => response.json())
            .then((data) => {
                if (data.success) {
                    // Remove the row with animation
                    row.style.transition = "opacity 0.3s ease";
                    row.style.opacity = "0";

                    setTimeout(() => {
                        row.remove();

                        // Check if there are any remaining attachments
                        const remainingRows = document.querySelectorAll(
                            "#existing_files_list tr"
                        );
                        if (remainingRows.length === 0) {
                            const existingAttachmentsSection =
                                document.getElementById("existing_attachments");
                            if (existingAttachmentsSection) {
                                existingAttachmentsSection.style.display =
                                    "none";
                            }
                        }
                    }, 300);

                    // Show success message
                    if (typeof Swal !== "undefined") {
                        Swal.fire({
                            title: "Removed!",
                            text: "The document has been successfully removed.",
                            icon: "success",
                            timer: 2000,
                            showConfirmButton: false,
                        });
                    } else {
                        alert("Document has been successfully removed.");
                    }
                } else {
                    throw new Error(
                        data.message || "Failed to remove attachment"
                    );
                }
            })
            .catch((error) => {
                console.error("Error removing attachment:", error);

                // Restore button state
                button.innerHTML = originalHtml;
                button.disabled = false;

                // Show error message
                if (typeof Swal !== "undefined") {
                    Swal.fire({
                        title: "Error!",
                        text: "Failed to remove the document. Please try again.",
                        icon: "error",
                        confirmButtonText: "OK",
                    });
                } else {
                    alert("Failed to remove the document. Please try again.");
                }
            });
    }

    /**
     * Utility methods
     */
    isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    isValidPhone(phone) {
        const phoneRegex = /^[\+]?[1-9][\d]{0,15}$/;
        return phoneRegex.test(phone.replace(/[\s\-\(\)]/g, ""));
    }
}

// Export for use in other modules or global access
window.TenderForm = TenderForm;
