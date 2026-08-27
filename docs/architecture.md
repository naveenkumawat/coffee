# Coffee Architecture

## Reference review

`zylm` influenced the project in four useful ways:

- Route segmentation by area instead of a single giant web file
- Clear separation between HTTP controllers, domain services, and data access
- Consistent use of Form Requests for validation
- Treating roles and authorization as first-class application concerns

`coffee` intentionally avoids the parts of `zylm` that would be too heavy for a new project foundation:

- No global root object or factory tree
- No per-entity interface boilerplate unless a seam becomes valuable
- No project-specific business rules carried across from manufacturing workflows

## Current layers

- `app/Http`: controllers, middleware, and Form Requests
- `app/Repositories`: query-focused data access for the menu catalog
- `app/Services`: business orchestration, transactions, caching, and role helpers
- `app/Transfers`: small immutable DTOs used between requests and services
- `app/Policies`: authorization rules for admin catalog management
- `app/Events` and `app/Listeners`: cache invalidation hooks for catalog updates
- `app/Support`: shared exceptions and logging helpers
- `resources/views`: public and admin Blade interfaces

## Security model

- `admin` guard is separate from the default `web` guard
- Roles live on the `users` table through `App\Enums\UserRole`
- `EnsureUserHasRole` protects admin routes at the route layer
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
- `bootstrap/app.php` centralizes web error rendering and exception reporting

## Next modules

- Orders and line items
- Inventory and supplier receiving
- Shift operations and cash reconciliation
- Customer profiles, loyalty, and notifications
