<?php
/**
 * Responsive breakpoint round-trip tests.
 *
 * Verifies that tablet/phone breakpoints and hover states survive
 * parse → convert round trips for the three next-generation framework paths,
 * and transfer across frameworks through the canonical responsive model
 * (DEVTB_Responsive_Helper).
 *
 * @package DevelopmentTranslationBridge
 */

use PHPUnit\Framework\TestCase;
use DEVTB\TranslationBridge\Parsers\DEVTB_DIVI5_Parser;
use DEVTB\TranslationBridge\Parsers\DEVTB_Elementor4_Parser;
use DEVTB\TranslationBridge\Parsers\DEVTB_Oxygen6_Parser;
use DEVTB\TranslationBridge\Converters\DEVTB_DIVI5_Converter;
use DEVTB\TranslationBridge\Converters\DEVTB_Elementor4_Converter;
use DEVTB\TranslationBridge\Converters\DEVTB_Oxygen6_Converter;
use DEVTB\TranslationBridge\Utils\DEVTB_Responsive_Helper;

class ResponsiveRoundTripTest extends TestCase {

	// -----------------------------------------------------------------
	// DIVI 5 — content-field breakpoints + hover states
	// -----------------------------------------------------------------

	private const DIVI5_RESPONSIVE_HEADING =
		'<!-- wp:divi/heading {"content":{"text":{"desktop":{"value":"Desktop title","hover":"Hover title"},"tablet":{"value":"Tablet title"},"phone":{"value":"Phone title"}},"level":{"desktop":{"value":"h2"}}},"builderVersion":"5.0.0"} /-->';

	public function testDivi5ParserCanonicalizesBreakpointsAndStates(): void {
		$parser     = new DEVTB_DIVI5_Parser();
		$components = $parser->parse( self::DIVI5_RESPONSIVE_HEADING );

		$this->assertCount( 1, $components );
		$component = $components[0];

		// Desktop default still drives content.
		$this->assertSame( 'Desktop title', $component->content );

		$fields = $component->metadata[ DEVTB_Responsive_Helper::METADATA_KEY ]['fields'] ?? null;
		$this->assertIsArray( $fields, 'responsive fields must canonicalize into metadata' );
		$this->assertSame( 'Desktop title', $fields['text']['desktop']['default'] ?? null );
		$this->assertSame( 'Hover title', $fields['text']['desktop']['hover'] ?? null );
		$this->assertSame( 'Tablet title', $fields['text']['tablet']['default'] ?? null );
		$this->assertSame( 'Phone title', $fields['text']['phone']['default'] ?? null );

		// A desktop-default-only field must NOT canonicalize (no data to round-trip).
		$this->assertArrayNotHasKey( 'level', $fields );
	}

	public function testDivi5RoundTripPreservesBreakpointsAndStates(): void {
		$parser     = new DEVTB_DIVI5_Parser();
		$components = $parser->parse( self::DIVI5_RESPONSIVE_HEADING );

		$markup = ( new DEVTB_DIVI5_Converter() )->convert( $components[0] );

		// Re-parse the emitted markup and compare canonical field data.
		$reparsed = $parser->parse( $markup );
		$this->assertCount( 1, $reparsed );

		$fields = $reparsed[0]->metadata[ DEVTB_Responsive_Helper::METADATA_KEY ]['fields'] ?? null;
		$this->assertIsArray( $fields, 'round-tripped markup must retain responsive fields' );
		$this->assertSame( 'Desktop title', $fields['text']['desktop']['default'] ?? null );
		$this->assertSame( 'Hover title', $fields['text']['desktop']['hover'] ?? null );
		$this->assertSame( 'Tablet title', $fields['text']['tablet']['default'] ?? null );
		$this->assertSame( 'Phone title', $fields['text']['phone']['default'] ?? null );
	}

	// -----------------------------------------------------------------
	// Elementor 4 — style variants per breakpoint/state
	// -----------------------------------------------------------------

	private function elementor4NodeWithVariants(): array {
		return [
			'id'              => 'rsp12345',
			'version'         => '0.0',
			'elType'          => 'e-heading',
			'isInner'         => false,
			'interactions'    => [],
			'settings'        => [
				'title' => [
					'$$type' => 'html-v3',
					'value'  => [ 'content' => [ '$$type' => 'string', 'value' => 'Hi' ] ],
				],
			],
			'editor_settings' => [],
			'styles'          => [
				'e-abc123' => [
					'id'       => 'e-abc123',
					'type'     => 'class',
					'label'    => 'local',
					'variants' => [
						[
							'meta'  => [ 'breakpoint' => 'desktop', 'state' => null ],
							'props' => [ 'font-size' => '32px' ],
						],
						[
							'meta'  => [ 'breakpoint' => 'tablet', 'state' => null ],
							'props' => [ 'font-size' => '24px' ],
						],
						[
							'meta'  => [ 'breakpoint' => 'mobile', 'state' => null ],
							'props' => [ 'font-size' => '18px' ],
						],
						[
							'meta'  => [ 'breakpoint' => 'desktop', 'state' => 'hover' ],
							'props' => [ 'color' => '#ff0000' ],
						],
					],
				],
			],
			'elements'        => [],
		];
	}

	public function testElementor4ParserCanonicalizesVariants(): void {
		$parser     = new DEVTB_Elementor4_Parser();
		$components = $parser->parse( wp_json_encode( [ $this->elementor4NodeWithVariants() ] ) );

		$this->assertCount( 1, $components );
		$styles = $components[0]->metadata[ DEVTB_Responsive_Helper::METADATA_KEY ]['styles'] ?? null;

		$this->assertIsArray( $styles );
		$this->assertSame( '32px', $styles['desktop']['default']['font-size'] ?? null );
		$this->assertSame( '24px', $styles['tablet']['default']['font-size'] ?? null );
		$this->assertSame( '18px', $styles['phone']['default']['font-size'] ?? null, 'mobile must canonicalize to phone' );
		$this->assertSame( '#ff0000', $styles['desktop']['hover']['color'] ?? null );

		// Desktop defaults also surface as the component's flat styles.
		$this->assertSame( '32px', $components[0]->styles['font-size'] ?? null );
	}

	public function testElementor4RoundTripPreservesVariants(): void {
		$parser     = new DEVTB_Elementor4_Parser();
		$components = $parser->parse( wp_json_encode( [ $this->elementor4NodeWithVariants() ] ) );

		$decoded = json_decode( ( new DEVTB_Elementor4_Converter() )->convert( $components ), true );
		$styles  = $decoded[0]['styles'];
		$this->assertNotEmpty( $styles );

		$definition = reset( $styles );
		$variants   = $definition['variants'];

		$by_key = [];
		foreach ( $variants as $variant ) {
			$key            = ( $variant['meta']['breakpoint'] ?? '?' ) . ':' . ( $variant['meta']['state'] ?? 'default' );
			$by_key[ $key ] = $variant['props'];
		}

		$this->assertSame( '32px', $by_key['desktop:default']['font-size'] ?? null );
		$this->assertSame( '24px', $by_key['tablet:default']['font-size'] ?? null );
		$this->assertSame( '18px', $by_key['mobile:default']['font-size'] ?? null, 'phone must emit as the real mobile breakpoint key' );
		$this->assertSame( '#ff0000', $by_key['desktop:hover']['color'] ?? null );
	}

	// -----------------------------------------------------------------
	// Oxygen 6 — design tree breakpoint_* leaves
	// -----------------------------------------------------------------

	private function oxygen6NodeWithResponsiveDesign(): array {
		return [
			'tree'        => [
				'root' => [
					'id'       => 1,
					'data'     => [ 'type' => 'root', 'properties' => [] ],
					'children' => [
						[
							'id'        => 100,
							'data'      => [
								'type'       => 'EssentialElements\\Heading',
								'properties' => [
									'content' => [ 'content' => [ 'text' => 'Hi', 'tags' => 'h2' ] ],
									'design'  => [
										'layout' => [
											'gap' => [
												'breakpoint_base'            => '40px',
												'breakpoint_tablet_portrait' => '24px',
												'breakpoint_phone_portrait'  => '12px',
											],
										],
									],
								],
							],
							'children'  => [],
							'_parentId' => 1,
						],
					],
				],
			],
			'_nextNodeId' => 101,
		];
	}

	public function testOxygen6ParserCanonicalizesDesignBreakpoints(): void {
		$parser     = new DEVTB_Oxygen6_Parser();
		$components = $parser->parse( wp_json_encode( $this->oxygen6NodeWithResponsiveDesign() ) );

		$this->assertCount( 1, $components );
		$styles = $components[0]->metadata[ DEVTB_Responsive_Helper::METADATA_KEY ]['styles'] ?? null;

		$this->assertIsArray( $styles );
		$this->assertSame( '40px', $styles['desktop']['default']['layout.gap'] ?? null );
		$this->assertSame( '24px', $styles['tablet']['default']['layout.gap'] ?? null );
		$this->assertSame( '12px', $styles['phone']['default']['layout.gap'] ?? null );
	}

	public function testOxygen6RoundTripPreservesDesignBreakpoints(): void {
		$parser     = new DEVTB_Oxygen6_Parser();
		$components = $parser->parse( wp_json_encode( $this->oxygen6NodeWithResponsiveDesign() ) );

		$payload = json_decode( ( new DEVTB_Oxygen6_Converter() )->convert( $components ), true );
		$node    = $payload['tree']['root']['children'][0];
		$gap     = $node['data']['properties']['design']['layout']['gap'] ?? null;

		$this->assertIsArray( $gap, 'design tree must re-nest from canonical data' );
		$this->assertSame( '40px', $gap['breakpoint_base'] ?? null );
		$this->assertSame( '24px', $gap['breakpoint_tablet_portrait'] ?? null );
		$this->assertSame( '12px', $gap['breakpoint_phone_portrait'] ?? null );
	}

	// -----------------------------------------------------------------
	// Cross-framework — canonical styles transfer between frameworks
	// -----------------------------------------------------------------

	public function testOxygen6DesignTransfersToElementor4Variants(): void {
		$parser     = new DEVTB_Oxygen6_Parser();
		$components = $parser->parse( wp_json_encode( $this->oxygen6NodeWithResponsiveDesign() ) );

		$decoded = json_decode( ( new DEVTB_Elementor4_Converter() )->convert( $components ), true );
		$styles  = $decoded[0]['styles'];
		$this->assertNotEmpty( $styles, 'canonical responsive styles must cross frameworks' );

		$definition = reset( $styles );
		$by_key     = [];
		foreach ( $definition['variants'] as $variant ) {
			$key            = ( $variant['meta']['breakpoint'] ?? '?' );
			$by_key[ $key ] = $variant['props'];
		}

		$this->assertSame( '40px', $by_key['desktop']['layout.gap'] ?? null );
		$this->assertSame( '24px', $by_key['tablet']['layout.gap'] ?? null );
		$this->assertSame( '12px', $by_key['mobile']['layout.gap'] ?? null );
	}

	public function testElementor4VariantsTransferToOxygen6Design(): void {
		$parser     = new DEVTB_Elementor4_Parser();
		$components = $parser->parse( wp_json_encode( [ $this->elementor4NodeWithVariants() ] ) );

		$payload = json_decode( ( new DEVTB_Oxygen6_Converter() )->convert( $components ), true );
		$node    = $payload['tree']['root']['children'][0];
		$design  = $node['data']['properties']['design'] ?? null;

		$this->assertIsArray( $design );
		$this->assertSame( '32px', $design['font-size']['breakpoint_base'] ?? null );
		$this->assertSame( '24px', $design['font-size']['breakpoint_tablet_portrait'] ?? null );
		$this->assertSame( '18px', $design['font-size']['breakpoint_phone_portrait'] ?? null );
	}

	// -----------------------------------------------------------------
	// Elementor v3 — _tablet/_mobile/_hover setting suffixes
	// -----------------------------------------------------------------

	private function elementorV3WidgetWithSuffixes(): array {
		return [
			[
				'id'       => 'sec1',
				'elType'   => 'section',
				'settings' => [],
				'elements' => [
					[
						'id'       => 'col1',
						'elType'   => 'column',
						'settings' => [ '_column_size' => 100 ],
						'elements' => [
							[
								'id'         => 'w1',
								'elType'     => 'widget',
								'widgetType' => 'heading',
								'settings'   => [
									'title'         => 'Hi',
									'align'         => 'left',
									'align_tablet'  => 'center',
									'align_mobile'  => 'right',
									'color_hover'   => '#ff0000',
								],
								'elements'   => [],
							],
						],
					],
				],
			],
		];
	}

	public function testElementorV3ParserCanonicalizesSuffixes(): void {
		$parser     = new \DEVTB\TranslationBridge\Parsers\DEVTB_Elementor_Parser();
		$components = $parser->parse( wp_json_encode( $this->elementorV3WidgetWithSuffixes() ) );

		$widget = $components[0]->children[0]->children[0];
		$styles = $widget->metadata[ DEVTB_Responsive_Helper::METADATA_KEY ]['styles'] ?? null;

		$this->assertIsArray( $styles );
		$this->assertSame( 'center', $styles['tablet']['default']['align'] ?? null );
		$this->assertSame( 'right', $styles['phone']['default']['align'] ?? null );
		$this->assertSame( '#ff0000', $styles['desktop']['hover']['color'] ?? null );
	}

	public function testElementorV3RoundTripReEmitsSuffixes(): void {
		$parser     = new \DEVTB\TranslationBridge\Parsers\DEVTB_Elementor_Parser();
		$components = $parser->parse( wp_json_encode( $this->elementorV3WidgetWithSuffixes() ) );

		$converter = new \DEVTB\TranslationBridge\Converters\DEVTB_Elementor_Converter();
		$output    = $converter->convert( $components );
		$decoded   = is_string( $output ) ? json_decode( $output, true ) : $output;
		$flat      = wp_json_encode( $decoded );

		$this->assertStringContainsString( 'align_tablet', $flat );
		$this->assertStringContainsString( 'align_mobile', $flat );
		$this->assertStringContainsString( 'color_hover', $flat );
	}

	// -----------------------------------------------------------------
	// Bricks — :breakpoint setting-key suffixes
	// -----------------------------------------------------------------

	public function testBricksParserCanonicalizesBreakpointSuffixes(): void {
		$input = wp_json_encode( [
			[
				'id'       => 'abc123',
				'name'     => 'heading',
				'parent'   => 0,
				'children' => [],
				'settings' => [
					'text'                        => 'Hi',
					'_typography'                 => [ 'font-size' => '32px' ],
					'_typography:tablet_portrait' => [ 'font-size' => '24px' ],
					'_typography:mobile_portrait' => [ 'font-size' => '18px' ],
				],
			],
		] );

		$parser     = new \DEVTB\TranslationBridge\Parsers\DEVTB_Bricks_Parser();
		$components = $parser->parse( $input );

		$this->assertNotEmpty( $components );
		$styles = $components[0]->metadata[ DEVTB_Responsive_Helper::METADATA_KEY ]['styles'] ?? null;
		$this->assertIsArray( $styles );
		$this->assertSame( '24px', $styles['tablet']['default']['_typography']['font-size'] ?? null );
		$this->assertSame( '18px', $styles['phone']['default']['_typography']['font-size'] ?? null );
	}

	public function testCrossFrameworkElementorV3ToBricks(): void {
		$parser     = new \DEVTB\TranslationBridge\Parsers\DEVTB_Elementor_Parser();
		$components = $parser->parse( wp_json_encode( $this->elementorV3WidgetWithSuffixes() ) );

		$converter = new \DEVTB\TranslationBridge\Converters\DEVTB_Bricks_Converter();
		$output    = $converter->convert( $components );

		$this->assertStringContainsString( 'align:tablet_portrait', $output, 'Elementor tablet override must become a Bricks breakpoint key' );
		$this->assertStringContainsString( 'align:mobile_portrait', $output );
	}
}
