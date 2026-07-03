"""
Responsive breakpoint canonicalization (mirror of the PHP
DEVTB_Responsive_Helper).

Canonical breakpoints: ``desktop``, ``tablet``, ``phone``.
Canonical states:      ``default``, ``hover``.

Canonical shapes carried on parsed elements under the ``responsive`` key:

    {
        "fields": {"<field>": {"<breakpoint>": {"<state>": value}}},
        "styles": {"<breakpoint>": {"<state>": {"<prop>": value}}},
    }

``fields`` carries content-level responsive values (DIVI 5 text/level/url);
``styles`` carries style-prop variants (Elementor 4 variants, Oxygen 6
design leaves flattened to dot-joined paths).
"""

from __future__ import annotations

from typing import Any, Dict, List, Optional

BREAKPOINTS = ("desktop", "tablet", "phone")
STATES = ("default", "hover")

ELEMENTOR_BREAKPOINTS = {"desktop": "desktop", "tablet": "tablet", "mobile": "phone"}

# Elementor v3 responsive SETTING SUFFIXES (``align_tablet``, ``align_mobile``)
# and the hover-state suffix (``color_hover``).
ELEMENTOR_V3_SUFFIXES = {"_tablet": ("tablet", "default"), "_mobile": ("phone", "default"),
                         "_hover": ("desktop", "hover")}

# Bricks responsive SETTING-KEY SUFFIXES (``_padding:tablet_portrait``).
# ``mobile_landscape`` has no canonical slot and passes through verbatim.
BRICKS_BREAKPOINT_SUFFIXES = {":tablet_portrait": "tablet", ":mobile_portrait": "phone"}
OXYGEN6_BREAKPOINTS = {
    "breakpoint_base": "desktop",
    "breakpoint_tablet_portrait": "tablet",
    "breakpoint_phone_portrait": "phone",
}
DIVI5_STATES = {"value": "default", "hover": "hover"}


def canonical_to_divi5_wrapper(canonical: Dict[str, Dict[str, Any]]) -> Dict[str, Dict[str, Any]]:
    """Emit a DIVI 5 responsive wrapper from a canonical field entry."""
    state_remap = {v: k for k, v in DIVI5_STATES.items()}
    wrapper: Dict[str, Dict[str, Any]] = {}
    for breakpoint in BREAKPOINTS:
        states = canonical.get(breakpoint)
        if not isinstance(states, dict):
            continue
        for state, value in states.items():
            divi_state = state_remap.get(state)
            if divi_state is not None:
                wrapper.setdefault(breakpoint, {})[divi_state] = value
    return wrapper


def canonical_to_elementor4_variants(
    canonical: Dict[str, Dict[str, Dict[str, Any]]],
) -> List[Dict[str, Any]]:
    """Emit Elementor 4 style variants from canonical styles."""
    breakpoint_remap = {v: k for k, v in ELEMENTOR_BREAKPOINTS.items()}
    variants: List[Dict[str, Any]] = []
    for breakpoint in BREAKPOINTS:
        states = canonical.get(breakpoint)
        if not isinstance(states, dict):
            continue
        for state in STATES:
            props = states.get(state)
            if not props:
                continue
            variants.append(
                {
                    "meta": {
                        "breakpoint": breakpoint_remap[breakpoint],
                        "state": None if state == "default" else state,
                    },
                    "props": props,
                }
            )
    return variants


def canonical_to_oxygen6_design(
    canonical: Dict[str, Dict[str, Dict[str, Any]]],
) -> Dict[str, Any]:
    """Emit an Oxygen 6 design tree (breakpoint_* leaves) from canonical styles."""
    breakpoint_remap = {v: k for k, v in OXYGEN6_BREAKPOINTS.items()}
    design: Dict[str, Any] = {}
    for breakpoint in BREAKPOINTS:
        props = canonical.get(breakpoint, {}).get("default")
        if not isinstance(props, dict):
            continue
        for path, value in props.items():
            cursor = design
            segments = str(path).split(".")
            for segment in segments:
                nxt = cursor.get(segment)
                if not isinstance(nxt, dict):
                    nxt = {}
                    cursor[segment] = nxt
                cursor = nxt
            cursor[breakpoint_remap[breakpoint]] = value
    return design


def elementor_v3_settings_to_canonical(
    settings: Dict[str, Any],
) -> Optional[Dict[str, Dict[str, Dict[str, Any]]]]:
    """Canonicalize Elementor v3 suffix-based responsive settings.

    ``{"align": "left", "align_tablet": "center", "align_mobile": "right",
    "color_hover": "#f00"}`` → ``{"tablet": {"default": {"align": "center"}},
    "phone": {"default": {"align": "right"}},
    "desktop": {"hover": {"color": "#f00"}}}``.

    Base (unsuffixed) settings stay where they are — they are the desktop
    defaults. Returns None when no responsive suffixes are present.
    """
    canonical: Dict[str, Dict[str, Dict[str, Any]]] = {}
    for key, value in settings.items():
        for suffix, (breakpoint, state) in ELEMENTOR_V3_SUFFIXES.items():
            if key.endswith(suffix) and len(key) > len(suffix):
                base = key[: -len(suffix)]
                canonical.setdefault(breakpoint, {}).setdefault(state, {})[base] = value
                break
    return canonical or None


def canonical_to_elementor_v3_settings(
    canonical: Dict[str, Dict[str, Dict[str, Any]]],
) -> Dict[str, Any]:
    """Emit Elementor v3 suffix-based settings from canonical styles."""
    suffix_for = {("tablet", "default"): "_tablet", ("phone", "default"): "_mobile",
                  ("desktop", "hover"): "_hover"}
    out: Dict[str, Any] = {}
    for breakpoint, states in canonical.items():
        if not isinstance(states, dict):
            continue
        for state, props in states.items():
            suffix = suffix_for.get((breakpoint, state))
            if suffix is None or not isinstance(props, dict):
                continue
            for prop, value in props.items():
                out[f"{prop}{suffix}"] = value
    return out


def bricks_settings_to_canonical(
    settings: Dict[str, Any],
) -> Optional[Dict[str, Dict[str, Dict[str, Any]]]]:
    """Canonicalize Bricks breakpoint-suffixed settings.

    ``{"_padding": {...}, "_padding:tablet_portrait": {...}}`` →
    ``{"tablet": {"default": {"_padding": {...}}}}``.
    """
    canonical: Dict[str, Dict[str, Dict[str, Any]]] = {}
    for key, value in settings.items():
        for suffix, breakpoint in BRICKS_BREAKPOINT_SUFFIXES.items():
            if key.endswith(suffix) and len(key) > len(suffix):
                base = key[: -len(suffix)]
                canonical.setdefault(breakpoint, {}).setdefault("default", {})[base] = value
                break
    return canonical or None


def canonical_to_bricks_settings(
    canonical: Dict[str, Dict[str, Dict[str, Any]]],
) -> Dict[str, Any]:
    """Emit Bricks breakpoint-suffixed settings from canonical styles."""
    suffix_for = {"tablet": ":tablet_portrait", "phone": ":mobile_portrait"}
    out: Dict[str, Any] = {}
    for breakpoint, suffix in suffix_for.items():
        props = canonical.get(breakpoint, {}).get("default")
        if isinstance(props, dict):
            for prop, value in props.items():
                out[f"{prop}{suffix}"] = value
    return out


def element_responsive(element: Dict[str, Any]) -> Optional[Dict[str, Any]]:
    """Locate canonical responsive data on an inbound parsed element.

    Accepted locations: ``element["responsive"]`` or
    ``element["metadata"]["responsive"]``.
    """
    for container in (element, element.get("metadata") or {}):
        if isinstance(container, dict):
            responsive = container.get("responsive")
            if isinstance(responsive, dict):
                return responsive
    return None
