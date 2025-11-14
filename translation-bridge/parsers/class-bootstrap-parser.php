<?php
/**
 * Bootstrap 5.3.3 Parser
 *
 * Intelligent Bootstrap HTML parser featuring:
 * - Automatic component detection
 * - Grid system extraction
 * - Utility class parsing
 * - Responsive breakpoint handling
 * - Nested component support
 * - Semantic understanding
 *
 * @package WordPress_Bootstrap_Claude
 * @subpackage Translation_Bridge
 * @since 3.0.0
 */

namespace WPBC\TranslationBridge\Parsers;

use WPBC\TranslationBridge\Core\WPBC_Parser_Interface;
use WPBC\TranslationBridge\Models\WPBC_Component;
use WPBC\TranslationBridge\Utils\WPBC_HTML_Helper;
use WPBC\TranslationBridge\Utils\WPBC_CSS_Helper;

/**
 * Class WPBC_Bootstrap_Parser
 *
 * Parse Bootstrap 5.3.3 HTML into universal components.
 */
class WPBC_Bootstrap_Parser implements WPBC_Parser_Interface {

	/**
	 * Supported Bootstrap component types
	 *
	 * @var array<string>
	 */
	private array $supported_types = [
		'button',
		'card',
		'alert',
		'badge',
		'breadcrumb',
		'button-group',
		'carousel',
		'dropdown',
		'list-group',
		'modal',
		'nav',
		'navbar',
		'pagination',
		'progress',
		'spinner',
		'accordion',
		'container',
		'row',
		'column',
		'heading',
		'text',
		'image',
		'link',
		'divider',
		'form',
		'input',
		'table',
	];

	/**
	 * Parse Bootstrap HTML into universal components
	 *
	 * @param string|array $content Bootstrap HTML content.
	 * @return WPBC_Component[] Array of parsed components.
	 */
	public function parse( $content ): array {
		if ( is_array( $content ) ) {
			$content = implode( "\n", $content );
		}

		if ( ! is_string( $content ) || empty( $content ) ) {
			return [];
		}

		$dom = WPBC_HTML_Helper::parse_html( $content );

		if ( ! $dom ) {
			return [];
		}

		$components = [];
		$body = $dom->getElementsByTagName( 'body' )->item( 0 );

		if ( $body ) {
			foreach ( $body->childNodes as $node ) {
				if ( $node->nodeType === XML_ELEMENT_NODE ) {
					$component = $this->parse_node( $node, $dom );
					if ( $component ) {
						$components[] = $component;
					}
				}
			}
		}

		return $components;
	}

	/**
	 * Parse single DOM node into component
	 *
	 * @param \DOMNode     $node DOM node.
	 * @param \DOMDocument $dom DOM document.
	 * @return WPBC_Component|null Parsed component or null.
	 */
	private function parse_node( \DOMNode $node, \DOMDocument $dom ): ?WPBC_Component {
		if ( $node->nodeType !== XML_ELEMENT_NODE ) {
			return null;
		}

		$html = $dom->saveHTML( $node );

		// Extract classes
		$classes = WPBC_HTML_Helper::extract_classes( $html );

		// Detect component type
		$type = $this->detect_component_type( $node->nodeName, $classes );

		// Extract attributes
		$attributes = $this->extract_bootstrap_attributes( $node, $classes );

		// Extract styles
		$styles = $this->extract_styles( $node );

		// Get content
		$content = $this->extract_content( $node, $dom );

		// Create component
		$component = new WPBC_Component([
			'type'       => $type,
			'category'   => $this->get_category( $type ),
			'attributes' => $attributes,
			'styles'     => $styles,
			'content'    => $content,
			'metadata'   => [
				'source_framework' => 'bootstrap',
				'original_tag'     => $node->nodeName,
				'original_classes' => $classes,
			],
		]);

		// Parse children recursively
		foreach ( $node->childNodes as $child ) {
			if ( $child->nodeType === XML_ELEMENT_NODE ) {
				$child_component = $this->parse_node( $child, $dom );
				if ( $child_component ) {
					$component->add_child( $child_component );
				}
			}
		}

		return $component;
	}

	/**
	 * Detect component type from tag and classes
	 *
	 * @param string $tag_name HTML tag name.
	 * @param array  $classes CSS classes.
	 * @return string Component type.
	 */
	private function detect_component_type( string $tag_name, array $classes ): string {
		// Try to detect from Bootstrap classes
		$detected = WPBC_HTML_Helper::detect_bootstrap_component( $classes );

		if ( $detected ) {
			return $detected;
		}

		// Fallback to tag-based detection
		$tag_map = [
			'h1' => 'heading',
			'h2' => 'heading',
			'h3' => 'heading',
			'h4' => 'heading',
			'h5' => 'heading',
			'h6' => 'heading',
			'p'  => 'text',
			'a'  => 'link',
			'img' => 'image',
			'button' => 'button',
			'form' => 'form',
			'input' => 'input',
			'table' => 'table',
			'hr' => 'divider',
			'div' => 'container',
			'section' => 'container',
		];

		return $tag_map[ strtolower( $tag_name ) ] ?? 'unknown';
	}

	/**
	 * Extract Bootstrap-specific attributes
	 *
	 * @param \DOMNode $node DOM node.
	 * @param array    $classes CSS classes.
	 * @return array Extracted attributes.
	 */
	private function extract_bootstrap_attributes( \DOMNode $node, array $classes ): array {
		$attributes = [];

		// Get all HTML attributes
		if ( $node->hasAttributes() ) {
			foreach ( $node->attributes as $attr ) {
				$name = $attr->name;
				$value = $attr->value;

				// Skip class (handled separately)
				if ( $name === 'class' ) {
					continue;
				}

				// Map common attributes to universal names
				$attr_map = [
					'href' => 'url',
					'src'  => 'image_url',
					'alt'  => 'alt_text',
					'title' => 'title',
					'id' => 'element_id',
					'data-bs-toggle' => 'toggle',
					'data-bs-target' => 'target',
					'aria-label' => 'aria_label',
					'role' => 'role',
				];

				$universal_name = $attr_map[ $name ] ?? $name;
				$attributes[ $universal_name ] = $value;
			}
		}

		// Extract grid information
		$grid = WPBC_HTML_Helper::extract_bootstrap_grid( $classes );
		if ( ! empty( $grid['breakpoints'] ) ) {
			$attributes['grid'] = $grid;
		}

		// Extract utilities
		$utilities = WPBC_HTML_Helper::extract_bootstrap_utilities( $classes );
		if ( ! empty( $utilities ) ) {
			$attributes['utilities'] = $utilities;
		}

		// Extract button variant
		foreach ( $classes as $class ) {
			if ( preg_match( '/^btn-(primary|secondary|success|danger|warning|info|light|dark|link)$/', $class, $matches ) ) {
				$attributes['variant'] = $matches[1];
			}

			// Card variant
			if ( preg_match( '/^(text|bg)-(primary|secondary|success|danger|warning|info|light|dark)$/', $class, $matches ) ) {
				$attributes['color'] = $matches[2];
			}

			// Size modifiers
			if ( preg_match( '/^btn-(sm|lg)$/', $class, $matches ) ) {
				$attributes['size'] = $matches[1];
			}

			// Alignment
			if ( preg_match( '/^text-(start|center|end|justify)$/', $class, $matches ) ) {
				$attributes['alignment'] = $matches[1];
			}
		}

		return $attributes;
	}

	/**
	 * Extract inline styles
	 *
	 * @param \DOMNode $node DOM node.
	 * @return array Style properties.
	 */
	private function extract_styles( \DOMNode $node ): array {
		if ( ! $node->hasAttributes() ) {
			return [];
		}

		$style_attr = $node->attributes->getNamedItem( 'style' );

		if ( ! $style_attr ) {
			return [];
		}

		return WPBC_CSS_Helper::parse_inline( $style_attr->value );
	}

	/**
	 * Extract text content from node
	 *
	 * @param \DOMNode     $node DOM node.
	 * @param \DOMDocument $dom DOM document.
	 * @return string Text content.
	 */
	private function extract_content( \DOMNode $node, \DOMDocument $dom ): string {
		// For text elements, get text content
		$text_elements = [ 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'span', 'a', 'button' ];

		if ( in_array( strtolower( $node->nodeName ), $text_elements, true ) ) {
			return trim( $node->textContent );
		}

		// For other elements, return empty (children will be parsed separately)
		return '';
	}

	/**
	 * Get component category from type
	 *
	 * @param string $type Component type.
	 * @return string Category name.
	 */
	private function get_category( string $type ): string {
		$categories = [
			'layout' => [ 'container', 'row', 'column', 'grid' ],
			'content' => [ 'heading', 'text', 'image', 'link', 'card' ],
			'interactive' => [ 'button', 'dropdown', 'modal', 'accordion', 'carousel' ],
			'navigation' => [ 'nav', 'navbar', 'breadcrumb', 'pagination' ],
			'feedback' => [ 'alert', 'toast', 'spinner', 'progress' ],
			'form' => [ 'form', 'input', 'select', 'textarea', 'checkbox', 'radio' ],
			'data' => [ 'table', 'list-group' ],
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
		return 'bootstrap';
	}

	/**
	 * Validate Bootstrap HTML content
	 *
	 * @param string|array $content Content to validate.
	 * @return bool True if valid Bootstrap HTML.
	 */
	public function is_valid_content( $content ): bool {
		if ( is_array( $content ) ) {
			$content = implode( "\n", $content );
		}

		if ( ! is_string( $content ) || empty( $content ) ) {
			return false;
		}

		// Check for Bootstrap classes
		$bootstrap_indicators = [
			'class="container',
			'class="row',
			'class="col-',
			'class="btn ',
			'class="card',
			'class="alert',
			'class="nav',
			'class="modal',
		];

		foreach ( $bootstrap_indicators as $indicator ) {
			if ( strpos( $content, $indicator ) !== false ) {
				return true;
			}
		}

		// If no Bootstrap classes found but valid HTML, still accept
		return WPBC_HTML_Helper::is_valid( $content );
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
	 * @param mixed $element HTML element or string.
	 * @return WPBC_Component|null Parsed component or null.
	 */
	public function parse_element( $element ): ?WPBC_Component {
		if ( is_string( $element ) ) {
			$components = $this->parse( $element );
			return $components[0] ?? null;
		}

		if ( $element instanceof \DOMNode ) {
			$dom = new \DOMDocument();
			return $this->parse_node( $element, $dom );
		}

		return null;
	}
}
