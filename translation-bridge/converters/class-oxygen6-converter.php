<?php
/**
 * Oxygen 6 Converter (Breakdance-proxy schema).
 *
 * Emits the wrapped Oxygen 6 JSON payload:
 *
 *     {
 *       "_version": 1,
 *       "_nextNodeId": <int>,
 *       "tree": [
 *         {
 *           "id": "n-1",
 *           "type": "EssentialElements\\Section",
 *           "properties": {...},
 *           "children": [ <node>, ... ]
 *         }
 *       ]
 *     }
 *
 * The schema is a proxy based on Breakdance's `_breakdance_data` format
 * (Oxygen 6 shares ~80% of Breakdance's codebase). Element type namespace and
 * property key conventions are draft assumptions until verified against a real
 * Oxygen 6 export. The namespace is centralized in `ELEMENT_NAMESPACE` so a
 * single change updates every emitted type.
 *
 * @package DevelopmentTranslation_Bridge
 * @subpackage Translation_Bridge
 * @since 4.3.0
 */

namespace DEVTB\TranslationBridge\Converters;

use DEVTB\TranslationBridge\Core\DEVTB_Converter_Interface;
use DEVTB\TranslationBridge\Models\DEVTB_Component;

/**
 * Class DEVTB_Oxygen6_Converter
 *
 * Convert universal components to Oxygen 6 JSON trees.
 */
class DEVTB_Oxygen6_Converter implements DEVTB_Converter_Interface {

	/**
	 * Upstream framework version this converter is calibrated against.
	 *
	 * Oxygen 6.0.0 is the first stable release of the rewrite; classic Oxygen
	 * (4.8.x and earlier) is handled by DEVTB_Oxygen_Converter.
	 */
	public const TARGET_CMS_VERSION = '6.0.0';

	/**
	 * Namespaced element prefix used by Breakdance/Oxygen 6 (proxy).
	 */
	public const ELEMENT_NAMESPACE = 'EssentialElements\\';

	/**
	 * @inheritDoc
	 */
	public function get_target_cms_version(): string {
		return self::TARGET_CMS_VERSION;
	}

	/**
	 * Universal type → local Oxygen 6 element name (namespace prepended on emit).
	 *
	 * @var array<string, string>
	 */
	private array $type_map = [
		'container'      => 'Section',
		'row'            => 'Section',
		'column'         => 'Div',
		'text'           => 'Text',
		'heading'        => 'Heading',
		'image'          => 'Image',
		'button'         => 'Button',
		'link'           => 'Link',
		'icon'           => 'Icon',
		'video'          => 'Video',
		'audio'          => 'Audio',
		'divider'        => 'Divider',
		'spacer'         => 'Spacer',
		'list'           => 'List',
		'accordion'      => 'Accordion',
		'tabs'           => 'Tabs',
		'form'           => 'Form',
		'gallery'        => 'Gallery',
		'slider'         => 'Slider',
		'card'           => 'Card',
		'testimonial'    => 'Testimonial',
		'pricing-table'  => 'Pricing',
		'counter'        => 'Counter',
		'progress'       => 'Progress',
		'map'            => 'Map',
		'alert'          => 'Alert',
		'social-icons'   => 'SocialIcons',
		'countdown'      => 'Countdown',
		'code'           => 'Code',
		'nav'            => 'Menu',
		'menu'           => 'Menu',
		'blockquote'     => 'Text',
		'cta'            => 'Card',
		'toc'            => 'List',
	];

	/**
	 * Monotonic node id counter; mirrors Oxygen 6/Breakdance's `_nextNodeId`.
	 *
	 * @var int
	 */
	private int $node_counter = 0;

	/**
	 * Convert universal component(s) to an Oxygen 6 JSON payload.
	 *
	 * @param DEVTB_Component|array $component Component or array of components.
	 * @return string Pretty-printed JSON.
	 */
	public function convert( $component ) {
		$this->node_counter = 0;

		$components = is_array( $component ) ? $component : [ $component ];

		$tree = [];
		foreach ( $components as $comp ) {
			if ( $comp instanceof DEVTB_Component ) {
				$node = $this->convert_component( $comp );
				if ( $node ) {
					$tree[] = $node;
				}
			}
		}

		$payload = [
			'_version'    => 1,
			'_nextNodeId' => $this->node_counter + 1,
			'tree'        => $tree,
		];

		return wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	}

	/**
	 * Convert a single universal component (and its children) to a nested node.
	 *
	 * Unlike the flat Bricks/Classic-Oxygen registries, Oxygen 6 trees nest
	 * children directly inside the parent node — so this method returns the
	 * full subtree rather than registering into a side table.
	 *
	 * @param DEVTB_Component $component Component to convert.
	 * @return array<string, mixed>|null Node array, or null if the type cannot be mapped.
	 */
	public function convert_component( DEVTB_Component $component ): ?array {
		$local = $this->type_map[ $component->type ] ?? null;
		if ( $local === null ) {
			return null;
		}

		$properties = $this->build_properties( $local, $component );

		$node = [
			'id'         => $this->generate_id(),
			'type'       => self::ELEMENT_NAMESPACE . $local,
			'properties' => $properties,
			'children'   => [],
		];

		if ( ! empty( $component->children ) ) {
			foreach ( $component->children as $child ) {
				$child_node = $this->convert_component( $child );
				if ( $child_node ) {
					$node['children'][] = $child_node;
				}
			}
		}

		return $node;
	}

	/**
	 * Build the Oxygen 6 `properties` object for an element.
	 *
	 * Content fields land at the top level (`text`, `title`, `code`...) to mirror
	 * the documented "content vs. design" split; visual style data goes under
	 * `design` so the proxy round-trips without colliding with content keys.
	 *
	 * @param string         $local     Local element name.
	 * @param DEVTB_Component $component Component being converted.
	 * @return array<string, mixed>
	 */
	private function build_properties( string $local, DEVTB_Component $component ): array {
		$attrs    = is_array( $component->attributes ?? null ) ? $component->attributes : [];
		$styles   = is_array( $component->styles ?? null ) ? $component->styles : [];
		$content  = isset( $component->content ) ? (string) $component->content : '';

		$properties = [];

		switch ( $local ) {
			case 'Heading':
				$properties['text'] = $content !== '' ? $content : (string) ( $attrs['heading'] ?? $attrs['title'] ?? '' );
				$properties['tag']  = (string) ( $attrs['level'] ?? $attrs['tag'] ?? 'h2' );
				break;
			case 'Text':
			case 'RichText':
				$properties['text'] = $content !== '' ? $content : (string) ( $attrs['text'] ?? '' );
				break;
			case 'Button':
			case 'Link':
				$properties['text'] = $content !== '' ? $content : (string) ( $attrs['label'] ?? $attrs['text'] ?? '' );
				if ( isset( $attrs['url'] ) ) {
					$properties['link'] = [
						'url'    => (string) $attrs['url'],
						'target' => isset( $attrs['target'] ) ? (string) $attrs['target'] : '_self',
					];
				}
				break;
			case 'Image':
				$properties['src'] = (string) ( $attrs['image_url'] ?? $attrs['src'] ?? '' );
				$properties['alt'] = (string) ( $attrs['alt_text'] ?? $attrs['alt'] ?? '' );
				break;
			case 'Icon':
				$properties['icon'] = (string) ( $attrs['icon'] ?? '' );
				break;
			case 'Code':
				$properties['code'] = $content;
				break;
			default:
				if ( $content !== '' ) {
					$properties['text'] = $content;
				}
				break;
		}

		// Pass through any extra universal attributes that didn't map to a
		// well-known property, so they aren't silently dropped.
		foreach ( $attrs as $key => $value ) {
			if ( ! array_key_exists( $key, $properties ) && is_scalar( $value ) ) {
				$properties[ $key ] = $value;
			}
		}

		if ( ! empty( $styles ) ) {
			$properties['design'] = $styles;
		}

		return $properties;
	}

	/**
	 * Generate a deterministic node id ("n-1", "n-2", ...) and advance the counter.
	 *
	 * @return string
	 */
	private function generate_id(): string {
		$this->node_counter++;
		return 'n-' . $this->node_counter;
	}

	/**
	 * @inheritDoc
	 */
	public function get_framework(): string {
		return 'oxygen-6';
	}

	/**
	 * @inheritDoc
	 */
	public function get_supported_types(): array {
		return array_keys( $this->type_map );
	}

	/**
	 * @inheritDoc
	 */
	public function can_convert( DEVTB_Component $component ): bool {
		return isset( $this->type_map[ $component->type ] );
	}

	/**
	 * @inheritDoc
	 */
	public function get_confidence( DEVTB_Component $component ): float {
		if ( ! $this->can_convert( $component ) ) {
			return 0.0;
		}

		$confidence = 0.75; // Base — proxy schema, real Oxygen 6 fixture not yet verified.

		if ( isset( $component->metadata['source_framework'] ) && $component->metadata['source_framework'] === 'oxygen-6' ) {
			$confidence = 0.95;
		}

		return $confidence;
	}

	/**
	 * @inheritDoc
	 */
	public function supports_type( string $type ): bool {
		return isset( $this->type_map[ $type ] );
	}

	/**
	 * @inheritDoc
	 */
	public function get_fallback( DEVTB_Component $component ) {
		return [
			'id'         => $this->generate_id(),
			'type'       => self::ELEMENT_NAMESPACE . 'Text',
			'properties' => [
				'text' => $component->content !== '' ? $component->content : 'Unsupported component: ' . $component->type,
			],
			'children'   => [],
		];
	}
}
