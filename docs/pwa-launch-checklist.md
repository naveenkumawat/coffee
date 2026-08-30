# Customer PWA Launch Checklist

Last updated: August 30, 2026  
Status after Phase C6 final QA: ready for production smoke test; keep customer Blade until live verification.

## Completed QA (C6)

- Responsive shell checked for common phone widths (320–430), tablet, and desktop fallback: overflow clipping, sticky CTA vs bottom nav/safe-area, long text wrapping, compact bottom-nav labels on very small screens.
- Journey coverage reviewed against seeded statuses: browse → product → cart → auth redirect → checkout → confirmation → tracking; favourites/account/auth; Pending Payment / Ready / Cancelled / Rejected treatments from C4.
- PWA installability: `manifest.webmanifest` (standalone, theme `#04764e`, background `#f6f1eb`), `any` + `maskable` icons, Apple touch meta, SW registration in production only (dev unregisters to avoid MIME/`text/html` for `/sw.js`).
- Offline/network: offline banner + `offline.html`; API/`/sanctum` never treated as cache authority; SW refuses to cache HTML as static assets; update banner + `SKIP_WAITING` / `controllerchange` reload.
- Performance: route lazy-loading retained; product/hero images lazy (eager only for LCP hero/detail); catalog media resolver ready for real Laravel storage URLs; no new dependencies.
- Accessibility: skip link, focus styles, form alerts, FAQ accordion semantics, reduced-motion, status badges with text.
- Imagery readiness: shared `ProductImage` with aspect ratio, object-fit, broken-image fallback to placeholder — final photos not sourced yet.

## Required production environment

| Variable | Where | Notes |
| --- | --- | --- |
| `VITE_API_BASE_URL` | PWA build | Absolute `…/api/v1` URL baked at build time |
| `COFFEE_PWA_URL` | Laravel | Public HTTPS PWA origin (reset links, customer URLs) |
| `SANCTUM_STATEFUL_DOMAINS` | Laravel | PWA host(s), no scheme |
| `CORS_ALLOWED_ORIGINS` | Laravel | Full PWA origin(s) with scheme |
| `SESSION_DOMAIN` | Laravel | Shared parent cookie domain when applicable; `null` on localhost |
| `SESSION_SECURE_COOKIE` / HTTPS | Laravel | `true` in production; both hosts on HTTPS |

Keep customer Blade routes enabled until the live smoke checklist below passes.

## Production smoke-test checklist

1. Build: `npm run typecheck && npm run build && npm audit` in `customer-pwa` with production `VITE_API_BASE_URL`.
2. Deploy `dist/` to PWA HTTPS host; confirm `/manifest.webmanifest`, icons, `/sw.js` return correct MIME types (not HTML).
3. Confirm CSRF cookie + login + authenticated `/api/v1` calls from the PWA origin.
4. Install / Add to Home Screen (Android Chrome; iOS Safari where supported) → opens standalone.
5. Full order path online: Home → Menu → Product → Cart → Checkout → Confirmation → Order detail.
6. Pending Payment: UPI copy + WhatsApp; Ready for Pickup callout; Cancelled/Rejected terminal UI.
7. Force offline: shell/offline messaging appears; cart/checkout/orders do not pretend to succeed from cache.
8. Ship a second build: in-app “new version” banner → Refresh loads new assets.
9. Empty customer + unavailable product paths remain clear.
10. Only after the above: schedule Blade customer storefront retirement separately.

## Remaining launch blockers / out of scope

- Real product photography (components ready; assets not final).
- Live production HTTPS cutover + cookie domain validation on the real hosts (environment-specific).
- Optional later UX (explicitly not in C6): pull-to-refresh, “I’m on my way”, offers, reorder, quick view.
- Device lab sign-off on physical iOS/Android install flows still recommended before Blade retirement.
