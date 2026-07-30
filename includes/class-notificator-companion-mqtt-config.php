<?php
/**
 * Local HiveMQ Cloud configuration and secret protection.
 *
 * @package NotificatorCompanion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores the MQTT password separately from normal plugin settings and exposes
 * only short-lived request configuration to the delivery layer.
 */
class Notificator_Companion_Mqtt_Config {

	const SECRET_OPTION        = 'notificator_companion_mqtt_secret';
	const HOST_SUFFIX          = '.hivemq.cloud';
	const WSS_PORT             = 8884;
	const WSS_PATH             = '/mqtt';
	const DEFAULT_TOPIC_PREFIX = 'notificator-project';
	const ENCRYPTION_AAD       = 'notificator-mqtt-secret-v1';
	const ENCRYPTION_CIPHER    = 'aes-256-gcm';

	/**
	 * Normalize and validate a HiveMQ Cloud hostname.
	 *
	 * @param string $host Candidate hostname.
	 * @return string Valid hostname or an empty string.
	 */
	public static function sanitize_host( $host ) {
		$host = strtolower( trim( sanitize_text_field( (string) $host ) ) );
		$host = rtrim( $host, '.' );
		if (
			'' === $host ||
			strlen( $host ) > 253 ||
			! preg_match( '/^[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?$/', $host ) ||
			false !== strpos( $host, '..' ) ||
			substr( $host, -strlen( self::HOST_SUFFIX ) ) !== self::HOST_SUFFIX
		) {
			return '';
		}
		return $host;
	}

	/**
	 * Normalize a publisher username.
	 *
	 * @param string $username Candidate username.
	 * @return string Valid username or an empty string.
	 */
	public static function sanitize_username( $username ) {
		$username = trim( sanitize_text_field( (string) $username ) );
		if ( '' === $username || strlen( $username ) > 128 ) {
			return '';
		}
		return $username;
	}

	/**
	 * Normalize a bounded topic prefix without MQTT wildcards.
	 *
	 * @param string $prefix Candidate topic prefix.
	 * @return string Valid prefix or an empty string.
	 */
	public static function sanitize_topic_prefix( $prefix ) {
		$prefix = trim( sanitize_text_field( (string) $prefix ) );
		$prefix = trim( $prefix, '/' );
		if (
			'' === $prefix ||
			strlen( $prefix ) > 128 ||
			false !== strpos( $prefix, '#' ) ||
			false !== strpos( $prefix, '+' ) ||
			! preg_match( '/^[A-Za-z0-9._-]+(?:\/[A-Za-z0-9._-]+)*$/', $prefix )
		) {
			return '';
		}
		return $prefix;
	}

	/**
	 * Validate a password without normalizing or changing its bytes.
	 *
	 * @param mixed $password Candidate password.
	 * @return string Valid password or an empty string.
	 */
	public static function sanitize_password( $password ) {
		if ( ! is_string( $password ) ) {
			return '';
		}
		if (
			'' === $password ||
			strlen( $password ) > 512 ||
			preg_match( '/[\x00-\x1F\x7F]/', $password )
		) {
			return '';
		}
		return $password;
	}

	/**
	 * Encrypt and store a publisher password in a non-autoloaded option.
	 *
	 * The key is derived from WordPress authentication salts. Rotating those
	 * salts intentionally makes the stored secret unreadable and requires the
	 * administrator to enter it again.
	 *
	 * @param string $password Plaintext password.
	 * @return bool Whether the secret was stored.
	 */
	public static function store_password( $password ) {
		$password = self::sanitize_password( $password );
		if ( '' === $password || ! function_exists( 'openssl_encrypt' ) ) {
			return false;
		}

		try {
			$iv = random_bytes( 12 );
		} catch ( Exception $exception ) {
			return false;
		}

		$tag        = '';
		$ciphertext = openssl_encrypt(
			$password,
			self::ENCRYPTION_CIPHER,
			self::get_encryption_key(),
			OPENSSL_RAW_DATA,
			$iv,
			$tag,
			self::ENCRYPTION_AAD,
			16
		);
		if ( false === $ciphertext || 16 !== strlen( $tag ) ) {
			return false;
		}

		// Base64 safely serializes encrypted binary values for WordPress options;
		// it is encoding only and is not used as a security boundary.
		// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$record = array(
			'version'    => 1,
			'algorithm'  => self::ENCRYPTION_CIPHER,
			'iv'         => base64_encode( $iv ),
			'tag'        => base64_encode( $tag ),
			'ciphertext' => base64_encode( $ciphertext ),
		);
		// phpcs:enable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

		return update_option( self::SECRET_OPTION, $record, false ) || self::has_password();
	}

	/**
	 * Remove the locally encrypted MQTT password.
	 *
	 * @return void
	 */
	public static function forget_password() {
		delete_option( self::SECRET_OPTION );
	}

	/**
	 * Return whether the stored password can currently be decrypted.
	 *
	 * @return bool
	 */
	public static function has_password() {
		return '' !== self::get_password();
	}

	/**
	 * Build redacted state for the settings UI.
	 *
	 * @param array $options Plugin settings.
	 * @return array<string,mixed>
	 */
	public static function get_admin_state( $options ) {
		$options    = is_array( $options ) ? $options : array();
		$enabled    = ! empty( $options['mqtt_custom_enabled'] );
		$host       = self::sanitize_host( isset( $options['mqtt_host'] ) ? $options['mqtt_host'] : '' );
		$username   = self::sanitize_username( isset( $options['mqtt_username'] ) ? $options['mqtt_username'] : '' );
		$prefix     = self::sanitize_topic_prefix( isset( $options['mqtt_topic_prefix'] ) ? $options['mqtt_topic_prefix'] : self::DEFAULT_TOPIC_PREFIX );
		$has_secret = self::has_password();
		$configured = '' !== $host && '' !== $username && '' !== $prefix && $has_secret;

		return array(
			'enabled'             => $enabled,
			'configured'          => $configured,
			'ready'               => $enabled && $configured,
			'host'                => $host,
			'username'            => $username,
			'topic_prefix'        => $prefix,
			'password_configured' => $has_secret,
			'port'                => self::WSS_PORT,
			'path'                => self::WSS_PATH,
		);
	}

	/**
	 * Build the ephemeral configuration added to an outbound API request.
	 *
	 * The returned array contains the plaintext password and must never be
	 * persisted or logged.
	 *
	 * @param array $options Plugin settings.
	 * @return array<string,mixed>
	 */
	public static function get_request_config( $options ) {
		$state = self::get_admin_state( $options );
		if ( empty( $state['ready'] ) ) {
			return array();
		}

		$password = self::get_password();
		if ( '' === $password ) {
			return array();
		}

		return array(
			'version'     => 1,
			'provider'    => 'hivemq_cloud',
			'host'        => $state['host'],
			'port'        => self::WSS_PORT,
			'path'        => self::WSS_PATH,
			'username'    => $state['username'],
			'password'    => $password,
			'topicPrefix' => $state['topic_prefix'],
		);
	}

	/**
	 * Decrypt the password for immediate use.
	 *
	 * @return string Plaintext password or an empty string.
	 */
	private static function get_password() {
		$record = get_option( self::SECRET_OPTION, array() );
		if (
			! is_array( $record ) ||
			1 !== (int) ( isset( $record['version'] ) ? $record['version'] : 0 ) ||
			self::ENCRYPTION_CIPHER !== ( isset( $record['algorithm'] ) ? $record['algorithm'] : '' ) ||
			! function_exists( 'openssl_decrypt' )
		) {
			return '';
		}

		// Decode the authenticated encryption record stored by store_password().
		// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$iv         = isset( $record['iv'] ) ? base64_decode( (string) $record['iv'], true ) : false;
		$tag        = isset( $record['tag'] ) ? base64_decode( (string) $record['tag'], true ) : false;
		$ciphertext = isset( $record['ciphertext'] ) ? base64_decode( (string) $record['ciphertext'], true ) : false;
		// phpcs:enable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $iv || 12 !== strlen( $iv ) || false === $tag || 16 !== strlen( $tag ) || false === $ciphertext ) {
			return '';
		}

		$password = openssl_decrypt(
			$ciphertext,
			self::ENCRYPTION_CIPHER,
			self::get_encryption_key(),
			OPENSSL_RAW_DATA,
			$iv,
			$tag,
			self::ENCRYPTION_AAD
		);
		return is_string( $password ) ? $password : '';
	}

	/**
	 * Derive a site-specific 256-bit key from WordPress authentication salts.
	 *
	 * @return string Raw binary key.
	 */
	private static function get_encryption_key() {
		$key_material = wp_salt( 'auth' ) . '|' . wp_salt( 'secure_auth' );
		return hash( 'sha256', self::ENCRYPTION_AAD . '|' . $key_material, true );
	}
}
