<?php
/**
 * Thrive Themes Parser (Architect / Theme Builder)
 *
 * Parses Thrive Content Builder (TCB) HTML into universal components. Thrive
 * keys per-element styling via opaque `data-css="tve-u-…"` tokens resolved by
 * an inline `<style class="tve_custom_style">` block; the parser pulls that
 * style map first and attaches resolved CSS as universal attributes on each
 * component.
 *
 * @package DevelopmentTranslation_Bridge
 * @subpackage Translation_Bridge
 * @since 4.2.0
 */

namespace DEVTB\TranslationBridge\Parsers;

use DEVTB\TranslationBridge\Core\DEVTB_Parser_Interface;
use DEVTB\TranslationBridge\Models\DEVTB_Component;

/**
 * Class DEVTB_Thrive_Parser
 */
class DEVTB_Thrive_Parser implements DEVTB_Parser_Interface {

	/**
	 * Token → CSS rule body map, populated from the trailing
	 * `<style class="tve_custom_style">…</style>` block when present.
	 *
	 * @var array<string, string>
	 */
	private array $style_map = [];

	/**
	 * @inheritDoc
	 */
	public function parse( $content ): array {
		if ( ! is_string( $content ) || empty( $content ) ) {
			return [];
		}

		$this->style_map = $this->extract_style_map( $content );

		// Strip the style block(s) before walking the structural HTML so the
		// element matcher doesn't see them as elements.
		$body = preg_replace(
			'#<style[^>]*class=["\'][^"\']*tve_custom_style[^"\']*["\'][^>]*>.*?</style>#is',
			'',
			$content
		);

		$dom = new \DOMDocument();
		libxml_use_internal_errors( true );
		// Wrap fragment so DOMDocument has a single root; suppress doctype warnings.
		$dom->loadHTML( '<?xml encoding="utf-8"?><div id="__tb_root__">' . $body . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();

		$root = $dom->getElementById( '__tb_root__' );
		if ( ! $root ) {
			return [];
		}

		$components = [];
		foreach ( $root->childNodes as $child ) {
			$component = $this->parse_node( $child );
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
		if ( $element instanceof \DOMNode ) {
			return $this->parse_node( $element );
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
		return preg_match( '/class=["\'][^"\']*(?:tcb-flex-row|tve_flt|tve_p|tve_h[1-6]|tcb-button-block|tve-u-)/i', $content ) === 1
			|| strpos( $content, 'data-css="tve-u-' ) !== false;
	}

	/**
	 * @inheritDoc
	 */
	public function get_framework(): string {
		return 'thrive';
	}

	/**
	 * @inheritDoc
	 */
	public function get_supported_types(): array {
		return [ 'row', 'column', 'heading', 'text', 'button', 'image', 'divider', 'spacer', 'icon', 'shortcode', 'html' ];
	}

	/**
	 * Build a token → CSS body map from the inline `tve_custom_style` block.
	 *
	 * @return array<string, string>
	 */
	private function extract_style_map( string $content ): array {
		if ( ! preg_match_all(
			'#<style[^>]*class=["\'][^"\']*tve_custom_style[^"\']*["\'][^>]*>(.*?)</style>#is',
			$content,
			$blocks
		) ) {
			return [];
		}

		$map = [];
		foreach ( $blocks[1] as $block_css ) {
			if ( preg_match_all( '/\.(tve-u-[a-z0-9]+)\s*\{([^}]*)\}/i', $block_css, $rules, PREG_SET_ORDER ) ) {
				foreach ( $rules as $rule ) {
					$map[ $rule[1] ] = trim( $rule[2] );
				}
			}
		}
		return $map;
	}

	private function parse_node( \DOMNode $node ): ?DEVTB_Component {
		if ( $node->nodeType !== XML_ELEMENT_NODE ) {
			return null;
		}

		/** @var \DOMElement $node */
		$tag        = strtolower( $node->tagName );
		$classes    = $this->class_list( $node );
		$data_css   = $node->getAttribute( 'data-css' );
		$attributes = [];

		if ( $data_css !== '' && isset( $this->style_map[ $data_css ] ) ) {
			$this->merge_css_attributes( $attributes, $this->style_map[ $data_css ] );
		}

		// Suite shortcode passthrough → record as type=shortcode without
		// recursing into children.
		if ( in_array( 'tcb-shortcode-passthrough', $classes, true ) ) {
			return new DEVTB_Component( [
				'type'       => 'shortcode',
				'category'   => 'content',
				'attributes' => $attributes,
				'content'    => trim( $this->inner_text( $node ) ),
				'metadata'   => [ 'source_framework' => 'thrive', 'original_type' => 'shortcode-passthrough' ],
			] );
		}

		// Layout containers
		if ( $tag === 'div' && in_array( 'tcb-flex-row', $classes, true ) ) {
			return $this->build_layout( $node, 'row', $attributes );
		}
		if ( $tag === 'div' && in_array( 'tcb-flex-col', $classes, true ) ) {
			return $this->build_layout( $node, 'column', $attributes );
		}

		// Leaf elements
		if ( preg_match( '/^h[1-6]$/', $tag ) ) {
			$attributes['level'] = (int) substr( $tag, 1 );
			return new DEVTB_Component( [
				'type'       => 'heading',
				'category'   => 'content',
				'attributes' => $attributes,
				'content'    => trim( $this->inner_text( $node ) ),
				'metadata'   => [ 'source_framework' => 'thrive', 'original_type' => $tag ],
			] );
		}

		if ( $tag === 'p' ) {
			return new DEVTB_Component( [
				'type'       => 'text',
				'category'   => 'content',
				'attributes' => $attributes,
				'content'    => trim( $this->inner_text( $node ) ),
				'metadata'   => [ 'source_framework' => 'thrive', 'original_type' => 'paragraph' ],
			] );
		}

		if ( $tag === 'div' && in_array( 'tcb-button-block', $classes, true ) ) {
			$anchor = $this->first_descendant_by_tag( $node, 'a' );
			if ( $anchor ) {
				$attributes['href'] = $anchor->getAttribute( 'href' );
			}
			return new DEVTB_Component( [
				'type'       => 'button',
				'category'   => 'interactive',
				'attributes' => $attributes,
				'content'    => trim( $this->inner_text( $node ) ),
				'metadata'   => [ 'source_framework' => 'thrive', 'original_type' => 'button' ],
			] );
		}

		if ( $tag === 'div' && in_array( 'tve_image_caption', $classes, true ) ) {
			$img = $this->first_descendant_by_tag( $node, 'img' );
			if ( $img ) {
				$attributes['src'] = $img->getAttribute( 'src' );
				$attributes['alt'] = $img->getAttribute( 'alt' );
			}
			return new DEVTB_Component( [
				'type'       => 'image',
				'category'   => 'media',
				'attributes' => $attributes,
				'content'    => '',
				'metadata'   => [ 'source_framework' => 'thrive', 'original_type' => 'image' ],
			] );
		}

		if ( $tag === 'div' && in_array( 'tve-divider', $classes, true ) ) {
			return new DEVTB_Component( [
				'type'       => 'divider',
				'category'   => 'layout',
				'attributes' => $attributes,
				'metadata'   => [ 'source_framework' => 'thrive', 'original_type' => 'divider' ],
			] );
		}

		if ( $tag === 'div' && in_array( 'thrv_responsive_spacer', $classes, true ) ) {
			return new DEVTB_Component( [
				'type'       => 'spacer',
				'category'   => 'layout',
				'attributes' => $attributes,
				'metadata'   => [ 'source_framework' => 'thrive', 'original_type' => 'spacer' ],
			] );
		}

		// Unknown wrapper — recurse and collapse children up.
		$children = $this->parse_children( $node );
		if ( ! empty( $children ) ) {
			$component = new DEVTB_Component( [
				'type'       => 'container',
				'category'   => 'layout',
				'attributes' => $attributes,
				'metadata'   => [ 'source_framework' => 'thrive', 'original_type' => $tag ],
			] );
			foreach ( $children as $child ) {
				$component->add_child( $child );
			}
			return $component;
		}

		return null;
	}

	private function build_layout( \DOMElement $node, string $universal_type, array $attributes ): DEVTB_Component {
		$component = new DEVTB_Component( [
			'type'       => $universal_type,
			'category'   => 'layout',
			'attributes' => $attributes,
			'metadata'   => [ 'source_framework' => 'thrive', 'original_type' => $universal_type ],
		] );

		foreach ( $this->parse_children( $node ) as $child ) {
			$component->add_child( $child );
		}

		return $component;
	}

	/**
	 * @return DEVTB_Component[]
	 */
	private function parse_children( \DOMNode $node ): array {
		$children = [];
		foreach ( $node->childNodes as $child ) {
			$parsed = $this->parse_node( $child );
			if ( $parsed ) {
				$children[] = $parsed;
			}
		}
		return $children;
	}

	private function class_list( \DOMElement $node ): array {
		$class = $node->getAttribute( 'class' );
		if ( $class === '' ) {
			return [];
		}
		return preg_split( '/\s+/', trim( $class ) ) ?: [];
	}

	private function first_descendant_by_tag( \DOMElement $node, string $tag ): ?\DOMElement {
		$nodes = $node->getElementsByTagName( $tag );
		if ( $nodes->length === 0 ) {
			return null;
		}
		$first = $nodes->item( 0 );
		return $first instanceof \DOMElement ? $first : null;
	}

	private function inner_text( \DOMNode $node ): string {
		// DOMNode::textContent returns concatenated text of all descendants.
		return (string) $node->textContent;
	}

	/**
	 * Parse a CSS rule body like `color:#fff;padding:80px 0 80px 0;` into
	 * normalized universal attribute keys.
	 */
	private function merge_css_attributes( array &$attributes, string $css ): void {
		foreach ( explode( ';', $css ) as $decl ) {
			$decl = trim( $decl );
			if ( $decl === '' ) {
				continue;
			}
			$parts = explode( ':', $decl, 2 );
			if ( count( $parts ) !== 2 ) {
				continue;
			}
			$prop  = strtolower( trim( $parts[0] ) );
			$value = trim( $parts[1] );

			if ( $prop === 'background-color' || $prop === 'color' || $prop === 'font-size'
				|| $prop === 'font-weight' || $prop === 'text-align' ) {
				$attributes[ $prop ] = $value;
				continue;
			}

			if ( $prop === 'padding' || $prop === 'margin' ) {
				$sides = preg_split( '/\s+/', $value ) ?: [];
				$map   = [ 0 => 'top', 1 => 'right', 2 => 'bottom', 3 => 'left' ];
				foreach ( $sides as $i => $side_value ) {
					if ( isset( $map[ $i ] ) && $side_value !== '' && $side_value !== '0' ) {
						$attributes[ $prop . '-' . $map[ $i ] ] = $side_value;
					}
				}
				continue;
			}

			if ( $prop === 'width' && preg_match( '/^([0-9.]+)%$/', $value, $m ) ) {
				$attributes['width'] = (float) $m[1];
			}
		}
	}
}
