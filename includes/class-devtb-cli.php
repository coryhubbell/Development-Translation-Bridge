<?php
/**
 * DEVTB CLI Command Handler
 *
 * Routes and executes CLI commands for the Translation Bridge
 *
 * @package    DevelopmentTranslation_Bridge
 * @subpackage CLI
 * @version    5.0.0
 */

class DEVTB_CLI {

	/**
	 * Command line arguments
	 *
	 * @var array
	 */
	private array $args;

	/**
	 * Parsed command
	 *
	 * @var string
	 */
	private string $command = 'help';

	/**
	 * Command options
	 *
	 * @var array
	 */
	private array $options = array();

	/**
	 * Command parameters
	 *
	 * @var array
	 */
	private array $params = array();

	/**
	 * Supported frameworks (slug => display label).
	 *
	 * Populated in the constructor from DEVTB_Converter_Factory so the CLI
	 * stays in lockstep with the REST API and admin UI.
	 *
	 * @var array<string,string>
	 */
	private array $frameworks = array();

	/**
	 * Logger instance
	 *
	 * @var DEVTB_Logger
	 */
	private DEVTB_Logger $logger;

	/**
	 * File handler instance
	 *
	 * @var DEVTB_File_Handler
	 */
	private DEVTB_File_Handler $file_handler;

	/**
	 * Constructor
	 *
	 * @param array $args Command line arguments.
	 */
	public function __construct( array $args ) {
		$this->args         = $args;
		$this->logger       = new DEVTB_Logger();
		$this->file_handler = new DEVTB_File_Handler();
		$this->frameworks   = self::build_framework_labels();
		$this->parse_arguments();
	}

	/**
	 * Build the slug => display label map from the converter factory.
	 *
	 * @return array<string,string>
	 */
	private static function build_framework_labels(): array {
		$labels = array();
		foreach ( \DEVTB\TranslationBridge\Core\DEVTB_Converter_Factory::get_framework_info() as $slug => $meta ) {
			$labels[ $slug ] = trim( $meta['name'] . ' ' . $meta['cms_version'] );
		}
		return $labels;
	}

	/**
	 * Validate that a framework is supported
	 *
	 * @param string $framework   Framework identifier.
	 * @param string $param_name  Parameter name for error message.
	 * @return bool True if valid, false and outputs error if not.
	 */
	private function validate_framework( string $framework, string $param_name = 'framework' ): bool {
		if ( isset( $this->frameworks[ $framework ] ) ) {
			return true;
		}

		$this->error( "Unknown {$param_name} framework: {$framework}" );
		$this->list_frameworks();
		return false;
	}

	/**
	 * Validate that an input file exists
	 *
	 * @param string $file_path Path to the input file.
	 * @return bool True if exists, false and outputs error if not.
	 */
	private function validate_input_file( string $file_path ): bool {
		if ( file_exists( $file_path ) ) {
			return true;
		}

		$this->error( "Input file not found: {$file_path}" );
		return false;
	}

	/**
	 * Get the current command name
	 *
	 * @return string The current command.
	 */
	public function get_command(): string {
		return $this->command;
	}

	/**
	 * Get parsed parameters
	 *
	 * @return array The parameters array.
	 */
	public function get_params(): array {
		return $this->params;
	}

	/**
	 * Get parsed options
	 *
	 * @return array The options array.
	 */
	public function get_options(): array {
		return $this->options;
	}

	/**
	 * Parse command line arguments
	 *
	 * @return void
	 */
	private function parse_arguments(): void {
		$positional = array();
		$i          = 0;
		$count      = count( $this->args );

		while ( $i < $count ) {
			$arg = $this->args[ $i ];

			// Check if it's an option.
			if ( 0 === strpos( $arg, '--' ) ) {
				// Long option (--option or --option=value).
				if ( false !== strpos( $arg, '=' ) ) {
					list( $key, $value )   = explode( '=', substr( $arg, 2 ), 2 );
					$this->options[ $key ] = $value;
				} else {
					$key = substr( $arg, 2 );
					// Boolean flags never consume the next argument as a value;
					// they're declared in self::BOOLEAN_FLAGS / BOOLEAN_SHORT_FLAGS.
					if (
						! in_array( $key, self::BOOLEAN_FLAGS, true )
						&& $i + 1 < $count
						&& 0 !== strpos( $this->args[ $i + 1 ], '-' )
					) {
						$this->options[ $key ] = $this->args[ $i + 1 ];
						$i++;
					} else {
						$this->options[ $key ] = true;
					}
				}
			} elseif ( 0 === strpos( $arg, '-' ) && 2 === strlen( $arg ) ) {
				// Short option (-o or -o value).
				// NB: short flags stay greedy to preserve `-d <dir>` (output-dir) usage;
				// disambiguating short flags requires per-command schemas and is out of scope here.
				$key = substr( $arg, 1 );
				if ( $i + 1 < $count && 0 !== strpos( $this->args[ $i + 1 ], '-' ) ) {
					$this->options[ $key ] = $this->args[ $i + 1 ];
					$i++;
				} else {
					$this->options[ $key ] = true;
				}
			} else {
				// Positional argument.
				$positional[] = $arg;
			}

			$i++;
		}

		// First positional is the command.
		$this->command = ! empty( $positional ) ? array_shift( $positional ) : 'help';
		$this->params  = $positional;
	}

	/**
	 * Long flags that never take a value (must be true/false only).
	 *
	 * Without this list the parser would greedily consume the next positional
	 * argument as the flag's value (e.g. `--dry-run divi` becoming
	 * `dry-run=divi`), breaking the mixed positional+options case.
	 */
	private const BOOLEAN_FLAGS = array(
		'dry-run',
		'debug',
		'verbose',
		'ai-ready',
		'force',
		'help',
		'version',
		'quiet',
		'no-color',
		'json-output',
	);

	/**
	 * Execute the CLI command
	 *
	 * @return int Exit code (0 = success, non-zero = error).
	 */
	public function execute(): int {
		// Handle global options first.
		if ( $this->has_option( 'version', 'v' ) ) {
			return $this->show_version();
		}

		if ( $this->has_option( 'help', 'h' ) || 'help' === $this->command ) {
			return $this->show_help();
		}

		// Route to command handler.
		$method = 'command_' . str_replace( '-', '_', $this->command );

		if ( method_exists( $this, $method ) ) {
			return $this->$method();
		}

		$this->error( "Unknown command: {$this->command}" );
		$this->info( "Run 'devtb help' to see available commands." );
		return 1;
	}

	/**
	 * Removed in 5.1: 'translate' and 'translate-all' (deprecated since
	 * 4.14.0). Use 'devtb transform' / 'devtb transform-all'.
	 *
	 * @return int Exit code.
	 */
	private function command_translate(): int {
		$this->error( "'translate' was removed in 5.1 — use 'devtb transform'." );
		return 1;
	}

	private function command_translate_all(): int {
		$this->error( "'translate-all' was removed in 5.1 — use 'devtb transform-all'." );
		return 1;
	}

    /**
     * Command: list-frameworks
     *
     * List all supported frameworks
     */
    private function command_list_frameworks()
    {
        $count = count($this->frameworks);
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("  Supported Frameworks ({$count} Total)");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        echo PHP_EOL;

        foreach ($this->frameworks as $key => $name) {
            $this->info("  {$key}");
            $this->dim("    {$name}");
        }

        echo PHP_EOL;
        $pairs = $count * ($count - 1);
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("Translation Pairs: {$pairs} (any framework to any other)");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        echo PHP_EOL;

        return 0;
    }

	/**
	 * Command: validate
	 *
	 * Validate a framework file.
	 *
	 * @return int Exit code.
	 */
	private function command_validate(): int {
		if ( count( $this->params ) < 2 ) {
			$this->error( "Insufficient arguments for 'validate' command" );
			$this->info( 'Usage: devtb validate <framework> <input-file>' );
			$this->info( 'Example: devtb validate bootstrap hero.html' );
			return 1;
		}

		$framework  = strtolower( $this->params[0] );
		$input_file = $this->params[1];

		// Validate framework using helper.
		if ( ! $this->validate_framework( $framework ) ) {
			return 1;
		}

		// Validate input file using helper.
		if ( ! $this->validate_input_file( $input_file ) ) {
			return 1;
		}

        $this->info("🔍 Validating {$this->frameworks[$framework]} file...");
        $this->info("File: {$input_file}");
        echo PHP_EOL;

        try {
            // Read and parse
            $input_content = $this->file_handler->read_file($input_file, $framework);

            require_once DEVTB_TRANSLATION_BRIDGE . '/core/class-parser-factory.php';
            $parser = \DEVTB\TranslationBridge\Core\DEVTB_Parser_Factory::create($framework);
            $components = $parser->parse($input_content);

            if (empty($components)) {
                $this->warning("⚠️  No components found in file");
                return 1;
            }

            $this->success("✓ File is valid");
            $this->info("Components found: " . count($components));

            // Show component breakdown
            if ($this->has_option('verbose', 'v')) {
                echo PHP_EOL;
                $this->info("Component Breakdown:");
                $types = [];
                foreach ($components as $component) {
                    $type = $component->type ?? 'unknown';
                    $types[$type] = ($types[$type] ?? 0) + 1;
                }
                foreach ($types as $type => $count) {
                    $this->info("  {$type}: {$count}");
                }
            }

            echo PHP_EOL;
            return 0;

        } catch (Exception $e) {
            $this->error("✗ Validation failed: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Show version information
     */
    private function show_version()
    {
        $framework_count = count($this->frameworks);
        $translation_pairs = $framework_count * ($framework_count - 1);

        echo $this->bold("DEVTB - DevelopmentTranslation Bridge") . PHP_EOL;
        echo "Version: " . DEVTB_VERSION . PHP_EOL;
        echo "Translation Bridge™ - Universal Framework Translator" . PHP_EOL;
        echo PHP_EOL;
        echo "Supported Frameworks: {$framework_count}" . PHP_EOL;
        echo "Translation Pairs: {$translation_pairs}" . PHP_EOL;
        echo PHP_EOL;
        return 0;
    }

    /**
     * Show help information
     */
    private function show_help()
    {
        $command = !empty($this->params) ? $this->params[0] : null;

        if ($command) {
            return $this->show_command_help($command);
        }

        echo $this->bold("DEVTB - DevelopmentTranslation Bridge CLI") . PHP_EOL;
        echo "Translation Bridge™ - Universal Framework Translator" . PHP_EOL;
        echo PHP_EOL;
        echo $this->bold("USAGE:") . PHP_EOL;
        echo "  devtb <command> [arguments] [options]" . PHP_EOL;
        echo PHP_EOL;
        echo $this->bold("COMMANDS:") . PHP_EOL;
        echo "  " . $this->bold("list-frameworks") . PHP_EOL;
        echo "    List all supported frameworks" . PHP_EOL;
        echo PHP_EOL;
        echo "  " . $this->bold("validate") . " <framework> <file>" . PHP_EOL;
        echo "    Validate a framework file" . PHP_EOL;
        echo PHP_EOL;
        echo "  " . $this->bold("help") . " [command]" . PHP_EOL;
        echo "    Show help for a specific command" . PHP_EOL;
        echo PHP_EOL;
        echo $this->bold("GLOBAL OPTIONS:") . PHP_EOL;
        echo "  -h, --help       Show help information" . PHP_EOL;
        echo "  -v, --version    Show version information" . PHP_EOL;
        echo "  -d, --debug      Enable debug mode" . PHP_EOL;
        echo "  -q, --quiet      Suppress non-error output" . PHP_EOL;
        echo "  -a, --ai-ready   Add AI-friendly attributes to output" . PHP_EOL;
        echo PHP_EOL;
        echo $this->bold("EXAMPLES:") . PHP_EOL;
        echo "  # Validate a file" . PHP_EOL;
        echo "  devtb validate bootstrap hero.html" . PHP_EOL;
        echo PHP_EOL;
        echo "Conversions: use 'devtb transform' (Python engine)." . PHP_EOL;
        echo "For more information: devtb help <command>" . PHP_EOL;
        echo PHP_EOL;

        return 0;
    }

    /**
     * Show help for a specific command
     *
     * @param string $command Command name
     */
    private function show_command_help($command)
    {
        // Command-specific help would go here
        $this->info("Help for command: {$command}");
        $this->info("(Detailed help coming soon)");
        return 0;
    }

    /**
     * List available frameworks
     */
    private function list_frameworks()
    {
        $this->info("Available frameworks:");
        foreach ($this->frameworks as $key => $name) {
            $this->info("  - {$key} ({$name})");
        }
    }

    /**
     * Check if an option exists
     *
     * @param string $long  Long option name
     * @param string $short Short option name
     * @return bool
     */
    private function has_option($long, $short = null)
    {
        return isset($this->options[$long]) || ($short && isset($this->options[$short]));
    }

    /**
     * Get option value
     *
     * @param string $long  Long option name
     * @param string $short Short option name
     * @return mixed Option value or null
     */
    private function get_option($long, $short = null)
    {
        if (isset($this->options[$long])) {
            return $this->options[$long];
        }
        if ($short && isset($this->options[$short])) {
            return $this->options[$short];
        }
        return null;
    }

    /**
     * Format bytes to human readable
     *
     * @param int $bytes Bytes
     * @return string Formatted string
     */
    private function format_bytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    // Output formatting methods

    private function success($message)
    {
        if (!$this->has_option('quiet', 'q')) {
            echo "\033[32m{$message}\033[0m" . PHP_EOL;
        }
    }

    private function error($message)
    {
        fwrite(STDERR, "\033[31m{$message}\033[0m" . PHP_EOL);
    }

    private function warning($message)
    {
        if (!$this->has_option('quiet', 'q')) {
            echo "\033[33m{$message}\033[0m" . PHP_EOL;
        }
    }

    private function info($message)
    {
        if (!$this->has_option('quiet', 'q')) {
            echo $message . PHP_EOL;
        }
    }

    private function dim($message)
    {
        if (!$this->has_option('quiet', 'q')) {
            return "\033[2m{$message}\033[0m";
        }
        return $message;
    }

    private function bold($message)
    {
        return "\033[1m{$message}\033[0m";
    }
}
