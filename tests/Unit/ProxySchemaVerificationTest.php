<?php
/**
 * Proxy-schema verification tests.
 *
 * v4.3.0 shipped the divi-5 / elementor-4 / oxygen-6 implementations against
 * published documentation without real-export verification. These tests pin
 * the formats to the real evidence gathered since:
 *
 * - Oxygen 6: a REAL Breakdance element export (fixtures/oxygen6/) — integer
 *   ids, `data`-nested type/properties, `_parentId` back-references, content
 *   fields under `properties.content.content`, plural `tags` heading key.
 * - Elementor 4: the open-source elementor repo (modules/atomic-widgets) —
 *   typed props (`$$type` envelopes), `paragraph` key, `link.destination`,
 *   Style_Definition variants.
 * - DIVI 5: the Divi 5 block-format docs — top-level `content` attribute
 *   group and unicode-escaped HTML inside block-comment attrs.
 *
 * @package DevelopmentTranslationBridge
 */

use PHPUnit\Framework\TestCase;
use DEVTB\TranslationBridge\Models\DEVTB_Component;
use DEVTB\TranslationBridge\Parsers\DEVTB_Oxygen6_Parser;
use DEVTB\TranslationBridge\Parsers\DEVTB_Elementor4_Parser;
use DEVTB\TranslationBridge\Parsers\DEVTB_DIVI5_Parser;
use DEVTB\TranslationBridge\Converters\DEVTB_Oxygen6_Converter;
use DEVTB\TranslationBridge\Converters\DEVTB_Elementor4_Converter;
use DEVTB\TranslationBridge\Converters\DEVTB_DIVI5_Converter;

class ProxySchemaVerificationTest extends TestCase {

	// -----------------------------------------------------------------
	// Oxygen 6 — real Breakdance export
	// -----------------------------------------------------------------

	public function testOxygen6ParsesRealBreakdanceExport(): void {
		$raw = file_get_contents( DEVTB_ROOT . '/tests/fixtures/oxygen6/breakdance-element-export.json' );
		$this->assertNotFalse( $raw, 'fixture must be readable' );

		$parser = new DEVTB_Oxygen6_Parser();
		$this->assertTrue( $parser->is_valid_content( $raw ), 'real export must be detected as Oxygen 6 content' );

		$components = $parser->parse( $raw );
		$this->assertNotEmpty( $components, 'real export must produce components' );

		// The export's outer element is a Div → universal container.
		$root = $components[0];
		$this->assertSame( 'container', $root->type );
		$this->assertNotEmpty( $root->children, 'nested Divs must parse as children' );

		// Find the first Heading: real content lives at
		// properties.content.content.text with the plural `tags` key.
		$heading = $this->findByType( $root, 'heading' );
		$this->assertNotNull( $heading, 'expected a heading in the real export' );
		$this->assertSame( 'Genre: ', $heading->content );
		$this->assertSame( 'h4', $heading->attributes['level'] ?? null, 'plural `tags` key must normalise to level' );

		// CodeBlock content must surface from content.content.php_code.
		$code = $this->findByType( $root, 'code' );
		$this->assertNotNull( $code, 'expected a code block in the real export' );
		$this->assertNotSame( '', $code->content, 'php_code must surface as content' );
	}

	public function testOxygen6RoundTripEmitsRealShape(): void {
		$raw    = file_get_contents( DEVTB_ROOT . '/tests/fixtures/oxygen6/breakdance-element-export.json' );
		$parser = new DEVTB_Oxygen6_Parser();
		$components = $parser->parse( $raw );

		$converter = new DEVTB_Oxygen6_Converter();
		$payload   = json_decode( $converter->convert( $components ), true );

		$this->assertSame( 1, $payload['tree']['root']['id'] ?? null );
		$this->assertSame( 'root', $payload['tree']['root']['data']['type'] ?? null );
		$this->assertIsInt( $payload['_nextNodeId'] ?? null );

		$first = $payload['tree']['root']['children'][0] ?? null;
		$this->assertIsArray( $first );
		$this->assertIsInt( $first['id'] );
		$this->assertSame( 1, $first['_parentId'] );
		$this->assertStringContainsString( 'EssentialElements\\', $first['data']['type'] );
	}

	// -----------------------------------------------------------------
	// Elementor 4 — typed props per the open-source repo
	// -----------------------------------------------------------------

	public function testElementor4EmitsTypedProps(): void {
		$component = new DEVTB_Component( [
			'type'       => 'heading',
			'category'   => 'content',
			'content'    => 'Welcome',
			'attributes' => [ 'level' => 'h2' ],
		] );

		$converter = new DEVTB_Elementor4_Converter();
		$decoded   = json_decode( $converter->convert( $component ), true );
		$settings  = $decoded[0]['settings'];

		$this->assertSame( 'html-v3', $settings['title']['$$type'] ?? null, 'heading title must be an html-v3 prop' );
		$this->assertSame( 'Welcome', $settings['title']['value']['content']['value'] ?? null, 'html-v3 content must nest a string prop' );
		$this->assertSame( 'string', $settings['title']['value']['content']['$$type'] ?? null );
		$this->assertSame( 'h2', $settings['tag']['value'] ?? null, 'tag must be a string prop' );
	}

	public function testElementor4ButtonLinkUsesDestination(): void {
		$component = new DEVTB_Component( [
			'type'       => 'button',
			'category'   => 'content',
			'content'    => 'Go',
			'attributes' => [
				'url'    => 'https://example.com',
				'target' => '_blank',
			],
		] );

		$converter = new DEVTB_Elementor4_Converter();
		$decoded   = json_decode( $converter->convert( $component ), true );
		$link      = $decoded[0]['settings']['link'];

		$this->assertSame( 'link', $link['$$type'] ?? null );
		$this->assertSame( 'https://example.com', $link['value']['destination']['value'] ?? null, 'links store `destination`, not `url`' );
		$this->assertTrue( $link['value']['isTargetBlank']['value'] ?? null, '_blank must map to isTargetBlank boolean prop' );
	}

	public function testElementor4ParserUnwrapsTypedProps(): void {
		$node = [
			'id'              => 'abc12345',
			'version'         => '0.0',
			'elType'          => 'e-paragraph',
			'isInner'         => false,
			'interactions'    => [],
			'settings'        => [
				'paragraph' => [
					'$$type' => 'html-v3',
					'value'  => [ 'content' => [ '$$type' => 'string', 'value' => 'Hello world' ] ],
				],
			],
			'editor_settings' => [],
			'styles'          => [],
			'elements'        => [],
		];

		$parser     = new DEVTB_Elementor4_Parser();
		$components = $parser->parse( wp_json_encode( [ $node ] ) );

		$this->assertCount( 1, $components );
		$this->assertSame( 'text', $components[0]->type );
		$this->assertSame( 'Hello world', $components[0]->content, 'typed html-v3 paragraph prop must unwrap to plain text' );
	}

	public function testElementor4EmitsOnlyRealElementTypes(): void {
		$real_types = [
			'e-div-block', 'e-flexbox', 'e-grid', 'e-heading', 'e-paragraph',
			'e-button', 'e-image', 'e-svg', 'e-divider', 'e-youtube',
			'e-self-hosted-video', 'e-form',
		];

		$components = [];
		foreach ( [ 'container', 'heading', 'text', 'button', 'image', 'video', 'icon', 'divider', 'list', 'card' ] as $type ) {
			$components[] = new DEVTB_Component( [
				'type'     => $type,
				'category' => 'content',
				'content'  => 'x',
			] );
		}

		$converter = new DEVTB_Elementor4_Converter();
		$decoded   = json_decode( $converter->convert( $components ), true );

		foreach ( $decoded as $node ) {
			$this->assertContains(
				$node['elType'],
				$real_types,
				"elType '{$node['elType']}' is not a real atomic element type"
			);
		}
	}

	// -----------------------------------------------------------------
	// DIVI 5 — top-level content group + unicode-escaped attrs
	// -----------------------------------------------------------------

	public function testDivi5EmitsTopLevelContentGroup(): void {
		$component = new DEVTB_Component( [
			'type'     => 'text',
			'category' => 'content',
			'content'  => '<p>Hello</p>',
		] );

		$converter = new DEVTB_DIVI5_Converter();
		$markup    = $converter->convert( $component );

		// HTML must be unicode-escaped inside the block-comment attrs so it
		// cannot break the comment delimiters.
		$this->assertStringNotContainsString( '<p>', $markup, 'raw HTML must not appear inside block attrs' );
		$this->assertStringContainsString( '\\u003cp\\u003e', $markup, 'HTML must use unicode escapes' );

		// Attrs must carry the top-level `content` group, not module.content.
		$this->assertMatchesRegularExpression( '/\{"content":\{"innerContent"/', $markup );
		$this->assertStringNotContainsString( '"module":{"content"', $markup );
	}

	public function testDivi5ParserReadsTopLevelContentGroup(): void {
		$markup = '<!-- wp:divi/heading {"content":{"text":{"desktop":{"value":"Real Shape"}},"level":{"desktop":{"value":"h3"}}},"builderVersion":"5.0.0"} /-->';

		$parser     = new DEVTB_DIVI5_Parser();
		$components = $parser->parse( $markup );

		$this->assertCount( 1, $components );
		$this->assertSame( 'heading', $components[0]->type );
		$this->assertSame( 'Real Shape', $components[0]->content );
		$this->assertSame( 'h3', $components[0]->attributes['level'] ?? null );
	}

	public function testDivi5ParserKeepsLegacyModuleContentFallback(): void {
		$markup = '<!-- wp:divi/heading {"module":{"content":{"text":{"desktop":{"value":"Legacy Shape"}}}},"builderVersion":"5.0.0"} /-->';

		$parser     = new DEVTB_DIVI5_Parser();
		$components = $parser->parse( $markup );

		$this->assertCount( 1, $components );
		$this->assertSame( 'Legacy Shape', $components[0]->content, 'legacy module.content shape must still parse' );
	}

	// -----------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------

	private function findByType( DEVTB_Component $component, string $type ): ?DEVTB_Component {
		if ( $component->type === $type ) {
			return $component;
		}
		foreach ( $component->children as $child ) {
			$hit = $this->findByType( $child, $type );
			if ( $hit ) {
				return $hit;
			}
		}
		return null;
	}
}
