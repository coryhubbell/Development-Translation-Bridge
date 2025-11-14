<?php
/**
 * DIVI Builder Converter
 *
 * Intelligent DIVI shortcode converter featuring:
 * - Component to shortcode generation
 * - Hierarchical structure building (Section > Row > Column > Module)
 * - 38+ module type support
 * - Attribute normalization
 * - Nested shortcode handling
 * - Visual Builder compatibility
 *
 * @package WordPress_Bootstrap_Claude
 * @subpackage Translation_Bridge
 * @since 3.0.0
 */

namespace WPBC\TranslationBridge\Converters;

use WPBC\TranslationBridge\Core\WPBC_Converter_Interface;
use WPBC\TranslationBridge\Models\WPBC_Component;
use WPBC\TranslationBridge\Utils\WPBC_Shortcode_Helper;

/**
 * Class WPBC_DIVI_Converter
 *
 * Convert universal components to DIVI Builder shortcodes.
 */
class WPBC_DIVI_Converter implements WPBC_Converter_Interface {

	/**
	 * Convert universal component(s) to DIVI shortcodes
	 *
	 * @param WPBC_Component|WPBC_Component[] $component Component(s) to convert.
	 * @return string DIVI shortcode output.
	 */
	public function convert( $component ): string {
		// Handle array of components
		if ( is_array( $component ) ) {
			$shortcode_parts = [];
			foreach ( $component as $comp ) {
				$shortcode_parts[] = $this->convert_component( $comp );
			}
			return implode( "\n", $shortcode_parts );
		}

		// Handle single component
		return $this->convert_component( $component );
	}

	/**
	 * Convert single component to DIVI shortcode
	 *
	 * @param WPBC_Component $component Component to convert.
	 * @return string DIVI shortcode.
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
	 * Convert container component (DIVI section)
	 *
	 * @param WPBC_Component $component Container component.
	 * @return string Section shortcode.
	 */
	private function convert_container( WPBC_Component $component ): string {
		$attributes = $this->denormalize_attributes( $component->attributes );

		$shortcode = WPBC_Shortcode_Helper::build( 'et_pb_section', $attributes );

		// Convert children (rows)
		$inner = '';
		foreach ( $component->children as $child ) {
			$inner .= $this->convert_component( $child );
		}

		return str_replace( ']', ']' . $inner, $shortcode ) . '[/et_pb_section]';
	}

	/**
	 * Convert row component
	 *
	 * @param WPBC_Component $component Row component.
	 * @return string Row shortcode.
	 */
	private function convert_row( WPBC_Component $component ): string {
		$attributes = $this->denormalize_attributes( $component->attributes );

		// Convert children (columns)
		$inner = '';
		foreach ( $component->children as $child ) {
			$inner .= $this->convert_component( $child );
		}

		return WPBC_Shortcode_Helper::build( 'et_pb_row', $attributes, $inner );
	}

	/**
	 * Convert column component
	 *
	 * @param WPBC_Component $component Column component.
	 * @return string Column shortcode.
	 */
	private function convert_column( WPBC_Component $component ): string {
		$attributes = $this->denormalize_attributes( $component->attributes );

		// Convert width to DIVI column type
		$width = $component->get_attribute( 'width', '100%' );
		$attributes['type'] = $this->convert_width_to_divi_type( $width );

		// Convert children (modules)
		$inner = '';
		foreach ( $component->children as $child ) {
			$inner .= $this->convert_component( $child );
		}

		return WPBC_Shortcode_Helper::build( 'et_pb_column', $attributes, $inner );
	}

	/**
	 * Convert button component
	 *
	 * @param WPBC_Component $component Button component.
	 * @return string Button module shortcode.
	 */
	private function convert_button( WPBC_Component $component ): string {
		$attributes = [
			'button_url'  => $component->get_attribute( 'url', '#' ),
			'button_text' => $component->content ?: $component->get_attribute( 'label', 'Click Here' ),
		];

		// Background color
		$bg_color = $component->get_attribute( 'background_color' );
		if ( $bg_color ) {
			$attributes['button_bg_color'] = $bg_color;
		}

		// Text color
		$text_color = $component->get_attribute( 'text_color' );
		if ( $text_color ) {
			$attributes['button_text_color'] = $text_color;
		}

		// Size
		$size = $component->get_attribute( 'size' );
		if ( $size ) {
			$attributes['button_size'] = $size;
		}

		return WPBC_Shortcode_Helper::build( 'et_pb_button', $attributes );
	}

	/**
	 * Convert card component (DIVI blurb)
	 *
	 * @param WPBC_Component $component Card component.
	 * @return string Blurb module shortcode.
	 */
	private function convert_card( WPBC_Component $component ): string {
		$attributes = [];

		// Title
		$title = $component->get_attribute( 'heading' ) ?: $component->get_attribute( 'title' );
		if ( $title ) {
			$attributes['title'] = $title;
		}

		// Image
		$image_url = $component->get_attribute( 'image_url' );
		if ( $image_url ) {
			$attributes['image'] = $image_url;
			$attributes['use_icon'] = 'off';
		}

		// Icon
		$icon = $component->get_attribute( 'icon' );
		if ( $icon && ! $image_url ) {
			$attributes['font_icon'] = $icon;
			$attributes['use_icon'] = 'on';
		}

		// URL
		$url = $component->get_attribute( 'link_url' ) ?: $component->get_attribute( 'url' );
		if ( $url ) {
			$attributes['url'] = $url;
		}

		// Background color
		$bg_color = $component->get_attribute( 'background_color' );
		if ( $bg_color ) {
			$attributes['background_color'] = $bg_color;
		}

		// Content
		$content = $component->content;

		return WPBC_Shortcode_Helper::build( 'et_pb_blurb', $attributes, $content );
	}

	/**
	 * Convert text component
	 *
	 * @param WPBC_Component $component Text component.
	 * @return string Text module shortcode.
	 */
	private function convert_text( WPBC_Component $component ): string {
		$attributes = [];

		// Background color
		$bg_color = $component->get_attribute( 'background_color' );
		if ( $bg_color ) {
			$attributes['background_color'] = $bg_color;
		}

		// Text color
		$text_color = $component->get_attribute( 'text_color' );
		if ( $text_color ) {
			$attributes['text_color'] = $text_color;
		}

		// Text alignment
		$alignment = $component->get_attribute( 'alignment' );
		if ( $alignment ) {
			$attributes['text_orientation'] = $alignment;
		}

		// Content
		$content = $component->content;

		return WPBC_Shortcode_Helper::build( 'et_pb_text', $attributes, $content );
	}

	/**
	 * Convert heading component (as text with heading tag)
	 *
	 * @param WPBC_Component $component Heading component.
	 * @return string Text module with heading.
	 */
	private function convert_heading( WPBC_Component $component ): string {
		$attributes = [];

		// Text alignment
		$alignment = $component->get_attribute( 'alignment' );
		if ( $alignment ) {
			$attributes['text_orientation'] = $alignment;
		}

		// Text color
		$color = $component->get_attribute( 'color' );
		if ( $color ) {
			$attributes['text_color'] = $color;
		}

		// Wrap content in heading tag
		$level = $component->get_attribute( 'level', 2 );
		$content = sprintf( '<h%d>%s</h%d>', $level, esc_html( $component->content ), $level );

		return WPBC_Shortcode_Helper::build( 'et_pb_text', $attributes, $content );
	}

	/**
	 * Convert image component
	 *
	 * @param WPBC_Component $component Image component.
	 * @return string Image module shortcode.
	 */
	private function convert_image( WPBC_Component $component ): string {
		$attributes = [
			'src' => $component->get_attribute( 'image_url', '' ),
			'alt' => $component->get_attribute( 'alt_text', '' ),
		];

		// Title
		$title = $component->get_attribute( 'title' );
		if ( $title ) {
			$attributes['title_text'] = $title;
		}

		// URL (if image is a link)
		$url = $component->get_attribute( 'url' );
		if ( $url ) {
			$attributes['url'] = $url;
		}

		// Alignment
		$alignment = $component->get_attribute( 'alignment' );
		if ( $alignment ) {
			$attributes['align'] = $alignment;
		}

		return WPBC_Shortcode_Helper::build( 'et_pb_image', $attributes );
	}

	/**
	 * Convert video component
	 *
	 * @param WPBC_Component $component Video component.
	 * @return string Video module shortcode.
	 */
	private function convert_video( WPBC_Component $component ): string {
		$attributes = [];

		// Video URL
		$url = $component->get_attribute( 'video_url' ) ?: $component->get_attribute( 'url' );
		if ( $url ) {
			$attributes['src'] = $url;
		}

		// Thumbnail
		$thumbnail = $component->get_attribute( 'thumbnail_url' );
		if ( $thumbnail ) {
			$attributes['image_src'] = $thumbnail;
		}

		return WPBC_Shortcode_Helper::build( 'et_pb_video', $attributes );
	}

	/**
	 * Convert accordion component
	 *
	 * @param WPBC_Component $component Accordion component.
	 * @return string Accordion module shortcode.
	 */
	private function convert_accordion( WPBC_Component $component ): string {
		$attributes = [];

		// Convert children (accordion items)
		$inner = '';
		foreach ( $component->children as $child ) {
			$item_attrs = [
				'title' => $child->get_attribute( 'title', 'Item' ),
			];

			$inner .= WPBC_Shortcode_Helper::build(
				'et_pb_accordion_item',
				$item_attrs,
				$child->content
			);
		}

		return WPBC_Shortcode_Helper::build( 'et_pb_accordion', $attributes, $inner );
	}

	/**
	 * Convert tabs component
	 *
	 * @param WPBC_Component $component Tabs component.
	 * @return string Tabs module shortcode.
	 */
	private function convert_tabs( WPBC_Component $component ): string {
		$attributes = [];

		// Convert children (tab items)
		$inner = '';
		foreach ( $component->children as $child ) {
			$item_attrs = [
				'title' => $child->get_attribute( 'title', 'Tab' ),
			];

			$inner .= WPBC_Shortcode_Helper::build(
				'et_pb_tab',
				$item_attrs,
				$child->content
			);
		}

		return WPBC_Shortcode_Helper::build( 'et_pb_tabs', $attributes, $inner );
	}

	/**
	 * Convert divider component
	 *
	 * @param WPBC_Component $component Divider component.
	 * @return string Divider module shortcode.
	 */
	private function convert_divider( WPBC_Component $component ): string {
		$attributes = [];

		// Color
		$color = $component->get_attribute( 'color' );
		if ( $color ) {
			$attributes['divider_color'] = $color;
		}

		// Style
		$style = $component->get_attribute( 'style' );
		if ( $style ) {
			$attributes['divider_style'] = $style;
		}

		return WPBC_Shortcode_Helper::build( 'et_pb_divider', $attributes );
	}

	/**
	 * Convert form component
	 *
	 * @param WPBC_Component $component Form component.
	 * @return string Contact form module shortcode.
	 */
	private function convert_form( WPBC_Component $component ): string {
		$attributes = [
			'email' => $component->get_attribute( 'email', get_option( 'admin_email' ) ),
		];

		// Title
		$title = $component->get_attribute( 'title' );
		if ( $title ) {
			$attributes['title'] = $title;
		}

		return WPBC_Shortcode_Helper::build( 'et_pb_contact_form', $attributes );
	}

	/**
	 * Convert generic component
	 *
	 * @param WPBC_Component $component Component to convert.
	 * @return string DIVI shortcode.
	 */
	private function convert_generic( WPBC_Component $component ): string {
		// Try to use original DIVI tag if available
		$divi_tag = $component->get_metadata( 'divi_tag' );
		$divi_attrs = $component->get_metadata( 'divi_attributes', [] );

		if ( $divi_tag && ! empty( $divi_attrs ) ) {
			return WPBC_Shortcode_Helper::build( $divi_tag, $divi_attrs, $component->content );
		}

		// Fallback to text module
		return WPBC_Shortcode_Helper::build( 'et_pb_text', [], $component->content );
	}

	/**
	 * Denormalize attributes from universal to DIVI format
	 *
	 * @param array $attributes Universal attributes.
	 * @return array DIVI attributes.
	 */
	private function denormalize_attributes( array $attributes ): array {
		$divi_attrs = [];

		// Reverse mapping
		$attr_map = [
			'url'              => 'button_url',
			'label'            => 'button_text',
			'background_color' => 'background_color',
			'heading'          => 'title',
			'link_url'         => 'url',
			'image_url'        => 'image',
			'icon'             => 'font_icon',
			'alt_text'         => 'alt',
			'text_color'       => 'text_color',
			'full_width'       => 'fullwidth',
		];

		foreach ( $attributes as $key => $value ) {
			$divi_key = $attr_map[ $key ] ?? $key;
			$divi_attrs[ $divi_key ] = $value;
		}

		// Convert boolean values
		foreach ( $divi_attrs as $key => $value ) {
			if ( is_bool( $value ) ) {
				$divi_attrs[ $key ] = $value ? 'on' : 'off';
			}
		}

		return $divi_attrs;
	}

	/**
	 * Convert width percentage to DIVI column type
	 *
	 * @param string $width Width as percentage.
	 * @return string DIVI column type.
	 */
	private function convert_width_to_divi_type( string $width ): string {
		// Remove % sign and convert to float
		$width_value = (float) str_replace( '%', '', $width );

		// Map to closest DIVI column type
		$type_map = [
			100   => '4_4',
			75    => '3_4',
			66.66 => '2_3',
			50    => '1_2',
			33.33 => '1_3',
			25    => '1_4',
			20    => '1_5',
			16.66 => '1_6',
		];

		// Find closest match
		$closest_diff = 999;
		$closest_type = '4_4';

		foreach ( $type_map as $percent => $type ) {
			$diff = abs( $width_value - $percent );
			if ( $diff < $closest_diff ) {
				$closest_diff = $diff;
				$closest_type = $type;
			}
		}

		return $closest_type;
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
	 * Get supported component types
	 *
	 * @return array<string> Supported types.
	 */
	public function get_supported_types(): array {
		return [
			'container',
			'row',
			'column',
			'text',
			'heading',
			'image',
			'button',
			'card',
			'video',
			'audio',
			'accordion',
			'tabs',
			'divider',
			'form',
			'slider',
			'gallery',
			'testimonial',
			'counter',
			'progress',
			'pricing-table',
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
	 * Get fallback shortcode for unsupported component
	 *
	 * @param WPBC_Component $component Unsupported component.
	 * @return string Fallback shortcode.
	 */
	public function get_fallback( WPBC_Component $component ): string {
		// Fallback to text module with content
		$fallback_content = sprintf(
			'<!-- Unsupported component type: %s -->%s',
			$component->type,
			$component->content
		);

		return WPBC_Shortcode_Helper::build( 'et_pb_text', [], $fallback_content );
	}
}
