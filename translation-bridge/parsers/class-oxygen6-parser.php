<?php
/**
 * Oxygen 6 Parser (Breakdance-based schema).
 *
 * Oxygen 6 is a ground-up rewrite built on the Breakdance codebase (~80% shared).
 * Classic Oxygen (ct_* shortcode vocabulary, ct_parent linkage) is incompatible
 * with Oxygen 6 per Oxygen's own migration docs. The node shape accepted here
 * is VERIFIED against a real Breakdance element export
 * (tests/fixtures/oxygen6/breakdance-element-export.json):
 *
 * - Data lives in `_breakdance_data` post meta (or an equivalent Oxygen 6 alias),
 *   with a `tree` object wrapping a `root` node.
 * - Node ids are integers; children carry a `_parentId` back-reference.
 * - Each node nests `type` + `properties` under a `data` key, e.g.
 *   `{"id": 102, "data": {"type": "EssentialElements\Heading", "properties":
 *   {...}}, "children": [...], "_parentId": 101}`.
 * - Content fields nest under `properties.content.content` (heading tag key is
 *   the plural `tags`); design/meta/settings are sibling sections.
 * - A `_nextNodeId` counter avoids collisions when injecting elements.
 *
 * The legacy proxy shape (flat `type`/`properties` at the node top level) is
 * still accepted for back-compat. An Oxygen 6-specific namespace variant, if
 * one emerges, is handled by namespace-agnostic local-name lookup below.
 *
 * @package DevelopmentTranslation_Bridge
 * @subpackage Translation_Bridge
 * @since 4.3.0
 */

namespace DEVTB\TranslationBridge\Parsers;

use DEVTB\TranslationBridge\Core\DEVTB_Parser_Interface;
use DEVTB\TranslationBridge\Models\DEVTB_Component;

/**
 * Class DEVTB_Oxygen6_Parser
 *
 * Parse Oxygen 6 JSON trees into universal components.
 */
class DEVTB_Oxygen6_Parser implements DEVTB_Parser_Interface {

	/**
	 * Namespaced Oxygen 6 / Breakdance element prefix used by the proxy schema.
	 *
	 * Real Oxygen 6 exports may use `OxygenElements\\` or another namespace; the
	 * parser also accepts unprefixed local names so this constant only governs
	 * the canonical lookup table.
	 */
	public const ELEMENT_NAMESPACE = 'EssentialElements\\';

	/**
	 * Local element name → universal component type.
	 *
	 * Lookup uses the local name (the segment after the last backslash) so the
	 * map keeps working if Oxygen 6 ends up using a different namespace prefix.
	 *
	 * @var array<string, string>
	 */
	private array $type_map = [
		'Section'       => 'container',
		'Container'     => 'container',
		'Div'           => 'container',
		'Block'         => 'container',
		'Column'        => 'column',
		'Heading'       => 'heading',
		'Text'          => 'text',
		'RichText'      => 'text',
		'Paragraph'     => 'text',
		'Image'         => 'image',
		'Video'         => 'video',
		'Audio'         => 'audio',
		'Button'        => 'button',
		'Link'          => 'button',
		'Icon'          => 'icon',
		'IconBox'       => 'card',
		'Card'          => 'card',
		'Divider'       => 'divider',
		'Separator'     => 'divider',
		'Spacer'        => 'spacer',
		'List'          => 'list',
		'Accordion'     => 'accordion',
		'Tabs'          => 'tabs',
		'Form'          => 'form',
		'Gallery'       => 'gallery',
		'Slider'        => 'slider',
		'Carousel'      => 'slider',
		'Menu'          => 'nav',
		'NavMenu'       => 'nav',
		'Testimonial'   => 'testimonial',
		'Pricing'       => 'pricing-table',
		'PricingTable'  => 'pricing-table',
		'Counter'       => 'counter',
		'Progress'      => 'progress',
		'ProgressBar'   => 'progress',
		'Map'           => 'map',
		'Alert'         => 'alert',
		'SocialIcons'   => 'social-icons',
		'Countdown'     => 'countdown',
		'Code'          => 'code',
		'CodeBlock'     => 'code',
		'TextLink'      => 'button',
		'Columns'       => 'row',
		'PostsLoop'     => 'container',
	];

	/**
	 * Parse Oxygen 6 JSON tree into universal components.
	 *
	 * Accepts:
	 *   - the wrapped form `{"tree":[...], "_nextNodeId":N}` (top-level payload)
	 *   - a bare array of root nodes
	 *   - a single root node object
	 *
	 * @param string|array $content Oxygen 6 JSON content.
	 * @return DEVTB_Component[]
	 */
	public function parse( $content ): array {
		if ( is_string( $content ) ) {
			$decoded = json_decode( $content, true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				return [];
			}
			$content = $decoded;
		}

		if ( ! is_array( $content ) ) {
			return [];
		}

		$nodes = $this->extract_root_nodes( $content );

		$components = [];
		foreach ( $nodes as $node ) {
			$component = $this->parse_element( $node );
			if ( $component ) {
				$components[] = $component;
			}
		}

		return $components;
	}

	/**
	 * Normalize the various accepted payload shapes into a list of root nodes.
	 *
	 * @param array $content Decoded JSON payload.
	 * @return array<int, array<string, mixed>>
	 */
	private function extract_root_nodes( array $content ): array {
		if ( isset( $content['tree'] ) && is_array( $content['tree'] ) ) {
			$tree = $content['tree'];

			// Real _breakdance_data shape: tree wraps a `root` node whose
			// children are the page elements. The root node itself carries
			// `data.type === "root"` and is not a renderable element.
			if ( isset( $tree['root'] ) && is_array( $tree['root'] ) ) {
				$root = $tree['root'];
				if ( isset( $root['children'] ) && is_array( $root['children'] ) ) {
					return array_values( array_filter( $root['children'], 'is_array' ) );
				}
				return $this->looks_like_node( $root ) ? [ $root ] : [];
			}

			return $this->looks_like_node( $tree ) ? [ $tree ] : array_values( array_filter( $tree, 'is_array' ) );
		}

		if ( $this->looks_like_node( $content ) ) {
			return [ $content ];
		}

		// Element-copy envelope: { "source": ..., "element": { node } }.
		if ( isset( $content['element'] ) && is_array( $content['element'] ) && $this->looks_like_node( $content['element'] ) ) {
			return [ $content['element'] ];
		}

		// Bare list of nodes.
		return array_values( array_filter( $content, 'is_array' ) );
	}

	/**
	 * Heuristic: a node is an associative array with a `type` key, either at
	 * the top level (legacy proxy shape) or nested under `data` (real
	 * Breakdance/Oxygen 6 shape).
	 *
	 * @param array $candidate Candidate array.
	 * @return bool
	 */
	private function looks_like_node( array $candidate ): bool {
		return isset( $candidate['type'] ) || isset( $candidate['data']['type'] );
	}

	/**
	 * Parse a single Oxygen 6 element node into a universal component, recursing
	 * into nested `children` (the canonical Oxygen 6 hierarchy key).
	 *
	 * @param mixed $element Node array.
	 * @return DEVTB_Component|null
	 */
	public function parse_element( $element ): ?DEVTB_Component {
		if ( ! is_array( $element ) ) {
			return null;
		}

		// Real Breakdance/Oxygen 6 nodes nest type + properties under `data`;
		// the legacy proxy shape carried them at the node top level.
		$data      = isset( $element['data'] ) && is_array( $element['data'] ) ? $element['data'] : $element;
		$type_full = isset( $data['type'] ) ? (string) $data['type'] : '';
		if ( $type_full === '' ) {
			return null;
		}

		$local_type     = $this->local_name( $type_full );
		$universal_type = $this->type_map[ $local_type ] ?? 'unknown';

		$properties = isset( $data['properties'] ) && is_array( $data['properties'] ) ? $data['properties'] : [];
		$flat       = $this->flatten_properties( $properties );

		$attributes = $this->normalize_properties( $flat );
		$content    = $this->extract_content( $local_type, $flat );
		$category   = $this->get_category( $universal_type );

		$component = new DEVTB_Component( [
			'type'       => $universal_type,
			'category'   => $category,
			'attributes' => $attributes,
			'content'    => $content,
			'metadata'   => [
				'source_framework'  => 'oxygen-6',
				'original_type'     => $type_full,
				'oxygen6_id'        => $element['id'] ?? '',
				'oxygen6_properties' => $properties,
			],
		] );

		if ( isset( $element['children'] ) && is_array( $element['children'] ) ) {
			foreach ( $element['children'] as $child_element ) {
				$child = $this->parse_element( $child_element );
				if ( $child ) {
					$component->add_child( $child );
				}
			}
		}

		return $component;
	}

	/**
	 * Strip any namespace prefix from a fully qualified element type.
	 *
	 * @param string $type Fully qualified type, e.g. `EssentialElements\Heading`.
	 * @return string Local name segment.
	 */
	private function local_name( string $type ): string {
		$pos = strrpos( $type, '\\' );
		return $pos === false ? $type : substr( $type, $pos + 1 );
	}

	/**
	 * Flatten the sectioned Breakdance property bag into one content bag.
	 *
	 * Real exports group properties into `content` / `design` / `settings` /
	 * `meta` sections, with content fields nested one level deeper under
	 * `content.content` (e.g. heading text at `properties.content.content.text`).
	 * The legacy proxy shape carried content fields at the top level; both are
	 * accepted here. Design/meta/settings sections are excluded — they are
	 * preserved verbatim in metadata.
	 *
	 * @param array $properties Raw element properties.
	 * @return array Flat content-field bag.
	 */
	private function flatten_properties( array $properties ): array {
		$flat = [];

		// Legacy flat proxy shape: everything except known section keys.
		foreach ( $properties as $key => $value ) {
			if ( in_array( $key, [ 'content', 'design', 'settings', 'meta' ], true ) ) {
				continue;
			}
			$flat[ $key ] = $value;
		}

		// Real shape: properties.content.<section>.* — usually the section is
		// itself named `content`; merge every sub-section's fields.
		if ( isset( $properties['content'] ) && is_array( $properties['content'] ) ) {
			foreach ( $properties['content'] as $section ) {
				if ( is_array( $section ) ) {
					$flat = array_merge( $flat, $section );
				}
			}
		}

		return $flat;
	}

	/**
	 * Best-effort mapping from Oxygen 6 property bag to universal attributes.
	 *
	 * Real Oxygen 6 properties are CSS-mapped and split content vs. design. The
	 * proxy implementation flattens the common content fields; design data is
	 * preserved verbatim in metadata for round-trip fidelity.
	 *
	 * @param array $properties Element properties.
	 * @return array
	 */
	private function normalize_properties( array $properties ): array {
		$normalized = [];

		$attr_map = [
			'text'           => 'label',
			'title'          => 'heading',
			'subtitle'       => 'description',
			'description'    => 'description',
			'link'           => 'url',
			'url'            => 'url',
			'href'           => 'url',
			'tag'            => 'level',
			'tags'           => 'level',
			'level'          => 'level',
			'src'            => 'image_url',
			'image'          => 'image_url',
			'alt'            => 'alt_text',
			'icon'           => 'icon',
		];

		foreach ( $properties as $key => $value ) {
			$universal_key = $attr_map[ $key ] ?? $key;

			if ( is_array( $value ) ) {
				if ( isset( $value['url'] ) && is_string( $value['url'] ) ) {
					$normalized['url'] = $value['url'];
					if ( ! empty( $value['target'] ) ) {
						$normalized['target'] = (string) $value['target'];
					}
					continue;
				}
				$normalized[ $universal_key ] = wp_json_encode( $value );
			} else {
				$normalized[ $universal_key ] = $value;
			}
		}

		return $normalized;
	}

	/**
	 * Extract the human-readable content for an element from its properties.
	 *
	 * @param string $local_type Local element name (e.g. `Heading`).
	 * @param array  $properties Element properties.
	 * @return string
	 */
	private function extract_content( string $local_type, array $properties ): string {
		switch ( $local_type ) {
			case 'Heading':
				return (string) ( $properties['text'] ?? $properties['title'] ?? $properties['content'] ?? '' );
			case 'Text':
			case 'RichText':
			case 'Paragraph':
				return (string) ( $properties['text'] ?? $properties['content'] ?? $properties['html'] ?? '' );
			case 'Button':
			case 'Link':
			case 'TextLink':
				return (string) ( $properties['text'] ?? $properties['label'] ?? '' );
			case 'Code':
			case 'CodeBlock':
				return (string) ( $properties['php_code'] ?? $properties['code'] ?? $properties['html'] ?? '' );
			default:
				return (string) ( $properties['text'] ?? $properties['content'] ?? '' );
		}
	}

	/**
	 * Map universal type → category for downstream consumers.
	 *
	 * @param string $type Universal component type.
	 * @return string
	 */
	private function get_category( string $type ): string {
		$categories = [
			'layout'      => [ 'container', 'row', 'column', 'spacer' ],
			'content'     => [ 'text', 'heading', 'image', 'card', 'blockquote' ],
			'media'       => [ 'video', 'audio', 'gallery', 'slider' ],
			'interactive' => [ 'button', 'accordion', 'tabs', 'modal', 'toggle' ],
			'form'        => [ 'form', 'input', 'search' ],
			'data'        => [ 'counter', 'progress', 'pricing-table', 'rating' ],
			'social'      => [ 'social-icons', 'testimonial', 'share-buttons' ],
			'navigation'  => [ 'nav', 'breadcrumb', 'toc', 'menu' ],
		];

		foreach ( $categories as $category => $types ) {
			if ( in_array( $type, $types, true ) ) {
				return $category;
			}
		}

		return 'general';
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
	public function is_valid_content( $content ): bool {
		if ( is_string( $content ) ) {
			$decoded = json_decode( $content, true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				return false;
			}
			$content = $decoded;
		}

		if ( ! is_array( $content ) ) {
			return false;
		}

		$nodes = $this->extract_root_nodes( $content );
		foreach ( $nodes as $node ) {
			if ( is_array( $node ) && $this->looks_like_node( $node ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function get_supported_types(): array {
		return array_keys( $this->type_map );
	}
}
