<?php
/**
 * PWA (Progressive Web App) support for Nozule.
 *
 * Registers the Web App Manifest, meta tags, and service worker
 * so the Nozule admin and public interfaces can be installed as
 * a standalone app on mobile and desktop devices.
 *
 * @package Nozule\Core
 * @since   1.0.8
 */

namespace Nozule\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PWA {

    /**
     * Register all PWA-related WordPress hooks.
     */
    public function register(): void {
        // Admin pages
        add_action( 'admin_head', [ $this, 'addManifestLink' ] );
        add_action( 'admin_head', [ $this, 'addMetaTags' ] );
        add_action( 'admin_footer', [ $this, 'registerServiceWorker' ] );

        // Public / front-end pages
        add_action( 'wp_head', [ $this, 'addManifestLink' ] );
        add_action( 'wp_head', [ $this, 'addMetaTags' ] );
        add_action( 'wp_footer', [ $this, 'registerServiceWorker' ] );
    }

    /**
     * Output the <link rel="manifest"> tag.
     */
    public function addManifestLink(): void {
        printf(
            '<link rel="manifest" href="%s" crossorigin="use-credentials">' . "\n",
            esc_url( NZL_PLUGIN_URL . 'assets/pwa/manifest.json' )
        );
    }

    /**
     * Output PWA-related meta tags.
     *
     * Covers: theme colour, Apple mobile web app, viewport, and touch icon.
     */
    public function addMetaTags(): void {
        $icon_url = esc_url( NZL_PLUGIN_URL . 'assets/pwa/icon.svg' );
        ?>
        <meta name="theme-color" content="#1e3a5f">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="Nozule">
        <meta name="application-name" content="Nozule">
        <meta name="msapplication-TileColor" content="#1e3a5f">
        <link rel="apple-touch-icon" href="<?php echo $icon_url; ?>">
        <?php
    }

    /**
     * Output an inline script that registers the service worker.
     *
     * The service worker file lives inside the plugin's assets/pwa/ directory.
     * Registration only runs when the browser supports the Service Worker API.
     */
    public function registerServiceWorker(): void {
        $sw_url = esc_url( NZL_PLUGIN_URL . 'assets/pwa/service-worker.js' );
        ?>
        <script>
        (function () {
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', function () {
                    navigator.serviceWorker
                        .register('<?php echo $sw_url; ?>')
                        .then(function (registration) {
                            // Check for updates periodically (every 60 minutes).
                            setInterval(function () {
                                registration.update();
                            }, 60 * 60 * 1000);

                            if (registration.waiting) {
                                // A new SW is already waiting — notify user if needed.
                                console.log('[Nozule PWA] New version ready.');
                            }
                        })
                        .catch(function (err) {
                            console.error('[Nozule PWA] SW registration failed:', err);
                        });
                });
            }
        })();
        </script>
        <?php
    }
}
