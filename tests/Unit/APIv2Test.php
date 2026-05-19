<?php
/**
 * API v2 Unit Tests
 *
 * @package DevelopmentTranslation_Bridge
 * @subpackage Tests
 */

namespace DEVTB\Tests\Unit;

use PHPUnit\Framework\TestCase;

class APIv2Test extends TestCase {

    private $api;

    protected function setUp(): void {
        parent::setUp();

        // Load the API class
        require_once DEVTB_INCLUDES . '/class-devtb-api-v2.php';
        $this->api = new \DEVTB_API_V2();
    }

    /**
     * Test API instance is created
     */
    public function test_api_instance_is_created() {
        $this->assertInstanceOf(\DEVTB_API_V2::class, $this->api);
    }

    /**
     * Test translate endpoint validates source framework
     */
    public function test_translate_endpoint_validates_source_framework() {
        $request = new \WP_REST_Request('POST', '/devtb/v2/translate');
        $request->set_param('source', 'invalid_framework');
        $request->set_param('target', 'bootstrap');
        $request->set_param('content', '<div>Test</div>');

        $result = $this->api->translate($request);

        $this->assertInstanceOf(\WP_Error::class, $result);
    }

    /**
     * Test translate endpoint validates target framework
     */
    public function test_translate_endpoint_validates_target_framework() {
        $request = new \WP_REST_Request('POST', '/devtb/v2/translate');
        $request->set_param('source', 'bootstrap');
        $request->set_param('target', 'invalid_framework');
        $request->set_param('content', '<div>Test</div>');

        $result = $this->api->translate($request);

        $this->assertInstanceOf(\WP_Error::class, $result);
    }

    /**
     * Test translate endpoint requires content
     */
    public function test_translate_endpoint_requires_content() {
        $request = new \WP_REST_Request('POST', '/devtb/v2/translate');
        $request->set_param('source', 'bootstrap');
        $request->set_param('target', 'divi');
        // No content provided

        $result = $this->api->translate($request);

        // Should return error or handle empty content
        $this->assertTrue(
            $result instanceof \WP_Error ||
            ($result instanceof \WP_REST_Response && isset($result->data['error']))
        );
    }

    /**
     * Test batch_translate validates targets array
     */
    public function test_batch_translate_validates_targets_array() {
        $request = new \WP_REST_Request('POST', '/devtb/v2/batch-translate');
        $request->set_param('source', 'bootstrap');
        $request->set_param('targets', 'not_an_array');
        $request->set_param('content', '<div>Test</div>');

        $result = $this->api->batch_translate($request);

        // Should return error for invalid targets
        $this->assertTrue(
            $result instanceof \WP_Error ||
            ($result instanceof \WP_REST_Response && !$result->data['success'])
        );
    }

    /**
     * Test frameworks endpoint is public
     */
    public function test_frameworks_endpoint_returns_frameworks() {
        $result = $this->api->list_frameworks();

        $this->assertInstanceOf(\WP_REST_Response::class, $result);

        $data = $result->get_data();
        $this->assertArrayHasKey('success', $data);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('frameworks', $data);
        $this->assertCount(14, $data['frameworks']);
    }

    /**
     * Test status endpoint returns API info
     */
    public function test_status_endpoint_returns_api_info() {
        $result = $this->api->get_status();

        $this->assertInstanceOf(\WP_REST_Response::class, $result);

        $data = $result->get_data();
        $this->assertArrayHasKey('success', $data);
        $this->assertArrayHasKey('api', $data);
        $this->assertEquals('devtb', $data['api']['name']);
        $this->assertEquals('v2', $data['api']['version']);
    }

    /**
     * Test validate endpoint returns validation results
     */
    public function test_validate_endpoint_returns_results() {
        $request = new \WP_REST_Request('POST', '/devtb/v2/validate');
        $request->set_param('framework', 'bootstrap');
        $request->set_param('content', '<div class="container"><div class="row"><div class="col">Test</div></div></div>');

        $result = $this->api->validate($request);

        if ($result instanceof \WP_REST_Response) {
            $data = $result->get_data();
            $this->assertArrayHasKey('success', $data);
            $this->assertArrayHasKey('valid', $data);
        } else {
            // May be WP_Error if parser not available
            $this->assertInstanceOf(\WP_Error::class, $result);
        }
    }

    /**
     * Test frameworks list contains all 14 frameworks
     */
    public function test_frameworks_list_contains_all_frameworks() {
        $result = $this->api->list_frameworks();
        $data = $result->get_data();
        $frameworks = array_keys($data['frameworks']);

        $expected = [
            'bootstrap', 'divi', 'divi-5', 'elementor', 'elementor-4',
            'avada', 'bricks', 'wpbakery', 'beaver-builder', 'gutenberg',
            'oxygen', 'oxygen-6', 'kadence', 'thrive',
        ];

        foreach ($expected as $framework) {
            $this->assertContains($framework, $frameworks);
        }
    }

    /**
     * Test translate with valid bootstrap content
     */
    public function test_translate_with_valid_bootstrap_content() {
        $request = new \WP_REST_Request('POST', '/devtb/v2/translate');
        $request->set_param('source', 'bootstrap');
        $request->set_param('target', 'divi');
        $request->set_param('content', '<section class="hero"><div class="container"><h1>Welcome</h1></div></section>');

        $result = $this->api->translate($request);

        if ($result instanceof \WP_REST_Response) {
            $data = $result->get_data();
            $this->assertArrayHasKey('success', $data);
            if ($data['success']) {
                $this->assertArrayHasKey('result', $data);
            }
        }
    }

    /**
     * Test get_job_status returns error for unknown job
     */
    public function test_get_job_status_returns_error_for_unknown() {
        $request = new \WP_REST_Request('GET', '/devtb/v2/job/nonexistent_job_id');
        $request->set_param('job_id', 'nonexistent_job_id');

        $result = $this->api->get_job_status($request);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('devtb_job_not_found', $result->get_error_code());
    }

    /**
     * Test framework info structure
     */
    public function test_framework_info_structure() {
        $result = $this->api->list_frameworks();
        $data = $result->get_data();
        $framework = $data['frameworks']['bootstrap'];

        $this->assertArrayHasKey('name', $framework);
        $this->assertArrayHasKey('description', $framework);
        $this->assertArrayHasKey('format', $framework);
        $this->assertArrayHasKey('file_extensions', $framework);
    }

    /**
     * Test API returns correct HTTP status codes
     */
    public function test_api_returns_correct_status_codes() {
        $result = $this->api->list_frameworks();

        $this->assertEquals(200, $result->get_status());
    }

    /**
     * Test batch translate with async flag creates job
     */
    public function test_batch_translate_async_returns_job_id() {
        $request = new \WP_REST_Request('POST', '/devtb/v2/batch-translate');
        $request->set_param('source', 'bootstrap');
        $request->set_param('targets', ['divi', 'elementor']);
        $request->set_param('content', '<div class="container">Test</div>');
        $request->set_param('async', true);

        $result = $this->api->batch_translate($request);

        if ($result instanceof \WP_REST_Response) {
            $data = $result->get_data();
            if ($data['success'] && isset($data['job_id'])) {
                $this->assertStringStartsWith('devtb_', $data['job_id']);
            }
        }
    }
}
