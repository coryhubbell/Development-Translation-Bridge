<?php
/**
 * PHPUnit Bootstrap File
 *
 * Sets up the testing environment for DevelopmentTranslation Bridge
 *
 * @package DevelopmentTranslation_Bridge
 * @subpackage Tests
 */

// Define test mode
define('DEVTB_TESTING', true);

// Define root directory
define('DEVTB_ROOT', dirname(__DIR__));
define('DEVTB_INCLUDES', DEVTB_ROOT . '/includes');
define('DEVTB_TRANSLATION_BRIDGE', DEVTB_ROOT . '/translation-bridge');
define('DEVTB_TRANSLATION_BRIDGE_DIR', DEVTB_TRANSLATION_BRIDGE);
define('DEVTB_VERSION', '3.3.0');

// Load Composer autoloader
$autoloader = DEVTB_ROOT . '/vendor/autoload.php';
if (file_exists($autoloader)) {
    require_once $autoloader;
} else {
    echo "Composer autoloader not found. Run: composer install\n";
    exit(1);
}

// Initialize Brain Monkey for WordPress function mocking
if (class_exists('\Brain\Monkey')) {
    \Brain\Monkey\setUp();
}

// Mock WordPress functions that are commonly used
if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
        return true;
    }
}

if (!function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {
        return true;
    }
}

if (!function_exists('do_action')) {
    function do_action($hook, ...$args) {
        return true;
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($hook, $value, ...$args) {
        return $value;
    }
}

if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        return $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($option, $value) {
        return true;
    }
}

if (!function_exists('delete_option')) {
    function delete_option($option) {
        return true;
    }
}

if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}

if (!function_exists('_e')) {
    function _e($text, $domain = 'default') {
        echo $text;
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_url')) {
    function esc_url($url) {
        return filter_var($url, FILTER_SANITIZE_URL);
    }
}

if (!function_exists('wp_kses_post')) {
    function wp_kses_post($data) {
        return $data;
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return strip_tags($str);
    }
}

// Mock WP_Error class
if (!class_exists('WP_Error')) {
    class WP_Error {
        private $errors = [];
        private $error_data = [];

        public function __construct($code = '', $message = '', $data = '') {
            if (empty($code)) {
                return;
            }
            $this->errors[$code][] = $message;
            if (!empty($data)) {
                $this->error_data[$code] = $data;
            }
        }

        public function get_error_code() {
            return key($this->errors);
        }

        public function get_error_message($code = '') {
            if (empty($code)) {
                $code = $this->get_error_code();
            }
            return $this->errors[$code][0] ?? '';
        }

        public function get_error_data($code = '') {
            if (empty($code)) {
                $code = $this->get_error_code();
            }
            return $this->error_data[$code] ?? null;
        }
    }
}

// Mock WP_REST_Request class
if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request {
        private $params = [];
        private $headers = [];

        public function __construct($method = 'GET', $route = '', $params = []) {
            $this->params = $params;
        }

        public function get_param($key) {
            return $this->params[$key] ?? null;
        }

        public function get_params() {
            return $this->params;
        }

        public function set_param($key, $value) {
            $this->params[$key] = $value;
        }

        public function get_header($key) {
            return $this->headers[$key] ?? null;
        }

        public function set_header($key, $value) {
            $this->headers[$key] = $value;
        }
    }
}

// Mock WP_REST_Response class
if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response {
        private $data;
        private $status;

        public function __construct($data = null, $status = 200) {
            $this->data = $data;
            $this->status = $status;
        }

        public function get_data() {
            return $this->data;
        }

        public function get_status() {
            return $this->status;
        }
    }
}

// Additional WordPress functions for Translation Bridge
if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, $options = 0, $depth = 512) {
        return json_encode($data, $options, $depth);
    }
}

if (!function_exists('maybe_unserialize')) {
    function maybe_unserialize($data) {
        if (is_serialized($data)) {
            return @unserialize(trim($data));
        }
        return $data;
    }
}

if (!function_exists('is_serialized')) {
    function is_serialized($data, $strict = true) {
        if (!is_string($data)) {
            return false;
        }
        $data = trim($data);
        if ('N;' === $data) {
            return true;
        }
        if (strlen($data) < 4) {
            return false;
        }
        if (':' !== $data[1]) {
            return false;
        }
        if ($strict) {
            $lastc = substr($data, -1);
            if (';' !== $lastc && '}' !== $lastc) {
                return false;
            }
        } else {
            $semicolon = strpos($data, ';');
            $brace = strpos($data, '}');
            if (false === $semicolon && false === $brace) {
                return false;
            }
            if (false !== $semicolon && $semicolon < 3) {
                return false;
            }
            if (false !== $brace && $brace < 4) {
                return false;
            }
        }
        $token = $data[0];
        switch ($token) {
            case 's':
                if ($strict) {
                    if ('"' !== substr($data, -2, 1)) {
                        return false;
                    }
                } elseif (!str_contains($data, '"')) {
                    return false;
                }
            case 'a':
            case 'O':
            case 'E':
                return (bool) preg_match("/^{$token}:[0-9]+:/s", $data);
            case 'b':
            case 'i':
            case 'd':
                $end = $strict ? '$' : '';
                return (bool) preg_match("/^{$token}:[0-9.E+-]+;{$end}/", $data);
        }
        return false;
    }
}

if (!function_exists('do_shortcode')) {
    function do_shortcode($content) {
        return $content;
    }
}

if (!function_exists('shortcode_atts')) {
    function shortcode_atts($pairs, $atts, $shortcode = '') {
        $atts = (array) $atts;
        $out = [];
        foreach ($pairs as $name => $default) {
            if (array_key_exists($name, $atts)) {
                $out[$name] = $atts[$name];
            } else {
                $out[$name] = $default;
            }
        }
        return $out;
    }
}

// Mock WordPress get_shortcode_regex function
// This regex matches WordPress core's implementation from wp-includes/shortcodes.php
if (!function_exists('get_shortcode_regex')) {
    function get_shortcode_regex($tagnames = null) {
        // Default to common page builder shortcode patterns
        $tagregexp = '[a-zA-Z0-9_-]+';
        if ($tagnames !== null && is_array($tagnames)) {
            $tagregexp = implode('|', array_map('preg_quote', $tagnames));
        }

        return '\\['                              // Opening bracket
            . '(\\[?)'                           // 1: Optional second opening bracket for escaping: [[tag]]
            . "($tagregexp)"                     // 2: Shortcode name
            . '(?![\\w-])'                       // Not followed by word character or hyphen
            . '('                                // 3: Unroll the loop: Inside the opening shortcode tag
            .     '[^\\]\\/]*'                   // Not a closing bracket or forward slash
            .     '(?:'
            .         '\\/(?!\\])'               // A forward slash not followed by a closing bracket
            .         '[^\\]\\/]*'               // Not a closing bracket or forward slash
            .     ')*?'
            . ')'
            . '(?:'
            .     '(\\/)'                        // 4: Self closing tag...
            .     '\\]'                          // ...and closing bracket
            . '|'
            .     '\\]'                          // Closing bracket
            .     '(?:'
            .         '('                        // 5: Unroll the loop: Optionally, anything between the opening and closing tags
            .             '[^\\[]*+'             // Not an opening bracket
            .             '(?:'
            .                 '\\[(?!\\/\\2\\])' // An opening bracket not followed by the closing shortcode tag
            .                 '[^\\[]*+'         // Not an opening bracket
            .             ')*+'
            .         ')'
            .         '\\[\\/\\2\\]'             // Closing shortcode tag
            .     ')?'
            . ')'
            . '(\\]?)';                          // 6: Optional second closing bracket for escaping: [[tag]]
    }
}

// Mock WordPress shortcode_parse_atts function
// Parses shortcode attribute strings into associative arrays
if (!function_exists('shortcode_parse_atts')) {
    function shortcode_parse_atts($text) {
        $atts = [];
        // Match: name="value", name='value', or name=value
        $pattern = '/(\w+)\s*=\s*"([^"]*)"|(\w+)\s*=\s*\'([^\']*)\'|(\w+)\s*=\s*([^\s\]]+)/';
        if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                if (!empty($match[1])) {
                    $atts[$match[1]] = $match[2];
                } elseif (!empty($match[3])) {
                    $atts[$match[3]] = $match[4];
                } elseif (!empty($match[5])) {
                    $atts[$match[5]] = $match[6];
                }
            }
        }
        return $atts;
    }
}

// Mock WordPress strip_shortcodes function
if (!function_exists('strip_shortcodes')) {
    function strip_shortcodes($content) {
        if (empty($content)) {
            return $content;
        }
        // Simple regex to remove shortcode tags
        return preg_replace('/\[[^\]]+\]/', '', $content);
    }
}

echo "PHPUnit Bootstrap loaded successfully\n";
echo "Test environment initialized\n";
