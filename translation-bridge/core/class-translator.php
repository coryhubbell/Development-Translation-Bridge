<?php
/**
 * Advanced Translation Orchestrator
 *
 * Einstein-level translator featuring:
 * - Intelligent workflow orchestration
 * - Performance optimization with caching
 * - Error recovery and fault tolerance
 * - Progress tracking and reporting
 * - Batch processing capabilities
 * - Quality assurance validation
 *
 * @package DevelopmentTranslation_Bridge
 * @subpackage Translation_Bridge
 * @since 3.0.0
 */

namespace DEVTB\TranslationBridge\Core;

use DEVTB\TranslationBridge\Models\DEVTB_Component;

/**
 * Class DEVTB_Translator
 *
 * Main orchestrator for framework translations with AI-like intelligence.
 */
class DEVTB_Translator {

	/**
	 * Mapping engine instance
	 *
	 * @var DEVTB_Mapping_Engine
	 */
	private DEVTB_Mapping_Engine $mapping_engine;

	/**
	 * Translation cache
	 *
	 * @var array<string, mixed>
	 */
	private array $cache = [];

	/**
	 * Translation statistics
	 *
	 * @var array<string, mixed>
	 */
	private array $stats = [
		'total_components'     => 0,
		'successful'           => 0,
		'failed'              => 0,
		'warnings'            => 0,
		'avg_confidence'      => 0.0,
		'processing_time'     => 0.0,
	];

	/**
	 * Error log
	 *
	 * @var array
	 */
	private array $errors = [];

	/**
	 * Warning log
	 *
	 * @var array
	 */
	private array $warnings = [];

	/**
	 * Performance mode (speed vs quality)
	 *
	 * @var string
	 */
	private string $performance_mode = 'balanced'; // 'speed', 'balanced', 'quality'

	/**
	 * Enable caching
	 *
	 * @var bool
	 */
	private bool $enable_cache = true;

	/**
	 * Progress callback
	 *
	 * @var callable|null
	 */
	private $progress_callback;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->mapping_engine = new DEVTB_Mapping_Engine();
	}

	/**
	 * Parse source content into the canonical universal document
	 * (RFC 5.0, Phase 2).
	 *
	 * @param string|array $content          Source content.
	 * @param string       $source_framework Source framework name.
	 * @return array|false Universal document, or false when nothing parses.
	 */
	public function parse_to_universal( $content, string $source_framework ) {
		$components = $this->parse_content( $content, $source_framework );
		if ( empty( $components ) ) {
			return false;
		}
		return DEVTB_Universal::components_to_document( $components );
	}

	/**
	 * Convert a canonical universal document to a target framework
	 * (RFC 5.0, Phase 2).
	 *
	 * @param array  $document         Universal document (or bare element list).
	 * @param string $target_framework Target framework name.
	 * @return string|array|false Converted output, or false on failure.
	 */
	public function translate_universal( array $document, string $target_framework ) {
		$components = DEVTB_Universal::document_to_components( $document );
		if ( empty( $components ) ) {
			return false;
		}
		$validated = $this->validate_components( $components );
		return $this->convert_to_framework( $validated, $target_framework );
	}

	/**
	 * Translate content from source framework to target framework
	 *
	 * Main entry point for all translations with intelligent orchestration.
	 *
	 * @param string|array $content Source content (HTML, JSON, shortcodes, etc.).
	 * @param string       $source_framework Source framework name.
	 * @param string       $target_framework Target framework name.
	 * @param array        $options Translation options.
	 * @return string|array|false Translated content or false on failure.
	 */
	public function translate(
		$content,
		string $source_framework,
		string $target_framework,
		array $options = []
	) {
		// Reset statistics
		$this->reset_stats();
		$start_time = microtime( true );

		// Apply options
		$this->apply_options( $options );

		try {
			// Validate frameworks
			if ( ! $this->validate_frameworks( $source_framework, $target_framework ) ) {
				$this->log_error( 'Invalid framework combination', [
					'source' => $source_framework,
					'target' => $target_framework,
				] );
				return false;
			}

			// Check cache
			if ( $this->enable_cache ) {
				$cached = $this->get_from_cache( $content, $source_framework, $target_framework );
				if ( $cached !== null ) {
					$this->log_stats( 'Cache hit', [ 'time' => microtime( true ) - $start_time ] );
					return $cached;
				}
			}

			// Parse source content to components
			$components = $this->parse_content( $content, $source_framework );

			if ( empty( $components ) ) {
				$this->log_warning( 'No components parsed from content' );
				return false;
			}

			// Map components to target framework
			$mapped_components = $this->map_components(
				$components,
				$source_framework,
				$target_framework
			);

			// Validate mapped components
			$validated_components = $this->validate_components( $mapped_components );

			// Convert to target framework format
			$output = $this->convert_to_framework(
				$validated_components,
				$target_framework
			);

			// Apply AI-ready annotations if requested
			if ( $this->ai_ready && is_string( $output ) ) {
				require_once dirname( __DIR__ ) . '/utils/class-ai-ready-annotator.php';
				$annotator = new \DEVTB\TranslationBridge\Utils\DEVTB_AI_Ready_Annotator();
				$output = $annotator->annotate( $output );
			}

			// Cache result
			if ( $this->enable_cache && $output ) {
				$this->cache_result( $content, $source_framework, $target_framework, $output );
			}

			// Record statistics
			$this->stats['processing_time'] = microtime( true ) - $start_time;
			$this->stats['successful']      = count( $validated_components );

			// Quality assurance check
			$this->perform_qa_check( $components, $validated_components, $source_framework, $target_framework );

			return $output;

		} catch ( \Exception $e ) {
			$this->log_error( 'Translation failed', [
				'message'   => $e->getMessage(),
				'trace'     => $e->getTraceAsString(),
			] );

			$this->stats['processing_time'] = microtime( true ) - $start_time;
			$this->stats['failed']++;

			return false;
		}
	}

	/**
	 * Batch translate multiple pieces of content
	 *
	 * Optimized for processing large volumes with progress tracking.
	 *
	 * @param array  $contents Array of content items.
	 * @param string $source_framework Source framework.
	 * @param string $target_framework Target framework.
	 * @param array  $options Translation options.
	 * @return array Translation results.
	 */
	public function batch_translate(
		array $contents,
		string $source_framework,
		string $target_framework,
		array $options = []
	): array {
		$results = [];
		$total   = count( $contents );
		$current = 0;

		foreach ( $contents as $key => $content ) {
			$current++;

			// Report progress
			if ( $this->progress_callback ) {
				call_user_func( $this->progress_callback, $current, $total, $key );
			}

			// Translate individual item
			$result = $this->translate( $content, $source_framework, $target_framework, $options );

			$results[ $key ] = [
				'success' => $result !== false,
				'output'  => $result,
				'stats'   => $this->get_stats(),
				'errors'  => $this->get_errors(),
				'warnings' => $this->get_warnings(),
			];
		}

		return $results;
	}

	/**
	 * Parse content into components
	 *
	 * @param string|array $content Source content.
	 * @param string       $framework Framework name.
	 * @return DEVTB_Component[] Parsed components.
	 * @throws \Exception If parsing fails.
	 */
	private function parse_content( $content, string $framework ): array {
		try {
			$parser = DEVTB_Parser_Factory::create( $framework );

			if ( ! $parser ) {
				throw new \Exception( sprintf( 'Parser not found for framework: %s', $framework ) );
			}

			// Validate content before parsing
			if ( ! $parser->is_valid_content( $content ) ) {
				throw new \Exception( 'Invalid content format for framework' );
			}

			$components = $parser->parse( $content );

			$this->stats['total_components'] = count( $components );

			return $components;

		} catch ( \Exception $e ) {
			$this->log_error( 'Parsing failed', [
				'framework' => $framework,
				'error'     => $e->getMessage(),
			] );
			throw $e;
		}
	}

	/**
	 * Map components using AI-like mapping engine
	 *
	 * @param DEVTB_Component[] $components Components to map.
	 * @param string           $source_framework Source framework.
	 * @param string           $target_framework Target framework.
	 * @return DEVTB_Component[] Mapped components.
	 */
	private function map_components(
		array $components,
		string $source_framework,
		string $target_framework
	): array {
		$mapped = [];
		$confidence_scores = [];

		foreach ( $components as $component ) {
			try {
				// Use mapping engine for intelligent transformation
				$mapped_component = $this->mapping_engine->map(
					$component,
					$source_framework,
					$target_framework
				);

				$mapped[] = $mapped_component;

				// Track confidence
				$confidence = $mapped_component->get_metadata( 'transformation_confidence' ) ?? 0.5;
				$confidence_scores[] = $confidence;

				// Warn on low confidence
				if ( $confidence < 0.7 ) {
					$this->log_warning( sprintf(
						'Low confidence transformation: %s (%.2f)',
						$component->type,
						$confidence
					) );
				}

			} catch ( \Exception $e ) {
				$this->log_error( 'Component mapping failed', [
					'component' => $component->type,
					'error'     => $e->getMessage(),
				] );

				$this->stats['failed']++;
			}
		}

		// Calculate average confidence
		if ( ! empty( $confidence_scores ) ) {
			$this->stats['avg_confidence'] = array_sum( $confidence_scores ) / count( $confidence_scores );
		}

		return $mapped;
	}

	/**
	 * Validate components after mapping
	 *
	 * @param DEVTB_Component[] $components Components to validate.
	 * @return DEVTB_Component[] Valid components.
	 */
	private function validate_components( array $components ): array {
		$valid = [];

		foreach ( $components as $component ) {
			if ( $component->is_valid() ) {
				$valid[] = $component;
			} else {
				$this->log_warning( sprintf(
					'Invalid component after mapping: %s',
					$component->type
				) );
				$this->stats['failed']++;
			}
		}

		return $valid;
	}

	/**
	 * Convert components to target framework format
	 *
	 * @param DEVTB_Component[] $components Components to convert.
	 * @param string           $framework Target framework.
	 * @return string|array Framework-specific output.
	 * @throws \Exception If conversion fails.
	 */
	private function convert_to_framework( array $components, string $framework ) {
		try {
			$converter = DEVTB_Converter_Factory::create( $framework );

			if ( ! $converter ) {
				throw new \Exception( sprintf( 'Converter not found for framework: %s', $framework ) );
			}

			// Convert all components
			return $converter->convert( $components );

		} catch ( \Exception $e ) {
			$this->log_error( 'Conversion failed', [
				'framework' => $framework,
				'error'     => $e->getMessage(),
			] );
			throw $e;
		}
	}

	/**
	 * Perform quality assurance check
	 *
	 * @param DEVTB_Component[] $source_components Source components.
	 * @param DEVTB_Component[] $mapped_components Mapped components.
	 * @param string           $source_framework Source framework.
	 * @param string           $target_framework Target framework.
	 * @return void
	 */
	private function perform_qa_check(
		array $source_components,
		array $mapped_components,
		string $source_framework,
		string $target_framework
	): void {
		// Check component count consistency
		if ( count( $source_components ) !== count( $mapped_components ) ) {
			$this->log_warning( sprintf(
				'Component count mismatch: %d source, %d mapped',
				count( $source_components ),
				count( $mapped_components )
			) );
		}

		// Check average confidence score
		if ( $this->stats['avg_confidence'] < 0.7 ) {
			$this->log_warning( sprintf(
				'Low average confidence: %.2f',
				$this->stats['avg_confidence']
			) );
		}

		// Check for data loss (empty content in mapped components)
		foreach ( $mapped_components as $index => $component ) {
			$source_has_content = ! empty( $source_components[ $index ]->content );
			$mapped_has_content = ! empty( $component->content );

			if ( $source_has_content && ! $mapped_has_content ) {
				$this->log_warning( sprintf(
					'Potential content loss in component: %s',
					$component->type
				) );
			}
		}
	}

	/**
	 * Validate framework combination
	 *
	 * @param string $source Source framework.
	 * @param string $target Target framework.
	 * @return bool True if valid.
	 */
	private function validate_frameworks( string $source, string $target ): bool {
		// Same framework translation is not supported (no-op)
		if ( strtolower( $source ) === strtolower( $target ) ) {
			$this->log_error( 'Source and target frameworks cannot be the same' );
			return false;
		}

		// Check if frameworks are supported
		if ( ! DEVTB_Parser_Factory::is_supported( $source ) ) {
			$this->log_error( sprintf( 'Unsupported source framework: %s', $source ) );
			return false;
		}

		if ( ! DEVTB_Converter_Factory::is_supported( $target ) ) {
			$this->log_error( sprintf( 'Unsupported target framework: %s', $target ) );
			return false;
		}

		return true;
	}

	/**
	 * AI-ready annotation enabled
	 *
	 * @var bool
	 */
	private bool $ai_ready = false;

	/**
	 * Apply translation options
	 *
	 * @param array $options Options array.
	 * @return void
	 */
	private function apply_options( array $options ): void {
		if ( isset( $options['performance_mode'] ) ) {
			$this->performance_mode = $options['performance_mode'];
		}

		if ( isset( $options['enable_cache'] ) ) {
			$this->enable_cache = (bool) $options['enable_cache'];
		}

		if ( isset( $options['progress_callback'] ) && is_callable( $options['progress_callback'] ) ) {
			$this->progress_callback = $options['progress_callback'];
		}

		if ( isset( $options['ai_ready'] ) ) {
			$this->ai_ready = (bool) $options['ai_ready'];
		}
	}

	/**
	 * Get from cache
	 *
	 * @param mixed  $content Content.
	 * @param string $source Source framework.
	 * @param string $target Target framework.
	 * @return mixed|null Cached result or null.
	 */
	private function get_from_cache( $content, string $source, string $target ): ?string {
		$cache_key = $this->generate_cache_key( $content, $source, $target );
		return $this->cache[ $cache_key ] ?? null;
	}

	/**
	 * Cache translation result
	 *
	 * @param mixed  $content Source content.
	 * @param string $source Source framework.
	 * @param string $target Target framework.
	 * @param mixed  $result Translation result.
	 * @return void
	 */
	private function cache_result( $content, string $source, string $target, $result ): void {
		$cache_key = $this->generate_cache_key( $content, $source, $target );
		$this->cache[ $cache_key ] = $result;

		// Limit cache size (keep last 100 items)
		if ( count( $this->cache ) > 100 ) {
			array_shift( $this->cache );
		}
	}

	/**
	 * Generate cache key
	 *
	 * @param mixed  $content Content.
	 * @param string $source Source framework.
	 * @param string $target Target framework.
	 * @return string Cache key.
	 */
	private function generate_cache_key( $content, string $source, string $target ): string {
		$content_hash = is_string( $content ) ? md5( $content ) : md5( serialize( $content ) );
		return sprintf( '%s:%s:%s', $source, $target, $content_hash );
	}

	/**
	 * Log error
	 *
	 * @param string $message Error message.
	 * @param array  $context Error context.
	 * @return void
	 */
	private function log_error( string $message, array $context = [] ): void {
		$this->errors[] = [
			'message'   => $message,
			'context'   => $context,
			'timestamp' => time(),
		];
	}

	/**
	 * Log warning
	 *
	 * @param string $message Warning message.
	 * @param array  $context Warning context.
	 * @return void
	 */
	private function log_warning( string $message, array $context = [] ): void {
		$this->warnings[] = [
			'message'   => $message,
			'context'   => $context,
			'timestamp' => time(),
		];
		$this->stats['warnings']++;
	}

	/**
	 * Log statistics event
	 *
	 * @param string $event Event name.
	 * @param array  $data Event data.
	 * @return void
	 */
	private function log_stats( string $event, array $data ): void {
		// Can be extended to store detailed analytics
	}

	/**
	 * Reset statistics
	 *
	 * @return void
	 */
	private function reset_stats(): void {
		$this->stats = [
			'total_components' => 0,
			'successful'       => 0,
			'failed'          => 0,
			'warnings'        => 0,
			'avg_confidence'  => 0.0,
			'processing_time' => 0.0,
		];
		$this->errors   = [];
		$this->warnings = [];
	}

	/**
	 * Get translation statistics
	 *
	 * @return array Statistics.
	 */
	public function get_stats(): array {
		return $this->stats;
	}

	/**
	 * Get errors
	 *
	 * @return array Errors.
	 */
	public function get_errors(): array {
		return $this->errors;
	}

	/**
	 * Get warnings
	 *
	 * @return array Warnings.
	 */
	public function get_warnings(): array {
		return $this->warnings;
	}

	/**
	 * Clear cache
	 *
	 * @return void
	 */
	public function clear_cache(): void {
		$this->cache = [];
	}

	/**
	 * Get supported frameworks
	 *
	 * @return array<string> Supported framework names.
	 */
	public static function get_supported_frameworks(): array {
		return DEVTB_Parser_Factory::get_supported_frameworks();
	}

	/**
	 * Check if translation is possible
	 *
	 * @param string $source Source framework.
	 * @param string $target Target framework.
	 * @return bool True if supported.
	 */
	public static function can_translate( string $source, string $target ): bool {
		return DEVTB_Parser_Factory::is_supported( $source )
			&& DEVTB_Converter_Factory::is_supported( $target )
			&& strtolower( $source ) !== strtolower( $target );
	}
}
