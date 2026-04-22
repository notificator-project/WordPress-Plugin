<?php
/**
 * Uninstall cleanup for Notificator Companion.
 *
 * @package NotificatorCompanion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$notificator_companion_option_name = 'notificator_companion_settings';

// Remove plugin options.
delete_option( $notificator_companion_option_name );
delete_option( 'notificator_companion_notification_log' );

require_once ABSPATH . 'wp-admin/includes/file.php';
WP_Filesystem();

global $wp_filesystem;

/**
 * Delete a directory recursively using WP_Filesystem when available.
 *
 * @param string $dir_path Directory absolute path.
 * @return void
 */
$notificator_companion_delete_dir = static function ( $dir_path ) use ( $wp_filesystem ) {
	if ( ! is_string( $dir_path ) || '' === $dir_path ) {
		return;
	}

	if ( $wp_filesystem && method_exists( $wp_filesystem, 'is_dir' ) && $wp_filesystem->is_dir( $dir_path ) ) {
		$wp_filesystem->rmdir( $dir_path, true );
		return;
	}

	if ( is_dir( $dir_path ) ) {
		$entries = glob( trailingslashit( $dir_path ) . '*' );
		if ( is_array( $entries ) ) {
			foreach ( $entries as $entry ) {
				if ( is_file( $entry ) ) {
					wp_delete_file( $entry );
				}
			}
		}
	}
};

// Remove cached scan files from uploads.

$notificator_companion_uploads = wp_upload_dir();
if ( is_array( $notificator_companion_uploads ) && empty( $notificator_companion_uploads['error'] ) && ! empty( $notificator_companion_uploads['basedir'] ) ) {
	$notificator_companion_data_dir = trailingslashit( $notificator_companion_uploads['basedir'] ) . 'notificator-companion';
	$notificator_companion_files = array(
		trailingslashit( $notificator_companion_data_dir ) . 'scanned-hooks.json',
		trailingslashit( $notificator_companion_data_dir ) . 'scanned-hooks.json.tmp',
		trailingslashit( $notificator_companion_data_dir ) . '.htaccess',
		trailingslashit( $notificator_companion_data_dir ) . 'index.php',
	);

	foreach ( $notificator_companion_files as $notificator_companion_file ) {
		if ( is_string( $notificator_companion_file ) && file_exists( $notificator_companion_file ) ) {
			wp_delete_file( $notificator_companion_file );
		}
	}

	$notificator_companion_delete_dir( $notificator_companion_data_dir );
}

// Remove legacy cached scan files from plugin data folder.
$notificator_companion_legacy_dir = plugin_dir_path( __FILE__ ) . 'data';
$notificator_companion_legacy_files = array(
	trailingslashit( $notificator_companion_legacy_dir ) . 'scanned-hooks.json',
	trailingslashit( $notificator_companion_legacy_dir ) . 'scanned-hooks.json.tmp',
	trailingslashit( $notificator_companion_legacy_dir ) . '.htaccess',
	trailingslashit( $notificator_companion_legacy_dir ) . 'index.php',
);

foreach ( $notificator_companion_legacy_files as $notificator_companion_file ) {
	if ( is_string( $notificator_companion_file ) && file_exists( $notificator_companion_file ) ) {
		wp_delete_file( $notificator_companion_file );
	}
}

$notificator_companion_delete_dir( $notificator_companion_legacy_dir );
