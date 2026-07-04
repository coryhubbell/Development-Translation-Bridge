<?php
/**
 * Universal interchange (RFC 5.0, Phase 2).
 *
 * Bidirectional bridge between the PHP engine's DEVTB_Component model and
 * the canonical universal element document specified in
 * schema/universal-element.schema.json. With this, every PHP parser can
 * emit — and every PHP converter can consume — the shared interchange
 * shape both engines conform to.
 *
 * @package DevelopmentTranslation_Bridge
 * @subpackage Translation_Bridge
 * @since 4.12.0
 */

namespace DEVTB\TranslationBridge\Core;

use DEVTB\TranslationBridge\Models\DEVTB_Component;

class DEVTB_Universal {

	/**
	 * Canonical widgetType → universal component type (reverse of
	 * DEVTB_Component::UNIVERSAL_WIDGET_TYPES primaries).
	 */
	private const COMPONENT_TYPES = [
		'text-editor'    => 'text',
		'heading'        => 'heading',
		'image'          => 'image',
		'button'         => 'button',
		'icon-list'      => 'list',
		'image-gallery'  => 'gallery',
		'video'          => 'video',
		'audio'          => 'audio',
		'divider'        => 'divider',
		'spacer'         => 'spacer',
		'testimonial'    => 'testimonial',
		'html'           => 'html',
		'icon'           => 'icon',
		'icon-box'       => 'card',
		'call-to-action' => 'cta',
		'counter'        => 'counter',
		'price-table'    => 'pricing-table',
		'alert'          => 'alert',
		'tabs'           => 'tabs',
		'accordion'      => 'accordion',
		'slides'         => 'slider',
		'social-icons'   => 'social-icons',
		'nav'            => 'nav',
		'form'           => 'form',
		'countdown'      => 'countdown',
		'google_maps'    => 'map',
		'progress'       => 'progress',
	];

	/**
	 * Wrap parsed components in a canonical universal document.
	 *
	 * @param DEVTB_Component[] $components Parsed components.
	 * @return array<string, mixed> Universal document.
	 */
	public static function components_to_document( array $components ): array {
		return [
			'elements' => array_map(
				static fn( DEVTB_Component $component ) => $component->to_universal(),
				array_filter( $components, static fn( $c ) => $c instanceof DEVTB_Component )
			),
			'version'  => '',
			'title'    => '',
			'meta'     => [ 'interchange' => 'universal' ],
		];
	}

	/**
	 * Build components from a universal document (or bare element list).
	 *
	 * @param array $document Universal document, single element, or element list.
	 * @return DEVTB_Component[]
	 */
	public static function document_to_components( array $document ): array {
		if ( isset( $document['elType'] ) ) {
			$elements = [ $document ];
		} elseif ( isset( $document['elements'] ) && is_array( $document['elements'] ) ) {
			$elements = $document['elements'];
		} else {
			$elements = $document;
		}

		$components = [];
		foreach ( $elements as $element ) {
			if ( is_array( $element ) ) {
				$component = self::element_to_component( $element );
				if ( $component ) {
					$components[] = $component;
				}
			}
		}
		return $components;
	}

	/**
	 * Convert a single universal element to a component (recursive).
	 *
	 * @param array $element Universal element dict.
	 * @return DEVTB_Component|null
	 */
	private static function element_to_component( array $element ): ?DEVTB_Component {
		$el_type  = (string) ( $element['elType'] ?? '' );
		$settings = is_array( $element['settings'] ?? null ) ? $element['settings'] : [];

		$structural = [ 'section' => 'container', 'container' => 'row', 'column' => 'column' ];

		if ( isset( $structural[ $el_type ] ) ) {
			$type       = $structural[ $el_type ];
			$attributes = [];
			$content    = '';
		} elseif ( $el_type === 'widget' ) {
			$widget_type = (string) ( $element['widgetType'] ?? '' );
			$type        = self::COMPONENT_TYPES[ $widget_type ] ?? 'html';
			[ $attributes, $content ] = self::settings_to_attributes( $widget_type, $settings );
		} else {
			return null;
		}

		$metadata = [ 'source_framework' => 'universal', 'original_type' => $element['widgetType'] ?? $el_type ];
		if ( is_array( $element['responsive'] ?? null ) ) {
			$metadata['responsive'] = $element['responsive'];
		}

		$data = [
			'type'       => $type,
			'category'   => 'general',
			'attributes' => $attributes,
			'content'    => $content,
			'metadata'   => $metadata,
		];
		if ( ! empty( $element['id'] ) ) {
			$data['id'] = (string) $element['id'];
		}
		$component = new DEVTB_Component( $data );

		foreach ( ( $element['elements'] ?? [] ) as $child ) {
			if ( is_array( $child ) ) {
				$child_component = self::element_to_component( $child );
				if ( $child_component ) {
					$component->add_child( $child_component );
				}
			}
		}

		return $component;
	}

	/**
	 * Reverse the settings vocabulary into universal attributes + content.
	 *
	 * @param string $widget_type Canonical widgetType.
	 * @param array  $settings    Elementor-style settings.
	 * @return array{0: array<string, mixed>, 1: string} [attributes, content]
	 */
	private static function settings_to_attributes( string $widget_type, array $settings ): array {
		$attrs   = [];
		$content = '';

		switch ( $widget_type ) {
			case 'heading':
				$content = (string) ( $settings['title'] ?? '' );
				$size    = (string) ( $settings['header_size'] ?? 'h2' );
				$attrs['level'] = (int) ltrim( $size, 'h' ) ?: 2;
				break;
			case 'text-editor':
				$content = (string) ( $settings['editor'] ?? ( $settings['text'] ?? '' ) );
				break;
			case 'button':
				$content = (string) ( $settings['text'] ?? '' );
				$link    = $settings['link'] ?? null;
				if ( is_array( $link ) && ! empty( $link['url'] ) ) {
					$attrs['url'] = (string) $link['url'];
					if ( ! empty( $link['is_external'] ) ) {
						$attrs['target'] = '_blank';
					}
				}
				break;
			case 'image':
				$image = $settings['image'] ?? null;
				if ( is_array( $image ) ) {
					$attrs['image_url'] = (string) ( $image['url'] ?? '' );
					if ( ! empty( $image['alt'] ) ) {
						$attrs['alt_text'] = (string) $image['alt'];
					}
				}
				break;
			case 'testimonial':
				$content = (string) ( $settings['testimonial_content'] ?? '' );
				if ( ! empty( $settings['testimonial_name'] ) ) {
					$attrs['author'] = (string) $settings['testimonial_name'];
				}
				if ( ! empty( $settings['testimonial_job'] ) ) {
					$attrs['job_title'] = (string) $settings['testimonial_job'];
				}
				break;
			case 'icon-box':
				$attrs['heading'] = (string) ( $settings['title_text'] ?? '' );
				$content          = (string) ( $settings['description_text'] ?? '' );
				break;
			case 'call-to-action':
				$attrs['heading'] = (string) ( $settings['title'] ?? '' );
				$content          = (string) ( $settings['description'] ?? '' );
				if ( ! empty( $settings['button_text'] ) ) {
					$attrs['label'] = (string) $settings['button_text'];
				}
				if ( ! empty( $settings['link']['url'] ) ) {
					$attrs['url'] = (string) $settings['link']['url'];
				}
				break;
			case 'tabs':
			case 'accordion':
				if ( is_array( $settings['tabs'] ?? null ) ) {
					$attrs['tabs'] = $settings['tabs'];
				}
				break;
			case 'counter':
				$attrs['heading'] = (string) ( $settings['title'] ?? '' );
				if ( ! empty( $settings['ending_number'] ) ) {
					$attrs['number'] = (string) $settings['ending_number'];
				}
				break;
			case 'html':
				$content = (string) ( $settings['html'] ?? '' );
				break;
			case 'video':
				$attrs['url'] = (string) ( $settings['youtube_url'] ?? '' );
				break;
			case 'alert':
				$attrs['heading'] = (string) ( $settings['alert_title'] ?? '' );
				$content          = (string) ( $settings['alert_description'] ?? '' );
				break;
			case 'icon':
				$icon = $settings['selected_icon'] ?? null;
				if ( is_array( $icon ) && ! empty( $icon['value'] ) ) {
					$attrs['icon'] = (string) $icon['value'];
				}
				break;
			case 'image-gallery':
				if ( is_array( $settings['wp_gallery'] ?? null ) ) {
					$attrs['images'] = $settings['wp_gallery'];
				}
				break;
			case 'icon-list':
				if ( is_array( $settings['icon_list'] ?? null ) ) {
					$attrs['items'] = $settings['icon_list'];
				}
				break;
			default:
				$content = (string) ( $settings['text'] ?? ( $settings['title'] ?? '' ) );
				break;
		}

		return [ $attrs, $content ];
	}
}
