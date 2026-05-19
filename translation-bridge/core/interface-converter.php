<?php
/**
 * Converter Interface
 *
 * Contract for all framework-specific converters that transform universal
 * component models into framework-specific markup.
 *
 * @package DevelopmentTranslation_Bridge
 * @subpackage Translation_Bridge
 * @since 3.0.0
 */

namespace DEVTB\TranslationBridge\Core;

use DEVTB\TranslationBridge\Models\DEVTB_Component;

/**
 * Interface DEVTB_Converter_Interface
 *
 * Defines methods that all framework converters must implement.
 */
interface DEVTB_Converter_Interface {

	/**
	 * Convert universal component(s) to framework-specific format
	 *
	 * @param DEVTB_Component|DEVTB_Component[] $component Component(s) to convert.
	 * @return string|array Framework-specific output (HTML, JSON, shortcodes, etc.).
	 */
	public function convert( $component );

	/**
	 * Get framework name this converter outputs
	 *
	 * @return string Framework name (bootstrap, divi, elementor, avada, bricks).
	 */
	public function get_framework(): string;

	/**
	 * Get supported component types this converter can handle
	 *
	 * @return array<string> Array of supported component type names.
	 */
	public function get_supported_types(): array;

	/**
	 * Check if component type is supported
	 *
	 * @param string $type Component type.
	 * @return bool True if supported, false otherwise.
	 */
	public function supports_type( string $type ): bool;

	/**
	 * Convert single component
	 *
	 * @param DEVTB_Component $component Component to convert.
	 * @return string|array Framework-specific output for single component.
	 */
	public function convert_component( DEVTB_Component $component );

	/**
	 * Get fallback conversion for unsupported component types
	 *
	 * @param DEVTB_Component $component Unsupported component.
	 * @return string|array Fallback output.
	 */
	public function get_fallback( DEVTB_Component $component );

	/**
	 * Get the upstream CMS version this converter is calibrated against.
	 *
	 * Used to track drift between our converter coverage and live CMS releases.
	 * Format: semver-style string ("3.30.0", "7.15.3").
	 *
	 * @return string
	 */
	public function get_target_cms_version(): string;
}
