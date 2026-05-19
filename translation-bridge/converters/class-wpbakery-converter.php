<?php
/**
 * WPBakery Page Builder Converter
 *
 * Intelligent universal to WPBakery shortcode converter featuring:
 * - Hierarchical shortcode generation (Row > Column > Element)
 * - 50+ element type support
 * - Parameter denormalization
 * - Responsive settings generation
 * - Animation and styling support
 * - Visual Composer compatibility
 *
 * @package DevelopmentTranslation_Bridge
 * @subpackage Translation_Bridge
 * @since 3.0.0
 */

namespace DEVTB\TranslationBridge\Converters;

use DEVTB\TranslationBridge\Core\DEVTB_Converter_Interface;
use DEVTB\TranslationBridge\Models\DEVTB_Component;
use DEVTB\TranslationBridge\Utils\DEVTB_Shortcode_Helper;
use DEVTB\TranslationBridge\Utils\DEVTB_CSS_Helper;

/**
 * Class DEVTB_WPBakery_Converter
 *
 * Convert universal components to WPBakery Page Builder shortcodes.
 */
class DEVTB_WPBakery_Converter implements DEVTB_Converter_Interface {

	/**
	 * Upstream framework version this converter is calibrated against.
	 */
	public const TARGET_CMS_VERSION = '8.7.3';

	/**
	 * @inheritDoc
	 */
	public function get_target_cms_version(): string {
		return self::TARGET_CMS_VERSION;
	}

	/**
	 * Convert universal component to WPBakery shortcode
	 *
	 * @param DEVTB_Component|array $component Component to convert.
	 * @return string WPBakery shortcode string.
	 */
	public function convert( $component ): string {
		if ( is_array( $component ) ) {
			$components = $component;
		} else {
			$components = [ $component ];
		}

		$shortcodes = [];

		foreach ( $components as $comp ) {
			if ( $comp instanceof DEVTB_Component ) {
				$shortcode = $this->convert_component( $comp );
				if ( $shortcode ) {
					$shortcodes[] = $shortcode;
				}
			}
		}

		return implode( "\n\n", $shortcodes );
	}

	/**
	 * Convert single component to WPBakery shortcode
	 *
	 * @param DEVTB_Component $component Component to convert.
	 * @return string WPBakery shortcode.
	 */
	public function convert_component( DEVTB_Component $component ): string {
		$type = $component->type;

		// Convert based on component type
		if ( $type === 'section' ) {
			return $this->convert_section( $component );
		} elseif ( $type === 'container' || $type === 'row' ) {
			return $this->convert_row( $component );
		} elseif ( $type === 'column' ) {
			return $this->convert_column( $component );
		} else {
			return $this->convert_element( $component );
		}
	}

	/**
	 * Convert section to WPBakery vc_section
	 *
	 * @param DEVTB_Component $component Component to convert.
	 * @return string WPBakery section shortcode.
	 */
	private function convert_section( DEVTB_Component $component ): string {
		$attributes = $this->denormalize_attributes( $component->attributes );

		$rows_content = '';

		// Convert children (rows)
		foreach ( $component->children as $child ) {
			if ( $child->type === 'row' || $child->type === 'container' ) {
				$rows_content .= $this->convert_row( $child ) . "\n";
			} else {
				// Wrap non-row children in a row
				$row = new DEVTB_Component([
					'type'       => 'row',
					'category'   => 'layout',
					'attributes' => [ 'width' => '100%' ],
					'children'   => [ $child ],
				]);
				$rows_content .= $this->convert_row( $row ) . "\n";
			}
		}

		return DEVTB_Shortcode_Helper::build(
			'vc_section',
			$attributes,
			$rows_content,
			false
		);
	}

	/**
	 * Convert row to WPBakery vc_row
	 *
	 * @param DEVTB_Component $component Component to convert.
	 * @return string WPBakery row shortcode.
	 */
	private function convert_row( DEVTB_Component $component ): string {
		$attributes = $this->denormalize_attributes( $component->attributes );

		$columns_content = '';

		// Convert children (columns)
		foreach ( $component->children as $child ) {
			if ( $child->type === 'column' ) {
				$columns_content .= $this->convert_column( $child ) . "\n";
			} else {
				// Wrap non-column children in a column
				$column = new DEVTB_Component([
					'type'       => 'column',
					'category'   => 'layout',
					'attributes' => [ 'width' => '100%' ],
					'children'   => [ $child ],
				]);
				$columns_content .= $this->convert_column( $column ) . "\n";
			}
		}

		// If no columns, create default full-width column
		if ( empty( $columns_content ) && ! empty( $component->content ) ) {
			$column = new DEVTB_Component([
				'type'       => 'column',
				'category'   => 'layout',
				'attributes' => [ 'width' => '100%' ],
				'content'    => $component->content,
			]);
			$columns_content = $this->convert_column( $column );
		}

		return DEVTB_Shortcode_Helper::build(
			'vc_row',
			$attributes,
			$columns_content,
			false
		);
	}

	/**
	 * Convert column to WPBakery vc_column
	 *
	 * @param DEVTB_Component $component Component to convert.
	 * @return string WPBakery column shortcode.
	 */
	private function convert_column( DEVTB_Component $component ): string {
		$attributes = $this->denormalize_attributes( $component->attributes );

		// Convert width to WPBakery format
		if ( isset( $component->attributes['width'] ) ) {
			$width = $this->convert_width_to_vc_format( $component->attributes['width'] );
			$attributes['width'] = $width;
		} else {
			$attributes['width'] = '1/1';
		}

		$elements_content = '';

		// Convert children (elements)
		foreach ( $component->children as $child ) {
			$elements_content .= $this->convert_element( $child ) . "\n";
		}

		// If no children but has content, wrap in text element
		if ( empty( $elements_content ) && ! empty( $component->content ) ) {
			$elements_content = '[vc_column_text]' . $component->content . '[/vc_column_text]';
		}

		return DEVTB_Shortcode_Helper::build(
			'vc_column',
			$attributes,
			$elements_content,
			false
		);
	}

	/**
	 * Convert element to WPBakery element shortcode
	 *
	 * @param DEVTB_Component $component Component to convert.
	 * @return string WPBakery element shortcode.
	 */
	private function convert_element( DEVTB_Component $component ): string {
		// Map universal type to WPBakery element type
		$vc_type = $this->map_to_wpbakery_type( $component->type );

		if ( ! $vc_type ) {
			// Fallback to text element with content
			$vc_type = 'vc_column_text';
		}

		$attributes = $this->denormalize_attributes( $component->attributes );

		// Handle element-specific conversions
		$attributes = $this->add_element_specific_attributes( $vc_type, $attributes, $component );

		// Some elements are self-closing
		$self_closing = in_array( $vc_type, [
			'vc_separator',
			'vc_empty_space',
			'vc_icon',
		], true );

		// Handle nested elements
		$content = $component->content;

		if ( ! empty( $component->children ) ) {
			$children_content = '';
			foreach ( $component->children as $child ) {
				// For tabs/accordion, convert children appropriately
				if ( in_array( $vc_type, [ 'vc_tabs', 'vc_tour', 'vc_accordion', 'vc_tta_tabs', 'vc_tta_accordion' ], true ) ) {
					$children_content .= $this->convert_tab_or_accordion_child( $child, $vc_type ) . "\n";
				} else {
					$children_content .= $this->convert_element( $child ) . "\n";
				}
			}

			// For container-like elements, replace content with children
			if ( in_array( $vc_type, [ 'vc_tabs', 'vc_tour', 'vc_accordion', 'vc_tta_tabs', 'vc_tta_accordion' ], true ) ) {
				$content = $children_content;
			} else {
				$content .= ( $content ? "\n\n" : '' ) . $children_content;
			}
		}

		return DEVTB_Shortcode_Helper::build( $vc_type, $attributes, $content, $self_closing );
	}

	/**
	 * Convert tab or accordion child item
	 *
	 * @param DEVTB_Component $component Child component.
	 * @param string         $parent_type Parent element type.
	 * @return string WPBakery shortcode for child.
	 */
	private function convert_tab_or_accordion_child( DEVTB_Component $component, string $parent_type ): string {
		// Determine child shortcode based on parent
		$child_type = 'vc_tab';
		if ( $parent_type === 'vc_accordion' ) {
			$child_type = 'vc_accordion_tab';
		} elseif ( in_array( $parent_type, [ 'vc_tta_tabs', 'vc_tta_accordion', 'vc_tta_tour' ], true ) ) {
			$child_type = 'vc_tta_section';
		}

		$attributes = $this->denormalize_attributes( $component->attributes );

		// Add title from component
		if ( ! isset( $attributes['title'] ) ) {
			$attributes['title'] = $component->content ? substr( strip_tags( $component->content ), 0, 50 ) : 'Tab';
		}

		return DEVTB_Shortcode_Helper::build( $child_type, $attributes, $component->content, false );
	}

	/**
	 * Map universal type to WPBakery element type
	 *
	 * @param string $universal_type Universal component type.
	 * @return string|null WPBakery element type.
	 */
	private function map_to_wpbakery_type( string $universal_type ): ?string {
		$type_map = [
			// Content
			'text'                => 'vc_column_text',
			'heading'             => 'vc_custom_heading',
			'divider'             => 'vc_separator',
			'alert'               => 'vc_message',
			'cta'                 => 'vc_cta',
			'spacer'              => 'vc_empty_space',

			// Media
			'image'               => 'vc_single_image',
			'gallery'             => 'vc_gallery',
			'slider'              => 'vc_images_carousel',
			'video'               => 'vc_video',
			'map'                 => 'vc_gmaps',

			// Interactive
			'button'              => 'vc_btn',
			'accordion'           => 'vc_accordion',
			'accordion-item'      => 'vc_accordion_tab',
			'tabs'                => 'vc_tabs',
			'tab'                 => 'vc_tab',
			'toggle'              => 'vc_toggle',

			// Lists & Grids
			'grid'                => 'vc_basic_grid',
			'post-grid'           => 'vc_posts_grid',

			// Social
			'social-icon'         => 'vc_facebook',

			// Charts & Progress
			'chart'               => 'vc_pie',
			'progress'            => 'vc_progress_bar',

			// Other
			'icon'                => 'vc_icon',
			'html'                => 'vc_raw_html',
			'code'                => 'vc_raw_js',
			'widget'              => 'vc_widget_sidebar',
			'search'              => 'vc_wp_search',
			'blog'                => 'vc_wp_posts',
			'list'                => 'vc_wp_categories',
			'tag-cloud'           => 'vc_wp_tagcloud',

			// WooCommerce
			'product'             => 'product',
			'product-grid'        => 'products',
			'product-slider'      => 'products',
		];

		return $type_map[ $universal_type ] ?? null;
	}

	/**
	 * Denormalize universal attributes to WPBakery format
	 *
	 * @param array $attributes Universal attributes.
	 * @return array WPBakery attributes.
	 */
	private function denormalize_attributes( array $attributes ): array {
		$vc_attrs = [];

		$attr_map = [
			// Button
			'url'                   => 'link',
			'target'                => 'target',
			'size'                  => 'size',
			'variant'               => 'color',
			'icon'                  => 'icon_left',
			'icon_right'            => 'icon_right',
			'full_width'            => 'button_block',

			// Image
			'image_url'             => 'image',
			'image_size'            => 'img_size',
			'alignment'             => 'alignment',
			'on_click'              => 'onclick',
			'link_url'              => 'img_link',
			'link_target'           => 'img_link_target',

			// Heading
			'heading'               => 'text',

			// Colors & Styling
			'custom_css'            => 'css',
			'css_class'             => 'el_class',
			'element_id'            => 'el_id',
			'animation'             => 'css_animation',

			// Layout
			'width'                 => 'width',
			'offset'                => 'offset',
			'content_placement'     => 'content_placement',
			'gap'                   => 'gap',
			'equal_height'          => 'equal_height',
			'full_width'            => 'full_width',
			'full_height'           => 'full_height',

			// Video
			'aspect_ratio'          => 'el_aspect',

			// Accordion/Tabs
			'title'                 => 'title',
			'tab_id'                => 'tab_id',
			'active'                => 'active',

			// Grid
			'grid_id'               => 'grid_id',
			'posts_per_page'        => 'posts_per_page',
			'max_items'             => 'max_items',
			'item_template'         => 'item',
		];

		foreach ( $attributes as $key => $value ) {
			$vc_key = $attr_map[ $key ] ?? $key;

			// Convert boolean to yes/no or true/false
			if ( is_bool( $value ) ) {
				// WPBakery typically uses 'yes'/'no' or 'true'/'false'
				$value = $value ? 'yes' : 'no';
			}

			$vc_attrs[ $vc_key ] = $value;
		}

		return $vc_attrs;
	}

	/**
	 * Add element-specific attributes
	 *
	 * @param string         $vc_type WPBakery element type.
	 * @param array          $attributes Current attributes.
	 * @param DEVTB_Component $component Original component.
	 * @return array Updated attributes.
	 */
	private function add_element_specific_attributes( string $vc_type, array $attributes, DEVTB_Component $component ): array {
		switch ( $vc_type ) {
			case 'vc_custom_heading':
				// Parse heading text from content or attributes
				if ( ! isset( $attributes['text'] ) && $component->content ) {
					$attributes['text'] = strip_tags( $component->content );
				}
				// Add font container if not present
				if ( ! isset( $attributes['font_container'] ) ) {
					$attributes['font_container'] = 'tag:h2|text_align:left';
				}
				break;

			case 'vc_btn':
			case 'vc_button':
			case 'vc_button2':
				// Ensure button has required attributes
				if ( ! isset( $attributes['title'] ) && $component->content ) {
					$attributes['title'] = strip_tags( $component->content );
				}
				if ( ! isset( $attributes['color'] ) ) {
					$attributes['color'] = 'default';
				}
				if ( ! isset( $attributes['size'] ) ) {
					$attributes['size'] = 'md';
				}
				break;

			case 'vc_single_image':
				// Add image alignment default
				if ( ! isset( $attributes['alignment'] ) ) {
					$attributes['alignment'] = 'left';
				}
				break;

			case 'vc_separator':
				// Add separator defaults
				if ( ! isset( $attributes['color'] ) ) {
					$attributes['color'] = 'grey';
				}
				break;

			case 'vc_message':
				// Add message type default
				if ( ! isset( $attributes['color'] ) ) {
					$attributes['color'] = 'info';
				}
				break;

			case 'vc_cta':
				// Add CTA defaults
				if ( ! isset( $attributes['h2'] ) && $component->content ) {
					$attributes['h2'] = strip_tags( $component->content );
				}
				break;
		}

		return $attributes;
	}

	/**
	 * Convert width percentage to WPBakery format
	 *
	 * @param string $width Width as percentage or fraction.
	 * @return string WPBakery width (1/1, 1/2, etc.).
	 */
	private function convert_width_to_vc_format( string $width ): string {
		// Remove % sign
		$width = str_replace( '%', '', $width );
		$width_float = (float) $width;

		// Map to closest WPBakery width. Keys are stringified floats because PHP
		// silently casts float array keys to int (deprecated in 8.5).
		$width_map = [
			'100'   => '1/1',
			'50'    => '1/2',
			'33.33' => '1/3',
			'66.66' => '2/3',
			'25'    => '1/4',
			'75'    => '3/4',
			'20'    => '1/5',
			'40'    => '2/5',
			'60'    => '3/5',
			'80'    => '4/5',
			'16.66' => '1/6',
			'83.33' => '5/6',
			'8.33'  => '1/12',
			'41.66' => '5/12',
			'58.33' => '7/12',
			'91.66' => '11/12',
		];

		// Find closest match
		$closest  = '1/1';
		$min_diff = 999.0;

		foreach ( $width_map as $percentage => $vc_width ) {
			$diff = abs( (float) $percentage - $width_float );
			if ( $diff < $min_diff ) {
				$min_diff = $diff;
				$closest  = $vc_width;
			}
		}

		return $closest;
	}

	/**
	 * Get framework name
	 *
	 * @return string Framework name.
	 */
	public function get_framework(): string {
		return 'wpbakery';
	}

	/**
	 * Get supported component types
	 *
	 * @return array<string> Array of supported types.
	 */
	public function get_supported_types(): array {
		return [
			'section',
			'container',
			'row',
			'column',
			'text',
			'heading',
			'image',
			'button',
			'divider',
			'alert',
			'cta',
			'spacer',
			'icon',
			'gallery',
			'slider',
			'video',
			'map',
			'accordion',
			'accordion-item',
			'tabs',
			'tab',
			'toggle',
			'grid',
			'post-grid',
			'social-icon',
			'chart',
			'progress',
			'html',
			'code',
			'widget',
			'search',
			'blog',
			'list',
			'tag-cloud',
			'product',
			'product-grid',
			'product-slider',
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
		return in_array( $component->type, $supported, true )
			|| $this->map_to_wpbakery_type( $component->type ) !== null;
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

		// Boost confidence if coming from WPBakery originally
		if ( isset( $component->metadata['source_framework'] ) && $component->metadata['source_framework'] === 'wpbakery' ) {
			$confidence = 0.95;
		}

		// WPBakery is simpler than some other builders, so standard components convert well
		$vc_type = $this->map_to_wpbakery_type( $component->type );
		if ( $vc_type && in_array( $component->type, [ 'text', 'heading', 'image', 'button', 'row', 'column' ], true ) ) {
			$confidence = max( $confidence, 0.9 );
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
	 * @return string Fallback WPBakery shortcode.
	 */
	public function get_fallback( DEVTB_Component $component ): string {
		// Create a basic text block as fallback
		$content = $component->content ? $component->content : 'Unsupported component type: ' . $component->type;
		return '[vc_column_text]' . esc_html( $content ) . '[/vc_column_text]';
	}
}
