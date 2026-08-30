# Coffee Customer PWA

Customer-facing mobile-first PWA for Coffee Cafe.

## Runtime Requirement

- Node.js `20.20.2` LTS via `.nvmrc`
- npm `10.x`

This workspace was verified with:

- `node v20.20.2`
- `npm 10.8.2`

The app depends on a Vite toolchain that is not compatible with the older default `node v16.13.0` runtime found on some local environments.

## Local Setup

macOS/Linux with `nvm`:

```bash
cd customer-pwa
nvm install
nvm use
npm install
cp .env.example .env
```

If your Laravel app runs from `http://localhost/coffee`, keep:

```dotenv
VITE_API_BASE_URL=http://localhost/coffee/api/v1
```

If your Laravel backend runs on a different host or port, point `VITE_API_BASE_URL` to that backend origin instead.

Root Laravel environment:

```dotenv
COFFEE_PWA_URL=http://localhost:5173
```

Use the deployed PWA URL in non-local environments so customer reset emails open the React app instead of the transitional Blade screen.

## Common Commands

```bash
npm run typecheck
npm run build
npm audit
```
