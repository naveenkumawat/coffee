# Tender Form JavaScript Module

## Overview
This module provides a comprehensive JavaScript class for handling tender form functionality including validation, file uploads, dynamic fields, and AJAX operations.

## Features
- **Form Validation**: Complete validation using FormValidation library
- **File Upload**: Dropzone integration for document attachments
- **Dynamic Fields**: Contact management with add/remove functionality
- **Country-State Dropdown**: AJAX-powered dependent dropdowns
- **Real-time Validation**: Instant feedback for user inputs
- **Modular Design**: Clean separation of concerns and reusable code

## Installation

### 1. Include the Module
```html
<script src="{{ asset('assets/js/modules/marketing/tender-form.js') }}"></script>
```

### 2. Initialize the Form
```javascript
document.addEventListener('DOMContentLoaded', function() {
    const tenderForm = new TenderForm({
        formId: 'kt_tender_edit_form',
        attachmentDocumentTypes: {
            'tender_document': 'Tender Document',
            'technical_specification': 'Technical Specification',
            // ... other types
        },
        uploadUrl: '/marketing/tenders/upload-temp-file',
        statesApiUrl: '/api/v1/locations/states',
        csrfToken: 'your-csrf-token'
    });
});
```

## Configuration Options

| Option | Type | Required | Description |
|--------|------|----------|-------------|
| `formId` | string | No | Form ID (default: 'kt_tender_edit_form') |
| `attachmentDocumentTypes` | object | No | Document type options for file uploads |
| `uploadUrl` | string | No | URL for temporary file uploads |
| `statesApiUrl` | string | No | API URL for loading states by country |
| `csrfToken` | string | No | CSRF token for AJAX requests |

## Dependencies

### Required Libraries
- **FormValidation**: Form validation library
- **Dropzone**: File upload library
- **SweetAlert2**: Alert dialogs
- **jQuery**: For Select2 integration
- **Select2**: Enhanced dropdowns

### HTML Structure Requirements
- Form with ID specified in configuration
- Required form elements with proper naming
- Dropzone container with ID `tender_documents_dropzone`
- Contact container with ID `tender_contacts_container`
- Country/State dropdowns with IDs `country_select` and `state_select`

## Methods

### Public Methods
```javascript
// Initialize the form (called automatically)
tenderForm.init()

// Initialize specific functionality
tenderForm.initializeToggleVisibility()
tenderForm.initializeDropzone()
tenderForm.initializeContacts()
tenderForm.initializeFormValidation()
tenderForm.initializeCountryStateDropdown()
tenderForm.initializeRealTimeValidation()
```

### Validation Helpers
```javascript
// Email validation
tenderForm.isValidEmail('user@example.com')

// Phone validation
tenderForm.isValidPhone('+1234567890')

// Custom validators for monetary amounts, percentages, etc.
```

## Event Handling

The module automatically handles:
- Form submission with validation
- File uploads with progress tracking
- Dynamic contact field management
- Country-state dependency updates
- Real-time field validation
- Toggle visibility for conditional fields

## Error Handling

- **File Upload Errors**: Displayed via browser alerts
- **Validation Errors**: Shown using SweetAlert2 with field highlighting
- **AJAX Errors**: Graceful fallback to hardcoded data
- **Form Submission Errors**: Loading state management and error recovery

## Customization

### Extending Validation
```javascript
// Add custom validation rules in getValidationFields()
custom_field: {
    validators: {
        callback: {
            message: 'Custom validation message',
            callback: function(value, validator, field) {
                // Custom validation logic
                return true; // or false
            }
        }
    }
}
```

### Adding New Features
```javascript
class ExtendedTenderForm extends TenderForm {
    constructor(config) {
        super(config);
        this.initializeCustomFeatures();
    }

    initializeCustomFeatures() {
        // Add your custom functionality
    }
}
```

## Browser Compatibility
- Modern browsers supporting ES6+ features
- IE11+ (with polyfills for modern JavaScript features)

## Performance Considerations
- Lazy loading of Select2 for dynamic fields
- Efficient event delegation
- Minimal DOM queries with caching
- Optimized validation rules

## Troubleshooting

### Common Issues
1. **FormValidation not found**: Ensure FormValidation library is loaded before the module
2. **Dropzone not working**: Check if Dropzone CSS/JS are properly included
3. **AJAX requests failing**: Verify CSRF token and API endpoints
4. **Select2 not initializing**: Confirm jQuery and Select2 are loaded

### Debug Mode
Enable console logging by adding debug configuration:
```javascript
const tenderForm = new TenderForm({
    // ... other config
    debug: true
});
```

## Version History
- v1.0.0: Initial release with complete tender form functionality

## Support
For issues or feature requests, please contact the ZYLM development team.
