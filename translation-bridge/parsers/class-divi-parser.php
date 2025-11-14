<?php
/**
 * DIVI Builder Parser
 *
 * Intelligent DIVI shortcode parser featuring:
 * - Hierarchical structure parsing (Section > Row > Column > Module)
 * - 38+ module type support
 * - Nested shortcode handling
 * - Attribute extraction and normalization
 * - Visual Builder compatibility
 * - Content preservation
 *
 * @package WordPress_Bootstrap_Claude
 * @subpackage Translation_Bridge
 * @since 3.0.0
 */

namespace WPBC\TranslationBridge\Parsers;

use WPBC\TranslationBridge\Core\WPBC_Parser_Interface;
use WPBC\TranslationBridge\Models\WPBC_Component;
use WPBC\TranslationBridge\Utils\WPBC_Shortcode_Helper;
use WPBC\TranslationBridge\Utils\WPBC_CSS_Helper;

/**
 * Class WPBC_DIVI_Parser
 *
 * Parse DIVI Builder shortcodes into universal components.
 */
class WPBC_DIVI_Parser implements WPBC_Parser_Interface {

	/**
	 * Supported DIVI module types
	 *
	 * @var array<string>
	 */
	private array $supported_types = [
		'section',
		'row',
		'column',
		'text',
		'image',
		'button',
		'blurb',
		'accordion',
		'audio',
		'bar_counters',
		'blog',
		'circle_counter',
		'code',
		'contact_form',
		'countdown_timer',
		'cta',
		'divider',
		'filterable_portfolio',
		'fullwidth_code',
		'fullwidth_header',
		'fullwidth_image',
		'fullwidth_map',
		'fullwidth_menu',
		'fullwidth_portfolio',
		'fullwidth_post_slider',
		'fullwidth_post_title',
		'fullwidth_slider',
		'gallery',
		'map',
		'number_counter',
		'portfolio',
		'post_slider',
		'post_title',
		'pricing_tables',
		'search',
		'shop',
		'sidebar',
		'signup',
		'slider',
		'social_media_follow',
		'tabs',
		'team_member',
		'testimonial',
		'toggle',
		'video',
		'video_slider',
	];

	/**
	 * Parse DIVI shortcode content into universal components
	 *
	 * @param string|array $content DIVI shortcode content.
	 * @return WPBC_Component[] Array of parsed components.
	 */
	public function parse( $content ): array {
		if ( is_array( $content ) ) {
			$content = implode( "\n", $content );
		}

		if ( ! is_string( $content ) || empty( $content ) ) {
			return [];
		}

		// Parse DIVI hierarchy
		$hierarchy = WPBC_Shortcode_Helper::parse_divi_hierarchy( $content );

		$components = [];

		foreach ( $hierarchy as $section_data ) {
			$section = $this->parse_section( $section_data );
			if ( $section ) {
				$components[] = $section;
			}
		}

		return $components;
	}

	/**
	 * Parse DIVI section
	 *
	 * @param array $section_data Section data from hierarchy.
	 * @return WPBC_Component|null Parsed section component.
	 */
	private function parse_section( array $section_data ): ?WPBC_Component {
		$attributes = $this->normalize_attributes( $section_data['attributes'] );

		$section = new WPBC_Component([
			'type'       => 'container',
			'category'   => 'layout',
			'attributes' => $attributes,
			'metadata'   => [
				'source_framework' => 'divi',
				'original_type'    => 'section',
				'divi_attributes'  => $section_data['attributes'],
			],
		]);

		// Parse rows
		if ( isset( $section_data['rows'] ) && is_array( $section_data['rows'] ) ) {
			foreach ( $section_data['rows'] as $row_data ) {
				$row = $this->parse_row( $row_data );
				if ( $row ) {
					$section->add_child( $row );
				}
			}
		}

		return $section;
	}

	/**
	 * Parse DIVI row
	 *
	 * @param array $row_data Row data from hierarchy.
	 * @return WPBC_Component|null Parsed row component.
	 */
	private function parse_row( array $row_data ): ?WPBC_Component {
		$attributes = $this->normalize_attributes( $row_data['attributes'] );

		$row = new WPBC_Component([
			'type'       => 'row',
			'category'   => 'layout',
			'attributes' => $attributes,
			'metadata'   => [
				'source_framework' => 'divi',
				'original_type'    => 'row',
				'divi_attributes'  => $row_data['attributes'],
			],
		]);

		// Parse columns
		if ( isset( $row_data['columns'] ) && is_array( $row_data['columns'] ) ) {
			foreach ( $row_data['columns'] as $column_data ) {
				$column = $this->parse_column( $column_data );
				if ( $column ) {
					$row->add_child( $column );
				}
			}
		}

		return $row;
	}

	/**
	 * Parse DIVI column
	 *
	 * @param array $column_data Column data from hierarchy.
	 * @return WPBC_Component|null Parsed column component.
	 */
	private function parse_column( array $column_data ): ?WPBC_Component {
		$attributes = $this->normalize_attributes( $column_data['attributes'] );

		// Extract column type (1_2, 1_3, 1_4, etc.)
		$type = $column_data['attributes']['type'] ?? '1_1';
		$attributes['width'] = $this->convert_divi_column_type( $type );

		$column = new WPBC_Component([
			'type'       => 'column',
			'category'   => 'layout',
			'attributes' => $attributes,
			'metadata'   => [
				'source_framework' => 'divi',
				'original_type'    => 'column',
				'column_type'      => $type,
				'divi_attributes'  => $column_data['attributes'],
			],
		]);

		// Parse modules
		if ( isset( $column_data['modules'] ) && is_array( $column_data['modules'] ) ) {
			foreach ( $column_data['modules'] as $module ) {
				$component = $this->parse_module( $module );
				if ( $component ) {
					$column->add_child( $component );
				}
			}
		}

		return $column;
	}

	/**
	 * Parse DIVI module
	 *
	 * @param array $module Module data.
	 * @return WPBC_Component|null Parsed module component.
	 */
	private function parse_module( array $module ): ?WPBC_Component {
		$tag = $module['tag'] ?? '';

		// Remove et_pb_ prefix to get module type
		$module_type = str_replace( 'et_pb_', '', $tag );

		// Map DIVI module type to universal type
		$universal_type = $this->map_module_type( $module_type );

		$attributes = $this->normalize_attributes( $module['attributes'] );

		// Extract content
		$content = $module['content'] ?? '';
		$content = do_shortcode( $content ); // Process nested shortcodes

		$component = new WPBC_Component([
			'type'       => $universal_type,
			'category'   => $this->get_category( $universal_type ),
			'attributes' => $attributes,
			'content'    => $content,
			'metadata'   => [
				'source_framework' => 'divi',
				'original_type'    => $module_type,
				'divi_tag'         => $tag,
				'divi_attributes'  => $module['attributes'],
			],
		]);

		// Parse nested modules (for accordion, tabs, etc.)
		if ( ! empty( $module['children'] ) ) {
			foreach ( $module['children'] as $child ) {
				$child_component = $this->parse_module( $child );
				if ( $child_component ) {
					$component->add_child( $child_component );
				}
			}
		}

		return $component;
	}

	/**
	 * Map DIVI module type to universal component type
	 *
	 * @param string $divi_type DIVI module type.
	 * @return string Universal component type.
	 */
	private function map_module_type( string $divi_type ): string {
		$type_map = [
			'text'              => 'text',
			'image'             => 'image',
			'button'            => 'button',
			'blurb'             => 'card',
			'accordion'         => 'accordion',
			'tabs'              => 'tabs',
			'toggle'            => 'accordion',
			'slider'            => 'slider',
			'video'             => 'video',
			'audio'             => 'audio',
			'gallery'           => 'gallery',
			'map'               => 'map',
			'divider'           => 'divider',
			'contact_form'      => 'form',
			'testimonial'       => 'testimonial',
			'team_member'       => 'team-member',
			'pricing_tables'    => 'pricing-table',
			'number_counter'    => 'counter',
			'circle_counter'    => 'counter',
			'bar_counters'      => 'progress',
			'countdown_timer'   => 'countdown',
			'cta'               => 'cta',
			'post_slider'       => 'slider',
			'portfolio'         => 'portfolio',
			'blog'              => 'blog',
			'social_media_follow' => 'social-icons',
		];

		return $type_map[ $divi_type ] ?? 'unknown';
	}

	/**
	 * Normalize DIVI attributes to universal format
	 *
	 * @param array $divi_attrs DIVI attributes.
	 * @return array Normalized attributes.
	 */
	private function normalize_attributes( array $divi_attrs ): array {
		$normalized = [];

		// Common attribute mappings
		$attr_map = [
			// Button
			'button_url'        => 'url',
			'button_text'       => 'label',
			'button_bg_color'   => 'background_color',

			// Blurb (card)
			'title'             => 'heading',
			'url'               => 'link_url',
			'image'             => 'image_url',
			'icon'              => 'icon',
			'font_icon'         => 'icon',

			// Image
			'src'               => 'image_url',
			'alt'               => 'alt_text',

			// Colors
			'background_color'  => 'background_color',
			'text_color'        => 'text_color',

			// Layout
			'fullwidth'         => 'full_width',
			'specialty'         => 'specialty',

			// Module visibility
			'disabled_on'       => 'hide_on',

			// Admin label
			'admin_label'       => 'admin_label',
		];

		// Map attributes
		foreach ( $divi_attrs as $key => $value ) {
			$universal_key = $attr_map[ $key ] ?? $key;
			$normalized[ $universal_key ] = $value;
		}

		// Convert boolean values
		foreach ( $normalized as $key => $value ) {
			if ( in_array( strtolower( (string) $value ), [ 'on', 'off' ], true ) ) {
				$normalized[ $key ] = strtolower( $value ) === 'on';
			}
		}

		return $normalized;
	}

	/**
	 * Convert DIVI column type to width percentage
	 *
	 * @param string $type DIVI column type (1_2, 1_3, etc.).
	 * @return string Width as percentage or fraction.
	 */
	private function convert_divi_column_type( string $type ): string {
		$width_map = [
			'4_4'   => '100%',
			'1_1'   => '100%',
			'1_2'   => '50%',
			'1_3'   => '33.33%',
			'2_3'   => '66.66%',
			'1_4'   => '25%',
			'3_4'   => '75%',
			'1_5'   => '20%',
			'2_5'   => '40%',
			'3_5'   => '60%',
			'4_5'   => '80%',
			'1_6'   => '16.66%',
			'5_6'   => '83.33%',
		];

		return $width_map[ $type ] ?? '100%';
	}

	/**
	 * Get component category from type
	 *
	 * @param string $type Component type.
	 * @return string Category name.
	 */
	private function get_category( string $type ): string {
		$categories = [
			'layout'      => [ 'container', 'row', 'column', 'section' ],
			'content'     => [ 'text', 'heading', 'image', 'card' ],
			'media'       => [ 'video', 'audio', 'gallery', 'slider' ],
			'interactive' => [ 'button', 'accordion', 'tabs', 'modal' ],
			'form'        => [ 'form', 'input', 'search' ],
			'data'        => [ 'counter', 'progress', 'pricing-table' ],
			'social'      => [ 'social-icons', 'testimonial', 'team-member' ],
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
		return 'divi';
	}

	/**
	 * Validate DIVI shortcode content
	 *
	 * @param string|array $content Content to validate.
	 * @return bool True if valid DIVI content.
	 */
	public function is_valid_content( $content ): bool {
		if ( is_array( $content ) ) {
			$content = implode( "\n", $content );
		}

		if ( ! is_string( $content ) || empty( $content ) ) {
			return false;
		}

		// Check for DIVI shortcodes
		return WPBC_Shortcode_Helper::is_divi( $content );
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
	 * @param mixed $element DIVI shortcode or module data.
	 * @return WPBC_Component|null Parsed component or null.
	 */
	public function parse_element( $element ): ?WPBC_Component {
		if ( is_string( $element ) ) {
			$components = $this->parse( $element );
			return $components[0] ?? null;
		}

		if ( is_array( $element ) && isset( $element['tag'] ) ) {
			return $this->parse_module( $element );
		}

		return null;
	}
}
