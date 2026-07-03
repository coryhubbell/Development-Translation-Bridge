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
