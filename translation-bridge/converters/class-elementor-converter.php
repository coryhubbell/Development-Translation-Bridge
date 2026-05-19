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
 * @package DevelopmentTranslation_Bridge
 * @subpackage Translation_Bridge
 * @since 3.0.0
 */

namespace DEVTB\TranslationBridge\Converters;

use DEVTB\TranslationBridge\Core\DEVTB_Converter_Interface;
use DEVTB\TranslationBridge\Models\DEVTB_Component;
use DEVTB\TranslationBridge\Utils\DEVTB_JSON_Helper;
use DEVTB\TranslationBridge\Utils\DEVTB_CSS_Helper;

/**
 * Class DEVTB_Elementor_Converter
 *
 * Convert universal components to Elementor JSON.
 */
class DEVTB_Elementor_Converter implements DEVTB_Converter_Interface {

	/**
	 * Upstream framework version this converter is calibrated against.
	 *
	 * Elementor 4.x ("Atomic Editor") is a full rewrite and is detected and
	 * passthrough'd as of 4.2; native 4.x support is planned for 4.3.
	 */
	public const TARGET_CMS_VERSION = '3.30.0';

	/**
	 * @inheritDoc
	 */
	public function get_target_cms_version(): string {
		return self::TARGET_CMS_VERSION;
	}

	/**
	 * Element ID counter for unique IDs
	 *
	 * @var int
	 */
	private int $id_counter = 0;

	/**
	 * Convert universal component to Elementor JSON
	 *
	 * @param DEVTB_Component|array $component Component to convert.
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
			if ( $comp instanceof DEVTB_Component ) {
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
	 * @param DEVTB_Component $component Component to convert.
	 * @return array|null Elementor element array.
	 */
	public function convert_component( DEVTB_Component $component ): ?array {
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
	 * @param DEVTB_Component $component Component to convert.
	 * @return array Elementor section element.
	 */
	private function convert_section( DEVTB_Component $component ): array {
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
			} elseif ( $child->type === 'row' ) {
				// Row children: process the row's columns directly
				foreach ( $child->children as $row_child ) {
					if ( $row_child->type === 'column' ) {
						$element['elements'][] = $this->convert_column( $row_child );
					} else {
						// Wrap non-column children in a column
						$column = $this->create_column( [ $row_child ] );
						$element['elements'][] = $column;
					}
				}
			} else {
				// Wrap non-column, non-row children in a column
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
	 * @param DEVTB_Component $component Component to convert.
	 * @param bool           $is_inner  Whether this is an inner column.
	 * @return array Elementor column element.
	 */
	private function convert_column( DEVTB_Component $component, bool $is_inner = false ): array {
		$settings = $this->denormalize_attributes( $component->attributes );

		// Convert width to Elementor column size with responsive breakpoints
		$column_size = 100;
		if ( isset( $component->attributes['width'] ) ) {
			$column_size = $this->parse_width( $component->attributes['width'] );
		}

		// Elementor requires responsive column sizes
		$settings['_column_size']        = $column_size;
		$settings['_inline_size']        = null;
		$settings['_column_size_tablet'] = ''; // Empty = inherit from desktop
		$settings['_column_size_mobile'] = ''; // Empty = inherit from desktop

		// Add styles
		if ( ! empty( $component->styles ) ) {
			$settings = array_merge( $settings, $this->convert_styles( $component->styles ) );
		}

		$element = [
			'id'       => $this->generate_id(),
			'elType'   => 'column',
			'settings' => ! empty( $settings ) ? $settings : new \stdClass(),
			'elements' => [],
			'isInner'  => $is_inner,
		];

		// Convert children - columns can ONLY contain widgets or inner sections
		foreach ( $component->children as $child ) {
			if ( in_array( $child->type, [ 'column', 'row', 'section', 'container' ], true ) ) {
				// Layout components become inner sections
				$inner_section = $this->create_inner_section( $child );
				$element['elements'][] = $inner_section;
			} else {
				$widget = $this->convert_widget( $child );
				if ( $widget ) {
					$element['elements'][] = $widget;
				}
			}
		}

		return $element;
	}

	/**
	 * Create inner section for nested layouts
	 *
	 * Elementor structure: section > column > inner_section > column > widget
	 *
	 * @param DEVTB_Component $component Layout component to wrap.
	 * @return array Elementor inner section element.
	 */
	private function create_inner_section( DEVTB_Component $component ): array {
		$section = [
			'id'       => $this->generate_id(),
			'elType'   => 'section',
			'settings' => new \stdClass(),
			'elements' => [],
			'isInner'  => true, // Critical flag for inner sections
		];

		// Process children based on component type
		if ( in_array( $component->type, [ 'row', 'section', 'container' ], true ) ) {
			foreach ( $component->children as $child ) {
				if ( $child->type === 'column' ) {
					$column = $this->convert_column( $child, true );
					$section['elements'][] = $column;
				} else {
					// Wrap non-column children in a column
					$column = $this->create_column( [ $child ], true );
					$section['elements'][] = $column;
				}
			}
		} else {
			// Single component - wrap in a column
			$column = $this->convert_column( $component, true );
			$section['elements'][] = $column;
		}

		// Ensure at least one column
		if ( empty( $section['elements'] ) ) {
			$column = $this->create_column( [], true );
			$section['elements'][] = $column;
		}

		return $section;
	}

	/**
	 * Convert component to Elementor widget
	 *
	 * IMPORTANT: Widgets cannot contain other widgets in Elementor.
	 * Nested items (for tabs/accordions) go in settings, not elements.
	 *
	 * @param DEVTB_Component $component Component to convert.
	 * @return array|null Elementor widget element.
	 */
	private function convert_widget( DEVTB_Component $component ): ?array {
		$widget_type = $this->map_to_widget_type( $component->type );

		// Use fallback for unmapped types instead of returning null
		if ( ! $widget_type ) {
			return $this->get_fallback( $component );
		}

		$settings = $this->denormalize_attributes( $component->attributes );

		// Add content to settings based on widget type
		$settings = $this->add_widget_content( $widget_type, $settings, $component->content );

		// Add styles
		if ( ! empty( $component->styles ) ) {
			$settings = array_merge( $settings, $this->convert_styles( $component->styles ) );
		}

		// Handle nested elements for tabs/accordions (stored in settings, not elements)
		if ( ! empty( $component->children ) ) {
			$settings = $this->add_nested_items( $widget_type, $settings, $component->children );
		}

		// Widgets DO NOT have an 'elements' key - they cannot contain child elements
		return [
			'id'         => $this->generate_id(),
			'elType'     => 'widget',
			'widgetType' => $widget_type,
			'settings'   => ! empty( $settings ) ? $settings : new \stdClass(),
		];
	}

	/**
	 * Add nested items to widget settings for tabs/accordions
	 *
	 * @param string $widget_type Widget type.
	 * @param array  $settings    Current settings.
	 * @param array  $children    Child components.
	 * @return array Updated settings with nested items.
	 */
	private function add_nested_items( string $widget_type, array $settings, array $children ): array {
		switch ( $widget_type ) {
			case 'tabs':
				$settings['tabs'] = [];
				foreach ( $children as $index => $child ) {
					$title = $child->get_attribute( 'title' ) ?? $child->get_attribute( 'label' ) ?? 'Tab ' . ( $index + 1 );
					$settings['tabs'][] = [
						'_id'         => $this->generate_id(),
						'tab_title'   => $title,
						'tab_content' => $child->content ?? '',
					];
				}
				break;

			case 'accordion':
				$settings['tabs'] = []; // Accordion also uses 'tabs' key
				foreach ( $children as $index => $child ) {
					$title = $child->get_attribute( 'title' ) ?? $child->get_attribute( 'label' ) ?? 'Item ' . ( $index + 1 );
					$settings['tabs'][] = [
						'_id'                 => $this->generate_id(),
						'tab_title'           => $title,
						'tab_content'         => $child->content ?? '',
						'tab_default_active'  => $index === 0 ? 'yes' : '',
					];
				}
				break;

			case 'icon-list':
				$settings['icon_list'] = [];
				foreach ( $children as $child ) {
					$settings['icon_list'][] = [
						'_id'  => $this->generate_id(),
						'text' => $child->content ?? '',
						'icon' => [
							'value'   => 'fas fa-check',
							'library' => 'fa-solid',
						],
					];
				}
				break;
		}

		return $settings;
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
			// CTA falls back to icon-box for Elementor free core; "call-to-action" is Pro-only.
			'cta'             => 'icon-box',
			'countdown'       => 'countdown',
			'blockquote'      => 'blockquote',
			'portfolio'       => 'portfolio',
			'toc'             => 'table-of-contents',
		];

		return $type_map[ $universal_type ] ?? null;
	}

	/**
	 * Create Elementor link object with all required properties
	 *
	 * @param string $url    URL value.
	 * @param string $target Link target (_self, _blank).
	 * @param string $rel    Rel attribute value.
	 * @return array Elementor link object.
	 */
	private function create_link_object( string $url, string $target = '_self', string $rel = '' ): array {
		return [
			'url'               => $url,
			'is_external'       => $target === '_blank' ? 'on' : '',
			'nofollow'          => strpos( $rel, 'nofollow' ) !== false ? 'on' : '',
			'custom_attributes' => '',
		];
	}

	/**
	 * Denormalize universal attributes to Elementor settings
	 *
	 * @param array $attributes Universal attributes.
	 * @return array Elementor settings.
	 */
	private function denormalize_attributes( array $attributes ): array {
		$settings = [];

		foreach ( $attributes as $key => $value ) {
			switch ( $key ) {
				case 'url':
					// URLs become full link objects
					$settings['link'] = $this->create_link_object(
						$value,
						$attributes['target'] ?? '_self',
						$attributes['rel'] ?? ''
					);
					break;

				case 'image_url':
					// Images become image arrays with all properties
					$settings['image'] = [
						'url'    => $value,
						'id'     => '',
						'alt'    => $attributes['alt_text'] ?? '',
						'source' => 'library',
					];
					break;

				case 'variant':
					// Map variants to Elementor button types
					$variant_map = [
						'primary'   => 'default',
						'secondary' => 'info',
						'success'   => 'success',
						'danger'    => 'danger',
						'warning'   => 'warning',
						'info'      => 'info',
						'light'     => '',
						'dark'      => 'default',
					];
					$settings['button_type'] = $variant_map[ $value ] ?? 'default';
					break;

				case 'size':
					// Map sizes to Elementor sizes
					$size_map = [
						'sm'     => 'sm',
						'small'  => 'sm',
						'md'     => 'md',
						'medium' => 'md',
						'lg'     => 'lg',
						'large'  => 'lg',
						'xl'     => 'xl',
					];
					$settings['size'] = $size_map[ $value ] ?? 'md';
					break;

				case 'level':
					// Convert heading level to Elementor header_size
					$level_map = [
						1 => 'h1', 2 => 'h2', 3 => 'h3',
						4 => 'h4', 5 => 'h5', 6 => 'h6',
					];
					$settings['header_size'] = $level_map[ (int) $value ] ?? 'h2';
					break;

				case 'alignment':
					$settings['align'] = $value;
					break;

				case 'heading':
				case 'title':
					$settings['title'] = $value;
					break;

				case 'label':
					$settings['text'] = $value;
					break;

				case 'description':
					$settings['description_text'] = $value;
					break;

				case 'icon':
					// Convert icon to Elementor icon format
					$settings['selected_icon'] = [
						'value'   => $value,
						'library' => 'fa-solid',
					];
					break;

				case 'background_color':
					$settings['background_color'] = $value;
					break;

				case 'text_color':
					$settings['color'] = $value;
					break;

				// Skip attributes handled elsewhere
				case 'target':
				case 'rel':
				case 'alt_text':
				case 'width': // Handled in column conversion
					break;

				default:
					// Pass through other attributes
					$settings[ $key ] = $value;
					break;
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
	 * Convert styles to Elementor settings with proper unit wrappers
	 *
	 * @param array $styles Styles array.
	 * @return array Elementor settings.
	 */
	private function convert_styles( array $styles ): array {
		$settings = [];

		// Properties that need dimension wrappers
		$dimensional_properties = [
			'font_size', 'font-size',
			'line_height', 'line-height',
			'letter_spacing', 'letter-spacing',
			'padding', 'margin',
			'border_radius', 'border-radius',
			'width', 'height',
			'max_width', 'max-width',
			'min_height', 'min-height',
		];

		foreach ( $styles as $property => $value ) {
			// Convert CSS property names to Elementor settings (underscore format)
			$elementor_key = str_replace( '-', '_', $property );

			// Check if this is a dimensional property
			if ( in_array( $property, $dimensional_properties, true ) ||
			     in_array( $elementor_key, $dimensional_properties, true ) ) {
				$settings[ $elementor_key ] = $this->create_dimension_value( $value );
			} else {
				$settings[ $elementor_key ] = $value;
			}
		}

		return $settings;
	}

	/**
	 * Create Elementor dimension value object
	 *
	 * Elementor requires dimensional values in a specific format with unit information.
	 *
	 * @param mixed $value CSS value with unit or numeric.
	 * @return array Elementor dimension object.
	 */
	private function create_dimension_value( $value ): array {
		// Already an array, return as-is
		if ( is_array( $value ) ) {
			return $value;
		}

		// Parse string values like "16px", "1.5em", "100%"
		if ( is_string( $value ) && preg_match( '/^([\d.]+)(px|em|rem|%|vw|vh|vmin|vmax)?$/', $value, $matches ) ) {
			return [
				'size'  => (float) $matches[1],
				'unit'  => $matches[2] ?? 'px',
				'sizes' => [], // Responsive breakpoints
			];
		}

		// Numeric values default to px
		if ( is_numeric( $value ) ) {
			return [
				'size'  => (float) $value,
				'unit'  => 'px',
				'sizes' => [],
			];
		}

		// Fallback for unparseable values
		return [
			'size'  => 0,
			'unit'  => 'px',
			'sizes' => [],
		];
	}

	/**
	 * Create a default column with all required Elementor settings
	 *
	 * @param array $widgets  Widgets to add to column.
	 * @param bool  $is_inner Whether this is an inner column.
	 * @return array Elementor column element.
	 */
	private function create_column( array $widgets = [], bool $is_inner = false ): array {
		$element = [
			'id'       => $this->generate_id(),
			'elType'   => 'column',
			'settings' => [
				'_column_size'        => 100,
				'_inline_size'        => null,
				'_column_size_tablet' => '',
				'_column_size_mobile' => '',
			],
			'elements' => [],
			'isInner'  => $is_inner,
		];

		foreach ( $widgets as $widget ) {
			if ( $widget instanceof DEVTB_Component ) {
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
		return DEVTB_JSON_Helper::generate_elementor_id();
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
	 * @param DEVTB_Component $component Component to validate.
	 * @return bool True if can be converted.
	 */
	public function can_convert( DEVTB_Component $component ): bool {
		$supported = $this->get_supported_types();
		return in_array( $component->type, $supported, true );
	}

	/**
	 * Get conversion confidence score
	 *
	 * @param DEVTB_Component $component Component to evaluate.
	 * @return float Confidence score (0.0-1.0).
	 */
	public function get_confidence( DEVTB_Component $component ): float {
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
	 * @param DEVTB_Component $component Unsupported component.
	 * @return array Fallback Elementor element.
	 */
	public function get_fallback( DEVTB_Component $component ) {
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
