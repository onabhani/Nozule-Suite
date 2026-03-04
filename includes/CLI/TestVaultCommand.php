<?php

namespace Nozule\CLI;

use Nozule\Core\CredentialVault;

/**
 * WP-CLI command: wp nozule test-vault
 *
 * Dev/debug only — verifies CredentialVault encrypt/decrypt round-trip.
 */
class TestVaultCommand {

	/**
	 * Test CredentialVault encrypt → decrypt round-trip.
	 *
	 * ## EXAMPLES
	 *
	 *     wp nozule test-vault
	 *
	 * @when after_wp_load
	 */
	public function __invoke(): void {
		$payload   = [ 'value' => 'test-api-key-12345' ];
		$plaintext = $payload['value'];

		// 1. Encrypt.
		\WP_CLI::log( 'Encrypting payload: ' . wp_json_encode( $payload ) );
		$ciphertext = CredentialVault::encrypt( $payload );
		\WP_CLI::log( 'Ciphertext: ' . $ciphertext );

		// 2. isEncrypted on ciphertext → expect true.
		$encCheck = CredentialVault::isEncrypted( $ciphertext );
		\WP_CLI::log( 'isEncrypted(ciphertext): ' . ( $encCheck ? 'true' : 'false' )
			. ' — ' . ( $encCheck ? 'PASS' : 'FAIL' ) );

		// 3. isEncrypted on plaintext → expect false.
		$plainCheck = CredentialVault::isEncrypted( $plaintext );
		\WP_CLI::log( 'isEncrypted(plaintext):  ' . ( $plainCheck ? 'true' : 'false' )
			. ' — ' . ( ! $plainCheck ? 'PASS' : 'FAIL' ) );

		// 4. Decrypt and assert round-trip.
		$decrypted = CredentialVault::decrypt( $ciphertext );
		$match     = isset( $decrypted['value'] ) && $decrypted['value'] === $plaintext;
		\WP_CLI::log( 'Decrypted value: ' . ( $decrypted['value'] ?? '(null)' ) );
		\WP_CLI::log( 'Round-trip match: ' . ( $match ? 'PASS' : 'FAIL' ) );

		// Summary.
		if ( $encCheck && ! $plainCheck && $match ) {
			\WP_CLI::success( 'All CredentialVault checks passed.' );
		} else {
			\WP_CLI::error( 'One or more CredentialVault checks failed.' );
		}
	}
}
