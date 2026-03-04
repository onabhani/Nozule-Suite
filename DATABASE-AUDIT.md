# Nozule PMS — Database Layer Deep Audit

**Date:** 2026-03-04
**Scope:** Tables, schema versioning, serialization, referential integrity, wp_options abuse, wp_users foreign keys

---

## 1. Complete Table Inventory

### Custom Tables (45 total — all use `{wp_prefix}nzl_` prefix)

Every table below is created via `dbDelta()` in migration files. **Zero data is stored in `wp_posts` or `wp_postmeta`.** No custom post types exist.

| # | Table | Migration | Purpose |
|---|-------|-----------|---------|
| 1 | `nzl_room_types` | 001 | Room type catalog |
| 2 | `nzl_rooms` | 001 | Individual room units |
| 3 | `nzl_room_inventory` | 001 | Per-date availability/pricing |
| 4 | `nzl_rate_plans` | 001 | Pricing plans |
| 5 | `nzl_seasonal_rates` | 001 | Date-range rate modifiers |
| 6 | `nzl_guests` | 001 | Guest profiles |
| 7 | `nzl_bookings` | 001 | Reservations |
| 8 | `nzl_booking_logs` | 001 | Booking audit trail |
| 9 | `nzl_payments` | 001 | Payment records |
| 10 | `nzl_notifications` | 001 | Notification queue |
| 11 | `nzl_channel_mappings` | 001 | OTA room/rate mapping |
| 12 | `nzl_settings` | 001 | Plugin configuration (own table, NOT wp_options) |
| 13 | `nzl_housekeeping_tasks` | 003 | Housekeeping work orders |
| 14 | `nzl_taxes` | 003 | Tax rules |
| 15 | `nzl_folios` | 003 | Guest billing folios |
| 16 | `nzl_folio_items` | 003 | Folio line items |
| 17 | `nzl_night_audits` | 003 | Nightly audit snapshots |
| 18 | `nzl_group_bookings` | 003 | Group reservations |
| 19 | `nzl_group_booking_rooms` | 003 | Group-to-room assignments |
| 20 | `nzl_promo_codes` | 004 | Promotional codes |
| 21 | `nzl_email_templates` | 004 | Email template definitions |
| 22 | `nzl_email_log` | 004 | Email send history |
| 23 | `nzl_currencies` | 004 | Supported currencies |
| 24 | `nzl_exchange_rates` | 004 | Currency exchange rates |
| 25 | `nzl_guest_documents` | 004 | Passport/ID scans |
| 26 | `nzl_occupancy_rules` | 005 | Dynamic pricing: occupancy-based |
| 27 | `nzl_dow_rules` | 005 | Dynamic pricing: day-of-week |
| 28 | `nzl_event_overrides` | 005 | Dynamic pricing: event-based |
| 29 | `nzl_review_requests` | 006 | Post-checkout review solicitation |
| 30 | `nzl_review_settings` | 006 | Review module config (own table) |
| 31 | `nzl_whatsapp_templates` | 006 | WhatsApp message templates |
| 32 | `nzl_whatsapp_log` | 006 | WhatsApp send history |
| 33 | `nzl_whatsapp_settings` | 006 | WhatsApp config (own table) |
| 34 | `nzl_channel_connections` | 007 | OTA channel credentials |
| 35 | `nzl_channel_sync_log` | 007 | Channel sync history |
| 36 | `nzl_channel_rate_map` | 007 | Channel rate mappings |
| 37 | `nzl_rate_restrictions` | 008 | Rate restriction rules |
| 38 | `nzl_demand_forecasts` | 009 | Demand prediction data |
| 39 | `nzl_loyalty_tiers` | 009 | Loyalty program tiers |
| 40 | `nzl_loyalty_members` | 009 | Loyalty member records |
| 41 | `nzl_loyalty_transactions` | 009 | Points earn/burn history |
| 42 | `nzl_loyalty_rewards` | 009 | Redeemable rewards |
| 43 | `nzl_pos_outlets` | 009 | POS outlet definitions |
| 44 | `nzl_pos_items` | 009 | POS menu items |
| 45 | `nzl_pos_orders` | 009 | POS orders |
| 46 | `nzl_pos_order_items` | 009 | POS order line items |
| 47 | `nzl_rate_shop_competitors` | 009 | Rate shopping competitors |
| 48 | `nzl_rate_shop_results` | 009 | Scraped competitor rates |
| 49 | `nzl_rate_shop_alerts` | 009 | Rate parity alerts |
| 50 | `nzl_brands` | 009 | White-label brand config |
| 51 | `nzl_properties` | 010 | Multi-property definitions |

*(51 tables total including the property_id column additions in migration 011)*

### WordPress Native Tables Used

| Table | How Used | Classification |
|-------|----------|----------------|
| `wp_options` | 3 plugin option keys (see section 5) + transient cache | **Legitimate — pure config** |
| `wp_users` | Employee/staff storage, user authentication | **Deep dependency** (see section 6) |
| `wp_usermeta` | Role/capability storage, capabilities meta lookup | **Deep dependency** (via WordPress roles system) |
| `wp_posts` | **NOT USED** | N/A |
| `wp_postmeta` | **NOT USED** | N/A |
| `wp_termmeta` | **NOT USED** | N/A |
| `wp_terms` | **NOT USED** | N/A |
| `wp_term_taxonomy` | **NOT USED** | N/A |
| `wp_comments` | **NOT USED** | N/A |

### Classification Summary

```
CUSTOM TABLES:        51 tables  — All business data lives here
wp_options:           3 keys     — Pure plugin config (version, activation timestamp, uninstall flag)
wp_options transient: ~N keys    — Cache layer (CacheManager + SSE events)
wp_users:             USED       — Employee identity, authentication, role assignment
wp_usermeta:          USED       — Role/capability storage (WordPress managed)
wp_posts/postmeta:    NOT USED   — Zero post type abuse
```

**Verdict: CLEAN.** All operational/business data is in custom tables. The only WordPress native table dependencies are `wp_users` (for identity) and `wp_options` (for 3 config keys + cache transients).

---

## 2. Schema Versioning and Upgrade Path

### Version Constants

```
nozule.php:27         — define('NZL_DB_VERSION', '1.8.0')
nozule.php:26         — define('NZL_VERSION', '1.8.0')
```

Both constants are set to `1.8.0`. The DB version tracks schema separately from plugin version (though currently equal).

### Upgrade Mechanism

```php
// includes/Core/Activator.php:81-93
public static function maybeUpgrade(): void {
    $installed = get_option('nzl_db_version', '0');
    if (version_compare($installed, NZL_DB_VERSION, '>=')) {
        return;
    }
    self::createTables();   // Runs ALL migrations again
    self::createRoles();    // Re-creates roles
    self::seedDefaultSettings(); // Seeds only if empty
    self::scheduleEvents(); // Re-schedules cron
    update_option('nzl_db_version', NZL_DB_VERSION);
}
```

Called on every `init` hook (`nozule.php:81`).

### How Migrations Work

All 11 migrations are called on **every** activation/upgrade via `Activator::createTables()`. They rely on `dbDelta()` idempotency:

- `dbDelta()` compares existing schema vs CREATE TABLE SQL and only adds/modifies what's different
- Seed data migrations check `COUNT(*) > 0` before inserting (idempotent)
- Column additions (migration 004, 011) check `INFORMATION_SCHEMA.COLUMNS` before ALTER

### Issues Found

**ISSUE 2a: No per-migration tracking — all migrations run every time**

```
Activator.php:43-72 — createTables() calls ALL 11 migration functions unconditionally
```

There is no migration tracking table. When `NZL_DB_VERSION` changes, every migration file re-executes. This works because `dbDelta()` is idempotent for CREATE TABLE, and seed data checks for existing rows — but it's fragile:

- A migration that does `ALTER TABLE ... ADD COLUMN` must guard itself (migration 004:157-168 and 011:59-68 do this correctly)
- A migration that does destructive changes (e.g., rename column) would be dangerous to re-run
- There's no rollback mechanism (migration 008 has a `_down()` function but it's never called)

**ISSUE 2b: Version mismatch between composer.json and plugin**

```
composer.json:   "version": "1.6.0"
package.json:    "version": "1.6.0"
nozule.php:      NZL_VERSION = '1.8.0'
```

Not a runtime issue, but indicates the config files lag behind.

**ISSUE 2c: dbDelta() cannot create FOREIGN KEY constraints**

WordPress `dbDelta()` silently drops FOREIGN KEY clauses. The codebase correctly avoids them (zero FOREIGN KEY or REFERENCES keywords in any migration). But this means referential integrity is entirely application-side (see section 4).

**Verdict: ADEQUATE but fragile.** The version check + dbDelta idempotency works for additive changes. Destructive migrations would require manual guards. For Laravel migration, this translates cleanly to Laravel's migration system since all CREATE TABLE statements are standard SQL.

---

## 3. Serialized PHP Objects

### serialize() / unserialize() / maybe_serialize() / maybe_unserialize()

```
ZERO occurrences found across the entire codebase.
```

**The plugin never uses PHP serialization.** This is excellent for migration.

### JSON Columns (the actual storage format)

All structured data is stored as JSON in LONGTEXT columns. Columns identified:

| Table | Column | Data Stored | Migration |
|-------|--------|-------------|-----------|
| `nzl_room_types` | `amenities` | `["wifi","minibar","ac"]` | 001:31 |
| `nzl_room_types` | `images` | `["url1","url2"]` | 001:32 |
| `nzl_rooms` | `amenities_override` | JSON overrides | 001:50 |
| `nzl_guests` | `tags` | `["vip","returning"]` | 001:151 |
| `nzl_seasonal_rates` | `days_of_week` | `[0,1,2,3,4,5,6]` | 001:121 |
| `nzl_payments` | `gateway_response` | Payment gateway JSON | 001:251 |
| `nzl_notifications` | `template_vars` | Template variable map | 001:276 |
| `nzl_folio_items` | `tax_json` | Tax breakdown JSON | 003:98 |
| `nzl_promo_codes` | `applicable_room_types` | Room type ID list | 004:33 |
| `nzl_promo_codes` | `applicable_sources` | Booking source list | 004:34 |
| `nzl_email_templates` | `variables` | Template var names JSON | 004:55 |
| `nzl_whatsapp_templates` | `variables` | Template var names JSON | 006:82 |
| `nzl_guest_documents` | `ocr_data` | OCR extraction JSON | 004:140 |
| `nzl_demand_forecasts` | `factors` | Forecast factor data | 009:36 |
| `nzl_loyalty_tiers` | `benefits` | Tier benefit descriptions | 009:54 |
| `nzl_properties` | `photos` | Photo URL array | 010:50 |
| `nzl_properties` | `facilities` | Facility list JSON | 010:51 |
| `nzl_properties` | `policies` | Policy text JSON | 010:52 |
| `nzl_properties` | `social_links` | Social media URLs JSON | 010:53 |
| `nzl_channel_connections` | `credentials` | Channel auth credentials | 007:18 |
| `nzl_rate_restrictions` | `days_of_week` | Day filter string | 008:33 |
| `nzl_settings` | `option_value` | Any setting value (may be JSON) | 001:325 |
| `nzl_review_settings` | `setting_value` | Review config values | 006:39 |
| `nzl_whatsapp_settings` | `setting_value` | WhatsApp config values | 006:117 |

JSON is encoded via `wp_json_encode()` (3 occurrences) and standard `json_encode()`. Decoded via `json_decode($value, true)` throughout.

**Verdict: CLEAN.** JSON-only, no PHP serialization. All JSON columns transfer directly to Laravel with `$casts` on Eloquent models. Consider adding MySQL JSON column type in Laravel schema for columns that are always JSON.

---

## 4. Foreign Keys and Referential Integrity

### Database-Level Constraints

```
ZERO foreign key constraints exist in any migration.
ZERO REFERENCES, ON DELETE, ON UPDATE, or CONSTRAINT keywords found.
```

This is expected — `dbDelta()` silently strips foreign key definitions.

### Application-Level Validation

Referential integrity is enforced **partially** at the application level. Here's the assessment:

#### Validated Before Insert/Update (GOOD)

| Relationship | Validated Where |
|-------------|----------------|
| `bookings.guest_id → guests.id` | `BookingService` creates/looks up guest before booking |
| `bookings.room_type_id → room_types.id` | `BookingValidator` checks room type exists |
| `bookings.rate_plan_id → rate_plans.id` | `BookingValidator` checks rate plan exists |
| `bookings.room_id → rooms.id` | Check-in process validates room exists and is available |
| `promo_codes` usage | `PromoCodeRepository` validates code exists and is valid |
| `loyalty_members.guest_id → guests.id` | Service checks guest exists before enrollment |

#### NOT Validated (GAPS)

| Relationship | Risk | Impact |
|-------------|------|--------|
| `rooms.room_type_id → room_types.id` | Room type could be deleted while rooms reference it | Orphaned rooms, broken inventory |
| `seasonal_rates.rate_plan_id → rate_plans.id` | Rate plan deletion leaves orphaned seasonal rates | Stale pricing data |
| `seasonal_rates.room_type_id → room_types.id` | Same | Stale pricing data |
| `channel_mappings.room_type_id → room_types.id` | Room type deletion breaks channel sync | OTA sync failures |
| `channel_mappings.rate_plan_id → rate_plans.id` | Rate plan deletion breaks channel sync | OTA sync failures |
| `folio_items.folio_id → folios.id` | No cascade on folio deletion | Orphaned line items |
| `group_booking_rooms.group_booking_id` | No cascade validation | Orphaned room assignments |
| `booking_logs.booking_id → bookings.id` | Logs could reference deleted bookings | Minor — logs are append-only |
| `pos_order_items.order_id → pos_orders.id` | No cascade validation | Orphaned POS items |
| `loyalty_transactions.member_id` | No cascade validation | Orphaned transactions |

#### Cascade Delete Behavior

**No cascade deletes exist anywhere.** The `uninstall.php` drops entire tables, but during normal operation, there is no cleanup when parent records are deleted. For example:

- Deleting a room type does NOT clean up: rooms, inventory, rate plans, seasonal rates, channel mappings, bookings
- Deleting a guest does NOT clean up: bookings, folios, documents, loyalty membership, notifications

**Verdict: MIGRATION RISK.** When creating Laravel migrations, you MUST add foreign key constraints with appropriate `ON DELETE` behavior (CASCADE, SET NULL, or RESTRICT). The current app likely doesn't expose "hard delete" operations for core entities, but the lack of constraints is a data integrity time bomb. Document the intended cascade behavior before migrating.

---

## 5. wp_options Usage — Complete Key Inventory

### Every option key the plugin writes to `wp_options`:

| Option Key | Set Where | Read Where | Classification |
|------------|-----------|------------|----------------|
| `nzl_db_version` | `Activator.php:20,93` | `Activator.php:82` | **Pure config** — schema version tracking |
| `nzl_activated_at` | `Activator.php:21` | Never read in code | **Pure config** — activation timestamp (dead read) |
| `nzl_remove_data_on_uninstall` | Never set in code (user sets manually) | `uninstall.php:15` | **Pure config** — uninstall behavior flag |

### WordPress native options READ (not written):

| Option Key | Read Where | Purpose |
|------------|------------|---------|
| `admin_email` | `migrations/002_seed_data.php:25`, `WhatsAppController.php:474`, `WhatsAppService.php:231`, `EmailService.php:163,244`, `EmailTemplateController.php:459`, `NotificationService.php:287,309`, `TemplateService.php:292` | Fallback email for "from" address |
| `date_format` | `Plugin.php:256`, `NotificationService.php:648`, `TemplateService.php:117` | WordPress date format for display |

### Transient keys used (stored in wp_options):

| Transient Pattern | Set Where | Read Where | Purpose |
|-------------------|-----------|------------|---------|
| `nzl_{anything}` | `CacheManager.php:34` | `CacheManager.php:24` | General plugin cache (TTL-based) |
| `nzl_sse_events` | `SSEController.php:149` | `SSEController.php:137,158` | SSE event queue |

### Assessment

**VERDICT: CLEAN — No wp_options abuse.**

The plugin writes exactly **3 option keys**, all pure configuration:
- `nzl_db_version` — schema version (required for upgrade path)
- `nzl_activated_at` — activation timestamp (informational, never read)
- `nzl_remove_data_on_uninstall` — uninstall behavior flag

**No business/operational data in wp_options.** No reservation counts, no last-run timestamps, no queue state, no counters. All business configuration lives in the custom `nzl_settings` table.

The `admin_email` and `date_format` reads are benign fallbacks — the plugin's own `SettingsManager` is the primary source for these values.

Transient usage is limited to cache and SSE events — both ephemeral by nature.

---

## 6. WordPress User IDs as Foreign Keys in Custom Tables

### Complete Column Inventory

Every custom table column that stores a WordPress `wp_users.ID`:

| Table | Column | Nullable | Indexed | Set By | Purpose |
|-------|--------|----------|---------|--------|---------|
| `nzl_guests` | `wp_user_id` | YES | No | `GuestService` | Link guest profile to WP user (optional) |
| `nzl_bookings` | `confirmed_by` | YES | No | `BookingService:204` via `get_current_user_id()` | Staff who confirmed |
| `nzl_bookings` | `checked_in_by` | YES | No | `BookingService:334` via `get_current_user_id()` | Staff who checked in |
| `nzl_bookings` | `checked_out_by` | YES | No | `BookingService:384` via `get_current_user_id()` | Staff who checked out |
| `nzl_bookings` | `cancelled_by` | YES | No | `BookingService:265` via `get_current_user_id()` | Staff who cancelled |
| `nzl_booking_logs` | `user_id` | YES | No | `BookingService:152,541` via `get_current_user_id()` | Who made the change |
| `nzl_payments` | `received_by` | YES | No | `BookingService` | Staff who received payment |
| `nzl_housekeeping_tasks` | `assigned_to` | YES | YES | `HousekeepingController` | Housekeeper assigned |
| `nzl_housekeeping_tasks` | `created_by` | YES | No | `HousekeepingController` | Staff who created task |
| `nzl_folios` | `closed_by` | YES | No | `BillingController` | Staff who closed folio |
| `nzl_folios` | `created_by` | YES | No | `BillingController` | Staff who created folio |
| `nzl_folio_items` | `posted_by` | YES | No | `BillingController` | Staff who posted charge |
| `nzl_night_audits` | `run_by` | YES | No | `NightAuditService` | Staff who ran audit |
| `nzl_group_bookings` | `created_by` | YES | No | `GroupController` | Staff who created group |
| `nzl_promo_codes` | `created_by` | YES | No | `PromotionsController` | Staff who created promo |
| `nzl_guest_documents` | `verified_by` | YES | No | `DocumentsController` | Staff who verified doc |
| `nzl_loyalty_transactions` | `created_by` | YES | No | `LoyaltyController` | Staff who created txn |
| `nzl_pos_orders` | `created_by` | YES | No | `POSController` | Staff who created order |

**Total: 18 columns across 11 tables reference `wp_users.ID`.**

### How User IDs Enter the System

All 18 columns are populated via one of these paths:

1. **`get_current_user_id()`** — Called in `BookingService.php:132,152,334,384,541` and various controllers. Returns the logged-in WordPress user's ID.

2. **`wp_insert_user()`** — Called in `EmployeeController.php:164` to create new hotel staff as WordPress users.

3. **`get_users()` / `get_userdata()`** — Called in `EmployeeController.php:113,182,196,269,283,404` and `HousekeepingController.php:309` to list/read staff users.

### Employee Module Deep Dependency

The `EmployeeController` (`includes/Modules/Employees/Controllers/EmployeeController.php`) is **100% coupled to WordPress user management**:

- **Line 164**: Creates employees via `wp_insert_user()` — employees ARE WordPress users
- **Line 113**: Lists employees via `get_users()` with meta_query on capabilities
- **Line 371**: Reads raw `wp_capabilities` from `wp_usermeta` for role detection
- **Line 244**: Updates employees via `wp_update_user()`
- **Line 304**: Deactivates by setting role to `subscriber`
- **No separate `nzl_employees` table exists** — the employee entity is the WordPress user record

### Migration Impact Assessment

**CRITICAL DEPENDENCY.** WordPress user IDs are embedded in 18 columns across 11 tables. This creates a hard migration dependency:

1. **User migration must happen first** — All 18 FK columns must be re-mapped to new user IDs in Laravel
2. **Employee module needs full rewrite** — No custom table exists; employees are WordPress users
3. **Audit trail integrity** — `confirmed_by`, `checked_in_by`, etc. will lose meaning if user IDs change
4. **Guest ↔ user link is optional** — `nzl_guests.wp_user_id` is nullable and rarely used (guests mostly don't have WP accounts)

### Recommended Migration Approach

```
Phase 1: Create Laravel `users` table
  - Import all wp_users with nzl_* roles
  - Create ID mapping table: wp_user_id → laravel_user_id

Phase 2: Create `employees` table in Laravel
  - Extract employee data from wp_users + wp_usermeta
  - Add columns currently missing: department, hire_date, etc.

Phase 3: Update FK columns
  - Run migration script to remap all 18 columns using the ID mapping
  - Consider keeping original wp_user_id as a migration reference column

Phase 4: Handle guests.wp_user_id
  - Remap or nullify — most guests won't have corresponding Laravel users
  - Consider a separate guest self-service auth system
```

---

## 7. Schema Bug Found During Audit

### BUG: Missing `resolved_by` column in `nzl_rate_shop_alerts`

**Migration 009** (`migrations/009_create_phase4_tables.php:249-267`) defines `nzl_rate_shop_alerts` with `resolved_at` but **no `resolved_by` column**.

However, the application code expects it:

```
includes/Modules/RateShopping/Models/ParityAlert.php:21     — @property int|null $resolved_by
includes/Modules/RateShopping/Models/ParityAlert.php:44     — 'resolved_by' => 'int' (in $casts)
includes/Modules/RateShopping/Repositories/RateShopRepository.php:307 — 'resolved_by' => get_current_user_id()
```

This will cause a **database error** when any user tries to resolve a rate parity alert. The UPDATE statement will fail because the column doesn't exist.

**Fix:** Add `resolved_by BIGINT UNSIGNED DEFAULT NULL` to the CREATE TABLE statement in migration 009, or create migration 012 to add it via ALTER TABLE.

---

## Summary of Findings

| Question | Answer | Migration Risk |
|----------|--------|----------------|
| All operational tables custom? | **YES** — 51 custom tables, zero wp_posts/wp_postmeta | LOW |
| Any wp_options abuse? | **NO** — only 3 config keys, no business data | LOW |
| dbDelta + version tracking? | **YES** but no per-migration tracking; all re-run on upgrade | LOW-MEDIUM |
| Serialized PHP objects? | **ZERO** — all structured data is JSON | NONE |
| Foreign key constraints? | **ZERO** — no DB-level constraints, partial app-level validation | MEDIUM |
| Referential integrity gaps? | **YES** — no cascade deletes, orphan risk on entity deletion | MEDIUM |
| wp_users as foreign keys? | **18 columns in 11 tables** + employees ARE WordPress users | HIGH |
| Transient/cache in wp_options? | **YES** — CacheManager + SSE use transients (ephemeral, acceptable) | LOW |

---
---

# Nozule PMS — API Layer Deep Audit

**Date:** 2026-03-04
**Scope:** REST routes, ajax handlers, authentication, response structure, input validation

---

## 8. Complete API Route Inventory

### Data Transport: 100% WP REST API

```
admin-ajax.php handlers:  ZERO  (no wp_ajax_* hooks found anywhere)
Direct $_POST/$_GET:      ZERO  (no form processing outside REST)
Shortcode form handlers:  ZERO
```

**All data operations go through WP REST API** under the `nozule/v1` namespace. This is the cleanest possible starting point for a Laravel migration — every endpoint maps directly to a Laravel route.

### Route Registration Architecture

Routes are registered via two mechanisms:

1. **Central registrar** — `includes/API/RestController.php` registers public, staff, and admin routes using `registerCrudRoutes()` helper
2. **Self-registering controllers** — 37 module controllers call `register_rest_route()` in their own `registerRoutes()` method, bootstrapped from `Plugin.php:170-175`

### Complete Route Table

#### PUBLIC ENDPOINTS (permission_callback: `__return_true`)

| # | Method | Route | Controller | Purpose |
|---|--------|-------|------------|---------|
| 1 | GET | `/room-types` | RoomTypeController::index | List active room types |
| 2 | GET | `/room-types/{id}` | RoomTypeController::show | Get single room type |
| 3 | GET | `/availability` | AvailabilityController::check | Search available rooms |
| 4 | POST | `/bookings` | BookingController::store | Create guest booking |
| 5 | GET | `/bookings/{booking_number}` | BookingController::show | Public booking lookup (requires email) |
| 6 | POST | `/bookings/{booking_number}/cancel` | BookingController::cancel | Guest self-cancel |
| 7 | GET | `/settings/public` | SettingsController::publicSettings | Safe public config subset |
| 8 | POST | `/promo-codes/validate` | PromoCodeController::validate | Validate promo code |
| 9 | GET | `/metasearch/google-hpa-feed` | MetasearchController::getGoogleFeed | Google HPA XML feed |

#### STAFF ENDPOINTS (permission: `manage_options OR nzl_staff`)

| # | Method | Route | Controller | Purpose |
|---|--------|-------|------------|---------|
| 8 | GET | `/admin/bookings` | AdminBookingController::index | List bookings |
| 9 | POST | `/admin/bookings` | AdminBookingController::store | Create booking (staff) |
| 10 | GET | `/admin/bookings/{id}` | AdminBookingController::show | Get booking detail |
| 11 | PUT | `/admin/bookings/{id}` | AdminBookingController::update | Update booking |
| 12 | POST | `/admin/bookings/{id}/confirm` | AdminBookingController::confirm | Confirm booking |
| 13 | POST | `/admin/bookings/{id}/cancel` | AdminBookingController::cancel | Cancel booking |
| 14 | POST | `/admin/bookings/{id}/check-in` | AdminBookingController::checkIn | Check-in |
| 15 | POST | `/admin/bookings/{id}/check-out` | AdminBookingController::checkOut | Check-out |
| 16 | POST | `/admin/bookings/{id}/assign-room` | AdminBookingController::assignRoom | Assign room |
| 17 | POST | `/admin/bookings/{id}/payments` | AdminBookingController::payments | Record payment |
| 18 | GET | `/admin/bookings/{id}/logs` | AdminBookingController::logs | Audit trail |
| 19 | GET | `/admin/dashboard/stats` | DashboardController::stats | Today's stats |
| 20 | GET | `/admin/dashboard/arrivals` | DashboardController::arrivals | Today's arrivals |
| 21 | GET | `/admin/dashboard/departures` | DashboardController::departures | Today's departures |
| 22 | GET | `/admin/dashboard/in-house` | DashboardController::inHouse | In-house guests |
| 23 | GET | `/admin/calendar` | CalendarController::index | Calendar view |
| 24 | GET | `/admin/guests` | GuestController::index | List guests |
| 25 | GET | `/admin/guests/{id}` | GuestController::show | Guest detail |

#### ADMIN ENDPOINTS (permission: `manage_options OR nzl_admin`)

| # | Method | Route | Controller | Purpose |
|---|--------|-------|------------|---------|
| 26-30 | CRUD | `/admin/room-types[/{id}]` | RoomTypeController | Room type management |
| 31-35 | CRUD | `/admin/rooms[/{id}]` | RoomController | Room management |
| 36-40 | CRUD | `/admin/rate-plans[/{id}]` | RatePlanController | Rate plan management |
| 41-45 | CRUD | `/admin/seasonal-rates[/{id}]` | SeasonalRateController | Seasonal rate management |
| 46 | GET | `/admin/settings` | SettingsController::index | Get all settings |
| 47 | PUT | `/admin/settings` | SettingsController::update | Update settings |
| 48 | GET | `/admin/reports` | ReportController::index | Report summary |
| 49 | GET | `/admin/reports/revenue` | ReportController::revenue | Revenue report |
| 50 | GET | `/admin/reports/occupancy` | ReportController::occupancy | Occupancy report |
| 51 | GET | `/admin/reports/sources` | ReportController::sources | Booking sources report |
| 52 | GET | `/admin/reports/export` | ReportController::export | Export report (streams file) |
| 53 | GET | `/admin/channels` | ChannelController::index | List channels |
| 54 | POST | `/admin/channels` | ChannelController::store | Create channel mapping |
| 55 | GET | `/admin/channels/{id}` | ChannelController::show | Channel detail |
| 56 | PUT | `/admin/channels/{id}` | ChannelController::update | Update channel |
| 57 | DELETE | `/admin/channels/{id}` | ChannelController::destroy | Delete channel |
| 58 | POST | `/admin/channels/{id}/sync` | ChannelController::sync | Trigger sync |

#### MODULE SELF-REGISTERED ENDPOINTS

| # | Method | Route | Controller | Permission | Purpose |
|---|--------|-------|------------|------------|---------|
| 59 | GET | `/admin/events/stream` | SSEController::stream | nzl_staff | SSE real-time events |
| 60-62 | GET,POST,PUT,DEL | `/admin/employees[/{id}]` | EmployeeController | manage_options OR nzl_manage_employees | Staff CRUD |
| 63 | GET | `/admin/employees/capabilities` | EmployeeController::getCapabilities | manage_options OR nzl_manage_employees | Capability list |
| 64-65 | GET,PUT | `/admin/property` | PropertyController | manage_options OR nzl_admin | Property detail |
| 66-70 | CRUD | `/admin/inventory[/{id}]` | InventoryController | nzl_admin OR nzl_manage_inventory | Inventory mgmt |
| 71 | POST | `/admin/inventory/bulk-update` | InventoryController | nzl_admin OR nzl_manage_inventory | Bulk update |
| 72 | POST | `/admin/inventory/initialize` | InventoryController | nzl_admin OR nzl_manage_inventory | Initialize dates |
| 73-77 | CRUD | `/admin/housekeeping[/{id}]` | HousekeepingController | nzl_admin OR nzl_manage_housekeeping | Tasks |
| 78 | GET | `/admin/housekeeping/staff` | HousekeepingController | nzl_manage_housekeeping | Staff list |
| 79-83 | CRUD | `/admin/taxes[/{id}]` | TaxController | nzl_admin OR nzl_manage_billing | Tax rules |
| 84-88 | CRUD | `/admin/folios[/{id}]` | FolioController | nzl_admin OR nzl_manage_billing | Folio mgmt |
| 89 | POST | `/admin/folios/{id}/items` | FolioController | nzl_manage_billing | Add folio item |
| 90 | POST | `/admin/folios/{id}/close` | FolioController | nzl_manage_billing | Close folio |
| 91-95 | CRUD | `/admin/promo-codes[/{id}]` | PromoCodeController | nzl_admin | Promo management |
| 96-100 | CRUD | `/admin/email-templates[/{id}]` | EmailTemplateController | nzl_admin OR nzl_manage_messaging | Email templates |
| 101 | POST | `/admin/email-templates/{id}/preview` | EmailTemplateController | nzl_manage_messaging | Preview template |
| 102-105 | CRUD | `/admin/currencies[/{id}]` | CurrencyController | nzl_admin | Currency mgmt |
| 106 | GET | `/admin/documents/{guest_id}/documents` | GuestDocumentController | nzl_manage_guests | Guest docs list |
| 107 | POST | `/admin/documents/{guest_id}/documents` | GuestDocumentController | nzl_manage_guests | Upload doc |
| 108 | GET | `/admin/documents/{id}` | GuestDocumentController::show | nzl_manage_guests | Doc detail |
| 109 | DELETE | `/admin/documents/{id}` | GuestDocumentController::destroy | nzl_manage_guests | Delete doc |
| 110 | POST | `/admin/documents/{id}/verify` | GuestDocumentController | nzl_manage_guests | Verify doc |
| 111 | POST | `/admin/documents/{id}/ocr` | GuestDocumentController | nzl_manage_guests | Run OCR |
| 112 | POST | `/admin/night-audit/run` | NightAuditController | nzl_admin | Run night audit |
| 113 | GET | `/admin/night-audit/history` | NightAuditController | nzl_admin | Audit history |
| 114 | GET | `/admin/night-audit/{date}` | NightAuditController | nzl_admin | Specific audit |
| 115-119 | CRUD | `/admin/groups[/{id}]` | GroupBookingController | nzl_staff | Group bookings |
| 120 | POST | `/admin/groups/{id}/confirm` | GroupBookingController | nzl_staff | Confirm group |
| 121 | POST | `/admin/groups/{id}/cancel` | GroupBookingController | nzl_staff | Cancel group |
| 122-126 | CRUD | `/admin/dynamic-pricing/occupancy[/{id}]` | DynamicPricingController | nzl_manage_rates | Occupancy rules |
| 127-131 | CRUD | `/admin/dynamic-pricing/dow[/{id}]` | DynamicPricingController | nzl_manage_rates | Day-of-week rules |
| 132-136 | CRUD | `/admin/dynamic-pricing/events[/{id}]` | DynamicPricingController | nzl_manage_rates | Event overrides |
| 137-141 | CRUD | `/admin/rate-restrictions[/{id}]` | RateRestrictionController | nzl_manage_rates | Rate restrictions |
| 142-143 | GET,PUT | `/admin/reviews/settings` | ReviewController | nzl_admin | Review settings |
| 144 | GET | `/admin/reviews/requests` | ReviewController | nzl_admin | Review request list |
| 145 | GET | `/reviews/track/{id}` | ReviewController::track | __return_true (public) | Pixel tracking |
| 146-148 | GET,PUT | `/admin/whatsapp/settings` | WhatsAppController | nzl_admin | WhatsApp config |
| 149-153 | CRUD | `/admin/whatsapp/templates[/{id}]` | WhatsAppController | nzl_manage_messaging | WA templates |
| 154 | GET | `/admin/whatsapp/log` | WhatsAppController | nzl_manage_messaging | WA send log |
| 155 | POST | `/admin/whatsapp/send` | WhatsAppController | nzl_manage_messaging | Send WA message |
| 156 | POST | `/admin/whatsapp/test` | WhatsAppController | nzl_manage_messaging | Test WA config |
| 157-159 | GET,POST,DEL | `/admin/channel-sync[/{id}]` | ChannelSyncController | nzl_manage_channels | Sync connections |
| 160-164 | CRUD | `/admin/forecasting[/{id}]` | ForecastController | nzl_admin | Demand forecasts |
| 165 | POST | `/admin/forecasting/generate` | ForecastController | nzl_admin | Generate forecast |
| 166-170 | CRUD | `/admin/loyalty/tiers[/{id}]` | LoyaltyController | nzl_admin | Loyalty tiers |
| 171-175 | CRUD | `/admin/loyalty/rewards[/{id}]` | LoyaltyController | nzl_admin | Loyalty rewards |
| 176-178 | GET,POST | `/admin/loyalty/members[/{id}]` | LoyaltyController | nzl_admin | Loyalty members |
| 179 | GET | `/admin/loyalty/members/{id}/transactions` | LoyaltyController | nzl_admin | Member txns |
| 180 | POST | `/admin/loyalty/members/{id}/points` | LoyaltyController | nzl_admin | Adjust points |
| 181-185 | CRUD | `/admin/pos/outlets[/{id}]` | POSController | nzl_admin OR nzl_manage_pos | POS outlets |
| 186-190 | CRUD | `/admin/pos/items[/{id}]` | POSController | nzl_manage_pos | POS menu items |
| 191-195 | CRUD | `/admin/pos/orders[/{id}]` | POSController | nzl_manage_pos | POS orders |
| 196 | POST | `/admin/pos/orders/{id}/charge-to-folio` | POSController | nzl_manage_pos | Charge to folio |
| 197-199 | CRUD | `/admin/rate-shopping/competitors[/{id}]` | RateShopController | nzl_admin | Competitors |
| 200 | GET | `/admin/rate-shopping/results` | RateShopController | nzl_admin | Rate results |
| 201 | POST | `/admin/rate-shopping/fetch` | RateShopController | nzl_admin | Fetch rates |
| 202 | GET | `/admin/rate-shopping/alerts` | RateShopController | nzl_admin | Parity alerts |
| 203 | POST | `/admin/rate-shopping/alerts/{id}/resolve` | RateShopController | nzl_admin | Resolve alert |
| 204-206 | CRUD | `/admin/brands[/{id}]` | BrandController | manage_options | Brand config |
| 207 | POST | `/admin/brands/{id}/activate` | BrandController | manage_options | Activate brand |
| 208-210 | CRUD | `/admin/integrations[/{id}]` | IntegrationController | manage_options | Integrations |
| 211 | POST | `/admin/integrations/{id}/test` | IntegrationController | manage_options | Test integration |
| 212 | GET | `/admin/metasearch/feeds/{type}` | MetasearchController | __return_true (public) | Metasearch XML feeds |
| 213 | GET | `/admin/metasearch/settings` | MetasearchController | nzl_admin | Metasearch config |
| 214 | PUT | `/admin/metasearch/settings` | MetasearchController | nzl_admin | Update config |

**Total: ~214 route registrations across 39 files.**

### Public Endpoints Summary

| # | Public Endpoint | Risk Assessment |
|---|----------------|-----------------|
| 1 | GET `/room-types` | LOW — public catalog data |
| 2 | GET `/room-types/{id}` | LOW — public catalog data |
| 3 | GET `/availability` | LOW — no sensitive data exposed |
| 4 | POST `/bookings` | MEDIUM — no rate limiting, no CAPTCHA |
| 5 | GET `/bookings/{booking_number}` | MEDIUM — email-based auth (weak) |
| 6 | POST `/bookings/{booking_number}/cancel` | MEDIUM — email-based auth (weak) |
| 7 | GET `/settings/public` | LOW — filtered safe subset only |
| 8 | POST `/promo-codes/validate` | LOW — validates promo code, no sensitive data |
| 9 | GET `/reviews/track/{id}` | LOW — 1x1 tracking pixel |
| 10 | GET `/metasearch/google-hpa-feed` | LOW — public XML feed for Google Hotel Ads |

---

## 9. Authentication and Authorization

### Auth Mechanism

**WordPress cookie-based session authentication only.** No JWT, no OAuth, no API keys, no application passwords.

```
JWT tokens:           NOT IMPLEMENTED
OAuth:                NOT IMPLEMENTED
API keys:             NOT IMPLEMENTED
Application passwords: NOT IMPLEMENTED
Rate limiting:        NOT IMPLEMENTED
HMAC request signing: NOT IMPLEMENTED
```

The REST API relies on WordPress's built-in cookie + nonce authentication:
- `Plugin.php:251` passes `wp_create_nonce('wp_rest')` to the frontend via `wp_localize_script()`
- All authenticated requests require the user to be logged into WordPress
- The nonce is sent as `X-WP-Nonce` header by the React frontend

### Permission Model — 4 Tiers

```
Tier 0: PUBLIC          → __return_true
Tier 1: STAFF           → manage_options OR nzl_staff
Tier 2: ADMIN           → manage_options OR nzl_admin
Tier 3: CAPABILITY-BASED → manage_options OR nzl_manage_{module}
Tier 4: WP SUPER ADMIN  → manage_options only (brands, integrations, features group)
```

### Permission Callback Patterns

All callbacks are consistent:

```php
// Pattern 1: Inline lambda (RestController.php)
$staff_permission = fn() => current_user_can('manage_options') || current_user_can('nzl_staff');
$admin_permission = fn() => current_user_can('manage_options') || current_user_can('nzl_admin');

// Pattern 2: Controller method (self-registering controllers)
public function checkAdminPermission(WP_REST_Request $request): bool {
    return current_user_can('manage_options') || current_user_can('nzl_admin');
}

// Pattern 3: Module-specific capability
fn() => current_user_can('manage_options') || current_user_can('nzl_manage_housekeeping')
```

### Security Issues Found

**ISSUE 9a: No per-resource authorization (IDOR risk)**

Staff endpoints check *capability* but not *resource ownership*. Any staff user with `nzl_staff` can access ANY booking, guest, folio, or housekeeping task — regardless of assignment or property.

```
GET /admin/bookings/999     → Any staff can view any booking
PUT /admin/bookings/999     → Any staff can edit any booking
GET /admin/guests/999       → Any staff can view any guest
```

This is acceptable for single-property, trusted-staff scenarios but is a vulnerability for multi-property deployments.

**ISSUE 9b: Weak public booking auth**

Public booking lookup (`GET /bookings/{number}`) and cancel (`POST /bookings/{number}/cancel`) use only booking_number + email for authentication. An attacker who knows both can view and cancel any booking. No rate limiting exists to prevent enumeration.

**ISSUE 9c: No rate limiting on public endpoints**

`POST /bookings` (create booking) has no rate limiting, CAPTCHA, or abuse prevention. A bot could flood the system with fake reservations.

**ISSUE 9d: SSE endpoint uses nzl_staff only**

`GET /admin/events/stream` requires only `nzl_staff` — no `manage_options` fallback. This means WordPress admins without `nzl_staff` capability would be denied. Inconsistent with all other staff routes.

---

## 10. Response Structure Consistency

### Three Distinct Patterns Found

**Pattern A: Standard envelope (most controllers)**
```json
{
  "success": true,
  "data": { ... },
  "message": "Operation completed.",
  "meta": { "total": 42, "pages": 3 }
}
```
Used by: AdminBookingController, BookingController, RoomTypeController, RoomController, DashboardController, AvailabilityController, EmployeeController, HousekeepingController, FolioController, TaxController, POSController, LoyaltyController

**Pattern B: Flat response (some controllers)**
```json
{
  "message": "Channel mapping created.",
  "mapping": { ... }
}
// Or raw object:
{ "general": { ... }, "currency": { ... } }
```
Used by: SettingsController (get returns raw object), ChannelController, GuestController (single GET returns flat guest object)

**Pattern C: Structured error (RatePlanController only)**
```json
{
  "success": false,
  "error": {
    "code": "NOT_FOUND",
    "message": "Rate plan not found.",
    "fields": { "name": ["Name is required"] }
  }
}
```
Used by: RatePlanController only

### Pagination — 3 Different Formats

| Controller | Format |
|-----------|--------|
| AdminBookingController | `{ data: [...], meta: { total, pages } }` |
| GuestController | `{ data: { items: [...], pagination: { page, per_page, total, total_pages } } }` + `X-WP-Total` header |
| ChannelController | `{ mappings: [...], total: N, pages: N }` flat keys |

### Error Responses — Inconsistent

| Controller | 404 Format | Validation Error Format |
|-----------|-----------|----------------------|
| Most | `{ success: false, message: "..." }` | `{ success: false, message: "...", errors: {...} }` |
| RatePlanController | `{ success: false, error: { code: "NOT_FOUND", message: "..." } }` | `{ success: false, error: { code: "VALIDATION_ERROR", fields: {...} } }` |
| GuestController | `{ message: "Guest not found." }` (no success key) | N/A |
| SettingsController | `{ message: "...", errors: [...] }` (no success key) | Same |

### HTTP Status Codes — Consistent

All controllers correctly use: 200 (success), 201 (created), 400 (bad request), 404 (not found), 409 (conflict), 422 (validation failed), 500 (server error).

### Assessment

**No shared response helper exists.** Each controller builds its own response shape. The dominant pattern (`success` + `data` + `message` + `meta`) is used by ~70% of controllers, but the remaining 30% deviate.

**Migration recommendation:** Create a single `ApiResponse` helper in Laravel that standardizes all responses into Pattern A. The React frontend already handles Pattern A — the deviations are likely bugs that the frontend works around.

---

## 11. Input Validation and Sanitization

### Superglobal Usage — Near Zero

Only 2 files access PHP superglobals directly:

| File | Line | Superglobal | Sanitized? | Purpose |
|------|------|-------------|-----------|---------|
| `StaffIsolation.php` | 138 | `$_GET['page']` | YES — `sanitize_text_field()` | Admin menu page detection |
| `BookingService.php` | 613-625 | `$_SERVER['HTTP_CF_CONNECTING_IP']`, `$_SERVER['HTTP_X_FORWARDED_FOR']`, `$_SERVER['REMOTE_ADDR']` | YES — `sanitize_text_field(wp_unslash())` | Client IP detection |

**No raw `$_POST` or `$_REQUEST` access anywhere.** All request data flows through `WP_REST_Request::get_param()`.

### REST-Level Validation (Route Args)

ID parameters use consistent validation across all controllers:

```php
'args' => [
    'id' => [
        'validate_callback' => fn($v) => is_numeric($v) && (int) $v > 0,
        'sanitize_callback' => 'absint',
    ],
]
```

Routes with typed URL patterns (`(?P<id>\d+)`) provide additional regex-level validation before the callback fires.

### Controller-Level Sanitization

All controllers sanitize inputs manually via WordPress functions:

| Function | Usage | Coverage |
|----------|-------|---------|
| `sanitize_text_field()` | 150+ calls | All string inputs |
| `sanitize_email()` | 20+ calls | Email fields |
| `sanitize_textarea_field()` | 20+ calls | Notes, descriptions, special requests |
| `absint()` / `(int)` | 100+ calls | All integer inputs |
| `wp_kses_post()` | 0 calls | Not used (email template HTML stored as-is) |

### SQL Injection — Fully Protected

All database access goes through the `Database` wrapper class which uses `$wpdb->prepare()` for every parameterized query:

```php
// Database.php — every query method uses prepare()
public function getRow(string $query, ...$args): ?object {
    if (!empty($args)) {
        $query = $this->wpdb->prepare($query, ...$args);
    }
    return $this->wpdb->get_row($query);
}
```

Zero raw SQL concatenation with user input found.

### Validation Gaps Found

**GAP 11a: `orderby` and `order` parameters not validated against allowlists**

Multiple controllers pass `orderby` and `order` directly from request to repository:

```php
// AdminBookingController, GuestController, GroupBookingController
'orderby' => $request->get_param('orderby') ?? 'created_at',
'order'   => $request->get_param('order') ?? 'DESC',
```

These go to the repository where they're used in SQL `ORDER BY` clauses. While `$wpdb->prepare()` doesn't apply to column names/sort direction, the repositories appear to use allowlists internally. Still, the validation should happen at the API boundary.

**GAP 11b: Search parameters passed raw to repositories**

```php
// AdminBookingController::index()
'status' => $request->get_param('status') ?? '',
'source' => $request->get_param('source') ?? '',
'search' => $request->get_param('search') ?? '',
```

No `sanitize_text_field()` at the controller level. The repository likely handles this via `$wpdb->prepare()` with `%s` placeholders, but sanitization should happen at the API boundary for defense in depth.

**GAP 11c: Email template HTML body not sanitized**

`EmailTemplateController` stores `body` and `body_ar` fields as raw HTML in the database. While only admins can edit templates, stored XSS via template editing is possible if an admin account is compromised. `wp_kses_post()` should be applied.

**GAP 11d: `GET /availability` has no args validation defined**

The availability endpoint has `permission_callback: __return_true` and no `args` block. All validation is deferred to the `AvailabilityController::check()` method body, meaning malformed requests hit the controller before being rejected.

**GAP 11e: `POST /bookings` has no args validation defined**

Same issue — public booking creation has no REST-level parameter schema. Validation happens inside `BookingController::store()` and `BookingService`, but the request reaches the controller unvalidated.

### Entity Existence Checks — Good

All controllers that accept resource IDs check existence before operating:

```php
// Standard pattern across all controllers:
$booking = $this->repository->find($id);
if (!$booking) {
    return new WP_REST_Response(['success' => false, 'message' => 'Not found.'], 404);
}
```

---

## Summary of API Layer Findings

| Question | Answer | Migration Risk |
|----------|--------|----------------|
| All data via REST API? | **YES** — 214 routes, zero ajax handlers, zero direct $_POST | LOW |
| Auth mechanism? | WordPress cookie + nonce only. No JWT/OAuth/API keys | MEDIUM — must implement JWT/Sanctum for Laravel |
| Any public endpoints that shouldn't be? | **Mostly OK** — 9 public routes, all justified. Booking lookup/cancel auth is weak | LOW-MEDIUM |
| Response structure consistent? | **NO** — 3 patterns, 3 pagination formats, inconsistent error shapes | MEDIUM — standardize during migration |
| Input validation at API boundary? | **MOSTLY** — ID validation solid, sanitization good. Gaps in orderby/search/email HTML | LOW-MEDIUM |
| Raw superglobal access? | **Near zero** — only 2 files, both properly sanitized | LOW |
| SQL injection risk? | **NONE** — all queries via $wpdb->prepare() | NONE |
| XSS risk? | **LOW** — JSON responses, no direct echo of user input. Email template HTML unsanitized | LOW |
| Rate limiting? | **NOT IMPLEMENTED** — public endpoints unprotected | MEDIUM — add during migration |
| Per-resource authorization? | **NOT IMPLEMENTED** — any staff can access any resource | MEDIUM-HIGH for multi-property |

---
---

# Nozule PMS — Performance & Infrastructure Deep Audit

**Date:** 2026-03-04
**Scope:** N+1 queries, caching, unbounded queries, wp-cron, missing indexes, synchronous HTTP

---

## 12. N+1 Query Patterns

### Critical N+1 Issues (Must Fix)

**N+1 #1: `InventoryRepository::initializeInventory()`** — HIGH
`includes/Modules/Rooms/Repositories/InventoryRepository.php:197-221`

```php
while ( $current <= $end ) {
    $dateStr  = $current->format( 'Y-m-d' );
    $existing = $this->getForDate( $roomTypeId, $dateStr );  // SELECT per day
    if ( ! $existing ) {
        $id = $this->db->insert( $this->table, [...] );      // INSERT per day
    }
    $current = $current->modify( '+1 day' );
}
```
**Impact:** 365-day init = 365+ queries. User-facing (room setup).
**Fix:** Batch SELECT for entire range, then batch INSERT for missing dates.

---

**N+1 #2: `ChannelSyncService::pushAvailability()`** — HIGH
`includes/Modules/Channels/Services/ChannelSyncService.php:128-150`

```php
foreach ( $mappings as $mapping ) {
    $rows = $this->db->getResults(
        "SELECT date, available_rooms, stop_sell, min_stay
         FROM {$inventoryTable}
         WHERE room_type_id = %d AND date >= %s AND date <= %s",
        $mapping->local_room_type_id, $startDate, $endDate
    );  // SELECT per mapping
}
```
**Impact:** O(M) queries where M = channel mappings (could be 10-50+). Background + user-triggered.
**Fix:** Single query with `WHERE room_type_id IN (...)`, group results in PHP.

---

**N+1 #3: `ChannelSyncService::pushRates()`** — HIGH
`includes/Modules/Channels/Services/ChannelSyncService.php:268-293`

Same pattern as #2 but for rate data with a JOIN. Same fix applies.

---

**N+1 #4: `NotificationService::sendScheduledReminders()`** — MEDIUM-HIGH
`includes/Modules/Notifications/Services/NotificationService.php:487-510`

```php
foreach ( $check_in_bookings as $booking ) {
    $notification = $this->queue( $booking, 'check_in_reminder' );
    // queue() internally calls:
    //   hasBeenSent()    → SELECT (1 query)
    //   resolveGuest()   → SELECT (1 query)
    //   buildTemplate()  → possible query
    //   repository->create() → INSERT (1 query)
}
```
**Impact:** 3-4 queries per booking. 100 arrivals = 300-400 queries. Background cron job.
**Fix:** Batch-fetch guest data, batch-check sent status with `WHERE booking_id IN (...)`.

---

### Acceptable Patterns (No Fix Needed)

| File | Pattern | Why OK |
|------|---------|--------|
| `ReportService.php` | Batch query + in-memory loop | Queries happen once, loop is CPU-only |
| `PricingService::enforceRestrictions()` | Single query, then loop validation | No DB inside loop |
| `SettingsManager::loadAutoloadSettings()` | Single query, loop for JSON decode | CPU-only loop |
| `HousekeepingRepository::countByStatus()` | GROUP BY + loop to format | Formatting only |
| `ReviewService::processPendingRequests()` | Batch fetch + per-item UPDATE | Status updates are inherently per-item |
| Migration seed loops | `foreach` with INSERT | Run once at setup, not user-facing |

---

## 13. Object Caching

### CacheManager Architecture

`includes/Core/CacheManager.php` — dual-layer cache:

```
Layer 1: wp_cache_get/set (WordPress object cache — in-memory per request,
         persistent with Redis/Memcached if external cache plugin installed)
Layer 2: get_transient/set_transient (wp_options table fallback — persistent
         across requests even without external cache)
```

Both layers are always written. Read checks object cache first, falls back to transient.

### Where Cache IS Used

| Module | What's Cached | TTL | File |
|--------|--------------|-----|------|
| **Reports** | Revenue report | 300s | `ReportService.php:49,135` |
| **Reports** | Occupancy report | 300s | `ReportService.php:153,240` |
| **Reports** | Source analysis | 300s | `ReportService.php:257,324` |
| **Reports** | Guest demographics | 300s | `ReportService.php:341,484` |
| **Reports** | Forecast report | 300s | `ReportService.php:501,608` |
| **Reports** | Financial report | 300s | `ReportService.php:625,775` |
| **Reports** | ADR/RevPAR | 120s | `ReportService.php:797,920` |
| **Settings** | Public settings | 600s | `SettingsController.php:216,228` |
| **Settings** | Auto-loaded settings | In-memory per request | `SettingsManager.php:148-163` |
| **Pricing** | Calculated rates | Via CacheManager | `PricingService.php` |
| **Property** | Property detail | Via CacheManager | `PropertyController.php` |
| **SSE** | Event queue | 3600s | `SSEController.php:149` (transient-only) |

### Where Cache is NOT Used (Gaps)

| Module | Expensive Query | Called From | Impact |
|--------|----------------|------------|--------|
| **Dashboard stats** | 5+ aggregate queries (occupancy, revenue, arrivals, departures) | `DashboardController::stats()` — every admin page load | HIGH — hits DB on every dashboard visit |
| **Availability check** | Complex date-range join across inventory + bookings | `AvailabilityController::check()` — public endpoint | MEDIUM — every search hits DB |
| **Calendar view** | Month-wide booking + room query | `CalendarController::index()` — staff page | MEDIUM — hits DB on every calendar load |
| **Room type list** | Full list with rates | `RoomTypeController::index()` — public + admin | LOW-MEDIUM — small result set but frequent |
| **Guest lookup** | Full text search | `GuestController::index()` — staff search | LOW — search is inherently uncacheable |
| **Employee list** | `get_users()` with meta query | `EmployeeController::list()` — admin page | LOW — small user count |

### Assessment

Caching is **well-implemented for reports** (the heaviest queries) and **completely missing for the dashboard and calendar** (the most frequently accessed pages). Without an external object cache plugin (Redis/Memcached), `wp_cache_get/set` provides zero benefit (in-memory only, reset every request) and all persistence comes from transients in `wp_options`.

---

## 14. Unbounded Queries

### Queries Without LIMIT That Could Return Many Rows

#### HIGH RISK (operational tables, grows over time)

| Repository | Method | Query | Potential Rows |
|-----------|--------|-------|---------------|
| `BookingRepository` | `getByStatus()` | `SELECT * FROM bookings WHERE status = %s` | Thousands (all confirmed/checked-in) |
| `BookingRepository` | `getByCheckInDate()` | `SELECT * FROM bookings WHERE check_in = %s` | 50-200 per day |
| `BookingRepository` | `getByCheckOutDate()` | `SELECT * FROM bookings WHERE check_out = %s` | 50-200 per day |
| `BookingRepository` | `getByGuestId()` | `SELECT * FROM bookings WHERE guest_id = %d` | Unbounded repeat guests |
| `BookingRepository` | `getInHouseGuests()` | `SELECT * FROM bookings WHERE status = 'checked_in'` | All in-house (OK for small hotels) |
| `FolioRepository` | `getByGuest()` | `SELECT * FROM folios WHERE guest_id = %d` | Unbounded |
| `FolioRepository` | `getByStatus()` | `SELECT * FROM folios WHERE status = %s` | All open folios |
| `NotificationRepository` | `getByBookingId()` | `SELECT * FROM notifications WHERE booking_id = %d` | Could be many per booking |
| `NotificationRepository` | `getByGuestId()` | `SELECT * FROM notifications WHERE guest_id = %d` | Unbounded |
| `HousekeepingRepository` | `getByStatus()` | `SELECT * FROM housekeeping WHERE status = %s` | All dirty rooms |
| `HousekeepingRepository` | `getAllWithRoomInfo()` | `SELECT * FROM housekeeping JOIN rooms` | All tasks ever |
| `GroupBookingRepository` | `getByStatus()` | `SELECT * FROM group_bookings WHERE status = %s` | All groups of type |
| `GroupBookingRepository` | `search()` | `SELECT * FROM group_bookings WHERE name LIKE %s` | Unbounded |
| `EmailLogRepository` | `getByBookingId()` | `SELECT * FROM email_log WHERE booking_id = %d` | Unbounded |
| `WhatsAppLogRepository` | `getByBookingId()` | `SELECT * FROM whatsapp_log WHERE booking_id = %d` | Unbounded |

#### LOW RISK (reference tables, small row counts)

| Repository | Method | Query | Why Low Risk |
|-----------|--------|-------|-------------|
| `RoomTypeRepository` | `getActive()` | `SELECT * FROM room_types WHERE status = 'active'` | Typically 5-20 room types |
| `RoomTypeRepository` | `getAllOrdered()` | `SELECT * FROM room_types ORDER BY sort_order` | Same |
| `RoomRepository` | `getByRoomType()` | `SELECT * FROM rooms WHERE room_type_id = %d` | Typically 10-50 rooms per type |
| `RoomRepository` | `getAllWithType()` | `SELECT * FROM rooms JOIN room_types` | Max ~200 rooms total |
| `RatePlanRepository` | `getActive()` | `SELECT * FROM rate_plans WHERE status = 'active'` | Typically 5-15 plans |
| `TaxRepository` | `getActive()` | `SELECT * FROM taxes WHERE is_active = 1` | Typically 2-5 taxes |
| `CurrencyRepository` | `getActive()` | `SELECT * FROM currencies WHERE is_active = 1` | Typically 3-5 currencies |
| `DynamicPricingRepository` | Various `getAll*()` | Full table scans | Typically <50 rules |

#### Paginated Queries (OK)

`AdminBookingController::index()`, `GuestController::index()`, and `FolioController::index()` all use pagination with LIMIT/OFFSET. These are safe.

### Assessment

**50+ unbounded SELECT queries** across the codebase. Most are in repository `getBy*()` methods that assume small result sets. For a hotel with years of booking history, queries like `getByGuestId()` or `getByStatus('confirmed')` could return thousands of rows.

**Fix priority:** Add default LIMIT (e.g., 1000) to all `getBy*()` methods. For methods that genuinely need all rows (e.g., report aggregations), use COUNT first to warn if result set is large.

---

## 15. wp-cron Scheduled Events

### Complete Cron Event Inventory

| # | Hook | Interval | Registered In | Callback | What It Does |
|---|------|----------|--------------|----------|-------------|
| 1 | `nzl_daily_maintenance` | Daily | `Activator.php:212` | `Plugin::runDailyMaintenance()` | Database cleanup, old notification purge |
| 2 | `nzl_send_reminders` | Hourly | `Activator.php:216` | `Plugin::sendReminders()` → `NotificationService::sendScheduledReminders()` | Pre-arrival/departure email reminders |
| 3 | `nzl_channel_sync` | Hourly | `ChannelsModule.php:117` | `ChannelsModule::runScheduledSync()` | Push availability/rates to OTAs |
| 4 | `nzl_channel_pull_reservations` | Every 15 min | `ChannelSyncModule.php:186` | `ChannelSyncModule::runReservationPull()` | Pull new bookings from OTAs |
| 5 | `nzl_channel_push_inventory` | Hourly | `ChannelSyncModule.php:191` | `ChannelSyncModule::runInventoryPush()` | Push inventory updates to OTAs |
| 6 | `nozule/cron/process_notifications` | Every 5 min | `NotificationsModule.php:168` | `NotificationsModule::processQueue()` → `NotificationService::processQueue()` | Send queued email/WhatsApp/SMS |
| 7 | `nzl_rate_shop_check` | Twice daily | `RateShoppingModule.php:79` | `RateShoppingModule::runScheduledParityCheck()` | Scrape competitor rates, check parity |
| 8 | `nzl_process_review_requests` | Every 15 min | `ReviewModule.php:94` | `ReviewService::processPendingRequests()` | Send post-checkout review solicitation emails |
| 9 | `nzl_generate_forecasts` | Daily | `ForecastingModule.php:68` | `ForecastService::generateAll()` | Calculate demand forecasts for next 90 days |

### Custom Cron Schedules Registered

| Name | Interval | Registered By |
|------|----------|--------------|
| `five_minutes` | 300s | `NotificationsModule.php:162` |
| `nzl_every_15_minutes` | 900s | `ReviewModule.php:86` |
| `nzl_fifteen_minutes` | 900s | `ChannelSyncModule.php:172` |

### Risk Assessment

| Hook | Time-Critical? | Heavy? | Should Be Real Cron? |
|------|---------------|--------|---------------------|
| `nzl_daily_maintenance` | No | Low | No — daily cleanup is fine on wp-cron |
| `nzl_send_reminders` | **YES** — guests expect reminders at specific times | Medium (N+1 issue #4) | **YES** — wp-cron depends on site traffic |
| `nzl_channel_sync` | **YES** — stale OTA data = overbookings | High (HTTP calls to OTA APIs) | **YES** — must run regardless of traffic |
| `nzl_channel_pull_reservations` | **CRITICAL** — missed OTA bookings = double bookings | High (HTTP calls) | **YES** — 15-min interval is aggressive for wp-cron |
| `nzl_channel_push_inventory` | **YES** — stale inventory on OTAs | High (HTTP calls) | **YES** — must push after every change |
| `nozule/cron/process_notifications` | **YES** — email delivery delays | Medium (sends emails/WhatsApp) | **YES** — 5-min interval unreliable on wp-cron |
| `nzl_rate_shop_check` | No | High (scrapes competitor sites) | Moderate — twice daily is tolerable |
| `nzl_process_review_requests` | No | Low-Medium | No — review emails can be delayed |
| `nzl_generate_forecasts` | No | High (90-day forecast calculation) | No — daily is fine, but should run during low-traffic |

### Critical wp-cron Limitation

**wp-cron is NOT real cron.** It only fires when someone visits the site. For a hotel with low website traffic overnight, these events will be delayed:

- **Channel pull (15 min):** A booking made at 2 AM on Booking.com won't be pulled until the next site visit — could be hours. This causes double-booking risk.
- **Notification queue (5 min):** Confirmation emails will be delayed until someone visits the site.
- **Channel push (hourly):** Inventory changes won't reach OTAs until a visit triggers cron.

**Recommendation:** Configure `DISABLE_WP_CRON` + real server cron (`* * * * * wget -q -O - https://site.com/wp-cron.php`) for all time-critical events. In Laravel, use `php artisan schedule:run` with system crontab.

---

## 16. Missing Database Indexes

### Complete Index Audit

#### Tables With Good Index Coverage

| Table | Indexes | Assessment |
|-------|---------|-----------|
| `nzl_bookings` | PK, UNIQUE(booking_number), KEY(guest_id), KEY(room_type_id), KEY(check_in), KEY(check_out), KEY(status), KEY(source), KEY(created_at), KEY(status, check_in) | **GOOD** — composite (status, check_in) covers common queries |
| `nzl_room_inventory` | PK, UNIQUE(room_type_id, date), KEY(date) | **GOOD** — unique composite covers date-range lookups |
| `nzl_housekeeping_tasks` | PK, KEY(room_id), KEY(assigned_to), KEY(status), KEY(priority), KEY(created_at) | **GOOD** |
| `nzl_folios` | PK, UNIQUE(folio_number), KEY(booking_id), KEY(group_booking_id), KEY(guest_id), KEY(status) | **GOOD** |
| `nzl_channel_mappings` | PK, UNIQUE(channel, room_type_id, rate_plan_id), KEY(room_type_id), KEY(channel), KEY(status) | **GOOD** |

#### Tables With Missing Indexes

| Table | Missing Index | Used In WHERE/JOIN | Impact |
|-------|--------------|-------------------|--------|
| **`nzl_guests`** | `wp_user_id` | Guest-user lookup | LOW — rare query |
| **`nzl_guests`** | `created_at` | Recent guests sort | LOW — small table |
| **`nzl_payments`** | `(booking_id, status)` composite | Unpaid payment lookup | MEDIUM — common query |
| **`nzl_seasonal_rates`** | `(room_type_id, status, start_date, end_date)` composite | Active rate for room type + date | **HIGH** — pricing engine query |
| **`nzl_seasonal_rates`** | `rate_plan_id` | Rate plan rate lookup | MEDIUM — frequently queried |
| **`nzl_rate_plans`** | `(room_type_id, status, is_default)` composite | Default rate plan lookup | MEDIUM |
| **`nzl_notifications`** | `guest_id` | Guest notification history | LOW |
| **`nzl_notifications`** | `(status, created_at)` composite | Queue processing ORDER BY | MEDIUM — cron job query |
| **`nzl_group_bookings`** | `check_out` | Date range overlap queries | MEDIUM |
| **`nzl_email_log`** | `(status, created_at)` composite | Queue processing | MEDIUM |
| **`nzl_booking_logs`** | `booking_id` | Audit trail display | **HIGH** — loaded on every booking detail view |
| **`nzl_folio_items`** | `(folio_id, category)` composite | Folio category filter | LOW-MEDIUM |
| **`nzl_group_booking_rooms`** | `guest_id` | Guest-to-group lookup | LOW |
| **`nzl_rate_restrictions`** | `(room_type_id, date_from, date_to, is_active)` composite | Active restrictions for date range | MEDIUM |
| **`nzl_loyalty_members`** | `guest_id` | Guest loyalty lookup | LOW |
| **`nzl_loyalty_transactions`** | `member_id` | Transaction history | LOW |
| **`nzl_pos_order_items`** | `order_id` | Order line items | MEDIUM |
| **`nzl_review_requests`** | `(status, send_after)` composite | Queue processing | Already exists! |

#### Most Critical Missing Indexes (Fix First)

1. **`nzl_booking_logs.booking_id`** — Every booking detail page queries this. No index = full table scan on growing table.
2. **`nzl_seasonal_rates (room_type_id, status, start_date, end_date)`** — Pricing engine queries this on every availability check and booking. Without composite index, the DB scans all seasonal rates.
3. **`nzl_payments (booking_id, status)`** — Payment status check runs on every booking detail view and folio calculation.
4. **`nzl_notifications (status, created_at)`** — Cron job processes queue every 5 minutes, sorted by created_at.

---

## 17. Synchronous External HTTP Calls

### Complete HTTP Call Inventory

| # | File | Method | External Service | In Request Path? | Blocking? |
|---|------|--------|-----------------|-----------------|-----------|
| 1 | `WhatsAppService.php:366` | `wp_remote_post()` | **Meta WhatsApp Cloud API** | **YES** — `POST /admin/whatsapp/send` and `POST /admin/whatsapp/test` | **YES — blocks user request** |
| 2 | `WhatsAppService.php:366` | `wp_remote_post()` | **Meta WhatsApp Cloud API** | YES — also called from `processQueue()` (cron) | Cron path is OK |
| 3 | `BookingComApiClient.php:421` | `wp_remote_post()` | **Booking.com XML API** | Mostly cron (push availability/rates, pull reservations) | Cron path is OK |
| 4 | `BookingComApiClient.php:421` | `wp_remote_post()` | **Booking.com XML API** | **YES** — `POST /admin/channels/{id}/sync` (manual sync) and `POST /admin/integrations/{id}/test` | **YES — blocks user request** |
| 5 | `OdooConnector.php:311` | `wp_remote_post()` | **Odoo ERP JSON-RPC** | **YES** — called when creating invoices/contacts during booking flow | **YES — blocks user request** |
| 6 | `WebhookConnector.php:114` | `wp_remote_post()` | **User-configured webhook URL** | **YES** — fires on booking events | **YES — blocks event processing** |

### Call Path Analysis

#### Path 1: WhatsApp Send (USER-FACING, BLOCKING)
```
User clicks "Send WhatsApp" →
  WhatsAppController::send() →
    WhatsAppService::sendMessage() →
      wp_remote_post( Meta Cloud API )  ← BLOCKS 1-5 seconds
```
**Risk:** If Meta API is slow or down, the admin UI hangs. No timeout configured.

#### Path 2: Channel Manual Sync (USER-FACING, BLOCKING)
```
User clicks "Sync Now" →
  ChannelController::sync() →
    ChannelService::syncAvailability() →
      BookingComApiClient::pushAvailability() →
        wp_remote_post( Booking.com XML API )  ← BLOCKS 2-10 seconds per call
```
**Risk:** Multiple HTTP calls in a loop (N+1 #2 and #3). Each blocks. Total could be 30+ seconds.

#### Path 3: Odoo Integration (USER-FACING, BLOCKING)
```
Booking confirmed →
  IntegrationModule hooks →
    OdooConnector::createInvoice() →
      wp_remote_post( Odoo JSON-RPC )  ← BLOCKS 1-5 seconds
        (also calls authenticate() first = 2 HTTP calls)
```
**Risk:** Two synchronous HTTP calls during booking confirmation. If Odoo is down, confirmation fails or hangs.

#### Path 4: Webhook (EVENT-DRIVEN, BLOCKING)
```
Booking created/confirmed/cancelled →
  IntegrationModule hooks →
    WebhookConnector::send() →
      wp_remote_post( user URL )  ← BLOCKS until response or timeout
```
**Risk:** User-configured URL could be anything. Slow webhook = slow booking operations.

#### Path 5: Cron Jobs (BACKGROUND, ACCEPTABLE)
```
nzl_channel_pull_reservations → BookingComApiClient → wp_remote_post()  ← OK (cron)
nzl_channel_push_inventory   → BookingComApiClient → wp_remote_post()  ← OK (cron)
nozule/cron/process_notifications → WhatsAppService → wp_remote_post() ← OK (cron)
```
These are acceptable because they run in background cron, not blocking user requests.

### Timeout Configuration

```php
// WhatsAppService.php:366
wp_remote_post( $url, [
    'timeout' => 30,  // 30 second timeout — TOO HIGH for user-facing
    'body'    => wp_json_encode( $payload ),
    ...
]);

// OdooConnector.php:311
wp_remote_post( $endpoint, [
    'timeout' => 15,  // 15 second timeout
    ...
]);

// WebhookConnector.php:114
wp_remote_post( $url, [
    'timeout' => 10,  // 10 second timeout
    ...
]);

// BookingComApiClient.php:421
wp_remote_post( $url, $args );  // DEFAULT timeout (5 seconds)
```

### Assessment

**4 synchronous blocking HTTP calls in user-facing request paths.** The Odoo and Webhook calls are particularly dangerous because they fire on booking lifecycle events (confirm, cancel, check-in) — the most critical user operations.

**Recommendation for Laravel migration:**
- Queue all external HTTP calls via Laravel Jobs
- WhatsApp send → dispatch `SendWhatsAppJob`
- Channel sync → dispatch `SyncChannelJob`
- Odoo integration → dispatch `SyncOdooJob`
- Webhooks → dispatch `FireWebhookJob`
- Use Laravel Horizon for queue monitoring
- Set aggressive timeouts (5s max for user-triggered, 30s for background)

---

## Summary of Performance Findings

| Question | Answer | Severity |
|----------|--------|----------|
| N+1 query patterns? | **4 critical** — inventory init, channel sync (×2), notification reminders | HIGH |
| Object caching used? | **Partially** — reports cached (5 min), dashboard/calendar/availability NOT cached | MEDIUM |
| Unbounded queries? | **50+ SELECT without LIMIT** across repositories. Most assume small result sets | MEDIUM-HIGH |
| wp-cron issues? | **9 scheduled events**, 5 are time-critical. wp-cron is traffic-dependent — unreliable | HIGH |
| Missing indexes? | **4 critical** — booking_logs.booking_id, seasonal_rates composite, payments composite, notifications queue | MEDIUM-HIGH |
| Synchronous HTTP? | **4 blocking calls** in user-facing paths — WhatsApp, Booking.com, Odoo, Webhooks | HIGH |

---
---

# Nozule PMS — Security Deep Audit

**Date:** 2026-03-04
**Scope:** SQL injection, authorization, CSRF/nonces, PII encryption, file uploads, error disclosure

---

## 18. Prepared Statements Audit

### Database Wrapper — Safe by Design

`includes/Core/Database.php` wraps all `$wpdb` methods. Every query method (`getRow`, `getResults`, `getVar`, `query`) calls `$wpdb->prepare()` when arguments are provided:

```php
public function getResults( string $query, ...$args ): array {
    if ( ! empty( $args ) ) {
        $query = $this->wpdb->prepare( $query, ...$args );
    }
    return $this->wpdb->get_results( $query ) ?: [];
}
```

Insert/update/delete use `$wpdb->insert()`, `$wpdb->update()`, `$wpdb->delete()` — which internally use `prepare()` for all values.

### String Concatenation Issues Found

#### ISSUE 18a: ORDER BY column name injection (14 repositories)

**Pattern in `BaseRepository::all()` and 13 child repositories:**

```php
// BaseRepository.php:54-58
public function all( string $orderBy = 'id', string $order = 'ASC' ): array {
    $table   = $this->tableName();
    $orderBy = sanitize_sql_orderby( "{$orderBy} {$order}" ) ?: 'id ASC';
    $rows    = $this->db->getResults( "SELECT * FROM {$table} ORDER BY {$orderBy}" );
    return $this->model::fromRows( $rows );
}

// BookingRepository.php:301-323 (and 12 other repositories)
$allowed_columns = ['id', 'booking_number', 'check_in', ...];
$orderby = in_array( $args['orderby'], $allowed_columns, true ) ? $args['orderby'] : 'created_at';
$order   = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';
// ...
$rows = $this->db->getResults(
    "SELECT b.* FROM {$table} b ... ORDER BY b.{$orderby} {$order} LIMIT %d OFFSET %d",
    ...$params
);
```

**Risk level: LOW-MEDIUM.** The `$orderby` values are validated against allowlists (`in_array(..., true)`), and `$order` is forced to ASC/DESC. `prepare()` cannot parameterize column names — this is the correct WordPress approach. However:

- `BaseRepository::all()` uses `sanitize_sql_orderby()` which is weaker than an allowlist
- The `all()` method accepts arbitrary strings from callers (not directly from user input, but from controllers)
- A controller bug could pass unsanitized input to `all()`

**Repositories using allowlist pattern (SAFE):**
BookingRepository, GuestRepository, EmailLogRepository, EmailTemplateRepository, GuestDocumentRepository, ChannelMappingRepository, ChannelSyncLogRepository, GroupBookingRepository, NotificationRepository, PromoCodeRepository, ReviewRepository, WhatsAppLogRepository, WhatsAppTemplateRepository

All 13 use `in_array($args['orderby'], $allowed_columns, true)` — this is **correctly implemented**.

#### ISSUE 18b: Dynamic WHERE clause in BaseRepository::count()

```php
// BaseRepository.php:91-107
public function count( ?array $where = null ): int {
    $table = $this->tableName();
    if ( $where ) {
        $conditions = [];
        $values     = [];
        foreach ( $where as $col => $val ) {
            $conditions[] = "`{$col}` = %s";  // Column name from caller
            $values[]     = $val;              // Value goes through prepare()
        }
        $where_clause = implode( ' AND ', $conditions );
        return (int) $this->db->getVar(
            "SELECT COUNT(*) FROM {$table} WHERE {$where_clause}",
            ...$values
        );
    }
}
```

**Risk: LOW.** Column names come from internal code (not user input), and values are properly prepared. The backtick escaping of `$col` provides some protection. But a coding error in a caller could inject malicious column names.

#### ISSUE 18c: Table name interpolation in migrations

All 11 migration files use `{$prefix}` interpolation in SQL strings:
```php
$sql = "CREATE TABLE {$prefix}nzl_room_types (...)";
```

**Risk: NONE.** `$prefix` comes from `$wpdb->prefix`, a trusted WordPress constant. This is standard WordPress practice.

#### ISSUE 18d: LIKE pattern in CacheManager::flush()

```php
// CacheManager.php:69-71
$wpdb->query(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_nzl_%'"
);
```

**Risk: NONE.** The LIKE pattern is a static string, not user input. `{$wpdb->options}` is a trusted constant.

### Summary

| Issue | Risk | Pattern | Fix Needed? |
|-------|------|---------|------------|
| ORDER BY with allowlist (13 repos) | LOW | `in_array()` check before interpolation | No — correctly implemented |
| `BaseRepository::all()` with `sanitize_sql_orderby()` | LOW-MEDIUM | Weaker than allowlist, but not directly user-callable | Consider hardening |
| `BaseRepository::count()` column names | LOW | Column names from internal code only | Monitor |
| Table name interpolation | NONE | WordPress standard | No |
| LIKE patterns | NONE | Static strings | No |

**Verdict: No SQL injection vulnerabilities found.** All user-provided values go through `$wpdb->prepare()`. Column names in ORDER BY are validated against allowlists. The Database wrapper enforces prepared statements on every code path.

---

## 19. Capability Checking on Every Privileged Action

### Route-Level Permission Callbacks

**Every single REST route has a `permission_callback` defined.** No routes are missing this parameter (which would cause WordPress to default to allowing unauthenticated access with a `_doing_it_wrong` warning).

#### Public Routes (19 `__return_true` callbacks)

| # | Route | Controller | Justification |
|---|-------|-----------|--------------|
| 1 | GET `/room-types` | RoomTypeController::index | Public catalog |
| 2 | GET `/room-types/{id}` | RoomTypeController::show | Public catalog |
| 3 | GET `/availability` | AvailabilityController::check | Public search |
| 4 | POST `/bookings` | BookingController::store | Guest booking creation |
| 5 | GET `/bookings/{number}` | BookingController::show | Guest lookup (requires email param) |
| 6 | POST `/bookings/{number}/cancel` | BookingController::cancel | Guest self-cancel (requires email) |
| 7 | GET `/settings/public` | SettingsController::publicSettings | Safe subset only |
| 8 | POST `/promo-codes/validate` | PromoCodeController::validate | Promo validation |
| 9 | GET `/reviews/track/{id}` | ReviewController::track | Tracking pixel |
| 10 | GET `/metasearch/feeds/{type}` | MetasearchController | XML feed for Google/TripAdvisor |
| 11 | GET `/admin/room-types` | RoomTypeController::index | Public read of room types |
| 12 | GET `/admin/room-types/{id}` | RoomTypeController::show | Public read of room type detail |
| 13 | GET `/currencies` | CurrencyController::publicList | Public currency list |
| 14 | GET `/property/public` | PropertyController::publicShow | Public property info |

**Assessment:** All public endpoints expose only read-only public data or guest-facing booking operations. No admin-only data is leaked through public endpoints.

**One concern:** Routes #11-12 (`/admin/room-types`) use `__return_true` — these are public reads registered under the `/admin/` prefix. Not a security issue (they return the same data as routes #1-2), but confusing. The `/admin/` namespace should be reserved for authenticated routes.

#### Protected Routes — Permission Tier Coverage

| Tier | Permission | Route Count | Pattern |
|------|-----------|-------------|---------|
| Staff | `manage_options \|\| nzl_staff` | ~25 | Booking operations, dashboard, calendar, guests |
| Admin | `manage_options \|\| nzl_admin` | ~90 | All CRUD endpoints |
| Module | `manage_options \|\| nzl_manage_{module}` | ~60 | Module-specific operations |
| Super | `manage_options` only | ~15 | Brands, integrations, advanced features |

**Every protected route checks capability at the route registration level** via `permission_callback`. WordPress verifies this before the callback function executes.

### Missing Per-Entity Authorization

As noted in section 9 (API audit), there are **no per-resource ownership checks**. A staff user with `nzl_staff` can access any booking, guest, folio, etc. — not just their own or their property's. This is an IDOR (Insecure Direct Object Reference) gap relevant to multi-property deployments.

---

## 20. Nonce Verification

### WordPress REST API Nonce Architecture

WordPress REST API has **built-in CSRF protection** for cookie-authenticated requests:

1. Frontend receives nonce: `wp_create_nonce('wp_rest')` (3 locations):
   - `Plugin.php:251` via `wp_localize_script()`
   - `AdminAssets.php:189` via `wp_localize_script()`
   - `templates/admin/reception-portal.php:26` inline

2. Frontend sends nonce as `X-WP-Nonce` header on every request

3. WordPress core verifies nonce automatically in `rest_cookie_check_errors()` — if a cookie is present but the nonce is invalid/missing, the request is treated as unauthenticated (permission callback fails)

### Manual Nonce Checks

```
wp_verify_nonce():      0 occurrences in plugin code
check_ajax_referer():   0 occurrences in plugin code
```

This is **correct** — manual nonce verification is unnecessary because WordPress REST API handles it automatically. The plugin correctly relies on the framework.

### Non-REST Form Handlers

```
admin-post.php handlers: 0
wp_ajax handlers:        0
Direct $_POST handlers:  0
```

**No form handlers exist outside the REST API.** There is no surface area for CSRF attacks via traditional form submissions.

### State-Changing GET Requests

No DELETE or state-changing operations use GET method. All destructive operations use POST, PUT, or DELETE methods — which require the nonce.

### Incoming Webhook Receivers

**No incoming webhook endpoints exist.** The plugin only *sends* webhooks (via `WebhookConnector`), it doesn't receive them. The Booking.com integration uses polling (cron pulls), not push webhooks. This means there's no attack surface for webhook forgery.

### Assessment

**CSRF protection is complete.** The WordPress REST API nonce system covers all state-changing endpoints. No legacy form handlers or ajax handlers exist that could bypass this protection.

---

## 21. Sensitive Data Storage

### Encryption Status by Data Type

#### ENCRYPTED (Channel Credentials Only)

| Table | Column | Encryption | Method |
|-------|--------|-----------|--------|
| `nzl_channel_connections` | `credentials` | **AES-256-CBC** | `ChannelConnection::encryptCredentials()` |

Implementation in `includes/Modules/Channels/Models/ChannelConnection.php:104-113`:
- Key: SHA-256 hash of WordPress `AUTH_KEY` constant
- IV: Random per encryption via `openssl_random_pseudo_bytes()`
- Format: `base64(IV + encrypted_data)`
- Legacy fallback: Handles unencrypted JSON for pre-encryption records

#### PLAINTEXT — CRITICAL (Guest PII)

| Table | Column | Data | Risk |
|-------|--------|------|------|
| `nzl_guests` | `email` | Email address | HIGH — GDPR PII |
| `nzl_guests` | `phone` | Phone number | HIGH — GDPR PII |
| `nzl_guests` | `phone_alt` | Alt phone | HIGH |
| `nzl_guests` | `id_number` | National ID / Passport number | **CRITICAL** |
| `nzl_guests` | `id_type` | Document type | MEDIUM |
| `nzl_guests` | `date_of_birth` | DOB | **CRITICAL** |
| `nzl_guests` | `address` | Street address | HIGH |
| `nzl_guest_documents` | `document_number` | Passport/ID number | **CRITICAL** |
| `nzl_guest_documents` | `mrz_line1` | Machine Readable Zone line 1 | **CRITICAL** |
| `nzl_guest_documents` | `mrz_line2` | Machine Readable Zone line 2 | **CRITICAL** |
| `nzl_guest_documents` | `date_of_birth` | DOB from passport | **CRITICAL** |
| `nzl_guest_documents` | `first_name` / `last_name` | Name from document | HIGH |
| `nzl_guest_documents` | `nationality` | Nationality | MEDIUM |
| `nzl_guest_documents` | `file_path` | Path to uploaded passport scan | HIGH |

#### PLAINTEXT — HIGH (API Credentials & Tokens)

| Table | Column | Data | Risk |
|-------|--------|------|------|
| `nzl_whatsapp_settings` | `setting_value` (key=`access_token`) | Meta WhatsApp API bearer token | **CRITICAL** |
| `nzl_whatsapp_settings` | `setting_value` (key=`phone_number_id`) | WhatsApp phone number ID | HIGH |
| `nzl_whatsapp_settings` | `setting_value` (key=`business_id`) | WhatsApp business ID | MEDIUM |

**Additional exposure:** The `GET /admin/whatsapp-settings` endpoint returns the **full plaintext `access_token`** alongside the masked version (`access_token_masked`). The masking adds a new key but does not remove the original from the response payload.

#### PLAINTEXT — MEDIUM (Payment & Business Data)

| Table | Column | Data | Risk |
|-------|--------|------|------|
| `nzl_payments` | `gateway_response` | Payment gateway JSON response | MEDIUM — may contain transaction tokens |
| `nzl_properties` | `tax_id` | Tax/VAT ID | MEDIUM |
| `nzl_properties` | `license_number` | Business license number | MEDIUM |

### Encryption Audit

```
openssl_encrypt/decrypt:  ChannelConnection.php only
sodium_crypto_*:          NOT USED
hash_hmac:                WebhookConnector.php (outgoing signature only)
wp_hash_password:         NOT USED (relies on WordPress for user passwords)
```

**Only 1 of 4 sensitive data categories is encrypted.** Channel credentials use proper AES-256-CBC. Guest PII, WhatsApp tokens, and payment data are all stored in plaintext.

---

## 22. File Upload Security

### Upload Handler

`includes/Modules/Documents/Services/GuestDocumentService.php:257-305` uses `wp_handle_upload()` — the standard WordPress upload handler.

### Validation

`includes/Modules/Documents/Validators/GuestDocumentValidator.php`:

```php
private const ALLOWED_MIME_TYPES = [
    'image/jpeg', 'image/jpg', 'image/png', 'application/pdf',
];
private const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5 MB
private const ALLOWED_EXTENSIONS = [ 'jpg', 'jpeg', 'png', 'pdf' ];
```

| Check | Implemented? | Method |
|-------|-------------|--------|
| File extension whitelist | YES | `ALLOWED_EXTENSIONS` array check |
| MIME type whitelist | **PARTIAL** — only validates if MIME type is non-empty | `in_array($mime_type, ALLOWED_MIME_TYPES)` but skips check if empty |
| File size limit | YES | 5 MB max |
| File content sniffing | NO — relies on browser-reported MIME type | `$file['type']` is client-provided |

### Storage Location

```php
$upload_dir_filter = function ( array $uploads ) use ( $guestId ): array {
    $subdir            = '/nozule/documents/' . $guestId;
    $uploads['subdir'] = $subdir;
    $uploads['path']   = $uploads['basedir'] . $subdir;
    $uploads['url']    = $uploads['baseurl'] . $subdir;
    return $uploads;
};
```

Files stored at: `wp-content/uploads/nozule/documents/{guestId}/`

| Security Measure | Status |
|-----------------|--------|
| Files inside webroot? | **YES** — accessible via URL |
| .htaccess to block PHP execution? | **NO** — not found |
| Index.php/index.html to prevent directory listing? | **NO** |
| Authentication required for access? | **NO** — direct URL access possible |
| Random file names? | **PARTIAL** — WordPress adds hash prefix |

### Issues

1. **MIME type validation gap:** If the browser doesn't send a MIME type, the check is skipped entirely
2. **No server-side content sniffing:** File is not inspected with `finfo_file()` or `getimagesize()` to verify actual content matches claimed type
3. **No execution protection:** A renamed `.php` file could potentially be uploaded and executed
4. **Direct URL access:** Passport scans and ID documents are accessible to anyone who knows/guesses the URL pattern

---

## 23. Error Message Disclosure

### Exception Messages in REST Responses

All controllers catch exceptions and return the message to the client:

```php
// BookingController.php, AdminBookingController.php, etc.
} catch ( \InvalidArgumentException $e ) {
    return new WP_REST_Response( [
        'success' => false,
        'message' => $e->getMessage(),
    ], 400 );
} catch ( \RuntimeException $e ) {
    return new WP_REST_Response( [
        'success' => false,
        'message' => $e->getMessage(),
    ], 500 );
}
```

| What's Exposed | Status |
|----------------|--------|
| Exception messages | **YES** — returned as `message` field |
| Stack traces | **NO** — `getTrace()` never called |
| File paths | **NO** — `getFile()` never called |
| Line numbers | **NO** — `getLine()` never called |
| SQL queries | **NO** — database errors caught at service layer |
| Table/column names | **NO** — not exposed in API responses |

Exception messages are **business-logic strings** like:
- "No rooms available for the selected room type and dates."
- "Cannot confirm booking: current status is 'pending'."
- "Guest not found."

These reveal business logic state but not technical internals. **Acceptable for development, should be genericized for production.**

### Logger Implementation

`includes/Core/Logger.php` only writes to `error_log()` when `WP_DEBUG_LOG` is enabled:

```php
private function log( string $level, string $message, array $context = [] ): void {
    if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
        error_log( $formatted );
    }
    do_action( 'nozule/log', $level, $message, $context );
}
```

### WP_DEBUG References

```
WP_DEBUG:     Referenced in Logger.php:41 (conditional debug logging)
WP_DEBUG_LOG: Referenced in Logger.php:57 (conditional file logging)
```

The plugin never sets or modifies `WP_DEBUG`. It only checks the value for conditional logging. **No risk from the plugin itself** — but a production site with `WP_DEBUG = true` would expose debug logs.

### Assessment

**No stack traces, file paths, SQL queries, or database structure exposed in API responses.** Exception messages are user-friendly business logic strings. The main risk is that error messages reveal booking state machine transitions, which could help an attacker understand system behavior.

---

## Summary of Security Findings

| Question | Answer | Severity |
|----------|--------|----------|
| All queries using prepared statements? | **YES** — Database wrapper enforces `prepare()`. ORDER BY uses allowlists. | LOW |
| Capability checking on every action? | **YES** — every route has `permission_callback`. No per-entity auth (IDOR risk). | LOW (single-property) / MEDIUM (multi-property) |
| Nonce verification present? | **YES** — WordPress REST API handles CSRF automatically. No non-REST handlers exist. | NONE |
| Sensitive data encrypted? | **PARTIALLY** — only channel credentials encrypted. Guest PII, WhatsApp token, payment data in plaintext. | **CRITICAL** |
| File uploads validated? | **PARTIALLY** — extension + MIME whitelist, but MIME check skippable. No execution protection. No auth on file access. | **HIGH** |
| Error messages exposing internals? | **NO** — business logic messages only. No stack traces, file paths, or SQL. | LOW |

---

## 24. Business Logic Separation from WordPress Hooks (Q7)

**Overall Assessment: EXCELLENT separation — 90% of modules score 9-10/10.**

The plugin uses a consistent **4-layer Module architecture**:

```
Module (WordPress hooks + bootstrap)
  → Controller (REST endpoints)
    → Service (business logic)
      → Repository (data access)
```

### Best Examples (10/10 Separation)

| Module | File | Pattern |
|--------|------|---------|
| Notifications | `NotificationsModule.php` | Pure delegation — `onBookingCreated()` is 3 lines calling service |
| Branding | `BrandingModule.php` | Hook callbacks are simple service delegates |
| Reviews | `ReviewModule.php` | Clean REST + cron, zero business logic in hooks |
| Integrations | `IntegrationsModule.php` | 3-line closures, all logic in services |
| Forecasting | `ForecastingModule.php` | Cron handler delegates to service in try/catch |

### Worst Offenders

| Module | File | Lines | Hook | Issue | Score |
|--------|------|-------|------|-------|-------|
| **Metasearch** | `MetasearchModule.php` | 72-90 | `wp_head` | Inline closure with 3 conditional checks + data fetch + output — should be a named method delegating to service | 6/10 |
| **Billing** | `BillingModule.php` | 146-176 | `nozule/booking/checked_in` | Direct `$db->getRow()` SQL query + business logic (folio existence check) inside hook callback | 7/10 |
| **Housekeeping** | `HousekeepingModule.php` | 106-126 | `nozule/booking/checked_out` | Direct `$db->getRow("SELECT room_id FROM {$table}")` inside hook callback | 7/10 |

### Consistent Patterns Observed

- **REST route registration**: 30+ modules use 1-2 line closures that call `$controller->registerRoutes()`
- **Booking lifecycle events**: Named methods (e.g., `onBookingCheckedOut`) that delegate to services
- **Cron handlers**: Named methods with try/catch wrapping service calls
- **No inline business logic in `add_action()` closures** (except 3 offenders above)

**Migration Impact**: LOW — hooks are thin wrappers. Business logic lives in Services/Repositories and can be extracted cleanly.

---

## 25. PHP Class Classification (Q8)

**Total: ~257 PHP classes across 305 files.**

### Summary by Category

| Category | Count | Description |
|----------|-------|-------------|
| **Models** | 77 | Extend `BaseModel`, data representation with `fromRow()`, type casting, JSON field decoding |
| **Services** | 40 | Business logic orchestration, calculations, external integrations |
| **Controllers** | 35 | REST endpoint handlers + 30 Admin Page classes |
| **Repositories** | 35 | Extend `BaseRepository`, database CRUD operations |
| **Helper/Utility** | 30 | Validators (12), Core utilities (Database, Logger, Cache, Container, etc.) |
| **Modules** | 20 | Extend `BaseModule`, WordPress hook registration + DI wiring |
| **Mixed** | 2 | Violate single responsibility |

### Mixed Classes (Violations)

**1. `Plugin` class** (`includes/Core/Plugin.php`) — **GOD CLASS**
- Bootstrap + service registration + module management + hook registration + REST route setup + shortcode registration + asset management + maintenance tasks
- **Fix**: Extract `PluginBootstrapper`, `HookRegistry`, `ModuleRegistry`, `AssetManager`

**2. `RestController`** (`includes/API/RestController.php`) — **DUAL ROLE**
- Acts as both route registrar AND dispatcher
- Permission callbacks defined inline alongside route definitions
- **Fix**: Split into `RouteRegistry` + `RouteDispatcher`

### Module Architecture (per module)

Every feature module follows the same structure:
```
Module/
├── Models/          (1-7 classes per module)
├── Repositories/    (1-4 classes per module)
├── Services/        (1-3 classes per module)
├── Controllers/     (1-4 classes per module)
├── Validators/      (0-2 classes per module)
├── Exceptions/      (0-2 classes, Bookings only)
└── XxxModule.php    (1 module bootstrap)
```

### Notable: No Employee Model or Repository

The `Employees` module has only `EmployeeController` + `EmployeesModule`. It operates directly on WordPress `wp_users`/`wp_usermeta` via `wp_insert_user()`, `get_users()`, `get_user_meta()`. This is the **single biggest migration blocker** for user management.

**Migration Impact**: MEDIUM — class structure maps cleanly to Laravel (Model→Eloquent, Repository→can be replaced, Service→stays, Controller→stays). The 2 Mixed classes need refactoring.

---

## 26. WP-Native Function Calls in Business Logic — Portability Blockers (Q9)

### CRITICAL: Direct WordPress API Calls in Services

#### Email Sending (`wp_mail()`) — 2 Services

| File | Line | Method |
|------|------|--------|
| `Messaging/Services/EmailService.php` | 108 | `sendRawEmail()` — `wp_mail( $to, $subject, $body, $headers )` |
| `Notifications/Services/NotificationService.php` | 328 | `sendEmail()` — `wp_mail( $args['to'], $args['subject'], ... )` |

**Fix**: Create `MailerInterface` → inject `WpMailer` in WordPress, `SmtpMailer` in Laravel.

#### HTTP Client (`wp_remote_post/get()`) — 4 Services

| File | Line | Context |
|------|------|---------|
| `Channels/Services/BookingComApiClient.php` | 421 | OTA channel sync API calls |
| `Integrations/Services/WebhookConnector.php` | 114 | External webhook dispatch |
| `Integrations/Services/OdooConnector.php` | 311 | Odoo ERP integration |
| `WhatsApp/Services/WhatsAppService.php` | 366 | WhatsApp Business API |

Each also uses `wp_remote_retrieve_response_code()` and `wp_remote_retrieve_body()` (12+ additional calls).

**Fix**: Create `HttpClientInterface` → inject PSR-18 compatible client.

#### File Uploads (`wp_handle_upload()`) — 1 Service

| File | Line | Context |
|------|------|---------|
| `Documents/Services/GuestDocumentService.php` | 264, 287 | Requires `ABSPATH . 'wp-admin/includes/file.php'`, uses `wp_handle_upload()` |

Also uses `add_filter()`/`remove_filter()` for upload directory manipulation.

**Fix**: Create `FileUploadInterface` abstraction.

### HIGH: WordPress Options Leaking into Services

#### `get_option()` in Services — 8 instances

| File | Line | What's Read |
|------|------|-------------|
| `Notifications/Services/NotificationService.php` | 287, 309, 648 | `admin_email`, `date_format` |
| `Notifications/Services/TemplateService.php` | 117, 292 | `date_format`, `admin_email` |
| `Messaging/Services/EmailService.php` | 163, 244 | `admin_email` (fallback) |
| `WhatsApp/Services/WhatsAppService.php` | 231 | `admin_email` (fallback) |

Pattern: `$this->settings->get( 'hotel.email', get_option( 'admin_email' ) )` — uses WP option as fallback.

**Fix**: Standardize on `SettingsManager` exclusively. Seed `hotel.email` from `admin_email` during activation.

#### `get_bloginfo()` in Services — 6 instances

Same pattern: `$this->settings->get( 'hotel.name', get_bloginfo( 'name' ) )` in NotificationService, TemplateService, EmailService, WhatsAppService.

### HIGH: WordPress Hook System in Services — 15+ calls

| Service | `do_action()` | `apply_filters()` |
|---------|---------------|-------------------|
| `BookingService` | 8 (lifecycle events) | 0 |
| `NotificationService` | 3 (send events) | 2 (email args) |
| `NightAuditService` | 1 | 0 |
| `IntegrationService` | 1 | 1 |
| `TemplateService` | 0 | 2 |
| `BookingComApiClient` | 0 | 1 |

The plugin has its own `EventDispatcher` class but services bypass it, calling WordPress hooks directly.

**Fix**: Route all events through `EventDispatcher` interface. WordPress adapter fires `do_action()`, Laravel adapter fires Laravel events.

### MEDIUM: WordPress Date/Time — 15+ instances in Services

Services use `current_time('mysql')` and `wp_date()` instead of PHP's `DateTime`.

Found in: `ChannelSyncService` (5), `HousekeepingService` (2), `NightAuditService` (2), `BookingService` (1+), `POSService` (1), `ReviewService` (1), `DocumentService` (1).

**Fix**: Use `DateTimeImmutable` with timezone injection.

### MEDIUM: URL Building in Services — 6 instances

| File | Function | Context |
|------|----------|---------|
| `NotificationService.php:687` | `home_url()` | Booking confirmation URL |
| `NotificationService.php:690,693` | `add_query_arg()` | URL parameter building |
| `IntegrationService.php:170` | `home_url()` | Webhook payload site URL |
| `WebhookConnector.php:36` | `home_url()` | Test webhook payload |
| `GoogleHotelAdsService.php:261` | `home_url()` | Schema.org landing URL |
| `ReviewService.php:283,287` | `add_query_arg()` | Review tracking URLs |

### ACCEPTABLE: Controllers & Modules (Not Blockers)

| Function | Where Used | Assessment |
|----------|------------|------------|
| `current_user_can()` | RestController, Admin Pages | Expected at API boundary |
| `get_current_user_id()` | Controllers | Expected at API boundary |
| `sanitize_text_field()` etc. | Controllers (423 instances) | Expected — input sanitization |
| `wp_insert_user()` etc. | EmployeeController | Expected (but needs abstraction) |
| `wp_create_nonce()` | Plugin.php admin config | Expected for WP REST auth |

### Complete Portability Blocker Count

| Severity | Count | Location |
|----------|-------|----------|
| **CRITICAL** | 7 calls | `wp_mail()` (2), `wp_remote_post()` (4), `wp_handle_upload()` (1) |
| **HIGH** | 29 calls | `get_option()` (8), `get_bloginfo()` (6), `do_action/apply_filters` (15+) |
| **MEDIUM** | 21+ calls | `current_time/wp_date` (15+), `home_url/add_query_arg` (6) |
| **Total in Services** | **57+** | Across 15 service classes |

---

## 27. Hardcoded WordPress Paths, URLs, and Constants (Q10)

### In Business Logic (Outside Bootstrap/Config)

| Constant/Function | File | Line | Context | Severity |
|-------------------|------|------|---------|----------|
| `ABSPATH` | `Documents/Services/GuestDocumentService.php` | 264 | `require_once ABSPATH . 'wp-admin/includes/file.php'` | **HIGH** |
| `home_url()` | `Notifications/Services/NotificationService.php` | 687 | Booking confirmation URL | MEDIUM |
| `home_url()` | `Integrations/Services/IntegrationService.php` | 170 | Webhook payload | MEDIUM |
| `home_url()` | `Integrations/Services/WebhookConnector.php` | 36 | Test webhook payload | MEDIUM |
| `home_url()` | `Metasearch/Services/GoogleHotelAdsService.php` | 261 | Schema.org landing URL | MEDIUM |
| `add_query_arg()` | `Notifications/Services/NotificationService.php` | 690, 693 | URL params | MEDIUM |
| `add_query_arg()` | `Reviews/Services/ReviewService.php` | 283, 287 | Review URLs | MEDIUM |

### NOT Found in Business Logic (Good)

| Constant | Status |
|----------|--------|
| `WP_CONTENT_DIR` | Not in services/repos |
| `WP_PLUGIN_DIR` | Not in services/repos |
| `NZL_PLUGIN_DIR` | Bootstrap only |
| `NZL_PLUGIN_URL` | Bootstrap only |
| `NZL_VERSION` | Bootstrap only |
| `NZL_DB_VERSION` | Activator only |
| `WPINC` | Not found anywhere |
| `plugins_url()` | Admin/Core only |
| `admin_url()` | Admin/Core only |

**Migration Impact**: LOW-MEDIUM — only 1 `ABSPATH` usage and 6 URL function calls need replacement. Plugin constants properly isolated.

---

## 28. DTO / Data Contract Layer (Q11)

**Answer: NO formal DTOs — uses a hybrid pseudo-DTO pattern with array-based data contracts.**

### What Exists

1. **`BaseModel`** — acts as pseudo-DTO for database hydration
   - `fromRow(object $row)` → type casting → `new static($data)`
   - Models define `$intFields`, `$jsonFields`, `$casts` for automatic type conversion
   - `toArray()` for serialization back to arrays

2. **`BaseValidator`** — acts as implicit data contract
   - Declarative rules define the expected shape: `['name' => ['required', 'min' => 2, 'max' => 100]]`
   - 16 validation methods (required, email, date, integer, min, max, slug, etc.)
   - 12 module-specific validators extend it

3. **Controllers extract params** — selective field whitelisting
   ```php
   // RoomTypeController::extractRoomTypeData()
   $fields = ['name', 'slug', 'description', 'max_occupancy', 'base_price', ...];
   foreach ($fields as $field) {
       $value = $request->get_param($field);
       if ($value !== null) { $data[$field] = $value; }
   }
   // Then per-field sanitization
   ```

### What's Missing

- **No DTO classes** — no `CreateBookingRequest`, `UpdateRoomTypeRequest` typed objects
- **No interfaces for data shapes** — service methods accept `array $data`
- **No response DTOs** — controllers build response arrays inline

### Data Flow: HTTP Request → Database

```
HTTP Request (WP_REST_Request)
  ↓ get_param() / get_params()
Controller (selective extraction + sanitization)
  ↓ array $data
Service (validates via Validator + transforms + enriches)
  ↓ array $data (typed values)
Repository::create(array $data)
  ↓ parameterized query
Database ($wpdb->prepare)
  ↓ returns stdClass
Model::fromRow() → type casting → BaseModel instance
```

### Worst Offender: BookingController

```php
// Passes entire request array directly to service
$booking = $this->service->createBooking($request->get_params());
```

No field whitelisting at controller level — relies entirely on service-level validation.

### Best Example: RoomTypeController

```php
private function extractRoomTypeData(WP_REST_Request $request): array {
    $fields = ['name', 'slug', 'description', 'max_occupancy', ...];
    $data = [];
    foreach ($fields as $field) {
        $value = $request->get_param($field);
        if ($value !== null) { $data[$field] = $value; }
    }
    if (isset($data['name'])) { $data['name'] = sanitize_text_field($data['name']); }
    if (isset($data['slug'])) { $data['slug'] = sanitize_title($data['slug']); }
    if (isset($data['base_price'])) { $data['base_price'] = (float)$data['base_price']; }
    return $data;
}
```

### Direct `$_POST`/`$_GET` Access

**Only 1 occurrence in entire codebase:**
- `Admin/StaffIsolation.php:138` — `$page = sanitize_text_field($_GET['page'] ?? '')`

All other data access uses `WP_REST_Request` API. Zero `$_POST`/`$_REQUEST` usage.

### Migration Impact

| Aspect | Current | Laravel Target | Effort |
|--------|---------|---------------|--------|
| Input extraction | `WP_REST_Request->get_param()` | `Request->input()` / Form Requests | LOW |
| Validation | Custom `BaseValidator` | Laravel `Validator` / Form Requests | MEDIUM |
| Type casting | Model `$casts` arrays | Eloquent `$casts` | LOW |
| Response building | Inline arrays | API Resources | MEDIUM |
| DTO layer | Missing | Laravel Form Requests = DTOs | NEW WORK |

**Recommendation**: When migrating to Laravel, create Form Request classes per endpoint. The existing validator rules translate almost 1:1 to Laravel validation rules.

---

## Architecture Audit Summary (Q7-Q11)

| Question | Finding | Migration Risk |
|----------|---------|---------------|
| Q7: Business logic separated from WP hooks? | **YES** — 90% excellent. 3 minor offenders (Metasearch, Billing, Housekeeping) | **LOW** |
| Q8: Service classes or procedural? | **257 classes, well-organized**. Module→Controller→Service→Repository pattern. 2 Mixed classes (Plugin, RestController) | **LOW** |
| Q9: WP-native calls in core logic? | **57+ calls in 15 services**. Critical: `wp_mail` (2), `wp_remote_post` (4), `wp_handle_upload` (1). High: `get_option` (8), `do_action` (15+) | **HIGH** |
| Q10: Hardcoded WP paths/URLs? | **7 instances** in services. 1 `ABSPATH`, 6 URL function calls. Plugin constants properly isolated to bootstrap | **LOW** |
| Q11: DTO/data contract layer? | **No formal DTOs**. Array-based contracts with validators as implicit schemas. Models as pseudo-DTOs. Only 1 `$_GET` usage in entire codebase | **MEDIUM** |
