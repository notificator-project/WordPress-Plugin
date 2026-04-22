<?php
/**
 * Plugin Name: Notificator Companion
 * Plugin URI: https://github.com/vagelisp/wp-notificator
 * Description: WordPress companion plugin for Notificator app. Send notifications, monitor events, and integrate with your notification service.
 * Version: 1.0.0
 * Author: Vagelis Papaioannou
 * Author URI: https://github.com/vagelisp
 * License: GPL-3.0-or-later
 * Text Domain: notificator-companion
 * Domain Path: /languages
 * Requires PHP: 7.2
 *
 * @package NotificatorCompanion
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'NOTIFICATOR_COMPANION_VERSION', '1.0.0' );
define( 'NOTIFICATOR_COMPANION_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'NOTIFICATOR_COMPANION_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'NOTIFICATOR_COMPANION_PLUGIN_FILE', __FILE__ );
define( 'NOTIFICATOR_COMPANION_API_ENDPOINT', 'https://api-wpnotificator.netlify.app/.netlify/functions/wpnotif-api' );

if ( ! function_exists( 'notificator_companion_get_api_endpoint' ) ) {
	/**
	 * Get the API endpoint for outbound requests.
	 *
	 * @return string
	 */
	function notificator_companion_get_api_endpoint() {
		return apply_filters(
			'notificator_companion_api_endpoint',
			NOTIFICATOR_COMPANION_API_ENDPOINT
		);
	}
}

// Include required files.
require_once NOTIFICATOR_COMPANION_PLUGIN_DIR . 'admin/class-admin-page.php';
require_once NOTIFICATOR_COMPANION_PLUGIN_DIR . 'includes/class-plugin-scanner.php';

/**
 * Register a scenario template for the Notificator admin UI.
 *
 * Expected keys (all strings unless noted):
 * - icon (string) Emoji or dashicon class (e.g. dashicons-admin-generic).
 * - title (string) Template title shown in the UI.
 * - hook_name (string) Hook to target when creating a scenario.
 * - description (string) Short description for the template.
 * - scenario_name (string) Default scenario name when applied.
 * - required_plugin (string) Plugin slug used for filtering.
 * - hook_meta (array) Optional hook metadata (arg_names, payload_arity, properties).
 * - conditions (array) Optional prefilled conditions list.
 *
 * @param array $template Template definition.
 * @return void
 */
if ( ! function_exists( 'notificator_companion_register_template' ) ) {
	function notificator_companion_register_template( $template ) {
		global $notificator_companion_registered_templates;

		if ( ! is_array( $notificator_companion_registered_templates ) ) {
			$notificator_companion_registered_templates = array();
		}

		if ( is_array( $template ) ) {
			$notificator_companion_registered_templates[] = $template;
		}
	}
}

/**
 * Get registered scenario templates.
 *
 * @return array<int, array> Registered templates.
 */
if ( ! function_exists( 'notificator_companion_get_registered_templates' ) ) {
	function notificator_companion_get_registered_templates() {
		global $notificator_companion_registered_templates;

		return is_array( $notificator_companion_registered_templates )
			? $notificator_companion_registered_templates
			: array();
	}
}

/**
 * Main Plugin Class
 *
 * @since 2.0.0
 */
class Notificator_Companion {

	/**
	 * Plugin instance
	 *
	 * @var Notificator_Companion|null
	 */
	private static $instance = null;

	/**
	 * Option name for settings
	 *
	 * @var string
	 */
	private $option_name = 'notificator_companion_settings';

	/**
	 * Admin page handler
	 *
	 * @var Notificator_Companion_Admin_Page
	 */
	private $admin_page;

	/**
	 * Plugin scanner
	 *
	 * @var Notificator_Companion_Plugin_Scanner
	 */
	private $plugin_scanner;

	/**
	 * Get plugin instance (Singleton pattern)
	 *
	 * @return Notificator_Companion
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor - Initialize plugin
	 */
	private function __construct() {
		// Initialize components.
		$this->admin_page     = new Notificator_Companion_Admin_Page( $this );
		$this->plugin_scanner = new Notificator_Companion_Plugin_Scanner();

		// Register hooks.
		$this->register_hooks();
	}

	/**
	 * Register WordPress hooks
	 */
	private function register_hooks() {
		// Admin hooks.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this->admin_page, 'enqueue_admin_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_toast_assets' ) );

		// Plugin links.
		add_filter( 'plugin_action_links_' . plugin_basename( NOTIFICATOR_COMPANION_PLUGIN_FILE ), array( $this, 'add_settings_link' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_notificator_companion_test', array( $this, 'handle_test_notification' ) );
		add_action( 'wp_ajax_notificator_companion_refresh_hooks', array( $this, 'handle_refresh_hooks' ) );
		add_action( 'wp_ajax_notificator_companion_save_settings', array( $this, 'handle_save_settings_ajax' ) );
		add_action( 'wp_ajax_notificator_companion_export_hooks', array( $this, 'handle_export_hooks' ) );
		add_action( 'wp_ajax_notificator_companion_import_hooks', array( $this, 'handle_import_hooks' ) );
		add_action( 'wp_ajax_notificator_companion_toggle_log', array( $this, 'handle_toggle_log' ) );
		add_action( 'wp_ajax_notificator_companion_export_log', array( $this, 'handle_export_log' ) );
		add_action( 'wp_ajax_notificator_companion_clear_log', array( $this, 'handle_clear_log' ) );
		add_action( 'wp_ajax_notificator_companion_delete_log_entry', array( $this, 'handle_delete_log_entry' ) );
		add_action( 'wp_ajax_notificator_companion_toggle_admin_toasts', array( $this, 'handle_toggle_admin_toasts' ) );
		add_action( 'wp_ajax_notificator_companion_fetch_admin_toasts', array( $this, 'handle_fetch_admin_toasts' ) );
		add_action( 'notificator_companion_run_background_scan', array( $this, 'handle_background_scan_event' ), 10, 1 );

		// Initialize hook listeners.
		add_action( 'init', array( $this, 'setup_hook_listeners' ) );

	}

	/**
	 * Enqueue global admin toast assets and render queued toasts.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_toast_assets( $hook ) {
		$options = get_option( $this->option_name );
		if ( ! is_array( $options ) ) {
			$options = array();
		}
		$admin_toasts_enabled = ! isset( $options['admin_toasts_enabled'] ) || (bool) $options['admin_toasts_enabled'];
		if ( ! $admin_toasts_enabled ) {
			return;
		}
		$toast_delivery_mode = isset( $options['toast_delivery_mode'] ) ? (string) $options['toast_delivery_mode'] : 'account';
		if ( ! in_array( $toast_delivery_mode, array( 'account', 'tab' ), true ) ) {
			$toast_delivery_mode = 'account';
		}

		$toasts = get_option( 'notificator_companion_admin_toasts', array() );
		if ( ! is_array( $toasts ) ) {
			$toasts = array();
		}

		$settings_url = current_user_can( 'manage_options' )
			? admin_url( 'admin.php?page=notificator-companion#notificator-log' )
			: '';
		$clean = ( 'tab' === $toast_delivery_mode )
			? $this->get_recent_admin_toasts( $toasts, $settings_url )
			: $this->get_unseen_admin_toasts( $toasts, $settings_url );

		if ( empty( $clean ) ) {
			$clean = array();
		}

		$handle = 'notificator-companion-admin-toast';
		$toast_css_path = NOTIFICATOR_COMPANION_PLUGIN_DIR . 'assets/dist/admin-toast.css';
		$toast_js_path  = NOTIFICATOR_COMPANION_PLUGIN_DIR . 'assets/dist/admin-toast.js';
		if ( file_exists( $toast_css_path ) ) {
			wp_enqueue_style(
				$handle,
				NOTIFICATOR_COMPANION_PLUGIN_URL . 'assets/dist/admin-toast.css',
				array(),
				(string) filemtime( $toast_css_path )
			);
		}
		wp_add_inline_style(
			$handle,
			''
		);
		if ( file_exists( $toast_js_path ) ) {
			wp_enqueue_script(
				$handle,
				NOTIFICATOR_COMPANION_PLUGIN_URL . 'assets/dist/admin-toast.js',
				array(),
				(string) filemtime( $toast_js_path ),
				true
			);
		}
		wp_add_inline_script(
			$handle,
			"window.notificatorAdminToastData=" . wp_json_encode(
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'action'  => 'notificator_companion_fetch_admin_toasts',
					'nonce'   => wp_create_nonce( 'notificator_companion_fetch_admin_toasts' ),
					'toastDeliveryMode' => $toast_delivery_mode,
					'toastSettings' => array(
						'duration'  => isset( $options['toast_duration'] ) ? (int) $options['toast_duration'] : 3,
						'positionX' => isset( $options['toast_position_x'] ) ? (string) $options['toast_position_x'] : 'right',
						'positionY' => isset( $options['toast_position_y'] ) ? (string) $options['toast_position_y'] : 'top',
						'dismissMode' => isset( $options['toast_dismiss_mode'] ) ? (string) $options['toast_dismiss_mode'] : 'auto',
					),
				)
			) . ";",
			'before'
		);
		wp_add_inline_script(
			$handle,
			"(function(){var toasts=" . wp_json_encode( $clean ) . ";function readSeen(storage,key){try{var raw=storage.getItem(key);var arr=raw?JSON.parse(raw):[];return Array.isArray(arr)?arr:[];}catch(e){return [];}}function getSeen(){var session=readSeen(window.sessionStorage,'notificatorToastSeen');var local=readSeen(window.localStorage,'notificatorToastSeenGlobal');return Array.from(new Set(session.concat(local)));}function setSeen(arr){try{var data=JSON.stringify(arr);window.sessionStorage.setItem('notificatorToastSeen',data);window.localStorage.setItem('notificatorToastSeenGlobal',data);}catch(e){}}function flush(){if(!window.notificatorToast||!window.notificatorToast.show){return false;}var seen=getSeen();var changed=false;var data=window.notificatorAdminToastData||{};var deliveryMode=(data.toastDeliveryMode||data.toastSettings&&data.toastSettings.deliveryMode||'account')==='tab'?'tab':'account';var now=Math.floor(Date.now()/1000);if(Array.isArray(toasts)){toasts.forEach(function(t){if(!t||(!t.message&&!t.title&&!t.notes)){return;}if(deliveryMode==='tab'&&t.time){var toastTime=parseInt(t.time,10);if(!isNaN(toastTime)&&toastTime>0&&(now-toastTime)>180){return;}}var seq=parseInt(t.seq,10);if(!isNaN(seq)&&seq>0){if(seen.indexOf(seq)!==-1){return;}seen.push(seq);if(seen.length>100){seen=seen.slice(seen.length-100);}changed=true;}var msg=window.notificatorToast.formatMessage?window.notificatorToast.formatMessage(t):t.message;if(!msg){return;}window.notificatorToast.show(msg,t.type,{url:t.url});});}if(changed){setSeen(seen);}return true;}if(flush()){return;}var tries=0;var timer=setInterval(function(){tries++;if(flush()||tries>=20){clearInterval(timer);}},200);})();",
			'after'
		);

	}

	/**
	 * AJAX: Export scenarios (hooks) as JSON.
	 */
	public function handle_export_hooks() {
		check_ajax_referer( 'notificator_companion_export_hooks', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'notificator-companion' ) ), 403 );
			return;
		}

		$options = get_option( $this->option_name );
		if ( ! is_array( $options ) ) {
			$options = array();
		}

		$hooks = isset( $options['hooks'] ) && is_array( $options['hooks'] ) ? array_values( $options['hooks'] ) : array();
		$sanitized = $this->sanitize_settings( array( 'hooks' => $hooks ) );
		$hooks_out = isset( $sanitized['hooks'] ) && is_array( $sanitized['hooks'] ) ? array_values( $sanitized['hooks'] ) : array();

		$payload = array(
			'schema_version' => 1,
			'exported_at'    => gmdate( 'c' ),
			'plugin'         => array(
				'slug'    => 'notificator-companion',
				'version' => defined( 'NOTIFICATOR_COMPANION_VERSION' ) ? NOTIFICATOR_COMPANION_VERSION : '',
			),
			'site'           => array(
				'url' => home_url(),
			),
			'hooks'          => $hooks_out,
		);

		$filename = 'notificator-scenarios-' . gmdate( 'Ymd-His' ) . '.json';

		wp_send_json_success(
			array(
				'file_name' => $filename,
				'payload'   => $payload,
			)
		);
	}

	/**
	 * AJAX: Import scenarios (hooks) from JSON.
	 */
	public function handle_import_hooks() {
		check_ajax_referer( 'notificator_companion_import_hooks', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'notificator-companion' ) ), 403 );
			return;
		}

		$mode = isset( $_POST['mode'] ) ? sanitize_text_field( wp_unslash( $_POST['mode'] ) ) : 'merge';
		if ( ! in_array( $mode, array( 'merge', 'replace' ), true ) ) {
			$mode = 'merge';
		}

		$payload_input = filter_input( INPUT_POST, 'payload', FILTER_UNSAFE_RAW );
		$payload_raw   = is_string( $payload_input ) ? trim( wp_unslash( $payload_input ) ) : '';
		if ( '' === $payload_raw ) {
			wp_send_json_error( array( 'message' => __( 'Missing import payload', 'notificator-companion' ) ), 400 );
			return;
		}

		$data = json_decode( $payload_raw, true );
		if ( ! is_array( $data ) ) {
			$message = __( 'Invalid JSON file', 'notificator-companion' );
			if ( JSON_ERROR_NONE !== json_last_error() ) {
				$message = sprintf(
					/* translators: %s is the JSON error message. */
					__( 'Invalid JSON file: %s', 'notificator-companion' ),
					json_last_error_msg()
				);
			}
			wp_send_json_error( array( 'message' => $message ), 400 );
			return;
		}

		// Accept either a full export object or a raw hooks array.
		$hooks_in = null;
		if ( isset( $data['hooks'] ) && is_array( $data['hooks'] ) ) {
			$hooks_in = $data['hooks'];
		} elseif ( array_keys( $data ) === range( 0, count( $data ) - 1 ) ) {
			$hooks_in = $data;
		}

		if ( ! is_array( $hooks_in ) ) {
			wp_send_json_error( array( 'message' => __( 'No scenarios found in import file', 'notificator-companion' ) ), 400 );
			return;
		}

		$sanitized = $this->sanitize_settings( array( 'hooks' => $hooks_in ) );
		$import_hooks = isset( $sanitized['hooks'] ) && is_array( $sanitized['hooks'] ) ? array_values( $sanitized['hooks'] ) : array();
		if ( empty( $import_hooks ) ) {
			wp_send_json_error( array( 'message' => __( 'Import file contained no valid scenarios', 'notificator-companion' ) ), 400 );
			return;
		}

		$options = get_option( $this->option_name );
		if ( ! is_array( $options ) ) {
			$options = array();
		}

		$existing_hooks = isset( $options['hooks'] ) && is_array( $options['hooks'] ) ? array_values( $options['hooks'] ) : array();

		if ( 'replace' === $mode ) {
			$options['hooks'] = $import_hooks;
		} else {
			// Merge: append imported scenarios, ensuring scenario_name uniqueness.
			$existing_names = array();
			foreach ( $existing_hooks as $hook ) {
				if ( ! is_array( $hook ) ) {
					continue;
				}
				$name = isset( $hook['scenario_name'] ) ? (string) $hook['scenario_name'] : '';
				if ( '' === $name && isset( $hook['hook_name'] ) ) {
					$name = (string) $hook['hook_name'];
				}
				$name = trim( $name );
				if ( '' !== $name ) {
					$existing_names[ strtolower( $name ) ] = true;
				}
			}

			foreach ( $import_hooks as &$hook ) {
				if ( ! is_array( $hook ) ) {
					continue;
				}
				$base_name = isset( $hook['scenario_name'] ) ? (string) $hook['scenario_name'] : '';
				if ( '' === trim( $base_name ) && isset( $hook['hook_name'] ) ) {
					$base_name = (string) $hook['hook_name'];
				}
				$base_name = trim( $base_name );
				if ( '' === $base_name ) {
					$base_name = __( 'Imported scenario', 'notificator-companion' );
				}

				$name = $base_name;
				$lower = strtolower( $name );
				if ( isset( $existing_names[ $lower ] ) ) {
					$suffix = __( ' (imported)', 'notificator-companion' );
					$name = $base_name . $suffix;
					$i = 2;
					while ( isset( $existing_names[ strtolower( $name ) ] ) ) {
						$name = $base_name . $suffix . ' ' . $i;
						$i++;
						if ( $i > 50 ) {
							break;
						}
					}
				}

				$hook['scenario_name'] = $name;
				$existing_names[ strtolower( $name ) ] = true;
			}
			unset( $hook );

			$options['hooks'] = array_values( array_merge( $existing_hooks, $import_hooks ) );
		}

		// Final sanitization pass (ensures merged option stays clean).
		$options = $this->sanitize_settings( $options );
		update_option( $this->option_name, $options, false );

		wp_send_json_success(
			array(
				'message' => ( 'replace' === $mode )
					? __( 'Scenarios imported (replaced existing).', 'notificator-companion' )
					: __( 'Scenarios imported (merged).', 'notificator-companion' ),
				'hooks'   => isset( $options['hooks'] ) ? $options['hooks'] : array(),
			)
		);
	}

	/**
	 * AJAX: Save plugin settings without full page reload.
	 */
	public function handle_save_settings_ajax() {
		check_ajax_referer( 'notificator_companion_save_settings', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'notificator-companion' ) ), 403 );
			return;
		}

		$input = isset( $_POST[ $this->option_name ] )
			? map_deep( wp_unslash( $_POST[ $this->option_name ] ), 'sanitize_text_field' )
			: null;
		if ( ! is_array( $input ) ) {
			wp_send_json_error( array( 'message' => __( 'Missing settings payload', 'notificator-companion' ) ), 400 );
			return;
		}

		$sanitized = $this->sanitize_settings( $input );
		update_option( $this->option_name, $sanitized, false );

		wp_send_json_success(
			array(
				'message' => __( 'Saved', 'notificator-companion' ),
				'hooks'    => isset( $sanitized['hooks'] ) ? $sanitized['hooks'] : array(),
			)
		);
	}

	/**
	 * AJAX: Enable or disable notifications log.
	 */
	public function handle_toggle_log() {
		check_ajax_referer( 'notificator_companion_toggle_log', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'notificator-companion' ) ), 403 );
			return;
		}

		$state = isset( $_POST['state'] ) ? sanitize_text_field( wp_unslash( $_POST['state'] ) ) : '';
		if ( ! in_array( $state, array( 'enable', 'disable' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid state', 'notificator-companion' ) ), 400 );
			return;
		}

		$options = get_option( $this->option_name );
		if ( ! is_array( $options ) ) {
			$options = array();
		}
		$options['log_enabled'] = ( 'enable' === $state );
		$options = $this->sanitize_settings( $options );
		update_option( $this->option_name, $options, false );

		$message = ( 'enable' === $state )
			? __( 'Notifications log enabled.', 'notificator-companion' )
			: __( 'Notifications log disabled.', 'notificator-companion' );

		wp_send_json_success(
			array(
				'message' => $message,
				'enabled' => (bool) $options['log_enabled'],
			)
		);
	}

	/**
	 * AJAX: Enable or disable dashboard toasts.
	 */
	public function handle_toggle_admin_toasts() {
		check_ajax_referer( 'notificator_companion_toggle_admin_toasts', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'notificator-companion' ) ), 403 );
			return;
		}

		$state = isset( $_POST['state'] ) ? sanitize_text_field( wp_unslash( $_POST['state'] ) ) : '';
		if ( ! in_array( $state, array( 'enable', 'disable' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid state', 'notificator-companion' ) ), 400 );
			return;
		}

		$options = get_option( $this->option_name );
		if ( ! is_array( $options ) ) {
			$options = array();
		}
		$options['admin_toasts_enabled'] = ( 'enable' === $state );
		$options = $this->sanitize_settings( $options );
		update_option( $this->option_name, $options, false );

		$message = ( 'enable' === $state )
			? __( 'Dashboard toasts enabled.', 'notificator-companion' )
			: __( 'Dashboard toasts disabled.', 'notificator-companion' );

		wp_send_json_success(
			array(
				'message' => $message,
				'enabled' => (bool) $options['admin_toasts_enabled'],
			)
		);
	}

	/**
	 * AJAX: Export notifications log to CSV.
	 */
	public function handle_export_log() {
		check_ajax_referer( 'notificator_companion_export_log', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'notificator-companion' ) ), 403 );
			return;
		}

		$log = get_option( 'notificator_companion_notification_log', array() );
		if ( ! is_array( $log ) || empty( $log ) ) {
			wp_send_json_error( array( 'message' => __( 'No log entries found', 'notificator-companion' ) ), 404 );
			return;
		}

		$escape_csv_field = static function ( $value ) {
			$value = (string) $value;
			$value = str_replace( '"', '""', $value );
			if ( false !== strpbrk( $value, ",\r\n" ) ) {
				return '"' . $value . '"';
			}

			return $value;
		};

		$lines = array();
		$lines[] = implode(
			',',
			array_map(
				$escape_csv_field,
				array( 'timestamp', 'title', 'hook', 'scenario', 'severity', 'status', 'api_suffixes', 'site_name', 'site_url' )
			)
		);

		foreach ( $log as $entry ) {
			$api_keys = isset( $entry['api_keys'] ) && is_array( $entry['api_keys'] ) ? $entry['api_keys'] : array();
			$api_keys = array_filter( array_map( 'strval', $api_keys ), 'strlen' );
			$api_display = '';
			if ( ! empty( $api_keys ) ) {
				$api_keys = array_map(
					static function( $suffix ) {
						return '…' . $suffix;
					},
					$api_keys
				);
				$api_display = implode( ', ', $api_keys );
			}

			$lines[] = implode(
				',',
				array_map(
					$escape_csv_field,
					array(
						isset( $entry['timestamp'] ) ? (string) $entry['timestamp'] : '',
						isset( $entry['title'] ) ? (string) $entry['title'] : '',
						isset( $entry['hook_name'] ) ? (string) $entry['hook_name'] : '',
						isset( $entry['scenario_name'] ) ? (string) $entry['scenario_name'] : '',
						isset( $entry['severity'] ) ? (string) $entry['severity'] : '',
						isset( $entry['status'] ) ? (string) $entry['status'] : '',
						$api_display,
						isset( $entry['site_name'] ) ? (string) $entry['site_name'] : '',
						isset( $entry['site_url'] ) ? (string) $entry['site_url'] : '',
					)
				)
			);
		}

		$csv = implode( "\r\n", $lines ) . "\r\n";

		$filename = 'notificator-log-' . gmdate( 'Ymd-His' ) . '.csv';
		wp_send_json_success(
			array(
				'file_name' => $filename,
				'csv'       => $csv,
			)
		);
	}

	/**
	 * AJAX: Clear notifications log.
	 */
	public function handle_clear_log() {
		check_ajax_referer( 'notificator_companion_clear_log', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'notificator-companion' ) ), 403 );
			return;
		}

		update_option( 'notificator_companion_notification_log', array(), false );
		wp_send_json_success(
			array(
				'message' => __( 'Log cleared.', 'notificator-companion' ),
			)
		);
	}

	/**
	 * AJAX: Delete a single log entry.
	 */
	public function handle_delete_log_entry() {
		check_ajax_referer( 'notificator_companion_delete_log_entry', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'notificator-companion' ) ), 403 );
			return;
		}

		$entry_id = isset( $_POST['entry_id'] ) ? sanitize_text_field( wp_unslash( $_POST['entry_id'] ) ) : '';
		if ( '' === $entry_id ) {
			wp_send_json_error( array( 'message' => __( 'Missing entry id', 'notificator-companion' ) ), 400 );
			return;
		}

		$log = get_option( 'notificator_companion_notification_log', array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}

		$filtered = array_filter(
			$log,
			static function( $entry ) use ( $entry_id ) {
				return ! ( is_array( $entry ) && isset( $entry['id'] ) && (string) $entry['id'] === $entry_id );
			}
		);

		update_option( 'notificator_companion_notification_log', array_values( $filtered ), false );
		wp_send_json_success(
			array(
				'message' => __( 'Log entry removed.', 'notificator-companion' ),
			)
		);
	}

	/**
	 * Add admin menu item
	 */
	public function add_admin_menu() {
		add_menu_page(
			__( 'Notificator Companion Settings', 'notificator-companion' ),
			__( 'Notificator Companion', 'notificator-companion' ),
			'manage_options',
			'notificator-companion',
			array( $this->admin_page, 'render_settings_page' ),
			'dashicons-megaphone',
			58
		);
	}

	/**
	 * Register plugin settings
	 */
	public function register_settings() {
		register_setting(
			'notificator_companion_settings_group',
			$this->option_name,
			array(
				'type'              => 'array',
				'default'           => array(),
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'autoload'          => false,
			)
		);
	}

	/**
	 * Sanitize settings before saving
	 *
	 * @param array $input Raw input data.
	 * @return array Sanitized data.
	 */
	public function sanitize_settings( $input ) {
		$sanitized = array();

		// Sanitize API keys and optional nicknames.
		$api_keys = array();
		$api_key_nicknames = array();
		$key_index_map = array();
		if ( isset( $input['api_keys'] ) ) {
			if ( is_array( $input['api_keys'] ) ) {
				$nickname_input = isset( $input['api_key_nicknames'] ) && is_array( $input['api_key_nicknames'] )
					? array_values( $input['api_key_nicknames'] )
					: array();
				foreach ( $input['api_keys'] as $index => $key ) {
					$key = is_string( $key ) ? trim( sanitize_text_field( $key ) ) : '';
					if ( '' !== $key ) {
						$nickname = '';
						if ( isset( $nickname_input[ $index ] ) && is_string( $nickname_input[ $index ] ) ) {
							$nickname = trim( sanitize_text_field( $nickname_input[ $index ] ) );
						}

						if ( ! isset( $key_index_map[ $key ] ) ) {
							$key_index_map[ $key ] = count( $api_keys );
							$api_keys[] = $key;
							$api_key_nicknames[] = $nickname;
						} elseif ( '' === $api_key_nicknames[ $key_index_map[ $key ] ] && '' !== $nickname ) {
							$api_key_nicknames[ $key_index_map[ $key ] ] = $nickname;
						}
					}
				}
			} elseif ( is_string( $input['api_keys'] ) ) {
				$raw = sanitize_textarea_field( $input['api_keys'] );
				$parts = preg_split( '/[\r\n,]+/', $raw );
				if ( is_array( $parts ) ) {
					foreach ( $parts as $p ) {
						$p = trim( (string) $p );
						if ( '' !== $p ) {
							if ( ! isset( $key_index_map[ $p ] ) ) {
								$key_index_map[ $p ] = count( $api_keys );
								$api_keys[] = $p;
								$api_key_nicknames[] = '';
							}
						}
					}
				}
			}
		}

		// Back-compat: accept legacy single api_key input.
		if ( isset( $input['api_key'] ) && is_string( $input['api_key'] ) ) {
			$legacy_key = trim( sanitize_text_field( $input['api_key'] ) );
			if ( '' !== $legacy_key && ! isset( $key_index_map[ $legacy_key ] ) ) {
				$key_index_map[ $legacy_key ] = count( $api_keys );
				$api_keys[] = $legacy_key;
				$api_key_nicknames[] = '';
			}
		}

		$sanitized['api_keys'] = $api_keys;
		$sanitized['api_key_nicknames'] = $api_key_nicknames;
		$sanitized['api_key']  = ! empty( $api_keys ) ? $api_keys[0] : '';

		$current_options = get_option( $this->option_name );
		if ( ! is_array( $current_options ) ) {
			$current_options = array();
		}

		// Log pagination preference.
		$log_per_page = isset( $input['log_per_page'] ) ? (int) $input['log_per_page'] : 20;
		if ( $log_per_page < 5 ) {
			$log_per_page = 5;
		} elseif ( $log_per_page > 200 ) {
			$log_per_page = 200;
		}
		$sanitized['log_per_page'] = $log_per_page;

		// Log enabled preference.
		$log_enabled = isset( $input['log_enabled'] )
			? (bool) $input['log_enabled']
			: ( isset( $current_options['log_enabled'] ) ? (bool) $current_options['log_enabled'] : true );
		$sanitized['log_enabled'] = $log_enabled;

		// Dashboard toasts preference.
		$admin_toasts_enabled = isset( $input['admin_toasts_enabled'] )
			? (bool) $input['admin_toasts_enabled']
			: ( isset( $current_options['admin_toasts_enabled'] ) ? (bool) $current_options['admin_toasts_enabled'] : true );
		$sanitized['admin_toasts_enabled'] = $admin_toasts_enabled;

		// Toast settings.
		$toast_duration = isset( $input['toast_duration'] ) ? (int) $input['toast_duration'] : 3;
		if ( $toast_duration < 1 ) {
			$toast_duration = 1;
		} elseif ( $toast_duration > 15 ) {
			$toast_duration = 15;
		}
		$toast_position_x = isset( $input['toast_position_x'] ) ? sanitize_text_field( $input['toast_position_x'] ) : 'right';
		if ( ! in_array( $toast_position_x, array( 'left', 'center', 'right' ), true ) ) {
			$toast_position_x = 'right';
		}
		$toast_position_y = isset( $input['toast_position_y'] ) ? sanitize_text_field( $input['toast_position_y'] ) : 'top';
		if ( ! in_array( $toast_position_y, array( 'top', 'bottom' ), true ) ) {
			$toast_position_y = 'top';
		}
		$toast_delivery_mode = isset( $input['toast_delivery_mode'] ) ? sanitize_text_field( $input['toast_delivery_mode'] ) : 'account';
		if ( ! in_array( $toast_delivery_mode, array( 'account', 'tab' ), true ) ) {
			$toast_delivery_mode = 'account';
		}
		$toast_dismiss_mode = isset( $input['toast_dismiss_mode'] ) ? sanitize_text_field( $input['toast_dismiss_mode'] ) : 'auto';
		if ( ! in_array( $toast_dismiss_mode, array( 'auto', 'click' ), true ) ) {
			$toast_dismiss_mode = 'auto';
		}
		$sanitized['toast_duration'] = $toast_duration;
		$sanitized['toast_position_x'] = $toast_position_x;
		$sanitized['toast_position_y'] = $toast_position_y;
		$sanitized['toast_delivery_mode'] = $toast_delivery_mode;
		$sanitized['toast_dismiss_mode'] = $toast_dismiss_mode;

		// Throttle setting (seconds).
		$throttle_seconds = isset( $input['throttle_seconds'] ) ? (int) $input['throttle_seconds'] : 30;
		if ( $throttle_seconds < 0 ) {
			$throttle_seconds = 0;
		} elseif ( $throttle_seconds > 3600 ) {
			$throttle_seconds = 3600;
		}
		$sanitized['throttle_seconds'] = $throttle_seconds;

		// Scan hook limit per plugin.
		$scan_hook_limit = isset( $input['scan_hook_limit'] )
			? (int) $input['scan_hook_limit']
			: ( isset( $current_options['scan_hook_limit'] ) ? (int) $current_options['scan_hook_limit'] : 500 );
		if ( $scan_hook_limit < 50 ) {
			$scan_hook_limit = 50;
		} elseif ( $scan_hook_limit > 5000 ) {
			$scan_hook_limit = 5000;
		}
		$sanitized['scan_hook_limit'] = $scan_hook_limit;

		// Function URL is hardcoded in the constant.

		// Sanitize monitors.
		if ( isset( $input['monitors'] ) && is_array( $input['monitors'] ) ) {
			$sanitized['monitors'] = array();
			foreach ( $input['monitors'] as $monitor ) {
				if ( ! is_array( $monitor ) || empty( $monitor['url'] ) ) {
					continue;
				}

				$method = isset( $monitor['method'] ) ? $monitor['method'] : 'HEAD';
				$sanitized['monitors'][] = array(
					'name'    => isset( $monitor['name'] ) ? sanitize_text_field( $monitor['name'] ) : '',
					'url'     => esc_url_raw( $monitor['url'] ),
					'method'  => in_array( $method, array( 'HEAD', 'GET' ), true ) ? $method : 'HEAD',
					'enabled' => isset( $monitor['enabled'] ) ? (bool) $monitor['enabled'] : true,
				);
			}
		}

		// Sanitize hooks (scenarios).
		if ( isset( $input['hooks'] ) && is_array( $input['hooks'] ) ) {
			$sanitized['hooks'] = array();
			foreach ( $input['hooks'] as $hook ) {
				if ( ! is_array( $hook ) || empty( $hook['hook_name'] ) ) {
					continue;
				}

				$sanitized_hook = array(
					'hook_name'   => sanitize_text_field( $hook['hook_name'] ),
					'description' => isset( $hook['description'] ) ? sanitize_text_field( $hook['description'] ) : '',
					'enabled'     => isset( $hook['enabled'] ) ? (bool) $hook['enabled'] : true,
				);

				if ( isset( $hook['scenario_name'] ) ) {
					$sanitized_hook['scenario_name'] = sanitize_text_field( $hook['scenario_name'] );
				}

				if ( isset( $hook['scenario_notes'] ) ) {
					$sanitized_hook['scenario_notes'] = sanitize_textarea_field( $hook['scenario_notes'] );
				}

				if ( isset( $hook['plugin_key'] ) ) {
					$sanitized_hook['plugin_key'] = sanitize_text_field( $hook['plugin_key'] );
				}

				if ( isset( $hook['plugin_name'] ) ) {
					$sanitized_hook['plugin_name'] = sanitize_text_field( $hook['plugin_name'] );
				}

				if ( isset( $hook['severity'] ) ) {
					$severity = sanitize_text_field( $hook['severity'] );
					$sanitized_hook['severity'] = in_array( $severity, array( 'info', 'warning', 'critical' ), true ) ? $severity : 'info';
				}

				if ( isset( $hook['send_push'] ) ) {
					$sanitized_hook['send_push'] = (bool) $hook['send_push'];
				}

				if ( isset( $hook['send_mqtt'] ) ) {
					$sanitized_hook['send_mqtt'] = (bool) $hook['send_mqtt'];
				}

				// Sanitize hook metadata if present.
				if ( isset( $hook['hook_meta'] ) ) {
					$meta_raw = $hook['hook_meta'];
					$meta     = null;
					if ( is_string( $meta_raw ) ) {
						$meta = json_decode( wp_unslash( $meta_raw ), true );
					} elseif ( is_array( $meta_raw ) ) {
						$meta = $meta_raw;
					}

					if ( is_array( $meta ) && ! empty( $meta ) ) {
						$sanitized_meta = array();

						if ( isset( $meta['label'] ) ) {
							$sanitized_meta['label'] = sanitize_text_field( $meta['label'] );
						}
						if ( isset( $meta['type'] ) ) {
							$sanitized_meta['type'] = in_array( $meta['type'], array( 'action', 'filter' ), true ) ? $meta['type'] : 'action';
						}
						if ( isset( $meta['payload_arity'] ) && is_numeric( $meta['payload_arity'] ) ) {
							$sanitized_meta['payload_arity'] = (int) $meta['payload_arity'];
						}
						if ( isset( $meta['arg_names'] ) && is_array( $meta['arg_names'] ) ) {
							$sanitized_meta['arg_names'] = array_map( 'sanitize_text_field', $meta['arg_names'] );
						}
						if ( isset( $meta['arg_mode'] ) ) {
							$sanitized_meta['arg_mode'] = in_array( $meta['arg_mode'], array( 'direct', 'ref_array' ), true ) ? $meta['arg_mode'] : 'direct';
						}

						// Optional property definitions for object args (e.g. order.total).
						if ( isset( $meta['properties'] ) && is_array( $meta['properties'] ) ) {
							$sanitized_properties = array();
							foreach ( $meta['properties'] as $arg_name => $props ) {
								if ( ! is_array( $props ) ) {
									continue;
								}

								$sanitized_props = array();
								foreach ( $props as $prop ) {
									if ( ! is_array( $prop ) || empty( $prop['name'] ) ) {
										continue;
									}

									$sanitized_props[] = array(
										'name'   => sanitize_text_field( $prop['name'] ),
										'label'  => isset( $prop['label'] ) ? sanitize_text_field( $prop['label'] ) : '',
										'type'   => isset( $prop['type'] ) ? sanitize_text_field( $prop['type'] ) : 'string',
										'method' => isset( $prop['method'] ) ? sanitize_text_field( $prop['method'] ) : '',
									);
								}

								if ( ! empty( $sanitized_props ) ) {
									$sanitized_properties[ sanitize_text_field( $arg_name ) ] = $sanitized_props;
								}
							}

							if ( ! empty( $sanitized_properties ) ) {
								$sanitized_meta['properties'] = $sanitized_properties;
							}
						}

						if ( ! empty( $sanitized_meta ) ) {
							$sanitized_hook['hook_meta'] = $sanitized_meta;
						}
					}
				}

				// Sanitize conditions if present.
				if ( isset( $hook['conditions'] ) && is_array( $hook['conditions'] ) ) {
					$sanitized_conditions = array();
					foreach ( $hook['conditions'] as $condition ) {
						if ( ! is_array( $condition ) || empty( $condition['field'] ) || empty( $condition['operator'] ) ) {
							continue;
						}

						$field = sanitize_text_field( $condition['field'] );
						// Allow: field OR field.subfield (e.g. order.total)
						if ( ! preg_match( '/^[a-z_][a-z0-9_]*(\.[a-z_][a-z0-9_]*)?$/i', $field ) ) {
							continue;
						}

						$sanitized_conditions[] = array(
							'field'    => $field,
							'operator' => in_array( $condition['operator'], array( '=', '!=', '>', '>=', '<', '<=', 'contains', 'not_contains' ), true )
								? $condition['operator']
								: '=',
							'value'    => isset( $condition['value'] ) ? sanitize_text_field( $condition['value'] ) : '',
						);
					}

					if ( ! empty( $sanitized_conditions ) ) {
						$sanitized_hook['conditions'] = $sanitized_conditions;
					}
				}

				$sanitized['hooks'][] = $sanitized_hook;
			}
		}

		return $sanitized;
	}

	/**
	 * Normalize API keys from the saved options array.
	 *
	 * @param array $options Options array.
	 * @return array<int,string>
	 */
	private function get_api_keys_from_options( $options ) {
		$api_keys = array();
		if ( is_array( $options ) ) {
			if ( isset( $options['api_keys'] ) && is_array( $options['api_keys'] ) ) {
				foreach ( $options['api_keys'] as $key ) {
					$key = is_string( $key ) ? trim( $key ) : '';
					if ( '' !== $key ) {
						$api_keys[] = $key;
					}
				}
			}

			if ( empty( $api_keys ) && isset( $options['api_key'] ) && is_string( $options['api_key'] ) ) {
				$legacy_key = trim( $options['api_key'] );
				if ( '' !== $legacy_key ) {
					$api_keys[] = $legacy_key;
				}
			}
		}

		return array_values( array_unique( $api_keys ) );
	}

	/**
	 * Build a map of API key => preferred display label (nickname if set).
	 *
	 * @param array $options Options array.
	 * @return array<string,string>
	 */
	private function get_api_key_labels_map_from_options( $options ) {
		$labels = array();

		if ( ! is_array( $options ) ) {
			return $labels;
		}

		if ( isset( $options['api_keys'] ) && is_array( $options['api_keys'] ) ) {
			$nickname_input = isset( $options['api_key_nicknames'] ) && is_array( $options['api_key_nicknames'] )
				? array_values( $options['api_key_nicknames'] )
				: array();

			foreach ( array_values( $options['api_keys'] ) as $index => $raw_key ) {
				$key = is_string( $raw_key ) ? trim( $raw_key ) : '';
				if ( '' === $key ) {
					continue;
				}

				$nickname = '';
				if ( isset( $nickname_input[ $index ] ) && is_string( $nickname_input[ $index ] ) ) {
					$nickname = trim( $nickname_input[ $index ] );
				}

				if ( ! isset( $labels[ $key ] ) || '' === $labels[ $key ] ) {
					$labels[ $key ] = $nickname;
				}
			}
		}

		if ( empty( $labels ) && isset( $options['api_key'] ) && is_string( $options['api_key'] ) ) {
			$legacy_key = trim( $options['api_key'] );
			if ( '' !== $legacy_key ) {
				$labels[ $legacy_key ] = '';
			}
		}

		return $labels;
	}

	/**
	 * Setup hook listeners for enabled scenarios
	 */
	public function setup_hook_listeners() {
		$options = get_option( $this->option_name );
		$hooks   = isset( $options['hooks'] ) ? $options['hooks'] : array();

		foreach ( $hooks as $hook_config ) {
			// Skip disabled hooks.
			if ( ! isset( $hook_config['enabled'] ) || ! $hook_config['enabled'] ) {
				continue;
			}

			$hook_name   = $hook_config['hook_name'];
			$description = isset( $hook_config['description'] ) ? $hook_config['description'] : $hook_name;
			$hook_meta   = isset( $hook_config['hook_meta'] ) && is_array( $hook_config['hook_meta'] ) ? $hook_config['hook_meta'] : array();
			$hook_type   = isset( $hook_meta['type'] ) && in_array( $hook_meta['type'], array( 'action', 'filter' ), true ) ? $hook_meta['type'] : 'action';

			// Add the hook listener.
			// - Capture args via func_get_args().
			// - Evaluate conditions at runtime.
			if ( 'filter' === $hook_type ) {
				add_filter(
					$hook_name,
					function () use ( $hook_name, $description, $hook_config ) {
						$args = func_get_args();
						if ( $this->should_trigger_notification( $hook_config, $args ) ) {
							$this->send_hook_notification( $hook_config, $hook_name, $description, $args );
						}

						// Preserve filter behavior.
						return isset( $args[0] ) ? $args[0] : null;
					},
					999,
					10
				);
			} else {
				add_action(
					$hook_name,
					function () use ( $hook_name, $description, $hook_config ) {
						$args = func_get_args();
						if ( $this->should_trigger_notification( $hook_config, $args ) ) {
							$this->send_hook_notification( $hook_config, $hook_name, $description, $args );
						}
					},
					999,
					10
				);
			}
		}
	}

	/**
	 * Determine whether a scenario should trigger, based on runtime hook args.
	 *
	 * @param array $hook_config Scenario configuration.
	 * @param array $args Runtime hook arguments (from func_get_args).
	 * @return bool
	 */
	private function should_trigger_notification( $hook_config, $args ) {
		$conditions = isset( $hook_config['conditions'] ) && is_array( $hook_config['conditions'] ) ? $hook_config['conditions'] : array();
		if ( empty( $conditions ) ) {
			return true;
		}

		$hook_meta = isset( $hook_config['hook_meta'] ) && is_array( $hook_config['hook_meta'] ) ? $hook_config['hook_meta'] : array();
		foreach ( $conditions as $condition ) {
			if ( ! is_array( $condition ) ) {
				return false;
			}
			$field    = isset( $condition['field'] ) ? (string) $condition['field'] : '';
			$operator = isset( $condition['operator'] ) ? (string) $condition['operator'] : '=';
			$expected = isset( $condition['value'] ) ? (string) $condition['value'] : '';

			$actual = $this->resolve_condition_field_value( $field, $hook_meta, $args );
			if ( ! $this->evaluate_condition( $actual, $operator, $expected ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Resolve a condition field into a runtime value.
	 *
	 * Supports:
	 * - direct args: 'order_id', 'arg_1'
	 * - property paths: 'order.total'
	 *
	 * @param string $field Field name or property path.
	 * @param array  $hook_meta Hook metadata (arg_names, properties).
	 * @param array  $args Runtime hook args.
	 * @return mixed|null
	 */
	private function resolve_condition_field_value( $field, $hook_meta, $args ) {
		$field = (string) $field;
		if ( '' === $field ) {
			return null;
		}

		$parts = explode( '.', $field, 2 );
		$base  = $parts[0];
		$prop  = isset( $parts[1] ) ? $parts[1] : null;

		$base_value = $this->resolve_arg_value( $base, $hook_meta, $args );
		if ( null === $prop ) {
			return $base_value;
		}

		if ( null === $base_value ) {
			return null;
		}

		// Array access.
		if ( is_array( $base_value ) ) {
			return array_key_exists( $prop, $base_value ) ? $base_value[ $prop ] : null;
		}

		// Object access (prefer method mapping from hook_meta.properties).
		if ( is_object( $base_value ) ) {
			$method = $this->resolve_property_method_from_meta( $base, $prop, $hook_meta );
			if ( $method && is_callable( array( $base_value, $method ) ) ) {
				try {
					return $base_value->{$method}();
				} catch ( \Throwable $e ) {
					return null;
				}
			}

			$fallback_method = 'get_' . $prop;
			if ( is_callable( array( $base_value, $fallback_method ) ) ) {
				try {
					return $base_value->{$fallback_method}();
				} catch ( \Throwable $e ) {
					return null;
				}
			}

			return isset( $base_value->{$prop} ) ? $base_value->{$prop} : null;
		}

		return null;
	}

	/**
	 * Replace {{field}} placeholders in notes with values from hook args.
	 *
	 * @param string $notes Raw notes text.
	 * @param array  $hook_meta Hook metadata (arg_names, properties).
	 * @param array  $args Hook arguments.
	 * @return string
	 */
	private function render_note_placeholders( $notes, $hook_meta, $args ) {
		if ( ! is_string( $notes ) || '' === trim( $notes ) ) {
			return '';
		}

		$callback = function ( $matches ) use ( $hook_meta, $args ) {
			$field = isset( $matches[1] ) ? trim( (string) $matches[1] ) : '';
			if ( '' === $field ) {
				return '';
			}
			$value = $this->resolve_condition_field_value( $field, $hook_meta, $args );
			if ( is_bool( $value ) ) {
				return $value ? 'true' : 'false';
			}
			if ( is_scalar( $value ) ) {
				return (string) $value;
			}
			if ( null === $value ) {
				return '';
			}
			return wp_json_encode( $value );
		};

		return preg_replace_callback( '/\{\{\s*([^}]+)\s*\}\}/', $callback, $notes );
	}

	/**
	 * Resolve an argument value from hook args by name.
	 *
	 * @param string $arg_name Argument name (e.g. 'order', 'order_id', 'arg_1').
	 * @param array  $hook_meta Hook metadata.
	 * @param array  $args Runtime hook args.
	 * @return mixed|null
	 */
	private function resolve_arg_value( $arg_name, $hook_meta, $args ) {
		$arg_name = (string) $arg_name;
		if ( '' === $arg_name ) {
			return null;
		}

		// Try named args from metadata.
		if ( isset( $hook_meta['arg_names'] ) && is_array( $hook_meta['arg_names'] ) ) {
			$idx = array_search( $arg_name, $hook_meta['arg_names'], true );
			if ( false !== $idx && array_key_exists( $idx, $args ) ) {
				return $args[ $idx ];
			}
		}

		// Fallback: arg_N.
		if ( preg_match( '/^arg_(\d+)$/i', $arg_name, $m ) ) {
			$idx = (int) $m[1] - 1;
			return array_key_exists( $idx, $args ) ? $args[ $idx ] : null;
		}

		return null;
	}

	/**
	 * Resolve method name for a property path using hook_meta.properties.
	 *
	 * @param string $arg_name Base arg name (e.g. 'order').
	 * @param string $prop Property name (e.g. 'total').
	 * @param array  $hook_meta Hook metadata.
	 * @return string|null
	 */
	private function resolve_property_method_from_meta( $arg_name, $prop, $hook_meta ) {
		if ( ! isset( $hook_meta['properties'] ) || ! is_array( $hook_meta['properties'] ) ) {
			return null;
		}
		if ( ! isset( $hook_meta['properties'][ $arg_name ] ) || ! is_array( $hook_meta['properties'][ $arg_name ] ) ) {
			return null;
		}

		foreach ( $hook_meta['properties'][ $arg_name ] as $def ) {
			if ( ! is_array( $def ) ) {
				continue;
			}
			if ( isset( $def['name'] ) && (string) $def['name'] === (string) $prop ) {
				return isset( $def['method'] ) && '' !== (string) $def['method'] ? (string) $def['method'] : null;
			}
		}

		return null;
	}

	/**
	 * Evaluate a single condition.
	 *
	 * @param mixed  $actual Actual runtime value.
	 * @param string $operator Operator.
	 * @param string $expected Expected value from settings.
	 * @return bool
	 */
	private function evaluate_condition( $actual, $operator, $expected ) {
		// If we can't resolve the field, treat as non-match.
		if ( null === $actual ) {
			return false;
		}

		$operator = (string) $operator;
		$expected = (string) $expected;

		// Numeric comparisons when possible.
		$actual_is_numeric   = is_int( $actual ) || is_float( $actual ) || ( is_string( $actual ) && is_numeric( $actual ) );
		$expected_is_numeric = is_numeric( $expected );
		if ( in_array( $operator, array( '>', '>=', '<', '<=' ), true ) && $actual_is_numeric && $expected_is_numeric ) {
			$a = (float) $actual;
			$b = (float) $expected;
			switch ( $operator ) {
				case '>':
					return $a > $b;
				case '>=':
					return $a >= $b;
				case '<':
					return $a < $b;
				case '<=':
					return $a <= $b;
			}
		}

		// Equality (numeric if both numeric).
		if ( in_array( $operator, array( '=', '!=' ), true ) ) {
			if ( $actual_is_numeric && $expected_is_numeric ) {
				$a = (float) $actual;
				$b = (float) $expected;
				return '=' === $operator ? ( $a === $b ) : ( $a !== $b );
			}

			$actual_str = is_scalar( $actual ) ? (string) $actual : wp_json_encode( $actual );
			return '=' === $operator ? ( $actual_str === $expected ) : ( $actual_str !== $expected );
		}

		// Contains / not_contains.
		if ( 'contains' === $operator || 'not_contains' === $operator ) {
			$found = false;
			if ( is_array( $actual ) ) {
				// Empty needle is always "contained".
				if ( '' === $expected ) {
					$found = true;
				} else {
					// If it's a simple scalar list, do strict in_array; otherwise fall back to JSON substring search.
					$all_scalars = true;
					foreach ( $actual as $v ) {
						if ( ! is_scalar( $v ) && null !== $v ) {
							$all_scalars = false;
							break;
						}
					}

					if ( $all_scalars ) {
						$found = in_array( $expected, array_map( 'strval', $actual ), true );
					} else {
						$actual_str = wp_json_encode( $actual );
						$found      = false !== stripos( $actual_str, $expected );
					}
				}
			} else {
				$actual_str = is_scalar( $actual ) ? (string) $actual : wp_json_encode( $actual );
				$found      = false !== stripos( $actual_str, $expected );
			}

			return 'contains' === $operator ? $found : ! $found;
		}

		// Unknown operator => fail closed.
		return false;
	}

	/**
	 * Send notification when a hook is triggered
	 *
	 * @param string $hook_name Hook name.
	 * @param string $description Hook description.
	 * @param array  $args Hook args.
	 * Note: hook arguments are intentionally not collected/transmitted to avoid
	 * accidental leakage of sensitive data.
	 */
	private function send_hook_notification( $hook_config, $hook_name, $description, $args = array() ) {
		$options = get_option( $this->option_name );
		$api_keys = $this->get_api_keys_from_options( $options );
		$api_key_labels_map = $this->get_api_key_labels_map_from_options( $options );

		if ( empty( $api_keys ) ) {
			return;
		}

		$scenario_name  = is_array( $hook_config ) && isset( $hook_config['scenario_name'] ) ? (string) $hook_config['scenario_name'] : '';
		$scenario_notes = is_array( $hook_config ) && isset( $hook_config['scenario_notes'] ) ? (string) $hook_config['scenario_notes'] : '';
		$severity       = is_array( $hook_config ) && isset( $hook_config['severity'] ) ? (string) $hook_config['severity'] : 'info';
		$hook_meta      = is_array( $hook_config ) && isset( $hook_config['hook_meta'] ) && is_array( $hook_config['hook_meta'] ) ? $hook_config['hook_meta'] : array();
		$send_push      = ! ( is_array( $hook_config ) && array_key_exists( 'send_push', $hook_config ) ) || (bool) $hook_config['send_push'];
		$send_mqtt      = ! ( is_array( $hook_config ) && array_key_exists( 'send_mqtt', $hook_config ) ) || (bool) $hook_config['send_mqtt'];

		$title = $scenario_name ? $scenario_name : ( $description ? $description : $hook_name );

		$site_url  = home_url();
		$site_name = function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'name' ) : '';

		$api_key_suffixes = array();
		$api_display_labels = array();
		foreach ( $api_keys as $api_key ) {
			if ( ! is_string( $api_key ) ) {
				continue;
			}
			$api_key = trim( $api_key );
			if ( '' === $api_key ) {
				continue;
			}
			$suffix = substr( $api_key, -6 );
			$api_key_suffixes[] = $suffix;

			$label = isset( $api_key_labels_map[ $api_key ] ) ? trim( (string) $api_key_labels_map[ $api_key ] ) : '';
			$api_display_labels[] = '' !== $label ? $label : '…' . $suffix;
		}
		$api_key_suffixes = array_values( array_unique( array_filter( $api_key_suffixes, 'strlen' ) ) );
		$api_display_labels = array_values( array_unique( array_filter( $api_display_labels, 'strlen' ) ) );

		if ( $this->should_throttle_hook_notification( $hook_config, $hook_name ) ) {
			$this->append_notification_log(
				array(
					'timestamp'     => gmdate( 'c' ),
					'hook_name'     => $hook_name,
					'scenario_name' => $scenario_name,
					'title'         => $title,
					'severity'      => $severity,
					'site_name'     => $site_name,
					'site_url'      => $site_url,
					'api_keys'      => $api_key_suffixes,
					'api_nicknames' => $api_display_labels,
					'sent'          => false,
					'status'        => 'throttled',
				)
			);
			return;
		}

		$wp_ver    = function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'version' ) : '';

		$rendered_notes = $this->render_note_placeholders( $scenario_notes, $hook_meta, $args );
		$message_body = $rendered_notes ? $rendered_notes : $title;

		$payload = array(
			'type'        => 'generic_notification',
			'title'       => $title,
			'body'        => $message_body,
			'severity'    => $severity,
			'source'      => 'wp_plugin',
			'pushPreview' => 'custom',
			'pushTitle'   => $title,
			'pushBody'    => $rendered_notes ? $rendered_notes : sprintf( '%s (%s)', $hook_name, $site_name ? $site_name : $site_url ),
			'sendPush'    => $send_push,
			'sendMqtt'    => $send_mqtt,
			'data'        => array(
				'hook_name'      => $hook_name,
				'scenario_name'  => $scenario_name,
				'scenario_notes' => $rendered_notes,
				'site_url'       => $site_url,
				'site_name'      => $site_name,
				'wp_version'     => $wp_ver,
				'plugin_version' => defined( 'NOTIFICATOR_COMPANION_VERSION' ) ? NOTIFICATOR_COMPANION_VERSION : '',
				'timestamp'      => gmdate( 'c' ),
			),
		);

		if ( ! $send_push && ! $send_mqtt ) {
			$this->append_notification_log(
				array(
					'timestamp'     => gmdate( 'c' ),
					'hook_name'     => $hook_name,
					'scenario_name' => $scenario_name,
					'title'         => $title,
					'severity'      => $severity,
					'site_name'     => $site_name,
					'site_url'      => $site_url,
					'api_keys'      => $api_key_suffixes,
					'api_nicknames' => $api_display_labels,
					'sent'          => false,
					'status'        => 'delivery_disabled',
				)
			);
			return;
		}

		$this->append_notification_log(
			array(
				'timestamp'     => gmdate( 'c' ),
				'hook_name'     => $hook_name,
				'scenario_name' => $scenario_name,
				'title'         => $title,
				'severity'      => $severity,
				'site_name'     => $site_name,
				'site_url'      => $site_url,
				'api_keys'      => $api_key_suffixes,
				'api_nicknames' => $api_display_labels,
				'sent'          => true,
				'status'        => 'sent',
			)
		);

		$this->queue_admin_toast(
			array(
				'title'        => $title,
				'hook'         => $hook_name,
				'scenario'     => $scenario_name,
				'notes'        => $rendered_notes,
				'severity'     => $severity,
				'api_keys'     => $api_key_suffixes,
				'status'       => 'sent',
				'timestamp'    => gmdate( 'c' ),
				'settings_url' => admin_url( 'admin.php?page=notificator-companion#notificator-log' ),
			)
		);

		$body = wp_json_encode( $payload );

		// Send async request(s) (don't wait for response).
		foreach ( $api_keys as $api_key ) {
			wp_remote_post(
				notificator_companion_get_api_endpoint(),
				array(
					'timeout'  => 1,
					'blocking' => false,
					'data_format' => 'body',
					'headers'  => $this->build_api_request_headers( $api_key, $body ),
					'body'     => $body,
				)
			);
		}
	}

	/**
	 * Queue an admin toast for the next admin page load.
	 *
	 * @param array $payload Notification metadata.
	 * @return void
	 */
	private function queue_admin_toast( $payload ) {
		if ( ! is_array( $payload ) ) {
			return;
		}

		$options = get_option( $this->option_name );
		if ( ! is_array( $options ) ) {
			$options = array();
		}
		$admin_toasts_enabled = ! isset( $options['admin_toasts_enabled'] ) || (bool) $options['admin_toasts_enabled'];
		if ( ! $admin_toasts_enabled ) {
			return;
		}

		$title    = isset( $payload['title'] ) ? sanitize_text_field( $payload['title'] ) : '';
		$hook     = isset( $payload['hook'] ) ? sanitize_text_field( $payload['hook'] ) : '';
		$scenario = isset( $payload['scenario'] ) ? sanitize_text_field( $payload['scenario'] ) : '';
		$notes    = isset( $payload['notes'] ) ? sanitize_text_field( $payload['notes'] ) : '';
		$severity = isset( $payload['severity'] ) ? sanitize_text_field( $payload['severity'] ) : 'info';
		$status   = isset( $payload['status'] ) ? sanitize_text_field( $payload['status'] ) : '';
		$timestamp = isset( $payload['timestamp'] ) ? sanitize_text_field( $payload['timestamp'] ) : '';
		$settings_url = isset( $payload['settings_url'] ) ? esc_url_raw( $payload['settings_url'] ) : '';
		$api_keys = isset( $payload['api_keys'] ) && is_array( $payload['api_keys'] ) ? $payload['api_keys'] : array();
		$api_keys = array_filter( array_map( 'sanitize_text_field', $api_keys ), 'strlen' );

		if ( '' === $title && '' === $hook ) {
			return;
		}

		$type = 'info';
		if ( 'critical' === $severity ) {
			$type = 'error';
		} elseif ( 'warning' === $severity ) {
			$type = 'warn';
		}

		$title_text = $title ? $title : __( 'Notification sent', 'notificator-companion' );
		$message = $title_text;

		$toasts = get_option( 'notificator_companion_admin_toasts', array() );
		if ( ! is_array( $toasts ) ) {
			$toasts = array();
		}

		$seq = (int) get_option( 'notificator_companion_admin_toast_seq', 0 );
		$seq++;
		update_option( 'notificator_companion_admin_toast_seq', $seq, false );

		$toasts[] = array(
			'id'       => function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'toast_', true ),
			'seq'      => $seq,
			'message'  => $message,
			'title'    => $title_text,
			'notes'    => $notes,
			'type'     => $type,
			'severity' => $severity,
			'url'      => $settings_url,
			'time'     => time(),
		);

		$max_age = 120;
		$now = time();
		$toasts = array_values(
			array_filter(
				$toasts,
				function ( $toast ) use ( $now, $max_age ) {
					$time = isset( $toast['time'] ) ? (int) $toast['time'] : 0;
					return $time > 0 && ( $now - $time ) <= $max_age;
				}
			)
		);
		$toasts = array_slice( $toasts, -20 );
		update_option( 'notificator_companion_admin_toasts', $toasts, false );
	}

	/**
	 * Return unseen admin toasts for the current user and mark them as seen.
	 *
	 * @param array  $toasts Stored toasts.
	 * @param string $settings_url Default settings URL.
	 * @return array
	 */
	private function get_unseen_admin_toasts( $toasts, $settings_url ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return array();
		}

		$last_seen = (int) get_user_meta( $user_id, 'notificator_companion_last_toast_seq', true );
		$clean = array();
		$max_seq = $last_seen;
		$now = time();
		$max_age = 120;
		foreach ( $toasts as $toast ) {
			if ( ! is_array( $toast ) ) {
				continue;
			}
			$seq = isset( $toast['seq'] ) ? (int) $toast['seq'] : 0;
			$time = isset( $toast['time'] ) ? (int) $toast['time'] : 0;
			if ( $seq > $max_seq ) {
				$max_seq = $seq;
			}
			if ( $seq <= $last_seen ) {
				continue;
			}
			if ( $time <= 0 || ( $now - $time ) > $max_age ) {
				continue;
			}
			$message = isset( $toast['message'] ) ? sanitize_text_field( $toast['message'] ) : '';
			$title   = isset( $toast['title'] ) ? sanitize_text_field( $toast['title'] ) : '';
			$notes   = isset( $toast['notes'] ) ? sanitize_text_field( $toast['notes'] ) : '';
			$type    = isset( $toast['type'] ) ? sanitize_text_field( $toast['type'] ) : 'info';
			$url     = isset( $toast['url'] ) ? esc_url_raw( $toast['url'] ) : '';
			if ( '' === $url && $settings_url ) {
				$url = $settings_url;
			}
			if ( '' === $message && '' !== $title ) {
				$message = $title;
			}
			if ( '' === $message && '' === $title && '' === $notes ) {
				continue;
			}
			$clean[] = array(
				'seq'     => $seq,
				'message' => $message,
				'title'   => $title,
				'notes'   => $notes,
				'type'    => in_array( $type, array( 'info', 'success', 'warn', 'error' ), true ) ? $type : 'info',
				'url'     => $url,
				'time'    => $time,
			);
		}

		if ( $max_seq > $last_seen ) {
			update_user_meta( $user_id, 'notificator_companion_last_toast_seq', $max_seq );
		}

		return $clean;
	}

	/**
	 * Return recent admin toasts without marking them as seen.
	 *
	 * @param array  $toasts Stored toasts.
	 * @param string $settings_url Default settings URL.
	 * @return array
	 */
	private function get_recent_admin_toasts( $toasts, $settings_url ) {
		$clean = array();
		$now = time();
		$max_age = 120;
		foreach ( $toasts as $toast ) {
			if ( ! is_array( $toast ) ) {
				continue;
			}
			$seq = isset( $toast['seq'] ) ? (int) $toast['seq'] : 0;
			$time = isset( $toast['time'] ) ? (int) $toast['time'] : 0;
			if ( $seq <= 0 || $time <= 0 || ( $now - $time ) > $max_age ) {
				continue;
			}
			$message = isset( $toast['message'] ) ? sanitize_text_field( $toast['message'] ) : '';
			$title   = isset( $toast['title'] ) ? sanitize_text_field( $toast['title'] ) : '';
			$notes   = isset( $toast['notes'] ) ? sanitize_text_field( $toast['notes'] ) : '';
			$type    = isset( $toast['type'] ) ? sanitize_text_field( $toast['type'] ) : 'info';
			$url     = isset( $toast['url'] ) ? esc_url_raw( $toast['url'] ) : '';
			if ( '' === $url && $settings_url ) {
				$url = $settings_url;
			}
			if ( '' === $message && '' !== $title ) {
				$message = $title;
			}
			if ( '' === $message && '' === $title && '' === $notes ) {
				continue;
			}
			$clean[] = array(
				'seq'     => $seq,
				'message' => $message,
				'title'   => $title,
				'notes'   => $notes,
				'type'    => in_array( $type, array( 'info', 'success', 'warn', 'error' ), true ) ? $type : 'info',
				'url'     => $url,
				'time'    => $time,
			);
		}

		return $clean;
	}

	/**
	 * AJAX: Fetch and clear queued admin toasts.
	 */
	public function handle_fetch_admin_toasts() {
		check_ajax_referer( 'notificator_companion_fetch_admin_toasts', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'notificator-companion' ) ), 403 );
			return;
		}

		$options = get_option( $this->option_name );
		if ( ! is_array( $options ) ) {
			$options = array();
		}
		$admin_toasts_enabled = ! isset( $options['admin_toasts_enabled'] ) || (bool) $options['admin_toasts_enabled'];
		if ( ! $admin_toasts_enabled ) {
			wp_send_json_success( array( 'toasts' => array() ) );
			return;
		}
		$toast_delivery_mode = isset( $options['toast_delivery_mode'] ) ? (string) $options['toast_delivery_mode'] : 'account';
		if ( ! in_array( $toast_delivery_mode, array( 'account', 'tab' ), true ) ) {
			$toast_delivery_mode = 'account';
		}

		$toasts = get_option( 'notificator_companion_admin_toasts', array() );
		if ( ! is_array( $toasts ) || empty( $toasts ) ) {
			wp_send_json_success( array( 'toasts' => array() ) );
			return;
		}

		$settings_url = admin_url( 'admin.php?page=notificator-companion#notificator-log' );
		$clean = ( 'tab' === $toast_delivery_mode )
			? $this->get_recent_admin_toasts( $toasts, $settings_url )
			: $this->get_unseen_admin_toasts( $toasts, $settings_url );

		wp_send_json_success( array( 'toasts' => $clean ) );
	}

	/**
	 * Append a notification entry to the local log.
	 *
	 * @param array $entry Log entry data.
	 * @return void
	 */
	private function append_notification_log( $entry ) {
		if ( ! is_array( $entry ) ) {
			return;
		}

		$options = get_option( $this->option_name );
		if ( is_array( $options ) && isset( $options['log_enabled'] ) && ! $options['log_enabled'] ) {
			return;
		}

		$log = get_option( 'notificator_companion_notification_log', array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}

		$log[] = array(
			'id'            => isset( $entry['id'] ) ? (string) $entry['id'] : ( function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'log_', true ) ),
			'timestamp'     => isset( $entry['timestamp'] ) ? (string) $entry['timestamp'] : gmdate( 'c' ),
			'hook_name'     => isset( $entry['hook_name'] ) ? (string) $entry['hook_name'] : '',
			'scenario_name' => isset( $entry['scenario_name'] ) ? (string) $entry['scenario_name'] : '',
			'title'         => isset( $entry['title'] ) ? (string) $entry['title'] : '',
			'severity'      => isset( $entry['severity'] ) ? (string) $entry['severity'] : 'info',
			'site_name'     => isset( $entry['site_name'] ) ? (string) $entry['site_name'] : '',
			'site_url'      => isset( $entry['site_url'] ) ? (string) $entry['site_url'] : '',
			'api_keys'      => isset( $entry['api_keys'] ) && is_array( $entry['api_keys'] ) ? array_values( $entry['api_keys'] ) : array(),
			'api_nicknames' => isset( $entry['api_nicknames'] ) && is_array( $entry['api_nicknames'] ) ? array_values( $entry['api_nicknames'] ) : array(),
			'sent'          => isset( $entry['sent'] ) ? (bool) $entry['sent'] : true,
			'status'        => isset( $entry['status'] ) ? (string) $entry['status'] : 'sent',
		);

		$max_entries = (int) apply_filters( 'notificator_companion_notification_log_max_entries', 200 );
		if ( $max_entries > 0 && count( $log ) > $max_entries ) {
			$log = array_slice( $log, -1 * $max_entries );
		}

		update_option( 'notificator_companion_notification_log', $log, false );
	}

	/**
	 * Determine if a hook notification should be throttled.
	 *
	 * @param array  $hook_config Hook configuration.
	 * @param string $hook_name Hook name.
	 * @return bool True if throttled, false otherwise.
	 */
	private function should_throttle_hook_notification( $hook_config, $hook_name ) {
		$seconds = $this->get_hook_rate_limit_seconds( $hook_name, $hook_config );
		if ( $seconds <= 0 ) {
			return false;
		}

		$key = $this->get_hook_rate_limit_key( $hook_name, $hook_config );
		if ( get_transient( $key ) ) {
			return true;
		}

		set_transient( $key, time(), $seconds );
		return false;
	}

	/**
	 * Get rate limit window for hook notifications.
	 *
	 * @param string $hook_name Hook name.
	 * @param array  $hook_config Hook configuration.
	 * @return int Seconds to debounce.
	 */
	private function get_hook_rate_limit_seconds( $hook_name, $hook_config ) {
		$options = get_option( $this->option_name );
		$seconds = isset( $options['throttle_seconds'] ) ? (int) $options['throttle_seconds'] : 30;
		if ( $seconds < 0 ) {
			$seconds = 0;
		} elseif ( $seconds > 3600 ) {
			$seconds = 3600;
		}
		$seconds = (int) apply_filters( 'notificator_companion_hook_rate_limit_seconds', $seconds, $hook_name, $hook_config );
		return max( 0, $seconds );
	}

	/**
	 * Get per-plugin hook scan limit from settings.
	 *
	 * @return int
	 */
	private function get_scan_hook_limit() {
		$options = get_option( $this->option_name );
		$limit = is_array( $options ) && isset( $options['scan_hook_limit'] ) ? (int) $options['scan_hook_limit'] : 500;
		if ( $limit < 50 ) {
			$limit = 50;
		} elseif ( $limit > 5000 ) {
			$limit = 5000;
		}

		return (int) apply_filters( 'notificator_companion_scan_hook_limit', $limit );
	}

	/**
	 * Get transient key used to lock concurrent scans.
	 *
	 * @return string
	 */
	private function get_scan_lock_key() {
		return 'notificator_companion_scan_lock';
	}

	/**
	 * Try to acquire scan lock.
	 *
	 * @param int $ttl Lock timeout seconds.
	 * @return bool
	 */
	private function acquire_scan_lock( $ttl = 180 ) {
		$key = $this->get_scan_lock_key();
		if ( get_transient( $key ) ) {
			return false;
		}

		set_transient( $key, (string) time(), max( 30, (int) $ttl ) );
		return true;
	}

	/**
	 * Release scan lock.
	 *
	 * @return void
	 */
	private function release_scan_lock() {
		delete_transient( $this->get_scan_lock_key() );
	}

	/**
	 * Schedule a background scan via WP-Cron.
	 *
	 * @param bool $include_inactive Whether to include inactive plugins.
	 * @return bool
	 */
	private function queue_background_scan( $include_inactive = false ) {
		$args = array( (bool) $include_inactive );
		if ( wp_next_scheduled( 'notificator_companion_run_background_scan', $args ) ) {
			return false;
		}

		return (bool) wp_schedule_single_event( time() + 5, 'notificator_companion_run_background_scan', $args );
	}

	/**
	 * WP-Cron background scan callback.
	 *
	 * @param bool $include_inactive Whether to include inactive plugins.
	 * @return void
	 */
	public function handle_background_scan_event( $include_inactive = false ) {
		if ( ! $this->acquire_scan_lock( 300 ) ) {
			return;
		}

		$this->plugin_scanner->scan_plugins_for_hooks( (bool) $include_inactive, $this->get_scan_hook_limit() );
		$this->release_scan_lock();
	}

	/**
	 * Build a unique transient key for hook notifications.
	 *
	 * @param string $hook_name Hook name.
	 * @param array  $hook_config Hook configuration.
	 * @return string Transient key.
	 */
	private function get_hook_rate_limit_key( $hook_name, $hook_config ) {
		$scenario_name = is_array( $hook_config ) && isset( $hook_config['scenario_name'] )
			? (string) $hook_config['scenario_name']
			: '';
		$payload = $hook_name . '|' . $scenario_name;
		return 'notificator_hook_rl_' . md5( $payload );
	}

	/**
	 * Build request headers for Netlify function calls.
	 *
	 * Adds HMAC signing headers with nonce-based replay protection.
	 *
	 * @param string $api_key API key.
	 * @param string $body Request body (string) used for signing.
	 * @return array
	 */
	private function build_api_request_headers( $api_key, $body ) {
		$api_key = trim( (string) $api_key );
		$body = (string) $body;
		$timestamp = (string) (int) round( microtime( true ) * 1000 );
		$nonce = wp_generate_uuid4();
		$message = $timestamp . '.' . $nonce . '.' . $body;
		$signature = hash_hmac( 'sha256', $message, $api_key );

		// Netlify validates allowed domains based on Origin/Referer headers.
		$origin = home_url();
		if ( is_string( $origin ) ) {
			$origin = rtrim( $origin, '/' );
		}

		return array(
			'Authorization' => 'Bearer ' . $api_key,
			'Content-Type'  => 'application/json',
			'Origin'        => $origin,
			'Referer'       => $origin . '/',
			'X-Timestamp'   => $timestamp,
			'X-Nonce'       => $nonce,
			'X-Signature'   => $signature,
		);
	}

	/**
	 * Handle AJAX request to scan plugins for hooks
	 */
	public function handle_refresh_hooks() {
		check_ajax_referer( 'notificator_companion_refresh_hooks', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized', 'notificator-companion' ) );
			return;
		}

		$include_inactive = ! empty( $_POST['include_inactive'] );
		$background = ! empty( $_POST['background'] );

		if ( $background ) {
			$queued = $this->queue_background_scan( $include_inactive );
			wp_send_json_success(
				array(
					'message' => $queued
						? __( 'Scan queued in background.', 'notificator-companion' )
						: __( 'Background scan is already queued.', 'notificator-companion' ),
				)
			);
			return;
		}

		if ( ! $this->acquire_scan_lock( 300 ) ) {
			$this->queue_background_scan( $include_inactive );
			wp_send_json_error(
				array(
					'message' => __( 'A scan is already in progress. Please wait a moment.', 'notificator-companion' ),
				),
				429
			);
			return;
		}

		// Scan plugins.
		$result = $this->plugin_scanner->scan_plugins_for_hooks( $include_inactive, $this->get_scan_hook_limit() );

		if ( $result['success'] ) {
			$available_plugins = $this->get_available_plugins_with_hooks();

			// Build active status for plugins.
			$plugin_active_status = array();
			foreach ( $available_plugins as $key => $plugin ) {
				$plugin_active_status[ $key ] = isset( $plugin['file'] ) ? $this->is_plugin_active_check( $plugin['file'] ) : true;
			}

			// Build active status for configured hooks.
			$options = get_option( $this->option_name );
			$hooks   = isset( $options['hooks'] ) && is_array( $options['hooks'] ) ? array_values( $options['hooks'] ) : array();
			$hook_active_status = array();
			foreach ( $hooks as $index => $hook ) {
				$hook_name = is_array( $hook ) && isset( $hook['hook_name'] ) ? (string) $hook['hook_name'] : '';
				$hook_active_status[ $index ] = $hook_name ? $this->is_hook_active( $hook_name ) : false;
			}

			$this->release_scan_lock();
			wp_send_json_success(
				array(
					'message'        => sprintf(
						/* translators: %1$d is plugins count, %2$d is hooks count */
						__( 'Scanned %1$d plugins and found %2$d hooks', 'notificator-companion' ),
						$result['plugins_found'],
						$result['hooks_found']
					),
					'plugins_scanned' => $result['plugins_found'],
					'hooks_found'    => $result['hooks_found'],
					'available_plugins' => $available_plugins,
					'plugin_active_status' => $plugin_active_status,
					'hook_active_status' => $hook_active_status,
				)
			);
		} else {
			$this->release_scan_lock();
			wp_send_json_error( __( 'Failed to scan plugins', 'notificator-companion' ) );
		}
	}

	/**
	 * Handle test notification AJAX request
	 */
	public function handle_test_notification() {
		check_ajax_referer( 'notificator_companion_test', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'notificator-companion' ) ) );
		}

		$requested_key = isset( $_POST['api_key'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) ) : '';
		if ( '' !== $requested_key ) {
			// Test only the key requested by the clicked row button.
			$api_keys = array( $requested_key );
		} else {
			$options  = get_option( $this->option_name );
			$api_keys = $this->get_api_keys_from_options( $options );
		}

		if ( empty( $api_keys ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please configure your API key first.', 'notificator-companion' ),
				)
			);
		}

		$site_url  = home_url();
		$site_name = function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'name' ) : '';
		$wp_ver    = function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'version' ) : '';

		$payload = array(
			'type'        => 'generic_notification',
			'title'       => 'WordPress Test',
			'body'        => sprintf( "Test notification from %s\n\nURL: %s", $site_name ? $site_name : 'your WordPress site', $site_url ),
			'severity'    => 'info',
			'source'      => 'wp_plugin',
			'pushPreview' => 'custom',
			'pushTitle'   => 'WordPress Test',
			'pushBody'    => $site_name ? $site_name : (string) $site_url,
			'data'        => array(
				'site_url'       => $site_url,
				'site_name'      => $site_name,
				'wp_version'     => $wp_ver,
				'plugin_version' => defined( 'NOTIFICATOR_COMPANION_VERSION' ) ? NOTIFICATOR_COMPANION_VERSION : '',
				'timestamp'      => gmdate( 'c' ),
			),
		);

		$body = wp_json_encode( $payload );

		$total     = count( $api_keys );
		$sent      = 0;
		$last_body = null;
		$last_code = null;

		foreach ( $api_keys as $api_key ) {
			$response = wp_remote_post(
				notificator_companion_get_api_endpoint(),
				array(
					'timeout' => 30,
					'data_format' => 'body',
					'headers' => array(
						// Keep headers explicit here to avoid breaking older sites if helper is removed.
						// build_api_request_headers() also adds signature headers + Origin/Referer.
					) + $this->build_api_request_headers( $api_key, $body ),
					'body'    => $body,
				)
			);

			if ( is_wp_error( $response ) ) {
				$last_body = array( 'error' => $response->get_error_message() );
				$last_code = 0;
				continue;
			}

			$status_code = wp_remote_retrieve_response_code( $response );
			$resp_body   = json_decode( wp_remote_retrieve_body( $response ), true );
			$last_body   = $resp_body;
			$last_code   = $status_code;

			if ( 200 === $status_code || 201 === $status_code ) {
				$sent++;
			}
		}

		if ( $sent > 0 ) {
			$message = ( 1 === $total )
				? __( 'Test notification sent successfully! Check your mobile app for the alert.', 'notificator-companion' )
				: sprintf(
					/* translators: %1$d is number sent, %2$d is total API keys */
					__( 'Test notification sent to %1$d of %2$d configured API keys.', 'notificator-companion' ),
					$sent,
					$total
				);

			wp_send_json_success(
				array(
					'message'  => $message,
					'response' => $last_body,
				)
			);
		}

		wp_send_json_error(
			array(
				'message'  => sprintf(
					/* translators: %d is the HTTP status code */
					__( 'Failed to send notification. Status: %d. Please check your API key.', 'notificator-companion' ),
					(int) $last_code
				),
				'response' => $last_body,
			)
		);
	}

	/**
	 * Add settings link on plugins page
	 *
	 * @param array $links Plugin action links.
	 * @return array Modified links.
	 */
	public function add_settings_link( $links ) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			admin_url( 'admin.php?page=notificator-companion' ),
			__( 'Settings', 'notificator-companion' )
		);
		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * Get available plugins with their discovered hooks
	 *
	 * @return array Array of plugins with their hooks.
	 */
	public function get_available_plugins_with_hooks() {
		return $this->plugin_scanner->get_available_plugins_with_hooks();
	}

	/**
	 * Check if a plugin is active
	 *
	 * @param string $plugin_file Plugin file path.
	 * @return bool True if active, false otherwise.
	 */
	public function is_plugin_active_check( $plugin_file ) {
		if ( empty( $plugin_file ) ) {
			return true; // WordPress Core.
		}

		return is_plugin_active( $plugin_file );
	}

	/**
	 * Check if a hook is currently registered in WordPress
	 *
	 * @param string $hook_name Hook name.
	 * @return bool True if active, false otherwise.
	 */
	public function is_hook_active( $hook_name ) {
		global $wp_filter;
		return isset( $wp_filter[ $hook_name ] ) && ! empty( $wp_filter[ $hook_name ] );
	}

	/**
	 * Send monitoring check
	 * This can be called via cron or manually
	 *
	 * @return bool True if successful, false otherwise.
	 */
	public function send_monitoring_check() {
		$options  = get_option( $this->option_name );
		$api_keys = $this->get_api_keys_from_options( $options );
		$monitors = isset( $options['monitors'] ) ? $options['monitors'] : array();

		if ( empty( $api_keys ) || empty( $monitors ) ) {
			return false;
		}

		// Filter only enabled monitors.
		$enabled_monitors = array_filter(
			$monitors,
			function ( $monitor ) {
				return isset( $monitor['enabled'] ) && $monitor['enabled'];
			}
		);

		if ( empty( $enabled_monitors ) ) {
			return false;
		}

		$body = wp_json_encode( array( 'monitors' => array_values( $enabled_monitors ) ) );
		$any_ok = false;

		foreach ( $api_keys as $api_key ) {
			$response = wp_remote_post(
				notificator_companion_get_api_endpoint(),
				array(
					'timeout' => 30,
					'data_format' => 'body',
					'headers' => $this->build_api_request_headers( $api_key, $body ),
					'body'    => $body,
				)
			);

			if ( is_wp_error( $response ) ) {
				continue;
			}

			$status_code = (int) wp_remote_retrieve_response_code( $response );
			if ( 200 === $status_code || 201 === $status_code ) {
				$any_ok = true;
			}
		}

		return $any_ok;
	}
}

/**
 * Initialize plugin
 *
 * @return Notificator_Companion
 */
function notificator_companion_init() {
	return Notificator_Companion::get_instance();
}
add_action( 'plugins_loaded', 'notificator_companion_init' );

/**
 * Activation hook
 */
register_activation_hook(
	__FILE__,
	function () {
		// Set default options on activation.
		$default_options = array(
			'api_key'  => '',
			'api_keys' => array(),
			'api_key_nicknames' => array(),
			'monitors' => array(),
			'hooks'    => array(),
			'log_per_page' => 20,
			'throttle_seconds' => 30,
			'scan_hook_limit' => 500,
			'log_enabled' => true,
		);

		// Migrate older option keys if present.
		$legacy_settings = get_option( 'authenticator_companion_settings', null );
		if ( null !== $legacy_settings && ! get_option( 'notificator_companion_settings', null ) ) {
			add_option( 'notificator_companion_settings', $legacy_settings, '', false );
		}

		if ( ! get_option( 'notificator_companion_settings' ) ) {
			add_option( 'notificator_companion_settings', $default_options, '', false );
		}
	}
);

/**
 * Deactivation hook
 */
register_deactivation_hook(
	__FILE__,
	function () {
		delete_transient( 'notificator_companion_scan_lock' );
		wp_clear_scheduled_hook( 'notificator_companion_run_background_scan', array( true ) );
		wp_clear_scheduled_hook( 'notificator_companion_run_background_scan', array( false ) );
	}
);
