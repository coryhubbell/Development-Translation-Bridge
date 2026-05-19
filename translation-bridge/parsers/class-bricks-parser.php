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
 * @package DevelopmentTranslation_Bridge
 * @subpackage Translation_Bridge
 * @since 3.0.0
 */

namespace DEVTB\TranslationBridge\Parsers;

use DEVTB\TranslationBridge\Core\DEVTB_Parser_Interface;
use DEVTB\TranslationBridge\Models\DEVTB_Component;
use DEVTB\TranslationBridge\Utils\DEVTB_JSON_Helper;
use DEVTB\TranslationBridge\Utils\DEVTB_CSS_Helper;

/**
 * Class DEVTB_Bricks_Parser
 *
 * Parse Bricks JSON into universal components.
 */
class DEVTB_Bricks_Parser implements DEVTB_Parser_Interface {

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
	 * Parse Bricks JSON into universal components.
	 *
	 * Accepts both the flat Bricks 2.x format (top-level array of elements with
	 * string `parent` ids and `children` arrays of string ids) and the legacy
	 * nested format some test fixtures use (`children` is an array of element
	 * objects). The format is detected per top-level array.
	 *
	 * @param string|array $content Bricks JSON content.
	 * @return DEVTB_Component[] Array of parsed components.
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

		// Flat format: any element has `children` of string ids, or a non-"0" parent.
		if ( $this->is_flat_format( $content ) ) {
			return $this->parse_flat( $content );
		}

		// Legacy nested format: top-level elements contain child element objects.
		$components = [];
		foreach ( $content as $element ) {
			$component = $this->parse_element( $element );
			if ( $component ) {
				$components[] = $component;
			}
		}

		return $components;
	}

	/**
	 * Detect the flat Bricks element format.
	 *
	 * @param array $content Top-level decoded JSON array.
	 * @return bool True if any element looks flat (string child ids or non-"0" parent).
	 */
	private function is_flat_format( array $content ): bool {
		foreach ( $content as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}

			if ( isset( $element['children'] ) && is_array( $element['children'] ) ) {
				foreach ( $element['children'] as $child ) {
					if ( is_string( $child ) ) {
						return true;
					}
				}
			}

			if ( isset( $element['parent'] ) && (string) $element['parent'] !== '0' && (string) $element['parent'] !== '' ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Parse the flat Bricks element format into a tree of universal components.
	 *
	 * Builds an id → element index, then walks roots (parent === "0") and
	 * recurses by resolving child id strings.
	 *
	 * @param array $content Flat array of Bricks elements.
	 * @return DEVTB_Component[] Root components, with children attached.
	 */
	private function parse_flat( array $content ): array {
		$by_id = [];
		foreach ( $content as $element ) {
			if ( is_array( $element ) && isset( $element['id'] ) ) {
				$by_id[ (string) $element['id'] ] = $element;
			}
		}

		$components = [];
		foreach ( $content as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			$parent = isset( $element['parent'] ) ? (string) $element['parent'] : '0';
			if ( $parent !== '0' ) {
				continue;
			}
			$component = $this->parse_flat_element( $element, $by_id );
			if ( $component ) {
				$components[] = $component;
			}
		}

		return $components;
	}

	/**
	 * Parse a single element from the flat registry, resolving child ids recursively.
	 *
	 * @param array $element Element data.
	 * @param array $by_id   Map of id → element for child resolution.
	 * @return DEVTB_Component|null
	 */
	private function parse_flat_element( array $element, array $by_id ): ?DEVTB_Component {
		$component = $this->build_component( $element );
		if ( ! $component ) {
			return null;
		}

		if ( isset( $element['children'] ) && is_array( $element['children'] ) ) {
			foreach ( $element['children'] as $child_ref ) {
				$child_element = is_string( $child_ref ) ? ( $by_id[ $child_ref ] ?? null ) : ( is_array( $child_ref ) ? $child_ref : null );
				if ( ! is_array( $child_element ) ) {
					continue;
				}
				$child = $this->parse_flat_element( $child_element, $by_id );
				if ( $child ) {
					$component->add_child( $child );
				}
			}
		}

		return $component;
	}

	/**
	 * Parse single Bricks element (legacy nested format).
	 *
	 * @param array $element Bricks element data.
	 * @return DEVTB_Component|null Parsed component or null.
	 */
	public function parse_element( $element ): ?DEVTB_Component {
		if ( ! is_array( $element ) ) {
			return null;
		}

		$component = $this->build_component( $element );
		if ( ! $component ) {
			return null;
		}

		// Parse nested elements (children) — legacy nested format only.
		if ( isset( $element['children'] ) && is_array( $element['children'] ) ) {
			foreach ( $element['children'] as $child_element ) {
				if ( ! is_array( $child_element ) ) {
					continue;
				}
				$child = $this->parse_element( $child_element );
				if ( $child ) {
					$component->add_child( $child );
				}
			}
		}

		return $component;
	}

	/**
	 * Build a universal component from a Bricks element without descending into children.
	 *
	 * @param array $element Bricks element data.
	 * @return DEVTB_Component|null
	 */
	private function build_component( array $element ): ?DEVTB_Component {
		$element_name   = $element['name'] ?? '';
		$universal_type = $this->map_element_type( $element_name );
		$settings       = $element['settings'] ?? [];

		$attributes = $this->normalize_settings( $settings );
		$content    = $this->extract_element_content( $element_name, $settings );
		$category   = $this->get_category( $universal_type );

		return new DEVTB_Component([
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
		return DEVTB_JSON_Helper::is_valid_bricks( $content );
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
