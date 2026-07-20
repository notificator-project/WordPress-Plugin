<?php
/**
 * Plugin Name: Notificator Sample Plugin
 * Description: Demonstrates a registered custom event and ready-made Notificator template.
 * Version: 1.1.0
 * Author: Vagelis Papaioannou
 * Requires PHP: 7.2
 * Text Domain: notificator-sample-plugin
 *
 * @package NotificatorCompanionSample
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Register the sample event for scan-free discovery. */
function notificator_sample_register_event() {
	if ( ! function_exists( 'notificator_companion_register_event' ) ) {
		return;
	}

	notificator_companion_register_event(
		array(
			'hook_name'   => 'notificator_sample_message_sent',
			'label'       => 'Sample message sent',
			'description' => 'Runs when the sample plugin sends its demonstration message.',
			'plugin_slug' => 'notificator-sample',
			'plugin_name' => 'Notificator Sample Plugin',
			'plugin_file' => plugin_basename( __FILE__ ),
			'arg_names'   => array( 'message', 'suffix' ),
		)
	);
}
add_action( 'notificator_companion_register_events', 'notificator_sample_register_event' );

/** Register a ready-made notification template for the sample event. */
function notificator_sample_register_template() {
	if ( ! function_exists( 'notificator_companion_register_template' ) ) {
		return;
	}

	notificator_companion_register_template(
		array(
			'icon'            => 'dashicons-megaphone',
			'title'           => 'Sample message notification',
			'hook_name'       => 'notificator_sample_message_sent',
			'description'     => 'Receive an alert when the sample message is sent.',
			'scenario_name'   => 'Sample Message Sent',
			'default_notes'   => 'Sample message: {{message}}{{suffix}}',
			'required_plugin' => 'notificator-sample',
			'severity'        => 'info',
			'hook_meta'       => array(
				'label'         => 'Sample message sent',
				'type'          => 'action',
				'arg_names'     => array( 'message', 'suffix' ),
				'payload_arity' => 2,
			),
			'conditions'      => array(),
		)
	);
}
add_action( 'notificator_companion_register_templates', 'notificator_sample_register_template' );

/** Add the demonstration page below Notificator. */
function notificator_sample_register_admin_page() {
	add_submenu_page(
		'notificator',
		'Sample Integration',
		'Sample Integration',
		'manage_options',
		'notificator-sample-integration',
		'notificator_sample_render_admin_page'
	);
}
add_action( 'admin_menu', 'notificator_sample_register_admin_page', 20 );

/** Render a safe form that can trigger the sample event on demand. */
function notificator_sample_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$triggered = isset( $_GET['notificator_sample_sent'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['notificator_sample_sent'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Notificator sample integration', 'notificator-sample-plugin' ); ?></h1>
		<p><?php esc_html_e( 'Use this button to emit the registered sample event and test your notification.', 'notificator-sample-plugin' ); ?></p>
		<?php if ( $triggered ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'The sample event was emitted.', 'notificator-sample-plugin' ); ?></p></div>
		<?php endif; ?>
		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
			<input type="hidden" name="action" value="notificator_sample_trigger_event">
			<?php wp_nonce_field( 'notificator_sample_trigger_event' ); ?>
			<label for="notificator-sample-message"><?php esc_html_e( 'Sample message', 'notificator-sample-plugin' ); ?></label>
			<input id="notificator-sample-message" name="message" type="text" class="regular-text" value="Hello from the sample plugin">
			<?php submit_button( __( 'Trigger sample event', 'notificator-sample-plugin' ) ); ?>
		</form>
	</div>
	<?php
}

/** Validate the request and emit the event. */
function notificator_sample_handle_trigger() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to trigger this event.', 'notificator-sample-plugin' ) );
	}

	check_admin_referer( 'notificator_sample_trigger_event' );
	$message = isset( $_POST['message'] ) ? sanitize_text_field( wp_unslash( $_POST['message'] ) ) : 'Hello from the sample plugin';
	if ( '' === $message ) {
		$message = 'Hello from the sample plugin';
	}

	do_action( 'notificator_sample_message_sent', $message, '!' );

	wp_safe_redirect(
		add_query_arg(
			'notificator_sample_sent',
			'1',
			admin_url( 'admin.php?page=notificator-sample-integration' )
		)
	);
	exit;
}
add_action( 'admin_post_notificator_sample_trigger_event', 'notificator_sample_handle_trigger' );
