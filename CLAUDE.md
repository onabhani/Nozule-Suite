# CLAUDE.md — Nozule Suite

Active development — pre-launch. Targeting pilot validation on WordPress before potential Laravel rebuild at month 6-12. Do not gold-plate features or over-architect.

## Commands

```bash
# Test
vendor/bin/phpunit

# Deploy (WordPress plugin activation runs migrations automatically)
# Upload plugin directory to wp-content/plugins/ and activate via WP Admin

# Cache clear
wp cache flush
```

## Dependencies

- **Required:** PHP 8.2+, Composer (dev only — for PHPUnit/Mockery)
- **Runtime:** Alpine.js 3.x (CDN), Tailwind CSS (CDN) — no local node_modules
- **No dependency on:** Gravity Forms, Gravity Flow, Simple HR Suite, or any other hdqah.com plugins

## Architecture

WordPress plugin using a modular, Laravel-inspired architecture. Single entry point (`nozule.php`) boots a singleton `Plugin` class that initializes a DI container and loads 27 modules.

### Boot Sequence

1. `plugins_loaded` → `Plugin::getInstance()->boot()`
2. Core services registered: Database, CacheManager, SettingsManager, Logger, EventDispatcher
3. Each module's `register()` method binds its services to the container
4. `rest_api_init` → `RestController` registers all REST routes grouped by access level

### REST API

- Namespace: `nozule/v1`
- Central registrar: `includes/API/RestController.php`
- Route groups: public (no auth), staff, admin, property-switch (super admin)
- Each module controller implements `registerRoutes()`
- Permission callbacks use WordPress capabilities (`nzl_manage_rooms`, `nzl_admin`, `nzl_staff`)

### Module Structure

Each module in `includes/Modules/{Name}/` follows:

```
{Name}Module.php       — Bootstrap, registers services
Controllers/           — REST API endpoints
Models/                — Data models (extends BaseModel)
Repositories/          — Data access (extends BaseRepository)
Services/              — Business logic
Validators/            — Input validation
Exceptions/            — Custom exceptions (optional)
```

### Module Status

| Status | Modules |
|--------|---------|
| Active development | Bookings, Rooms, Pricing, Billing, Guests, Channels, Notifications, POS, Groups, Loyalty, Promotions, Currency, Housekeeping, Reports, Forecasting, Audit, Reviews, WhatsApp, Messaging, RateShopping, Property, Branding |
| Stable | Settings, Documents, Employees, Integrations, Metasearch |
| Stub/incomplete | Channels' OTA connectors (Booking.com, Expedia) — placeholder methods with TODOs for availability push, rate sync, reservation pull/confirm/cancel |

### Frontend

- Alpine.js 3.x for interactivity, Tailwind CSS for styling — both loaded via CDN
- `NozuleConfig` JS global (injected via `wp_localize_script`) exposes `locale`, `dateFormat`, `currency`, `restUrl`, `nonce` to Alpine components
- RTL CSS (`assets/css/rtl.css`) loaded conditionally when `is_rtl()` returns true

### Core Classes

| Class | Purpose |
|-------|---------|
| `Core/Plugin.php` | Singleton bootstrap |
| `Core/Container.php` | PSR-11-style DI container |
| `Core/Database.php` | Custom table wrapper over `wpdb` |
| `Core/BaseModule.php` | Abstract module base |
| `Core/BaseRepository.php` | Data access with property scoping |
| `Core/BaseModel.php` | ORM-style model with `fromRow()` and type casting |
| `Core/SettingsManager.php` | Settings storage with encryption for sensitive keys |
| `Core/PropertyScope.php` | Multi-property context resolution |
| `Core/Activator.php` | Plugin activation (runs migrations) |

## Conventions

### Naming

- **Namespace:** `Nozule\Modules\{ModuleName}\{Layer}` (PSR-4, autoloaded from `includes/`)
- **DB tables:** `{wp_prefix}nzl_{table_name}` (snake_case). Foreign keys end `_id`, timestamps end `_at`
- **Arabic columns:** Tables with translatable text have `_ar` suffix variants (e.g., `name` + `name_ar`, `description` + `description_ar`)
- **Hooks:** `nozule/{event}` for actions/filters (e.g., `nozule/booking/created`)
- **Options:** `nzl_` prefix (e.g., `nzl_db_version`, `nzl_activated_at`)
- **Capabilities:** `nzl_` prefix (e.g., `nzl_manage_rooms`, `nzl_admin`, `nzl_staff`)
- **Roles:** `nzl_manager`, `nzl_reception`
- **Constants:** `NZL_` prefix (e.g., `NZL_VERSION`, `NZL_PLUGIN_DIR`)

### Code Style

- No linter configured — follow WordPress-like spacing, PSR-4 namespacing, strict types not enforced
- Type hints on all method signatures
- WordPress sanitization (`sanitize_text_field`, `absint`) on input, escaping (`esc_html`, `esc_attr`) on output

### Settings

- Plugin settings stored in `nzl_settings` table (not `wp_options`), managed by `SettingsManager`
- Access: `$settings->get('group.key', $default)` / `$settings->set('group.key', $value)`
- Sensitive keys encrypted via `CredentialVault`: `integrations.odoo_api_key`, `integrations.webhook_secret`, `metasearch.partner_key`

### Migrations

- Location: `migrations/NNN_description.php`
- Functions: `nzl_migration_NNN_function_name()` (global)
- Run sequentially by `Activator::createTables()` on plugin activation
- Numbering has a gap: 013 is missing. Current latest is `016`. Next migration should be `017`

## Boundaries

- **`vendor/`** — Composer-managed. Do not edit
- **`wp_posts` / `wp_options`** — Never query `wp_posts`. Plugin uses custom `nzl_*` tables exclusively. Only `nzl_db_version` and `nzl_activated_at` stored in `wp_options`
- **Sensitive keys** — Never log or expose values from `CredentialVault`. Use `$settings->get()`, never raw DB queries for encrypted fields
- **Property scoping** — All repository queries must scope by `property_id` via `BaseRepository`. Never bypass with direct `$wpdb` queries unless explicitly needed

## Known Gotchas

- **PropertyScope session methods are no-ops:** `PropertyScope::getSessionProperty()` and `setSessionProperty()` are stub implementations (Phase 2 TODO). Super admins cannot persist property filters across requests
- **OTA connectors are stubs:** `BookingComConnector` and `ExpediaConnector` in Channels have placeholder methods that return empty `SyncResult` objects. Do not call them expecting real API responses
- **No uninstall safety net:** `uninstall.php` drops all `nzl_*` tables when `nzl_remove_data_on_uninstall` option is true. Be careful with that flag in production

## Domain Terminology

- **Folio** — Guest's itemized bill (`nzl_folios`, `nzl_folio_items`)
- **Night Audit** — Daily end-of-day financial/occupancy snapshot (`nzl_night_audits`)
- **Rate Plan** — Pricing package (e.g., "Room Only", "Half Board") (`nzl_rate_plans`)
- **Inventory** — Room availability and pricing per date (`nzl_room_inventory`)
- **Room Type** vs **Room** — Category (Standard, Deluxe) vs physical room (#123)
- **ADR** — Average Daily Rate (computed)
- **RevPAR** — Revenue Per Available Room (computed)
- **Booking Source** — Origin enum: `direct`, `website`, `booking_com`, `expedia`, `airbnb`, `phone`, `walk_in`
- **OTA** — Online Travel Agency (Booking.com, Expedia, Agoda)
- **Channel Manager** — System that syncs availability/rates across OTAs
- **Rate Shopping** — Monitoring competitor rates for parity alerts
