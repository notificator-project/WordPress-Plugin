<?php
/**
 * Admin Page Class
 *
 * Handles the admin settings page rendering and AJAX handlers
 *
 * @package NotificatorCompanion
 * @since 1.1.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Notificator_Companion_Admin_Page
 *
 * Manages the plugin's admin interface
 */
class Notificator_Companion_Admin_Page {


	/**
	 * Option name
	 *
	 * @var string
	 */
	private $option_name = 'notificator_companion_settings';

	/**
	 * Parent plugin instance
	 *
	 * @var object
	 */
	private $plugin;

	/**
	 * Constructor
	 *
	 * @param object $plugin Parent plugin instance.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Enqueue admin assets
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 0 !== strpos( $page, 'notificator' ) && 'settings_page_notificator' !== $hook && 'toplevel_page_notificator' !== $hook ) {
			return;
		}

		// Ensure jQuery is available.
		wp_enqueue_script( 'jquery' );

		// Ensure Dashicons are available (some installs dequeue them).
		wp_enqueue_style( 'dashicons' );

		$dist_css_path = NOTIFICATOR_COMPANION_PLUGIN_DIR . 'assets/dist/admin.css';
		$dist_js_path  = NOTIFICATOR_COMPANION_PLUGIN_DIR . 'assets/dist/admin.js';
		$has_dist      = file_exists( $dist_css_path ) && file_exists( $dist_js_path );

		if ( ! $has_dist ) {
			return;
		}
		wp_enqueue_style(
			'notificator-companion-admin-bundle',
			NOTIFICATOR_COMPANION_PLUGIN_URL . 'assets/dist/admin.css',
			array(),
			(string) filemtime( $dist_css_path )
		);

		wp_enqueue_script(
			'notificator-companion-admin-bundle',
			NOTIFICATOR_COMPANION_PLUGIN_URL . 'assets/dist/admin.js',
			array( 'jquery' ),
			(string) filemtime( $dist_js_path ),
			true
		);

		$options = get_option( $this->option_name );
		if ( ! is_array( $options ) ) {
			$options = array();
		}

		// Pass PHP data to JavaScript.
		wp_localize_script(
			'notificator-companion-admin-bundle',
			'notificatorCompanionData',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'toastsEnabled' => ! isset( $options['admin_toasts_enabled'] ) || (bool) $options['admin_toasts_enabled'],
				'toastSettings' => array(
					'duration'     => isset( $options['toast_duration'] ) ? (int) $options['toast_duration'] : 3,
					'positionX'    => isset( $options['toast_position_x'] ) ? (string) $options['toast_position_x'] : 'right',
					'positionY'    => isset( $options['toast_position_y'] ) ? (string) $options['toast_position_y'] : 'top',
					'deliveryMode' => isset( $options['toast_delivery_mode'] ) ? (string) $options['toast_delivery_mode'] : 'account',
					'dismissMode'  => isset( $options['toast_dismiss_mode'] ) ? (string) $options['toast_dismiss_mode'] : 'auto',
				),
				'health'        => $this->plugin->get_admin_health(),
				'nonces'        => array(
					'scan'              => wp_create_nonce( 'notificator_companion_refresh_hooks' ),
					'health'            => wp_create_nonce( 'notificator_companion_get_health' ),
					'test'              => wp_create_nonce( 'notificator_companion_test' ),
					'testMqtt'          => wp_create_nonce( 'notificator_companion_test_mqtt' ),
					'saveSettings'      => wp_create_nonce( 'notificator_companion_save_settings' ),
					'exportHooks'       => wp_create_nonce( 'notificator_companion_export_hooks' ),
					'importHooks'       => wp_create_nonce( 'notificator_companion_import_hooks' ),
					'toggleLog'         => wp_create_nonce( 'notificator_companion_toggle_log' ),
					'exportLog'         => wp_create_nonce( 'notificator_companion_export_log' ),
					'clearLog'          => wp_create_nonce( 'notificator_companion_clear_log' ),
					'deleteLog'         => wp_create_nonce( 'notificator_companion_delete_log_entry' ),
					'toggleAdminToasts' => wp_create_nonce( 'notificator_companion_toggle_admin_toasts' ),
					'discovery'         => wp_create_nonce( 'notificator_companion_discovery' ),
					'observation'       => wp_create_nonce( 'notificator_companion_observation' ),
					'resetTestData'     => wp_create_nonce( 'notificator_companion_reset_test_data' ),
				),
				'actions'       => array(
					'scan'              => 'notificator_companion_refresh_hooks',
					'health'            => 'notificator_companion_get_health',
					'test'              => 'notificator_companion_test',
					'testMqtt'          => 'notificator_companion_test_mqtt',
					'saveSettings'      => 'notificator_companion_save_settings',
					'exportHooks'       => 'notificator_companion_export_hooks',
					'importHooks'       => 'notificator_companion_import_hooks',
					'toggleLog'         => 'notificator_companion_toggle_log',
					'exportLog'         => 'notificator_companion_export_log',
					'clearLog'          => 'notificator_companion_clear_log',
					'deleteLog'         => 'notificator_companion_delete_log_entry',
					'toggleAdminToasts' => 'notificator_companion_toggle_admin_toasts',
					'discoveryIgnore'   => 'notificator_companion_discovery_ignore',
					'discoveryRefresh'  => 'notificator_companion_get_discovery_inbox',
					'observationStart'  => 'notificator_companion_observation_start',
					'observationStop'   => 'notificator_companion_observation_stop',
					'resetTestData'     => 'notificator_companion_reset_test_data',
				),
			)
		);

		// Pass list of active plugins for template filtering.
		wp_localize_script(
			'notificator-companion-admin-bundle',
			'notificatorActivePlugins',
			$this->get_active_plugin_identifiers()
		);

		/**
		 * Let third-party plugins/themes register templates.
		 *
		 * Use the action to call notificator_companion_register_template().
		 */
		do_action( 'notificator_companion_register_templates' );

		$registered_templates = function_exists( 'notificator_companion_get_registered_templates' )
			? notificator_companion_get_registered_templates()
			: array();
		$registered_templates = apply_filters( 'notificator_companion_templates', $registered_templates );
		$registered_templates = array_values( array_filter( $registered_templates, 'is_array' ) );
		wp_localize_script(
			'notificator-companion-admin-bundle',
			'notificatorTemplateLibrary',
			array(
				'templates'                => $registered_templates,
				'woocommerceOrderStatuses' => $this->get_woocommerce_order_status_options(),
			)
		);
	}

	/**
	 * Get WooCommerce order statuses formatted for dropdowns.
	 *
	 * @return array<int, array{value:string,label:string}>
	 */
	private function get_woocommerce_order_status_options() {
		if ( ! function_exists( 'wc_get_order_statuses' ) ) {
			return array();
		}

		$statuses = call_user_func( 'wc_get_order_statuses' );
		$options  = array();

		foreach ( $statuses as $key => $label ) {
			// wc_get_order_statuses returns keys like 'wc-completed'.
			$slug = is_string( $key ) ? preg_replace( '/^wc-/', '', $key ) : '';
			if ( empty( $slug ) ) {
				continue;
			}
			$options[] = array(
				'value' => $slug,
				'label' => is_string( $label ) ? $label : $slug,
			);
		}

		return $options;
	}

	/**
	 * Get list of active plugin identifiers for template filtering.
	 *
	 * @return array<int, string>
	 */
	private function get_active_plugin_identifiers() {
		$active_plugins = array( 'wordpress-core' );

		// Check for WooCommerce.
		if ( class_exists( 'WooCommerce' ) ) {
			$active_plugins[] = 'woocommerce';
		}

		// Check for WooCommerce Subscriptions.
		if ( class_exists( 'WC_Subscriptions' ) ) {
			$active_plugins[] = 'woocommerce-subscriptions';
		}

		// Check for Contact Form 7.
		if ( function_exists( 'wpcf7' ) ) {
			$active_plugins[] = 'contact-form-7';
		}

		// Check for Gravity Forms.
		if ( class_exists( 'GFForms' ) ) {
			$active_plugins[] = 'gravityforms';
		}

		// Check for WPForms Lite or Pro.
		if ( function_exists( 'wpforms' ) || defined( 'WPFORMS_VERSION' ) ) {
			$active_plugins[] = 'wpforms-lite';
		}

		// Check for Fluent Forms.
		if ( defined( 'FLUENTFORM' ) || function_exists( 'wpFluent' ) || class_exists( '\\FluentForm\\App\\App' ) ) {
			$active_plugins[] = 'fluentform';
		}

		// Check for Ninja Forms.
		if ( function_exists( 'Ninja_Forms' ) || class_exists( 'Ninja_Forms' ) ) {
			$active_plugins[] = 'ninja-forms';
		}

		// Check for Paid Memberships Pro.
		if ( function_exists( 'pmpro_init' ) ) {
			$active_plugins[] = 'paid-memberships-pro';
		}

		// Check for Yoast SEO.
		if ( defined( 'WPSEO_VERSION' ) || class_exists( 'WPSEO_Options' ) ) {
			$active_plugins[] = 'wordpress-seo';
		}

		// Check for Rank Math SEO.
		if (
			defined( 'RANK_MATH_VERSION' ) ||
			class_exists( 'RankMath' ) ||
			class_exists( 'RankMath\\Helper' ) ||
			class_exists( '\\RankMath\\Helper' )
		) {
			// Add the detected Rank Math integration.
			$active_plugins[] = 'seo-by-rank-math';
		}

		// Check for UpdraftPlus.
		if ( defined( 'UPDRAFTPLUS_DIR' ) || class_exists( 'UpdraftPlus' ) || class_exists( 'UpdraftPlus_Options' ) ) {
			$active_plugins[] = 'updraftplus';
		}

		// Check for Wordfence.
		if ( defined( 'WORDFENCE_VERSION' ) || class_exists( 'wordfence' ) || class_exists( 'wfConfig' ) ) {
			$active_plugins[] = 'wordfence';
		}

		// Check for Elementor.
		if ( defined( 'ELEMENTOR_VERSION' ) || class_exists( '\\Elementor\\Plugin' ) ) {
			$active_plugins[] = 'elementor';
		}

		// Check for Elementor Pro forms.
		if ( defined( 'ELEMENTOR_PRO_VERSION' ) || class_exists( '\\ElementorPro\\Plugin' ) ) {
			$active_plugins[] = 'elementor-pro';
		}

		// Check for LearnDash LMS.
		if ( defined( 'LEARNDASH_VERSION' ) || function_exists( 'learndash_get_course_list' ) ) {
			$active_plugins[] = 'sfwd-lms';
		}

		// Check for FluentCRM.
		if ( function_exists( 'fluentcrm' ) || defined( 'FLUENTCRM' ) || class_exists( '\\FluentCrm\\App' ) ) {
			$active_plugins[] = 'fluent-crm';
		}

		// Check for WP Rocket.
		if ( defined( 'WP_ROCKET_VERSION' ) || function_exists( 'rocket_clean_domain' ) ) {
			$active_plugins[] = 'wp-rocket';
		}

		// Check for Redirection plugin.
		if (
			defined( 'REDIRECTION_VERSION' ) ||
			class_exists( 'Redirection' ) ||
			class_exists( 'Red_Item' ) ||
			function_exists( 'red_get_options' )
		) {
			$active_plugins[] = 'redirection';
		}

		// Check for LiteSpeed Cache.
		if (
			defined( 'LSCWP_V' ) ||
			defined( 'LITESPEED_ON' ) ||
			class_exists( '\\LiteSpeed\\Core' ) ||
			class_exists( '\\LiteSpeed\\Router' )
		) {
			$active_plugins[] = 'litespeed-cache';
		}

		// Event registrations also prove that their integration is active, so a
		// matching template group should be visible without another filter.
		$registered_events = function_exists( 'notificator_companion_get_registered_events' )
			? notificator_companion_get_registered_events()
			: array();
		foreach ( $registered_events as $registered_event ) {
			if ( is_array( $registered_event ) && ! empty( $registered_event['plugin_slug'] ) ) {
				$active_plugins[] = (string) $registered_event['plugin_slug'];
			}
		}

		/**
		 * Filter the list of plugin identifiers treated as active for templates.
		 *
		 * This is used only for template visibility, not hook scanning.
		 */
		$active_plugins = apply_filters( 'notificator_companion_active_plugin_identifiers', $active_plugins );
		$active_plugins = array_values( array_unique( array_filter( $active_plugins, 'is_string' ) ) );

		return $active_plugins;
	}

	/**
	 * Render the main settings page
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Get options.
		$options = get_option( $this->option_name );
		if ( ! is_array( $options ) ) {
			$options = array();
		}
		$log_enabled       = ! isset( $options['log_enabled'] ) || (bool) $options['log_enabled'];
		$api_keys          = array();
		$api_key_nicknames = array();
		$api_key_enabled   = array();
		if ( isset( $options['api_keys'] ) && is_array( $options['api_keys'] ) ) {
			$api_keys = array_values(
				array_filter(
					array_map(
						function ( $v ) {
							return is_string( $v ) ? trim( $v ) : '';
						},
						$options['api_keys']
					)
				)
			);
			if ( isset( $options['api_key_nicknames'] ) && is_array( $options['api_key_nicknames'] ) ) {
				$api_key_nicknames = array_map(
					function ( $v ) {
						return is_string( $v ) ? trim( $v ) : '';
					},
					array_values( $options['api_key_nicknames'] )
				);
			}
			$saved_enabled_states = isset( $options['api_key_enabled'] ) && is_array( $options['api_key_enabled'] ) ? array_values( $options['api_key_enabled'] ) : array();
			foreach ( $api_keys as $index => $unused_key ) {
				$api_key_enabled[] = ! isset( $saved_enabled_states[ $index ] ) || (bool) $saved_enabled_states[ $index ];
			}
		} elseif ( isset( $options['api_key'] ) && is_string( $options['api_key'] ) && '' !== trim( $options['api_key'] ) ) {
			$api_keys          = array( trim( $options['api_key'] ) );
			$api_key_nicknames = array( '' );
			$api_key_enabled   = array( true );
		}
		$monitors             = isset( $options['monitors'] ) ? $options['monitors'] : array();
		$hooks                = isset( $options['hooks'] ) ? $options['hooks'] : array();
		$has_api_key          = ! empty( $api_keys );
		$active_api_key_count = count( array_filter( $api_key_enabled ) );
		$mqtt_state           = Notificator_Companion_Mqtt_Config::get_admin_state( $options );
		if ( ! empty( $mqtt_state['ready'] ) ) {
			$mqtt_status_class  = 'is-active';
			$mqtt_status_label  = __( 'Connected', 'notificator-project' );
			$mqtt_summary_label = __( 'Your broker', 'notificator-project' );
			$mqtt_summary_text  = $mqtt_state['host'];
		} elseif ( ! empty( $mqtt_state['enabled'] ) ) {
			$mqtt_status_class  = 'is-warning';
			$mqtt_status_label  = __( 'Needs setup', 'notificator-project' );
			$mqtt_summary_label = __( 'Incomplete', 'notificator-project' );
			$mqtt_summary_text  = __( 'MQTT paused', 'notificator-project' );
		} else {
			$mqtt_status_class  = 'is-neutral';
			$mqtt_status_label  = __( 'Not configured', 'notificator-project' );
			$mqtt_summary_label = __( 'Not configured', 'notificator-project' );
			$mqtt_summary_text  = __( 'Connect your broker', 'notificator-project' );
		}
		$admin_toasts_enabled = ! isset( $options['admin_toasts_enabled'] ) || (bool) $options['admin_toasts_enabled'];
		$toast_duration       = isset( $options['toast_duration'] ) ? (int) $options['toast_duration'] : 3;
		if ( $toast_duration < 1 ) {
			$toast_duration = 1;
		} elseif ( $toast_duration > 15 ) {
			$toast_duration = 15;
		}
		$toast_poll_interval = isset( $options['toast_poll_interval'] ) ? (int) $options['toast_poll_interval'] : 30;
		if ( $toast_poll_interval < 15 ) {
			$toast_poll_interval = 15;
		} elseif ( $toast_poll_interval > 300 ) {
			$toast_poll_interval = 300;
		}
		/* translators: %d: Dashboard alert polling interval in seconds. */
		$toast_poll_summary_format = __( 'Every %d seconds', 'notificator-project' );
		$toast_poll_summary        = sprintf( $toast_poll_summary_format, $toast_poll_interval );
		$poll_intervals            = array(
			15  => __( 'Every 15 seconds (fastest)', 'notificator-project' ),
			30  => __( 'Every 30 seconds (recommended)', 'notificator-project' ),
			60  => __( 'Every minute', 'notificator-project' ),
			120 => __( 'Every 2 minutes', 'notificator-project' ),
			300 => __( 'Every 5 minutes (lightest)', 'notificator-project' ),
		);
		if ( ! isset( $poll_intervals[ $toast_poll_interval ] ) ) {
			/* translators: %d: Custom dashboard alert polling interval in seconds. */
			$custom_poll_interval_format            = __( 'Every %d seconds (custom)', 'notificator-project' );
			$poll_intervals[ $toast_poll_interval ] = sprintf( $custom_poll_interval_format, $toast_poll_interval );
			ksort( $poll_intervals );
		}
		$toast_position_x = isset( $options['toast_position_x'] ) ? (string) $options['toast_position_x'] : 'right';
		if ( ! in_array( $toast_position_x, array( 'left', 'center', 'right' ), true ) ) {
			$toast_position_x = 'right';
		}
		$toast_position_y = isset( $options['toast_position_y'] ) ? (string) $options['toast_position_y'] : 'top';
		if ( ! in_array( $toast_position_y, array( 'top', 'bottom' ), true ) ) {
			$toast_position_y = 'top';
		}
		$toast_delivery_mode = isset( $options['toast_delivery_mode'] ) ? (string) $options['toast_delivery_mode'] : 'account';
		if ( ! in_array( $toast_delivery_mode, array( 'account', 'tab' ), true ) ) {
			$toast_delivery_mode = 'account';
		}
		$toast_dismiss_mode = isset( $options['toast_dismiss_mode'] ) ? (string) $options['toast_dismiss_mode'] : 'auto';
		if ( ! in_array( $toast_dismiss_mode, array( 'auto', 'click' ), true ) ) {
			$toast_dismiss_mode = 'auto';
		}
		$save_label        = __( 'Save Settings', 'notificator-project' );
		$current_page      = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'notificator'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$workspace_routes  = array(
			'notificator'               => 'overview',
			'notificator-notifications' => 'notifications',
			'notificator-activity'      => 'activity',
			'notificator-settings'      => 'settings',
			'notificator-developer'     => 'developer',
			'notificator-support'       => 'support',
		);
		$initial_workspace = isset( $workspace_routes[ $current_page ] ) ? $workspace_routes[ $current_page ] : 'overview';

		?>
		<div class="wrap notificator-companion-wrap" data-notificator-initial-workspace="<?php echo esc_attr( $initial_workspace ); ?>" data-notificator-current-workspace="<?php echo esc_attr( $initial_workspace ); ?>">
			<header class="notificator-overview-hero notificator-page-hero">
				<div>
					<p class="notificator-eyebrow"><?php esc_html_e( 'WordPress notifications', 'notificator-project' ); ?></p>
					<h1><?php esc_html_e( 'Notificator', 'notificator-project' ); ?></h1>
					<p><?php esc_html_e( 'Turn important WordPress events into useful notifications without touching code.', 'notificator-project' ); ?></p>
				</div>
				<div class="notificator-overview-actions">
					<button type="button" class="btn-primary notificator-header-create" data-notificator-create>
						<span class="dashicons dashicons-plus-alt2"></span>
						<?php esc_html_e( 'Create notification', 'notificator-project' ); ?>
					</button>
					<button type="submit" form="notificator-settings-form" id="notificator-save-settings" class="btn-primary notificator-header-save"><span class="dashicons dashicons-yes-alt"></span><?php echo esc_html( $save_label ); ?></button>
					<button type="button" id="notificator-header-tools" class="btn-secondary"><span class="dashicons dashicons-admin-tools"></span><?php esc_html_e( 'Tools', 'notificator-project' ); ?></button>
					<button type="button" id="notificator-theme-toggle" class="btn-icon notificator-header-icon-button" aria-label="<?php echo esc_attr__( 'Switch to dark theme', 'notificator-project' ); ?>" title="<?php echo esc_attr__( 'Switch to dark theme', 'notificator-project' ); ?>" aria-pressed="false"><span class="notificator-theme-icon" data-theme-icon aria-hidden="true">🌙</span></button>
				</div>
			</header>

			<div id="notificator-admin-notices" hidden></div>
			<nav class="notificator-workspace-tabs" aria-label="<?php esc_attr_e( 'Notificator sections', 'notificator-project' ); ?>">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=notificator' ) ); ?>" data-notificator-workspace-tab="overview"><span class="dashicons dashicons-dashboard"></span><?php esc_html_e( 'Overview', 'notificator-project' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=notificator-notifications' ) ); ?>" data-notificator-workspace-tab="notifications"><span class="dashicons <?php echo esc_attr( $this->get_section_icon_class( 'builder' ) ); ?>"></span><?php esc_html_e( 'Notifications', 'notificator-project' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=notificator-activity' ) ); ?>" data-notificator-workspace-tab="activity"><span class="dashicons <?php echo esc_attr( $this->get_section_icon_class( 'log' ) ); ?>"></span><?php esc_html_e( 'Activity', 'notificator-project' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=notificator-settings' ) ); ?>" data-notificator-workspace-tab="settings"><span class="dashicons dashicons-admin-settings"></span><?php esc_html_e( 'Settings', 'notificator-project' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=notificator-developer' ) ); ?>" data-notificator-workspace-tab="developer"><span class="dashicons dashicons-editor-code"></span><?php esc_html_e( 'Developer', 'notificator-project' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=notificator-support' ) ); ?>" data-notificator-workspace-tab="support"><span class="dashicons dashicons-sos"></span><?php esc_html_e( 'Support', 'notificator-project' ); ?></a>
			</nav>

			<form id="notificator-settings-form" action="options.php" method="post" class="notificator-settings-form">
				<?php settings_fields( 'notificator_companion_settings_group' ); ?>

				<div class="notificator-layout">
					<div class="notificator-main-content">
						<?php
						$throttle_seconds = isset( $options['throttle_seconds'] ) ? (int) $options['throttle_seconds'] : 30;
						if ( $throttle_seconds < 0 ) {
							$throttle_seconds = 0;
						} elseif ( $throttle_seconds > 3600 ) {
							$throttle_seconds = 3600;
						}
						/* translators: %d: Notification throttle duration in seconds. */
						$throttle_duration_format = __( '%ds', 'notificator-project' );
						$throttle_status          = $throttle_seconds > 0
							? sprintf( $throttle_duration_format, $throttle_seconds )
							: __( 'Off', 'notificator-project' );
						$scan_hook_limit          = isset( $options['scan_hook_limit'] ) ? (int) $options['scan_hook_limit'] : 500;
						if ( $scan_hook_limit < 50 ) {
							$scan_hook_limit = 50;
						} elseif ( $scan_hook_limit > 10000 ) {
							$scan_hook_limit = 10000;
						}
						?>
						<div class="notificator-tools-host">
							<div class="notificator-action-left">
								<span class="notificator-action-title" id="notificator-workspace-title"><?php esc_html_e( 'Overview', 'notificator-project' ); ?></span>
								<div class="notificator-save-status" id="notificator-save-status-inline" hidden data-state="idle">
									<span class="notificator-save-status-text"><?php esc_html_e( 'Saved', 'notificator-project' ); ?></span>
								</div>
							</div>

							<div class="notificator-action-right">
								<div class="notificator-action-group">
									<button type="button" id="notificator-add-scenario-top" class="btn-secondary" data-notificator-create>
										<span class="dashicons dashicons-plus-alt2"></span>
										<?php esc_html_e( 'Create notification', 'notificator-project' ); ?>
									</button>

									<details class="notificator-action-menu" id="notificator-scenarios-menu">
									<summary class="btn-secondary notificator-action-menu-trigger" aria-haspopup="menu">
											<span class="dashicons dashicons-admin-tools"></span>
											<?php esc_html_e( 'Tools', 'notificator-project' ); ?>
											<span class="dashicons dashicons-arrow-down-alt2 notificator-action-menu-caret" aria-hidden="true"></span>
									</summary>
									<button type="button" class="notificator-tools-backdrop" data-notificator-tools-close aria-label="<?php esc_attr_e( 'Close tools', 'notificator-project' ); ?>"></button>
									<div class="notificator-action-menu-panel notificator-tools-modal notificator-tools-modal--transfer" role="dialog" aria-modal="true" aria-labelledby="notificator-tools-title">
										<div class="notificator-tools-heading"><div><span class="dashicons dashicons-migrate"></span><div><h2 id="notificator-tools-title"><?php esc_html_e( 'Import & export', 'notificator-project' ); ?></h2><p><?php esc_html_e( 'Move notification setups between WordPress sites.', 'notificator-project' ); ?></p></div></div><button type="button" class="btn-icon" data-notificator-tools-close aria-label="<?php esc_attr_e( 'Close tools', 'notificator-project' ); ?>"><span class="dashicons dashicons-no-alt"></span></button></div>
										<div class="notificator-tools-grid">
											<div class="notificator-action-menu-section">
												<div class="notificator-action-menu-section-title">
													<?php esc_html_e( 'Notification setups', 'notificator-project' ); ?>
												</div>
												<button type="button" id="notificator-export-scenarios" class="notificator-action-menu-item" role="menuitem">
													<span class="dashicons dashicons-download"></span>
													<?php esc_html_e( 'Export notifications', 'notificator-project' ); ?>
												</button>
												<button type="button" id="notificator-import-scenarios" class="notificator-action-menu-item" role="menuitem">
													<span class="dashicons dashicons-upload"></span>
													<?php esc_html_e( 'Import notifications', 'notificator-project' ); ?>
												</button>
											</div>
										</div>
									</div>
								</details>

									<button type="button" class="btn-icon" aria-label="<?php echo esc_attr__( 'Switch to dark theme', 'notificator-project' ); ?>" title="<?php echo esc_attr__( 'Switch to dark theme', 'notificator-project' ); ?>" aria-pressed="false">
										<span class="notificator-theme-icon" data-theme-icon aria-hidden="true">🌙</span>
									</button>
								</div>

								<span class="notificator-action-divider" aria-hidden="true"></span>

								<div class="notificator-action-group notificator-action-group--primary">
									<button type="submit" class="btn-primary">
										<span class="dashicons dashicons-yes-alt"></span>
										<?php echo esc_html( $save_label ); ?>
									</button>
								</div>
							</div>
						</div>

						<?php $this->render_scan_modal(); ?>
						<?php $this->render_import_hooks_modal(); ?>
						<?php $this->render_overview_section( $options, $has_api_key ); ?>
						<section class="notificator-settings-intro" data-notificator-workspace="settings" aria-labelledby="notificator-settings-title">
							<div><p class="notificator-eyebrow"><?php esc_html_e( 'Configuration', 'notificator-project' ); ?></p><h2 id="notificator-settings-title"><?php esc_html_e( 'Connections & preferences', 'notificator-project' ); ?></h2><p><?php esc_html_e( 'See what is active, then fine-tune how notifications behave.', 'notificator-project' ); ?></p></div>
							<div class="notificator-settings-summary">
								<span id="notificator-remote-summary" class="<?php echo $active_api_key_count ? 'is-active' : 'is-neutral'; ?>"><small><?php esc_html_e( 'Remote delivery', 'notificator-project' ); ?></small><strong><b id="notificator-active-key-count"><?php echo esc_html( $active_api_key_count ); ?></b>/<b id="notificator-configured-key-count"><?php echo esc_html( count( $api_keys ) ); ?></b></strong><em><?php esc_html_e( 'keys active', 'notificator-project' ); ?></em></span>
								<span id="notificator-mqtt-summary" class="<?php echo esc_attr( $mqtt_status_class ); ?>"><small><?php esc_html_e( 'MQTT broker', 'notificator-project' ); ?></small><strong data-notificator-mqtt-summary-label><?php echo esc_html( $mqtt_summary_label ); ?></strong><em data-notificator-mqtt-summary-detail><?php echo esc_html( $mqtt_summary_text ); ?></em></span>
								<span id="notificator-dashboard-summary" class="<?php echo $admin_toasts_enabled ? 'is-active' : 'is-neutral'; ?>"><small><?php esc_html_e( 'Dashboard alerts', 'notificator-project' ); ?></small><strong><?php echo esc_html( $admin_toasts_enabled ? __( 'On', 'notificator-project' ) : __( 'Off', 'notificator-project' ) ); ?></strong><em><?php echo esc_html( $toast_poll_summary ); ?></em></span>
								<span id="notificator-log-summary" class="<?php echo $log_enabled ? 'is-active' : 'is-neutral'; ?>"><small><?php esc_html_e( 'Activity log', 'notificator-project' ); ?></small><strong><?php echo esc_html( $log_enabled ? __( 'On', 'notificator-project' ) : __( 'Off', 'notificator-project' ) ); ?></strong><em><?php esc_html_e( 'Delivery history', 'notificator-project' ); ?></em></span>
							</div>
						</section>

						<nav class="notificator-settings-tabs" data-notificator-workspace="settings" aria-label="<?php esc_attr_e( 'Settings categories', 'notificator-project' ); ?>">
							<button type="button" class="is-active" data-notificator-settings-tab="connections" aria-pressed="true">
								<span class="dashicons dashicons-admin-links" aria-hidden="true"></span>
								<span><strong><?php esc_html_e( 'Connections', 'notificator-project' ); ?></strong><small><?php esc_html_e( 'API keys and MQTT', 'notificator-project' ); ?></small></span>
							</button>
							<button type="button" data-notificator-settings-tab="dashboard" data-notificator-settings-title="<?php esc_attr_e( 'Dashboard alerts', 'notificator-project' ); ?>" data-notificator-settings-description="<?php esc_attr_e( 'Choose when dashboard alerts appear and how administrators dismiss them.', 'notificator-project' ); ?>" aria-pressed="false">
								<span class="dashicons dashicons-dashboard" aria-hidden="true"></span>
								<span><strong><?php esc_html_e( 'Dashboard alerts', 'notificator-project' ); ?></strong><small><?php esc_html_e( 'Timing and appearance', 'notificator-project' ); ?></small></span>
							</button>
							<button type="button" data-notificator-settings-tab="delivery" data-notificator-settings-title="<?php esc_attr_e( 'Discovery & delivery', 'notificator-project' ); ?>" data-notificator-settings-description="<?php esc_attr_e( 'Control event scanning limits and protection against repeated notifications.', 'notificator-project' ); ?>" aria-pressed="false">
								<span class="dashicons dashicons-performance" aria-hidden="true"></span>
								<span><strong><?php esc_html_e( 'Discovery & delivery', 'notificator-project' ); ?></strong><small><?php esc_html_e( 'Scanning and safeguards', 'notificator-project' ); ?></small></span>
							</button>
							<button type="button" data-notificator-settings-tab="data" data-notificator-settings-title="<?php esc_attr_e( 'Data & logs', 'notificator-project' ); ?>" data-notificator-settings-description="<?php esc_attr_e( 'Manage delivery history, exports, and plugin-generated test data.', 'notificator-project' ); ?>" aria-pressed="false">
								<span class="dashicons dashicons-database" aria-hidden="true"></span>
								<span><strong><?php esc_html_e( 'Data & logs', 'notificator-project' ); ?></strong><small><?php esc_html_e( 'History and reset', 'notificator-project' ); ?></small></span>
							</button>
						</nav>

						<!-- API Configuration -->
						<div class="scenario-section notificator-section" id="notificator-api" data-notificator-section="api" data-notificator-settings-group="connections" data-notificator-workspace="settings">
							<div class="notificator-scenario-head notificator-scenario-head--api">
								<div class="flex items-center gap-3 min-w-0">
									<div class="notificator-section-icon">
										<span class="dashicons <?php echo esc_attr( $this->get_section_icon_class( 'api' ) ); ?> text-white"></span>
									</div>
									<div class="min-w-0">
										<span class="notificator-connection-kicker"><?php esc_html_e( 'Mobile app & API', 'notificator-project' ); ?></span>
										<h3 class="text-base font-semibold text-white"><?php esc_html_e( 'Remote delivery', 'notificator-project' ); ?></h3>
										<p class="text-xs text-white text-opacity-70"><?php esc_html_e( 'Optional API keys for mobile push and authenticated MQTT delivery.', 'notificator-project' ); ?></p>
									</div>
								</div>
								<span id="notificator-remote-section-status" class="notificator-section-status <?php echo $active_api_key_count ? 'is-active' : 'is-neutral'; ?>"><?php echo esc_html( $active_api_key_count ? __( 'Connected', 'notificator-project' ) : __( 'Optional', 'notificator-project' ) ); ?></span>
							</div>
							<div class="card-body space-y-4">
								<div class="notificator-remote-guide">
									<span class="dashicons dashicons-smartphone"></span>
									<div><strong><?php esc_html_e( 'Connect the Notificator mobile app', 'notificator-project' ); ?></strong><p><?php esc_html_e( 'Add an API key when you want mobile push or MQTT. Dashboard-only notifications work without one.', 'notificator-project' ); ?></p></div>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=notificator-support' ) ); ?>"><?php esc_html_e( 'Account guide', 'notificator-project' ); ?></a>
								</div>
								<div class="notificator-connection-panel">
									<label class="block text-sm font-semibold text-gray-700 mb-2" for="api_key_0">
										<?php esc_html_e( 'Connected accounts', 'notificator-project' ); ?>
									</label>
									<div id="notificator-api-keys" class="space-y-2" data-has-api-key="<?php echo esc_attr( $has_api_key ? '1' : '0' ); ?>">
										<?php
										$api_keys_for_render = ! empty( $api_keys ) ? $api_keys : array( '' );
										foreach ( $api_keys_for_render as $i => $key ) :
											$input_id    = 'api_key_' . (int) $i;
											$nickname_id = 'api_key_nickname_' . (int) $i;
											$nickname    = isset( $api_key_nicknames[ $i ] ) ? $api_key_nicknames[ $i ] : '';
											$key_enabled = ! isset( $api_key_enabled[ $i ] ) || (bool) $api_key_enabled[ $i ];
											$hide_remove = ! $has_api_key && 0 === $i && 1 === count( $api_keys_for_render );
											$key_suffix  = $key ? substr( $key, -6 ) : '';
											/* translators: %s: Last characters of a saved API key. */
											$key_placeholder = $key_suffix ? sprintf( __( 'Saved key ending in %s', 'notificator-project' ), $key_suffix ) : 'wpnotif_...';
											?>
											<div class="notificator-api-key-row <?php echo $key_enabled ? 'is-enabled' : 'is-disabled'; ?>">
												<div class="notificator-api-key-state"><label class="notificator-switch"><input type="checkbox" class="notificator-api-key-toggle" <?php checked( $key_enabled ); ?> aria-label="<?php echo esc_attr( $key_enabled ? __( 'Disable API key', 'notificator-project' ) : __( 'Enable API key', 'notificator-project' ) ); ?>"><span></span></label><strong><?php echo esc_html( $key_enabled ? __( 'On', 'notificator-project' ) : __( 'Off', 'notificator-project' ) ); ?></strong><input type="hidden" class="notificator-api-key-enabled-value" name="<?php echo esc_attr( $this->option_name ); ?>[api_key_enabled][]" value="<?php echo esc_attr( $key_enabled ? '1' : '0' ); ?>"></div>
												<input type="password"
													id="<?php echo esc_attr( $input_id ); ?>"
													name="<?php echo esc_attr( $this->option_name ); ?>[api_keys][]"
													value=""
													data-existing-key-index="<?php echo $key ? esc_attr( (string) $i ) : ''; ?>"
													class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
												placeholder="<?php echo esc_attr( $key_placeholder ); ?>">
												<input type="hidden" name="<?php echo esc_attr( $this->option_name ); ?>[api_key_existing_indexes][]" value="<?php echo $key ? esc_attr( (string) $i ) : ''; ?>">
													<input type="text"
													id="<?php echo esc_attr( $nickname_id ); ?>"
													name="<?php echo esc_attr( $this->option_name ); ?>[api_key_nicknames][]"
													value="<?php echo esc_attr( $nickname ); ?>"
													class="px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 min-w-[140px]"
																placeholder="<?php echo esc_attr__( 'Device or account label', 'notificator-project' ); ?>">
													<button type="button" class="btn-secondary notificator-test-api-key" aria-label="<?php echo esc_attr( wp_json_encode( __( 'Send test notification', 'notificator-project' ) ) ); ?>" data-notificator-unlock="test-connection" <?php echo $key_enabled ? '' : 'disabled'; ?>>
														<span class="dashicons dashicons-yes-alt"></span>
														<?php esc_html_e( 'Test', 'notificator-project' ); ?>
													</button>
													<button type="button" class="btn-secondary btn-secondary--danger notificator-remove-api-key" aria-label="<?php echo esc_attr( wp_json_encode( __( 'Remove API key', 'notificator-project' ) ) ); ?>" <?php echo $hide_remove ? 'hidden' : ''; ?> data-notificator-unlock="remove-api-key">
														<span class="dashicons dashicons-trash"></span>
														<?php esc_html_e( 'Remove', 'notificator-project' ); ?>
													</button>
											</div>
										<?php endforeach; ?>
									</div>
										<input type="hidden" id="notificator_companion_test_nonce" value="<?php echo esc_attr( wp_create_nonce( 'notificator_companion_test' ) ); ?>">
									<div class="notificator-api-actions mt-2">
										<div class="notificator-api-actions-left">
											<button type="button" id="notificator-add-api-key" class="btn-secondary" data-notificator-unlock="add-api-key" <?php echo $has_api_key ? '' : 'hidden'; ?>>
												<span class="dashicons dashicons-plus-alt2"></span>
												<?php esc_html_e( 'Add another key', 'notificator-project' ); ?>
											</button>
											<button type="submit" id="notificator-save-api-keys" class="btn-primary btn-primary--compact">
												<span class="dashicons dashicons-yes-alt"></span>
												<?php esc_html_e( 'Save connections', 'notificator-project' ); ?>
											</button>
										</div>
										<p class="text-xs text-gray-500">
											<?php esc_html_e( 'One key can represent one person, device, or destination.', 'notificator-project' ); ?>
										</p>
									</div>
								</div>

								<div class="notificator-connection-note" data-notificator-lock="api-warning" <?php echo $has_api_key ? 'hidden style="display:none;"' : ''; ?>>
									<span class="dashicons dashicons-dashboard" aria-hidden="true"></span>
									<div>
										<strong><?php esc_html_e( 'Dashboard mode is ready', 'notificator-project' ); ?></strong>
										<p><?php esc_html_e( 'No API key is needed for WordPress dashboard alerts. Add a key only when you want mobile push or MQTT delivery.', 'notificator-project' ); ?></p>
									</div>
								</div>
							</div>
						</div>

						<section class="scenario-section notificator-section notificator-mqtt-settings" id="notificator-mqtt" data-notificator-settings-group="connections" data-notificator-workspace="settings" aria-labelledby="notificator-mqtt-title" data-mqtt-ready="<?php echo esc_attr( ! empty( $mqtt_state['ready'] ) ? '1' : '0' ); ?>">
							<div class="notificator-scenario-head">
								<div class="flex items-center gap-3">
									<div class="notificator-section-icon"><span class="dashicons dashicons-cloud"></span></div>
									<div>
										<span class="notificator-connection-kicker"><?php esc_html_e( 'Devices & broker', 'notificator-project' ); ?></span>
										<h3 id="notificator-mqtt-title"><?php esc_html_e( 'MQTT broker', 'notificator-project' ); ?></h3>
										<p><?php esc_html_e( 'Connect your own broker. The current release supports HiveMQ Cloud only.', 'notificator-project' ); ?></p>
									</div>
								</div>
								<span id="notificator-mqtt-status" class="notificator-section-status <?php echo esc_attr( $mqtt_status_class ); ?>"><?php echo esc_html( $mqtt_status_label ); ?></span>
							</div>
							<div class="card-body">
								<div class="notificator-mqtt-provider-guide">
									<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
									<div>
										<strong><?php esc_html_e( 'HiveMQ Cloud setup', 'notificator-project' ); ?></strong>
										<p><?php esc_html_e( 'HiveMQ offers a free Serverless plan. Create a cluster, copy its URL from the cluster overview, then create a username and password under Access Management.', 'notificator-project' ); ?></p>
										<ol>
											<li><?php esc_html_e( 'Choose Create Serverless Cluster.', 'notificator-project' ); ?></li>
											<li><?php esc_html_e( 'Copy the cluster URL; enter only its hostname below.', 'notificator-project' ); ?></li>
											<li><?php esc_html_e( 'Create a Publish Only credential for WordPress and a separate Publish and Subscribe credential for the device.', 'notificator-project' ); ?></li>
										</ol>
									</div>
									<a href="https://console.hivemq.cloud/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open HiveMQ Cloud', 'notificator-project' ); ?><span class="dashicons dashicons-external" aria-hidden="true"></span></a>
								</div>
								<div class="notificator-mqtt-mode">
									<div>
										<strong><?php esc_html_e( 'Enable MQTT delivery', 'notificator-project' ); ?></strong>
										<p><?php esc_html_e( 'Use your own HiveMQ Cloud cluster for MQTT-enabled notifications.', 'notificator-project' ); ?></p>
									</div>
									<label class="notificator-switch">
										<input type="hidden" name="<?php echo esc_attr( $this->option_name ); ?>[mqtt_custom_enabled]" value="0">
										<input type="checkbox" id="notificator-mqtt-custom-enabled" name="<?php echo esc_attr( $this->option_name ); ?>[mqtt_custom_enabled]" value="1" <?php checked( ! empty( $mqtt_state['enabled'] ) ); ?> aria-describedby="notificator-mqtt-mode-help">
										<span></span>
										<em class="screen-reader-text"><?php esc_html_e( 'Enable MQTT delivery through your HiveMQ Cloud cluster', 'notificator-project' ); ?></em>
									</label>
								</div>
								<p id="notificator-mqtt-mode-help" class="notificator-mqtt-help"><?php esc_html_e( 'Your device must use the same cluster and topic prefix. Use a separate publisher credential here when possible.', 'notificator-project' ); ?></p>

								<div class="notificator-mqtt-fields" data-notificator-mqtt-fields>
									<label>
										<span><?php esc_html_e( 'Cluster hostname', 'notificator-project' ); ?></span>
										<input type="text" id="notificator-mqtt-host" name="<?php echo esc_attr( $this->option_name ); ?>[mqtt_host]" value="<?php echo esc_attr( $mqtt_state['host'] ); ?>" maxlength="253" placeholder="abc123.s1.eu.hivemq.cloud" autocomplete="off" inputmode="url">
										<small><?php esc_html_e( 'HiveMQ Cloud hostname only. Do not include https:// or a port.', 'notificator-project' ); ?></small>
									</label>
									<label>
										<span><?php esc_html_e( 'Publisher username', 'notificator-project' ); ?></span>
										<input type="text" id="notificator-mqtt-username" name="<?php echo esc_attr( $this->option_name ); ?>[mqtt_username]" value="<?php echo esc_attr( $mqtt_state['username'] ); ?>" maxlength="128" autocomplete="username">
										<small><?php esc_html_e( 'Prefer a credential limited to publishing device messages and commands.', 'notificator-project' ); ?></small>
									</label>
									<label>
										<span><?php esc_html_e( 'Publisher password', 'notificator-project' ); ?></span>
										<input type="password" id="notificator-mqtt-password" name="<?php echo esc_attr( $this->option_name ); ?>[mqtt_password]" value="" maxlength="512" autocomplete="new-password" placeholder="<?php echo esc_attr( ! empty( $mqtt_state['password_configured'] ) ? __( 'Saved securely; leave blank to keep it', 'notificator-project' ) : __( 'Enter the HiveMQ password', 'notificator-project' ) ); ?>">
										<small><?php esc_html_e( 'Encrypted with keys derived from this WordPress installation and never displayed again.', 'notificator-project' ); ?></small>
									</label>
									<label>
										<span><?php esc_html_e( 'Topic prefix', 'notificator-project' ); ?></span>
										<input type="text" id="notificator-mqtt-topic-prefix" name="<?php echo esc_attr( $this->option_name ); ?>[mqtt_topic_prefix]" value="<?php echo esc_attr( $mqtt_state['topic_prefix'] ); ?>" maxlength="128" placeholder="notificator-project" autocomplete="off">
										<small><?php esc_html_e( 'The firmware must use this exact prefix. MQTT wildcards are not accepted.', 'notificator-project' ); ?></small>
									</label>
								</div>

								<div class="notificator-mqtt-transport">
									<span><strong><?php esc_html_e( 'API transport', 'notificator-project' ); ?></strong> WSS :<?php echo esc_html( (string) $mqtt_state['port'] ); ?><?php echo esc_html( $mqtt_state['path'] ); ?></span>
									<span><strong><?php esc_html_e( 'Device transport', 'notificator-project' ); ?></strong> MQTT TLS :8883</span>
								</div>

								<input type="hidden" id="notificator-mqtt-forget" name="<?php echo esc_attr( $this->option_name ); ?>[mqtt_forget]" value="0">
								<div class="notificator-mqtt-actions">
								<button type="button" id="notificator-test-mqtt" class="btn-secondary" <?php disabled( empty( $mqtt_state['ready'] ) || 0 === $active_api_key_count ); ?>><span class="dashicons dashicons-yes-alt"></span><?php esc_html_e( 'Test broker', 'notificator-project' ); ?></button>
								<button type="button" id="notificator-forget-mqtt" class="btn-secondary btn-secondary--danger" <?php echo empty( $mqtt_state['configured'] ) && empty( $mqtt_state['host'] ) ? 'hidden' : ''; ?>><span class="dashicons dashicons-trash"></span><?php esc_html_e( 'Forget broker connection', 'notificator-project' ); ?></button>
									<p class="notificator-mqtt-result" id="notificator-mqtt-result" role="status" aria-live="polite"></p>
								</div>

								<div class="notificator-mqtt-security-note">
									<span class="dashicons dashicons-lock"></span>
									<p><?php esc_html_e( 'The password is excluded from exports, logs, diagnostics, and the persisted delivery queue. It is decrypted only while preparing an HTTPS request.', 'notificator-project' ); ?></p>
								</div>
							</div>
						</section>

						<section class="scenario-section notificator-section notificator-preferences" id="notificator-preferences" data-notificator-settings-preferences data-notificator-workspace="settings" aria-labelledby="notificator-preferences-title">
							<div class="notificator-scenario-head">
								<div class="flex items-center gap-3">
									<div class="notificator-section-icon"><span class="dashicons dashicons-admin-settings"></span></div>
									<div>
										<h3 id="notificator-preferences-title" data-notificator-settings-panel-title><?php esc_html_e( 'Dashboard alerts', 'notificator-project' ); ?></h3>
										<p data-notificator-settings-panel-description><?php esc_html_e( 'Choose when dashboard alerts appear and how administrators dismiss them.', 'notificator-project' ); ?></p>
									</div>
								</div>
							</div>
							<div class="card-body">
								<div class="notificator-preferences-grid">
									<section class="notificator-preference-card" data-notificator-settings-group="delivery">
										<div class="notificator-preference-card__heading"><span class="dashicons dashicons-search"></span><div><h4><?php esc_html_e( 'Event discovery', 'notificator-project' ); ?></h4><p><?php esc_html_e( 'Refresh events after installing or updating plugins.', 'notificator-project' ); ?></p></div><span class="notificator-card-status is-neutral"><?php esc_html_e( 'On demand', 'notificator-project' ); ?></span></div>
										<button type="button" id="notificator-scan-plugins-tool" class="btn-secondary"><span class="dashicons dashicons-update"></span><?php esc_html_e( 'Scan active plugins', 'notificator-project' ); ?></button>
										<label class="notificator-preference-field" for="notificator-scan-hook-limit"><span><?php esc_html_e( 'Per-plugin event limit', 'notificator-project' ); ?></span><input type="number" id="notificator-scan-hook-limit" name="<?php echo esc_attr( $this->option_name ); ?>[scan_hook_limit]" min="50" max="10000" value="<?php echo esc_attr( $scan_hook_limit ); ?>" title="<?php esc_attr_e( 'Applied separately to each plugin; the total scan can be higher.', 'notificator-project' ); ?>" /></label>
										<small><?php esc_html_e( 'The overall result may be higher because this limit applies to each plugin separately.', 'notificator-project' ); ?></small>
									</section>

									<section class="notificator-preference-card" data-notificator-settings-group="delivery">
										<div class="notificator-preference-card__heading"><span class="dashicons dashicons-controls-repeat"></span><div><h4><?php esc_html_e( 'Notification safeguards', 'notificator-project' ); ?></h4><p><?php esc_html_e( 'Prevent the same event from sending too frequently.', 'notificator-project' ); ?></p></div><span class="notificator-card-status is-active" data-notificator-throttle-status data-disabled-label="<?php esc_attr_e( 'Off', 'notificator-project' ); ?>" data-current-template="<?php echo esc_attr( $throttle_duration_format ); ?>"><?php echo esc_html( $throttle_status ); ?></span></div>
										<label class="notificator-preference-field" for="notificator-throttle-seconds"><span><?php esc_html_e( 'Throttle window', 'notificator-project' ); ?></span><span class="notificator-preference-input-suffix"><input type="number" id="notificator-throttle-seconds" name="<?php echo esc_attr( $this->option_name ); ?>[throttle_seconds]" min="0" max="3600" value="<?php echo esc_attr( $throttle_seconds ); ?>" /><em><?php esc_html_e( 'seconds', 'notificator-project' ); ?></em></span></label>
										<small><?php esc_html_e( 'Use 0 to allow every matching event.', 'notificator-project' ); ?></small>
									</section>

									<section class="notificator-preference-card notificator-preference-card--wide notificator-preference-card--dashboard" data-notificator-settings-group="dashboard">
										<div class="notificator-preference-card__heading"><span class="dashicons dashicons-dashboard"></span><div><h4><?php esc_html_e( 'Dashboard alerts', 'notificator-project' ); ?></h4><p><?php esc_html_e( 'Choose how alerts appear inside WordPress.', 'notificator-project' ); ?></p></div><span id="notificator-dashboard-card-status" class="notificator-card-status <?php echo $admin_toasts_enabled ? 'is-active' : 'is-neutral'; ?>"><?php echo esc_html( $admin_toasts_enabled ? __( 'On', 'notificator-project' ) : __( 'Off', 'notificator-project' ) ); ?></span></div>
										<div id="notificator-dashboard-alert-settings" class="notificator-dashboard-alert-settings <?php echo $admin_toasts_enabled ? 'is-enabled' : 'is-disabled'; ?>">
											<div class="notificator-dashboard-alert-toggle">
												<div><span class="notificator-live-dot" aria-hidden="true"></span><div><strong><?php esc_html_e( 'Show alerts in WordPress', 'notificator-project' ); ?></strong><p><?php esc_html_e( 'Display new event notifications while an administrator is using the dashboard.', 'notificator-project' ); ?></p></div></div>
												<button type="button" id="notificator-toggle-admin-toasts" class="btn-secondary notificator-preference-toggle" data-toasts-enabled="<?php echo esc_attr( $admin_toasts_enabled ? '1' : '0' ); ?>" aria-pressed="<?php echo esc_attr( $admin_toasts_enabled ? 'true' : 'false' ); ?>"><span class="dashicons <?php echo esc_attr( $admin_toasts_enabled ? 'dashicons-no' : 'dashicons-yes' ); ?>"></span><?php echo esc_html( $admin_toasts_enabled ? __( 'Turn off', 'notificator-project' ) : __( 'Turn on', 'notificator-project' ) ); ?></button>
											</div>
											<div class="notificator-dashboard-alert-groups">
												<section class="notificator-dashboard-alert-group">
													<div class="notificator-dashboard-alert-group__heading"><span class="dashicons dashicons-clock"></span><div><h5><?php esc_html_e( 'Delivery timing', 'notificator-project' ); ?></h5><p><?php esc_html_e( 'Balance responsiveness with admin requests.', 'notificator-project' ); ?></p></div></div>
													<div class="notificator-preference-fields">
												<label class="notificator-preference-field" for="notificator-toast-poll-interval"><span><?php esc_html_e( 'Check for alerts', 'notificator-project' ); ?></span><select id="notificator-toast-poll-interval" name="<?php echo esc_attr( $this->option_name ); ?>[toast_poll_interval]">
												<?php
												foreach ( $poll_intervals as $interval => $interval_label ) :
													?>
													<option value="<?php echo esc_attr( $interval ); ?>" <?php selected( $toast_poll_interval, $interval ); ?>><?php echo esc_html( $interval_label ); ?></option><?php endforeach; ?></select></label>
														<label class="notificator-preference-field" for="notificator-toast-duration"><span><?php esc_html_e( 'Keep visible for', 'notificator-project' ); ?></span><span class="notificator-preference-input-suffix"><input type="number" min="1" max="15" id="notificator-toast-duration" name="<?php echo esc_attr( $this->option_name ); ?>[toast_duration]" value="<?php echo esc_attr( $toast_duration ); ?>" /><em><?php esc_html_e( 'seconds', 'notificator-project' ); ?></em></span></label>
													</div>
												</section>
												<section class="notificator-dashboard-alert-group">
													<div class="notificator-dashboard-alert-group__heading"><span class="dashicons dashicons-visibility"></span><div><h5><?php esc_html_e( 'Appearance & behavior', 'notificator-project' ); ?></h5><p><?php esc_html_e( 'Control where alerts appear and when they disappear.', 'notificator-project' ); ?></p></div></div>
													<div class="notificator-preference-fields">
														<div class="notificator-preference-field"><span><?php esc_html_e( 'Position', 'notificator-project' ); ?></span><span class="notificator-preference-select-pair"><label><small><?php esc_html_e( 'Vertical', 'notificator-project' ); ?></small><select id="notificator-toast-position-y" name="<?php echo esc_attr( $this->option_name ); ?>[toast_position_y]">
														<?php
														foreach ( array( 'top', 'bottom' ) as $pos_y ) :
															?>
															<option value="<?php echo esc_attr( $pos_y ); ?>" <?php selected( $toast_position_y, $pos_y ); ?>><?php echo esc_html( ucfirst( $pos_y ) ); ?></option><?php endforeach; ?></select></label><label><small><?php esc_html_e( 'Horizontal', 'notificator-project' ); ?></small><select id="notificator-toast-position-x" name="<?php echo esc_attr( $this->option_name ); ?>[toast_position_x]">
															<?php
															foreach ( array( 'left', 'center', 'right' ) as $pos_x ) :
																?>
															<option value="<?php echo esc_attr( $pos_x ); ?>" <?php selected( $toast_position_x, $pos_x ); ?>><?php echo esc_html( ucfirst( $pos_x ) ); ?></option><?php endforeach; ?></select></label></span></div>
														<label class="notificator-preference-field" for="notificator-toast-delivery"><span><?php esc_html_e( 'Avoid duplicates', 'notificator-project' ); ?></span><select id="notificator-toast-delivery" name="<?php echo esc_attr( $this->option_name ); ?>[toast_delivery_mode]"><option value="account" <?php selected( $toast_delivery_mode, 'account' ); ?>><?php esc_html_e( 'Once per WordPress account', 'notificator-project' ); ?></option><option value="tab" <?php selected( $toast_delivery_mode, 'tab' ); ?>><?php esc_html_e( 'Once in each browser tab', 'notificator-project' ); ?></option></select></label>
														<label class="notificator-preference-field" for="notificator-toast-dismiss"><span><?php esc_html_e( 'Dismiss alert', 'notificator-project' ); ?></span><select id="notificator-toast-dismiss" name="<?php echo esc_attr( $this->option_name ); ?>[toast_dismiss_mode]"><option value="auto" <?php selected( $toast_dismiss_mode, 'auto' ); ?>><?php esc_html_e( 'Automatically after the duration', 'notificator-project' ); ?></option><option value="click" <?php selected( $toast_dismiss_mode, 'click' ); ?>><?php esc_html_e( 'Only when clicked', 'notificator-project' ); ?></option></select></label>
													</div>
												</section>
											</div>
											<p class="notificator-dashboard-alert-note"><span class="dashicons dashicons-info-outline"></span><?php esc_html_e( 'Checking pauses automatically when the browser tab is hidden, so inactive tabs do not keep polling.', 'notificator-project' ); ?></p>
										</div>
									</section>

									<section class="notificator-preference-card" data-notificator-settings-group="data">
										<div class="notificator-preference-card__heading"><span class="dashicons dashicons-list-view"></span><div><h4><?php esc_html_e( 'Activity log', 'notificator-project' ); ?></h4><p><?php esc_html_e( 'Store delivery results for troubleshooting and reporting.', 'notificator-project' ); ?></p></div><span id="notificator-log-card-status" class="notificator-card-status <?php echo $log_enabled ? 'is-active' : 'is-neutral'; ?>"><?php echo esc_html( $log_enabled ? __( 'On', 'notificator-project' ) : __( 'Off', 'notificator-project' ) ); ?></span></div>
										<div class="notificator-preference-actions"><button type="button" id="notificator-toggle-log" class="btn-secondary" data-log-enabled="<?php echo esc_attr( $log_enabled ? '1' : '0' ); ?>"><span class="dashicons <?php echo esc_attr( $log_enabled ? 'dashicons-no' : 'dashicons-yes' ); ?>"></span><?php echo esc_html( $log_enabled ? __( 'Disable activity log', 'notificator-project' ) : __( 'Enable activity log', 'notificator-project' ) ); ?></button><button type="button" id="notificator-export-log" class="btn-secondary"><span class="dashicons dashicons-media-spreadsheet"></span><?php esc_html_e( 'Export log CSV', 'notificator-project' ); ?></button></div>
									</section>

									<section class="notificator-preference-card notificator-preference-card--danger" data-notificator-settings-group="data">
										<div class="notificator-preference-card__heading"><span class="dashicons dashicons-image-rotate"></span><div><h4><?php esc_html_e( 'Reset test data', 'notificator-project' ); ?></h4><p><?php esc_html_e( 'Remove notifications, activity, scan results, observation data, and preferences. API keys and the MQTT connection are always kept.', 'notificator-project' ); ?></p></div></div>
										<button type="button" id="notificator-reset-test-data" class="btn-secondary btn-secondary--danger"><span class="dashicons dashicons-image-rotate"></span><?php esc_html_e( 'Reset plugin data', 'notificator-project' ); ?></button>
									</section>
								</div>
							</div>
						</section>


						<div data-notificator-workspace="notifications">
							<?php $this->render_hooks_field(); ?>
						</div>

						<div data-notificator-workspace="activity">
							<?php $this->render_log_section(); ?>
						</div>
						<?php $this->render_developer_section(); ?>
						<?php $this->render_help_section(); ?>

					</div>
				</div>

			</form>
		</div>
		<?php
	}

	/**
	 * Render the operational Overview workspace.
	 *
	 * @param array $options Saved plugin options.
	 * @param bool  $has_api_key Whether at least one connection exists.
	 * @return void
	 */
	private function render_overview_section( $options, $has_api_key ) {
		$hooks                    = isset( $options['hooks'] ) && is_array( $options['hooks'] ) ? $options['hooks'] : array();
		$active_hooks             = array_filter(
			$hooks,
			static function ( $hook ) {
				return is_array( $hook ) && ! empty( $hook['enabled'] );
			}
		);
		$health                   = $this->plugin->get_admin_health();
		$queue                    = isset( $health['queue'] ) && is_array( $health['queue'] ) ? $health['queue'] : array();
		$pending                  = isset( $queue['pending'] ) ? (int) $queue['pending'] : 0;
		$last_scan                = isset( $health['last_scan_at'] ) ? (int) $health['last_scan_at'] : 0;
		$unscanned_active_plugins = $this->plugin->get_unscanned_active_plugins();
		$unscanned_plugin_count   = count( $unscanned_active_plugins );
		$scan_complete            = $last_scan && empty( $unscanned_active_plugins );
		$last_test                = isset( $health['last_test_status'] ) ? (string) $health['last_test_status'] : '';
		$delivery                 = isset( $health['last_delivery_status'] ) ? (string) $health['last_delivery_status'] : '';
		$saved_keys               = isset( $options['api_keys'] ) && is_array( $options['api_keys'] ) ? array_values( $options['api_keys'] ) : array();
		if ( empty( $saved_keys ) && ! empty( $options['api_key'] ) ) {
			$saved_keys = array( (string) $options['api_key'] );
		}
		$saved_key_states  = isset( $options['api_key_enabled'] ) && is_array( $options['api_key_enabled'] ) ? array_values( $options['api_key_enabled'] ) : array();
		$enabled_key_count = 0;
		foreach ( $saved_keys as $key_index => $saved_key ) {
			if ( '' !== trim( (string) $saved_key ) && ( ! isset( $saved_key_states[ $key_index ] ) || (bool) $saved_key_states[ $key_index ] ) ) {
				++$enabled_key_count;
			}
		}
		$disabled_key_count = max( 0, count( $saved_keys ) - $enabled_key_count );
		if ( ! empty( $unscanned_active_plugins ) ) {
			$scan_label = __( 'Scan recommended', 'notificator-project' );
		} elseif ( $last_scan ) {
			/* translators: %s: Human-readable elapsed time, such as "5 minutes". */
			$scan_label = sprintf( __( '%s ago', 'notificator-project' ), human_time_diff( $last_scan, time() ) );
		} else {
			$scan_label = __( 'Not scanned yet', 'notificator-project' );
		}
		$scan_step_title       = __( 'Discover site events', 'notificator-project' );
		$scan_step_description = __( 'Scan active plugins for events and ready-made templates.', 'notificator-project' );
		if ( 1 === $unscanned_plugin_count ) {
			/* translators: %s: Name of a newly activated plugin. */
			$scan_step_title       = sprintf( __( 'Discover events from %s', 'notificator-project' ), (string) $unscanned_active_plugins[0]['name'] );
			$scan_step_description = __( 'This plugin was activated after your last scan. Scan it to add its events and templates.', 'notificator-project' );
		} elseif ( 1 < $unscanned_plugin_count ) {
			$scan_step_title       = __( 'Discover events from new plugins', 'notificator-project' );
			$scan_step_description = sprintf(
				/* translators: %d: Number of newly activated plugins. */
				__( '%d plugins were activated after your last scan. Scan them to add their events and templates.', 'notificator-project' ),
				$unscanned_plugin_count
			);
		}
		$disabled_key_message = sprintf(
			/* translators: %d: Number of disabled API keys. */
			_n(
				'%d API key is turned off. Events for that destination will not be delivered.',
				'%d API keys are turned off. Events for those destinations will not be delivered.',
				$disabled_key_count,
				'notificator-project'
			),
			$disabled_key_count
		);
		$connection_label      = $has_api_key
			? ( 0 === $enabled_key_count ? __( 'Delivery paused', 'notificator-project' ) : ( $disabled_key_count ? __( 'Partially active', 'notificator-project' ) : ( 'failed' === $last_test ? __( 'Needs attention', 'notificator-project' ) : __( 'Connected', 'notificator-project' ) ) ) )
			: __( 'Dashboard only', 'notificator-project' );
		$recent_log            = get_option( 'notificator_companion_notification_log', array() );
		$recent_log            = is_array( $recent_log ) ? array_slice( array_reverse( array_values( $recent_log ) ), 0, 5 ) : array();
		$delivery_sample       = is_array( $recent_log ) ? $recent_log : array();
		$successful_deliveries = count(
			array_filter(
				$delivery_sample,
				static function ( $entry ) {
					$status = is_array( $entry ) && isset( $entry['status'] ) ? (string) $entry['status'] : '';
						return in_array( $status, array( 'delivered', 'sent', 'dashboard_only' ), true );
				}
			)
		);
		$delivery_rate         = count( $delivery_sample ) ? (int) round( ( $successful_deliveries / count( $delivery_sample ) ) * 100 ) : 0;
		?>
		<section class="notificator-overview" id="overview" data-notificator-workspace="overview" aria-label="<?php esc_attr_e( 'System overview', 'notificator-project' ); ?>">
			<div class="notificator-health-grid">
				<div class="notificator-health-card">
					<span class="notificator-health-icon dashicons dashicons-admin-network" aria-hidden="true"></span>
					<div><span><?php esc_html_e( 'Connection', 'notificator-project' ); ?></span><strong id="notificator-overview-connection-status"><?php echo esc_html( $connection_label ); ?></strong></div>
				</div>
				<div class="notificator-health-card">
					<span class="notificator-health-icon dashicons dashicons-megaphone" aria-hidden="true"></span>
					<div><span><?php esc_html_e( 'Active notifications', 'notificator-project' ); ?></span><strong><?php echo esc_html( count( $active_hooks ) ); ?></strong></div>
				</div>
				<div class="notificator-health-card">
					<span class="notificator-health-icon dashicons dashicons-update" aria-hidden="true"></span>
					<div><span><?php esc_html_e( 'Plugin scan', 'notificator-project' ); ?></span><strong id="notificator-overview-scan-status"><?php echo esc_html( $scan_label ); ?></strong></div>
				</div>
				<div class="notificator-health-card">
					<span class="notificator-health-icon dashicons dashicons-clock" aria-hidden="true"></span>
					<div><span><?php esc_html_e( 'Delivery queue', 'notificator-project' ); ?></span><strong><?php echo esc_html( $pending ); ?> <?php esc_html_e( 'pending', 'notificator-project' ); ?></strong></div>
				</div>
			</div>
			<div id="notificator-overview-key-alert" class="notificator-overview-alert <?php echo 0 === $enabled_key_count ? 'is-danger' : 'is-warning'; ?>" <?php echo 0 === $disabled_key_count ? 'hidden' : ''; ?>><span class="dashicons dashicons-warning"></span><div><strong data-key-alert-title><?php echo 0 === $enabled_key_count ? esc_html__( 'Notification delivery is paused', 'notificator-project' ) : esc_html__( 'Some destinations are paused', 'notificator-project' ); ?></strong><p data-key-alert-message><?php echo esc_html( $disabled_key_message ); ?></p></div></div>

			<div class="notificator-overview-grid">
				<div class="notificator-overview-panel">
					<div class="notificator-panel-heading">
						<div><h3><?php esc_html_e( 'Getting started', 'notificator-project' ); ?></h3><p><?php esc_html_e( 'Complete these steps once, then this becomes your health dashboard.', 'notificator-project' ); ?></p></div>
					</div>
					<ol class="notificator-checklist">
						<li id="notificator-overview-scan-step" class="<?php echo $scan_complete ? 'is-complete' : ''; ?>" data-scan-complete-title="<?php esc_attr_e( 'Discover site events', 'notificator-project' ); ?>" data-scan-complete-description="<?php esc_attr_e( 'Scan active plugins for events and ready-made templates.', 'notificator-project' ); ?>"><span class="dashicons <?php echo $scan_complete ? 'dashicons-yes-alt' : 'dashicons-marker'; ?>"></span><div><strong data-notificator-scan-step-title><?php echo esc_html( $scan_step_title ); ?></strong><small data-notificator-scan-step-description><?php echo esc_html( $scan_step_description ); ?></small></div><button type="button" id="auto-scan-btn"><?php esc_html_e( 'Scan', 'notificator-project' ); ?></button></li>
						<li class="<?php echo ! empty( $hooks ) ? 'is-complete' : ''; ?>"><span class="dashicons <?php echo ! empty( $hooks ) ? 'dashicons-yes-alt' : 'dashicons-marker'; ?>"></span><div><strong><?php esc_html_e( 'Create a notification', 'notificator-project' ); ?></strong><small><?php esc_html_e( 'Choose a template or build one from a WordPress event.', 'notificator-project' ); ?></small></div><a href="#notifications" data-notificator-workspace-tab="notifications"><?php esc_html_e( 'Open', 'notificator-project' ); ?></a></li>
					</ol>
				</div>
				<div class="notificator-overview-panel notificator-overview-panel--status">
					<div class="notificator-panel-heading"><div><h3><?php esc_html_e( 'Latest status', 'notificator-project' ); ?></h3><p><?php esc_html_e( 'Live operational signals from this site.', 'notificator-project' ); ?></p></div></div>
					<dl class="notificator-status-list">
						<div><dt><?php esc_html_e( 'Last delivery', 'notificator-project' ); ?></dt><dd><span class="badge <?php echo in_array( $delivery, array( 'delivered', 'partial' ), true ) ? 'badge-success' : 'badge-info'; ?>"><?php echo esc_html( $delivery ? ucfirst( $delivery ) : __( 'No activity', 'notificator-project' ) ); ?></span></dd></div>
						<div><dt><?php esc_html_e( 'Events discovered', 'notificator-project' ); ?></dt><dd id="notificator-overview-events-discovered"><?php echo esc_html( isset( $health['last_scan_hooks'] ) ? (int) $health['last_scan_hooks'] : 0 ); ?></dd></div>
						<div><dt><?php esc_html_e( 'Configured notifications', 'notificator-project' ); ?></dt><dd><?php echo esc_html( count( $hooks ) ); ?></dd></div>
					</dl>
					<a class="btn-secondary btn-secondary--compact" href="#activity" data-notificator-workspace-tab="activity"><?php esc_html_e( 'View activity', 'notificator-project' ); ?></a>
				</div>
			</div>

			<div class="notificator-overview-grid notificator-overview-grid--operations">
				<div class="notificator-overview-panel">
					<div class="notificator-panel-heading"><div><h3><?php esc_html_e( 'Recent events', 'notificator-project' ); ?></h3><p><?php esc_html_e( 'The latest notification activity from this site.', 'notificator-project' ); ?></p></div><a href="#activity" data-notificator-workspace-tab="activity"><?php esc_html_e( 'View all', 'notificator-project' ); ?></a></div>
					<?php if ( $recent_log ) : ?>
						<ul class="notificator-recent-events">
							<?php foreach ( $recent_log as $recent_entry ) : ?>
								<?php
								$recent_status  = isset( $recent_entry['status'] ) ? (string) $recent_entry['status'] : ( ! empty( $recent_entry['sent'] ) ? 'sent' : 'not_sent' );
								$recent_success = in_array( $recent_status, array( 'delivered', 'sent', 'dashboard_only' ), true );
								if ( ! empty( $recent_entry['timestamp'] ) ) {
									/* translators: %s: Human-readable elapsed time, such as "5 minutes". */
									$recent_time = sprintf( __( '%s ago', 'notificator-project' ), human_time_diff( strtotime( (string) $recent_entry['timestamp'] ), time() ) );
								} else {
									$recent_time = __( 'Unknown time', 'notificator-project' );
								}
								?>
								<li><span class="notificator-event-dot <?php echo $recent_success ? 'is-success' : 'is-warning'; ?>"></span><div><strong><?php echo esc_html( ! empty( $recent_entry['title'] ) ? (string) $recent_entry['title'] : (string) ( $recent_entry['hook_name'] ?? __( 'WordPress event', 'notificator-project' ) ) ); ?></strong><small><?php echo esc_html( $recent_time ); ?> · <?php echo esc_html( ucfirst( str_replace( '_', ' ', $recent_status ) ) ); ?></small></div></li>
							<?php endforeach; ?>
						</ul>
					<?php else : ?>
						<div class="notificator-overview-empty"><span class="dashicons dashicons-bell"></span><p><?php esc_html_e( 'No events yet. Your latest deliveries will appear here.', 'notificator-project' ); ?></p></div>
					<?php endif; ?>
				</div>
				<div class="notificator-overview-panel">
					<div class="notificator-panel-heading"><div><h3><?php esc_html_e( 'Delivery snapshot', 'notificator-project' ); ?></h3><p><?php esc_html_e( 'A quick signal based on your five latest events.', 'notificator-project' ); ?></p></div></div>
					<div class="notificator-delivery-snapshot"><strong><?php echo esc_html( $delivery_rate ); ?>%</strong><span><?php esc_html_e( 'delivered successfully', 'notificator-project' ); ?></span><div><i style="width: <?php echo esc_attr( $delivery_rate ); ?>%"></i></div></div>
					<div class="notificator-quick-actions"><button type="button" data-notificator-create><span class="dashicons dashicons-plus-alt2"></span><?php esc_html_e( 'New notification', 'notificator-project' ); ?></button><button type="button" id="notificator-overview-tools"><span class="dashicons dashicons-admin-tools"></span><?php esc_html_e( 'Open tools', 'notificator-project' ); ?></button><a href="#support" data-notificator-workspace-tab="support"><span class="dashicons dashicons-sos"></span><?php esc_html_e( 'Get support', 'notificator-project' ); ?></a></div>
				</div>
			</div>
		</section>
		<?php
	}

	/**
	 * Resolve section icon class for sidebar and section headers.
	 *
	 * @param string $section Section key.
	 * @return string
	 */
	private function get_section_icon_class( $section ) {
		$icons = array(
			'api'              => 'dashicons-admin-network',
			'api_locked'       => 'dashicons-lock',
			'templates'        => 'dashicons-media-code',
			'templates_locked' => 'dashicons-lock',
			'builder'          => 'dashicons-bell',
			'builder_locked'   => 'dashicons-lock',
			'log'              => 'dashicons-list-view',
			'help'             => 'dashicons-sos',
		);

		return isset( $icons[ $section ] ) ? $icons[ $section ] : 'dashicons-admin-generic';
	}

	/**
	 * Render scan controls (button + toggle).
	 *
	 * @param bool $disabled Whether the scan action is disabled.
	 * @return void
	 */
	private function render_scan_controls( $disabled = false ) {
		?>
		<div class="notificator-scan-controls">
			<button type="button" id="scan-plugins-btn" class="btn-secondary notificator-scan-btn" <?php echo $disabled ? 'disabled' : ''; ?>>
				<span class="dashicons dashicons-update"></span>
				<?php esc_html_e( 'Scan Plugins', 'notificator-project' ); ?>
			</button>
		</div>
		<?php
	}

	/**
	 * Render scan modal markup.
	 *
	 * This should be rendered outside of sticky containers to avoid stacking/overflow issues.
	 */
	private function render_scan_modal() {
		$nonce = wp_create_nonce( 'notificator_companion_refresh_hooks' );
		?>
		<input type="hidden" id="notificator_companion_scan_nonce" value="<?php echo esc_attr( $nonce ); ?>">

		<!-- Scan Modal -->
		<div id="scan-modal" class="hidden fixed inset-0 z-50 overflow-y-auto scan-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="notificator-scan-modal-title">
			<div class="flex items-center justify-center min-h-screen px-4">
				<div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6 relative">
					<button type="button" id="notificator-scan-modal-close" class="btn-icon notificator-scan-modal-close" aria-label="<?php esc_attr_e( 'Close scan', 'notificator-project' ); ?>"><span class="dashicons dashicons-no-alt"></span></button>
					<div id="scan-progress">
						<div class="text-center notificator-scan-progress-content">
							<div class="w-16 h-16 bg-indigo-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
								<div class="loading-spinner"></div>
							</div>
							<h3 id="notificator-scan-modal-title" class="text-lg font-semibold text-gray-900 mb-2"><?php esc_html_e( 'Discovering events…', 'notificator-project' ); ?></h3>
							<p id="scan-current-plugin" class="text-sm text-gray-600"><?php esc_html_e( 'Preparing the plugin scan…', 'notificator-project' ); ?></p>
							<div class="notificator-scan-progress-track" aria-hidden="true"><span id="scan-progress-bar"></span></div>
							<p class="notificator-scan-progress-help"><?php esc_html_e( 'You can close this window. The scan will continue in the background.', 'notificator-project' ); ?></p>
						</div>
					</div>

					<div id="scan-results" class="hidden">
						<div id="scan-success-message" class="hidden text-center">
							<div class="w-16 h-16 bg-green-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
								<svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
									<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
								</svg>
							</div>
							<h3 class="text-lg font-semibold text-gray-900 mb-2"><?php esc_html_e( 'Scan Complete!', 'notificator-project' ); ?></h3>
							<p class="text-sm text-gray-600">
								<?php esc_html_e( 'Found', 'notificator-project' ); ?> <span id="total-hooks" class="font-semibold">0</span> <?php esc_html_e( 'events from', 'notificator-project' ); ?> <span id="total-plugins" class="font-semibold">0</span> <?php esc_html_e( 'plugins', 'notificator-project' ); ?>
							</p>
						</div>

						<div id="scan-error-message" class="hidden text-center text-red-600">
							<div class="w-16 h-16 bg-red-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
								<svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
									<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
								</svg>
							</div>
							<h3 class="text-lg font-semibold text-gray-900 mb-2"><?php esc_html_e( 'Scan Failed', 'notificator-project' ); ?></h3>
							<p id="scan-error-detail" class="text-sm"></p>
						</div>
						<div class="notificator-scan-result-actions">
							<button type="button" id="notificator-scan-modal-done" class="btn-secondary"><?php esc_html_e( 'Close', 'notificator-project' ); ?></button>
							<a id="notificator-scan-review" class="btn-primary hidden" href="<?php echo esc_url( admin_url( 'admin.php?page=notificator-notifications#notificator-discovery' ) ); ?>"><?php esc_html_e( 'Review discoveries', 'notificator-project' ); ?></a>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render import scenarios modal markup.
	 *
	 * Rendered outside sticky containers to avoid stacking/overflow issues.
	 */
	private function render_import_hooks_modal() {
		?>
		<!-- Import Scenarios Modal -->
		<div id="notificator-import-modal" class="hidden fixed inset-0 z-50 overflow-y-auto scan-modal-backdrop">
			<div class="flex items-center justify-center min-h-screen px-4">
				<div class="bg-white rounded-2xl shadow-2xl max-w-lg w-full p-6">
					<div class="flex items-start justify-between gap-4">
						<div class="min-w-0">
							<h3 class="text-lg font-semibold text-gray-900 mb-1"><?php esc_html_e( 'Import Scenarios', 'notificator-project' ); ?></h3>
							<p class="text-sm text-gray-600"><?php esc_html_e( 'Upload a JSON export from another site to migrate your scenario builder rules. API keys are not imported.', 'notificator-project' ); ?></p>
						</div>
						<button type="button" id="notificator-import-modal-close" class="btn-secondary" aria-label="<?php echo esc_attr( __( 'Close', 'notificator-project' ) ); ?>">
							<span class="dashicons dashicons-no-alt"></span>
						</button>
					</div>

					<div class="mt-4 space-y-4">
						<div>
							<label class="block text-sm font-semibold text-gray-700 mb-2" for="notificator-import-file"><?php esc_html_e( 'Scenario JSON file', 'notificator-project' ); ?></label>
							<input type="file" id="notificator-import-file" accept="application/json,.json" class="w-full px-3 py-2 border border-gray-300 rounded-lg" />
							<p class="text-xs text-gray-500 mt-2" id="notificator-import-file-hint"><?php esc_html_e( 'Choose a file exported with “Export Scenarios”.', 'notificator-project' ); ?></p>
						</div>

						<div class="border border-gray-200 rounded-xl p-4">
							<div class="text-sm font-semibold text-gray-900 mb-2"><?php esc_html_e( 'Import mode', 'notificator-project' ); ?></div>
							<label class="flex items-center gap-2 mb-2">
								<input type="radio" name="notificator-import-mode" value="merge" checked />
								<span class="text-sm text-gray-700"><?php esc_html_e( 'Merge (recommended): keep existing scenarios and append imported ones.', 'notificator-project' ); ?></span>
							</label>
							<label class="flex items-center gap-2">
								<input type="radio" name="notificator-import-mode" value="replace" />
								<span class="text-sm text-gray-700"><?php esc_html_e( 'Replace: delete all existing scenarios and use the imported file.', 'notificator-project' ); ?></span>
							</label>
							<div class="mt-3 hidden" id="notificator-import-replace-warning">
								<div class="notice notice-warning inline notice-inline-warning">
									<p><?php esc_html_e( 'Replace will remove all existing scenarios on this site.', 'notificator-project' ); ?></p>
								</div>
								<label class="flex items-start gap-2 mt-3 cursor-pointer">
									<input type="checkbox" id="notificator-import-confirm-replace" value="1" class="mt-0.5" />
									<span class="text-sm text-gray-700"><?php esc_html_e( 'I understand, replace my scenarios.', 'notificator-project' ); ?></span>
								</label>
							</div>
						</div>

						<div id="notificator-import-status" class="text-sm text-gray-700" aria-live="polite" hidden></div>

						<div class="flex items-center justify-end gap-2">
							<button type="button" id="notificator-import-cancel" class="btn-secondary"><?php esc_html_e( 'Cancel', 'notificator-project' ); ?></button>
							<button type="button" id="notificator-import-confirm" class="btn-primary btn-primary--compact">
								<span class="dashicons dashicons-upload"></span>
								<?php esc_html_e( 'Import', 'notificator-project' ); ?>
							</button>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render hooks field with Alpine.js-powered scenario builder
	 */
	public function render_hooks_field() {
		$options = get_option( $this->option_name );
		if ( ! is_array( $options ) ) {
			$options = array();
		}
		$hooks               = isset( $options['hooks'] ) ? $options['hooks'] : array();
		$api_keys            = isset( $options['api_keys'] ) && is_array( $options['api_keys'] ) ? array_values( $options['api_keys'] ) : array();
		$api_key_states      = isset( $options['api_key_enabled'] ) && is_array( $options['api_key_enabled'] ) ? array_values( $options['api_key_enabled'] ) : array();
		$has_remote_delivery = false;
		foreach ( $api_keys as $api_key_index => $api_key ) {
			if ( '' !== trim( (string) $api_key ) && ( ! isset( $api_key_states[ $api_key_index ] ) || (bool) $api_key_states[ $api_key_index ] ) ) {
				$has_remote_delivery = true;
				break;
			}
		}
		if ( ! $has_remote_delivery && empty( $api_keys ) && ! empty( $options['api_key'] ) ) {
			$has_remote_delivery = true;
		}
		$available_plugins        = $this->plugin->get_available_plugins_with_hooks();
		$last_scan                = get_option( 'notificator_companion_last_scan', 0 );
		$unscanned_active_plugins = $this->plugin->get_unscanned_active_plugins();
		$show_scan_recommendation = ( empty( $last_scan ) && 1 === count( $available_plugins ) ) || ! empty( $unscanned_active_plugins );

		// Build active status for plugins.
		$plugin_active_status = array();
		foreach ( $available_plugins as $key => $plugin ) {
			$plugin_active_status[ $key ] = $this->plugin->is_plugin_active_check( $plugin['file'] );
		}

		// Build active status for hooks.
		$hook_active_status = array();
		foreach ( $hooks as $index => $hook ) {
			$hook_active_status[ $index ] = $this->plugin->is_hook_active( $hook['hook_name'] );
		}

		// Prepare JSON data for Alpine with proper flags.
		$hooks_json             = wp_json_encode( array_values( $hooks ), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP );
		$available_plugins_json = wp_json_encode( $available_plugins, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP );
		$plugin_active_json     = wp_json_encode( $plugin_active_status, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP );
		$hook_active_json       = wp_json_encode( $hook_active_status, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP );

		?>
		<div x-data="window.initScenarioBuilder(
			<?php echo esc_attr( $hooks_json ); ?>,
			<?php echo esc_attr( $available_plugins_json ); ?>,
			<?php echo esc_attr( $plugin_active_json ); ?>,
			<?php echo esc_attr( $hook_active_json ); ?>,
			<?php echo esc_attr( wp_json_encode( $this->option_name ) ); ?>,
			<?php echo $has_remote_delivery ? 'true' : 'false'; ?>
		)" @notificator:add-scenario.window="openAddModal()" class="space-y-5 mt-6">
			<nav class="notificator-notification-tabs" aria-label="<?php esc_attr_e( 'Notification views', 'notificator-project' ); ?>">
				<button type="button" @click="setNotificationView('created')" :class="notificationView === 'created' ? 'is-active' : ''" :aria-current="notificationView === 'created' ? 'page' : null">
					<span class="dashicons dashicons-bell"></span><span><strong><?php esc_html_e( 'Created notifications', 'notificator-project' ); ?></strong><small x-text="hooks.length + ' configured'"></small></span>
				</button>
				<button type="button" @click="setNotificationView('templates')" :class="notificationView === 'templates' ? 'is-active' : ''" :aria-current="notificationView === 'templates' ? 'page' : null">
					<span class="dashicons dashicons-layout"></span><span><strong><?php esc_html_e( 'Templates', 'notificator-project' ); ?></strong><small><?php esc_html_e( 'Ready-made starting points', 'notificator-project' ); ?></small></span>
				</button>
				<button type="button" @click="setNotificationView('discover')" :class="notificationView === 'discover' ? 'is-active' : ''" :aria-current="notificationView === 'discover' ? 'page' : null">
					<span class="dashicons dashicons-search"></span><span><strong><?php esc_html_e( 'Discover events', 'notificator-project' ); ?></strong><small><?php esc_html_e( 'Review scanned possibilities', 'notificator-project' ); ?></small></span>
				</button>
			</nav>

			<?php if ( $show_scan_recommendation ) : ?>
				<?php $this->render_scan_recommendation( $unscanned_active_plugins ); ?>
			<?php endif; ?>

			<div x-show="notificationView === 'discover'" x-cloak>
				<?php $this->render_discovery_inbox( $available_plugins, $hooks ); ?>
			</div>

			<!-- Hidden inputs for form submission -->
			<div class="hidden">
				<template x-for="(hook, index) in hooks" :key="'hidden-' + index">
					<div>
						<input type="hidden" :name="'<?php echo esc_attr( $this->option_name ); ?>[hooks][' + index + '][hook_name]'" :value="hook.hook_name">
						<input type="hidden" :name="'<?php echo esc_attr( $this->option_name ); ?>[hooks][' + index + '][description]'" :value="hook.description">
						<input type="hidden" :name="'<?php echo esc_attr( $this->option_name ); ?>[hooks][' + index + '][enabled]'" :value="hook.enabled ? '1' : '0'">
						<input type="hidden" :name="'<?php echo esc_attr( $this->option_name ); ?>[hooks][' + index + '][scenario_name]'" :value="hook.scenario_name || ''">
						<input type="hidden" :name="'<?php echo esc_attr( $this->option_name ); ?>[hooks][' + index + '][scenario_notes]'" :value="hook.scenario_notes || ''">
						<input type="hidden" :name="'<?php echo esc_attr( $this->option_name ); ?>[hooks][' + index + '][plugin_key]'" :value="hook.plugin_key || ''">
						<input type="hidden" :name="'<?php echo esc_attr( $this->option_name ); ?>[hooks][' + index + '][plugin_name]'" :value="hook.plugin_name || ''">
						<input type="hidden" :name="'<?php echo esc_attr( $this->option_name ); ?>[hooks][' + index + '][severity]'" :value="hook.severity || 'info'">
						<input type="hidden" :name="'<?php echo esc_attr( $this->option_name ); ?>[hooks][' + index + '][send_dashboard]'" :value="hook.send_dashboard ? '1' : '0'">
						<input type="hidden" :name="'<?php echo esc_attr( $this->option_name ); ?>[hooks][' + index + '][send_push]'" :value="hook.send_push ? '1' : '0'">
						<input type="hidden" :name="'<?php echo esc_attr( $this->option_name ); ?>[hooks][' + index + '][send_mqtt]'" :value="hook.send_mqtt ? '1' : '0'">
						<!-- Hook metadata (for conditions support) -->
						<template x-if="hook.hook_meta">
							<input type="hidden" :name="'<?php echo esc_attr( $this->option_name ); ?>[hooks][' + index + '][hook_meta]'" :value="JSON.stringify(hook.hook_meta)">
						</template>
						<!-- Conditions hidden inputs -->
						<template x-if="hook.conditions && hook.conditions.length">
							<template x-for="(condition, cIndex) in hook.conditions" :key="'hidden-cond-' + index + '-' + cIndex">
								<div>
									<input type="hidden" :name="'<?php echo esc_attr( $this->option_name ); ?>[hooks][' + index + '][conditions][' + cIndex + '][field]'" :value="condition.field">
									<input type="hidden" :name="'<?php echo esc_attr( $this->option_name ); ?>[hooks][' + index + '][conditions][' + cIndex + '][operator]'" :value="condition.operator">
									<input type="hidden" :name="'<?php echo esc_attr( $this->option_name ); ?>[hooks][' + index + '][conditions][' + cIndex + '][value]'" :value="condition.value">
								</div>
							</template>
						</template>
					</div>
				</template>
			</div>

			<!-- Scenarios Wrapper - Full Width Container -->
			<div class="scenarios-wrapper">
				<div class="scenarios-container">

					<!-- Templates -->
					<div x-show="notificationView === 'templates'" x-cloak class="scenario-section notificator-section mt-6" id="notificator-templates" data-notificator-section="templates">
						<div class="notificator-scenario-head notificator-scenario-head--templates">
							<div class="flex items-start sm:items-center justify-between gap-3 flex-wrap">
								<div class="flex items-center gap-3 min-w-0">
									<div class="notificator-section-icon">
										<span class="dashicons <?php echo esc_attr( $this->get_section_icon_class( 'templates' ) ); ?> text-white"></span>
									</div>
									<div class="min-w-0">
									<h3 class="text-base font-semibold text-white"><?php esc_html_e( 'Start with a template', 'notificator-project' ); ?></h3>
									<p class="text-xs text-white text-opacity-70" x-text="getFilteredTemplates().length + ' curated option' + (getFilteredTemplates().length === 1 ? '' : 's') + ' for your active plugins'"></p>
									</div>
								</div>

								<div class="flex items-center gap-3 flex-wrap justify-end notificator-templates-controls">
									<?php $this->render_scan_controls(); ?>
									<div>
										<label for="notificator-template-category-filter" class="screen-reader-text"><?php esc_html_e( 'Filter templates by category', 'notificator-project' ); ?></label>
										<select id="notificator-template-category-filter" x-model="templateCategoryFilter" @change="onTemplateFacetChange()" class="notificator-section-control notificator-section-control--select notificator-templates-filter-select">
											<template x-for="category in getTemplateCategoryFilterOptions()" :key="'template-category-' + category.value">
												<option :value="category.value" x-text="category.label"></option>
											</template>
										</select>
									</div>
									<div>
										<label for="notificator-template-readiness-filter" class="screen-reader-text"><?php esc_html_e( 'Filter templates by setup required', 'notificator-project' ); ?></label>
										<select id="notificator-template-readiness-filter" x-model="templateReadinessFilter" @change="onTemplateFacetChange()" class="notificator-section-control notificator-section-control--select notificator-templates-filter-select">
											<option value="__all__"><?php esc_html_e( 'Any setup', 'notificator-project' ); ?></option>
											<option value="instant"><?php esc_html_e( 'Ready now', 'notificator-project' ); ?></option>
											<option value="configure"><?php esc_html_e( 'Needs a setting', 'notificator-project' ); ?></option>
										</select>
									</div>
									<div>
										<label for="notificator-template-plugin-filter" class="screen-reader-text"><?php esc_html_e( 'Filter templates by active plugin', 'notificator-project' ); ?></label>
										<select
											id="notificator-template-plugin-filter"
											x-model="templatePluginFilter"
											@change="onTemplatePluginFilterChange()"
											class="notificator-section-control notificator-section-control--select notificator-templates-filter-select">
											<template x-for="plugin in getTemplatePluginFilterOptions()" :key="'template-plugin-filter-' + plugin.value">
												<option :value="plugin.value" x-text="plugin.label"></option>
											</template>
										</select>
									</div>
									<div>
										<label for="notificator-template-per-page" class="screen-reader-text"><?php esc_html_e( 'Templates shown per page', 'notificator-project' ); ?></label>
										<select
											id="notificator-template-per-page"
											x-model="templatesPerPage"
											@change="onTemplatesPerPageChange()"
											class="notificator-section-control notificator-section-control--select notificator-templates-filter-select">
											<template x-for="count in getTemplatesPerPageOptions()" :key="'templates-per-page-' + count">
												<option :value="count" x-text="'Show ' + count"></option>
											</template>
										</select>
									</div>
									<div class="relative notificator-templates-search notificator-search notificator-search--on-dark">
										<input type="text"
											x-model="templateSearchQuery"
											@input="onTemplateSearchChange()"
											placeholder="<?php esc_attr_e( 'Search templates...', 'notificator-project' ); ?>"
											class="notificator-section-control notificator-section-control--search notificator-templates-search-input">
										<svg class="w-4 h-4 absolute left-3 top-1/2 transform -translate-y-1/2 notificator-search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
											<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
										</svg>
									</div>
								</div>
							</div>
						</div>

						<div class="card-body">
							<template x-if="getFilteredTemplates().length === 0">
								<div class="text-center py-12">
									<div class="w-16 h-16 bg-gray-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
										<svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
											<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
										</svg>
									</div>
									<p class="text-gray-900 font-medium mb-1"><?php esc_html_e( 'No templates found', 'notificator-project' ); ?></p>
									<p class="text-xs text-gray-500"><?php esc_html_e( 'Try a different search term or clear one of the filters.', 'notificator-project' ); ?></p>
								</div>
							</template>

							<template x-if="getFilteredTemplates().length > 0">
								<div>
											<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
										<template x-for="template in getPaginatedTemplates()" :key="'template-' + template.hook_name + '-' + template.title">
													<button @click="useTemplate(template)" type="button" class="notificator-template-card cursor-pointer text-left p-4 rounded-xl bg-white border-2 border-gray-200 hover:border-indigo-400 transition-all group focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500/40">
														<div class="flex items-start gap-3 mb-3">
															<div class="w-12 h-12 rounded-lg bg-linear-to-br from-indigo-50 to-purple-50 flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform">
																<span class="text-2xl" x-text="template.icon"></span>
															</div>
															<div class="flex-1 min-w-0">
																<div class="flex items-start justify-between gap-2 mb-1">
																	<div class="text-sm font-semibold text-gray-900 line-clamp-2" x-text="template.title"></div>
																	<span x-show="template.featured" class="notificator-template-badge notificator-template-badge--featured"><?php esc_html_e( 'Recommended', 'notificator-project' ); ?></span>
																</div>
																<p class="notificator-template-description" x-text="template.description"></p>
																<div class="mt-2 flex flex-wrap gap-1.5">
																	<span class="inline-flex items-center text-[11px] font-medium text-slate-600 bg-slate-100 border border-slate-200 rounded-full px-2 py-0.5" x-text="template.plugin_name || template.required_plugin"></span>
																	<span class="notificator-template-badge" x-text="template.category_label"></span>
																</div>
															</div>
														</div>
													<div class="notificator-template-card__footer">
														<span class="notificator-template-readiness" :class="'notificator-template-readiness--' + template.readiness" x-text="template.readiness_label"></span>
														<span class="notificator-template-severity" :class="'notificator-template-severity--' + template.severity" x-text="template.severity"></span>
														<span class="notificator-template-use"><?php esc_html_e( 'Use template', 'notificator-project' ); ?> →</span>
													</div>
													<code class="notificator-template-hook" x-text="template.hook_name"></code>
											</button>
										</template>
									</div>

									<div class="flex items-center justify-between pt-3 border-t border-gray-200">
										<div class="text-xs text-gray-500">
											<?php esc_html_e( 'Page', 'notificator-project' ); ?>
											<span x-text="templatePage"></span>
											<?php esc_html_e( 'of', 'notificator-project' ); ?>
											<span x-text="getTemplateTotalPages()"></span>
											<span class="ml-2">(<span x-text="getFilteredTemplates().length"></span> <?php esc_html_e( 'total', 'notificator-project' ); ?>)</span>
										</div>

										<div class="flex items-center gap-2">
											<button @click="prevTemplatePage()" type="button" :disabled="templatePage === 1" class="btn-secondary btn-secondary--compact">
												<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
													<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
												</svg>
												<?php esc_html_e( 'Previous', 'notificator-project' ); ?>
											</button>
											<button @click="nextTemplatePage()" type="button" :disabled="templatePage >= getTemplateTotalPages()" class="btn-secondary btn-secondary--compact">
												<?php esc_html_e( 'Next', 'notificator-project' ); ?>
												<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
													<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
												</svg>
											</button>
										</div>
									</div>
								</div>
							</template>
						</div>
					</div>

					<!-- Builder -->
					<div x-show="notificationView === 'created'" x-cloak class="scenario-section notificator-section mt-6" id="notificator-builder" data-notificator-section="builder">
						<div class="notificator-scenario-head notificator-scenario-head--builder">
							<div class="flex items-start sm:items-center justify-between gap-3 flex-wrap">
								<div class="flex items-center gap-3 min-w-0">
									<div class="notificator-section-icon">
										<span class="dashicons <?php echo esc_attr( $this->get_section_icon_class( 'builder' ) ); ?> text-white"></span>
									</div>
									<div class="min-w-0">
									<h3 class="text-base font-semibold text-white"><?php esc_html_e( 'Your notifications', 'notificator-project' ); ?></h3>
									<p class="text-xs text-white text-opacity-70" x-text="hooks.length + ' notification' + (hooks.length === 1 ? '' : 's')"></p>
									</div>
								</div>

								<div class="flex items-center gap-3 flex-wrap justify-end">
									<div class="relative notificator-search notificator-search--on-dark notificator-notification-search">
										<label class="screen-reader-text" for="notificator-notification-search"><?php esc_html_e( 'Search notifications', 'notificator-project' ); ?></label>
										<input id="notificator-notification-search" type="search" x-model="searchQuery" placeholder="<?php esc_attr_e( 'Search notifications…', 'notificator-project' ); ?>" class="notificator-section-control notificator-section-control--search" />
										<span class="dashicons dashicons-search notificator-search-icon" aria-hidden="true"></span>
									</div>
									<button @click="openAddModal()" type="button" class="btn-secondary">
										<span class="dashicons dashicons-plus-alt2"></span>
										<?php esc_html_e( 'Create notification', 'notificator-project' ); ?>
									</button>
								</div>
							</div>
						</div>

						<div class="card-body">
							<template x-if="hooks.length === 0">
								<div class="empty-state">
									<div class="empty-state-icon">
										<svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
											<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
										</svg>
									</div>
									<p class="empty-state-title"><?php esc_html_e( 'No notifications yet', 'notificator-project' ); ?></p>
									<p class="empty-state-description mb-4"><?php esc_html_e( 'Create your first notification from a template or WordPress event.', 'notificator-project' ); ?></p>
									<button @click="openAddModal()" type="button" class="btn-primary"><?php esc_html_e( 'Create Your First Notification', 'notificator-project' ); ?></button>
								</div>
							</template>

							<template x-if="hooks.length > 0">
								<div class="space-y-2">
									<template x-for="row in getFilteredScenarioRows()" :key="'scenario-row-' + row.index">
										<div x-data="{ hook: row.hook, index: row.index }" class="group flex items-center justify-between p-3 rounded-xl bg-white border border-purple-100 hover:bg-indigo-50/30 hover:border-purple-300 hover:shadow-sm transition-all" :class="isScenarioPluginInactive(hook) ? 'notificator-scenario--plugin-inactive' : ''">
											<div class="flex-1 min-w-0">
												<div class="flex items-center gap-2 mb-1">
													<span class="text-sm font-semibold text-gray-900 truncate" x-text="hook.scenario_name || hook.hook_name"></span>
													<code class="text-xs font-mono bg-purple-100 text-purple-700 px-2 py-0.5 rounded" x-text="hook.hook_name"></code>
													<span class="badge text-xs" :class="hook.enabled ? 'badge-success' : 'badge-warning'">
														<span class="w-1.5 h-1.5 rounded-full mr-1" :class="hook.enabled ? 'bg-green-500' : 'bg-gray-400'"></span>
														<span x-text="hook.enabled ? <?php echo esc_attr( wp_json_encode( __( 'Active', 'notificator-project' ) ) ); ?> : <?php echo esc_attr( wp_json_encode( __( 'Paused', 'notificator-project' ) ) ); ?>"></span>
													</span>
											<span class="badge text-xs" :class="(hook.severity || 'info') === 'critical' ? 'badge-danger' : ((hook.severity || 'info') === 'warning' ? 'badge-warning' : 'badge-info')">
												<span x-text="(hook.severity || 'info').charAt(0).toUpperCase() + (hook.severity || 'info').slice(1)"></span>
											</span>
											<span class="badge badge-info text-xs" x-show="hook.send_dashboard"><?php esc_html_e( 'Dashboard', 'notificator-project' ); ?></span>
											<span class="badge badge-info text-xs" x-show="hook.send_push"><?php esc_html_e( 'Push', 'notificator-project' ); ?></span>
											<span class="badge badge-info text-xs" x-show="hook.send_mqtt"><?php esc_html_e( 'MQTT', 'notificator-project' ); ?></span>
													<template x-if="getScenarioPluginStatus(hook) === 'inactive'">
														<span class="badge text-xs badge-warning" :title="getScenarioPluginName(hook) ? (<?php echo esc_attr( wp_json_encode( __( 'Plugin:', 'notificator-project' ) ) ); ?> + ' ' + getScenarioPluginName(hook)) : ''">
															<span x-text="getScenarioPluginBadgeLabel(hook)"></span>
														</span>
													</template>
													<template x-if="getScenarioPluginStatus(hook) === 'missing'">
														<span class="badge text-xs badge-warning" :title="getScenarioPluginName(hook) ? (<?php echo esc_attr( wp_json_encode( __( 'Plugin:', 'notificator-project' ) ) ); ?> + ' ' + getScenarioPluginName(hook)) : ''">
															<span x-text="getScenarioPluginBadgeLabel(hook)"></span>
														</span>
													</template>
												</div>
												<div class="flex items-center gap-2 text-xs text-gray-500">
													<span x-text="hook.description"></span>
													<template x-if="hook.scenario_notes"><span class="text-purple-600">• <span x-text="hook.scenario_notes"></span></span></template>
												</div>
											</div>
											<div class="ml-3 flex items-center gap-1 notificator-scenario-actions">
												<button @click="toggleScenario(index)" type="button" class="btn-secondary btn-secondary--compact" :aria-label="hook.enabled ? <?php echo esc_attr( wp_json_encode( __( 'Pause notification', 'notificator-project' ) ) ); ?> : <?php echo esc_attr( wp_json_encode( __( 'Enable notification', 'notificator-project' ) ) ); ?>">
													<span class="dashicons" :class="hook.enabled ? 'dashicons-controls-pause' : 'dashicons-controls-play'"></span>
													<span x-text="hook.enabled ? <?php echo esc_attr( wp_json_encode( __( 'Pause', 'notificator-project' ) ) ); ?> : <?php echo esc_attr( wp_json_encode( __( 'Enable', 'notificator-project' ) ) ); ?>"></span>
												</button>
											<button @click="openEditModal(index)" type="button" class="cursor-pointer inline-flex items-center justify-center h-9 w-9 bg-slate-50 border border-slate-200/70 text-slate-600 hover:text-indigo-700 hover:bg-indigo-50 rounded-lg transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500/40" :title="<?php echo esc_attr( wp_json_encode( __( 'Edit notification', 'notificator-project' ) ) ); ?>">
													<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
														<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
													</svg>
												</button>
											<button @click="removeHook(index)" type="button" class="cursor-pointer inline-flex items-center justify-center h-9 w-9 bg-slate-50 border border-slate-200/70 text-slate-600 hover:text-red-700 hover:bg-red-50 rounded-lg transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500/40" :title="<?php echo esc_attr( wp_json_encode( __( 'Delete notification', 'notificator-project' ) ) ); ?>">
													<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
														<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
													</svg>
												</button>
											</div>
										</div>
									</template>
								</div>
							</template>
						</div>
					</div>

				</div>
			</div>

			<?php $this->render_scenario_modal(); ?>
		</div>
		<?php
	}

	/**
	 * Return a freshly rendered Discovery inbox after a plugin scan.
	 *
	 * @return void
	 */
	public function handle_get_discovery_inbox() {
		check_ajax_referer( 'notificator_companion_discovery', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'notificator-project' ) ), 403 );
			return;
		}

		$options = get_option( $this->option_name );
		if ( ! is_array( $options ) ) {
			$options = array();
		}
		$hooks             = isset( $options['hooks'] ) && is_array( $options['hooks'] ) ? $options['hooks'] : array();
		$available_plugins = $this->plugin->get_available_plugins_with_hooks();

		ob_start();
		$this->render_discovery_inbox( $available_plugins, $hooks );
		$html = ob_get_clean();

		wp_send_json_success(
			array(
				'html' => is_string( $html ) ? $html : '',
			)
		);
	}

	/**
	 * Render the ranked discovery review inbox.
	 *
	 * @param array $available_plugins Scanned plugins and hooks.
	 * @param array $configured_hooks Existing notification rules.
	 * @return void
	 */
	private function render_discovery_inbox( $available_plugins, $configured_hooks ) {
		$ignored            = get_option( 'notificator_companion_discovery_ignored', array() );
		$ignored            = is_array( $ignored ) ? array_fill_keys( array_map( 'strval', $ignored ), true ) : array();
		$observation        = get_option( 'notificator_companion_hook_observation', array() );
		$observation        = is_array( $observation ) ? $observation : array();
		$observation_active = ! empty( $observation['active'] ) && ! empty( $observation['ends_at'] ) && time() < (int) $observation['ends_at'];
		$observed_counts    = isset( $observation['counts'] ) && is_array( $observation['counts'] ) ? $observation['counts'] : array();
		$configured_names   = array();
		foreach ( (array) $configured_hooks as $configured_hook ) {
			if ( is_array( $configured_hook ) && ! empty( $configured_hook['hook_name'] ) ) {
				$configured_names[ (string) $configured_hook['hook_name'] ] = true;
			}
		}
		$candidates = array();
		foreach ( (array) $available_plugins as $plugin_key => $plugin ) {
			if ( ! is_array( $plugin ) ) {
				continue;
			}
			foreach ( (array) ( $plugin['hooks'] ?? array() ) as $hook_name => $hook_meta ) {
				if ( is_string( $hook_meta ) ) {
					/* translators: %s: Human-readable event name. */
					$hook_description = sprintf( __( 'Triggered when “%s” happens. Use it to receive a notification when this event occurs.', 'notificator-project' ), $hook_meta );
					$hook_meta        = array(
						'label'       => $hook_meta,
						'description' => $hook_description,
						'type'        => 'action',
						'score'       => 90,
						'confidence'  => 'high',
						'risk'        => 'normal',
						'recommended' => true,
						'selectable'  => true,
						'reason'      => __( 'Curated WordPress event.', 'notificator-project' ),
					);
				}
				if ( ! is_array( $hook_meta ) ) {
					continue;
				}
				$source       = isset( $hook_meta['source'] ) && is_array( $hook_meta['source'] ) ? $hook_meta['source'] : array();
				$id           = sha1( $plugin_key . '|' . $hook_name . '|' . ( $source['file'] ?? '' ) . '|' . ( $source['line'] ?? 0 ) . '|' . ( $plugin['fingerprint'] ?? '' ) );
				$candidates[] = array(
					'id'          => $id,
					'plugin_key'  => (string) $plugin_key,
					'plugin_name' => isset( $plugin['name'] ) ? (string) $plugin['name'] : (string) $plugin_key,
					'hook_name'   => (string) $hook_name,
					'label'       => isset( $hook_meta['label'] ) && is_string( $hook_meta['label'] ) ? $hook_meta['label'] : (string) $hook_name,
					'meta'        => $hook_meta,
					'score'       => isset( $hook_meta['score'] ) ? (int) $hook_meta['score'] : 35,
					'ignored'     => isset( $ignored[ $id ] ),
					'configured'  => isset( $configured_names[ $hook_name ] ),
					'observation' => isset( $observed_counts[ $hook_name ] ) && is_array( $observed_counts[ $hook_name ] ) ? $observed_counts[ $hook_name ] : array(),
				);
			}
		}
		usort(
			$candidates,
			static function ( $left, $right ) {
				return $right['score'] === $left['score'] ? strcmp( $left['hook_name'], $right['hook_name'] ) : $right['score'] <=> $left['score'];
			}
		);
		// Keep the inbox fast without starving low-scored diagnostic categories.
		$all_candidates       = $candidates;
		$total_scanned_events = count( $all_candidates );
		$candidates           = array_slice( $all_candidates, 0, 500 );
		$included_ids         = array_fill_keys( array_column( $candidates, 'id' ), true );
		$category_limits      = array(
			'dynamic'      => 30,
			'registration' => 30,
			'noisy'        => 30,
		);
		$category_added       = array_fill_keys( array_keys( $category_limits ), 0 );
		foreach ( $all_candidates as $candidate ) {
			$meta           = $candidate['meta'];
			$observed_count = isset( $candidate['observation']['count'] ) ? (int) $candidate['observation']['count'] : 0;
			$categories     = array(
				'dynamic'      => ! empty( $meta['dynamic'] ),
				'registration' => 'registration' === ( $meta['discovery'] ?? $meta['arg_mode'] ?? '' ),
				'noisy'        => 'potentially_noisy' === ( $meta['risk'] ?? '' ) || $observed_count > 100,
			);
			foreach ( $categories as $category => $matches ) {
				if ( ! $matches || isset( $included_ids[ $candidate['id'] ] ) || $category_added[ $category ] >= $category_limits[ $category ] ) {
					continue;
				}
				$candidates[]                     = $candidate;
				$included_ids[ $candidate['id'] ] = true;
				++$category_added[ $category ];
			}
		}
		// Always surface a small set of recognisable, high-value events when the
		// integration provides them, even if a large plugin has higher-scored internals.
		$essential_hooks    = array(
			'woocommerce_new_order',
			'woocommerce_order_status_changed',
			'woocommerce_low_stock',
			'user_register',
			'wp_login_failed',
			'wp_login',
			'transition_post_status',
			'comment_post',
			'wpcf7_mail_sent',
			'gform_after_submission',
		);
		$essential_priority = array_flip( $essential_hooks );
		foreach ( $all_candidates as $candidate ) {
			if ( in_array( $candidate['hook_name'], $essential_hooks, true ) && ! isset( $included_ids[ $candidate['id'] ] ) ) {
				$candidates[]                     = $candidate;
				$included_ids[ $candidate['id'] ] = true;
			}
		}
		// Recommended is deliberately curated: high-confidence, actionable events,
		// capped across plugins so one large integration cannot dominate the list.
		$recommended_ids       = array();
		$recommended_by_plugin = array();
		$add_recommended       = static function ( $candidate, $is_essential = false ) use ( &$recommended_ids, &$recommended_by_plugin ) {
			$meta            = $candidate['meta'];
			$plugin_key      = (string) $candidate['plugin_key'];
			$plugin_count    = isset( $recommended_by_plugin[ $plugin_key ] ) ? (int) $recommended_by_plugin[ $plugin_key ] : 0;
			$is_noisy        = 'potentially_noisy' === ( $meta['risk'] ?? '' );
			$is_registration = 'registration' === ( $meta['discovery'] ?? $meta['arg_mode'] ?? '' );
			if (
				count( $recommended_ids ) >= 24
				|| $plugin_count >= 4
				|| isset( $recommended_ids[ $candidate['id'] ] )
				|| $candidate['ignored']
				|| $candidate['configured']
				|| empty( $meta['selectable'] )
				|| ! empty( $meta['dynamic'] )
				|| $is_noisy
				|| $is_registration
				|| ( ! $is_essential && ( empty( $meta['recommended'] ) || (int) $candidate['score'] < 80 ) )
			) {
				return;
			}
			$recommended_ids[ $candidate['id'] ]  = true;
			$recommended_by_plugin[ $plugin_key ] = $plugin_count + 1;
		};
		foreach ( $essential_hooks as $essential_hook ) {
			foreach ( $candidates as $candidate ) {
				if ( $essential_hook === $candidate['hook_name'] ) {
					$add_recommended( $candidate, true );
				}
			}
		}
		foreach ( $candidates as $candidate ) {
			$add_recommended( $candidate );
		}
		$recommended_count = count( $recommended_ids );
		/* translators: %s: Formatted total number of discovered events. */
		$show_all_events_format = __( 'Show all %s events', 'notificator-project' );
		$show_all_events_label  = sprintf( $show_all_events_format, number_format_i18n( $total_scanned_events ) );
		$filter_counts          = array(
			'all'          => 0,
			'recommended'  => 0,
			'observed'     => 0,
			'noisy'        => 0,
			'dynamic'      => 0,
			'registration' => 0,
			'ignored'      => 0,
		);
		foreach ( $candidates as $candidate ) {
			$meta           = $candidate['meta'];
			$observed_count = isset( $candidate['observation']['count'] ) ? (int) $candidate['observation']['count'] : 0;
			if ( $candidate['ignored'] ) {
				++$filter_counts['ignored'];
				continue;
			}
			++$filter_counts['all'];
			if ( isset( $recommended_ids[ $candidate['id'] ] ) ) {
				++$filter_counts['recommended'];
			}
			if ( ! empty( $candidate['observation'] ) ) {
				++$filter_counts['observed'];
			}
			if ( 'potentially_noisy' === ( $meta['risk'] ?? '' ) || $observed_count > 100 ) {
				++$filter_counts['noisy'];
			}
			if ( ! empty( $meta['dynamic'] ) ) {
				++$filter_counts['dynamic'];
			}
			if ( 'registration' === ( $meta['discovery'] ?? $meta['arg_mode'] ?? '' ) ) {
				++$filter_counts['registration'];
			}
		}
		?>
		<section class="scenario-section notificator-section notificator-discovery" id="notificator-discovery" data-notificator-section="discovery">
			<div class="notificator-scenario-head">
				<div class="notificator-discovery-heading"><div class="flex items-center gap-3"><div class="notificator-section-icon"><span class="dashicons dashicons-search"></span></div><div><h3><?php esc_html_e( 'Discovery inbox', 'notificator-project' ); ?></h3><p><?php esc_html_e( 'Review ranked events before turning them into notifications.', 'notificator-project' ); ?></p></div></div><div class="notificator-discovery-observe"><span id="notificator-observation-status" class="badge <?php echo $observation_active ? 'badge-success' : 'badge-info'; ?>"><?php echo $observation_active ? esc_html__( 'Observing', 'notificator-project' ) : esc_html__( 'Observation off', 'notificator-project' ); ?></span><button type="button" id="notificator-observation-toggle" class="btn-secondary btn-secondary--compact" data-observing="<?php echo esc_attr( $observation_active ? '1' : '0' ); ?>" title="<?php esc_attr_e( 'Samples site traffic and batches database updates to reduce server load.', 'notificator-project' ); ?>"><span class="dashicons <?php echo $observation_active ? 'dashicons-controls-pause' : 'dashicons-visibility'; ?>"></span><?php echo $observation_active ? esc_html__( 'Stop observing', 'notificator-project' ) : esc_html__( 'Observe for 10 min', 'notificator-project' ); ?></button></div></div>
			</div>
			<div class="card-body">
				<div class="notificator-discovery-summary"><span><strong><?php echo esc_html( $total_scanned_events ); ?></strong><?php esc_html_e( 'Scanned events', 'notificator-project' ); ?></span><span><strong><?php echo esc_html( count( $candidates ) ); ?></strong><?php esc_html_e( 'Review shortlist', 'notificator-project' ); ?></span><span><strong><?php echo esc_html( $recommended_count ); ?></strong><?php esc_html_e( 'Recommended', 'notificator-project' ); ?></span><span><strong><?php echo esc_html( count( $observed_counts ) ); ?></strong><?php esc_html_e( 'Observed', 'notificator-project' ); ?></span></div>
				<p class="notificator-discovery-explainer"><?php esc_html_e( 'Discovery keeps a ranked shortlist for review. The complete scan remains available in the event browser.', 'notificator-project' ); ?></p>
				<div class="notificator-discovery-controls"><div class="relative notificator-search"><input type="search" id="notificator-discovery-search" class="notificator-section-control notificator-section-control--search" placeholder="<?php esc_attr_e( 'Search hooks or plugins…', 'notificator-project' ); ?>"><span class="dashicons dashicons-search notificator-search-icon"></span></div><select id="notificator-discovery-filter" class="notificator-section-control notificator-section-control--select">
				<?php
				foreach ( array(
					'recommended'  => __( 'Recommended', 'notificator-project' ),
					'all'          => __( 'Ranked shortlist', 'notificator-project' ),
					'observed'     => __( 'Observed', 'notificator-project' ),
					'noisy'        => __( 'Potentially noisy', 'notificator-project' ),
					'dynamic'      => __( 'Dynamic patterns', 'notificator-project' ),
					'registration' => __( 'Registration only', 'notificator-project' ),
					'ignored'      => __( 'Ignored', 'notificator-project' ),
				) as $filter_key => $filter_label ) :
					?>
																<option value="<?php echo esc_attr( $filter_key ); ?>" data-filter-label="<?php echo esc_attr( $filter_label ); ?>" <?php disabled( 0 === $filter_counts[ $filter_key ] && 'ignored' !== $filter_key ); ?>><?php echo esc_html( sprintf( '%s (%d)', $filter_label, $filter_counts[ $filter_key ] ) ); ?></option><?php endforeach; ?></select><button type="button" id="notificator-browse-all-events" class="btn-secondary" data-event-count="<?php echo esc_attr( $total_scanned_events ); ?>"><span class="dashicons dashicons-list-view"></span><?php echo esc_html( $show_all_events_label ); ?></button></div>
				<div class="notificator-discovery-list" id="notificator-discovery-list">
				<?php foreach ( $candidates as $candidate ) : ?>
					<?php
					$meta     = $candidate['meta'];
					$observed = $candidate['observation'];
					if ( isset( $observed['count'] ) && (int) $observed['count'] > 100 ) {
						$meta['risk'] = 'potentially_noisy';
					}
					$payload_fields_label = '';
					if ( ! empty( $meta['arg_names'] ) ) {
						/* translators: %d: Number of data fields supplied by an event. */
						$payload_fields_label = sprintf( __( '%d payload fields', 'notificator-project' ), count( $meta['arg_names'] ) );
					}
					$observed_label = '';
					if ( $observed ) {
						/* translators: %d: Number of times an event was observed. */
						$observed_label = sprintf( __( 'Observed at least %d times', 'notificator-project' ), (int) ( $observed['count'] ?? 0 ) );
					}
					?>
					<article class="notificator-discovery-item" data-discovery-item data-search="<?php echo esc_attr( strtolower( $candidate['hook_name'] . ' ' . $candidate['label'] . ' ' . $candidate['plugin_name'] . ' ' . ( $meta['description'] ?? '' ) ) ); ?>" data-recommended="<?php echo isset( $recommended_ids[ $candidate['id'] ] ) ? '1' : '0'; ?>" data-recommend-priority="<?php echo esc_attr( isset( $essential_priority[ $candidate['hook_name'] ] ) ? (string) $essential_priority[ $candidate['hook_name'] ] : '999' ); ?>" data-risk="<?php echo esc_attr( $meta['risk'] ?? 'normal' ); ?>" data-dynamic="<?php echo ! empty( $meta['dynamic'] ) ? '1' : '0'; ?>" data-registration="<?php echo 'registration' === ( $meta['discovery'] ?? $meta['arg_mode'] ?? '' ) ? '1' : '0'; ?>" data-observed="<?php echo $observed ? '1' : '0'; ?>" data-ignored="<?php echo $candidate['ignored'] ? '1' : '0'; ?>">
							<div class="notificator-discovery-score is-<?php echo esc_attr( $meta['confidence'] ?? 'low' ); ?>"><strong><?php echo esc_html( $candidate['score'] ); ?></strong><span><?php esc_html_e( 'score', 'notificator-project' ); ?></span></div>
						<div class="notificator-discovery-content"><div class="notificator-discovery-title"><div><strong><?php echo esc_html( $candidate['label'] ); ?></strong><code><?php echo esc_html( $candidate['hook_name'] ); ?></code></div><span><?php echo esc_html( $candidate['plugin_name'] ); ?></span></div><p><?php echo esc_html( $meta['description'] ?? $meta['reason'] ?? __( 'Discovered in plugin code.', 'notificator-project' ) ); ?></p><div class="notificator-discovery-meta"><span><?php echo esc_html( ucfirst( $meta['type'] ?? 'action' ) ); ?></span>
						<?php
						if ( 'registered_integration' === ( $meta['discovery'] ?? '' ) ) :
							?>
							<span class="is-observed"><?php esc_html_e( 'Registered integration', 'notificator-project' ); ?></span><?php endif; ?><span><?php echo esc_html( ucfirst( $meta['confidence'] ?? 'low' ) ); ?> <?php esc_html_e( 'confidence', 'notificator-project' ); ?></span>
							<?php
							if ( $payload_fields_label ) :
								?>
							<span title="<?php echo esc_attr( implode( ', ', array_map( 'strval', $meta['arg_names'] ) ) ); ?>"><?php echo esc_html( $payload_fields_label ); ?></span><?php endif; ?>
							<?php
							if ( ! empty( $meta['source']['file'] ) ) :
								?>
	<span title="<?php echo esc_attr( $meta['source']['file'] ); ?>"><?php echo esc_html( basename( $meta['source']['file'] ) . ':' . (int) ( $meta['source']['line'] ?? 0 ) ); ?></span><?php endif; ?>
					<?php
					if ( $observed_label ) :
						?>
	<span class="is-observed"><?php echo esc_html( $observed_label ); ?></span><?php endif; ?>
					<?php
					if ( $candidate['configured'] ) :
						?>
	<span class="is-configured"><?php esc_html_e( 'Already configured', 'notificator-project' ); ?></span><?php endif; ?></div></div>
							<div class="notificator-discovery-actions"><button type="button" class="btn-primary btn-primary--compact" data-discovery-create data-plugin="<?php echo esc_attr( $candidate['plugin_key'] ); ?>" data-hook="<?php echo esc_attr( $candidate['hook_name'] ); ?>" <?php echo empty( $meta['selectable'] ) || $candidate['configured'] ? 'disabled' : ''; ?>><?php esc_html_e( 'Create', 'notificator-project' ); ?></button><button type="button" class="btn-secondary btn-secondary--compact" data-discovery-ignore data-candidate-id="<?php echo esc_attr( $candidate['id'] ); ?>"><?php echo $candidate['ignored'] ? esc_html__( 'Restore', 'notificator-project' ) : esc_html__( 'Ignore', 'notificator-project' ); ?></button></div>
						</article>
					<?php endforeach; ?>
				</div>
				<p id="notificator-discovery-empty" class="notificator-discovery-empty" hidden><?php esc_html_e( 'No discovery candidates match this view.', 'notificator-project' ); ?></p>
			</div>
		</section>
		<?php
	}

	/**
	 * Render notification log section.
	 */
	private function render_log_section() {
		$options = get_option( $this->option_name );
		if ( ! is_array( $options ) ) {
			$options = array();
		}
		$log_per_page = isset( $options['log_per_page'] ) ? (int) $options['log_per_page'] : 20;
		if ( $log_per_page < 5 ) {
			$log_per_page = 5;
		} elseif ( $log_per_page > 200 ) {
			$log_per_page = 200;
		}

		$log_enabled = ! isset( $options['log_enabled'] ) || (bool) $options['log_enabled'];
		$log         = get_option( 'notificator_companion_notification_log', array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}
		$updated_ids = false;
		foreach ( $log as $index => $entry ) {
			if ( is_array( $entry ) && empty( $entry['id'] ) ) {
				$log[ $index ]['id'] = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'log_', true );
				$updated_ids         = true;
			}
		}
		if ( $updated_ids ) {
			update_option( 'notificator_companion_notification_log', $log, false );
		}
		$log             = array_values( $log );
		$log             = array_reverse( $log );
		$log             = array_slice( $log, 0, 200 );
		$activity_counts = array(
			'total'      => count( $log ),
			'delivered'  => 0,
			'queued'     => 0,
			'attention'  => 0,
			'suppressed' => 0,
		);
		foreach ( $log as $activity_entry ) {
			if ( ! is_array( $activity_entry ) ) {
				continue;
			}
			$activity_status = isset( $activity_entry['status'] ) ? (string) $activity_entry['status'] : ( ! empty( $activity_entry['sent'] ) ? 'sent' : 'not_sent' );
			if ( in_array( $activity_status, array( 'delivered', 'sent', 'dashboard_only' ), true ) ) {
				++$activity_counts['delivered'];
			} elseif ( in_array( $activity_status, array( 'pending', 'retrying' ), true ) ) {
				++$activity_counts['queued'];
			} elseif ( in_array( $activity_status, array( 'failed', 'partial', 'not_sent', 'connection_required' ), true ) ) {
				++$activity_counts['attention'];
			} elseif ( in_array( $activity_status, array( 'throttled', 'delivery_disabled' ), true ) ) {
				++$activity_counts['suppressed'];
			}
		}

		$date_format            = get_option( 'date_format', 'Y-m-d' );
		$time_format            = get_option( 'time_format', 'H:i' );
		$api_suffix_to_nickname = array();
		$settings               = get_option( $this->option_name );
		if ( is_array( $settings ) && isset( $settings['api_keys'] ) && is_array( $settings['api_keys'] ) ) {
			$settings_api_keys      = array_values( $settings['api_keys'] );
			$settings_api_nicknames = isset( $settings['api_key_nicknames'] ) && is_array( $settings['api_key_nicknames'] )
				? array_values( $settings['api_key_nicknames'] )
				: array();

			foreach ( $settings_api_keys as $index => $raw_key ) {
				$key = is_string( $raw_key ) ? trim( $raw_key ) : '';
				if ( '' === $key ) {
					continue;
				}
				$suffix = substr( $key, -6 );
				if ( '' === $suffix ) {
					continue;
				}

				$nickname = '';
				if ( isset( $settings_api_nicknames[ $index ] ) && is_string( $settings_api_nicknames[ $index ] ) ) {
					$nickname = trim( $settings_api_nicknames[ $index ] );
				}

				if ( '' !== $nickname && ! isset( $api_suffix_to_nickname[ $suffix ] ) ) {
					$api_suffix_to_nickname[ $suffix ] = $nickname;
				}
			}
		}
		?>
		<div class="scenario-section notificator-section mt-6" id="notificator-log" data-notificator-section="log">
			<div class="notificator-scenario-head notificator-scenario-head--help">
				<div class="flex items-start sm:items-center justify-between gap-3 flex-wrap">
					<div class="flex items-center gap-3 min-w-0">
						<div class="notificator-section-icon">
							<span class="dashicons <?php echo esc_attr( $this->get_section_icon_class( 'log' ) ); ?> text-white"></span>
						</div>
						<div class="min-w-0">
							<h3 class="text-base font-semibold text-white"><?php esc_html_e( 'Activity', 'notificator-project' ); ?></h3>
							<p class="text-xs text-white text-opacity-70"><?php esc_html_e( 'Understand what triggered, where it went, and whether delivery succeeded.', 'notificator-project' ); ?></p>
						</div>
					</div>
					<span class="notificator-activity-total"><strong id="notificator-log-count"><?php echo esc_html( $activity_counts['total'] ); ?></strong> <?php esc_html_e( 'events', 'notificator-project' ); ?></span>
				</div>
			</div>

			<div class="card-body">
				<?php if ( $log_enabled ) : ?>
					<div class="notificator-activity-stats" aria-label="<?php esc_attr_e( 'Activity summary', 'notificator-project' ); ?>">
						<div><span class="dashicons dashicons-list-view"></span><p><?php esc_html_e( 'Total', 'notificator-project' ); ?><strong><?php echo esc_html( $activity_counts['total'] ); ?></strong></p></div>
						<div class="is-success"><span class="dashicons dashicons-yes-alt"></span><p><?php esc_html_e( 'Delivered', 'notificator-project' ); ?><strong><?php echo esc_html( $activity_counts['delivered'] ); ?></strong></p></div>
						<div class="is-pending"><span class="dashicons dashicons-clock"></span><p><?php esc_html_e( 'Queued', 'notificator-project' ); ?><strong><?php echo esc_html( $activity_counts['queued'] ); ?></strong></p></div>
						<div class="is-danger"><span class="dashicons dashicons-warning"></span><p><?php esc_html_e( 'Needs attention', 'notificator-project' ); ?><strong><?php echo esc_html( $activity_counts['attention'] ); ?></strong></p></div>
						<div class="is-muted"><span class="dashicons dashicons-controls-pause"></span><p><?php esc_html_e( 'Suppressed', 'notificator-project' ); ?><strong><?php echo esc_html( $activity_counts['suppressed'] ); ?></strong></p></div>
					</div>
				<?php endif; ?>
				<?php if ( ! $log_enabled ) : ?>
					<div class="notice notice-warning inline notice-inline-warning">
						<p><?php esc_html_e( 'Log is disabled. Enable it from Tools to start tracking notifications.', 'notificator-project' ); ?></p>
					</div>
				<?php elseif ( empty( $log ) ) : ?>
					<p class="text-sm text-gray-600"><?php esc_html_e( 'No notifications have been triggered yet.', 'notificator-project' ); ?></p>
				<?php else : ?>
					<div class="notificator-activity-toolbar">
						<div class="relative notificator-search notificator-log-search-header">
							<label class="screen-reader-text" for="notificator-log-search"><?php esc_html_e( 'Search activity', 'notificator-project' ); ?></label>
							<input type="search" id="notificator-log-search" placeholder="<?php esc_attr_e( 'Search events, hooks, destinations…', 'notificator-project' ); ?>" class="notificator-section-control notificator-section-control--search" />
							<span class="dashicons dashicons-search notificator-search-icon" aria-hidden="true"></span>
						</div>
						<label><span class="screen-reader-text"><?php esc_html_e( 'Filter by status', 'notificator-project' ); ?></span><select id="notificator-log-status-filter" class="notificator-section-control notificator-section-control--select"><option value=""><?php esc_html_e( 'All statuses', 'notificator-project' ); ?></option><option value="delivered"><?php esc_html_e( 'Delivered', 'notificator-project' ); ?></option><option value="queued"><?php esc_html_e( 'Queued / retrying', 'notificator-project' ); ?></option><option value="attention"><?php esc_html_e( 'Needs attention', 'notificator-project' ); ?></option><option value="suppressed"><?php esc_html_e( 'Suppressed', 'notificator-project' ); ?></option></select></label>
						<label><span class="screen-reader-text"><?php esc_html_e( 'Filter by severity', 'notificator-project' ); ?></span><select id="notificator-log-severity-filter" class="notificator-section-control notificator-section-control--select"><option value=""><?php esc_html_e( 'All severities', 'notificator-project' ); ?></option><option value="info"><?php esc_html_e( 'Low', 'notificator-project' ); ?></option><option value="warning"><?php esc_html_e( 'Medium', 'notificator-project' ); ?></option><option value="critical"><?php esc_html_e( 'Critical', 'notificator-project' ); ?></option></select></label>
						<label><span class="screen-reader-text"><?php esc_html_e( 'Events per page', 'notificator-project' ); ?></span><select id="notificator-log-per-page" name="<?php echo esc_attr( $this->option_name ); ?>[log_per_page]" class="notificator-section-control notificator-section-control--select">
							<?php foreach ( array( 10, 20, 50, 100, 200 ) as $count ) : ?>
								<?php
								/* translators: %d: Number of activity entries shown on one page. */
								$per_page_label = sprintf( __( '%d per page', 'notificator-project' ), $count );
								?>
								<option value="<?php echo esc_attr( $count ); ?>" <?php selected( $log_per_page, $count ); ?>><?php echo esc_html( $per_page_label ); ?></option>
							<?php endforeach; ?>
						</select></label>
						<button type="button" id="notificator-log-reset" class="btn-secondary btn-secondary--compact"><span class="dashicons dashicons-image-rotate"></span><?php esc_html_e( 'Reset', 'notificator-project' ); ?></button>
						<button type="button" id="notificator-clear-log" class="btn-secondary btn-secondary--danger btn-secondary--compact"><span class="dashicons dashicons-trash"></span><?php esc_html_e( 'Clear', 'notificator-project' ); ?></button>
					</div>
					<div class="overflow-x-auto">
						<table class="widefat striped notificator-log-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Event', 'notificator-project' ); ?></th>
									<th><?php esc_html_e( 'Delivery', 'notificator-project' ); ?></th>
									<th><?php esc_html_e( 'Severity', 'notificator-project' ); ?></th>
									<th><?php esc_html_e( 'Time', 'notificator-project' ); ?></th>
									<th><?php esc_html_e( 'Actions', 'notificator-project' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $log as $entry ) : ?>
									<?php
										$timestamp    = isset( $entry['timestamp'] ) ? (string) $entry['timestamp'] : '';
										$time_display = '';
									if ( $timestamp ) {
										$time_display = date_i18n( $date_format . ' ' . $time_format, strtotime( $timestamp ) );
									}
									if ( $timestamp ) {
										/* translators: %s: Human-readable elapsed time, such as "5 minutes". */
										$time_relative = sprintf( __( '%s ago', 'notificator-project' ), human_time_diff( strtotime( $timestamp ), time() ) );
									} else {
										$time_relative = __( 'Unknown', 'notificator-project' );
									}
										$severity = isset( $entry['severity'] ) ? (string) $entry['severity'] : 'info';
										$status   = isset( $entry['status'] ) ? (string) $entry['status'] : '';
										$sent     = isset( $entry['sent'] ) ? (bool) $entry['sent'] : true;
									if ( '' === $status ) {
										$status = $sent ? 'sent' : 'not_sent';
									}
										$status_labels = array(
											'pending'   => __( 'Pending', 'notificator-project' ),
											'retrying'  => __( 'Retrying', 'notificator-project' ),
											'delivered' => __( 'Delivered', 'notificator-project' ),
											'partial'   => __( 'Partially delivered', 'notificator-project' ),
											'dashboard_only' => __( 'Dashboard delivered', 'notificator-project' ),
											'connection_required' => __( 'API key required', 'notificator-project' ),
											'failed'    => __( 'Failed', 'notificator-project' ),
											'throttled' => __( 'Throttled', 'notificator-project' ),
											'delivery_disabled' => __( 'Delivery disabled', 'notificator-project' ),
											'sent'      => __( 'Sent', 'notificator-project' ),
										);
										$status_label  = isset( $status_labels[ $status ] ) ? $status_labels[ $status ] : ( $sent ? __( 'Sent', 'notificator-project' ) : __( 'Not sent', 'notificator-project' ) );
										$status_badge  = in_array( $status, array( 'delivered', 'sent', 'dashboard_only' ), true ) ? 'badge-success' : ( in_array( $status, array( 'failed', 'connection_required' ), true ) ? 'badge-danger' : 'badge-warning' );
										$status_error  = isset( $entry['error'] ) ? (string) $entry['error'] : '';
										$badge_class   = 'badge-info';
										if ( 'critical' === $severity ) {
											$badge_class = 'badge-danger';
										} elseif ( 'warning' === $severity ) {
											$badge_class = 'badge-warning';
										}
										$api_display_values = array();
										$api_nicknames      = isset( $entry['api_nicknames'] ) && is_array( $entry['api_nicknames'] ) ? $entry['api_nicknames'] : array();
										$api_nicknames      = array_filter( array_map( 'strval', $api_nicknames ), 'strlen' );

										if ( ! empty( $api_nicknames ) ) {
											$api_display_values = array_values( array_unique( $api_nicknames ) );
										} else {
											$api_keys = isset( $entry['api_keys'] ) && is_array( $entry['api_keys'] ) ? $entry['api_keys'] : array();
											$api_keys = array_filter( array_map( 'strval', $api_keys ), 'strlen' );
											if ( ! empty( $api_keys ) ) {
												$api_keys           = array_map(
													static function ( $suffix ) {
														return trim( (string) $suffix );
													},
													$api_keys
												);
												$api_display_values = array();
												foreach ( $api_keys as $suffix ) {
													if ( isset( $api_suffix_to_nickname[ $suffix ] ) && '' !== $api_suffix_to_nickname[ $suffix ] ) {
														$api_display_values[] = $api_suffix_to_nickname[ $suffix ];
													} else {
														$api_display_values[] = '…' . $suffix;
													}
												}
												$api_display_values = array_values( array_unique( $api_display_values ) );
											}
										}

										$api_display   = implode( ', ', $api_display_values );
										$event_title   = isset( $entry['title'] ) ? (string) $entry['title'] : '';
										$hook_name     = isset( $entry['hook_name'] ) ? (string) $entry['hook_name'] : '';
										$scenario_name = isset( $entry['scenario_name'] ) ? (string) $entry['scenario_name'] : '';
										$search_value  = implode( ' ', array( $event_title, $hook_name, $scenario_name, $api_display, $status_label, $severity, $status_error, $time_display ) );
										?>
									<?php $entry_id = isset( $entry['id'] ) ? (string) $entry['id'] : ''; ?>
								<tr class="notificator-log-row" data-log-id="<?php echo esc_attr( $entry_id ); ?>" data-log-status="<?php echo esc_attr( $status ); ?>" data-log-severity="<?php echo esc_attr( $severity ); ?>" data-log-search="<?php echo esc_attr( $search_value ); ?>">
									<td class="notificator-activity-event"><strong><?php echo esc_html( $event_title ? $event_title : $hook_name ); ?></strong>
									<?php
									if ( $hook_name ) :
										?>
										<code><?php echo esc_html( $hook_name ); ?></code><?php endif; ?>
										<?php
										if ( $scenario_name ) :
											?>
										<small><?php echo esc_html( $scenario_name ); ?></small><?php endif; ?></td>
								<td class="notificator-activity-delivery"><span class="badge <?php echo esc_attr( $status_badge ); ?>"><?php echo esc_html( $status_label ); ?></span><strong><?php echo esc_html( $api_display ? $api_display : ( 'dashboard_only' === $status ? __( 'WordPress dashboard', 'notificator-project' ) : __( 'No remote destination', 'notificator-project' ) ) ); ?></strong>
									<?php
									if ( $status_error ) :
										?>
									<small title="<?php echo esc_attr( $status_error ); ?>"><?php echo esc_html( $status_error ); ?></small><?php endif; ?></td>
									<td><span class="badge <?php echo esc_attr( $badge_class ); ?>"><?php echo esc_html( ucfirst( $severity ) ); ?></span></td>
									<td class="notificator-activity-time"><strong><?php echo esc_html( $time_relative ); ?></strong><small><?php echo esc_html( $time_display ); ?></small></td>
										<td>
											<button type="button" class="btn-icon btn-icon--danger notificator-log-delete" data-log-id="<?php echo esc_attr( $entry_id ); ?>" aria-label="<?php echo esc_attr__( 'Delete log entry', 'notificator-project' ); ?>">
												<span class="dashicons dashicons-trash"></span>
											</button>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<div class="notificator-activity-footer"><p id="notificator-log-empty" class="text-sm text-gray-600" hidden><?php esc_html_e( 'No activity matches these filters.', 'notificator-project' ); ?></p><div class="notificator-log-pagination"><button type="button" id="notificator-log-prev" class="btn-secondary btn-secondary--compact" disabled><span class="dashicons dashicons-arrow-left-alt2"></span><?php esc_html_e( 'Previous', 'notificator-project' ); ?></button><span class="text-xs text-gray-500" id="notificator-log-page">1 / 1</span><button type="button" id="notificator-log-next" class="btn-secondary btn-secondary--compact" disabled><?php esc_html_e( 'Next', 'notificator-project' ); ?><span class="dashicons dashicons-arrow-right-alt2"></span></button></div></div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/** Render user-facing support resources. */
	private function render_help_section() {
		?>
		<div class="scenario-section notificator-section" id="notificator-help" data-notificator-section="help" data-notificator-workspace="support">
			<div class="notificator-scenario-head notificator-scenario-head--help"><div class="flex items-center gap-3"><div class="notificator-section-icon"><span class="dashicons <?php echo esc_attr( $this->get_section_icon_class( 'help' ) ); ?> text-white"></span></div><div><h3 class="text-base font-semibold text-white"><?php esc_html_e( 'Support', 'notificator-project' ); ?></h3><p class="text-xs text-white text-opacity-70"><?php esc_html_e( 'Guides and the right next step when you need help.', 'notificator-project' ); ?></p></div></div></div>
			<div class="card-body"><div class="space-y-4">
				<p class="text-sm text-slate-800"><?php esc_html_e( 'Start with the complete workflow or jump directly to the setup step you need.', 'notificator-project' ); ?></p>
				<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 notificator-help-links-grid">
					<div class="rounded-xl border border-slate-200 bg-slate-50 p-4 notificator-help-link-card"><p class="text-sm font-semibold text-slate-900 mb-2"><?php esc_html_e( 'Setup guides', 'notificator-project' ); ?></p><div class="flex flex-col gap-2"><a class="btn-secondary btn-secondary--compact" href="<?php echo esc_url( 'https://docs.notificator-project.com/guides/workflow-overview/' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Complete Workflow', 'notificator-project' ); ?></a><a class="btn-secondary btn-secondary--compact" href="<?php echo esc_url( 'https://docs.notificator-project.com/guides/account-creation/' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Create Account', 'notificator-project' ); ?></a><a class="btn-secondary btn-secondary--compact" href="<?php echo esc_url( 'https://docs.notificator-project.com/guides/mobile-api-key-creation/' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Create API Key (Mobile)', 'notificator-project' ); ?></a><a class="btn-secondary btn-secondary--compact" href="<?php echo esc_url( 'https://docs.notificator-project.com/guides/wordpress-plugin-setup/' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'WordPress Plugin Setup', 'notificator-project' ); ?></a></div></div>
					<div class="rounded-xl border border-slate-200 bg-slate-50 p-4 notificator-help-link-card"><p class="text-sm font-semibold text-slate-900 mb-2"><?php esc_html_e( 'More help', 'notificator-project' ); ?></p><p class="text-sm text-slate-600 mb-3"><?php esc_html_e( 'Browse the documentation or contact the project when a guide does not answer your question.', 'notificator-project' ); ?></p><div class="flex flex-col gap-2"><a class="btn-secondary btn-secondary--compact" href="<?php echo esc_url( 'https://docs.notificator-project.com/' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open documentation', 'notificator-project' ); ?></a><a class="btn-secondary btn-secondary--compact" href="<?php echo esc_url( 'https://notificator-project.com/contact/' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Contact the project', 'notificator-project' ); ?></a></div></div>
				</div>
			</div></div>
		</div>
		<?php
	}

	/** Render the dedicated developer integration workspace. */
	private function render_developer_section() {
		$registered_events    = function_exists( 'notificator_companion_get_registered_events' ) ? notificator_companion_get_registered_events() : array();
		$registered_templates = function_exists( 'notificator_companion_get_registered_templates' ) ? notificator_companion_get_registered_templates() : array();
		$integration_example  = <<<'PHP'
add_action( 'notificator_companion_register_events', function () {
    notificator_companion_register_event( array(
        'hook_name'   => 'notificator_sample_message_sent',
        'label'       => 'Sample message sent',
        'description' => 'Runs when the sample plugin sends a message.',
		'plugin_slug' => 'notificator-sample-plugin',
		'plugin_name' => 'Notificator – Integration Example',
        'plugin_file' => plugin_basename( __FILE__ ),
        'arg_names'   => array( 'message', 'suffix' ),
    ) );
} );

add_action( 'notificator_companion_register_templates', function () {
    notificator_companion_register_template( array(
        'title'           => 'Sample message notification',
        'hook_name'       => 'notificator_sample_message_sent',
        'description'     => 'Alert when the sample message is sent.',
        'scenario_name'   => 'Sample Message Sent',
		'required_plugin' => 'notificator-sample-plugin',
    ) );
} );

do_action( 'notificator_sample_message_sent', $message, $suffix );
PHP;
		?>
		<div class="scenario-section notificator-section" id="notificator-integrations" data-notificator-section="developer" data-notificator-workspace="developer">
			<div class="notificator-scenario-head notificator-scenario-head--help"><div class="flex items-center gap-3"><div class="notificator-section-icon"><span class="dashicons dashicons-editor-code text-white"></span></div><div><h3 class="text-base font-semibold text-white"><?php esc_html_e( 'Developer integrations', 'notificator-project' ); ?></h3><p class="text-xs text-white text-opacity-70"><?php esc_html_e( 'Register reliable custom events and reusable notification templates.', 'notificator-project' ); ?></p></div></div></div>
			<div class="card-body"><div class="space-y-4">
				<div class="notificator-developer-links"><a class="btn-secondary btn-secondary--compact" href="<?php echo esc_url( 'https://docs.notificator-project.com/guides/quick-start/' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Quick Start', 'notificator-project' ); ?></a><a class="btn-secondary btn-secondary--compact" href="<?php echo esc_url( 'https://docs.notificator-project.com/guides/code-samples/' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Code Samples', 'notificator-project' ); ?></a><a class="btn-secondary btn-secondary--compact" href="<?php echo esc_url( 'https://docs.notificator-project.com/reference/public-notify/' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Public Notify API', 'notificator-project' ); ?></a></div>
				<div class="notificator-help-link-card notificator-integration-card">
					<div class="notificator-integration-header"><div class="notificator-integration-copy"><p class="notificator-integration-title"><?php esc_html_e( 'Build a first-party integration', 'notificator-project' ); ?></p><p class="notificator-integration-description"><?php esc_html_e( 'Register an event for accurate, scan-free discovery, then add an optional template to give users a ready-made notification.', 'notificator-project' ); ?></p></div><div class="notificator-integration-actions"><a class="btn-secondary btn-secondary--compact" href="<?php echo esc_url( 'https://docs.notificator-project.com/guides/wordpress-custom-events/' ); ?>" target="_blank" rel="noopener noreferrer"><span class="dashicons dashicons-external"></span><?php esc_html_e( 'Open integration guide', 'notificator-project' ); ?></a></div></div>
					<div class="notificator-integration-concepts" aria-label="<?php esc_attr_e( 'Difference between registered events and templates', 'notificator-project' ); ?>"><div class="notificator-integration-concept is-event"><span class="dashicons dashicons-rss"></span><div><strong><?php esc_html_e( 'Registered event: what happened', 'notificator-project' ); ?></strong><p><?php esc_html_e( 'Describes a hook and its payload so it appears accurately in Discover. It does not create a notification by itself.', 'notificator-project' ); ?></p></div></div><div class="notificator-integration-concept is-template"><span class="dashicons dashicons-layout"></span><div><strong><?php esc_html_e( 'Template: suggested setup', 'notificator-project' ); ?></strong><p><?php esc_html_e( 'Provides a ready-made configuration. It becomes a notification only after the user applies and saves it.', 'notificator-project' ); ?></p></div></div></div>
					<p class="notificator-integration-flow"><strong><?php esc_html_e( 'Together:', 'notificator-project' ); ?></strong> <?php esc_html_e( 'the event describes what can occur; the template recommends what the user can do with it.', 'notificator-project' ); ?></p>
					<div class="notificator-integration-stats"><div class="notificator-integration-stat"><strong><?php echo esc_html( count( $registered_events ) ); ?></strong><span><?php esc_html_e( 'Registered events detected', 'notificator-project' ); ?></span></div><div class="notificator-integration-stat"><strong><?php echo esc_html( count( $registered_templates ) ); ?></strong><span><?php esc_html_e( 'Third-party templates detected', 'notificator-project' ); ?></span></div></div>
					<?php
					if ( $registered_events ) :
						?>
						<div class="notificator-integration-list">
						<?php
						foreach ( array_slice( $registered_events, 0, 8 ) as $event ) :
							?>
						<div class="notificator-integration-item"><div><strong><?php echo esc_html( $event['label'] ?? $event['hook_name'] ?? __( 'Registered event', 'notificator-project' ) ); ?></strong><code><?php echo esc_html( $event['hook_name'] ?? '' ); ?></code></div><span class="badge badge-info text-xs"><?php echo esc_html( $event['plugin_name'] ?? $event['plugin_slug'] ?? __( 'Integration', 'notificator-project' ) ); ?></span></div><?php endforeach; ?></div>
						<?php
else :
	?>
	<p class="notificator-integration-empty"><?php esc_html_e( 'No plugin has registered a Notificator event yet. Scanned WordPress hooks remain available as usual.', 'notificator-project' ); ?></p><?php endif; ?>
					<details class="notificator-integration-example"><summary><?php esc_html_e( 'Event + template example', 'notificator-project' ); ?></summary><pre><code><?php echo esc_html( $integration_example ); ?></code></pre></details>
					<p class="notificator-integration-empty"><?php esc_html_e( 'The integration guide includes the complete example source and installation instructions.', 'notificator-project' ); ?></p>
				</div>
			</div></div>
		</div>
		<?php
	}

	/**
	 * Render the scenario modal
	 */
	private function render_scenario_modal() {
		?>
		<!-- Add/Edit Scenario Modal -->
		<div x-show="modalOpen"
			x-cloak
			class="fixed inset-0 z-50 overflow-y-auto modal-backdrop">
			<div class="flex items-center justify-center min-h-screen px-4">
				<!-- Backdrop -->
				<div @click="modalOpen = false"
					x-show="modalOpen"
					x-transition:enter="ease-out duration-300"
					x-transition:enter-start="opacity-0"
					x-transition:enter-end="opacity-100"
					x-transition:leave="ease-in duration-200"
					x-transition:leave-start="opacity-100"
					x-transition:leave-end="opacity-0"
					class="fixed inset-0"></div>

				<!-- Modal Content -->
				<div x-show="modalOpen"
					x-transition:enter="ease-out duration-300"
					x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
					x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
					x-transition:leave="ease-in duration-200"
					x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
					x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
					@keydown.escape.window="modalOpen = false"
					class="notificator-create-modal relative flex flex-col bg-white rounded-2xl shadow-2xl max-w-4xl w-full max-h-[95vh] overflow-hidden">

					<!-- Modal Header -->
					<div class="notificator-modal-head notificator-scenario-head--builder">
						<div class="flex items-center justify-between">
							<div>
								<h3 class="text-lg font-semibold text-white" x-text="editingIndex !== null ? <?php echo esc_attr( wp_json_encode( __( 'Edit notification', 'notificator-project' ) ) ); ?> : <?php echo esc_attr( wp_json_encode( __( 'Create notification', 'notificator-project' ) ) ); ?>"></h3>
								<p class="text-xs text-white text-opacity-70 mt-0.5">
									<span x-show="modalStep === 1"><?php esc_html_e( 'Step 1: Choose a source', 'notificator-project' ); ?></span>
									<span x-show="modalStep === 2"><?php esc_html_e( 'Step 2: Choose an event', 'notificator-project' ); ?></span>
									<span x-show="modalStep === 3 && editingIndex === null"><?php esc_html_e( 'Step 3: Configure and review', 'notificator-project' ); ?></span>
								</p>
							</div>
							<button @click="modalOpen = false" type="button"
								class="cursor-pointer inline-flex items-center justify-center rounded-full p-2 bg-white/10 text-white/90 hover:text-white hover:bg-white/20 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/60">
								<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
									<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
								</svg>
							</button>
						</div>
					</div>

					<!-- Step Indicator -->
					<template x-if="editingIndex === null">
						<div class="notificator-modal-step-indicator px-6 py-3 bg-gray-50 border-b">
							<div class="notificator-modal-steps">
								<div class="notificator-modal-step" :class="modalStep >= 1 ? 'is-active' : ''"><span>1</span><strong><?php esc_html_e( 'Source', 'notificator-project' ); ?></strong></div>
								<div class="notificator-modal-step__line" :class="modalStep >= 2 ? 'is-active' : ''"></div>
								<div class="notificator-modal-step" :class="modalStep >= 2 ? 'is-active' : ''"><span>2</span><strong><?php esc_html_e( 'Event', 'notificator-project' ); ?></strong></div>
								<div class="notificator-modal-step__line" :class="modalStep >= 3 ? 'is-active' : ''"></div>
								<div class="notificator-modal-step" :class="modalStep >= 3 ? 'is-active' : ''"><span>3</span><strong><?php esc_html_e( 'Notification', 'notificator-project' ); ?></strong></div>
							</div>
						</div>
					</template>

					<!-- Modal Body -->
					<div class="p-6 overflow-y-auto custom-scrollbar modal-body-scrollable">

						<!-- Step 1: Select Plugin -->
						<div x-show="modalStep === 1">
							<div class="mb-4">
								<h3 class="text-sm font-semibold text-gray-900 mb-3"><?php esc_html_e( 'Build a custom notification', 'notificator-project' ); ?></h3>
								<p class="text-xs text-gray-500 mb-4"><?php esc_html_e( 'Choose where the event comes from. You will select the exact WordPress event next.', 'notificator-project' ); ?></p>
							</div>

							<!-- Plugin Selection Grid -->
							<div class="grid grid-cols-2 gap-3">
								<template x-for="(plugin, key) in availablePlugins" :key="'modal-plugin-' + key">
									<button @click="selectPlugin(key)" type="button"
										class="notificator-plugin-select text-left p-4 rounded-xl border-2 transition-all hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500/40"
										:class="plugin.file && !pluginActiveStatus[key] ? 'opacity-40 cursor-not-allowed border-gray-200' : 'cursor-pointer border-gray-200 hover:border-indigo-300'">
										<div class="flex items-center gap-3">
											<div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center text-2xl">
												<template x-if="String(plugin.icon || '').indexOf('dashicons-') === 0">
													<span class="dashicons" :class="plugin.icon" aria-hidden="true"></span>
												</template>
												<template x-if="String(plugin.icon || '').indexOf('dashicons-') !== 0">
													<span x-text="plugin.icon || '🔌'"></span>
												</template>
											</div>
											<div class="flex-1 min-w-0">
												<div class="font-semibold text-sm text-gray-900 truncate" x-text="plugin.name"></div>
								<div class="text-xs text-gray-500" x-text="Object.keys(plugin.hooks).length + ' event' + (Object.keys(plugin.hooks).length === 1 ? '' : 's') + ' available'"></div>
											</div>
										</div>
									</button>
								</template>
							</div>
						</div>

						<!-- Step 2: Select Hook -->
						<div x-show="modalStep === 2">
							<!-- Search input -->
							<div class="mb-4">
								<div class="relative notificator-search notificator-modal-event-search">
									<input type="search"
										x-model="hookSearchQuery"
										autocomplete="off"
										aria-label="<?php esc_attr_e( 'Search available events', 'notificator-project' ); ?>"
									placeholder="<?php esc_attr_e( 'Search events, e.g. order, login, form…', 'notificator-project' ); ?>"
										class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm">
									<span class="dashicons dashicons-search notificator-search-icon" aria-hidden="true"></span>
								</div>
								<p class="text-xs text-gray-500 mt-2" x-text="getHookResultsSummary()"></p>
							</div>

							<!-- Hooks list -->
							<div class="space-y-2 overflow-y-auto hook-list-scrollable">
								<template x-for="(hookData, hookName) in getVisiblePluginHooks()" :key="'modal-hook-' + hookName">
									<button @click="selectHook(hookName, hookData)" type="button"
										class="cursor-pointer w-full text-left p-3 rounded-lg bg-gray-50 hover:bg-indigo-50 border border-gray-200 hover:border-indigo-300 transition-all focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500/40">
										<div class="flex items-start justify-between gap-2">
											<div class="flex-1 min-w-0">
												<strong class="block text-sm text-gray-900" x-text="(hookData && hookData.label) ? hookData.label : hookName"></strong>
												<p class="text-xs text-gray-600 mt-1.5 leading-relaxed" x-text="(hookData && hookData.description) ? hookData.description : ((hookData && hookData.label) ? hookData.label : hookData)"></p>
											<template x-if="hookData && typeof hookData === 'object' && getHookArgumentSummary(hookData).length > 0">
												<div class="notificator-event-data-preview">
													<span class="notificator-event-data-preview__label" x-text="getHookArgumentSummary(hookData).length + ' useful detail' + (getHookArgumentSummary(hookData).length === 1 ? '' : 's') + ' available'"></span>
														<template x-for="arg in getHookArgumentSummary(hookData).slice(0, 3)" :key="'hook-arg-' + hookName + '-' + arg.value">
															<span class="notificator-data-chip" x-text="arg.label"></span>
														</template>
													</div>
												</template>
												<code class="notificator-technical-hook" x-text="hookName"></code>
											</div>
										</div>
									</button>
								</template>
								<template x-if="Object.keys(getFilteredPluginHooks()).length === 0">
									<div class="text-center py-8 text-gray-500">
										<p class="text-sm"><?php esc_html_e( 'No events found matching your search', 'notificator-project' ); ?></p>
									</div>
								</template>
							</div>
						</div>
						<!-- Step 3: Configure Scenario -->
						<div x-show="modalStep === 3">
							<div class="space-y-4">
								<div class="notificator-selected-event">
									<div class="notificator-selected-event__eyebrow"><?php esc_html_e( 'When this happens', 'notificator-project' ); ?></div>
									<strong x-text="(scenarioForm.hook_meta && scenarioForm.hook_meta.label) ? scenarioForm.hook_meta.label : scenarioForm.scenario_name"></strong>
									<p x-text="scenarioForm.description"></p>
									<template x-if="getHookArgumentSummary(scenarioForm.hook_meta).length">
										<div class="notificator-available-data">
											<div class="notificator-available-data__title"><?php esc_html_e( 'Information you can use', 'notificator-project' ); ?></div>
											<div class="notificator-available-data__grid">
												<template x-for="arg in getHookArgumentSummary(scenarioForm.hook_meta)" :key="'selected-arg-' + arg.value">
													<div class="notificator-available-data__item"><strong x-text="arg.label"></strong><span x-text="arg.description"></span></div>
												</template>
											</div>
										</div>
									</template>
									<details class="notificator-technical-details">
										<summary><?php esc_html_e( 'Technical details', 'notificator-project' ); ?></summary>
										<code x-text="scenarioForm.hook_name"></code>
									</details>
								</div>

								<div class="grid grid-cols-1 gap-4 md:grid-cols-2">
									<div>
									<label class="block text-sm font-semibold text-gray-700 mb-2"><?php esc_html_e( 'Notification name', 'notificator-project' ); ?></label>
										<input type="text" x-model="scenarioForm.scenario_name"
											class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
											placeholder="<?php esc_attr_e( 'e.g. New order placed', 'notificator-project' ); ?>">
									</div>

									<div>
										<label class="block text-sm font-semibold text-gray-700 mb-2"><?php esc_html_e( 'Priority', 'notificator-project' ); ?></label>
										<select x-model="scenarioForm.severity"
											class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
											<option value="info"><?php esc_html_e( 'Normal (routine information)', 'notificator-project' ); ?></option>
											<option value="warning"><?php esc_html_e( 'Important (needs attention)', 'notificator-project' ); ?></option>
											<option value="critical"><?php esc_html_e( 'Urgent (act immediately)', 'notificator-project' ); ?></option>
										</select>
									</div>
								</div>

							<div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
								<label class="block text-sm font-semibold text-gray-700 mb-1"><?php esc_html_e( 'Where should it be sent?', 'notificator-project' ); ?></label>
								<p class="text-xs text-gray-500 mb-3"><?php esc_html_e( 'Choose at least one channel. Dashboard alerts work without an API key.', 'notificator-project' ); ?></p>
								<div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
									<label class="flex items-center gap-2 text-sm text-gray-700">
										<input type="checkbox" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" x-model="scenarioForm.send_dashboard">
										<span><strong><?php esc_html_e( 'Dashboard', 'notificator-project' ); ?></strong><small><?php esc_html_e( 'WordPress admin alerts', 'notificator-project' ); ?></small></span>
									</label>
									<label class="flex items-center gap-2 text-sm text-gray-700">
										<input type="checkbox" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" x-model="scenarioForm.send_push" :disabled="!hasRemoteDelivery">
										<span><strong><?php esc_html_e( 'Mobile push', 'notificator-project' ); ?></strong><small x-text="hasRemoteDelivery ? <?php echo esc_attr( wp_json_encode( __( 'Connected phones', 'notificator-project' ) ) ); ?> : <?php echo esc_attr( wp_json_encode( __( 'API key required', 'notificator-project' ) ) ); ?>"></small></span>
									</label>
									<label class="flex items-center gap-2 text-sm text-gray-700">
										<input type="checkbox" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" x-model="scenarioForm.send_mqtt" :disabled="!hasRemoteDelivery">
										<span><strong><?php esc_html_e( 'MQTT', 'notificator-project' ); ?></strong><small x-text="hasRemoteDelivery ? <?php echo esc_attr( wp_json_encode( __( 'Connected IoT devices', 'notificator-project' ) ) ); ?> : <?php echo esc_attr( wp_json_encode( __( 'API key required', 'notificator-project' ) ) ); ?>"></small></span>
									</label>
								</div>
								</div>

								<!-- Conditions Builder (only for hooks with args) -->
								<div x-show="hasConditionSupport()">
									<div class="flex items-center justify-between mb-2">
										<div>
											<label class="block text-sm font-semibold text-gray-700"><?php esc_html_e( 'Only notify me when…', 'notificator-project' ); ?></label>
											<p class="text-xs text-gray-500 mt-0.5"><?php esc_html_e( 'Optional. Add a rule to receive fewer, more relevant alerts.', 'notificator-project' ); ?></p>
										</div>
										<button @click="addCondition()" type="button"
											x-show="!areAllConditionsLocked()"
											class="cursor-pointer inline-flex items-center gap-1 h-8 px-3 text-xs font-semibold bg-indigo-500 text-white rounded-lg hover:bg-indigo-600 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500/40">
											<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
												<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
											</svg>
											<?php esc_html_e( 'Add rule', 'notificator-project' ); ?>
										</button>
									</div>

									<div class="space-y-2 mb-3">
										<template x-for="(condition, cIndex) in scenarioForm.conditions" :key="'condition-' + cIndex">
											<div class="notificator-condition-row flex gap-3 items-start p-3.5 bg-white rounded-xl border border-slate-200 shadow-sm transition-colors focus-within:border-indigo-300 focus-within:ring-2 focus-within:ring-indigo-500/15">
												<div class="notificator-condition-grid flex-1 grid grid-cols-3 gap-2">
													<!-- Field selector -->
													<div class="notificator-condition-col">
												<label class="block text-xs text-gray-600 mb-1"><?php esc_html_e( 'Information', 'notificator-project' ); ?></label>
												<template x-if="condition.locked || condition.lock_field">
												<div class="notificator-condition-locked w-full h-9 px-2 text-sm border border-gray-200 rounded bg-slate-50 text-gray-800 flex items-center" x-text="getFriendlyFieldLabel(condition.field)"></div>
														</template>
														<template x-if="!(condition.locked || condition.lock_field)">
															<select x-model="condition.field" class="notificator-condition-control w-full h-9 px-2 text-sm border border-gray-300 rounded bg-white focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500">
																<template x-for="field in getConditionFields()" :key="'field-' + field.value">
																	<option :value="field.value" x-text="field.label"></option>
																</template>
													</select>
												</template>
												<p class="notificator-condition-help" x-text="getFriendlyFieldDescription(condition.field)"></p>
													</div>

													<!-- Operator selector -->
													<div class="notificator-condition-col">
												<label class="block text-xs text-gray-600 mb-1"><?php esc_html_e( 'Comparison', 'notificator-project' ); ?></label>
												<template x-if="condition.locked || condition.lock_operator">
												<div class="notificator-condition-locked w-full h-9 px-2 text-sm border border-gray-200 rounded bg-slate-50 text-gray-800 flex items-center" x-text="getOperatorLabel(condition.operator)"></div>
														</template>
														<template x-if="!(condition.locked || condition.lock_operator)">
															<select x-model="condition.operator" class="notificator-condition-control w-full h-9 px-2 text-sm border border-gray-300 rounded bg-white focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500">
																<template x-for="op in getOperators()" :key="'op-' + op.value">
																	<option :value="op.value" x-text="op.label"></option>
																</template>
															</select>
														</template>
													</div>

													<!-- Value input -->
													<div class="notificator-condition-col">
														<label class="block text-xs text-gray-600 mb-1" x-text="condition.value_label || <?php echo esc_attr( wp_json_encode( __( 'Value', 'notificator-project' ) ) ); ?>"></label>
														<template x-if="Array.isArray(getConditionValueOptions(condition)) && getConditionValueOptions(condition).length">
															<select x-model="condition.value" class="notificator-condition-control w-full h-9 px-2 text-sm border border-gray-300 rounded bg-white focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500">
																<option value=""><?php esc_html_e( 'Select…', 'notificator-project' ); ?></option>
																<template x-for="opt in getConditionValueOptions(condition)" :key="'opt-' + opt.value">
																	<option :value="opt.value" x-text="opt.label"></option>
																</template>
															</select>
														</template>
														<template x-if="!(Array.isArray(getConditionValueOptions(condition)) && getConditionValueOptions(condition).length)">
															<input :type="condition.value_type || 'text'" x-model="condition.value"
																class="notificator-condition-control w-full h-9 px-2 text-sm border border-gray-300 rounded bg-white focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500"
																:placeholder="condition.value_placeholder || <?php echo esc_attr( wp_json_encode( __( 'Enter value', 'notificator-project' ) ) ); ?>">
														</template>
													</div>
												</div>

												<!-- Remove button -->
												<button @click="removeCondition(cIndex)" type="button"
													x-show="!(condition.locked || areAllConditionsLocked())"
													class="cursor-pointer inline-flex items-center justify-center h-9 w-9 mt-5 bg-red-50/40 border border-red-200/70 text-red-700 hover:bg-red-50 rounded-lg transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500/40">
													<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
														<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
													</svg>
												</button>
											</div>
										</template>

										<template x-if="scenarioForm.conditions.length === 0">
											<p class="text-xs text-gray-500 italic text-center py-2">
										<?php esc_html_e( 'Every occurrence of this event will send a notification.', 'notificator-project' ); ?>
											</p>
										</template>

										<template x-if="scenarioForm.conditions.length > 1">
											<p class="text-xs text-gray-500 mt-2">
												<?php esc_html_e( 'All rules must match before a notification is sent.', 'notificator-project' ); ?>
											</p>
										</template>
									</div>
								</div>

								<div>
									<label class="block text-sm font-semibold text-gray-700 mb-1"><?php esc_html_e( 'Notification message', 'notificator-project' ); ?></label>
									<p class="text-xs text-gray-500 mb-2"><?php esc_html_e( 'Write the message recipients will see. Insert event information using the buttons below.', 'notificator-project' ); ?></p>
									<textarea x-model="scenarioForm.scenario_notes" rows="3"
										class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
										placeholder="<?php esc_attr_e( 'Example: Order #123 needs your attention', 'notificator-project' ); ?>"></textarea>
									<template x-if="getNoteTagSuggestions().length">
										<div class="mt-2">
											<div class="text-xs text-gray-500 mb-1"><?php esc_html_e( 'Insert event information:', 'notificator-project' ); ?></div>
											<div class="flex flex-wrap gap-2">
												<template x-for="tag in getNoteTagSuggestions()" :key="'note-tag-' + tag.value">
													<button type="button" @click="insertNoteTag(tag.value)" :title="'Inserts {{' + tag.value + '}}'"
														class="notificator-note-tag-btn text-xs px-2 py-1 rounded bg-indigo-50 text-indigo-700 border border-indigo-100 hover:border-indigo-200">
														<span>＋ </span><span x-text="tag.label"></span>
													</button>
												</template>
											</div>
										</div>
									</template>
								</div>

							<div class="flex items-center gap-3 p-4 bg-gray-50 rounded-lg">
									<label class="inline-flex items-center cursor-pointer gap-2">
									<input type="checkbox" x-model="scenarioForm.enabled" aria-label="<?php echo esc_attr( __( 'Enable notification', 'notificator-project' ) ); ?>">
										<div>
										<div class="text-sm font-semibold text-gray-900"><?php esc_html_e( 'Enable notification', 'notificator-project' ); ?></div>
											<div class="text-xs text-gray-500"><?php esc_html_e( 'Start watching for this event immediately', 'notificator-project' ); ?></div>
										</div>
									</label>
								</div>
							</div>

							<div class="notificator-review-card" aria-live="polite">
								<div class="notificator-review-card__label"><?php esc_html_e( 'Review', 'notificator-project' ); ?></div>
								<strong x-text="scenarioForm.scenario_name || <?php echo esc_attr( wp_json_encode( __( 'Untitled notification', 'notificator-project' ) ) ); ?>"></strong>
								<p><span x-text="scenarioForm.description || scenarioForm.hook_name"></span> · <span x-text="(scenarioForm.severity || 'info').charAt(0).toUpperCase() + (scenarioForm.severity || 'info').slice(1)"></span></p>
								<div class="notificator-review-tags">
									<span x-show="scenarioForm.send_dashboard"><?php esc_html_e( 'Dashboard', 'notificator-project' ); ?></span>
									<span x-show="scenarioForm.send_push"><?php esc_html_e( 'Push', 'notificator-project' ); ?></span>
									<span x-show="scenarioForm.send_mqtt"><?php esc_html_e( 'MQTT', 'notificator-project' ); ?></span>
									<span x-show="scenarioForm.conditions && scenarioForm.conditions.length" x-text="scenarioForm.conditions.length + ' condition' + (scenarioForm.conditions.length === 1 ? '' : 's')"></span>
								</div>
							</div>
						</div>
					</div>

					<!-- Modal Footer -->
					<div class="px-6 py-4 bg-gray-50 border-t">
						<div x-show="modalError" x-cloak class="notificator-modal-error" role="alert" x-text="modalError"></div>
						<div class="flex items-center justify-between gap-3 flex-wrap">
						<template x-if="editingIndex === null">
							<button @click="modalStep > 1 ? modalStep-- : modalOpen = false" type="button" class="btn-secondary btn-secondary--ghost">
								<span x-show="modalStep === 1"><?php esc_html_e( 'Cancel', 'notificator-project' ); ?></span>
								<span x-show="modalStep > 1">← <?php esc_html_e( 'Back', 'notificator-project' ); ?></span>
							</button>
						</template>

						<div class="flex items-center gap-2">
							<button x-show="modalStep === 1" type="button" class="btn-primary" disabled>
								<?php esc_html_e( 'Choose a plugin', 'notificator-project' ); ?>
							</button>
							<button x-show="modalStep === 2" type="button" class="btn-primary" disabled>
								<?php esc_html_e( 'Choose an event', 'notificator-project' ); ?>
							</button>
							<button @click="modalStep === 3 ? saveScenario() : null"
								x-show="modalStep === 3"
								type="button"
								class="btn-primary">
								<span x-text="editingIndex !== null ? <?php echo esc_attr( wp_json_encode( __( 'Update notification', 'notificator-project' ) ) ); ?> : <?php echo esc_attr( wp_json_encode( __( 'Create notification', 'notificator-project' ) ) ); ?>"></span>
							</button>
						</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the initial or newly activated plugin scan prompt.
	 *
	 * @param array<int, array{file: string, name: string, slug: string}> $unscanned_plugins Unscanned active plugins.
	 * @return void
	 */
	private function render_scan_recommendation( $unscanned_plugins = array() ) {
		$unscanned_count = count( $unscanned_plugins );
		if ( 1 === $unscanned_count ) {
			$title = __( 'New plugin detected', 'notificator-project' );
			/* translators: %s: Name of the newly activated plugin. */
			$message = sprintf( __( '%s was activated after the last scan. Scan again to discover its events and templates.', 'notificator-project' ), (string) $unscanned_plugins[0]['name'] );
		} elseif ( 1 < $unscanned_count ) {
			$title = __( 'New plugins detected', 'notificator-project' );
			/* translators: %d: Number of newly activated plugins. */
			$message = sprintf( __( '%d plugins were activated after the last scan. Scan again to discover their events and templates.', 'notificator-project' ), $unscanned_count );
		} else {
			$title   = __( 'First-time setup', 'notificator-project' );
			$message = __( 'Run a quick plugin scan to discover available hooks and unlock ready-to-use templates.', 'notificator-project' );
		}
		?>
		<div id="notificator-scan-recommendation" class="notificator-first-time-setup">
			<div class="notificator-first-time-setup__icon" aria-hidden="true">
				<span class="dashicons dashicons-search"></span>
			</div>
			<div class="notificator-first-time-setup__content">
				<h3><?php echo esc_html( $title ); ?></h3>
				<p><?php echo esc_html( $message ); ?></p>
				<button type="button" id="notificator-scan-recommendation-button" class="btn-secondary">
					<span class="dashicons dashicons-update"></span>
					<?php esc_html_e( 'Scan Plugins Now', 'notificator-project' ); ?>
				</button>
			</div>
		</div>
		<?php
	}
}
