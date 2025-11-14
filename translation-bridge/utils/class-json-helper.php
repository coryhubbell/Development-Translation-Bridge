<?php
/**
 * Advanced JSON Processing Helper
 *
 * Einstein-level JSON manipulation featuring:
 * - Deep array operations
 * - Path-based access (dot notation)
 * - Schema validation
 * - Elementor/Bricks JSON handling
 * - Diff and merge operations
 * - Performance optimization
 *
 * @package WordPress_Bootstrap_Claude
 * @subpackage Translation_Bridge
 * @since 3.0.0
 */

namespace WPBC\TranslationBridge\Utils;

/**
 * Class WPBC_JSON_Helper
 *
 * Advanced JSON processing and manipulation utilities.
 */
class WPBC_JSON_Helper {

	/**
	 * Parse JSON with error handling
	 *
	 * @param string $json JSON string.
	 * @param bool   $assoc Return associative array.
	 * @return mixed|null Parsed data or null on error.
	 */
	public static function parse( string $json, bool $assoc = true ) {
		if ( empty( $json ) ) {
			return null;
		}

		$data = json_decode( $json, $assoc );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return null;
		}

		return $data;
	}

	/**
	 * Encode data to JSON with pretty printing
	 *
	 * @param mixed $data Data to encode.
	 * @param bool  $pretty Pretty print.
	 * @return string|false JSON string or false on error.
	 */
	public static function encode( $data, bool $pretty = true ) {
		$options = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

		if ( $pretty ) {
			$options |= JSON_PRETTY_PRINT;
		}

		return json_encode( $data, $options );
	}

	/**
	 * Get value from JSON using dot notation path
	 *
	 * Example: get($data, 'settings.typography.font_size')
	 *
	 * @param array  $data JSON data array.
	 * @param string $path Dot notation path.
	 * @param mixed  $default Default value if not found.
	 * @return mixed Value or default.
	 */
	public static function get( array $data, string $path, $default = null ) {
		$keys = explode( '.', $path );

		foreach ( $keys as $key ) {
			if ( ! is_array( $data ) || ! isset( $data[ $key ] ) ) {
				return $default;
			}
			$data = $data[ $key ];
		}

		return $data;
	}

	/**
	 * Set value in JSON using dot notation path
	 *
	 * @param array  $data JSON data array (passed by reference).
	 * @param string $path Dot notation path.
	 * @param mixed  $value Value to set.
	 * @return void
	 */
	public static function set( array &$data, string $path, $value ): void {
		$keys = explode( '.', $path );
		$last_key = array_pop( $keys );

		$current = &$data;
		foreach ( $keys as $key ) {
			if ( ! isset( $current[ $key ] ) || ! is_array( $current[ $key ] ) ) {
				$current[ $key ] = [];
			}
			$current = &$current[ $key ];
		}

		$current[ $last_key ] = $value;
	}

	/**
	 * Check if path exists in JSON
	 *
	 * @param array  $data JSON data array.
	 * @param string $path Dot notation path.
	 * @return bool True if exists.
	 */
	public static function has( array $data, string $path ): bool {
		$keys = explode( '.', $path );

		foreach ( $keys as $key ) {
			if ( ! is_array( $data ) || ! isset( $data[ $key ] ) ) {
				return false;
			}
			$data = $data[ $key ];
		}

		return true;
	}

	/**
	 * Remove value from JSON using dot notation path
	 *
	 * @param array  $data JSON data array (passed by reference).
	 * @param string $path Dot notation path.
	 * @return void
	 */
	public static function remove( array &$data, string $path ): void {
		$keys = explode( '.', $path );
		$last_key = array_pop( $keys );

		$current = &$data;
		foreach ( $keys as $key ) {
			if ( ! isset( $current[ $key ] ) || ! is_array( $current[ $key ] ) ) {
				return;
			}
			$current = &$current[ $key ];
		}

		unset( $current[ $last_key ] );
	}

	/**
	 * Flatten nested JSON to dot notation keys
	 *
	 * @param array  $data JSON data array.
	 * @param string $prefix Key prefix.
	 * @return array Flattened array.
	 */
	public static function flatten( array $data, string $prefix = '' ): array {
		$result = [];

		foreach ( $data as $key => $value ) {
			$new_key = $prefix !== '' ? $prefix . '.' . $key : $key;

			if ( is_array( $value ) && ! empty( $value ) ) {
				$result = array_merge( $result, self::flatten( $value, $new_key ) );
			} else {
				$result[ $new_key ] = $value;
			}
		}

		return $result;
	}

	/**
	 * Unflatten dot notation keys to nested array
	 *
	 * @param array $data Flattened array.
	 * @return array Nested array.
	 */
	public static function unflatten( array $data ): array {
		$result = [];

		foreach ( $data as $key => $value ) {
			self::set( $result, $key, $value );
		}

		return $result;
	}

	/**
	 * Deep merge two JSON structures
	 *
	 * @param array $array1 First array.
	 * @param array $array2 Second array.
	 * @return array Merged array.
	 */
	public static function merge( array $array1, array $array2 ): array {
		$result = $array1;

		foreach ( $array2 as $key => $value ) {
			if ( is_array( $value ) && isset( $result[ $key ] ) && is_array( $result[ $key ] ) ) {
				$result[ $key ] = self::merge( $result[ $key ], $value );
			} else {
				$result[ $key ] = $value;
			}
		}

		return $result;
	}

	/**
	 * Calculate diff between two JSON structures
	 *
	 * @param array $array1 First array.
	 * @param array $array2 Second array.
	 * @return array Diff array.
	 */
	public static function diff( array $array1, array $array2 ): array {
		$diff = [];

		foreach ( $array1 as $key => $value ) {
			if ( ! array_key_exists( $key, $array2 ) ) {
				$diff[ $key ] = [ 'removed' => $value ];
			} elseif ( is_array( $value ) && is_array( $array2[ $key ] ) ) {
				$nested_diff = self::diff( $value, $array2[ $key ] );
				if ( ! empty( $nested_diff ) ) {
					$diff[ $key ] = $nested_diff;
				}
			} elseif ( $value !== $array2[ $key ] ) {
				$diff[ $key ] = [
					'old' => $value,
					'new' => $array2[ $key ],
				];
			}
		}

		foreach ( $array2 as $key => $value ) {
			if ( ! array_key_exists( $key, $array1 ) ) {
				$diff[ $key ] = [ 'added' => $value ];
			}
		}

		return $diff;
	}

	/**
	 * Search JSON for matching values
	 *
	 * @param array  $data JSON data array.
	 * @param mixed  $search Search value or callback.
	 * @param bool   $recursive Search recursively.
	 * @return array Found paths.
	 */
	public static function search( array $data, $search, bool $recursive = true ): array {
		$results = [];
		$is_callback = is_callable( $search );

		foreach ( $data as $key => $value ) {
			$match = false;

			if ( $is_callback ) {
				$match = call_user_func( $search, $value, $key );
			} else {
				$match = $value === $search;
			}

			if ( $match ) {
				$results[] = $key;
			}

			if ( $recursive && is_array( $value ) ) {
				$nested = self::search( $value, $search, $recursive );
				foreach ( $nested as $nested_path ) {
					$results[] = $key . '.' . $nested_path;
				}
			}
		}

		return $results;
	}

	/**
	 * Validate Elementor JSON structure
	 *
	 * @param array $data Elementor JSON data.
	 * @return bool True if valid Elementor structure.
	 */
	public static function is_valid_elementor( array $data ): bool {
		// Must be an array of elements
		if ( ! is_array( $data ) ) {
			return false;
		}

		// Check if it's a single element or array of elements
		$elements = isset( $data['elType'] ) ? [ $data ] : $data;

		foreach ( $elements as $element ) {
			// Must have elType
			if ( ! isset( $element['elType'] ) ) {
				return false;
			}

			// Must have id
			if ( ! isset( $element['id'] ) ) {
				return false;
			}

			// If widget, must have widgetType
			if ( $element['elType'] === 'widget' && ! isset( $element['widgetType'] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Validate Bricks JSON structure
	 *
	 * @param array $data Bricks JSON data.
	 * @return bool True if valid Bricks structure.
	 */
	public static function is_valid_bricks( array $data ): bool {
		// Must be an array of elements
		if ( ! is_array( $data ) ) {
			return false;
		}

		// Check if it's a single element or array of elements
		$elements = isset( $data['name'] ) ? [ $data ] : $data;

		foreach ( $elements as $element ) {
			// Must have name (element type)
			if ( ! isset( $element['name'] ) ) {
				return false;
			}

			// Must have id
			if ( ! isset( $element['id'] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Extract Elementor element IDs
	 *
	 * @param array $data Elementor JSON data.
	 * @return array Array of element IDs.
	 */
	public static function extract_elementor_ids( array $data ): array {
		$ids = [];

		if ( isset( $data['id'] ) ) {
			$ids[] = $data['id'];
		}

		if ( isset( $data['elements'] ) && is_array( $data['elements'] ) ) {
			foreach ( $data['elements'] as $element ) {
				$ids = array_merge( $ids, self::extract_elementor_ids( $element ) );
			}
		}

		return $ids;
	}

	/**
	 * Generate unique Elementor element ID
	 *
	 * @return string 8-character hex ID.
	 */
	public static function generate_elementor_id(): string {
		return substr( md5( uniqid( '', true ) ), 0, 8 );
	}

	/**
	 * Generate unique Bricks element ID
	 *
	 * @param int $counter Counter for sequential IDs.
	 * @return string Bricks element ID (e.g., brxe00001).
	 */
	public static function generate_bricks_id( int $counter = 1 ): string {
		return 'brxe' . str_pad( (string) $counter, 5, '0', STR_PAD_LEFT );
	}

	/**
	 * Transform JSON keys (camelCase, snake_case, kebab-case)
	 *
	 * @param array  $data JSON data array.
	 * @param string $case Target case (camel, snake, kebab).
	 * @return array Transformed array.
	 */
	public static function transform_keys( array $data, string $case ): array {
		$result = [];

		foreach ( $data as $key => $value ) {
			$new_key = self::transform_string_case( $key, $case );

			if ( is_array( $value ) ) {
				$result[ $new_key ] = self::transform_keys( $value, $case );
			} else {
				$result[ $new_key ] = $value;
			}
		}

		return $result;
	}

	/**
	 * Transform string case
	 *
	 * @param string $string Input string.
	 * @param string $case Target case (camel, snake, kebab).
	 * @return string Transformed string.
	 */
	private static function transform_string_case( string $string, string $case ): string {
		switch ( $case ) {
			case 'camel':
				// Convert to camelCase
				$string = str_replace( [ '-', '_' ], ' ', $string );
				$string = str_replace( ' ', '', ucwords( $string ) );
				return lcfirst( $string );

			case 'snake':
				// Convert to snake_case
				$string = preg_replace( '/([a-z])([A-Z])/', '$1_$2', $string );
				$string = str_replace( '-', '_', $string );
				return strtolower( $string );

			case 'kebab':
				// Convert to kebab-case
				$string = preg_replace( '/([a-z])([A-Z])/', '$1-$2', $string );
				$string = str_replace( '_', '-', $string );
				return strtolower( $string );

			default:
				return $string;
		}
	}

	/**
	 * Filter JSON by callback
	 *
	 * @param array    $data JSON data array.
	 * @param callable $callback Filter callback.
	 * @param bool     $recursive Filter recursively.
	 * @return array Filtered array.
	 */
	public static function filter( array $data, callable $callback, bool $recursive = false ): array {
		$result = [];

		foreach ( $data as $key => $value ) {
			if ( call_user_func( $callback, $value, $key ) ) {
				if ( $recursive && is_array( $value ) ) {
					$result[ $key ] = self::filter( $value, $callback, $recursive );
				} else {
					$result[ $key ] = $value;
				}
			}
		}

		return $result;
	}

	/**
	 * Map JSON values by callback
	 *
	 * @param array    $data JSON data array.
	 * @param callable $callback Map callback.
	 * @param bool     $recursive Map recursively.
	 * @return array Mapped array.
	 */
	public static function map( array $data, callable $callback, bool $recursive = false ): array {
		$result = [];

		foreach ( $data as $key => $value ) {
			if ( $recursive && is_array( $value ) ) {
				$result[ $key ] = self::map( $value, $callback, $recursive );
			} else {
				$result[ $key ] = call_user_func( $callback, $value, $key );
			}
		}

		return $result;
	}

	/**
	 * Get JSON last error message
	 *
	 * @return string Error message.
	 */
	public static function get_last_error(): string {
		$error_code = json_last_error();

		$error_messages = [
			JSON_ERROR_NONE           => 'No error',
			JSON_ERROR_DEPTH          => 'Maximum stack depth exceeded',
			JSON_ERROR_STATE_MISMATCH => 'Invalid or malformed JSON',
			JSON_ERROR_CTRL_CHAR      => 'Control character error',
			JSON_ERROR_SYNTAX         => 'Syntax error',
			JSON_ERROR_UTF8           => 'Malformed UTF-8 characters',
		];

		return $error_messages[ $error_code ] ?? 'Unknown error';
	}

	/**
	 * Validate JSON against schema
	 *
	 * @param array $data JSON data.
	 * @param array $schema Schema definition.
	 * @return bool True if valid.
	 */
	public static function validate_schema( array $data, array $schema ): bool {
		foreach ( $schema as $key => $rules ) {
			// Required check
			if ( isset( $rules['required'] ) && $rules['required'] && ! isset( $data[ $key ] ) ) {
				return false;
			}

			if ( ! isset( $data[ $key ] ) ) {
				continue;
			}

			$value = $data[ $key ];

			// Type check
			if ( isset( $rules['type'] ) ) {
				$type = gettype( $value );
				if ( $type !== $rules['type'] ) {
					return false;
				}
			}

			// Enum check
			if ( isset( $rules['enum'] ) && ! in_array( $value, $rules['enum'], true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Pretty print JSON for debugging
	 *
	 * @param mixed $data Data to print.
	 * @return void
	 */
	public static function dump( $data ): void {
		echo '<pre>';
		echo self::encode( $data, true );
		echo '</pre>';
	}
}
