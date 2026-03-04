<?php
/**
 * Encryption service.
 *
 * @package OpenWP\Inc\Security
 */

namespace OpenWP\Inc\Security;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Encrypts/decrypts secrets for option storage.
 */
class Encryption_Service {
	/**
	 * Encrypt plaintext.
	 *
	 * @param string $plaintext Value to encrypt.
	 * @return string
	 */
	public function encrypt( $plaintext ) {
		if ( '' === $plaintext ) {
			return '';
		}

		if ( function_exists( 'sodium_crypto_secretbox' ) && defined( 'SODIUM_CRYPTO_SECRETBOX_NONCEBYTES' ) ) {
			$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$key   = $this->sodium_key();
			$ciphertext = sodium_crypto_secretbox( $plaintext, $nonce, $key );
			return 'sodium:' . base64_encode( $nonce . $ciphertext );
		}

		$iv    = random_bytes( 16 );
		$key   = hash( 'sha256', $this->key_material(), true );
		$crypt = openssl_encrypt( $plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

		if ( false === $crypt ) {
			return '';
		}

		return 'openssl:' . base64_encode( $iv . $crypt );
	}

	/**
	 * Decrypt stored ciphertext.
	 *
	 * @param string $ciphertext Ciphertext value.
	 * @return string
	 */
	public function decrypt( $ciphertext ) {
		if ( '' === $ciphertext ) {
			return '';
		}

		$parts = explode( ':', $ciphertext, 2 );
		if ( 2 !== count( $parts ) ) {
			return '';
		}

		list( $method, $payload ) = $parts;
		$decoded = base64_decode( $payload, true );

		if ( false === $decoded || '' === $decoded ) {
			return '';
		}

		if ( 'sodium' === $method && function_exists( 'sodium_crypto_secretbox_open' ) ) {
			$nonce_size = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
			$nonce      = substr( $decoded, 0, $nonce_size );
			$encrypted  = substr( $decoded, $nonce_size );
			$plain      = sodium_crypto_secretbox_open( $encrypted, $nonce, $this->sodium_key() );
			return false === $plain ? '' : (string) $plain;
		}

		if ( 'openssl' === $method ) {
			$iv        = substr( $decoded, 0, 16 );
			$encrypted = substr( $decoded, 16 );
			$key       = hash( 'sha256', $this->key_material(), true );
			$plain     = openssl_decrypt( $encrypted, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
			return false === $plain ? '' : (string) $plain;
		}

		return '';
	}

	/**
	 * Build sodium key.
	 *
	 * @return string
	 */
	private function sodium_key() {
		$key = hash( 'sha256', $this->key_material(), true );

		if ( strlen( $key ) > SODIUM_CRYPTO_SECRETBOX_KEYBYTES ) {
			return substr( $key, 0, SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
		}

		if ( strlen( $key ) < SODIUM_CRYPTO_SECRETBOX_KEYBYTES ) {
			return str_pad( $key, SODIUM_CRYPTO_SECRETBOX_KEYBYTES, "\0" );
		}

		return $key;
	}

	/**
	 * Build site-specific encryption material.
	 *
	 * @return string
	 */
	private function key_material() {
		$parts = [
			wp_salt( 'auth' ),
			defined( 'DB_NAME' ) ? DB_NAME : '',
			site_url(),
		];

		return implode( '|', $parts );
	}
}
