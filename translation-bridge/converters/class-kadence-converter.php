<?php
/**
 * Kadence Blocks Converter
 *
 * Converts universal components to Kadence Blocks markup (Gutenberg block-comment
 * serialization with the `kadence/` namespace). Supports the Kadence Blocks plugin,
 * Kadence Theme template parts (header/footer/template builder), and Kadence Pro
 * premium blocks where coverage exists. Pro-only blocks without coverage fall
 * through to the universal fallback.
 *
 * @package DevelopmentTranslation_Bridge
 * @subpackage Translation_Bridge
 * @since 4.2.0
 */

namespace DEVTB\TranslationBridge\Converters;

use DEVTB\TranslationBridge\Core\DEVTB_Converter_Interface;
use DEVTB\TranslationBridge\Models\DEVTB_Component;

/**
 * Class DEVTB_Kadence_Converter
 */
class DEVTB_Kadence_Converter implements DEVTB_Converter_Interface {

	/**
	 * Upstream framework version (Kadence Blocks plugin) this converter is
	 * calibrated against. Theme template parts (Kadence Theme 1.5.x) share
	 * the same `kadence/*` namespace and are emitted through the same path.
	 */
	public const TARGET_CMS_VERSION = '3.7.2';

	/**
	 * Counter for generating per-block unique IDs.
	 *
	 * @var int
	 */
	private int $id_counter = 0;

	/**
	 * @inheritDoc
	 */
	public function get_target_cms_version(): string {
		return self::TARGET_CMS_VERSION;
	}

	/**
	 * @inheritDoc
	 */
	public function convert( $component ) {
		$components = is_array( $component ) ? $component : [ $component ];
		$output     = '';

		foreach ( $components as $comp ) {
			if ( $comp instanceof DEVTB_Component ) {
				$output .= $this->convert_component( $comp );
			}
		}

		return $output;
	}

	/**
	 * @inheritDoc
	 */
	public function convert_component( DEVTB_Component $component ): string {
		$type = $component->type;

		if ( $type === 'container' || $type === 'row' ) {
			return $this->convert_rowlayout( $component );
		} elseif ( $type === 'column' ) {
			return $this->convert_column( $component );
		}

		return $this->convert_block( $component );
	}

	/**
	 * Convert a row/container to kadence/rowlayout.
	 */
	private function convert_rowlayout( DEVTB_Component $component ): string {
		$column_children = array_values( array_filter(
			$component->children,
			static fn( $child ) => $child->type === 'column'
		) );
		$column_count    = max( 1, count( $column_children ) );

		$attrs = array_merge(
			[
				'uniqueID' => $this->next_unique_id(),
				'columns'  => $column_count,
				'colLayout' => $column_count === 1 ? 'row' : 'equal',
			],
			$this->denormalize_attributes( $component->attributes )
		);

		$opening = $this->create_block_delimiter( 'kadence/rowlayout', $attrs );
		$content = '<div class="kb-row-layout-wrap alignnone">';
		$content .= "\n" . '<div class="kt-row-column-wrap kt-has-' . $column_count . '-columns">';

		if ( $column_children ) {
			foreach ( $column_children as $child ) {
				$content .= "\n" . $this->convert_column( $child );
			}
		} else {
			foreach ( $component->children as $child ) {
				$content .= "\n" . $this->convert_component( $child );
			}
		}

		$content .= "\n</div>\n</div>";
		$closing  = $this->create_closing_delimiter( 'kadence/rowlayout' );

		return $opening . "\n" . $content . "\n" . $closing . "\n\n";
	}

	/**
	 * Convert a column to kadence/column.
	 */
	private function convert_column( DEVTB_Component $component ): string {
		$attrs = array_merge(
			[ 'uniqueID' => $this->next_unique_id() ],
			$this->denormalize_attributes( $component->attributes )
		);

		$opening = $this->create_block_delimiter( 'kadence/column', $attrs );
		$content = '<div class="kadence-column' . esc_attr( $attrs['uniqueID'] ) . ' inner-column">';
		$content .= "\n" . '<div class="kt-inside-inner-col">';

		foreach ( $component->children as $child ) {
			$content .= "\n" . $this->convert_component( $child );
		}

		$content .= "\n</div>\n</div>";
		$closing  = $this->create_closing_delimiter( 'kadence/column' );

		return $opening . "\n" . $content . "\n" . $closing . "\n";
	}

	/**
	 * Convert a leaf component to a Kadence block (or fall through to a core block).
	 */
	private function convert_block( DEVTB_Component $component ): string {
		$block_name = $this->map_to_block_type( $component->type );

		if ( ! $block_name ) {
			return $this->get_fallback( $component );
		}

		$attrs      = $this->denormalize_attributes( $component->attributes );
		$attrs      = $this->add_block_content( $block_name, $attrs, $component );
		$inner_html = $this->generate_inner_html( $block_name, $component, $attrs );

		// All Kadence-namespaced blocks require a uniqueID.
		if ( strpos( $block_name, 'kadence/' ) === 0 ) {
			$attrs = array_merge( [ 'uniqueID' => $this->next_unique_id() ], $attrs );
		}

		$opening = $this->create_block_delimiter( $block_name, $attrs );
		$closing = $this->create_closing_delimiter( $block_name );

		return $opening . "\n" . $inner_html . "\n" . $closing . "\n\n";
	}

	/**
	 * Map universal component types to Kadence block names. Falls back to
	 * core/* blocks for types Kadence intentionally leaves to core (paragraph,
	 * list, quote, etc.).
	 */
	private function map_to_block_type( string $universal_type ): ?string {
		$type_map = [
			'heading'     => 'kadence/advancedheading',
			'button'      => 'kadence/advancedbtn',
			'icon'        => 'kadence/icon',
			'image'       => 'kadence/image',
			'spacer'      => 'kadence/spacer',
			'divider'     => 'kadence/spacer',
			'infobox'     => 'kadence/infobox',
			'card'        => 'kadence/infobox',
			'tabs'        => 'kadence/tabs',
			'accordion'   => 'kadence/accordion',
			'posts'       => 'kadence/posts',
			// Kadence sites use core blocks for these — no Kadence equivalent.
			'text'        => 'core/paragraph',
			'list'        => 'core/list',
			'quote'       => 'core/quote',
			'code'        => 'core/code',
			'html'        => 'core/html',
			'video'       => 'core/video',
		];

		return $type_map[ $universal_type ] ?? null;
	}

	/**
	 * Lift well-known component fields into block attributes (heading level,
	 * image url/alt, button link, etc.).
	 */
	private function add_block_content( string $block_name, array $attributes, DEVTB_Component $component ): array {
		switch ( $block_name ) {
			case 'kadence/advancedheading':
				$attributes['level'] = intval( $component->attributes['level'] ?? 2 );
				break;

			case 'kadence/image':
				if ( ! empty( $component->attributes['src'] ) ) {
					$attributes['url'] = $component->attributes['src'];
				}
				if ( ! empty( $component->attributes['alt'] ) ) {
					$attributes['alt'] = $component->attributes['alt'];
				}
				break;

			case 'kadence/spacer':
				$attributes['spacerHeight'] = intval( $component->attributes['height'] ?? 40 );
				break;

			case 'kadence/icon':
				if ( ! empty( $component->attributes['icon'] ) ) {
					$attributes['icons'] = [
						[ 'icon' => (string) $component->attributes['icon'] ],
					];
				}
				break;

			case 'core/image':
				if ( ! empty( $component->attributes['src'] ) ) {
					$attributes['url'] = $component->attributes['src'];
				}
				if ( ! empty( $component->attributes['alt'] ) ) {
					$attributes['alt'] = $component->attributes['alt'];
				}
				break;
		}

		return $attributes;
	}

	/**
	 * Produce the inner HTML payload for a given block.
	 */
	private function generate_inner_html( string $block_name, DEVTB_Component $component, array $attrs ): string {
		$content = $component->content ?? '';
		$unique  = $attrs['uniqueID'] ?? '';

		switch ( $block_name ) {
			case 'kadence/advancedheading':
				$level = intval( $component->attributes['level'] ?? 2 );
				return '<h' . $level . ' class="kt-adv-heading' . esc_attr( $unique ) . ' wp-block-kadence-advancedheading">' . esc_html( $content ) . '</h' . $level . '>';

			case 'kadence/advancedbtn':
				$url = esc_url( $component->attributes['href'] ?? '#' );
				return '<div class="wp-block-kadence-advancedbtn kb-btns-outer-wrap kt-btn-align-inherit">'
					. '<a href="' . $url . '" class="kt-button kt-btn-' . esc_attr( $unique ) . ' kb-btn-global-inherit">'
					. '<span class="kt-btn-text">' . esc_html( $content ) . '</span></a></div>';

			case 'kadence/icon':
				return '<div class="wp-block-kadence-icon kt-svg-icons kt-svg-icons-' . esc_attr( $unique ) . '"></div>';

			case 'kadence/image':
				$url = esc_url( $component->attributes['src'] ?? '' );
				$alt = esc_attr( $component->attributes['alt'] ?? '' );
				return '<figure class="wp-block-kadence-image kb-image-' . esc_attr( $unique ) . '"><img src="' . $url . '" alt="' . $alt . '"/></figure>';

			case 'kadence/spacer':
				$height = intval( $component->attributes['height'] ?? 40 );
				return '<div class="wp-block-kadence-spacer aligncenter kt-block-spacer-' . esc_attr( $unique ) . '" style="height:' . $height . 'px"></div>';

			case 'kadence/infobox':
				return '<div class="wp-block-kadence-infobox kt-info-box-' . esc_attr( $unique ) . '"><div class="kt-blocks-info-box-text">' . esc_html( $content ) . '</div></div>';

			case 'kadence/tabs':
				return '<div class="wp-block-kadence-tabs kt-tabs-wrap kt-tabs-id-' . esc_attr( $unique ) . '"></div>';

			case 'kadence/accordion':
				return '<div class="wp-block-kadence-accordion kt-accordion-wrap kt-accordion-id-' . esc_attr( $unique ) . '"></div>';

			case 'kadence/posts':
				return '<div class="wp-block-kadence-posts kt-posts-wrap kt-posts-id-' . esc_attr( $unique ) . '"></div>';

			// core/* fall-through blocks emit canonical Gutenberg HTML so they
			// round-trip cleanly through the Gutenberg parser.
			case 'core/paragraph':
				return '<p>' . esc_html( $content ) . '</p>';

			case 'core/list':
				return '<ul class="wp-block-list"><li>' . esc_html( $content ) . '</li></ul>';

			case 'core/quote':
				return '<blockquote class="wp-block-quote"><p>' . esc_html( $content ) . '</p></blockquote>';

			case 'core/code':
				return '<pre class="wp-block-code"><code>' . esc_html( $content ) . '</code></pre>';

			case 'core/html':
				return $content;

			case 'core/video':
				$url = esc_url( $component->attributes['src'] ?? '' );
				return '<figure class="wp-block-video"><video controls src="' . $url . '"></video></figure>';

			case 'core/image':
				$url = esc_url( $component->attributes['src'] ?? '' );
				$alt = esc_attr( $component->attributes['alt'] ?? '' );
				return '<figure class="wp-block-image"><img src="' . $url . '" alt="' . $alt . '"/></figure>';

			default:
				return esc_html( $content );
		}
	}

	/**
	 * Map universal attributes to Kadence block-level attributes. Kadence
	 * blocks generally use responsive *Desktop/*Tablet/*Mobile triples and
	 * a `padding`/`margin` 4-tuple in CSS shorthand order [top, right, bottom, left].
	 */
	private function denormalize_attributes( array $attributes ): array {
		$kadence_attrs = [];

		if ( isset( $attributes['text-align'] ) ) {
			$kadence_attrs['align'] = $attributes['text-align'];
		}

		if ( isset( $attributes['class'] ) ) {
			$kadence_attrs['className'] = $attributes['class'];
		}

		if ( isset( $attributes['id'] ) ) {
			$kadence_attrs['anchor'] = $attributes['id'];
		}

		if ( isset( $attributes['color'] ) ) {
			$kadence_attrs['color'] = $attributes['color'];
		}

		if ( isset( $attributes['background-color'] ) ) {
			$kadence_attrs['background'] = $attributes['background-color'];
		}

		$padding = [
			$attributes['padding-top']    ?? null,
			$attributes['padding-right']  ?? null,
			$attributes['padding-bottom'] ?? null,
			$attributes['padding-left']   ?? null,
		];
		if ( array_filter( $padding, static fn( $v ) => $v !== null && $v !== '' ) ) {
			$kadence_attrs['padding'] = array_map(
				static fn( $v ) => $v === null || $v === '' ? '' : (string) $v,
				$padding
			);
		}

		$margin = [
			$attributes['margin-top']    ?? null,
			$attributes['margin-right']  ?? null,
			$attributes['margin-bottom'] ?? null,
			$attributes['margin-left']   ?? null,
		];
		if ( array_filter( $margin, static fn( $v ) => $v !== null && $v !== '' ) ) {
			$kadence_attrs['margin'] = array_map(
				static fn( $v ) => $v === null || $v === '' ? '' : (string) $v,
				$margin
			);
		}

		return $kadence_attrs;
	}

	/**
	 * Block delimiter — Kadence retains its namespace prefix (unlike core/*).
	 */
	private function create_block_delimiter( string $block_name, array $attributes = [] ): string {
		$delimiter = '<!-- wp:' . $block_name;

		if ( ! empty( $attributes ) ) {
			$encoded = function_exists( 'wp_json_encode' )
				? wp_json_encode( $attributes, JSON_UNESCAPED_SLASHES )
				: json_encode( $attributes, JSON_UNESCAPED_SLASHES );
			$delimiter .= ' ' . $encoded;
		}

		return $delimiter . ' -->';
	}

	private function create_closing_delimiter( string $block_name ): string {
		return '<!-- /wp:' . $block_name . ' -->';
	}

	/**
	 * Generate a Kadence-style uniqueID. Kadence uses a short alphanumeric
	 * suffix unique to the post; counter-based suffix is sufficient for export.
	 */
	private function next_unique_id(): string {
		++$this->id_counter;
		return '_kb' . str_pad( (string) $this->id_counter, 3, '0', STR_PAD_LEFT ) . '-' . substr( md5( (string) $this->id_counter ), 0, 4 );
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
		return [
			'row',
			'container',
			'column',
			'heading',
			'button',
			'icon',
			'image',
			'spacer',
			'divider',
			'infobox',
			'card',
			'tabs',
			'accordion',
			'posts',
			'text',
			'list',
			'quote',
			'code',
			'html',
			'video',
		];
	}

	/**
	 * @inheritDoc
	 */
	public function supports_type( string $type ): bool {
		return in_array( $type, $this->get_supported_types(), true );
	}

	/**
	 * @inheritDoc
	 */
	public function get_fallback( DEVTB_Component $component ) {
		$content = $component->content ?? '';
		return '<!-- wp:html -->' . "\n" . $content . "\n" . '<!-- /wp:html -->' . "\n\n";
	}
}
