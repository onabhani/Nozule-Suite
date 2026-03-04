<?php

namespace Nozule\Modules\Channels\Models;

use Nozule\Core\BaseModel;
use Nozule\Core\CredentialVault;

/**
 * Channel Connection model.
 *
 * Represents a configured OTA connection with encrypted credentials
 * and activation status.
 *
 * @property int    $id
 * @property string $channel_name   OTA identifier (e.g. 'booking_com').
 * @property string $hotel_id       Hotel/property ID on the OTA side.
 * @property string $credentials    Encrypted JSON of API credentials.
 * @property int    $is_active      Whether the connection is active (0/1).
 * @property string $last_sync_at   Timestamp of last successful sync.
 * @property string $created_at
 * @property string $updated_at
 */
class ChannelConnection extends BaseModel {

	/**
	 * @var string[]
	 */
	protected static array $intFields = [
		'id',
		'is_active',
	];

	/**
	 * Create from a database row with type casting.
	 */
	public static function fromRow( object $row ): static {
		$data = (array) $row;

		foreach ( static::$intFields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$data[ $field ] = (int) $data[ $field ];
			}
		}

		return new static( $data );
	}

	/**
	 * Check whether this connection is active.
	 */
	public function isActive(): bool {
		return (bool) $this->is_active;
	}

	/**
	 * Decrypt and return the stored credentials.
	 *
	 * Delegates to CredentialVault for the actual decryption.
	 *
	 * @return array Associative array of credential key-value pairs.
	 */
	public function getDecryptedCredentials(): array {
		if ( empty( $this->credentials ) ) {
			return [];
		}

		return CredentialVault::decrypt( $this->credentials );
	}

	/**
	 * Encrypt credentials for storage.
	 *
	 * Delegates to CredentialVault for the actual encryption.
	 *
	 * @param array $credentials Associative array of credentials.
	 * @return string Encrypted, base64-encoded string.
	 */
	public static function encryptCredentials( array $credentials ): string {
		return CredentialVault::encrypt( $credentials );
	}

	/**
	 * Get a human-readable label for the channel.
	 */
	public function getChannelLabel(): string {
		$labels = [
			'booking_com' => 'Booking.com',
			'expedia'     => 'Expedia',
			'airbnb'      => 'Airbnb',
			'agoda'       => 'Agoda',
		];

		return $labels[ $this->channel_name ] ?? ucfirst( str_replace( '_', ' ', $this->channel_name ) );
	}
}
