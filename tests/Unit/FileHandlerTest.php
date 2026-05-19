<?php
/**
 * File Handler Unit Tests
 *
 * @package DevelopmentTranslation_Bridge
 * @subpackage Tests
 */

namespace DEVTB\Tests\Unit;

use PHPUnit\Framework\TestCase;

class FileHandlerTest extends TestCase {

    private $file_handler;
    private $test_dir;

    protected function setUp(): void {
        parent::setUp();

        // Create test directory
        $this->test_dir = DEVTB_ROOT . '/tests/fixtures/temp';
        if (!is_dir($this->test_dir)) {
            mkdir($this->test_dir, 0755, true);
        }

        // Load the File Handler class
        require_once DEVTB_INCLUDES . '/class-devtb-file-handler.php';
        $this->file_handler = new \DEVTB_File_Handler();
    }

    protected function tearDown(): void {
        // Clean up test files
        if (is_dir($this->test_dir)) {
            $this->recursiveDelete($this->test_dir);
        }

        parent::tearDown();
    }

    private function recursiveDelete($dir) {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->recursiveDelete($path) : unlink($path);
        }
        rmdir($dir);
    }

    /**
     * Test path safety validation
     */
    public function test_is_safe_path_rejects_directory_traversal() {
        $unsafe_paths = [
            '../../../etc/passwd',
            '../../sensitive-file.php',
            'test/../../../etc/passwd',
        ];

        foreach ($unsafe_paths as $path) {
            $this->assertFalse(
                $this->file_handler->is_safe_path($path),
                "Path should be rejected: $path"
            );
        }
    }

    public function test_is_safe_path_rejects_null_bytes() {
        $path_with_null = "test\0.php";
        $this->assertFalse(
            $this->file_handler->is_safe_path($path_with_null),
            'Path with null byte should be rejected'
        );
    }

    public function test_is_safe_path_accepts_valid_paths() {
        $test_file = $this->test_dir . '/test.txt';
        file_put_contents($test_file, 'test content');

        $this->assertTrue(
            $this->file_handler->is_safe_path($test_file),
            'Valid path should be accepted'
        );
    }

    /**
     * Test filename sanitization
     */
    public function test_sanitize_filename_removes_dangerous_characters() {
        $dangerous_names = [
            '../malicious.php' => 'malicious.php',
            'test/../file.txt' => 'file.txt',
            'file<script>.txt' => 'filescript.txt',
            'file|name.txt' => 'filename.txt',
        ];

        foreach ($dangerous_names as $input => $expected) {
            $result = $this->file_handler->sanitize_filename($input);
            $this->assertStringNotContainsString('..', $result);
            $this->assertStringNotContainsString('/', $result);
        }
    }

    /**
     * Test filesystem extension extraction (filename-based, not framework-based).
     */
    public function test_get_extension_returns_correct_extension() {
        $test_files = [
            'file.txt' => 'txt',
            'document.html' => 'html',
            'script.js' => 'js',
            'data.json' => 'json',
            'no_extension' => '',
        ];

        foreach ($test_files as $filename => $expected) {
            $result = $this->file_handler->get_file_extension($filename);
            $this->assertEquals($expected, $result, "Extension mismatch for $filename");
        }
    }

    /**
     * Test file size formatting
     */
    public function test_format_file_size() {
        $sizes = [
            0 => '0 B',
            1024 => '1 KB',
            1048576 => '1 MB',
            1073741824 => '1 GB',
            500 => '500 B',
            1536 => '1.5 KB',
        ];

        foreach ($sizes as $bytes => $expected) {
            $result = $this->file_handler->format_file_size($bytes);
            $this->assertEquals($expected, $result, "Size formatting mismatch for $bytes bytes");
        }
    }

    /**
     * Test directory listing
     */
    public function test_list_files_returns_files_in_directory() {
        // Create test files
        file_put_contents($this->test_dir . '/file1.txt', 'content 1');
        file_put_contents($this->test_dir . '/file2.txt', 'content 2');
        file_put_contents($this->test_dir . '/file3.json', 'content 3');

        $files = $this->file_handler->list_files($this->test_dir);

        $this->assertIsArray($files);
        $this->assertGreaterThanOrEqual(3, count($files));
    }

    /**
     * Test file pattern matching
     */
    public function test_find_files_by_pattern() {
        // Create test files
        file_put_contents($this->test_dir . '/test1.txt', 'content');
        file_put_contents($this->test_dir . '/test2.txt', 'content');
        file_put_contents($this->test_dir . '/other.json', 'content');

        $txt_files = $this->file_handler->find_files($this->test_dir, '*.txt');

        $this->assertIsArray($txt_files);
        $this->assertGreaterThanOrEqual(2, count($txt_files));

        foreach ($txt_files as $file) {
            $this->assertStringEndsWith('.txt', $file);
        }
    }

    /**
     * Test file existence check
     */
    public function test_file_exists_check() {
        $test_file = $this->test_dir . '/exists.txt';
        file_put_contents($test_file, 'content');

        $this->assertTrue(file_exists($test_file));
        $this->assertFalse(file_exists($this->test_dir . '/nonexistent.txt'));
    }
}
