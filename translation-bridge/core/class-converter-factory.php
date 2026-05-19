<?php
/**
 * Converter Factory
 *
 * Creates appropriate converter instances based on framework name.
 *
 * @package DevelopmentTranslation_Bridge
 * @subpackage Translation_Bridge
 * @since 3.0.0
 */

namespace DEVTB\TranslationBridge\Core;

use DEVTB\TranslationBridge\Converters\DEVTB_Bootstrap_Converter;
use DEVTB\TranslationBridge\Converters\DEVTB_DIVI_Converter;
use DEVTB\TranslationBridge\Converters\DEVTB_DIVI5_Converter;
use DEVTB\TranslationBridge\Converters\DEVTB_Elementor_Converter;
use DEVTB\TranslationBridge\Converters\DEVTB_Elementor4_Converter;
use DEVTB\TranslationBridge\Converters\DEVTB_Avada_Converter;
use DEVTB\TranslationBridge\Converters\DEVTB_Bricks_Converter;
use DEVTB\TranslationBridge\Converters\DEVTB_WPBakery_Converter;
use DEVTB\TranslationBridge\Converters\DEVTB_Beaver_Builder_Converter;
use DEVTB\TranslationBridge\Converters\DEVTB_Gutenberg_Converter;
use DEVTB\TranslationBridge\Converters\DEVTB_Oxygen_Converter;
use DEVTB\TranslationBridge\Converters\DEVTB_Oxygen6_Converter;
use DEVTB\TranslationBridge\Converters\DEVTB_Kadence_Converter;
use DEVTB\TranslationBridge\Converters\DEVTB_Thrive_Converter;

/**
 * Class DEVTB_Converter_Factory
 *
 * Factory for creating framework-specific converters.
 */
class DEVTB_Converter_Factory {

	/**
	 * Registered converters
	 *
	 * @var array<string, DEVTB_Converter_Interface>
	 */
	private static array $converters = [];

	/**
	 * Human-readable framework display names keyed by slug.
	 *
	 * @var array<string,string>
	 */
	public const FRAMEWORK_DISPLAY_NAMES = [
		'bootstrap'      => 'Bootstrap',
		'divi'           => 'DIVI Builder',
		'divi-5'         => 'DIVI 5',
		'elementor'      => 'Elementor',
		'elementor-4'    => 'Elementor 4 Atomic',
		'avada'          => 'Avada Fusion',
		'bricks'         => 'Bricks Builder',
		'wpbakery'       => 'WPBakery Page Builder',
		'beaver-builder' => 'Beaver Builder',
		'gutenberg'      => 'Gutenberg',
		'oxygen'         => 'Oxygen Builder',
		'oxygen-6'       => 'Oxygen 6 / Breakdance',
		'kadence'        => 'Kadence Blocks',
		'thrive'         => 'Thrive Architect',
	];

	/**
	 * Output content format per framework. One of: html, json, shortcodes, block.
	 *
	 * @var array<string,string>
	 */
	public const FRAMEWORK_FORMATS = [
		'bootstrap'      => 'html',
		'divi'           => 'shortcodes',
		'divi-5'         => 'block',
		'elementor'      => 'json',
		'elementor-4'    => 'json',
		'avada'          => 'shortcodes',
		'bricks'         => 'json',
		'wpbakery'       => 'shortcodes',
		'beaver-builder' => 'json',
		'gutenberg'      => 'block',
		'oxygen'         => 'json',
		'oxygen-6'       => 'json',
		'kadence'        => 'block',
		'thrive'         => 'html',
	];

	/**
	 * Output file extension per framework (single canonical extension).
	 *
	 * @var array<string,string>
	 */
	public const FRAMEWORK_FILE_EXTENSIONS = [
		'bootstrap'      => 'html',
		'divi'           => 'txt',
		'divi-5'         => 'html',
		'elementor'      => 'json',
		'elementor-4'    => 'json',
		'avada'          => 'html',
		'bricks'         => 'json',
		'wpbakery'       => 'txt',
		'beaver-builder' => 'txt',
		'gutenberg'      => 'html',
		'oxygen'         => 'json',
		'oxygen-6'       => 'json',
		'kadence'        => 'html',
		'thrive'         => 'html',
	];

	/**
	 * Create converter for specified framework
	 *
	 * @param string $framework Framework name (bootstrap, divi, elementor, avada, bricks, wpbakery, beaver-builder, gutenberg, oxygen).
	 * @return DEVTB_Converter_Interface|null Converter instance or null if not found.
	 * @throws \InvalidArgumentException If framework not supported.
	 */
	public static function create( string $framework ): ?DEVTB_Converter_Interface {
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
	 * @return DEVTB_Converter_Interface|null
	 */
	private static function create_converter_instance( string $framework ): ?DEVTB_Converter_Interface {
		switch ( $framework ) {
			case 'bootstrap':
				return new DEVTB_Bootstrap_Converter();

			case 'divi':
				return new DEVTB_DIVI_Converter();

			case 'divi-5':
			case 'divi5':
				return new DEVTB_DIVI5_Converter();

			case 'elementor':
				return new DEVTB_Elementor_Converter();

			case 'elementor-4':
			case 'elementor4':
			case 'elementor-atomic':
				return new DEVTB_Elementor4_Converter();

			case 'avada':
			case 'fusion':
				return new DEVTB_Avada_Converter();

			case 'bricks':
				return new DEVTB_Bricks_Converter();

			case 'wpbakery':
			case 'vc':
			case 'visualcomposer':
				return new DEVTB_WPBakery_Converter();

			case 'beaver':
			case 'beaverbuilder':
			case 'beaver-builder':
				return new DEVTB_Beaver_Builder_Converter();

			case 'gutenberg':
			case 'blocks':
			case 'block-editor':
				return new DEVTB_Gutenberg_Converter();

			case 'oxygen':
			case 'oxygen-builder':
				return new DEVTB_Oxygen_Converter();

			case 'oxygen-6':
			case 'oxygen6':
				return new DEVTB_Oxygen6_Converter();

			case 'kadence':
			case 'kadence-blocks':
			case 'kadenceblocks':
				return new DEVTB_Kadence_Converter();

			case 'thrive':
			case 'thrive-themes':
			case 'thrive-architect':
			case 'thrive-theme-builder':
				return new DEVTB_Thrive_Converter();

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
		return [ 'bootstrap', 'divi', 'divi-5', 'elementor', 'elementor-4', 'avada', 'bricks', 'wpbakery', 'beaver-builder', 'gutenberg', 'oxygen', 'oxygen-6', 'kadence', 'thrive' ];
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
	 * Get framework metadata (name, CMS version, format, file extension).
	 *
	 * Single source of truth for REST API, CLI, and admin UI consumers.
	 *
	 * @return array<string, array{name:string, cms_version:string, format:string, file_extensions:array<string>, extension:string}>
	 */
	public static function get_framework_info(): array {
		$info = [];
		foreach ( self::get_supported_frameworks() as $slug ) {
			$cms_version = '';
			try {
				$converter   = self::create( $slug );
				$cms_version = $converter ? $converter->get_target_cms_version() : '';
			} catch ( \Throwable $e ) {
				$cms_version = '';
			}

			$extension = self::FRAMEWORK_FILE_EXTENSIONS[ $slug ] ?? 'html';

			$info[ $slug ] = [
				'name'            => self::FRAMEWORK_DISPLAY_NAMES[ $slug ] ?? ucfirst( $slug ),
				'cms_version'     => $cms_version,
				'format'          => self::FRAMEWORK_FORMATS[ $slug ] ?? 'html',
				'extension'       => $extension,
				'file_extensions' => [ $extension ],
			];
		}
		return $info;
	}

	/**
	 * Register custom converter
	 *
	 * @param string                    $framework Framework name.
	 * @param DEVTB_Converter_Interface $converter Converter instance.
	 * @return void
	 */
	public static function register( string $framework, DEVTB_Converter_Interface $converter ): void {
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
	 * @return array<string, DEVTB_Converter_Interface>
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
