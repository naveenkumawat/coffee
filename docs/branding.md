# Sip The Soul branding

Canonical café name: **Sip The Soul**  
Canonical tagline: **CAFFEINE TILL COFFIN.**  
Production domain (when deployed): **sipthesoul.com**

Do not edit source code to change the primary logo. Use Administrator Website Settings.

---

## A. Primary logo — Administrator

**Administrator → Website Settings → Branding → Primary Logo → Upload / Replace → Save Branding**

1. Sign in to Administrator.
2. Open **Website Settings**.
3. Choose **Branding** (or open `/administrator/website-settings?section=branding`).
4. Set **Brand name** (`Sip The Soul`) and **Tagline** (`CAFFEINE TILL COFFIN.`) if needed.
5. Under **Primary Logo**, choose a file (or replace the current preview).
6. Choose **Brand display**: Logo only / Logo + name / Logo + name + tagline.
7. Save Branding.

Accepted formats: **SVG, PNG, JPG/JPEG, WebP**.  
Primary logo size limit: **5 MB** (`COFFEE_BRAND_LOGO_MAX_KB`, default 5120). This is separate from catalog/product media (`COFFEE_MEDIA_MAX_KB=512`).  
SVG is accepted for the primary logo only. Uploaded SVG is **sanitized automatically** (scripts, event handlers, `javascript:` URLs, and other active content are removed) before public storage.

Favicon remains a static frontend asset. Do not upload it here.

If no logo is uploaded, or the image fails to load, the customer header falls back to the configured name and tagline (never a blank header).

Recommended:

- Transparent **SVG, PNG, or WebP** for best results on the cream customer page
- Square mark: about **512×512**
- Horizontal wordmark: up to about **1200×400**
- JPG remains supported when that is the only artwork available

The uploaded logo is used on the customer header/auth lockup and on staff login. Invoices and emails use the brand name and tagline from the same settings (and the logo in email headers when a URL is available).

---

## B. Favicon — static frontend asset (not Admin upload)

Favicon is **not** uploaded through Administrator. There is no favicon field in Website Settings.

Canonical files live in the customer PWA public folder. Vite serves them from the PWA root (`/`). If you deploy the PWA under a subdirectory, set Vite `base` so these root URLs stay correct.

| File | Size | Role | In repo today |
| --- | --- | --- | --- |
| `customer-pwa/public/favicon.png` | 32×32 (or similar) | Tab icon referenced by `index.html` | Yes |
| `customer-pwa/public/favicon.ico` | 16×16 / 32×32 | Optional classic favicon | Add when you have the asset |
| `customer-pwa/public/favicon-32x32.png` | 32×32 | Optional explicit PNG favicon | Add when you have the asset |
| `customer-pwa/public/apple-touch-icon.png` | 180×180 preferred | iOS home screen | Add when you have the asset; `index.html` currently uses `pwa-192x192.png` |
| `customer-pwa/public/pwa-192x192.png` | 192×192 | Manifest `any` icon + current apple-touch-icon | Yes |
| `customer-pwa/public/pwa-512x512.png` | 512×512 | Manifest `any` icon | Yes |
| `customer-pwa/public/maskable-512x512.png` | 512×512 | Manifest maskable install icon | Yes |

Staff panels use `public/internal/assets/media/logos/favicon.ico`.

Rebuild the PWA after replacing static icons (`npm run build` in `customer-pwa`).

Manifest `name` / `short_name` are **Sip The Soul**. `theme_color` is the café brown token (`#7c5a3b`); `background_color` is cream (`#f6efe6`).

---

## C. Storage (primary logo only)

Logo files use the **PublicMedia** public disk. Raster logos follow the generic store path; SVG uses the branding sanitizer, then the same `website/` directory:

- Disk: `COFFEE_MEDIA_DISK` (default `public`)
- Directory: `storage/app/public/website/`
- Public URL: `{APP_URL}/storage/website/{uuid}.{ext}` after `php artisan storage:link`
- PHP `upload_max_filesize` / `post_max_size` must be at least 5 MB for large logo files

`APP_URL` must include the application subdirectory when Apache serves the app as `/coffee` (example: `http://localhost/coffee`). PublicMedia builds storage URLs from `url('storage/...')` so the subdirectory is kept.

`storage:link` **is required** so browsers can load `/storage/...`.

Production must keep `storage/app/public` on **persistent disk**. Uploaded logos are not in git.

---

## D. Static pages (CMS)

**Administrator → Content → Pages**

| Customer route | Admin page |
| --- | --- |
| About `/about` | About |
| Visit `/contact` | Contact / Visit |
| FAQ `/faq` | FAQ |
| Terms `/terms` | Terms |
| Privacy `/privacy` | Privacy |

These are required system pages and cannot be deleted. Unpublished pages show a safe empty state. Legal copy is not invented for production.

---

## E. Do not

- Do not edit React/PHP source just to change the primary logo when Administrator upload exists.
- Do not upload a favicon through Website Settings (the field was removed).
- Do not put production logos only in `theme/pwa` (that tree is an unused Ombe reference kit).
- Do not change local `APP_URL` for branding. Production `APP_URL` / `CUSTOMER_APP_URL` are set at deploy (`sipthesoul.com` hosts).
