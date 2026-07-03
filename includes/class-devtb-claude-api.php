<?php
/**
 * DEVTB Claude AI API Integration
 *
 * Dual-mode support for Claude AI:
 * - Mode A: Claude Code CLI (current workflow, generates AI-optimized HTML)
 * - Mode B: Claude API (future web interface, direct AI interaction)
 *
 * @package    DevelopmentTranslation_Bridge
 * @subpackage Claude_Integration
 * @version    4.5.0
 */

class DEVTB_Claude_API {
    /**
     * Claude API endpoint
     *
     * @var string
     */
    private $api_endpoint = 'https://api.anthropic.com/v1/messages';

    /**
     * API key
     *
     * @var string
     */
    private $api_key;

    /**
     * API model
     *
     * @var string
     */
    private $model = 'claude-sonnet-4-5-20250929';

    /**
     * API mode (cli or api)
     *
     * @var string
     */
    private $mode = 'cli';

    /**
     * Logger instance
     *
     * @var DEVTB_Logger
     */
    private $logger;

    /**
     * Constructor
     *
     * @param array $config Configuration array
     */
    public function __construct($config = []) {
        $this->api_key = $config['api_key'] ?? getenv('CLAUDE_API_KEY') ?? null;
        $this->mode = $config['mode'] ?? 'cli';
        $this->logger = new DEVTB_Logger();

        if (!empty($config['model'])) {
            $this->model = $config['model'];
        }

        // Determine mode if not explicitly set
        if ($this->mode === 'auto') {
            $this->mode = $this->api_key ? 'api' : 'cli';
        }
    }

    /**
     * Generate Claude-optimized HTML from any framework
     *
     * This is the primary method for Mode A (Claude Code CLI workflow)
     *
     * @param string $content        Framework content
     * @param string $source_framework Source framework
     * @param array  $options        Additional options
     * @return string Claude-optimized HTML
     */
    public function generate_claude_html($content, $source_framework, $options = []) {
        // Use Translation Bridge to convert to Claude format
        require_once DEVTB_TRANSLATION_BRIDGE . '/core/class-translator.php';

        $translator = new Translator();
        $claude_html = $translator->translate($content, $source_framework, 'claude');

        // Add CLI instructions if in CLI mode
        if ($this->mode === 'cli' && !isset($options['no_instructions'])) {
            $claude_html = $this->add_cli_instructions($claude_html, $source_framework);
        }

        $this->logger->info("Generated Claude-optimized HTML", [
            'source'    => $source_framework,
            'mode'      => $this->mode,
            'size'      => strlen($claude_html),
        ]);

        return $claude_html;
    }

    /**
     * Add CLI instructions to Claude HTML
     *
     * Embeds helpful instructions for Claude Code CLI users
     *
     * @param string $html HTML content
     * @param string $source_framework Original framework
     * @return string HTML with instructions
     */
    private function add_cli_instructions($html, $source_framework) {
        $instructions = <<<'EOT'
<!--
╔════════════════════════════════════════════════════════════════════════════╗
║                     CLAUDE CODE CLI EDITING INSTRUCTIONS                    ║
╚════════════════════════════════════════════════════════════════════════════╝

🤖 Welcome! This HTML is optimized for editing with Claude Code CLI.

📍 EDITABLE ELEMENTS
All components with the data-claude-editable attribute can be modified:
- data-claude-editable="heading"    → Headings and titles
- data-claude-editable="text"       → Text content and paragraphs
- data-claude-editable="button"     → Buttons and CTAs
- data-claude-editable="image"      → Images and media
- data-claude-editable="container"  → Layout containers
- data-claude-editable="form"       → Forms and inputs

💬 NATURAL LANGUAGE COMMANDS
You can use conversational language to make changes:

Examples:
"Change the hero heading to 'Welcome to Our Platform'"
"Make the CTA button blue with rounded corners"
"Add a newsletter signup form after the hero section"
"Update the image to use a different source"
"Make the text larger and bold"

🔄 CONVERTING BACK
When you're done editing, convert back to the original framework:
devtb translate claude %SOURCE_FRAMEWORK% output.html

📚 MORE EXAMPLES
"Add padding to the container"
"Change font family to Arial"
"Make the section full-width"
"Add a shadow effect to the card"
"Update the link color to match the brand"

✨ BEST PRACTICES
1. Be specific about which element you want to change
2. Use element IDs or classes when referencing specific components
3. Test changes incrementally
4. Keep track of your modifications
5. Validate the output after converting back

Happy editing!
╚════════════════════════════════════════════════════════════════════════════╝
-->


EOT;

        $instructions = str_replace('%SOURCE_FRAMEWORK%', $source_framework, $instructions);

        // Insert instructions at the beginning of the HTML
        return $instructions . $html;
    }

    /**
     * Edit Claude HTML using natural language (Mode B - API)
     *
     * Uses Claude API to make AI-powered edits
     *
     * @param string $html    Claude HTML content
     * @param string $prompt  Natural language edit instruction
     * @param array  $options Additional options
     * @return string|false Modified HTML or false on failure
     */
    public function edit_with_ai($html, $prompt, $options = []) {
        if ($this->mode === 'cli') {
            throw new Exception("AI editing requires API mode. Set CLAUDE_API_KEY or use Claude Code CLI.");
        }

        if (empty($this->api_key)) {
            throw new Exception("Claude API key not configured.");
        }

        $system_prompt = $this->build_system_prompt();
        $user_message = $this->build_edit_message($html, $prompt);

        try {
            $response = $this->call_api($system_prompt, $user_message, $options);

            if ($response && isset($response['content'][0]['text'])) {
                $modified_html = $this->extract_html($response['content'][0]['text']);

                $this->logger->info("AI edit completed", [
                    'prompt'        => substr($prompt, 0, 100),
                    'original_size' => strlen($html),
                    'modified_size' => strlen($modified_html),
                ]);

                return $modified_html;
            }

            return false;

        } catch (Exception $e) {
            $this->logger->error("AI edit failed", [
                'error'  => $e->getMessage(),
                'prompt' => substr($prompt, 0, 100),
            ]);
            return false;
        }
    }

    /**
     * Build system prompt for Claude API
     *
     * @return string System prompt
     */
    private function build_system_prompt() {
        return <<<'EOT'
You are an expert web developer helping to edit Claude AI-Optimized HTML.

Your task is to modify HTML according to user instructions while:
1. Preserving all data-claude-editable attributes
2. Maintaining semantic structure
3. Following accessibility best practices
4. Keeping the code clean and well-formatted
5. Only modifying what was requested

When you make changes:
- Keep all existing data-claude-editable attributes
- Maintain proper HTML structure
- Use semantic HTML5 elements
- Ensure accessibility (ARIA labels, alt text, etc.)
- Follow modern CSS best practices

Always return the complete, modified HTML without explanations or code fences.
EOT;
    }

    /**
     * Build edit message for Claude API
     *
     * @param string $html   HTML content
     * @param string $prompt Edit instruction
     * @return string User message
     */
    private function build_edit_message($html, $prompt) {
        return "Here is the HTML to edit:\n\n{$html}\n\nEdit instruction: {$prompt}\n\nReturn only the modified HTML.";
    }

    /**
     * Call Claude API
     *
     * @param string $system  System prompt
     * @param string $message User message
     * @param array  $options API options
     * @return array|false API response or false on failure
     */
    private function call_api($system, $message, $options = []) {
        $headers = [
            'Content-Type: application/json',
            'x-api-key: ' . $this->api_key,
            'anthropic-version: 2023-06-01',
        ];

        $data = [
            'model'      => $this->model,
            'max_tokens' => $options['max_tokens'] ?? 4096,
            'system'     => $system,
            'messages'   => [
                [
                    'role'    => 'user',
                    'content' => $message,
                ],
            ],
        ];

        if (isset($options['temperature'])) {
            $data['temperature'] = $options['temperature'];
        }

        $ch = curl_init($this->api_endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, DEVTB_Config::API_TIMEOUT);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error) {
            throw new Exception("cURL error: {$error}");
        }

        if ($http_code !== 200) {
            throw new Exception("API error (HTTP {$http_code}): {$response}");
        }

        return json_decode($response, true);
    }

    /**
     * Extract HTML from API response
     *
     * @param string $text API response text
     * @return string Extracted HTML
     */
    private function extract_html($text) {
        // Remove markdown code fences if present
        $text = preg_replace('/^```html\s*/m', '', $text);
        $text = preg_replace('/^```\s*/m', '', $text);

        return trim($text);
    }

    /**
     * Convert Claude HTML back to original framework
     *
     * @param string $claude_html      Claude HTML content
     * @param string $target_framework Target framework
     * @return string Converted content
     */
    public function convert_from_claude($claude_html, $target_framework) {
        require_once DEVTB_TRANSLATION_BRIDGE . '/core/class-translator.php';

        $translator = new Translator();
        $result = $translator->translate($claude_html, 'claude', $target_framework);

        $this->logger->info("Converted from Claude HTML", [
            'target' => $target_framework,
            'size'   => strlen($result),
        ]);

        return $result;
    }

    /**
     * Get current mode
     *
     * @return string 'cli' or 'api'
     */
    public function get_mode() {
        return $this->mode;
    }

    /**
     * Check if API mode is available
     *
     * @return bool True if API can be used
     */
    public function is_api_available() {
        return !empty($this->api_key);
    }

    /**
     * Get mode description
     *
     * @return string Mode description
     */
    public function get_mode_description() {
        if ($this->mode === 'cli') {
            return "Claude Code CLI Mode: Generate AI-optimized HTML for terminal editing";
        } else {
            return "Claude API Mode: Direct AI editing via API (requires API key)";
        }
    }

    /**
     * Validate Claude HTML structure
     *
     * Ensures HTML has proper data-claude-editable attributes
     *
     * @param string $html HTML content
     * @return array Validation results
     */
    public function validate_claude_html($html) {
        $results = [
            'valid'           => false,
            'editable_count'  => 0,
            'component_types' => [],
            'warnings'        => [],
        ];

        // Count data-claude-editable attributes
        preg_match_all('/data-claude-editable="([^"]+)"/', $html, $matches);

        if (!empty($matches[1])) {
            $results['valid'] = true;
            $results['editable_count'] = count($matches[1]);
            $results['component_types'] = array_unique($matches[1]);
        } else {
            $results['warnings'][] = "No data-claude-editable attributes found";
        }

        // Check for documentation comments
        if (strpos($html, 'CLAUDE AI-OPTIMIZED') === false) {
            $results['warnings'][] = "Missing Claude AI documentation header";
        }

        return $results;
    }

    /**
     * Get suggested edits for a component
     *
     * Analyzes HTML and suggests possible improvements
     *
     * @param string $html HTML content
     * @return array Suggestions
     */
    public function get_suggestions($html) {
        $suggestions = [];

        // Check accessibility
        if (strpos($html, 'alt=') === false && strpos($html, '<img') !== false) {
            $suggestions[] = "Add alt attributes to images for accessibility";
        }

        if (strpos($html, 'aria-label') === false && strpos($html, '<button') !== false) {
            $suggestions[] = "Consider adding ARIA labels to buttons";
        }

        // Check semantic HTML
        if (strpos($html, '<div class="header') !== false && strpos($html, '<header') === false) {
            $suggestions[] = "Consider using semantic <header> element instead of div.header";
        }

        // Check for inline styles
        if (preg_match('/style="[^"]*"/', $html)) {
            $suggestions[] = "Consider moving inline styles to CSS classes";
        }

        return $suggestions;
    }
}
