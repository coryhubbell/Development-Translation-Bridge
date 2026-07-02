<?php
/**
 * DEVTB File Handler
 *
 * Handles file I/O operations for various framework formats
 *
 * @package    DevelopmentTranslation_Bridge
 * @subpackage CLI
 * @version    4.3.4
 */

class DEVTB_File_Handler {

	/**
	 * Framework file extensions (canonical).
	 *
	 * @var array<string,string>
	 */
	private array $extensions = array(
		'bootstrap'      => 'html',
		'divi'           => 'txt',  // Shortcodes in text files.
		'divi-5'         => 'html', // Block markup.
		'elementor'      => 'json',
		'elementor-4'    => 'json',
		'avada'          => 'html',
		'bricks'         => 'json',
		'wpbakery'       => 'txt',  // Shortcodes.
		'beaver-builder' => 'txt',  // Serialized PHP.
		'gutenberg'      => 'html', // HTML comments.
		'oxygen'         => 'json',
		'oxygen-6'       => 'json',
		'kadence'        => 'html', // Block markup.
		'thrive'         => 'html',
	);

	/**
	 * Read a file with framework-specific handling
	 *
	 * @param string $file_path Path to input file.
	 * @param string $framework Framework name.
	 * @return string|array File contents.
	 * @throws Exception If file cannot be read.
	 */
	public function read_file( string $file_path, string $framework ) {
		if ( ! file_exists( $file_path ) ) {
			throw new Exception( "File not found: {$file_path}" );
		}

		if ( ! is_readable( $file_path ) ) {
			throw new Exception( "File is not readable: {$file_path}" );
		}

		$content = file_get_contents( $file_path );

		if ( false === $content ) {
			throw new Exception( "Failed to read file: {$file_path}" );
		}

		// Framework-specific parsing.
		switch ( $framework ) {
			case 'elementor':
			case 'bricks':
			case 'oxygen':
				// JSON-based frameworks.
				$decoded = json_decode( $content, true );
				if ( JSON_ERROR_NONE !== json_last_error() ) {
					throw new Exception( 'Invalid JSON in file: ' . json_last_error_msg() );
				}
				return $decoded;

			default:
				// Text/HTML-based frameworks.
				return $content;
		}
	}

	/**
	 * Write a file with framework-specific handling
	 *
	 * @param string       $file_path Path to output file.
	 * @param string|array $content   Content to write.
	 * @param string       $framework Framework name.
	 * @return void
	 * @throws Exception If file cannot be written.
	 */
	public function write_file( string $file_path, $content, string $framework ): void {
		// Create directory if it doesn't exist.
		$dir = dirname( $file_path );
		if ( ! is_dir( $dir ) ) {
			if ( ! mkdir( $dir, 0755, true ) ) {
				throw new Exception( "Failed to create directory: {$dir}" );
			}
		}

		// Framework-specific formatting.
		switch ( $framework ) {
			case 'elementor':
			case 'bricks':
			case 'oxygen':
				// JSON-based frameworks.
				if ( is_array( $content ) ) {
					$content = wp_json_encode( $content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
					if ( false === $content ) {
						throw new Exception( 'Failed to encode JSON: ' . json_last_error_msg() );
					}
				}
				break;

			default:
				// Text/HTML-based frameworks.
				if ( is_array( $content ) ) {
					$content = implode( PHP_EOL, $content );
				}
				break;
		}

		// Write file.
		$bytes = file_put_contents( $file_path, $content );

		if ( false === $bytes ) {
			throw new Exception( "Failed to write file: {$file_path}" );
		}

		// Set permissions.
		chmod( $file_path, 0644 );
	}

	/**
	 * Generate output filename based on input and frameworks
	 *
	 * @param string $input_file Input file path.
	 * @param string $source     Source framework.
	 * @param string $target     Target framework.
	 * @return string Output file path.
	 */
	public function generate_output_filename( string $input_file, string $source, string $target ): string {
		$dir       = dirname( $input_file );
		$filename  = pathinfo( $input_file, PATHINFO_FILENAME );
		$extension = $this->get_extension( $target );

		// Remove source framework suffix if present.
		$filename = preg_replace( '/-' . preg_quote( $source, '/' ) . '$/', '', $filename );

		// Add target framework suffix.
		$output_filename = $filename . '-' . $target . '.' . $extension;

		return $dir . '/' . $output_filename;
	}

	/**
	 * Get the canonical output extension for a framework slug.
	 *
	 * Used by output filename generation (e.g. divi -> txt, oxygen -> json).
	 * For filesystem extension extraction from a path, use {@see get_file_extension}.
	 *
	 * @param string $framework Framework slug.
	 * @return string File extension (without leading dot).
	 */
	public function get_extension( string $framework ): string {
		return $this->extensions[ $framework ] ?? 'html';
	}

	/**
	 * Extract the filesystem extension from a filename.
	 *
	 * @param string $filename Filename or path.
	 * @return string Extension without dot, or empty string if none.
	 */
	public function get_file_extension( string $filename ): string {
		$ext = pathinfo( $filename, PATHINFO_EXTENSION );
		return is_string( $ext ) ? strtolower( $ext ) : '';
	}

	/**
	 * Format a byte count as a human-readable size string.
	 *
	 * @param int $bytes Number of bytes (>= 0).
	 * @return string e.g. "0 B", "1 KB", "1.5 KB", "1 MB".
	 */
	public function format_file_size( int $bytes ): string {
		if ( $bytes <= 0 ) {
			return '0 B';
		}
		$units = array( 'B', 'KB', 'MB', 'GB', 'TB' );
		$power = (int) floor( log( $bytes, 1024 ) );
		$power = min( $power, count( $units ) - 1 );
		$value = $bytes / pow( 1024, $power );

		if ( fmod( $value, 1.0 ) === 0.0 ) {
			$formatted = (string) (int) $value;
		} else {
			// Trim trailing zeros and the dot if needed (1.50 -> 1.5, 2.00 -> 2).
			$formatted = rtrim( rtrim( number_format( $value, 2, '.', '' ), '0' ), '.' );
		}
		return $formatted . ' ' . $units[ $power ];
	}

	/**
	 * Find files in a directory matching a glob pattern.
	 *
	 * Thin alias of {@see list_files} for clearer call-site intent.
	 *
	 * @param string $dir     Directory path.
	 * @param string $pattern Glob pattern (e.g. "*.txt").
	 * @return array<string> Matching file paths.
	 */
	public function find_files( string $dir, string $pattern = '*' ): array {
		return $this->list_files( $dir, $pattern );
	}

	/**
	 * Detect framework from file content
	 *
	 * @param string $file_path File path.
	 * @return string|null Framework name or null if cannot detect.
	 */
	public function detect_framework( string $file_path ): ?string {
		if ( ! file_exists( $file_path ) ) {
			return null;
		}

		$content   = file_get_contents( $file_path );
		$extension = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );

		// Check by extension first.
		if ( 'json' === $extension ) {
			$data = json_decode( $content, true );
			if ( isset( $data['content'] ) ) {
				// Elementor format.
				return 'elementor';
			} elseif ( isset( $data['elements'] ) ) {
				// Bricks format.
				return 'bricks';
			}
		}

		// Check by content patterns.
		if ( preg_match( '/\[et_pb_/', $content ) ) {
			return 'divi';
		}

		if ( preg_match( '/\[vc_/', $content ) ) {
			return 'wpbakery';
		}

		if ( preg_match( '/\[fusion_/', $content ) ) {
			return 'avada';
		}

		if ( preg_match( '/wp:kadence\//', $content ) ) {
			return 'kadence';
		}

		if ( preg_match( '/<div class="[^"]*\bcontainer\b[^"]*"/', $content ) ) {
			return 'bootstrap';
		}

		return null;
	}

	/**
	 * Validate file format for a framework
	 *
	 * @param string $file_path File path.
	 * @param string $framework Framework name.
	 * @return bool True if valid.
	 */
	public function validate_format( string $file_path, string $framework ): bool {
		try {
			$content = $this->read_file( $file_path, $framework );
			return ! empty( $content );
		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Create backup of a file
	 *
	 * @param string $file_path File to backup.
	 * @return string|false Backup file path or false on failure.
	 */
	public function backup_file( string $file_path ) {
		if ( ! file_exists( $file_path ) ) {
			return false;
		}

		$backup_path = $file_path . '.backup-' . gmdate( 'Y-m-d-His' );

		if ( copy( $file_path, $backup_path ) ) {
			return $backup_path;
		}

		return false;
	}

	/**
	 * Get file information
	 *
	 * @param string $file_path File path.
	 * @return array File information.
	 */
	public function get_file_info( string $file_path ): array {
		if ( ! file_exists( $file_path ) ) {
			return array();
		}

		$info = array(
			'path'      => $file_path,
			'name'      => basename( $file_path ),
			'dir'       => dirname( $file_path ),
			'extension' => pathinfo( $file_path, PATHINFO_EXTENSION ),
			'size'      => filesize( $file_path ),
			'modified'  => filemtime( $file_path ),
			'readable'  => is_readable( $file_path ),
			'writable'  => is_writable( $file_path ),
		);

		// Try to detect framework.
		$info['framework'] = $this->detect_framework( $file_path );

		return $info;
	}

	/**
	 * List files in directory matching pattern
	 *
	 * @param string $directory Directory path.
	 * @param string $pattern   File pattern (e.g., "*.html").
	 * @return array List of file paths.
	 */
	public function list_files( string $directory, string $pattern = '*' ): array {
		if ( ! is_dir( $directory ) ) {
			return array();
		}

		$files = glob( $directory . '/' . $pattern );
		return $files ? $files : array();
	}

	/**
	 * Check if path is safe (no directory traversal)
	 *
	 * @param string $path File path.
	 * @return bool True if safe.
	 */
	public function is_safe_path( string $path ): bool {
		// Normalize the path to handle URL encoding and various traversal techniques.
		$path = urldecode( $path );

		// Check for various directory traversal patterns.
		if ( preg_match( '/\.\.|%2e%2e|%252e%252e/i', $path ) ) {
			return false;
		}

		// Check for null bytes.
		if ( strpos( $path, "\0" ) !== false ) {
			return false;
		}

		$real_path = realpath( $path );

		// Path doesn't exist yet (for new files) - check parent directory.
		if ( false === $real_path ) {
			$real_path = realpath( dirname( $path ) );
			if ( false === $real_path ) {
				return false;
			}
		}

		// Verify the resolved path is within the expected base directory.
		// This prevents symlink attacks and ensures files stay within project.
		$base_dir = realpath( DEVTB_ROOT );
		if ( $base_dir && strpos( $real_path, $base_dir ) !== 0 ) {
			return false;
		}

		return true;
	}

	/**
	 * Sanitize filename
	 *
	 * @param string $filename Filename to sanitize.
	 * @return string Sanitized filename.
	 */
	public function sanitize_filename( string $filename ): string {
		// Remove any path components.
		$filename = basename( $filename );

		// Remove special characters.
		$filename = preg_replace( '/[^a-zA-Z0-9._-]/', '-', $filename );

		// Remove multiple dashes.
		$filename = preg_replace( '/-+/', '-', $filename );

		return $filename;
	}
}
