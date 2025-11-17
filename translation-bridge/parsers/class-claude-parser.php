<?php
/**
 * Claude AI-Optimized Framework Parser
 *
 * Intelligent Claude-optimized HTML parser featuring:
 * - Recognition of Claude-enhanced markup
 * - data-claude-editable attribute parsing
 * - Documentation comment extraction
 * - Component metadata recovery
 * - AI modification history tracking
 * - Semantic structure understanding
 *
 * @package WordPress_Bootstrap_Claude
 * @subpackage Translation_Bridge
 * @since 3.1.0
 */

namespace WPBC\TranslationBridge\Parsers;

use WPBC\TranslationBridge\Core\WPBC_Parser_Interface;
use WPBC\TranslationBridge\Models\WPBC_Component;
use WPBC\TranslationBridge\Utils\WPBC_HTML_Helper;

/**
 * Class WPBC_Claude_Parser
 *
 * Parse Claude AI-optimized HTML into universal components.
 */
class WPBC_Claude_Parser implements WPBC_Parser_Interface {

	/**
	 * Supported Claude component types
	 *
	 * @var array<string>
	 */
	private array $supported_types = [
		'button',
		'heading',
		'text',
		'image',
		'card',
		'container',
		'row',
		'column',
		'section',
		'hero',
		'accordion',
		'tabs',
		'modal',
		'nav',
		'form',
		'gallery',
		'testimonial',
		'cta',
		'footer',
		'divider',
		'list',
		'table',
		'video',
		'audio',
		'icon',
		'badge',
		'alert',
		'progress',
		'spinner',
	];

	/**
	 * Parse Claude-optimized HTML into universal components
	 *
	 * @param string|array $content Claude-optimized HTML content.
	 * @return WPBC_Component[] Array of parsed components.
	 */
	public function parse( $content ): array {
		if ( is_array( $content ) ) {
			$content = implode( "\n", $content );
		}

		if ( ! is_string( $content ) || empty( $content ) ) {
			return [];
		}

		// Remove Claude documentation headers/footers
		$content = $this->clean_documentation( $content );

		// Create DOM document
		$dom = new \DOMDocument();
		$previous_value = libxml_use_internal_errors( true );

		// Load HTML5 content
		$dom->loadHTML(
			'<?xml encoding="UTF-8">' . $content,
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
		);

		libxml_clear_errors();
		libxml_use_internal_errors( $previous_value );

		$components = [];

		// Parse body children
		if ( $dom->getElementsByTagName( 'body' )->length > 0 ) {
			$body = $dom->getElementsByTagName( 'body' )->item( 0 );
			foreach ( $body->childNodes as $node ) {
				if ( $node->nodeType === XML_ELEMENT_NODE ) {
					$component = $this->parse_element( $node );
					if ( $component ) {
						$components[] = $component;
					}
				}
			}
		}

		return $components;
	}

	/**
	 * Clean Claude documentation comments
	 *
	 * @param string $content HTML with documentation.
	 * @return string Cleaned HTML.
	 */
	private function clean_documentation( string $content ): string {
		// Remove file header (up to first component)
		$content = preg_replace(
			'/<!--\s*╔═+╗.*?╚═+╝.*?-->/s',
			'',
			$content
		);

		// Keep component-level documentation (it's useful for re-parsing)
		return $content;
	}

	/**
	 * Parse DOM element to component
	 *
	 * @param \DOMElement $element DOM element.
	 * @return WPBC_Component|null Parsed component.
	 */
	private function parse_element( \DOMElement $element ): ?WPBC_Component {
		// Check for data-claude-editable attribute
		$editable_type = $element->getAttribute( 'data-claude-editable' );

		// Detect component type from element and classes
		$component_type = $editable_type ? $editable_type : $this->detect_component_type( $element );

		if ( ! $component_type ) {
			return null;
		}

		// Parse based on type
		$parser_method = 'parse_' . str_replace( '-', '_', $component_type );

		if ( method_exists( $this, $parser_method ) ) {
			return $this->$parser_method( $element );
		}

		// Fallback to generic parsing
		return $this->parse_generic( $element, $component_type );
	}

	/**
	 * Detect component type from element
	 *
	 * @param \DOMElement $element DOM element.
	 * @return string|null Component type.
	 */
	private function detect_component_type( \DOMElement $element ): ?string {
		$tag = $element->tagName;
		$classes = $element->getAttribute( 'class' );

		// Button detection
		if ( strpos( $classes, 'btn' ) !== false || $tag === 'button' ) {
			return 'button';
		}

		// Heading detection
		if ( preg_match( '/^h[1-6]$/i', $tag ) ) {
			return 'heading';
		}

		// Text/paragraph
		if ( $tag === 'p' ) {
			return 'text';
		}

		// Container
		if ( strpos( $classes, 'container' ) !== false ) {
			return 'container';
		}

		// Row
		if ( strpos( $classes, 'row' ) !== false ) {
			return 'row';
		}

		// Column
		if ( preg_match( '/\bcol(-\w+)?(-\d+)?\b/', $classes ) ) {
			return 'column';
		}

		// Card
		if ( strpos( $classes, 'card' ) !== false ) {
			return 'card';
		}

		// Image
		if ( $tag === 'img' ) {
			return 'image';
		}

		// Section
		if ( $tag === 'section' ) {
			return 'section';
		}

		// Nav
		if ( $tag === 'nav' || strpos( $classes, 'nav' ) !== false ) {
			return 'nav';
		}

		// Form
		if ( $tag === 'form' ) {
			return 'form';
		}

		return null;
	}

	/**
	 * Parse button component
	 *
	 * @param \DOMElement $element Button element.
	 * @return WPBC_Component Button component.
	 */
	private function parse_button( \DOMElement $element ): WPBC_Component {
		$classes = explode( ' ', $element->getAttribute( 'class' ) );

		$attributes = [
			'url' => $element->getAttribute( 'href' ) ?: '#',
			'target' => $element->getAttribute( 'target' ) ?: '_self',
		];

		// Parse variant
		foreach ( $classes as $class ) {
			if ( preg_match( '/^btn-(primary|secondary|success|danger|warning|info|light|dark)$/', $class, $matches ) ) {
				$attributes['variant'] = $matches[1];
			}
			if ( preg_match( '/^btn-(sm|lg)$/', $class, $matches ) ) {
				$attributes['size'] = $matches[1];
			}
			if ( $class === 'w-100' ) {
				$attributes['full_width'] = true;
			}
		}

		return new WPBC_Component([
			'type'       => 'button',
			'category'   => 'interactive',
			'attributes' => $attributes,
			'content'    => $element->textContent,
			'metadata'   => [
				'source_framework' => 'claude',
				'original_classes' => $element->getAttribute( 'class' ),
			],
		]);
	}

	/**
	 * Parse heading component
	 *
	 * @param \DOMElement $element Heading element.
	 * @return WPBC_Component Heading component.
	 */
	private function parse_heading( \DOMElement $element ): WPBC_Component {
		$level = (int) substr( $element->tagName, 1 ); // h1 -> 1, h2 -> 2, etc.
		$classes = $element->getAttribute( 'class' );

		$attributes = [
			'level' => $level,
		];

		// Parse size (display class)
		if ( preg_match( '/display-(\d)/', $classes, $matches ) ) {
			$attributes['size'] = $matches[1];
		}

		// Parse alignment
		if ( preg_match( '/text-(start|center|end)/', $classes, $matches ) ) {
			$attributes['alignment'] = $matches[1];
		}

		return new WPBC_Component([
			'type'       => 'heading',
			'category'   => 'content',
			'attributes' => $attributes,
			'content'    => $element->textContent,
			'metadata'   => [
				'source_framework' => 'claude',
				'original_classes' => $classes,
			],
		]);
	}

	/**
	 * Parse text/paragraph component
	 *
	 * @param \DOMElement $element Paragraph element.
	 * @return WPBC_Component Text component.
	 */
	private function parse_text( \DOMElement $element ): WPBC_Component {
		$classes = $element->getAttribute( 'class' );

		$attributes = [];

		// Check for lead paragraph
		if ( strpos( $classes, 'lead' ) !== false ) {
			$attributes['lead'] = true;
		}

		// Parse alignment
		if ( preg_match( '/text-(start|center|end)/', $classes, $matches ) ) {
			$attributes['alignment'] = $matches[1];
		}

		return new WPBC_Component([
			'type'       => 'text',
			'category'   => 'content',
			'attributes' => $attributes,
			'content'    => $element->textContent,
			'metadata'   => [
				'source_framework' => 'claude',
			],
		]);
	}

	/**
	 * Parse container component
	 *
	 * @param \DOMElement $element Container element.
	 * @return WPBC_Component Container component.
	 */
	private function parse_container( \DOMElement $element ): WPBC_Component {
		$classes = $element->getAttribute( 'class' );

		$attributes = [
			'fluid' => strpos( $classes, 'container-fluid' ) !== false,
		];

		$component = new WPBC_Component([
			'type'       => 'container',
			'category'   => 'layout',
			'attributes' => $attributes,
			'metadata'   => [
				'source_framework' => 'claude',
			],
		]);

		// Parse children
		foreach ( $element->childNodes as $child ) {
			if ( $child->nodeType === XML_ELEMENT_NODE ) {
				$child_component = $this->parse_element( $child );
				if ( $child_component ) {
					$component->add_child( $child_component );
				}
			}
		}

		return $component;
	}

	/**
	 * Parse row component
	 *
	 * @param \DOMElement $element Row element.
	 * @return WPBC_Component Row component.
	 */
	private function parse_row( \DOMElement $element ): WPBC_Component {
		$component = new WPBC_Component([
			'type'       => 'row',
			'category'   => 'layout',
			'attributes' => [],
			'metadata'   => [
				'source_framework' => 'claude',
			],
		]);

		// Parse children (columns)
		foreach ( $element->childNodes as $child ) {
			if ( $child->nodeType === XML_ELEMENT_NODE ) {
				$child_component = $this->parse_element( $child );
				if ( $child_component ) {
					$component->add_child( $child_component );
				}
			}
		}

		return $component;
	}

	/**
	 * Parse column component
	 *
	 * @param \DOMElement $element Column element.
	 * @return WPBC_Component Column component.
	 */
	private function parse_column( \DOMElement $element ): WPBC_Component {
		$classes = $element->getAttribute( 'class' );

		// Extract column width from classes
		$width = $this->extract_column_width( $classes );

		$component = new WPBC_Component([
			'type'       => 'column',
			'category'   => 'layout',
			'attributes' => [
				'width' => $width,
			],
			'metadata'   => [
				'source_framework' => 'claude',
				'original_classes' => $classes,
			],
		]);

		// Parse children
		foreach ( $element->childNodes as $child ) {
			if ( $child->nodeType === XML_ELEMENT_NODE ) {
				$child_component = $this->parse_element( $child );
				if ( $child_component ) {
					$component->add_child( $child_component );
				}
			}
		}

		return $component;
	}

	/**
	 * Extract column width from Bootstrap classes
	 *
	 * @param string $classes Class string.
	 * @return string Width as percentage.
	 */
	private function extract_column_width( string $classes ): string {
		// Match col-{number} or col-{breakpoint}-{number}
		if ( preg_match( '/col-(?:\w+-)?(\d+)/', $classes, $matches ) ) {
			$cols = (int) $matches[1];
			$percentage = ( $cols / 12 ) * 100;
			return round( $percentage, 2 ) . '%';
		}

		// col without number = auto
		if ( strpos( $classes, 'col' ) !== false ) {
			return '100%';
		}

		return '100%';
	}

	/**
	 * Parse generic component
	 *
	 * @param \DOMElement $element Element to parse.
	 * @param string      $type Component type.
	 * @return WPBC_Component Generic component.
	 */
	private function parse_generic( \DOMElement $element, string $type ): WPBC_Component {
		$component = new WPBC_Component([
			'type'       => $type,
			'category'   => 'general',
			'attributes' => [],
			'content'    => $element->textContent,
			'metadata'   => [
				'source_framework' => 'claude',
				'tag_name'         => $element->tagName,
				'classes'          => $element->getAttribute( 'class' ),
			],
		]);

		// Parse children if any
		foreach ( $element->childNodes as $child ) {
			if ( $child->nodeType === XML_ELEMENT_NODE ) {
				$child_component = $this->parse_element( $child );
				if ( $child_component ) {
					$component->add_child( $child_component );
				}
			}
		}

		return $component;
	}

	/**
	 * Get framework name
	 *
	 * @return string Framework name.
	 */
	public function get_framework(): string {
		return 'claude';
	}

	/**
	 * Validate Claude-optimized HTML content
	 *
	 * @param string|array $content Content to validate.
	 * @return bool True if valid Claude content.
	 */
	public function is_valid_content( $content ): bool {
		if ( is_array( $content ) ) {
			$content = implode( "\n", $content );
		}

		if ( ! is_string( $content ) || empty( $content ) ) {
			return false;
		}

		// Check for Claude-specific markers
		return strpos( $content, 'data-claude-editable' ) !== false
			|| strpos( $content, 'CLAUDE AI-OPTIMIZED HTML' ) !== false
			|| strpos( $content, 'Generated by WordPress Bootstrap Claude' ) !== false;
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
		if ( $element instanceof \DOMElement ) {
			return $this->parse_element( $element );
		}

		if ( is_string( $element ) ) {
			$components = $this->parse( $element );
			return $components[0] ?? null;
		}

		return null;
	}
}
