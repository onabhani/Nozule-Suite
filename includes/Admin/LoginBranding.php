<?php

namespace Nozule\Admin;

use Nozule\Core\Container;
use Nozule\Core\SettingsManager;

/**
 * Customizes the WordPress login page with hotel branding.
 *
 * Reads the default brand (logo, colors) from the Branding module
 * and applies them to wp-login.php via standard WordPress hooks.
 */
class LoginBranding {

    private Container $container;

    public function __construct( Container $container ) {
        $this->container = $container;
    }

    /**
     * Register all login-page hooks.
     */
    public function register(): void {
        add_action( 'login_enqueue_scripts', [ $this, 'enqueueLoginStyles' ] );
        add_filter( 'login_headerurl', [ $this, 'loginHeaderUrl' ] );
        add_filter( 'login_headertext', [ $this, 'loginHeaderText' ] );
    }

    /**
     * Inject custom CSS on the login page.
     */
    public function enqueueLoginStyles(): void {
        $brand = $this->getDefaultBrand();

        $logo_url      = $brand['logo_url'] ?? '';
        $primary_color = $brand['primary_color'] ?? '#1e40af';
        $hotel_name    = $brand['name'] ?? '';

        $css = '
            body.login {
                background: #f8fafc;
            }
            #login h1 a {
                width: 100%;
                max-width: 280px;
                height: 80px;
                background-size: contain;
                background-repeat: no-repeat;
                background-position: center;
        ';

        if ( $logo_url ) {
            $css .= "background-image: url('" . esc_url( $logo_url ) . "') !important;";
        }

        $css .= '}';

        // Style the login button and links.
        $css .= '
            .wp-core-ui .button-primary {
                background: ' . esc_attr( $primary_color ) . ' !important;
                border-color: ' . esc_attr( $primary_color ) . ' !important;
                box-shadow: 0 1px 0 ' . esc_attr( $primary_color ) . ' !important;
            }
            .wp-core-ui .button-primary:hover,
            .wp-core-ui .button-primary:focus {
                filter: brightness(0.9);
            }
            .login #backtoblog a,
            .login #nav a,
            .login .privacy-policy-page-link a {
                color: ' . esc_attr( $primary_color ) . ' !important;
            }
            .login form {
                border-radius: 8px;
                box-shadow: 0 1px 3px rgba(0,0,0,.08);
            }
        ';

        // If there's a hotel name but no logo, show it as text.
        if ( ! $logo_url && $hotel_name ) {
            $css .= '
                #login h1 a {
                    font-size: 24px !important;
                    color: ' . esc_attr( $primary_color ) . ';
                    text-indent: 0 !important;
                    overflow: visible !important;
                    background-image: none !important;
                    height: auto !important;
                    width: auto !important;
                }
            ';
        }

        echo '<style>' . $css . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * Change the login logo URL to point to the hotel site.
     */
    public function loginHeaderUrl(): string {
        return home_url( '/' );
    }

    /**
     * Change the login logo alt text.
     */
    public function loginHeaderText(): string {
        $brand = $this->getDefaultBrand();
        return $brand['name'] ?? get_bloginfo( 'name' );
    }

    /**
     * Retrieve the default brand data.
     *
     * @return array{name: string, logo_url: string, primary_color: string}
     */
    private function getDefaultBrand(): array {
        static $cache = null;
        if ( $cache !== null ) {
            return $cache;
        }

        $cache = [
            'name'          => get_bloginfo( 'name' ),
            'logo_url'      => '',
            'primary_color' => '#1e40af',
        ];

        // Try to load the default brand from the Branding module's repository.
        try {
            $brand_repo_class = \Nozule\Modules\Branding\Repositories\BrandRepository::class;
            if ( $this->container->has( $brand_repo_class ) ) {
                $repo  = $this->container->get( $brand_repo_class );
                $brand = $repo->getDefault();
                if ( $brand ) {
                    $data = $brand->toArray();
                    $cache['name']          = $data['name'] ?: $cache['name'];
                    $cache['logo_url']      = $data['logo_url'] ?? '';
                    $cache['primary_color'] = $data['primary_color'] ?? '#1e40af';
                }
            }
        } catch ( \Throwable $e ) {
            // Branding module may not be ready yet — use defaults.
        }

        return $cache;
    }
}
