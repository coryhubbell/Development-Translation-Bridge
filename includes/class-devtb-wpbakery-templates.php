<?php
/**
 * WPBakery Template System
 *
 * Manages WPBakery templates, extraction, and conversion
 *
 * @package    DevelopmentTranslation_Bridge
 * @subpackage WPBakery
 * @version    5.0.0
 */

class DEVTB_WPBakery_Templates {
	/**
	 * Logger instance
	 *
	 * @var DEVTB_Logger
	 */
	private $logger;

	/**
	 * Template categories
	 *
	 * @var array
	 */
	private $categories = [
		'hero'        => 'Hero Sections',
		'features'    => 'Feature Sections',
		'pricing'     => 'Pricing Tables',
		'testimonials' => 'Testimonials',
		'team'        => 'Team Sections',
		'gallery'     => 'Galleries',
		'contact'     => 'Contact Forms',
		'cta'         => 'Call to Action',
		'header'      => 'Headers',
		'footer'      => 'Footers',
		'content'     => 'Content Blocks',
	];

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->logger = new DEVTB_Logger();
	}

	/**
	 * Extract template from WPBakery content
	 *
	 * @param string $content   WPBakery shortcode content.
	 * @param array  $options   Extraction options.
	 * @return array Template data.
	 */
	public function extract_template( string $content, array $options = [] ): array {
		// Parse content to identify template structure
		$template = [
			'content'         => $content,
			'shortcodes'      => $this->extract_shortcodes( $content ),
			'custom_elements' => $this->identify_custom_elements( $content ),
			'design_options'  => $this->extract_design_options( $content ),
			'dependencies'    => $this->find_dependencies( $content ),
		];

		// Extract CSS if design options present
		if ( ! empty( $template['design_options'] ) ) {
			$template['css'] = $this->generate_css_from_design_options( $template['design_options'] );
		}

		$this->logger->info( 'Template extracted', [
			'shortcode_count' => count( $template['shortcodes'] ),
			'custom_elements' => count( $template['custom_elements'] ),
		]);

		return $template;
	}

	/**
	 * Extract all shortcodes from content
	 *
	 * @param string $content Content to analyze.
	 * @return array Shortcode data.
	 */
	private function extract_shortcodes( string $content ): array {
		$shortcodes = [];

		// Match all shortcode tags
		preg_match_all( '/\[([a-zA-Z0-9_\-]+)([^\]]*)\]/', $content, $matches, PREG_SET_ORDER );

		foreach ( $matches as $match ) {
			$tag = $match[1];

			if ( ! isset( $shortcodes[ $tag ] ) ) {
				$shortcodes[ $tag ] = [
					'tag'   => $tag,
					'count' => 0,
					'params' => [],
				];
			}

			$shortcodes[ $tag ]['count']++;

			// Extract parameters
			if ( ! empty( $match[2] ) ) {
				$this->parse_shortcode_params( $match[2], $shortcodes[ $tag ]['params'] );
			}
		}

		return array_values( $shortcodes );
	}

	/**
	 * Parse shortcode parameters
	 *
	 * @param string $params_string Parameter string.
	 * @param array  &$params       Reference to params array.
	 */
	private function parse_shortcode_params( string $params_string, array &$params ) {
		preg_match_all( '/(\w+)=["\']([^"\']*)["\']/', $params_string, $matches, PREG_SET_ORDER );

		foreach ( $matches as $match ) {
			$key = $match[1];
			if ( ! isset( $params[ $key ] ) ) {
				$params[ $key ] = [];
			}
			$params[ $key ][] = $match[2];
		}
	}

	/**
	 * Identify custom elements in content
	 *
	 * @param string $content Content to analyze.
	 * @return array Custom element types.
	 */
	private function identify_custom_elements( string $content ): array {
		$custom = [];

		// Find all shortcode tags
		preg_match_all( '/\[([a-zA-Z0-9_\-]+)/', $content, $matches );

		if ( ! empty( $matches[1] ) ) {
			$tags = array_unique( $matches[1] );

			foreach ( $tags as $tag ) {
				// Check if it's not a core vc_ element
				if ( strpos( $tag, 'vc_' ) !== 0 ) {
					$custom[] = $tag;
				}
			}
		}

		return $custom;
	}

	/**
	 * Extract design options from shortcodes
	 *
	 * @param string $content Content with shortcodes.
	 * @return array Design options.
	 */
	private function extract_design_options( string $content ): array {
		$design_options = [];

		// Pattern to match css attributes in shortcodes
		preg_match_all( '/css=["\']([^"\']*)["\']/', $content, $matches );

		foreach ( $matches[1] as $css_string ) {
			$decoded = urldecode( $css_string );

			// Parse vc_custom_ CSS classes
			preg_match_all( '/vc_custom_([a-zA-Z0-9_]+)/', $decoded, $custom_matches );

			foreach ( $custom_matches[1] as $custom_id ) {
				if ( ! in_array( $custom_id, $design_options, true ) ) {
					$design_options[] = $custom_id;
				}
			}
		}

		return $design_options;
	}

	/**
	 * Generate CSS from design options
	 *
	 * @param array $design_options Design option IDs.
	 * @return string CSS code.
	 */
	private function generate_css_from_design_options( array $design_options ): string {
		$css = '';

		// This would typically query the design options from wp_options
		// or extract from the shortcode attributes
		// For now, return placeholder
		foreach ( $design_options as $option_id ) {
			$css .= "/* Design option: {$option_id} */\n";
		}

		return $css;
	}

	/**
	 * Find template dependencies
	 *
	 * @param string $content Content to analyze.
	 * @return array Dependencies (images, fonts, etc).
	 */
	private function find_dependencies( string $content ): array {
		$dependencies = [
			'images' => [],
			'fonts'  => [],
			'icons'  => [],
		];

		// Extract image URLs
		preg_match_all( '/image=["\']([^"\']+)["\']/', $content, $image_matches );
		$dependencies['images'] = array_unique( $image_matches[1] );

		// Extract font families
		preg_match_all( '/font_family=["\']([^"\']+)["\']/', $content, $font_matches );
		$dependencies['fonts'] = array_unique( $font_matches[1] );

		// Extract icon classes
		preg_match_all( '/icon=["\']([^"\']+)["\']/', $content, $icon_matches );
		$dependencies['icons'] = array_unique( $icon_matches[1] );

		return $dependencies;
	}

	/**
	 * Save template to library
	 *
	 * @param string $name     Template name.
	 * @param array  $template Template data.
	 * @param array  $meta     Template metadata.
	 * @return int Template ID.
	 */
	public function save_template( string $name, array $template, array $meta = [] ): int {
		$templates = get_option( 'devtb_wpbakery_templates', [] );

		$template_id = count( $templates ) + 1;

		$templates[ $template_id ] = [
			'id'          => $template_id,
			'name'        => $name,
			'template'    => $template,
			'category'    => $meta['category'] ?? 'content',
			'description' => $meta['description'] ?? '',
			'tags'        => $meta['tags'] ?? [],
			'created_at'  => current_time( 'mysql' ),
			'updated_at'  => current_time( 'mysql' ),
		];

		update_option( 'devtb_wpbakery_templates', $templates );

		$this->logger->info( 'Template saved', [
			'id'   => $template_id,
			'name' => $name,
		]);

		return $template_id;
	}

	/**
	 * Get template by ID
	 *
	 * @param int $template_id Template ID.
	 * @return array|null Template data or null.
	 */
	public function get_template( int $template_id ): ?array {
		$templates = get_option( 'devtb_wpbakery_templates', [] );

		return $templates[ $template_id ] ?? null;
	}

	/**
	 * Get all templates
	 *
	 * @param string|null $category Filter by category.
	 * @return array Templates.
	 */
	public function get_all_templates( ?string $category = null ): array {
		$templates = get_option( 'devtb_wpbakery_templates', [] );

		if ( $category ) {
			$templates = array_filter( $templates, function( $template ) use ( $category ) {
				return $template['category'] === $category;
			});
		}

		return array_values( $templates );
	}

	/**
	 * Delete template
	 *
	 * @param int $template_id Template ID.
	 * @return bool Success.
	 */
	public function delete_template( int $template_id ): bool {
		$templates = get_option( 'devtb_wpbakery_templates', [] );

		if ( ! isset( $templates[ $template_id ] ) ) {
			return false;
		}

		unset( $templates[ $template_id ] );
		update_option( 'devtb_wpbakery_templates', $templates );

		$this->logger->info( 'Template deleted', [
			'id' => $template_id,
		]);

		return true;
	}

	/**
	 * Convert template to another framework
	 *
	 * @param int    $template_id Template ID.
	 * @param string $target_framework Target framework.
	 * @return string|false Converted content or false.
	 */
	public function convert_template( int $template_id, string $target_framework ) {
		$template = $this->get_template( $template_id );

		if ( ! $template ) {
			return false;
		}

		// Use translator to convert
		require_once DEVTB_TRANSLATION_BRIDGE_DIR . '/core/class-translator.php';
		$translator = new Translator();

		try {
			$result = $translator->translate(
				$template['template']['content'],
				'wpbakery',
				$target_framework
			);

			$this->logger->info( 'Template converted', [
				'template_id' => $template_id,
				'target'      => $target_framework,
			]);

			return $result;

		} catch ( Exception $e ) {
			$this->logger->error( 'Template conversion failed', [
				'template_id' => $template_id,
				'target'      => $target_framework,
				'error'       => $e->getMessage(),
			]);

			return false;
		}
	}

	/**
	 * Export template to JSON
	 *
	 * @param int $template_id Template ID.
	 * @return string|false JSON or false.
	 */
	public function export_template( int $template_id ) {
		$template = $this->get_template( $template_id );

		if ( ! $template ) {
			return false;
		}

		return wp_json_encode( $template, JSON_PRETTY_PRINT );
	}

	/**
	 * Import template from JSON
	 *
	 * @param string $json Template JSON.
	 * @return int|false Template ID or false.
	 */
	public function import_template( string $json ) {
		$template_data = json_decode( $json, true );

		if ( ! $template_data || empty( $template_data['name'] ) ) {
			return false;
		}

		return $this->save_template(
			$template_data['name'],
			$template_data['template'] ?? [],
			[
				'category'    => $template_data['category'] ?? 'content',
				'description' => $template_data['description'] ?? '',
				'tags'        => $template_data['tags'] ?? [],
			]
		);
	}

	/**
	 * Get template categories
	 *
	 * @return array Categories.
	 */
	public function get_categories(): array {
		return $this->categories;
	}

	/**
	 * Search templates
	 *
	 * @param string $keyword Search keyword.
	 * @return array Matching templates.
	 */
	public function search_templates( string $keyword ): array {
		$all_templates = $this->get_all_templates();
		$matches = [];

		$keyword = strtolower( $keyword );

		foreach ( $all_templates as $template ) {
			// Search in name
			if ( stripos( $template['name'], $keyword ) !== false ) {
				$matches[] = $template;
				continue;
			}

			// Search in description
			if ( ! empty( $template['description'] ) && stripos( $template['description'], $keyword ) !== false ) {
				$matches[] = $template;
				continue;
			}

			// Search in tags
			if ( ! empty( $template['tags'] ) ) {
				foreach ( $template['tags'] as $tag ) {
					if ( stripos( $tag, $keyword ) !== false ) {
						$matches[] = $template;
						break;
					}
				}
			}
		}

		return $matches;
	}

	/**
	 * Create template library from posts
	 *
	 * @param array $post_ids Post IDs to extract templates from.
	 * @return array Created template IDs.
	 */
	public function create_library_from_posts( array $post_ids ): array {
		$created = [];

		foreach ( $post_ids as $post_id ) {
			$content = get_post_meta( $post_id, '_wpb_shortcodes_custom_css', true );

			if ( empty( $content ) ) {
				$post = get_post( $post_id );
				if ( $post ) {
					$content = $post->post_content;
				}
			}

			if ( ! empty( $content ) ) {
				$template = $this->extract_template( $content );

				$template_id = $this->save_template(
					get_the_title( $post_id ) . ' Template',
					$template,
					[
						'description' => 'Extracted from post ' . $post_id,
						'tags'        => [ 'auto-extracted' ],
					]
				);

				$created[] = $template_id;
			}
		}

		$this->logger->info( 'Template library created from posts', [
			'posts_processed' => count( $post_ids ),
			'templates_created' => count( $created ),
		]);

		return $created;
	}

	/**
	 * Analyze template compatibility with frameworks
	 *
	 * @param int $template_id Template ID.
	 * @return array Compatibility scores.
	 */
	public function analyze_compatibility( int $template_id ): array {
		$template = $this->get_template( $template_id );

		if ( ! $template ) {
			return [];
		}

		$frameworks = [
			'bootstrap'      => 1.0, // High compatibility
			'divi'           => 0.9,
			'divi-5'         => 0.9,
			'elementor'      => 0.85,
			'elementor-4'    => 0.85,
			'avada'          => 0.9,
			'bricks'         => 0.8,
			'beaver-builder' => 0.85,
			'gutenberg'      => 0.7,
			'oxygen'         => 0.75,
			'oxygen-6'       => 0.75,
			'kadence'        => 0.8,
			'thrive'         => 0.7,
		];

		// Adjust scores based on custom elements
		if ( ! empty( $template['template']['custom_elements'] ) ) {
			foreach ( $frameworks as $fw => &$score ) {
				$score *= 0.9; // Reduce score if custom elements present
			}
		}

		arsort( $frameworks );

		return $frameworks;
	}

	/**
	 * Get template statistics
	 *
	 * @return array Statistics.
	 */
	public function get_stats(): array {
		$templates = $this->get_all_templates();

		$stats = [
			'total'          => count( $templates ),
			'by_category'    => [],
			'with_custom'    => 0,
			'with_css'       => 0,
		];

		foreach ( $templates as $template ) {
			$category = $template['category'];
			if ( ! isset( $stats['by_category'][ $category ] ) ) {
				$stats['by_category'][ $category ] = 0;
			}
			$stats['by_category'][ $category ]++;

			if ( ! empty( $template['template']['custom_elements'] ) ) {
				$stats['with_custom']++;
			}

			if ( ! empty( $template['template']['css'] ) ) {
				$stats['with_css']++;
			}
		}

		return $stats;
	}
}
