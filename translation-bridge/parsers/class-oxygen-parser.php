<?php
/**
 * Oxygen Builder Parser
 *
 * Intelligent Oxygen Builder parser featuring:
 * - JSON element parsing
 * - Element hierarchy (parent-child via ct_parent)
 * - 30+ element type support
 * - Style extraction from 'original' property
 * - Content extraction from ct_content
 *
 * @package DevelopmentTranslation_Bridge
 * @subpackage Translation_Bridge
 * @since 3.2.0
 */

namespace DEVTB\TranslationBridge\Parsers;

use DEVTB\TranslationBridge\Core\DEVTB_Parser_Interface;
use DEVTB\TranslationBridge\Models\DEVTB_Component;
use DEVTB\TranslationBridge\Utils\DEVTB_JSON_Helper;
use DEVTB\TranslationBridge\Utils\DEVTB_CSS_Helper;

/**
 * Class DEVTB_Oxygen_Parser
 *
 * Parse Oxygen Builder JSON into universal components.
 */
class DEVTB_Oxygen_Parser implements DEVTB_Parser_Interface {

	/**
	 * Supported Oxygen element types
	 *
	 * @var array<string>
	 */
	private array $supported_types = [
		'ct_section',
		'ct_div_block',
		'ct_text_block',
		'ct_headline',
		'ct_link_text',
		'ct_link_button',
		'ct_image',
		'ct_fancy_icon',
		'ct_icon',
		'ct_video',
		'ct_audio',
		'ct_code_block',
		'ct_separator',
		'ct_span',
		'ct_widget',
		'ct_slider',
		'ct_slide',
		'ct_progress_bar',
		'ct_testimonial',
		'ct_pricing_box',
		'ct_google_map',
		'ct_tabs',
		'ct_tab',
		'ct_accordion',
		'ct_toggle',
		'ct_menu',
		'ct_nav_menu',
		'ct_reusable',
		'oxy_posts_grid',
		'oxy_gallery',
		'ct_inner_content',
	];

	/**
	 * Parse Oxygen JSON into universal components
	 *
	 * @param string|array $content Oxygen JSON content.
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

		// Oxygen 6 content is a nested tree with namespaced `type` keys and lives
		// under `_breakdance_data` post meta — fundamentally incompatible with the
		// flat `ct_parent` model. Caller should route to DEVTB_Oxygen6_Parser.
		if ( self::is_oxygen6_payload( $content ) ) {
			return [];
		}

		// Oxygen stores elements as flat array with parent-child relationships
		// We need to build the tree structure
		$elements_by_id = $this->index_elements_by_id( $content );
		$root_elements = $this->get_root_elements( $content );

		$components = [];
		foreach ( $root_elements as $element ) {
			$component = $this->parse_oxygen_element( $element, $elements_by_id );
			if ( $component ) {
				$components[] = $component;
			}
		}

		return $components;
	}

	/**
	 * Index elements by their ct_id for quick lookup
	 *
	 * @param array $elements All elements.
	 * @return array Indexed elements.
	 */
	private function index_elements_by_id( array $elements ): array {
		$indexed = [];

		foreach ( $elements as $element ) {
			$ct_id = $element['options']['ct_id'] ?? $element['id'] ?? null;
			if ( $ct_id !== null ) {
				$indexed[ $ct_id ] = $element;
			}
		}

		return $indexed;
	}

	/**
	 * Get root elements (elements with no parent or parent = 0)
	 *
	 * @param array $elements All elements.
	 * @return array Root elements.
	 */
	private function get_root_elements( array $elements ): array {
		$roots = [];

		foreach ( $elements as $element ) {
			$parent_id = $element['options']['ct_parent'] ?? 0;
			if ( empty( $parent_id ) || $parent_id === 0 ) {
				$roots[] = $element;
			}
		}

		return $roots;
	}

	/**
	 * Get child elements of a parent
	 *
	 * @param int|string $parent_id Parent element ID.
	 * @param array      $all_elements All elements indexed by ID.
	 * @return array Child elements.
	 */
	private function get_child_elements( $parent_id, array $all_elements ): array {
		$children = [];

		foreach ( $all_elements as $element ) {
			$element_parent = $element['options']['ct_parent'] ?? 0;
			if ( $element_parent == $parent_id ) {
				$children[] = $element;
			}
		}

		return $children;
	}

	/**
	 * Parse single Oxygen element
	 *
	 * @param array $element Element data.
	 * @param array $all_elements All elements indexed by ID.
	 * @return DEVTB_Component|null Parsed component or null.
	 */
	private function parse_oxygen_element( array $element, array $all_elements ): ?DEVTB_Component {
		$element_name = $element['name'] ?? '';

		if ( empty( $element_name ) ) {
			return null;
		}

		// Map element type to universal type
		$universal_type = $this->map_element_type( $element_name );

		$options = $element['options'] ?? [];
		$ct_id = $options['ct_id'] ?? $element['id'] ?? uniqid();

		// Extract content
		$content = $this->extract_content( $element_name, $options );

		// Normalize attributes
		$attributes = $this->normalize_options( $options );

		$component = new DEVTB_Component([
			'type'       => $universal_type,
			'category'   => $this->get_category( $universal_type ),
			'attributes' => $attributes,
			'content'    => $content,
			'metadata'   => [
				'source_framework' => 'oxygen',
				'original_type'    => $element_name,
				'ct_id'            => $ct_id,
				'oxygen_options'   => $options,
			],
		]);

		// Parse child elements
		$children = $this->get_child_elements( $ct_id, $all_elements );
		foreach ( $children as $child ) {
			$child_component = $this->parse_oxygen_element( $child, $all_elements );
			if ( $child_component ) {
				$component->add_child( $child_component );
			}
		}

		return $component;
	}

	/**
	 * Map Oxygen element type to universal type
	 *
	 * @param string $element_name Oxygen element name.
	 * @return string Universal type.
	 */
	private function map_element_type( string $element_name ): string {
		$type_map = [
			'ct_section'       => 'container',
			'ct_div_block'     => 'container',
			'ct_text_block'    => 'text',
			'ct_headline'      => 'heading',
			'ct_link_text'     => 'link',
			'ct_link_button'   => 'button',
			'ct_image'         => 'image',
			'ct_fancy_icon'    => 'icon',
			'ct_icon'          => 'icon',
			'ct_video'         => 'video',
			'ct_audio'         => 'audio',
			'ct_code_block'    => 'code',
			'ct_separator'     => 'divider',
			'ct_span'          => 'text',
			'ct_slider'        => 'slider',
			'ct_slide'         => 'slide',
			'ct_progress_bar'  => 'progress',
			'ct_testimonial'   => 'testimonial',
			'ct_pricing_box'   => 'pricing-table',
			'ct_google_map'    => 'map',
			'ct_tabs'          => 'tabs',
			'ct_tab'           => 'tab',
			'ct_accordion'     => 'accordion',
			'ct_toggle'        => 'toggle',
			'ct_menu'          => 'menu',
			'ct_nav_menu'      => 'menu',
			'oxy_posts_grid'   => 'posts',
			'oxy_gallery'      => 'gallery',
			'ct_inner_content' => 'container',
		];

		return $type_map[ $element_name ] ?? 'container';
	}

	/**
	 * Get component category based on type
	 *
	 * @param string $type Universal type.
	 * @return string Category.
	 */
	private function get_category( string $type ): string {
		$category_map = [
			'container'      => 'layout',
			'text'           => 'content',
			'heading'        => 'content',
			'link'           => 'interactive',
			'button'         => 'interactive',
			'image'          => 'media',
			'icon'           => 'decorative',
			'video'          => 'media',
			'audio'          => 'media',
			'code'           => 'content',
			'divider'        => 'decorative',
			'slider'         => 'media',
			'slide'          => 'media',
			'progress'       => 'interactive',
			'testimonial'    => 'content',
			'pricing-table'  => 'content',
			'map'            => 'media',
			'tabs'           => 'interactive',
			'tab'            => 'interactive',
			'accordion'      => 'interactive',
			'toggle'         => 'interactive',
			'menu'           => 'navigation',
			'posts'          => 'content',
			'gallery'        => 'media',
		];

		return $category_map[ $type ] ?? 'general';
	}

	/**
	 * Extract content from element
	 *
	 * @param string $element_name Element name.
	 * @param array  $options Element options.
	 * @return string Content.
	 */
	private function extract_content( string $element_name, array $options ): string {
		// Check for ct_content first
		if ( ! empty( $options['ct_content'] ) ) {
			return $options['ct_content'];
		}

		// Element-specific content extraction
		switch ( $element_name ) {
			case 'ct_headline':
				return $options['headline_text'] ?? '';

			case 'ct_text_block':
				return $options['text'] ?? '';

			case 'ct_link_text':
			case 'ct_link_button':
				return $options['text'] ?? $options['ct_content'] ?? '';

			case 'ct_code_block':
				return $options['code'] ?? '';

			default:
				return '';
		}
	}

	/**
	 * Normalize Oxygen options to universal attributes
	 *
	 * @param array $options Oxygen element options.
	 * @return array Normalized attributes.
	 */
	private function normalize_options( array $options ): array {
		$attributes = [];

		// Extract styles from 'original' object
		if ( ! empty( $options['original'] ) && is_array( $options['original'] ) ) {
			$attributes = array_merge( $attributes, $this->extract_styles( $options['original'] ) );
		}

		// Link URL
		if ( ! empty( $options['url'] ) ) {
			$attributes['href'] = $options['url'];
		}

		// Link target
		if ( ! empty( $options['target'] ) ) {
			$attributes['target'] = $options['target'];
		}

		// Image source
		if ( ! empty( $options['src'] ) ) {
			$attributes['src'] = $options['src'];
		}

		// Alt text
		if ( ! empty( $options['alt'] ) ) {
			$attributes['alt'] = $options['alt'];
		}

		// Heading tag
		if ( ! empty( $options['tag'] ) ) {
			// Extract number from tag (h1, h2, etc.)
			if ( preg_match( '/h(\d)/', $options['tag'], $matches ) ) {
				$attributes['level'] = intval( $matches[1] );
			}
		}

		// ID and classes
		if ( ! empty( $options['selector'] ) ) {
			$attributes['oxygen-selector'] = $options['selector'];
		}

		if ( ! empty( $options['classes'] ) ) {
			$attributes['class'] = is_array( $options['classes'] )
				? implode( ' ', $options['classes'] )
				: $options['classes'];
		}

		if ( ! empty( $options['id'] ) ) {
			$attributes['id'] = $options['id'];
		}

		return $attributes;
	}

	/**
	 * Extract styles from Oxygen 'original' object
	 *
	 * @param array $original Original styles object.
	 * @return array CSS properties.
	 */
	private function extract_styles( array $original ): array {
		$styles = [];

		// Common style properties
		$style_map = [
			'background-color'   => 'background-color',
			'background-image'   => 'background-image',
			'color'              => 'color',
			'font-family'        => 'font-family',
			'font-size'          => 'font-size',
			'font-weight'        => 'font-weight',
			'line-height'        => 'line-height',
			'text-align'         => 'text-align',
			'padding-top'        => 'padding-top',
			'padding-right'      => 'padding-right',
			'padding-bottom'     => 'padding-bottom',
			'padding-left'       => 'padding-left',
			'margin-top'         => 'margin-top',
			'margin-right'       => 'margin-right',
			'margin-bottom'      => 'margin-bottom',
			'margin-left'        => 'margin-left',
			'border-radius'      => 'border-radius',
			'border-width'       => 'border-width',
			'border-color'       => 'border-color',
			'border-style'       => 'border-style',
			'width'              => 'width',
			'height'             => 'height',
			'max-width'          => 'max-width',
			'min-height'         => 'min-height',
			'display'            => 'display',
			'flex-direction'     => 'flex-direction',
			'justify-content'    => 'justify-content',
			'align-items'        => 'align-items',
		];

		foreach ( $style_map as $oxygen_prop => $css_prop ) {
			if ( isset( $original[ $oxygen_prop ] ) ) {
				$styles[ $css_prop ] = $original[ $oxygen_prop ];
			}
		}

		return $styles;
	}

	/**
	 * Validate Oxygen content
	 *
	 * @param mixed $content Content to validate.
	 * @return bool True if valid.
	 */
	public function validate( $content ): bool {
		// Handle string JSON
		if ( is_string( $content ) ) {
			$content = json_decode( $content, true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				return false;
			}
		}

		// Must be an array
		if ( ! is_array( $content ) || empty( $content ) ) {
			return false;
		}

		// Check if it looks like Oxygen data
		// Oxygen elements have 'name' and 'options' properties
		foreach ( $content as $element ) {
			if ( is_array( $element ) && isset( $element['name'] ) && isset( $element['options'] ) ) {
				// Check for Oxygen-specific element names
				if ( strpos( $element['name'], 'ct_' ) === 0 || strpos( $element['name'], 'oxy_' ) === 0 ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Get framework name
	 *
	 * @return string Framework name.
	 */
	public function get_framework(): string {
		return 'oxygen';
	}

	/**
	 * Validate content (alias for interface compliance)
	 *
	 * @param mixed $content Content to validate.
	 * @return bool True if valid.
	 */
	public function is_valid_content( $content ): bool {
		return $this->validate( $content );
	}

	/**
	 * Get supported component types
	 *
	 * @return array Supported types.
	 */
	public function get_supported_types(): array {
		return [ 'section', 'div', 'heading', 'text', 'button', 'image', 'link', 'code' ];
	}

	/**
	 * Parse single element (public interface method)
	 *
	 * @param mixed $element Element to parse.
	 * @return DEVTB_Component|null Parsed component or null.
	 */
	public function parse_element( $element ): ?DEVTB_Component {
		if ( is_string( $element ) ) {
			$components = $this->parse( $element );
			return $components[0] ?? null;
		}

		if ( is_array( $element ) ) {
			// For array elements, parse them directly
			// Note: This won't have full context of $all_elements, but it's needed for interface compliance
			$components = $this->parse( json_encode( [ $element ] ) );
			return $components[0] ?? null;
		}

		return null;
	}

	/**
	 * Detect an Oxygen 6 (Breakdance-rewrite) payload.
	 *
	 * Oxygen 6 is a ground-up rewrite that abandons the flat `ct_*` element
	 * vocabulary and `ct_parent` linkage of Classic. It stores designs as a
	 * nested JSON tree under `_breakdance_data`, where every node carries a
	 * namespaced `type` (e.g. `EssentialElements\Heading`) and nests children
	 * inline. This helper looks for those structural markers so callers can
	 * route to DEVTB_Oxygen6_Parser instead.
	 *
	 * @param mixed $content Decoded JSON payload (array or scalar).
	 * @return bool
	 */
	public static function is_oxygen6_payload( $content ): bool {
		if ( is_string( $content ) ) {
			$decoded = json_decode( $content, true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				return false;
			}
			$content = $decoded;
		}

		if ( ! is_array( $content ) ) {
			return false;
		}

		// Wrapped payload: { "tree": ..., "_nextNodeId": ... }
		if ( isset( $content['_nextNodeId'] ) || ( isset( $content['tree'] ) && is_array( $content['tree'] ) ) ) {
			return true;
		}

		// Bare node or list of nodes: namespaced `type` (contains `\`) anywhere in tree.
		return self::tree_has_namespaced_type( $content );
	}

	/**
	 * Recursively scan for a namespaced element `type` string.
	 *
	 * @param mixed $data Decoded JSON branch.
	 * @return bool
	 */
	private static function tree_has_namespaced_type( $data ): bool {
		if ( ! is_array( $data ) ) {
			return false;
		}

		if ( isset( $data['type'] ) && is_string( $data['type'] ) && strpos( $data['type'], '\\' ) !== false ) {
			return true;
		}

		foreach ( $data as $value ) {
			if ( is_array( $value ) && self::tree_has_namespaced_type( $value ) ) {
				return true;
			}
		}

		return false;
	}
}
