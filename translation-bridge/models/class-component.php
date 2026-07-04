<?php
/**
 * Universal Component Model
 *
 * Intermediate representation for components across all frameworks.
 * This allows translation between any framework by converting to/from this universal format.
 *
 * @package DevelopmentTranslation_Bridge
 * @subpackage Translation_Bridge
 * @since 3.0.0
 */

namespace DEVTB\TranslationBridge\Models;

/**
 * Class DEVTB_Component
 *
 * Universal component model that represents elements from any framework
 * (Bootstrap, DIVI, Elementor, Avada, Bricks) in a standardized format.
 */
class DEVTB_Component {

	/**
	 * Unique component identifier
	 *
	 * @var string
	 */
	public string $id;

	/**
	 * Component type (button, card, container, heading, etc.)
	 *
	 * @var string
	 */
	public string $type;

	/**
	 * Component category (layout, content, media, interactive, etc.)
	 *
	 * @var string
	 */
	public string $category;

	/**
	 * Universal attributes
	 *
	 * @var array<string, mixed>
	 */
	public array $attributes;

	/**
	 * CSS styles and properties
	 *
	 * @var array<string, mixed>
	 */
	public array $styles;

	/**
	 * Child components (nested elements)
	 *
	 * @var DEVTB_Component[]
	 */
	public array $children;

	/**
	 * Inner content (text, HTML, etc.)
	 *
	 * @var string
	 */
	public string $content;

	/**
	 * Framework-specific metadata
	 *
	 * @var array<string, mixed>
	 */
	public array $metadata;

	/**
	 * Component constructor
	 *
	 * @param array<string, mixed> $data Component data.
	 */
	public function __construct( array $data = [] ) {
		$this->id         = $data['id'] ?? $this->generate_id();
		$this->type       = $data['type'] ?? 'unknown';
		$this->category   = $data['category'] ?? 'general';
		$this->attributes = $data['attributes'] ?? [];
		$this->styles     = $data['styles'] ?? [];
		$this->children   = $data['children'] ?? [];
		$this->content    = $data['content'] ?? '';
		$this->metadata   = $data['metadata'] ?? [];
	}

	/**
	 * Generate unique component ID
	 *
	 * @return string
	 */
	private function generate_id(): string {
		return 'devtb_' . uniqid( '', true );
	}

	/**
	 * Add child component
	 *
	 * @param DEVTB_Component $component Child component to add.
	 * @return void
	 */
	public function add_child( DEVTB_Component $component ): void {
		$this->children[] = $component;
	}

	/**
	 * Get attribute value
	 *
	 * @param string $key Attribute key.
	 * @param mixed  $default Default value if not found.
	 * @return mixed
	 */
	public function get_attribute( string $key, $default = null ) {
		return $this->attributes[ $key ] ?? $default;
	}

	/**
	 * Set attribute value
	 *
	 * @param string $key Attribute key.
	 * @param mixed  $value Attribute value.
	 * @return void
	 */
	public function set_attribute( string $key, $value ): void {
		$this->attributes[ $key ] = $value;
	}

	/**
	 * Get style value
	 *
	 * @param string $key Style property key.
	 * @param mixed  $default Default value if not found.
	 * @return mixed
	 */
	public function get_style( string $key, $default = null ) {
		return $this->styles[ $key ] ?? $default;
	}

	/**
	 * Set style value
	 *
	 * @param string $key Style property key.
	 * @param mixed  $value Style value.
	 * @return void
	 */
	public function set_style( string $key, $value ): void {
		$this->styles[ $key ] = $value;
	}

	/**
	 * Get metadata value
	 *
	 * @param string $key Metadata key.
	 * @param mixed  $default Default value if not found.
	 * @return mixed
	 */
	public function get_metadata( string $key, $default = null ) {
		return $this->metadata[ $key ] ?? $default;
	}

	/**
	 * Set metadata value
	 *
	 * @param string $key Metadata key.
	 * @param mixed  $value Metadata value.
	 * @return void
	 */
	public function set_metadata( string $key, $value ): void {
		$this->metadata[ $key ] = $value;
	}

	/**
	 * Check if component has children
	 *
	 * @return bool
	 */
	public function has_children(): bool {
		return ! empty( $this->children );
	}

	/**
	 * Get all children
	 *
	 * @return DEVTB_Component[]
	 */
	public function get_children(): array {
		return $this->children;
	}

	/**
	 * Convert component to array
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return [
			'id'         => $this->id,
			'type'       => $this->type,
			'category'   => $this->category,
			'attributes' => $this->attributes,
			'styles'     => $this->styles,
			'children'   => array_map( fn( $child ) => $child->to_array(), $this->children ),
			'content'    => $this->content,
			'metadata'   => $this->metadata,
		];
	}

	/**
	 * Universal component type → widgetType in the canonical schema.
	 *
	 * @var array<string, string>
	 */
	private const UNIVERSAL_WIDGET_TYPES = [
		'text'          => 'text-editor',
		'paragraph'     => 'text-editor',
		'heading'       => 'heading',
		'image'         => 'image',
		'button'        => 'button',
		'link'          => 'button',
		'list'          => 'icon-list',
		'gallery'       => 'image-gallery',
		'video'         => 'video',
		'audio'         => 'audio',
		'divider'       => 'divider',
		'spacer'        => 'spacer',
		'blockquote'    => 'testimonial',
		'quote'         => 'testimonial',
		'testimonial'   => 'testimonial',
		'html'          => 'html',
		'code'          => 'html',
		'icon'          => 'icon',
		'card'          => 'icon-box',
		'cta'           => 'call-to-action',
		'counter'       => 'counter',
		'pricing-table' => 'price-table',
		'alert'         => 'alert',
		'tabs'          => 'tabs',
		'accordion'     => 'accordion',
		'toggle'        => 'accordion',
		'slider'        => 'slides',
		'slide'         => 'slides',
		'social-icons'  => 'social-icons',
		'nav'           => 'nav',
		'menu'          => 'nav',
		'form'          => 'form',
		'countdown'     => 'countdown',
		'map'           => 'google_maps',
		'progress'      => 'progress',
	];

	/**
	 * Convert to the canonical universal element dict (RFC 5.0, Phase 1).
	 *
	 * Emits the shared interchange shape both engines conform to — see
	 * schema/universal-element.schema.json. Universal attribute names
	 * (label/url/heading/image_url/...) translate to the Elementor-style
	 * setting vocabulary; canonical responsive metadata carries through.
	 *
	 * @param bool $is_inner Whether this element nests inside another.
	 * @return array<string, mixed>
	 */
	public function to_universal( bool $is_inner = false ): array {
		$structural = [
			'container' => 'section',
			'section'   => 'section',
			'row'       => 'container',
			'column'    => 'column',
		];

		if ( isset( $structural[ $this->type ] ) ) {
			$el_type = $structural[ $this->type ];
			if ( $is_inner && $el_type === 'section' ) {
				$el_type = 'container';
			}
			$element = [
				'id'       => (string) $this->id,
				'elType'   => $el_type,
				'settings' => (object) [],
				'elements' => [],
			];
		} else {
			$widget_type = self::UNIVERSAL_WIDGET_TYPES[ $this->type ] ?? 'html';
			$settings    = $this->universal_settings( $widget_type );
			$element     = [
				'id'         => (string) $this->id,
				'elType'     => 'widget',
				'widgetType' => $widget_type,
				'settings'   => $settings === [] ? (object) [] : $settings,
				'elements'   => [],
			];
		}

		if ( $is_inner ) {
			$element['isInner'] = true;
		}

		$responsive = $this->metadata['responsive'] ?? null;
		if ( is_array( $responsive ) && $responsive !== [] ) {
			$element['responsive'] = $responsive;
		}

		foreach ( $this->children as $child ) {
			$element['elements'][] = $child->to_universal( true );
		}

		return $element;
	}

	/**
	 * Build Elementor-style settings from universal attributes + content.
	 *
	 * @param string $widget_type Canonical widgetType.
	 * @return array<string, mixed>
	 */
	private function universal_settings( string $widget_type ): array {
		$attrs   = $this->attributes;
		$content = trim( (string) $this->content );
		$out     = [];

		switch ( $widget_type ) {
			case 'heading':
				$out['title']       = $content !== '' ? $content : (string) ( $attrs['heading'] ?? ( $attrs['title'] ?? '' ) );
				$level              = $attrs['level'] ?? 2;
				$out['header_size'] = is_string( $level ) && str_starts_with( $level, 'h' ) ? $level : 'h' . (int) $level;
				break;
			case 'text-editor':
				$out['editor'] = $content !== '' ? $content : (string) ( $attrs['text'] ?? '' );
				break;
			case 'button':
				$out['text'] = $content !== '' ? $content : (string) ( $attrs['label'] ?? ( $attrs['text'] ?? '' ) );
				$url         = (string) ( $attrs['url'] ?? ( $attrs['href'] ?? '' ) );
				if ( $url !== '' ) {
					$out['link'] = [ 'url' => $url ];
					if ( ( $attrs['target'] ?? '' ) === '_blank' ) {
						$out['link']['is_external'] = 'on';
					}
				}
				break;
			case 'image':
				$out['image'] = [ 'url' => (string) ( $attrs['image_url'] ?? ( $attrs['src'] ?? '' ) ) ];
				$alt          = (string) ( $attrs['alt_text'] ?? ( $attrs['alt'] ?? '' ) );
				if ( $alt !== '' ) {
					$out['image']['alt'] = $alt;
				}
				break;
			case 'testimonial':
				$out['testimonial_content'] = $content !== '' ? $content : (string) ( $attrs['testimonial_content'] ?? '' );
				$name                       = (string) ( $attrs['author'] ?? ( $attrs['testimonial_name'] ?? ( $attrs['cite'] ?? '' ) ) );
				if ( $name !== '' ) {
					$out['testimonial_name'] = $name;
				}
				$job = (string) ( $attrs['job_title'] ?? ( $attrs['testimonial_job'] ?? '' ) );
				if ( $job !== '' ) {
					$out['testimonial_job'] = $job;
				}
				break;
			case 'icon-box':
				$out['title_text']       = (string) ( $attrs['heading'] ?? ( $attrs['title'] ?? ( $attrs['title_text'] ?? '' ) ) );
				$out['description_text'] = $content !== '' ? $content : (string) ( $attrs['description'] ?? '' );
				break;
			case 'call-to-action':
				$out['title']       = (string) ( $attrs['heading'] ?? ( $attrs['title'] ?? '' ) );
				$out['description'] = $content;
				if ( ! empty( $attrs['label'] ) ) {
					$out['button_text'] = (string) $attrs['label'];
				}
				break;
			case 'accordion':
			case 'tabs':
				$items = $attrs['tabs'] ?? ( $attrs['items'] ?? null );
				if ( is_string( $items ) ) {
					$decoded = json_decode( $items, true );
					$items   = is_array( $decoded ) ? $decoded : null;
				}
				if ( is_array( $items ) ) {
					$out['tabs'] = $items;
				} elseif ( ! empty( $attrs['heading'] ) || $content !== '' ) {
					$out['tabs'] = [ [ 'tab_title' => (string) ( $attrs['heading'] ?? '' ), 'tab_content' => $content ] ];
				}
				break;
			case 'counter':
				$out['title'] = (string) ( $attrs['heading'] ?? ( $attrs['title'] ?? $content ) );
				if ( ! empty( $attrs['number'] ) ) {
					$out['ending_number'] = (string) $attrs['number'];
				}
				break;
			case 'html':
				$out['html'] = $content !== '' ? $content : (string) ( $attrs['html'] ?? ( $attrs['code'] ?? '' ) );
				break;
			case 'video':
				$out['youtube_url'] = (string) ( $attrs['url'] ?? ( $attrs['src'] ?? ( $attrs['image_url'] ?? '' ) ) );
				break;
			default:
				if ( $content !== '' ) {
					$out['text'] = $content;
				}
				if ( ! empty( $attrs['heading'] ) ) {
					$out['title'] = (string) $attrs['heading'];
				}
				break;
		}

		return $out;
	}

	/**
	 * Create component from array
	 *
	 * @param array<string, mixed> $data Component data.
	 * @return DEVTB_Component
	 */
	public static function from_array( array $data ): DEVTB_Component {
		// Convert children arrays back to components
		if ( isset( $data['children'] ) && is_array( $data['children'] ) ) {
			$data['children'] = array_map(
				fn( $child_data ) => self::from_array( $child_data ),
				$data['children']
			);
		}

		return new self( $data );
	}

	/**
	 * Convert to JSON
	 *
	 * @return string
	 */
	public function to_json(): string {
		return json_encode( $this->to_array(), JSON_PRETTY_PRINT );
	}

	/**
	 * Create component from JSON
	 *
	 * @param string $json JSON string.
	 * @return DEVTB_Component|null
	 */
	public static function from_json( string $json ): ?DEVTB_Component {
		$data = json_decode( $json, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return null;
		}

		return self::from_array( $data );
	}

	/**
	 * Clone component with new ID
	 *
	 * @return DEVTB_Component
	 */
	public function duplicate(): DEVTB_Component {
		$clone     = clone $this;
		$clone->id = $this->generate_id();

		// Clone children recursively
		$clone->children = array_map(
			fn( $child ) => $child->duplicate(),
			$this->children
		);

		return $clone;
	}

	/**
	 * Get source framework from metadata
	 *
	 * @return string|null
	 */
	public function get_source_framework(): ?string {
		return $this->get_metadata( 'source_framework' );
	}

	/**
	 * Check if component is from specific framework
	 *
	 * @param string $framework Framework name (bootstrap, divi, elementor, avada, bricks).
	 * @return bool
	 */
	public function is_from_framework( string $framework ): bool {
		return $this->get_source_framework() === strtolower( $framework );
	}

	/**
	 * Validate component structure
	 *
	 * @return bool
	 */
	public function is_valid(): bool {
		// Must have valid type
		if ( empty( $this->type ) || $this->type === 'unknown' ) {
			return false;
		}

		// Must have valid category
		if ( empty( $this->category ) ) {
			return false;
		}

		// All children must be valid
		foreach ( $this->children as $child ) {
			if ( ! $child->is_valid() ) {
				return false;
			}
		}

		return true;
	}
}
