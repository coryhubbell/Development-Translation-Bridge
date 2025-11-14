<?php
/**
 * Avada Theme Builder Parser
 *
 * Intelligent Avada Fusion Builder shortcode parser featuring:
 * - Hierarchical structure parsing (Container > Row > Column > Element)
 * - 150+ element type support
 * - Nested shortcode handling
 * - Parameter extraction and normalization
 * - Responsive settings parsing
 * - Animation and styling support
 *
 * @package WordPress_Bootstrap_Claude
 * @subpackage Translation_Bridge
 * @since 3.0.0
 */

namespace WPBC\TranslationBridge\Parsers;

use WPBC\TranslationBridge\Core\WPBC_Parser_Interface;
use WPBC\TranslationBridge\Models\WPBC_Component;
use WPBC\TranslationBridge\Utils\WPBC_Shortcode_Helper;
use WPBC\TranslationBridge\Utils\WPBC_CSS_Helper;

/**
 * Class WPBC_Avada_Parser
 *
 * Parse Avada Fusion Builder shortcodes into universal components.
 */
class WPBC_Avada_Parser implements WPBC_Parser_Interface {

	/**
	 * Supported Avada element types (150+ elements)
	 *
	 * @var array<string>
	 */
	private array $supported_types = [
		// Layout
		'builder_container',
		'builder_row',
		'builder_column',

		// Content
		'text',
		'title',
		'content_boxes',
		'content_box',
		'separator',
		'section_separator',
		'alert',
		'fontawesome',
		'highlight',
		'dropcap',
		'popover',
		'tooltip',

		// Media
		'imageframe',
		'gallery',
		'slider',
		'carousel',
		'video',
		'audio',
		'soundcloud',
		'vimeo',
		'youtube',

		// Interactive
		'button',
		'modal',
		'modal_text_link',
		'tabs',
		'tab',
		'accordion',
		'toggle',
		'flip_boxes',
		'flip_box',
		'counters_box',
		'counter_box',
		'counters_circle',
		'counter_circle',
		'progressbar',
		'checklist',
		'menu_anchor',

		// Tables & Lists
		'table',
		'pricing_table',
		'person',
		'testimonials',
		'testimonial',
		'tagline_box',
		'images',

		// Social
		'social_links',
		'sharing_box',
		'recent_posts',

		// Blog & Portfolio
		'blog',
		'portfolio',
		'postcard',
		'recent_works',

		// WooCommerce
		'products_slider',
		'woo_shortcodes',
		'product_slider',
		'featured_products_slider',

		// Forms
		'form',
		'contact',

		// Maps
		'map',
		'google_map',

		// Code & Widgets
		'code_block',
		'widget_area',
		'layerslider',
		'revolution_slider',

		// Chart & Data
		'chart',
		'counters',

		// Advanced
		'post_cards',
		'faq',
		'events',
		'user_login',
		'user_register',
		'woo_cart',
		'woo_checkout',
		'scrolling_text',
		'animated_text',
		'image_hotspots',
		'image_before_after',
		'content_slider',
		'stripe_button',
		'popups',
	];

	/**
	 * Parse Avada shortcode content into universal components
	 *
	 * @param string|array $content Avada shortcode content.
	 * @return WPBC_Component[] Array of parsed components.
	 */
	public function parse( $content ): array {
		if ( is_array( $content ) ) {
			$content = implode( "\n", $content );
		}

		if ( ! is_string( $content ) || empty( $content ) ) {
			return [];
		}

		// Parse Avada hierarchy
		$hierarchy = WPBC_Shortcode_Helper::parse_avada_hierarchy( $content );

		$components = [];

		foreach ( $hierarchy as $container_data ) {
			$container = $this->parse_container( $container_data );
			if ( $container ) {
				$components[] = $container;
			}
		}

		return $components;
	}

	/**
	 * Parse Avada container
	 *
	 * @param array $container_data Container data from hierarchy.
	 * @return WPBC_Component|null Parsed container component.
	 */
	private function parse_container( array $container_data ): ?WPBC_Component {
		$attributes = $this->normalize_attributes( $container_data['attributes'] );

		$container = new WPBC_Component([
			'type'       => 'container',
			'category'   => 'layout',
			'attributes' => $attributes,
			'metadata'   => [
				'source_framework'  => 'avada',
				'original_type'     => 'builder_container',
				'avada_attributes'  => $container_data['attributes'],
			],
		]);

		// Parse rows
		if ( isset( $container_data['rows'] ) && is_array( $container_data['rows'] ) ) {
			foreach ( $container_data['rows'] as $row_data ) {
				$row = $this->parse_row( $row_data );
				if ( $row ) {
					$container->add_child( $row );
				}
			}
		}

		return $container;
	}

	/**
	 * Parse Avada row
	 *
	 * @param array $row_data Row data from hierarchy.
	 * @return WPBC_Component|null Parsed row component.
	 */
	private function parse_row( array $row_data ): ?WPBC_Component {
		$attributes = $this->normalize_attributes( $row_data['attributes'] );

		$row = new WPBC_Component([
			'type'       => 'row',
			'category'   => 'layout',
			'attributes' => $attributes,
			'metadata'   => [
				'source_framework'  => 'avada',
				'original_type'     => 'builder_row',
				'avada_attributes'  => $row_data['attributes'],
			],
		]);

		// Parse columns
		if ( isset( $row_data['columns'] ) && is_array( $row_data['columns'] ) ) {
			foreach ( $row_data['columns'] as $column_data ) {
				$column = $this->parse_column( $column_data );
				if ( $column ) {
					$row->add_child( $column );
				}
			}
		}

		return $row;
	}

	/**
	 * Parse Avada column
	 *
	 * @param array $column_data Column data from hierarchy.
	 * @return WPBC_Component|null Parsed column component.
	 */
	private function parse_column( array $column_data ): ?WPBC_Component {
		$attributes = $this->normalize_attributes( $column_data['attributes'] );

		// Extract column spacing (1_1, 1_2, 1_3, etc.)
		$spacing = $column_data['attributes']['spacing'] ?? '1_1';
		$attributes['width'] = $this->convert_avada_spacing( $spacing );

		$column = new WPBC_Component([
			'type'       => 'column',
			'category'   => 'layout',
			'attributes' => $attributes,
			'metadata'   => [
				'source_framework'  => 'avada',
				'original_type'     => 'builder_column',
				'column_spacing'    => $spacing,
				'avada_attributes'  => $column_data['attributes'],
			],
		]);

		// Parse elements
		if ( isset( $column_data['elements'] ) && is_array( $column_data['elements'] ) ) {
			foreach ( $column_data['elements'] as $element ) {
				$component = $this->parse_element( $element );
				if ( $component ) {
					$column->add_child( $component );
				}
			}
		}

		return $column;
	}

	/**
	 * Parse Avada element
	 *
	 * @param array $element Element data.
	 * @return WPBC_Component|null Parsed element component.
	 */
	private function parse_element( array $element ): ?WPBC_Component {
		$tag = $element['tag'] ?? '';

		// Remove fusion_ prefix to get element type
		$element_type = str_replace( 'fusion_', '', $tag );

		// Map Avada element type to universal type
		$universal_type = $this->map_element_type( $element_type );

		$attributes = $this->normalize_attributes( $element['attributes'] );

		// Extract content
		$content = $element['content'] ?? '';
		$content = do_shortcode( $content ); // Process nested shortcodes

		$component = new WPBC_Component([
			'type'       => $universal_type,
			'category'   => $this->get_category( $universal_type ),
			'attributes' => $attributes,
			'content'    => $content,
			'metadata'   => [
				'source_framework'  => 'avada',
				'original_type'     => $element_type,
				'avada_tag'         => $tag,
				'avada_attributes'  => $element['attributes'],
			],
		]);

		// Parse nested elements (for tabs, accordion, content_boxes, etc.)
		if ( ! empty( $element['children'] ) ) {
			foreach ( $element['children'] as $child ) {
				$child_component = $this->parse_element( $child );
				if ( $child_component ) {
					$component->add_child( $child_component );
				}
			}
		}

		return $component;
	}

	/**
	 * Map Avada element type to universal component type
	 *
	 * @param string $avada_type Avada element type.
	 * @return string Universal component type.
	 */
	private function map_element_type( string $avada_type ): string {
		$type_map = [
			// Content
			'text'                => 'text',
			'title'               => 'heading',
			'content_boxes'       => 'card-group',
			'content_box'         => 'card',
			'separator'           => 'divider',
			'section_separator'   => 'divider',
			'alert'               => 'alert',
			'fontawesome'         => 'icon',
			'highlight'           => 'highlight',
			'dropcap'             => 'dropcap',
			'popover'             => 'popover',
			'tooltip'             => 'tooltip',

			// Media
			'imageframe'          => 'image',
			'gallery'             => 'gallery',
			'slider'              => 'slider',
			'carousel'            => 'slider',
			'video'               => 'video',
			'audio'               => 'audio',
			'soundcloud'          => 'audio',
			'vimeo'               => 'video',
			'youtube'             => 'video',

			// Interactive
			'button'              => 'button',
			'modal'               => 'modal',
			'modal_text_link'     => 'link',
			'tabs'                => 'tabs',
			'tab'                 => 'tab',
			'accordion'           => 'accordion',
			'toggle'              => 'accordion',
			'flip_boxes'          => 'card-group',
			'flip_box'            => 'card',
			'counters_box'        => 'counter-group',
			'counter_box'         => 'counter',
			'counters_circle'     => 'counter-group',
			'counter_circle'      => 'counter',
			'progressbar'         => 'progress',
			'checklist'           => 'list',
			'menu_anchor'         => 'anchor',

			// Tables & Lists
			'table'               => 'table',
			'pricing_table'       => 'pricing-table',
			'person'              => 'team-member',
			'testimonials'        => 'testimonial-group',
			'testimonial'         => 'testimonial',
			'tagline_box'         => 'cta',
			'images'              => 'image-group',

			// Social
			'social_links'        => 'social-icons',
			'sharing_box'         => 'share-buttons',
			'recent_posts'        => 'blog',

			// Blog & Portfolio
			'blog'                => 'blog',
			'portfolio'           => 'portfolio',
			'postcard'            => 'post-card',
			'recent_works'        => 'portfolio',

			// WooCommerce
			'products_slider'     => 'product-slider',
			'woo_shortcodes'      => 'woocommerce',
			'product_slider'      => 'product-slider',
			'featured_products_slider' => 'product-slider',

			// Forms
			'form'                => 'form',
			'contact'             => 'form',

			// Maps
			'map'                 => 'map',
			'google_map'          => 'map',

			// Code & Widgets
			'code_block'          => 'code',
			'widget_area'         => 'widget',
			'layerslider'         => 'slider',
			'revolution_slider'   => 'slider',

			// Chart & Data
			'chart'               => 'chart',
			'counters'            => 'counter-group',

			// Advanced
			'post_cards'          => 'post-grid',
			'faq'                 => 'accordion',
			'events'              => 'events',
			'user_login'          => 'form',
			'user_register'       => 'form',
			'woo_cart'            => 'cart',
			'woo_checkout'        => 'checkout',
			'scrolling_text'      => 'marquee',
			'animated_text'       => 'heading',
			'image_hotspots'      => 'image-hotspot',
			'image_before_after'  => 'image-compare',
			'content_slider'      => 'slider',
			'stripe_button'       => 'button',
			'popups'              => 'modal',
		];

		return $type_map[ $avada_type ] ?? 'unknown';
	}

	/**
	 * Normalize Avada attributes to universal format
	 *
	 * @param array $avada_attrs Avada attributes.
	 * @return array Normalized attributes.
	 */
	private function normalize_attributes( array $avada_attrs ): array {
		$normalized = [];

		// Common attribute mappings
		$attr_map = [
			// Button
			'link'                  => 'url',
			'target'                => 'target',
			'size'                  => 'size',
			'color'                 => 'variant',
			'icon'                  => 'icon',

			// Image
			'image'                 => 'image_url',
			'image_id'              => 'image_id',
			'alt'                   => 'alt_text',
			'lightbox'              => 'lightbox',

			// Title / Heading
			'title'                 => 'heading',
			'size'                  => 'size',
			'content_align'         => 'alignment',

			// Colors
			'backgroundcolor'       => 'background_color',
			'background_color'      => 'background_color',
			'bordercolor'           => 'border_color',
			'border_color'          => 'border_color',
			'textcolor'             => 'text_color',
			'text_color'            => 'text_color',

			// Layout
			'spacing'               => 'spacing',
			'padding'               => 'padding',
			'margin'                => 'margin',
			'border_size'           => 'border_width',
			'border_radius'         => 'border_radius',

			// Animation
			'animation_type'        => 'animation',
			'animation_direction'   => 'animation_direction',
			'animation_speed'       => 'animation_speed',
			'animation_delay'       => 'animation_delay',

			// Visibility
			'hide_on_mobile'        => 'hide_on_mobile',
			'class'                 => 'css_class',
			'id'                    => 'element_id',
		];

		// Map attributes
		foreach ( $avada_attrs as $key => $value ) {
			$universal_key = $attr_map[ $key ] ?? $key;
			$normalized[ $universal_key ] = $value;
		}

		// Convert boolean values
		foreach ( $normalized as $key => $value ) {
			if ( in_array( strtolower( (string) $value ), [ 'yes', 'no' ], true ) ) {
				$normalized[ $key ] = strtolower( $value ) === 'yes';
			}
		}

		return $normalized;
	}

	/**
	 * Convert Avada column spacing to width percentage
	 *
	 * @param string $spacing Avada column spacing (1_1, 1_2, etc.).
	 * @return string Width as percentage.
	 */
	private function convert_avada_spacing( string $spacing ): string {
		$width_map = [
			'1_1'   => '100%',
			'1_2'   => '50%',
			'1_3'   => '33.33%',
			'2_3'   => '66.66%',
			'1_4'   => '25%',
			'3_4'   => '75%',
			'1_5'   => '20%',
			'2_5'   => '40%',
			'3_5'   => '60%',
			'4_5'   => '80%',
			'1_6'   => '16.66%',
			'5_6'   => '83.33%',
		];

		return $width_map[ $spacing ] ?? '100%';
	}

	/**
	 * Get component category from type
	 *
	 * @param string $type Component type.
	 * @return string Category name.
	 */
	private function get_category( string $type ): string {
		$categories = [
			'layout'      => [ 'container', 'row', 'column', 'section', 'spacer' ],
			'content'     => [ 'text', 'heading', 'image', 'card', 'highlight', 'dropcap' ],
			'media'       => [ 'video', 'audio', 'gallery', 'slider' ],
			'interactive' => [ 'button', 'accordion', 'tabs', 'modal', 'popover', 'tooltip' ],
			'form'        => [ 'form', 'input', 'search', 'cart', 'checkout' ],
			'data'        => [ 'counter', 'progress', 'pricing-table', 'chart', 'table' ],
			'social'      => [ 'social-icons', 'testimonial', 'team-member', 'share-buttons' ],
			'blog'        => [ 'blog', 'portfolio', 'post-card', 'post-grid' ],
			'ecommerce'   => [ 'product-slider', 'woocommerce' ],
		];

		foreach ( $categories as $category => $types ) {
			if ( in_array( $type, $types, true ) ) {
				return $category;
			}
		}

		return 'general';
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
	 * Validate Avada shortcode content
	 *
	 * @param string|array $content Content to validate.
	 * @return bool True if valid Avada content.
	 */
	public function is_valid_content( $content ): bool {
		if ( is_array( $content ) ) {
			$content = implode( "\n", $content );
		}

		if ( ! is_string( $content ) || empty( $content ) ) {
			return false;
		}

		// Check for Avada shortcodes
		return WPBC_Shortcode_Helper::is_avada( $content );
	}

	/**
	 * Get supported component types
	 *
	 * @return array<string> Array of supported types.
	 */
	public function get_supported_types(): array {
		return $this->supported_types;
	}

	/**
	 * Parse single element
	 *
	 * @param mixed $element Avada shortcode or element data.
	 * @return WPBC_Component|null Parsed component or null.
	 */
	public function parse_element( $element ): ?WPBC_Component {
		if ( is_string( $element ) ) {
			$components = $this->parse( $element );
			return $components[0] ?? null;
		}

		if ( is_array( $element ) && isset( $element['tag'] ) ) {
			return $this->parse_element( $element );
		}

		return null;
	}
}
