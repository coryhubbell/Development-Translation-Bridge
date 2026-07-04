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

from ..interchange import document_to_components


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


# Settings keys that carry user-visible content (mirror of the CLI fidelity
# probe). Used to rescue strings the reverse interchange has no slot for.
_CONTENT_KEY_PARTS: tuple = (
    "text", "title", "content", "description", "heading", "editor",
    "caption", "label", "alt", "html", "name", "job", "address", "url", "date",
)

# elTypes the reverse interchange converts (everything else it skips).
_UNIVERSAL_EL_TYPES: tuple = ("section", "container", "column", "widget")


def _is_universal_element(value: Any) -> bool:
    return isinstance(value, dict) and "elType" in value


def _element_content_strings(settings: Any) -> List[str]:
    """Content-bearing strings in one element's settings (children excluded)."""
    out: List[str] = []

    def maybe(key: str, value: Any) -> None:
        if isinstance(value, str) and value.strip() and any(
            part in key.lower() for part in _CONTENT_KEY_PARTS
        ):
            out.append(value.strip())

    if not isinstance(settings, dict):
        return out
    for key, value in settings.items():
        if isinstance(value, dict):
            for sub_key, sub in value.items():
                maybe(sub_key, sub)
        elif isinstance(value, list):
            for item in value:
                if isinstance(item, dict):
                    for sub_key, sub in item.items():
                        maybe(sub_key, sub)
        else:
            maybe(key, value)
    return out


def _component_kept_strings(component: Dict[str, Any]) -> str:
    """Every string the component can still express, for extras diffing."""
    parts: List[str] = [str(component.get("content") or "")]

    def collect(value: Any) -> None:
        if isinstance(value, str):
            parts.append(value)
        elif isinstance(value, dict):
            for sub in value.values():
                collect(sub)
        elif isinstance(value, list):
            for sub in value:
                collect(sub)

    collect(component.get("attributes") or {})
    return "\n".join(parts)


def _graft_universal_extras(
    elements: List[Any], components: List[Dict[str, Any]]
) -> None:
    """Attach settings strings the reverse interchange dropped (no attribute
    slot, e.g. price-table rows) so the converted output never loses them."""
    index = 0
    for element in elements:
        if not _is_universal_element(element):
            continue
        if str(element.get("elType") or "") not in _UNIVERSAL_EL_TYPES:
            continue
        if index >= len(components):
            break
        component = components[index]
        index += 1
        kept = _component_kept_strings(component)
        extras = [
            value
            for value in _element_content_strings(element.get("settings"))
            if value not in kept
        ]
        if extras:
            component["_universal_extras"] = extras
        _graft_universal_extras(
            element.get("elements") or [], component.get("children") or []
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

        body = "".join(
            self._convert_component(c) for c in self._normalize_input(data)
        )

        return body + "\n" + self._render_style_block()

    def get_framework(self) -> str:
        return "thrive"

    def get_supported_types(self) -> List[str]:
        return [
            "row", "container", "column",
            "heading", "text", "paragraph", "button", "image",
            "divider", "spacer", "icon", "html", "shortcode",
            "testimonial", "card", "cta", "alert", "tabs", "accordion",
            "counter", "list", "gallery", "video",
        ]

    def supports_type(self, type_name: str) -> bool:
        return type_name in self.get_supported_types()

    def get_fallback(self, component: Dict[str, Any]) -> str:
        parts: List[str] = []
        content = component.get("content", "") or ""
        if content:
            parts.append(self._render_text(str(content)))
        attrs = component.get("attributes", {}) or {}
        for key in ("heading", "title", "label", "number", "url", "author", "job_title"):
            value = attrs.get(key)
            if isinstance(value, str) and value.strip():
                parts.append(escape(value.strip()))
        for key in ("tabs", "items", "images"):
            value = attrs.get(key)
            if not isinstance(value, list):
                continue
            for item in value:
                if isinstance(item, dict):
                    parts.extend(
                        self._render_text(str(sub).strip())
                        for sub in item.values()
                        if isinstance(sub, str) and sub.strip()
                    )
        return f'<div class="tcb-fallback">{" ".join(parts)}</div>'

    # --- Internal -------------------------------------------------------

    def _normalize_input(self, data: Any) -> List[Dict[str, Any]]:
        """Normalize input to component dicts; universal elements route
        through the reverse interchange (plus dropped-content grafting)."""
        if isinstance(data, list):
            elements = data
        elif _is_universal_element(data):
            elements = [data]
        elif isinstance(data, dict) and isinstance(data.get("elements"), list):
            elements = data["elements"]
        elif isinstance(data, dict):
            return [data]
        else:
            return []

        if any(_is_universal_element(e) for e in elements):
            components = document_to_components(elements)
            _graft_universal_extras(elements, components)
            return components
        return [e for e in elements if isinstance(e, dict)]

    def _convert_component(self, component: Any) -> str:
        if not isinstance(component, dict):
            return ""

        comp_type = component.get("type", "")
        content = component.get("content", "") or ""

        if comp_type in ("container", "row"):
            markup = self._convert_row(component)
        elif comp_type == "column":
            markup = self._convert_column(component)
        else:
            if comp_type == "shortcode" or self._is_passthrough_shortcode(content):
                markup = self._convert_shortcode_passthrough(component)
            else:
                markup = self._convert_element(component)
            # Leaf components can still carry children (universal sources
            # nest freely) — never drop them.
            markup += "".join(
                self._convert_component(c)
                for c in (component.get("children", []) or [])
            )
        return markup + self._render_universal_extras(component)

    def _render_universal_extras(self, component: Dict[str, Any]) -> str:
        extras = component.get("_universal_extras") or []
        if not extras:
            return ""
        return f'<div class="tcb-fallback">{" ".join(extras)}</div>'

    @staticmethod
    def _render_text(content: str) -> str:
        # Rich-text sources ship ready-made HTML; keep it verbatim.
        return content if "<" in content else escape(content)

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
            return f'<p class="tve_p" data-css="{escape(token)}">{self._render_text(content)}</p>'

        if comp_type == "button":
            href = attrs.get("url") or attrs.get("href") or "#"
            label = content if content else attrs.get("label", "Button")
            return (
                f'<div class="tcb-button-block" data-css="{escape(token)}">'
                f'<a href="{escape(href)}" class="tcb-button-link">'
                f'<span class="tcb-button-texts">{escape(label)}</span></a></div>'
            )

        if comp_type == "image":
            src = attrs.get("image_url") or attrs.get("src") or ""
            alt = attrs.get("alt_text") or attrs.get("alt") or ""
            return (
                f'<div class="tve_image_caption" data-css="{escape(token)}">'
                f'<img src="{escape(src)}" alt="{escape(alt)}" class="tve_image"/></div>'
            )

        if comp_type == "testimonial":
            cite = ", ".join(
                str(attrs[key]).strip()
                for key in ("author", "job_title")
                if attrs.get(key)
            )
            cite_html = (
                f'<cite class="tcb-testimonial-cite">{escape(cite)}</cite>'
                if cite
                else ""
            )
            return (
                f'<blockquote class="tcb-testimonial" data-css="{escape(token)}">'
                f"<p>{self._render_text(content)}</p>{cite_html}</blockquote>"
            )

        if comp_type in ("card", "cta", "alert"):
            heading = str(attrs.get("heading") or attrs.get("title") or "")
            label = str(attrs.get("label") or "")
            url = str(attrs.get("url") or attrs.get("href") or "")
            parts = [f'<div class="tcb-content-box" data-css="{escape(token)}">']
            if heading:
                parts.append(f'<h3 class="tve_h3">{escape(heading)}</h3>')
            if content:
                parts.append(f'<p class="tve_p">{self._render_text(content)}</p>')
            if label or url:
                parts.append(
                    f'<a href="{escape(url or "#")}" class="tcb-button-link">'
                    f'<span class="tcb-button-texts">{escape(label)}</span></a>'
                )
            parts.append("</div>")
            return "".join(parts)

        if comp_type in ("tabs", "accordion"):
            panes = []
            for item in attrs.get("tabs") or []:
                if not isinstance(item, dict):
                    continue
                title = str(item.get("tab_title", "") or "")
                body = str(item.get("tab_content", "") or "")
                # tab_content is rich text — keep it verbatim.
                panes.append(
                    '<div class="tve_tab_pane">'
                    f'<h4 class="tve_tab_title">{escape(title)}</h4>{body}</div>'
                )
            return (
                f'<div class="thrv_tabs_shortcode" data-css="{escape(token)}">'
                f'{"".join(panes)}</div>'
            )

        if comp_type == "counter":
            heading = str(attrs.get("heading") or attrs.get("title") or content)
            number = str(attrs.get("number", "") or "")
            return (
                f'<div class="thrv_number_counter" data-css="{escape(token)}">'
                f'<div class="tve-count">{escape(number)}</div>'
                f'<div class="tve-count-title">{escape(heading)}</div></div>'
            )

        if comp_type == "list":
            items = attrs.get("items")
            if isinstance(items, list) and items:
                lis = "".join(
                    f'<li>{escape(str(item.get("text", "") or ""))}</li>'
                    for item in items
                    if isinstance(item, dict)
                )
            else:
                lis = f"<li>{self._render_text(content)}</li>"
            return f'<ul class="thrv-list" data-css="{escape(token)}">{lis}</ul>'

        if comp_type == "gallery":
            images = "".join(
                f'<img src="{escape(str(image.get("url", "") or ""))}" '
                f'alt="{escape(str(image.get("alt", "") or ""))}" class="tve_image"/>'
                for image in (attrs.get("images") or [])
                if isinstance(image, dict)
            )
            return f'<div class="tcb-gallery" data-css="{escape(token)}">{images}</div>'

        if comp_type == "video":
            url = str(attrs.get("url") or attrs.get("src") or "")
            return (
                f'<div class="thrv_responsive_video" data-css="{escape(token)}">'
                f'<iframe src="{escape(url)}" allowfullscreen></iframe></div>'
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

        heading = str(attrs.get("heading") or attrs.get("title") or "")
        heading_html = (
            f'<div class="tcb-element-title">{escape(heading)}</div>' if heading else ""
        )
        return (
            f'<div class="tcb-element" data-css="{escape(token)}">'
            f"{heading_html}{self._render_text(content)}</div>"
        )

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
