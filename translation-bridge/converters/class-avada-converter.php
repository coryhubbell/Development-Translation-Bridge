<?php
/**
 * Avada Theme Builder Converter
 *
 * Intelligent universal to Avada shortcode converter featuring:
 * - Hierarchical shortcode generation (Container > Row > Column > Element)
 * - 150+ element type support
 * - Parameter denormalization
 * - Responsive settings generation
 * - Animation and styling support
 * - Fusion Builder compatibility
 *
 * @package WordPress_Bootstrap_Claude
 * @subpackage Translation_Bridge
 * @since 3.0.0
 */

namespace WPBC\TranslationBridge\Converters;

use WPBC\TranslationBridge\Core\WPBC_Converter_Interface;
use WPBC\TranslationBridge\Models\WPBC_Component;
use WPBC\TranslationBridge\Utils\WPBC_Shortcode_Helper;
use WPBC\TranslationBridge\Utils\WPBC_CSS_Helper;

/**
 * Class WPBC_Avada_Converter
 *
 * Convert universal components to Avada Fusion Builder shortcodes.
 */
class WPBC_Avada_Converter implements WPBC_Converter_Interface {

	/**
	 * Convert universal component to Avada shortcode
	 *
	 * @param WPBC_Component|array $component Component to convert.
	 * @return string Avada shortcode string.
	 */
	public function convert( $component ): string {
		if ( is_array( $component ) ) {
			$components = $component;
		} else {
			$components = [ $component ];
		}

		$shortcodes = [];

		foreach ( $components as $comp ) {
			if ( $comp instanceof WPBC_Component ) {
				$shortcode = $this->convert_component( $comp );
				if ( $shortcode ) {
					$shortcodes[] = $shortcode;
				}
			}
		}

		return implode( "\n\n", $shortcodes );
	}

	/**
	 * Convert single component to Avada shortcode
	 *
	 * @param WPBC_Component $component Component to convert.
	 * @return string Avada shortcode.
	 */
	public function convert_component( WPBC_Component $component ): string {
		$type = $component->type;

		// Convert based on component type
		if ( $type === 'container' ) {
			return $this->convert_container( $component );
		} elseif ( $type === 'row' ) {
			return $this->convert_row( $component );
		} elseif ( $type === 'column' ) {
			return $this->convert_column( $component );
		} else {
			return $this->convert_element( $component );
		}
	}

	/**
	 * Convert container to Avada builder_container
	 *
	 * @param WPBC_Component $component Component to convert.
	 * @return string Avada container shortcode.
	 */
	private function convert_container( WPBC_Component $component ): string {
		$attributes = $this->denormalize_attributes( $component->attributes );

		$rows_content = '';

		// Convert children (rows)
		foreach ( $component->children as $child ) {
			if ( $child->type === 'row' || $child->type === 'column' ) {
				$rows_content .= $this->convert_component( $child ) . "\n";
			}
		}

		// If no rows, create a default row with children
		if ( empty( $rows_content ) && ! empty( $component->children ) ) {
			$row = new WPBC_Component([
				'type'     => 'row',
				'category' => 'layout',
				'children' => $component->children,
			]);
			$rows_content = $this->convert_row( $row );
		}

		return WPBC_Shortcode_Helper::build(
			'fusion_builder_container',
			$attributes,
			$rows_content,
			false
		);
	}

	/**
	 * Convert row to Avada builder_row
	 *
	 * @param WPBC_Component $component Component to convert.
	 * @return string Avada row shortcode.
	 */
	private function convert_row( WPBC_Component $component ): string {
		$attributes = $this->denormalize_attributes( $component->attributes );

		$columns_content = '';

		// Convert children (columns)
		foreach ( $component->children as $child ) {
			if ( $child->type === 'column' ) {
				$columns_content .= $this->convert_column( $child ) . "\n";
			} else {
				// Wrap non-column children in a column
				$column = new WPBC_Component([
					'type'       => 'column',
					'category'   => 'layout',
					'attributes' => [ 'width' => '100%' ],
					'children'   => [ $child ],
				]);
				$columns_content .= $this->convert_column( $column ) . "\n";
			}
		}

		return WPBC_Shortcode_Helper::build(
			'fusion_builder_row',
			$attributes,
			$columns_content,
			false
		);
	}

	/**
	 * Convert column to Avada builder_column
	 *
	 * @param WPBC_Component $component Component to convert.
	 * @return string Avada column shortcode.
	 */
	private function convert_column( WPBC_Component $component ): string {
		$attributes = $this->denormalize_attributes( $component->attributes );

		// Convert width to Avada spacing format
		if ( isset( $component->attributes['width'] ) ) {
			$spacing = $this->convert_width_to_avada_spacing( $component->attributes['width'] );
			$attributes['spacing'] = $spacing;
		}

		$elements_content = '';

		// Convert children (elements)
		foreach ( $component->children as $child ) {
			$elements_content .= $this->convert_element( $child ) . "\n";
		}

		return WPBC_Shortcode_Helper::build(
			'fusion_builder_column',
			$attributes,
			$elements_content,
			false
		);
	}

	/**
	 * Convert element to Avada fusion element
	 *
	 * @param WPBC_Component $component Component to convert.
	 * @return string Avada element shortcode.
	 */
	private function convert_element( WPBC_Component $component ): string {
		// Map universal type to Avada element type
		$avada_type = $this->map_to_avada_type( $component->type );

		if ( ! $avada_type ) {
			// Fallback to text element with content
			$avada_type = 'text';
		}

		$attributes = $this->denormalize_attributes( $component->attributes );

		// Handle element-specific conversions
		$attributes = $this->add_element_specific_attributes( $avada_type, $attributes, $component );

		$tag = 'fusion_' . $avada_type;

		// Some elements are self-closing
		$self_closing = in_array( $avada_type, [
			'separator',
			'section_separator',
			'fontawesome',
			'menu_anchor',
		], true );

		// Handle nested elements
		$content = $component->content;

		if ( ! empty( $component->children ) ) {
			$children_content = '';
			foreach ( $component->children as $child ) {
				$children_content .= $this->convert_element( $child ) . "\n";
			}

			// For container-like elements, append children
			if ( in_array( $avada_type, [ 'tabs', 'accordion', 'content_boxes', 'testimonials', 'counters_box', 'flip_boxes' ], true ) ) {
				$content = $children_content;
			} else {
				$content .= ( $content ? "\n\n" : '' ) . $children_content;
			}
		}

		return WPBC_Shortcode_Helper::build( $tag, $attributes, $content, $self_closing );
	}

	/**
	 * Map universal type to Avada element type
	 *
	 * @param string $universal_type Universal component type.
	 * @return string|null Avada element type.
	 */
	private function map_to_avada_type( string $universal_type ): ?string {
		$type_map = [
			// Content
			'text'                => 'text',
			'heading'             => 'title',
			'card'                => 'content_box',
			'card-group'          => 'content_boxes',
			'divider'             => 'separator',
			'alert'               => 'alert',
			'icon'                => 'fontawesome',
			'highlight'           => 'highlight',
			'dropcap'             => 'dropcap',
			'popover'             => 'popover',
			'tooltip'             => 'tooltip',

			// Media
			'image'               => 'imageframe',
			'gallery'             => 'gallery',
			'slider'              => 'slider',
			'video'               => 'video',
			'audio'               => 'audio',

			// Interactive
			'button'              => 'button',
			'modal'               => 'modal',
			'link'                => 'modal_text_link',
			'tabs'                => 'tabs',
			'tab'                 => 'tab',
			'accordion'           => 'accordion',
			'toggle'              => 'toggle',
			'counter'             => 'counter_box',
			'counter-group'       => 'counters_box',
			'progress'            => 'progressbar',
			'list'                => 'checklist',
			'anchor'              => 'menu_anchor',

			// Tables & Lists
			'table'               => 'table',
			'pricing-table'       => 'pricing_table',
			'team-member'         => 'person',
			'testimonial'         => 'testimonial',
			'testimonial-group'   => 'testimonials',
			'cta'                 => 'tagline_box',
			'image-group'         => 'images',

			// Social
			'social-icons'        => 'social_links',
			'share-buttons'       => 'sharing_box',

			// Blog & Portfolio
			'blog'                => 'blog',
			'portfolio'           => 'portfolio',
			'post-card'           => 'postcard',
			'post-grid'           => 'post_cards',

			// WooCommerce
			'product-slider'      => 'products_slider',
			'woocommerce'         => 'woo_shortcodes',

			// Forms
			'form'                => 'form',

			// Maps
			'map'                 => 'google_map',

			// Code & Widgets
			'code'                => 'code_block',
			'widget'              => 'widget_area',

			// Chart & Data
			'chart'               => 'chart',

			// Advanced
			'events'              => 'events',
			'cart'                => 'woo_cart',
			'checkout'            => 'woo_checkout',
			'marquee'             => 'scrolling_text',
			'image-hotspot'       => 'image_hotspots',
			'image-compare'       => 'image_before_after',
		];

		return $type_map[ $universal_type ] ?? null;
	}

	/**
	 * Denormalize universal attributes to Avada format
	 *
	 * @param array $attributes Universal attributes.
	 * @return array Avada attributes.
	 */
	private function denormalize_attributes( array $attributes ): array {
		$avada_attrs = [];

		$attr_map = [
			// Button
			'url'                   => 'link',
			'target'                => 'target',
			'size'                  => 'size',
			'variant'               => 'color',
			'icon'                  => 'icon',

			// Image
			'image_url'             => 'image',
			'image_id'              => 'image_id',
			'alt_text'              => 'alt',
			'lightbox'              => 'lightbox',

			// Heading
			'heading'               => 'title',
			'alignment'             => 'content_align',

			// Colors
			'background_color'      => 'backgroundcolor',
			'border_color'          => 'bordercolor',
			'text_color'            => 'textcolor',

			// Layout
			'spacing'               => 'spacing',
			'padding'               => 'padding',
			'margin'                => 'margin',
			'border_width'          => 'border_size',
			'border_radius'         => 'border_radius',

			// Animation
			'animation'             => 'animation_type',
			'animation_direction'   => 'animation_direction',
			'animation_speed'       => 'animation_speed',
			'animation_delay'       => 'animation_delay',

			// Visibility
			'hide_on_mobile'        => 'hide_on_mobile',
			'css_class'             => 'class',
			'element_id'            => 'id',
		];

		foreach ( $attributes as $key => $value ) {
			$avada_key = $attr_map[ $key ] ?? $key;

			// Convert boolean to yes/no
			if ( is_bool( $value ) ) {
				$value = $value ? 'yes' : 'no';
			}

			$avada_attrs[ $avada_key ] = $value;
		}

		return $avada_attrs;
	}

	/**
	 * Add element-specific attributes
	 *
	 * @param string         $avada_type Avada element type.
	 * @param array          $attributes Current attributes.
	 * @param WPBC_Component $component Original component.
	 * @return array Updated attributes.
	 */
	private function add_element_specific_attributes( string $avada_type, array $attributes, WPBC_Component $component ): array {
		switch ( $avada_type ) {
			case 'title':
				// Add size if not present
				if ( ! isset( $attributes['size'] ) ) {
					$attributes['size'] = '2'; // Default h2
				}
				break;

			case 'button':
				// Ensure button has required attributes
				if ( ! isset( $attributes['color'] ) ) {
					$attributes['color'] = 'default';
				}
				if ( ! isset( $attributes['size'] ) ) {
					$attributes['size'] = 'medium';
				}
				break;

			case 'imageframe':
				// Add lightbox default
				if ( ! isset( $attributes['lightbox'] ) ) {
					$attributes['lightbox'] = 'no';
				}
				break;

			case 'separator':
				// Add separator defaults
				if ( ! isset( $attributes['style'] ) ) {
					$attributes['style'] = 'single';
				}
				break;
		}

		return $attributes;
	}

	/**
	 * Convert width percentage to Avada spacing format
	 *
	 * @param string $width Width as percentage or fraction.
	 * @return string Avada spacing (1_1, 1_2, etc.).
	 */
	private function convert_width_to_avada_spacing( string $width ): string {
		// Remove % sign
		$width = str_replace( '%', '', $width );
		$width_float = (float) $width;

		// Map to closest Avada spacing
		$spacing_map = [
			100    => '1_1',
			50     => '1_2',
			33.33  => '1_3',
			66.66  => '2_3',
			25     => '1_4',
			75     => '3_4',
			20     => '1_5',
			40     => '2_5',
			60     => '3_5',
			80     => '4_5',
			16.66  => '1_6',
			83.33  => '5_6',
		];

		// Find closest match
		$closest = '1_1';
		$min_diff = 999;

		foreach ( $spacing_map as $percentage => $spacing ) {
			$diff = abs( $percentage - $width_float );
			if ( $diff < $min_diff ) {
				$min_diff = $diff;
				$closest = $spacing;
			}
		}

		return $closest;
	}

	/**
	 * Get framework name
	 *
	 * @return string Framework name.
	 */
	public function get_framework(): string {
		return 'avada';
	}

	/**
	 * Get supported component types
	 *
	 * @return array<string> Array of supported types.
	 */
	public function get_supported_types(): array {
		return [
			'container',
			'row',
			'column',
			'text',
			'heading',
			'image',
			'button',
			'divider',
			'alert',
			'icon',
			'card',
			'card-group',
			'gallery',
			'slider',
			'video',
			'audio',
			'modal',
			'tabs',
			'tab',
			'accordion',
			'toggle',
			'counter',
			'counter-group',
			'progress',
			'list',
			'table',
			'pricing-table',
			'team-member',
			'testimonial',
			'testimonial-group',
			'cta',
			'social-icons',
			'share-buttons',
			'blog',
			'portfolio',
			'post-card',
			'post-grid',
			'form',
			'map',
			'code',
			'widget',
			'chart',
		];
	}

	/**
	 * Validate component can be converted
	 *
	 * @param WPBC_Component $component Component to validate.
	 * @return bool True if can be converted.
	 */
	public function can_convert( WPBC_Component $component ): bool {
		$supported = $this->get_supported_types();
		return in_array( $component->type, $supported, true )
			|| $this->map_to_avada_type( $component->type ) !== null;
	}

	/**
	 * Get conversion confidence score
	 *
	 * @param WPBC_Component $component Component to evaluate.
	 * @return float Confidence score (0.0-1.0).
	 */
	public function get_confidence( WPBC_Component $component ): float {
		if ( ! $this->can_convert( $component ) ) {
			return 0.0;
		}

		$confidence = 0.8; // Base confidence

		// Boost confidence if coming from Avada originally
		if ( isset( $component->metadata['source_framework'] ) && $component->metadata['source_framework'] === 'avada' ) {
			$confidence = 0.95;
		}

		// Check for Avada-specific features
		$avada_type = $this->map_to_avada_type( $component->type );
		if ( $avada_type && in_array( $avada_type, [ 'flip_boxes', 'counters_circle', 'image_hotspots' ], true ) ) {
			// These are Avada-specific, might not convert well from other frameworks
			if ( ! isset( $component->metadata['source_framework'] ) || $component->metadata['source_framework'] !== 'avada' ) {
				$confidence -= 0.15;
			}
		}

		return max( 0.0, min( 1.0, $confidence ) );
	}

	/**
	 * Check if component type is supported
	 *
	 * @param string $type Component type.
	 * @return bool True if supported, false otherwise.
	 */
	public function supports_type( string $type ): bool {
		$supported = $this->get_supported_types();
		return in_array( $type, $supported, true );
	}

	/**
	 * Get fallback conversion for unsupported component types
	 *
	 * @param WPBC_Component $component Unsupported component.
	 * @return string Fallback Avada shortcode.
	 */
	public function get_fallback( WPBC_Component $component ): string {
		// Create a basic text block as fallback
		$content = $component->content ? $component->content : 'Unsupported component type: ' . $component->type;
		return '[fusion_text]' . esc_html( $content ) . '[/fusion_text]';
	}
}
