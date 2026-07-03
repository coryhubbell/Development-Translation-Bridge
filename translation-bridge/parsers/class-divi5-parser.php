<?php
/**
 * DIVI 5 Parser.
 *
 * DIVI 5 abandoned the `[et_pb_*]` shortcode format for the WordPress block
 * serialization spec, with the namespace `divi`. Pages now look like:
 *
 *     <!-- wp:divi/placeholder -->
 *       <!-- wp:divi/section {"module":{...},"builderVersion":"5.0.0"} -->
 *         <!-- wp:divi/row {...} -->
 *           <!-- wp:divi/column {...} -->
 *             <!-- wp:divi/text {...} /-->
 *           <!-- /wp:divi/column -->
 *         <!-- /wp:divi/row -->
 *       <!-- /wp:divi/section -->
 *     <!-- /wp:divi/placeholder -->
 *
 * This parser reuses WordPress core's `parse_blocks()` when available (same as
 * the Gutenberg parser) and falls back to a regex tokeniser. Block names are
 * filtered to the `divi/*` namespace and mapped to universal component types.
 *
 * Module attributes use responsive wrappers (`{"desktop":{"value":"..."}}`);
 * for v1 the parser reads desktop values only — tablet/phone overrides are
 * preserved verbatim in component metadata so a follow-up patch can surface
 * them as responsive breakpoints.
 *
 * @package DevelopmentTranslation_Bridge
 * @subpackage Translation_Bridge
 * @since 4.3.0
 */

namespace DEVTB\TranslationBridge\Parsers;

use DEVTB\TranslationBridge\Core\DEVTB_Parser_Interface;
use DEVTB\TranslationBridge\Models\DEVTB_Component;

/**
 * Class DEVTB_DIVI5_Parser
 *
 * Parse DIVI 5 block markup into universal components.
 */
class DEVTB_DIVI5_Parser implements DEVTB_Parser_Interface {

	/**
	 * Local DIVI 5 block name (after the `divi/` prefix) → universal type.
	 *
	 * @var array<string, string>
	 */
	private array $block_map = [
		'placeholder'     => 'container',
		'section'         => 'container',
		'row'             => 'row',
		'column'          => 'column',
		'text'            => 'text',
		'heading'         => 'heading',
		'button'          => 'button',
		'image'           => 'image',
		'video'           => 'video',
		'audio'           => 'audio',
		'gallery'         => 'gallery',
		'divider'         => 'divider',
		'blurb'           => 'card',
		'testimonial'     => 'testimonial',
		'accordion'       => 'accordion',
		'accordion-item'  => 'accordion-item',
		'tabs'            => 'tabs',
		'tab'             => 'tab',
		'slider'          => 'slider',
		'slide'           => 'slide',
		'code'            => 'code',
		'pricing-table'   => 'pricing-table',
		'counter'         => 'counter',
		'progress'        => 'progress',
		'social-media'    => 'social-icons',
		'social-media-network' => 'social-icons',
		'cta'             => 'cta',
		'contact-form'    => 'form',
		'menu'            => 'nav',
		'icon'            => 'icon',
		'map'             => 'map',
		'countdown'       => 'countdown',
		'group'           => 'container',
	];

	/**
	 * @inheritDoc
	 */
	public function parse( $content ): array {
		if ( is_array( $content ) ) {
			$content = implode( "\n", $content );
		}

		if ( ! is_string( $content ) || $content === '' ) {
			return [];
		}

		$blocks = $this->parse_blocks( $content );

		$components = [];
		foreach ( $blocks as $block ) {
			$component = $this->parse_block( $block );
			if ( $component ) {
				$components[] = $component;
			}
		}

		return $components;
	}

	/**
	 * Use WordPress's `parse_blocks()` when available; fall back to manual tokenisation.
	 *
	 * @param string $content Block-comment-delimited content.
	 * @return array<int, array<string, mixed>>
	 */
	private function parse_blocks( string $content ): array {
		if ( function_exists( 'parse_blocks' ) ) {
			return parse_blocks( $content );
		}

		return $this->manual_parse_blocks( $content );
	}

	/**
	 * Manual block tokeniser (mirrors DEVTB_Gutenberg_Parser::manual_parse_blocks).
	 *
	 * @param string $content Content to tokenise.
	 * @return array<int, array<string, mixed>>
	 */
	private function manual_parse_blocks( string $content ): array {
		$blocks  = [];
		$pattern = '/<!--\s+wp:([a-z0-9\-\/]+)(\s+(\{.*?\}))?\s+(\/)?-->/s';

		preg_match_all( $pattern, $content, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER );

		foreach ( $matches as $match ) {
			$full_match      = $match[0][0];
			$offset          = $match[0][1];
			$block_name      = $match[1][0];
			$attrs_json      = isset( $match[3][0] ) ? $match[3][0] : '{}';
			$is_self_closing = isset( $match[4][0] ) && $match[4][0] === '/';

			$attributes = json_decode( $attrs_json, true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				$attributes = [];
			}

			$inner_html   = '';
			$inner_blocks = [];

			if ( ! $is_self_closing ) {
				$closing_pattern = '/<!--\s+\/wp:' . preg_quote( $block_name, '/' ) . '\s+-->/';
				if ( preg_match( $closing_pattern, $content, $closing_match, PREG_OFFSET_CAPTURE, $offset ) ) {
					$closing_offset = $closing_match[0][1];
					$content_start  = $offset + strlen( $full_match );
					$content_length = $closing_offset - $content_start;
					$inner_html     = substr( $content, $content_start, $content_length );

					if ( strpos( $inner_html, '<!-- wp:' ) !== false ) {
						$inner_blocks = $this->manual_parse_blocks( $inner_html );
						$inner_html   = preg_replace( '/<!--\s+wp:[^>]+-->/', '', $inner_html );
						$inner_html   = preg_replace( '/<!--\s+\/wp:[^>]+-->/', '', $inner_html );
					}
				}
			}

			$blocks[] = [
				'blockName'   => $block_name,
				'attrs'       => $attributes,
				'innerBlocks' => $inner_blocks,
				'innerHTML'   => trim( (string) $inner_html ),
			];
		}

		return $blocks;
	}

	/**
	 * Parse a single block into a universal component, filtering to `divi/*`.
	 *
	 * @param array $block Block data from parse_blocks().
	 * @return DEVTB_Component|null
	 */
	private function parse_block( array $block ): ?DEVTB_Component {
		$block_name = $block['blockName'] ?? '';
		if ( ! is_string( $block_name ) || strpos( $block_name, 'divi/' ) !== 0 ) {
			// Ignore non-divi blocks; recurse defensively into their innerBlocks
			// in case a divi tree is wrapped in a container.
			if ( ! empty( $block['innerBlocks'] ) ) {
				foreach ( $block['innerBlocks'] as $inner ) {
					$component = $this->parse_block( $inner );
					if ( $component ) {
						return $component;
					}
				}
			}
			return null;
		}

		$local_name     = substr( $block_name, strlen( 'divi/' ) );
		$universal_type = $this->block_map[ $local_name ] ?? 'unknown';
		$attrs          = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : [];
		$inner_html     = (string) ( $block['innerHTML'] ?? '' );

		$content = $this->extract_content( $local_name, $attrs, $inner_html );
		$attributes = $this->normalize_attributes( $local_name, $attrs );

		$component = new DEVTB_Component( [
			'type'       => $universal_type,
			'category'   => $this->get_category( $universal_type ),
			'attributes' => $attributes,
			'content'    => $content,
			'metadata'   => [
				'source_framework' => 'divi-5',
				'original_type'    => $block_name,
				'divi5_attrs'      => $attrs,
				'builder_version'  => $attrs['builderVersion'] ?? null,
			],
		] );

		// Recurse into innerBlocks (`column` → `text`, etc.)
		if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
			foreach ( $block['innerBlocks'] as $inner ) {
				$child = $this->parse_block( $inner );
				if ( $child ) {
					$component->add_child( $child );
				}
			}
		}

		return $component;
	}

	/**
	 * Extract the human-readable content for a block from its attrs / innerHTML.
	 *
	 * DIVI 5 wraps content values in a responsive object — `{"desktop":{"value":"..."}}`.
	 * v1 reads the desktop value only; other breakpoints stay in metadata.
	 *
	 * @param string $local_name DIVI 5 local block name (without `divi/` prefix).
	 * @param array  $attrs      Block attributes.
	 * @param string $inner_html Inner HTML (for container blocks).
	 * @return string
	 */
	private function extract_content( string $local_name, array $attrs, string $inner_html ): string {
		$content = $this->content_group( $attrs );

		switch ( $local_name ) {
			case 'text':
				return $this->desktop_value( $content['innerContent'] ?? $content['module'] ?? '' ) ?: $inner_html;
			case 'heading':
				return $this->desktop_value( $content['text'] ?? $content['title'] ?? '' ) ?: $inner_html;
			case 'button':
				return $this->desktop_value( $content['text'] ?? '' );
			case 'code':
				return $this->desktop_value( $content['code'] ?? $content['html'] ?? '' );
			case 'image':
				return $this->desktop_value( $content['alt'] ?? '' );
			default:
				return $inner_html !== '' ? $inner_html : (string) $this->desktop_value( $content['text'] ?? '' );
		}
	}

	/**
	 * Locate the content attribute group.
	 *
	 * Verified against the Divi 5 block-format docs: content lives in a
	 * TOP-LEVEL `content` group (`attrs.content.innerContent` etc.); the
	 * legacy proxy shape nested it under `module.content`, which is kept as
	 * a fallback for back-compat.
	 *
	 * @param array $attrs Block attributes.
	 * @return array
	 */
	private function content_group( array $attrs ): array {
		if ( is_array( $attrs['content'] ?? null ) ) {
			return $attrs['content'];
		}

		$module = is_array( $attrs['module'] ?? null ) ? $attrs['module'] : [];
		return is_array( $module['content'] ?? null ) ? $module['content'] : [];
	}

	/**
	 * Resolve a responsive DIVI 5 value to its desktop variant.
	 *
	 * @param mixed $value Responsive wrapper, scalar, or empty.
	 * @return string
	 */
	private function desktop_value( $value ): string {
		if ( is_array( $value ) ) {
			if ( isset( $value['desktop']['value'] ) ) {
				return (string) $value['desktop']['value'];
			}
			if ( isset( $value['value'] ) ) {
				return (string) $value['value'];
			}
			return '';
		}
		return (string) $value;
	}

	/**
	 * Best-effort attribute normalisation from a DIVI 5 module bag.
	 *
	 * @param string $local_name DIVI 5 local block name.
	 * @param array  $attrs      Block attributes.
	 * @return array
	 */
	private function normalize_attributes( string $local_name, array $attrs ): array {
		$content = $this->content_group( $attrs );

		$normalized = [];

		if ( $local_name === 'button' ) {
			$url = $this->desktop_value( $content['url'] ?? '' );
			if ( $url !== '' ) {
				$normalized['url'] = $url;
			}
			$label = $this->desktop_value( $content['text'] ?? '' );
			if ( $label !== '' ) {
				$normalized['label'] = $label;
			}
			if ( ! empty( $content['urlNewWindow'] ) ) {
				$normalized['target'] = '_blank';
			}
		} elseif ( $local_name === 'image' ) {
			$src = $this->desktop_value( $content['src'] ?? '' );
			if ( $src !== '' ) {
				$normalized['image_url'] = $src;
			}
			$alt = $this->desktop_value( $content['alt'] ?? '' );
			if ( $alt !== '' ) {
				$normalized['alt_text'] = $alt;
			}
			$url = $this->desktop_value( $content['url'] ?? '' );
			if ( $url !== '' ) {
				$normalized['url'] = $url;
			}
		} elseif ( $local_name === 'heading' ) {
			$level = $this->desktop_value( $content['level'] ?? $content['tag'] ?? '' );
			if ( $level !== '' ) {
				$normalized['level'] = $level;
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
			'content'     => [ 'text', 'heading', 'image', 'card', 'blockquote' ],
			'media'       => [ 'video', 'audio', 'gallery', 'slider', 'slide' ],
			'interactive' => [ 'button', 'accordion', 'accordion-item', 'tabs', 'tab' ],
			'form'        => [ 'form' ],
			'data'        => [ 'counter', 'progress', 'pricing-table' ],
			'social'      => [ 'social-icons', 'testimonial' ],
			'navigation'  => [ 'nav' ],
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
	public function parse_element( $element ): ?DEVTB_Component {
		if ( is_string( $element ) ) {
			$components = $this->parse( $element );
			return $components[0] ?? null;
		}
		if ( is_array( $element ) ) {
			return $this->parse_block( $element );
		}
		return null;
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
	public function is_valid_content( $content ): bool {
		if ( is_array( $content ) ) {
			$content = implode( "\n", $content );
		}
		if ( ! is_string( $content ) || $content === '' ) {
			return false;
		}
		return DEVTB_DIVI_Parser::is_divi5_payload( $content );
	}

	/**
	 * @inheritDoc
	 */
	public function get_supported_types(): array {
		return array_keys( $this->block_map );
	}
}
