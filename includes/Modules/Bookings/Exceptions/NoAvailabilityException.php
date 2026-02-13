<?php

namespace Nozule\Modules\Bookings\Exceptions;

/**
 * Thrown when a booking cannot be created due to insufficient room availability.
 */
class NoAvailabilityException extends \RuntimeException {

	/**
	 * @param string          $message  Human-readable explanation.
	 * @param int             $code     Optional error code.
	 * @param \Throwable|null $previous Optional previous exception.
	 */
	public function __construct(
		string $message = 'No rooms available for the requested dates.',
		int $code = 0,
		?\Throwable $previous = null
	) {
		parent::__construct( $message, $code, $previous );
	}
}
