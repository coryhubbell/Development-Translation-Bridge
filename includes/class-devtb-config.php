<?php
/**
 * DEVTB Configuration
 *
 * Centralized configuration constants and values for DevelopmentTranslation Bridge.
 * This class provides a single source of truth for shared configuration.
 *
 * @package    DevelopmentTranslation_Bridge
 * @subpackage Core
 * @version    4.3.2
 */

/**
 * DEVTB Configuration class
 *
 * Contains all shared configuration constants used across the plugin.
 *
 * NOTE: FRAMEWORKS / FRAMEWORK_NAMES mirror DEVTB_Converter_Factory but stay
 * defined here for the legacy procedural call sites. Keep both in lockstep.
 */
class DEVTB_Config {

	/**
	 * Plugin version
	 */
	public const VERSION = '4.3.2';

	/**
	 * API namespace
	 */
	public const API_NAMESPACE = 'devtb/v2';

	/**
	 * Supported frameworks for translation (14 frameworks as of v4.3.0).
	 *
	 * @var array
	 */
	public const FRAMEWORKS = array(
		'bootstrap',
		'divi',
		'divi-5',
		'elementor',
		'elementor-4',
		'avada',
		'bricks',
		'wpbakery',
		'beaver-builder',
		'gutenberg',
		'oxygen',
		'oxygen-6',
		'kadence',
		'thrive',
	);

	/**
	 * Framework display names
	 *
	 * @var array
	 */
	public const FRAMEWORK_NAMES = array(
		'bootstrap'      => 'Bootstrap',
		'divi'           => 'DIVI Builder',
		'divi-5'         => 'DIVI 5',
		'elementor'      => 'Elementor',
		'elementor-4'    => 'Elementor 4 Atomic',
		'avada'          => 'Avada / Fusion Builder',
		'bricks'         => 'Bricks Builder',
		'wpbakery'       => 'WPBakery Page Builder',
		'beaver-builder' => 'Beaver Builder',
		'gutenberg'      => 'Gutenberg / Block Editor',
		'oxygen'         => 'Oxygen Builder',
		'oxygen-6'       => 'Oxygen 6 / Breakdance',
		'kadence'        => 'Kadence Blocks',
		'thrive'         => 'Thrive Architect',
	);

	/**
	 * Rate limit time windows in seconds
	 */
	public const TIME_WINDOW_HOUR   = 3600;
	public const TIME_WINDOW_MINUTE = 60;
	public const TIME_WINDOW_BURST  = 10;

	/**
	 * Default rate limit tiers
	 *
	 * @var array
	 */
	public const RATE_LIMIT_TIERS = array(
		'free'       => array(
			'requests_per_hour'   => 100,
			'requests_per_minute' => 20,
			'burst_limit'         => 5,
		),
		'basic'      => array(
			'requests_per_hour'   => 500,
			'requests_per_minute' => 50,
			'burst_limit'         => 10,
		),
		'premium'    => array(
			'requests_per_hour'   => 2000,
			'requests_per_minute' => 100,
			'burst_limit'         => 20,
		),
		'enterprise' => array(
			'requests_per_hour'   => 10000,
			'requests_per_minute' => 500,
			'burst_limit'         => 50,
		),
		'key_creation' => array(
			'requests_per_hour'   => 5,
			'requests_per_minute' => 2,
			'burst_limit'         => 1,
		),
	);

	/**
	 * Logger configuration
	 */
	public const LOG_MAX_FILE_SIZE = 10485760; // 10MB
	public const LOG_MAX_FILES     = 5;

	/**
	 * Authentication configuration
	 */
	public const TOKEN_TTL_DEFAULT = 3600; // 1 hour in seconds

	/**
	 * API configuration
	 */
	public const API_TIMEOUT = 60; // Seconds for external API requests

	/**
	 * Encryption configuration
	 */
	public const ENCRYPTION_CIPHER = 'AES-256-CBC';

	/**
	 * Check if a framework is supported
	 *
	 * @param string $framework Framework identifier.
	 * @return bool True if supported.
	 */
	public static function is_valid_framework( string $framework ): bool {
		return in_array( strtolower( $framework ), self::FRAMEWORKS, true );
	}

	/**
	 * Get framework display name
	 *
	 * @param string $framework Framework identifier.
	 * @return string Display name or identifier if not found.
	 */
	public static function get_framework_name( string $framework ): string {
		$key = strtolower( $framework );
		return self::FRAMEWORK_NAMES[ $key ] ?? ucfirst( $framework );
	}

	/**
	 * Get all frameworks as options array
	 *
	 * @return array Associative array of framework => display name.
	 */
	public static function get_framework_options(): array {
		return self::FRAMEWORK_NAMES;
	}

	/**
	 * Get rate limit tier configuration
	 *
	 * @param string $tier Tier name.
	 * @return array Tier configuration or free tier as default.
	 */
	public static function get_rate_limit_tier( string $tier ): array {
		return self::RATE_LIMIT_TIERS[ $tier ] ?? self::RATE_LIMIT_TIERS['free'];
	}

	/**
	 * Get time window duration in seconds
	 *
	 * @param string $window Window name (hour, minute, burst).
	 * @return int Duration in seconds.
	 */
	public static function get_time_window( string $window ): int {
		switch ( $window ) {
			case 'hour':
				return self::TIME_WINDOW_HOUR;
			case 'minute':
				return self::TIME_WINDOW_MINUTE;
			case 'burst':
				return self::TIME_WINDOW_BURST;
			default:
				return self::TIME_WINDOW_HOUR;
		}
	}
}
