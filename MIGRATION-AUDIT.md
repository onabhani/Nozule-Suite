# Nozule PMS — Migration Audit Report

**Date:** 2026-03-04
**Scope:** Identify (1) blockers for Laravel + React migration, (2) performance/stability/security issues before migration
**Codebase:** v1.8.0 — 305 PHP files, 38 JS files, 28 modules, 205 REST endpoints, 11 migration files

---

## Table of Contents

1. [Architecture Overview](#1-architecture-overview)
2. [Migration Blockers](#2-migration-blockers)
3. [Pre-Migration Issues (Security)](#3-pre-migration-issues-security)
4. [Pre-Migration Issues (Performance)](#4-pre-migration-issues-performance)
5. [What's Already Migration-Ready](#5-whats-already-migration-ready)
6. [Module-by-Module Extractability](#6-module-by-module-extractability)
7. [Recommended Migration Strategy](#7-recommended-migration-strategy)

---

## 1. Architecture Overview

```text
Frontend:  Alpine.js 3.x + Tailwind CSS (both CDN, no build step)
API:       WordPress REST API (205 routes under /wp-json/nozule/v1)
Backend:   PHP 8.0+ with PSR-4 autoloading, custom DI container
Database:  Custom tables via $wpdb (no custom post types)
External:  Booking.com (OTA XML), Odoo (JSON-RPC), WhatsApp (Meta Graph API)
```

**Key architectural decisions that help migration:**
- No custom post types or taxonomies — all data in custom `nzl_*` tables
- No jQuery dependency — vanilla JS + Alpine.js
- No `wp_ajax_*` handlers — REST API only
- PSR-4 autoloading with `Nozule\` namespace
- Repository pattern with constructor-injected dependencies
- Custom service container (`includes/Core/Container.php`) — 100% framework-agnostic
- Custom abstractions already exist for Database, EventDispatcher, CacheManager, SettingsManager, Logger

**Key architectural decisions that hurt migration:**
- EventDispatcher wraps `do_action()`/`apply_filters()` internally
- Database class wraps `$wpdb` with raw SQL strings (no query builder)
- 5 custom WordPress user roles with 15+ capabilities
- 4 shortcodes for public-facing pages
- SSE events stored in WordPress transients

---

## 2. Migration Blockers

### BLOCKER 1: Database Layer — Raw `$wpdb` Everywhere

**Severity: CRITICAL** | **Files affected: ~40+** | **Effort: 2-3 weeks**

The `Database` class (`includes/Core/Database.php:10-117`) wraps the global `$wpdb` instance. Every repository builds raw SQL strings with `{$table}` interpolation:

```text
includes/Core/Database.php:12-14          — Constructor pulls global $wpdb
includes/Core/BaseRepository.php:34       — "SELECT * FROM {$table} WHERE id = %d"
includes/Core/BaseRepository.php:41       — "SELECT * FROM {$table} ORDER BY ..."
includes/Core/BaseRepository.php:57       — "SELECT * FROM {$table}" with manual WHERE
includes/Core/BaseRepository.php:91-107   — Manual condition building for count()
```

Concrete repositories compound this:
```text
includes/Modules/Rooms/Repositories/RoomRepository.php:26-28         — Raw SQL per method
includes/Modules/Rooms/Repositories/InventoryRepository.php:79-100   — Complex subquery SQL
includes/Modules/Bookings/Repositories/BookingRepository.php:244-333 — Manual pagination SQL
includes/Modules/Channels/Services/ChannelSyncService.php:129-139    — DB queries in service
```

**Why it blocks:** No query builder abstraction exists. Every SQL string must be manually rewritten to Eloquent or Laravel's query builder. The `%d`/`%s` placeholder syntax is wpdb-specific.

**Migration path:** Replace `Database` class internals with Laravel `DB` facade. Convert BaseRepository to use query builder. Convert each concrete repository method individually.

---

### BLOCKER 2: Event System — WordPress Hooks as Domain Events

**Severity: CRITICAL** | **Files affected: 15+** | **Effort: 1-2 weeks**

The `EventDispatcher` (`includes/Core/EventDispatcher.php:15-41`) delegates directly to WordPress:
```php
// Line 15-16
public function dispatch(string $event, ...$args): void {
    do_action('nozule/' . $event, ...$args);
}
```

Domain events are fired from core business logic:
```text
includes/Modules/Bookings/Services/BookingService.php:175    — do_action('nozule/booking/created')
includes/Modules/Bookings/Services/BookingService.php:221    — do_action('nozule/booking/confirmed')
includes/Modules/Bookings/Services/BookingService.php:289    — do_action('nozule/booking/cancelled')
includes/Modules/Bookings/Services/BookingService.php:344    — do_action('nozule/booking/checked_in')
includes/Modules/Bookings/Services/BookingService.php:394    — do_action('nozule/booking/checked_out')
includes/Modules/Bookings/Services/BookingService.php:472    — do_action('nozule/booking/no_show')
includes/Modules/Bookings/Services/BookingService.php:545    — do_action('nozule/booking/payment_added')
```

12+ modules subscribe to these events via `add_action()`:
```text
includes/Modules/Notifications/NotificationsModule.php:70-83     — 6 booking event listeners
includes/Modules/Integrations/Services/IntegrationService.php:35-44 — 7+ event listeners
includes/Modules/Housekeeping/HousekeepingModule.php:95          — checked_out listener
includes/Modules/Billing/BillingModule.php:135                   — checked_in listener
includes/Modules/WhatsApp/WhatsAppModule.php:78-88               — 3 booking listeners
includes/Modules/Messaging/MessagingModule.php:78-88             — 3 booking listeners
includes/Modules/Review/ReviewModule.php:71                      — checked_out listener
```

**Why it blocks:** The entire cross-module communication layer depends on WordPress hooks. Events are untyped (just string names + varargs), with no event classes.

**Migration path:** Create typed Event classes (e.g., `BookingCreatedEvent`). Replace `EventDispatcher` internals with Laravel's `Event` facade. Convert `add_action` listeners to Laravel event listeners/subscribers.

---

### BLOCKER 3: 205 REST API Routes via `register_rest_route()`

**Severity: HIGH** | **Files affected: 39 controllers** | **Effort: 1-2 weeks**

All API routes are registered through WordPress REST API:
```text
includes/API/RestController.php          — 25 core routes
includes/API/SSEController.php           — 1 SSE route
+ 37 module controllers                  — 179 additional routes
```

Each route registration looks like:
```php
register_rest_route('nzl/v1', '/admin/bookings', [
    'methods'  => 'GET',
    'callback' => [$controller, 'list'],
    'permission_callback' => function() { return current_user_can('nzl_staff'); }
]);
```

Controller methods receive `WP_REST_Request` and return `WP_REST_Response`:
```text
All 39 controllers accept WP_REST_Request as parameter
All 39 controllers return WP_REST_Response or WP_Error
```

**Why it blocks:** Every controller method signature, request parsing (`$request->get_param()`), and response construction must change.

**Migration path:** Convert to Laravel route files. Replace `WP_REST_Request` with Laravel `Request`. Replace `WP_REST_Response` with Laravel JSON responses. Map `permission_callback` to Laravel middleware/policies.

---

### BLOCKER 4: Authentication & Authorization — WordPress Roles/Capabilities

**Severity: HIGH** | **Files affected: 39+ controllers** | **Effort: 1 week**

5 custom roles defined in `includes/Core/Activator.php:107-180`:
- `nzl_manager`, `nzl_reception`, `nzl_housekeeper`, `nzl_finance`, `nzl_concierge`

15+ custom capabilities checked across 98 instances:
```text
current_user_can('nzl_admin')              — Admin-level access
current_user_can('nzl_staff')              — Staff-level access
current_user_can('nzl_manage_rooms')       — Room management
current_user_can('nzl_manage_bookings')    — Booking management
current_user_can('nzl_manage_guests')      — Guest management
current_user_can('nzl_view_reports')       — Report viewing
current_user_can('nzl_manage_channels')    — Channel management
current_user_can('nzl_manage_billing')     — Billing access
current_user_can('nzl_manage_pos')         — POS access
... etc
```

Additional auth dependencies:
```text
includes/Modules/Bookings/Services/BookingService.php:132,152,334,384,541 — get_current_user_id()
includes/Admin/StaffIsolation.php:20-41  — 7 WordPress admin hooks for multi-property isolation
```

**Why it blocks:** Auth checks are scattered across every controller. User ID retrieval is WordPress-specific. Staff isolation relies on WordPress admin hooks.

**Migration path:** Create Laravel roles/permissions matching the 5 roles and 15 capabilities. Use Spatie laravel-permission or Laravel Gates/Policies. Replace `get_current_user_id()` with `Auth::id()`.

---

### BLOCKER 5: WordPress Utility Functions Scattered in Business Logic

**Severity: MEDIUM** | **Files affected: 20+** | **Effort: 1 week**

WordPress functions used directly in services (not just bootstrap code):

| Function | Occurrences | Files |
|----------|-------------|-------|
| `current_time('mysql')` | 10+ | BookingService, BookingRepository, RoomRepository, ChannelSyncService |
| `__()` / `esc_html__()` | 50+ | All validators, most services |
| `sanitize_text_field()` | 8+ | BookingService:514-597 |
| `sanitize_email()` | 2+ | BookingService:580 |
| `wp_json_encode()` | 3+ | BookingService:147,535, SettingsManager:62 |
| `wp_parse_args()` | 2+ | BookingRepository:257 |
| `wp_date()` | 3+ | ChannelSyncService:98,230 |
| `get_option('admin_email')` | 5 | WhatsApp, Email, Notification services |
| `get_option('date_format')` | 4 | Plugin, NotificationService, TemplateService |
| `wp_mail()` | 1+ | EmailService |
| `wp_remote_post()` | 3 | BookingComApiClient, OdooConnector, WebhookConnector |
| `sanitize_sql_orderby()` | 1 | BaseRepository:56 |
| `esc_like()` | 1 | BookingRepository:288 |
| `wp_unslash()` | 1 | BookingService:614 |

**Migration path:** Create adapter classes: `TimeProvider` (→ Carbon), `Sanitizer` (→ Laravel validation), `HttpClient` (→ Laravel HTTP), `Mailer` (→ Laravel Mail). Translation `__()` maps directly to Laravel's `__()`.

---

### BLOCKER 6: Frontend — Alpine.js Templates Embedded in PHP

**Severity: MEDIUM** | **Files affected: 36 templates + 38 JS files** | **Effort: 2-4 weeks (for React rewrite)**

Templates use Alpine.js directives within WordPress PHP templates:
```text
templates/admin/*.php  — 25 files with x-data, x-for, x-if, x-text directives
templates/public/*.php — 4 files for booking widget, room cards, etc.
templates/emails/*.php — 2 email templates
```

Each template pairs with an Alpine.js component:
```text
assets/js/admin/*.js     — 26 admin Alpine.js components
assets/js/components/*.js — 5 public Alpine.js components
```

All JS talks to `/wp-json/nozule/v1` with WordPress nonces for auth.

**Why it blocks:** The React rewrite means all 36 templates and 38 JS files are replaced entirely. The API contract (request/response shapes) is the migration bridge.

**Migration path:** The REST API response shapes are the contract. Document every endpoint's request/response schema. Build React components consuming the same API shapes from Laravel routes.

---

### BLOCKER 7: Cron Jobs — WordPress Scheduled Events

**Severity: LOW** | **Files affected: 5** | **Effort: 2 days**

```text
includes/Core/Activator.php:212  — nzl_daily_maintenance (daily)
includes/Core/Activator.php:216  — nzl_send_reminders (hourly)
includes/Core/Plugin.php         — nzl_daily_maintenance, nzl_send_reminders handlers
includes/Modules/Channels/ChannelSyncModule.php:134-147 — pull/push cron jobs
```

**Migration path:** Convert to Laravel scheduled tasks in `app/Console/Kernel.php`.

---

## 3. Pre-Migration Issues (Security)

### SEC-1: API Keys Stored in Plaintext

**Severity: MEDIUM** | **Risk: credential exposure on DB compromise**

```text
includes/Modules/Integrations/Services/OdooConnector.php:39   — Odoo API key in settings table
includes/Modules/Channels/Services/BookingComApiClient.php:24-26 — Password in class property
includes/Modules/WhatsApp/Services/WhatsAppService.php         — WhatsApp access token in settings
```

All credentials stored as plaintext in `nzl_settings` table. A SQL injection or DB backup leak exposes all integration credentials.

**Fix now:** Encrypt sensitive settings at rest using `openssl_encrypt()` with a key from `wp-config.php`. Decrypt on read. This is cheap to implement and carries over to Laravel (use `Crypt` facade).

---

### SEC-2: StaffIsolation Uses `$_GET` Directly

**Severity: LOW** | **Risk: minimal (sanitized, but not ideal)**

```text
includes/Admin/StaffIsolation.php:138 — $_GET['page'] with sanitize_text_field()
```

Uses `sanitize_text_field()` which is adequate but `sanitize_key()` would be more appropriate for a menu slug.

---

### SEC-3: No Rate Limiting on Public Booking Endpoint

**Severity: MEDIUM** | **Risk: booking spam, inventory manipulation**

The public `POST /bookings` endpoint (`includes/API/RestController.php`) has no rate limiting. An attacker could:
- Flood the system with fake bookings (status: pending)
- Temporarily block inventory by creating bookings that hold rooms

**Fix now:** Add rate limiting via transient-based counter or a lightweight middleware.

---

### SEC-4: SSE Controller Transient Race Condition

**Severity: LOW** | **Risk: missed events under concurrent load**

```text
includes/API/SSEController.php:137,149,158 — get_transient/set_transient for event queue
```

WordPress transients are not atomic. Under concurrent SSE connections, events can be lost when two processes read/write the same transient simultaneously.

**Fix now:** Acceptable for single-property low-traffic. For multi-property, switch to database-backed queue table.

---

## 4. Pre-Migration Issues (Performance)

### PERF-1: N+1 Query in `markNoShows()`

**Severity: HIGH** | **Impact: daily cron can be slow with many pending bookings**

```text
includes/Modules/Bookings/Services/BookingService.php:437-485
```

```php
$candidates = $this->bookingRepository->getNoShowCandidates();
foreach ($candidates as $booking) {
    $this->bookingRepository->beginTransaction();
    $this->availabilityService->restoreInventory(...);  // DB query
    $this->bookingRepository->update(...);               // DB query
    $this->bookingRepository->createLog(...);             // DB query
    $this->bookingRepository->commit();
}
```

100 no-shows = 300+ queries + 100 transactions.

**Fix now:** Wrap entire loop in single transaction. Batch the UPDATE and INSERT statements.

---

### PERF-2: N+1 Query in Channel Sync Inventory Push

**Severity: HIGH** | **Impact: slow OTA sync under many room type × rate plan combinations**

```text
includes/Modules/Channels/Services/ChannelSyncService.php:128-149
```

```php
foreach ($mappings as $mapping) {
    $rows = $this->db->getResults("SELECT ... WHERE room_type_id = %d", ...);
    foreach ($rows as $row) { ... }
}
```

10 mappings × 30 days = 300 queries.

**Fix now:** Fetch all inventory for the date range in a single query, then group in PHP.

---

### PERF-3: Cache Underutilization

**Severity: MEDIUM** | **Impact: repeated identical queries on every request**

`CacheManager` exists (`includes/Core/CacheManager.php`) but is only used in:
- `PricingService` (rate caching)
- `SSEController` (event queue)

Not cached but should be:
- Room type lookups (queried on nearly every page)
- Active channel mappings (queried on every sync)
- Settings (already cached in SettingsManager — this is good)

**Fix now:** Add caching to `RoomTypeRepository.getAll()` and `ChannelConnectionRepository.getActive()`.

---

### PERF-4: CDN-Only Asset Loading (No Bundling)

**Severity: LOW** | **Impact: 30+ HTTP requests per admin page load**

```text
includes/Admin/AdminAssets.php — Enqueues 30+ separate JS/CSS files
```

Each admin page loads: Tailwind CSS (CDN) + admin.css + RTL CSS + api.js + utils.js + i18n.js + store.js + page-specific JS + Alpine.js (CDN) = 10+ requests minimum.

**Fix now:** Not urgent for pre-migration. The React rewrite will use a proper bundler (Vite).

---

### PERF-5: Missing Composite Indexes

**Severity: MEDIUM** | **Impact: slow queries as data grows**

```text
migrations/001_create_tables.php
```

Missing indexes that would help common query patterns:
- `nzl_payments`: No composite index on `(booking_id, status)` — used in payment summary queries
- `nzl_bookings`: No index on `(property_id, status, check_in)` — needed for multi-property dashboard
- `nzl_room_inventory`: The unique index on `(room_type_id, date)` is good, but property-aware queries after migration 011 will need `(property_id, room_type_id, date)`

**Fix now:** Add these indexes in a new migration (012). This improves current performance and carries over to Laravel.

---

## 5. What's Already Migration-Ready

These components are well-designed and need minimal changes:

| Component | File | WP Coupling | Notes |
|-----------|------|-------------|-------|
| Service Container | `includes/Core/Container.php` | **NONE** | 100% portable, standard DI pattern |
| BaseValidator | `includes/Core/BaseValidator.php` | Only `__()` | Pure validation logic |
| BookingValidator | `Modules/Bookings/Validators/BookingValidator.php` | Only `__()` | Clean, no service deps |
| RatePlanValidator | `Modules/Pricing/Validators/RatePlanValidator.php` | Only `__()` | Injected repo dep |
| PricingService | `Modules/Pricing/Services/PricingService.php` | Only `__()` | 95% extractable |
| AvailabilityService | `Modules/Rooms/Services/AvailabilityService.php` | **NONE** | 95% extractable |
| SettingsManager | `includes/Core/SettingsManager.php` | Only `wp_json_encode()` | 98% extractable |
| All Model classes | `*/Models/*.php` | **NONE** | Data objects with `fromRow()` |
| Database schema | `migrations/001-011` | dbDelta syntax | Tables transfer directly |

The codebase uses **constructor injection consistently** across all services. This is the single most important factor for migration — dependencies are explicit and swappable.

---

## 6. Module-by-Module Extractability

| Module | Services WP-Coupled | Repositories WP-Coupled | Extractability |
|--------|---------------------|------------------------|----------------|
| Bookings | BookingService (16 WP calls) | BookingRepository (5 WP calls) | 70% |
| Pricing | PricingService (1 WP call) | RatePlanRepo, SeasonalRepo | 95% |
| Rooms | AvailabilityService (0 WP calls) | RoomRepo (1 WP call) | 95% |
| Channels | ChannelSyncService (8 WP calls) | via Database wrapper | 85% |
| Guests | GuestService (unknown) | GuestRepository | 80% est. |
| Housekeeping | Subscribes to 1 hook | Standard repos | 85% |
| Billing | Subscribes to 1 hook | Standard repos | 85% |
| Notifications | 6 hook subscriptions + wp_mail | Standard repos | 60% |
| WhatsApp | wp_remote_post + hooks | Standard repos | 75% |
| Integrations | wp_remote_post (Odoo, Webhooks) | Standard repos | 70% |
| Settings | wp_json_encode only | Via SettingsManager | 98% |
| All Others | Likely similar patterns | Standard repos | 80% est. |

---

## 7. Recommended Migration Strategy

### Phase 0: Pre-Migration Fixes (Do Now — 1-2 weeks)

These fixes improve the current system AND reduce migration friction:

1. **Fix N+1 in `markNoShows()`** — Batch the loop (PERF-1)
2. **Fix N+1 in channel sync** — Single query for inventory (PERF-2)
3. **Add missing indexes** — New migration 012 (PERF-5)
4. **Encrypt API credentials at rest** — Settings encryption (SEC-1)
5. **Add rate limiting to public booking endpoint** (SEC-3)
6. **Increase cache usage** — Room types, channel mappings (PERF-3)

### Phase 1: Create Abstraction Interfaces (Before Migration — 1 week)

Create interfaces that both WordPress and Laravel can implement:

```text
DatabaseInterface        → wraps query execution
EventDispatcherInterface → wraps event dispatch/listen
TimeProviderInterface    → wraps current_time() / Carbon
HttpClientInterface      → wraps wp_remote_post() / Guzzle
AuthServiceInterface     → wraps current_user_can() / Gates
MailerInterface          → wraps wp_mail() / Laravel Mail
```

This is optional but reduces risk during migration by allowing parallel running.

### Phase 2: Migrate Backend (Laravel) — 4-6 weeks

**Week 1-2:** Infrastructure
- Laravel project setup with database (tables transfer directly — no schema rewrite needed)
- Implement interfaces from Phase 1 with Laravel adapters
- Migrate Database class → Laravel DB facade
- Migrate auth system (5 roles, 15 capabilities → Spatie permissions)

**Week 2-3:** Core Modules
- Migrate repositories (BaseRepository → Eloquent base)
- Migrate BookingService, PricingService, AvailabilityService
- Migrate EventDispatcher → Laravel Events with typed event classes

**Week 3-4:** API Layer
- Convert 205 REST routes to Laravel route files
- Convert controllers (WP_REST_Request → Laravel Request)
- Migrate cron jobs → Laravel Scheduler

**Week 4-6:** Integration Modules
- Migrate Booking.com, Odoo, WhatsApp connectors (swap wp_remote_post → Http facade)
- Migrate notification system (wp_mail → Laravel Mail)
- Migrate remaining modules

### Phase 3: Migrate Frontend (React) — 3-4 weeks

The API response shapes are the migration bridge. Document them from current JS files:
```text
assets/js/admin/*.js   — 26 files define the data contract
assets/js/core/api.js  — API client shows all endpoint patterns
```

Build React components consuming the same shapes from Laravel endpoints.

### Total Estimated Effort: 8-12 weeks

The codebase is in **better shape than typical WordPress plugins** for migration because:
1. No custom post types (custom DB tables transfer directly)
2. Constructor injection everywhere (dependencies are swappable)
3. Custom abstractions already exist (Container, EventDispatcher, CacheManager)
4. REST API already returns JSON (API contract is documented in JS files)
5. No jQuery (less legacy frontend debt)

The main cost is volume — 28 modules, 205 routes, 39 controllers — not complexity.
