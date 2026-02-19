<?php

namespace Nozule\Modules\Billing\Services;

use Nozule\Core\EventDispatcher;
use Nozule\Core\Logger;
use Nozule\Core\SettingsManager;
use Nozule\Modules\Billing\Models\Folio;
use Nozule\Modules\Billing\Models\FolioItem;
use Nozule\Modules\Billing\Repositories\FolioItemRepository;
use Nozule\Modules\Billing\Repositories\FolioRepository;
use Nozule\Modules\Billing\Validators\FolioValidator;

/**
 * Service layer orchestrating folio and folio item operations.
 */
class FolioService {

	private FolioRepository $folioRepository;
	private FolioItemRepository $folioItemRepository;
	private TaxService $taxService;
	private FolioValidator $folioValidator;
	private SettingsManager $settings;
	private EventDispatcher $events;
	private Logger $logger;

	public function __construct(
		FolioRepository $folioRepository,
		FolioItemRepository $folioItemRepository,
		TaxService $taxService,
		FolioValidator $folioValidator,
		SettingsManager $settings,
		EventDispatcher $events,
		Logger $logger
	) {
		$this->folioRepository     = $folioRepository;
		$this->folioItemRepository = $folioItemRepository;
		$this->taxService          = $taxService;
		$this->folioValidator      = $folioValidator;
		$this->settings            = $settings;
		$this->events              = $events;
		$this->logger              = $logger;
	}

	/**
	 * Create a folio for an individual booking.
	 */
	public function createFolioForBooking( int $bookingId, int $guestId ): Folio|array {
		$data = [
			'booking_id' => $bookingId,
			'guest_id'   => $guestId,
			'status'     => Folio::STATUS_OPEN,
			'currency'   => $this->settings->get( 'billing.currency', 'SYP' ),
			'subtotal'       => 0,
			'tax_total'      => 0,
			'discount_total' => 0,
			'grand_total'    => 0,
			'paid_amount'    => 0,
			'created_by'     => get_current_user_id() ?: null,
		];

		if ( ! $this->folioValidator->validateCreate( $data ) ) {
			return $this->folioValidator->getErrors();
		}

		$folio = $this->folioRepository->create( $data );
		if ( ! $folio ) {
			$this->logger->error( 'Failed to create folio for booking', [ 'booking_id' => $bookingId ] );
			return [ 'general' => [ __( 'Failed to create folio.', 'nozule' ) ] ];
		}

		$this->events->dispatch( 'billing/folio_created', $folio );
		$this->logger->info( 'Folio created for booking', [
			'folio_id'   => $folio->id,
			'booking_id' => $bookingId,
			'folio_number' => $folio->folio_number,
		] );

		return $folio;
	}

	/**
	 * Create a folio for a group booking.
	 */
	public function createFolioForGroup( int $groupBookingId, int $guestId ): Folio|array {
		$data = [
			'group_booking_id' => $groupBookingId,
			'guest_id'         => $guestId,
			'status'           => Folio::STATUS_OPEN,
			'currency'         => $this->settings->get( 'billing.currency', 'SYP' ),
			'subtotal'         => 0,
			'tax_total'        => 0,
			'discount_total'   => 0,
			'grand_total'      => 0,
			'paid_amount'      => 0,
			'created_by'       => get_current_user_id() ?: null,
		];

		if ( ! $this->folioValidator->validateCreate( $data ) ) {
			return $this->folioValidator->getErrors();
		}

		$folio = $this->folioRepository->create( $data );
		if ( ! $folio ) {
			$this->logger->error( 'Failed to create folio for group booking', [ 'group_booking_id' => $groupBookingId ] );
			return [ 'general' => [ __( 'Failed to create folio.', 'nozule' ) ] ];
		}

		$this->events->dispatch( 'billing/folio_created', $folio );
		$this->logger->info( 'Folio created for group booking', [
			'folio_id'         => $folio->id,
			'group_booking_id' => $groupBookingId,
			'folio_number'     => $folio->folio_number,
		] );

		return $folio;
	}

	/**
	 * Get a folio by ID.
	 */
	public function getFolio( int $id ): ?Folio {
		return $this->folioRepository->find( $id );
	}

	/**
	 * Get a folio with all its items.
	 *
	 * @return array{folio: Folio, items: FolioItem[]}|null
	 */
	public function getFolioWithItems( int $id ): ?array {
		$folio = $this->folioRepository->find( $id );
		if ( ! $folio ) {
			return null;
		}

		$items = $this->folioItemRepository->getByFolio( $id );

		return [
			'folio' => $folio,
			'items' => $items,
		];
	}

	/**
	 * Get a folio by booking ID.
	 */
	public function getFolioByBooking( int $bookingId ): ?Folio {
		return $this->folioRepository->findByBooking( $bookingId );
	}

	/**
	 * Get a folio by group booking ID.
	 */
	public function getFolioByGroupBooking( int $groupBookingId ): ?Folio {
		return $this->folioRepository->findByGroupBooking( $groupBookingId );
	}

	/**
	 * Add an item to a folio.
	 *
	 * Validates the item data, calculates taxes using TaxService, creates the
	 * item record, and recalculates folio totals.
	 *
	 * @return FolioItem|array FolioItem on success, errors on failure.
	 */
	public function addItem( int $folioId, array $data ): FolioItem|array {
		$data['folio_id'] = $folioId;

		if ( ! $this->folioValidator->validateAddItem( $data ) ) {
			return $this->folioValidator->getErrors();
		}

		// Ensure the folio exists and is open.
		$folio = $this->folioRepository->find( $folioId );
		if ( ! $folio ) {
			return [ 'folio_id' => [ __( 'Folio not found.', 'nozule' ) ] ];
		}

		if ( ! $folio->isOpen() ) {
			return [ 'folio_id' => [ __( 'Cannot add items to a closed or voided folio.', 'nozule' ) ] ];
		}

		$quantity  = (int) $data['quantity'];
		$unitPrice = (float) $data['unit_price'];
		$subtotal  = round( $quantity * $unitPrice, 2 );
		$category  = $data['category'];

		// Calculate taxes for charge categories.
		$taxJson  = [];
		$taxTotal = 0.0;

		if ( in_array( $category, [ FolioItem::CAT_ROOM_CHARGE, FolioItem::CAT_EXTRA, FolioItem::CAT_SERVICE ], true ) ) {
			$taxResult = $this->taxService->calculateTaxes( $subtotal, $category );
			$taxJson   = $taxResult['taxes'];
			$taxTotal  = $taxResult['total_tax'];
		}

		$total = round( $subtotal + $taxTotal, 2 );

		// For discounts and payments, store as positive amounts.
		if ( in_array( $category, [ FolioItem::CAT_DISCOUNT, FolioItem::CAT_PAYMENT ], true ) ) {
			$subtotal = abs( $subtotal );
			$total    = abs( $subtotal );
		}

		$itemData = [
			'folio_id'       => $folioId,
			'category'       => $category,
			'description'    => sanitize_text_field( $data['description'] ),
			'description_ar' => isset( $data['description_ar'] ) ? sanitize_text_field( $data['description_ar'] ) : null,
			'quantity'       => $quantity,
			'unit_price'     => $unitPrice,
			'subtotal'       => $subtotal,
			'tax_json'       => $taxJson,
			'tax_total'      => $taxTotal,
			'total'          => $total,
			'date'           => $data['date'] ?? current_time( 'Y-m-d' ),
			'posted_by'      => get_current_user_id() ?: null,
		];

		$item = $this->folioItemRepository->create( $itemData );
		if ( ! $item ) {
			$this->logger->error( 'Failed to add folio item', [ 'folio_id' => $folioId, 'data' => $data ] );
			return [ 'general' => [ __( 'Failed to add item to folio.', 'nozule' ) ] ];
		}

		// Recalculate folio totals.
		$this->recalculateFolioTotals( $folioId );

		$this->events->dispatch( 'billing/item_added', $item, $folio );
		$this->logger->info( 'Folio item added', [
			'folio_id' => $folioId,
			'item_id'  => $item->id,
			'category' => $category,
			'total'    => $total,
		] );

		return $item;
	}

	/**
	 * Remove an item from a folio.
	 */
	public function removeItem( int $itemId ): true|array {
		$item = $this->folioItemRepository->find( $itemId );
		if ( ! $item ) {
			return [ 'id' => [ __( 'Folio item not found.', 'nozule' ) ] ];
		}

		$folio = $this->folioRepository->find( $item->folio_id );
		if ( $folio && ! $folio->isOpen() ) {
			return [ 'folio_id' => [ __( 'Cannot remove items from a closed or voided folio.', 'nozule' ) ] ];
		}

		$success = $this->folioItemRepository->delete( $itemId );
		if ( ! $success ) {
			return [ 'general' => [ __( 'Failed to remove folio item.', 'nozule' ) ] ];
		}

		// Recalculate folio totals.
		$this->recalculateFolioTotals( $item->folio_id );

		$this->events->dispatch( 'billing/item_removed', $item, $folio );
		$this->logger->info( 'Folio item removed', [
			'folio_id' => $item->folio_id,
			'item_id'  => $itemId,
		] );

		return true;
	}

	/**
	 * Post room charges to a folio.
	 *
	 * Adds a consolidated room charge line item for the specified number of
	 * nights at the given rate, with tax calculation.
	 */
	public function postRoomCharges( int $folioId, int $nights, float $ratePerNight, string $roomTypeName ): FolioItem|array {
		$description = sprintf(
			/* translators: 1: room type name, 2: number of nights */
			__( '%1$s - %2$d night(s)', 'nozule' ),
			$roomTypeName,
			$nights
		);

		return $this->addItem( $folioId, [
			'category'    => FolioItem::CAT_ROOM_CHARGE,
			'description' => $description,
			'quantity'    => $nights,
			'unit_price'  => $ratePerNight,
			'date'        => current_time( 'Y-m-d' ),
		] );
	}

	/**
	 * Close a folio.
	 */
	public function closeFolio( int $folioId ): Folio|array {
		$folio = $this->folioRepository->find( $folioId );
		if ( ! $folio ) {
			return [ 'id' => [ __( 'Folio not found.', 'nozule' ) ] ];
		}

		if ( ! $folio->isOpen() ) {
			return [ 'status' => [ __( 'Folio is already closed or voided.', 'nozule' ) ] ];
		}

		$success = $this->folioRepository->update( $folioId, [
			'status'    => Folio::STATUS_CLOSED,
			'closed_at' => current_time( 'mysql', true ),
			'closed_by' => get_current_user_id() ?: null,
		] );

		if ( ! $success ) {
			$this->logger->error( 'Failed to close folio', [ 'folio_id' => $folioId ] );
			return [ 'general' => [ __( 'Failed to close folio.', 'nozule' ) ] ];
		}

		$updated = $this->folioRepository->find( $folioId );

		$this->events->dispatch( 'billing/folio_closed', $updated );
		$this->logger->info( 'Folio closed', [ 'folio_id' => $folioId ] );

		return $updated;
	}

	/**
	 * Void a folio.
	 */
	public function voidFolio( int $folioId ): Folio|array {
		$folio = $this->folioRepository->find( $folioId );
		if ( ! $folio ) {
			return [ 'id' => [ __( 'Folio not found.', 'nozule' ) ] ];
		}

		if ( $folio->isVoid() ) {
			return [ 'status' => [ __( 'Folio is already voided.', 'nozule' ) ] ];
		}

		$success = $this->folioRepository->update( $folioId, [
			'status' => Folio::STATUS_VOID,
		] );

		if ( ! $success ) {
			$this->logger->error( 'Failed to void folio', [ 'folio_id' => $folioId ] );
			return [ 'general' => [ __( 'Failed to void folio.', 'nozule' ) ] ];
		}

		$updated = $this->folioRepository->find( $folioId );

		$this->events->dispatch( 'billing/folio_voided', $updated );
		$this->logger->info( 'Folio voided', [ 'folio_id' => $folioId ] );

		return $updated;
	}

	/**
	 * Recalculate folio totals.
	 *
	 * Delegates to the repository which queries all items and updates the folio.
	 */
	public function recalculateFolioTotals( int $folioId ): bool {
		return $this->folioRepository->recalculateTotals( $folioId );
	}

	/**
	 * Get all folios with optional filtering.
	 *
	 * @return Folio[]
	 */
	public function getFolios( ?string $status = null, ?int $bookingId = null, ?int $guestId = null ): array {
		return $this->folioRepository->getAllFiltered( $status, $bookingId, $guestId );
	}
}
