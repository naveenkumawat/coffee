# Coffee PWA Theme Map

Last updated: August 30, 2026
Theme source inspected: `theme/pwa/ombe-bootstrap-pwa.vercel.app`

## Purpose

This document maps the local Ombe Bootstrap + PWA theme into a Coffee-specific React + Vite + TypeScript implementation plan.

The theme is a reference source for layout and component direction only. It is not the target runtime architecture.

## Theme Summary

Observed characteristics:

- mobile-first HTML pages
- Bootstrap-based styling
- heavy use of static HTML demo pages
- jQuery-driven interactions
- Swiper-based carousels and tab strips
- multiple demo flows beyond Coffee scope
- existing manifest for standalone install behavior

Useful visual direction for Coffee:

- soft rounded mobile cards
- sticky headers and sticky bottom actions
- compact product list cards
- horizontally scrollable category or section rails
- simple auth and account page framing
- order timeline visual language

## HTML Pages Available

### Customer flow-adjacent pages

- `index.html`
- `products.html`
- `product-detail.html`
- `cart.html`
- `checkout.html`
- `my-order.html`
- `track-order.html`
- `profile.html`
- `edit-profile.html`
- `change-password.html`
- `sign-in.html`
- `sign-up.html`
- `forgot-password.html`
- `wishlist.html`
- `notification.html`
- `faq.html`
- `error-404.html`

### Demo/support pages not core to Coffee v1

- `welcome.html`
- `onboarding.html`
- `payment.html`
- `delivery-address.html`
- `add-delivery-address.html`
- `add-card.html`
- `reward.html`
- `review.html`
- `search.html`
- `otp-confirm.html`
- `chat-list.html`
- `chat.html`
- `pages.html`
- `components.html`
- `uc-header.html`

### UI kit / component demo pages

- `ui-accordion.html`
- `ui-alert.html`
- `ui-avatar.html`
- `ui-badge.html`
- `ui-breadcrumb.html`
- `ui-button.html`
- `ui-button-group.html`
- `ui-card.html`
- `ui-collapse.html`
- `ui-datetimepicker.html`
- `ui-divider.html`
- `ui-dropdown.html`
- `ui-inputs.html`
- `ui-lightgallery.html`
- `ui-list-group.html`
- `ui-modal.html`
- `ui-offcanvas.html`
- `ui-pagination.html`
- `ui-placeholder.html`
- `ui-progress.html`
- `ui-radio.html`
- `ui-scrollspy.html`
- `ui-social.html`
- `ui-spinners.html`
- `ui-stepper.html`
- `ui-switch.html`
- `ui-tab.html`
- `ui-timeline.html`
- `ui-toast.html`
- `ui-typography.html`

## Shared Layout Patterns

Common layout structures found across pages:

- `page-wrapper`
- `header`, often `header-fixed` or `header-sticky`
- `page-content` main container
- `footer-fixed-btn` sticky bottom CTA region
- `menubar-area footer-fixed` bottom mobile navigation
- `#preloader` startup overlay

Primary reusable navigation patterns:

- top app bar with back button and page title
- bottom menu bar for main destinations
- slide-out sidebar on the homepage demo

Coffee recommendation:

- keep the top app bar pattern
- keep the bottom navigation idea
- do not adopt the large floating sidebar as the main customer navigation

## Reusable Components Worth Porting Conceptually

Recommended to reinterpret as React components:

- app shell with sticky top bar
- bottom navigation
- search field
- category chip rail
- product card list
- product hero/detail section
- quantity stepper
- favourite toggle
- summary card
- sticky checkout/submit action bar
- order list card
- order status timeline
- account info card
- empty state card
- loading skeleton or spinner treatment

Theme class patterns that suggest reusable UI:

- `dz-card list`
- `dz-card-overlay`
- `dz-product-preview`
- `dz-product-detail`
- `dz-stepper`
- `dz-list-group`
- `default-tab`
- `search-box`
- `title-bar`

## CSS, JS, Assets, Fonts, and Icons

### Core CSS

- `assets/css/style.css`

### Core JS

- `assets/js/jquery.js`
- `assets/js/custom.js`
- `assets/js/settings.js`
- `assets/js/dz.carousel.js`
- `index.js`

### Vendor CSS/JS observed

- Bootstrap bundle
- Swiper
- Bootstrap TouchSpin
- Bootstrap Select
- NoUI Slider
- wNumb
- Imageuploadify
- LightGallery
- Mobiscroll / jquery-listview
- Bootstrap material datetimepicker
- WOW animation

### Asset groups

- app logos and favicon
- product imagery
- order imagery
- avatar imagery
- background and decorative shapes
- illustration SVGs
- bank/card imagery

### Fonts and icon packs

- Google Fonts: `Poppins`, `Raleway`
- Font Awesome
- Feather icons
- Flaticon
- Themify icons

Coffee recommendation:

- keep the visual direction, spacing, and tone
- rebundle only the assets actually used by Coffee
- replace jQuery/plugin dependencies with React-native or lightweight alternatives
- reduce icon/font sprawl to a single primary icon system plus one brand-safe font strategy

## Theme Assets or Code to Avoid or Remove

Not recommended for the Coffee React PWA foundation:

- `jquery.js`
- `custom.js` as-is
- `settings.js` as-is
- `dz.carousel.js` as-is
- Bootstrap TouchSpin jQuery dependency
- Bootstrap Select jQuery dependency
- NoUI Slider for quantity selection
- LightGallery unless a real gallery requirement appears
- Imageuploadify for customer v1
- Mobiscroll listview/datetimepicker flows
- WOW animation dependency
- chat pages
- rewards pages
- review pages
- delivery-address and add-card flows
- payment card storage/wallet UX
- Google Maps embed delivery tracking concept
- demo copy, fake user profiles, fake locations, and placeholder commerce data

Reason:

- they add extra payload or mismatched behavior
- several flows are delivery-first rather than pickup-first
- many features are outside current Coffee scope
- the target app architecture is React + Vite + TypeScript, not static Bootstrap + jQuery

## Pages Most Useful for Coffee

Strong reference candidates:

- `index.html`
  - homepage hero, search, card rails, quick browsing rhythm
- `products.html`
  - category rail + product list structure
- `product-detail.html`
  - product hero, variant/quantity area, sticky CTA
- `cart.html`
  - cart list cards and sticky proceed action
- `checkout.html`
  - summary and notes layout
- `my-order.html`
  - active/history list split
- `track-order.html`
  - timeline/status presentation, but adapted for pickup/manual status instead of delivery map tracking
- `profile.html`
  - account overview framing
- `edit-profile.html`
  - profile form layout
- `sign-in.html`, `sign-up.html`, `forgot-password.html`
  - auth screen framing
- `error-404.html`
  - empty/error state styling direction

## Theme Pages Useful Only Partially

- `wishlist.html`
  - useful if favourites are implemented
- `notification.html`
  - useful later for order-ready/promo notifications
- `search.html`
  - useful if dedicated search becomes needed
- `faq.html`
  - useful for support/static content later
- `payment.html`
  - only as a reminder that payment instructions need a dedicated page, not as a direct design fit

## Proposed Mapping: Theme Pages to React Pages and Components

### Route mapping

- `index.html` -> `HomePage`
- `products.html` -> `MenuPage`
- `product-detail.html` -> `ProductDetailPage`
- `cart.html` -> `CartPage`
- `checkout.html` -> `CheckoutPage`
- `my-order.html` -> `OrdersPage`
- `track-order.html` -> `OrderDetailPage`
- `profile.html` -> `AccountPage`
- `edit-profile.html` -> `ProfileEditPage`
- `change-password.html` -> `PasswordPage`
- `sign-in.html` -> `LoginPage`
- `sign-up.html` -> `RegisterPage`
- `forgot-password.html` -> `ForgotPasswordPage`
- `wishlist.html` -> `FavouritesPage` later
- `notification.html` -> `NotificationsPage` later
- `faq.html` -> `SupportPage` later
- `error-404.html` -> `NotFoundPage`

### Component mapping

- homepage sidebar + header ideas -> `AppHeader`, `MenuDrawer` only if needed
- search box -> `SearchField`
- overlay/product cards -> `FeaturedProductCard`, `ProductCard`
- tab swiper -> `CategoryTabs` or `CategoryChipRail`
- quantity stepper -> `QuantityStepper`
- footer fixed action -> `StickyActionBar`
- cart rows -> `CartItemCard`
- profile info blocks -> `AccountSummaryCard`
- order tabs -> `OrdersTabs`
- order tracking section -> `OrderStatusTimeline`
- auth screen wrappers -> `AuthLayout`

## Coffee-Specific Adaptation Rules

- replace delivery wording with pickup wording
- replace saved-card and wallet flows with manual payment instructions driven by backend config
- replace demo ratings/reviews with real business fields only if implemented
- remove fake social/chat features
- keep product and order cards compact and touch-friendly
- ensure bottom navigation fits Coffee destinations only
- keep customer visual language separate from internal ZYLM admin/barista styling

## Recommended First React Implementation Slice

Best first vertical slice:

- app shell
- route layout
- bottom navigation
- Home page
- Menu page
- Product detail page
- cart badge and Cart page
- API client integration with existing `/api/v1/catalog` and `/api/v1/cart`

Why this first:

- it validates the API-driven architecture quickly
- it proves the theme can be adapted without dragging in demo-only features
- it gives the team a usable storefront foundation before tackling auth/session and checkout edge cases
