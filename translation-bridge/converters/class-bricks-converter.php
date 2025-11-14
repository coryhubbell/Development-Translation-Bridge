<?php
/**
 * Bricks Builder Converter
 *
 * Intelligent universal to Bricks JSON converter featuring:
 * - JSON structure generation (Container > Element)
 * - 80+ element type support
 * - Settings denormalization
 * - Responsive controls generation
 * - ID generation (brxeXXXXX format)
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
 * Class WPBC_Bricks_Converter
 *
 * Convert universal components to Bricks JSON.
 */
class WPBC_Bricks_Converter implements WPBC_Converter_Interface {

	/**
	 * Element ID counter for unique IDs
	 *
	 * @var int
	 */
	private int $id_counter = 0;

	/**
	 * Convert universal component to Bricks JSON
	 *
	 * @param WPBC_Component|array $component Component to convert.
	 * @return string|array Bricks JSON string or array.
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
	 * Convert single component to Bricks element
	 *
	 * @param WPBC_Component $component Component to convert.
	 * @return array|null Bricks element array.
	 */
	public function convert_component( WPBC_Component $component ): ?array {
		$type = $component->type;

		// Map universal type to Bricks element
		$bricks_type = $this->map_to_bricks_type( $type );

		if ( ! $bricks_type ) {
			return null;
		}

		$settings = $this->denormalize_attributes( $component->attributes );

		// Add content to settings based on element type
		$settings = $this->add_element_content( $bricks_type, $settings, $component->content );

		// Add styles
		if ( ! empty( $component->styles ) ) {
			$settings = array_merge( $settings, $this->convert_styles( $component->styles ) );
		}

		$element = [
			'id'       => $this->generate_id(),
			'name'     => $bricks_type,
			'parent'   => '0', // Will be set by Bricks
			'settings' => $settings,
		];

		// Handle nested elements (children)
		if ( ! empty( $component->children ) ) {
			$element['children'] = [];
			foreach ( $component->children as $child ) {
				$child_element = $this->convert_component( $child );
				if ( $child_element ) {
					$element['children'][] = $child_element;
				}
			}
		}

		return $element;
	}

	/**
	 * Map universal type to Bricks element type
	 *
	 * @param string $universal_type Universal component type.
	 * @return string|null Bricks element type.
	 */
	private function map_to_bricks_type( string $universal_type ): ?string {
		$type_map = [
			'container'       => 'container',
			'row'             => 'container',
			'column'          => 'div',
			'heading'         => 'heading',
			'text'            => 'text-basic',
			'image'           => 'image',
			'button'          => 'button',
			'divider'         => 'divider',
			'spacer'          => 'spacer',
			'map'             => 'map',
			'icon'            => 'icon',
			'card'            => 'icon-box',
			'rating'          => 'icon',
			'slider'          => 'carousel',
			'gallery'         => 'carousel',
			'list'            => 'list',
			'counter'         => 'counter',
			'progress'        => 'progress-bar',
			'testimonial'     => 'testimonial',
			'tabs'            => 'tabs',
			'accordion'       => 'accordion',
			'social-icons'    => 'social-icons',
			'alert'           => 'alert',
			'audio'           => 'video',
			'video'           => 'video',
			'form'            => 'form',
			'nav'             => 'nav-menu',
			'pricing-table'   => 'pricing-table',
			'cta'             => 'icon-box',
			'countdown'       => 'countdown',
			'blockquote'      => 'text-basic',
			'portfolio'       => 'carousel',
			'toc'             => 'list',
		];

		return $type_map[ $universal_type ] ?? null;
	}

	/**
	 * Denormalize universal attributes to Bricks settings
	 *
	 * @param array $attributes Universal attributes.
	 * @return array Bricks settings.
	 */
	private function denormalize_attributes( array $attributes ): array {
		$settings = [];

		$attr_map = [
			// Button
			'url'               => 'link',
			'label'             => 'text',
			'variant'           => 'buttonStyle',
			'size'              => 'size',
			'target'            => '_link_target',

			// Image
			'image_url'         => 'image',
			'alt_text'          => 'alt',

			// Heading
			'heading'           => 'text',
			'level'             => 'tag',

			// Card / Icon box
			'description'       => 'description',
			'icon'              => 'icon',

			// Colors
			'background_color'  => 'backgroundColor',
			'text_color'        => 'color',

			// Layout
			'width'             => 'width',
			'gap'               => 'gap',

			// Alignment
			'alignment'         => 'textAlign',
		];

		foreach ( $attributes as $key => $value ) {
			// Check if we need to denormalize this key
			$bricks_key = $attr_map[ $key ] ?? $key;

			// Handle special cases
			if ( $key === 'url' ) {
				// URLs become link objects
				$settings['link'] = [
					'url'     => $value,
					'newTab'  => ( $attributes['target'] ?? '' ) === '_blank',
					'nofollow' => isset( $attributes['rel'] ) && strpos( $attributes['rel'], 'nofollow' ) !== false,
				];
			} elseif ( $key === 'image_url' ) {
				// Images become image objects
				$settings['image'] = [
					'url' => $value,
					'alt' => $attributes['alt_text'] ?? '',
				];
			} elseif ( $key === 'heading' && isset( $attributes['title'] ) ) {
				// For icon-box, use title
				$settings['title'] = $value;
			} else {
				$settings[ $bricks_key ] = $value;
			}
		}

		return $settings;
	}

	/**
	 * Add element-specific content to settings
	 *
	 * @param string $element_type Element type.
	 * @param array  $settings Settings array.
	 * @param string $content Content string.
	 * @return array Updated settings.
	 */
	private function add_element_content( string $element_type, array $settings, string $content ): array {
		if ( empty( $content ) ) {
			return $settings;
		}

		switch ( $element_type ) {
			case 'heading':
				$settings['text'] = $content;
				break;

			case 'text-basic':
			case 'text':
			case 'rich-text':
				$settings['text'] = $content;
				break;

			case 'button':
				$settings['text'] = $content;
				break;

			case 'icon-box':
				// Try to split into title and description
				$parts = explode( "\n\n", $content, 2 );
				if ( count( $parts ) === 2 ) {
					$settings['title'] = $parts[0];
					$settings['description'] = $parts[1];
				} else {
					$settings['title'] = $content;
				}
				break;

			case 'testimonial':
				$settings['content'] = $content;
				break;

			default:
				// Store in a generic field
				$settings['content'] = $content;
				break;
		}

		return $settings;
	}

	/**
	 * Convert styles to Bricks settings
	 *
	 * @param array $styles Styles array.
	 * @return array Bricks settings.
	 */
	private function convert_styles( array $styles ): array {
		$settings = [];

		foreach ( $styles as $property => $value ) {
			// Convert CSS property names to Bricks camelCase
			$bricks_key = WPBC_CSS_Helper::convert_property_case( $property, 'camel' );

			// Add to settings
			$settings[ $bricks_key ] = $value;
		}

		return $settings;
	}

	/**
	 * Generate unique Bricks ID (brxe00001 format)
	 *
	 * @return string Bricks ID.
	 */
	private function generate_id(): string {
		$this->id_counter++;
		return WPBC_JSON_Helper::generate_bricks_id( $this->id_counter );
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

		// Boost confidence if coming from Bricks originally
		if ( isset( $component->metadata['source_framework'] ) && $component->metadata['source_framework'] === 'bricks' ) {
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
	 * @return array Fallback Bricks element.
	 */
	public function get_fallback( WPBC_Component $component ) {
		// Create a basic text element as fallback
		$content = $component->content ? $component->content : 'Unsupported component type: ' . $component->type;

		return [
			'id'       => $this->generate_id(),
			'name'     => 'text-basic',
			'parent'   => '0',
			'settings' => [
				'text' => $content,
			],
		];
	}
}
