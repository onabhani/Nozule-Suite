<?php

namespace Nozule\Core;

/**
 * Shared encryption utility for API credentials stored at rest.
 *
 * Algorithm : AES-256-GCM (authenticated encryption)
 * Key       : SHA-256 hash of WordPress AUTH_KEY
 * IV        : 12 random bytes
 * Tag       : 16-byte GCM authentication tag
 * Encoding  : base64( IV . tag . ciphertext )
 *
 * Decrypt is backward-compatible with legacy AES-256-CBC payloads
 * (16-byte IV, no tag) so existing data keeps working until
 * migration 014 re-encrypts all rows.
 */
class CredentialVault {

	private const CIPHER     = 'aes-256-gcm';
	private const LEGACY_CBC = 'aes-256-cbc';
	private const GCM_IV_LEN = 12;
	private const GCM_TAG_LEN = 16;

	/**
	 * WordPress ships wp-config-sample.php with this placeholder value.
	 * It must be replaced before credentials can be encrypted safely.
	 */
	private const WP_PLACEHOLDER = 'put your unique phrase here';

	/**
	 * Encrypt an associative array of credentials for storage.
	 *
	 * @param array $data Key-value pairs to encrypt.
	 * @return string Base64-encoded IV + tag + ciphertext.
	 *
	 * @throws \RuntimeException If AUTH_KEY is missing or still the default placeholder.
	 */
	public static function encrypt( array $data ): string {
		$key  = self::deriveKey();
		$json = wp_json_encode( $data );
		$iv   = openssl_random_pseudo_bytes( self::GCM_IV_LEN );
		$tag  = '';

		$encrypted = openssl_encrypt(
			$json,
			self::CIPHER,
			$key,
			OPENSSL_RAW_DATA,
			$iv,
			$tag,
			'',
			self::GCM_TAG_LEN
		);

		if ( $encrypted === false ) {
			throw new \RuntimeException( 'CredentialVault: openssl_encrypt failed.' );
		}

		return base64_encode( $iv . $tag . $encrypted );
	}

	/**
	 * Decrypt a stored ciphertext back to an associative array.
	 *
	 * Supports two legacy paths:
	 *  1. AES-256-CBC payloads (16-byte IV, no tag) — auto-detected by size.
	 *  2. Plain JSON strings — decoded directly.
	 *
	 * @param string $ciphertext Base64-encoded payload, or legacy plain JSON.
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

		$totalLen = strlen( $decoded );

		// Try GCM first: IV(12) + tag(16) + ciphertext.
		if ( $totalLen >= self::GCM_IV_LEN + self::GCM_TAG_LEN ) {
			$iv  = substr( $decoded, 0, self::GCM_IV_LEN );
			$tag = substr( $decoded, self::GCM_IV_LEN, self::GCM_TAG_LEN );
			$enc = substr( $decoded, self::GCM_IV_LEN + self::GCM_TAG_LEN );

			$decrypted = openssl_decrypt(
				$enc,
				self::CIPHER,
				$key,
				OPENSSL_RAW_DATA,
				$iv,
				$tag
			);

			if ( $decrypted !== false ) {
				$result = json_decode( $decrypted, true );
				if ( is_array( $result ) ) {
					return $result;
				}
			}
		}

		// Fallback: legacy AES-256-CBC (16-byte IV, no tag).
		$cbc_iv_len = openssl_cipher_iv_length( self::LEGACY_CBC );
		if ( $totalLen >= $cbc_iv_len ) {
			$iv  = substr( $decoded, 0, $cbc_iv_len );
			$enc = substr( $decoded, $cbc_iv_len );

			$decrypted = openssl_decrypt( $enc, self::LEGACY_CBC, $key, OPENSSL_RAW_DATA, $iv );

			if ( $decrypted !== false ) {
				$result = json_decode( $decrypted, true );
				if ( is_array( $result ) ) {
					return $result;
				}
			}
		}

		return [];
	}

	/**
	 * Check whether a stored value is already encrypted.
	 *
	 * Returns true if the value is valid base64 and decrypts successfully
	 * with the current key (GCM or legacy CBC).
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

		$totalLen = strlen( $decoded );

		try {
			$key = self::deriveKey();
		} catch ( \RuntimeException $e ) {
			return false;
		}

		// Try GCM.
		if ( $totalLen >= self::GCM_IV_LEN + self::GCM_TAG_LEN ) {
			$iv  = substr( $decoded, 0, self::GCM_IV_LEN );
			$tag = substr( $decoded, self::GCM_IV_LEN, self::GCM_TAG_LEN );
			$enc = substr( $decoded, self::GCM_IV_LEN + self::GCM_TAG_LEN );

			$decrypted = openssl_decrypt( $enc, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag );
			if ( $decrypted !== false ) {
				$parsed = json_decode( $decrypted, true );
				if ( is_array( $parsed ) ) {
					return true;
				}
			}
		}

		// Try legacy CBC.
		$cbc_iv_len = openssl_cipher_iv_length( self::LEGACY_CBC );
		if ( $totalLen >= $cbc_iv_len ) {
			$iv  = substr( $decoded, 0, $cbc_iv_len );
			$enc = substr( $decoded, $cbc_iv_len );

			$decrypted = openssl_decrypt( $enc, self::LEGACY_CBC, $key, OPENSSL_RAW_DATA, $iv );
			if ( $decrypted !== false ) {
				$parsed = json_decode( $decrypted, true );
				if ( is_array( $parsed ) ) {
					return true;
				}
			}
		}

		return false;
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
