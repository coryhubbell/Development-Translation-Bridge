<?php
/**
 * Thrive Themes Converter (Architect + Theme Builder + Suite passthrough)
 *
 * Converts universal components into Thrive Content Builder (TCB) HTML markup.
 * Thrive's styling model is unusual: every element gets an opaque `data-css`
 * token (e.g. `tve-u-167abc12345`), and a single inline `<style>` block at the
 * bottom of the output resolves each token to its rule set. This converter
 * builds both halves in lockstep so output is self-contained.
 *
 * Surface covered:
 * - Thrive Architect HTML (page builder output)
 * - Thrive Theme Builder template parts (same TCB markup, just header/footer
 *   wrappers)
 * - Suite extras (Leads, Quiz Builder, Apprentice, Ultimatum) are embedded as
 *   shortcodes inside Architect content and pass through unchanged.
 *
 * @package DevelopmentTranslation_Bridge
 * @subpackage Translation_Bridge
 * @since 4.2.0
 */

namespace DEVTB\TranslationBridge\Converters;

use DEVTB\TranslationBridge\Core\DEVTB_Converter_Interface;
use DEVTB\TranslationBridge\Models\DEVTB_Component;

/**
 * Class DEVTB_Thrive_Converter
 */
class DEVTB_Thrive_Converter implements DEVTB_Converter_Interface {

	/**
	 * Upstream framework version (Thrive Architect) this converter is
	 * calibrated against. Thrive Theme Builder ships independently; current
	 * versions of both share the TCB markup format.
	 */
	public const TARGET_CMS_VERSION = '10.8.10';

	/**
	 * Pass-through shortcodes from Thrive Suite extras (Leads/Quiz/Apprentice/
	 * Ultimatum). These are emitted verbatim in Architect content and the
	 * converter should not try to translate them.
	 */
	private const SUITE_SHORTCODE_PREFIXES = [
		'thrive_leads',
		'thrive_2step',
		'thrive_optin',
		'tcb-quiz',
		'thrive_quiz',
		'thrive_ultimatum',
		'tve_leads',
		'tva_',           // Thrive Apprentice
	];

	/**
	 * Counter feeding the `tve-u-XXXXX` token generator.
	 *
	 * @var int
	 */
	private int $token_counter = 0;

	/**
	 * Pending style rules keyed by `data-css` token, collected during
	 * conversion and flushed into the trailing `<style>` block.
	 *
	 * @var array<string, string>
	 */
	private array $style_rules = [];

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
		// Reset per-conversion state so repeated invocations don't accumulate.
		$this->token_counter = 0;
		$this->style_rules   = [];

		$components = is_array( $component ) ? $component : [ $component ];
		$body       = '';

		foreach ( $components as $comp ) {
			if ( $comp instanceof DEVTB_Component ) {
				$body .= $this->convert_component( $comp );
			}
		}

		return $body . "\n" . $this->render_style_block();
	}

	/**
	 * @inheritDoc
	 */
	public function convert_component( DEVTB_Component $component ): string {
		$type = $component->type;

		if ( $type === 'container' || $type === 'row' ) {
			return $this->convert_row( $component );
		} elseif ( $type === 'column' ) {
			return $this->convert_column( $component );
		} elseif ( $type === 'shortcode' || $this->is_passthrough_shortcode( $component->content ?? '' ) ) {
			return $this->convert_shortcode_passthrough( $component );
		}

		return $this->convert_element( $component );
	}

	/**
	 * Section / row → tve-flex-row wrapper.
	 */
	private function convert_row( DEVTB_Component $component ): string {
		$token = $this->register_token( $this->build_section_css( $component->attributes ) );

		$inner = '';
		foreach ( $component->children as $child ) {
			$inner .= $this->convert_component( $child );
		}

		return sprintf(
			'<div class="tve_flt tcb-flex-row" data-css="%s">%s</div>',
			esc_attr( $token ),
			$inner
		);
	}

	/**
	 * Column → tve-flex-cell.
	 */
	private function convert_column( DEVTB_Component $component ): string {
		$width_pct = isset( $component->attributes['width'] ) ? floatval( $component->attributes['width'] ) : 100.0;
		$css       = sprintf( 'width:%s%%;', $this->trim_zeros( $width_pct ) );
		$css      .= $this->build_box_css( $component->attributes );
		$token     = $this->register_token( $css );

		$inner = '';
		foreach ( $component->children as $child ) {
			$inner .= $this->convert_component( $child );
		}

		return sprintf(
			'<div class="tcb-flex-col" data-css="%s">%s</div>',
			esc_attr( $token ),
			$inner
		);
	}

	/**
	 * Translate a leaf component (heading/text/button/image/etc.).
	 */
	private function convert_element( DEVTB_Component $component ): string {
		$type    = $component->type;
		$content = $component->content ?? '';
		$attrs   = $component->attributes ?? [];

		$css   = $this->build_element_css( $type, $attrs );
		$token = $this->register_token( $css );

		switch ( $type ) {
			case 'heading':
				$level = max( 1, min( 6, intval( $attrs['level'] ?? 2 ) ) );
				return sprintf(
					'<h%1$d class="tve_h%1$d" data-css="%2$s">%3$s</h%1$d>',
					$level,
					esc_attr( $token ),
					esc_html( $content )
				);

			case 'text':
			case 'paragraph':
				return sprintf(
					'<p class="tve_p" data-css="%s">%s</p>',
					esc_attr( $token ),
					esc_html( $content )
				);

			case 'button':
				$href  = $attrs['href'] ?? '#';
				$label = $content !== '' ? $content : ( $attrs['label'] ?? 'Button' );
				return sprintf(
					'<div class="tcb-button-block" data-css="%s"><a href="%s" class="tcb-button-link"><span class="tcb-button-texts">%s</span></a></div>',
					esc_attr( $token ),
					esc_url( $href ),
					esc_html( $label )
				);

			case 'image':
				$src = $attrs['src'] ?? '';
				$alt = $attrs['alt'] ?? '';
				return sprintf(
					'<div class="tve_image_caption" data-css="%s"><img src="%s" alt="%s" class="tve_image"/></div>',
					esc_attr( $token ),
					esc_url( $src ),
					esc_attr( $alt )
				);

			case 'divider':
				return sprintf( '<div class="tcb-style-wrap tve-divider" data-css="%s"></div>', esc_attr( $token ) );

			case 'spacer':
				$height = intval( $attrs['height'] ?? 40 );
				return sprintf(
					'<div class="thrv_responsive_spacer" data-css="%s" style="height:%dpx" aria-hidden="true"></div>',
					esc_attr( $token ),
					$height
				);

			case 'icon':
				return sprintf( '<span class="tve_ea_icon" data-css="%s" aria-hidden="true"></span>', esc_attr( $token ) );

			case 'html':
				return $content;

			default:
				return sprintf(
					'<div class="tcb-element" data-css="%s">%s</div>',
					esc_attr( $token ),
					esc_html( $content )
				);
		}
	}

	/**
	 * Pass Thrive Suite shortcodes through unchanged.
	 */
	private function convert_shortcode_passthrough( DEVTB_Component $component ): string {
		$content = $component->content ?? '';
		// Wrap in TCB marker so the Theme Builder can re-parse it on import.
		return sprintf(
			'<div class="thrv_text_element tcb-shortcode-passthrough">%s</div>',
			$content
		);
	}

	/**
	 * Detect Thrive Suite shortcodes (leads, quiz, ultimatum, apprentice).
	 */
	private function is_passthrough_shortcode( string $content ): bool {
		if ( strpos( $content, '[' ) === false ) {
			return false;
		}
		foreach ( self::SUITE_SHORTCODE_PREFIXES as $prefix ) {
			if ( preg_match( '/\[' . preg_quote( $prefix, '/' ) . '/', $content ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Build the section/row CSS rule body.
	 */
	private function build_section_css( array $attributes ): string {
		$css  = 'display:flex;flex-wrap:wrap;';
		$css .= $this->build_box_css( $attributes );
		if ( isset( $attributes['background-color'] ) ) {
			$css .= 'background-color:' . $attributes['background-color'] . ';';
		}
		return $css;
	}

	/**
	 * Build the per-leaf CSS rule body for a given element type.
	 */
	private function build_element_css( string $type, array $attributes ): string {
		$css = $this->build_box_css( $attributes );

		if ( isset( $attributes['color'] ) ) {
			$css .= 'color:' . $attributes['color'] . ';';
		}
		if ( isset( $attributes['background-color'] ) ) {
			$css .= 'background-color:' . $attributes['background-color'] . ';';
		}
		if ( isset( $attributes['font-size'] ) ) {
			$css .= 'font-size:' . $attributes['font-size'] . ';';
		}
		if ( isset( $attributes['font-weight'] ) ) {
			$css .= 'font-weight:' . $attributes['font-weight'] . ';';
		}
		if ( isset( $attributes['text-align'] ) ) {
			$css .= 'text-align:' . $attributes['text-align'] . ';';
		}

		if ( $type === 'button' ) {
			$css .= 'display:inline-block;cursor:pointer;';
		}

		return $css;
	}

	/**
	 * Collapse padding/margin shorthand from universal attributes.
	 */
	private function build_box_css( array $attributes ): string {
		$css = '';

		foreach ( [ 'padding', 'margin' ] as $box ) {
			$sides = [
				$attributes[ $box . '-top' ]    ?? null,
				$attributes[ $box . '-right' ]  ?? null,
				$attributes[ $box . '-bottom' ] ?? null,
				$attributes[ $box . '-left' ]   ?? null,
			];
			if ( ! array_filter( $sides, static fn( $v ) => $v !== null && $v !== '' ) ) {
				continue;
			}
			$parts = array_map(
				static fn( $v ) => ( $v === null || $v === '' ) ? '0' : (string) $v,
				$sides
			);
			$css .= $box . ':' . implode( ' ', $parts ) . ';';
		}

		return $css;
	}

	/**
	 * Register a CSS rule under a fresh `tve-u-*` token and return that token.
	 * Returns an empty string if the rule body is empty (caller omits attr).
	 */
	private function register_token( string $css ): string {
		$css = trim( $css );
		if ( $css === '' ) {
			++$this->token_counter;
			$token = $this->build_token();
		} else {
			++$this->token_counter;
			$token                       = $this->build_token();
			$this->style_rules[ $token ] = $css;
		}
		return $token;
	}

	/**
	 * Build a 13-char `tve-u-XXXXXXXXXXX` token. Thrive uses opaque IDs;
	 * we use the counter + a short md5 suffix for stability across reruns.
	 */
	private function build_token(): string {
		$suffix = substr( md5( (string) $this->token_counter ), 0, 11 );
		return 'tve-u-' . $suffix;
	}

	/**
	 * Render the trailing `<style>` block resolving all data-css tokens.
	 */
	private function render_style_block(): string {
		if ( empty( $this->style_rules ) ) {
			return '';
		}

		$rules = '';
		foreach ( $this->style_rules as $token => $css ) {
			$rules .= sprintf( '.%s{%s}', $token, $css ) . "\n";
		}

		return "<style type=\"text/css\" class=\"tve_custom_style\">\n" . $rules . '</style>';
	}

	/**
	 * Drop trailing zeros from a width percentage like 33.333333 → 33.33.
	 */
	private function trim_zeros( float $n ): string {
		return rtrim( rtrim( sprintf( '%.2F', $n ), '0' ), '.' );
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
		return [
			'row',
			'container',
			'column',
			'heading',
			'text',
			'paragraph',
			'button',
			'image',
			'divider',
			'spacer',
			'icon',
			'html',
			'shortcode',
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
		return sprintf( '<div class="tcb-fallback">%s</div>', esc_html( $content ) );
	}
}
