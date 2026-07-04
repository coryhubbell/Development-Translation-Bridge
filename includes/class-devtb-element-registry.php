<?php
/**
 * DEVTB Element Registry
 *
 * Manages custom and third-party WPBakery elements
 * Allows dynamic registration of element types and their mappings
 *
 * @package    DevelopmentTranslation_Bridge
 * @subpackage WPBakery
 * @version    5.0.0
 */

class DEVTB_Element_Registry {
	/**
	 * Registered custom elements
	 *
	 * @var array
	 */
	private static $custom_elements = [];

	/**
	 * Element type mappings to universal types
	 *
	 * @var array
	 */
	private static $type_mappings = [];

	/**
	 * Logger instance
	 *
	 * @var DEVTB_Logger
	 */
	private $logger;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->logger = new DEVTB_Logger();
		$this->register_default_addons();
	}

	/**
	 * Register default third-party addon elements
	 */
	private function register_default_addons() {
		// Ultimate Addons for WPBakery
		$this->register_ultimate_addons();

		// Other popular addons can be added here
		$this->logger->info('Default WPBakery addons registered');
	}

	/**
	 * Register Ultimate Addons elements
	 */
	private function register_ultimate_addons() {
		$ultimate_elements = [
			'ultimate_heading'           => [
				'universal_type' => 'heading',
				'category'       => 'content',
				'content_field'  => 'main_heading',
			],
			'ultimate_icon'              => [
				'universal_type' => 'icon',
				'category'       => 'decorative',
				'content_field'  => 'icon',
			],
			'ultimate_icon_list'         => [
				'universal_type' => 'icon-list',
				'category'       => 'content',
				'content_field'  => 'list_items',
			],
			'ultimate_carousel'          => [
				'universal_type' => 'slider',
				'category'       => 'media',
				'content_field'  => 'slides',
			],
			'ultimate_info_banner'       => [
				'universal_type' => 'banner',
				'category'       => 'content',
				'content_field'  => 'banner_desc',
			],
			'ultimate_info_list'         => [
				'universal_type' => 'list',
				'category'       => 'content',
				'content_field'  => 'list_items',
			],
			'ultimate_pricing'           => [
				'universal_type' => 'pricing-table',
				'category'       => 'content',
				'content_field'  => 'package_heading',
			],
			'ultimate_spacer'            => [
				'universal_type' => 'spacer',
				'category'       => 'decorative',
				'content_field'  => '',
			],
			'ultimate_google_map'        => [
				'universal_type' => 'map',
				'category'       => 'media',
				'content_field'  => 'map_override',
			],
			'ultimate_buttons'           => [
				'universal_type' => 'button',
				'category'       => 'interactive',
				'content_field'  => 'btn_title',
			],
			'ultimate_ctation'           => [
				'universal_type' => 'call-to-action',
				'category'       => 'interactive',
				'content_field'  => 'ctaction_text',
			],
			'ultimate_modal'             => [
				'universal_type' => 'modal',
				'category'       => 'interactive',
				'content_field'  => 'modal_title',
			],
			'ultimate_video_banner'      => [
				'universal_type' => 'video',
				'category'       => 'media',
				'content_field'  => 'video_url',
			],
			'ult_countdown'              => [
				'universal_type' => 'countdown',
				'category'       => 'interactive',
				'content_field'  => 'countdown_datetime',
			],
			'ultimate_dual_button'       => [
				'universal_type' => 'button-group',
				'category'       => 'interactive',
				'content_field'  => 'button1_text',
			],
			'ultimate_expandable'        => [
				'universal_type' => 'accordion',
				'category'       => 'interactive',
				'content_field'  => 'title',
			],
			'ultimate_fancy_text'        => [
				'universal_type' => 'text',
				'category'       => 'content',
				'content_field'  => 'fancy_text',
			],
			'ultimate_highlight_box'     => [
				'universal_type' => 'callout',
				'category'       => 'content',
				'content_field'  => 'content',
			],
			'ultimate_content_box'       => [
				'universal_type' => 'container',
				'category'       => 'layout',
				'content_field'  => 'content',
			],
		];

		foreach ( $ultimate_elements as $element_type => $config ) {
			$this->register_element( $element_type, $config );
		}
	}

	/**
	 * Register a custom element
	 *
	 * @param string $element_type WPBakery element type.
	 * @param array  $config Element configuration.
	 */
	public function register_element( string $element_type, array $config ) {
		self::$custom_elements[ $element_type ] = $config;

		// Register type mapping
		if ( ! empty( $config['universal_type'] ) ) {
			self::$type_mappings[ $element_type ] = $config['universal_type'];
		}

		$this->logger->debug( 'Registered custom element', [
			'type' => $element_type,
			'universal_type' => $config['universal_type'] ?? 'unknown',
		]);
	}

	/**
	 * Check if element type is registered
	 *
	 * @param string $element_type Element type to check.
	 * @return bool
	 */
	public static function is_registered( string $element_type ): bool {
		return isset( self::$custom_elements[ $element_type ] );
	}

	/**
	 * Get element configuration
	 *
	 * @param string $element_type Element type.
	 * @return array|null Configuration or null if not found.
	 */
	public static function get_element_config( string $element_type ): ?array {
		return self::$custom_elements[ $element_type ] ?? null;
	}

	/**
	 * Get universal type mapping for element
	 *
	 * @param string $element_type WPBakery element type.
	 * @return string|null Universal type or null.
	 */
	public static function get_universal_type( string $element_type ): ?string {
		return self::$type_mappings[ $element_type ] ?? null;
	}

	/**
	 * Get content field name for element
	 *
	 * @param string $element_type Element type.
	 * @return string|null Content field name or null.
	 */
	public static function get_content_field( string $element_type ): ?string {
		$config = self::get_element_config( $element_type );
		return $config['content_field'] ?? null;
	}

	/**
	 * Get category for element
	 *
	 * @param string $element_type Element type.
	 * @return string Category name.
	 */
	public static function get_category( string $element_type ): string {
		$config = self::get_element_config( $element_type );
		return $config['category'] ?? 'general';
	}

	/**
	 * Get all registered elements
	 *
	 * @return array All custom elements.
	 */
	public static function get_all_elements(): array {
		return self::$custom_elements;
	}

	/**
	 * Unregister an element
	 *
	 * @param string $element_type Element type to unregister.
	 */
	public static function unregister_element( string $element_type ) {
		unset( self::$custom_elements[ $element_type ] );
		unset( self::$type_mappings[ $element_type ] );
	}

	/**
	 * Clear all registered elements
	 */
	public static function clear_all() {
		self::$custom_elements = [];
		self::$type_mappings = [];
	}

	/**
	 * Detect and auto-register elements from content
	 *
	 * @param string $content WPBakery shortcode content.
	 * @return array Detected element types.
	 */
	public function auto_detect_elements( string $content ): array {
		$detected = [];

		// Pattern to match shortcode tags
		preg_match_all( '/\[([a-zA-Z0-9_\-]+)/', $content, $matches );

		if ( ! empty( $matches[1] ) ) {
			$shortcodes = array_unique( $matches[1] );

			foreach ( $shortcodes as $shortcode ) {
				// Check if it's not a core vc_ element and not already registered
				if ( strpos( $shortcode, 'vc_' ) !== 0 && ! self::is_registered( $shortcode ) ) {
					$detected[] = $shortcode;

					// Auto-register with generic config
					$this->register_element( $shortcode, [
						'universal_type' => 'container',
						'category'       => 'custom',
						'content_field'  => 'content',
						'auto_detected'  => true,
					]);
				}
			}
		}

		if ( ! empty( $detected ) ) {
			$this->logger->info( 'Auto-detected custom elements', [
				'elements' => $detected,
			]);
		}

		return $detected;
	}

	/**
	 * Export registry configuration
	 *
	 * @return array Registry configuration.
	 */
	public static function export_config(): array {
		return [
			'elements' => self::$custom_elements,
			'mappings' => self::$type_mappings,
			'count'    => count( self::$custom_elements ),
		];
	}

	/**
	 * Import registry configuration
	 *
	 * @param array $config Configuration to import.
	 * @return bool Success.
	 */
	public static function import_config( array $config ): bool {
		if ( isset( $config['elements'] ) && is_array( $config['elements'] ) ) {
			self::$custom_elements = $config['elements'];
		}

		if ( isset( $config['mappings'] ) && is_array( $config['mappings'] ) ) {
			self::$type_mappings = $config['mappings'];
		}

		return true;
	}
}

// Initialize element registry
if ( class_exists( 'DEVTB_Logger' ) ) {
	new DEVTB_Element_Registry();
}
