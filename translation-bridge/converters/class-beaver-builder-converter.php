<?php
/**
 * Beaver Builder Page Builder Converter
 *
 * Intelligent universal to Beaver Builder converter featuring:
 * - Serialized PHP array generation (Row > Column Group > Column > Module)
 * - 30+ module type support
 * - Settings denormalization
 * - Node structure generation with parent/child relationships
 * - Position management
 *
 * @package DevelopmentTranslation_Bridge
 * @subpackage Translation_Bridge
 * @since 3.2.0
 */

namespace DEVTB\TranslationBridge\Converters;

use DEVTB\TranslationBridge\Core\DEVTB_Converter_Interface;
use DEVTB\TranslationBridge\Models\DEVTB_Component;
use DEVTB\TranslationBridge\Utils\DEVTB_CSS_Helper;

/**
 * Class DEVTB_Beaver_Builder_Converter
 *
 * Convert universal components to Beaver Builder serialized data.
 */
class DEVTB_Beaver_Builder_Converter implements DEVTB_Converter_Interface {

	/**
	 * Upstream framework version this converter is calibrated against.
	 */
	public const TARGET_CMS_VERSION = '2.10.2';

	/**
	 * @inheritDoc
	 */
	public function get_target_cms_version(): string {
		return self::TARGET_CMS_VERSION;
	}

	/**
	 * Node ID counter for unique IDs
	 *
	 * @var int
	 */
	private int $id_counter = 0;

	/**
	 * All nodes array (flat structure keyed by node ID)
	 *
	 * @var array
	 */
	private array $nodes = [];

	/**
	 * Convert universal component to Beaver Builder serialized data
	 *
	 * @param DEVTB_Component|array $component Component to convert.
	 * @return string Beaver Builder serialized PHP data.
	 */
	public function convert( $component ) {
		// Reset for new conversion
		$this->nodes = [];
		$this->id_counter = 0;

		if ( is_array( $component ) ) {
			$components = $component;
		} else {
			$components = [ $component ];
		}

		// Convert each component to Beaver Builder nodes
		foreach ( $components as $index => $comp ) {
			if ( $comp instanceof DEVTB_Component ) {
				$this->convert_component( $comp, null, $index );
			}
		}

		// Return serialized nodes array
		return serialize( $this->nodes );
	}

	/**
	 * Convert single component to Beaver Builder node(s)
	 *
	 * @param DEVTB_Component $component Component to convert.
	 * @param string|null    $parent_id Parent node ID.
	 * @param int            $position Position within parent.
	 * @return string Node ID of created node.
	 */
	public function convert_component( DEVTB_Component $component, ?string $parent_id = null, int $position = 0 ) {
		$type = $component->type;

		// Convert based on component type
		if ( $type === 'container' ) {
			return $this->convert_row( $component, $parent_id, $position );
		} elseif ( $type === 'row' ) {
			// Row becomes column-group in Beaver Builder
			return $this->convert_column_group( $component, $parent_id, $position );
		} elseif ( $type === 'column' ) {
			return $this->convert_column( $component, $parent_id, $position );
		} else {
			return $this->convert_module( $component, $parent_id, $position );
		}
	}

	/**
	 * Convert container to Beaver Builder row
	 *
	 * @param DEVTB_Component $component Component to convert.
	 * @param string|null    $parent_id Parent node ID.
	 * @param int            $position Position.
	 * @return string Node ID.
	 */
	private function convert_row( DEVTB_Component $component, ?string $parent_id, int $position ): string {
		$node_id = $this->generate_node_id();
		$settings = $this->denormalize_attributes( $component->attributes );

		// Add styles to settings
		if ( ! empty( $component->styles ) ) {
			$settings = array_merge( $settings, $this->convert_styles( $component->styles ) );
		}

		// Create row node
		$node = (object) [
			'node'     => $node_id,
			'type'     => 'row',
			'parent'   => $parent_id,
			'position' => $position,
			'settings' => (object) $settings,
		];

		$this->nodes[ $node_id ] = $node;

		// Convert children - if children are columns, create column-group wrapper
		if ( ! empty( $component->children ) ) {
			// Check if children are columns
			$has_columns = false;
			foreach ( $component->children as $child ) {
				if ( $child->type === 'column' ) {
					$has_columns = true;
					break;
				}
			}

			if ( $has_columns ) {
				// Create a column-group for the columns
				$group_id = $this->generate_node_id();
				$group_node = (object) [
					'node'     => $group_id,
					'type'     => 'column-group',
					'parent'   => $node_id,
					'position' => 0,
					'settings' => (object) [],
				];
				$this->nodes[ $group_id ] = $group_node;

				// Add columns to the group
				foreach ( $component->children as $idx => $child ) {
					$this->convert_component( $child, $group_id, $idx );
				}
			} else {
				// Create default column-group and column, then add children
				$group_id = $this->generate_node_id();
				$group_node = (object) [
					'node'     => $group_id,
					'type'     => 'column-group',
					'parent'   => $node_id,
					'position' => 0,
					'settings' => (object) [],
				];
				$this->nodes[ $group_id ] = $group_node;

				$col_id = $this->generate_node_id();
				$col_node = (object) [
					'node'     => $col_id,
					'type'     => 'column',
					'parent'   => $group_id,
					'position' => 0,
					'settings' => (object) [ 'size' => 100 ],
				];
				$this->nodes[ $col_id ] = $col_node;

				// Add children to column
				foreach ( $component->children as $idx => $child ) {
					$this->convert_component( $child, $col_id, $idx );
				}
			}
		}

		return $node_id;
	}

	/**
	 * Convert row to Beaver Builder column-group
	 *
	 * @param DEVTB_Component $component Component to convert.
	 * @param string|null    $parent_id Parent node ID.
	 * @param int            $position Position.
	 * @return string Node ID.
	 */
	private function convert_column_group( DEVTB_Component $component, ?string $parent_id, int $position ): string {
		$node_id = $this->generate_node_id();
		$settings = $this->denormalize_attributes( $component->attributes );

		// Create column-group node
		$node = (object) [
			'node'     => $node_id,
			'type'     => 'column-group',
			'parent'   => $parent_id,
			'position' => $position,
			'settings' => (object) $settings,
		];

		$this->nodes[ $node_id ] = $node;

		// Convert children (columns)
		foreach ( $component->children as $idx => $child ) {
			$this->convert_component( $child, $node_id, $idx );
		}

		return $node_id;
	}

	/**
	 * Convert column component to Beaver Builder column
	 *
	 * @param DEVTB_Component $component Component to convert.
	 * @param string|null    $parent_id Parent node ID.
	 * @param int            $position Position.
	 * @return string Node ID.
	 */
	private function convert_column( DEVTB_Component $component, ?string $parent_id, int $position ): string {
		$node_id = $this->generate_node_id();
		$settings = $this->denormalize_attributes( $component->attributes );

		// Convert width to Beaver Builder size
		if ( isset( $component->attributes['width'] ) ) {
			$width = $this->parse_width( $component->attributes['width'] );
			$settings['size'] = $width;
		}

		// Add styles
		if ( ! empty( $component->styles ) ) {
			$settings = array_merge( $settings, $this->convert_styles( $component->styles ) );
		}

		// Create column node
		$node = (object) [
			'node'     => $node_id,
			'type'     => 'column',
			'parent'   => $parent_id,
			'position' => $position,
			'settings' => (object) $settings,
		];

		$this->nodes[ $node_id ] = $node;

		// Convert children (modules)
		foreach ( $component->children as $idx => $child ) {
			$this->convert_component( $child, $node_id, $idx );
		}

		return $node_id;
	}

	/**
	 * Convert component to Beaver Builder module
	 *
	 * @param DEVTB_Component $component Component to convert.
	 * @param string|null    $parent_id Parent node ID.
	 * @param int            $position Position.
	 * @return string Node ID.
	 */
	private function convert_module( DEVTB_Component $component, ?string $parent_id, int $position ): string {
		$module_type = $this->map_to_module_type( $component->type );

		if ( ! $module_type ) {
			// Default to rich-text for unknown types
			$module_type = 'rich-text';
		}

		$node_id = $this->generate_node_id();
		$settings = $this->denormalize_attributes( $component->attributes );

		// Add content to settings based on module type
		$settings = $this->add_module_content( $module_type, $settings, $component->content );

		// Add styles
		if ( ! empty( $component->styles ) ) {
			$settings = array_merge( $settings, $this->convert_styles( $component->styles ) );
		}

		// Create module node
		$node = (object) [
			'node'     => $node_id,
			'type'     => $module_type,
			'parent'   => $parent_id,
			'position' => $position,
			'settings' => (object) $settings,
		];

		$this->nodes[ $node_id ] = $node;

		return $node_id;
	}

	/**
	 * Map universal type to Beaver Builder module type
	 *
	 * @param string $universal_type Universal type.
	 * @return string|null Beaver Builder module type.
	 */
	private function map_to_module_type( string $universal_type ): ?string {
		$type_map = [
			'heading'          => 'heading',
			'text'             => 'rich-text',
			'image'            => 'photo',
			'button'           => 'button',
			'button-group'     => 'button-group',
			'html'             => 'html',
			'divider'          => 'separator',
			'icon'             => 'icon',
			'icon-list'        => 'icon-group',
			'gallery'          => 'gallery',
			'video'            => 'video',
			'audio'            => 'audio',
			'map'              => 'map',
			'slider'           => 'slider',
			'testimonial'      => 'testimonials',
			'callout'          => 'callout',
			'call-to-action'   => 'cta',
			'form'             => 'contact-form',
			'login'            => 'login-form',
			'menu'             => 'menu',
			'search'           => 'search',
			'accordion'        => 'accordion',
			'tabs'             => 'tabs',
			'pricing-table'    => 'pricing-table',
			'sidebar'          => 'sidebar',
			'countdown'        => 'countdown',
			'counter'          => 'number-counter',
			'posts'            => 'posts',
		];

		return $type_map[ $universal_type ] ?? null;
	}

	/**
	 * Add content to module settings
	 *
	 * @param string $module_type Module type.
	 * @param array  $settings Current settings.
	 * @param string $content Content to add.
	 * @return array Updated settings.
	 */
	private function add_module_content( string $module_type, array $settings, string $content ): array {
		if ( empty( $content ) ) {
			return $settings;
		}

		switch ( $module_type ) {
			case 'heading':
				$settings['heading'] = $content;
				$settings['tag'] = $settings['tag'] ?? 'h2';
				break;

			case 'rich-text':
				$settings['text'] = $content;
				break;

			case 'html':
				$settings['html'] = $content;
				break;

			case 'button':
				$settings['text'] = $content;
				break;

			case 'callout':
			case 'cta':
				$settings['text'] = $content;
				break;

			case 'testimonials':
				$settings['text'] = $content;
				break;

			default:
				$settings['content'] = $content;
				break;
		}

		return $settings;
	}

	/**
	 * Denormalize universal attributes to Beaver Builder settings
	 *
	 * @param array $attributes Universal attributes.
	 * @return array Beaver Builder settings.
	 */
	private function denormalize_attributes( array $attributes ): array {
		$settings = [];

		// Background
		if ( isset( $attributes['background-color'] ) ) {
			$settings['bg_color'] = $attributes['background-color'];
		}

		if ( isset( $attributes['background-image'] ) ) {
			$settings['bg_image'] = $attributes['background-image'];
		}

		// Padding
		if ( isset( $attributes['padding-top'] ) ) {
			$settings['padding_top'] = $this->remove_px( $attributes['padding-top'] );
		}
		if ( isset( $attributes['padding-bottom'] ) ) {
			$settings['padding_bottom'] = $this->remove_px( $attributes['padding-bottom'] );
		}
		if ( isset( $attributes['padding-left'] ) ) {
			$settings['padding_left'] = $this->remove_px( $attributes['padding-left'] );
		}
		if ( isset( $attributes['padding-right'] ) ) {
			$settings['padding_right'] = $this->remove_px( $attributes['padding-right'] );
		}

		// Margin
		if ( isset( $attributes['margin-top'] ) ) {
			$settings['margin_top'] = $this->remove_px( $attributes['margin-top'] );
		}
		if ( isset( $attributes['margin-bottom'] ) ) {
			$settings['margin_bottom'] = $this->remove_px( $attributes['margin-bottom'] );
		}

		// Border
		if ( isset( $attributes['border'] ) ) {
			$settings['border'] = $attributes['border'];
		}
		if ( isset( $attributes['border-color'] ) ) {
			$settings['border_color'] = $attributes['border-color'];
		}
		if ( isset( $attributes['border-radius'] ) ) {
			$settings['border_radius'] = $this->remove_px( $attributes['border-radius'] );
		}

		// Text
		if ( isset( $attributes['color'] ) ) {
			$settings['color'] = $attributes['color'];
		}
		if ( isset( $attributes['font-family'] ) ) {
			$settings['font_family'] = $attributes['font-family'];
		}
		if ( isset( $attributes['font-size'] ) ) {
			$settings['font_size'] = $attributes['font-size'];
		}
		if ( isset( $attributes['font-weight'] ) ) {
			$settings['font_weight'] = $attributes['font-weight'];
		}
		if ( isset( $attributes['text-align'] ) ) {
			$settings['text_align'] = $attributes['text-align'];
		}

		// Link (for buttons, CTAs)
		if ( isset( $attributes['href'] ) ) {
			$settings['link'] = $attributes['href'];
		}
		if ( isset( $attributes['target'] ) ) {
			$settings['link_target'] = $attributes['target'];
		}

		// Image (for photo modules)
		if ( isset( $attributes['src'] ) ) {
			$settings['photo_src'] = $attributes['src'];
		}
		if ( isset( $attributes['alt'] ) ) {
			$settings['alt'] = $attributes['alt'];
		}

		// Alignment
		if ( isset( $attributes['align'] ) ) {
			$settings['align'] = $attributes['align'];
		}

		// Custom classes and IDs
		if ( isset( $attributes['class'] ) ) {
			$settings['class'] = $attributes['class'];
		}
		if ( isset( $attributes['id'] ) ) {
			$settings['id'] = $attributes['id'];
		}

		return $settings;
	}

	/**
	 * Convert styles array to Beaver Builder settings
	 *
	 * @param array $styles Styles array.
	 * @return array Settings.
	 */
	private function convert_styles( array $styles ): array {
		$settings = [];

		// Map CSS properties to Beaver Builder settings
		foreach ( $styles as $property => $value ) {
			$bb_property = str_replace( '-', '_', $property );
			$settings[ $bb_property ] = $value;
		}

		return $settings;
	}

	/**
	 * Parse width value to percentage
	 *
	 * @param string $width Width value.
	 * @return float Percentage width.
	 */
	private function parse_width( string $width ): float {
		// Remove % if present
		$width = str_replace( '%', '', $width );

		// Convert to float
		return (float) $width;
	}

	/**
	 * Remove px unit from value
	 *
	 * @param string $value Value with potential px unit.
	 * @return string Value without px.
	 */
	private function remove_px( string $value ): string {
		return str_replace( 'px', '', $value );
	}

	/**
	 * Generate unique Beaver Builder node ID
	 *
	 * @return string Node ID.
	 */
	private function generate_node_id(): string {
		$this->id_counter++;
		return 'bb_node_' . uniqid() . '_' . $this->id_counter;
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
		return 'beaver-builder';
	}

	/**
	 * Get supported types
	 */
	public function get_supported_types(): array {
		return [ 'container', 'row', 'column', 'heading', 'text', 'button', 'image', 'video', 'html' ];
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
		// Return raw HTML as fallback
		return $component->content ?? '';
	}
}
