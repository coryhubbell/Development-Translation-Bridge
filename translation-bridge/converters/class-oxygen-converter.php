<?php
/**
 * Oxygen Builder (Classic 4.x) Converter
 *
 * Emits classic Oxygen's real `ct_builder_json` post-meta shape — a nested
 * tree under a `root` node, with both inline `children` and redundant
 * `ct_id`/`ct_parent` linkage in options (matching real exports):
 *
 *     {
 *       "id": 0,
 *       "name": "root",
 *       "depth": 0,
 *       "children": [
 *         { "id": 1, "name": "ct_section",
 *           "options": { "ct_id": 1, "ct_parent": 0, "selector": "section-1-tb",
 *                        "original": {...} },
 *           "children": [ ... ] }
 *       ]
 *     }
 *
 * Element vocabulary uses classic Oxygen's real names (`ct_*` core,
 * `oxy_*` composites). Design props emit into `options.original` with
 * full passthrough (classic Oxygen stores length values unitless — px
 * suffixes are stripped); canonical responsive metadata emits into
 * `options.media.<breakpoint>.original` (tablet, phone-portrait).
 *
 * @package DevelopmentTranslation_Bridge
 * @subpackage Translation_Bridge
 * @since 3.2.0
 */

namespace DEVTB\TranslationBridge\Converters;

use DEVTB\TranslationBridge\Core\DEVTB_Converter_Interface;
use DEVTB\TranslationBridge\Models\DEVTB_Component;
use DEVTB\TranslationBridge\Utils\DEVTB_Responsive_Helper;

/**
 * Class DEVTB_Oxygen_Converter
 *
 * Convert universal components to Oxygen Builder JSON.
 */
class DEVTB_Oxygen_Converter implements DEVTB_Converter_Interface {

	/**
	 * Upstream framework version this converter is calibrated against.
	 *
	 * The converter emits the classic Oxygen 4.x JSON schema (`ct_*`/`oxy_*`
	 * element vocabulary). Oxygen 6 is a ground-up rewrite with a new schema
	 * handled by DEVTB_Oxygen6_Converter.
	 */
	public const TARGET_CMS_VERSION = '4.8.3';

	/**
	 * Canonical breakpoint → classic Oxygen `options.media` key.
	 */
	private const MEDIA_BREAKPOINTS = [
		'tablet' => 'tablet',
		'phone'  => 'phone-portrait',
	];

	/**
	 * Length props classic Oxygen stores unitless (px implied).
	 */
	private const UNITLESS_PX_PROPS = [
		'font-size', 'border-radius', 'border-width', 'width', 'height',
		'max-width', 'min-width', 'max-height', 'min-height', 'gap',
		'padding-top', 'padding-right', 'padding-bottom', 'padding-left',
		'margin-top', 'margin-right', 'margin-bottom', 'margin-left',
		'top', 'right', 'bottom', 'left', 'letter-spacing',
	];

	/**
	 * Attribute keys that are semantic (not CSS) and must not enter `original`.
	 */
	private const NON_STYLE_ATTRIBUTES = [
		'href', 'url', 'target', 'src', 'alt', 'level', 'id', 'class',
		'classes', 'icon', 'author', 'author_title', 'attachment_id',
		'oxygen-selector', 'label', 'heading', 'description', 'image_url',
		'alt_text',
	];

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
	 * Universal type → real classic Oxygen element name.
	 *
	 * @var array<string, string>
	 */
	private array $type_map = [
		'container'      => 'ct_div_block',
		'section'        => 'ct_section',
		'row'            => 'ct_new_columns',
		'column'         => 'ct_column',
		'text'           => 'ct_text_block',
		'paragraph'      => 'ct_text_block',
		'heading'        => 'ct_headline',
		'link'           => 'ct_link',
		'button'         => 'ct_link_button',
		'image'          => 'ct_image',
		'icon'           => 'ct_fancy_icon',
		'video'          => 'ct_video',
		'code'           => 'ct_code_block',
		'divider'        => 'ct_separator',
		'slider'         => 'ct_slider',
		'slide'          => 'ct_slide',
		'progress'       => 'oxy_progress_bar',
		'testimonial'    => 'oxy_testimonial_box',
		'pricing-table'  => 'oxy_pricing_box',
		'card'           => 'oxy_icon_box',
		'icon-box'       => 'oxy_icon_box',
		'map'            => 'oxy_map',
		'tabs'           => 'oxy_tabs',
		'tab'            => 'oxy_tab',
		'accordion'      => 'oxy_toggle',
		'toggle'         => 'oxy_toggle',
		'menu'           => 'oxy_nav_menu',
		'nav'            => 'oxy_nav_menu',
		'posts'          => 'oxy_posts_grid',
		'gallery'        => 'oxy_gallery',
		'social-icons'   => 'oxy_share_box',
	];

	/**
	 * Container element names that must not carry ct_content.
	 */
	private const CONTAINER_ELEMENTS = [
		'ct_section', 'ct_div_block', 'ct_new_columns', 'ct_column',
		'ct_inner_content', 'oxy_superbox',
	];

	/**
	 * Convert universal component to Oxygen Builder JSON.
	 *
	 * @param DEVTB_Component|array $component Component to convert.
	 * @return string Oxygen JSON string (ct_builder_json root-tree shape).
	 */
	public function convert( $component ) {
		$this->id_counter = 0;

		$components = is_array( $component ) ? $component : [ $component ];

		$children = [];
		foreach ( $components as $comp ) {
			if ( $comp instanceof DEVTB_Component ) {
				$element = $this->convert_component( $comp, 0, true );
				if ( $element ) {
					$children[] = $element;
				}
			}
		}

		$tree = [
			'id'       => 0,
			'name'     => 'root',
			'depth'    => 0,
			'children' => $children,
		];

		return wp_json_encode( $tree, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	}

	/**
	 * Convert single component to a nested Oxygen element.
	 *
	 * @param DEVTB_Component $component Component to convert.
	 * @param int             $parent_id Parent element ID (0 = root).
	 * @param bool            $is_root   Whether this element sits at the root level.
	 * @return array<string, mixed> Element array with nested children.
	 */
	public function convert_component( DEVTB_Component $component, int $parent_id = 0, bool $is_root = false ) {
		$element_name = $this->type_map[ $component->type ] ?? 'ct_div_block';

		// Top-level containers are sections in real Oxygen documents.
		if ( $is_root && $element_name === 'ct_div_block' && $component->type === 'container' ) {
			$element_name = 'ct_section';
		}

		$element_id = ++$this->id_counter;
		$options    = $this->create_element_options( $component, $element_name, $element_id, $parent_id );

		if ( ! empty( $component->content ) && ! in_array( $element_name, self::CONTAINER_ELEMENTS, true ) ) {
			$options = $this->add_element_content( $element_name, $options, $component->content );
		}

		$element = [
			'id'       => $element_id,
			'name'     => $element_name,
			'options'  => $options,
			'children' => [],
		];

		foreach ( $component->children as $child ) {
			$child_element = $this->convert_component( $child, $element_id );
			if ( $child_element ) {
				$element['children'][] = $child_element;
			}
		}

		return $element;
	}

	/**
	 * Create element options object.
	 *
	 * @param DEVTB_Component $component    Component.
	 * @param string          $element_name Oxygen element name.
	 * @param int             $element_id   Element ID.
	 * @param int             $parent_id    Parent ID.
	 * @return array Options object.
	 */
	private function create_element_options( DEVTB_Component $component, string $element_name, int $element_id, int $parent_id ): array {
		$options = [
			'ct_id'     => $element_id,
			'ct_parent' => $parent_id,
			'selector'  => $this->generate_selector( $element_name, $element_id ),
			'nicename'  => ucfirst( $component->type ) . ' (#' . $element_id . ')',
		];

		// Denormalize semantic attributes to Oxygen options.
		$options = array_merge( $options, $this->denormalize_attributes( $element_name, $component->attributes ) );

		// Design props → options.original (full passthrough, unitless lengths).
		$original = $this->create_original_styles( $component->attributes, $component->styles );
		if ( $original !== [] ) {
			$options['original'] = $original;
		}

		// Responsive round-trip: canonical responsive metadata →
		// options.media.<breakpoint>.original.
		$metadata  = is_array( $component->metadata ?? null ) ? $component->metadata : [];
		$canonical = $metadata[ DEVTB_Responsive_Helper::METADATA_KEY ]['styles'] ?? null;
		if ( is_array( $canonical ) ) {
			$media = [];
			foreach ( self::MEDIA_BREAKPOINTS as $breakpoint => $oxygen_key ) {
				$props = $canonical[ $breakpoint ]['default'] ?? null;
				if ( is_array( $props ) && $props !== [] ) {
					$media[ $oxygen_key ] = [ 'original' => $this->unitless( $props ) ];
				}
			}
			if ( $media !== [] ) {
				$options['media'] = $media;
			}
		}

		return $options;
	}

	/**
	 * Generate a deterministic selector for an element.
	 *
	 * Mirrors real Oxygen's `{type}-{id}-{post}` selector shape with a fixed
	 * suffix so output is reproducible (real installs use the post id).
	 *
	 * @param string $element_name Oxygen element name.
	 * @param int    $id Element ID.
	 * @return string Selector.
	 */
	private function generate_selector( string $element_name, int $id ): string {
		$base = str_replace( '_', '-', preg_replace( '/^(ct_|oxy_)/', '', $element_name ) );
		return $base . '-' . $id . '-tb';
	}

	/**
	 * Denormalize universal attributes to Oxygen options.
	 *
	 * @param string $element_name Oxygen element name.
	 * @param array  $attributes Universal attributes.
	 * @return array Oxygen options.
	 */
	private function denormalize_attributes( string $element_name, array $attributes ): array {
		$options = [];

		// Link URL.
		if ( isset( $attributes['href'] ) || isset( $attributes['url'] ) ) {
			$options['url'] = $attributes['href'] ?? $attributes['url'];
		}

		// Link target.
		if ( isset( $attributes['target'] ) ) {
			$options['target'] = $attributes['target'];
		}

		// Image source.
		if ( isset( $attributes['src'] ) || isset( $attributes['image_url'] ) ) {
			$options['src'] = $attributes['src'] ?? $attributes['image_url'];
		}
		if ( isset( $attributes['attachment_id'] ) ) {
			$options['image_id'] = $attributes['attachment_id'];
		}

		// Alt text.
		if ( isset( $attributes['alt'] ) || isset( $attributes['alt_text'] ) ) {
			$options['alt'] = $attributes['alt'] ?? $attributes['alt_text'];
		}

		// Heading level.
		if ( isset( $attributes['level'] ) ) {
			$options['tag'] = 'h' . intval( $attributes['level'] );
		}

		// Icon id.
		if ( isset( $attributes['icon'] ) ) {
			$options['icon'] = $attributes['icon'];
		}

		// Testimonial author fields.
		if ( $element_name === 'oxy_testimonial_box' ) {
			if ( isset( $attributes['author'] ) ) {
				$options['author'] = $attributes['author'];
			}
			if ( isset( $attributes['author_title'] ) ) {
				$options['title'] = $attributes['author_title'];
			}
		}

		// ID and classes.
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
	 * Create the `original` design bag.
	 *
	 * All CSS-shaped props pass through (no allow-list); semantic attributes
	 * are excluded; length values are stored unitless like real Oxygen.
	 *
	 * @param array $attributes Attributes.
	 * @param array $styles Styles.
	 * @return array Original styles object.
	 */
	private function create_original_styles( array $attributes, array $styles = [] ): array {
		$original = [];

		foreach ( array_merge( $attributes, $styles ) as $prop => $value ) {
			if ( ! is_scalar( $value ) ) {
				continue;
			}
			if ( in_array( $prop, self::NON_STYLE_ATTRIBUTES, true ) ) {
				continue;
			}
			// CSS-shaped keys only: hyphenated props or common single-word props.
			if ( strpos( (string) $prop, '-' ) === false
				&& ! in_array( $prop, [ 'color', 'width', 'height', 'display', 'opacity', 'transform', 'overflow', 'position', 'top', 'right', 'bottom', 'left', 'gap' ], true ) ) {
				continue;
			}

			$original[ (string) $prop ] = (string) $value;
		}

		return $this->unitless( $original );
	}

	/**
	 * Strip px suffixes on length props (classic Oxygen stores them unitless).
	 *
	 * @param array $props CSS props.
	 * @return array
	 */
	private function unitless( array $props ): array {
		foreach ( $props as $prop => $value ) {
			if ( is_string( $value )
				&& in_array( $prop, self::UNITLESS_PX_PROPS, true )
				&& preg_match( '/^(-?\d+(?:\.\d+)?)px$/', $value, $m ) ) {
				$props[ $prop ] = $m[1];
			}
		}
		return $props;
	}

	/**
	 * Add content to element options.
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
				$options['ct_content']    = $content;
				break;

			case 'ct_text_block':
			case 'oxy_rich_text':
				$options['text']       = $content;
				$options['ct_content'] = $content;
				break;

			case 'ct_link':
			case 'ct_link_button':
				$options['text']       = $content;
				$options['ct_content'] = $content;
				break;

			case 'ct_code_block':
				$options['code']       = $content;
				$options['ct_content'] = $content;
				break;

			case 'oxy_testimonial_box':
				$options['testimonial_text'] = $content;
				break;

			case 'oxy_icon_box':
				$options['heading'] = $content;
				break;

			default:
				$options['ct_content'] = $content;
				break;
		}

		return $options;
	}

	/**
	 * Validate that content can be converted.
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
		return array_keys( $this->type_map );
	}

	/**
	 * Check if type is supported
	 */
	public function supports_type( string $type ): bool {
		return isset( $this->type_map[ $type ] );
	}

	/**
	 * Get fallback conversion
	 */
	public function get_fallback( DEVTB_Component $component ) {
		return [
			'id'       => ++$this->id_counter,
			'name'     => 'ct_code_block',
			'options'  => [
				'ct_id'     => $this->id_counter,
				'ct_parent' => 0,
				'code'      => $component->content ?? '',
			],
			'children' => [],
		];
	}
}
