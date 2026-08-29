"use strict";

/**
 * Tender Module - Client-side functionality for Tender management
 *
 * Features:
 * - AJAX-based data loading and manipulation
 * - Real-time validation
 * - Dynamic UI interactions
 * - Form submission and error handling
 * - Advanced filtering and sorting
 *
 * @module TenderModule
 * @requires KTApp, jQuery, SweetAlert2
 */
var TenderModule = (function () {
    // Private variables
    var _table = document.querySelector("#kt_tender_table");
    var _filterForm = document.querySelector(
        '[data-kt-tender-table-filter="form"]'
    );
    var _filterButton = document.querySelector(
        '[data-kt-tender-table-filter="filter"]'
    );
    var _resetButton = document.querySelector(
        '[data-kt-tender-table-filter="reset"]'
    );
    var _deleteButtons = document.querySelectorAll(
        '[data-kt-tender-table-filter="delete_row"]'
    );
    var _statusToggleButtons = document.querySelectorAll(
        ".tender-status-toggle"
    );
    var _tenderForm = document.querySelector("#tender_form");
    var _submitButton = document.querySelector("#kt_tender_submit");
    var _cancelButton = document.querySelector("#kt_tender_cancel");
    var _validator;

    // Module initialization
    var init = function () {
        console.log("🎯 Initializing Tender Module");

        // Setup event listeners
        setupEventListeners();

        // Initialize validators
        initValidation();

        // Initialize components
        initComponents();
    };

    // Quick initialization for index pages
    var quickInit = function () {
        console.log("🎯 Quick initializing Tender Module for list view");

        // Setup minimal event listeners for index page
        if (_table) {
            _table
                .querySelectorAll('[data-kt-tender-table-filter="delete_row"]')
                .forEach((button) => {
                    button.addEventListener("click", function (e) {
                        e.preventDefault();
                        handleDeleteRow(button);
                    });
                });
        }

        // Setup status toggle buttons
        if (_statusToggleButtons) {
            _statusToggleButtons.forEach((button) => {
                button.addEventListener("click", function (e) {
                    e.preventDefault();
                    handleStatusToggle(button);
                });
            });
        }

        // Initialize datatable if needed
        if (typeof $.fn.DataTable !== "undefined" && _table) {
            initDatatable();
        }
    };

    // Setup all required event listeners
    var setupEventListeners = function () {
        // Form submission
        if (_tenderForm) {
            _submitButton?.addEventListener("click", function (e) {
                e.preventDefault();

                if (_validator) {
                    _validator.validate().then(function (status) {
                        if (status === "Valid") {
                            submitForm();
                        } else {
                            Swal.fire({
                                text: "Sorry, looks like there are some validation errors detected. Please try again.",
                                icon: "error",
                                buttonsStyling: false,
                                confirmButtonText: "Ok, got it!",
                                customClass: {
                                    confirmButton: "btn btn-primary",
                                },
                            });
                        }
                    });
                } else {
                    submitForm();
                }
            });
        }

        // Cancel button
        if (_cancelButton) {
            _cancelButton.addEventListener("click", function (e) {
                e.preventDefault();

                Swal.fire({
                    text: "Are you sure you want to cancel?",
                    icon: "warning",
                    showCancelButton: true,
                    buttonsStyling: false,
                    confirmButtonText: "Yes, cancel it!",
                    cancelButtonText: "No, return",
                    customClass: {
                        confirmButton: "btn btn-primary",
                        cancelButton: "btn btn-active-light",
                    },
                }).then(function (result) {
                    if (result.isConfirmed) {
                        window.location = document
                            .querySelector("form")
                            .getAttribute("data-kt-redirect");
                    }
                });
            });
        }

        // Filter buttons
        if (_filterButton) {
            _filterButton.addEventListener("click", function () {
                applyFilter();
            });
        }

        if (_resetButton) {
            _resetButton.addEventListener("click", function () {
                resetFilter();
            });
        }
    };

    // Initialize form validation
    var initValidation = function () {
        if (_tenderForm) {
            // Initialize form validation rules. For more info check the FormValidation plugin's official documentation: https://formvalidation.io/
            _validator = FormValidation.formValidation(_tenderForm, {
                fields: {
                    tender_number: {
                        validators: {
                            notEmpty: {
                                message: "Tender number is required",
                            },
                        },
                    },
                    tender_title: {
                        validators: {
                            notEmpty: {
                                message: "Tender title is required",
                            },
                        },
                    },
                    client_name: {
                        validators: {
                            notEmpty: {
                                message: "Client name is required",
                            },
                        },
                    },
                    document_type: {
                        validators: {
                            notEmpty: {
                                message: "Document type is required",
                            },
                        },
                    },
                    announcement_date: {
                        validators: {
                            notEmpty: {
                                message: "Announcement date is required",
                            },
                        },
                    },
                    submission_date: {
                        validators: {
                            notEmpty: {
                                message: "Submission date is required",
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
        }
    };

    // Initialize components
    var initComponents = function () {
        // Initialize date pickers
        const dateInputs = document.querySelectorAll(".tender-date-picker");
        if (dateInputs) {
            dateInputs.forEach((input) => {
                $(input).flatpickr({
                    altInput: true,
                    altFormat: "F j, Y",
                    dateFormat: "Y-m-d",
                    allowInput: true,
                });
            });
        }

        // Initialize select2 dropdowns
        const selectInputs = document.querySelectorAll(".tender-select");
        if (selectInputs) {
            selectInputs.forEach((select) => {
                $(select).select2({
                    placeholder: "Select an option",
                    minimumResultsForSearch: 10,
                });
            });
        }
    };

    // Initialize datatable
    var initDatatable = function () {
        const datatable = $(_table).DataTable({
            info: false,
            order: [],
            columnDefs: [
                { orderable: false, targets: 0 }, // Disable ordering on the first column (checkbox)
                { orderable: false, targets: -1 }, // Disable ordering on the last column (actions)
            ],
            pageLength: 10,
            lengthChange: false,
            searching: false,
        });
    };

    // Handle form submission
    var submitForm = function () {
        // Show loading indication
        _submitButton.setAttribute("data-kt-indicator", "on");
        _submitButton.disabled = true;

        // Submit the form
        _tenderForm.submit();
    };

    // Handle delete row action
    var handleDeleteRow = function (button) {
        // Select parent row
        const row = button.closest("tr");
        const tenderId = button.getAttribute("data-tender-id");
        const tenderName = button.getAttribute("data-tender-name");

        // SweetAlert2 confirmation
        Swal.fire({
            text: `Are you sure you want to delete ${tenderName}?`,
            icon: "warning",
            showCancelButton: true,
            buttonsStyling: false,
            confirmButtonText: "Yes, delete!",
            cancelButtonText: "No, cancel",
            customClass: {
                confirmButton: "btn fw-bold btn-danger",
                cancelButton: "btn fw-bold btn-active-light-primary",
            },
        }).then(function (result) {
            if (result.isConfirmed) {
                // Send AJAX request to delete the record
                fetch(`/marketing/tenders/${tenderId}`, {
                    method: "DELETE",
                    headers: {
                        "X-CSRF-TOKEN": document
                            .querySelector('meta[name="csrf-token"]')
                            .getAttribute("content"),
                        "Content-Type": "application/json",
                        Accept: "application/json",
                    },
                })
                    .then((response) => response.json())
                    .then((data) => {
                        if (data.success) {
                            Swal.fire({
                                text: "You have deleted " + tenderName + "!",
                                icon: "success",
                                buttonsStyling: false,
                                confirmButtonText: "Ok, got it!",
                                customClass: {
                                    confirmButton: "btn fw-bold btn-primary",
                                },
                            }).then(function () {
                                // Remove row from the table
                                if (typeof $.fn.DataTable !== "undefined") {
                                    $(row).fadeOut(400, function () {
                                        $(_table)
                                            .DataTable()
                                            .row($(this))
                                            .remove()
                                            .draw();
                                    });
                                } else {
                                    row.remove();
                                }
                            });
                        } else {
                            Swal.fire({
                                text: data.message || "Delete failed!",
                                icon: "error",
                                buttonsStyling: false,
                                confirmButtonText: "Ok, got it!",
                                customClass: {
                                    confirmButton: "btn fw-bold btn-primary",
                                },
                            });
                        }
                    })
                    .catch((error) => {
                        console.error("Error:", error);
                        Swal.fire({
                            text: "Delete failed!",
                            icon: "error",
                            buttonsStyling: false,
                            confirmButtonText: "Ok, got it!",
                            customClass: {
                                confirmButton: "btn fw-bold btn-primary",
                            },
                        });
                    });
            }
        });
    };

    // Handle status toggle action
    var handleStatusToggle = function (button) {
        const tenderId = button.getAttribute("data-tender-id");
        const currentStatus = button.getAttribute("data-tender-status");
        const newStatus = currentStatus === "1" ? "0" : "1";
        const statusText = newStatus === "1" ? "activate" : "deactivate";

        // SweetAlert2 confirmation
        Swal.fire({
            text: `Are you sure you want to ${statusText} this tender?`,
            icon: "warning",
            showCancelButton: true,
            buttonsStyling: false,
            confirmButtonText: `Yes, ${statusText}!`,
            cancelButtonText: "No, cancel",
            customClass: {
                confirmButton: "btn fw-bold btn-primary",
                cancelButton: "btn fw-bold btn-active-light-primary",
            },
        }).then(function (result) {
            if (result.isConfirmed) {
                // Send AJAX request to update status
                fetch(`/marketing/tenders/${tenderId}/toggle-status`, {
                    method: "POST",
                    headers: {
                        "X-CSRF-TOKEN": document
                            .querySelector('meta[name="csrf-token"]')
                            .getAttribute("content"),
                        "Content-Type": "application/json",
                        Accept: "application/json",
                    },
                    body: JSON.stringify({ status: newStatus }),
                })
                    .then((response) => response.json())
                    .then((data) => {
                        if (data.success) {
                            Swal.fire({
                                text: `Tender has been ${
                                    newStatus === "1"
                                        ? "activated"
                                        : "deactivated"
                                }!`,
                                icon: "success",
                                buttonsStyling: false,
                                confirmButtonText: "Ok, got it!",
                                customClass: {
                                    confirmButton: "btn fw-bold btn-primary",
                                },
                            }).then(function () {
                                // Update button state
                                button.setAttribute(
                                    "data-tender-status",
                                    newStatus
                                );
                                const statusBadge = button
                                    .closest("tr")
                                    .querySelector(".tender-status-badge");

                                if (statusBadge) {
                                    if (newStatus === "1") {
                                        statusBadge.classList.remove(
                                            "badge-light-danger"
                                        );
                                        statusBadge.classList.add(
                                            "badge-light-success"
                                        );
                                        statusBadge.textContent = "Active";
                                        button.classList.remove("btn-success");
                                        button.classList.add("btn-danger");
                                        button
                                            .querySelector("i")
                                            .classList.remove("ki-check");
                                        button
                                            .querySelector("i")
                                            .classList.add("ki-cross");
                                    } else {
                                        statusBadge.classList.remove(
                                            "badge-light-success"
                                        );
                                        statusBadge.classList.add(
                                            "badge-light-danger"
                                        );
                                        statusBadge.textContent = "Inactive";
                                        button.classList.remove("btn-danger");
                                        button.classList.add("btn-success");
                                        button
                                            .querySelector("i")
                                            .classList.remove("ki-cross");
                                        button
                                            .querySelector("i")
                                            .classList.add("ki-check");
                                    }
                                }
                            });
                        } else {
                            Swal.fire({
                                text: data.message || "Status update failed!",
                                icon: "error",
                                buttonsStyling: false,
                                confirmButtonText: "Ok, got it!",
                                customClass: {
                                    confirmButton: "btn fw-bold btn-primary",
                                },
                            });
                        }
                    })
                    .catch((error) => {
                        console.error("Error:", error);
                        Swal.fire({
                            text: "Status update failed!",
                            icon: "error",
                            buttonsStyling: false,
                            confirmButtonText: "Ok, got it!",
                            customClass: {
                                confirmButton: "btn fw-bold btn-primary",
                            },
                        });
                    });
            }
        });
    };

    // Apply filter
    var applyFilter = function () {
        if (_filterForm) {
            // Get filter values
            const tenderNumber =
                document.querySelector('[name="tender_number"]')?.value || "";
            const tenderTitle =
                document.querySelector('[name="tender_title"]')?.value || "";
            const status =
                document.querySelector('[name="status"]')?.value || "";

            // Build query string
            let queryParams = [];
            if (tenderNumber)
                queryParams.push(
                    `tender_number=${encodeURIComponent(tenderNumber)}`
                );
            if (tenderTitle)
                queryParams.push(
                    `tender_title=${encodeURIComponent(tenderTitle)}`
                );
            if (status)
                queryParams.push(`status=${encodeURIComponent(status)}`);

            // Redirect with filters
            window.location.href = `${
                window.location.pathname
            }?${queryParams.join("&")}`;
        }
    };

    // Reset filter
    var resetFilter = function () {
        if (_filterForm) {
            // Reset all form inputs
            _filterForm.reset();

            // Reset select2 dropdowns if they exist
            const selects = _filterForm.querySelectorAll("select");
            if (selects) {
                selects.forEach((select) => {
                    if ($(select).data("select2")) {
                        $(select).val("").trigger("change");
                    }
                });
            }

            // Apply filter with reset values
            window.location.href = window.location.pathname;
        }
    };

    // Public methods
    return {
        init: function () {
            init();
        },

        quickInit: function () {
            quickInit();
        },
    };
})();

// On document ready
if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () {
        TenderModule.init();
    });
} else {
    TenderModule.init();
}
