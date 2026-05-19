<?php
/**
 * Parser Factory
 *
 * Creates appropriate parser instances based on framework name.
 *
 * @package DevelopmentTranslation_Bridge
 * @subpackage Translation_Bridge
 * @since 3.0.0
 */

namespace DEVTB\TranslationBridge\Core;

use DEVTB\TranslationBridge\Parsers\DEVTB_Bootstrap_Parser;
use DEVTB\TranslationBridge\Parsers\DEVTB_DIVI_Parser;
use DEVTB\TranslationBridge\Parsers\DEVTB_Elementor_Parser;
use DEVTB\TranslationBridge\Parsers\DEVTB_Avada_Parser;
use DEVTB\TranslationBridge\Parsers\DEVTB_Bricks_Parser;
use DEVTB\TranslationBridge\Parsers\DEVTB_WPBakery_Parser;
use DEVTB\TranslationBridge\Parsers\DEVTB_Beaver_Builder_Parser;
use DEVTB\TranslationBridge\Parsers\DEVTB_Gutenberg_Parser;
use DEVTB\TranslationBridge\Parsers\DEVTB_Oxygen_Parser;
use DEVTB\TranslationBridge\Parsers\DEVTB_Kadence_Parser;
use DEVTB\TranslationBridge\Parsers\DEVTB_Thrive_Parser;

/**
 * Class DEVTB_Parser_Factory
 *
 * Factory for creating framework-specific parsers.
 */
class DEVTB_Parser_Factory {

	/**
	 * Registered parsers
	 *
	 * @var array<string, DEVTB_Parser_Interface>
	 */
	private static array $parsers = [];

	/**
	 * Create parser for specified framework
	 *
	 * @param string $framework Framework name (bootstrap, divi, elementor, avada, bricks, wpbakery, beaver-builder, gutenberg, oxygen).
	 * @return DEVTB_Parser_Interface|null Parser instance or null if not found.
	 * @throws \InvalidArgumentException If framework not supported.
	 */
	public static function create( string $framework ): ?DEVTB_Parser_Interface {
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
	 * @return DEVTB_Parser_Interface|null
	 */
	private static function create_parser_instance( string $framework ): ?DEVTB_Parser_Interface {
		switch ( $framework ) {
			case 'bootstrap':
				return new DEVTB_Bootstrap_Parser();

			case 'divi':
				return new DEVTB_DIVI_Parser();

			case 'elementor':
				return new DEVTB_Elementor_Parser();

			case 'avada':
			case 'fusion':
				return new DEVTB_Avada_Parser();

			case 'bricks':
				return new DEVTB_Bricks_Parser();

			case 'wpbakery':
			case 'vc':
			case 'visualcomposer':
				return new DEVTB_WPBakery_Parser();

			case 'beaver':
			case 'beaverbuilder':
			case 'beaver-builder':
				return new DEVTB_Beaver_Builder_Parser();

			case 'gutenberg':
			case 'blocks':
			case 'block-editor':
				return new DEVTB_Gutenberg_Parser();

			case 'oxygen':
			case 'oxygen-builder':
				return new DEVTB_Oxygen_Parser();

			case 'kadence':
			case 'kadence-blocks':
			case 'kadenceblocks':
				return new DEVTB_Kadence_Parser();

			case 'thrive':
			case 'thrive-themes':
			case 'thrive-architect':
			case 'thrive-theme-builder':
				return new DEVTB_Thrive_Parser();

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
		return [ 'bootstrap', 'divi', 'elementor', 'avada', 'bricks', 'wpbakery', 'beaver-builder', 'gutenberg', 'oxygen', 'kadence', 'thrive' ];
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
	 * @param DEVTB_Parser_Interface $parser Parser instance.
	 * @return void
	 */
	public static function register( string $framework, DEVTB_Parser_Interface $parser ): void {
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
	 * @return array<string, DEVTB_Parser_Interface>
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
