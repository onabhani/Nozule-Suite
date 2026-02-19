/**
 * Nozule PWA Service Worker
 *
 * Provides offline support, caching strategies, and background sync
 * for the Nozule hotel management system.
 *
 * @package Nozule
 * @since   1.0.8
 */

'use strict';

const CACHE_NAME = 'nozule-v1';
const OFFLINE_URL = './offline.html';

/**
 * Critical assets to pre-cache during install.
 * Paths are relative to the plugin URL and will be resolved at runtime.
 */
const PRECACHE_ASSETS = [
    OFFLINE_URL,
];

/**
 * URL patterns that should NEVER be cached.
 * Includes WP admin AJAX, REST API mutations, and WP login/cron.
 */
const NO_CACHE_PATTERNS = [
    /\/wp-admin\/admin-ajax\.php/,
    /\/wp-json\/.+/,       // All REST API calls handled separately below
    /\/wp-login\.php/,
    /\/wp-cron\.php/,
    /\?doing_wp_cron/,
    /\/xmlrpc\.php/,
];

/**
 * REST API read patterns (GET only) that CAN be cached with network-first.
 */
const CACHEABLE_API_PATTERN = /\/wp-json\/nozule\/v1\//;

/**
 * Static asset extensions eligible for cache-first strategy.
 */
const STATIC_EXTENSIONS = /\.(css|js|svg|png|jpg|jpeg|gif|webp|woff|woff2|ttf|eot|ico)(\?.*)?$/i;

// ---------------------------------------------------------------------------
// Install: pre-cache critical assets
// ---------------------------------------------------------------------------
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => {
                return cache.addAll(PRECACHE_ASSETS);
            })
            .then(() => {
                // Activate immediately instead of waiting for all clients to close.
                return self.skipWaiting();
            })
            .catch((err) => {
                console.error('[Nozule SW] Pre-cache failed:', err);
            })
    );
});

// ---------------------------------------------------------------------------
// Activate: clean old caches & claim clients
// ---------------------------------------------------------------------------
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((cacheNames) => {
                return Promise.all(
                    cacheNames
                        .filter((name) => name !== CACHE_NAME)
                        .map((name) => {
                            console.log('[Nozule SW] Deleting old cache:', name);
                            return caches.delete(name);
                        })
                );
            })
            .then(() => {
                // Take control of all open pages immediately.
                return self.clients.claim();
            })
    );
});

// ---------------------------------------------------------------------------
// Fetch: route requests through the appropriate caching strategy
// ---------------------------------------------------------------------------
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    // Only handle GET requests for caching; let mutations pass through.
    if (request.method !== 'GET') {
        return;
    }

    // Skip requests that should never be cached.
    if (shouldSkipCache(url, request)) {
        return;
    }

    // Nozule REST API GET requests -> network-first
    if (CACHEABLE_API_PATTERN.test(url.pathname)) {
        event.respondWith(networkFirst(request));
        return;
    }

    // Static assets -> cache-first
    if (STATIC_EXTENSIONS.test(url.pathname)) {
        event.respondWith(cacheFirst(request));
        return;
    }

    // Navigation requests -> network-first with offline fallback
    if (request.mode === 'navigate') {
        event.respondWith(networkFirstWithOfflineFallback(request));
        return;
    }

    // Everything else -> network-first
    event.respondWith(networkFirst(request));
});

// ---------------------------------------------------------------------------
// Caching strategies
// ---------------------------------------------------------------------------

/**
 * Network-first: try network, fall back to cache.
 * Updates the cache with fresh responses for future offline use.
 */
async function networkFirst(request) {
    try {
        const networkResponse = await fetch(request);
        if (networkResponse && networkResponse.ok) {
            const cache = await caches.open(CACHE_NAME);
            cache.put(request, networkResponse.clone());
        }
        return networkResponse;
    } catch (err) {
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        throw err;
    }
}

/**
 * Cache-first: serve from cache, refresh from network in background.
 * If not cached yet, fetch from network and cache the result.
 */
async function cacheFirst(request) {
    const cachedResponse = await caches.match(request);
    if (cachedResponse) {
        // Refresh cache in the background (stale-while-revalidate).
        refreshCache(request);
        return cachedResponse;
    }

    try {
        const networkResponse = await fetch(request);
        if (networkResponse && networkResponse.ok) {
            const cache = await caches.open(CACHE_NAME);
            cache.put(request, networkResponse.clone());
        }
        return networkResponse;
    } catch (err) {
        // Nothing in cache, network failed — return a basic error response.
        return new Response('', {
            status: 503,
            statusText: 'Service Unavailable',
        });
    }
}

/**
 * Network-first with offline fallback for navigation requests.
 * On failure, serves the pre-cached offline page.
 */
async function networkFirstWithOfflineFallback(request) {
    try {
        const networkResponse = await fetch(request);
        if (networkResponse && networkResponse.ok) {
            const cache = await caches.open(CACHE_NAME);
            cache.put(request, networkResponse.clone());
        }
        return networkResponse;
    } catch (err) {
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        // Serve offline fallback page.
        const offlineResponse = await caches.match(OFFLINE_URL);
        if (offlineResponse) {
            return offlineResponse;
        }
        // Last resort: a plain text error.
        return new Response('You are offline. Please check your connection.', {
            status: 503,
            headers: { 'Content-Type': 'text/plain' },
        });
    }
}

/**
 * Background cache refresh — fetches a fresh copy without blocking.
 */
function refreshCache(request) {
    fetch(request)
        .then((response) => {
            if (response && response.ok) {
                caches.open(CACHE_NAME).then((cache) => {
                    cache.put(request, response);
                });
            }
        })
        .catch(() => {
            // Silently fail — we already served from cache.
        });
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Determine if a request should bypass the cache entirely.
 */
function shouldSkipCache(url, request) {
    // Skip non-GET requests (already handled above, but defensive).
    if (request.method !== 'GET') {
        return true;
    }

    // Skip Chrome extension and non-http(s) requests.
    if (!url.protocol.startsWith('http')) {
        return true;
    }

    // Skip patterns in the no-cache list.
    for (const pattern of NO_CACHE_PATTERNS) {
        if (pattern.test(url.href)) {
            return true;
        }
    }

    return false;
}
