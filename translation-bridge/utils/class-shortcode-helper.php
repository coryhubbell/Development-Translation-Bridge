<?php
/**
 * Advanced Shortcode Processing Helper
 *
 * Einstein-level shortcode manipulation featuring:
 * - Intelligent shortcode parsing
 * - Nested shortcode handling
 * - Attribute extraction and normalization
 * - DIVI/Avada-specific utilities
 * - Shortcode generation
 * - Performance optimization
 *
 * @package WordPress_Bootstrap_Claude
 * @subpackage Translation_Bridge
 * @since 3.0.0
 */

namespace WPBC\TranslationBridge\Utils;

/**
 * Class WPBC_Shortcode_Helper
 *
 * Advanced shortcode processing and manipulation utilities.
 */
class WPBC_Shortcode_Helper {

	/**
	 * Parse shortcode string into components
	 *
	 * @param string $content Content with shortcodes.
	 * @return array Parsed shortcodes.
	 */
	public static function parse( string $content ): array {
		$shortcodes = [];
		$pattern    = self::get_shortcode_regex();

		if ( preg_match_all( $pattern, $content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$shortcodes[] = [
					'full'       => $match[0],
					'tag'        => $match[2],
					'attributes' => self::parse_attributes( $match[3] ?? '' ),
					'content'    => $match[5] ?? '',
					'self_closing' => empty( $match[5] ),
				];
			}
		}

		return $shortcodes;
	}

	/**
	 * Parse shortcode attributes
	 *
	 * Enhanced version of WordPress shortcode_parse_atts with better handling.
	 *
	 * @param string $text Attribute string.
	 * @return array Parsed attributes.
	 */
	public static function parse_attributes( string $text ): array {
		$text = trim( $text );

		if ( empty( $text ) ) {
			return [];
		}

		$attributes = [];
		$pattern    = '/(\w+)\s*=\s*"([^"]*)"|(\w+)\s*=\s*\'([^\']*)\'|(\w+)\s*=\s*([^\s]+)/';

		if ( preg_match_all( $pattern, $text, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				if ( ! empty( $match[1] ) ) {
					// Double quoted
					$attributes[ $match[1] ] = $match[2];
				} elseif ( ! empty( $match[3] ) ) {
					// Single quoted
					$attributes[ $match[3] ] = $match[4];
				} elseif ( ! empty( $match[5] ) ) {
					// Unquoted
					$attributes[ $match[5] ] = $match[6];
				}
			}
		}

		return $attributes;
	}

	/**
	 * Build shortcode string from components
	 *
	 * @param string $tag Shortcode tag.
	 * @param array  $attributes Shortcode attributes.
	 * @param string $content Inner content.
	 * @param bool   $self_closing Self-closing shortcode.
	 * @return string Shortcode string.
	 */
	public static function build(
		string $tag,
		array $attributes = [],
		string $content = '',
		bool $self_closing = false
	): string {
		$shortcode = '[' . $tag;

		// Add attributes
		foreach ( $attributes as $key => $value ) {
			if ( is_bool( $value ) ) {
				// Boolean to yes/no
				$value = $value ? 'yes' : 'no';
			} elseif ( is_array( $value ) ) {
				// Array to comma-separated
				$value = implode( ',', $value );
			}

			// Quote value if it contains spaces or special characters
			if ( preg_match( '/[\s,]/', $value ) ) {
				$shortcode .= sprintf( ' %s="%s"', $key, esc_attr( $value ) );
			} else {
				$shortcode .= sprintf( ' %s=%s', $key, $value );
			}
		}

		if ( $self_closing || empty( $content ) ) {
			$shortcode .= ']';
		} else {
			$shortcode .= ']' . $content . '[/' . $tag . ']';
		}

		return $shortcode;
	}

	/**
	 * Extract all shortcodes of a specific tag
	 *
	 * @param string $content Content to search.
	 * @param string $tag Shortcode tag to find.
	 * @return array Array of found shortcodes.
	 */
	public static function extract_by_tag( string $content, string $tag ): array {
		$all_shortcodes = self::parse( $content );
		$filtered       = [];

		foreach ( $all_shortcodes as $shortcode ) {
			if ( $shortcode['tag'] === $tag ) {
				$filtered[] = $shortcode;
			}
		}

		return $filtered;
	}

	/**
	 * Replace shortcode in content
	 *
	 * @param string $content Original content.
	 * @param string $search Shortcode to replace.
	 * @param string $replace Replacement shortcode.
	 * @return string Modified content.
	 */
	public static function replace( string $content, string $search, string $replace ): string {
		return str_replace( $search, $replace, $content );
	}

	/**
	 * Strip all shortcodes from content
	 *
	 * @param string $content Content with shortcodes.
	 * @param array  $tags_to_keep Tags to preserve.
	 * @return string Content without shortcodes.
	 */
	public static function strip( string $content, array $tags_to_keep = [] ): string {
		if ( empty( $tags_to_keep ) ) {
			return strip_shortcodes( $content );
		}

		$shortcodes = self::parse( $content );

		foreach ( $shortcodes as $shortcode ) {
			if ( ! in_array( $shortcode['tag'], $tags_to_keep, true ) ) {
				$content = str_replace( $shortcode['full'], '', $content );
			}
		}

		return $content;
	}

	/**
	 * Get nested shortcodes
	 *
	 * Handles nested structures like DIVI sections/rows/columns.
	 *
	 * @param string $content Content with shortcodes.
	 * @param string $parent_tag Parent shortcode tag.
	 * @return array Hierarchical structure.
	 */
	public static function get_nested( string $content, string $parent_tag ): array {
		$results = [];
		$pattern = self::get_shortcode_regex();

		if ( preg_match_all( $pattern, $content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				if ( $match[2] === $parent_tag ) {
					$inner_content = $match[5] ?? '';

					$result = [
						'tag'        => $match[2],
						'attributes' => self::parse_attributes( $match[3] ?? '' ),
						'content'    => $inner_content,
						'children'   => [],
					];

					// Recursively get children
					if ( ! empty( $inner_content ) ) {
						$result['children'] = self::parse( $inner_content );
					}

					$results[] = $result;
				}
			}
		}

		return $results;
	}

	/**
	 * Detect if content contains DIVI shortcodes
	 *
	 * @param string $content Content to check.
	 * @return bool True if DIVI shortcodes found.
	 */
	public static function is_divi( string $content ): bool {
		$divi_tags = [
			'et_pb_section',
			'et_pb_row',
			'et_pb_column',
			'et_pb_text',
			'et_pb_image',
			'et_pb_button',
			'et_pb_blurb',
		];

		foreach ( $divi_tags as $tag ) {
			if ( strpos( $content, '[' . $tag ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Detect if content contains Avada shortcodes
	 *
	 * @param string $content Content to check.
	 * @return bool True if Avada shortcodes found.
	 */
	public static function is_avada( string $content ): bool {
		$avada_tags = [
			'fusion_builder_container',
			'fusion_builder_row',
			'fusion_builder_column',
			'fusion_button',
			'fusion_text',
			'fusion_imageframe',
			'fusion_content_box',
		];

		foreach ( $avada_tags as $tag ) {
			if ( strpos( $content, '[' . $tag ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get DIVI hierarchy (section > row > column > module)
	 *
	 * @param string $content DIVI content.
	 * @return array Hierarchical structure.
	 */
	public static function parse_divi_hierarchy( string $content ): array {
		$sections = [];

		// Extract sections
		$section_pattern = '/\[et_pb_section([^\]]*)\](.*?)\[\/et_pb_section\]/s';

		if ( preg_match_all( $section_pattern, $content, $section_matches, PREG_SET_ORDER ) ) {
			foreach ( $section_matches as $section ) {
				$section_data = [
					'type'       => 'section',
					'attributes' => self::parse_attributes( $section[1] ),
					'rows'       => [],
				];

				$section_content = $section[2];

				// Extract rows
				$row_pattern = '/\[et_pb_row([^\]]*)\](.*?)\[\/et_pb_row\]/s';

				if ( preg_match_all( $row_pattern, $section_content, $row_matches, PREG_SET_ORDER ) ) {
					foreach ( $row_matches as $row ) {
						$row_data = [
							'type'       => 'row',
							'attributes' => self::parse_attributes( $row[1] ),
							'columns'    => [],
						];

						$row_content = $row[2];

						// Extract columns
						$column_pattern = '/\[et_pb_column([^\]]*)\](.*?)\[\/et_pb_column\]/s';

						if ( preg_match_all( $column_pattern, $row_content, $column_matches, PREG_SET_ORDER ) ) {
							foreach ( $column_matches as $column ) {
								$column_data = [
									'type'       => 'column',
									'attributes' => self::parse_attributes( $column[1] ),
									'modules'    => self::parse( $column[2] ),
								];

								$row_data['columns'][] = $column_data;
							}
						}

						$section_data['rows'][] = $row_data;
					}
				}

				$sections[] = $section_data;
			}
		}

		return $sections;
	}

	/**
	 * Get Avada hierarchy (container > row > column > element)
	 *
	 * @param string $content Avada content.
	 * @return array Hierarchical structure.
	 */
	public static function parse_avada_hierarchy( string $content ): array {
		$containers = [];

		// Extract containers
		$container_pattern = '/\[fusion_builder_container([^\]]*)\](.*?)\[\/fusion_builder_container\]/s';

		if ( preg_match_all( $container_pattern, $content, $container_matches, PREG_SET_ORDER ) ) {
			foreach ( $container_matches as $container ) {
				$container_data = [
					'type'       => 'container',
					'attributes' => self::parse_attributes( $container[1] ),
					'rows'       => [],
				];

				$container_content = $container[2];

				// Extract rows
				$row_pattern = '/\[fusion_builder_row([^\]]*)\](.*?)\[\/fusion_builder_row\]/s';

				if ( preg_match_all( $row_pattern, $container_content, $row_matches, PREG_SET_ORDER ) ) {
					foreach ( $row_matches as $row ) {
						$row_data = [
							'type'       => 'row',
							'attributes' => self::parse_attributes( $row[1] ),
							'columns'    => [],
						];

						$row_content = $row[2];

						// Extract columns
						$column_pattern = '/\[fusion_builder_column([^\]]*)\](.*?)\[\/fusion_builder_column\]/s';

						if ( preg_match_all( $column_pattern, $row_content, $column_matches, PREG_SET_ORDER ) ) {
							foreach ( $column_matches as $column ) {
								$column_data = [
									'type'       => 'column',
									'attributes' => self::parse_attributes( $column[1] ),
									'elements'   => self::parse( $column[2] ),
								];

								$row_data['columns'][] = $column_data;
							}
						}

						$container_data['rows'][] = $row_data;
					}
				}

				$containers[] = $container_data;
			}
		}

		return $containers;
	}

	/**
	 * Normalize attribute names (convert between frameworks)
	 *
	 * @param array  $attributes Source attributes.
	 * @param string $source_framework Source framework.
	 * @param string $target_framework Target framework.
	 * @return array Normalized attributes.
	 */
	public static function normalize_attributes(
		array $attributes,
		string $source_framework,
		string $target_framework
	): array {
		// Common attribute mappings
		$mappings = [
			'divi_to_avada' => [
				'background_color' => 'backgroundcolor',
				'button_url'       => 'link',
				'button_text'      => 'text',
			],
			'avada_to_divi' => [
				'backgroundcolor' => 'background_color',
				'link'            => 'button_url',
				'text'            => 'button_text',
			],
		];

		$map_key = strtolower( $source_framework ) . '_to_' . strtolower( $target_framework );
		$map     = $mappings[ $map_key ] ?? [];

		$normalized = [];

		foreach ( $attributes as $key => $value ) {
			$new_key = $map[ $key ] ?? $key;
			$normalized[ $new_key ] = $value;
		}

		return $normalized;
	}

	/**
	 * Get shortcode regex pattern
	 *
	 * @return string Regex pattern.
	 */
	private static function get_shortcode_regex(): string {
		return '/'
			. '\['                              // Opening bracket
			. '(\[?)'                           // 1: Optional second opening bracket for escaping shortcodes: [[tag]]
			. '([a-zA-Z0-9_-]+)'                // 2: Shortcode name
			. '(?![\w-])'                       // Not followed by word character or hyphen
			. '('                               // 3: Unroll the loop: Inside the opening shortcode tag
			.     '[^\]\/]*'                    // Not a closing bracket or forward slash
			.     '(?:'
			.         '\/(?!\])'                // A forward slash not followed by a closing bracket
			.         '[^\]\/]*'                // Not a closing bracket or forward slash
			.     ')*?'
			. ')'
			. '(?:'
			.     '(\/)'                        // 4: Self closing tag ...
			.     '\]'                          // ... and closing bracket
			. '|'
			.     '\]'                          // Closing bracket
			.     '(?:'
			.         '('                       // 5: Unroll the loop: Optionally, anything between the opening and closing shortcode tags
			.             '[^\[]*+'             // Not an opening bracket
			.             '(?:'
			.                 '\[(?!\/\2\])'    // An opening bracket not followed by the closing shortcode tag
			.                 '[^\[]*+'         // Not an opening bracket
			.             ')*+'
			.         ')'
			.         '\[\/\2\]'                // Closing shortcode tag
			.     ')?'
			. ')'
			. '(\]?)'                           // 6: Optional second closing brocket for escaping shortcodes: [[tag]]
			. '/';
	}

	/**
	 * Count shortcodes in content
	 *
	 * @param string $content Content to analyze.
	 * @param string $tag Optional specific tag to count.
	 * @return int Shortcode count.
	 */
	public static function count( string $content, string $tag = '' ): int {
		if ( empty( $tag ) ) {
			$shortcodes = self::parse( $content );
			return count( $shortcodes );
		}

		$shortcodes = self::extract_by_tag( $content, $tag );
		return count( $shortcodes );
	}

	/**
	 * Validate shortcode syntax
	 *
	 * @param string $shortcode Shortcode string.
	 * @return bool True if valid.
	 */
	public static function is_valid( string $shortcode ): bool {
		$pattern = self::get_shortcode_regex();
		return preg_match( $pattern, $shortcode ) === 1;
	}
}
