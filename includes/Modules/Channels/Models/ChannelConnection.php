<?php

namespace Nozule\Modules\Channels\Models;

use Nozule\Core\BaseModel;

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
	 * Credentials are stored as an AES-256-CBC encrypted JSON string
	 * using the WordPress AUTH_KEY as the encryption key.
	 *
	 * @return array Associative array of credential key-value pairs.
	 */
	public function getDecryptedCredentials(): array {
		$raw = $this->credentials;

		if ( empty( $raw ) ) {
			return [];
		}

		$key = self::getEncryptionKey();

		// Try to decrypt.
		$decoded = base64_decode( $raw, true );
		if ( $decoded === false ) {
			// Not base64 — might be plain JSON (legacy).
			$plain = json_decode( $raw, true );
			return is_array( $plain ) ? $plain : [];
		}

		$iv_length = openssl_cipher_iv_length( 'aes-256-cbc' );
		if ( strlen( $decoded ) < $iv_length ) {
			return [];
		}

		$iv        = substr( $decoded, 0, $iv_length );
		$encrypted = substr( $decoded, $iv_length );

		$decrypted = openssl_decrypt( $encrypted, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );

		if ( $decrypted === false ) {
			return [];
		}

		$result = json_decode( $decrypted, true );

		return is_array( $result ) ? $result : [];
	}

	/**
	 * Encrypt credentials for storage.
	 *
	 * @param array $credentials Associative array of credentials.
	 * @return string Encrypted, base64-encoded string.
	 */
	public static function encryptCredentials( array $credentials ): string {
		$key       = self::getEncryptionKey();
		$json      = wp_json_encode( $credentials );
		$iv_length = openssl_cipher_iv_length( 'aes-256-cbc' );
		$iv        = openssl_random_pseudo_bytes( $iv_length );

		$encrypted = openssl_encrypt( $json, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );

		return base64_encode( $iv . $encrypted );
	}

	/**
	 * Get the encryption key derived from AUTH_KEY.
	 */
	private static function getEncryptionKey(): string {
		$auth_key = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'nzl-default-key';
		return hash( 'sha256', $auth_key, true );
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
