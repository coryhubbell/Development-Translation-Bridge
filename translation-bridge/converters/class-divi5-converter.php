<?php
/**
 * DIVI 5 Converter.
 *
 * Emits block-comment-delimited WordPress block markup with the `divi/` namespace:
 *
 *     <!-- wp:divi/section {"module":{...},"builderVersion":"5.0.0"} -->
 *       <!-- wp:divi/row {...} -->
 *         <!-- wp:divi/column {...} -->
 *           <!-- wp:divi/text {"module":{"content":{"innerContent":{"desktop":{"value":"..."}}}}} /-->
 *         <!-- /wp:divi/column -->
 *       <!-- /wp:divi/row -->
 *     <!-- /wp:divi/section -->
 *
 * Container blocks emit opening/closing pairs; leaf blocks self-close. Content
 * values are wrapped in the desktop responsive variant — tablet/phone
 * overrides are not generated (round-tripping preserves them in metadata only).
 *
 * @package DevelopmentTranslation_Bridge
 * @subpackage Translation_Bridge
 * @since 4.3.0
 */

namespace DEVTB\TranslationBridge\Converters;

use DEVTB\TranslationBridge\Core\DEVTB_Converter_Interface;
use DEVTB\TranslationBridge\Models\DEVTB_Component;

/**
 * Class DEVTB_DIVI5_Converter
 *
 * Convert universal components to DIVI 5 block markup.
 */
class DEVTB_DIVI5_Converter implements DEVTB_Converter_Interface {

	/**
	 * Upstream framework version this converter is calibrated against.
	 */
	public const TARGET_CMS_VERSION = '5.0.0';

	/**
	 * Block namespace prefix for DIVI 5.
	 */
	public const BLOCK_NAMESPACE = 'divi/';

	/**
	 * @inheritDoc
	 */
	public function get_target_cms_version(): string {
		return self::TARGET_CMS_VERSION;
	}

	/**
	 * Universal type → DIVI 5 local block name (namespace prepended on emit).
	 *
	 * @var array<string, string>
	 */
	private array $type_map = [
		'container'      => 'section',
		'row'            => 'row',
		'column'         => 'column',
		'text'           => 'text',
		'heading'        => 'heading',
		'button'         => 'button',
		'image'          => 'image',
		'video'          => 'video',
		'audio'          => 'audio',
		'gallery'        => 'gallery',
		'divider'        => 'divider',
		'card'           => 'blurb',
		'testimonial'    => 'testimonial',
		'accordion'      => 'accordion',
		'tabs'           => 'tabs',
		'slider'         => 'slider',
		'code'           => 'code',
		'pricing-table'  => 'pricing-table',
		'counter'        => 'counter',
		'progress'       => 'progress',
		'social-icons'   => 'social-media',
		'cta'            => 'cta',
		'form'           => 'contact-form',
		'nav'            => 'menu',
		'icon'           => 'icon',
		'map'            => 'map',
		'countdown'      => 'countdown',
	];

	/**
	 * Local block names that always self-close (leaf modules).
	 *
	 * @var array<string, true>
	 */
	private array $self_closing = [
		'text'           => true,
		'heading'        => true,
		'button'         => true,
		'image'          => true,
		'video'          => true,
		'audio'          => true,
		'divider'        => true,
		'code'           => true,
		'counter'        => true,
		'progress'       => true,
		'icon'           => true,
		'map'            => true,
		'countdown'      => true,
		'cta'            => true,
		'blurb'          => true,
		'testimonial'    => true,
		'gallery'        => true,
		'contact-form'   => true,
		'pricing-table'  => true,
		'social-media'   => true,
		'menu'           => true,
	];

	/**
	 * @inheritDoc
	 */
	public function convert( $component ) {
		$components = is_array( $component ) ? $component : [ $component ];

		$markup = '';
		foreach ( $components as $comp ) {
			if ( $comp instanceof DEVTB_Component ) {
				$markup .= $this->convert_component( $comp );
			}
		}

		return $markup;
	}

	/**
	 * Convert a single component (and its children) into block-comment markup.
	 *
	 * @param DEVTB_Component $component Component to convert.
	 * @return string DIVI 5 block markup.
	 */
	public function convert_component( DEVTB_Component $component ): string {
		$local = $this->type_map[ $component->type ] ?? null;
		if ( $local === null ) {
			return '';
		}

		$attrs   = $this->build_attrs( $local, $component );
		$json    = $this->serialize_attrs( $attrs );
		$has_kids = ! empty( $component->children ) && isset( $this->type_map[ $component->children[0]->type ?? '' ] );

		// Leaf modules self-close unless caller injected actual child components.
		if ( isset( $this->self_closing[ $local ] ) && ! $has_kids ) {
			return sprintf( '<!-- wp:%s%s %s /-->%s', self::BLOCK_NAMESPACE, $local, $json, "\n" );
		}

		$inner = '';
		foreach ( $component->children as $child ) {
			$inner .= $this->convert_component( $child );
		}

		return sprintf(
			'<!-- wp:%1$s%2$s %3$s -->%4$s%5$s<!-- /wp:%1$s%2$s -->%4$s',
			self::BLOCK_NAMESPACE,
			$local,
			$json,
			"\n",
			$inner
		);
	}

	/**
	 * Build the DIVI 5 block attribute object for an element.
	 *
	 * Verified against the Divi 5 block-format docs: content lives in a
	 * TOP-LEVEL `content` attribute group; `module` holds meta/decoration.
	 *
	 *   {
	 *     "content": { "innerContent": {"desktop": {"value": ...}}, ... },
	 *     "builderVersion": "5.0.0"
	 *   }
	 *
	 * Content values are wrapped in the desktop responsive variant.
	 *
	 * @param string         $local     DIVI 5 local block name.
	 * @param DEVTB_Component $component Component being converted.
	 * @return array<string, mixed>
	 */
	private function build_attrs( string $local, DEVTB_Component $component ): array {
		$attrs   = is_array( $component->attributes ?? null ) ? $component->attributes : [];
		$content = isset( $component->content ) ? (string) $component->content : '';

		$module_content = [];

		switch ( $local ) {
			case 'text':
				if ( $content !== '' ) {
					$module_content['innerContent'] = $this->responsive( $content );
				}
				break;
			case 'heading':
				$text = $content !== '' ? $content : (string) ( $attrs['heading'] ?? $attrs['title'] ?? '' );
				if ( $text !== '' ) {
					$module_content['text'] = $this->responsive( $text );
				}
				$level = (string) ( $attrs['level'] ?? $attrs['tag'] ?? 'h2' );
				$module_content['level'] = $this->responsive( $level );
				break;
			case 'button':
				$label = $content !== '' ? $content : (string) ( $attrs['label'] ?? $attrs['text'] ?? '' );
				if ( $label !== '' ) {
					$module_content['text'] = $this->responsive( $label );
				}
				if ( isset( $attrs['url'] ) ) {
					$module_content['url'] = $this->responsive( (string) $attrs['url'] );
				}
				if ( isset( $attrs['target'] ) && (string) $attrs['target'] === '_blank' ) {
					$module_content['urlNewWindow'] = true;
				}
				break;
			case 'image':
				$src = (string) ( $attrs['image_url'] ?? $attrs['src'] ?? '' );
				if ( $src !== '' ) {
					$module_content['src'] = $this->responsive( $src );
				}
				$alt = (string) ( $attrs['alt_text'] ?? $attrs['alt'] ?? '' );
				if ( $alt !== '' ) {
					$module_content['alt'] = $this->responsive( $alt );
				}
				if ( isset( $attrs['url'] ) ) {
					$module_content['url'] = $this->responsive( (string) $attrs['url'] );
				}
				break;
			case 'code':
				if ( $content !== '' ) {
					$module_content['code'] = $this->responsive( $content );
				}
				break;
			default:
				if ( $content !== '' ) {
					$module_content['text'] = $this->responsive( $content );
				}
				break;
		}

		$result = [];
		if ( ! empty( $module_content ) ) {
			$result['content'] = $module_content;
		}
		$result['builderVersion'] = self::TARGET_CMS_VERSION;

		return $result;
	}

	/**
	 * Wrap a scalar value in DIVI 5's responsive desktop wrapper.
	 *
	 * @param string $value Scalar value.
	 * @return array<string, mixed>
	 */
	private function responsive( string $value ): array {
		return [ 'desktop' => [ 'value' => $value ] ];
	}

	/**
	 * Serialize block attrs the way WP core's serialize_block_attributes() does.
	 *
	 * HTML inside the JSON must use unicode escapes so it cannot break the
	 * surrounding block-comment delimiters (`--`, `<`, `>`).
	 *
	 * @param array<string, mixed> $attrs Block attributes.
	 * @return string
	 */
	private function serialize_attrs( array $attrs ): string {
		if ( function_exists( 'serialize_block_attributes' ) ) {
			return serialize_block_attributes( $attrs );
		}

		$encoded = wp_json_encode( $attrs, JSON_UNESCAPED_SLASHES );
		$encoded = str_replace( '--', '\\u002d\\u002d', $encoded );
		$encoded = str_replace( '<', '\\u003c', $encoded );
		$encoded = str_replace( '>', '\\u003e', $encoded );
		$encoded = str_replace( '&', '\\u0026', $encoded );
		$encoded = str_replace( '\\"', '\\u0022', $encoded );
		return $encoded;
	}

	/**
	 * @inheritDoc
	 */
	public function get_framework(): string {
		return 'divi-5';
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
		$base = 0.85;
		if ( isset( $component->metadata['source_framework'] ) && $component->metadata['source_framework'] === 'divi-5' ) {
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
		$text = $component->content !== '' ? $component->content : 'Unsupported component: ' . $component->type;
		$attrs = [
			'content'        => [ 'innerContent' => $this->responsive( $text ) ],
			'builderVersion' => self::TARGET_CMS_VERSION,
		];
		return sprintf( '<!-- wp:%stext %s /-->%s', self::BLOCK_NAMESPACE, $this->serialize_attrs( $attrs ), "\n" );
	}
}
