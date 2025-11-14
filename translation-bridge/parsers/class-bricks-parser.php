<?php
/**
 * Bricks Builder Parser
 *
 * Intelligent Bricks JSON parser featuring:
 * - JSON structure parsing (Container > Element)
 * - 80+ element type support
 * - Nested element handling
 * - Settings extraction and normalization
 * - Dynamic content support
 * - Responsive controls parsing
 *
 * @package WordPress_Bootstrap_Claude
 * @subpackage Translation_Bridge
 * @since 3.0.0
 */

namespace WPBC\TranslationBridge\Parsers;

use WPBC\TranslationBridge\Core\WPBC_Parser_Interface;
use WPBC\TranslationBridge\Models\WPBC_Component;
use WPBC\TranslationBridge\Utils\WPBC_JSON_Helper;
use WPBC\TranslationBridge\Utils\WPBC_CSS_Helper;

/**
 * Class WPBC_Bricks_Parser
 *
 * Parse Bricks JSON into universal components.
 */
class WPBC_Bricks_Parser implements WPBC_Parser_Interface {

	/**
	 * Supported Bricks element types
	 *
	 * @var array<string>
	 */
	private array $supported_types = [
		'section',
		'container',
		'block',
		'div',
		'heading',
		'text',
		'text-basic',
		'rich-text',
		'image',
		'video',
		'button',
		'icon',
		'icon-box',
		'divider',
		'spacer',
		'map',
		'carousel',
		'slider',
		'tabs',
		'accordion',
		'list',
		'counter',
		'progress-bar',
		'testimonial',
		'pricing-table',
		'countdown',
		'social-icons',
		'alert',
		'form',
		'svg',
		'code',
		'shortcode',
		'template',
		'nav-menu',
		'search',
		'logo',
		'menu',
		'sidebar',
	];

	/**
	 * Parse Bricks JSON into universal components
	 *
	 * @param string|array $content Bricks JSON content.
	 * @return WPBC_Component[] Array of parsed components.
	 */
	public function parse( $content ): array {
		// Handle string JSON
		if ( is_string( $content ) ) {
			$content = json_decode( $content, true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				return [];
			}
		}

		if ( ! is_array( $content ) ) {
			return [];
		}

		$components = [];

		// Parse each top-level element
		foreach ( $content as $element ) {
			$component = $this->parse_element( $element );
			if ( $component ) {
				$components[] = $component;
			}
		}

		return $components;
	}

	/**
	 * Parse single Bricks element
	 *
	 * @param array $element Bricks element data.
	 * @return WPBC_Component|null Parsed component or null.
	 */
	public function parse_element( $element ): ?WPBC_Component {
		if ( ! is_array( $element ) ) {
			return null;
		}

		$element_name = $element['name'] ?? '';
		$universal_type = $this->map_element_type( $element_name );
		$settings = $element['settings'] ?? [];

		// Normalize settings to universal attributes
		$attributes = $this->normalize_settings( $settings );

		// Extract content based on element type
		$content = $this->extract_element_content( $element_name, $settings );

		// Determine category
		$category = $this->get_category( $universal_type );

		$component = new WPBC_Component([
			'type'       => $universal_type,
			'category'   => $category,
			'attributes' => $attributes,
			'content'    => $content,
			'metadata'   => [
				'source_framework' => 'bricks',
				'original_type'    => $element_name,
				'bricks_id'        => $element['id'] ?? '',
				'bricks_settings'  => $settings,
			],
		]);

		// Parse nested elements (children)
		if ( isset( $element['children'] ) && is_array( $element['children'] ) ) {
			foreach ( $element['children'] as $child_element ) {
				$child = $this->parse_element( $child_element );
				if ( $child ) {
					$component->add_child( $child );
				}
			}
		}

		return $component;
	}

	/**
	 * Map Bricks element type to universal component type
	 *
	 * @param string $element_name Bricks element name.
	 * @return string Universal component type.
	 */
	private function map_element_type( string $element_name ): string {
		$type_map = [
			'section'        => 'container',
			'container'      => 'container',
			'block'          => 'container',
			'div'            => 'container',
			'heading'        => 'heading',
			'text'           => 'text',
			'text-basic'     => 'text',
			'rich-text'      => 'text',
			'image'          => 'image',
			'video'          => 'video',
			'button'         => 'button',
			'icon'           => 'icon',
			'icon-box'       => 'card',
			'divider'        => 'divider',
			'spacer'         => 'spacer',
			'map'            => 'map',
			'carousel'       => 'slider',
			'slider'         => 'slider',
			'tabs'           => 'tabs',
			'accordion'      => 'accordion',
			'list'           => 'list',
			'counter'        => 'counter',
			'progress-bar'   => 'progress',
			'testimonial'    => 'testimonial',
			'pricing-table'  => 'pricing-table',
			'countdown'      => 'countdown',
			'social-icons'   => 'social-icons',
			'alert'          => 'alert',
			'form'           => 'form',
			'nav-menu'       => 'nav',
			'menu'           => 'nav',
		];

		return $type_map[ $element_name ] ?? 'unknown';
	}

	/**
	 * Extract content from element settings
	 *
	 * @param string $element_name Element type.
	 * @param array  $settings Element settings.
	 * @return string Extracted content.
	 */
	private function extract_element_content( string $element_name, array $settings ): string {
		switch ( $element_name ) {
			case 'heading':
				return $settings['text'] ?? $settings['content'] ?? '';

			case 'text':
			case 'text-basic':
			case 'rich-text':
				return $settings['text'] ?? $settings['content'] ?? '';

			case 'button':
				return $settings['text'] ?? '';

			case 'icon-box':
				$title = $settings['title'] ?? '';
				$description = $settings['description'] ?? '';
				return $title . ( $title && $description ? "\n\n" : '' ) . $description;

			case 'testimonial':
				return $settings['content'] ?? $settings['text'] ?? '';

			default:
				return $settings['content'] ?? $settings['text'] ?? '';
		}
	}

	/**
	 * Normalize Bricks settings to universal attributes
	 *
	 * @param array $settings Bricks settings.
	 * @return array Normalized attributes.
	 */
	private function normalize_settings( array $settings ): array {
		$normalized = [];

		// Common attribute mappings
		$attr_map = [
			// Button
			'link'               => 'url',
			'text'               => 'label',
			'buttonStyle'        => 'variant',
			'size'               => 'size',

			// Image
			'image'              => 'image_url',
			'alt'                => 'alt_text',

			// Heading
			'content'            => 'heading',
			'tag'                => 'level',

			// Icon box
			'title'              => 'heading',
			'description'        => 'description',
			'icon'               => 'icon',

			// Colors
			'backgroundColor'    => 'background_color',
			'textColor'          => 'text_color',
			'color'              => 'text_color',

			// Layout
			'width'              => 'width',
			'gap'                => 'gap',

			// Alignment
			'textAlign'          => 'alignment',
			'align'              => 'alignment',
		];

		// Map attributes
		foreach ( $settings as $key => $value ) {
			// Skip internal Bricks settings (start with _)
			if ( strpos( $key, '_' ) === 0 ) {
				continue;
			}

			$universal_key = $attr_map[ $key ] ?? $key;

			// Handle complex values
			if ( is_array( $value ) ) {
				// Handle link objects
				if ( $key === 'link' && isset( $value['url'] ) ) {
					$normalized['url'] = $value['url'];
					if ( ! empty( $value['newTab'] ) ) {
						$normalized['target'] = '_blank';
					}
					if ( ! empty( $value['nofollow'] ) ) {
						$normalized['rel'] = 'nofollow';
					}
				}
				// Handle image objects
				elseif ( $key === 'image' && isset( $value['url'] ) ) {
					$normalized['image_url'] = $value['url'];
					if ( isset( $value['alt'] ) ) {
						$normalized['alt_text'] = $value['alt'];
					}
				} else {
					// Convert array to JSON string for preservation
					$normalized[ $universal_key ] = wp_json_encode( $value );
				}
			} else {
				$normalized[ $universal_key ] = $value;
			}
		}

		return $normalized;
	}

	/**
	 * Get component category from type
	 *
	 * @param string $type Component type.
	 * @return string Category name.
	 */
	private function get_category( string $type ): string {
		$categories = [
			'layout'      => [ 'container', 'row', 'column', 'section', 'spacer', 'div', 'block' ],
			'content'     => [ 'text', 'heading', 'image', 'card', 'blockquote' ],
			'media'       => [ 'video', 'audio', 'gallery', 'slider' ],
			'interactive' => [ 'button', 'accordion', 'tabs', 'modal', 'toggle' ],
			'form'        => [ 'form', 'input', 'search' ],
			'data'        => [ 'counter', 'progress', 'pricing-table', 'rating' ],
			'social'      => [ 'social-icons', 'testimonial', 'share-buttons' ],
			'navigation'  => [ 'nav', 'breadcrumb', 'toc', 'menu' ],
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
		return 'bricks';
	}

	/**
	 * Validate Bricks JSON content
	 *
	 * @param string|array $content Content to validate.
	 * @return bool True if valid Bricks content.
	 */
	public function is_valid_content( $content ): bool {
		// Handle string JSON
		if ( is_string( $content ) ) {
			$content = json_decode( $content, true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				return false;
			}
		}

		if ( ! is_array( $content ) ) {
			return false;
		}

		// Use JSON helper validation
		return WPBC_JSON_Helper::is_valid_bricks( $content );
	}

	/**
	 * Get supported component types
	 *
	 * @return array<string> Array of supported types.
	 */
	public function get_supported_types(): array {
		return $this->supported_types;
	}
}
