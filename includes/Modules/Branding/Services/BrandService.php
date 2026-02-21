<?php

namespace Nozule\Modules\Branding\Services;

use Nozule\Core\EventDispatcher;
use Nozule\Core\Logger;
use Nozule\Modules\Branding\Models\Brand;
use Nozule\Modules\Branding\Repositories\BrandRepository;

/**
 * Service layer for brand / white-label business logic.
 */
class BrandService {

	private BrandRepository $repository;
	private EventDispatcher $events;
	private Logger $logger;

	public function __construct(
		BrandRepository $repository,
		EventDispatcher $events,
		Logger $logger
	) {
		$this->repository = $repository;
		$this->events     = $events;
		$this->logger     = $logger;
	}

	/**
	 * Get all brands.
	 *
	 * @return Brand[]
	 */
	public function getBrands(): array {
		return $this->repository->getBrands();
	}

	/**
	 * Get a single brand by ID.
	 */
	public function getBrand( int $id ): ?Brand {
		return $this->repository->getBrand( $id );
	}

	/**
	 * Get the active (default) brand, or null if none is configured.
	 */
	public function getActiveBrand(): ?Brand {
		return $this->repository->getActiveBrand();
	}

	/**
	 * Create a new brand.
	 *
	 * @param array $data Brand data.
	 * @return Brand|array Brand on success, validation errors array on failure.
	 */
	public function createBrand( array $data ) {
		$errors = $this->validateBrand( $data );
		if ( ! empty( $errors ) ) {
			return $errors;
		}

		$sanitized = $this->sanitizeBrandData( $data );

		$brand = $this->repository->saveBrand( $sanitized );

		if ( ! $brand ) {
			$this->logger->error( 'Failed to create brand', [ 'data' => $data ] );
			return [ 'general' => [ __( 'Failed to create brand.', 'nozule' ) ] ];
		}

		$this->events->dispatch( 'branding/brand_created', $brand );
		$this->logger->info( 'Brand created', [
			'id'   => $brand->id,
			'name' => $brand->name,
		] );

		return $brand;
	}

	/**
	 * Update an existing brand.
	 *
	 * @param int   $id   The brand ID.
	 * @param array $data The fields to update.
	 * @return Brand|array Updated Brand on success, errors on failure.
	 */
	public function updateBrand( int $id, array $data ) {
		$existing = $this->repository->getBrand( $id );
		if ( ! $existing ) {
			return [ 'id' => [ __( 'Brand not found.', 'nozule' ) ] ];
		}

		$errors = $this->validateBrand( $data, $id );
		if ( ! empty( $errors ) ) {
			return $errors;
		}

		$sanitized       = $this->sanitizeBrandData( $data );
		$sanitized['id'] = $id;

		$brand = $this->repository->saveBrand( $sanitized );

		if ( ! $brand ) {
			$this->logger->error( 'Failed to update brand', [ 'id' => $id ] );
			return [ 'general' => [ __( 'Failed to update brand.', 'nozule' ) ] ];
		}

		$this->events->dispatch( 'branding/brand_updated', $brand );
		$this->logger->info( 'Brand updated', [ 'id' => $id ] );

		return $brand;
	}

	/**
	 * Delete a brand.
	 *
	 * @param int $id The brand ID.
	 * @return true|array True on success, errors on failure.
	 */
	public function deleteBrand( int $id ) {
		$existing = $this->repository->getBrand( $id );
		if ( ! $existing ) {
			return [ 'id' => [ __( 'Brand not found.', 'nozule' ) ] ];
		}

		if ( $existing->isDefault() ) {
			return [ 'general' => [ __( 'Cannot delete the default brand. Set another brand as default first.', 'nozule' ) ] ];
		}

		$success = $this->repository->deleteBrand( $id );
		if ( ! $success ) {
			$this->logger->error( 'Failed to delete brand', [ 'id' => $id ] );
			return [ 'general' => [ __( 'Failed to delete brand.', 'nozule' ) ] ];
		}

		$this->events->dispatch( 'branding/brand_deleted', $existing );
		$this->logger->info( 'Brand deleted', [
			'id'   => $id,
			'name' => $existing->name,
		] );

		return true;
	}

	/**
	 * Set a brand as the default.
	 *
	 * @param int $id The brand ID.
	 * @return Brand|array Brand on success, errors on failure.
	 */
	public function setDefault( int $id ) {
		$existing = $this->repository->getBrand( $id );
		if ( ! $existing ) {
			return [ 'id' => [ __( 'Brand not found.', 'nozule' ) ] ];
		}

		$success = $this->repository->setDefault( $id );
		if ( ! $success ) {
			$this->logger->error( 'Failed to set default brand', [ 'id' => $id ] );
			return [ 'general' => [ __( 'Failed to set default brand.', 'nozule' ) ] ];
		}

		$brand = $this->repository->getBrand( $id );

		$this->events->dispatch( 'branding/brand_set_default', $brand );
		$this->logger->info( 'Brand set as default', [ 'id' => $id ] );

		return $brand;
	}

	/**
	 * Generate CSS custom properties string for a brand configuration.
	 *
	 * @param Brand|array $brand Brand model or associative array with color keys.
	 */
	public function generateCSSVariables( $brand ): string {
		if ( $brand instanceof Brand ) {
			return $brand->getCSSVariables();
		}

		$primary   = ! empty( $brand['primary_color'] ) ? $brand['primary_color'] : Brand::DEFAULT_PRIMARY_COLOR;
		$secondary = ! empty( $brand['secondary_color'] ) ? $brand['secondary_color'] : Brand::DEFAULT_SECONDARY_COLOR;
		$accent    = ! empty( $brand['accent_color'] ) ? $brand['accent_color'] : Brand::DEFAULT_ACCENT_COLOR;
		$text      = ! empty( $brand['text_color'] ) ? $brand['text_color'] : Brand::DEFAULT_TEXT_COLOR;

		$vars = [
			'--nzl-brand-primary: ' . $primary,
			'--nzl-brand-secondary: ' . $secondary,
			'--nzl-brand-accent: ' . $accent,
			'--nzl-brand-text: ' . $text,
		];

		return implode( '; ', $vars ) . ';';
	}

	/**
	 * Output CSS variables for the active brand to wp_head.
	 *
	 * Hooked into wp_head to inject brand theming on public pages.
	 */
	public function applyBrand(): void {
		$brand = $this->getActiveBrand();
		if ( ! $brand ) {
			return;
		}

		$css_vars = $this->generateCSSVariables( $brand );
		$output   = ':root { ' . $css_vars . ' }';

		// Append custom CSS if configured.
		if ( ! empty( $brand->custom_css ) ) {
			$output .= "\n" . $brand->custom_css;
		}

		echo '<style id="nzl-brand-css">' . "\n" . $output . "\n" . '</style>' . "\n";
	}

	/**
	 * Output brand accent color for admin pages.
	 *
	 * Hooked into admin_head to inject brand accent in admin if configured.
	 */
	public function applyAdminBrand(): void {
		$brand = $this->getActiveBrand();
		if ( ! $brand ) {
			return;
		}

		$css_vars = $this->generateCSSVariables( $brand );
		echo '<style id="nzl-admin-brand-css">' . "\n" . ':root { ' . $css_vars . ' }' . "\n" . '</style>' . "\n";
	}

	/**
	 * Get brand data needed for email templates.
	 *
	 * @return array|null Brand email data array, or null if no active brand.
	 */
	public function getBrandForEmails(): ?array {
		$brand = $this->getActiveBrand();
		if ( ! $brand ) {
			return null;
		}

		return $brand->toEmailArray();
	}

	/**
	 * Validate brand data.
	 *
	 * @param array    $data Brand data to validate.
	 * @param int|null $id   Brand ID when updating (null for create).
	 * @return array Associative array of errors (empty if valid).
	 */
	public function validateBrand( array $data, ?int $id = null ): array {
		$errors = [];

		// Name is required for new brands.
		if ( $id === null && empty( $data['name'] ) ) {
			$errors['name'] = [ __( 'Brand name is required.', 'nozule' ) ];
		}

		// Validate color fields if provided.
		$color_fields = [ 'primary_color', 'secondary_color', 'accent_color', 'text_color' ];
		foreach ( $color_fields as $field ) {
			if ( ! empty( $data[ $field ] ) && ! $this->isValidHexColor( $data[ $field ] ) ) {
				$errors[ $field ] = [
					/* translators: %s: field name */
					sprintf( __( 'Invalid hex color for %s.', 'nozule' ), $field ),
				];
			}
		}

		// Validate URL fields if provided.
		$url_fields = [ 'logo_url', 'favicon_url' ];
		foreach ( $url_fields as $field ) {
			if ( ! empty( $data[ $field ] ) && ! filter_var( $data[ $field ], FILTER_VALIDATE_URL ) ) {
				$errors[ $field ] = [
					/* translators: %s: field name */
					sprintf( __( 'Invalid URL for %s.', 'nozule' ), $field ),
				];
			}
		}

		return $errors;
	}

	/**
	 * Check if a string is a valid hex color code.
	 *
	 * @param string $color The color string to validate.
	 */
	private function isValidHexColor( string $color ): bool {
		return (bool) preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color );
	}

	/**
	 * Sanitize brand data before storage.
	 *
	 * @param array $data Raw input data.
	 * @return array Sanitized data.
	 */
	private function sanitizeBrandData( array $data ): array {
		$sanitized = [];

		// Text fields.
		$text_fields = [ 'name', 'name_ar' ];
		foreach ( $text_fields as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$sanitized[ $field ] = sanitize_text_field( $data[ $field ] );
			}
		}

		// URL fields.
		$url_fields = [ 'logo_url', 'favicon_url' ];
		foreach ( $url_fields as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$sanitized[ $field ] = esc_url_raw( $data[ $field ] );
			}
		}

		// Color fields.
		$color_fields = [ 'primary_color', 'secondary_color', 'accent_color', 'text_color' ];
		foreach ( $color_fields as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$sanitized[ $field ] = sanitize_hex_color( $data[ $field ] ) ?: sanitize_text_field( $data[ $field ] );
			}
		}

		// HTML fields (allow safe HTML for email templates).
		$html_fields = [ 'email_header_html', 'email_footer_html' ];
		foreach ( $html_fields as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$sanitized[ $field ] = wp_kses_post( $data[ $field ] );
			}
		}

		// Custom CSS (sanitize carefully — allow CSS, strip dangerous content).
		if ( array_key_exists( 'custom_css', $data ) ) {
			$sanitized['custom_css'] = wp_strip_all_tags( $data['custom_css'] );
		}

		// Boolean fields.
		if ( array_key_exists( 'is_active', $data ) ) {
			$sanitized['is_active'] = (int) (bool) $data['is_active'];
		}

		if ( array_key_exists( 'is_default', $data ) ) {
			$sanitized['is_default'] = (int) (bool) $data['is_default'];
		}

		return $sanitized;
	}
}
