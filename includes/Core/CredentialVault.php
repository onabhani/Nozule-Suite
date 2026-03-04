<?php

namespace Nozule\Core;

/**
 * Shared encryption utility for API credentials stored at rest.
 *
 * Algorithm : AES-256-CBC
 * Key       : SHA-256 hash of WordPress AUTH_KEY
 * IV        : 16 random bytes, prepended to the ciphertext
 * Encoding  : base64( IV . ciphertext )
 */
class CredentialVault {

	private const CIPHER = 'aes-256-cbc';

	/**
	 * WordPress ships wp-config-sample.php with this placeholder value.
	 * It must be replaced before credentials can be encrypted safely.
	 */
	private const WP_PLACEHOLDER = 'put your unique phrase here';

	/**
	 * Encrypt an associative array of credentials for storage.
	 *
	 * @param array $data Key-value pairs to encrypt.
	 * @return string Base64-encoded IV + ciphertext.
	 *
	 * @throws \RuntimeException If AUTH_KEY is missing or still the default placeholder.
	 */
	public static function encrypt( array $data ): string {
		$key       = self::deriveKey();
		$json      = wp_json_encode( $data );
		$iv_length = openssl_cipher_iv_length( self::CIPHER );
		$iv        = openssl_random_pseudo_bytes( $iv_length );

		$encrypted = openssl_encrypt( $json, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv );

		if ( $encrypted === false ) {
			throw new \RuntimeException( 'CredentialVault: openssl_encrypt failed.' );
		}

		return base64_encode( $iv . $encrypted );
	}

	/**
	 * Decrypt a stored ciphertext back to an associative array.
	 *
	 * Supports a legacy migration path: if the value is not valid base64
	 * it is treated as plain JSON and decoded directly.
	 *
	 * @param string $ciphertext Base64-encoded IV + ciphertext, or legacy plain JSON.
	 * @return array Associative array of credential key-value pairs.
	 *
	 * @throws \RuntimeException If AUTH_KEY is missing or still the default placeholder.
	 */
	public static function decrypt( string $ciphertext ): array {
		if ( $ciphertext === '' ) {
			return [];
		}

		$key = self::deriveKey();

		// Attempt base64 decode.
		$decoded = base64_decode( $ciphertext, true );
		if ( $decoded === false ) {
			// Not base64 — might be plain JSON (legacy).
			$plain = json_decode( $ciphertext, true );
			return is_array( $plain ) ? $plain : [];
		}

		$iv_length = openssl_cipher_iv_length( self::CIPHER );
		if ( strlen( $decoded ) < $iv_length ) {
			return [];
		}

		$iv        = substr( $decoded, 0, $iv_length );
		$encrypted = substr( $decoded, $iv_length );

		$decrypted = openssl_decrypt( $encrypted, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv );

		if ( $decrypted === false ) {
			return [];
		}

		$result = json_decode( $decrypted, true );

		return is_array( $result ) ? $result : [];
	}

	/**
	 * Check whether a stored value is already encrypted.
	 *
	 * Returns true if the value is valid base64, contains at least an IV,
	 * and decrypts successfully with the current key.
	 *
	 * @param string $value The stored credential string to test.
	 * @return bool
	 */
	public static function isEncrypted( string $value ): bool {
		if ( $value === '' ) {
			return false;
		}

		$decoded = base64_decode( $value, true );
		if ( $decoded === false ) {
			return false;
		}

		$iv_length = openssl_cipher_iv_length( self::CIPHER );
		if ( strlen( $decoded ) < $iv_length ) {
			return false;
		}

		$iv        = substr( $decoded, 0, $iv_length );
		$encrypted = substr( $decoded, $iv_length );

		try {
			$key       = self::deriveKey();
			$decrypted = openssl_decrypt( $encrypted, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv );
		} catch ( \RuntimeException $e ) {
			return false;
		}

		if ( $decrypted === false ) {
			return false;
		}

		$parsed = json_decode( $decrypted, true );

		return is_array( $parsed );
	}

	/**
	 * Derive the 256-bit encryption key from AUTH_KEY.
	 *
	 * @throws \RuntimeException If AUTH_KEY is not defined or is the default placeholder.
	 */
	private static function deriveKey(): string {
		if ( ! defined( 'AUTH_KEY' ) ) {
			throw new \RuntimeException(
				'CredentialVault: AUTH_KEY is not defined. Cannot encrypt or decrypt credentials.'
			);
		}

		if ( AUTH_KEY === self::WP_PLACEHOLDER ) {
			throw new \RuntimeException(
				'CredentialVault: AUTH_KEY is still the default WordPress placeholder. '
				. 'Set a unique AUTH_KEY in wp-config.php before storing credentials.'
			);
		}

		return hash( 'sha256', AUTH_KEY, true );
	}
}
