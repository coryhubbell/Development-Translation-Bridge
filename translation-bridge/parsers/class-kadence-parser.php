<?php
/**
 * Kadence Blocks Parser
 *
 * Parses Kadence Blocks markup (Gutenberg block-comment serialization with the
 * `kadence/` namespace) into universal components. Body text is typically
 * authored as `core/*` blocks; those are also recognised and folded into the
 * same component tree.
 *
 * @package DevelopmentTranslation_Bridge
 * @subpackage Translation_Bridge
 * @since 4.2.0
 */

namespace DEVTB\TranslationBridge\Parsers;

use DEVTB\TranslationBridge\Core\DEVTB_Parser_Interface;
use DEVTB\TranslationBridge\Models\DEVTB_Component;

/**
 * Class DEVTB_Kadence_Parser
 */
class DEVTB_Kadence_Parser implements DEVTB_Parser_Interface {

	/**
	 * Map of Kadence block names → universal component types.
	 */
	private const BLOCK_TYPE_MAP = [
		'kadence/rowlayout'        => 'row',
		'kadence/column'           => 'column',
		'kadence/advancedheading'  => 'heading',
		'kadence/advancedbtn'      => 'button',
		'kadence/singlebtn'        => 'button',
		'kadence/icon'             => 'icon',
		'kadence/image'            => 'image',
		'kadence/spacer'           => 'spacer',
		'kadence/infobox'          => 'infobox',
		'kadence/tabs'             => 'tabs',
		'kadence/accordion'        => 'accordion',
		'kadence/posts'            => 'posts',
		// core/* fall-through for body content authored on a Kadence site.
		'core/paragraph'           => 'text',
		'core/heading'             => 'heading',
		'core/list'                => 'list',
		'core/quote'               => 'quote',
		'core/code'                => 'code',
		'core/image'               => 'image',
		'core/video'               => 'video',
		'core/html'                => 'html',
		'core/separator'           => 'divider',
		'core/spacer'              => 'spacer',
		'core/columns'             => 'row',
		'core/column'              => 'column',
		'core/group'               => 'container',
	];

	/**
	 * @inheritDoc
	 */
	public function parse( $content ): array {
		if ( ! is_string( $content ) || empty( $content ) ) {
			return [];
		}

		$blocks     = $this->parse_blocks( $content );
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
	 * @inheritDoc
	 */
	public function parse_element( $element ): ?DEVTB_Component {
		if ( is_array( $element ) ) {
			return $this->parse_block( $element );
		}
		return null;
	}

	/**
	 * @inheritDoc
	 */
	public function is_valid_content( $content ): bool {
		if ( ! is_string( $content ) || empty( $content ) ) {
			return false;
		}
		return strpos( $content, '<!-- wp:kadence/' ) !== false;
	}

	/**
	 * @inheritDoc
	 */
	public function get_framework(): string {
		return 'kadence';
	}

	/**
	 * @inheritDoc
	 */
	public function get_supported_types(): array {
		return array_values( array_unique( self::BLOCK_TYPE_MAP ) );
	}

	/**
	 * Walk the block-comment delimited document into a flat block list.
	 *
	 * Uses WP core `parse_blocks()` when available; falls back to a regex
	 * scanner for unit tests that run outside WordPress.
	 *
	 * @param string $content
	 * @return array<int, array{blockName: string, attrs: array, innerBlocks: array, innerHTML: string}>
	 */
	private function parse_blocks( string $content ): array {
		if ( function_exists( 'parse_blocks' ) ) {
			return parse_blocks( $content );
		}
		return $this->manual_parse_blocks( $content );
	}

	private function manual_parse_blocks( string $content ): array {
		$blocks = [];
		// Match either kadence/* or core/* block delimiters.
		$pattern = '/<!--\s+wp:([a-z0-9\-\/]+)(?:\s+(\{[^}]*\}))?\s+(\/)?-->/';

		preg_match_all( $pattern, $content, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER );

		foreach ( $matches as $match ) {
			$full_match      = $match[0][0];
			$offset          = $match[0][1];
			$block_name_raw  = $match[1][0];
			$attrs_json      = isset( $match[2][0] ) ? $match[2][0] : '{}';
			$is_self_closing = isset( $match[3][0] ) && $match[3][0] === '/';

			// Canonical WP serialization drops the `core/` prefix for core
			// blocks ("wp:paragraph" rather than "wp:core/paragraph"). Restore
			// it so the type map matches.
			$block_name = ( strpos( $block_name_raw, '/' ) === false )
				? 'core/' . $block_name_raw
				: $block_name_raw;

			$attributes = json_decode( $attrs_json, true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				$attributes = [];
			}

			$inner_html   = '';
			$inner_blocks = [];

			if ( ! $is_self_closing ) {
				$closing_pattern = '/<!--\s+\/wp:' . preg_quote( $block_name_raw, '/' ) . '\s+-->/';
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

	private function parse_block( array $block ): ?DEVTB_Component {
		$block_name = $block['blockName'] ?? '';
		if ( empty( $block_name ) ) {
			return null;
		}

		$universal_type = self::BLOCK_TYPE_MAP[ $block_name ] ?? 'text';
		$attributes     = $block['attrs'] ?? [];
		$inner_html     = $block['innerHTML'] ?? '';
		$inner_blocks   = $block['innerBlocks'] ?? [];

		$content = $this->extract_text_content( $block_name, $inner_html );

		$normalized_attrs = $this->normalize_attributes( $block_name, $attributes );

		$component = new DEVTB_Component( [
			'type'       => $universal_type,
			'category'   => $this->get_category( $universal_type ),
			'attributes' => $normalized_attrs,
			'content'    => $content,
			'metadata'   => [
				'source_framework' => 'kadence',
				'original_type'    => $block_name,
				'kadence_attrs'    => $attributes,
			],
		] );

		foreach ( $inner_blocks as $inner_block ) {
			$child = $this->parse_block( $inner_block );
			if ( $child ) {
				$component->add_child( $child );
			}
		}

		return $component;
	}

	/**
	 * Pull the visible text out of a block's inner HTML (Kadence wraps in
	 * `kt-adv-heading…`, button text in `<span class="kt-btn-text">`, etc.).
	 */
	private function extract_text_content( string $block_name, string $inner_html ): string {
		if ( $inner_html === '' ) {
			return '';
		}

		switch ( $block_name ) {
			case 'kadence/advancedheading':
				if ( preg_match( '/<h[1-6][^>]*>(.*?)<\/h[1-6]>/s', $inner_html, $m ) ) {
					return trim( html_entity_decode( strip_tags( $m[1] ) ) );
				}
				break;

			case 'kadence/advancedbtn':
			case 'kadence/singlebtn':
				if ( preg_match( '/<span[^>]*class="[^"]*kt-btn-text[^"]*"[^>]*>(.*?)<\/span>/s', $inner_html, $m ) ) {
					return trim( html_entity_decode( strip_tags( $m[1] ) ) );
				}
				break;

			case 'kadence/infobox':
				if ( preg_match( '/<div[^>]*class="[^"]*kt-blocks-info-box-text[^"]*"[^>]*>(.*?)<\/div>/s', $inner_html, $m ) ) {
					return trim( html_entity_decode( strip_tags( $m[1] ) ) );
				}
				break;

			case 'core/paragraph':
				if ( preg_match( '/<p[^>]*>(.*?)<\/p>/s', $inner_html, $m ) ) {
					return trim( html_entity_decode( strip_tags( $m[1] ) ) );
				}
				break;

			case 'core/heading':
				if ( preg_match( '/<h[1-6][^>]*>(.*?)<\/h[1-6]>/s', $inner_html, $m ) ) {
					return trim( html_entity_decode( strip_tags( $m[1] ) ) );
				}
				break;
		}

		// Generic fallback: strip tags entirely.
		return trim( html_entity_decode( strip_tags( $inner_html ) ) );
	}

	private function normalize_attributes( string $block_name, array $attributes ): array {
		$out = [];

		if ( isset( $attributes['align'] ) && is_string( $attributes['align'] ) ) {
			$out['text-align'] = $attributes['align'];
		}
		if ( isset( $attributes['textAlign'] ) && is_string( $attributes['textAlign'] ) ) {
			$out['text-align'] = $attributes['textAlign'];
		}
		if ( isset( $attributes['className'] ) ) {
			$out['class'] = (string) $attributes['className'];
		}
		if ( isset( $attributes['anchor'] ) ) {
			$out['id'] = (string) $attributes['anchor'];
		}
		if ( isset( $attributes['color'] ) && is_string( $attributes['color'] ) ) {
			$out['color'] = $attributes['color'];
		}
		if ( isset( $attributes['background'] ) && is_string( $attributes['background'] ) ) {
			$out['background-color'] = $attributes['background'];
		}
		if ( isset( $attributes['level'] ) ) {
			$out['level'] = (int) $attributes['level'];
		}
		if ( isset( $attributes['spacerHeight'] ) ) {
			$out['height'] = (int) $attributes['spacerHeight'];
		}
		if ( isset( $attributes['url'] ) && is_string( $attributes['url'] ) ) {
			$out['src'] = $attributes['url'];
		}
		if ( isset( $attributes['alt'] ) && is_string( $attributes['alt'] ) ) {
			$out['alt'] = $attributes['alt'];
		}

		// padding / margin shorthand → split into per-side keys.
		foreach ( [ 'padding', 'margin' ] as $box ) {
			if ( isset( $attributes[ $box ] ) && is_array( $attributes[ $box ] ) && count( $attributes[ $box ] ) === 4 ) {
				$sides = [ 'top', 'right', 'bottom', 'left' ];
				foreach ( $sides as $i => $side ) {
					$value = $attributes[ $box ][ $i ] ?? '';
					if ( $value !== '' && $value !== null ) {
						$out[ $box . '-' . $side ] = (string) $value;
					}
				}
			}
		}

		return $out;
	}

	private function get_category( string $universal_type ): string {
		if ( in_array( $universal_type, [ 'row', 'column', 'container' ], true ) ) {
			return 'layout';
		}
		if ( in_array( $universal_type, [ 'heading', 'text', 'paragraph', 'list', 'quote', 'code', 'html' ], true ) ) {
			return 'content';
		}
		if ( in_array( $universal_type, [ 'image', 'video', 'icon' ], true ) ) {
			return 'media';
		}
		if ( in_array( $universal_type, [ 'button' ], true ) ) {
			return 'interactive';
		}
		return 'content';
	}
}
