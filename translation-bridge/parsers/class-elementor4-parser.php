<?php
/**
 * Elementor 4 Atomic Editor Parser.
 *
 * Elementor 4 ("Atomic Editor") replaces the v3 Section → Column → Widget
 * model with semantic atomic elements. Per Elementor's developer docs:
 *
 *     {
 *       "id": "12345678",
 *       "version": "0.0",
 *       "elType": "e-div-block",
 *       "isInner": false,
 *       "interactions": [],
 *       "settings": [],
 *       "editor_settings": [],
 *       "styles": [],
 *       "elements": []
 *     }
 *
 * Confirmed atomic element types:
 *   - Layout: `e-div-block`, `e-flexbox`, `e-grid`
 *   - Widgets: `e-heading`, `e-paragraph`, `e-button`, `e-image`, `e-form`
 *
 * The `settings` and `styles` fields are either an empty array (none defined)
 * or an object. `styles` is the structured, centralised location for local
 * style definitions including responsive variants and pseudo-state styles;
 * v1 reads it verbatim into component metadata so a follow-up patch can
 * surface it as proper responsive breakpoints.
 *
 * @package DevelopmentTranslation_Bridge
 * @subpackage Translation_Bridge
 * @since 4.3.0
 */

namespace DEVTB\TranslationBridge\Parsers;

use DEVTB\TranslationBridge\Core\DEVTB_Parser_Interface;
use DEVTB\TranslationBridge\Models\DEVTB_Component;

/**
 * Class DEVTB_Elementor4_Parser
 *
 * Parse Elementor 4 Atomic Editor JSON into universal components.
 */
class DEVTB_Elementor4_Parser implements DEVTB_Parser_Interface {

	/**
	 * Atomic `elType` (with `e-` prefix) → universal component type.
	 *
	 * @var array<string, string>
	 */
	private array $type_map = [
		'e-div-block'  => 'container',
		'e-flexbox'    => 'container',
		'e-grid'       => 'container',
		'e-section'    => 'container',
		'e-heading'    => 'heading',
		'e-paragraph'  => 'text',
		'e-text'       => 'text',
		'e-button'     => 'button',
		'e-link'       => 'button',
		'e-image'      => 'image',
		'e-video'      => 'video',
		'e-icon'       => 'icon',
		'e-form'       => 'form',
		'e-list'       => 'list',
	];

	/**
	 * @inheritDoc
	 */
	public function parse( $content ): array {
		if ( is_string( $content ) ) {
			$decoded = json_decode( $content, true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				return [];
			}
			$content = $decoded;
		}

		if ( ! is_array( $content ) ) {
			return [];
		}

		$roots = $this->extract_root_elements( $content );

		$components = [];
		foreach ( $roots as $element ) {
			$component = $this->parse_element( $element );
			if ( $component ) {
				$components[] = $component;
			}
		}

		return $components;
	}

	/**
	 * Normalise the input shape into a list of root atomic elements.
	 *
	 * @param array $content Decoded JSON payload.
	 * @return array<int, array<string, mixed>>
	 */
	private function extract_root_elements( array $content ): array {
		// Single root node.
		if ( isset( $content['elType'] ) ) {
			return [ $content ];
		}

		// Wrapped: {"content": [...]} or {"elements": [...]}
		foreach ( [ 'content', 'elements' ] as $key ) {
			if ( isset( $content[ $key ] ) && is_array( $content[ $key ] ) ) {
				return array_values( array_filter( $content[ $key ], 'is_array' ) );
			}
		}

		return array_values( array_filter( $content, 'is_array' ) );
	}

	/**
	 * @inheritDoc
	 */
	public function parse_element( $element ): ?DEVTB_Component {
		if ( ! is_array( $element ) ) {
			return null;
		}

		$el_type = isset( $element['elType'] ) ? (string) $element['elType'] : '';
		if ( $el_type === '' ) {
			return null;
		}

		$universal_type = $this->type_map[ $el_type ] ?? 'unknown';

		// `settings`/`styles`/`editor_settings` may be empty arrays OR objects per
		// the v4 docs. Normalise both shapes to associative arrays.
		$settings        = $this->as_assoc( $element['settings'] ?? null );
		$styles          = $this->as_assoc( $element['styles'] ?? null );
		$editor_settings = $this->as_assoc( $element['editor_settings'] ?? null );
		$interactions    = is_array( $element['interactions'] ?? null ) ? $element['interactions'] : [];

		$content    = $this->extract_content( $el_type, $settings );
		$attributes = $this->normalize_settings( $el_type, $settings );

		$component = new DEVTB_Component( [
			'type'       => $universal_type,
			'category'   => $this->get_category( $universal_type ),
			'attributes' => $attributes,
			'content'    => $content,
			'metadata'   => [
				'source_framework'         => 'elementor-4',
				'original_type'            => $el_type,
				'elementor4_id'            => $element['id'] ?? '',
				'elementor4_version'       => $element['version'] ?? null,
				'elementor4_isInner'       => (bool) ( $element['isInner'] ?? false ),
				'elementor4_settings'      => $settings,
				'elementor4_styles'        => $styles,
				'elementor4_editor'        => $editor_settings,
				'elementor4_interactions'  => $interactions,
			],
		] );

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
	 * Atomic v4 fields may serialise as empty arrays when no value is set, or as
	 * objects when populated. Coerce both to an associative array.
	 *
	 * @param mixed $value Raw field value.
	 * @return array
	 */
	private function as_assoc( $value ): array {
		if ( is_array( $value ) ) {
			return $value;
		}
		if ( is_object( $value ) ) {
			return (array) $value;
		}
		return [];
	}

	/**
	 * Extract the human-readable content for an atomic element.
	 *
	 * @param string $el_type   Atomic elType.
	 * @param array  $settings  Element settings.
	 * @return string
	 */
	private function extract_content( string $el_type, array $settings ): string {
		switch ( $el_type ) {
			case 'e-heading':
				return (string) ( $settings['title'] ?? $settings['text'] ?? $settings['content'] ?? '' );
			case 'e-paragraph':
			case 'e-text':
				return (string) ( $settings['text'] ?? $settings['content'] ?? $settings['paragraph'] ?? '' );
			case 'e-button':
			case 'e-link':
				return (string) ( $settings['text'] ?? $settings['label'] ?? '' );
			default:
				return (string) ( $settings['text'] ?? $settings['content'] ?? '' );
		}
	}

	/**
	 * Best-effort mapping from atomic settings → universal attributes.
	 *
	 * @param string $el_type  Atomic elType.
	 * @param array  $settings Element settings.
	 * @return array
	 */
	private function normalize_settings( string $el_type, array $settings ): array {
		$normalized = [];

		if ( $el_type === 'e-heading' ) {
			$level = $settings['tag'] ?? $settings['level'] ?? null;
			if ( $level !== null ) {
				$normalized['level'] = (string) $level;
			}
		} elseif ( $el_type === 'e-button' || $el_type === 'e-link' ) {
			$link = $settings['link'] ?? null;
			if ( is_array( $link ) && isset( $link['url'] ) ) {
				$normalized['url'] = (string) $link['url'];
				if ( ! empty( $link['target'] ) ) {
					$normalized['target'] = (string) $link['target'];
				}
			} elseif ( is_string( $link ) && $link !== '' ) {
				$normalized['url'] = $link;
			}
		} elseif ( $el_type === 'e-image' ) {
			$image = $settings['image'] ?? null;
			if ( is_array( $image ) ) {
				if ( isset( $image['url'] ) ) {
					$normalized['image_url'] = (string) $image['url'];
				}
				if ( isset( $image['alt'] ) ) {
					$normalized['alt_text'] = (string) $image['alt'];
				}
			} elseif ( isset( $settings['src'] ) ) {
				$normalized['image_url'] = (string) $settings['src'];
			}
		}

		return $normalized;
	}

	/**
	 * Map universal type → category.
	 *
	 * @param string $type Universal type.
	 * @return string
	 */
	private function get_category( string $type ): string {
		$categories = [
			'layout'      => [ 'container', 'row', 'column', 'spacer' ],
			'content'     => [ 'text', 'heading', 'image' ],
			'media'       => [ 'video', 'audio', 'gallery', 'slider' ],
			'interactive' => [ 'button', 'icon' ],
			'form'        => [ 'form' ],
		];

		foreach ( $categories as $category => $types ) {
			if ( in_array( $type, $types, true ) ) {
				return $category;
			}
		}

		return 'general';
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
	public function is_valid_content( $content ): bool {
		if ( is_string( $content ) ) {
			$decoded = json_decode( $content, true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				return false;
			}
			$content = $decoded;
		}
		return is_array( $content ) && DEVTB_Elementor_Parser::is_atomic_v4_payload( $content );
	}

	/**
	 * @inheritDoc
	 */
	public function get_supported_types(): array {
		return array_keys( $this->type_map );
	}
}
