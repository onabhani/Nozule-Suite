# Dashboard Design Guide — Nozule-Style WordPress Plugin

Use this prompt when asking Claude Code to build admin dashboard pages for a new WordPress plugin that follows the same design system as Nozule Suite.

---

## Prompt (copy and paste this)

```
Build admin dashboard pages for this WordPress plugin following this exact frontend stack and design system. No build tools — everything loads via CDN.

### Tech Stack

| Layer | Technology | Version | How Loaded |
|-------|-----------|---------|------------|
| CSS Framework | Tailwind CSS | 3.x | CDN: `https://cdn.jsdelivr.net/npm/tailwindcss@3/dist/tailwind.min.css` |
| JS Framework | Alpine.js | 3.x | CDN: `https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js` |
| Custom Styles | Single `admin.css` file | — | WordPress `wp_enqueue_style` |
| RTL Styles | `admin-rtl.css` (optional) | — | Conditional on `is_rtl()` |
| REST Client | Custom JS API wrapper | — | WordPress `wp_enqueue_script` |

**No build tools.** No webpack, no Vite, no node_modules. Tailwind and Alpine.js are loaded purely from CDN.

### Asset Loading Order (Critical)

Scripts must load in this exact order in the footer:

1. **Core JS utilities** (plain JS, no Alpine dependency):
   - `api.js` — REST API client wrapper around `fetch()` with WP nonce auth
   - `utils.js` — Helpers for formatting prices, dates, generating unique IDs, toast shortcuts
   - `i18n.js` — Simple translation function (optional)

2. **Alpine.js global store** (`store.js`):
   - Registers `alpine:init` event listener
   - Defines shared stores (e.g., `Alpine.store('notifications', { items: [], add(), remove() })`)
   - Must load BEFORE module scripts and BEFORE Alpine.js CDN

3. **Module/page-specific scripts** (e.g., `dashboard.js`, `bookings.js`):
   - Each registers an Alpine component via `alpine:init` event listener
   - Pattern: `document.addEventListener('alpine:init', function() { Alpine.data('componentName', function() { return { ... } }) })`
   - Depends on core utilities and store

4. **Alpine.js CDN** — loaded LAST:
   - Must depend on ALL component scripts so `alpine:init` listeners are registered before Alpine auto-starts
   - Use `wp_enqueue_script('alpinejs', CDN_URL, [...all_component_handles], VERSION, true)`

### CSS Architecture

Use a **hybrid approach**: Tailwind utilities for layout/spacing inline + custom prefixed CSS classes for reusable components.

**Prefix all custom classes** with your plugin prefix (e.g., `.myplugin-*` or `.nzl-*`) to avoid conflicts with WordPress admin styles.

#### Required CSS Components

```css
/* --- Layout --- */
.{prefix}-admin-wrap        /* Main page container: margin: 20px, system font stack */
.{prefix}-admin-header      /* Page header: flexbox, space-between, title + actions */
.{prefix}-admin-loading     /* Centered loading state with spinner */

/* --- Stats Cards --- */
.{prefix}-stats-grid        /* CSS Grid: repeat(auto-fit, minmax(220px, 1fr)), gap 1rem */
.{prefix}-stat-card         /* White card: border #e2e8f0, radius 0.5rem, shadow */
  .stat-label               /* Uppercase, 0.75rem, #64748b, letter-spacing 0.05em */
  .stat-value               /* Bold 1.5rem, #1e293b */

/* --- Tables --- */
.{prefix}-table-wrap        /* White container with overflow-x: auto for mobile scroll */
.{prefix}-table             /* Full-width, collapse borders */
  th                        /* Background #f8fafc, uppercase labels, #64748b */
  td                        /* 0.875rem, #334155, bottom border #f1f5f9 */
  tr:hover td               /* Subtle hover: #f8fafc */

/* --- Tabs --- */
.{prefix}-tabs              /* Flexbox row with bottom border */
.{prefix}-tab               /* No background, bottom border highlight on .active */
.{prefix}-tab.active        /* Color: #1e40af, border-bottom-color: #1e40af */

/* --- Buttons --- */
.{prefix}-btn               /* Base: inline-flex, 0.875rem, border #e2e8f0, radius 0.375rem */
.{prefix}-btn-primary       /* Background: #1e40af, white text */
.{prefix}-btn-success       /* Background: #059669, white text */
.{prefix}-btn-danger        /* Background: #dc2626, white text */
.{prefix}-btn-sm            /* Smaller padding for table action buttons */

/* --- Badges --- */
.{prefix}-badge             /* Inline pill: border-radius 9999px, 0.75rem */
.{prefix}-badge-confirmed   /* Green: bg #dcfce7, text #166534 */
.{prefix}-badge-pending     /* Yellow: bg #fef9c3, text #854d0e */
.{prefix}-badge-cancelled   /* Red: bg #fee2e2, text #991b1b */

/* --- Modals --- */
.{prefix}-modal-overlay     /* Fixed fullscreen, bg rgba(0,0,0,0.5), z-index 99999 */
.{prefix}-modal             /* Centered white box, max-width 640px, flex column */
.{prefix}-modal-header/body/footer  /* Structured sections with borders */

/* --- Sidebar Panel (slides from right) --- */
.{prefix}-sidebar-panel     /* Width 480px, slide-in animation, right-anchored */

/* --- Toast Notifications --- */
.{prefix}-toast-container   /* Fixed top-right, z-index 100000 */
.{prefix}-toast             /* Rounded bar with dismiss button */
.{prefix}-toast-success/error/warning/info  /* Color variants */

/* --- Forms --- */
.{prefix}-label             /* 0.85rem, #475569 */
.{prefix}-input             /* Full width, border #cbd5e1, focus ring #3b82f6 */
.{prefix}-form-grid         /* 2-column CSS grid for form layouts */
.{prefix}-form-group        /* Flex column: label + input */

/* --- Spinner --- */
.{prefix}-spinner           /* CSS border spinner, 0.6s rotation */
.{prefix}-spinner-lg        /* 2rem size variant */

/* --- Cards --- */
.{prefix}-card              /* White, border, radius, padding, shadow */

/* --- Filters Bar --- */
.{prefix}-filters           /* Flex wrap, background #f8fafc, for search/filter controls */
```

#### Color Palette

| Token | Value | Usage |
|-------|-------|-------|
| Primary | `#1e40af` | Buttons, active tabs, links, spinner accent |
| Success | `#059669` / `#10b981` | Confirm actions, positive badges |
| Danger | `#dc2626` / `#ef4444` | Delete/cancel actions, error toasts |
| Warning | `#d97706` / `#f59e0b` | Warning toasts, pending badges |
| Info | `#2563eb` | Info toasts, checked-in badge |
| Text Primary | `#1e293b` | Headings, stat values |
| Text Secondary | `#334155` | Table cells, body text |
| Text Muted | `#64748b` | Labels, placeholders, subtle text |
| Border | `#e2e8f0` | Cards, tables, dividers |
| Background | `#f8fafc` | Table headers, filter bars, hover states |
| Background Alt | `#f1f5f9` | Table row borders |

#### Responsive Breakpoints

```css
/* Tablet ≤ 960px */
  - Stats grid: 2 columns
  - Sidebar panel: full width
  - Tables: smaller padding
  - Filters: stack vertically

/* Mobile ≤ 600px */
  - Stats grid: 1 column
  - Header: stack vertically
  - Modals: full-width, bottom-anchored
  - Form grids: single column
  - Tabs: horizontal scroll
  - Toasts: full width

/* Small phones ≤ 400px */
  - Reduce all font sizes and padding further
```

### PHP Template Pattern

Each admin page follows this structure:

```php
<?php
// Page class (includes/Admin/Pages/SomePage.php)
namespace MyPlugin\Admin\Pages;

class SomePage {
    public function render(): void {
        if ( ! current_user_can( 'required_capability' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'my-plugin' ) );
        }
        include MY_PLUGIN_DIR . 'templates/admin/some-page.php';
    }
}
```

```php
<?php // Template file (templates/admin/some-page.php)
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="{prefix}-admin-wrap" x-data="myComponent">
    <div class="{prefix}-admin-header">
        <h1><?php esc_html_e( 'Page Title', 'my-plugin' ); ?></h1>
        <button class="{prefix}-btn {prefix}-btn-primary" @click="create()">
            <?php esc_html_e( 'Add New', 'my-plugin' ); ?>
        </button>
    </div>

    <!-- Loading state -->
    <template x-if="loading">
        <div class="{prefix}-admin-loading">
            <div class="{prefix}-spinner {prefix}-spinner-lg"></div>
        </div>
    </template>

    <!-- Content (shown after loading) -->
    <template x-if="!loading">
        <div>
            <!-- Stats cards, tables, modals go here -->
        </div>
    </template>
</div>

<!-- Toast container (one per page, at bottom of template) -->
<div class="{prefix}-toast-container" x-data x-show="$store.notifications.items.length > 0">
    <template x-for="notif in $store.notifications.items" :key="notif.id">
        <div class="{prefix}-toast" :class="'{prefix}-toast-' + notif.type">
            <span x-text="notif.message"></span>
            <button @click="$store.notifications.remove(notif.id)">&times;</button>
        </div>
    </template>
</div>
```

### Alpine.js Component Pattern

```javascript
// assets/js/admin/some-page.js
document.addEventListener('alpine:init', function () {
    Alpine.data('myComponent', function () {
        return {
            // State
            items: [],
            loading: true,
            activeTab: 'tab1',

            // Lifecycle
            init: function () {
                this.loadData();
            },

            // Data fetching via REST API client
            loadData: function () {
                var self = this;
                self.loading = true;
                MyPluginAPI.get('/admin/endpoint').then(function (response) {
                    self.items = response.data || [];
                }).catch(function (err) {
                    console.error('Load error:', err);
                }).finally(function () {
                    self.loading = false;
                });
            },

            // Actions
            deleteItem: function (id) {
                var self = this;
                MyPluginAPI.delete('/admin/items/' + id).then(function () {
                    self.loadData();
                    Alpine.store('notifications').add({
                        id: Date.now(),
                        message: 'Item deleted',
                        type: 'success'
                    });
                }).catch(function (err) {
                    Alpine.store('notifications').add({
                        id: Date.now(),
                        message: err.message,
                        type: 'error'
                    });
                });
            }
        };
    });
});
```

### REST API Client Pattern

```javascript
// assets/js/core/api.js
const MyPluginAPI = {
    get config() {
        return window.MyPluginAdmin || {};
    },
    get baseURL() {
        return this.config.apiBase || '/wp-json/myplugin/v1';
    },
    get nonce() {
        return this.config.nonce || '';
    },
    async request(method, endpoint, data) {
        const url = new URL(this.baseURL + endpoint, window.location.origin);
        if (method === 'GET' && data) {
            Object.keys(data).forEach(function (k) {
                if (data[k] != null) url.searchParams.append(k, data[k]);
            });
        }
        const config = {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': this.nonce
            }
        };
        if (method !== 'GET' && data) {
            config.body = JSON.stringify(data);
        }
        const response = await fetch(url.toString(), config);
        const json = await response.json();
        if (!response.ok) {
            const error = new Error(json.message || 'Request failed');
            error.status = response.status;
            throw error;
        }
        return json;
    },
    get: function (endpoint, params) { return this.request('GET', endpoint, params); },
    post: function (endpoint, data) { return this.request('POST', endpoint, data); },
    put: function (endpoint, data) { return this.request('PUT', endpoint, data); },
    delete: function (endpoint) { return this.request('DELETE', endpoint); }
};
window.MyPluginAPI = MyPluginAPI;
```

### Asset Enqueuing Pattern (PHP)

```php
// includes/Admin/AdminAssets.php
public function enqueue( string $hook_suffix ): void {
    if ( ! $this->isPluginPage( $hook_suffix ) ) return;

    // 1. Tailwind CSS (CDN)
    wp_enqueue_style( 'tailwindcss',
        'https://cdn.jsdelivr.net/npm/tailwindcss@3/dist/tailwind.min.css', [], '3.4.0' );

    // 2. Plugin admin CSS (depends on Tailwind)
    wp_enqueue_style( 'myplugin-admin',
        MY_PLUGIN_URL . 'assets/css/admin.css', [ 'tailwindcss' ], MY_PLUGIN_VERSION );

    // 3. RTL CSS (conditional)
    if ( is_rtl() ) {
        wp_enqueue_style( 'myplugin-admin-rtl',
            MY_PLUGIN_URL . 'assets/css/admin-rtl.css', [ 'myplugin-admin' ], MY_PLUGIN_VERSION );
    }

    // 4. Core JS utilities (footer, no Alpine dependency)
    wp_enqueue_script( 'myplugin-api',
        MY_PLUGIN_URL . 'assets/js/core/api.js', [], MY_PLUGIN_VERSION, true );
    wp_enqueue_script( 'myplugin-utils',
        MY_PLUGIN_URL . 'assets/js/core/utils.js', [], MY_PLUGIN_VERSION, true );

    // 5. Alpine store (depends on utils)
    wp_enqueue_script( 'myplugin-store',
        MY_PLUGIN_URL . 'assets/js/core/store.js', [ 'myplugin-utils' ], MY_PLUGIN_VERSION, true );

    // 6. Admin page scripts (each registers alpine:init listener)
    $scripts = [
        'myplugin-admin-dashboard' => 'dashboard.js',
        'myplugin-admin-settings'  => 'settings.js',
        // ... add more as needed
    ];
    $handles = [];
    foreach ( $scripts as $handle => $file ) {
        wp_enqueue_script( $handle,
            MY_PLUGIN_URL . 'assets/js/admin/' . $file,
            [ 'myplugin-api', 'myplugin-utils', 'myplugin-store' ],
            MY_PLUGIN_VERSION, true );
        $handles[] = $handle;
    }

    // 7. Alpine.js CDN — LAST (depends on store + all component scripts)
    wp_enqueue_script( 'alpinejs',
        'https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js',
        array_merge( [ 'myplugin-store' ], $handles ), '3.14.0', true );

    // 8. Localized config object (injected into api.js)
    wp_localize_script( 'myplugin-api', 'MyPluginAdmin', [
        'nonce'    => wp_create_nonce( 'wp_rest' ),
        'apiBase'  => rest_url( 'myplugin/v1' ),
        'adminUrl' => admin_url(),
        'locale'   => get_locale(),
        'isRtl'    => is_rtl(),
        'userId'   => get_current_user_id(),
        'isAdmin'  => current_user_can( 'manage_options' ),
    ] );
}
```

### File Structure

```
my-plugin/
├── assets/
│   ├── css/
│   │   ├── admin.css          # All custom admin component styles
│   │   └── admin-rtl.css      # RTL overrides (optional)
│   └── js/
│       ├── core/
│       │   ├── api.js          # REST API client
│       │   ├── utils.js        # Formatting helpers
│       │   ├── i18n.js         # Translation helper (optional)
│       │   └── store.js        # Alpine.js global stores
│       └── admin/
│           ├── dashboard.js    # Dashboard Alpine component
│           ├── settings.js     # Settings Alpine component
│           └── ...             # One file per admin page
├── includes/
│   └── Admin/
│       ├── AdminMenu.php       # add_menu_page / add_submenu_page
│       ├── AdminAssets.php     # wp_enqueue_style / wp_enqueue_script
│       └── Pages/
│           ├── DashboardPage.php
│           └── ...
├── templates/
│   └── admin/
│       ├── dashboard.php       # Dashboard HTML + Alpine directives
│       └── ...
└── my-plugin.php               # Plugin entry point
```

### Key Design Principles

1. **No build tools** — CDN only for Tailwind and Alpine.js
2. **Hybrid CSS** — Tailwind utilities inline + prefixed custom classes in admin.css
3. **Alpine.js for reactivity** — `x-data`, `x-show`, `x-for`, `x-text`, `@click`
4. **REST API for data** — All data flows through `wp-json` endpoints with nonce auth
5. **Global notification store** — `Alpine.store('notifications')` for toast messages
6. **Loading states** — Every page shows a spinner while data loads via API
7. **Responsive by default** — Three breakpoints (960px, 600px, 400px)
8. **WordPress conventions** — `esc_html_e()` for output, `sanitize_text_field()` for input, capabilities for access control
9. **Scoped assets** — Only load CSS/JS on plugin pages (check hook suffix)
10. **Alpine loads LAST** — All `alpine:init` listeners must be registered before Alpine auto-starts
```
