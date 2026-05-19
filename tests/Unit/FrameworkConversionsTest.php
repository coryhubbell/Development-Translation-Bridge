<?php
/**
 * Framework Conversions Test
 *
 * Tests all 110 framework conversion pairs (11 frameworks × 10 targets each).
 * Uses PHPUnit data providers to test every source→target combination.
 *
 * @package DevelopmentTranslation_Bridge
 * @subpackage Tests
 * @since 3.4.0
 */

namespace DEVTB\Tests\Unit;

use PHPUnit\Framework\TestCase;
use DEVTB\TranslationBridge\Core\DEVTB_Translator;

class FrameworkConversionsTest extends TestCase {

    /**
     * All supported frameworks
     *
     * @var array<string>
     */
    private static array $frameworks = [
        'bootstrap',
        'elementor',
        'elementor-4',
        'gutenberg',
        'beaver-builder',
        'oxygen',
        'oxygen-6',
        'divi',
        'divi-5',
        'wpbakery',
        'bricks',
        'avada',
        'kadence',
        'thrive',
    ];

    /**
     * Sample inputs for each framework
     *
     * @var array<string, string>
     */
    private static array $sampleInputs = [];

    /**
     * Translator instance
     *
     * @var DEVTB_Translator
     */
    private DEVTB_Translator $translator;

    /**
     * Set up test fixtures
     */
    protected function setUp(): void {
        parent::setUp();

        // Load Translation Bridge classes
        $this->loadTranslationBridge();

        // Initialize translator
        $this->translator = new DEVTB_Translator();

        // Initialize sample inputs
        self::initializeSampleInputs();
    }

    /**
     * Load Translation Bridge classes
     */
    private function loadTranslationBridge(): void {
        $bridge_path = DEVTB_TRANSLATION_BRIDGE;

        // Load models
        require_once $bridge_path . '/models/class-component.php';

        // Load utils
        require_once $bridge_path . '/utils/class-html-helper.php';
        require_once $bridge_path . '/utils/class-css-helper.php';
        require_once $bridge_path . '/utils/class-json-helper.php';
        require_once $bridge_path . '/utils/class-shortcode-helper.php';

        // Load core
        require_once $bridge_path . '/core/interface-parser.php';
        require_once $bridge_path . '/core/interface-converter.php';
        require_once $bridge_path . '/core/class-parser-factory.php';
        require_once $bridge_path . '/core/class-converter-factory.php';
        require_once $bridge_path . '/core/class-mapping-engine.php';
        require_once $bridge_path . '/core/class-translator.php';

        // Load parsers
        require_once $bridge_path . '/parsers/class-bootstrap-parser.php';
        require_once $bridge_path . '/parsers/class-elementor-parser.php';
        require_once $bridge_path . '/parsers/class-gutenberg-parser.php';
        require_once $bridge_path . '/parsers/class-beaver-builder-parser.php';
        require_once $bridge_path . '/parsers/class-oxygen-parser.php';
        require_once $bridge_path . '/parsers/class-oxygen6-parser.php';
        require_once $bridge_path . '/parsers/class-divi-parser.php';
        require_once $bridge_path . '/parsers/class-divi5-parser.php';
        require_once $bridge_path . '/parsers/class-elementor4-parser.php';
        require_once $bridge_path . '/parsers/class-wpbakery-parser.php';
        require_once $bridge_path . '/parsers/class-bricks-parser.php';
        require_once $bridge_path . '/parsers/class-avada-parser.php';
        require_once $bridge_path . '/parsers/class-kadence-parser.php';
        require_once $bridge_path . '/parsers/class-thrive-parser.php';
        // Claude parser removed - AI-ready is now a modifier, not a framework

        // Load converters
        require_once $bridge_path . '/converters/class-bootstrap-converter.php';
        require_once $bridge_path . '/converters/class-elementor-converter.php';
        require_once $bridge_path . '/converters/class-gutenberg-converter.php';
        require_once $bridge_path . '/converters/class-beaver-builder-converter.php';
        require_once $bridge_path . '/converters/class-oxygen-converter.php';
        require_once $bridge_path . '/converters/class-oxygen6-converter.php';
        require_once $bridge_path . '/converters/class-divi-converter.php';
        require_once $bridge_path . '/converters/class-divi5-converter.php';
        require_once $bridge_path . '/converters/class-elementor4-converter.php';
        require_once $bridge_path . '/converters/class-wpbakery-converter.php';
        require_once $bridge_path . '/converters/class-bricks-converter.php';
        require_once $bridge_path . '/converters/class-avada-converter.php';
        require_once $bridge_path . '/converters/class-kadence-converter.php';
        require_once $bridge_path . '/converters/class-thrive-converter.php';
        // Claude converter removed - AI-ready is now a modifier, not a framework
    }

    /**
     * Initialize sample inputs for all frameworks
     */
    private static function initializeSampleInputs(): void {
        if ( ! empty( self::$sampleInputs ) ) {
            return;
        }

        // Bootstrap 5.3 - HTML with Bootstrap classes
        self::$sampleInputs['bootstrap'] = <<<HTML
<div class="container">
    <div class="row">
        <div class="col-md-6">
            <h2 class="text-primary">Welcome</h2>
            <p class="lead">This is a sample Bootstrap layout.</p>
            <a href="#" class="btn btn-primary">Learn More</a>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Card Title</h5>
                    <p class="card-text">Some card content here.</p>
                </div>
            </div>
        </div>
    </div>
</div>
HTML;

        // Elementor - JSON structure
        self::$sampleInputs['elementor'] = json_encode([
            [
                'id' => 'section1',
                'elType' => 'section',
                'settings' => [],
                'elements' => [
                    [
                        'id' => 'col1',
                        'elType' => 'column',
                        'settings' => ['_column_size' => 50],
                        'elements' => [
                            [
                                'id' => 'heading1',
                                'elType' => 'widget',
                                'widgetType' => 'heading',
                                'settings' => ['title' => 'Welcome']
                            ],
                            [
                                'id' => 'text1',
                                'elType' => 'widget',
                                'widgetType' => 'text-editor',
                                'settings' => ['editor' => 'This is sample Elementor content.']
                            ],
                            [
                                'id' => 'button1',
                                'elType' => 'widget',
                                'widgetType' => 'button',
                                'settings' => [
                                    'text' => 'Learn More',
                                    'link' => ['url' => '#']
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        // Gutenberg - Block markup
        self::$sampleInputs['gutenberg'] = <<<HTML
<!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group">
    <!-- wp:columns -->
    <div class="wp-block-columns">
        <!-- wp:column {"width":"50%"} -->
        <div class="wp-block-column" style="flex-basis:50%">
            <!-- wp:heading {"level":2} -->
            <h2>Welcome</h2>
            <!-- /wp:heading -->
            <!-- wp:paragraph -->
            <p>This is sample Gutenberg content.</p>
            <!-- /wp:paragraph -->
            <!-- wp:button -->
            <div class="wp-block-button"><a class="wp-block-button__link">Learn More</a></div>
            <!-- /wp:button -->
        </div>
        <!-- /wp:column -->
    </div>
    <!-- /wp:columns -->
</div>
<!-- /wp:group -->
HTML;

        // Beaver Builder - Serialized PHP (simplified)
        $bbData = new \stdClass();
        $bbData->node = 'row1';
        $bbData->type = 'row';
        $bbData->parent = null;
        $bbData->position = 0;
        $bbData->settings = new \stdClass();

        $bbCol = new \stdClass();
        $bbCol->node = 'col1';
        $bbCol->type = 'column';
        $bbCol->parent = 'row1';
        $bbCol->position = 0;
        $bbCol->settings = (object)['size' => 50];

        $bbHeading = new \stdClass();
        $bbHeading->node = 'heading1';
        $bbHeading->type = 'heading';
        $bbHeading->parent = 'col1';
        $bbHeading->position = 0;
        $bbHeading->settings = (object)['heading' => 'Welcome'];

        $bbText = new \stdClass();
        $bbText->node = 'text1';
        $bbText->type = 'rich-text';
        $bbText->parent = 'col1';
        $bbText->position = 1;
        $bbText->settings = (object)['text' => 'This is sample Beaver Builder content.'];

        self::$sampleInputs['beaver-builder'] = serialize([
            'row1' => $bbData,
            'col1' => $bbCol,
            'heading1' => $bbHeading,
            'text1' => $bbText,
        ]);

        // Oxygen - JSON structure
        self::$sampleInputs['oxygen'] = json_encode([
            [
                'id' => 1,
                'name' => 'ct_section',
                'options' => [
                    'ct_id' => 1,
                    'ct_parent' => 0
                ]
            ],
            [
                'id' => 2,
                'name' => 'ct_headline',
                'options' => [
                    'ct_id' => 2,
                    'ct_parent' => 1,
                    'ct_content' => 'Welcome',
                    'tag' => 'h2'
                ]
            ],
            [
                'id' => 3,
                'name' => 'ct_text_block',
                'options' => [
                    'ct_id' => 3,
                    'ct_parent' => 1,
                    'ct_content' => 'This is sample Oxygen content.'
                ]
            ],
            [
                'id' => 4,
                'name' => 'ct_link_button',
                'options' => [
                    'ct_id' => 4,
                    'ct_parent' => 1,
                    'ct_content' => 'Learn More',
                    'url' => '#'
                ]
            ]
        ]);

        // Elementor 4 Atomic - atomic element tree (e-* elTypes)
        self::$sampleInputs['elementor-4'] = json_encode([
            [
                'id'              => 'e4root1',
                'version'         => '0.0',
                'elType'          => 'e-div-block',
                'isInner'         => false,
                'interactions'    => [],
                'settings'        => (object) [],
                'editor_settings' => (object) [],
                'styles'          => (object) [],
                'elements'        => [
                    [
                        'id'              => 'e4hd1',
                        'version'         => '0.0',
                        'elType'          => 'e-heading',
                        'isInner'         => true,
                        'interactions'    => [],
                        'settings'        => [ 'title' => 'Welcome', 'tag' => 'h2' ],
                        'editor_settings' => (object) [],
                        'styles'          => (object) [],
                        'elements'        => [],
                    ],
                    [
                        'id'              => 'e4p1',
                        'version'         => '0.0',
                        'elType'          => 'e-paragraph',
                        'isInner'         => true,
                        'interactions'    => [],
                        'settings'        => [ 'text' => 'This is sample Elementor 4 Atomic content.' ],
                        'editor_settings' => (object) [],
                        'styles'          => (object) [],
                        'elements'        => [],
                    ],
                    [
                        'id'              => 'e4btn1',
                        'version'         => '0.0',
                        'elType'          => 'e-button',
                        'isInner'         => true,
                        'interactions'    => [],
                        'settings'        => [ 'text' => 'Learn More', 'link' => [ 'url' => '#', 'target' => '_self' ] ],
                        'editor_settings' => (object) [],
                        'styles'          => (object) [],
                        'elements'        => [],
                    ],
                ],
            ],
        ]);

        // DIVI 5 - WordPress block markup with the `divi/*` namespace
        self::$sampleInputs['divi-5'] = <<<HTML
<!-- wp:divi/section {"module":{},"builderVersion":"5.0.0"} -->
<!-- wp:divi/row {"module":{},"builderVersion":"5.0.0"} -->
<!-- wp:divi/column {"module":{},"builderVersion":"5.0.0"} -->
<!-- wp:divi/heading {"module":{"content":{"text":{"desktop":{"value":"Welcome"}},"level":{"desktop":{"value":"h2"}}}},"builderVersion":"5.0.0"} /-->
<!-- wp:divi/text {"module":{"content":{"innerContent":{"desktop":{"value":"This is sample DIVI 5 content."}}}},"builderVersion":"5.0.0"} /-->
<!-- wp:divi/button {"module":{"content":{"text":{"desktop":{"value":"Learn More"}},"url":{"desktop":{"value":"#"}}}},"builderVersion":"5.0.0"} /-->
<!-- /wp:divi/column -->
<!-- /wp:divi/row -->
<!-- /wp:divi/section -->
HTML;

        // Oxygen 6 - nested JSON tree (Breakdance-proxy schema, EssentialElements\* namespace)
        self::$sampleInputs['oxygen-6'] = json_encode([
            '_version'    => 1,
            '_nextNodeId' => 5,
            'tree'        => [
                [
                    'id'         => 'n-1',
                    'type'       => 'EssentialElements\\Section',
                    'properties' => [],
                    'children'   => [
                        [
                            'id'         => 'n-2',
                            'type'       => 'EssentialElements\\Heading',
                            'properties' => [ 'text' => 'Welcome', 'tag' => 'h2' ],
                            'children'   => [],
                        ],
                        [
                            'id'         => 'n-3',
                            'type'       => 'EssentialElements\\Text',
                            'properties' => [ 'text' => 'This is sample Oxygen 6 content.' ],
                            'children'   => [],
                        ],
                        [
                            'id'         => 'n-4',
                            'type'       => 'EssentialElements\\Button',
                            'properties' => [ 'text' => 'Learn More', 'link' => [ 'url' => '#' ] ],
                            'children'   => [],
                        ],
                    ],
                ],
            ],
        ]);

        // DIVI - Shortcodes
        self::$sampleInputs['divi'] = <<<SHORTCODE
[et_pb_section]
[et_pb_row]
[et_pb_column type="1_2"]
[et_pb_text]
<h2>Welcome</h2>
<p>This is sample DIVI content.</p>
[/et_pb_text]
[et_pb_button button_text="Learn More" button_url="#"]
[/et_pb_button]
[/et_pb_column]
[/et_pb_row]
[/et_pb_section]
SHORTCODE;

        // WPBakery - Shortcodes
        self::$sampleInputs['wpbakery'] = <<<SHORTCODE
[vc_row]
[vc_column width="1/2"]
[vc_custom_heading text="Welcome"]
[/vc_custom_heading]
[vc_column_text]
This is sample WPBakery content.
[/vc_column_text]
[vc_btn title="Learn More" link="url:#"]
[/vc_btn]
[/vc_column]
[/vc_row]
SHORTCODE;

        // Bricks - JSON structure
        self::$sampleInputs['bricks'] = json_encode([
            [
                'id' => 'section1',
                'name' => 'section',
                'settings' => [],
                'children' => [
                    [
                        'id' => 'heading1',
                        'name' => 'heading',
                        'settings' => ['text' => 'Welcome', 'tag' => 'h2']
                    ],
                    [
                        'id' => 'text1',
                        'name' => 'text',
                        'settings' => ['text' => 'This is sample Bricks content.']
                    ],
                    [
                        'id' => 'button1',
                        'name' => 'button',
                        'settings' => ['text' => 'Learn More', 'link' => ['url' => '#']]
                    ]
                ]
            ]
        ]);

        // Avada - Shortcodes
        self::$sampleInputs['avada'] = <<<SHORTCODE
[fusion_builder_container]
[fusion_builder_row]
[fusion_builder_column type="1_2"]
[fusion_title size="2"]Welcome[/fusion_title]
[fusion_text]
This is sample Avada content.
[/fusion_text]
[fusion_button link="#"]Learn More[/fusion_button]
[/fusion_builder_column]
[/fusion_builder_row]
[/fusion_builder_container]
SHORTCODE;

        // Kadence - Block markup with kadence/* namespace
        self::$sampleInputs['kadence'] = <<<HTML
<!-- wp:kadence/rowlayout {"uniqueID":"_kb001-c4ca","columns":1,"colLayout":"row"} -->
<div class="kb-row-layout-wrap alignnone">
<div class="kt-row-column-wrap kt-has-1-columns">
<!-- wp:kadence/column {"uniqueID":"_kb002-c81e"} -->
<div class="kadence-column_kb002-c81e inner-column">
<div class="kt-inside-inner-col">
<!-- wp:kadence/advancedheading {"uniqueID":"_kb003-eccb","level":2} -->
<h2 class="kt-adv-heading_kb003-eccb wp-block-kadence-advancedheading">Welcome</h2>
<!-- /wp:kadence/advancedheading -->
<!-- wp:paragraph -->
<p>This is sample Kadence content.</p>
<!-- /wp:paragraph -->
<!-- wp:kadence/advancedbtn {"uniqueID":"_kb004-a87f"} -->
<div class="wp-block-kadence-advancedbtn kb-btns-outer-wrap kt-btn-align-inherit"><a href="#" class="kt-button"><span class="kt-btn-text">Learn More</span></a></div>
<!-- /wp:kadence/advancedbtn -->
</div>
</div>
<!-- /wp:kadence/column -->
</div>
</div>
<!-- /wp:kadence/rowlayout -->
HTML;

        // Thrive - TCB HTML with data-css tokens + inline style block
        self::$sampleInputs['thrive'] = <<<HTML
<div class="tve_flt tcb-flex-row" data-css="tve-u-c4ca4238a0b"><div class="tcb-flex-col" data-css="tve-u-c81e728d9d4"><h2 class="tve_h2" data-css="tve-u-eccbc87e4b5">Welcome</h2><p class="tve_p" data-css="tve-u-a87ff679a2f">This is sample Thrive content.</p><div class="tcb-button-block" data-css="tve-u-e4da3b7fbbc"><a href="#" class="tcb-button-link"><span class="tcb-button-texts">Learn More</span></a></div></div></div>
<style type="text/css" class="tve_custom_style">
.tve-u-c4ca4238a0b{display:flex;flex-wrap:wrap;}
.tve-u-c81e728d9d4{width:100%;}
.tve-u-eccbc87e4b5{}
.tve-u-a87ff679a2f{}
.tve-u-e4da3b7fbbc{display:inline-block;cursor:pointer;}
</style>
HTML;

        // Claude removed - AI-ready is now a modifier, not a framework
        // Use 'bootstrap' with --ai-ready flag instead:
        // self::$sampleInputs['bootstrap'] with ai_ready option
        /*
        self::$sampleInputs['ai-ready-example'] = <<<HTML
<!-- AI-READY HTML -->
<div class="container" data-ai-editable="container">
    <div class="row">
        <div class="col-md-6" data-ai-editable="column">
            <h2 data-ai-editable="heading">Welcome</h2>
            <p data-ai-editable="text">This is sample AI-ready content.</p>
            <a href="#" class="btn btn-primary" data-ai-editable="button">Learn More</a>
        </div>
    </div>
</div>
HTML;
        */
    }

    /**
     * Data provider for all 72 framework conversion pairs
     *
     * @return array Test data sets
     */
    public static function frameworkPairsProvider(): array {
        self::initializeSampleInputs();

        $pairs = [];

        foreach ( self::$frameworks as $source ) {
            foreach ( self::$frameworks as $target ) {
                // Skip same-to-same translations
                if ( $source === $target ) {
                    continue;
                }

                $testName = "{$source}_to_{$target}";
                $pairs[$testName] = [
                    'source' => $source,
                    'target' => $target,
                    'input' => self::$sampleInputs[$source]
                ];
            }
        }

        return $pairs;
    }

    /**
     * Test framework conversion
     *
     * @dataProvider frameworkPairsProvider
     * @param string $source Source framework
     * @param string $target Target framework
     * @param string $input Sample input
     */
    public function testFrameworkConversion( string $source, string $target, string $input ): void {
        // Attempt translation
        $result = $this->translator->translate( $input, $source, $target );

        // Assert translation produced output
        $this->assertNotFalse(
            $result,
            "Translation {$source} → {$target} returned false"
        );

        $this->assertNotEmpty(
            $result,
            "Translation {$source} → {$target} produced empty output"
        );

        // Validate output format matches target framework
        $this->assertValidFrameworkOutput( $result, $target, $source );
    }

    /**
     * Validate output matches expected framework format
     *
     * @param mixed  $output Translated output
     * @param string $target Target framework
     * @param string $source Source framework (for error messages)
     */
    private function assertValidFrameworkOutput( $output, string $target, string $source ): void {
        $outputStr = is_string( $output ) ? $output : json_encode( $output );

        switch ( $target ) {
            case 'elementor':
            case 'elementor-4':
            case 'oxygen':
            case 'oxygen-6':
            case 'bricks':
                // Should be valid JSON
                if ( is_string( $output ) ) {
                    $decoded = json_decode( $output, true );
                    $this->assertNotNull(
                        $decoded,
                        "{$source} → {$target}: Output is not valid JSON"
                    );
                } else {
                    $decoded = $output;
                    $this->assertIsArray(
                        $output,
                        "{$source} → {$target}: Output is not an array"
                    );
                }

                if ( $target === 'bricks' && is_array( $decoded ) && ! empty( $decoded ) ) {
                    $this->assertBricksFlatStructure( $decoded, $source );
                }

                if ( $target === 'oxygen-6' && is_array( $decoded ) ) {
                    $this->assertOxygen6TreeStructure( $decoded, $source );
                }

                if ( $target === 'elementor-4' && is_array( $decoded ) ) {
                    $this->assertElementor4AtomicStructure( $decoded, $source );
                }
                break;

            case 'divi-5':
                $this->assertStringContainsString(
                    '<!-- wp:divi/',
                    $outputStr,
                    "{$source} → {$target}: Missing DIVI 5 block markers (`wp:divi/*`)"
                );
                $this->assertMatchesRegularExpression(
                    '/builderVersion/i',
                    $outputStr,
                    "{$source} → {$target}: DIVI 5 blocks must declare builderVersion"
                );
                break;

            case 'gutenberg':
                // Should contain block comment markers
                $this->assertStringContainsString(
                    '<!-- wp:',
                    $outputStr,
                    "{$source} → {$target}: Missing Gutenberg block markers"
                );
                break;

            case 'wpbakery':
                // Should contain vc_ shortcodes
                $this->assertMatchesRegularExpression(
                    '/\[vc_/',
                    $outputStr,
                    "{$source} → {$target}: Missing WPBakery shortcodes"
                );
                break;

            case 'divi':
                // Should contain et_pb_ shortcodes
                $this->assertMatchesRegularExpression(
                    '/\[et_pb_/',
                    $outputStr,
                    "{$source} → {$target}: Missing DIVI shortcodes"
                );
                break;

            case 'avada':
                // Should contain fusion_ shortcodes
                $this->assertMatchesRegularExpression(
                    '/\[fusion_/',
                    $outputStr,
                    "{$source} → {$target}: Missing Avada/Fusion shortcodes"
                );
                break;

            case 'beaver-builder':
                // Beaver Builder outputs serialized PHP or array
                if ( is_string( $output ) ) {
                    // Should be serialized or contain BB elements
                    $unserialized = @unserialize( $output );
                    if ( $unserialized !== false ) {
                        $this->assertIsArray( $unserialized );
                    } else {
                        // Could be HTML output
                        $this->assertNotEmpty( $output );
                    }
                } else {
                    $this->assertIsArray( $output );
                }
                break;

            case 'bootstrap':
            // claude case removed - AI-ready is now a modifier, not a framework
                // Should be valid HTML with content
                $this->assertNotEmpty(
                    strip_tags( $outputStr ),
                    "{$source} → {$target}: Output has no text content"
                );
                // Should contain HTML tags
                $this->assertMatchesRegularExpression(
                    '/<[a-z][\s\S]*>/i',
                    $outputStr,
                    "{$source} → {$target}: Output is not valid HTML"
                );
                break;
        }
    }

    /**
     * Assert Bricks output uses the flat 2.x structure.
     *
     * Real Bricks pages are a flat top-level array; hierarchy is expressed via
     * each element's string `parent` id and `children` arrays of string ids
     * (never nested element objects).
     *
     * @param array  $decoded Decoded JSON output.
     * @param string $source  Source framework, for error messages.
     */
    private function assertBricksFlatStructure( array $decoded, string $source ): void {
        $this->assertArrayNotHasKey(
            'name',
            $decoded,
            "{$source} → bricks: top level must be a flat array of elements, not a single element"
        );

        $ids = [];
        foreach ( $decoded as $i => $element ) {
            $this->assertIsArray( $element, "{$source} → bricks: element [{$i}] is not an array" );
            $this->assertArrayHasKey( 'id', $element, "{$source} → bricks: element [{$i}] missing id" );
            $this->assertArrayHasKey( 'name', $element, "{$source} → bricks: element [{$i}] missing name" );
            $this->assertArrayHasKey( 'parent', $element, "{$source} → bricks: element [{$i}] missing parent" );

            $ids[ (string) $element['id'] ] = true;

            if ( isset( $element['children'] ) ) {
                $this->assertIsArray( $element['children'], "{$source} → bricks: element [{$i}] children is not an array" );
                foreach ( $element['children'] as $j => $child ) {
                    $this->assertIsString(
                        $child,
                        "{$source} → bricks: element [{$i}] children[{$j}] must be a string id, not an element object"
                    );
                }
            }
        }

        // Every non-root parent id must point to a real element.
        foreach ( $decoded as $i => $element ) {
            $parent = (string) ( $element['parent'] ?? '0' );
            if ( $parent === '0' || $parent === '' ) {
                continue;
            }
            $this->assertArrayHasKey(
                $parent,
                $ids,
                "{$source} → bricks: element [{$i}] parent '{$parent}' does not match any element id"
            );
        }
    }

    /**
     * Assert Oxygen 6 output is a wrapped nested JSON tree (Breakdance-proxy schema).
     *
     * Expected shape: `{ "_version": 1, "_nextNodeId": <int>, "tree": [ <node>, ... ] }`
     * where each node is `{ id, type, properties, children: [<node>, ...] }` and
     * `type` is a namespaced string containing a backslash.
     *
     * @param array  $decoded Decoded JSON output.
     * @param string $source  Source framework, for error messages.
     */
    private function assertOxygen6TreeStructure( array $decoded, string $source ): void {
        $this->assertArrayHasKey( 'tree', $decoded, "{$source} → oxygen-6: payload must have a 'tree' key" );
        $this->assertIsArray( $decoded['tree'], "{$source} → oxygen-6: 'tree' must be an array of root nodes" );
        $this->assertArrayHasKey( '_nextNodeId', $decoded, "{$source} → oxygen-6: payload must carry '_nextNodeId' for collision-free injects" );

        foreach ( $decoded['tree'] as $i => $node ) {
            $this->walkOxygen6Node( $node, "{$source} → oxygen-6: tree[{$i}]" );
        }
    }

    /**
     * Assert Elementor 4 Atomic output: every element has the v4 atomic shape
     * (id/version/elType/isInner/interactions/settings/editor_settings/styles/elements)
     * and elType is prefixed `e-`.
     *
     * @param array  $decoded Decoded JSON output.
     * @param string $source  Source framework, for error messages.
     */
    private function assertElementor4AtomicStructure( array $decoded, string $source ): void {
        $this->assertNotEmpty( $decoded, "{$source} → elementor-4: empty output" );

        foreach ( $decoded as $i => $node ) {
            $this->walkElementor4Node( $node, "{$source} → elementor-4: [{$i}]" );
        }
    }

    /**
     * Recursively validate a single atomic v4 node.
     *
     * @param mixed  $node Candidate node.
     * @param string $path Human-readable path for error messages.
     */
    private function walkElementor4Node( $node, string $path ): void {
        $this->assertIsArray( $node, "{$path}: node is not an array" );
        foreach ( [ 'id', 'version', 'elType', 'isInner', 'interactions', 'settings', 'editor_settings', 'styles', 'elements' ] as $key ) {
            $this->assertArrayHasKey( $key, $node, "{$path}: atomic node missing '{$key}'" );
        }
        $this->assertIsString( $node['elType'], "{$path}: elType must be a string" );
        $this->assertStringStartsWith(
            'e-',
            $node['elType'],
            "{$path}: elType '{$node['elType']}' must use the atomic `e-` prefix"
        );
        $this->assertIsArray( $node['elements'], "{$path}: elements must be an array" );

        foreach ( $node['elements'] as $j => $child ) {
            $this->walkElementor4Node( $child, "{$path}/elements[{$j}]" );
        }
    }

    /**
     * Recursively validate a single Oxygen 6 node.
     *
     * @param mixed  $node Candidate node.
     * @param string $path Human-readable path for error messages.
     */
    private function walkOxygen6Node( $node, string $path ): void {
        $this->assertIsArray( $node, "{$path}: node is not an array" );
        $this->assertArrayHasKey( 'id', $node, "{$path}: node missing id" );
        $this->assertArrayHasKey( 'type', $node, "{$path}: node missing type" );
        $this->assertIsString( $node['type'], "{$path}: type must be a string" );
        $this->assertStringContainsString(
            '\\',
            $node['type'],
            "{$path}: type '{$node['type']}' must be namespaced (e.g. EssentialElements\\Heading)"
        );
        $this->assertArrayHasKey( 'properties', $node, "{$path}: node missing properties" );
        $this->assertIsArray( $node['properties'], "{$path}: properties must be an array" );
        $this->assertArrayHasKey( 'children', $node, "{$path}: node missing children" );
        $this->assertIsArray( $node['children'], "{$path}: children must be an array" );

        foreach ( $node['children'] as $j => $child ) {
            $this->walkOxygen6Node( $child, "{$path}/children[{$j}]" );
        }
    }

    /**
     * Test that translator reports correct supported frameworks
     */
    public function testSupportedFrameworks(): void {
        $supported = DEVTB_Translator::get_supported_frameworks();

        foreach ( self::$frameworks as $framework ) {
            $this->assertContains(
                $framework,
                $supported,
                "Framework '{$framework}' should be supported"
            );
        }
    }

    /**
     * Test that same-framework translations are rejected
     *
     * @dataProvider sameFrameworkProvider
     */
    public function testSameFrameworkRejected( string $framework ): void {
        self::initializeSampleInputs();

        $result = $this->translator->translate(
            self::$sampleInputs[$framework],
            $framework,
            $framework
        );

        $this->assertFalse(
            $result,
            "Same-to-same translation should be rejected: {$framework}"
        );
    }

    /**
     * Data provider for same-framework tests
     */
    public static function sameFrameworkProvider(): array {
        $data = [];
        foreach ( self::$frameworks as $framework ) {
            $data[$framework] = [$framework];
        }
        return $data;
    }

    /**
     * Test that can_translate returns correct values
     */
    public function testCanTranslate(): void {
        // Valid pairs should return true
        $this->assertTrue(
            DEVTB_Translator::can_translate( 'bootstrap', 'elementor' )
        );

        // Same framework should return false
        $this->assertFalse(
            DEVTB_Translator::can_translate( 'bootstrap', 'bootstrap' )
        );

        // Invalid framework should return false
        $this->assertFalse(
            DEVTB_Translator::can_translate( 'invalid', 'bootstrap' )
        );
    }

    /**
     * Test translation statistics are populated
     */
    public function testTranslationStats(): void {
        self::initializeSampleInputs();

        $this->translator->translate(
            self::$sampleInputs['bootstrap'],
            'bootstrap',
            'elementor'
        );

        $stats = $this->translator->get_stats();

        $this->assertIsArray( $stats );
        $this->assertArrayHasKey( 'total_components', $stats );
        $this->assertArrayHasKey( 'processing_time', $stats );
        $this->assertGreaterThan( 0, $stats['processing_time'] );
    }

    /**
     * Bricks parser must accept the flat 2.x format (`children` of string ids).
     *
     * The translator's `bricks_to_*` paths feed the legacy nested fixture through
     * the parser, so without this test the flat-format branch in
     * DEVTB_Bricks_Parser::parse_flat() would be uncovered.
     */
    public function testBricksParserAcceptsFlatFormat(): void {
        $bridge_path = dirname( __DIR__, 2 ) . '/translation-bridge';
        require_once $bridge_path . '/parsers/class-bricks-parser.php';

        $flat = json_encode( [
            [
                'id'       => 'brxe00001',
                'name'     => 'section',
                'parent'   => '0',
                'children' => [ 'brxe00002' ],
                'settings' => [],
            ],
            [
                'id'       => 'brxe00002',
                'name'     => 'heading',
                'parent'   => 'brxe00001',
                'children' => [],
                'settings' => [ 'text' => 'Hello', 'tag' => 'h2' ],
            ],
        ] );

        $parser     = new \DEVTB\TranslationBridge\Parsers\DEVTB_Bricks_Parser();
        $components = $parser->parse( $flat );

        $this->assertCount( 1, $components, 'flat parse must yield exactly one root component' );

        $root = $components[0];
        $this->assertSame( 'container', $root->type, 'root section should map to universal container' );
        $this->assertCount( 1, $root->children, 'root should have one resolved child' );
        $this->assertSame( 'heading', $root->children[0]->type );
        $this->assertSame( 'Hello', $root->children[0]->content );
    }
}
