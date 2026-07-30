<?php
/**
 * Uninstall cleanup for Notificator.
 *
 * @package NotificatorCompanion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once ABSPATH . 'wp-admin/includes/file.php';
WP_Filesystem();

global $wp_filesystem;

/**
 * Delete a plugin-owned directory recursively.
 *
 * @param string $dir_path Directory absolute path.
 * @return void
 */
$notificator_companion_delete_dir = null;
$notificator_companion_delete_dir = static function ( $dir_path ) use ( &$notificator_companion_delete_dir, $wp_filesystem ) {
	if ( ! is_string( $dir_path ) || '' === $dir_path ) {
		return;
	}

	if ( $wp_filesystem && method_exists( $wp_filesystem, 'is_dir' ) && $wp_filesystem->is_dir( $dir_path ) ) {
		$wp_filesystem->rmdir( $dir_path, true );
		return;
	}

	if ( ! is_dir( $dir_path ) ) {
		return;
	}

	$entries = array_merge(
		(array) glob( trailingslashit( $dir_path ) . '*' ),
		(array) glob( trailingslashit( $dir_path ) . '.*' )
	);
	foreach ( $entries as $entry ) {
		if ( in_array( basename( $entry ), array( '.', '..' ), true ) ) {
			continue;
		}
		if ( is_dir( $entry ) ) {
			$notificator_companion_delete_dir( $entry );
		} elseif ( is_file( $entry ) ) {
			wp_delete_file( $entry );
		}
	}

	if ( is_dir( $dir_path ) ) {
		rmdir( $dir_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
	}
};

/**
 * Remove all plugin data belonging to the current site.
 *
 * @return void
 */
$notificator_companion_cleanup_site = static function () use ( $notificator_companion_delete_dir ) {
	$settings = get_option( 'notificator_companion_settings', array() );
	if ( is_array( $settings ) && ! empty( $settings['hooks'] ) && is_array( $settings['hooks'] ) ) {
		foreach ( $settings['hooks'] as $hook ) {
			if ( ! is_array( $hook ) || empty( $hook['hook_name'] ) ) {
				continue;
			}
			$scenario_name = isset( $hook['scenario_name'] ) ? (string) $hook['scenario_name'] : '';
			delete_transient( 'notificator_hook_rl_' . md5( (string) $hook['hook_name'] . '|' . $scenario_name ) );
		}
	}

	$option_names = array(
		'notificator_companion_settings',
		'notificator_companion_notification_log',
		'notificator_companion_health',
		'notificator_companion_delivery_queue',
		'notificator_companion_scan_fingerprints',
		'notificator_companion_last_scan',
		'notificator_companion_scan_state',
		'notificator_companion_hook_observation',
		'notificator_companion_observation_flush_lock',
		'notificator_companion_discovery_ignored',
		'notificator_companion_admin_toasts',
		'notificator_companion_admin_toast_seq',
		'notificator_companion_mqtt_secret',
		'notificator_companion_scanned_hooks',
		// Options used by older releases before the plugin was renamed.
		'authenticator_companion_settings',
		'authenticator_companion_scanned_hooks',
		'uptime_monitor_scanned_hooks',
	);

	foreach ( $option_names as $option_name ) {
		delete_option( $option_name );
	}

	delete_transient( 'notificator_companion_scan_lock' );

	// Rate-limit transient names contain a hash, so discover and remove every one.
	global $wpdb;
	$transient_prefixes = array(
		'_transient_notificator_hook_rl_',
		'_transient_timeout_notificator_hook_rl_',
	);
	foreach ( $transient_prefixes as $transient_prefix ) {
		$like = $wpdb->esc_like( $transient_prefix ) . '%';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall must enumerate hash-suffixed transients; WordPress has no API for matching transient names.
		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				$like
			)
		);

		foreach ( (array) $rows as $stored_name ) {
			$transient_name = preg_replace( '/^_transient_(?:timeout_)?/', '', (string) $stored_name );
			if ( is_string( $transient_name ) && 0 === strpos( $transient_name, 'notificator_hook_rl_' ) ) {
				delete_transient( $transient_name );
			}
		}
	}

	// Remove every scheduled instance, including jobs carrying scan or queue arguments.
	foreach ( array( 'notificator_companion_run_background_scan', 'notificator_companion_process_delivery' ) as $cron_hook ) {
		if ( function_exists( 'wp_unschedule_hook' ) ) {
			wp_unschedule_hook( $cron_hook );
		} else {
			wp_clear_scheduled_hook( $cron_hook );
		}
	}

	$uploads = wp_upload_dir();
	if ( is_array( $uploads ) && empty( $uploads['error'] ) && ! empty( $uploads['basedir'] ) ) {
		$notificator_companion_delete_dir(
			trailingslashit( $uploads['basedir'] ) . 'notificator'
		);
	}
};

// Plugin options and upload paths are site-specific on multisite.
if ( is_multisite() && function_exists( 'get_sites' ) ) {
	$notificator_companion_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $notificator_companion_site_ids as $notificator_companion_site_id ) {
		switch_to_blog( (int) $notificator_companion_site_id );
		$notificator_companion_cleanup_site();
		restore_current_blog();
	}
} else {
	$notificator_companion_cleanup_site();
}

// Toast read state is user metadata shared across sites.
delete_metadata( 'user', 0, 'notificator_companion_last_toast_seq', '', true );

// Remove the legacy plugin-local cache directory once after site cleanup.
$notificator_companion_delete_dir( plugin_dir_path( __FILE__ ) . 'data' );
