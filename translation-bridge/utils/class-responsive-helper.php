<?php
/**
 * Responsive breakpoint canonicalization helper.
 *
 * Round-trips responsive values through the universal component model using a
 * canonical vocabulary, so breakpoint data survives same-framework round trips
 * and transfers across frameworks where representable.
 *
 * Canonical breakpoints: `desktop`, `tablet`, `phone`.
 * Canonical states:      `default`, `hover`.
 *
 * Framework mappings:
 * - DIVI 5:      desktop/tablet/phone breakpoints with states nested inside
 *                (`{"desktop": {"value": X, "hover": Y}}`) — `value` is the
 *                default state.
 * - Elementor 4: style variants (`{meta: {breakpoint, state}, props}`) with
 *                breakpoints desktop/tablet/mobile (mobile ↔ phone) and state
 *                null ↔ default.
 * - Oxygen 6:    design leaves keyed by `breakpoint_base` (↔ desktop),
 *                `breakpoint_tablet_portrait` (↔ tablet),
 *                `breakpoint_phone_portrait` (↔ phone). Landscape variants
 *                pass through verbatim in framework metadata.
 *
 * Canonical data lives in component metadata under the `responsive` key:
 *
 *     [
 *       'fields' => [ '<field>' => [ '<breakpoint>' => [ '<state>' => <value> ] ] ],
 *       'styles' => [ '<breakpoint>' => [ '<state>' => [ '<prop>' => <value> ] ] ],
 *     ]
 *
 * `fields` carries content-level responsive values (DIVI 5 text/level/url);
 * `styles` carries style-prop variants (Elementor 4 variants, Oxygen 6 design).
 *
 * @package DevelopmentTranslation_Bridge
 * @subpackage Translation_Bridge
 * @since 4.5.0
 */

namespace DEVTB\TranslationBridge\Utils;

if ( ! defined( 'ABSPATH' ) && ! defined( 'DEVTB_TESTING' ) && ! defined( 'DEVTB_CLI' ) ) {
	exit;
}

class DEVTB_Responsive_Helper {

	public const BREAKPOINTS = [ 'desktop', 'tablet', 'phone' ];
	public const STATES      = [ 'default', 'hover' ];

	/**
	 * Metadata key holding canonical responsive data on a component.
	 */
	public const METADATA_KEY = 'responsive';

	/**
	 * Elementor breakpoint key ↔ canonical breakpoint.
	 */
	public const ELEMENTOR_BREAKPOINTS = [
		'desktop' => 'desktop',
		'tablet'  => 'tablet',
		'mobile'  => 'phone',
	];

	/**
	 * Oxygen 6 / Breakdance design breakpoint key ↔ canonical breakpoint.
	 */
	public const OXYGEN6_BREAKPOINTS = [
		'breakpoint_base'            => 'desktop',
		'breakpoint_tablet_portrait' => 'tablet',
		'breakpoint_phone_portrait'  => 'phone',
	];

	/**
	 * DIVI 5 state key ↔ canonical state (`value` is DIVI's default state).
	 */
	public const DIVI5_STATES = [
		'value' => 'default',
		'hover' => 'hover',
	];

	/**
	 * Parse a DIVI 5 responsive wrapper into a canonical field entry.
	 *
	 * `{"desktop": {"value": X, "hover": H}, "tablet": {"value": Y}}` →
	 * `{"desktop": {"default": X, "hover": H}, "tablet": {"default": Y}}`.
	 *
	 * Returns null when the wrapper holds nothing beyond a single desktop
	 * default value (no responsive data worth round-tripping).
	 *
	 * @param mixed $wrapper Raw attribute value.
	 * @return array<string, array<string, mixed>>|null
	 */
	public static function divi5_wrapper_to_canonical( $wrapper ): ?array {
		if ( ! is_array( $wrapper ) ) {
			return null;
		}

		$canonical = [];
		foreach ( self::BREAKPOINTS as $breakpoint ) {
			if ( ! isset( $wrapper[ $breakpoint ] ) || ! is_array( $wrapper[ $breakpoint ] ) ) {
				continue;
			}
			foreach ( self::DIVI5_STATES as $divi_state => $canonical_state ) {
				if ( array_key_exists( $divi_state, $wrapper[ $breakpoint ] ) ) {
					$canonical[ $breakpoint ][ $canonical_state ] = $wrapper[ $breakpoint ][ $divi_state ];
				}
			}
		}

		if ( $canonical === [] ) {
			return null;
		}

		// Desktop default only → nothing beyond what attributes already carry.
		if ( count( $canonical ) === 1
			&& isset( $canonical['desktop'] )
			&& array_keys( $canonical['desktop'] ) === [ 'default' ] ) {
			return null;
		}

		return $canonical;
	}

	/**
	 * Emit a DIVI 5 responsive wrapper from a canonical field entry.
	 *
	 * @param array<string, array<string, mixed>> $canonical Canonical field entry.
	 * @return array<string, array<string, mixed>>
	 */
	public static function canonical_to_divi5_wrapper( array $canonical ): array {
		$wrapper     = [];
		$state_remap = array_flip( self::DIVI5_STATES ); // default→value, hover→hover.

		foreach ( self::BREAKPOINTS as $breakpoint ) {
			if ( ! isset( $canonical[ $breakpoint ] ) || ! is_array( $canonical[ $breakpoint ] ) ) {
				continue;
			}
			foreach ( $canonical[ $breakpoint ] as $state => $value ) {
				$divi_state = $state_remap[ $state ] ?? null;
				if ( $divi_state !== null ) {
					$wrapper[ $breakpoint ][ $divi_state ] = $value;
				}
			}
		}

		return $wrapper;
	}

	/**
	 * Parse Elementor 4 style variants into canonical styles.
	 *
	 * @param array<int, array<string, mixed>> $variants Style variants.
	 * @return array<string, array<string, array<string, mixed>>>|null
	 */
	public static function elementor4_variants_to_canonical( array $variants ): ?array {
		$canonical = [];

		foreach ( $variants as $variant ) {
			if ( ! is_array( $variant ) || ! isset( $variant['props'] ) || ! is_array( $variant['props'] ) ) {
				continue;
			}
			$meta       = is_array( $variant['meta'] ?? null ) ? $variant['meta'] : [];
			$breakpoint = self::ELEMENTOR_BREAKPOINTS[ $meta['breakpoint'] ?? 'desktop' ] ?? null;
			$state      = ( $meta['state'] ?? null ) === null ? 'default' : (string) $meta['state'];

			if ( $breakpoint === null || ! in_array( $state, self::STATES, true ) ) {
				continue; // Unmappable breakpoint/state — preserved verbatim in framework metadata.
			}

			$canonical[ $breakpoint ][ $state ] = array_merge(
				$canonical[ $breakpoint ][ $state ] ?? [],
				$variant['props']
			);
		}

		return $canonical === [] ? null : $canonical;
	}

	/**
	 * Emit Elementor 4 style variants from canonical styles.
	 *
	 * @param array<string, array<string, array<string, mixed>>> $canonical Canonical styles.
	 * @return array<int, array<string, mixed>>
	 */
	public static function canonical_to_elementor4_variants( array $canonical ): array {
		$breakpoint_remap = array_flip( self::ELEMENTOR_BREAKPOINTS ); // phone→mobile.
		$variants         = [];

		foreach ( self::BREAKPOINTS as $breakpoint ) {
			if ( ! isset( $canonical[ $breakpoint ] ) || ! is_array( $canonical[ $breakpoint ] ) ) {
				continue;
			}
			foreach ( self::STATES as $state ) {
				if ( ! isset( $canonical[ $breakpoint ][ $state ] ) || $canonical[ $breakpoint ][ $state ] === [] ) {
					continue;
				}
				$variants[] = [
					'meta'  => [
						'breakpoint' => $breakpoint_remap[ $breakpoint ],
						'state'      => $state === 'default' ? null : $state,
					],
					'props' => $canonical[ $breakpoint ][ $state ],
				];
			}
		}

		return $variants;
	}

	/**
	 * Parse an Oxygen 6 design section into canonical styles.
	 *
	 * Design is a nested tree whose leaves may be keyed by `breakpoint_*`.
	 * Leaf paths flatten to dot-joined prop names, e.g.
	 * `design.layout.gap.breakpoint_base` → `layout.gap` @ desktop.
	 *
	 * @param array<string, mixed> $design Design section.
	 * @return array<string, array<string, array<string, mixed>>>|null
	 */
	public static function oxygen6_design_to_canonical( array $design ): ?array {
		$canonical = [];
		self::walk_oxygen6_design( $design, '', $canonical );
		return $canonical === [] ? null : $canonical;
	}

	/**
	 * Recursive walker for oxygen6_design_to_canonical().
	 *
	 * @param array  $node      Current subtree.
	 * @param string $path      Dot-joined path so far.
	 * @param array  $canonical Accumulator (by reference).
	 */
	private static function walk_oxygen6_design( array $node, string $path, array &$canonical ): void {
		$breakpoint_keys = array_intersect_key( self::OXYGEN6_BREAKPOINTS, $node );

		if ( $breakpoint_keys !== [] ) {
			foreach ( $breakpoint_keys as $oxygen_key => $breakpoint ) {
				$canonical[ $breakpoint ]['default'][ $path ] = $node[ $oxygen_key ];
			}
			return;
		}

		foreach ( $node as $key => $value ) {
			if ( is_array( $value ) ) {
				self::walk_oxygen6_design( $value, $path === '' ? (string) $key : $path . '.' . $key, $canonical );
			} elseif ( $path !== '' || $key !== '' ) {
				// Non-responsive scalar leaf — treat as a desktop default.
				$leaf = $path === '' ? (string) $key : $path . '.' . $key;
				$canonical['desktop']['default'][ $leaf ] = $value;
			}
		}
	}

	/**
	 * Emit an Oxygen 6 design section from canonical styles.
	 *
	 * Dot-joined prop paths re-nest into the design tree with `breakpoint_*`
	 * leaf keys. Only the `default` state is representable.
	 *
	 * @param array<string, array<string, array<string, mixed>>> $canonical Canonical styles.
	 * @return array<string, mixed>
	 */
	public static function canonical_to_oxygen6_design( array $canonical ): array {
		$breakpoint_remap = array_flip( self::OXYGEN6_BREAKPOINTS ); // desktop→breakpoint_base.
		$design           = [];

		foreach ( self::BREAKPOINTS as $breakpoint ) {
			$props = $canonical[ $breakpoint ]['default'] ?? null;
			if ( ! is_array( $props ) ) {
				continue;
			}
			foreach ( $props as $path => $value ) {
				$segments = explode( '.', (string) $path );
				$cursor   = &$design;
				foreach ( $segments as $segment ) {
					if ( ! isset( $cursor[ $segment ] ) || ! is_array( $cursor[ $segment ] ) ) {
						$cursor[ $segment ] = [];
					}
					$cursor = &$cursor[ $segment ];
				}
				$cursor[ $breakpoint_remap[ $breakpoint ] ] = $value;
				unset( $cursor );
			}
		}

		return $design;
	}
}
