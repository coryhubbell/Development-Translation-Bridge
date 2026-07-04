<?php
/**
 * DEVTB Rate Limiter
 *
 * Implements rate limiting for API requests to prevent abuse
 *
 * @package    DevelopmentTranslation_Bridge
 * @subpackage API
 * @version    4.14.0
 */

class DEVTB_Rate_Limiter {

	/**
	 * Transient buffer time in seconds
	 */
	private const TRANSIENT_BUFFER = 60;

	/**
	 * Default retry delay in seconds
	 */
	private const DEFAULT_RETRY_DELAY = 60;

	/**
	 * Logger instance
	 *
	 * @var DEVTB_Logger
	 */
	private DEVTB_Logger $logger;

	/**
	 * Rate limit tiers
	 *
	 * @var array
	 */
	private array $tiers;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->logger = new DEVTB_Logger();
		$this->tiers  = DEVTB_Config::RATE_LIMIT_TIERS;
	}

	/**
	 * Mask identifier for logging
	 *
	 * Creates a safe hash prefix for logging without exposing API keys.
	 *
	 * @param string $identifier The identifier to mask.
	 * @return string Masked identifier safe for logging.
	 */
	private function mask_identifier( string $identifier ): string {
		// Extract the type prefix (key_, user_, ip_)
		$parts = explode( '_', $identifier, 2 );
		$type  = $parts[0] ?? 'unknown';

		// Create a short hash of the full identifier
		$hash = substr( hash( 'sha256', $identifier ), 0, 8 );

		return $type . '_' . $hash;
	}

	/**
	 * Check rate limit for request
	 *
	 * @param string $identifier Unique identifier (API key, IP, user ID).
	 * @param string $tier       Rate limit tier.
	 * @return array Rate limit status.
	 */
	public function check_limit( string $identifier, string $tier = 'free' ): array {
		$limits = $this->tiers[ $tier ] ?? $this->tiers['free'];

		// Check hourly limit
		$hourly = $this->check_window( $identifier, 'hour', $limits['requests_per_hour'] );

		// Check per-minute limit
		$minute = $this->check_window( $identifier, 'minute', $limits['requests_per_minute'] );

		// Check burst limit (last 10 seconds)
		$burst = $this->check_window( $identifier, 'burst', $limits['burst_limit'], 10 );

		// Determine if request should be allowed
		$allowed = $hourly['allowed'] && $minute['allowed'] && $burst['allowed'];

		$result = [
			'allowed'         => $allowed,
			'tier'            => $tier,
			'limits'          => [
				'hourly' => [
					'limit'     => $limits['requests_per_hour'],
					'remaining' => $hourly['remaining'],
					'reset_at'  => $hourly['reset_at'],
				],
				'minute' => [
					'limit'     => $limits['requests_per_minute'],
					'remaining' => $minute['remaining'],
					'reset_at'  => $minute['reset_at'],
				],
				'burst'  => [
					'limit'     => $limits['burst_limit'],
					'remaining' => $burst['remaining'],
					'reset_at'  => $burst['reset_at'],
				],
			],
			'retry_after'     => null,
		];

		// Calculate retry-after if limited
		if ( ! $allowed ) {
			$result['retry_after'] = $this->calculate_retry_after( $hourly, $minute, $burst );
		}

		// Log if rate limited
		if ( ! $allowed ) {
			$this->logger->warning( 'Rate limit exceeded', [
				'identifier'  => $this->mask_identifier( $identifier ),
				'tier'        => $tier,
				'retry_after' => $result['retry_after'],
			] );
		}

		return $result;
	}

	/**
	 * Record request
	 *
	 * @param string $identifier Unique identifier.
	 * @param string $tier       Rate limit tier.
	 * @return bool Success.
	 */
	public function record_request( string $identifier, string $tier = 'free' ): bool {
		$timestamp = time();

		// Record in each time window
		$this->record_in_window( $identifier, 'hour', $timestamp );
		$this->record_in_window( $identifier, 'minute', $timestamp );
		$this->record_in_window( $identifier, 'burst', $timestamp );

		$this->logger->debug( 'Request recorded', [
			'identifier' => $this->mask_identifier( $identifier ),
			'tier'       => $tier,
		] );

		return true;
	}

	/**
	 * Check rate limit for time window
	 *
	 * @param string $identifier Identifier.
	 * @param string $window     Window type (hour, minute, burst).
	 * @param int    $limit      Request limit.
	 * @param int    $duration   Window duration in seconds.
	 * @return array Window status.
	 */
	private function check_window( string $identifier, string $window, int $limit, ?int $duration = null ): array {
		// Determine window duration
		if ( $duration === null ) {
			$duration = $this->get_window_duration( $window );
		}

		$key = $this->get_window_key( $identifier, $window );
		$requests = get_transient( $key );

		if ( ! $requests ) {
			$requests = [];
		}

		// Clean old requests
		$cutoff = time() - $duration;
		$requests = array_filter( $requests, function( $timestamp ) use ( $cutoff ) {
			return $timestamp > $cutoff;
		});

		$count = count( $requests );
		$remaining = max( 0, $limit - $count );
		$allowed = $count < $limit;

		// Calculate reset time
		if ( ! empty( $requests ) ) {
			$oldest = min( $requests );
			$reset_at = $oldest + $duration;
		} else {
			$reset_at = time() + $duration;
		}

		return [
			'allowed'   => $allowed,
			'count'     => $count,
			'remaining' => $remaining,
			'reset_at'  => $reset_at,
		];
	}

	/**
	 * Record request in time window
	 *
	 * @param string $identifier Identifier.
	 * @param string $window     Window type.
	 * @param int    $timestamp  Timestamp.
	 */
	private function record_in_window( string $identifier, string $window, int $timestamp ) {
		$key = $this->get_window_key( $identifier, $window );
		$duration = $this->get_window_duration( $window );

		$requests = get_transient( $key );

		if ( ! $requests ) {
			$requests = [];
		}

		// Add new request
		$requests[] = $timestamp;

		// Clean old requests
		$cutoff = $timestamp - $duration;
		$requests = array_filter( $requests, function( $ts ) use ( $cutoff ) {
			return $ts > $cutoff;
		});

		// Store with expiration (add buffer to ensure data available for window duration).
		set_transient( $key, $requests, $duration + self::TRANSIENT_BUFFER );
	}

	/**
	 * Get window duration in seconds
	 *
	 * @param string $window Window type.
	 * @return int Duration in seconds.
	 */
	private function get_window_duration( string $window ): int {
		return DEVTB_Config::get_time_window( $window );
	}

	/**
	 * Get window cache key
	 *
	 * @param string $identifier Identifier.
	 * @param string $window     Window type.
	 * @return string Cache key.
	 */
	private function get_window_key( string $identifier, string $window ): string {
		return 'devtb_ratelimit_' . $window . '_' . md5( $identifier );
	}

	/**
	 * Calculate retry-after seconds
	 *
	 * @param array $hourly Hourly window data.
	 * @param array $minute Minute window data.
	 * @param array $burst  Burst window data.
	 * @return int Seconds to wait.
	 */
	private function calculate_retry_after( array $hourly, array $minute, array $burst ): int {
		$retry_times = [];

		if ( ! $hourly['allowed'] ) {
			$retry_times[] = $hourly['reset_at'] - time();
		}

		if ( ! $minute['allowed'] ) {
			$retry_times[] = $minute['reset_at'] - time();
		}

		if ( ! $burst['allowed'] ) {
			$retry_times[] = $burst['reset_at'] - time();
		}

		return ! empty( $retry_times ) ? min( $retry_times ) : self::DEFAULT_RETRY_DELAY;
	}

	/**
	 * Get rate limit tier for API key
	 *
	 * @param array $key_data API key data.
	 * @return string Tier name.
	 */
	public function get_tier_for_key( array $key_data ): string {
		// Check if tier is explicitly set
		if ( isset( $key_data['tier'] ) && isset( $this->tiers[ $key_data['tier'] ] ) ) {
			return $key_data['tier'];
		}

		// Default based on permissions
		if ( in_array( 'admin', $key_data['permissions'] ?? [], true ) ) {
			return 'enterprise';
		}

		return 'free';
	}

	/**
	 * Get identifier from request
	 *
	 * @param WP_REST_Request $request Request object.
	 * @param array|null      $key_data API key data if authenticated.
	 * @return string Identifier.
	 */
	public function get_identifier( WP_REST_Request $request, ?array $key_data = null ): string {
		// Use API key if available
		if ( $key_data && isset( $key_data['key'] ) ) {
			return 'key_' . $key_data['key'];
		}

		// Use user ID if logged in
		$user_id = get_current_user_id();
		if ( $user_id ) {
			return 'user_' . $user_id;
		}

		// Fall back to IP address
		$ip = $this->get_client_ip( $request );
		return 'ip_' . $ip;
	}

	/**
	 * Get client IP address
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return string IP address.
	 */
	private function get_client_ip( WP_REST_Request $request ): string {
		// Check for forwarded IP
		$forwarded = $request->get_header( 'X-Forwarded-For' );
		if ( $forwarded ) {
			$ips = explode( ',', $forwarded );
			return trim( $ips[0] );
		}

		// Check real IP header
		$real_ip = $request->get_header( 'X-Real-IP' );
		if ( $real_ip ) {
			return $real_ip;
		}

		// Use remote address
		return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
	}

	/**
	 * Reset rate limit for identifier
	 *
	 * @param string $identifier Identifier to reset.
	 * @return bool Success.
	 */
	public function reset_limit( string $identifier ): bool {
		$windows = [ 'hour', 'minute', 'burst' ];

		foreach ( $windows as $window ) {
			$key = $this->get_window_key( $identifier, $window );
			delete_transient( $key );
		}

		$this->logger->info( 'Rate limit reset', [
			'identifier' => $this->mask_identifier( $identifier ),
		] );

		return true;
	}

	/**
	 * Get rate limit headers for response
	 *
	 * @param array $limit_data Rate limit data from check_limit().
	 * @return array Headers.
	 */
	public function get_headers( array $limit_data ): array {
		$headers = [];

		// Use hourly limits for headers
		$hourly = $limit_data['limits']['hourly'];

		$headers['X-RateLimit-Limit'] = (string) $hourly['limit'];
		$headers['X-RateLimit-Remaining'] = (string) $hourly['remaining'];
		$headers['X-RateLimit-Reset'] = (string) $hourly['reset_at'];

		if ( ! $limit_data['allowed'] && $limit_data['retry_after'] ) {
			$headers['Retry-After'] = (string) $limit_data['retry_after'];
		}

		return $headers;
	}

	/**
	 * Get rate limit statistics
	 *
	 * @return array Statistics.
	 */
	public function get_stats(): array {
		// This would track rate limit hits, blocks, etc.
		// For now, return basic tier info
		return [
			'tiers'       => array_keys( $this->tiers ),
			'tier_limits' => $this->tiers,
		];
	}

	/**
	 * Update tier limits
	 *
	 * @param string $tier   Tier name.
	 * @param array  $limits New limits.
	 * @return bool Success.
	 */
	public function update_tier_limits( string $tier, array $limits ): bool {
		if ( ! isset( $this->tiers[ $tier ] ) ) {
			return false;
		}

		$this->tiers[ $tier ] = array_merge( $this->tiers[ $tier ], $limits );

		// Store in options
		update_option( 'devtb_rate_limit_tiers', $this->tiers );

		$this->logger->info( 'Rate limit tier updated', [
			'tier' => $tier,
		]);

		return true;
	}

	/**
	 * Load custom tier limits from options
	 */
	public function load_custom_tiers() {
		$custom_tiers = get_option( 'devtb_rate_limit_tiers' );

		if ( $custom_tiers && is_array( $custom_tiers ) ) {
			$this->tiers = array_merge( $this->tiers, $custom_tiers );
		}
	}
}
