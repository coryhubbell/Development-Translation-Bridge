<?php
/**
 * Advanced CSS Processing Helper
 *
 * Einstein-level CSS manipulation featuring:
 * - Intelligent CSS parsing
 * - Property normalization
 * - Unit conversion
 * - Color transformation
 * - Minification and optimization
 * - Framework-specific adaptations
 *
 * @package WordPress_Bootstrap_Claude
 * @subpackage Translation_Bridge
 * @since 3.0.0
 */

namespace WPBC\TranslationBridge\Utils;

/**
 * Class WPBC_CSS_Helper
 *
 * Advanced CSS processing and manipulation utilities.
 */
class WPBC_CSS_Helper {

	/**
	 * Parse inline CSS string to array
	 *
	 * @param string $css Inline CSS string.
	 * @return array Array of property => value pairs.
	 */
	public static function parse_inline( string $css ): array {
		$styles = [];
		$rules  = explode( ';', $css );

		foreach ( $rules as $rule ) {
			$rule = trim( $rule );

			if ( empty( $rule ) || strpos( $rule, ':' ) === false ) {
				continue;
			}

			list( $property, $value ) = explode( ':', $rule, 2 );
			$styles[ trim( $property ) ] = trim( $value );
		}

		return $styles;
	}

	/**
	 * Convert array to inline CSS string
	 *
	 * @param array $styles Array of property => value pairs.
	 * @return string Inline CSS string.
	 */
	public static function to_inline( array $styles ): string {
		$css = [];

		foreach ( $styles as $property => $value ) {
			if ( ! empty( $value ) ) {
				$css[] = $property . ': ' . $value;
			}
		}

		return implode( '; ', $css );
	}

	/**
	 * Convert property name to different case
	 *
	 * @param string $property Property name.
	 * @param string $format Target format (kebab, camel, underscore).
	 * @return string Converted property name.
	 */
	public static function convert_property_case( string $property, string $format ): string {
		switch ( $format ) {
			case 'kebab':
			case 'css':
				// background-color
				return strtolower( preg_replace( '/([a-z])([A-Z])/', '$1-$2', $property ) );

			case 'camel':
			case 'js':
				// backgroundColor
				$parts = explode( '-', $property );
				$parts = array_map( 'ucfirst', $parts );
				$camel = implode( '', $parts );
				return lcfirst( $camel );

			case 'underscore':
			case 'bricks':
				// _backgroundColor (Bricks format)
				$camel = self::convert_property_case( $property, 'camel' );
				return '_' . $camel;

			default:
				return $property;
		}
	}

	/**
	 * Normalize color value
	 *
	 * Converts colors to consistent format.
	 *
	 * @param string $color Color value.
	 * @param string $format Target format (hex, rgb, rgba).
	 * @return string Normalized color.
	 */
	public static function normalize_color( string $color, string $format = 'hex' ): string {
		$color = trim( strtolower( $color ) );

		// Named colors to hex
		$named_colors = [
			'black'       => '#000000',
			'white'       => '#ffffff',
			'red'         => '#ff0000',
			'green'       => '#008000',
			'blue'        => '#0000ff',
			'yellow'      => '#ffff00',
			'gray'        => '#808080',
			'transparent' => 'rgba(0,0,0,0)',
		];

		if ( isset( $named_colors[ $color ] ) ) {
			$color = $named_colors[ $color ];
		}

		// Convert based on format
		if ( $format === 'hex' && ! str_starts_with( $color, '#' ) ) {
			// Convert RGB to hex
			if ( preg_match( '/rgba?\((\d+),\s*(\d+),\s*(\d+)/', $color, $matches ) ) {
				return sprintf( '#%02x%02x%02x', $matches[1], $matches[2], $matches[3] );
			}
		}

		return $color;
	}

	/**
	 * Convert CSS unit
	 *
	 * @param string $value CSS value with unit.
	 * @param string $from_unit Source unit.
	 * @param string $to_unit Target unit.
	 * @param int    $base_font_size Base font size for em/rem (default 16).
	 * @return string Converted value.
	 */
	public static function convert_unit(
		string $value,
		string $from_unit,
		string $to_unit,
		int $base_font_size = 16
	): string {
		// Extract numeric value
		$numeric = floatval( $value );

		if ( $numeric === 0.0 ) {
			return '0';
		}

		// Convert to px first
		switch ( $from_unit ) {
			case 'em':
			case 'rem':
				$px = $numeric * $base_font_size;
				break;

			case 'pt':
				$px = $numeric * 1.3333;
				break;

			case 'cm':
				$px = $numeric * 37.795;
				break;

			case 'mm':
				$px = $numeric * 3.7795;
				break;

			case 'in':
				$px = $numeric * 96;
				break;

			case 'px':
			default:
				$px = $numeric;
		}

		// Convert from px to target unit
		switch ( $to_unit ) {
			case 'em':
			case 'rem':
				$result = $px / $base_font_size;
				break;

			case 'pt':
				$result = $px / 1.3333;
				break;

			case 'cm':
				$result = $px / 37.795;
				break;

			case 'mm':
				$result = $px / 3.7795;
				break;

			case 'in':
				$result = $px / 96;
				break;

			case 'px':
			default:
				$result = $px;
		}

		return round( $result, 2 ) . $to_unit;
	}

	/**
	 * Extract spacing values (margin, padding)
	 *
	 * @param array $styles CSS styles array.
	 * @param string $property Property name (margin or padding).
	 * @return array Spacing values (top, right, bottom, left).
	 */
	public static function extract_spacing( array $styles, string $property ): array {
		$spacing = [
			'top'    => '',
			'right'  => '',
			'bottom' => '',
			'left'   => '',
		];

		// Check for shorthand property
		if ( isset( $styles[ $property ] ) ) {
			$values = explode( ' ', $styles[ $property ] );

			switch ( count( $values ) ) {
				case 1:
					// All sides
					$spacing = array_fill_keys( [ 'top', 'right', 'bottom', 'left' ], $values[0] );
					break;

				case 2:
					// Vertical | Horizontal
					$spacing['top'] = $spacing['bottom'] = $values[0];
					$spacing['left'] = $spacing['right'] = $values[1];
					break;

				case 3:
					// Top | Horizontal | Bottom
					$spacing['top']    = $values[0];
					$spacing['left'] = $spacing['right'] = $values[1];
					$spacing['bottom'] = $values[2];
					break;

				case 4:
					// Top | Right | Bottom | Left
					$spacing['top']    = $values[0];
					$spacing['right']  = $values[1];
					$spacing['bottom'] = $values[2];
					$spacing['left']   = $values[3];
					break;
			}
		}

		// Check for individual properties
		$sides = [ 'top', 'right', 'bottom', 'left' ];
		foreach ( $sides as $side ) {
			$prop = $property . '-' . $side;
			if ( isset( $styles[ $prop ] ) && ! empty( $styles[ $prop ] ) ) {
				$spacing[ $side ] = $styles[ $prop ];
			}
		}

		return $spacing;
	}

	/**
	 * Convert spacing to Bootstrap classes
	 *
	 * @param array $spacing Spacing values (top, right, bottom, left).
	 * @param string $type Type (margin or padding).
	 * @return array Bootstrap classes.
	 */
	public static function spacing_to_bootstrap( array $spacing, string $type ): array {
		$classes = [];
		$prefix  = $type === 'margin' ? 'm' : 'p';

		$sides = [
			'top'    => 't',
			'right'  => 'e',
			'bottom' => 'b',
			'left'   => 's',
		];

		// Bootstrap spacing scale (0-5)
		$scale_map = [
			'0'     => 0,
			'0px'   => 0,
			'4px'   => 1,
			'8px'   => 2,
			'16px'  => 3,
			'24px'  => 4,
			'48px'  => 5,
		];

		foreach ( $spacing as $side => $value ) {
			if ( empty( $value ) ) {
				continue;
			}

			$scale = $scale_map[ $value ] ?? null;

			if ( $scale !== null ) {
				$classes[] = $prefix . $sides[ $side ] . '-' . $scale;
			}
		}

		return $classes;
	}

	/**
	 * Extract typography properties
	 *
	 * @param array $styles CSS styles array.
	 * @return array Typography properties.
	 */
	public static function extract_typography( array $styles ): array {
		$typography_props = [
			'font-family',
			'font-size',
			'font-weight',
			'font-style',
			'line-height',
			'letter-spacing',
			'text-align',
			'text-decoration',
			'text-transform',
			'color',
		];

		$typography = [];

		foreach ( $typography_props as $prop ) {
			if ( isset( $styles[ $prop ] ) ) {
				$typography[ $prop ] = $styles[ $prop ];
			}
		}

		return $typography;
	}

	/**
	 * Minify CSS
	 *
	 * @param string $css CSS string.
	 * @return string Minified CSS.
	 */
	public static function minify( string $css ): string {
		// Remove comments
		$css = preg_replace( '/\/\*.*?\*\//s', '', $css );

		// Remove whitespace
		$css = preg_replace( '/\s+/', ' ', $css );

		// Remove spaces around special characters
		$css = preg_replace( '/\s*([{}:;,])\s*/', '$1', $css );

		// Remove last semicolon in block
		$css = preg_replace( '/;}/','}',$css );

		return trim( $css );
	}

	/**
	 * Prettify CSS
	 *
	 * @param string $css CSS string.
	 * @return string Formatted CSS.
	 */
	public static function prettify( string $css ): string {
		// Add newlines after braces
		$css = preg_replace( '/\{/', "{\n\t", $css );
		$css = preg_replace( '/\}/', "\n}\n", $css );
		$css = preg_replace( '/;/', ";\n\t", $css );

		// Clean up extra whitespace
		$css = preg_replace( '/\n\s*\n/', "\n", $css );

		return trim( $css );
	}

	/**
	 * Convert CSS to JavaScript object notation
	 *
	 * @param array $styles CSS styles array.
	 * @return array JS-style object.
	 */
	public static function to_js_object( array $styles ): array {
		$js_object = [];

		foreach ( $styles as $property => $value ) {
			$js_property = self::convert_property_case( $property, 'camel' );
			$js_object[ $js_property ] = $value;
		}

		return $js_object;
	}

	/**
	 * Extract border properties
	 *
	 * @param array $styles CSS styles array.
	 * @return array Border properties.
	 */
	public static function extract_border( array $styles ): array {
		$border = [
			'width' => '',
			'style' => '',
			'color' => '',
			'radius' => '',
		];

		// Shorthand border
		if ( isset( $styles['border'] ) ) {
			$parts = explode( ' ', $styles['border'] );

			foreach ( $parts as $part ) {
				if ( preg_match( '/^\d+/', $part ) ) {
					$border['width'] = $part;
				} elseif ( in_array( $part, [ 'solid', 'dashed', 'dotted', 'double', 'groove', 'ridge', 'inset', 'outset' ], true ) ) {
					$border['style'] = $part;
				} else {
					$border['color'] = $part;
				}
			}
		}

		// Individual properties
		$individual = [ 'width', 'style', 'color', 'radius' ];
		foreach ( $individual as $prop ) {
			$key = 'border-' . $prop;
			if ( isset( $styles[ $key ] ) ) {
				$border[ $prop ] = $styles[ $key ];
			}
		}

		return array_filter( $border );
	}

	/**
	 * Merge CSS styles with priority
	 *
	 * @param array $styles1 First styles array.
	 * @param array $styles2 Second styles array (takes priority).
	 * @return array Merged styles.
	 */
	public static function merge( array $styles1, array $styles2 ): array {
		return array_merge( $styles1, $styles2 );
	}

	/**
	 * Calculate CSS specificity
	 *
	 * @param string $selector CSS selector.
	 * @return int Specificity score.
	 */
	public static function calculate_specificity( string $selector ): int {
		$specificity = 0;

		// IDs
		$specificity += substr_count( $selector, '#' ) * 100;

		// Classes, attributes, pseudo-classes
		$specificity += preg_match_all( '/[\.\[]|:[^:]/', $selector ) * 10;

		// Elements and pseudo-elements
		$specificity += preg_match_all( '/\b[a-z]+\b|::/', $selector );

		return $specificity;
	}

	/**
	 * Extract important rules
	 *
	 * @param array $styles CSS styles array.
	 * @return array Important rules.
	 */
	public static function extract_important( array $styles ): array {
		$important = [];

		foreach ( $styles as $property => $value ) {
			if ( strpos( $value, '!important' ) !== false ) {
				$important[ $property ] = str_replace( '!important', '', $value );
			}
		}

		return $important;
	}

	/**
	 * Add !important to CSS value
	 *
	 * @param string $value CSS value.
	 * @return string Value with !important.
	 */
	public static function make_important( string $value ): string {
		if ( strpos( $value, '!important' ) !== false ) {
			return $value;
		}

		return trim( $value ) . ' !important';
	}

	/**
	 * Remove !important from CSS value
	 *
	 * @param string $value CSS value.
	 * @return string Value without !important.
	 */
	public static function remove_important( string $value ): string {
		return trim( str_replace( '!important', '', $value ) );
	}

	/**
	 * Validate CSS property name
	 *
	 * @param string $property Property name.
	 * @return bool True if valid.
	 */
	public static function is_valid_property( string $property ): bool {
		// List of common valid CSS properties
		$valid_properties = [
			'display', 'position', 'top', 'right', 'bottom', 'left',
			'width', 'height', 'margin', 'padding', 'border',
			'color', 'background', 'font', 'text-align',
			'flex', 'grid', 'z-index', 'opacity', 'transform',
		];

		// Check if starts with vendor prefix
		if ( preg_match( '/^(-webkit-|-moz-|-ms-|-o-)/', $property ) ) {
			return true;
		}

		// Check if in valid list or follows CSS naming convention
		return in_array( $property, $valid_properties, true )
			|| preg_match( '/^[a-z-]+$/', $property );
	}

	/**
	 * Get CSS vendor prefixes for property
	 *
	 * @param string $property Property name.
	 * @param string $value Property value.
	 * @return array Array of prefixed properties.
	 */
	public static function get_vendor_prefixes( string $property, string $value ): array {
		$prefixes = [ '-webkit-', '-moz-', '-ms-', '-o-', '' ];
		$results  = [];

		$needs_prefix = [
			'transform',
			'transition',
			'animation',
			'box-shadow',
			'border-radius',
			'user-select',
		];

		if ( in_array( $property, $needs_prefix, true ) ) {
			foreach ( $prefixes as $prefix ) {
				$results[ $prefix . $property ] = $value;
			}
		} else {
			$results[ $property ] = $value;
		}

		return $results;
	}
}
