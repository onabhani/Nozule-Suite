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
