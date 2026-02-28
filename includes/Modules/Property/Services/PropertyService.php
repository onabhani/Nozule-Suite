<?php

namespace Nozule\Modules\Property\Services;

use Nozule\Core\CacheManager;
use Nozule\Core\EventDispatcher;
use Nozule\Core\Logger;
use Nozule\Core\SettingsManager;
use Nozule\Modules\Property\Models\Property;
use Nozule\Modules\Property\Repositories\PropertyRepository;
use Nozule\Modules\Property\Validators\PropertyValidator;

/**
 * Service layer for property business logic.
 */
class PropertyService {

	private PropertyRepository $repository;
	private PropertyValidator $validator;
	private CacheManager $cache;
	private EventDispatcher $events;
	private Logger $logger;
	private SettingsManager $settings;

	public function __construct(
		PropertyRepository $repository,
		PropertyValidator $validator,
		CacheManager $cache,
		EventDispatcher $events,
		Logger $logger,
		SettingsManager $settings
	) {
		$this->repository = $repository;
		$this->validator  = $validator;
		$this->cache      = $cache;
		$this->events     = $events;
		$this->logger     = $logger;
		$this->settings   = $settings;
	}

	/**
	 * Check whether multi-property mode (NZL-019) is enabled.
	 */
	public function isMultiPropertyEnabled(): bool {
		$flag = $this->settings->get( 'features.multi_property', '0' );
		return $flag === '1' || $flag === true;
	}

	/**
	 * Get the current property.
	 *
	 * In multi-property mode accepts an optional property_id to resolve
	 * a specific property; otherwise returns the first active property.
	 */
	public function getCurrent( ?string $propertyId = null ): ?Property {
		$cacheKey = $propertyId ? 'property_' . $propertyId : 'property_current';
		$cached   = $this->cache->get( $cacheKey );
		if ( $cached !== false ) {
			return $cached === '__no_property__' ? null : $cached;
		}

		$property = $this->repository->getCurrent( $propertyId );

		// Cache a sentinel when no property exists so we don't hit the DB on
		// every subsequent call for the same 300-second window.
		$this->cache->set( $cacheKey, $property ?? '__no_property__', 300 );

		return $property;
	}

	/**
	 * Get all properties.
	 *
	 * @return Property[]
	 */
	public function getAll(): array {
		return $this->repository->getAll();
	}

	/**
	 * Find a property by ID.
	 */
	public function find( int $id ): ?Property {
		return $this->repository->find( $id );
	}

	/**
	 * Find a property by its public property_id (UUID).
	 */
	public function findByPropertyId( string $propertyId ): ?Property {
		return $this->repository->findByPropertyId( $propertyId );
	}

	/**
	 * Create a new property.
	 *
	 * @return Property|array Property on success, errors on failure.
	 */
	public function createProperty( array $data ): Property|array {
		// Auto-generate slug from name if not provided.
		if ( empty( $data['slug'] ) && ! empty( $data['name'] ) ) {
			$data['slug'] = sanitize_title( $data['name'] );
		}

		if ( ! $this->validator->validateCreate( $data ) ) {
			return $this->validator->getErrors();
		}

		$sanitized = $this->sanitize( $data );

		$property = $this->repository->create( $sanitized );
		if ( ! $property ) {
			$this->logger->error( 'Failed to create property', [ 'data' => $data ] );
			return [ 'general' => [ __( 'Failed to create property.', 'nozule' ) ] ];
		}

		$this->invalidateCache();
		$this->events->dispatch( 'property/property_created', $property );
		$this->logger->info( 'Property created', [
			'id'          => $property->id,
			'property_id' => $property->property_id,
			'name'        => $property->name,
		] );

		return $property;
	}

	/**
	 * Update an existing property.
	 *
	 * @return Property|array Updated Property on success, errors on failure.
	 */
	public function updateProperty( int $id, array $data ): Property|array {
		$existing = $this->repository->find( $id );
		if ( ! $existing ) {
			return [ 'id' => [ __( 'Property not found.', 'nozule' ) ] ];
		}

		if ( ! $this->validator->validateUpdate( $id, $data ) ) {
			return $this->validator->getErrors();
		}

		$sanitized = $this->sanitize( $data );

		$success = $this->repository->update( $id, $sanitized );
		if ( ! $success ) {
			$this->logger->error( 'Failed to update property', [ 'id' => $id ] );
			return [ 'general' => [ __( 'Failed to update property.', 'nozule' ) ] ];
		}

		$updated = $this->repository->find( $id );

		$this->invalidateCache();
		$this->events->dispatch( 'property/property_updated', $updated, $existing );
		$this->logger->info( 'Property updated', [ 'id' => $id ] );

		return $updated;
	}

	/**
	 * Delete a property.
	 *
	 * @return true|array True on success, errors on failure.
	 */
	public function deleteProperty( int $id ): true|array {
		$existing = $this->repository->find( $id );
		if ( ! $existing ) {
			return [ 'id' => [ __( 'Property not found.', 'nozule' ) ] ];
		}

		$success = $this->repository->delete( $id );
		if ( ! $success ) {
			$this->logger->error( 'Failed to delete property', [ 'id' => $id ] );
			return [ 'general' => [ __( 'Failed to delete property.', 'nozule' ) ] ];
		}

		$this->invalidateCache();
		$this->events->dispatch( 'property/property_deleted', $existing );
		$this->logger->info( 'Property deleted', [
			'id'   => $id,
			'name' => $existing->name,
		] );

		return true;
	}

	/**
	 * Sanitize property data before storage.
	 */
	private function sanitize( array $data ): array {
		$sanitized = [];

		// Text fields.
		$text_fields = [
			'name', 'name_ar', 'slug', 'property_type',
			'address_line_1', 'address_line_2', 'city', 'state_province',
			'country', 'postal_code', 'phone', 'phone_alt', 'email',
			'timezone', 'tax_id', 'license_number', 'currency', 'status',
			'check_in_time', 'check_out_time',
		];
		foreach ( $text_fields as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$sanitized[ $field ] = sanitize_text_field( $data[ $field ] );
			}
		}

		// Textarea fields (descriptions).
		$textarea_fields = [ 'description', 'description_ar' ];
		foreach ( $textarea_fields as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$sanitized[ $field ] = sanitize_textarea_field( $data[ $field ] );
			}
		}

		// URL fields.
		$url_fields = [ 'logo_url', 'cover_image_url', 'website' ];
		foreach ( $url_fields as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$sanitized[ $field ] = esc_url_raw( $data[ $field ] );
			}
		}

		// Numeric fields.
		if ( array_key_exists( 'star_rating', $data ) ) {
			$sanitized['star_rating'] = $data['star_rating'] !== null && $data['star_rating'] !== '' ? absint( $data['star_rating'] ) : null;
		}
		if ( array_key_exists( 'total_rooms', $data ) ) {
			$sanitized['total_rooms'] = $data['total_rooms'] !== null && $data['total_rooms'] !== '' ? absint( $data['total_rooms'] ) : null;
		}
		if ( array_key_exists( 'total_floors', $data ) ) {
			$sanitized['total_floors'] = $data['total_floors'] !== null && $data['total_floors'] !== '' ? absint( $data['total_floors'] ) : null;
		}
		if ( array_key_exists( 'year_built', $data ) ) {
			$sanitized['year_built'] = $data['year_built'] !== null && $data['year_built'] !== '' ? absint( $data['year_built'] ) : null;
		}
		if ( array_key_exists( 'year_renovated', $data ) ) {
			$sanitized['year_renovated'] = $data['year_renovated'] !== null && $data['year_renovated'] !== '' ? absint( $data['year_renovated'] ) : null;
		}

		// Float fields (coordinates).
		if ( array_key_exists( 'latitude', $data ) ) {
			$sanitized['latitude'] = $data['latitude'] !== null && $data['latitude'] !== '' ? (float) $data['latitude'] : null;
		}
		if ( array_key_exists( 'longitude', $data ) ) {
			$sanitized['longitude'] = $data['longitude'] !== null && $data['longitude'] !== '' ? (float) $data['longitude'] : null;
		}

		// JSON fields — pass through as arrays; repository handles encoding.
		$json_fields = [ 'photos', 'facilities', 'policies', 'social_links' ];
		foreach ( $json_fields as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$sanitized[ $field ] = is_array( $data[ $field ] ) ? $data[ $field ] : [];
			}
		}

		return $sanitized;
	}

	/**
	 * Invalidate property caches.
	 */
	private function invalidateCache(): void {
		$this->cache->delete( 'property_current' );
	}
}
