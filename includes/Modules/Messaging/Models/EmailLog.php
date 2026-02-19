<?php

namespace Nozule\Modules\Messaging\Models;

use Nozule\Core\BaseModel;

/**
 * Email Log model.
 *
 * Records every email dispatched by the system for auditing and debugging.
 *
 * @property int         $id
 * @property int|null    $template_id    FK to email_templates (null for raw sends).
 * @property int|null    $booking_id     Associated booking, if applicable.
 * @property int|null    $guest_id       Associated guest, if applicable.
 * @property string      $to_email       Recipient address.
 * @property string      $subject
 * @property string      $body           Rendered HTML body.
 * @property string      $status         queued|sent|failed
 * @property string|null $error_message  Error details when status = failed.
 * @property string|null $sent_at        Timestamp of successful delivery.
 * @property string      $created_at
 */
class EmailLog extends BaseModel {

	// ── Status Constants ────────────────────────────────────────────

	public const STATUS_QUEUED = 'queued';
	public const STATUS_SENT   = 'sent';
	public const STATUS_FAILED = 'failed';

	/** @var array<string, string> */
	protected static array $casts = [
		'id'          => 'int',
		'template_id' => 'int',
		'booking_id'  => 'int',
		'guest_id'    => 'int',
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

	// ── Status Helpers ──────────────────────────────────────────────

	/**
	 * Whether the email was successfully sent.
	 */
	public function isSent(): bool {
		return $this->status === self::STATUS_SENT;
	}

	/**
	 * Whether the email send failed.
	 */
	public function isFailed(): bool {
		return $this->status === self::STATUS_FAILED;
	}

	/**
	 * Whether the email is still queued.
	 */
	public function isQueued(): bool {
		return $this->status === self::STATUS_QUEUED;
	}

	/**
	 * All valid status values.
	 *
	 * @return string[]
	 */
	public static function validStatuses(): array {
		return [
			self::STATUS_QUEUED,
			self::STATUS_SENT,
			self::STATUS_FAILED,
		];
	}
}
