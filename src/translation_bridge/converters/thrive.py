"""
Translation Bridge v4 - Thrive Themes Converter.

Converts universal/parsed data to Thrive Content Builder (TCB) HTML markup.
Thrive's styling is keyed by opaque `data-css` tokens (e.g. `tve-u-167abc12345`)
resolved by an inline `<style>` block at the bottom of the document. This
converter builds both halves in lockstep.

Covers Thrive Architect (page builder), Thrive Theme Builder template parts
(same TCB markup), and Thrive Suite extras (Leads/Quiz/Apprentice/Ultimatum)
which pass through as embedded shortcodes.

Sibling of `translation-bridge/converters/class-thrive-converter.php`.
"""

from __future__ import annotations

import hashlib
import re
from html import escape
from typing import Any, Dict, List, Optional


# Upstream framework version (Thrive Architect) this converter is calibrated
# against. Thrive Theme Builder ships independently; current versions share
# the TCB markup format.
TARGET_CMS_VERSION: str = "10.8.10"


# Thrive Suite shortcodes that should pass through unchanged.
_SUITE_SHORTCODE_PREFIXES: tuple = (
    "thrive_leads",
    "thrive_2step",
    "thrive_optin",
    "tcb-quiz",
    "thrive_quiz",
    "thrive_ultimatum",
    "tve_leads",
    "tva_",  # Thrive Apprentice
)
_SUITE_PATTERN = re.compile(
    r"\[(?:" + "|".join(re.escape(p) for p in _SUITE_SHORTCODE_PREFIXES) + r")"
)


class ThriveConverter:
    """Convert universal data to TCB HTML + matching inline style block."""

    def __init__(self) -> None:
        self._token_counter: int = 0
        self._style_rules: Dict[str, str] = {}

    # --- Public surface -------------------------------------------------

    def convert(self, data: Any) -> str:
        """Convert universal data to TCB HTML; appends inline style block."""
        # Reset per-conversion state so repeat invocations don't accumulate.
        self._token_counter = 0
        self._style_rules = {}

        if isinstance(data, dict):
            if "elements" in data and isinstance(data["elements"], list):
                body = "".join(self._convert_component(c) for c in data["elements"])
            else:
                body = self._convert_component(data)
        elif isinstance(data, list):
            body = "".join(self._convert_component(c) for c in data)
        else:
            body = ""

        return body + "\n" + self._render_style_block()

    def get_framework(self) -> str:
        return "thrive"

    def get_supported_types(self) -> List[str]:
        return [
            "row", "container", "column",
            "heading", "text", "paragraph", "button", "image",
            "divider", "spacer", "icon", "html", "shortcode",
        ]

    def supports_type(self, type_name: str) -> bool:
        return type_name in self.get_supported_types()

    def get_fallback(self, component: Dict[str, Any]) -> str:
        return f'<div class="tcb-fallback">{escape(component.get("content", "") or "")}</div>'

    # --- Internal -------------------------------------------------------

    def _convert_component(self, component: Any) -> str:
        if not isinstance(component, dict):
            return ""

        comp_type = component.get("type", "")
        content = component.get("content", "") or ""

        if comp_type in ("container", "row"):
            return self._convert_row(component)
        if comp_type == "column":
            return self._convert_column(component)
        if comp_type == "shortcode" or self._is_passthrough_shortcode(content):
            return self._convert_shortcode_passthrough(component)
        return self._convert_element(component)

    def _convert_row(self, component: Dict[str, Any]) -> str:
        token = self._register_token(self._build_section_css(component.get("attributes", {}) or {}))
        inner = "".join(
            self._convert_component(c)
            for c in (component.get("children", []) or [])
        )
        return f'<div class="tve_flt tcb-flex-row" data-css="{escape(token)}">{inner}</div>'

    def _convert_column(self, component: Dict[str, Any]) -> str:
        attrs = component.get("attributes", {}) or {}
        width = float(attrs.get("width", 100.0))
        css = f"width:{self._trim_zeros(width)}%;" + self._build_box_css(attrs)
        token = self._register_token(css)
        inner = "".join(
            self._convert_component(c)
            for c in (component.get("children", []) or [])
        )
        return f'<div class="tcb-flex-col" data-css="{escape(token)}">{inner}</div>'

    def _convert_element(self, component: Dict[str, Any]) -> str:
        comp_type = component.get("type", "")
        content = component.get("content", "") or ""
        attrs = component.get("attributes", {}) or {}

        css = self._build_element_css(comp_type, attrs)
        token = self._register_token(css)

        if comp_type == "heading":
            level = max(1, min(6, int(attrs.get("level", 2))))
            return (
                f'<h{level} class="tve_h{level}" data-css="{escape(token)}">'
                f"{escape(content)}</h{level}>"
            )

        if comp_type in ("text", "paragraph"):
            return f'<p class="tve_p" data-css="{escape(token)}">{escape(content)}</p>'

        if comp_type == "button":
            href = attrs.get("href", "#")
            label = content if content else attrs.get("label", "Button")
            return (
                f'<div class="tcb-button-block" data-css="{escape(token)}">'
                f'<a href="{escape(href)}" class="tcb-button-link">'
                f'<span class="tcb-button-texts">{escape(label)}</span></a></div>'
            )

        if comp_type == "image":
            src = attrs.get("src", "")
            alt = attrs.get("alt", "")
            return (
                f'<div class="tve_image_caption" data-css="{escape(token)}">'
                f'<img src="{escape(src)}" alt="{escape(alt)}" class="tve_image"/></div>'
            )

        if comp_type == "divider":
            return f'<div class="tcb-style-wrap tve-divider" data-css="{escape(token)}"></div>'

        if comp_type == "spacer":
            height = int(attrs.get("height", 40))
            return (
                f'<div class="thrv_responsive_spacer" data-css="{escape(token)}" '
                f'style="height:{height}px" aria-hidden="true"></div>'
            )

        if comp_type == "icon":
            return f'<span class="tve_ea_icon" data-css="{escape(token)}" aria-hidden="true"></span>'

        if comp_type == "html":
            return content

        return f'<div class="tcb-element" data-css="{escape(token)}">{escape(content)}</div>'

    def _convert_shortcode_passthrough(self, component: Dict[str, Any]) -> str:
        # Wrap in TCB marker so the Theme Builder can re-parse it on import.
        return f'<div class="thrv_text_element tcb-shortcode-passthrough">{component.get("content", "") or ""}</div>'

    def _is_passthrough_shortcode(self, content: str) -> bool:
        if "[" not in content:
            return False
        return bool(_SUITE_PATTERN.search(content))

    # --- CSS builders ---------------------------------------------------

    def _build_section_css(self, attributes: Dict[str, Any]) -> str:
        css = "display:flex;flex-wrap:wrap;"
        css += self._build_box_css(attributes)
        if "background-color" in attributes:
            css += f"background-color:{attributes['background-color']};"
        return css

    def _build_element_css(self, comp_type: str, attributes: Dict[str, Any]) -> str:
        css = self._build_box_css(attributes)
        if "color" in attributes:
            css += f"color:{attributes['color']};"
        if "background-color" in attributes:
            css += f"background-color:{attributes['background-color']};"
        if "font-size" in attributes:
            css += f"font-size:{attributes['font-size']};"
        if "font-weight" in attributes:
            css += f"font-weight:{attributes['font-weight']};"
        if "text-align" in attributes:
            css += f"text-align:{attributes['text-align']};"
        if comp_type == "button":
            css += "display:inline-block;cursor:pointer;"
        return css

    def _build_box_css(self, attributes: Dict[str, Any]) -> str:
        out = ""
        for box in ("padding", "margin"):
            sides = [
                attributes.get(f"{box}-top"),
                attributes.get(f"{box}-right"),
                attributes.get(f"{box}-bottom"),
                attributes.get(f"{box}-left"),
            ]
            if not any(v not in (None, "") for v in sides):
                continue
            parts = ["0" if v in (None, "") else str(v) for v in sides]
            out += f"{box}:{' '.join(parts)};"
        return out

    # --- Token management -----------------------------------------------

    def _register_token(self, css: str) -> str:
        css = css.strip()
        self._token_counter += 1
        token = self._build_token()
        if css:
            self._style_rules[token] = css
        return token

    def _build_token(self) -> str:
        suffix = hashlib.md5(str(self._token_counter).encode("utf-8")).hexdigest()[:11]
        return f"tve-u-{suffix}"

    def _render_style_block(self) -> str:
        if not self._style_rules:
            return ""
        rules = "".join(
            f".{token}{{{css}}}\n"
            for token, css in self._style_rules.items()
        )
        return (
            '<style type="text/css" class="tve_custom_style">\n'
            + rules
            + "</style>"
        )

    @staticmethod
    def _trim_zeros(n: float) -> str:
        s = f"{n:.2f}"
        return s.rstrip("0").rstrip(".")
