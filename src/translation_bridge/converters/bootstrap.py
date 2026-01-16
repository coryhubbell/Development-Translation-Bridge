"""
Translation Bridge v4 - Bootstrap HTML Converter.

Converts Elementor JSON (and other page builder formats) to clean Bootstrap 5 HTML.
Preserves styling, typography, flexbox layouts, and responsive settings.
"""

from typing import Any, Dict, List, Optional
from html import escape


class BootstrapConverter:
    """
    Converts page builder JSON to Bootstrap 5 HTML.

    Generates semantic, accessible HTML using Bootstrap 5.3 classes
    while preserving all content and maintaining visual structure.
    """

    # Elementor widget to Bootstrap component mapping
    WIDGET_MAP = {
        "heading": "_convert_heading",
        "text-editor": "_convert_text_editor",
        "button": "_convert_button",
        "image": "_convert_image",
        "video": "_convert_video",
        "icon": "_convert_icon",
        "icon-box": "_convert_icon_box",
        "image-box": "_convert_image_box",
        "counter": "_convert_counter",
        "progress": "_convert_progress",
        "testimonial": "_convert_testimonial",
        "tabs": "_convert_tabs",
        "accordion": "_convert_accordion",
        "alert": "_convert_alert",
        "divider": "_convert_divider",
        "spacer": "_convert_spacer",
        "html": "_convert_html",
        "shortcode": "_convert_shortcode",
        "icon-list": "_convert_icon_list",
        "social-icons": "_convert_social_icons",
        "form": "_convert_form",
        "nav-menu": "_convert_nav_menu",
        "mega-menu": "_convert_nav_menu",
        "theme-site-logo": "_convert_site_logo",
    }

    # Color mapping from Elementor globals to Bootstrap
    COLOR_MAP = {
        "primary": "primary",
        "secondary": "secondary",
        "text": "dark",
        "accent": "info",
    }

    def __init__(self, include_metadata: bool = True):
        """
        Initialize the converter.

        Args:
            include_metadata: Include data-* attributes for metadata preservation
        """
        self.include_metadata = include_metadata
        self.indent_level = 0
        self.indent_str = "  "

    def convert(self, data: Any) -> str:
        """
        Convert Elementor JSON to Bootstrap HTML.

        Args:
            data: Elementor JSON data (list of elements or dict with 'elements')

        Returns:
            Bootstrap HTML string
        """
        # Handle different input formats
        if isinstance(data, dict):
            elements = data.get("elements", data.get("content", [data]))
        elif isinstance(data, list):
            elements = data
        else:
            return ""

        html_parts = [
            "<!DOCTYPE html>",
            '<html lang="en">',
            "<head>",
            '  <meta charset="UTF-8">',
            '  <meta name="viewport" content="width=device-width, initial-scale=1.0">',
            "  <title>Converted Page</title>",
            '  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">',
            '  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">',
            "  <style>",
            "    * { box-sizing: border-box; }",
            "    body { margin: 0; font-family: Arial, sans-serif; }",
            "    .btn-grow:hover { transform: scale(1.05); }",
            "    .icon-box-horizontal { display: flex; align-items: center; gap: 10px; }",
            "    .icon-box-horizontal .icon-wrapper { flex-shrink: 0; }",
            "  </style>",
            "</head>",
            "<body>",
        ]

        # Convert each top-level element
        for element in elements:
            html_parts.append(self._convert_element(element))

        html_parts.extend([
            '  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>',
            "</body>",
            "</html>",
        ])

        return "\n".join(html_parts)

    def convert_fragment(self, data: Any) -> str:
        """
        Convert Elementor JSON to Bootstrap HTML fragment (no doctype/head).

        Args:
            data: Elementor JSON data

        Returns:
            Bootstrap HTML fragment string
        """
        if isinstance(data, dict):
            elements = data.get("elements", data.get("content", [data]))
        elif isinstance(data, list):
            elements = data
        else:
            return ""

        html_parts = []
        for element in elements:
            html_parts.append(self._convert_element(element))

        return "\n".join(html_parts)

    def _convert_element(self, element: Dict[str, Any], depth: int = 0) -> str:
        """Convert a single element to HTML."""
        if not isinstance(element, dict):
            return ""

        el_type = element.get("elType", "")
        widget_type = element.get("widgetType", "")
        settings = element.get("settings", {})
        children = element.get("elements", [])
        element_id = element.get("id", "")

        indent = self.indent_str * depth

        if el_type == "section" or el_type == "container":
            return self._convert_section(element, depth)
        elif el_type == "column":
            return self._convert_column(element, depth)
        elif el_type == "widget":
            return self._convert_widget(element, depth)
        else:
            # Unknown element type - wrap children
            if children:
                child_html = "\n".join(
                    self._convert_element(child, depth + 1) for child in children
                )
                return f"{indent}<div>\n{child_html}\n{indent}</div>"
            return ""

    def _convert_section(self, element: Dict[str, Any], depth: int = 0) -> str:
        """Convert a section/container to Bootstrap HTML."""
        settings = element.get("settings", {})
        children = element.get("elements", [])
        element_id = element.get("id", "")
        is_inner = element.get("isInner", False)
        indent = self.indent_str * depth

        # Build style attribute
        style_parts = []

        # Background handling - gradient or solid
        bg_type = settings.get("background_background", "")
        if bg_type == "gradient":
            color_a = settings.get("background_color", "#000000")
            color_b = settings.get("background_color_b", "#16213e")
            angle = settings.get("background_gradient_angle", {})
            angle_val = angle.get("size", 135) if isinstance(angle, dict) else 135
            style_parts.append(f"background: linear-gradient({angle_val}deg, {color_a} 0%, {color_b} 100%)")
        elif settings.get("background_color"):
            bg_color = settings.get("background_color", "")
            if bg_color and not bg_color.startswith("globals"):
                style_parts.append(f"background-color: {bg_color}")

        # Min-height
        min_height = settings.get("min_height", {})
        if isinstance(min_height, dict) and min_height.get("size"):
            unit = min_height.get("unit", "px")
            style_parts.append(f"min-height: {min_height['size']}{unit}")

        # Flexbox settings
        flex_dir = settings.get("flex_direction", "")
        flex_justify = settings.get("flex_justify_content", "")
        flex_align = settings.get("flex_align_items", "")
        flex_gap = settings.get("flex_gap", {})

        if flex_dir or flex_justify or flex_align:
            style_parts.append("display: flex")
            if flex_dir:
                style_parts.append(f"flex-direction: {flex_dir}")
            if flex_justify:
                style_parts.append(f"justify-content: {flex_justify}")
            if flex_align:
                style_parts.append(f"align-items: {flex_align}")
            if isinstance(flex_gap, dict) and flex_gap.get("size"):
                style_parts.append(f"gap: {flex_gap['size']}{flex_gap.get('unit', 'px')}")
            if settings.get("flex_wrap"):
                style_parts.append(f"flex-wrap: {settings['flex_wrap']}")

        # Padding
        padding = settings.get("padding", {})
        if isinstance(padding, dict):
            p_parts = []
            if padding.get("top"):
                p_parts.append(f"{padding['top']}{padding.get('unit', 'px')}")
            else:
                p_parts.append("0")
            if padding.get("right"):
                p_parts.append(f"{padding['right']}{padding.get('unit', 'px')}")
            else:
                p_parts.append("0")
            if padding.get("bottom"):
                p_parts.append(f"{padding['bottom']}{padding.get('unit', 'px')}")
            else:
                p_parts.append("0")
            if padding.get("left"):
                p_parts.append(f"{padding['left']}{padding.get('unit', 'px')}")
            else:
                p_parts.append("0")
            if any(p != "0" for p in p_parts):
                style_parts.append(f"padding: {' '.join(p_parts)}")

        # Margin
        margin = settings.get("_margin", {})
        if isinstance(margin, dict):
            m_parts = []
            if margin.get("top"):
                m_parts.append(f"{margin['top']}{margin.get('unit', 'px')}")
            else:
                m_parts.append("0")
            if margin.get("right"):
                m_parts.append(f"{margin['right']}{margin.get('unit', 'px')}")
            else:
                m_parts.append("0")
            if margin.get("bottom"):
                m_parts.append(f"{margin['bottom']}{margin.get('unit', 'px')}")
            else:
                m_parts.append("0")
            if margin.get("left"):
                m_parts.append(f"{margin['left']}{margin.get('unit', 'px')}")
            else:
                m_parts.append("0")
            if any(m != "0" for m in m_parts):
                style_parts.append(f"margin: {' '.join(m_parts)}")

        # Boxed width
        boxed_width = settings.get("boxed_width", {})
        if isinstance(boxed_width, dict) and boxed_width.get("size"):
            style_parts.append(f"max-width: {boxed_width['size']}{boxed_width.get('unit', 'px')}")
            style_parts.append("width: 100%")

        style_attr = f' style="{"; ".join(style_parts)}"' if style_parts else ""
        id_attr = f' id="{element_id}"' if element_id and self.include_metadata else ""

        # Convert children
        child_html = "\n".join(
            self._convert_element(child, depth + 1) for child in children
        )

        tag = "div" if is_inner else "section"
        return f"""{indent}<{tag}{id_attr}{style_attr}>
{child_html}
{indent}</{tag}>"""

    def _convert_column(self, element: Dict[str, Any], depth: int = 0) -> str:
        """Convert a column to Bootstrap HTML."""
        settings = element.get("settings", {})
        children = element.get("elements", [])
        element_id = element.get("id", "")
        indent = self.indent_str * depth

        # Determine column size
        col_size = settings.get("_column_size", 100)
        col_class = self._size_to_bootstrap_col(col_size)

        # Convert children
        child_html = "\n".join(
            self._convert_element(child, depth + 1) for child in children
        )

        data_attr = f' data-elementor-id="{element_id}"' if element_id and self.include_metadata else ""

        return f"""{indent}<div class="{col_class}"{data_attr}>
{child_html}
{indent}</div>"""

    def _convert_widget(self, element: Dict[str, Any], depth: int = 0) -> str:
        """Convert a widget to Bootstrap HTML."""
        widget_type = element.get("widgetType", "")

        # Look up converter method
        converter_name = self.WIDGET_MAP.get(widget_type)
        if converter_name and hasattr(self, converter_name):
            converter = getattr(self, converter_name)
            return converter(element, depth)

        # Fallback for unknown widgets
        return self._convert_generic_widget(element, depth)

    def _convert_heading(self, element: Dict[str, Any], depth: int = 0) -> str:
        """Convert heading widget with full typography support."""
        settings = element.get("settings", {})
        indent = self.indent_str * depth

        title = settings.get("title", "")
        tag = settings.get("header_size", "h2")
        align = settings.get("align", "")

        style_parts = []

        # Alignment
        if align:
            style_parts.append(f"text-align: {align}")

        # Color
        color = settings.get("title_color", "")
        if color and not color.startswith("globals"):
            style_parts.append(f"color: {color}")

        # Typography
        font_family = settings.get("typography_font_family", "")
        if font_family:
            style_parts.append(f"font-family: {font_family}, sans-serif")

        font_size = settings.get("typography_font_size", {})
        if isinstance(font_size, dict) and font_size.get("size"):
            style_parts.append(f"font-size: {font_size['size']}{font_size.get('unit', 'px')}")

        font_weight = settings.get("typography_font_weight", "")
        if font_weight:
            style_parts.append(f"font-weight: {font_weight}")

        line_height = settings.get("typography_line_height", {})
        if isinstance(line_height, dict) and line_height.get("size"):
            style_parts.append(f"line-height: {line_height['size']}{line_height.get('unit', 'em')}")

        # Margin
        margin = settings.get("_margin", {})
        if isinstance(margin, dict):
            m_parts = []
            m_parts.append(f"{margin.get('top', '0')}{margin.get('unit', 'px')}")
            m_parts.append(f"{margin.get('right', '0')}{margin.get('unit', 'px')}")
            m_parts.append(f"{margin.get('bottom', '0')}{margin.get('unit', 'px')}")
            m_parts.append(f"{margin.get('left', '0')}{margin.get('unit', 'px')}")
            if any(m != "0px" for m in m_parts):
                style_parts.append(f"margin: {' '.join(m_parts)}")

        style_attr = f' style="{"; ".join(style_parts)}"' if style_parts else ""

        return f"{indent}<{tag}{style_attr}>{escape(title)}</{tag}>"

    def _convert_text_editor(self, element: Dict[str, Any], depth: int = 0) -> str:
        """Convert text-editor widget (preserves HTML content)."""
        settings = element.get("settings", {})
        indent = self.indent_str * depth

        content = settings.get("editor", "")
        align = settings.get("align", "")

        style_parts = []
        if align:
            style_parts.append(f"text-align: {align}")

        # Margin
        margin = settings.get("_margin", {})
        if isinstance(margin, dict):
            m_parts = []
            m_parts.append(f"{margin.get('top', '0')}{margin.get('unit', 'px')}")
            m_parts.append(f"{margin.get('right', '0')}{margin.get('unit', 'px')}")
            m_parts.append(f"{margin.get('bottom', '0')}{margin.get('unit', 'px')}")
            m_parts.append(f"{margin.get('left', '0')}{margin.get('unit', 'px')}")
            if any(m != "0px" for m in m_parts):
                style_parts.append(f"margin: {' '.join(m_parts)}")

        style_attr = f' style="{"; ".join(style_parts)}"' if style_parts else ""

        # Content is already HTML, don't escape
        return f"{indent}<div{style_attr}>{content}</div>"

    def _convert_button(self, element: Dict[str, Any], depth: int = 0) -> str:
        """Convert button widget with full styling support."""
        settings = element.get("settings", {})
        indent = self.indent_str * depth

        text = settings.get("text", "Click Here")
        link = settings.get("link", {})
        url = link.get("url", "#") if isinstance(link, dict) else "#"

        style_parts = []

        # Background color
        bg_color = settings.get("background_color", "")
        if bg_color and bg_color != "transparent":
            style_parts.append(f"background-color: {bg_color}")
        elif bg_color == "transparent":
            style_parts.append("background-color: transparent")

        # Text color
        text_color = settings.get("button_text_color", "#ffffff")
        if text_color:
            style_parts.append(f"color: {text_color}")

        # Border
        border_type = settings.get("border_border", "")
        if border_type == "solid":
            border_width = settings.get("border_width", {})
            border_color = settings.get("border_color", "")
            if isinstance(border_width, dict):
                bw = border_width.get("top", "1")
                style_parts.append(f"border: {bw}px solid {border_color}")
            else:
                style_parts.append(f"border: 2px solid {border_color}")

        # Border radius
        border_radius = settings.get("border_radius", {})
        if isinstance(border_radius, dict) and border_radius.get("top"):
            br = border_radius.get("top", "0")
            unit = border_radius.get("unit", "px")
            style_parts.append(f"border-radius: {br}{unit}")

        # Padding
        btn_padding = settings.get("button_padding", {})
        if isinstance(btn_padding, dict):
            p_parts = []
            p_parts.append(f"{btn_padding.get('top', '12')}{btn_padding.get('unit', 'px')}")
            p_parts.append(f"{btn_padding.get('right', '24')}{btn_padding.get('unit', 'px')}")
            p_parts.append(f"{btn_padding.get('bottom', '12')}{btn_padding.get('unit', 'px')}")
            p_parts.append(f"{btn_padding.get('left', '24')}{btn_padding.get('unit', 'px')}")
            style_parts.append(f"padding: {' '.join(p_parts)}")

        # Typography
        font_weight = settings.get("typography_font_weight", "")
        if font_weight:
            style_parts.append(f"font-weight: {font_weight}")

        font_size = settings.get("typography_font_size", {})
        if isinstance(font_size, dict) and font_size.get("size"):
            style_parts.append(f"font-size: {font_size['size']}{font_size.get('unit', 'px')}")

        # Common button styles
        style_parts.append("text-decoration: none")
        style_parts.append("display: inline-block")
        style_parts.append("cursor: pointer")
        style_parts.append("transition: all 0.3s ease")

        # Hover animation class
        hover_class = ""
        if settings.get("hover_animation") == "grow":
            hover_class = " btn-grow"

        style_attr = f' style="{"; ".join(style_parts)}"'

        return f'{indent}<a href="{escape(url)}" class="btn{hover_class}"{style_attr}>{escape(text)}</a>'

    def _convert_image(self, element: Dict[str, Any], depth: int = 0) -> str:
        """Convert image widget."""
        settings = element.get("settings", {})
        indent = self.indent_str * depth

        image = settings.get("image", {})
        url = image.get("url", "") if isinstance(image, dict) else ""
        alt = image.get("alt", "") if isinstance(image, dict) else ""
        align = settings.get("align", "")

        classes = ["img-fluid"]
        if align == "center":
            classes.append("d-block mx-auto")

        class_attr = " ".join(classes)

        return f'{indent}<img src="{escape(url)}" alt="{escape(alt)}" class="{class_attr}">'

    def _convert_icon_box(self, element: Dict[str, Any], depth: int = 0) -> str:
        """Convert icon-box widget with position and styling support."""
        settings = element.get("settings", {})
        indent = self.indent_str * depth

        title = settings.get("title_text", "")
        description = settings.get("description_text", "")
        position = settings.get("position", "top")  # top, left, right

        # Icon settings
        icon = settings.get("selected_icon", {})
        icon_value = icon.get("value", "fas fa-star") if isinstance(icon, dict) else "fas fa-star"

        # Icon color
        icon_color = settings.get("primary_color", "#e94560")

        # Icon size
        icon_size = settings.get("icon_size", {})
        icon_size_val = icon_size.get("size", 20) if isinstance(icon_size, dict) else 20

        # Title color and typography
        title_color = settings.get("title_color", "#ffffff")
        title_font_size = settings.get("title_typography_font_size", {})
        title_size_val = title_font_size.get("size", 14) if isinstance(title_font_size, dict) else 14
        title_font_weight = settings.get("title_typography_font_weight", "500")

        # Build icon style
        icon_style = f"color: {icon_color}; font-size: {icon_size_val}px"

        # Build title style
        title_style = f"color: {title_color}; font-size: {title_size_val}px; font-weight: {title_font_weight}; margin: 0"

        if position == "left":
            # Horizontal layout with icon on left
            return f"""{indent}<div class="icon-box-horizontal">
{indent}  <div class="icon-wrapper">
{indent}    <i class="{icon_value}" style="{icon_style}"></i>
{indent}  </div>
{indent}  <div class="icon-box-content">
{indent}    <h6 style="{title_style}">{escape(title)}</h6>
{indent}  </div>
{indent}</div>"""
        elif position == "right":
            # Horizontal layout with icon on right
            return f"""{indent}<div class="icon-box-horizontal">
{indent}  <div class="icon-box-content">
{indent}    <h6 style="{title_style}">{escape(title)}</h6>
{indent}  </div>
{indent}  <div class="icon-wrapper">
{indent}    <i class="{icon_value}" style="{icon_style}"></i>
{indent}  </div>
{indent}</div>"""
        else:
            # Vertical layout (top - default)
            desc_html = f'\n{indent}  <p class="text-muted">{escape(description)}</p>' if description else ""
            return f"""{indent}<div class="icon-box-vertical text-center">
{indent}  <i class="{icon_value}" style="{icon_style}; margin-bottom: 10px;"></i>
{indent}  <h6 style="{title_style}">{escape(title)}</h6>{desc_html}
{indent}</div>"""

    def _convert_image_box(self, element: Dict[str, Any], depth: int = 0) -> str:
        """Convert image-box widget."""
        settings = element.get("settings", {})
        indent = self.indent_str * depth

        title = settings.get("title_text", "")
        description = settings.get("description_text", "")
        image = settings.get("image", {})
        image_url = image.get("url", "") if isinstance(image, dict) else ""

        return f"""{indent}<div class="card h-100">
{indent}  <img src="{escape(image_url)}" class="card-img-top" alt="{escape(title)}">
{indent}  <div class="card-body">
{indent}    <h5 class="card-title">{escape(title)}</h5>
{indent}    <p class="card-text">{escape(description)}</p>
{indent}  </div>
{indent}</div>"""

    def _convert_icon_list(self, element: Dict[str, Any], depth: int = 0) -> str:
        """Convert icon-list widget."""
        settings = element.get("settings", {})
        indent = self.indent_str * depth

        items = settings.get("icon_list", [])

        list_items = []
        for item in items:
            if isinstance(item, dict):
                text = item.get("text", "")
                list_items.append(f'{indent}  <li class="list-group-item"><i class="bi-check-circle text-success me-2"></i>{escape(text)}</li>')

        items_html = "\n".join(list_items)
        return f"""{indent}<ul class="list-group list-group-flush">
{items_html}
{indent}</ul>"""

    def _convert_social_icons(self, element: Dict[str, Any], depth: int = 0) -> str:
        """Convert social-icons widget."""
        settings = element.get("settings", {})
        indent = self.indent_str * depth

        icons = settings.get("social_icon_list", [])

        icon_map = {
            "facebook": "bi-facebook",
            "twitter": "bi-twitter-x",
            "instagram": "bi-instagram",
            "linkedin": "bi-linkedin",
            "youtube": "bi-youtube",
        }

        icon_links = []
        for item in icons:
            if isinstance(item, dict):
                social = item.get("social", "").lower()
                link = item.get("link", {})
                url = link.get("url", "#") if isinstance(link, dict) else "#"
                icon_class = icon_map.get(social, "bi-link")
                icon_links.append(f'{indent}  <a href="{escape(url)}" class="btn btn-outline-secondary btn-sm me-2"><i class="{icon_class}"></i></a>')

        icons_html = "\n".join(icon_links)
        return f"""{indent}<div class="social-icons">
{icons_html}
{indent}</div>"""

    def _convert_divider(self, element: Dict[str, Any], depth: int = 0) -> str:
        """Convert divider widget."""
        indent = self.indent_str * depth
        return f"{indent}<hr>"

    def _convert_spacer(self, element: Dict[str, Any], depth: int = 0) -> str:
        """Convert spacer widget."""
        settings = element.get("settings", {})
        indent = self.indent_str * depth

        space = settings.get("space", {})
        size = space.get("size", 50) if isinstance(space, dict) else 50

        return f'{indent}<div style="height: {size}px;"></div>'

    def _convert_html(self, element: Dict[str, Any], depth: int = 0) -> str:
        """Convert HTML widget (pass-through)."""
        settings = element.get("settings", {})
        indent = self.indent_str * depth

        html = settings.get("html", "")
        return f"{indent}{html}"

    def _convert_shortcode(self, element: Dict[str, Any], depth: int = 0) -> str:
        """Convert shortcode widget (placeholder)."""
        settings = element.get("settings", {})
        indent = self.indent_str * depth

        shortcode = settings.get("shortcode", "")
        return f'{indent}<!-- Shortcode: {escape(shortcode)} -->'

    def _convert_alert(self, element: Dict[str, Any], depth: int = 0) -> str:
        """Convert alert widget."""
        settings = element.get("settings", {})
        indent = self.indent_str * depth

        title = settings.get("alert_title", "")
        description = settings.get("alert_description", "")
        alert_type = settings.get("alert_type", "info")

        type_map = {"info": "info", "success": "success", "warning": "warning", "danger": "danger"}
        bs_type = type_map.get(alert_type, "info")

        return f"""{indent}<div class="alert alert-{bs_type}" role="alert">
{indent}  <strong>{escape(title)}</strong> {description}
{indent}</div>"""

    def _convert_tabs(self, element: Dict[str, Any], depth: int = 0) -> str:
        """Convert tabs widget."""
        settings = element.get("settings", {})
        indent = self.indent_str * depth
        element_id = element.get("id", "tabs")

        tabs = settings.get("tabs", [])

        nav_items = []
        tab_panes = []

        for i, tab in enumerate(tabs):
            if isinstance(tab, dict):
                title = tab.get("tab_title", f"Tab {i+1}")
                content = tab.get("tab_content", "")
                tab_id = f"{element_id}-tab-{i}"
                active = "active" if i == 0 else ""
                selected = "true" if i == 0 else "false"

                nav_items.append(f'{indent}    <li class="nav-item"><button class="nav-link {active}" data-bs-toggle="tab" data-bs-target="#{tab_id}" aria-selected="{selected}">{escape(title)}</button></li>')
                tab_panes.append(f'{indent}    <div class="tab-pane fade {"show " + active if active else ""}" id="{tab_id}">{content}</div>')

        nav_html = "\n".join(nav_items)
        panes_html = "\n".join(tab_panes)

        return f"""{indent}<div class="tabs-wrapper">
{indent}  <ul class="nav nav-tabs">
{nav_html}
{indent}  </ul>
{indent}  <div class="tab-content p-3">
{panes_html}
{indent}  </div>
{indent}</div>"""

    def _convert_accordion(self, element: Dict[str, Any], depth: int = 0) -> str:
        """Convert accordion widget."""
        settings = element.get("settings", {})
        indent = self.indent_str * depth
        element_id = element.get("id", "accordion")

        tabs = settings.get("tabs", [])

        items = []
        for i, tab in enumerate(tabs):
            if isinstance(tab, dict):
                title = tab.get("tab_title", f"Item {i+1}")
                content = tab.get("tab_content", "")
                item_id = f"{element_id}-item-{i}"
                collapsed = "" if i == 0 else "collapsed"
                show = "show" if i == 0 else ""

                items.append(f"""{indent}  <div class="accordion-item">
{indent}    <h2 class="accordion-header">
{indent}      <button class="accordion-button {collapsed}" type="button" data-bs-toggle="collapse" data-bs-target="#{item_id}">{escape(title)}</button>
{indent}    </h2>
{indent}    <div id="{item_id}" class="accordion-collapse collapse {show}" data-bs-parent="#{element_id}">
{indent}      <div class="accordion-body">{content}</div>
{indent}    </div>
{indent}  </div>""")

        items_html = "\n".join(items)

        return f"""{indent}<div class="accordion" id="{element_id}">
{items_html}
{indent}</div>"""

    def _convert_testimonial(self, element: Dict[str, Any], depth: int = 0) -> str:
        """Convert testimonial widget."""
        settings = element.get("settings", {})
        indent = self.indent_str * depth

        content = settings.get("testimonial_content", "")
        name = settings.get("testimonial_name", "")
        job = settings.get("testimonial_job", "")
        image = settings.get("testimonial_image", {})
        image_url = image.get("url", "") if isinstance(image, dict) else ""

        img_html = f'<img src="{escape(image_url)}" class="rounded-circle mb-3" width="80" height="80" alt="{escape(name)}">' if image_url else ""

        return f"""{indent}<div class="testimonial text-center p-4">
{indent}  {img_html}
{indent}  <blockquote class="blockquote">
{indent}    <p>{content}</p>
{indent}  </blockquote>
{indent}  <figcaption class="blockquote-footer mt-2">
{indent}    {escape(name)} <cite title="Position">{escape(job)}</cite>
{indent}  </figcaption>
{indent}</div>"""

    def _convert_counter(self, element: Dict[str, Any], depth: int = 0) -> str:
        """Convert counter widget."""
        settings = element.get("settings", {})
        indent = self.indent_str * depth

        number = settings.get("ending_number", "100")
        title = settings.get("title", "")
        prefix = settings.get("prefix", "")
        suffix = settings.get("suffix", "")

        return f"""{indent}<div class="counter text-center">
{indent}  <h2 class="display-4 fw-bold">{escape(prefix)}{escape(str(number))}{escape(suffix)}</h2>
{indent}  <p class="text-muted">{escape(title)}</p>
{indent}</div>"""

    def _convert_progress(self, element: Dict[str, Any], depth: int = 0) -> str:
        """Convert progress widget."""
        settings = element.get("settings", {})
        indent = self.indent_str * depth

        title = settings.get("title", "")
        percent = settings.get("percent", {})
        value = percent.get("size", 50) if isinstance(percent, dict) else 50

        return f"""{indent}<div class="progress-wrapper mb-3">
{indent}  <div class="d-flex justify-content-between mb-1">
{indent}    <span>{escape(title)}</span>
{indent}    <span>{value}%</span>
{indent}  </div>
{indent}  <div class="progress">
{indent}    <div class="progress-bar" role="progressbar" style="width: {value}%" aria-valuenow="{value}" aria-valuemin="0" aria-valuemax="100"></div>
{indent}  </div>
{indent}</div>"""

    def _convert_video(self, element: Dict[str, Any], depth: int = 0) -> str:
        """Convert video widget."""
        settings = element.get("settings", {})
        indent = self.indent_str * depth

        video_type = settings.get("video_type", "youtube")
        youtube_url = settings.get("youtube_url", "")

        # Extract YouTube video ID
        video_id = ""
        if "youtube.com" in youtube_url:
            if "v=" in youtube_url:
                video_id = youtube_url.split("v=")[1].split("&")[0]
            elif "youtu.be" in youtube_url:
                video_id = youtube_url.split("/")[-1]

        if video_id:
            return f"""{indent}<div class="ratio ratio-16x9">
{indent}  <iframe src="https://www.youtube.com/embed/{video_id}" allowfullscreen></iframe>
{indent}</div>"""

        return f'{indent}<!-- Video: {escape(youtube_url)} -->'

    def _convert_nav_menu(self, element: Dict[str, Any], depth: int = 0) -> str:
        """Convert nav-menu/mega-menu widget."""
        settings = element.get("settings", {})
        indent = self.indent_str * depth

        return f"""{indent}<nav class="navbar navbar-expand-lg">
{indent}  <div class="container">
{indent}    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
{indent}      <span class="navbar-toggler-icon"></span>
{indent}    </button>
{indent}    <div class="collapse navbar-collapse" id="navbarNav">
{indent}      <ul class="navbar-nav">
{indent}        <!-- Menu items would be populated from WordPress menu -->
{indent}      </ul>
{indent}    </div>
{indent}  </div>
{indent}</nav>"""

    def _convert_site_logo(self, element: Dict[str, Any], depth: int = 0) -> str:
        """Convert theme-site-logo widget."""
        settings = element.get("settings", {})
        indent = self.indent_str * depth

        return f'{indent}<a href="/" class="navbar-brand">Site Logo</a>'

    def _convert_form(self, element: Dict[str, Any], depth: int = 0) -> str:
        """Convert form widget."""
        settings = element.get("settings", {})
        indent = self.indent_str * depth

        fields = settings.get("form_fields", [])

        field_html = []
        for field in fields:
            if isinstance(field, dict):
                field_type = field.get("field_type", "text")
                label = field.get("field_label", "")
                placeholder = field.get("placeholder", "")
                required = field.get("required", False)

                req_attr = ' required' if required else ''

                if field_type == "textarea":
                    field_html.append(f"""{indent}  <div class="mb-3">
{indent}    <label class="form-label">{escape(label)}</label>
{indent}    <textarea class="form-control" placeholder="{escape(placeholder)}"{req_attr}></textarea>
{indent}  </div>""")
                else:
                    field_html.append(f"""{indent}  <div class="mb-3">
{indent}    <label class="form-label">{escape(label)}</label>
{indent}    <input type="{field_type}" class="form-control" placeholder="{escape(placeholder)}"{req_attr}>
{indent}  </div>""")

        fields_html = "\n".join(field_html)
        button_text = settings.get("button_text", "Submit")

        return f"""{indent}<form>
{fields_html}
{indent}  <button type="submit" class="btn btn-primary">{escape(button_text)}</button>
{indent}</form>"""

    def _convert_generic_widget(self, element: Dict[str, Any], depth: int = 0) -> str:
        """Fallback converter for unknown widgets."""
        settings = element.get("settings", {})
        widget_type = element.get("widgetType", "unknown")
        indent = self.indent_str * depth

        # Try to extract any text content
        content = ""
        for key in ["title", "text", "content", "editor", "description"]:
            if key in settings:
                content = settings[key]
                break

        if content:
            return f'{indent}<div class="widget-{widget_type}">{content}</div>'

        return f'{indent}<!-- Widget: {widget_type} -->'

    def _get_background_class(self, settings: Dict[str, Any]) -> str:
        """Get Bootstrap background class from settings."""
        bg = settings.get("background_background", "")

        if bg == "classic":
            color = settings.get("background_color", "")
            # Check for globals reference
            globals_ref = settings.get("__globals__", {})
            if isinstance(globals_ref, dict):
                global_color = globals_ref.get("background_color", "")
                if "primary" in global_color:
                    return "bg-primary"
                elif "secondary" in global_color:
                    return "bg-secondary"

            # Direct color mapping
            if color:
                if color.lower() in ["#007bff", "#0d6efd"]:
                    return "bg-primary"
                elif color.lower() == "#ffffff":
                    return "bg-white"
                elif color.lower() in ["#000000", "#212529"]:
                    return "bg-dark"

        return ""

    def _get_text_color_class(self, settings: Dict[str, Any]) -> str:
        """Get Bootstrap text color class from settings."""
        # Check for light backgrounds
        bg_color = settings.get("background_color", "").lower()
        if bg_color in ["#ffffff", "#f8f9fa", "#fff"]:
            return ""
        elif bg_color and bg_color not in ["", "#"]:
            return "text-white"
        return ""

    def _size_to_bootstrap_col(self, size: int) -> str:
        """Convert Elementor column size (percentage) to Bootstrap col class."""
        if size >= 100:
            return "col-12"
        elif size >= 75:
            return "col-lg-9"
        elif size >= 66:
            return "col-lg-8"
        elif size >= 50:
            return "col-lg-6"
        elif size >= 33:
            return "col-lg-4"
        elif size >= 25:
            return "col-lg-3"
        else:
            return "col"
