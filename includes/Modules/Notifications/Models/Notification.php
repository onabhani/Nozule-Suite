<?php

namespace Nozule\Modules\Notifications\Models;

use Nozule\Core\BaseModel;

/**
 * Notification model representing a queued or sent notification.
 *
 * @property int         $id
 * @property int|null    $booking_id
 * @property int|null    $guest_id
 * @property string      $type
 * @property string      $channel
 * @property string      $recipient
 * @property string|null $subject
 * @property string      $content
 * @property string|null $content_html
 * @property string|null $template_id
 * @property string|null $template_vars
 * @property string      $status
 * @property int         $attempts
 * @property string|null $sent_at
 * @property string|null $delivered_at
 * @property string|null $error_message
 * @property string|null $external_id
 * @property string      $created_at
 */
class Notification extends BaseModel {

	/**
	 * Allowed notification types.
	 *
	 * @var string[]
	 */
	public const TYPES = [
		'booking_confirmation',
		'booking_confirmed',
		'booking_cancelled',
		'booking_reminder',
		'check_in_reminder',
		'check_out_reminder',
		'payment_receipt',
		'review_request',
		'custom',
	];

	/**
	 * Allowed notification channels.
	 *
	 * @var string[]
	 */
	public const CHANNELS = [
		'email',
		'sms',
		'whatsapp',
		'push',
	];

	/**
	 * Possible notification statuses.
	 *
	 * @var string[]
	 */
	public const STATUSES = [
		'queued',
		'sending',
		'sent',
		'delivered',
		'failed',
		'cancelled',
	];

	/**
	 * Maximum send attempts before marking as failed.
	 */
	public const MAX_ATTEMPTS = 3;

	/**
	 * Attributes that should be cast to specific types.
	 *
	 * @var array<string, string>
	 */
	protected static array $casts = [
		'id'         => 'int',
		'booking_id' => 'int',
		'guest_id'   => 'int',
		'attempts'   => 'int',
	];

	/**
	 * Fill attributes, applying type casts.
	 */
	public function fill( array $attributes ): static {
		foreach ( $attributes as $key => $value ) {
			if ( $value !== null && isset( static::$casts[ $key ] ) ) {
				$value = match ( static::$casts[ $key ] ) {
					'int'   => (int) $value,
					'float' => (float) $value,
					default => $value,
				};
			}
			$this->attributes[ $key ] = $value;
		}
		return $this;
	}

	/**
	 * Check whether the notification is still pending in the queue.
	 */
	public function isPending(): bool {
		return $this->status === 'queued';
	}

	/**
	 * Check whether the notification has been sent or delivered.
	 */
	public function isSent(): bool {
		return in_array( $this->status, [ 'sent', 'delivered' ], true );
	}

	/**
	 * Check whether the notification has failed.
	 */
	public function isFailed(): bool {
		return $this->status === 'failed';
	}

	/**
	 * Check whether the notification is currently being sent.
	 */
	public function isSending(): bool {
		return $this->status === 'sending';
	}

	/**
	 * Check whether the notification has been cancelled.
	 */
	public function isCancelled(): bool {
		return $this->status === 'cancelled';
	}

	/**
	 * Check whether the notification can still be retried.
	 */
	public function canRetry(): bool {
		return $this->attempts < self::MAX_ATTEMPTS
			&& ! $this->isSent()
			&& ! $this->isCancelled();
	}

	/**
	 * Get template variables decoded from JSON.
	 *
	 * @return array<string, mixed>
	 */
	public function getTemplateVars(): array {
		if ( empty( $this->template_vars ) ) {
			return [];
		}

		$decoded = json_decode( $this->template_vars, true );

		return is_array( $decoded ) ? $decoded : [];
	}

	/**
	 * Check if this notification uses a specific channel.
	 */
	public function isChannel( string $channel ): bool {
		return $this->channel === $channel;
	}

	/**
	 * Check if this notification is of a specific type.
	 */
	public function isType( string $type ): bool {
		return $this->type === $type;
	}

	/**
	 * Convert to array, including decoded template_vars.
	 */
	public function toArray(): array {
		$data                  = parent::toArray();
		$data['template_vars'] = $this->getTemplateVars();

		return $data;
	}

	/**
	 * Get fields suitable for database insertion/update.
	 *
	 * Encodes template_vars back to JSON for storage.
	 */
	public function toDatabaseArray(): array {
		$data = parent::toArray();

		if ( isset( $data['template_vars'] ) && is_array( $data['template_vars'] ) ) {
			$data['template_vars'] = wp_json_encode( $data['template_vars'] );
		}

		return $data;
	}
}
