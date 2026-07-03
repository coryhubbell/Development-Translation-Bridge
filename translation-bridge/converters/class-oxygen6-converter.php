<?php
/**
 * Oxygen 6 Converter (Breakdance-based schema).
 *
 * Emits the wrapped Oxygen 6 JSON payload:
 *
 *     {
 *       "tree": {
 *         "root": {
 *           "id": 1,
 *           "data": { "type": "root", "properties": [] },
 *           "children": [
 *             {
 *               "id": 2,
 *               "data": {
 *                 "type": "EssentialElements\\Section",
 *                 "properties": { "content": { "content": {...} }, "design": {...} }
 *               },
 *               "children": [ <node>, ... ],
 *               "_parentId": 1
 *             }
 *           ]
 *         }
 *       },
 *       "_nextNodeId": <int>
 *     }
 *
 * The node shape is VERIFIED against a real Breakdance element export
 * (tests/fixtures/oxygen6/breakdance-element-export.json); Oxygen 6 shares
 * ~80% of Breakdance's codebase. The namespace is centralized in
 * `ELEMENT_NAMESPACE` so a single change updates every emitted type if
 * Oxygen 6 ends up shipping its own prefix.
 *
 * @package DevelopmentTranslation_Bridge
 * @subpackage Translation_Bridge
 * @since 4.3.0
 */

namespace DEVTB\TranslationBridge\Converters;

use DEVTB\TranslationBridge\Core\DEVTB_Converter_Interface;
use DEVTB\TranslationBridge\Models\DEVTB_Component;
use DEVTB\TranslationBridge\Utils\DEVTB_Responsive_Helper;

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
		'link'           => 'TextLink',
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
		'pricing-table'  => 'PricingTable',
		'counter'        => 'Counter',
		'progress'       => 'ProgressBar',
		'map'            => 'Map',
		'alert'          => 'Alert',
		'social-icons'   => 'SocialIcons',
		'countdown'      => 'Countdown',
		'code'           => 'CodeBlock',
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
		$this->node_counter = 1; // id 1 is reserved for the root node.

		$components = is_array( $component ) ? $component : [ $component ];

		$children = [];
		foreach ( $components as $comp ) {
			if ( $comp instanceof DEVTB_Component ) {
				$node = $this->convert_component( $comp, 1 );
				if ( $node ) {
					$children[] = $node;
				}
			}
		}

		// Real _breakdance_data envelope: a `tree` object wrapping a `root`
		// node, plus the monotonic `_nextNodeId` collision-avoidance counter.
		$payload = [
			'tree'        => [
				'root' => [
					'id'       => 1,
					'data'     => [
						'type'       => 'root',
						'properties' => [],
					],
					'children' => $children,
				],
			],
			'_nextNodeId' => $this->node_counter + 1,
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
	public function convert_component( DEVTB_Component $component, int $parent_id = 1 ): ?array {
		$local = $this->type_map[ $component->type ] ?? null;
		if ( $local === null ) {
			return null;
		}

		$properties = $this->build_properties( $local, $component );

		// Verified against a real Breakdance export: integer ids, the element
		// payload nested under `data`, and a `_parentId` back-reference.
		$node_id = $this->generate_id();
		$node    = [
			'id'        => $node_id,
			'data'      => [
				'type'       => self::ELEMENT_NAMESPACE . $local,
				'properties' => $properties,
			],
			'children'  => [],
			'_parentId' => $parent_id,
		];

		if ( ! empty( $component->children ) ) {
			foreach ( $component->children as $child ) {
				$child_node = $this->convert_component( $child, $node_id );
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

		$fields = [];

		switch ( $local ) {
			case 'Heading':
				$fields['text'] = $content !== '' ? $content : (string) ( $attrs['heading'] ?? $attrs['title'] ?? '' );
				// Real exports use the plural `tags` key for the heading tag.
				$fields['tags'] = (string) ( $attrs['level'] ?? $attrs['tag'] ?? 'h2' );
				break;
			case 'Text':
			case 'RichText':
				$fields['text'] = $content !== '' ? $content : (string) ( $attrs['text'] ?? '' );
				break;
			case 'Button':
			case 'TextLink':
				$fields['text'] = $content !== '' ? $content : (string) ( $attrs['label'] ?? $attrs['text'] ?? '' );
				if ( isset( $attrs['url'] ) ) {
					$fields['link'] = [
						'url'    => (string) $attrs['url'],
						'target' => isset( $attrs['target'] ) ? (string) $attrs['target'] : '_self',
					];
				}
				break;
			case 'Image':
				$fields['src'] = (string) ( $attrs['image_url'] ?? $attrs['src'] ?? '' );
				$fields['alt'] = (string) ( $attrs['alt_text'] ?? $attrs['alt'] ?? '' );
				break;
			case 'Icon':
				$fields['icon'] = (string) ( $attrs['icon'] ?? '' );
				break;
			case 'CodeBlock':
				// Real CodeBlock exports carry php_code / javascript_code;
				// php_code accepts raw HTML too.
				$fields['php_code'] = $content;
				break;
			default:
				if ( $content !== '' ) {
					$fields['text'] = $content;
				}
				break;
		}

		// Pass through any extra universal attributes that didn't map to a
		// well-known field, so they aren't silently dropped.
		foreach ( $attrs as $key => $value ) {
			if ( ! array_key_exists( $key, $fields ) && is_scalar( $value ) ) {
				$fields[ $key ] = $value;
			}
		}

		// Verified property layout: content fields nest under content.content;
		// design data sits alongside in its own section.
		$properties = [];
		if ( ! empty( $fields ) ) {
			$properties['content'] = [ 'content' => $fields ];
		}

		// Responsive round-trip: rebuild the design tree with breakpoint_*
		// leaves from canonical responsive metadata when present; otherwise
		// fall back to the flat styles bag.
		$metadata  = is_array( $component->metadata ?? null ) ? $component->metadata : [];
		$canonical = $metadata[ DEVTB_Responsive_Helper::METADATA_KEY ]['styles'] ?? null;
		if ( is_array( $canonical ) ) {
			$design = DEVTB_Responsive_Helper::canonical_to_oxygen6_design( $canonical );
			if ( $design !== [] ) {
				$properties['design'] = $design;
			}
		} elseif ( ! empty( $styles ) ) {
			$properties['design'] = $styles;
		}

		return $properties;
	}

	/**
	 * Generate a monotonically increasing integer node id.
	 *
	 * @return int
	 */
	private function generate_id(): int {
		$this->node_counter++;
		return $this->node_counter;
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

		$confidence = 0.85; // Base — node shape verified against a real Breakdance export; Oxygen 6-specific deltas may remain.

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
			'id'        => $this->generate_id(),
			'data'      => [
				'type'       => self::ELEMENT_NAMESPACE . 'Text',
				'properties' => [
					'content' => [
						'content' => [
							'text' => $component->content !== '' ? $component->content : 'Unsupported component: ' . $component->type,
						],
					],
				],
			],
			'children'  => [],
			'_parentId' => 1,
		];
	}
}
