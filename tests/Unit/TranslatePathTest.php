<?php
/**
 * RFC 5.0 Phase 3 — translate-path re-route tests.
 *
 * translate() now rides parse → universal → convert for every pair; the
 * v3 mapping engine remains only as the content-extraction fallback, and
 * every conversion reports route + fidelity metrics in its stats.
 *
 * @package DevelopmentTranslationBridge
 */

use PHPUnit\Framework\TestCase;
use DEVTB\TranslationBridge\Core\DEVTB_Translator;

class TranslatePathTest extends TestCase {

	private DEVTB_Translator $translator;

	protected function setUp(): void {
		parent::setUp();
		$this->translator = new DEVTB_Translator();
	}

	public function testTranslateTakesTheUniversalRoute(): void {
		$fixture = file_get_contents( DEVTB_ROOT . '/tests/fixtures/elementor/kitchen-sink.json' );
		$output  = $this->translator->translate( $fixture, 'elementor', 'gutenberg' );

		$this->assertIsString( $output );
		$stats = $this->translator->get_stats();
		$this->assertSame( 'universal', $stats['route'], 'translate() must default to the universal route' );

		foreach ( [ 'Kitchen Sink Hero', 'Get started', 'We cut migration time' ] as $needle ) {
			$this->assertStringContainsString( $needle, $output );
		}
	}

	public function testDiviFixtureRidesTheUniversalRouteToo(): void {
		$fixture = file_get_contents( DEVTB_ROOT . '/tests/fixtures/divi/kitchen-sink.txt' );
		$output  = $this->translator->translate( $fixture, 'divi', 'gutenberg' );

		$this->assertIsString( $output );
		$this->assertSame( 'universal', $this->translator->get_stats()['route'] );
		$this->assertStringContainsString( 'DIVI Kitchen Sink', $output );
	}

	public function testFidelityMetricsAreReportedPerConversion(): void {
		$fixture = file_get_contents( DEVTB_ROOT . '/tests/fixtures/elementor/kitchen-sink.json' );
		$this->translator->translate( $fixture, 'elementor', 'gutenberg' );

		$fidelity = $this->translator->get_stats()['fidelity'];
		$this->assertIsArray( $fidelity );
		$this->assertGreaterThan( 0, $fidelity['content_total'] );
		$this->assertLessThanOrEqual( $fidelity['content_total'], $fidelity['content_preserved'] );
		$this->assertGreaterThanOrEqual( 0.8, $fidelity['ratio'], 'universal route must preserve the bulk of content' );
	}

	public function testFidelitySurvivesJsonTargets(): void {
		// Fidelity must compare text-to-text: JSON escaping and markup in
		// the output must not read as content loss.
		$fixture = file_get_contents( DEVTB_ROOT . '/tests/fixtures/elementor/kitchen-sink.json' );
		$output  = $this->translator->translate( $fixture, 'elementor', 'bricks' );

		$this->assertNotFalse( $output );
		$fidelity = $this->translator->get_stats()['fidelity'];
		$this->assertGreaterThanOrEqual( 0.8, $fidelity['ratio'] );
	}

	public function testUniversalRouteConfidenceIsFull(): void {
		$fixture = file_get_contents( DEVTB_ROOT . '/tests/fixtures/elementor/kitchen-sink.json' );
		$this->translator->translate( $fixture, 'elementor', 'gutenberg' );

		// The universal route is deterministic vocabulary translation —
		// no fuzzy mapping confidence involved.
		$this->assertSame( 1.0, $this->translator->get_stats()['avg_confidence'] );
	}
}
