<?php
/**
 * CLI Unit Tests
 *
 * @package DevelopmentTranslation_Bridge
 * @subpackage Tests
 */

namespace DEVTB\Tests\Unit;

use PHPUnit\Framework\TestCase;

class CLITest extends TestCase {

    /**
     * Test parse_arguments extracts command
     */
    public function test_parse_arguments_extracts_command() {
        require_once DEVTB_INCLUDES . '/class-devtb-cli.php';
        $cli = new \DEVTB_CLI(['translate', 'bootstrap', 'divi', 'test.html']);

        // Use reflection to check parsed command
        $reflection = new \ReflectionClass($cli);
        $commandProperty = $reflection->getProperty('command');

        $this->assertEquals('translate', $commandProperty->getValue($cli));
    }

    /**
     * Test parse_arguments handles long options
     */
    public function test_parse_arguments_handles_long_options() {
        require_once DEVTB_INCLUDES . '/class-devtb-cli.php';
        $cli = new \DEVTB_CLI(['translate', '--dry-run', '--debug']);

        $reflection = new \ReflectionClass($cli);
        $optionsProperty = $reflection->getProperty('options');
        $options = $optionsProperty->getValue($cli);

        $this->assertTrue($options['dry-run']);
        $this->assertTrue($options['debug']);
    }

    /**
     * Test parse_arguments handles short options
     */
    public function test_parse_arguments_handles_short_options() {
        require_once DEVTB_INCLUDES . '/class-devtb-cli.php';
        $cli = new \DEVTB_CLI(['translate', '-n', '-d']);

        $reflection = new \ReflectionClass($cli);
        $optionsProperty = $reflection->getProperty('options');
        $options = $optionsProperty->getValue($cli);

        $this->assertTrue($options['n']);
        $this->assertTrue($options['d']);
    }

    /**
     * Test parse_arguments handles option equals syntax
     */
    public function test_parse_arguments_handles_option_equals_syntax() {
        require_once DEVTB_INCLUDES . '/class-devtb-cli.php';
        $cli = new \DEVTB_CLI(['translate', '--output=/path/to/file.html']);

        $reflection = new \ReflectionClass($cli);
        $optionsProperty = $reflection->getProperty('options');
        $options = $optionsProperty->getValue($cli);

        $this->assertEquals('/path/to/file.html', $options['output']);
    }

    /**
     * Test empty args defaults to help command
     */
    public function test_empty_args_defaults_to_help() {
        require_once DEVTB_INCLUDES . '/class-devtb-cli.php';
        $cli = new \DEVTB_CLI([]);

        $reflection = new \ReflectionClass($cli);
        $commandProperty = $reflection->getProperty('command');

        $this->assertEquals('help', $commandProperty->getValue($cli));
    }

    /**
     * Test frameworks array contains all 14 frameworks (v4.3.0 matrix)
     */
    public function test_frameworks_array_contains_all_frameworks() {
        require_once DEVTB_INCLUDES . '/class-devtb-cli.php';
        $cli = new \DEVTB_CLI(['list-frameworks']);

        $reflection = new \ReflectionClass($cli);
        $frameworksProperty = $reflection->getProperty('frameworks');
        $frameworks = $frameworksProperty->getValue($cli);

        $expected_frameworks = [
            'bootstrap', 'divi', 'divi-5', 'elementor', 'elementor-4',
            'avada', 'bricks', 'wpbakery', 'beaver-builder', 'gutenberg',
            'oxygen', 'oxygen-6', 'kadence', 'thrive',
        ];

        foreach ($expected_frameworks as $framework) {
            $this->assertArrayHasKey($framework, $frameworks);
        }

        $this->assertCount(14, $frameworks);
    }

    /**
     * Test params extraction for translate command
     */
    public function test_params_extraction_for_translate_command() {
        require_once DEVTB_INCLUDES . '/class-devtb-cli.php';
        $cli = new \DEVTB_CLI(['translate', 'bootstrap', 'divi', 'input.html']);

        $reflection = new \ReflectionClass($cli);
        $paramsProperty = $reflection->getProperty('params');
        $params = $paramsProperty->getValue($cli);

        $this->assertEquals('bootstrap', $params[0]);
        $this->assertEquals('divi', $params[1]);
        $this->assertEquals('input.html', $params[2]);
    }

    /**
     * Test translate-all params extraction
     */
    public function test_translate_all_params_extraction() {
        require_once DEVTB_INCLUDES . '/class-devtb-cli.php';
        $cli = new \DEVTB_CLI(['translate-all', 'bootstrap', 'input.html']);

        $reflection = new \ReflectionClass($cli);
        $paramsProperty = $reflection->getProperty('params');
        $params = $paramsProperty->getValue($cli);

        $this->assertEquals('bootstrap', $params[0]);
        $this->assertEquals('input.html', $params[1]);
    }

    /**
     * Test validate command params
     */
    public function test_validate_command_params() {
        require_once DEVTB_INCLUDES . '/class-devtb-cli.php';
        $cli = new \DEVTB_CLI(['validate', 'elementor', 'page.json']);

        $reflection = new \ReflectionClass($cli);
        $commandProperty = $reflection->getProperty('command');
        $paramsProperty = $reflection->getProperty('params');

        $this->assertEquals('validate', $commandProperty->getValue($cli));
        $params = $paramsProperty->getValue($cli);
        $this->assertEquals('elementor', $params[0]);
        $this->assertEquals('page.json', $params[1]);
    }

    /**
     * Test option with value after space
     */
    public function test_option_with_value_after_space() {
        require_once DEVTB_INCLUDES . '/class-devtb-cli.php';
        $cli = new \DEVTB_CLI(['translate', '--target', 'elementor']);

        $reflection = new \ReflectionClass($cli);
        $optionsProperty = $reflection->getProperty('options');
        $options = $optionsProperty->getValue($cli);

        $this->assertEquals('elementor', $options['target']);
    }

    /**
     * Test mixed positional and options parsing
     */
    public function test_mixed_positional_and_options_parsing() {
        require_once DEVTB_INCLUDES . '/class-devtb-cli.php';
        $cli = new \DEVTB_CLI(['translate', 'bootstrap', '--dry-run', 'divi', '-o', 'output.html', 'input.html']);

        $reflection = new \ReflectionClass($cli);
        $paramsProperty = $reflection->getProperty('params');
        $optionsProperty = $reflection->getProperty('options');

        $params = $paramsProperty->getValue($cli);
        $options = $optionsProperty->getValue($cli);

        $this->assertContains('bootstrap', $params);
        $this->assertTrue($options['dry-run']);
        $this->assertEquals('output.html', $options['o']);
    }

    /**
     * Test list-frameworks command detection
     */
    public function test_list_frameworks_command_detection() {
        require_once DEVTB_INCLUDES . '/class-devtb-cli.php';
        $cli = new \DEVTB_CLI(['list-frameworks']);

        $reflection = new \ReflectionClass($cli);
        $commandProperty = $reflection->getProperty('command');

        $this->assertEquals('list-frameworks', $commandProperty->getValue($cli));
    }

    /**
     * Test help command with additional params
     */
    public function test_help_command_with_params() {
        require_once DEVTB_INCLUDES . '/class-devtb-cli.php';
        $cli = new \DEVTB_CLI(['help', 'translate']);

        $reflection = new \ReflectionClass($cli);
        $commandProperty = $reflection->getProperty('command');
        $paramsProperty = $reflection->getProperty('params');

        $this->assertEquals('help', $commandProperty->getValue($cli));
        $params = $paramsProperty->getValue($cli);
        $this->assertEquals('translate', $params[0]);
    }
}
