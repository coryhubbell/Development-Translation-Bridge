<?php
/**
 * WPBakery Advanced Features
 *
 * Grid Builder, Animations, Design Options CSS, and Advanced Parsing
 *
 * @package    DevelopmentTranslation_Bridge
 * @subpackage WPBakery
 * @version    5.1.0
 */

class DEVTB_WPBakery_Advanced {
	/**
	 * Logger instance
	 *
	 * @var DEVTB_Logger
	 */
	private $logger;

	/**
	 * Animation types
	 *
	 * @var array
	 */
	private $animations = [
		'bounce'         => 'Bounce',
		'flash'          => 'Flash',
		'pulse'          => 'Pulse',
		'rubberBand'     => 'Rubber Band',
		'shake'          => 'Shake',
		'swing'          => 'Swing',
		'tada'           => 'Tada',
		'wobble'         => 'Wobble',
		'bounceIn'       => 'Bounce In',
		'bounceInDown'   => 'Bounce In Down',
		'bounceInLeft'   => 'Bounce In Left',
		'bounceInRight'  => 'Bounce In Right',
		'bounceInUp'     => 'Bounce In Up',
		'fadeIn'         => 'Fade In',
		'fadeInDown'     => 'Fade In Down',
		'fadeInLeft'     => 'Fade In Left',
		'fadeInRight'    => 'Fade In Right',
		'fadeInUp'       => 'Fade In Up',
		'slideInDown'    => 'Slide In Down',
		'slideInLeft'    => 'Slide In Left',
		'slideInRight'   => 'Slide In Right',
		'slideInUp'      => 'Slide In Up',
	];

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->logger = new DEVTB_Logger();
	}

	/**
	 * Parse Grid Builder shortcodes
	 *
	 * @param string $content Grid shortcode content.
	 * @return array Grid structure.
	 */
	public function parse_grid( string $content ): array {
		$grid = [
			'type'     => 'grid',
			'settings' => [],
			'items'    => [],
		];

		// Extract grid settings
		if ( preg_match( '/\[vc_basic_grid([^\]]*)\]/', $content, $match ) ) {
			$grid['settings'] = $this->parse_grid_settings( $match[1] );
		}

		// Extract grid items
		preg_match_all( '/\[vc_grid_item([^\]]*)\](.*?)\[\/vc_grid_item\]/s', $content, $items );

		foreach ( $items[1] as $index => $item_attrs ) {
			$grid['items'][] = [
				'attributes' => $this->parse_attributes( $item_attrs ),
				'content'    => $items[2][ $index ],
			];
		}

		$this->logger->debug( 'Grid parsed', [
			'items' => count( $grid['items'] ),
		]);

		return $grid;
	}

	/**
	 * Parse grid settings
	 *
	 * @param string $attrs_string Grid attributes.
	 * @return array Grid settings.
	 */
	private function parse_grid_settings( string $attrs_string ): array {
		$settings = [
			'post_type'       => 'post',
			'max_items'       => 10,
			'grid_columns'    => 3,
			'gap'             => 30,
			'orderby'         => 'date',
			'order'           => 'DESC',
			'pagination'      => false,
			'arrows'          => false,
		];

		// Parse attributes from shortcode
		preg_match_all( '/(\w+)=["\']([^"\']*)["\']/', $attrs_string, $matches, PREG_SET_ORDER );

		foreach ( $matches as $match ) {
			$key = $match[1];
			$value = $match[2];

			// Convert specific values
			if ( in_array( $key, [ 'max_items', 'grid_columns', 'gap' ], true ) ) {
				$value = (int) $value;
			} elseif ( in_array( $key, [ 'pagination', 'arrows' ], true ) ) {
				$value = ( $value === 'yes' || $value === 'true' );
			}

			$settings[ $key ] = $value;
		}

		return $settings;
	}

	/**
	 * Convert grid to responsive layout
	 *
	 * @param array $grid Grid structure.
	 * @return string Responsive CSS.
	 */
	public function generate_responsive_grid_css( array $grid ): string {
		$columns = $grid['settings']['grid_columns'] ?? 3;
		$gap = $grid['settings']['gap'] ?? 30;

		$css = "
.devtb-grid-container {
    display: grid;
    grid-template-columns: repeat({$columns}, 1fr);
    gap: {$gap}px;
}

@media (max-width: 992px) {
    .devtb-grid-container {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 576px) {
    .devtb-grid-container {
        grid-template-columns: 1fr;
    }
}
";

		return $css;
	}

	/**
	 * Extract and parse Design Options CSS
	 *
	 * @param string $content Content with design options.
	 * @return array CSS rules.
	 */
	public function extract_design_options_css( string $content ): array {
		$css_rules = [];

		// Extract css parameter from shortcodes
		preg_match_all( '/css=["\']([^"\']*)["\']/', $content, $matches );

		foreach ( $matches[1] as $css_string ) {
			$decoded = urldecode( $css_string );

			// Parse Design Options format: .vc_custom_ID{property:value;}
			preg_match_all( '/\.(vc_custom_[a-zA-Z0-9_]+)\{([^}]+)\}/', $decoded, $rules );

			for ( $i = 0; $i < count( $rules[1] ); $i++ ) {
				$selector = '.' . $rules[1][ $i ];
				$properties = $this->parse_css_properties( $rules[2][ $i ] );

				$css_rules[] = [
					'selector'   => $selector,
					'properties' => $properties,
				];
			}
		}

		return $css_rules;
	}

	/**
	 * Parse CSS properties string
	 *
	 * @param string $props_string Properties string.
	 * @return array Properties array.
	 */
	private function parse_css_properties( string $props_string ): array {
		$properties = [];

		$parts = explode( ';', $props_string );

		foreach ( $parts as $part ) {
			$part = trim( $part );
			if ( empty( $part ) ) {
				continue;
			}

			$prop_parts = explode( ':', $part, 2 );
			if ( count( $prop_parts ) === 2 ) {
				$properties[ trim( $prop_parts[0] ) ] = trim( $prop_parts[1] );
			}
		}

		return $properties;
	}

	/**
	 * Generate CSS from design options
	 *
	 * @param array $css_rules CSS rules array.
	 * @return string CSS code.
	 */
	public function generate_css( array $css_rules ): string {
		$css = '';

		foreach ( $css_rules as $rule ) {
			$css .= $rule['selector'] . " {\n";

			foreach ( $rule['properties'] as $prop => $value ) {
				$css .= "    {$prop}: {$value};\n";
			}

			$css .= "}\n\n";
		}

		return $css;
	}

	/**
	 * Extract animations from content
	 *
	 * @param string $content Content to analyze.
	 * @return array Animation data.
	 */
	public function extract_animations( string $content ): array {
		$animations = [];

		// Pattern for animation parameters
		$patterns = [
			'css_animation'        => '/css_animation=["\']([^"\']*)["\']/',
			'animation_delay'      => '/animation_delay=["\']([^"\']*)["\']/',
			'animation_duration'   => '/animation_duration=["\']([^"\']*)["\']/',
			'animation_iteration'  => '/animation_iteration=["\']([^"\']*)["\']/',
		];

		foreach ( $patterns as $key => $pattern ) {
			preg_match_all( $pattern, $content, $matches );
			if ( ! empty( $matches[1] ) ) {
				$animations[ $key ] = array_unique( $matches[1] );
			}
		}

		return $animations;
	}

	/**
	 * Convert WPBakery animation to CSS
	 *
	 * @param string $animation   Animation type.
	 * @param array  $options     Animation options.
	 * @return string CSS animation code.
	 */
	public function convert_animation_to_css( string $animation, array $options = [] ): string {
		$delay = $options['delay'] ?? '0s';
		$duration = $options['duration'] ?? '1s';
		$iteration = $options['iteration'] ?? '1';

		// Map WPBakery animation to CSS
		$animation_name = $this->map_animation_name( $animation );

		$css = <<<CSS
.devtb-animated {
    animation-name: {$animation_name};
    animation-duration: {$duration};
    animation-delay: {$delay};
    animation-iteration-count: {$iteration};
    animation-fill-mode: both;
}
CSS;

		return $css;
	}

	/**
	 * Map WPBakery animation name to standard name
	 *
	 * @param string $wpbakery_animation WPBakery animation type.
	 * @return string Standard animation name.
	 */
	private function map_animation_name( string $wpbakery_animation ): string {
		// WPBakery uses different naming conventions
		$map = [
			'appear'           => 'fadeIn',
			'bottom-in-view'   => 'fadeInUp',
			'top-in-view'      => 'fadeInDown',
			'left-in-view'     => 'fadeInLeft',
			'right-in-view'    => 'fadeInRight',
			'bounce-in'        => 'bounceIn',
			'slide-in-bottom'  => 'slideInUp',
			'slide-in-top'     => 'slideInDown',
			'slide-in-left'    => 'slideInLeft',
			'slide-in-right'   => 'slideInRight',
		];

		return $map[ $wpbakery_animation ] ?? $wpbakery_animation;
	}

	/**
	 * Parse attributes from shortcode string
	 *
	 * @param string $attrs_string Attributes string.
	 * @return array Parsed attributes.
	 */
	private function parse_attributes( string $attrs_string ): array {
		$attributes = [];

		preg_match_all( '/(\w+)=["\']([^"\']*)["\']/', $attrs_string, $matches, PREG_SET_ORDER );

		foreach ( $matches as $match ) {
			$attributes[ $match[1] ] = $match[2];
		}

		return $attributes;
	}

	/**
	 * Extract parallax settings
	 *
	 * @param string $content Content to analyze.
	 * @return array Parallax data.
	 */
	public function extract_parallax( string $content ): array {
		$parallax = [];

		// Check for parallax rows
		preg_match_all( '/parallax=["\']([^"\']*)["\']/', $content, $parallax_matches );
		preg_match_all( '/parallax_speed_bg=["\']([^"\']*)["\']/', $content, $speed_matches );

		if ( ! empty( $parallax_matches[1] ) ) {
			foreach ( $parallax_matches[1] as $index => $enabled ) {
				if ( $enabled === 'content-moving' || $enabled === 'content-moving-fade' ) {
					$parallax[] = [
						'enabled' => true,
						'type'    => $enabled,
						'speed'   => $speed_matches[1][ $index ] ?? '1.5',
					];
				}
			}
		}

		return $parallax;
	}

	/**
	 * Convert parallax to CSS
	 *
	 * @param array $parallax_data Parallax settings.
	 * @return string CSS/JS code.
	 */
	public function convert_parallax_to_css( array $parallax_data ): string {
		if ( empty( $parallax_data ) ) {
			return '';
		}

		$css = <<<CSS
.devtb-parallax {
    background-attachment: fixed;
    background-position: center;
    background-repeat: no-repeat;
    background-size: cover;
}

@media (max-width: 768px) {
    .devtb-parallax {
        background-attachment: scroll;
    }
}
CSS;

		return $css;
	}

	/**
	 * Extract custom CSS from content
	 *
	 * @param string $content Content to analyze.
	 * @return string Custom CSS.
	 */
	public function extract_custom_css( string $content ): string {
		$custom_css = '';

		// Extract inline styles
		preg_match_all( '/el_id=["\']([^"\']*)["\']/', $content, $id_matches );
		preg_match_all( '/el_class=["\']([^"\']*)["\']/', $content, $class_matches );

		// Extract design options CSS
		$design_options = $this->extract_design_options_css( $content );
		$custom_css .= $this->generate_css( $design_options );

		return $custom_css;
	}

	/**
	 * Optimize shortcode structure
	 *
	 * @param string $content Shortcode content.
	 * @return string Optimized content.
	 */
	public function optimize_shortcodes( string $content ): string {
		// Remove unnecessary whitespace
		$content = preg_replace( '/\s+/', ' ', $content );

		// Remove empty shortcodes
		$content = preg_replace( '/\[vc_\w+\s*\]\[\/vc_\w+\]/', '', $content );

		// Consolidate nested rows
		$content = $this->consolidate_nested_rows( $content );

		return trim( $content );
	}

	/**
	 * Consolidate nested rows
	 *
	 * @param string $content Content with nested rows.
	 * @return string Optimized content.
	 */
	private function consolidate_nested_rows( string $content ): string {
		// Pattern to find rows within rows (which is not ideal)
		// This is a simplified version - full implementation would use recursive parsing
		$content = preg_replace(
			'/\[vc_row\]\[vc_column\]\[vc_row\]/',
			'[vc_row]',
			$content
		);

		return $content;
	}

	/**
	 * Get animation types
	 *
	 * @return array Animation types.
	 */
	public function get_animations(): array {
		return $this->animations;
	}

	/**
	 * Analyze WPBakery features usage
	 *
	 * @param string $content Content to analyze.
	 * @return array Feature usage statistics.
	 */
	public function analyze_features( string $content ): array {
		return [
			'has_grid'        => strpos( $content, '[vc_basic_grid' ) !== false,
			'has_animations'  => ! empty( $this->extract_animations( $content ) ),
			'has_parallax'    => ! empty( $this->extract_parallax( $content ) ),
			'has_design_opts' => ! empty( $this->extract_design_options_css( $content ) ),
			'shortcode_count' => substr_count( $content, '[vc_' ),
			'custom_elements' => count( $this->get_custom_elements( $content ) ),
		];
	}

	/**
	 * Get custom elements from content
	 *
	 * @param string $content Content to analyze.
	 * @return array Custom element tags.
	 */
	private function get_custom_elements( string $content ): array {
		$custom = [];

		preg_match_all( '/\[([a-zA-Z0-9_\-]+)/', $content, $matches );

		if ( ! empty( $matches[1] ) ) {
			foreach ( array_unique( $matches[1] ) as $tag ) {
				if ( strpos( $tag, 'vc_' ) !== 0 ) {
					$custom[] = $tag;
				}
			}
		}

		return $custom;
	}
}
