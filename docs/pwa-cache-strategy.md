# Customer PWA cache strategy

Sip The Soul caches public, repeat-visit data on the device. The Laravel API remains authoritative. Caching is an optimization: ordering never depends on IndexedDB, Cache Storage, or a successful service-worker write.

## What is cached

| Kind | Examples | Storage | Strategy |
| --- | --- | --- | --- |
| Version metadata | `cache_version` | `localStorage` key `sip-the-soul.cache-version` | Tiny string only |
| Public JSON | `/content`, categories, flavours, full menu catalogue JSON | IndexedDB `sip-the-soul` / store `public-content` | Show cached copy, revalidate when the version changes; menu also uses ETag |
| Static build | hashed JS/CSS, shell, fonts, icons | Cache Storage `coffee-shell-*` | Precache via `scripts/generate-sw.mjs` |
| Public media | `/storage/…` product, category, logo, CMS images | Cache Storage `sip-the-soul-media-*` | CacheFirst, max 80 entries, 7-day age, trimmed |
| Session | cart, dining draft/session, `orderingContext`, auth | Zustand + existing `localStorage`/`sessionStorage` keys | Not part of public cache |

Do **not** put carts, orders, payments, loyalty, addresses, notifications, waiter ops, or other private API responses into IndexedDB or the media cache.

`/home` stays network-first (personalized). Cafe availability and stock-backed `is_available` are reconciled with the API (menu ETag / live dining `force` fetch). Cached JSON is `JSON.parse(JSON.stringify(…))` plain data, never Laravel `ResourceCollection` objects.

## Version invalidation

Canonical server value: `public.cache.version` (opaque UUID), exposed by lightweight `GET /api/v1/app-bootstrap` as `cache_version` (catalog/content/media fields currently mirror the same value).

The PWA stores the last seen version in `localStorage`. It checks the endpoint on first bootstrap, tab focus/visibility, and `online` after reconnect. Realtime `public.cache` / `.public.cache.invalidated` with payload `{ cache_version }` can accelerate connected clients. Websocket failure does not break correctness; the REST version check remains mandatory.

If the server version matches, public IndexedDB entries are reused and `/content` plus category/flavour lists are not re-downloaded. If it differs, the client clears IndexedDB public records and media Cache Storage, then refetches what the current screen needs. Auth, guest cart (`coffee.guest-cart.v1`), and dining `orderingContext` are left alone.

First visit caches progressively (Home does not prefetch the whole catalog). Revisits hydrate from IndexedDB immediately when a valid copy exists.

## Automatic invalidation

`PublicCacheVersionService` is the only place that bumps the version. Domain services call `invalidate()` / catalog `flushPublicCache()` after public content commits:

- Catalog: products, categories, flavours, tags, menu, merchandising, related recipe/add-on presentation
- Website Settings fields that appear in customer `/content` (branding, business, payments, fulfilment, manual closed)
- CMS pages and FAQ
- Social links

Not bumped: staff password/profile edits, referral/advanced internals, order-security-only settings, payment verification, private notifications.

## Admin actions

**Administrator → Cache Management**

- **Clear Server Cache** — forgets application public catalog/CMS/campaign/merchandising keys. Does not run `config:clear` / `route:clear` / `optimize:clear`. Does not change the client version.
- **Refresh Customer Cache** — increments `public_cache_version`, forgets those server keys, records `invalidated_at`, broadcasts `cache_version` only. Customer browsers update on the next version check or realtime event. Admin cannot wipe remote IndexedDB directly.
- **Clear All Caches** — same as refresh (server public caches + new client version). Does not delete carts, sessions, orders, dining, payments, or loyalty.

## Offline

- Previously cached CMS (About / Visit / FAQ / Terms / Privacy) and menu JSON can render offline.
- Never cached and offline: existing empty/error UI.
- Quota errors, private browsing, or missing IndexedDB: continue from the network.

## Production notes

- Framework caches (`config:cache`, `route:cache`, `view:cache`) stay a deployment concern.
- Prefer versioned or replaced `PublicMedia` paths so a new logo/product image is a new URL; global refresh also drops `sip-the-soul-media-*`.
- Service worker does not intercept `/api/` or `/sanctum/`.

## Debug one development browser

1. DevTools → Application → Local Storage: only `sip-the-soul.cache-version` for public cache (no menu JSON).
2. IndexedDB → `sip-the-soul` → `public-content`.
3. Cache Storage → `coffee-shell-*` and `sip-the-soul-media-*`.
4. Delete those three, or bump version via Admin **Refresh Customer Cache**, then reload.
5. Obsolete keys `coffee_public_cache_version` / `the88coffees.cache-version` are removed on version sync.
