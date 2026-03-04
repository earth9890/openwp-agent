<?php
/**
 * Provider key manager.
 *
 * @package OpenWP\Inc\Security
 */

namespace OpenWP\Inc\Security;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores provider keys encrypted in WP options.
 */
class Provider_Key_Manager {
	/**
	 * Encryption service.
	 *
	 * @var Encryption_Service
	 */
	private $encryption;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->encryption = new Encryption_Service();
	}

	/**
	 * Save provider keys.
	 *
	 * @param array<string,string> $keys Raw keys.
	 * @return array<string,string>|WP_Error
	 */
	public function save_keys( $keys ) {
		$stored = $this->get_keys();

		foreach ( [ 'openai', 'anthropic', 'glm', 'openrouter' ] as $provider ) {
			if ( isset( $keys[ $provider ] ) ) {
				$value = trim( (string) $keys[ $provider ] );
				if ( '' !== $value ) {
					$encrypted = $this->encryption->encrypt( $value );
					if ( '' === $encrypted ) {
						return new WP_Error( 'openwp_key_encrypt_failed', __( 'Failed to encrypt provider key for storage.', 'openwp' ) );
					}

					$stored[ $provider ] = $encrypted;
				}
			}
		}

		update_option( OPENWP_OPTION_PROVIDER_KEYS, $stored );

		return $this->get_masked_keys();
	}

	/**
	 * Return decrypted key for provider.
	 *
	 * @param string $provider Provider slug.
	 * @return string
	 */
	public function get_key( $provider ) {
		$stored = $this->get_keys();
		$key    = isset( $stored[ $provider ] ) ? (string) $stored[ $provider ] : '';

		if ( '' === $key ) {
			return '';
		}

		return $this->encryption->decrypt( $key );
	}

	/**
	 * Return masked keys for UI.
	 *
	 * @return array<string,string>
	 */
	public function get_masked_keys() {
		$result = [
			'openai'    => '',
			'anthropic' => '',
			'glm'       => '',
			'openrouter' => '',
		];

		foreach ( array_keys( $result ) as $provider ) {
			$key = $this->get_key( $provider );
			if ( '' === $key ) {
				continue;
			}

			$len = strlen( $key );
			if ( $len <= 8 ) {
				$result[ $provider ] = str_repeat( '*', $len );
				continue;
			}

			$result[ $provider ] = substr( $key, 0, 4 ) . str_repeat( '*', max( 1, $len - 8 ) ) . substr( $key, -4 );
		}

		return $result;
	}

	/**
	 * Read raw storage.
	 *
	 * @return array<string,string>
	 */
	private function get_keys() {
		$keys = get_option( OPENWP_OPTION_PROVIDER_KEYS, [] );

		if ( ! is_array( $keys ) ) {
			$keys = [];
		}

		return wp_parse_args(
			$keys,
			[
				'openai'    => '',
				'anthropic' => '',
				'glm'       => '',
				'openrouter' => '',
			]
		);
	}
}
