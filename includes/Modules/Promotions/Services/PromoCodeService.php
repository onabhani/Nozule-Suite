<?php

namespace Nozule\Modules\Promotions\Services;

use Nozule\Core\EventDispatcher;
use Nozule\Core\Logger;
use Nozule\Modules\Promotions\Models\PromoCode;
use Nozule\Modules\Promotions\Repositories\PromoCodeRepository;
use Nozule\Modules\Promotions\Validators\PromoCodeValidator;

/**
 * Service layer for promo code business logic.
 */
class PromoCodeService {

	private PromoCodeRepository $repository;
	private PromoCodeValidator $validator;
	private EventDispatcher $events;
	private Logger $logger;

	public function __construct(
		PromoCodeRepository $repository,
		PromoCodeValidator $validator,
		EventDispatcher $events,
		Logger $logger
	) {
		$this->repository = $repository;
		$this->validator  = $validator;
		$this->events     = $events;
		$this->logger     = $logger;
	}

	/**
	 * Get paginated list of promo codes with optional filters.
	 *
	 * @param array $filters Filter arguments (status, search, date_from, date_to, page, per_page, orderby, order).
	 * @return array{ items: array, total: int, pages: int }
	 */
	public function getPromoCodes( array $filters = [] ): array {
		return $this->repository->list( $filters );
	}

	/**
	 * Get a single promo code by ID.
	 *
	 * @param int $id The promo code ID.
	 */
	public function getPromoCode( int $id ): ?PromoCode {
		return $this->repository->find( $id );
	}

	/**
	 * Create a new promo code.
	 *
	 * @param array $data The promo code data.
	 * @return PromoCode|array PromoCode on success, validation errors array on failure.
	 */
	public function createPromoCode( array $data ): PromoCode|array {
		if ( ! $this->validator->validateCreate( $data ) ) {
			return $this->validator->getErrors();
		}

		$sanitized = $this->sanitizePromoData( $data );

		$promo = $this->repository->create( $sanitized );

		if ( ! $promo ) {
			$this->logger->error( 'Failed to create promo code', [ 'data' => $data ] );
			return [ 'general' => [ __( 'Failed to create promo code.', 'nozule' ) ] ];
		}

		$this->events->dispatch( 'promotions/promo_code_created', $promo );
		$this->logger->info( 'Promo code created', [
			'id'   => $promo->id,
			'code' => $promo->code,
		] );

		return $promo;
	}

	/**
	 * Update an existing promo code.
	 *
	 * @param int   $id   The promo code ID.
	 * @param array $data The fields to update.
	 * @return PromoCode|array Updated PromoCode on success, errors on failure.
	 */
	public function updatePromoCode( int $id, array $data ): PromoCode|array {
		$existing = $this->repository->find( $id );
		if ( ! $existing ) {
			return [ 'id' => [ __( 'Promo code not found.', 'nozule' ) ] ];
		}

		if ( ! $this->validator->validateUpdate( $id, $data ) ) {
			return $this->validator->getErrors();
		}

		$sanitized = $this->sanitizePromoData( $data );

		$success = $this->repository->update( $id, $sanitized );
		if ( ! $success ) {
			$this->logger->error( 'Failed to update promo code', [ 'id' => $id ] );
			return [ 'general' => [ __( 'Failed to update promo code.', 'nozule' ) ] ];
		}

		$updated = $this->repository->find( $id );

		$this->events->dispatch( 'promotions/promo_code_updated', $updated );
		$this->logger->info( 'Promo code updated', [ 'id' => $id ] );

		return $updated;
	}

	/**
	 * Delete a promo code.
	 *
	 * @param int $id The promo code ID.
	 * @return bool|array True on success, errors on failure.
	 */
	public function deletePromoCode( int $id ): bool|array {
		$existing = $this->repository->find( $id );
		if ( ! $existing ) {
			return [ 'id' => [ __( 'Promo code not found.', 'nozule' ) ] ];
		}

		$success = $this->repository->delete( $id );
		if ( ! $success ) {
			$this->logger->error( 'Failed to delete promo code', [ 'id' => $id ] );
			return [ 'general' => [ __( 'Failed to delete promo code.', 'nozule' ) ] ];
		}

		$this->events->dispatch( 'promotions/promo_code_deleted', $existing );
		$this->logger->info( 'Promo code deleted', [
			'id'   => $id,
			'code' => $existing->code,
		] );

		return true;
	}

	/**
	 * Validate a promo code string for use in a booking.
	 *
	 * @param string   $code     The promo code string.
	 * @param float    $subtotal The booking subtotal.
	 * @param int      $nights   The number of nights in the booking.
	 * @param int|null $guestId  The guest ID, if available.
	 * @return PromoCode|array PromoCode on success, errors on failure.
	 */
	public function validateCode( string $code, float $subtotal, int $nights, ?int $guestId = null ): PromoCode|array {
		$promo = $this->repository->findByCode( $code );

		if ( ! $promo ) {
			return [ 'code' => [ __( 'Promo code not found.', 'nozule' ) ] ];
		}

		if ( ! $this->validator->validateApplication( $promo, $subtotal, $nights, $guestId ) ) {
			return $this->validator->getErrors();
		}

		return $promo;
	}

	/**
	 * Calculate the discount amount for a given promo code and subtotal.
	 *
	 * Handles both percentage and fixed discount types, and caps
	 * the discount at the max_discount value if set.
	 *
	 * @param PromoCode $promo    The promo code to apply.
	 * @param float     $subtotal The booking subtotal.
	 * @return float The calculated discount amount.
	 */
	public function applyDiscount( PromoCode $promo, float $subtotal ): float {
		$discount = 0.0;

		if ( $promo->discount_type === PromoCode::TYPE_PERCENTAGE ) {
			$discount = round( $subtotal * ( $promo->discount_value / 100 ), 2 );
		} elseif ( $promo->discount_type === PromoCode::TYPE_FIXED ) {
			$discount = round( (float) $promo->discount_value, 2 );
		}

		// Cap discount at the subtotal (cannot discount more than the total).
		if ( $discount > $subtotal ) {
			$discount = $subtotal;
		}

		// Cap discount at max_discount if set.
		if ( ! empty( $promo->max_discount ) && $promo->max_discount > 0 && $discount > $promo->max_discount ) {
			$discount = round( (float) $promo->max_discount, 2 );
		}

		return $discount;
	}

	/**
	 * Record a promo code usage by incrementing the used_count.
	 *
	 * @param int $promoId The promo code ID.
	 */
	public function recordUsage( int $promoId ): bool {
		$result = $this->repository->incrementUsedCount( $promoId );

		if ( $result ) {
			$promo = $this->repository->find( $promoId );
			$this->events->dispatch( 'promotions/promo_code_used', $promo );
			$this->logger->info( 'Promo code usage recorded', [
				'id'         => $promoId,
				'used_count' => $promo ? $promo->used_count : null,
			] );
		}

		return $result;
	}

	/**
	 * Sanitize promo code data before storage.
	 *
	 * @param array $data Raw input data.
	 * @return array Sanitized data.
	 */
	private function sanitizePromoData( array $data ): array {
		$sanitized = [];

		// Text fields.
		$text_fields = [
			'code', 'name', 'name_ar', 'currency_code',
		];
		foreach ( $text_fields as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$sanitized[ $field ] = sanitize_text_field( $data[ $field ] );
			}
		}

		// Code should be uppercase for consistency.
		if ( isset( $sanitized['code'] ) ) {
			$sanitized['code'] = strtoupper( $sanitized['code'] );
		}

		// Textarea fields.
		$textarea_fields = [ 'description', 'description_ar' ];
		foreach ( $textarea_fields as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$sanitized[ $field ] = sanitize_textarea_field( $data[ $field ] );
			}
		}

		// Enum field.
		if ( array_key_exists( 'discount_type', $data ) ) {
			$sanitized['discount_type'] = sanitize_text_field( $data['discount_type'] );
		}

		// Numeric fields.
		$float_fields = [ 'discount_value', 'min_amount', 'max_discount' ];
		foreach ( $float_fields as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$sanitized[ $field ] = (float) $data[ $field ];
			}
		}

		$int_fields = [ 'min_nights', 'max_uses', 'per_guest_limit', 'created_by' ];
		foreach ( $int_fields as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$sanitized[ $field ] = absint( $data[ $field ] );
			}
		}

		// Boolean field.
		if ( array_key_exists( 'is_active', $data ) ) {
			$sanitized['is_active'] = (int) (bool) $data['is_active'];
		}

		// Date fields.
		$date_fields = [ 'valid_from', 'valid_to' ];
		foreach ( $date_fields as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$sanitized[ $field ] = $data[ $field ] ? sanitize_text_field( $data[ $field ] ) : null;
			}
		}

		// JSON array fields.
		if ( array_key_exists( 'applicable_room_types', $data ) ) {
			$value = $data['applicable_room_types'];
			if ( is_string( $value ) ) {
				$value = json_decode( $value, true );
			}
			$sanitized['applicable_room_types'] = is_array( $value ) ? array_map( 'absint', $value ) : [];
		}

		if ( array_key_exists( 'applicable_sources', $data ) ) {
			$value = $data['applicable_sources'];
			if ( is_string( $value ) ) {
				$value = json_decode( $value, true );
			}
			$sanitized['applicable_sources'] = is_array( $value )
				? array_map( 'sanitize_text_field', $value )
				: [];
		}

		return $sanitized;
	}
}
