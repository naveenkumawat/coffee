# User Form Module - Administrator Domain

## Overview
This module provides a comprehensive JavaScript class for handling user form functionality including validation, role-based field visibility, dynamic dropdowns, and form submission. It follows the same architecture pattern as the marketing tender form module.

## Features
- **Form Validation**: Complete validation using FormValidation library with fallback support
- **Role-Based Fields**: Dynamic field visibility based on user role selection
- **Country-State Dropdown**: AJAX-powered dependent dropdowns for location fields
- **Production Unit Management**: Show/hide production unit field for Production Manager role
- **Real-time Validation**: Instant feedback for user inputs with field formatting
- **Modular Design**: Clean separation of concerns and reusable code

## Architecture

### Class Structure
```javascript
class UserForm {
    constructor(config = {})    // Initialize with configuration options
    init()                      // Main initialization method
    initializeForm()           // Setup all form functionality
    initializeElements()       // Find and cache DOM elements
    initializeRoleHandling()   // Setup role-based field behavior
    initializeFormValidation() // Setup validation with FormValidation library
    initializeCountryStateDropdown() // Setup location dropdowns
    initializeRealTimeValidation()   // Setup real-time field validation
}
```

### Configuration Options
```javascript
{
    formId: "createUserForm",                    // Form element ID
    roleSelectId: "role_id",                     // Role dropdown ID
    productionUnitWrapperId: "production_unit_wrapper", // Production unit container ID
    userTypeSelectId: "user_type_id",            // Production unit select ID
    statesApiUrl: "/api/v1/locations/states",    // API endpoint for states
    csrfToken: "auto-detected"                   // CSRF token
}
```

## Usage

### Basic Initialization
```javascript
// Simple initialization with defaults
window.UserForm.quickInit();

// Custom configuration
const userForm = new UserForm({
    formId: 'createUserForm',
    roleSelectId: 'role_id',
    productionUnitWrapperId: 'production_unit_wrapper',
    userTypeSelectId: 'user_type_id',
    statesApiUrl: '/api/v1/locations/states'
});
userForm.init();
```

### In Blade Templates
```blade
@push('scripts')
<script src="{{ asset('assets/js/modules/administrator/user-form.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    window.UserForm.quickInit({
        formId: 'createUserForm',
        statesApiUrl: '/api/v1/locations/states'
    });
});
</script>
@endpush
```

## Role-Based Functionality

### Production Manager Role
When role ID = 3 (Production Manager) is selected:
- Shows production unit field wrapper
- Makes production unit selection required
- Initializes Select2 for production unit dropdown
- Updates field labels and help text

### Other Roles
For all other roles:
- Hides production unit field
- Removes production unit requirement
- Clears production unit selection

## Form Validation

### Supported Fields
- **Basic Fields**: name, email, phone_number, role_id, status
- **Address Fields**: address, city, country_id, state_id, zipcode
- **Administrator Fields**: aadhar_card, pan_number, gst_number
- **Contact Fields**: alternate_phone, alternate_email
- **Optional Fields**: website, image upload

### Validation Rules
```javascript
// Example field validation
name: {
    validators: {
        notEmpty: { message: "Full name is required" },
        stringLength: { min: 2, max: 255 },
        regexp: { regexp: /^[a-zA-Z\s\.\-']+$/ }
    }
}
```

### Real-time Validation Features
- **Phone Number Formatting**: Auto-format and limit digits
- **Uppercase Fields**: Auto-convert GST/PAN to uppercase
- **Aadhar Formatting**: Limit to 12 digits
- **Postal Code Formatting**: Limit to 6 digits
- **Email Validation**: Real-time email format checking

## Country-State Dropdown

### API Integration
```javascript
// Automatic state loading on country change
const response = await fetch(`/api/v1/locations/states/${countryId}`, {
    method: "GET",
    headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
    },
});
```

### Expected API Response
```json
{
    "success": true,
    "states": {
        "1": "Gujarat",
        "2": "Maharashtra",
        "3": "Karnataka"
    }
}
```

## Error Handling

### Validation Errors
- Inline field error messages
- Scroll to first invalid field
- SweetAlert notifications for general errors
- Bootstrap 5 styling with `.is-invalid` classes

### Network Errors
- Graceful handling of API failures
- Retry options for failed state loading
- Fallback validation when FormValidation library unavailable

## Browser Compatibility

### Required Dependencies
- **FormValidation Library** (preferred) or fallback validation
- **SweetAlert2** (optional for enhanced error messages)
- **Select2** (optional for enhanced dropdowns)
- **Bootstrap 5** (for styling)

### Fallback Support
- Works without FormValidation library
- Graceful degradation for missing dependencies
- Native browser validation as last resort

## Integration with Laravel

### Controller Integration
```php
// In your controller, just include the script
@push('scripts')
<script src="{{ asset('assets/js/modules/administrator/user-form.js') }}"></script>
@endpush
```

### Form Structure Requirements
```html
<!-- Required form structure -->
<form id="createUserForm" data-role-type="administrator">
    <select name="role_id" id="role_id" required>...</select>
    <div id="production_unit_wrapper" style="display: none;">
        <select name="user_type_id" id="user_type_id">...</select>
    </div>
    <!-- Other form fields -->
</form>
```

## API Methods

### Public Methods
```javascript
const userForm = new UserForm();

// Initialize the form
userForm.init();

// Validate the form
userForm.validate().then(result => {
    console.log('Validation result:', result);
});

// Reset the form
userForm.resetForm();

// Clean up
userForm.destroy();
```

### Event Handling
```javascript
// Role change events
roleSelect.addEventListener('change', handleRoleChange);

// Select2 compatibility
$('#role_id').on('select2:select', handleRoleChange);

// Real-time validation
field.addEventListener('blur', validateField);
field.addEventListener('input', formatField);
```

## Performance Considerations

### Optimization Features
- **Lazy Loading**: Form validation initialized only when needed
- **Debounced Events**: Real-time validation with appropriate delays
- **Memory Management**: Proper cleanup and event removal
- **Caching**: DOM element caching for better performance

### Best Practices
- Single form instance per page
- Automatic cleanup on page unload
- Minimal DOM queries through caching
- Efficient event delegation

## Debugging

### Console Logging
The module provides comprehensive console logging:
```javascript
// Initialization logs
console.log('🚀 UserForm initialized with config:', this.config);

// Element detection
console.log('🔍 Element Detection Results:');

// Validation logs
console.log('🔧 Validation fields configured:', Object.keys(fields));

// Role change logs
console.log('🔄 Role changed to:', roleId);
```

### Debug Mode
Enable detailed logging by opening browser console and checking for:
- `✅` Success messages
- `⚠️` Warning messages
- `❌` Error messages
- `🔍` Debug information

## Migration from Old System

### From user-module.js
1. Replace `window.UserModule.quickInit()` with `window.UserForm.quickInit()`
2. Update script src to new module path
3. Remove complex role handling from blade templates
4. Update configuration options

### Configuration Mapping
```javascript
// Old system
window.UserModule.quickInit();

// New system
window.UserForm.quickInit({
    formId: 'createUserForm',
    roleSelectId: 'role_id',
    productionUnitWrapperId: 'production_unit_wrapper',
    userTypeSelectId: 'user_type_id'
});
```

## Version History

### v1.0.0
- Initial release based on marketing tender form architecture
- Complete FormValidation integration
- Role-based field visibility
- Country-state dropdown functionality
- Real-time validation with field formatting
- Comprehensive error handling
- Production unit management for Production Manager role

---

## Support

For issues or questions regarding this module:
1. Check browser console for detailed error messages
2. Verify all required dependencies are loaded
3. Ensure form structure matches requirements
4. Check API endpoints are accessible and returning expected format
