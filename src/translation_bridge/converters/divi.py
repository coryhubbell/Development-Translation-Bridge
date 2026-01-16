"""
Translation Bridge v4 - DIVI Shortcode Converter.

Converts universal/parsed data TO DIVI Builder shortcode format.
Generates proper shortcode structure: [et_pb_section][et_pb_row][et_pb_column][et_pb_module]
"""

from typing import Any, Dict, List, Optional
from html import escape


class DiviConverter:
    """
    Converts parsed content to DIVI Builder shortcode format.

    DIVI structure:
    [et_pb_section]
      [et_pb_row]
        [et_pb_column]
          [et_pb_text] or other module
        [/et_pb_column]
      [/et_pb_row]
    [/et_pb_section]
    """

    # Universal type to DIVI module mapping
    MODULE_TYPE_MAP = {
        "heading": "et_pb_text",
        "text": "et_pb_text",
        "paragraph": "et_pb_text",
        "image": "et_pb_image",
        "button": "et_pb_button",
        "divider": "et_pb_divider",
        "spacer": "et_pb_divider",
        "icon": "et_pb_blurb",
        "icon-box": "et_pb_blurb",
        "image-box": "et_pb_blurb",
        "counter": "et_pb_number_counter",
        "progress": "et_pb_counters",
        "testimonial": "et_pb_testimonial",
        "tabs": "et_pb_tabs",
        "accordion": "et_pb_accordion",
        "alert": "et_pb_text",
        "video": "et_pb_video",
        "gallery": "et_pb_gallery",
        "carousel": "et_pb_gallery",
        "form": "et_pb_contact_form",
        "nav": "et_pb_menu",
        "menu": "et_pb_menu",
        "cta": "et_pb_cta",
        "html": "et_pb_code",
        "slider": "et_pb_slider",
    }

    def __init__(self):
        self._module_counter = 0

    def convert(self, data: Any) -> str:
        """
        Convert universal data to DIVI shortcode string.

        Args:
            data: Universal component data or list of components

        Returns:
            DIVI shortcode string
        """
        return self._convert_to_shortcode(data)

    def _convert_to_shortcode(self, data: Any) -> str:
        """Convert data to DIVI shortcode structure."""
        if isinstance(data, dict):
            # Check if it has elements array
            if "elements" in data:
                return self._convert_elements(data["elements"])

            # Single component - wrap in section
            module = self._convert_component(data)
            return self._wrap_in_section(module)

        elif isinstance(data, list):
            # Convert list of elements
            return self._convert_elements(data)

        return ""

    def _convert_elements(self, elements: List[Dict[str, Any]]) -> str:
        """Convert a list of elements to shortcodes."""
        sections = []

        for element in elements:
            el_type = element.get("elType", "")

            if el_type == "section" or el_type == "container":
                sections.append(self._convert_section(element))
            elif el_type == "column":
                # Wrap orphan column in section/row
                sections.append(self._wrap_column_in_section(element))
            elif el_type == "widget":
                # Wrap widget in full structure
                module = self._convert_widget(element)
                sections.append(self._wrap_in_section(module))
            else:
                # Generic component
                module = self._convert_component(element)
                sections.append(self._wrap_in_section(module))

        return "\n\n".join(sections)

    def _convert_section(self, section: Dict[str, Any]) -> str:
        """Convert a section element to DIVI shortcode."""
        settings = section.get("settings", {})
        children = section.get("elements", [])

        # Build section attributes
        attrs = self._build_section_attrs(settings)
        attrs_str = self._attrs_to_string(attrs)

        # Convert children (rows/columns)
        rows_content = []
        for child in children:
            el_type = child.get("elType", "")
            if el_type == "column":
                # Single column, wrap in row
                col = self._convert_column(child)
                rows_content.append(f'[et_pb_row]{col}[/et_pb_row]')
            else:
                # Assume it's a row structure
                rows_content.append(self._convert_row_from_columns(children))
                break

        inner = "\n".join(rows_content)
        return f'[et_pb_section{attrs_str}]\n{inner}\n[/et_pb_section]'

    def _convert_row_from_columns(self, columns: List[Dict[str, Any]]) -> str:
        """Convert columns to a DIVI row."""
        cols_content = []
        for col in columns:
            if col.get("elType") == "column":
                cols_content.append(self._convert_column(col))

        inner = "\n".join(cols_content)
        return f'[et_pb_row]\n{inner}\n[/et_pb_row]'

    def _convert_column(self, column: Dict[str, Any]) -> str:
        """Convert a column element to DIVI shortcode."""
        settings = column.get("settings", {})
        children = column.get("elements", [])

        # Determine column type based on size
        col_size = settings.get("_column_size", 100)
        col_type = self._size_to_divi_column(col_size)

        # Convert children (widgets)
        modules = []
        for child in children:
            if child.get("elType") == "widget":
                modules.append(self._convert_widget(child))
            else:
                modules.append(self._convert_component(child))

        inner = "\n".join(modules)
        return f'[et_pb_column type="{col_type}"]\n{inner}\n[/et_pb_column]'

    def _convert_widget(self, widget: Dict[str, Any]) -> str:
        """Convert an Elementor widget to DIVI module."""
        widget_type = widget.get("widgetType", "text")
        settings = widget.get("settings", {})

        return self._build_module(widget_type, settings)

    def _convert_component(self, component: Dict[str, Any]) -> str:
        """Convert a generic component to DIVI module."""
        comp_type = component.get("type", component.get("widgetType", "text"))
        attrs = component.get("attributes", component.get("settings", {}))
        content = component.get("content", "")

        return self._build_module(comp_type, attrs, content)

    def _build_module(self, comp_type: str, settings: Dict[str, Any], content: str = "") -> str:
        """Build a DIVI module shortcode."""
        module_type = self.MODULE_TYPE_MAP.get(comp_type, "et_pb_text")

        if module_type == "et_pb_text":
            return self._build_text_module(settings, content)
        elif module_type == "et_pb_image":
            return self._build_image_module(settings)
        elif module_type == "et_pb_button":
            return self._build_button_module(settings)
        elif module_type == "et_pb_blurb":
            return self._build_blurb_module(settings)
        elif module_type == "et_pb_number_counter":
            return self._build_counter_module(settings)
        elif module_type == "et_pb_testimonial":
            return self._build_testimonial_module(settings)
        elif module_type == "et_pb_tabs":
            return self._build_tabs_module(settings)
        elif module_type == "et_pb_accordion":
            return self._build_accordion_module(settings)
        elif module_type == "et_pb_video":
            return self._build_video_module(settings)
        elif module_type == "et_pb_gallery":
            return self._build_gallery_module(settings)
        elif module_type == "et_pb_cta":
            return self._build_cta_module(settings)
        elif module_type == "et_pb_code":
            return self._build_code_module(settings, content)
        elif module_type == "et_pb_divider":
            return self._build_divider_module(settings)
        else:
            # Default to text module
            return self._build_text_module(settings, content)

    def _build_text_module(self, settings: Dict[str, Any], content: str = "") -> str:
        """Build et_pb_text module."""
        text = content or settings.get("editor", settings.get("title", ""))

        # Handle heading
        if settings.get("header_size"):
            tag = settings.get("header_size", "h2")
            text = f"<{tag}>{text}</{tag}>"

        attrs = {}
        if settings.get("text_orientation"):
            attrs["text_orientation"] = settings["text_orientation"]
        if settings.get("align"):
            attrs["text_orientation"] = settings["align"]

        attrs_str = self._attrs_to_string(attrs)
        return f'[et_pb_text{attrs_str}]\n{text}\n[/et_pb_text]'

    def _build_image_module(self, settings: Dict[str, Any]) -> str:
        """Build et_pb_image module."""
        image = settings.get("image", {})
        url = image.get("url", "") if isinstance(image, dict) else ""
        alt = image.get("alt", "") if isinstance(image, dict) else ""

        attrs = {"src": url, "alt": alt}

        if settings.get("align"):
            attrs["align"] = settings["align"]

        attrs_str = self._attrs_to_string(attrs)
        return f'[et_pb_image{attrs_str} /]'

    def _build_button_module(self, settings: Dict[str, Any]) -> str:
        """Build et_pb_button module."""
        text = settings.get("text", "Click Here")
        link = settings.get("link", {})
        url = link.get("url", "#") if isinstance(link, dict) else "#"

        attrs = {
            "button_text": text,
            "button_url": url,
        }

        if settings.get("button_alignment"):
            attrs["button_alignment"] = settings["button_alignment"]

        attrs_str = self._attrs_to_string(attrs)
        return f'[et_pb_button{attrs_str} /]'

    def _build_blurb_module(self, settings: Dict[str, Any]) -> str:
        """Build et_pb_blurb module (icon box)."""
        title = settings.get("title_text", settings.get("title", ""))
        content = settings.get("description_text", "")

        icon = settings.get("selected_icon", {})
        icon_value = icon.get("value", "fas fa-star") if isinstance(icon, dict) else "fas fa-star"

        # Convert FontAwesome class to DIVI icon format
        divi_icon = self._fa_to_divi_icon(icon_value)

        attrs = {
            "title": title,
            "use_icon": "on",
            "font_icon": divi_icon,
        }

        attrs_str = self._attrs_to_string(attrs)
        return f'[et_pb_blurb{attrs_str}]\n{content}\n[/et_pb_blurb]'

    def _build_counter_module(self, settings: Dict[str, Any]) -> str:
        """Build et_pb_number_counter module."""
        number = settings.get("ending_number", "100")
        title = settings.get("title", "")

        attrs = {
            "title": title,
            "number": number,
        }

        attrs_str = self._attrs_to_string(attrs)
        return f'[et_pb_number_counter{attrs_str} /]'

    def _build_testimonial_module(self, settings: Dict[str, Any]) -> str:
        """Build et_pb_testimonial module."""
        content = settings.get("testimonial_content", "")
        author = settings.get("testimonial_name", "")
        job = settings.get("testimonial_job", "")
        image = settings.get("testimonial_image", {})
        portrait_url = image.get("url", "") if isinstance(image, dict) else ""

        attrs = {
            "author": author,
            "job_title": job,
        }
        if portrait_url:
            attrs["portrait_url"] = portrait_url

        attrs_str = self._attrs_to_string(attrs)
        return f'[et_pb_testimonial{attrs_str}]\n{content}\n[/et_pb_testimonial]'

    def _build_tabs_module(self, settings: Dict[str, Any]) -> str:
        """Build et_pb_tabs module."""
        tabs = settings.get("tabs", [])

        tab_items = []
        for tab in tabs:
            title = tab.get("tab_title", "Tab")
            content = tab.get("tab_content", "")
            tab_items.append(f'[et_pb_tab title="{escape(title)}"]\n{content}\n[/et_pb_tab]')

        inner = "\n".join(tab_items)
        return f'[et_pb_tabs]\n{inner}\n[/et_pb_tabs]'

    def _build_accordion_module(self, settings: Dict[str, Any]) -> str:
        """Build et_pb_accordion module."""
        items = settings.get("tabs", [])

        acc_items = []
        for i, item in enumerate(items):
            title = item.get("tab_title", f"Item {i+1}")
            content = item.get("tab_content", "")
            open_state = "on" if i == 0 else "off"
            acc_items.append(f'[et_pb_accordion_item title="{escape(title)}" open="{open_state}"]\n{content}\n[/et_pb_accordion_item]')

        inner = "\n".join(acc_items)
        return f'[et_pb_accordion]\n{inner}\n[/et_pb_accordion]'

    def _build_video_module(self, settings: Dict[str, Any]) -> str:
        """Build et_pb_video module."""
        url = settings.get("youtube_url", settings.get("video_url", ""))

        attrs = {"src": url}
        attrs_str = self._attrs_to_string(attrs)
        return f'[et_pb_video{attrs_str} /]'

    def _build_gallery_module(self, settings: Dict[str, Any]) -> str:
        """Build et_pb_gallery module."""
        gallery = settings.get("gallery", settings.get("wp_gallery", []))

        gallery_ids = []
        for img in gallery:
            if isinstance(img, dict) and img.get("id"):
                gallery_ids.append(str(img["id"]))

        attrs = {}
        if gallery_ids:
            attrs["gallery_ids"] = ",".join(gallery_ids)

        attrs["fullwidth"] = "off"
        columns = settings.get("columns", 3)
        attrs["posts_number"] = str(columns)

        attrs_str = self._attrs_to_string(attrs)
        return f'[et_pb_gallery{attrs_str} /]'

    def _build_cta_module(self, settings: Dict[str, Any]) -> str:
        """Build et_pb_cta module."""
        title = settings.get("title", "")
        content = settings.get("description", "")
        button_text = settings.get("button", "Click Here")
        link = settings.get("link", {})
        url = link.get("url", "#") if isinstance(link, dict) else "#"

        attrs = {
            "title": title,
            "button_text": button_text,
            "button_url": url,
        }

        attrs_str = self._attrs_to_string(attrs)
        return f'[et_pb_cta{attrs_str}]\n{content}\n[/et_pb_cta]'

    def _build_code_module(self, settings: Dict[str, Any], content: str = "") -> str:
        """Build et_pb_code module for raw HTML."""
        html = content or settings.get("html", "")
        return f'[et_pb_code]\n{html}\n[/et_pb_code]'

    def _build_divider_module(self, settings: Dict[str, Any]) -> str:
        """Build et_pb_divider module."""
        attrs = {"show_divider": "on"}

        space = settings.get("space", {})
        if isinstance(space, dict) and space.get("size"):
            attrs["divider_weight"] = f"{space['size']}{space.get('unit', 'px')}"

        attrs_str = self._attrs_to_string(attrs)
        return f'[et_pb_divider{attrs_str} /]'

    def _build_section_attrs(self, settings: Dict[str, Any]) -> Dict[str, str]:
        """Build section attributes from settings."""
        attrs = {}

        bg_color = settings.get("background_color", "")
        if bg_color and not bg_color.startswith("globals"):
            attrs["background_color"] = bg_color

        return attrs

    def _wrap_in_section(self, module: str) -> str:
        """Wrap a module in section > row > column structure."""
        return f'''[et_pb_section]
[et_pb_row]
[et_pb_column type="4_4"]
{module}
[/et_pb_column]
[/et_pb_row]
[/et_pb_section]'''

    def _wrap_column_in_section(self, column: Dict[str, Any]) -> str:
        """Wrap a column in section > row structure."""
        col = self._convert_column(column)
        return f'''[et_pb_section]
[et_pb_row]
{col}
[/et_pb_row]
[/et_pb_section]'''

    def _size_to_divi_column(self, size: int) -> str:
        """Convert column size percentage to DIVI column type."""
        if size >= 100:
            return "4_4"
        elif size >= 75:
            return "3_4"
        elif size >= 66:
            return "2_3"
        elif size >= 50:
            return "1_2"
        elif size >= 33:
            return "1_3"
        elif size >= 25:
            return "1_4"
        else:
            return "4_4"

    def _fa_to_divi_icon(self, fa_class: str) -> str:
        """Convert FontAwesome class to DIVI icon format."""
        # DIVI uses a different icon format
        # This is a simplified mapping
        icon_map = {
            "fas fa-star": "&#xe087;",
            "fas fa-check": "&#xe073;",
            "fas fa-heart": "&#xe089;",
            "fas fa-phone": "&#xe090;",
            "fas fa-envelope": "&#xe076;",
            "fas fa-user": "&#xe08a;",
            "fas fa-home": "&#xe074;",
        }
        return icon_map.get(fa_class, "&#xe087;")

    def _attrs_to_string(self, attrs: Dict[str, str]) -> str:
        """Convert attributes dict to shortcode attribute string."""
        if not attrs:
            return ""

        parts = [f' {key}="{escape(str(value))}"' for key, value in attrs.items() if value]
        return "".join(parts)

    def get_framework(self) -> str:
        """Return framework name."""
        return "divi"

    def get_supported_types(self) -> List[str]:
        """Return list of supported module types."""
        return list(self.MODULE_TYPE_MAP.keys())
