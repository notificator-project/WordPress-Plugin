<?php
/**
 * Immediate outbound delivery with WordPress-Cron-backed retries.
 *
 * @package NotificatorCompanion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Queues notification requests and retries transient failures.
 */
class Notificator_Companion_Delivery_Queue {

	const OPTION_NAME = 'notificator_companion_delivery_queue';
	const CRON_HOOK   = 'notificator_companion_process_delivery';

	/**
	 * Callback that builds authenticated request headers for an API key.
	 *
	 * @var callable
	 */
	private $headers_callback;

	/**
	 * Callback that injects ephemeral data immediately before delivery.
	 *
	 * @var callable|null
	 */
	private $body_callback;

	/**
	 * Initialize the delivery queue.
	 *
	 * @param callable $headers_callback Builds signed request headers.
	 * @param callable $body_callback    Optional request-body preparation callback.
	 */
	public function __construct( $headers_callback, $body_callback = null ) {
		$this->headers_callback = $headers_callback;
		$this->body_callback    = $body_callback;
		add_action( self::CRON_HOOK, array( $this, 'process' ), 10, 1 );
	}

	/**
	 * Deliver one notification immediately and retain it only when a retry is needed.
	 *
	 * @param array  $api_keys API keys.
	 * @param string $body JSON request body.
	 * @param array  $meta Non-sensitive log metadata.
	 * @return string|false Queue id or false on failure.
	 */
	public function enqueue( $api_keys, $body, $meta = array() ) {
		$api_keys = array_values( array_filter( array_map( 'strval', (array) $api_keys ), 'strlen' ) );
		if ( empty( $api_keys ) || '' === (string) $body ) {
			return false;
		}

		$id           = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'delivery_', true );
		$queue        = $this->get_queue();
		$queue[ $id ] = array(
			'id'         => $id,
			'api_keys'   => $api_keys,
			'body'       => (string) $body,
			'meta'       => is_array( $meta ) ? $meta : array(),
			'attempts'   => 0,
			'created_at' => time(),
			'updated_at' => time(),
			'status'     => 'pending',
		);

		// Keep the option bounded even if cron is disabled for a long period.
		if ( count( $queue ) > 100 ) {
			$queue = array_slice( $queue, -100, null, true );
		}
		update_option( self::OPTION_NAME, $queue, false );

		// Preserve the plugin's instant-alert behavior. process() removes successful
		// jobs immediately and schedules WP-Cron only after a transient failure.
		$this->process( $id );

		return $id;
	}

	/**
	 * Process one queued notification.
	 *
	 * @param string $id Queue id.
	 * @return void
	 */
	public function process( $id ) {
		$queue = $this->get_queue();
		if ( ! isset( $queue[ $id ] ) || ! is_array( $queue[ $id ] ) ) {
			return;
		}

		$job               = $queue[ $id ];
		$job['attempts']   = isset( $job['attempts'] ) ? (int) $job['attempts'] + 1 : 1;
		$job['updated_at'] = time();
		$job['status']     = 'processing';
		$queue[ $id ]      = $job;
		update_option( self::OPTION_NAME, $queue, false );

		$delivered = 0;
		$errors    = array();
		foreach ( (array) $job['api_keys'] as $api_key ) {
			$request_body = is_callable( $this->body_callback )
				? call_user_func( $this->body_callback, $job['body'] )
				: $job['body'];
			if ( ! is_string( $request_body ) || '' === $request_body ) {
				$errors[] = 'Request body preparation failed';
				continue;
			}
			$headers  = is_callable( $this->headers_callback )
				? call_user_func( $this->headers_callback, $api_key, $request_body )
				: array();
			$response = wp_remote_post(
				notificator_companion_get_api_endpoint(),
				array(
					'timeout'     => 15,
					'data_format' => 'body',
					'headers'     => $headers,
					'body'        => $request_body,
				)
			);

			if ( is_wp_error( $response ) ) {
				$errors[] = $response->get_error_message();
				continue;
			}

			$code = (int) wp_remote_retrieve_response_code( $response );
			if ( $code >= 200 && $code < 300 ) {
				++$delivered;
			} else {
				$errors[] = sprintf( 'HTTP %d', $code );
			}
		}

		$total   = count( (array) $job['api_keys'] );
		$success = $delivered === $total;
		$partial = $delivered > 0 && $delivered < $total;
		if ( ! $success && ! $partial && $job['attempts'] < 3 ) {
			$job['status']     = 'retrying';
			$job['last_error'] = implode( '; ', array_unique( $errors ) );
			$queue[ $id ]      = $job;
			update_option( self::OPTION_NAME, $queue, false );
			$delay     = 1 === $job['attempts'] ? 60 : 300;
			$scheduled = wp_schedule_single_event( time() + $delay, self::CRON_HOOK, array( $id ) );
			if ( false === $scheduled || is_wp_error( $scheduled ) ) {
				unset( $queue[ $id ] );
				update_option( self::OPTION_NAME, $queue, false );
				do_action( 'notificator_companion_delivery_result', $job, 'failed', $job['last_error'] . '; retry could not be scheduled' );
				return;
			}
			do_action( 'notificator_companion_delivery_result', $job, 'retrying', $job['last_error'] );
			return;
		}

		$status = $success ? 'delivered' : ( $partial ? 'partial' : 'failed' );
		$error  = implode( '; ', array_unique( $errors ) );
		unset( $queue[ $id ] );
		update_option( self::OPTION_NAME, $queue, false );
		do_action( 'notificator_companion_delivery_result', $job, $status, $error );
	}

	/**
	 * Return queue totals for the Overview page.
	 *
	 * @return array
	 */
	public function get_summary() {
		$queue    = $this->get_queue();
		$retrying = 0;
		foreach ( $queue as $job ) {
			if ( is_array( $job ) && isset( $job['status'] ) && 'retrying' === $job['status'] ) {
				++$retrying;
			}
		}
		return array(
			'pending'  => count( $queue ),
			'retrying' => $retrying,
		);
	}

	/**
	 * Load the persisted delivery queue.
	 *
	 * @return array
	 */
	private function get_queue() {
		$queue = get_option( self::OPTION_NAME, array() );
		return is_array( $queue ) ? $queue : array();
	}
}
