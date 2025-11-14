<?php
/**
 * Advanced HTML Manipulation Helper
 *
 * Einstein-level HTML processing featuring:
 * - Intelligent DOM parsing and manipulation
 * - Class extraction and analysis
 * - Attribute normalization
 * - Bootstrap-specific utilities
 * - Security and sanitization
 * - Performance optimization
 *
 * @package WordPress_Bootstrap_Claude
 * @subpackage Translation_Bridge
 * @since 3.0.0
 */

namespace WPBC\TranslationBridge\Utils;

/**
 * Class WPBC_HTML_Helper
 *
 * Advanced HTML processing and manipulation utilities.
 */
class WPBC_HTML_Helper {

	/**
	 * Parse HTML string into DOMDocument
	 *
	 * @param string $html HTML string.
	 * @param bool   $suppress_errors Suppress libxml errors.
	 * @return \DOMDocument|null DOMDocument or null on failure.
	 */
	public static function parse_html( string $html, bool $suppress_errors = true ): ?\DOMDocument {
		if ( empty( $html ) ) {
			return null;
		}

		$dom = new \DOMDocument( '1.0', 'UTF-8' );

		// Configure DOM settings
		$dom->preserveWhiteSpace = false;
		$dom->formatOutput       = true;

		// Suppress errors for malformed HTML
		if ( $suppress_errors ) {
			libxml_use_internal_errors( true );
		}

		// Load HTML with UTF-8 encoding
		$success = $dom->loadHTML(
			'<?xml encoding="UTF-8">' . $html,
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
		);

		if ( $suppress_errors ) {
			libxml_clear_errors();
		}

		return $success ? $dom : null;
	}

	/**
	 * Extract element from HTML by tag name
	 *
	 * @param string $html HTML string.
	 * @param string $tag Tag name to extract.
	 * @param int    $index Index of element (0-based), -1 for all.
	 * @return array|string|null Extracted element(s).
	 */
	public static function extract_element( string $html, string $tag, int $index = 0 ) {
		$dom = self::parse_html( $html );

		if ( ! $dom ) {
			return null;
		}

		$elements = $dom->getElementsByTagName( $tag );

		if ( $elements->length === 0 ) {
			return null;
		}

		// Return all elements
		if ( $index === -1 ) {
			$results = [];
			foreach ( $elements as $element ) {
				$results[] = $dom->saveHTML( $element );
			}
			return $results;
		}

		// Return specific element
		if ( isset( $elements[ $index ] ) ) {
			return $dom->saveHTML( $elements[ $index ] );
		}

		return null;
	}

	/**
	 * Extract classes from HTML element
	 *
	 * @param string $html HTML string.
	 * @return array Array of class names.
	 */
	public static function extract_classes( string $html ): array {
		// Match class attribute
		if ( preg_match( '/class=(["\'])([^"\']*)\1/', $html, $matches ) ) {
			$class_string = $matches[2];
			return array_filter( array_map( 'trim', explode( ' ', $class_string ) ) );
		}

		return [];
	}

	/**
	 * Extract attributes from HTML element
	 *
	 * @param string $html HTML string.
	 * @return array Associative array of attributes.
	 */
	public static function extract_attributes( string $html ): array {
		$attributes = [];

		// Match all attributes
		if ( preg_match_all( '/(\w+)=(["\'])([^"\']*)\2/', $html, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$attributes[ $match[1] ] = $match[3];
			}
		}

		// Match boolean attributes (no value)
		if ( preg_match_all( '/\s(\w+)(?=\s|>|$)/', $html, $matches ) ) {
			foreach ( $matches[1] as $attr ) {
				if ( ! isset( $attributes[ $attr ] ) ) {
					$attributes[ $attr ] = true;
				}
			}
		}

		return $attributes;
	}

	/**
	 * Get Bootstrap component type from classes
	 *
	 * Intelligently detects Bootstrap component types.
	 *
	 * @param array $classes Array of class names.
	 * @return string|null Component type or null.
	 */
	public static function detect_bootstrap_component( array $classes ): ?string {
		$component_patterns = [
			'button'       => [ 'btn' ],
			'card'         => [ 'card' ],
			'alert'        => [ 'alert' ],
			'badge'        => [ 'badge' ],
			'breadcrumb'   => [ 'breadcrumb' ],
			'button-group' => [ 'btn-group' ],
			'carousel'     => [ 'carousel' ],
			'dropdown'     => [ 'dropdown' ],
			'list-group'   => [ 'list-group' ],
			'modal'        => [ 'modal' ],
			'nav'          => [ 'nav' ],
			'navbar'       => [ 'navbar' ],
			'pagination'   => [ 'pagination' ],
			'progress'     => [ 'progress' ],
			'spinner'      => [ 'spinner-border', 'spinner-grow' ],
			'toast'        => [ 'toast' ],
			'tooltip'      => [ 'tooltip' ],
			'popover'      => [ 'popover' ],
			'accordion'    => [ 'accordion' ],
			'container'    => [ 'container', 'container-fluid' ],
			'row'          => [ 'row' ],
			'column'       => [ 'col' ],
		];

		foreach ( $component_patterns as $type => $patterns ) {
			foreach ( $patterns as $pattern ) {
				foreach ( $classes as $class ) {
					// Exact match
					if ( $class === $pattern ) {
						return $type;
					}

					// Prefix match (e.g., col-md-6 matches col)
					if ( strpos( $class, $pattern . '-' ) === 0 ) {
						return $type;
					}
				}
			}
		}

		return null;
	}

	/**
	 * Extract Bootstrap grid information
	 *
	 * @param array $classes Array of class names.
	 * @return array Grid configuration.
	 */
	public static function extract_bootstrap_grid( array $classes ): array {
		$grid = [
			'type'        => null,
			'breakpoints' => [],
		];

		foreach ( $classes as $class ) {
			// Container
			if ( $class === 'container' ) {
				$grid['type'] = 'container';
			} elseif ( $class === 'container-fluid' ) {
				$grid['type'] = 'container-fluid';
			}

			// Row
			if ( $class === 'row' ) {
				$grid['type'] = 'row';
			}

			// Columns (col, col-sm-6, col-md-4, etc.)
			if ( preg_match( '/^col(?:-([a-z]+))?(?:-(\d+|auto))?$/', $class, $matches ) ) {
				$grid['type'] = 'column';

				$breakpoint = $matches[1] ?? 'xs';
				$size       = $matches[2] ?? 'auto';

				$grid['breakpoints'][ $breakpoint ] = $size;
			}

			// Offset classes
			if ( preg_match( '/^offset-([a-z]+)-(\d+)$/', $class, $matches ) ) {
				$grid['offsets'][ $matches[1] ] = (int) $matches[2];
			}

			// Order classes
			if ( preg_match( '/^order-([a-z]+)-(\d+)$/', $class, $matches ) ) {
				$grid['order'][ $matches[1] ] = (int) $matches[2];
			}
		}

		return $grid;
	}

	/**
	 * Extract Bootstrap utilities
	 *
	 * @param array $classes Array of class names.
	 * @return array Utility classes categorized.
	 */
	public static function extract_bootstrap_utilities( array $classes ): array {
		$utilities = [
			'display'    => [],
			'flexbox'    => [],
			'spacing'    => [],
			'sizing'     => [],
			'text'       => [],
			'colors'     => [],
			'borders'    => [],
			'visibility' => [],
			'position'   => [],
			'misc'       => [],
		];

		foreach ( $classes as $class ) {
			// Display utilities
			if ( preg_match( '/^d-(?:([a-z]+)-)?(.+)$/', $class, $matches ) ) {
				$utilities['display'][] = $class;
			}

			// Flexbox utilities
			if ( preg_match( '/^(flex|justify-content|align-items|align-self)/', $class ) ) {
				$utilities['flexbox'][] = $class;
			}

			// Spacing utilities (m-, p-, mt-, mb-, etc.)
			if ( preg_match( '/^([mp][tblrxy]?)-/', $class ) ) {
				$utilities['spacing'][] = $class;
			}

			// Sizing (w-, h-)
			if ( preg_match( '/^[wh]-/', $class ) ) {
				$utilities['sizing'][] = $class;
			}

			// Text utilities
			if ( preg_match( '/^text-/', $class ) ) {
				$utilities['text'][] = $class;
			}

			// Color utilities
			if ( preg_match( '/^(bg|text|border)-(?:primary|secondary|success|danger|warning|info|light|dark)/', $class ) ) {
				$utilities['colors'][] = $class;
			}

			// Border utilities
			if ( preg_match( '/^border/', $class ) ) {
				$utilities['borders'][] = $class;
			}

			// Visibility utilities
			if ( preg_match( '/^(visible|invisible|d-none)/', $class ) ) {
				$utilities['visibility'][] = $class;
			}

			// Position utilities
			if ( preg_match( '/^(position|fixed|sticky|top|bottom|start|end)/', $class ) ) {
				$utilities['position'][] = $class;
			}
		}

		// Remove empty categories
		return array_filter( $utilities );
	}

	/**
	 * Build HTML element from components
	 *
	 * @param string $tag Tag name.
	 * @param array  $attributes Attributes.
	 * @param string $content Inner content.
	 * @param bool   $self_closing Self-closing tag.
	 * @return string HTML string.
	 */
	public static function build_element(
		string $tag,
		array $attributes = [],
		string $content = '',
		bool $self_closing = false
	): string {
		$html = '<' . $tag;

		// Add attributes
		foreach ( $attributes as $key => $value ) {
			if ( is_bool( $value ) ) {
				// Boolean attribute
				if ( $value ) {
					$html .= ' ' . $key;
				}
			} else {
				// Key-value attribute
				$html .= sprintf( ' %s="%s"', $key, esc_attr( $value ) );
			}
		}

		if ( $self_closing ) {
			$html .= ' />';
		} else {
			$html .= '>' . $content . '</' . $tag . '>';
		}

		return $html;
	}

	/**
	 * Add class to HTML element
	 *
	 * @param string       $html HTML string.
	 * @param string|array $classes Class(es) to add.
	 * @return string Modified HTML.
	 */
	public static function add_class( string $html, $classes ): string {
		if ( is_array( $classes ) ) {
			$classes = implode( ' ', $classes );
		}

		$existing_classes = self::extract_classes( $html );
		$new_classes      = array_filter( array_map( 'trim', explode( ' ', $classes ) ) );
		$all_classes      = array_unique( array_merge( $existing_classes, $new_classes ) );

		// Remove existing class attribute
		$html = preg_replace( '/\s*class=(["\'])[^"\']*\1/', '', $html );

		// Add new class attribute
		if ( ! empty( $all_classes ) ) {
			$class_string = implode( ' ', $all_classes );
			$html = preg_replace( '/(<\w+)/', '$1 class="' . esc_attr( $class_string ) . '"', $html, 1 );
		}

		return $html;
	}

	/**
	 * Remove class from HTML element
	 *
	 * @param string       $html HTML string.
	 * @param string|array $classes Class(es) to remove.
	 * @return string Modified HTML.
	 */
	public static function remove_class( string $html, $classes ): string {
		if ( is_array( $classes ) ) {
			$classes = $classes;
		} else {
			$classes = array_filter( array_map( 'trim', explode( ' ', $classes ) ) );
		}

		$existing_classes = self::extract_classes( $html );
		$remaining_classes = array_diff( $existing_classes, $classes );

		// Remove existing class attribute
		$html = preg_replace( '/\s*class=(["\'])[^"\']*\1/', '', $html );

		// Add new class attribute if classes remain
		if ( ! empty( $remaining_classes ) ) {
			$class_string = implode( ' ', $remaining_classes );
			$html = preg_replace( '/(<\w+)/', '$1 class="' . esc_attr( $class_string ) . '"', $html, 1 );
		}

		return $html;
	}

	/**
	 * Extract inner HTML content
	 *
	 * @param string $html HTML string.
	 * @return string Inner content.
	 */
	public static function get_inner_html( string $html ): string {
		// Remove opening and closing tags
		$html = preg_replace( '/^<[^>]+>/', '', $html );
		$html = preg_replace( '/<\/[^>]+>$/', '', $html );

		return trim( $html );
	}

	/**
	 * Strip all HTML tags
	 *
	 * @param string $html HTML string.
	 * @param array  $allowed_tags Allowed tags.
	 * @return string Plain text or HTML with allowed tags.
	 */
	public static function strip_tags( string $html, array $allowed_tags = [] ): string {
		if ( empty( $allowed_tags ) ) {
			return wp_strip_all_tags( $html );
		}

		$allowed = '<' . implode( '><', $allowed_tags ) . '>';
		return strip_tags( $html, $allowed );
	}

	/**
	 * Sanitize HTML for safe output
	 *
	 * @param string $html HTML string.
	 * @param string $context Sanitization context (post, comment, etc.).
	 * @return string Sanitized HTML.
	 */
	public static function sanitize( string $html, string $context = 'post' ): string {
		switch ( $context ) {
			case 'post':
				return wp_kses_post( $html );

			case 'comment':
				return wp_kses_data( $html );

			case 'text':
				return sanitize_text_field( $html );

			case 'textarea':
				return sanitize_textarea_field( $html );

			default:
				return wp_kses_post( $html );
		}
	}

	/**
	 * Minify HTML
	 *
	 * @param string $html HTML string.
	 * @return string Minified HTML.
	 */
	public static function minify( string $html ): string {
		// Remove comments
		$html = preg_replace( '/<!--(?!\s*(?:\[if [^\]]+]|<!|>))(?:(?!-->).)*-->/s', '', $html );

		// Remove whitespace between tags
		$html = preg_replace( '/>\s+</', '><', $html );

		// Remove multiple spaces
		$html = preg_replace( '/\s+/', ' ', $html );

		return trim( $html );
	}

	/**
	 * Pretty print HTML
	 *
	 * @param string $html HTML string.
	 * @return string Formatted HTML.
	 */
	public static function prettify( string $html ): string {
		$dom = self::parse_html( $html );

		if ( ! $dom ) {
			return $html;
		}

		$dom->formatOutput = true;
		return $dom->saveHTML();
	}

	/**
	 * Validate HTML structure
	 *
	 * @param string $html HTML string.
	 * @return bool True if valid.
	 */
	public static function is_valid( string $html ): bool {
		libxml_use_internal_errors( true );
		$dom = self::parse_html( $html, true );
		$errors = libxml_get_errors();
		libxml_clear_errors();

		return empty( $errors );
	}

	/**
	 * Get HTML validation errors
	 *
	 * @param string $html HTML string.
	 * @return array Array of errors.
	 */
	public static function get_validation_errors( string $html ): array {
		libxml_use_internal_errors( true );
		self::parse_html( $html, true );
		$errors = libxml_get_errors();
		libxml_clear_errors();

		return array_map( function( $error ) {
			return [
				'line'    => $error->line,
				'column'  => $error->column,
				'message' => trim( $error->message ),
				'level'   => $error->level,
			];
		}, $errors );
	}
}
