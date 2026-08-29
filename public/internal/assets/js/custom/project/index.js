/**
 * ZYLM JavaScript Architecture Index
 *
 * Centralized inclusion file for all JavaScript features and modules
 * Include this file in your blade templates for complete functionality
 *
 * @package ZYLM Manufacturing System
 * @version 2.0
 */

/**
 * Usage in Blade Templates:
 *
 * <!-- Include complete JavaScript suite -->
 * <script src="{{ asset('assets/js/custom/project/feature-loader.js') }}"></script>
 *
 * <!-- OR include individual features as needed -->
 * <script src="{{ asset('assets/js/custom/project/features/status-toggle.js') }}"></script>
 * <script src="{{ asset('assets/js/custom/project/modules/administrator.js') }}"></script>
 *
 * <!-- Wait for features to load before custom code -->
 * <script>
 * window.waitForFeatures(() => {
 *     // Your custom code here
 *     console.log('All features loaded and ready');
 * });
 * </script>
 */ // Load order for production build:
const loadOrder = [
    // 1. Core feature loader (must be first)
    "feature-loader.js",

    // 2. Action-based features (loaded automatically by feature-loader)
    "features/ui-interactions.js", // UI helpers, modals, notifications
    "features/status-toggle.js", // Single entity status toggle
    "features/entity-actions.js", // Single entity actions (delete, remove avatar)
];

/**
 * Action-Based Architecture Overview:
 *
 * 1. UI Interactions (ui-interactions.js)
 *    - Modal dialogs and popups
 *    - Confirmation dialogs
 *    - Tooltips and notifications
 *    - Tab switching and UI helpers
 *    - Clipboard operations
 *
 * 2. Status Toggle (status-toggle.js)
 *    - Generic entity status toggling (users, customers, orders, etc.)
 *    - AJAX status updates with error handling
 *    - Button state management and loading indicators
 *    - Visual status indicators update
 *    - Works with data-action="toggle-status"
 *
 * 3. Entity Actions (entity-actions.js)
 *    - Single entity actions (delete, remove avatar)
 *    - Confirmation dialogs for destructive actions
 *    - Works with data-action="delete-entity", data-action="remove-avatar"
 *    - Generic implementation for all entity types
 *
 */

/**
 * Global API Reference:
 *
 * // Feature Loader
 * window.FeatureLoader - Main feature loading instance
 * window.waitForFeatures(callback) - Wait for all features to load
 *
 * // Action-Based Feature Instances
 * window.StatusToggle - Status toggle functionality
 * window.EntityActions - Single entity action operations
 * window.UIInteractions - UI interaction methods
 */

/**
 * Data Attribute API:
 *
 * Status Toggle (works for any entity):
 * data-action="toggle-status" - Enable status toggle
 * data-entity-id="123" - Entity ID
 * data-current-status="1|0" - Current status value
 * data-route="/route/to/update" - Update endpoint
 *
 * Entity Actions (works for any entity):
 * data-action="delete-entity" - Enable delete action
 * data-action="remove-avatar" - Enable avatar removal
 * data-entity-id="123" - Entity ID
 * data-route="/route/to/action" - Action endpoint
 *
 * UI Interactions:
 * data-action="show-modal" - Show modal
 * data-action="hide-modal" - Hide modal
 * data-modal-target="#modalId" - Modal target
 * data-clipboard="text" - Clipboard copy
 */

/**
 * Development Guidelines:
 *
 * 1. Use action-based architecture with data attributes
 * 2. All functionality is generic and entity-agnostic
 * 3. Use data-action attributes for semantic selectors
 * 4. Create reusable actions that work across all modules
 * 5. Focus on three core actions: status toggle, entity actions, UI interactions
 * 6. Avoid module-specific JavaScript files
 */

console.log("ZYLM JavaScript Architecture v3.0 - Action-based system loaded");
console.log(
    "Available actions: Status Toggle, Entity Actions, UI Interactions"
);
console.log(
    "Use window.waitForFeatures() to ensure all features are loaded before your code executes"
);

// Make loadOrder available globally for build tools (if needed)
window.ZYLM = window.ZYLM || {};
window.ZYLM.loadOrder = loadOrder;
