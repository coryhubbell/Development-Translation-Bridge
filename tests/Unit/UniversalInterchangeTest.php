<?php
/**
 * RFC 5.0 Phase 2 — universal interchange tests.
 *
 * The PHP engine can now consume the canonical universal document
 * (DEVTB_Universal::document_to_components + translator entry points),
 * closing the loop opened by DEVTB_Component::to_universal() in Phase 1.
 *
 * @package DevelopmentTranslationBridge
 */

use PHPUnit\Framework\TestCase;
use DEVTB\TranslationBridge\Core\DEVTB_Translator;
use DEVTB\TranslationBridge\Core\DEVTB_Universal;
use DEVTB\TranslationBridge\Parsers\DEVTB_Elementor_Parser;

class UniversalInterchangeTest extends TestCase {

	private function universal_document(): array {
		return [
			'elements' => [
				[
					'id'       => 's1',
					'elType'   => 'section',
					'settings' => [],
					'elements' => [
						[
							'id'         => 'w1',
							'elType'     => 'widget',
							'widgetType' => 'heading',
							'settings'   => [ 'title' => 'Universal Title', 'header_size' => 'h2' ],
							'elements'   => [],
							'isInner'    => true,
						],
						[
							'id'         => 'w2',
							'elType'     => 'widget',
							'widgetType' => 'button',
							'settings'   => [
								'text' => 'Press me',
								'link' => [ 'url' => 'https://example.com', 'is_external' => 'on' ],
							],
							'elements'   => [],
							'isInner'    => true,
						],
						[
							'id'         => 'w3',
							'elType'     => 'widget',
							'widgetType' => 'testimonial',
							'settings'   => [
								'testimonial_content' => 'It just works.',
								'testimonial_name'    => 'Ada Lovelace',
							],
							'elements'   => [],
							'isInner'    => true,
						],
					],
				],
			],
			'version'  => '',
			'title'    => '',
			'meta'     => [],
		];
	}

	public function testDocumentToComponentsBuildsTheComponentTree(): void {
		$components = DEVTB_Universal::document_to_components( $this->universal_document() );

		$this->assertCount( 1, $components );
		$section = $components[0];
		$this->assertSame( 'container', $section->type );
		$this->assertCount( 3, $section->children );

		[ $heading, $button, $testimonial ] = $section->children;
		$this->assertSame( 'heading', $heading->type );
		$this->assertSame( 'Universal Title', $heading->content );
		$this->assertSame( 2, $heading->attributes['level'] );

		$this->assertSame( 'Press me', $button->content );
		$this->assertSame( 'https://example.com', $button->attributes['url'] );
		$this->assertSame( '_blank', $button->attributes['target'] );

		$this->assertSame( 'It just works.', $testimonial->content );
		$this->assertSame( 'Ada Lovelace', $testimonial->attributes['author'] );
	}

	public function testRoundTripIsStable(): void {
		$document  = $this->universal_document();
		$components = DEVTB_Universal::document_to_components( $document );
		$again      = DEVTB_Universal::components_to_document( $components );

		// A second pass through the interchange must be idempotent.
		$twice = DEVTB_Universal::components_to_document(
			DEVTB_Universal::document_to_components( $again )
		);

		$normalize = static function ( array $doc ): string {
			// ids regenerate; strip them for comparison.
			$strip = static function ( array $el ) use ( &$strip ): array {
				unset( $el['id'] );
				if ( isset( $el['elements'] ) ) {
					$el['elements'] = array_map( $strip, $el['elements'] );
				}
				return $el;
			};
			return wp_json_encode( array_map( $strip, $doc['elements'] ) );
		};

		$this->assertSame( $normalize( $again ), $normalize( $twice ) );
	}

	public function testCollectionSettingsSurviveTheComponentRoundTrip(): void {
		$document = [
			'elements' => [
				[
					'id'         => 'w1',
					'elType'     => 'widget',
					'widgetType' => 'icon-list',
					'settings'   => [ 'icon_list' => [ [ 'text' => 'First' ], [ 'text' => 'Second' ] ] ],
					'elements'   => [],
				],
				[
					'id'         => 'w2',
					'elType'     => 'widget',
					'widgetType' => 'image-gallery',
					'settings'   => [ 'wp_gallery' => [ [ 'url' => 'https://x.test/1.png', 'alt' => 'one' ] ] ],
					'elements'   => [],
				],
				[
					'id'         => 'w3',
					'elType'     => 'widget',
					'widgetType' => 'alert',
					'settings'   => [
						'alert_title'       => 'Heads up',
						'alert_description' => 'Body.',
					],
					'elements'   => [],
				],
				[
					'id'         => 'w4',
					'elType'     => 'widget',
					'widgetType' => 'icon',
					'settings'   => [ 'selected_icon' => [ 'value' => 'fas fa-star' ] ],
					'elements'   => [],
				],
			],
			'version'  => '',
			'title'    => '',
			'meta'     => [],
		];

		$again = DEVTB_Universal::components_to_document(
			DEVTB_Universal::document_to_components( $document )
		);

		[ $list, $gallery, $alert, $icon ] = $again['elements'];
		$this->assertSame( [ [ 'text' => 'First' ], [ 'text' => 'Second' ] ], $list['settings']['icon_list'] );
		$this->assertSame( 'https://x.test/1.png', $gallery['settings']['wp_gallery'][0]['url'] );
		$this->assertSame( 'Heads up', $alert['settings']['alert_title'] );
		$this->assertSame( 'Body.', $alert['settings']['alert_description'] );
		$this->assertSame( 'fas fa-star', $icon['settings']['selected_icon']['value'] );
	}

	public function testTranslatorConvertsUniversalToGutenberg(): void {
		$translator = new DEVTB_Translator();
		$output     = $translator->translate_universal( $this->universal_document(), 'gutenberg' );

		$this->assertIsString( $output );
		$this->assertStringContainsString( 'Universal Title', $output );
		$this->assertStringContainsString( 'Press me', $output );
		$this->assertStringContainsString( 'Ada Lovelace', $output );
		$this->assertStringContainsString( '<!-- wp:', $output );
	}

	public function testTranslatorConvertsUniversalToBricks(): void {
		$translator = new DEVTB_Translator();
		$output     = $translator->translate_universal( $this->universal_document(), 'bricks' );

		$this->assertIsString( $output );
		$this->assertStringContainsString( 'Universal Title', $output );
		$decoded = json_decode( $output, true );
		$this->assertIsArray( $decoded, 'bricks output must stay valid flat JSON' );
	}

	public function testParseToUniversalFromElementorFixture(): void {
		$translator = new DEVTB_Translator();
		$fixture    = file_get_contents( DEVTB_ROOT . '/tests/fixtures/elementor/kitchen-sink.json' );

		$document = $translator->parse_to_universal( $fixture, 'elementor' );

		$this->assertIsArray( $document );
		$this->assertNotEmpty( $document['elements'] );
		$blob = wp_json_encode( $document );
		$this->assertStringContainsString( 'Kitchen Sink Hero', $blob );
		$this->assertStringContainsString( '"elType"', $blob );
	}

	public function testCrossPathElementorFixtureThroughUniversalToGutenberg(): void {
		// Full Phase 2 loop: parse → universal document → translate_universal.
		$translator = new DEVTB_Translator();
		$fixture    = file_get_contents( DEVTB_ROOT . '/tests/fixtures/elementor/kitchen-sink.json' );

		$document = $translator->parse_to_universal( $fixture, 'elementor' );
		$output   = $translator->translate_universal( $document, 'gutenberg' );

		$this->assertIsString( $output );
		foreach ( [ 'Kitchen Sink Hero', 'Get started', 'We cut migration time' ] as $needle ) {
			$this->assertStringContainsString( $needle, $output, "content lost through the universal loop: {$needle}" );
		}
	}
}
