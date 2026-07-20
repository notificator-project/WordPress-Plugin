<?php
/**
 * Runtime health state for the Notificator admin experience.
 *
 * @package NotificatorCompanion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores small, non-sensitive operational summaries used by the Overview page.
 */
class Notificator_Companion_Health_Service {

	const OPTION_NAME = 'notificator_companion_health';

	/**
	 * Return normalized health data.
	 *
	 * @return array
	 */
	public function get_status() {
		$status = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $status ) ) {
			$status = array();
		}

		return wp_parse_args(
			$status,
			array(
				'last_scan_at'         => (int) get_option( 'notificator_companion_last_scan', 0 ),
				'last_scan_status'     => '',
				'last_scan_plugins'    => 0,
				'last_scan_hooks'      => 0,
				'scan_current_plugin'  => '',
				'scan_processed'       => 0,
				'scan_total'           => 0,
				'last_test_at'         => 0,
				'last_test_status'     => '',
				'last_delivery_at'     => 0,
				'last_delivery_status' => '',
				'last_delivery_error'  => '',
			)
		);
	}

	/**
	 * Merge a health update into the stored state.
	 *
	 * @param array $update State fields to update.
	 * @return void
	 */
	public function record( $update ) {
		if ( ! is_array( $update ) ) {
			return;
		}

		$allowed = array(
			'last_scan_at',
			'last_scan_status',
			'last_scan_plugins',
			'last_scan_hooks',
			'scan_current_plugin',
			'scan_processed',
			'scan_total',
			'last_test_at',
			'last_test_status',
			'last_delivery_at',
			'last_delivery_status',
			'last_delivery_error',
		);

		$status = $this->get_status();
		foreach ( $allowed as $key ) {
			if ( ! array_key_exists( $key, $update ) ) {
				continue;
			}
			$status[ $key ] = is_int( $update[ $key ] )
				? $update[ $key ]
				: sanitize_text_field( (string) $update[ $key ] );
		}

		update_option( self::OPTION_NAME, $status, false );
	}
}
