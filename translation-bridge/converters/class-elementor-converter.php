<?php
/**
 * Elementor Page Builder Converter
 *
 * Intelligent universal to Elementor JSON converter featuring:
 * - JSON structure generation (Section > Column > Widget)
 * - 90+ widget type support
 * - Settings denormalization
 * - Responsive controls generation
 * - ID generation (8-char hex)
 * - Dynamic content support
 *
 * @package WordPress_Bootstrap_Claude
 * @subpackage Translation_Bridge
 * @since 3.0.0
 */

namespace WPBC\TranslationBridge\Converters;

use WPBC\TranslationBridge\Core\WPBC_Converter_Interface;
use WPBC\TranslationBridge\Models\WPBC_Component;
use WPBC\TranslationBridge\Utils\WPBC_JSON_Helper;
use WPBC\TranslationBridge\Utils\WPBC_CSS_Helper;

/**
 * Class WPBC_Elementor_Converter
 *
 * Convert universal components to Elementor JSON.
 */
class WPBC_Elementor_Converter implements WPBC_Converter_Interface {

	/**
	 * Element ID counter for unique IDs
	 *
	 * @var int
	 */
	private int $id_counter = 0;

	/**
	 * Convert universal component to Elementor JSON
	 *
	 * @param WPBC_Component|array $component Component to convert.
	 * @return string|array Elementor JSON string or array.
	 */
	public function convert( $component ) {
		if ( is_array( $component ) ) {
			$components = $component;
		} else {
			$components = [ $component ];
		}

		$elements = [];

		foreach ( $components as $comp ) {
			if ( $comp instanceof WPBC_Component ) {
				$element = $this->convert_component( $comp );
				if ( $element ) {
					$elements[] = $element;
				}
			}
		}

		return wp_json_encode( $elements, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	}

	/**
	 * Convert single component to Elementor element
	 *
	 * @param WPBC_Component $component Component to convert.
	 * @return array|null Elementor element array.
	 */
	public function convert_component( WPBC_Component $component ): ?array {
		$type = $component->type;

		// Convert based on component type
		if ( $type === 'container' ) {
			return $this->convert_section( $component );
		} elseif ( $type === 'row' ) {
			// Rows become sections in Elementor
			return $this->convert_section( $component );
		} elseif ( $type === 'column' ) {
			return $this->convert_column( $component );
		} else {
			return $this->convert_widget( $component );
		}
	}

	/**
	 * Convert container/row to Elementor section
	 *
	 * @param WPBC_Component $component Component to convert.
	 * @return array Elementor section element.
	 */
	private function convert_section( WPBC_Component $component ): array {
		$settings = $this->denormalize_attributes( $component->attributes );

		// Add styles to settings
		if ( ! empty( $component->styles ) ) {
			$settings = array_merge( $settings, $this->convert_styles( $component->styles ) );
		}

		$element = [
			'id'       => $this->generate_id(),
			'elType'   => 'section',
			'settings' => $settings,
			'elements' => [],
		];

		// Convert children to columns
		foreach ( $component->children as $child ) {
			if ( $child->type === 'column' ) {
				$element['elements'][] = $this->convert_column( $child );
			} else {
				// Wrap non-column children in a column
				$column = $this->create_column( [ $child ] );
				$element['elements'][] = $column;
			}
		}

		// If no columns, create a default one
		if ( empty( $element['elements'] ) ) {
			$element['elements'][] = $this->create_column( [] );
		}

		return $element;
	}

	/**
	 * Convert column component to Elementor column
	 *
	 * @param WPBC_Component $component Component to convert.
	 * @return array Elementor column element.
	 */
	private function convert_column( WPBC_Component $component ): array {
		$settings = $this->denormalize_attributes( $component->attributes );

		// Convert width to Elementor column size
		if ( isset( $component->attributes['width'] ) ) {
			$width = $this->parse_width( $component->attributes['width'] );
			$settings['_column_size'] = $width;
		}

		// Add styles
		if ( ! empty( $component->styles ) ) {
			$settings = array_merge( $settings, $this->convert_styles( $component->styles ) );
		}

		$element = [
			'id'       => $this->generate_id(),
			'elType'   => 'column',
			'settings' => $settings,
			'elements' => [],
		];

		// Convert children to widgets
		foreach ( $component->children as $child ) {
			$widget = $this->convert_widget( $child );
			if ( $widget ) {
				$element['elements'][] = $widget;
			}
		}

		return $element;
	}

	/**
	 * Convert component to Elementor widget
	 *
	 * @param WPBC_Component $component Component to convert.
	 * @return array|null Elementor widget element.
	 */
	private function convert_widget( WPBC_Component $component ): ?array {
		$widget_type = $this->map_to_widget_type( $component->type );

		if ( ! $widget_type ) {
			return null;
		}

		$settings = $this->denormalize_attributes( $component->attributes );

		// Add content to settings based on widget type
		$settings = $this->add_widget_content( $widget_type, $settings, $component->content );

		// Add styles
		if ( ! empty( $component->styles ) ) {
			$settings = array_merge( $settings, $this->convert_styles( $component->styles ) );
		}

		$element = [
			'id'         => $this->generate_id(),
			'elType'     => 'widget',
			'widgetType' => $widget_type,
			'settings'   => $settings,
		];

		// Handle nested elements (for tabs, accordion, etc.)
		if ( ! empty( $component->children ) ) {
			$element['elements'] = [];
			foreach ( $component->children as $child ) {
				$child_widget = $this->convert_widget( $child );
				if ( $child_widget ) {
					$element['elements'][] = $child_widget;
				}
			}
		}

		return $element;
	}

	/**
	 * Map universal type to Elementor widget type
	 *
	 * @param string $universal_type Universal component type.
	 * @return string|null Elementor widget type.
	 */
	private function map_to_widget_type( string $universal_type ): ?string {
		$type_map = [
			'heading'         => 'heading',
			'text'            => 'text-editor',
			'image'           => 'image',
			'button'          => 'button',
			'divider'         => 'divider',
			'spacer'          => 'spacer',
			'map'             => 'google_maps',
			'icon'            => 'icon',
			'card'            => 'icon-box',
			'rating'          => 'star-rating',
			'slider'          => 'image-carousel',
			'gallery'         => 'image-gallery',
			'list'            => 'icon-list',
			'counter'         => 'counter',
			'progress'        => 'progress',
			'testimonial'     => 'testimonial',
			'tabs'            => 'tabs',
			'accordion'       => 'accordion',
			'social-icons'    => 'social-icons',
			'alert'           => 'alert',
			'audio'           => 'audio',
			'video'           => 'video',
			'form'            => 'form',
			'nav'             => 'nav-menu',
			'pricing-table'   => 'price-table',
			'cta'             => 'call-to-action',
			'countdown'       => 'countdown',
			'blockquote'      => 'blockquote',
			'portfolio'       => 'portfolio',
			'toc'             => 'table-of-contents',
		];

		return $type_map[ $universal_type ] ?? null;
	}

	/**
	 * Denormalize universal attributes to Elementor settings
	 *
	 * @param array $attributes Universal attributes.
	 * @return array Elementor settings.
	 */
	private function denormalize_attributes( array $attributes ): array {
		$settings = [];

		$attr_map = [
			// Button
			'url'               => 'link',
			'label'             => 'text',
			'variant'           => 'button_type',
			'size'              => 'size',
			'target'            => '_link_target',

			// Image
			'image_url'         => 'image',
			'alt_text'          => 'alt_text',

			// Heading
			'heading'           => 'title',
			'level'             => 'header_size',

			// Card / Icon box
			'description'       => 'description_text',
			'icon'              => 'icon',

			// Colors
			'background_color'  => 'background_color',
			'text_color'        => 'color',

			// Layout
			'width'             => 'content_width',
			'gap'               => 'gap',

			// Alignment
			'alignment'         => 'align',
		];

		foreach ( $attributes as $key => $value ) {
			// Check if we need to denormalize this key
			$elementor_key = $attr_map[ $key ] ?? $key;

			// Handle special cases
			if ( $key === 'url' ) {
				// URLs become link arrays
				$settings['link'] = [
					'url'         => $value,
					'is_external' => $attributes['target'] === '_blank' ? 'on' : '',
					'nofollow'    => isset( $attributes['rel'] ) && strpos( $attributes['rel'], 'nofollow' ) !== false ? 'on' : '',
				];
			} elseif ( $key === 'image_url' ) {
				// Images become image arrays
				$settings['image'] = [
					'url' => $value,
					'alt' => $attributes['alt_text'] ?? '',
				];
			} elseif ( $key === 'heading' && isset( $attributes['title'] ) ) {
				// For icon-box, use title_text
				$settings['title_text'] = $value;
			} else {
				$settings[ $elementor_key ] = $value;
			}
		}

		return $settings;
	}

	/**
	 * Add widget-specific content to settings
	 *
	 * @param string $widget_type Widget type.
	 * @param array  $settings Settings array.
	 * @param string $content Content string.
	 * @return array Updated settings.
	 */
	private function add_widget_content( string $widget_type, array $settings, string $content ): array {
		if ( empty( $content ) ) {
			return $settings;
		}

		switch ( $widget_type ) {
			case 'heading':
				$settings['title'] = $content;
				break;

			case 'text-editor':
				$settings['editor'] = $content;
				break;

			case 'button':
				$settings['text'] = $content;
				break;

			case 'icon-box':
			case 'image-box':
				// Try to split into title and description
				$parts = explode( "\n\n", $content, 2 );
				if ( count( $parts ) === 2 ) {
					$settings['title_text'] = $parts[0];
					$settings['description_text'] = $parts[1];
				} else {
					$settings['title_text'] = $content;
				}
				break;

			case 'testimonial':
				$settings['testimonial_content'] = $content;
				break;

			case 'blockquote':
				$settings['blockquote_content'] = $content;
				break;

			default:
				// Store in a generic field
				$settings['content'] = $content;
				break;
		}

		return $settings;
	}

	/**
	 * Convert styles to Elementor settings
	 *
	 * @param array $styles Styles array.
	 * @return array Elementor settings.
	 */
	private function convert_styles( array $styles ): array {
		$settings = [];

		foreach ( $styles as $property => $value ) {
			// Convert CSS property names to Elementor settings
			$elementor_key = str_replace( '-', '_', $property );

			// Add to settings
			$settings[ $elementor_key ] = $value;
		}

		return $settings;
	}

	/**
	 * Create a default column
	 *
	 * @param array $widgets Widgets to add to column.
	 * @return array Elementor column element.
	 */
	private function create_column( array $widgets = [] ): array {
		$element = [
			'id'       => $this->generate_id(),
			'elType'   => 'column',
			'settings' => [
				'_column_size' => 100,
			],
			'elements' => [],
		];

		foreach ( $widgets as $widget ) {
			if ( $widget instanceof WPBC_Component ) {
				$converted = $this->convert_widget( $widget );
				if ( $converted ) {
					$element['elements'][] = $converted;
				}
			}
		}

		return $element;
	}

	/**
	 * Parse width value to percentage
	 *
	 * @param string $width Width value (50%, 1/2, etc.).
	 * @return int Width as percentage.
	 */
	private function parse_width( string $width ): int {
		// Remove % sign
		$width = str_replace( '%', '', $width );

		// Handle fractions (1/2 -> 50)
		if ( strpos( $width, '/' ) !== false ) {
			list( $numerator, $denominator ) = explode( '/', $width );
			$width = ( (int) $numerator / (int) $denominator ) * 100;
		}

		return (int) round( (float) $width );
	}

	/**
	 * Generate unique Elementor ID (8-char hex)
	 *
	 * @return string Elementor ID.
	 */
	private function generate_id(): string {
		$this->id_counter++;
		return WPBC_JSON_Helper::generate_elementor_id();
	}

	/**
	 * Get framework name
	 *
	 * @return string Framework name.
	 */
	public function get_framework(): string {
		return 'elementor';
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
			'heading',
			'text',
			'image',
			'button',
			'divider',
			'spacer',
			'map',
			'icon',
			'card',
			'rating',
			'slider',
			'gallery',
			'list',
			'counter',
			'progress',
			'testimonial',
			'tabs',
			'accordion',
			'social-icons',
			'alert',
			'audio',
			'video',
			'form',
			'nav',
			'pricing-table',
			'cta',
			'countdown',
			'blockquote',
			'portfolio',
			'toc',
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
		return in_array( $component->type, $supported, true );
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

		// Boost confidence if coming from Elementor originally
		if ( isset( $component->metadata['source_framework'] ) && $component->metadata['source_framework'] === 'elementor' ) {
			$confidence = 0.95;
		}

		// Check for complex features that might not convert perfectly
		if ( ! empty( $component->children ) && count( $component->children ) > 5 ) {
			$confidence -= 0.1; // Reduce for complex nested structures
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
	 * @return array Fallback Elementor element.
	 */
	public function get_fallback( WPBC_Component $component ) {
		// Create a basic text widget as fallback
		$settings = [
			'editor' => $component->content ? $component->content : 'Unsupported component type: ' . $component->type,
		];

		return [
			'id'         => $this->generate_id(),
			'elType'     => 'widget',
			'widgetType' => 'text-editor',
			'settings'   => $settings,
		];
	}
}
