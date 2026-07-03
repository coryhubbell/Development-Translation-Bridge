<?php
/**
 * Oxygen Builder (Classic 4.x) Parser
 *
 * Parses every storage shape classic Oxygen actually uses:
 * - Flat element array with `options.ct_parent` linkage
 * - Nested JSON tree (`{"id":0,"name":"root","children":[...]}`) — the
 *   `ct_builder_json` post-meta shape
 * - `{"ct_builder_json": {...}}` / `{"ct_builder_json":{"ct_builder":[...]}}`
 *   wrappers
 * - `ct_builder_shortcodes` strings (`[ct_section ct_options='{...}']...`)
 *
 * Element vocabulary uses classic Oxygen's real names (`ct_*` core elements,
 * `oxy_*` composite elements); legacy invented aliases from earlier releases
 * are still accepted on parse for back-compat but never emitted.
 *
 * Style handling:
 * - `options.original` design props pass through in full (no allow-list),
 *   with classic Oxygen's unitless numerics normalized to px.
 * - Responsive overrides in `options.media.<breakpoint>.original`
 *   canonicalize into metadata['responsive']['styles'] (tablet → tablet,
 *   phone-portrait → phone) so they round-trip; unmapped breakpoints stay
 *   verbatim in metadata.oxygen_options.
 *
 * @package DevelopmentTranslation_Bridge
 * @subpackage Translation_Bridge
 * @since 3.2.0
 */

namespace DEVTB\TranslationBridge\Parsers;

use DEVTB\TranslationBridge\Core\DEVTB_Parser_Interface;
use DEVTB\TranslationBridge\Models\DEVTB_Component;
use DEVTB\TranslationBridge\Utils\DEVTB_Responsive_Helper;

/**
 * Class DEVTB_Oxygen_Parser
 *
 * Parse Oxygen Builder JSON into universal components.
 */
class DEVTB_Oxygen_Parser implements DEVTB_Parser_Interface {

	/**
	 * Classic Oxygen breakpoint key → canonical breakpoint.
	 *
	 * Classic Oxygen keys `options.media` by named breakpoints. `page-width`
	 * and `phone-landscape` have no canonical slot and stay verbatim in
	 * metadata.
	 */
	private const MEDIA_BREAKPOINTS = [
		'tablet'         => 'tablet',
		'phone-portrait' => 'phone',
	];

	/**
	 * Real classic Oxygen element name → universal type.
	 *
	 * @var array<string, string>
	 */
	private array $type_map = [
		// Layout.
		'ct_section'        => 'container',
		'ct_div_block'      => 'container',
		'ct_new_columns'    => 'row',
		'ct_column'         => 'column',
		'ct_inner_content'  => 'container',
		'oxy_superbox'      => 'container',
		// Content.
		'ct_headline'       => 'heading',
		'ct_text_block'     => 'text',
		'oxy_rich_text'     => 'text',
		'ct_span'           => 'text',
		'ct_code_block'     => 'code',
		'oxy_shortcode'     => 'code',
		// Interactive.
		'ct_link'           => 'link',
		'ct_link_button'    => 'button',
		'oxy_tabs'          => 'tabs',
		'oxy_tab'           => 'tab',
		'oxy_tab_content'   => 'container',
		'oxy_toggle'        => 'toggle',
		'oxy_progress_bar'  => 'progress',
		'oxy_login_form'    => 'form',
		'oxy_search_form'   => 'form',
		// Media.
		'ct_image'          => 'image',
		'ct_video'          => 'video',
		'ct_fancy_icon'     => 'icon',
		'ct_svg_icon'       => 'icon',
		'ct_slider'         => 'slider',
		'ct_slide'          => 'slide',
		'oxy_gallery'       => 'gallery',
		'oxy_map'           => 'map',
		// Composite.
		'oxy_icon_box'      => 'card',
		'oxy_testimonial_box' => 'testimonial',
		'oxy_pricing_box'   => 'pricing-table',
		'oxy_share_box'     => 'social-icons',
		'oxy_nav_menu'      => 'nav',
		'oxy_posts_grid'    => 'posts',
		'ct_reusable'       => 'container',
		'ct_separator'      => 'divider',
		// Legacy aliases from earlier Translation Bridge releases — accepted
		// on parse for back-compat, never emitted.
		'ct_columns'        => 'row',
		'ct_link_text'      => 'link',
		'ct_icon'           => 'icon',
		'ct_audio'          => 'audio',
		'ct_widget'         => 'container',
		'ct_tabs'           => 'tabs',
		'ct_tab'            => 'tab',
		'ct_accordion'      => 'accordion',
		'ct_toggle'         => 'toggle',
		'ct_progress_bar'   => 'progress',
		'ct_testimonial'    => 'testimonial',
		'ct_pricing_box'    => 'pricing-table',
		'ct_google_map'     => 'map',
		'ct_menu'           => 'nav',
		'ct_nav_menu'       => 'nav',
	];

	/**
	 * Parse Oxygen content into universal components.
	 *
	 * @param string|array $content Oxygen content in any classic storage shape.
	 * @return DEVTB_Component[] Array of parsed components.
	 */
	public function parse( $content ): array {
		// Shortcode storage (`ct_builder_shortcodes`) — starts with a literal
		// `[ct_...` / `[oxy_...` shortcode (a JSON array starts `[{` / `["`).
		if ( is_string( $content )
			&& ( strpos( ltrim( $content ), '[ct_' ) === 0 || strpos( ltrim( $content ), '[oxy_' ) === 0 ) ) {
			$elements = $this->parse_shortcodes( $content );
			return $this->parse_flat_list( $elements );
		}

		if ( is_string( $content ) ) {
			$content = json_decode( $content, true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				return [];
			}
		}

		if ( ! is_array( $content ) ) {
			return [];
		}

		// Oxygen 6 content is a nested tree with namespaced `type` keys and lives
		// under `_breakdance_data` post meta — fundamentally incompatible with the
		// classic `ct_*` model. Caller should route to DEVTB_Oxygen6_Parser.
		if ( self::is_oxygen6_payload( $content ) ) {
			return [];
		}

		// `{"ct_builder_json": ...}` wrapper (with optional `ct_builder` list).
		if ( isset( $content['ct_builder_json'] ) ) {
			$inner = $content['ct_builder_json'];
			if ( is_string( $inner ) ) {
				$inner = json_decode( $inner, true );
			}
			if ( is_array( $inner ) ) {
				$content = $inner['ct_builder'] ?? $inner;
			}
		}

		// Nested tree shape: `{"name":"root","children":[...]}` (the real
		// ct_builder_json post-meta shape) or a single element with children.
		if ( isset( $content['name'] ) || isset( $content['children'] ) ) {
			if ( ( $content['name'] ?? '' ) === 'root' ) {
				$roots = is_array( $content['children'] ?? null ) ? $content['children'] : [];
			} else {
				$roots = [ $content ];
			}

			$components = [];
			foreach ( $roots as $element ) {
				if ( is_array( $element ) ) {
					$component = $this->parse_tree_element( $element );
					if ( $component ) {
						$components[] = $component;
					}
				}
			}
			return $components;
		}

		// A bare list — elements may be flat (`ct_parent` linkage) or carry
		// nested `children`. Nested children win when present.
		$list = array_values( array_filter( $content, 'is_array' ) );
		if ( $list !== [] && $this->list_has_nested_children( $list ) ) {
			$components = [];
			foreach ( $list as $element ) {
				$component = $this->parse_tree_element( $element );
				if ( $component ) {
					$components[] = $component;
				}
			}
			return $components;
		}

		return $this->parse_flat_list( $list );
	}

	/**
	 * Whether any element in a bare list nests children inline.
	 *
	 * @param array $elements Element list.
	 * @return bool
	 */
	private function list_has_nested_children( array $elements ): bool {
		foreach ( $elements as $element ) {
			if ( ! empty( $element['children'] ) && is_array( $element['children'] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Parse a flat element list linked by `options.ct_parent`.
	 *
	 * @param array $elements Flat element list.
	 * @return DEVTB_Component[]
	 */
	private function parse_flat_list( array $elements ): array {
		$components = [];
		foreach ( $this->get_root_elements( $elements ) as $element ) {
			$component = $this->parse_oxygen_element( $element, $elements );
			if ( $component ) {
				$components[] = $component;
			}
		}
		return $components;
	}

	/**
	 * Parse the shortcode storage format into a flat element list.
	 *
	 * Classic Oxygen serializes each element as
	 * `[ct_section ct_sign_sha256="..." ct_options='{"ct_id":2,...}']` with
	 * options JSON in the `ct_options` attribute and hierarchy carried by
	 * `ct_options.ct_parent` — so a flat scan is sufficient.
	 *
	 * @param string $content Shortcode string.
	 * @return array<int, array<string, mixed>>
	 */
	private function parse_shortcodes( string $content ): array {
		$elements = [];

		if ( ! preg_match_all( '/\[(ct_[a-z0-9_]+|oxy_[a-z0-9_]+)\b([^\]]*)\]/i', $content, $matches, PREG_SET_ORDER ) ) {
			return $elements;
		}

		foreach ( $matches as $match ) {
			$name  = $match[1];
			$attrs = $match[2];

			$options = [];
			if ( preg_match( '/ct_options=(["\'])(.*?)\1/s', $attrs, $opt_match ) ) {
				$decoded = json_decode( html_entity_decode( $opt_match[2] ), true );
				if ( is_array( $decoded ) ) {
					$options = $decoded;
				}
			}

			$elements[] = [
				'id'      => $options['ct_id'] ?? count( $elements ) + 1,
				'name'    => $name,
				'options' => $options,
			];
		}

		return $elements;
	}

	/**
	 * Get root elements (elements with no parent or parent = 0).
	 *
	 * @param array $elements All elements.
	 * @return array Root elements.
	 */
	private function get_root_elements( array $elements ): array {
		$roots = [];

		foreach ( $elements as $element ) {
			$parent_id = $element['options']['ct_parent'] ?? 0;
			if ( empty( $parent_id ) || $parent_id === 0 ) {
				$roots[] = $element;
			}
		}

		return $roots;
	}

	/**
	 * Get child elements of a parent (flat `ct_parent` linkage).
	 *
	 * @param int|string $parent_id Parent element ID.
	 * @param array      $all_elements All elements.
	 * @return array Child elements.
	 */
	private function get_child_elements( $parent_id, array $all_elements ): array {
		$children = [];

		foreach ( $all_elements as $element ) {
			$element_parent = $element['options']['ct_parent'] ?? 0;
			if ( $element_parent == $parent_id && ! empty( $element_parent ) ) {
				$children[] = $element;
			}
		}

		return $children;
	}

	/**
	 * Parse a nested-tree element (children inline under `children`).
	 *
	 * @param array $element Element data with optional nested children.
	 * @return DEVTB_Component|null
	 */
	private function parse_tree_element( array $element ): ?DEVTB_Component {
		$component = $this->build_component( $element );
		if ( ! $component ) {
			return null;
		}

		if ( ! empty( $element['children'] ) && is_array( $element['children'] ) ) {
			foreach ( $element['children'] as $child ) {
				if ( is_array( $child ) ) {
					$child_component = $this->parse_tree_element( $child );
					if ( $child_component ) {
						$component->add_child( $child_component );
					}
				}
			}
		}

		return $component;
	}

	/**
	 * Parse single Oxygen element from a flat list (ct_parent linkage).
	 *
	 * @param array $element Element data.
	 * @param array $all_elements All elements.
	 * @return DEVTB_Component|null Parsed component or null.
	 */
	private function parse_oxygen_element( array $element, array $all_elements ): ?DEVTB_Component {
		$component = $this->build_component( $element );
		if ( ! $component ) {
			return null;
		}

		$ct_id    = $element['options']['ct_id'] ?? $element['id'] ?? null;
		$children = $ct_id === null ? [] : $this->get_child_elements( $ct_id, $all_elements );
		foreach ( $children as $child ) {
			$child_component = $this->parse_oxygen_element( $child, $all_elements );
			if ( $child_component ) {
				$component->add_child( $child_component );
			}
		}

		return $component;
	}

	/**
	 * Build a component from a single element (shared by both hierarchies).
	 *
	 * @param array $element Element data.
	 * @return DEVTB_Component|null
	 */
	private function build_component( array $element ): ?DEVTB_Component {
		$element_name = $element['name'] ?? '';

		if ( empty( $element_name ) || $element_name === 'root' ) {
			return null;
		}

		$universal_type = $this->map_element_type( $element_name );
		$options        = is_array( $element['options'] ?? null ) ? $element['options'] : [];
		$ct_id          = $options['ct_id'] ?? $element['id'] ?? uniqid();

		$content    = $this->extract_content( $element_name, $options );
		$attributes = $this->normalize_options( $element_name, $options );
		$styles     = isset( $options['original'] ) && is_array( $options['original'] )
			? $this->extract_styles( $options['original'] )
			: [];

		$metadata = [
			'source_framework' => 'oxygen',
			'original_type'    => $element_name,
			'ct_id'            => $ct_id,
			'oxygen_options'   => $options,
		];

		if ( ! empty( $options['nicename'] ) ) {
			$metadata['nicename'] = $options['nicename'];
		}
		if ( $element_name === 'ct_reusable' && isset( $options['view_id'] ) ) {
			$metadata['reusable_view_id'] = $options['view_id'];
		}

		// Responsive overrides: options.media.<breakpoint>.original.
		$canonical = $this->canonicalize_media( $options, $styles );
		if ( $canonical !== null ) {
			$metadata[ DEVTB_Responsive_Helper::METADATA_KEY ] = [ 'styles' => $canonical ];
		}

		return new DEVTB_Component( [
			'type'       => $universal_type,
			'category'   => $this->get_category( $universal_type ),
			'attributes' => array_merge( $styles, $attributes ),
			'content'    => $content,
			'styles'     => $styles,
			'metadata'   => $metadata,
		] );
	}

	/**
	 * Canonicalize `options.media.<breakpoint>.original` responsive overrides.
	 *
	 * @param array $options        Element options.
	 * @param array $desktop_styles Extracted base (desktop) styles.
	 * @return array<string, array<string, array<string, mixed>>>|null
	 */
	private function canonicalize_media( array $options, array $desktop_styles ): ?array {
		if ( empty( $options['media'] ) || ! is_array( $options['media'] ) ) {
			return null;
		}

		$canonical = [];
		foreach ( self::MEDIA_BREAKPOINTS as $oxygen_key => $breakpoint ) {
			$original = $options['media'][ $oxygen_key ]['original'] ?? null;
			if ( is_array( $original ) && $original !== [] ) {
				$canonical[ $breakpoint ]['default'] = $this->extract_styles( $original );
			}
		}

		if ( $canonical === [] ) {
			return null;
		}

		if ( $desktop_styles !== [] ) {
			$canonical['desktop']['default'] = $desktop_styles;
		}

		return $canonical;
	}

	/**
	 * Map Oxygen element type to universal type.
	 *
	 * @param string $element_name Oxygen element name.
	 * @return string Universal type.
	 */
	private function map_element_type( string $element_name ): string {
		return $this->type_map[ $element_name ] ?? 'container';
	}

	/**
	 * Get component category based on type.
	 *
	 * @param string $type Universal type.
	 * @return string Category.
	 */
	private function get_category( string $type ): string {
		$category_map = [
			'container'      => 'layout',
			'row'            => 'layout',
			'column'         => 'layout',
			'text'           => 'content',
			'heading'        => 'content',
			'link'           => 'interactive',
			'button'         => 'interactive',
			'form'           => 'interactive',
			'image'          => 'media',
			'icon'           => 'decorative',
			'video'          => 'media',
			'audio'          => 'media',
			'code'           => 'content',
			'divider'        => 'decorative',
			'slider'         => 'media',
			'slide'          => 'media',
			'progress'       => 'interactive',
			'testimonial'    => 'content',
			'pricing-table'  => 'content',
			'card'           => 'content',
			'map'            => 'media',
			'tabs'           => 'interactive',
			'tab'            => 'interactive',
			'accordion'      => 'interactive',
			'toggle'         => 'interactive',
			'nav'            => 'navigation',
			'menu'           => 'navigation',
			'posts'          => 'content',
			'gallery'        => 'media',
			'social-icons'   => 'social',
		];

		return $category_map[ $type ] ?? 'general';
	}

	/**
	 * Extract content from element.
	 *
	 * @param string $element_name Element name.
	 * @param array  $options Element options.
	 * @return string Content.
	 */
	private function extract_content( string $element_name, array $options ): string {
		// Check for ct_content first.
		if ( ! empty( $options['ct_content'] ) ) {
			return (string) $options['ct_content'];
		}

		// Element-specific content extraction.
		switch ( $element_name ) {
			case 'ct_headline':
				return (string) ( $options['headline_text'] ?? '' );

			case 'ct_text_block':
			case 'oxy_rich_text':
				return (string) ( $options['text'] ?? '' );

			case 'ct_link':
			case 'ct_link_text':
			case 'ct_link_button':
				return (string) ( $options['text'] ?? '' );

			case 'ct_code_block':
			case 'oxy_shortcode':
				return (string) ( $options['code'] ?? $options['full_shortcode'] ?? '' );

			case 'oxy_testimonial_box':
			case 'ct_testimonial':
				return (string) ( $options['testimonial_text'] ?? $options['quote'] ?? '' );

			case 'oxy_icon_box':
				return (string) ( $options['heading'] ?? $options['text'] ?? '' );

			default:
				return '';
		}
	}

	/**
	 * Normalize Oxygen options to universal attributes.
	 *
	 * @param string $element_name Element name.
	 * @param array  $options Oxygen element options.
	 * @return array Normalized attributes.
	 */
	private function normalize_options( string $element_name, array $options ): array {
		$attributes = [];

		// Link URL.
		if ( ! empty( $options['url'] ) ) {
			$attributes['href'] = $options['url'];
			$attributes['url']  = $options['url'];
		}

		// Link target.
		if ( ! empty( $options['target'] ) ) {
			$attributes['target'] = $options['target'];
		}

		// Image source — direct src or media-library attachment.
		if ( ! empty( $options['src'] ) ) {
			$attributes['src'] = $options['src'];
		} elseif ( ! empty( $options['image_id'] ) || ! empty( $options['attachment_id'] ) ) {
			$attributes['attachment_id'] = $options['image_id'] ?? $options['attachment_id'];
		}

		// Alt text.
		if ( ! empty( $options['alt'] ) ) {
			$attributes['alt'] = $options['alt'];
		}

		// Heading tag.
		if ( ! empty( $options['tag'] ) && preg_match( '/h(\d)/', (string) $options['tag'], $matches ) ) {
			$attributes['level'] = intval( $matches[1] );
		}

		// Icon (ct_fancy_icon / ct_svg_icon store the icon id here).
		if ( ! empty( $options['icon'] ) && is_scalar( $options['icon'] ) ) {
			$attributes['icon'] = (string) $options['icon'];
		}

		// Testimonial author fields.
		if ( in_array( $element_name, [ 'oxy_testimonial_box', 'ct_testimonial' ], true ) ) {
			if ( ! empty( $options['author'] ) ) {
				$attributes['author'] = (string) $options['author'];
			}
			if ( ! empty( $options['title'] ) ) {
				$attributes['author_title'] = (string) $options['title'];
			}
		}

		// ID and classes.
		if ( ! empty( $options['selector'] ) ) {
			$attributes['oxygen-selector'] = $options['selector'];
		}

		if ( ! empty( $options['classes'] ) ) {
			$attributes['class'] = is_array( $options['classes'] )
				? implode( ' ', $options['classes'] )
				: $options['classes'];
		}

		if ( ! empty( $options['id'] ) && is_scalar( $options['id'] ) ) {
			$attributes['id'] = $options['id'];
		}

		return $attributes;
	}

	/**
	 * Numeric-value CSS props that classic Oxygen stores unitless (px implied).
	 */
	private const UNITLESS_PX_PROPS = [
		'font-size', 'border-radius', 'border-width', 'width', 'height',
		'max-width', 'min-width', 'max-height', 'min-height', 'gap',
		'padding-top', 'padding-right', 'padding-bottom', 'padding-left',
		'margin-top', 'margin-right', 'margin-bottom', 'margin-left',
		'top', 'right', 'bottom', 'left', 'letter-spacing',
	];

	/**
	 * Extract styles from an Oxygen `original` design bag.
	 *
	 * All scalar props pass through (classic Oxygen's `original` bag holds
	 * only design props); unitless numerics on length props get px appended
	 * so the output is valid CSS.
	 *
	 * @param array $original Original styles object.
	 * @return array CSS properties.
	 */
	private function extract_styles( array $original ): array {
		$styles = [];

		foreach ( $original as $prop => $value ) {
			if ( ! is_scalar( $value ) ) {
				continue;
			}

			$value = (string) $value;
			if ( $value === '' ) {
				continue;
			}

			if ( in_array( $prop, self::UNITLESS_PX_PROPS, true ) && is_numeric( $value ) ) {
				$value .= 'px';
			}

			$styles[ (string) $prop ] = $value;
		}

		return $styles;
	}

	/**
	 * Validate Oxygen content.
	 *
	 * @param mixed $content Content to validate.
	 * @return bool True if valid.
	 */
	public function validate( $content ): bool {
		// Shortcode storage.
		if ( is_string( $content ) && strpos( ltrim( $content ), '[ct_' ) === 0 ) {
			return true;
		}

		if ( is_string( $content ) ) {
			$content = json_decode( $content, true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				return false;
			}
		}

		if ( ! is_array( $content ) || empty( $content ) ) {
			return false;
		}

		if ( self::is_oxygen6_payload( $content ) ) {
			return false;
		}

		// Wrapper / tree shapes.
		if ( isset( $content['ct_builder_json'] ) ) {
			return true;
		}
		if ( ( $content['name'] ?? '' ) === 'root' && isset( $content['children'] ) ) {
			return true;
		}

		// Flat or nested list: any ct_*/oxy_* element name marks it Oxygen.
		return $this->tree_has_oxygen_element( $content );
	}

	/**
	 * Recursively scan for a classic ct_ or oxy_ element.
	 *
	 * @param array $data Decoded branch.
	 * @return bool
	 */
	private function tree_has_oxygen_element( array $data ): bool {
		if ( isset( $data['name'] ) && is_string( $data['name'] )
			&& ( strpos( $data['name'], 'ct_' ) === 0 || strpos( $data['name'], 'oxy_' ) === 0 ) ) {
			return true;
		}

		foreach ( $data as $value ) {
			if ( is_array( $value ) && $this->tree_has_oxygen_element( $value ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get framework name.
	 *
	 * @return string Framework name.
	 */
	public function get_framework(): string {
		return 'oxygen';
	}

	/**
	 * Validate content (alias for interface compliance).
	 *
	 * @param mixed $content Content to validate.
	 * @return bool True if valid.
	 */
	public function is_valid_content( $content ): bool {
		return $this->validate( $content );
	}

	/**
	 * Get supported component types.
	 *
	 * @return array Supported types.
	 */
	public function get_supported_types(): array {
		return array_values( array_unique( array_values( $this->type_map ) ) );
	}

	/**
	 * Parse single element (public interface method).
	 *
	 * @param mixed $element Element to parse.
	 * @return DEVTB_Component|null Parsed component or null.
	 */
	public function parse_element( $element ): ?DEVTB_Component {
		if ( is_string( $element ) ) {
			$components = $this->parse( $element );
			return $components[0] ?? null;
		}

		if ( is_array( $element ) ) {
			return $this->parse_tree_element( $element );
		}

		return null;
	}

	/**
	 * Detect an Oxygen 6 (Breakdance-rewrite) payload.
	 *
	 * Oxygen 6 is a ground-up rewrite that abandons the flat `ct_*` element
	 * vocabulary and `ct_parent` linkage of Classic. It stores designs as a
	 * nested JSON tree under `_breakdance_data`, where every node carries a
	 * namespaced `type` (e.g. `EssentialElements\Heading`) and nests children
	 * inline. This helper looks for those structural markers so callers can
	 * route to DEVTB_Oxygen6_Parser instead.
	 *
	 * @param mixed $content Decoded JSON payload (array or scalar).
	 * @return bool
	 */
	public static function is_oxygen6_payload( $content ): bool {
		if ( is_string( $content ) ) {
			$decoded = json_decode( $content, true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				return false;
			}
			$content = $decoded;
		}

		if ( ! is_array( $content ) ) {
			return false;
		}

		// Wrapped payload: { "tree": ..., "_nextNodeId": ... }
		if ( isset( $content['_nextNodeId'] ) || ( isset( $content['tree'] ) && is_array( $content['tree'] ) ) ) {
			return true;
		}

		// Bare node or list of nodes: namespaced `type` (contains `\`) anywhere in tree.
		return self::tree_has_namespaced_type( $content );
	}

	/**
	 * Recursively scan for a namespaced element `type` string.
	 *
	 * @param mixed $data Decoded JSON branch.
	 * @return bool
	 */
	private static function tree_has_namespaced_type( $data ): bool {
		if ( ! is_array( $data ) ) {
			return false;
		}

		if ( isset( $data['type'] ) && is_string( $data['type'] ) && strpos( $data['type'], '\\' ) !== false ) {
			return true;
		}

		foreach ( $data as $value ) {
			if ( is_array( $value ) && self::tree_has_namespaced_type( $value ) ) {
				return true;
			}
		}

		return false;
	}
}
