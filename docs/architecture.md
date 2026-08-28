# Coffee Architecture

## Reference review

`zylm` influenced the project in four useful ways:

- Route segmentation by area instead of a single giant web file
- Clear separation between HTTP controllers, domain services, and data access
- Consistent use of Form Requests for validation
- Treating roles, panel boundaries, and authorization as first-class application concerns

`coffee` intentionally avoids the parts of `zylm` that would be too heavy for a new project foundation:

- No global root object or factory tree
- No per-entity interface boilerplate unless a seam becomes valuable
- No project-specific business rules carried across from manufacturing workflows
- No reuse of ZYLM business modules, only its internal UI and architectural approach

## Internal UI foundation

- `administrator` and `barista` each have their own route file, controller namespace, and view tree
- `resources/views/internal` contains the shared internal shell: auth layout, app layout, header, sidebar, footer, alerts, breadcrumbs, stat cards, and pagination
- `public/internal/assets` contains one shared copy of the reused ZYLM-style CSS, JavaScript, plugins, and internal media assets
- Sidebar visibility still depends on the logged-in user role and policy-driven actions
- The public customer-facing Coffee website remains outside this theme layer

## Current layers

- `app/Http`: controllers, middleware, and Form Requests
- `app/Repositories`: query-focused data access for the menu catalog
- `app/Services`: business orchestration, transactions, caching, and role helpers
- `app/Transfers`: small immutable DTOs used between requests and services
- `app/Policies`: authorization rules for admin catalog management
- `app/Events` and `app/Listeners`: cache invalidation hooks for catalog updates
- `app/Support`: shared exceptions and logging helpers
- `resources/views/administrator`: administrator panel screens
- `resources/views/barista`: barista panel screens
- `resources/views/internal`: shared internal panel layouts and components
- `resources/views`: public Coffee storefront screens

## Security model

- `admin` guard is separate from the default `web` guard
- Roles live on the `users` table through `App\Enums\UserRole`
- `EnsureUserHasRole` protects panel routes at the route layer
- Login is role-aware, so a valid staff account still cannot enter the wrong panel
- Policies enforce per-resource permissions inside controllers
- Owner access is elevated centrally in `AuthServiceProvider`

## Database conventions

- Singular Eloquent models with plural snake_case tables
- Foreign keys use Laravel defaults and cascade where it is safe
- Boolean status flags prefer explicit columns like `is_active`
- Sortable admin-managed entities get a `sort_order`
- Jobs, sessions, and cache tables remain Laravel-native

## Logging and errors

- `AddRequestContext` attaches request metadata and a request id to logs
- `structured` logging writes JSON lines for ingestion-friendly logs
- `bootstrap/app.php` centralizes web error rendering and exception reporting while preserving Laravel-native validation redirects

## Next modules

- Orders and line items
- Inventory and supplier receiving
- Shift operations and cash reconciliation
- Customer profiles, loyalty, and notifications
