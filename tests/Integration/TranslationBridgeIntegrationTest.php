<?php
/**
 * Translation Bridge Integration Tests
 *
 * Tests full translation workflows across multiple frameworks
 *
 * @package DevelopmentTranslation_Bridge
 * @subpackage Tests
 */

namespace DEVTB\Tests\Integration;

use PHPUnit\Framework\TestCase;

class TranslationBridgeIntegrationTest extends TestCase {

    private $translator;

    protected function setUp(): void {
        parent::setUp();

        // Load the Translation Bridge
        require_once DEVTB_TRANSLATION_BRIDGE . '/core/class-translator.php';
        $this->translator = new \DEVTB\TranslationBridge\Core\DEVTB_Translator();
    }

    /**
     * Test Bootstrap to DIVI full page translation
     */
    public function test_bootstrap_to_divi_full_page_translation() {
        $bootstrap_html = '<section class="hero"><div class="container"><h1>Welcome</h1><p class="lead">Description</p></div></section>';

        $result = $this->translator->translate($bootstrap_html, 'bootstrap', 'divi');

        $this->assertNotEmpty($result);
        $this->assertIsString($result);
        // DIVI uses shortcode format
        $this->assertStringContainsString('[', $result);
    }

    /**
     * Test Bootstrap to Elementor translation
     */
    public function test_bootstrap_to_elementor_translation() {
        $bootstrap_html = '<div class="container"><div class="row"><div class="col-md-6"><h2>Title</h2></div></div></div>';

        $result = $this->translator->translate($bootstrap_html, 'bootstrap', 'elementor');

        $this->assertNotEmpty($result);
        // Elementor uses JSON format
        $decoded = json_decode($result, true);
        $this->assertIsArray($decoded);
    }

    /**
     * Test Bootstrap to Gutenberg translation
     */
    public function test_bootstrap_to_gutenberg_translation() {
        $bootstrap_html = '<div class="container"><h1>Heading</h1><p>Paragraph text</p></div>';

        $result = $this->translator->translate($bootstrap_html, 'bootstrap', 'gutenberg');

        $this->assertNotEmpty($result);
        // Gutenberg uses HTML comments format
        $this->assertStringContainsString('wp:', $result);
    }

    /**
     * Test round trip translation preserves content
     */
    public function test_round_trip_translation_preserves_content() {
        $original_content = '<section class="hero"><div class="container"><h1>Welcome to Our Site</h1></div></section>';

        // Bootstrap -> Gutenberg -> Bootstrap (HTML-friendly intermediate).
        $to_gutenberg      = $this->translator->translate($original_content, 'bootstrap', 'gutenberg');
        $back_to_bootstrap = $this->translator->translate($to_gutenberg, 'gutenberg', 'bootstrap');

        $this->assertNotEmpty($back_to_bootstrap);
        // Content should be preserved (text content)
        $this->assertStringContainsString('Welcome', $back_to_bootstrap);
    }

    /**
     * Test translation with large content
     */
    public function test_translation_handles_large_content() {
        // Create a large Bootstrap page
        $sections = '';
        for ($i = 0; $i < 10; $i++) {
            $sections .= "<section class=\"section-{$i}\"><div class=\"container\"><h2>Section {$i}</h2><p>Content for section {$i}</p></div></section>";
        }

        $result = $this->translator->translate($sections, 'bootstrap', 'divi');

        $this->assertNotEmpty($result);
        $this->assertIsString($result);
    }

    /**
     * Test translation statistics are recorded
     */
    public function test_translation_records_statistics() {
        $content = '<div class="container"><h1>Test</h1></div>';

        $this->translator->translate($content, 'bootstrap', 'elementor');
        $stats = $this->translator->get_stats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_components', $stats);
        $this->assertArrayHasKey('successful', $stats);
        $this->assertArrayHasKey('processing_time', $stats);
    }

    /**
     * Test batch translation to multiple targets
     */
    public function test_batch_translation_multiple_targets() {
        $content = '<div class="container"><h1>Batch Test</h1></div>';
        $targets = ['divi', 'elementor', 'gutenberg'];

        $results = [];
        foreach ($targets as $target) {
            $results[$target] = $this->translator->translate($content, 'bootstrap', $target);
        }

        foreach ($targets as $target) {
            $this->assertNotEmpty($results[$target], "Translation to {$target} should not be empty");
        }
    }

    /**
     * Test translation from fixture file
     */
    public function test_translation_from_fixture_file() {
        $fixture_path = DEVTB_ROOT . '/tests/fixtures/bootstrap/simple-page.html';

        if (!file_exists($fixture_path)) {
            $this->markTestSkipped('Fixture file not found');
        }

        $content = file_get_contents($fixture_path);
        $result = $this->translator->translate($content, 'bootstrap', 'gutenberg');

        $this->assertNotEmpty($result);
        $this->assertStringContainsString('wp:', $result);
    }

    /**
     * Test Elementor JSON fixture translation
     */
    public function test_elementor_json_fixture_translation() {
        $fixture_path = DEVTB_ROOT . '/tests/fixtures/elementor/simple-page.json';

        if (!file_exists($fixture_path)) {
            $this->markTestSkipped('Elementor fixture file not found');
        }

        $content = file_get_contents($fixture_path);
        $result = $this->translator->translate($content, 'elementor', 'bootstrap');

        $this->assertNotEmpty($result);
        // Should produce HTML
        $this->assertStringContainsString('<', $result);
    }

    /**
     * Test all supported frameworks can be translated to (v4.3.0: 14 targets).
     */
    public function test_all_frameworks_as_targets() {
        $source     = '<div class="container"><h1>Test</h1></div>';
        $frameworks = [
            'divi', 'divi-5', 'elementor', 'elementor-4', 'avada',
            'bricks', 'wpbakery', 'beaver-builder', 'gutenberg',
            'oxygen', 'oxygen-6', 'kadence', 'thrive',
        ];

        foreach ($frameworks as $target) {
            $result = $this->translator->translate($source, 'bootstrap', $target);
            $this->assertNotEmpty($result, "Translation to {$target} should not be empty");
        }
    }
}
