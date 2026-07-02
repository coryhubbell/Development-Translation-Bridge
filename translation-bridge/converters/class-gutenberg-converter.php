<?php
/**
 * Gutenberg Block Editor Converter
 *
 * Intelligent universal to Gutenberg converter featuring:
 * - HTML comment block delimiter generation
 * - JSON attribute encoding
 * - 50+ core block type support
 * - Nested block (innerBlocks) generation
 * - Block serialization
 *
 * @package DevelopmentTranslation_Bridge
 * @subpackage Translation_Bridge
 * @since 3.2.0
 */

namespace DEVTB\TranslationBridge\Converters;

use DEVTB\TranslationBridge\Core\DEVTB_Converter_Interface;
use DEVTB\TranslationBridge\Models\DEVTB_Component;
use DEVTB\TranslationBridge\Utils\DEVTB_HTML_Helper;

/**
 * Class DEVTB_Gutenberg_Converter
 *
 * Convert universal components to Gutenberg block markup.
 */
class DEVTB_Gutenberg_Converter implements DEVTB_Converter_Interface {

	/**
	 * Upstream framework (WordPress core) version this converter is calibrated against.
	 */
	public const TARGET_CMS_VERSION = '6.9.0';

	/**
	 * @inheritDoc
	 */
	public function get_target_cms_version(): string {
		return self::TARGET_CMS_VERSION;
	}

	/**
	 * Convert universal component to Gutenberg block markup
	 *
	 * @param DEVTB_Component|array $component Component to convert.
	 * @return string Gutenberg block markup.
	 */
	public function convert( $component ) {
		if ( is_array( $component ) ) {
			$components = $component;
		} else {
			$components = [ $component ];
		}

		$output = '';

		foreach ( $components as $comp ) {
			if ( $comp instanceof DEVTB_Component ) {
				$output .= $this->convert_component( $comp );
			}
		}

		return $output;
	}

	/**
	 * Convert single component to Gutenberg block
	 *
	 * @param DEVTB_Component $component Component to convert.
	 * @return string Gutenberg block markup.
	 */
	public function convert_component( DEVTB_Component $component ): string {
		$type = $component->type;

		if ( $type === 'container' || $type === 'row' || $type === 'section' ) {
			return $this->convert_container( $component );
		}

		if ( $type === 'column' ) {
			return $this->convert_column( $component );
		}

		if ( $this->is_compound_type( $type ) ) {
			return $this->convert_compound( $component );
		}

		if ( $this->is_marker_type( $type ) ) {
			return $this->convert_as_marker( $component );
		}

		return $this->convert_block( $component );
	}

	/**
	 * Compound widget types produce multiple Gutenberg blocks (e.g. tabs → headings + groups).
	 */
	private function is_compound_type( string $type ): bool {
		return in_array(
			$type,
			[ 'tabs', 'accordion', 'card', 'cta', 'counter', 'testimonial', 'pricing-table', 'alert' ],
			true
		);
	}

	/**
	 * Marker widget types have no clean Gutenberg equivalent and are preserved as
	 * core/html with a devtb annotation comment so editors can still see and edit them.
	 */
	private function is_marker_type( string $type ): bool {
		return in_array(
			$type,
			[ 'slider', 'form', 'countdown', 'portfolio', 'toc', 'map', 'progress', 'rating', 'unknown' ],
			true
		);
	}

	/**
	 * Convert container to Gutenberg group/columns block
	 *
	 * @param DEVTB_Component $component Component to convert.
	 * @return string Block markup.
	 */
	private function convert_container( DEVTB_Component $component ): string {
		// Check if container has columns
		$has_columns = false;
		foreach ( $component->children as $child ) {
			if ( $child->type === 'column' ) {
				$has_columns = true;
				break;
			}
		}

		if ( $has_columns ) {
			// Use columns block
			return $this->convert_columns( $component );
		} else {
			// Use group block
			return $this->convert_group( $component );
		}
	}

	/**
	 * Convert to Gutenberg columns block
	 *
	 * @param DEVTB_Component $component Component to convert.
	 * @return string Block markup.
	 */
	private function convert_columns( DEVTB_Component $component ): string {
		$attributes = $this->denormalize_attributes( $component->attributes );

		$opening = $this->create_block_delimiter( 'core/columns', $attributes );
		$content = '<div class="wp-block-columns">';

		// Convert child columns
		foreach ( $component->children as $child ) {
			if ( $child->type === 'column' ) {
				$content .= "\n" . $this->convert_column( $child );
			}
		}

		$content .= "\n</div>";
		$closing = $this->create_closing_delimiter( 'core/columns' );

		return $opening . "\n" . $content . "\n" . $closing . "\n\n";
	}

	/**
	 * Convert to Gutenberg column block
	 *
	 * @param DEVTB_Component $component Component to convert.
	 * @return string Block markup.
	 */
	private function convert_column( DEVTB_Component $component ): string {
		$attributes = $this->denormalize_attributes( $component->attributes );

		// Extract width if present — canonical core/column width is a string with unit ("50%").
		if ( isset( $component->attributes['width'] ) ) {
			$attributes['width'] = floatval( $component->attributes['width'] ) . '%';
		}

		$opening = $this->create_block_delimiter( 'core/column', $attributes );
		$content = '<div class="wp-block-column">';

		// Convert children
		foreach ( $component->children as $child ) {
			$content .= "\n" . $this->convert_component( $child );
		}

		$content .= "\n</div>";
		$closing = $this->create_closing_delimiter( 'core/column' );

		return $opening . "\n" . $content . "\n" . $closing;
	}

	/**
	 * Convert to Gutenberg group block
	 *
	 * @param DEVTB_Component $component Component to convert.
	 * @return string Block markup.
	 */
	private function convert_group( DEVTB_Component $component ): string {
		$attributes = $this->denormalize_attributes( $component->attributes );

		$opening = $this->create_block_delimiter( 'core/group', $attributes );
		$content = '<div class="wp-block-group">';

		// Convert children
		foreach ( $component->children as $child ) {
			$content .= "\n" . $this->convert_component( $child );
		}

		$content .= "\n</div>";
		$closing = $this->create_closing_delimiter( 'core/group' );

		return $opening . "\n" . $content . "\n" . $closing . "\n\n";
	}

	/**
	 * Convert component to Gutenberg block
	 *
	 * @param DEVTB_Component $component Component to convert.
	 * @return string Block markup.
	 */
	private function convert_block( DEVTB_Component $component ): string {
		$block_name = $this->map_to_block_type( $component->type );

		if ( ! $block_name ) {
			// Unknown types are preserved as core/html with a visible devtb marker
			// rather than silently collapsing to an empty paragraph.
			return $this->convert_as_marker( $component );
		}

		$attributes = $this->denormalize_attributes( $component->attributes );

		if ( $block_name === 'core/paragraph' && $this->should_preserve_rich_text_as_html_block( (string) ( $component->content ?? '' ) ) ) {
			$block_name  = 'core/html';
			$attributes = [];
		}

		// Add content to attributes if needed
		$attributes = $this->add_block_content( $block_name, $attributes, $component );

		// Generate inner HTML
		$inner_html = $this->generate_inner_html( $block_name, $component );

		// Create block markup
		$opening = $this->create_block_delimiter( $block_name, $attributes );
		$closing = $this->create_closing_delimiter( $block_name );

		return $opening . "\n" . $inner_html . "\n" . $closing . "\n\n";
	}

	/**
	 * Map universal type to Gutenberg block type
	 *
	 * @param string $universal_type Universal type.
	 * @return string|null Gutenberg block type.
	 */
	private function map_to_block_type( string $universal_type ): ?string {
		$type_map = [
			'text'          => 'core/paragraph',
			'paragraph'     => 'core/paragraph',
			'heading'       => 'core/heading',
			'image'         => 'core/image',
			'gallery'       => 'core/gallery',
			'list'          => 'core/list',
			'quote'         => 'core/quote',
			'blockquote'    => 'core/quote',
			'code'          => 'core/code',
			'table'         => 'core/table',
			'button'        => 'core/button',
			'button-group'  => 'core/buttons',
			'divider'       => 'core/separator',
			'spacer'        => 'core/spacer',
			'html'          => 'core/html',
			'video'         => 'core/video',
			'audio'         => 'core/audio',
			'file'          => 'core/file',
			'embed'         => 'core/embed',
			// Parser produces 'social-icons' for social-icons / share-buttons widgets.
			'social-icons'  => 'core/social-links',
			'social-links'  => 'core/social-links',
			'social-link'   => 'core/social-link',
			'search'        => 'core/search',
			// Parser produces 'nav' for nav-menu widget.
			'nav'           => 'core/navigation',
			'menu'          => 'core/navigation',
			'menu-item'     => 'core/navigation-link',
			'icon'          => 'core/html',
		];

		return $type_map[ $universal_type ] ?? null;
	}

	/**
	 * Add content to block attributes
	 *
	 * @param string         $block_name Block name.
	 * @param array          $attributes Current attributes.
	 * @param DEVTB_Component $component Component.
	 * @return array Updated attributes.
	 */
	private function add_block_content( string $block_name, array $attributes, DEVTB_Component $component ): array {
		$attrs = $component->attributes;

		if ( $block_name === 'core/heading' ) {
			$level = $this->parse_heading_level( $attrs['level'] ?? ( $attrs['header_size'] ?? 2 ) );
			$attributes['level'] = $level;
		}

		if ( $block_name === 'core/image' ) {
			$url = $attrs['image_url'] ?? ( $attrs['src'] ?? ( $attrs['url'] ?? '' ) );
			if ( ! empty( $url ) ) {
				$attributes['url'] = $url;
			}
			$alt = $attrs['alt_text'] ?? ( $attrs['alt'] ?? '' );
			if ( ! empty( $alt ) ) {
				$attributes['alt'] = $alt;
			}
			if ( ! empty( $attrs['caption'] ) ) {
				$attributes['caption'] = $attrs['caption'];
			}
		}

		if ( $block_name === 'core/button' ) {
			$url = $attrs['url'] ?? ( $attrs['href'] ?? '' );
			if ( ! empty( $url ) ) {
				$attributes['url'] = $url;
			}
			if ( ! empty( $attrs['target'] ) ) {
				$attributes['linkTarget'] = $attrs['target'];
			}
			if ( ! empty( $attrs['rel'] ) ) {
				$attributes['rel'] = $attrs['rel'];
			}
		}

		if ( in_array( $block_name, [ 'core/video', 'core/audio' ], true ) ) {
			$src = $attrs['src'] ?? ( $attrs['url'] ?? ( $attrs['video_url'] ?? '' ) );
			if ( ! empty( $src ) ) {
				$attributes['src'] = $src;
			}
		}

		if ( $block_name === 'core/gallery' ) {
			$ids = $this->extract_gallery_ids( $component );
			if ( ! empty( $ids ) ) {
				$attributes['ids'] = $ids;
			}
			$attributes['linkTo'] = $attrs['link_to'] ?? 'none';
		}

		if ( $block_name === 'core/list' ) {
			if ( ! empty( $attrs['list_style'] ) && in_array( $attrs['list_style'], [ 'ordered', 'numbered' ], true ) ) {
				$attributes['ordered'] = true;
			}
		}

		if ( $block_name === 'core/embed' ) {
			$url = $attrs['url'] ?? ( $attrs['youtube_url'] ?? ( $attrs['video_url'] ?? '' ) );
			if ( ! empty( $url ) ) {
				$attributes['url'] = $url;
				$attributes['type'] = 'video';
				if ( strpos( $url, 'vimeo' ) !== false ) {
					$attributes['providerNameSlug'] = 'vimeo';
				} elseif ( strpos( $url, 'youtube' ) !== false || strpos( $url, 'youtu.be' ) !== false ) {
					$attributes['providerNameSlug'] = 'youtube';
				}
			}
		}

		return $attributes;
	}

	/**
	 * Parse a heading level from either a number, "h2"-style string, or other input.
	 */
	private function parse_heading_level( $value ): int {
		if ( is_int( $value ) ) {
			return max( 1, min( 6, $value ) );
		}
		if ( is_string( $value ) && preg_match( '/^h([1-6])$/i', $value, $m ) ) {
			return (int) $m[1];
		}
		if ( is_numeric( $value ) ) {
			return max( 1, min( 6, (int) $value ) );
		}
		return 2;
	}

	/**
	 * Extract attachment ids from a gallery component's image array.
	 */
	private function extract_gallery_ids( DEVTB_Component $component ): array {
		$images = $component->attributes['images']
			?? ( $component->attributes['gallery']
			?? ( $component->attributes['wp_gallery'] ?? [] ) );
		if ( is_string( $images ) ) {
			$decoded = json_decode( $images, true );
			if ( is_array( $decoded ) ) {
				$images = $decoded;
			}
		}
		if ( ! is_array( $images ) ) {
			return [];
		}
		$ids = [];
		foreach ( $images as $img ) {
			if ( is_array( $img ) && ! empty( $img['id'] ) ) {
				$ids[] = (int) $img['id'];
			}
		}
		return $ids;
	}

	/**
	 * Generate inner HTML for block
	 *
	 * @param string         $block_name Block name.
	 * @param DEVTB_Component $component Component.
	 * @return string Inner HTML.
	 */
	private function generate_inner_html( string $block_name, DEVTB_Component $component ): string {
		$content = (string) ( $component->content ?? '' );
		$attrs   = $component->attributes;

		switch ( $block_name ) {
			case 'core/paragraph':
				return $this->render_paragraph_html( $content );

			case 'core/heading':
				$level = $this->parse_heading_level( $attrs['level'] ?? ( $attrs['header_size'] ?? 2 ) );
				// `wp-block-heading` class is canonical since WP 6.3; without it 6.7+ marks the block invalid.
				return '<h' . $level . ' class="wp-block-heading">' . esc_html( $content ) . '</h' . $level . '>';

			case 'core/button':
				$url    = $attrs['url'] ?? ( $attrs['href'] ?? '#' );
				$target = ! empty( $attrs['target'] ) ? ' target="' . esc_attr( $attrs['target'] ) . '"' : '';
				$rel    = ! empty( $attrs['rel'] ) ? ' rel="' . esc_attr( $attrs['rel'] ) . '"' : '';
				// `wp-element-button` is required by theme.json element-styling pipeline since WP 6.1.
				return '<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="' . esc_url( $url ) . '"' . $target . $rel . '>' . esc_html( $content ) . '</a></div>';

			case 'core/quote':
				// `author` is the Elementor blockquote widget's citation field (parser passes through
				// unmapped). `cite` / `citation` cover generic universal sources; `testimonial_name`
				// covers the testimonial → quote downgrade path.
				$cite = $attrs['cite']
					?? ( $attrs['citation']
					?? ( $attrs['author']
					?? ( $attrs['testimonial_name'] ?? '' ) ) );
				$cite_html = ! empty( $cite ) ? '<cite>' . esc_html( $cite ) . '</cite>' : '';
				$body = $this->looks_like_html( $content ) ? $content : '<p>' . esc_html( $content ) . '</p>';
				return '<blockquote class="wp-block-quote">' . $body . $cite_html . '</blockquote>';

			case 'core/code':
				return '<pre class="wp-block-code"><code>' . esc_html( $content ) . '</code></pre>';

			case 'core/image':
				$url = $attrs['image_url'] ?? ( $attrs['src'] ?? ( $attrs['url'] ?? '' ) );
				$alt = $attrs['alt_text'] ?? ( $attrs['alt'] ?? '' );
				$caption = ! empty( $attrs['caption'] ) ? '<figcaption class="wp-element-caption">' . esc_html( $attrs['caption'] ) . '</figcaption>' : '';
				return '<figure class="wp-block-image"><img src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '"/>' . $caption . '</figure>';

			case 'core/video':
				$src = $attrs['src'] ?? ( $attrs['url'] ?? ( $attrs['video_url'] ?? '' ) );
				return '<figure class="wp-block-video"><video controls src="' . esc_url( $src ) . '"></video></figure>';

			case 'core/audio':
				$src = $attrs['src'] ?? ( $attrs['url'] ?? '' );
				return '<figure class="wp-block-audio"><audio controls src="' . esc_url( $src ) . '"></audio></figure>';

			case 'core/embed':
				$url = $attrs['url'] ?? ( $attrs['youtube_url'] ?? ( $attrs['video_url'] ?? '' ) );
				$provider = strpos( $url, 'vimeo' ) !== false ? 'vimeo' : 'youtube';
				return '<figure class="wp-block-embed is-type-video is-provider-' . esc_attr( $provider ) . '"><div class="wp-block-embed__wrapper">' . esc_url( $url ) . '</div></figure>';

			case 'core/separator':
				// `has-alpha-channel-opacity` is canonical since WP 6.5; without it the separator re-renders and loses opacity.
				return '<hr class="wp-block-separator has-alpha-channel-opacity"/>';

			case 'core/spacer':
				$height = $attrs['height'] ?? '100px';
				return '<div style="height:' . esc_attr( $height ) . '" aria-hidden="true" class="wp-block-spacer"></div>';

			case 'core/list':
				$ordered = ! empty( $attrs['ordered'] );
				$tag = $ordered ? 'ol' : 'ul';
				$items = $this->extract_list_items( $component );
				if ( empty( $items ) ) {
					$items = [ $content ];
				}
				// WP 6.0+ canonical: core/list-item innerBlocks rather than a flat <ul><li>.
				$item_blocks = '';
				foreach ( $items as $item ) {
					$text = is_array( $item ) ? ( $item['text'] ?? '' ) : (string) $item;
					$inner = '<li>' . esc_html( $text ) . '</li>';
					$item_blocks .= "\n" . $this->create_block_delimiter( 'core/list-item', [] ) . "\n" . $inner . "\n" . $this->create_closing_delimiter( 'core/list-item' );
				}
				return '<' . $tag . ' class="wp-block-list">' . $item_blocks . "\n</" . $tag . '>';

			case 'core/gallery':
				$images = $attrs['images'] ?? ( $attrs['gallery'] ?? ( $attrs['wp_gallery'] ?? [] ) );
				if ( is_string( $images ) ) {
					$decoded = json_decode( $images, true );
					if ( is_array( $decoded ) ) {
						$images = $decoded;
					}
				}
				$figures = [];
				if ( is_array( $images ) ) {
					foreach ( $images as $img ) {
						if ( ! is_array( $img ) ) {
							continue;
						}
						$src = $img['url'] ?? '';
						$alt = $img['alt'] ?? '';
						$figures[] = '<figure class="wp-block-image"><img src="' . esc_url( $src ) . '" alt="' . esc_attr( $alt ) . '"/></figure>';
					}
				}
				return '<figure class="wp-block-gallery has-nested-images columns-default">' . implode( '', $figures ) . '</figure>';

			case 'core/table':
				return $this->build_table_inner_html( $component );

			case 'core/social-links':
				return '<ul class="wp-block-social-links">' . $this->build_social_link_items( $component ) . '</ul>';

			case 'core/navigation':
				return '';

			case 'core/file':
				$url = $attrs['url'] ?? ( $attrs['file_url'] ?? '#' );
				$name = $content ?: ( $attrs['filename'] ?? basename( $url ) );
				return '<div class="wp-block-file"><a href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a></div>';

			case 'core/html':
				if ( $component->type === 'icon' ) {
					return $this->render_icon_html( $component );
				}
				return $content;

			default:
				return $content;
		}
	}

	/**
	 * Heuristic: does this content already look like rendered HTML?
	 */
	private function looks_like_html( string $content ): bool {
		return strpos( $content, '<' ) !== false && strpos( $content, '>' ) !== false;
	}

	/**
	 * Check whether content already serializes as paragraph HTML.
	 */
	private function is_paragraph_html( string $content ): bool {
		return (bool) preg_match( '/^<p(?:\s[^>]*)?>[\s\S]*<\/p>$/i', trim( $content ) );
	}

	/**
	 * Render core/paragraph inner HTML without nesting existing paragraph tags.
	 */
	private function render_paragraph_html( string $content ): string {
		$trimmed = trim( $content );
		if ( $this->is_paragraph_html( $trimmed ) ) {
			return $trimmed;
		}
		$rendered = $this->looks_like_html( $trimmed ) ? $trimmed : esc_html( $content );
		return '<p>' . $rendered . '</p>';
	}

	/**
	 * Block-level rich text is invalid inside core/paragraph, so preserve it as core/html.
	 */
	private function should_preserve_rich_text_as_html_block( string $content ): bool {
		$trimmed = trim( $content );
		if ( ! $this->looks_like_html( $trimmed ) ) {
			return false;
		}

		$paragraph_count = preg_match_all( '/<p(?:\s[^>]*)?>/i', $trimmed, $matches );
		if ( $paragraph_count > 1 ) {
			return true;
		}

		return (bool) preg_match(
			'/<\/?(?:address|article|aside|blockquote|div|dl|figure|figcaption|footer|form|h[1-6]|header|hr|li|main|nav|ol|pre|section|table|tbody|td|tfoot|th|thead|tr|ul)\b/i',
			$trimmed
		);
	}

	/**
	 * Pull list items from a list component's attributes (icon_list, items[], etc.).
	 */
	private function extract_list_items( DEVTB_Component $component ): array {
		foreach ( [ 'items', 'icon_list', 'list_items' ] as $key ) {
			$items = $component->attributes[ $key ] ?? null;
			if ( is_string( $items ) ) {
				$decoded = json_decode( $items, true );
				if ( is_array( $decoded ) ) {
					$items = $decoded;
				}
			}
			if ( is_array( $items ) && ! empty( $items ) ) {
				return $items;
			}
		}
		return [];
	}

	/**
	 * Build a core/table inner HTML body from rows/cells in attributes.
	 */
	private function build_table_inner_html( DEVTB_Component $component ): string {
		$rows = $component->attributes['rows'] ?? ( $component->attributes['body'] ?? [] );
		if ( is_string( $rows ) ) {
			$decoded = json_decode( $rows, true );
			if ( is_array( $decoded ) ) {
				$rows = $decoded;
			}
		}
		if ( ! is_array( $rows ) || empty( $rows ) ) {
			return '<figure class="wp-block-table"><table><tbody></tbody></table></figure>';
		}
		$tbody = '';
		foreach ( $rows as $row ) {
			$cells = is_array( $row ) ? ( $row['cells'] ?? $row ) : [];
			$tr = '';
			foreach ( (array) $cells as $cell ) {
				$value = is_array( $cell ) ? ( $cell['content'] ?? '' ) : (string) $cell;
				$tr .= '<td>' . esc_html( $value ) . '</td>';
			}
			$tbody .= '<tr>' . $tr . '</tr>';
		}
		return '<figure class="wp-block-table"><table><tbody>' . $tbody . '</tbody></table></figure>';
	}

	/**
	 * Build nested social link list items as self-closing wp:social-link block stubs.
	 */
	private function build_social_link_items( DEVTB_Component $component ): string {
		$items = $component->attributes['icons'] ?? ( $component->attributes['social_icon_list'] ?? [] );
		if ( is_string( $items ) ) {
			$decoded = json_decode( $items, true );
			if ( is_array( $decoded ) ) {
				$items = $decoded;
			}
		}
		if ( ! is_array( $items ) ) {
			return '';
		}
		$out = '';
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$service = $item['service'] ?? ( $item['social'] ?? 'link' );
			$url = $item['url'] ?? ( $item['link']['url'] ?? '#' );
			$attrs_json = wp_json_encode( [ 'url' => $url, 'service' => $service ], JSON_UNESCAPED_SLASHES );
			$out .= '<!-- wp:social-link ' . $attrs_json . ' /-->';
		}
		return $out;
	}

	/**
	 * Render an icon widget as inline HTML, preserving SVG or font-icon markup.
	 */
	private function render_icon_html( DEVTB_Component $component ): string {
		$attrs = $component->attributes;
		if ( ! empty( $attrs['svg'] ) ) {
			return (string) $attrs['svg'];
		}
		$icon_class = $attrs['icon'] ?? ( $attrs['selected_icon']['value'] ?? '' );
		if ( is_array( $icon_class ) ) {
			$icon_class = $icon_class['value'] ?? '';
		}
		if ( ! empty( $icon_class ) ) {
			return '<i class="' . esc_attr( $icon_class ) . '" aria-hidden="true"></i>';
		}
		return $component->content ?? '';
	}

	/**
	 * Denormalize universal attributes to Gutenberg attributes
	 *
	 * @param array $attributes Universal attributes.
	 * @return array Gutenberg attributes.
	 */
	private function denormalize_attributes( array $attributes ): array {
		$gutenberg_attrs = [];

		// Note: `customTextColor`, `customBackgroundColor`, `customFontSize` were canonical
		// pre-WP-5.8 and are no longer emitted. Color/typography now lives under `style.*`
		// (built further down) so we avoid producing duplicate/conflicting attribute pairs.

		// Text alignment (paragraph/heading use `textAlign`; `align` means block-level
		// alignment in canonical 6.5+ schema and will mis-render text alignment).
		if ( isset( $attributes['text-align'] ) ) {
			$gutenberg_attrs['textAlign'] = $attributes['text-align'];
		}

		// Class name
		if ( isset( $attributes['class'] ) ) {
			$gutenberg_attrs['className'] = $attributes['class'];
		}

		// Anchor (ID)
		if ( isset( $attributes['id'] ) ) {
			$gutenberg_attrs['anchor'] = $attributes['id'];
		}

		// Width — canonical `core/column` width is a string with unit (e.g. "50%"), not a bare float.
		if ( isset( $attributes['width'] ) ) {
			$gutenberg_attrs['width'] = floatval( $attributes['width'] ) . '%';
		}

		// Build style object
		$style = [];

		// Typography
		$typography = [];
		if ( isset( $attributes['font-family'] ) ) {
			$typography['fontFamily'] = $attributes['font-family'];
		}
		if ( isset( $attributes['font-weight'] ) ) {
			$typography['fontWeight'] = $attributes['font-weight'];
		}
		if ( isset( $attributes['line-height'] ) ) {
			$typography['lineHeight'] = $attributes['line-height'];
		}
		if ( ! empty( $typography ) ) {
			$style['typography'] = $typography;
		}

		// Color
		$color = [];
		if ( isset( $attributes['color'] ) && ! isset( $gutenberg_attrs['customTextColor'] ) ) {
			$color['text'] = $attributes['color'];
		}
		if ( isset( $attributes['background-color'] ) && ! isset( $gutenberg_attrs['customBackgroundColor'] ) ) {
			$color['background'] = $attributes['background-color'];
		}
		if ( ! empty( $color ) ) {
			$style['color'] = $color;
		}

		// Spacing
		$spacing = [];

		// Padding
		$padding = [];
		if ( isset( $attributes['padding-top'] ) ) {
			$padding['top'] = $attributes['padding-top'];
		}
		if ( isset( $attributes['padding-right'] ) ) {
			$padding['right'] = $attributes['padding-right'];
		}
		if ( isset( $attributes['padding-bottom'] ) ) {
			$padding['bottom'] = $attributes['padding-bottom'];
		}
		if ( isset( $attributes['padding-left'] ) ) {
			$padding['left'] = $attributes['padding-left'];
		}
		if ( ! empty( $padding ) ) {
			$spacing['padding'] = $padding;
		}

		// Margin
		$margin = [];
		if ( isset( $attributes['margin-top'] ) ) {
			$margin['top'] = $attributes['margin-top'];
		}
		if ( isset( $attributes['margin-right'] ) ) {
			$margin['right'] = $attributes['margin-right'];
		}
		if ( isset( $attributes['margin-bottom'] ) ) {
			$margin['bottom'] = $attributes['margin-bottom'];
		}
		if ( isset( $attributes['margin-left'] ) ) {
			$margin['left'] = $attributes['margin-left'];
		}
		if ( ! empty( $margin ) ) {
			$spacing['margin'] = $margin;
		}

		if ( ! empty( $spacing ) ) {
			$style['spacing'] = $spacing;
		}

		// Border
		$border = [];
		if ( isset( $attributes['border-radius'] ) ) {
			$border['radius'] = $attributes['border-radius'];
		}
		if ( isset( $attributes['border-width'] ) ) {
			$border['width'] = $attributes['border-width'];
		}
		if ( isset( $attributes['border-color'] ) ) {
			$border['color'] = $attributes['border-color'];
		}
		if ( ! empty( $border ) ) {
			$style['border'] = $border;
		}

		if ( ! empty( $style ) ) {
			$gutenberg_attrs['style'] = $style;
		}

		return $gutenberg_attrs;
	}

	/**
	 * Create block opening delimiter
	 *
	 * @param string $block_name Block name.
	 * @param array  $attributes Block attributes.
	 * @return string Opening delimiter.
	 */
	private function create_block_delimiter( string $block_name, array $attributes = [] ): string {
		// Canonical WordPress serialization drops the `core/` namespace from core blocks.
		$name = ( strpos( $block_name, 'core/' ) === 0 ) ? substr( $block_name, 5 ) : $block_name;

		$delimiter = '<!-- wp:' . $name;

		if ( ! empty( $attributes ) ) {
			$delimiter .= ' ' . wp_json_encode( $attributes, JSON_UNESCAPED_SLASHES );
		}

		$delimiter .= ' -->';

		return $delimiter;
	}

	/**
	 * Create block closing delimiter
	 *
	 * @param string $block_name Block name.
	 * @return string Closing delimiter.
	 */
	private function create_closing_delimiter( string $block_name ): string {
		$name = ( strpos( $block_name, 'core/' ) === 0 ) ? substr( $block_name, 5 ) : $block_name;
		return '<!-- /wp:' . $name . ' -->';
	}

	/**
	 * Convert a compound widget (tabs, accordion, card, cta, counter, testimonial, pricing-table, alert)
	 * into a small group of native Gutenberg blocks. Source content is preserved as editable blocks
	 * rather than collapsed into inert markup.
	 */
	private function convert_compound( DEVTB_Component $component ): string {
		switch ( $component->type ) {
			case 'tabs':
			case 'accordion':
				return $this->convert_tabs_or_accordion( $component );

			case 'card':
				return $this->convert_card( $component );

			case 'cta':
				return $this->convert_cta( $component );

			case 'counter':
				return $this->convert_counter( $component );

			case 'testimonial':
				return $this->convert_testimonial( $component );

			case 'pricing-table':
				return $this->convert_pricing_table( $component );

			case 'alert':
				return $this->convert_alert( $component );
		}

		return $this->convert_as_marker( $component );
	}

	/**
	 * Tabs/accordion -> stacked core/heading (h3 per item) + nested content as core/group.
	 * Native Gutenberg has no tabs primitive; this is the agreed downgrade that keeps content editable.
	 */
	private function convert_tabs_or_accordion( DEVTB_Component $component ): string {
		$items = $component->attributes['tabs'] ?? ( $component->attributes['items'] ?? [] );
		if ( is_string( $items ) ) {
			$decoded = json_decode( $items, true );
			if ( is_array( $decoded ) ) {
				$items = $decoded;
			}
		}

		$blocks = [];
		if ( is_array( $items ) && ! empty( $items ) ) {
			foreach ( $items as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$title = $item['tab_title'] ?? ( $item['title'] ?? '' );
				$body = $item['tab_content'] ?? ( $item['content'] ?? '' );
				$blocks[] = $this->build_heading_block( $title, 3 );
				$blocks[] = $this->build_paragraph_block( $body );
			}
		} elseif ( ! empty( $component->children ) ) {
			foreach ( $component->children as $child ) {
				$blocks[] = $this->convert_component( $child );
			}
		}

		$inner = implode( "\n", $blocks );
		$class = $component->type === 'accordion' ? 'devtb-accordion-converted' : 'devtb-tabs-converted';
		return $this->wrap_in_group( $inner, [ 'className' => $class ] );
	}

	/**
	 * Card / icon-box / image-box / flip-box -> group of optional image + heading + paragraph + button.
	 */
	private function convert_card( DEVTB_Component $component ): string {
		$attrs = $component->attributes;
		$blocks = [];

		$image_url = $attrs['image_url'] ?? ( $attrs['image']['url'] ?? '' );
		if ( ! empty( $image_url ) ) {
			$blocks[] = $this->build_image_block( $image_url, $attrs['alt_text'] ?? '' );
		}

		$title = $attrs['heading'] ?? ( $attrs['title_text'] ?? ( $attrs['title'] ?? '' ) );
		if ( ! empty( $title ) ) {
			$blocks[] = $this->build_heading_block( $title, 3 );
		}

		$description = $attrs['description'] ?? ( $attrs['description_text'] ?? $component->content );
		if ( ! empty( $description ) ) {
			$blocks[] = $this->build_paragraph_block( $description );
		}

		$button_url = $attrs['url'] ?? ( $attrs['link']['url'] ?? '' );
		$button_text = $attrs['button_text'] ?? ( $attrs['link_text'] ?? '' );
		if ( ! empty( $button_url ) || ! empty( $button_text ) ) {
			$blocks[] = $this->build_buttons_block( $button_text, $button_url );
		}

		return $this->wrap_in_group( implode( "\n", $blocks ), [ 'className' => 'devtb-card-converted' ] );
	}

	/**
	 * Call-to-action -> heading + paragraph + button.
	 */
	private function convert_cta( DEVTB_Component $component ): string {
		$attrs = $component->attributes;
		$blocks = [];

		$title = $attrs['heading'] ?? ( $attrs['title'] ?? '' );
		if ( ! empty( $title ) ) {
			$blocks[] = $this->build_heading_block( $title, 2 );
		}
		$description = $attrs['description'] ?? $component->content;
		if ( ! empty( $description ) ) {
			$blocks[] = $this->build_paragraph_block( $description );
		}
		$url = $attrs['url'] ?? ( $attrs['link']['url'] ?? '' );
		$btn_text = $attrs['button_text'] ?? ( $attrs['cta_text'] ?? 'Learn more' );
		if ( ! empty( $url ) ) {
			$blocks[] = $this->build_buttons_block( $btn_text, $url );
		}

		return $this->wrap_in_group( implode( "\n", $blocks ), [ 'className' => 'devtb-cta-converted' ] );
	}

	/**
	 * Counter -> big heading with the ending number + a description paragraph.
	 */
	private function convert_counter( DEVTB_Component $component ): string {
		$attrs = $component->attributes;
		$ending = $attrs['ending_number'] ?? ( $attrs['ending'] ?? ( $attrs['number'] ?? '' ) );
		$prefix = $attrs['prefix'] ?? '';
		$suffix = $attrs['suffix'] ?? '';
		// Elementor parser's normalize_settings maps the widget `title` setting to the
		// universal `heading` attribute, so a counter widget's title arrives as `heading`,
		// not `title`. Read both so we work whether the converter is fed normalized
		// universal components or raw passthrough.
		$title = $attrs['heading'] ?? ( $attrs['title'] ?? $component->content );

		$display = trim( (string) $prefix . (string) $ending . (string) $suffix );

		$blocks = [];
		if ( $display !== '' ) {
			$blocks[] = $this->build_heading_block( $display, 2 );
		}
		if ( ! empty( $title ) ) {
			$blocks[] = $this->build_paragraph_block( $title );
		}

		return $this->wrap_in_group( implode( "\n", $blocks ), [ 'className' => 'devtb-counter-converted' ] );
	}

	/**
	 * Testimonial -> core/quote with citation.
	 */
	private function convert_testimonial( DEVTB_Component $component ): string {
		$attrs = $component->attributes;
		$content = $attrs['testimonial_content'] ?? $component->content;
		$author = $attrs['testimonial_name'] ?? ( $attrs['name'] ?? '' );
		$job = $attrs['testimonial_job'] ?? ( $attrs['title'] ?? '' );
		$cite = trim( $author . ( $author && $job ? ', ' : '' ) . $job );

		$inner = '<blockquote class="wp-block-quote"><p>' . esc_html( $content ) . '</p>';
		if ( ! empty( $cite ) ) {
			$inner .= '<cite>' . esc_html( $cite ) . '</cite>';
		}
		$inner .= '</blockquote>';

		$opening = $this->create_block_delimiter( 'core/quote', [] );
		$closing = $this->create_closing_delimiter( 'core/quote' );
		return $opening . "\n" . $inner . "\n" . $closing . "\n\n";
	}

	/**
	 * Pricing table -> heading + price + list + button.
	 */
	private function convert_pricing_table( DEVTB_Component $component ): string {
		$attrs = $component->attributes;
		$blocks = [];

		$title = $attrs['heading'] ?? ( $attrs['title'] ?? '' );
		if ( ! empty( $title ) ) {
			$blocks[] = $this->build_heading_block( $title, 3 );
		}

		$price = $attrs['price'] ?? '';
		$currency = $attrs['currency_symbol'] ?? '';
		$period = $attrs['period'] ?? '';
		$price_display = trim( (string) $currency . (string) $price . ( $period ? ' / ' . $period : '' ) );
		if ( $price_display !== '' ) {
			$blocks[] = $this->build_heading_block( $price_display, 2 );
		}

		$features = $attrs['features'] ?? ( $attrs['items'] ?? [] );
		if ( is_string( $features ) ) {
			$decoded = json_decode( $features, true );
			if ( is_array( $decoded ) ) {
				$features = $decoded;
			}
		}
		if ( is_array( $features ) && ! empty( $features ) ) {
			$item_blocks = '';
			foreach ( $features as $f ) {
				$text = is_array( $f ) ? ( $f['item_text'] ?? ( $f['text'] ?? '' ) ) : (string) $f;
				$item_blocks .= "\n" . $this->create_block_delimiter( 'core/list-item', [] ) . "\n<li>" . esc_html( $text ) . '</li>' . "\n" . $this->create_closing_delimiter( 'core/list-item' );
			}
			$list_opening = $this->create_block_delimiter( 'core/list', [] );
			$list_closing = $this->create_closing_delimiter( 'core/list' );
			$blocks[] = $list_opening . "\n" . '<ul class="wp-block-list">' . $item_blocks . "\n</ul>\n" . $list_closing;
		}

		$button_url = $attrs['button_url'] ?? ( $attrs['url'] ?? '' );
		$button_text = $attrs['button_text'] ?? 'Get started';
		if ( ! empty( $button_url ) ) {
			$blocks[] = $this->build_buttons_block( $button_text, $button_url );
		}

		return $this->wrap_in_group( implode( "\n", $blocks ), [ 'className' => 'devtb-pricing-converted' ] );
	}

	/**
	 * Alert -> core/group with status className and an inner paragraph.
	 */
	private function convert_alert( DEVTB_Component $component ): string {
		$attrs = $component->attributes;
		$type = $attrs['alert_type'] ?? ( $attrs['type'] ?? 'info' );
		$title = $attrs['alert_title'] ?? '';
		$body = $attrs['alert_description'] ?? $component->content;

		$blocks = [];
		if ( ! empty( $title ) ) {
			$blocks[] = $this->build_heading_block( $title, 4 );
		}
		if ( ! empty( $body ) ) {
			$blocks[] = $this->build_paragraph_block( $body );
		}

		return $this->wrap_in_group(
			implode( "\n", $blocks ),
			[ 'className' => 'devtb-alert is-style-' . preg_replace( '/[^a-z0-9_-]/i', '', $type ) ]
		);
	}

	/**
	 * Marker fallback: preserve the original widget as core/html with a visible devtb annotation
	 * comment plus the widget's title/content as inline HTML, so editors can see and edit what was there.
	 */
	private function convert_as_marker( DEVTB_Component $component ): string {
		$source = $component->metadata['original_type'] ?? $component->type;
		$framework = $component->metadata['source_framework'] ?? 'unknown';
		$attrs = $component->attributes;

		$title = $attrs['heading'] ?? ( $attrs['title'] ?? ( $attrs['title_text'] ?? '' ) );
		$body = $component->content ?? '';

		$inner = '<div class="devtb-unconverted" data-devtb-source="' . esc_attr( $framework . ':' . $source ) . '">';
		if ( ! empty( $title ) ) {
			$inner .= '<strong>' . esc_html( $title ) . '</strong>';
		}
		if ( ! empty( $body ) ) {
			$inner .= '<p>' . esc_html( $body ) . '</p>';
		}
		$inner .= '</div>';

		$comment = '<!-- devtb: unconverted ' . esc_html( $framework ) . ' widget "' . esc_html( $source ) . '" -->';

		$opening = $this->create_block_delimiter( 'core/html', [] );
		$closing = $this->create_closing_delimiter( 'core/html' );
		return $opening . "\n" . $comment . "\n" . $inner . "\n" . $closing . "\n\n";
	}

	/**
	 * Helpers for compound block construction.
	 */
	private function build_heading_block( string $text, int $level = 2 ): string {
		$level = max( 1, min( 6, $level ) );
		$opening = $this->create_block_delimiter( 'core/heading', [ 'level' => $level ] );
		$closing = $this->create_closing_delimiter( 'core/heading' );
		return $opening . "\n" . '<h' . $level . ' class="wp-block-heading">' . esc_html( $text ) . '</h' . $level . '>' . "\n" . $closing;
	}

	private function build_paragraph_block( string $text ): string {
		if ( $this->should_preserve_rich_text_as_html_block( $text ) ) {
			$opening = $this->create_block_delimiter( 'core/html', [] );
			$closing = $this->create_closing_delimiter( 'core/html' );
			return $opening . "\n" . trim( $text ) . "\n" . $closing;
		}

		$opening = $this->create_block_delimiter( 'core/paragraph', [] );
		$closing = $this->create_closing_delimiter( 'core/paragraph' );
		return $opening . "\n" . $this->render_paragraph_html( $text ) . "\n" . $closing;
	}

	private function build_image_block( string $url, string $alt = '' ): string {
		$opening = $this->create_block_delimiter( 'core/image', [] );
		$closing = $this->create_closing_delimiter( 'core/image' );
		return $opening . "\n" . '<figure class="wp-block-image"><img src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '"/></figure>' . "\n" . $closing;
	}

	private function build_buttons_block( string $text, string $url ): string {
		$btn_attrs = ! empty( $url ) ? [ 'url' => $url ] : [];
		$btn_opening = $this->create_block_delimiter( 'core/button', $btn_attrs );
		$btn_closing = $this->create_closing_delimiter( 'core/button' );
		$btn_inner = '<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="' . esc_url( $url ) . '">' . esc_html( $text ) . '</a></div>';
		$btn = $btn_opening . "\n" . $btn_inner . "\n" . $btn_closing;

		$opening = $this->create_block_delimiter( 'core/buttons', [] );
		$closing = $this->create_closing_delimiter( 'core/buttons' );
		return $opening . "\n" . '<div class="wp-block-buttons">' . "\n" . $btn . "\n" . '</div>' . "\n" . $closing;
	}

	private function wrap_in_group( string $inner_blocks, array $attrs = [] ): string {
		$opening = $this->create_block_delimiter( 'core/group', $attrs );
		$closing = $this->create_closing_delimiter( 'core/group' );
		return $opening . "\n" . '<div class="wp-block-group">' . "\n" . $inner_blocks . "\n" . '</div>' . "\n" . $closing . "\n\n";
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
		return 'gutenberg';
	}

	/**
	 * Get supported types
	 */
	public function get_supported_types(): array {
		return [
			// Layout
			'container', 'row', 'section', 'column',
			// Simple 1:1 blocks
			'text', 'paragraph', 'heading', 'image', 'gallery', 'list', 'quote', 'blockquote',
			'code', 'table', 'button', 'button-group', 'divider', 'spacer', 'html',
			'video', 'audio', 'file', 'embed', 'icon',
			'social-icons', 'social-links', 'social-link', 'search', 'nav', 'menu', 'menu-item',
			// Compound widgets (emit a group of native blocks)
			'tabs', 'accordion', 'card', 'cta', 'counter', 'testimonial', 'pricing-table', 'alert',
			// Marker fallback (preserved as annotated core/html)
			'slider', 'form', 'countdown', 'portfolio', 'toc', 'map', 'progress', 'rating', 'unknown',
		];
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
		return $this->convert_as_marker( $component );
	}
}
