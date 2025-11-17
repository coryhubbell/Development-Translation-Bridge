<?php
/**
 * Converter Factory
 *
 * Creates appropriate converter instances based on framework name.
 *
 * @package WordPress_Bootstrap_Claude
 * @subpackage Translation_Bridge
 * @since 3.0.0
 */

namespace WPBC\TranslationBridge\Core;

use WPBC\TranslationBridge\Converters\WPBC_Bootstrap_Converter;
use WPBC\TranslationBridge\Converters\WPBC_DIVI_Converter;
use WPBC\TranslationBridge\Converters\WPBC_Elementor_Converter;
use WPBC\TranslationBridge\Converters\WPBC_Avada_Converter;
use WPBC\TranslationBridge\Converters\WPBC_Bricks_Converter;
use WPBC\TranslationBridge\Converters\WPBC_WPBakery_Converter;
use WPBC\TranslationBridge\Converters\WPBC_Claude_Converter;

/**
 * Class WPBC_Converter_Factory
 *
 * Factory for creating framework-specific converters.
 */
class WPBC_Converter_Factory {

	/**
	 * Registered converters
	 *
	 * @var array<string, WPBC_Converter_Interface>
	 */
	private static array $converters = [];

	/**
	 * Create converter for specified framework
	 *
	 * @param string $framework Framework name (bootstrap, divi, elementor, avada, bricks, wpbakery, claude).
	 * @return WPBC_Converter_Interface|null Converter instance or null if not found.
	 * @throws \InvalidArgumentException If framework not supported.
	 */
	public static function create( string $framework ): ?WPBC_Converter_Interface {
		$framework = strtolower( trim( $framework ) );

		// Return cached converter if exists
		if ( isset( self::$converters[ $framework ] ) ) {
			return self::$converters[ $framework ];
		}

		// Create new converter instance
		$converter = self::create_converter_instance( $framework );

		if ( $converter ) {
			self::$converters[ $framework ] = $converter;
			return $converter;
		}

		throw new \InvalidArgumentException( sprintf( 'Unsupported framework: %s', $framework ) );
	}

	/**
	 * Create converter instance for framework
	 *
	 * @param string $framework Framework name.
	 * @return WPBC_Converter_Interface|null
	 */
	private static function create_converter_instance( string $framework ): ?WPBC_Converter_Interface {
		switch ( $framework ) {
			case 'bootstrap':
				return new WPBC_Bootstrap_Converter();

			case 'divi':
				return new WPBC_DIVI_Converter();

			case 'elementor':
				return new WPBC_Elementor_Converter();

			case 'avada':
			case 'fusion':
				return new WPBC_Avada_Converter();

			case 'bricks':
				return new WPBC_Bricks_Converter();

			case 'wpbakery':
			case 'vc':
			case 'visualcomposer':
				return new WPBC_WPBakery_Converter();

			case 'claude':
			case 'claude-ai':
			case 'ai':
				return new WPBC_Claude_Converter();

			default:
				return null;
		}
	}

	/**
	 * Get all supported frameworks
	 *
	 * @return array<string> Array of framework names.
	 */
	public static function get_supported_frameworks(): array {
		return [ 'bootstrap', 'divi', 'elementor', 'avada', 'bricks', 'wpbakery', 'claude' ];
	}

	/**
	 * Check if framework is supported
	 *
	 * @param string $framework Framework name.
	 * @return bool
	 */
	public static function is_supported( string $framework ): bool {
		return in_array( strtolower( trim( $framework ) ), self::get_supported_frameworks(), true );
	}

	/**
	 * Register custom converter
	 *
	 * @param string                    $framework Framework name.
	 * @param WPBC_Converter_Interface $converter Converter instance.
	 * @return void
	 */
	public static function register( string $framework, WPBC_Converter_Interface $converter ): void {
		self::$converters[ strtolower( trim( $framework ) ) ] = $converter;
	}

	/**
	 * Clear converter cache
	 *
	 * @return void
	 */
	public static function clear_cache(): void {
		self::$converters = [];
	}

	/**
	 * Get all registered converters
	 *
	 * @return array<string, WPBC_Converter_Interface>
	 */
	public static function get_all_converters(): array {
		// Ensure all converters are instantiated
		foreach ( self::get_supported_frameworks() as $framework ) {
			if ( ! isset( self::$converters[ $framework ] ) ) {
				try {
					self::create( $framework );
				} catch ( \InvalidArgumentException $e ) {
					// Skip if converter can't be created
					continue;
				}
			}
		}

		return self::$converters;
	}
}
