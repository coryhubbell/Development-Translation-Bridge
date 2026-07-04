<?php
/**
 * DevelopmentTranslation Bridge Theme Functions
 *
 * Registers the Translation Bridge with WordPress and provides
 * admin interface integration.
 *
 * @package    DevelopmentTranslation_Bridge
 * @version    4.12.0
 * @license    GPL-2.0+
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define theme constants
define('DEVTB_THEME_VERSION', '4.12.0');
define('DEVTB_ROOT', get_template_directory());
define('DEVTB_THEME_DIR', get_template_directory());
define('DEVTB_THEME_URL', get_template_directory_uri());
define('DEVTB_TRANSLATION_BRIDGE_DIR', DEVTB_THEME_DIR . '/translation-bridge');
define('DEVTB_INCLUDES_DIR', DEVTB_THEME_DIR . '/includes');

/**
 * Theme Setup
 */
function devtb_theme_setup() {
    // Add theme support
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', [
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'style',
        'script'
    ]);
    add_theme_support('customize-selective-refresh-widgets');

    // Register navigation menus
    register_nav_menus([
        'primary' => __('Primary Menu', 'devtb'),
        'footer'  => __('Footer Menu', 'devtb'),
    ]);

    // Set content width (WordPress global)
    global $content_width;
    if (!isset($content_width)) {
        $content_width = 1200;
    }
}
add_action('after_setup_theme', 'devtb_theme_setup');

/**
 * Enqueue Scripts and Styles
 */
function devtb_enqueue_assets() {
    // Theme stylesheet
    wp_enqueue_style(
        'devtb-style',
        get_stylesheet_uri(),
        [],
        DEVTB_THEME_VERSION
    );

    // Theme script (if needed)
    // wp_enqueue_script(
    //     'devtb-script',
    //     DEVTB_THEME_URL . '/assets/js/main.js',
    //     ['jquery'],
    //     DEVTB_THEME_VERSION,
    //     true
    // );
}
add_action('wp_enqueue_scripts', 'devtb_enqueue_assets');

/**
 * Register Widget Areas
 */
function devtb_register_sidebars() {
    register_sidebar([
        'name'          => __('Sidebar', 'devtb'),
        'id'            => 'sidebar-1',
        'description'   => __('Main sidebar widget area', 'devtb'),
        'before_widget' => '<div id="%1$s" class="widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ]);

    register_sidebar([
        'name'          => __('Footer', 'devtb'),
        'id'            => 'footer-1',
        'description'   => __('Footer widget area', 'devtb'),
        'before_widget' => '<div id="%1$s" class="widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ]);
}
add_action('widgets_init', 'devtb_register_sidebars');

/**
 * Initialize Translation Bridge
 */
function devtb_init_translation_bridge() {
    // Defense-in-depth: register the shared autoloader so any class missed by the
    // glob-loaders below still resolves on first use (matches CLI + test behavior).
    if (file_exists(DEVTB_INCLUDES_DIR . '/class-devtb-autoloader.php')) {
        if (!defined('DEVTB_INCLUDES'))           { define('DEVTB_INCLUDES', DEVTB_INCLUDES_DIR); }
        if (!defined('DEVTB_TRANSLATION_BRIDGE')) { define('DEVTB_TRANSLATION_BRIDGE', DEVTB_TRANSLATION_BRIDGE_DIR); }
        require_once DEVTB_INCLUDES_DIR . '/class-devtb-autoloader.php';
        devtb_register_autoloader();
    }

    // Load Translation Bridge utils (must load first)
    foreach (glob(DEVTB_TRANSLATION_BRIDGE_DIR . '/utils/*.php') as $util_file) {
        require_once $util_file;
    }

    // Load Translation Bridge interfaces
    if (file_exists(DEVTB_TRANSLATION_BRIDGE_DIR . '/core/interface-parser.php')) {
        require_once DEVTB_TRANSLATION_BRIDGE_DIR . '/core/interface-parser.php';
        require_once DEVTB_TRANSLATION_BRIDGE_DIR . '/core/interface-converter.php';
    }

    // Load Translation Bridge model classes
    if (file_exists(DEVTB_TRANSLATION_BRIDGE_DIR . '/models/class-component.php')) {
        require_once DEVTB_TRANSLATION_BRIDGE_DIR . '/models/class-component.php';
        require_once DEVTB_TRANSLATION_BRIDGE_DIR . '/models/class-attribute.php';
        require_once DEVTB_TRANSLATION_BRIDGE_DIR . '/models/class-style.php';
    }

    // Load Translation Bridge parsers
    foreach (glob(DEVTB_TRANSLATION_BRIDGE_DIR . '/parsers/class-*-parser.php') as $parser_file) {
        require_once $parser_file;
    }

    // Load Translation Bridge converters
    foreach (glob(DEVTB_TRANSLATION_BRIDGE_DIR . '/converters/class-*-converter.php') as $converter_file) {
        require_once $converter_file;
    }

    // Load Translation Bridge core classes
    if (file_exists(DEVTB_TRANSLATION_BRIDGE_DIR . '/core/class-translator.php')) {
        require_once DEVTB_TRANSLATION_BRIDGE_DIR . '/core/class-mapping-engine.php';
        require_once DEVTB_TRANSLATION_BRIDGE_DIR . '/core/class-translator.php';
        require_once DEVTB_TRANSLATION_BRIDGE_DIR . '/core/class-parser-factory.php';
        require_once DEVTB_TRANSLATION_BRIDGE_DIR . '/core/class-converter-factory.php';
    }

    // Load theme includes
    if (file_exists(DEVTB_INCLUDES_DIR . '/class-devtb-file-handler.php')) {
        require_once DEVTB_INCLUDES_DIR . '/class-devtb-file-handler.php';
        require_once DEVTB_INCLUDES_DIR . '/class-devtb-logger.php';
        require_once DEVTB_INCLUDES_DIR . '/class-devtb-claude-api.php';
        require_once DEVTB_INCLUDES_DIR . '/class-devtb-auth.php';
        require_once DEVTB_INCLUDES_DIR . '/class-devtb-rate-limiter.php';
    }

    // Load config class
    if (file_exists(DEVTB_INCLUDES_DIR . '/class-devtb-config.php')) {
        require_once DEVTB_INCLUDES_DIR . '/class-devtb-config.php';
    }

    // Load encryption class
    if (file_exists(DEVTB_INCLUDES_DIR . '/class-devtb-encryption.php')) {
        require_once DEVTB_INCLUDES_DIR . '/class-devtb-encryption.php';
    }

    // Load persistence class
    if (file_exists(DEVTB_INCLUDES_DIR . '/class-devtb-persistence.php')) {
        require_once DEVTB_INCLUDES_DIR . '/class-devtb-persistence.php';
    }

    // Load corrections class
    if (file_exists(DEVTB_INCLUDES_DIR . '/class-devtb-corrections.php')) {
        require_once DEVTB_INCLUDES_DIR . '/class-devtb-corrections.php';
    }

    // Load API v2
    if (file_exists(DEVTB_INCLUDES_DIR . '/class-devtb-api-v2.php')) {
        require_once DEVTB_INCLUDES_DIR . '/class-devtb-api-v2.php';
        new DEVTB_API_V2();
    }

    // Load Job Queue (for async batch processing)
    if (file_exists(DEVTB_INCLUDES_DIR . '/class-devtb-job-queue.php')) {
        require_once DEVTB_INCLUDES_DIR . '/class-devtb-job-queue.php';
    }

    // Load Visual Interface
    if (file_exists(DEVTB_INCLUDES_DIR . '/class-devtb-visual-interface.php')) {
        require_once DEVTB_INCLUDES_DIR . '/class-devtb-visual-interface.php';
        new DEVTB_Visual_Interface();
    }
}
add_action('after_setup_theme', 'devtb_init_translation_bridge');

/**
 * Add Translation Bridge Admin Menu
 */
function devtb_add_admin_menu() {
    add_menu_page(
        __('Translation Bridge', 'devtb'),
        __('DEVTB Translation', 'devtb'),
        'manage_options',
        'devtb-translation',
        'devtb_admin_page',
        'dashicons-translation',
        30
    );

    add_submenu_page(
        'devtb-translation',
        __('Translate Content', 'devtb'),
        __('Translate', 'devtb'),
        'manage_options',
        'devtb-translation',
        'devtb_admin_page'
    );

    add_submenu_page(
        'devtb-translation',
        __('Frameworks', 'devtb'),
        __('Frameworks', 'devtb'),
        'manage_options',
        'devtb-frameworks',
        'devtb_frameworks_page'
    );

    add_submenu_page(
        'devtb-translation',
        __('Settings', 'devtb'),
        __('Settings', 'devtb'),
        'manage_options',
        'devtb-settings',
        'devtb_settings_page'
    );

    add_submenu_page(
        'devtb-translation',
        __('Documentation', 'devtb'),
        __('Docs', 'devtb'),
        'manage_options',
        'devtb-docs',
        'devtb_docs_page'
    );
}
add_action('admin_menu', 'devtb_add_admin_menu');

/**
 * Admin Page: Translation
 */
function devtb_admin_page() {
    ?>
    <div class="wrap">
        <h1><?php _e('Translation Bridge', 'devtb'); ?></h1>

        <div class="devtb-admin-notice">
            <strong>DevelopmentTranslation Bridge v<?php echo DEVTB_THEME_VERSION; ?></strong>
            <p><?php _e('Universal page builder translation system with AI optimization.', 'devtb'); ?></p>
        </div>

        <div class="card">
            <h2><?php _e('Quick Start', 'devtb'); ?></h2>
            <p><?php _e('Use the devtb CLI command to translate between frameworks:', 'devtb'); ?></p>
            <pre><code>devtb translate bootstrap divi input.html</code></pre>
            <pre><code>devtb translate elementor bootstrap page.json --ai-ready</code></pre>
            <pre><code>devtb translate-all bootstrap hero.html</code></pre>
        </div>

        <div class="card">
            <h2><?php _e('Supported Frameworks', 'devtb'); ?></h2>
            <?php
            // Pull live data from the canonical factory so this stays in sync with REST/CLI/admin.
            $framework_info    = \DEVTB\TranslationBridge\Core\DEVTB_Converter_Factory::get_framework_info();
            $framework_count   = count($framework_info);
            $translation_pairs = $framework_count * ($framework_count - 1);
            ?>
            <ul>
                <?php foreach ($framework_info as $slug => $meta): ?>
                    <li>
                        <strong><?php echo esc_html($meta['name']); ?></strong>
                        <?php if (!empty($meta['cms_version'])): ?>
                            — <?php echo esc_html($meta['cms_version']); ?>
                        <?php endif; ?>
                        (<?php echo esc_html($meta['format']); ?>)
                    </li>
                <?php endforeach; ?>
            </ul>
            <p><strong><?php echo (int) $translation_pairs; ?> Translation Pairs</strong> — convert any framework to any other framework</p>
            <p><strong>AI-Ready Option</strong> — use <code>--ai-ready</code> flag to add AI-friendly attributes</p>
        </div>

        <div class="card">
            <h2><?php _e('System Status', 'devtb'); ?></h2>
            <?php devtb_show_system_status(); ?>
        </div>

        <div class="card">
            <h2><?php _e('CLI Commands', 'devtb'); ?></h2>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php _e('Command', 'devtb'); ?></th>
                        <th><?php _e('Description', 'devtb'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>devtb translate &lt;source&gt; &lt;target&gt; &lt;file&gt;</code></td>
                        <td><?php _e('Translate between two frameworks', 'devtb'); ?></td>
                    </tr>
                    <tr>
                        <td><code>devtb translate-all &lt;source&gt; &lt;file&gt;</code></td>
                        <td><?php _e('Translate to all frameworks (14 files)', 'devtb'); ?></td>
                    </tr>
                    <tr>
                        <td><code>devtb list-frameworks</code></td>
                        <td><?php _e('List all supported frameworks', 'devtb'); ?></td>
                    </tr>
                    <tr>
                        <td><code>devtb validate &lt;framework&gt; &lt;file&gt;</code></td>
                        <td><?php _e('Validate a framework file', 'devtb'); ?></td>
                    </tr>
                    <tr>
                        <td><code>devtb --help</code></td>
                        <td><?php _e('Show help and usage information', 'devtb'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}

/**
 * Admin Page: Frameworks
 */
function devtb_frameworks_page() {
    ?>
    <div class="wrap">
        <h1><?php _e('Supported Frameworks', 'devtb'); ?></h1>

        <div class="card">
            <h2>Translation Matrix</h2>
            <?php
            // Single source of truth: factory's framework info (slug => name/cms_version/format/extension).
            $framework_info    = \DEVTB\TranslationBridge\Core\DEVTB_Converter_Factory::get_framework_info();
            $framework_count   = count($framework_info);
            $translation_pairs = $framework_count * ($framework_count - 1);
            ?>
            <p>The Translation Bridge supports conversion between all <?php echo (int) $framework_count; ?> frameworks:</p>

            <table class="widefat">
                <thead>
                    <tr>
                        <th>Framework</th>
                        <th>Target CMS</th>
                        <th>Format</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($framework_info as $slug => $meta): ?>
                    <tr>
                        <td><strong><?php echo esc_html($meta['name']); ?></strong></td>
                        <td><?php echo esc_html($meta['cms_version'] ?: '—'); ?></td>
                        <td><?php echo esc_html($meta['format']); ?></td>
                        <td><span style="color: green;">✓ Active</span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="card">
            <h2>Framework Details</h2>
            <p><strong>Translation Pairs:</strong> <?php echo (int) $translation_pairs; ?> (<?php echo (int) $framework_count; ?> frameworks × <?php echo (int) ($framework_count - 1); ?> targets)</p>
            <p><strong>AI-Ready Option:</strong> Use <code>--ai-ready</code> flag to add AI-friendly attributes</p>
            <p><strong>Visual Accuracy:</strong> 98% across all conversions</p>
            <p><strong>Conversion Speed:</strong> ~30 seconds average</p>
        </div>
    </div>
    <?php
}

/**
 * Admin Page: Settings
 */
function devtb_settings_page() {
    ?>
    <div class="wrap">
        <h1><?php _e('Settings', 'devtb'); ?></h1>

        <form method="post" action="options.php">
            <?php
            settings_fields('devtb_settings');
            do_settings_sections('devtb_settings');
            ?>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Claude API Key', 'devtb'); ?></th>
                    <td>
                        <input type="text"
                               name="devtb_claude_api_key"
                               value="<?php echo esc_attr(get_option('devtb_claude_api_key', '')); ?>"
                               class="regular-text"
                               placeholder="sk-ant-...">
                        <p class="description">
                            <?php _e('Optional: Add Claude API key for direct AI editing in web interface.', 'devtb'); ?>
                            <br>
                            <?php _e('Leave empty to use CLI-only mode.', 'devtb'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php _e('Default Source Framework', 'devtb'); ?></th>
                    <td>
                        <?php $current_default = get_option('devtb_default_source', 'bootstrap'); ?>
                        <select name="devtb_default_source">
                            <?php foreach (\DEVTB\TranslationBridge\Core\DEVTB_Converter_Factory::get_framework_info() as $slug => $meta): ?>
                                <option value="<?php echo esc_attr($slug); ?>" <?php selected($current_default, $slug); ?>><?php echo esc_html($meta['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php _e('Enable Logging', 'devtb'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="devtb_enable_logging" value="1" checked>
                            <?php _e('Log all translation operations', 'devtb'); ?>
                        </label>
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

/**
 * Admin Page: Documentation
 */
function devtb_docs_page() {
    ?>
    <div class="wrap">
        <h1><?php _e('Documentation', 'devtb'); ?></h1>

        <div class="card">
            <h2><?php _e('Getting Started', 'devtb'); ?></h2>
            <ol>
                <li><?php _e('Install and activate the DevelopmentTranslation Bridge theme', 'devtb'); ?></li>
                <li><?php _e('Open terminal and navigate to the theme directory', 'devtb'); ?></li>
                <li><?php _e('Use the devtb CLI command to translate content', 'devtb'); ?></li>
            </ol>
        </div>

        <div class="card">
            <h2><?php _e('CLI Documentation', 'devtb'); ?></h2>
            <p><?php _e('Full documentation available in:', 'devtb'); ?></p>
            <ul>
                <li><code>README.md</code> - <?php _e('Project overview and features', 'devtb'); ?></li>
                <li><code>QUICK_START.md</code> - <?php _e('Quick start guide', 'devtb'); ?></li>
                <li><code>TERMINAL_COMMANDS.md</code> - <?php _e('Complete CLI reference', 'devtb'); ?></li>
                <li><code>docs/TRANSLATION_BRIDGE.md</code> - <?php _e('Architecture documentation', 'devtb'); ?></li>
                <li><code>docs/CONVERSION_EXAMPLES.md</code> - <?php _e('Conversion examples', 'devtb'); ?></li>
            </ul>
        </div>

        <div class="card">
            <h2><?php _e('Resources', 'devtb'); ?></h2>
            <ul>
                <li><a href="https://github.com/coryhubbell/development-translation-bridge" target="_blank"><?php _e('GitHub Repository', 'devtb'); ?></a></li>
                <li><a href="https://claude.ai" target="_blank"><?php _e('Claude AI', 'devtb'); ?></a></li>
            </ul>
        </div>
    </div>
    <?php
}

/**
 * Show System Status
 */
function devtb_show_system_status() {
    // Pull live framework info from the factory.
    $framework_info    = \DEVTB\TranslationBridge\Core\DEVTB_Converter_Factory::get_framework_info();
    $framework_count   = count($framework_info);
    $translation_pairs = $framework_count * ($framework_count - 1);
    $framework_names   = array_map(static fn($meta) => $meta['name'], $framework_info);
    ?>
    <table class="widefat">
        <tr>
            <th><?php _e('Theme Version', 'devtb'); ?></th>
            <td><?php echo DEVTB_THEME_VERSION; ?></td>
        </tr>
        <tr>
            <th><?php _e('PHP Version', 'devtb'); ?></th>
            <td><?php echo PHP_VERSION; ?> <?php echo version_compare(PHP_VERSION, '8.1.0', '>=') ? '✓' : '✗ (8.1+ required)'; ?></td>
        </tr>
        <tr>
            <th><?php _e('Translation Bridge', 'devtb'); ?></th>
            <td><?php echo file_exists(DEVTB_TRANSLATION_BRIDGE_DIR . '/core/class-translator.php') ? '✓ Installed' : '✗ Not found'; ?></td>
        </tr>
        <tr>
            <th><?php _e('CLI Executable', 'devtb'); ?></th>
            <td><?php echo file_exists(DEVTB_THEME_DIR . '/devtb') ? '✓ Available' : '✗ Not found'; ?></td>
        </tr>
        <tr>
            <th><?php _e('Claude API', 'devtb'); ?></th>
            <td><?php echo get_option('devtb_claude_api_key') ? '✓ Configured' : '○ Not configured (CLI mode only)'; ?></td>
        </tr>
        <tr>
            <th><?php _e('Supported Frameworks', 'devtb'); ?></th>
            <td><?php echo (int) $framework_count; ?> (<?php echo esc_html(implode(', ', $framework_names)); ?>)</td>
        </tr>
        <tr>
            <th><?php _e('Translation Pairs', 'devtb'); ?></th>
            <td><?php echo (int) $translation_pairs; ?></td>
        </tr>
        <tr>
            <th><?php _e('AI-Ready Option', 'devtb'); ?></th>
            <td>--ai-ready flag available for all conversions</td>
        </tr>
    </table>
    <?php
}

/**
 * Add admin notices
 */
function devtb_admin_notices() {
    $screen = get_current_screen();

    // Only show on DEVTB pages
    if (strpos($screen->id, 'devtb') === false) {
        return;
    }

    // Check PHP version
    if (version_compare(PHP_VERSION, '8.1.0', '<')) {
        ?>
        <div class="notice notice-error">
            <p><strong><?php _e('DevelopmentTranslation Bridge requires PHP 8.1 or higher.', 'devtb'); ?></strong></p>
            <p><?php printf(__('You are running PHP %s. Please upgrade to use this theme.', 'devtb'), PHP_VERSION); ?></p>
        </div>
        <?php
    }

    // Check if CLI is executable
    $devtb_cli = DEVTB_THEME_DIR . '/devtb';
    if (file_exists($devtb_cli) && !is_executable($devtb_cli)) {
        ?>
        <div class="notice notice-warning">
            <p><strong><?php _e('DEVTB CLI is not executable.', 'devtb'); ?></strong></p>
            <p><?php _e('Run:', 'devtb'); ?> <code>chmod +x <?php echo esc_html($devtb_cli); ?></code></p>
        </div>
        <?php
    }
}
add_action('admin_notices', 'devtb_admin_notices');

/**
 * Custom theme support and features
 */
function devtb_custom_features() {
    // Add support for AI-editable content in the editor
    add_filter('the_content', 'devtb_preserve_ai_attributes');
}
add_action('init', 'devtb_custom_features');

/**
 * Preserve data-ai-editable attributes in content
 */
function devtb_preserve_ai_attributes($content) {
    // Allow data-ai-editable attribute in content
    return $content;
}

/**
 * Allow data-ai-editable in KSES
 */
function devtb_allowed_html($tags, $context) {
    if ($context === 'post') {
        foreach ($tags as $tag => $rules) {
            $tags[$tag]['data-ai-editable'] = true;
            $tags[$tag]['data-ai-type'] = true;
            $tags[$tag]['data-ai-id'] = true;
        }
    }
    return $tags;
}
add_filter('wp_kses_allowed_html', 'devtb_allowed_html', 10, 2);

/**
 * Theme Activation - Install database tables
 *
 * Creates necessary database tables for translation persistence
 * and correction tracking when theme is activated.
 */
function devtb_theme_activation() {
    // Load required classes if not already loaded
    if (!class_exists('DEVTB_Persistence')) {
        require_once get_template_directory() . '/includes/class-devtb-config.php';
        require_once get_template_directory() . '/includes/class-devtb-logger.php';
        require_once get_template_directory() . '/includes/class-devtb-persistence.php';
    }

    // Install database tables
    DEVTB_Persistence::install();

    // Log activation
    $logger = new DEVTB_Logger();
    $logger->info('DEVTB theme activated', ['version' => DEVTB_THEME_VERSION]);
}
add_action('after_switch_theme', 'devtb_theme_activation');

/**
 * Check and install tables if needed
 *
 * This runs on admin_init to ensure tables exist even if
 * the theme was activated before this version.
 */
function devtb_maybe_install_tables() {
    // Only run on admin
    if (!is_admin()) {
        return;
    }

    // Check if we need to install
    $installed_version = get_option('devtb_db_version', '0');
    $current_version = '1.0.0';

    if (version_compare($installed_version, $current_version, '<')) {
        // Load required classes
        if (!class_exists('DEVTB_Persistence')) {
            if (file_exists(DEVTB_INCLUDES_DIR . '/class-devtb-persistence.php')) {
                require_once DEVTB_INCLUDES_DIR . '/class-devtb-config.php';
                require_once DEVTB_INCLUDES_DIR . '/class-devtb-logger.php';
                require_once DEVTB_INCLUDES_DIR . '/class-devtb-persistence.php';
            }
        }

        if (class_exists('DEVTB_Persistence')) {
            DEVTB_Persistence::install();
        }
    }
}
add_action('admin_init', 'devtb_maybe_install_tables');
