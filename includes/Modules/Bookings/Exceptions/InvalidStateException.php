<?php

namespace Nozule\Modules\Bookings\Exceptions;

/**
 * Thrown when a booking state transition is not allowed.
 *
 * For example, attempting to check in a booking that has not been confirmed,
 * or cancelling a booking that is already checked out.
 */
class InvalidStateException extends \LogicException {

	/**
	 * @param string          $message  Human-readable explanation.
	 * @param int             $code     Optional error code.
	 * @param \Throwable|null $previous Optional previous exception.
	 */
	public function __construct(
		string $message = 'The booking is not in a valid state for this operation.',
		int $code = 0,
		?\Throwable $previous = null
	) {
		parent::__construct( $message, $code, $previous );
	}
}
