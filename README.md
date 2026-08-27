# Coffee

Coffee is a Laravel 13 cafe foundation built for MySQL-backed operations. The first implemented slice is a role-aware menu catalog with a public storefront homepage and a separate admin guard for staff workflows.

## What was reused conceptually from ZYLM

- Route organization by area
- Layering between controllers, services, repositories, and views
- Form Request validation and policy-driven authorization
- Treating roles and security as architecture, not an afterthought

## What was simplified or improved

- Laravel-native dependency injection instead of a global root/factory graph
- DTOs only where they help service boundaries
- Repositories only for query-heavy menu catalog reads
- Centralized request-context logging and exception rendering
- A lighter role model using enums, middleware, and policies

## Foundation included on August 27, 2026

- Laravel 13 application scaffold
- MySQL-ready `.env.example`
- Separate `admin` guard for staff access
- Role enum and role configuration
- Public homepage backed by cached menu catalog queries
- Admin dashboard
- Menu category and menu item CRUD foundation
- Structured JSON logging channel
- Seed support for an owner account through environment variables
- Feature tests for public menu, admin auth, and catalog management

## Project structure

- `app/Http`: controllers, middleware, and Form Requests
- `app/Repositories`: query-focused data access
- `app/Services`: business services, transactions, caching, and auth helpers
- `app/Transfers`: immutable DTOs
- `app/Policies`: authorization rules
- `app/Events` and `app/Listeners`: catalog change hooks
- `app/Support`: shared logging and exception utilities
- `resources/views`: public and admin Blade UI
- `docs/architecture.md`: short architecture reference

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

5. Run migrations and seed:

```bash
php artisan migrate
php artisan db:seed
```

6. Start the app:

```bash
composer run dev
```

7. Build frontend assets for a production-style local run:

```bash
npm run build
```

## Testing

```bash
php artisan test
```

The test suite uses in-memory SQLite and sets `QUEUE_CONNECTION=sync`, so it stays isolated from your MySQL setup.
