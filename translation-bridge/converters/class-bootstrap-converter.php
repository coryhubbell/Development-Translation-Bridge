<?php
/**
 * Bootstrap 5.3.3 Converter
 *
 * Intelligent Bootstrap HTML converter featuring:
 * - Component to HTML generation
 * - Grid system building
 * - Utility class application
 * - Responsive output
 * - Clean, semantic HTML
 * - Accessibility support
 *
 * @package WordPress_Bootstrap_Claude
 * @subpackage Translation_Bridge
 * @since 3.0.0
 */

namespace WPBC\TranslationBridge\Converters;

use WPBC\TranslationBridge\Core\WPBC_Converter_Interface;
use WPBC\TranslationBridge\Models\WPBC_Component;
use WPBC\TranslationBridge\Utils\WPBC_HTML_Helper;
use WPBC\TranslationBridge\Utils\WPBC_CSS_Helper;

/**
 * Class WPBC_Bootstrap_Converter
 *
 * Convert universal components to Bootstrap 5.3.3 HTML.
 */
class WPBC_Bootstrap_Converter implements WPBC_Converter_Interface {

	/**
	 * Convert universal component(s) to Bootstrap HTML
	 *
	 * @param WPBC_Component|WPBC_Component[] $component Component(s) to convert.
	 * @return string Bootstrap HTML output.
	 */
	public function convert( $component ): string {
		// Handle array of components
		if ( is_array( $component ) ) {
			$html_parts = [];
			foreach ( $component as $comp ) {
				$html_parts[] = $this->convert_component( $comp );
			}
			return implode( "\n", $html_parts );
		}

		// Handle single component
		return $this->convert_component( $component );
	}

	/**
	 * Convert single component to Bootstrap HTML
	 *
	 * @param WPBC_Component $component Component to convert.
	 * @return string Bootstrap HTML.
	 */
	public function convert_component( WPBC_Component $component ): string {
		$type = $component->type;

		// Route to specific converter based on type
		$converter_method = 'convert_' . str_replace( '-', '_', $type );

		if ( method_exists( $this, $converter_method ) ) {
			return $this->$converter_method( $component );
		}

		// Fallback to generic conversion
		return $this->convert_generic( $component );
	}

	/**
	 * Convert button component
	 *
	 * @param WPBC_Component $component Button component.
	 * @return string Button HTML.
	 */
	private function convert_button( WPBC_Component $component ): string {
		$classes = [ 'btn' ];

		// Variant
		$variant = $component->get_attribute( 'variant', 'primary' );
		$classes[] = 'btn-' . $variant;

		// Size
		$size = $component->get_attribute( 'size' );
		if ( $size ) {
			$classes[] = 'btn-' . $size;
		}

		// Additional utilities
		$utilities = $component->get_attribute( 'utilities', [] );
		if ( ! empty( $utilities ) ) {
			foreach ( $utilities as $util_category ) {
				if ( is_array( $util_category ) ) {
					$classes = array_merge( $classes, $util_category );
				}
			}
		}

		$attributes = [
			'type'  => $component->get_attribute( 'type', 'button' ),
			'class' => implode( ' ', $classes ),
		];

		// URL (for link-style buttons)
		$url = $component->get_attribute( 'url' );
		if ( $url ) {
			$attributes['href'] = $url;
			$tag = 'a';
		} else {
			$tag = 'button';
		}

		// ARIA label
		$aria_label = $component->get_attribute( 'aria_label' );
		if ( $aria_label ) {
			$attributes['aria-label'] = $aria_label;
		}

		// Add inline styles if present
		if ( ! empty( $component->styles ) ) {
			$attributes['style'] = WPBC_CSS_Helper::to_inline( $component->styles );
		}

		return WPBC_HTML_Helper::build_element(
			$tag,
			$attributes,
			$component->content
		);
	}

	/**
	 * Convert card component
	 *
	 * @param WPBC_Component $component Card component.
	 * @return string Card HTML.
	 */
	private function convert_card( WPBC_Component $component ): string {
		$classes = [ 'card' ];

		// Color variant
		$color = $component->get_attribute( 'color' );
		if ( $color ) {
			$classes[] = 'text-' . $color;
			$classes[] = 'bg-' . $color;
		}

		$card_html = '<div class="' . implode( ' ', $classes ) . '">';

		// Check for image child
		$image_url = $component->get_attribute( 'image_url' );
		if ( $image_url ) {
			$card_html .= '<img src="' . esc_url( $image_url ) . '" class="card-img-top" alt="">';
		}

		// Card body
		$card_html .= '<div class="card-body">';

		// Title
		$title = $component->get_attribute( 'title' ) ?: $component->get_attribute( 'heading' );
		if ( $title ) {
			$card_html .= '<h5 class="card-title">' . esc_html( $title ) . '</h5>';
		}

		// Content
		if ( ! empty( $component->content ) ) {
			$card_html .= '<p class="card-text">' . wp_kses_post( $component->content ) . '</p>';
		}

		// Convert children (like buttons)
		foreach ( $component->children as $child ) {
			$card_html .= $this->convert_component( $child );
		}

		$card_html .= '</div>'; // .card-body
		$card_html .= '</div>'; // .card

		return $card_html;
	}

	/**
	 * Convert container component
	 *
	 * @param WPBC_Component $component Container component.
	 * @return string Container HTML.
	 */
	private function convert_container( WPBC_Component $component ): string {
		$grid = $component->get_attribute( 'grid', [] );

		$classes = [];

		// Container type
		if ( isset( $grid['type'] ) ) {
			$classes[] = $grid['type'];
		} else {
			$classes[] = 'container';
		}

		// Additional classes
		$utilities = $component->get_attribute( 'utilities', [] );
		if ( ! empty( $utilities ) ) {
			foreach ( $utilities as $util_category ) {
				if ( is_array( $util_category ) ) {
					$classes = array_merge( $classes, $util_category );
				}
			}
		}

		$attributes = [
			'class' => implode( ' ', array_filter( $classes ) ),
		];

		// Add inline styles
		if ( ! empty( $component->styles ) ) {
			$attributes['style'] = WPBC_CSS_Helper::to_inline( $component->styles );
		}

		// Convert children
		$inner_html = '';
		foreach ( $component->children as $child ) {
			$inner_html .= $this->convert_component( $child );
		}

		return WPBC_HTML_Helper::build_element( 'div', $attributes, $inner_html );
	}

	/**
	 * Convert row component
	 *
	 * @param WPBC_Component $component Row component.
	 * @return string Row HTML.
	 */
	private function convert_row( WPBC_Component $component ): string {
		$classes = [ 'row' ];

		// Add utilities
		$utilities = $component->get_attribute( 'utilities', [] );
		if ( ! empty( $utilities ) ) {
			foreach ( $utilities as $util_category ) {
				if ( is_array( $util_category ) ) {
					$classes = array_merge( $classes, $util_category );
				}
			}
		}

		$attributes = [
			'class' => implode( ' ', $classes ),
		];

		// Convert children (columns)
		$inner_html = '';
		foreach ( $component->children as $child ) {
			$inner_html .= $this->convert_component( $child );
		}

		return WPBC_HTML_Helper::build_element( 'div', $attributes, $inner_html );
	}

	/**
	 * Convert column component
	 *
	 * @param WPBC_Component $component Column component.
	 * @return string Column HTML.
	 */
	private function convert_column( WPBC_Component $component ): string {
		$classes = [];

		// Grid configuration
		$grid = $component->get_attribute( 'grid', [] );

		if ( isset( $grid['breakpoints'] ) && is_array( $grid['breakpoints'] ) ) {
			foreach ( $grid['breakpoints'] as $breakpoint => $size ) {
				if ( $breakpoint === 'xs' ) {
					$classes[] = 'col-' . $size;
				} else {
					$classes[] = 'col-' . $breakpoint . '-' . $size;
				}
			}
		} else {
			// Default column
			$classes[] = 'col';
		}

		// Offset
		if ( isset( $grid['offsets'] ) && is_array( $grid['offsets'] ) ) {
			foreach ( $grid['offsets'] as $breakpoint => $offset ) {
				$classes[] = 'offset-' . $breakpoint . '-' . $offset;
			}
		}

		// Order
		if ( isset( $grid['order'] ) && is_array( $grid['order'] ) ) {
			foreach ( $grid['order'] as $breakpoint => $order ) {
				$classes[] = 'order-' . $breakpoint . '-' . $order;
			}
		}

		$attributes = [
			'class' => implode( ' ', $classes ),
		];

		// Convert children
		$inner_html = '';
		foreach ( $component->children as $child ) {
			$inner_html .= $this->convert_component( $child );
		}

		return WPBC_HTML_Helper::build_element( 'div', $attributes, $inner_html );
	}

	/**
	 * Convert heading component
	 *
	 * @param WPBC_Component $component Heading component.
	 * @return string Heading HTML.
	 */
	private function convert_heading( WPBC_Component $component ): string {
		$level = $component->get_attribute( 'level', 2 );
		$tag = 'h' . min( 6, max( 1, $level ) );

		$classes = [];

		// Alignment
		$alignment = $component->get_attribute( 'alignment' );
		if ( $alignment ) {
			$classes[] = 'text-' . $alignment;
		}

		// Color
		$color = $component->get_attribute( 'color' );
		if ( $color ) {
			$classes[] = 'text-' . $color;
		}

		$attributes = [];
		if ( ! empty( $classes ) ) {
			$attributes['class'] = implode( ' ', $classes );
		}

		// Add inline styles
		if ( ! empty( $component->styles ) ) {
			$attributes['style'] = WPBC_CSS_Helper::to_inline( $component->styles );
		}

		return WPBC_HTML_Helper::build_element(
			$tag,
			$attributes,
			esc_html( $component->content )
		);
	}

	/**
	 * Convert text/paragraph component
	 *
	 * @param WPBC_Component $component Text component.
	 * @return string Text HTML.
	 */
	private function convert_text( WPBC_Component $component ): string {
		$classes = [];

		// Alignment
		$alignment = $component->get_attribute( 'alignment' );
		if ( $alignment ) {
			$classes[] = 'text-' . $alignment;
		}

		// Color
		$color = $component->get_attribute( 'color' );
		if ( $color ) {
			$classes[] = 'text-' . $color;
		}

		$attributes = [];
		if ( ! empty( $classes ) ) {
			$attributes['class'] = implode( ' ', $classes );
		}

		// Add inline styles
		if ( ! empty( $component->styles ) ) {
			$attributes['style'] = WPBC_CSS_Helper::to_inline( $component->styles );
		}

		return WPBC_HTML_Helper::build_element(
			'p',
			$attributes,
			wp_kses_post( $component->content )
		);
	}

	/**
	 * Convert image component
	 *
	 * @param WPBC_Component $component Image component.
	 * @return string Image HTML.
	 */
	private function convert_image( WPBC_Component $component ): string {
		$classes = [ 'img-fluid' ]; // Responsive by default

		$attributes = [
			'src'   => $component->get_attribute( 'image_url', '' ),
			'alt'   => $component->get_attribute( 'alt_text', '' ),
			'class' => implode( ' ', $classes ),
		];

		// Title
		$title = $component->get_attribute( 'title' );
		if ( $title ) {
			$attributes['title'] = $title;
		}

		return WPBC_HTML_Helper::build_element( 'img', $attributes, '', true );
	}

	/**
	 * Convert alert component
	 *
	 * @param WPBC_Component $component Alert component.
	 * @return string Alert HTML.
	 */
	private function convert_alert( WPBC_Component $component ): string {
		$classes = [ 'alert' ];

		// Variant
		$variant = $component->get_attribute( 'variant', 'info' );
		$classes[] = 'alert-' . $variant;

		// Dismissible
		$dismissible = $component->get_attribute( 'dismissible', false );
		if ( $dismissible ) {
			$classes[] = 'alert-dismissible fade show';
		}

		$attributes = [
			'class' => implode( ' ', $classes ),
			'role'  => 'alert',
		];

		$html = '<div ' . $this->build_attributes_string( $attributes ) . '>';
		$html .= wp_kses_post( $component->content );

		if ( $dismissible ) {
			$html .= '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Convert divider component
	 *
	 * @param WPBC_Component $component Divider component.
	 * @return string Divider HTML.
	 */
	private function convert_divider( WPBC_Component $component ): string {
		$classes = [];

		// Add any utility classes
		$utilities = $component->get_attribute( 'utilities', [] );
		if ( ! empty( $utilities ) ) {
			foreach ( $utilities as $util_category ) {
				if ( is_array( $util_category ) ) {
					$classes = array_merge( $classes, $util_category );
				}
			}
		}

		$attributes = [];
		if ( ! empty( $classes ) ) {
			$attributes['class'] = implode( ' ', $classes );
		}

		return WPBC_HTML_Helper::build_element( 'hr', $attributes, '', true );
	}

	/**
	 * Generic component conversion
	 *
	 * @param WPBC_Component $component Component to convert.
	 * @return string HTML output.
	 */
	private function convert_generic( WPBC_Component $component ): string {
		$tag = $component->get_metadata( 'original_tag', 'div' );

		$attributes = [];

		// Add classes from metadata
		$original_classes = $component->get_metadata( 'original_classes', [] );
		if ( ! empty( $original_classes ) ) {
			$attributes['class'] = implode( ' ', $original_classes );
		}

		// Add inline styles
		if ( ! empty( $component->styles ) ) {
			$attributes['style'] = WPBC_CSS_Helper::to_inline( $component->styles );
		}

		// Convert children
		$inner_html = $component->content;
		foreach ( $component->children as $child ) {
			$inner_html .= $this->convert_component( $child );
		}

		return WPBC_HTML_Helper::build_element( $tag, $attributes, $inner_html );
	}

	/**
	 * Build attributes string from array
	 *
	 * @param array $attributes Attributes array.
	 * @return string Attributes string.
	 */
	private function build_attributes_string( array $attributes ): string {
		$parts = [];

		foreach ( $attributes as $key => $value ) {
			if ( is_bool( $value ) ) {
				if ( $value ) {
					$parts[] = $key;
				}
			} else {
				$parts[] = sprintf( '%s="%s"', $key, esc_attr( $value ) );
			}
		}

		return implode( ' ', $parts );
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
	 * Get supported component types
	 *
	 * @return array<string> Supported types.
	 */
	public function get_supported_types(): array {
		return [
			'button',
			'card',
			'alert',
			'container',
			'row',
			'column',
			'heading',
			'text',
			'image',
			'divider',
			'link',
			'badge',
			'breadcrumb',
			'pagination',
			'nav',
			'navbar',
			'modal',
			'accordion',
		];
	}

	/**
	 * Check if component type is supported
	 *
	 * @param string $type Component type.
	 * @return bool True if supported.
	 */
	public function supports_type( string $type ): bool {
		return in_array( $type, $this->get_supported_types(), true );
	}

	/**
	 * Get fallback HTML for unsupported component
	 *
	 * @param WPBC_Component $component Unsupported component.
	 * @return string Fallback HTML.
	 */
	public function get_fallback( WPBC_Component $component ): string {
		// Fallback to div with content
		return sprintf(
			'<div class="wpbc-fallback" data-original-type="%s">%s</div>',
			esc_attr( $component->type ),
			wp_kses_post( $component->content )
		);
	}
}
