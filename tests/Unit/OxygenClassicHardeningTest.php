<?php
/**
 * Classic Oxygen (4.x) hardening tests.
 *
 * Pins the hardened classic Oxygen support:
 * - All real storage shapes parse: nested root tree (the actual
 *   ct_builder_json shape — also the committed fixture), the
 *   ct_builder_json wrapper, the flat ct_parent list, and
 *   ct_builder_shortcodes strings.
 * - Real element vocabulary on emit (no fabricated ct_* names).
 * - Full style passthrough with unit normalization (Oxygen unitless ↔ CSS px).
 * - Responsive options.media round-tripping via the canonical model.
 * - Deterministic output (no time()-based selectors).
 *
 * @package DevelopmentTranslationBridge
 */

use PHPUnit\Framework\TestCase;
use DEVTB\TranslationBridge\Parsers\DEVTB_Oxygen_Parser;
use DEVTB\TranslationBridge\Converters\DEVTB_Oxygen_Converter;
use DEVTB\TranslationBridge\Models\DEVTB_Component;
use DEVTB\TranslationBridge\Utils\DEVTB_Responsive_Helper;

class OxygenClassicHardeningTest extends TestCase {

	private const FABRICATED_NAMES = [
		'ct_link_text', 'ct_icon', 'ct_tabs', 'ct_tab', 'ct_accordion',
		'ct_toggle', 'ct_google_map', 'ct_testimonial', 'ct_pricing_box',
		'ct_progress_bar', 'ct_nav_menu', 'ct_menu', 'ct_gallery',
		'oxy_accordion', 'oxy_counter', 'oxy_testimonial', 'ct_icon_box',
		'ct_contact_form',
	];

	private function fixture(): string {
		return file_get_contents( DEVTB_ROOT . '/tests/fixtures/oxygen/simple-page.json' );
	}

	// -----------------------------------------------------------------
	// Input shapes
	// -----------------------------------------------------------------

	public function testParsesTheNestedTreeFixture(): void {
		$parser     = new DEVTB_Oxygen_Parser();
		$components = $parser->parse( $this->fixture() );

		$this->assertCount( 4, $components, 'fixture has four root sections' );
		$this->assertSame( 'container', $components[0]->type );

		// Heading content and children parse from nested `children`.
		$heading = $components[0]->children[0];
		$this->assertSame( 'heading', $heading->type );
		$this->assertNotSame( '', $heading->content );

		// Buttons carry url attributes.
		$buttons_wrap = $components[0]->children[2];
		$this->assertNotEmpty( $buttons_wrap->children );
		$button = $buttons_wrap->children[0];
		$this->assertSame( 'button', $button->type );
		$this->assertArrayHasKey( 'url', $button->attributes );
	}

	public function testFixtureStylesPassThroughWithUnitNormalization(): void {
		$parser     = new DEVTB_Oxygen_Parser();
		$components = $parser->parse( $this->fixture() );

		$section = $components[0];
		// Previously dropped by the 33-prop allow-list: gap + border shorthand.
		$flex_wrap = $section->children[2];
		$this->assertSame( '10px', $flex_wrap->styles['gap'] ?? null, 'gap must pass through' );

		$outline_button = $flex_wrap->children[1];
		$this->assertSame( '2px solid #ffffff', $outline_button->styles['border'] ?? null, 'border shorthand must pass through' );

		// Oxygen unitless numerics normalize to valid CSS.
		$this->assertSame( '80px', $section->styles['padding-top'] ?? null, 'unitless 80 must normalize to 80px' );
	}

	public function testFixtureTestimonialFieldsExtract(): void {
		$parser     = new DEVTB_Oxygen_Parser();
		$components = $parser->parse( $this->fixture() );

		$testimonial = $components[2]->children[1]->children[0];
		$this->assertSame( 'testimonial', $testimonial->type );
		$this->assertNotSame( '', $testimonial->content, 'quote must surface as content' );
		$this->assertArrayHasKey( 'author', $testimonial->attributes );
	}

	public function testParsesCtBuilderJsonWrapper(): void {
		$wrapped = wp_json_encode( [ 'ct_builder_json' => json_decode( $this->fixture(), true ) ] );

		$components = ( new DEVTB_Oxygen_Parser() )->parse( $wrapped );
		$this->assertCount( 4, $components );
	}

	public function testParsesShortcodeStorage(): void {
		$shortcodes = '[ct_section ct_sign_sha256="x" ct_options=\'{"ct_id":1,"ct_parent":0,"selector":"section-1-9"}\']'
			. '[ct_headline ct_options=\'{"ct_id":2,"ct_parent":1,"ct_content":"Shortcode Heading","tag":"h2"}\'][/ct_headline]'
			. '[/ct_section]';

		$parser     = new DEVTB_Oxygen_Parser();
		$this->assertTrue( $parser->is_valid_content( $shortcodes ) );

		$components = $parser->parse( $shortcodes );
		$this->assertCount( 1, $components );
		$this->assertSame( 'container', $components[0]->type );
		$this->assertCount( 1, $components[0]->children );
		$this->assertSame( 'Shortcode Heading', $components[0]->children[0]->content );
	}

	// -----------------------------------------------------------------
	// Emission
	// -----------------------------------------------------------------

	public function testConverterEmitsRootTreeWithRealNames(): void {
		$components = [
			new DEVTB_Component( [ 'type' => 'container', 'category' => 'layout', 'content' => '' ] ),
			new DEVTB_Component( [ 'type' => 'testimonial', 'category' => 'content', 'content' => 'Great!', 'attributes' => [ 'author' => 'Ada' ] ] ),
			new DEVTB_Component( [ 'type' => 'link', 'category' => 'interactive', 'content' => 'More', 'attributes' => [ 'url' => '#' ] ] ),
			new DEVTB_Component( [ 'type' => 'tabs', 'category' => 'interactive', 'content' => '' ] ),
			new DEVTB_Component( [ 'type' => 'progress', 'category' => 'interactive', 'content' => '' ] ),
			new DEVTB_Component( [ 'type' => 'map', 'category' => 'media', 'content' => '' ] ),
		];

		$tree = json_decode( ( new DEVTB_Oxygen_Converter() )->convert( $components ), true );

		$this->assertSame( 'root', $tree['name'] );
		$this->assertSame( 0, $tree['id'] );

		$names = array_column( $tree['children'], 'name' );
		$this->assertSame(
			[ 'ct_section', 'oxy_testimonial_box', 'ct_link', 'oxy_tabs', 'oxy_progress_bar', 'oxy_map' ],
			$names
		);

		foreach ( $names as $name ) {
			$this->assertNotContains( $name, self::FABRICATED_NAMES );
		}

		// Containers carry no ct_content.
		$this->assertArrayNotHasKey( 'ct_content', $tree['children'][0]['options'] );
		// Testimonial content lands in testimonial_text.
		$this->assertSame( 'Great!', $tree['children'][1]['options']['testimonial_text'] );
		$this->assertSame( 'Ada', $tree['children'][1]['options']['author'] );
	}

	public function testConverterOutputIsDeterministic(): void {
		$component = new DEVTB_Component( [
			'type'       => 'heading',
			'category'   => 'content',
			'content'    => 'Hello',
			'attributes' => [ 'level' => 2 ],
		] );

		$first  = ( new DEVTB_Oxygen_Converter() )->convert( $component );
		$second = ( new DEVTB_Oxygen_Converter() )->convert( $component );

		$this->assertSame( $first, $second, 'output must be reproducible (no time()-based selectors)' );
	}

	// -----------------------------------------------------------------
	// Responsive media round-trip
	// -----------------------------------------------------------------

	public function testResponsiveMediaRoundTrips(): void {
		$input = wp_json_encode( [
			[
				'id'      => 1,
				'name'    => 'ct_headline',
				'options' => [
					'ct_id'      => 1,
					'ct_parent'  => 0,
					'ct_content' => 'Hi',
					'tag'        => 'h2',
					'original'   => [ 'font-size' => '32' ],
					'media'      => [
						'tablet'         => [ 'original' => [ 'font-size' => '24' ] ],
						'phone-portrait' => [ 'original' => [ 'font-size' => '18' ] ],
					],
				],
			],
		] );

		$parser     = new DEVTB_Oxygen_Parser();
		$components = $parser->parse( $input );
		$this->assertCount( 1, $components );

		$canonical = $components[0]->metadata[ DEVTB_Responsive_Helper::METADATA_KEY ]['styles'] ?? null;
		$this->assertIsArray( $canonical, 'media overrides must canonicalize' );
		$this->assertSame( '24px', $canonical['tablet']['default']['font-size'] ?? null );
		$this->assertSame( '18px', $canonical['phone']['default']['font-size'] ?? null );
		$this->assertSame( '32px', $canonical['desktop']['default']['font-size'] ?? null );

		// Emit back out: media bag re-created with unitless values.
		$tree    = json_decode( ( new DEVTB_Oxygen_Converter() )->convert( $components ), true );
		$options = $tree['children'][0]['options'];
		$this->assertSame( '24', $options['media']['tablet']['original']['font-size'] ?? null );
		$this->assertSame( '18', $options['media']['phone-portrait']['original']['font-size'] ?? null );
		$this->assertSame( '32', $options['original']['font-size'] ?? null );
	}

	// -----------------------------------------------------------------
	// Full round trip on the fixture
	// -----------------------------------------------------------------

	public function testFixtureRoundTripPreservesContentAndStructure(): void {
		$parser = new DEVTB_Oxygen_Parser();

		$first_pass = $parser->parse( $this->fixture() );
		$emitted    = ( new DEVTB_Oxygen_Converter() )->convert( $first_pass );
		$second     = $parser->parse( $emitted );

		$this->assertCount( count( $first_pass ), $second, 'root count must survive the round trip' );
		$this->assertSame( $first_pass[0]->children[0]->content, $second[0]->children[0]->content, 'heading content must survive' );
		$this->assertSame(
			$first_pass[0]->styles['padding-top'] ?? null,
			$second[0]->styles['padding-top'] ?? null,
			'unit normalization must be stable across round trips'
		);
	}
}
