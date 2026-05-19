<?php
/**
 * Elementor Page Builder Parser
 *
 * Intelligent Elementor JSON parser featuring:
 * - JSON structure parsing (Section > Column > Widget)
 * - 90+ widget type support
 * - Nested element handling
 * - Settings extraction and normalization
 * - Responsive controls parsing
 * - Dynamic content support
 *
 * @package DevelopmentTranslation_Bridge
 * @subpackage Translation_Bridge
 * @since 3.0.0
 */

namespace DEVTB\TranslationBridge\Parsers;

use DEVTB\TranslationBridge\Core\DEVTB_Parser_Interface;
use DEVTB\TranslationBridge\Models\DEVTB_Component;
use DEVTB\TranslationBridge\Utils\DEVTB_JSON_Helper;
use DEVTB\TranslationBridge\Utils\DEVTB_CSS_Helper;

/**
 * Class DEVTB_Elementor_Parser
 *
 * Parse Elementor JSON into universal components.
 */
class DEVTB_Elementor_Parser implements DEVTB_Parser_Interface {

	/**
	 * Detect Elementor 4.x ("Atomic Editor") content.
	 *
	 * 4.x introduced a new element model with `elType` values prefixed `e-`
	 * (e.g. `e-div-block`, `e-flexbox`, `e-grid`) and a per-element `version`
	 * field >= 4. 3.x uses bare elType strings.
	 *
	 * Callers should passthrough atomic v4 content untouched; native 4.x
	 * parsing is planned for Translation Bridge 4.3.
	 *
	 * @param mixed $data Parsed JSON (array or scalar).
	 * @return bool
	 */
	public static function is_atomic_v4_payload( $data ): bool {
		if ( is_array( $data ) ) {
			if ( isset( $data['elType'] ) && is_string( $data['elType'] ) && str_starts_with( $data['elType'], 'e-' ) ) {
				return true;
			}
			if ( isset( $data['version'] ) && (int) $data['version'] >= 4 ) {
				return true;
			}
			foreach ( [ 'content', 'elements' ] as $key ) {
				if ( isset( $data[ $key ] ) && self::is_atomic_v4_payload( $data[ $key ] ) ) {
					return true;
				}
			}
			foreach ( $data as $item ) {
				if ( is_array( $item ) && self::is_atomic_v4_payload( $item ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Supported Elementor widget types
	 *
	 * @var array<string>
	 */
	private array $supported_types = [
		'section',
		'column',
		'heading',
		'text-editor',
		'image',
		'button',
		'divider',
		'spacer',
		'google_maps',
		'icon',
		'image-box',
		'icon-box',
		'star-rating',
		'image-carousel',
		'image-gallery',
		'icon-list',
		'counter',
		'progress',
		'testimonial',
		'tabs',
		'accordion',
		'toggle',
		'social-icons',
		'alert',
		'audio',
		'shortcode',
		'html',
		'menu-anchor',
		'sidebar',
		'read-more',
		'video',
		'basic-gallery',
		'testimonial-carousel',
		'reviews',
		'slides',
		'form',
		'login',
		'nav-menu',
		'animated-headline',
		'price-list',
		'price-table',
		'flip-box',
		'call-to-action',
		'card',
		'countdown',
		'blockquote',
		'post',
		'posts',
		'portfolio',
		'gallery',
		'share-buttons',
		'slider',
		'table-of-contents',
	];

	/**
	 * Parse Elementor JSON into universal components
	 *
	 * @param string|array $content Elementor JSON content.
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

		$components = [];

		// Parse each top-level element
		foreach ( $content as $element ) {
			$component = $this->parse_element( $element );
			if ( $component ) {
				$components[] = $component;
			}
		}

		return $components;
	}

	/**
	 * Parse single Elementor element
	 *
	 * @param array $element Elementor element data.
	 * @return DEVTB_Component|null Parsed component or null.
	 */
	public function parse_element( $element ): ?DEVTB_Component {
		if ( ! is_array( $element ) ) {
			return null;
		}

		$el_type = $element['elType'] ?? '';

		// Determine component type based on elType
		switch ( $el_type ) {
			case 'section':
				return $this->parse_section( $element );

			case 'column':
				return $this->parse_column( $element );

			case 'widget':
				return $this->parse_widget( $element );

			default:
				return null;
		}
	}

	/**
	 * Parse Elementor section
	 *
	 * @param array $element Section element data.
	 * @return DEVTB_Component|null Parsed section component.
	 */
	private function parse_section( array $element ): ?DEVTB_Component {
		$settings = $element['settings'] ?? [];
		$attributes = $this->normalize_settings( $settings );

		$section = new DEVTB_Component([
			'type'       => 'container',
			'category'   => 'layout',
			'attributes' => $attributes,
			'metadata'   => [
				'source_framework' => 'elementor',
				'original_type'    => 'section',
				'elementor_id'     => $element['id'] ?? '',
				'elementor_settings' => $settings,
			],
		]);

		// Parse columns
		if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
			foreach ( $element['elements'] as $column_element ) {
				$column = $this->parse_element( $column_element );
				if ( $column ) {
					$section->add_child( $column );
				}
			}
		}

		return $section;
	}

	/**
	 * Parse Elementor column
	 *
	 * @param array $element Column element data.
	 * @return DEVTB_Component|null Parsed column component.
	 */
	private function parse_column( array $element ): ?DEVTB_Component {
		$settings = $element['settings'] ?? [];
		$attributes = $this->normalize_settings( $settings );

		// Extract column width
		$width = $settings['_column_size'] ?? 100;
		$attributes['width'] = $width . '%';

		$column = new DEVTB_Component([
			'type'       => 'column',
			'category'   => 'layout',
			'attributes' => $attributes,
			'metadata'   => [
				'source_framework' => 'elementor',
				'original_type'    => 'column',
				'elementor_id'     => $element['id'] ?? '',
				'elementor_settings' => $settings,
			],
		]);

		// Parse widgets
		if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
			foreach ( $element['elements'] as $widget_element ) {
				$widget = $this->parse_element( $widget_element );
				if ( $widget ) {
					$column->add_child( $widget );
				}
			}
		}

		return $column;
	}

	/**
	 * Parse Elementor widget
	 *
	 * @param array $element Widget element data.
	 * @return DEVTB_Component|null Parsed widget component.
	 */
	private function parse_widget( array $element ): ?DEVTB_Component {
		$widget_type = $element['widgetType'] ?? '';
		$settings = $element['settings'] ?? [];

		// Map widget type to universal type
		$universal_type = $this->map_widget_type( $widget_type );

		$attributes = $this->normalize_settings( $settings );

		// Extract content based on widget type
		$content = $this->extract_widget_content( $widget_type, $settings );

		$component = new DEVTB_Component([
			'type'       => $universal_type,
			'category'   => $this->get_category( $universal_type ),
			'attributes' => $attributes,
			'content'    => $content,
			'metadata'   => [
				'source_framework'   => 'elementor',
				'original_type'      => $widget_type,
				'elementor_id'       => $element['id'] ?? '',
				'elementor_settings' => $settings,
			],
		]);

		// Parse nested elements (for tabs, accordion, etc.)
		if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
			foreach ( $element['elements'] as $child_element ) {
				$child = $this->parse_element( $child_element );
				if ( $child ) {
					$component->add_child( $child );
				}
			}
		}

		return $component;
	}

	/**
	 * Map Elementor widget type to universal component type
	 *
	 * @param string $widget_type Elementor widget type.
	 * @return string Universal component type.
	 */
	private function map_widget_type( string $widget_type ): string {
		$type_map = [
			'heading'              => 'heading',
			'text-editor'          => 'text',
			'image'                => 'image',
			'button'               => 'button',
			'divider'              => 'divider',
			'spacer'               => 'spacer',
			'google_maps'          => 'map',
			'icon'                 => 'icon',
			'image-box'            => 'card',
			'icon-box'             => 'card',
			'star-rating'          => 'rating',
			'image-carousel'       => 'slider',
			'image-gallery'        => 'gallery',
			'icon-list'            => 'list',
			'counter'              => 'counter',
			'progress'             => 'progress',
			'testimonial'          => 'testimonial',
			'tabs'                 => 'tabs',
			'accordion'            => 'accordion',
			'toggle'               => 'accordion',
			'social-icons'         => 'social-icons',
			'alert'                => 'alert',
			'audio'                => 'audio',
			'video'                => 'video',
			'basic-gallery'        => 'gallery',
			'testimonial-carousel' => 'slider',
			'slides'               => 'slider',
			'form'                 => 'form',
			'login'                => 'form',
			'nav-menu'             => 'nav',
			'animated-headline'    => 'heading',
			'price-list'           => 'pricing-table',
			'price-table'          => 'pricing-table',
			'flip-box'             => 'card',
			'call-to-action'       => 'cta',
			'card'                 => 'card',
			'countdown'            => 'countdown',
			'blockquote'           => 'blockquote',
			'portfolio'            => 'portfolio',
			'gallery'              => 'gallery',
			'share-buttons'        => 'social-icons',
			'slider'               => 'slider',
			'table-of-contents'    => 'toc',
		];

		return $type_map[ $widget_type ] ?? 'unknown';
	}

	/**
	 * Extract content from widget settings
	 *
	 * @param string $widget_type Widget type.
	 * @param array  $settings Widget settings.
	 * @return string Extracted content.
	 */
	private function extract_widget_content( string $widget_type, array $settings ): string {
		switch ( $widget_type ) {
			case 'heading':
				return $settings['title'] ?? '';

			case 'text-editor':
				return $settings['editor'] ?? '';

			case 'button':
				return $settings['text'] ?? '';

			case 'icon-box':
			case 'image-box':
				$title = $settings['title_text'] ?? '';
				$description = $settings['description_text'] ?? '';
				return $title . ( $title && $description ? "\n\n" : '' ) . $description;

			case 'testimonial':
				return $settings['testimonial_content'] ?? '';

			case 'blockquote':
				return $settings['blockquote_content'] ?? '';

			default:
				return '';
		}
	}

	/**
	 * Normalize Elementor settings to universal attributes
	 *
	 * @param array $settings Elementor settings.
	 * @return array Normalized attributes.
	 */
	private function normalize_settings( array $settings ): array {
		$normalized = [];

		// Common attribute mappings
		$attr_map = [
			// Button
			'link'                => 'url',
			'text'                => 'label',
			'button_type'         => 'variant',
			'size'                => 'size',

			// Image
			'image'               => 'image_url',
			'alt_text'            => 'alt_text',

			// Heading
			'title'               => 'heading',
			'header_size'         => 'level',

			// Icon box / Image box
			'title_text'          => 'heading',
			'description_text'    => 'description',
			'icon'                => 'icon',

			// Colors
			'background_color'    => 'background_color',
			'text_color'          => 'text_color',
			'color'               => 'text_color',

			// Layout
			'content_width'       => 'width',
			'gap'                 => 'gap',

			// Alignment
			'align'               => 'alignment',
			'text_align'          => 'alignment',

			// Spacing
			'padding'             => 'padding',
			'margin'              => 'margin',
		];

		// Map attributes
		foreach ( $settings as $key => $value ) {
			// Skip internal Elementor settings (start with _)
			if ( strpos( $key, '_' ) === 0 && $key !== '_column_size' ) {
				continue;
			}

			$universal_key = $attr_map[ $key ] ?? $key;

			// Handle complex values
			if ( is_array( $value ) ) {
				// Handle link arrays
				if ( $key === 'link' && isset( $value['url'] ) ) {
					$normalized['url'] = $value['url'];
					if ( ! empty( $value['is_external'] ) ) {
						$normalized['target'] = '_blank';
					}
					if ( ! empty( $value['nofollow'] ) ) {
						$normalized['rel'] = 'nofollow';
					}
				}
				// Handle image arrays
				elseif ( $key === 'image' && isset( $value['url'] ) ) {
					$normalized['image_url'] = $value['url'];
					if ( isset( $value['alt'] ) ) {
						$normalized['alt_text'] = $value['alt'];
					}
				}
				// Handle responsive values (take default)
				elseif ( isset( $value['size'] ) ) {
					$normalized[ $universal_key ] = $value['size'];
				} else {
					// Convert array to JSON string for preservation
					$normalized[ $universal_key ] = wp_json_encode( $value );
				}
			} else {
				$normalized[ $universal_key ] = $value;
			}
		}

		return $normalized;
	}

	/**
	 * Get component category from type
	 *
	 * @param string $type Component type.
	 * @return string Category name.
	 */
	private function get_category( string $type ): string {
		$categories = [
			'layout'      => [ 'container', 'row', 'column', 'section', 'spacer' ],
			'content'     => [ 'text', 'heading', 'image', 'card', 'blockquote' ],
			'media'       => [ 'video', 'audio', 'gallery', 'slider' ],
			'interactive' => [ 'button', 'accordion', 'tabs', 'modal', 'toggle' ],
			'form'        => [ 'form', 'input', 'search' ],
			'data'        => [ 'counter', 'progress', 'pricing-table', 'rating' ],
			'social'      => [ 'social-icons', 'testimonial', 'share-buttons' ],
			'navigation'  => [ 'nav', 'breadcrumb', 'toc' ],
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
		return 'elementor';
	}

	/**
	 * Validate Elementor JSON content
	 *
	 * @param string|array $content Content to validate.
	 * @return bool True if valid Elementor content.
	 */
	public function is_valid_content( $content ): bool {
		// Handle string JSON
		if ( is_string( $content ) ) {
			$content = json_decode( $content, true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				return false;
			}
		}

		if ( ! is_array( $content ) ) {
			return false;
		}

		// Use JSON helper validation
		return DEVTB_JSON_Helper::is_valid_elementor( $content );
	}

	/**
	 * Get supported component types
	 *
	 * @return array<string> Array of supported types.
	 */
	public function get_supported_types(): array {
		return $this->supported_types;
	}
}
