<?php
/**
 * Elementor 4 Atomic Editor Converter.
 *
 * Emits the atomic v4 JSON shape documented at
 * https://developers.elementor.com/docs/data-structure/atomic-elements/ :
 *
 *     {
 *       "id": "<hex>",
 *       "version": "0.0",
 *       "elType": "e-div-block",
 *       "isInner": false,
 *       "interactions": [],
 *       "settings": {},
 *       "editor_settings": {},
 *       "styles": {},
 *       "elements": [ <child>, ... ]
 *     }
 *
 * Children nest directly inside `elements` (same as v3). The settings/styles
 * bags are populated only when we have data — otherwise emitted as empty
 * objects to match the documented shape ("empty array if not defined, or an
 * object" — we standardise on the object form so downstream consumers always
 * see a stable key→value structure).
 *
 * @package DevelopmentTranslation_Bridge
 * @subpackage Translation_Bridge
 * @since 4.3.0
 */

namespace DEVTB\TranslationBridge\Converters;

use DEVTB\TranslationBridge\Core\DEVTB_Converter_Interface;
use DEVTB\TranslationBridge\Models\DEVTB_Component;
use DEVTB\TranslationBridge\Utils\DEVTB_JSON_Helper;

/**
 * Class DEVTB_Elementor4_Converter
 *
 * Convert universal components to Elementor 4 Atomic Editor JSON.
 */
class DEVTB_Elementor4_Converter implements DEVTB_Converter_Interface {

	/**
	 * Upstream framework version this converter is calibrated against.
	 *
	 * Atomic Editor introduced in Elementor 4.0; the per-element `version` field
	 * documented as `"0.0"` reflects the atomic schema iteration, not the
	 * Elementor release. We track the release.
	 */
	public const TARGET_CMS_VERSION = '4.0.0';

	/**
	 * Atomic schema version emitted on each element.
	 */
	public const ATOMIC_SCHEMA_VERSION = '0.0';

	/**
	 * @inheritDoc
	 */
	public function get_target_cms_version(): string {
		return self::TARGET_CMS_VERSION;
	}

	/**
	 * Universal type → atomic `elType`.
	 *
	 * @var array<string, string>
	 */
	private array $type_map = [
		'container'  => 'e-div-block',
		'row'        => 'e-flexbox',
		'column'     => 'e-div-block',
		'text'       => 'e-paragraph',
		'heading'    => 'e-heading',
		'button'     => 'e-button',
		'link'       => 'e-button',
		'image'      => 'e-image',
		// Real atomic element types verified against the open-source
		// elementor repo (modules/atomic-widgets/elements): there is no
		// e-video / e-icon / e-list — video splits into e-youtube /
		// e-self-hosted-video and icons are e-svg.
		'video'      => 'e-youtube',
		'icon'       => 'e-svg',
		'form'       => 'e-form',
		'divider'    => 'e-divider',
		// Fallbacks for richer universal types — atomic v4 doesn't yet expose
		// dedicated widgets for these so map to the closest layout/text shape.
		'list'       => 'e-div-block',
		'card'       => 'e-div-block',
		'gallery'    => 'e-div-block',
		'accordion'  => 'e-div-block',
		'tabs'       => 'e-div-block',
		'slider'     => 'e-div-block',
		'spacer'     => 'e-div-block',
	];

	/**
	 * @inheritDoc
	 */
	public function convert( $component ) {
		$components = is_array( $component ) ? $component : [ $component ];

		$elements = [];
		foreach ( $components as $comp ) {
			if ( $comp instanceof DEVTB_Component ) {
				$node = $this->convert_component( $comp );
				if ( $node ) {
					$elements[] = $node;
				}
			}
		}

		return wp_json_encode( $elements, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	}

	/**
	 * Convert a single component to an atomic v4 element (with nested children).
	 *
	 * @param DEVTB_Component $component Component to convert.
	 * @return array<string, mixed>|null
	 */
	public function convert_component( DEVTB_Component $component ): ?array {
		$el_type = $this->type_map[ $component->type ] ?? null;
		if ( $el_type === null ) {
			return null;
		}

		$settings = $this->build_settings( $el_type, $component );
		$styles   = $this->build_styles( $component );

		// Local styles apply through the classes prop referencing the style
		// definition id (see atomic-widgets/styles in the elementor repo).
		if ( ! empty( $styles ) ) {
			$settings['classes'] = $this->prop( 'classes', array_keys( $styles ) );
		}

		$node = [
			'id'              => $this->generate_id(),
			'version'         => self::ATOMIC_SCHEMA_VERSION,
			'elType'          => $el_type,
			'isInner'         => false,
			'interactions'    => [],
			'settings'        => (object) $settings,
			'editor_settings' => (object) [],
			'styles'          => (object) $styles,
			'elements'        => [],
		];

		if ( ! empty( $component->children ) ) {
			foreach ( $component->children as $child ) {
				$child_node = $this->convert_component( $child );
				if ( $child_node ) {
					$child_node['isInner']    = true;
					$node['elements'][]       = $child_node;
				}
			}
		}

		return $node;
	}

	/**
	 * Build the atomic `settings` bag for an element.
	 *
	 * @param string         $el_type   Atomic elType.
	 * @param DEVTB_Component $component Component being converted.
	 * @return array
	 */
	private function build_settings( string $el_type, DEVTB_Component $component ): array {
		$attrs   = is_array( $component->attributes ?? null ) ? $component->attributes : [];
		$content = isset( $component->content ) ? (string) $component->content : '';

		$settings = [];

		switch ( $el_type ) {
			case 'e-heading':
				$title             = $content !== '' ? $content : (string) ( $attrs['heading'] ?? '' );
				$settings['title'] = $this->html_prop( $title );
				$settings['tag']   = $this->string_prop( (string) ( $attrs['level'] ?? $attrs['tag'] ?? 'h2' ) );
				break;
			case 'e-paragraph':
				// Real prop key is `paragraph` (Html_V3), not `text` — see
				// atomic-widgets/elements/atomic-paragraph.
				$text                  = $content !== '' ? $content : (string) ( $attrs['text'] ?? '' );
				$settings['paragraph'] = $this->html_prop( $text );
				break;
			case 'e-button':
				$settings['text'] = $this->html_prop( $content !== '' ? $content : (string) ( $attrs['label'] ?? $attrs['text'] ?? '' ) );
				if ( isset( $attrs['url'] ) ) {
					$settings['link'] = $this->link_prop(
						(string) $attrs['url'],
						isset( $attrs['target'] ) ? (string) $attrs['target'] : '_self'
					);
				}
				break;
			case 'e-image':
				$src = (string) ( $attrs['image_url'] ?? $attrs['src'] ?? '' );
				if ( $src !== '' ) {
					$settings['image'] = $this->image_prop( $src, (string) ( $attrs['alt_text'] ?? $attrs['alt'] ?? '' ) );
				}
				break;
			case 'e-svg':
				if ( isset( $attrs['icon'] ) && $attrs['icon'] !== '' ) {
					$settings['svg'] = $this->string_prop( (string) $attrs['icon'] );
				}
				break;
			default:
				if ( $content !== '' ) {
					$settings['paragraph'] = $this->html_prop( $content );
				}
				break;
		}

		// Pass through other scalar attributes so they aren't silently dropped.
		foreach ( $attrs as $key => $value ) {
			if ( array_key_exists( $key, $settings ) || in_array( $key, [ 'heading', 'label', 'text', 'level', 'tag', 'url', 'target', 'image_url', 'src', 'alt_text', 'alt', 'icon' ], true ) ) {
				continue;
			}
			if ( is_scalar( $value ) ) {
				$settings[ $key ] = $this->wrap_scalar( $value );
			}
		}

		return $settings;
	}

	/**
	 * Wrap a value in Elementor's typed-prop envelope.
	 *
	 * Every stored atomic setting is a `{"$$type": <key>, "value": ...}` object
	 * (verified against the open-source elementor repo, modules/atomic-widgets).
	 *
	 * @param string $type  Prop type key (string, html-v3, link, image, ...).
	 * @param mixed  $value Prop value.
	 * @return array{"$$type": string, value: mixed}
	 */
	private function prop( string $type, $value ): array {
		return [
			'$$type' => $type,
			'value'  => $value,
		];
	}

	/**
	 * `string` prop wrapper.
	 *
	 * @param string $value String value.
	 * @return array
	 */
	private function string_prop( string $value ): array {
		return $this->prop( 'string', $value );
	}

	/**
	 * `html-v3` prop: content nests a string prop (Html_V3_Prop_Type).
	 *
	 * @param string $value HTML/text content.
	 * @return array
	 */
	private function html_prop( string $value ): array {
		return $this->prop( 'html-v3', [ 'content' => $this->string_prop( $value ) ] );
	}

	/**
	 * `link` prop: destination url prop + isTargetBlank boolean prop
	 * (Link_Prop_Type stores `destination`, not `url`).
	 *
	 * @param string $url    Destination URL.
	 * @param string $target Legacy target value (`_blank` → isTargetBlank).
	 * @return array
	 */
	private function link_prop( string $url, string $target = '_self' ): array {
		return $this->prop( 'link', [
			'destination'   => $this->prop( 'url', $url ),
			'isTargetBlank' => $this->prop( 'boolean', $target === '_blank' ),
		] );
	}

	/**
	 * `image` prop: src (image-src → id/url/alt) + size (Image_Prop_Type).
	 *
	 * @param string $url Image URL.
	 * @param string $alt Alt text.
	 * @return array
	 */
	private function image_prop( string $url, string $alt = '' ): array {
		$src = [
			'id'  => null,
			'url' => $this->prop( 'url', $url ),
		];
		if ( $alt !== '' ) {
			$src['alt'] = $this->string_prop( $alt );
		}
		return $this->prop( 'image', [
			'src'  => $this->prop( 'image-src', $src ),
			'size' => $this->string_prop( 'full' ),
		] );
	}

	/**
	 * Wrap an arbitrary scalar in the matching primitive prop type.
	 *
	 * @param mixed $value Scalar value.
	 * @return mixed
	 */
	private function wrap_scalar( $value ) {
		if ( is_bool( $value ) ) {
			return $this->prop( 'boolean', $value );
		}
		if ( is_int( $value ) || is_float( $value ) ) {
			return $this->prop( 'number', $value );
		}
		if ( is_string( $value ) ) {
			return $this->string_prop( $value );
		}
		return $value;
	}

	/**
	 * Build the atomic `styles` object as a real Style_Definition entry:
	 * `{id, type, label, variants: [{meta: {breakpoint, state}, props}]}`.
	 *
	 * @param DEVTB_Component $component Component being converted.
	 * @return array
	 */
	private function build_styles( DEVTB_Component $component ): array {
		$styles = is_array( $component->styles ?? null ) ? $component->styles : [];
		if ( empty( $styles ) ) {
			return [];
		}
		$style_id = 'e-' . $this->generate_id();
		return [
			$style_id => [
				'id'       => $style_id,
				'type'     => 'class',
				'label'    => 'local',
				'variants' => [
					[
						'meta'  => [
							'breakpoint' => 'desktop',
							'state'      => null,
						],
						'props' => $styles,
					],
				],
			],
		];
	}

	/**
	 * Generate an 8-char hex id (matches Elementor's v3/v4 id convention).
	 *
	 * @return string
	 */
	private function generate_id(): string {
		if ( class_exists( DEVTB_JSON_Helper::class ) && method_exists( DEVTB_JSON_Helper::class, 'generate_elementor_id' ) ) {
			return DEVTB_JSON_Helper::generate_elementor_id();
		}
		return substr( md5( uniqid( '', true ) ), 0, 8 );
	}

	/**
	 * @inheritDoc
	 */
	public function get_framework(): string {
		return 'elementor-4';
	}

	/**
	 * @inheritDoc
	 */
	public function get_supported_types(): array {
		return array_keys( $this->type_map );
	}

	/**
	 * @inheritDoc
	 */
	public function can_convert( DEVTB_Component $component ): bool {
		return isset( $this->type_map[ $component->type ] );
	}

	/**
	 * @inheritDoc
	 */
	public function get_confidence( DEVTB_Component $component ): float {
		if ( ! $this->can_convert( $component ) ) {
			return 0.0;
		}
		$base = 0.75;
		if ( isset( $component->metadata['source_framework'] ) && $component->metadata['source_framework'] === 'elementor-4' ) {
			$base = 0.95;
		}
		return $base;
	}

	/**
	 * @inheritDoc
	 */
	public function supports_type( string $type ): bool {
		return isset( $this->type_map[ $type ] );
	}

	/**
	 * @inheritDoc
	 */
	public function get_fallback( DEVTB_Component $component ) {
		return [
			'id'              => $this->generate_id(),
			'version'         => self::ATOMIC_SCHEMA_VERSION,
			'elType'          => 'e-paragraph',
			'isInner'         => false,
			'interactions'    => [],
			'settings'        => (object) [ 'paragraph' => $this->html_prop( $component->content !== '' ? $component->content : 'Unsupported component: ' . $component->type ) ],
			'editor_settings' => (object) [],
			'styles'          => (object) [],
			'elements'        => [],
		];
	}
}
