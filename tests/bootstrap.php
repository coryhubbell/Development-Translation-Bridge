<?php
/**
 * PHPUnit Bootstrap File
 *
 * Sets up the testing environment for DevelopmentTranslation Bridge.
 *
 * @package DevelopmentTranslation_Bridge
 * @subpackage Tests
 */

// Define test mode (guarded — same constants may be defined by the CLI bootstrap or theme).
if (!defined('DEVTB_TESTING'))                 { define('DEVTB_TESTING', true); }
if (!defined('DEVTB_ROOT'))                    { define('DEVTB_ROOT', dirname(__DIR__)); }
if (!defined('DEVTB_INCLUDES'))                { define('DEVTB_INCLUDES', DEVTB_ROOT . '/includes'); }
if (!defined('DEVTB_TRANSLATION_BRIDGE'))      { define('DEVTB_TRANSLATION_BRIDGE', DEVTB_ROOT . '/translation-bridge'); }
if (!defined('DEVTB_TRANSLATION_BRIDGE_DIR'))  { define('DEVTB_TRANSLATION_BRIDGE_DIR', DEVTB_TRANSLATION_BRIDGE); }
if (!defined('DEVTB_VERSION'))                 { define('DEVTB_VERSION', '4.6.0'); }

// Load Composer autoloader
$autoloader = DEVTB_ROOT . '/vendor/autoload.php';
if (file_exists($autoloader)) {
    require_once $autoloader;
} else {
    echo "Composer autoloader not found. Run: composer install\n";
    exit(1);
}

// Shared DEVTB class autoloader (CLI + tests + WP all use the same logic).
require_once DEVTB_ROOT . '/includes/class-devtb-autoloader.php';
devtb_register_autoloader();

// Shared WordPress function stubs (also used by /devtb-php).
require_once DEVTB_ROOT . '/includes/wp-function-stubs.php';

// Initialize Brain Monkey for WordPress function mocking (no-op if not installed).
if (class_exists('\Brain\Monkey')) {
    \Brain\Monkey\setUp();
}

echo "PHPUnit Bootstrap loaded successfully\n";
echo "Test environment initialized\n";
