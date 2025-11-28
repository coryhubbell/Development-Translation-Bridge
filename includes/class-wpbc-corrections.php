<?php
/**
 * WPBC Corrections Handler
 *
 * Provides rule-based and AI-powered code analysis and correction suggestions.
 *
 * @package    WordPress_Bootstrap_Claude
 * @subpackage Core
 * @version    3.3.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPBC Corrections class
 *
 * Handles code analysis and correction generation.
 */
class WPBC_Corrections {

	/**
	 * Logger instance
	 *
	 * @var WPBC_Logger
	 */
	private WPBC_Logger $logger;

	/**
	 * Claude API instance (optional)
	 *
	 * @var WPBC_Claude_API|null
	 */
	private ?WPBC_Claude_API $claude_api = null;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->logger = new WPBC_Logger();

		// Initialize Claude API if available
		if ( class_exists( 'WPBC_Claude_API' ) ) {
			try {
				$this->claude_api = new WPBC_Claude_API();
			} catch ( Exception $e ) {
				$this->logger->warning( 'Claude API not available', array( 'error' => $e->getMessage() ) );
			}
		}
	}

	/**
	 * Analyze code and return corrections
	 *
	 * @param string $code      Code to analyze.
	 * @param string $framework Framework type.
	 * @param array  $options   Analysis options.
	 * @return array Corrections array with summary.
	 */
	public function analyze( string $code, string $framework, array $options = array() ): array {
		$defaults = array(
			'aiEnabled'          => false,
			'checkAccessibility' => true,
			'checkBestPractices' => true,
			'maxSuggestions'     => 50,
		);

		$options     = wp_parse_args( $options, $defaults );
		$corrections = array();

		// Always run rule-based checks
		$corrections = array_merge( $corrections, $this->validate_html( $code ) );

		if ( $options['checkAccessibility'] ) {
			$corrections = array_merge( $corrections, $this->check_accessibility( $code ) );
		}

		if ( $options['checkBestPractices'] ) {
			$corrections = array_merge( $corrections, $this->check_best_practices( $code, $framework ) );
		}

		// Framework-specific checks
		$corrections = array_merge( $corrections, $this->check_framework_syntax( $code, $framework ) );

		// AI analysis if enabled and available
		if ( $options['aiEnabled'] && $this->is_ai_available() ) {
			$ai_corrections = $this->analyze_with_ai( $code, $framework );
			$corrections    = array_merge( $corrections, $ai_corrections );
		}

		// Deduplicate and limit
		$corrections = $this->deduplicate_corrections( $corrections );
		$corrections = array_slice( $corrections, 0, $options['maxSuggestions'] );

		// Generate summary
		$summary = $this->generate_summary( $corrections );

		return array(
			'corrections' => $corrections,
			'summary'     => $summary,
		);
	}

	/**
	 * Check if AI analysis is available
	 *
	 * @return bool True if AI is available.
	 */
	public function is_ai_available(): bool {
		return null !== $this->claude_api && $this->claude_api->is_api_available();
	}

	/**
	 * Validate HTML structure
	 *
	 * @param string $code HTML code to validate.
	 * @return array Corrections array.
	 */
	public function validate_html( string $code ): array {
		$corrections = array();
		$lines       = explode( "\n", $code );

		// Check for unclosed tags
		$tag_stack = array();
		$self_closing = array( 'br', 'hr', 'img', 'input', 'meta', 'link', 'area', 'base', 'col', 'embed', 'param', 'source', 'track', 'wbr' );

		foreach ( $lines as $line_num => $line ) {
			// Find opening tags
			preg_match_all( '/<([a-z][a-z0-9]*)\b[^>]*(?<!\/)\s*>/i', $line, $opening_matches, PREG_OFFSET_CAPTURE );
			foreach ( $opening_matches[1] as $match ) {
				$tag_name = strtolower( $match[0] );
				if ( ! in_array( $tag_name, $self_closing, true ) ) {
					$tag_stack[] = array(
						'tag'    => $tag_name,
						'line'   => $line_num + 1,
						'column' => $match[1],
					);
				}
			}

			// Find closing tags
			preg_match_all( '/<\/([a-z][a-z0-9]*)\s*>/i', $line, $closing_matches, PREG_OFFSET_CAPTURE );
			foreach ( $closing_matches[1] as $match ) {
				$tag_name = strtolower( $match[0] );
				if ( ! empty( $tag_stack ) && end( $tag_stack )['tag'] === $tag_name ) {
					array_pop( $tag_stack );
				}
			}
		}

		// Report unclosed tags
		foreach ( $tag_stack as $unclosed ) {
			$corrections[] = $this->create_correction(
				'error',
				'high',
				$unclosed['line'],
				$unclosed['column'],
				$unclosed['column'] + strlen( $unclosed['tag'] ) + 2,
				"Unclosed <{$unclosed['tag']}> tag",
				"Add closing </{$unclosed['tag']}> tag",
				false
			);
		}

		// Check for deprecated tags
		$deprecated_tags = array( 'font', 'center', 'marquee', 'blink', 'strike', 'big', 'tt' );
		foreach ( $lines as $line_num => $line ) {
			foreach ( $deprecated_tags as $tag ) {
				if ( preg_match( "/<{$tag}\b/i", $line, $match, PREG_OFFSET_CAPTURE ) ) {
					$corrections[] = $this->create_correction(
						'warning',
						'medium',
						$line_num + 1,
						$match[0][1],
						$match[0][1] + strlen( $tag ) + 1,
						"Deprecated HTML tag <{$tag}>",
						"Replace with modern CSS styling",
						false
					);
				}
			}
		}

		return $corrections;
	}

	/**
	 * Check accessibility issues
	 *
	 * @param string $code HTML code to check.
	 * @return array Corrections array.
	 */
	public function check_accessibility( string $code ): array {
		$corrections = array();
		$lines       = explode( "\n", $code );

		foreach ( $lines as $line_num => $line ) {
			// Check for images without alt text
			if ( preg_match( '/<img\b(?![^>]*\balt=)[^>]*>/i', $line, $match, PREG_OFFSET_CAPTURE ) ) {
				$corrections[] = $this->create_correction(
					'warning',
					'high',
					$line_num + 1,
					$match[0][1],
					$match[0][1] + strlen( $match[0][0] ),
					'Image missing alt attribute',
					'Add alt="" attribute with descriptive text for accessibility',
					false
				);
			}

			// Check for empty alt attributes (might be intentional for decorative images)
			if ( preg_match( '/<img\b[^>]*alt=["\']["\'][^>]*>/i', $line, $match, PREG_OFFSET_CAPTURE ) ) {
				$corrections[] = $this->create_correction(
					'info',
					'low',
					$line_num + 1,
					$match[0][1],
					$match[0][1] + strlen( $match[0][0] ),
					'Image has empty alt attribute',
					'Ensure this is intentional for decorative images, otherwise add descriptive text',
					false
				);
			}

			// Check for form inputs without labels
			if ( preg_match( '/<input\b(?![^>]*\baria-label)[^>]*type=["\'](?:text|email|password|tel|number)["\'][^>]*>/i', $line, $match, PREG_OFFSET_CAPTURE ) ) {
				// Check if there's no associated label
				if ( ! preg_match( '/\bid=["\']([^"\']+)["\']/i', $match[0][0], $id_match ) ) {
					$corrections[] = $this->create_correction(
						'warning',
						'medium',
						$line_num + 1,
						$match[0][1],
						$match[0][1] + strlen( $match[0][0] ),
						'Form input may be missing accessible label',
						'Add aria-label attribute or associate with a <label> element',
						false
					);
				}
			}

			// Check for links with generic text
			if ( preg_match( '/<a\b[^>]*>(?:click here|read more|learn more|here)<\/a>/i', $line, $match, PREG_OFFSET_CAPTURE ) ) {
				$corrections[] = $this->create_correction(
					'info',
					'medium',
					$line_num + 1,
					$match[0][1],
					$match[0][1] + strlen( $match[0][0] ),
					'Link uses generic text that may not be accessible',
					'Use descriptive link text that explains where the link goes',
					false
				);
			}

			// Check for missing lang attribute on html tag
			if ( preg_match( '/<html\b(?![^>]*\blang=)[^>]*>/i', $line, $match, PREG_OFFSET_CAPTURE ) ) {
				$corrections[] = $this->create_correction(
					'warning',
					'medium',
					$line_num + 1,
					$match[0][1],
					$match[0][1] + strlen( $match[0][0] ),
					'HTML tag missing lang attribute',
					'Add lang="en" or appropriate language code for accessibility',
					false
				);
			}
		}

		return $corrections;
	}

	/**
	 * Check best practices
	 *
	 * @param string $code      Code to check.
	 * @param string $framework Framework type.
	 * @return array Corrections array.
	 */
	public function check_best_practices( string $code, string $framework ): array {
		$corrections = array();
		$lines       = explode( "\n", $code );

		foreach ( $lines as $line_num => $line ) {
			// Check for inline styles
			if ( preg_match( '/\bstyle=["\'][^"\']{50,}["\']/i', $line, $match, PREG_OFFSET_CAPTURE ) ) {
				$corrections[] = $this->create_correction(
					'info',
					'low',
					$line_num + 1,
					$match[0][1],
					$match[0][1] + strlen( $match[0][0] ),
					'Long inline style detected',
					'Consider moving styles to a CSS class for better maintainability',
					false
				);
			}

			// Check for non-semantic divs with classes suggesting semantic meaning
			$semantic_classes = array( 'header', 'footer', 'nav', 'navigation', 'sidebar', 'main', 'article', 'section' );
			foreach ( $semantic_classes as $semantic ) {
				if ( preg_match( "/<div\b[^>]*class=[\"'][^\"']*\b{$semantic}\b[^\"']*[\"'][^>]*>/i", $line, $match, PREG_OFFSET_CAPTURE ) ) {
					$corrections[] = $this->create_correction(
						'enhancement',
						'low',
						$line_num + 1,
						$match[0][1],
						$match[0][1] + strlen( $match[0][0] ),
						"Consider using semantic <{$semantic}> element instead of div",
						"Replace <div class=\"{$semantic}\"> with <{$semantic}> for better semantics",
						false
					);
				}
			}

			// Check for heading hierarchy issues
			if ( preg_match( '/<h([1-6])\b/i', $line, $match, PREG_OFFSET_CAPTURE ) ) {
				// This is a simplified check - a full implementation would track heading order
				$level = (int) $match[1][0];
				if ( $level > 2 && ! preg_match( '/<h[12]\b/i', $code ) ) {
					$corrections[] = $this->create_correction(
						'info',
						'low',
						$line_num + 1,
						$match[0][1],
						$match[0][1] + 3,
						"Consider heading hierarchy: h{$level} used without h1 or h2",
						'Ensure proper heading hierarchy for accessibility and SEO',
						false
					);
				}
			}
		}

		return $corrections;
	}

	/**
	 * Check framework-specific syntax
	 *
	 * @param string $code      Code to check.
	 * @param string $framework Framework type.
	 * @return array Corrections array.
	 */
	public function check_framework_syntax( string $code, string $framework ): array {
		$corrections = array();

		switch ( $framework ) {
			case 'bootstrap':
				$corrections = $this->check_bootstrap_syntax( $code );
				break;
			case 'elementor':
				$corrections = $this->check_elementor_syntax( $code );
				break;
			case 'gutenberg':
				$corrections = $this->check_gutenberg_syntax( $code );
				break;
			// Add more frameworks as needed
		}

		return $corrections;
	}

	/**
	 * Check Bootstrap-specific issues
	 *
	 * @param string $code Bootstrap HTML code.
	 * @return array Corrections array.
	 */
	private function check_bootstrap_syntax( string $code ): array {
		$corrections = array();
		$lines       = explode( "\n", $code );

		foreach ( $lines as $line_num => $line ) {
			// Check for row without container
			if ( preg_match( '/class=["\'][^"\']*\brow\b[^"\']*["\']/', $line ) ) {
				// This is a simplified check
				if ( ! preg_match( '/class=["\'][^"\']*\bcontainer\b/', $code ) ) {
					// Don't add this as it's too noisy - just an example
				}
			}

			// Check for deprecated Bootstrap 4 classes in Bootstrap 5 context
			$deprecated_classes = array(
				'btn-default'  => 'btn-secondary',
				'hidden-xs'    => 'd-none d-sm-block',
				'visible-xs'   => 'd-block d-sm-none',
				'pull-left'    => 'float-start',
				'pull-right'   => 'float-end',
				'text-justify' => 'removed in BS5',
			);

			foreach ( $deprecated_classes as $old => $new ) {
				if ( preg_match( "/class=[\"'][^\"']*\b{$old}\b[^\"']*[\"']/", $line, $match, PREG_OFFSET_CAPTURE ) ) {
					$corrections[] = $this->create_correction(
						'warning',
						'medium',
						$line_num + 1,
						$match[0][1],
						$match[0][1] + strlen( $match[0][0] ),
						"Deprecated Bootstrap class: {$old}",
						"Replace with: {$new}",
						false
					);
				}
			}
		}

		return $corrections;
	}

	/**
	 * Check Elementor-specific issues
	 *
	 * @param string $code Elementor JSON code.
	 * @return array Corrections array.
	 */
	private function check_elementor_syntax( string $code ): array {
		$corrections = array();

		// Check if it's valid JSON
		$decoded = json_decode( $code );
		if ( null === $decoded && json_last_error() !== JSON_ERROR_NONE ) {
			$corrections[] = $this->create_correction(
				'error',
				'critical',
				1,
				0,
				50,
				'Invalid JSON: ' . json_last_error_msg(),
				'Check JSON syntax for errors',
				false
			);
		}

		return $corrections;
	}

	/**
	 * Check Gutenberg-specific issues
	 *
	 * @param string $code Gutenberg HTML code.
	 * @return array Corrections array.
	 */
	private function check_gutenberg_syntax( string $code ): array {
		$corrections = array();
		$lines       = explode( "\n", $code );

		foreach ( $lines as $line_num => $line ) {
			// Check for malformed block comments
			if ( preg_match( '/<!--\s*wp:/', $line ) && ! preg_match( '/<!--\s*wp:[a-z][a-z0-9-]*\/[a-z][a-z0-9-]*\s|<!--\s*wp:[a-z][a-z0-9-]*\s/', $line ) ) {
				$corrections[] = $this->create_correction(
					'warning',
					'medium',
					$line_num + 1,
					0,
					strlen( $line ),
					'Potentially malformed Gutenberg block comment',
					'Ensure block comment follows format: <!-- wp:namespace/block-name -->',
					false
				);
			}
		}

		return $corrections;
	}

	/**
	 * Analyze code with Claude AI
	 *
	 * @param string $code      Code to analyze.
	 * @param string $framework Framework type.
	 * @return array AI-generated corrections.
	 */
	private function analyze_with_ai( string $code, string $framework ): array {
		if ( ! $this->is_ai_available() ) {
			return array();
		}

		try {
			$corrections = $this->claude_api->get_corrections( $code, $framework );

			// Mark all as AI-generated
			foreach ( $corrections as &$correction ) {
				$correction['aiGenerated'] = true;
			}

			return $corrections;
		} catch ( Exception $e ) {
			$this->logger->error( 'AI analysis failed', array( 'error' => $e->getMessage() ) );
			return array();
		}
	}

	/**
	 * Create a correction object
	 *
	 * @param string $type        Correction type.
	 * @param string $severity    Severity level.
	 * @param int    $line        Line number.
	 * @param int    $column      Start column.
	 * @param int    $end_column  End column.
	 * @param string $message     Error message.
	 * @param string $suggestion  Suggested fix.
	 * @param bool   $ai_generated Whether AI generated.
	 * @param array  $auto_fix    Optional auto-fix data.
	 * @return array Correction object.
	 */
	private function create_correction(
		string $type,
		string $severity,
		int $line,
		int $column,
		int $end_column,
		string $message,
		string $suggestion,
		bool $ai_generated,
		array $auto_fix = null
	): array {
		$correction = array(
			'id'          => 'corr_' . wp_generate_uuid4(),
			'type'        => $type,
			'severity'    => $severity,
			'line'        => $line,
			'column'      => $column,
			'endLine'     => $line,
			'endColumn'   => $end_column,
			'message'     => $message,
			'suggestion'  => $suggestion,
			'aiGenerated' => $ai_generated,
			'confidence'  => $ai_generated ? 85 : 100,
		);

		if ( $auto_fix ) {
			$correction['autoFix'] = $auto_fix;
		}

		return $correction;
	}

	/**
	 * Deduplicate corrections
	 *
	 * @param array $corrections Corrections array.
	 * @return array Deduplicated corrections.
	 */
	private function deduplicate_corrections( array $corrections ): array {
		$seen = array();
		$result = array();

		foreach ( $corrections as $correction ) {
			$key = $correction['line'] . ':' . $correction['column'] . ':' . $correction['message'];
			if ( ! isset( $seen[ $key ] ) ) {
				$seen[ $key ] = true;
				$result[]     = $correction;
			}
		}

		return $result;
	}

	/**
	 * Generate correction summary
	 *
	 * @param array $corrections Corrections array.
	 * @return array Summary with counts.
	 */
	private function generate_summary( array $corrections ): array {
		$summary = array(
			'total'        => count( $corrections ),
			'errors'       => 0,
			'warnings'     => 0,
			'info'         => 0,
			'enhancements' => 0,
		);

		foreach ( $corrections as $correction ) {
			switch ( $correction['type'] ) {
				case 'error':
					$summary['errors']++;
					break;
				case 'warning':
					$summary['warnings']++;
					break;
				case 'info':
					$summary['info']++;
					break;
				case 'enhancement':
					$summary['enhancements']++;
					break;
			}
		}

		return $summary;
	}
}
