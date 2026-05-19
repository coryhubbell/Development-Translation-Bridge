<?php
/**
 * Oxygen Builder Converter
 *
 * Intelligent universal to Oxygen Builder converter featuring:
 * - JSON element generation
 * - Element hierarchy (parent-child via ct_parent)
 * - 30+ element type support
 * - Style generation in 'original' property
 * - Unique ID generation
 *
 * @package DevelopmentTranslation_Bridge
 * @subpackage Translation_Bridge
 * @since 3.2.0
 */

namespace DEVTB\TranslationBridge\Converters;

use DEVTB\TranslationBridge\Core\DEVTB_Converter_Interface;
use DEVTB\TranslationBridge\Models\DEVTB_Component;
use DEVTB\TranslationBridge\Utils\DEVTB_JSON_Helper;
use DEVTB\TranslationBridge\Utils\DEVTB_CSS_Helper;

/**
 * Class DEVTB_Oxygen_Converter
 *
 * Convert universal components to Oxygen Builder JSON.
 */
class DEVTB_Oxygen_Converter implements DEVTB_Converter_Interface {

	/**
	 * Upstream framework version this converter is calibrated against.
	 *
	 * The converter emits the legacy Oxygen 4.x JSON schema (`ct_*` element vocabulary,
	 * `id`/`ct_id`/`ct_parent` linkage). Oxygen 6 is a ground-up rewrite with a new
	 * schema; native Oxygen 6 support is planned for Translation Bridge 4.3.
	 */
	public const TARGET_CMS_VERSION = '4.8.3';

	/**
	 * @inheritDoc
	 */
	public function get_target_cms_version(): string {
		return self::TARGET_CMS_VERSION;
	}

	/**
	 * Element ID counter
	 *
	 * @var int
	 */
	private int $id_counter = 0;

	/**
	 * All elements array (flat structure)
	 *
	 * @var array
	 */
	private array $elements = [];

	/**
	 * Convert universal component to Oxygen Builder JSON
	 *
	 * @param DEVTB_Component|array $component Component to convert.
	 * @return string Oxygen JSON string.
	 */
	public function convert( $component ) {
		// Reset for new conversion
		$this->elements = [];
		$this->id_counter = 0;

		if ( is_array( $component ) ) {
			$components = $component;
		} else {
			$components = [ $component ];
		}

		// Convert each component to Oxygen elements
		foreach ( $components as $comp ) {
			if ( $comp instanceof DEVTB_Component ) {
				$this->convert_component( $comp, 0 );
			}
		}

		// Return JSON encoded elements
		return wp_json_encode( $this->elements, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	}

	/**
	 * Convert single component to Oxygen element(s)
	 *
	 * @param DEVTB_Component $component Component to convert.
	 * @param int            $parent_id Parent element ID.
	 * @return int Element ID of created element.
	 */
	public function convert_component( DEVTB_Component $component, int $parent_id = 0 ) {
		$type = $component->type;

		// Map universal type to Oxygen element
		$element_name = $this->map_to_element_type( $type );

		if ( ! $element_name ) {
			// Default to div block
			$element_name = 'ct_div_block';
		}

		// Generate element ID
		$element_id = ++$this->id_counter;

		// Create element options
		$options = $this->create_element_options( $component, $element_id, $parent_id );

		// Add content to options if applicable
		if ( ! empty( $component->content ) ) {
			$options = $this->add_element_content( $element_name, $options, $component->content );
		}

		// Create element
		$element = [
			'id'      => $element_id,
			'name'    => $element_name,
			'options' => $options,
		];

		$this->elements[] = $element;

		// Convert children
		foreach ( $component->children as $child ) {
			$this->convert_component( $child, $element_id );
		}

		return $element_id;
	}

	/**
	 * Map universal type to Oxygen element type
	 *
	 * @param string $universal_type Universal type.
	 * @return string|null Oxygen element name.
	 */
	private function map_to_element_type( string $universal_type ): ?string {
		$type_map = [
			'container'      => 'ct_div_block',
			'row'            => 'ct_section',
			'column'         => 'ct_div_block',
			'text'           => 'ct_text_block',
			'heading'        => 'ct_headline',
			'link'           => 'ct_link_text',
			'button'         => 'ct_link_button',
			'image'          => 'ct_image',
			'icon'           => 'ct_fancy_icon',
			'video'          => 'ct_video',
			'audio'          => 'ct_audio',
			'code'           => 'ct_code_block',
			'divider'        => 'ct_separator',
			'slider'         => 'ct_slider',
			'slide'          => 'ct_slide',
			'progress'       => 'ct_progress_bar',
			'testimonial'    => 'ct_testimonial',
			'pricing-table'  => 'ct_pricing_box',
			'map'            => 'ct_google_map',
			'tabs'           => 'ct_tabs',
			'tab'            => 'ct_tab',
			'accordion'      => 'ct_accordion',
			'toggle'         => 'ct_toggle',
			'menu'           => 'ct_nav_menu',
			'posts'          => 'oxy_posts_grid',
			'gallery'        => 'oxy_gallery',
		];

		return $type_map[ $universal_type ] ?? null;
	}

	/**
	 * Create element options object
	 *
	 * @param DEVTB_Component $component Component.
	 * @param int            $element_id Element ID.
	 * @param int            $parent_id Parent ID.
	 * @return array Options object.
	 */
	private function create_element_options( DEVTB_Component $component, int $element_id, int $parent_id ): array {
		$options = [
			'ct_id'     => $element_id,
			'ct_parent' => $parent_id,
			'selector'  => $this->generate_selector( $component->type, $element_id ),
			'nicename'  => ucfirst( $component->type ) . ' (#' . $element_id . ')',
		];

		// Denormalize attributes to Oxygen options
		$oxygen_options = $this->denormalize_attributes( $component->attributes );
		$options = array_merge( $options, $oxygen_options );

		// Add styles to 'original' property
		if ( ! empty( $component->attributes ) || ! empty( $component->styles ) ) {
			$options['original'] = $this->create_original_styles( $component->attributes, $component->styles );
		}

		return $options;
	}

	/**
	 * Generate selector for element
	 *
	 * @param string $type Element type.
	 * @param int    $id Element ID.
	 * @return string Selector.
	 */
	private function generate_selector( string $type, int $id ): string {
		return $type . '-' . $id . '-' . time();
	}

	/**
	 * Denormalize universal attributes to Oxygen options
	 *
	 * @param array $attributes Universal attributes.
	 * @return array Oxygen options.
	 */
	private function denormalize_attributes( array $attributes ): array {
		$options = [];

		// Link URL
		if ( isset( $attributes['href'] ) ) {
			$options['url'] = $attributes['href'];
		}

		// Link target
		if ( isset( $attributes['target'] ) ) {
			$options['target'] = $attributes['target'];
		}

		// Image source
		if ( isset( $attributes['src'] ) ) {
			$options['src'] = $attributes['src'];
		}

		// Alt text
		if ( isset( $attributes['alt'] ) ) {
			$options['alt'] = $attributes['alt'];
		}

		// Heading level
		if ( isset( $attributes['level'] ) ) {
			$level = intval( $attributes['level'] );
			$options['tag'] = 'h' . $level;
		}

		// ID and classes
		if ( isset( $attributes['id'] ) ) {
			$options['id'] = $attributes['id'];
		}

		if ( isset( $attributes['class'] ) ) {
			$options['classes'] = is_string( $attributes['class'] )
				? explode( ' ', $attributes['class'] )
				: $attributes['class'];
		}

		return $options;
	}

	/**
	 * Create 'original' styles object
	 *
	 * @param array $attributes Attributes.
	 * @param array $styles Styles.
	 * @return array Original styles object.
	 */
	private function create_original_styles( array $attributes, array $styles = [] ): array {
		$original = [];

		// Merge attributes and styles
		$all_styles = array_merge( $attributes, $styles );

		// Map CSS properties to Oxygen properties
		$property_map = [
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

		foreach ( $property_map as $css_prop => $oxygen_prop ) {
			if ( isset( $all_styles[ $css_prop ] ) ) {
				$original[ $oxygen_prop ] = $all_styles[ $css_prop ];
			}
		}

		return $original;
	}

	/**
	 * Add content to element options
	 *
	 * @param string $element_name Element name.
	 * @param array  $options Current options.
	 * @param string $content Content to add.
	 * @return array Updated options.
	 */
	private function add_element_content( string $element_name, array $options, string $content ): array {
		switch ( $element_name ) {
			case 'ct_headline':
				$options['headline_text'] = $content;
				$options['ct_content'] = $content;
				break;

			case 'ct_text_block':
				$options['text'] = $content;
				$options['ct_content'] = $content;
				break;

			case 'ct_link_text':
			case 'ct_link_button':
				$options['text'] = $content;
				$options['ct_content'] = $content;
				break;

			case 'ct_code_block':
				$options['code'] = $content;
				$options['ct_content'] = $content;
				break;

			default:
				$options['ct_content'] = $content;
				break;
		}

		return $options;
	}

	/**
	 * Validate that content can be converted
	 *
	 * @param DEVTB_Component|array $component Component to validate.
	 * @return bool True if valid.
	 */
	public function validate( $component ): bool {
		if ( is_array( $component ) ) {
			foreach ( $component as $comp ) {
				if ( ! $comp instanceof DEVTB_Component ) {
					return false;
				}
			}
			return true;
		}

		return $component instanceof DEVTB_Component;
	}

	/**
	 * Get framework name
	 */
	public function get_framework(): string {
		return 'oxygen';
	}

	/**
	 * Get supported types
	 */
	public function get_supported_types(): array {
		return [ 'section', 'div', 'heading', 'text', 'button', 'image', 'link', 'code' ];
	}

	/**
	 * Check if type is supported
	 */
	public function supports_type( string $type ): bool {
		return in_array( $type, $this->get_supported_types(), true );
	}

	/**
	 * Get fallback conversion
	 */
	public function get_fallback( DEVTB_Component $component ) {
		// Return as code block for unsupported types.
		// Bug fix (4.2): was referencing $this->current_id which doesn't exist; the class
		// declares $id_counter.
		return [
			'id' => ++$this->id_counter,
			'name' => 'ct_code_block',
			'options' => [
				'code' => $component->content ?? '',
			],
			'children' => [],
		];
	}
}
