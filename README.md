# Coffee

Coffee is a Laravel 13 cafe foundation built for MySQL-backed operations. It includes a shared internal management UI for the `administrator` and `barista` panels, while keeping the public customer-facing Coffee frontend separate.

## Architectural foundations

- Route organization by area
- Interface-driven layering between controllers, services, repositories, and views
- Form Request validation and policy-driven authorization
- Treating roles and security as architecture, not an afterthought

## What was simplified or improved

- Laravel-native dependency injection instead of a global root/factory graph
- DTOs only where they help service boundaries
- Contract-bound repositories and services for reusable domain logic
- Centralized request-context logging and exception rendering
- A lighter role model using enums, middleware, and policies

## Foundation included on August 28, 2026

- Laravel 13 application scaffold
- MySQL-ready `.env.example`
- Separate `admin` guard for staff access
- Role enum and role configuration
- Public homepage backed by cached menu catalog queries
- Shared internal theme assets, layouts, components, and responsive behavior
- Separate `administrator` and `barista` panel routes, views, and dashboards
- Menu category and menu item CRUD foundation
- Structured JSON logging channel
- Seed support for an owner account through environment variables
- Feature tests for public menu, internal auth, and catalog management

## Project structure

- `app/Http`: controllers, middleware, and Form Requests
- `app/Repositories`: contract-backed data access and persistence seams
- `app/Services`: contract-backed business services, transactions, caching, and auth helpers
- `app/Transfers`: immutable DTOs
- `app/Policies`: authorization rules
- `app/Events` and `app/Listeners`: catalog change hooks
- `app/Support`: shared logging and exception utilities
- `resources/views/administrator`: canonical Administrator panel views
- `resources/views/barista`: barista panel views
- `resources/views/internal`: shared internal layouts, auth shell, partials, and UI building blocks
- `resources/views`: public Coffee storefront views remain separate from the internal theme
- `docs/architecture.md`: short architecture reference

## Architecture conventions

- Controllers should stay thin: authorize, validate, call a service, return a response.
- Validated write input should pass through a module parser and transfer before business persistence.
- Reusable business logic belongs in services, not controllers or large models.
- Reusable query and persistence logic belongs in repositories behind interfaces.
- Requests, repositories, services, transfers, and parsers are grouped by module.
- Transfers normalize validated input before it reaches business services.
- Role-specific HTTP code stays separated, while shared business logic lives once.

## Local setup

1. Install dependencies:

```bash
composer install
npm install
```

2. Create environment file and application key:

```bash
cp .env.example .env
php artisan key:generate
```

3. Update `.env` for MySQL:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=coffee
DB_USERNAME=root
DB_PASSWORD=
```

4. Optionally prepare an initial owner account before seeding:

```env
ADMIN_NAME="Cafe Owner"
ADMIN_EMAIL="owner@example.com"
ADMIN_PASSWORD="change-me"
```

5. Reset and seed the local database (canonical demo reset):

```bash
php artisan migrate:fresh --seed
```

This loads structural taxonomy always, then `DemoSeeder` for **local/testing only** (catalog, CMS, customers, orders, café tables, staff bell data). Production (`APP_ENV=production`) never loads `DemoSeeder`.

Optional one-shot owner bootstrap (any environment, including production):

```bash
ADMIN_EMAIL="owner@example.com"
ADMIN_PASSWORD="change-me"
```

6. Start the app:

```bash
composer run dev
```

7. Build frontend assets for a production-style local run:

```bash
npm run build
```

## Internal panel URLs

- Administrator login: `/administrator/login`
- Barista login: `/barista/login`

## Internal role naming convention

- Coffee uses `Administrator` as the canonical internal management folder and route-file convention for role-specific PHP namespaces, controllers, views, and route files.
- Do not create parallel `Admin` folders such as `app/Http/Controllers/Admin` or `resources/views/admin`.
- The auth guard key intentionally remains `admin`, because it is a guard identifier rather than a folder or namespace convention.

## Development Credentials (DEVELOPMENT ONLY)

Seeded by `DemoSeeder` for **local/testing** only. Shared password: `password`.

**Never use these accounts or password in production.**

| Role | Email |
|------|-------|
| Owner / Administrator | `admin@coffee.local` |
| Manager | `manager@coffee.local` |
| Barista 1 | `barista@coffee.local` |
| Barista 2 | `barista2@coffee.local` |
| Inactive staff (login blocked) | `inactive.staff@coffee.local` |
| Customer (full activity) | `customer@coffee.local` |
| Additional customers | `priya@coffee.local`, `arjun@coffee.local`, `empty@coffee.local`, `neha@coffee.local`, `rohan@coffee.local`, `meera@coffee.local`, `kabir@coffee.local`, `ananya@coffee.local`, `vikram@coffee.local`, `sara@coffee.local` |

After seeding, run `php artisan coffee:catalog-readiness` to inspect intentional incomplete/paused/stock-concern demo products.

## Testing

```bash
php artisan test
```

The test suite uses in-memory SQLite and sets `QUEUE_CONNECTION=sync`, so it stays isolated from your MySQL setup.
