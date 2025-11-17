<?php
/**
 * Parser Factory
 *
 * Creates appropriate parser instances based on framework name.
 *
 * @package WordPress_Bootstrap_Claude
 * @subpackage Translation_Bridge
 * @since 3.0.0
 */

namespace WPBC\TranslationBridge\Core;

use WPBC\TranslationBridge\Parsers\WPBC_Bootstrap_Parser;
use WPBC\TranslationBridge\Parsers\WPBC_DIVI_Parser;
use WPBC\TranslationBridge\Parsers\WPBC_Elementor_Parser;
use WPBC\TranslationBridge\Parsers\WPBC_Avada_Parser;
use WPBC\TranslationBridge\Parsers\WPBC_Bricks_Parser;
use WPBC\TranslationBridge\Parsers\WPBC_WPBakery_Parser;
use WPBC\TranslationBridge\Parsers\WPBC_Claude_Parser;

/**
 * Class WPBC_Parser_Factory
 *
 * Factory for creating framework-specific parsers.
 */
class WPBC_Parser_Factory {

	/**
	 * Registered parsers
	 *
	 * @var array<string, WPBC_Parser_Interface>
	 */
	private static array $parsers = [];

	/**
	 * Create parser for specified framework
	 *
	 * @param string $framework Framework name (bootstrap, divi, elementor, avada, bricks, wpbakery, claude).
	 * @return WPBC_Parser_Interface|null Parser instance or null if not found.
	 * @throws \InvalidArgumentException If framework not supported.
	 */
	public static function create( string $framework ): ?WPBC_Parser_Interface {
		$framework = strtolower( trim( $framework ) );

		// Return cached parser if exists
		if ( isset( self::$parsers[ $framework ] ) ) {
			return self::$parsers[ $framework ];
		}

		// Create new parser instance
		$parser = self::create_parser_instance( $framework );

		if ( $parser ) {
			self::$parsers[ $framework ] = $parser;
			return $parser;
		}

		throw new \InvalidArgumentException( sprintf( 'Unsupported framework: %s', $framework ) );
	}

	/**
	 * Create parser instance for framework
	 *
	 * @param string $framework Framework name.
	 * @return WPBC_Parser_Interface|null
	 */
	private static function create_parser_instance( string $framework ): ?WPBC_Parser_Interface {
		switch ( $framework ) {
			case 'bootstrap':
				return new WPBC_Bootstrap_Parser();

			case 'divi':
				return new WPBC_DIVI_Parser();

			case 'elementor':
				return new WPBC_Elementor_Parser();

			case 'avada':
			case 'fusion':
				return new WPBC_Avada_Parser();

			case 'bricks':
				return new WPBC_Bricks_Parser();

			case 'wpbakery':
			case 'vc':
			case 'visualcomposer':
				return new WPBC_WPBakery_Parser();

			case 'claude':
			case 'claude-ai':
			case 'ai':
				return new WPBC_Claude_Parser();

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
	 * Register custom parser
	 *
	 * @param string                 $framework Framework name.
	 * @param WPBC_Parser_Interface $parser Parser instance.
	 * @return void
	 */
	public static function register( string $framework, WPBC_Parser_Interface $parser ): void {
		self::$parsers[ strtolower( trim( $framework ) ) ] = $parser;
	}

	/**
	 * Clear parser cache
	 *
	 * @return void
	 */
	public static function clear_cache(): void {
		self::$parsers = [];
	}

	/**
	 * Get all registered parsers
	 *
	 * @return array<string, WPBC_Parser_Interface>
	 */
	public static function get_all_parsers(): array {
		// Ensure all parsers are instantiated
		foreach ( self::get_supported_frameworks() as $framework ) {
			if ( ! isset( self::$parsers[ $framework ] ) ) {
				try {
					self::create( $framework );
				} catch ( \InvalidArgumentException $e ) {
					// Skip if parser can't be created
					continue;
				}
			}
		}

		return self::$parsers;
	}
}
