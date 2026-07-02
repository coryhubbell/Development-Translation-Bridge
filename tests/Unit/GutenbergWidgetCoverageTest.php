<?php
/**
 * Gutenberg Converter Widget Coverage Test
 *
 * Verifies that the Gutenberg converter no longer collapses Elementor widget types
 * into empty paragraphs, and that compound widgets emit native Gutenberg blocks
 * while marker fallbacks preserve content with a visible devtb annotation.
 *
 * Targets the v4.3.4 fix for the production Elementor → Gutenberg gap.
 *
 * @package DevelopmentTranslation_Bridge
 * @subpackage Tests
 * @since 4.3.4
 */

namespace DEVTB\Tests\Unit;

use PHPUnit\Framework\TestCase;
use DEVTB\TranslationBridge\Converters\DEVTB_Gutenberg_Converter;
use DEVTB\TranslationBridge\Models\DEVTB_Component;

class GutenbergWidgetCoverageTest extends TestCase {

	private DEVTB_Gutenberg_Converter $converter;

	protected function setUp(): void {
		parent::setUp();

		$bridge_path = DEVTB_TRANSLATION_BRIDGE;
		require_once $bridge_path . '/models/class-component.php';
		require_once $bridge_path . '/utils/class-html-helper.php';
		require_once $bridge_path . '/utils/class-css-helper.php';
		require_once $bridge_path . '/utils/class-json-helper.php';
		require_once $bridge_path . '/core/interface-converter.php';
		require_once $bridge_path . '/converters/class-gutenberg-converter.php';

		$this->converter = new DEVTB_Gutenberg_Converter();
	}

	/**
	 * Simple heading widget should emit a real wp:heading block (canonical, no core/ namespace).
	 */
	public function test_heading_emits_canonical_heading_block(): void {
		$c = new DEVTB_Component([
			'type'       => 'heading',
			'content'    => 'Section title',
			'attributes' => [ 'level' => 'h2' ],
		]);

		$out = $this->converter->convert( $c );

		$this->assertStringContainsString( '<!-- wp:heading', $out );
		$this->assertStringContainsString( 'Section title', $out );
		// Canonical class required since WP 6.3.
		$this->assertStringContainsString( 'wp-block-heading', $out );
		// Canonical Gutenberg drops the `core/` namespace.
		$this->assertStringNotContainsString( 'wp:core/heading', $out );
		$this->assertStringNotContainsString( '<!-- wp:paragraph -->', $out );
	}

	/**
	 * Icon-list widget (universal type 'list') with items in attributes -> real list with
	 * canonical core/list-item innerBlocks (WP 6.0+).
	 */
	public function test_icon_list_renders_canonical_list_items(): void {
		$c = new DEVTB_Component([
			'type'       => 'list',
			'attributes' => [
				'items' => [
					[ 'text' => 'Feature A' ],
					[ 'text' => 'Feature B' ],
					[ 'text' => 'Feature C' ],
				],
			],
		]);

		$out = $this->converter->convert( $c );

		$this->assertStringContainsString( '<!-- wp:list', $out );
		// Canonical WP 6.0+ shape: list contains list-item innerBlocks.
		$this->assertStringContainsString( '<!-- wp:list-item', $out );
		$this->assertStringContainsString( '<li>Feature A</li>', $out );
		$this->assertStringContainsString( '<li>Feature B</li>', $out );
		$this->assertStringContainsString( '<li>Feature C</li>', $out );
	}

	/**
	 * Tabs widget should emit a stacked group of headings + paragraphs, not collapse to paragraph.
	 */
	public function test_tabs_renders_stacked_heading_group(): void {
		$c = new DEVTB_Component([
			'type'       => 'tabs',
			'attributes' => [
				'tabs' => [
					[ 'tab_title' => 'Tab one', 'tab_content' => 'Body one.' ],
					[ 'tab_title' => 'Tab two', 'tab_content' => 'Body two.' ],
				],
			],
		]);

		$out = $this->converter->convert( $c );

		$this->assertStringContainsString( '<!-- wp:group', $out );
		$this->assertStringContainsString( 'devtb-tabs-converted', $out );
		$this->assertStringContainsString( 'Tab one', $out );
		$this->assertStringContainsString( 'Body one.', $out );
		$this->assertStringContainsString( 'Tab two', $out );
		$this->assertStringContainsString( '<!-- wp:heading', $out );
	}

	public function test_accordion_renders_stacked_heading_group(): void {
		$c = new DEVTB_Component([
			'type'       => 'accordion',
			'attributes' => [
				'tabs' => [
					[ 'tab_title' => 'Question?', 'tab_content' => 'Answer.' ],
				],
			],
		]);

		$out = $this->converter->convert( $c );

		$this->assertStringContainsString( 'devtb-accordion-converted', $out );
		$this->assertStringContainsString( 'Question?', $out );
		$this->assertStringContainsString( 'Answer.', $out );
	}

	public function test_card_compound_renders_image_heading_paragraph_button(): void {
		$c = new DEVTB_Component([
			'type'       => 'card',
			'attributes' => [
				'image_url'   => 'https://example.com/icon.png',
				'alt_text'    => 'Icon',
				'heading'     => 'Card title',
				'description' => 'Card description.',
				'url'         => 'https://example.com/learn',
				'button_text' => 'Learn more',
			],
		]);

		$out = $this->converter->convert( $c );

		$this->assertStringContainsString( 'devtb-card-converted', $out );
		$this->assertStringContainsString( '<!-- wp:image', $out );
		$this->assertStringContainsString( 'https://example.com/icon.png', $out );
		$this->assertStringContainsString( 'Card title', $out );
		$this->assertStringContainsString( 'Card description.', $out );
		$this->assertStringContainsString( '<!-- wp:buttons', $out );
		$this->assertStringContainsString( 'Learn more', $out );
		$this->assertStringContainsString( 'https://example.com/learn', $out );
		// Canonical button class required by theme.json since WP 6.1.
		$this->assertStringContainsString( 'wp-element-button', $out );
	}

	public function test_counter_renders_heading_with_number(): void {
		$c = new DEVTB_Component([
			'type'       => 'counter',
			'attributes' => [
				'ending_number' => 250,
				'prefix'        => '$',
				'suffix'        => '+',
				'title'         => 'Happy customers',
			],
		]);

		$out = $this->converter->convert( $c );

		$this->assertStringContainsString( 'devtb-counter-converted', $out );
		$this->assertStringContainsString( '<!-- wp:heading', $out );
		$this->assertStringContainsString( '$250+', $out );
		$this->assertStringContainsString( 'Happy customers', $out );
	}

	public function test_testimonial_renders_quote_with_cite(): void {
		$c = new DEVTB_Component([
			'type'       => 'testimonial',
			'attributes' => [
				'testimonial_content' => 'The best decision we ever made.',
				'testimonial_name'    => 'Jane Doe',
				'testimonial_job'     => 'CTO, Acme',
			],
		]);

		$out = $this->converter->convert( $c );

		$this->assertStringContainsString( '<!-- wp:quote', $out );
		$this->assertStringContainsString( 'The best decision we ever made.', $out );
		$this->assertStringContainsString( 'Jane Doe', $out );
		$this->assertStringContainsString( 'CTO, Acme', $out );
		$this->assertStringContainsString( '<cite>', $out );
	}

	public function test_pricing_table_renders_full_group(): void {
		$c = new DEVTB_Component([
			'type'       => 'pricing-table',
			'attributes' => [
				'heading'         => 'Pro',
				'currency_symbol' => '$',
				'price'           => '49',
				'period'          => 'mo',
				'features'        => [
					[ 'item_text' => 'Unlimited projects' ],
					[ 'item_text' => 'Priority support' ],
				],
				'button_text'     => 'Sign up',
				'button_url'      => 'https://example.com/signup',
			],
		]);

		$out = $this->converter->convert( $c );

		$this->assertStringContainsString( 'devtb-pricing-converted', $out );
		$this->assertStringContainsString( 'Pro', $out );
		$this->assertStringContainsString( '$49 / mo', $out );
		$this->assertStringContainsString( 'Unlimited projects', $out );
		$this->assertStringContainsString( 'Priority support', $out );
		$this->assertStringContainsString( 'Sign up', $out );
		// Pricing list must use canonical list-item innerBlocks.
		$this->assertStringContainsString( '<!-- wp:list-item', $out );
	}

	public function test_alert_renders_styled_group(): void {
		$c = new DEVTB_Component([
			'type'       => 'alert',
			'attributes' => [
				'alert_type'        => 'success',
				'alert_title'       => 'Great!',
				'alert_description' => 'It worked.',
			],
		]);

		$out = $this->converter->convert( $c );

		$this->assertStringContainsString( '<!-- wp:group', $out );
		$this->assertStringContainsString( 'devtb-alert', $out );
		$this->assertStringContainsString( 'is-style-success', $out );
		$this->assertStringContainsString( 'Great!', $out );
		$this->assertStringContainsString( 'It worked.', $out );
	}

	public function test_cta_renders_heading_paragraph_button(): void {
		$c = new DEVTB_Component([
			'type'       => 'cta',
			'attributes' => [
				'heading'     => 'Ready to start?',
				'description' => 'Sign up in seconds.',
				'url'         => 'https://example.com/start',
				'button_text' => 'Get started',
			],
		]);

		$out = $this->converter->convert( $c );

		$this->assertStringContainsString( 'devtb-cta-converted', $out );
		$this->assertStringContainsString( '<!-- wp:heading', $out );
		$this->assertStringContainsString( 'Ready to start?', $out );
		$this->assertStringContainsString( '<!-- wp:buttons', $out );
		$this->assertStringContainsString( 'Get started', $out );
	}

	public function test_form_widget_falls_back_to_marker_html(): void {
		$c = new DEVTB_Component([
			'type'       => 'form',
			'content'    => 'Contact form here',
			'attributes' => [
				'heading' => 'Contact us',
			],
			'metadata'   => [
				'source_framework' => 'elementor',
				'original_type'    => 'form',
			],
		]);

		$out = $this->converter->convert( $c );

		$this->assertStringContainsString( '<!-- wp:html', $out );
		$this->assertStringContainsString( 'devtb: unconverted elementor widget "form"', $out );
		$this->assertStringContainsString( 'data-devtb-source="elementor:form"', $out );
		$this->assertStringContainsString( 'Contact us', $out );
		$this->assertStringContainsString( 'Contact form here', $out );
		// Critical: must NOT silently collapse to an empty paragraph
		$this->assertStringNotContainsString( '<!-- wp:paragraph -->', $out );
	}

	public function test_unknown_type_emits_marker(): void {
		$c = new DEVTB_Component([
			'type'       => 'unknown',
			'content'    => 'Some preserved text',
			'metadata'   => [
				'source_framework' => 'elementor',
				'original_type'    => 'some-third-party-widget',
			],
		]);

		$out = $this->converter->convert( $c );

		$this->assertStringContainsString( '<!-- wp:html', $out );
		$this->assertStringContainsString( 'devtb: unconverted elementor widget "some-third-party-widget"', $out );
		$this->assertStringContainsString( 'Some preserved text', $out );
	}

	public function test_gallery_renders_with_image_figures_and_ids(): void {
		$c = new DEVTB_Component([
			'type'       => 'gallery',
			'attributes' => [
				'images' => [
					[ 'id' => 11, 'url' => 'https://example.com/1.jpg', 'alt' => 'One' ],
					[ 'id' => 22, 'url' => 'https://example.com/2.jpg', 'alt' => 'Two' ],
				],
			],
		]);

		$out = $this->converter->convert( $c );

		$this->assertStringContainsString( '<!-- wp:gallery', $out );
		$this->assertStringContainsString( '"ids":[11,22]', $out );
		$this->assertStringContainsString( 'https://example.com/1.jpg', $out );
		$this->assertStringContainsString( 'https://example.com/2.jpg', $out );
	}

	public function test_social_icons_and_nav_map_to_native_blocks(): void {
		$social = new DEVTB_Component([
			'type'       => 'social-icons',
			'attributes' => [
				'icons' => [
					[ 'service' => 'twitter', 'url' => 'https://twitter.com/x' ],
					[ 'service' => 'github',  'url' => 'https://github.com/x' ],
				],
			],
		]);
		$nav = new DEVTB_Component([ 'type' => 'nav' ]);

		$social_out = $this->converter->convert( $social );
		$nav_out    = $this->converter->convert( $nav );

		$this->assertStringContainsString( '<!-- wp:social-links', $social_out );
		$this->assertStringContainsString( 'twitter', $social_out );
		$this->assertStringContainsString( 'github', $social_out );

		$this->assertStringContainsString( '<!-- wp:navigation', $nav_out );
	}

	public function test_text_widget_still_maps_to_paragraph(): void {
		$c = new DEVTB_Component([
			'type'    => 'text',
			'content' => 'Hello world.',
		]);

		$out = $this->converter->convert( $c );

		$this->assertStringContainsString( '<!-- wp:paragraph', $out );
		$this->assertStringContainsString( 'Hello world.', $out );
	}

	public function test_text_widget_preserves_existing_paragraph_html(): void {
		$c = new DEVTB_Component([
			'type'    => 'text',
			'content' => '<p>Already wrapped.</p>',
		]);

		$out = $this->converter->convert( $c );

		$this->assertStringContainsString( '<p>Already wrapped.</p>', $out );
		$this->assertStringNotContainsString( '<p><p>Already wrapped.</p></p>', $out );
	}

	public function test_get_supported_types_includes_new_widgets(): void {
		$types = $this->converter->get_supported_types();

		$expected_new = [
			'tabs', 'accordion', 'card', 'cta', 'counter', 'testimonial',
			'pricing-table', 'alert',
			'slider', 'form', 'countdown', 'portfolio', 'toc', 'map',
			'progress', 'rating', 'unknown',
			'social-icons', 'nav',
		];

		foreach ( $expected_new as $type ) {
			$this->assertTrue(
				$this->converter->supports_type( $type ),
				"Expected supports_type({$type}) to return true"
			);
			$this->assertContains( $type, $types, "get_supported_types() missing {$type}" );
		}
	}
}
