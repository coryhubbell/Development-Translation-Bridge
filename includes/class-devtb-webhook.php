<?php
/**
 * DEVTB Webhook Handler
 *
 * Manages webhook notifications for job completions and events
 *
 * @package    DevelopmentTranslation_Bridge
 * @subpackage API
 * @version    4.9.0
 */

class DEVTB_Webhook {
	/**
	 * Logger instance
	 *
	 * @var DEVTB_Logger
	 */
	private $logger;

	/**
	 * Max retry attempts
	 *
	 * @var int
	 */
	private $max_retries = 3;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->logger = new DEVTB_Logger();
	}

	/**
	 * Send webhook notification
	 *
	 * @param string $event Event type.
	 * @param array  $payload Payload data.
	 * @return bool Success.
	 */
	public function send( string $event, array $payload ): bool {
		$webhook_url = get_option( 'devtb_webhook_url' );

		if ( empty( $webhook_url ) ) {
			return false;
		}

		// Add event metadata
		$full_payload = array_merge([
			'event'     => $event,
			'timestamp' => current_time( 'mysql' ),
			'site_url'  => get_site_url(),
		], $payload );

		// Send webhook
		$response = $this->send_request( $webhook_url, $full_payload, $event );

		if ( is_wp_error( $response ) ) {
			$this->logger->error( 'Webhook failed', [
				'event' => $event,
				'url'   => $webhook_url,
				'error' => $response->get_error_message(),
			]);

			// Retry failed webhooks
			$this->schedule_retry( $webhook_url, $event, $full_payload );

			return false;
		}

		$this->logger->info( 'Webhook sent successfully', [
			'event'  => $event,
			'url'    => $webhook_url,
			'status' => wp_remote_retrieve_response_code( $response ),
		]);

		return true;
	}

	/**
	 * Send HTTP request
	 *
	 * @param string $url Webhook URL.
	 * @param array  $payload Payload.
	 * @param string $event Event type.
	 * @return array|WP_Error Response or error.
	 */
	private function send_request( string $url, array $payload, string $event ) {
		$args = [
			'body'        => wp_json_encode( $payload ),
			'headers'     => [
				'Content-Type'  => 'application/json',
				'X-DEVTB-Event'  => $event,
				'X-DEVTB-Signature' => $this->generate_signature( $payload ),
			],
			'timeout'     => 10,
			'blocking'    => true,
			'httpversion' => '1.1',
		];

		return wp_remote_post( $url, $args );
	}

	/**
	 * Generate HMAC signature for payload
	 *
	 * @param array $payload Payload data.
	 * @return string Signature.
	 */
	private function generate_signature( array $payload ): string {
		$secret = get_option( 'devtb_webhook_secret' );

		if ( empty( $secret ) ) {
			$secret = wp_generate_password( 32, false );
			update_option( 'devtb_webhook_secret', $secret );
		}

		$payload_json = wp_json_encode( $payload );
		return hash_hmac( 'sha256', $payload_json, $secret );
	}

	/**
	 * Schedule retry for failed webhook
	 *
	 * @param string $url Webhook URL.
	 * @param string $event Event type.
	 * @param array  $payload Payload.
	 */
	private function schedule_retry( string $url, string $event, array $payload ) {
		// Get current retry count
		$retry_count = $payload['retry_count'] ?? 0;

		if ( $retry_count >= $this->max_retries ) {
			$this->logger->warning( 'Webhook max retries exceeded', [
				'event' => $event,
				'url'   => $url,
			]);
			return;
		}

		// Increment retry count
		$payload['retry_count'] = $retry_count + 1;

		// Schedule retry with exponential backoff
		$delay = pow( 2, $retry_count ) * MINUTE_IN_SECONDS;

		wp_schedule_single_event(
			time() + $delay,
			'devtb_retry_webhook',
			[ $url, $event, $payload ]
		);

		$this->logger->info( 'Webhook retry scheduled', [
			'event'       => $event,
			'retry_count' => $payload['retry_count'],
			'delay'       => $delay,
		]);
	}

	/**
	 * Retry webhook (called by cron)
	 *
	 * @param string $url Webhook URL.
	 * @param string $event Event type.
	 * @param array  $payload Payload.
	 */
	public function retry( string $url, string $event, array $payload ) {
		$response = $this->send_request( $url, $payload, $event );

		if ( is_wp_error( $response ) ) {
			$this->schedule_retry( $url, $event, $payload );
		} else {
			$this->logger->info( 'Webhook retry successful', [
				'event'       => $event,
				'retry_count' => $payload['retry_count'] ?? 0,
			]);
		}
	}

	/**
	 * Verify webhook signature
	 *
	 * @param string $payload_json JSON payload.
	 * @param string $signature Provided signature.
	 * @return bool Valid signature.
	 */
	public static function verify_signature( string $payload_json, string $signature ): bool {
		$secret = get_option( 'devtb_webhook_secret' );

		if ( empty( $secret ) ) {
			return false;
		}

		$expected_signature = hash_hmac( 'sha256', $payload_json, $secret );
		return hash_equals( $expected_signature, $signature );
	}

	/**
	 * Get webhook statistics
	 *
	 * @return array Statistics.
	 */
	public function get_stats(): array {
		// This would query logs or a webhook history table
		return [
			'total_sent'   => 0,
			'successful'   => 0,
			'failed'       => 0,
			'pending'      => 0,
		];
	}
}

// Register webhook retry cron action
add_action( 'devtb_retry_webhook', function( $url, $event, $payload ) {
	$webhook = new DEVTB_Webhook();
	$webhook->retry( $url, $event, $payload );
}, 10, 3 );
