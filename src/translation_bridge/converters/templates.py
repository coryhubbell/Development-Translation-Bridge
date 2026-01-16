"""
Translation Bridge v4 - Template Part Converter.

Converts Elementor Theme Builder templates (headers, footers, singles, archives)
to standalone HTML partials with support for static site generator includes.

Supports output formats:
- HTML includes (Jekyll, Hugo, 11ty)
- PHP includes (WordPress themes)
- Jinja2 templates (Python static sites)
- Handlebars partials (JavaScript frameworks)
"""

from typing import Any, Dict, List, Optional, Tuple
from dataclasses import dataclass, field
import re

from .bootstrap import BootstrapConverter


@dataclass
class DynamicPlaceholder:
    """Represents a dynamic content placeholder."""

    name: str
    elementor_type: str  # theme-site-logo, nav-menu, etc.
    default_value: str = ""
    variable_syntax: str = ""  # {{ site.title }} for Jinja2, <?php bloginfo() ?> for PHP

    def to_html_comment(self) -> str:
        """Convert to HTML comment placeholder."""
        return f"<!-- PLACEHOLDER: {self.name} -->"

    def to_jinja2(self) -> str:
        """Convert to Jinja2 syntax."""
        return f"{{{{ {self.name} }}}}"

    def to_php(self) -> str:
        """Convert to PHP syntax."""
        php_functions = {
            "site_title": "<?php bloginfo('name'); ?>",
            "site_description": "<?php bloginfo('description'); ?>",
            "site_url": "<?php echo home_url(); ?>",
            "site_logo": "<?php the_custom_logo(); ?>",
            "nav_menu": "<?php wp_nav_menu(array('theme_location' => 'primary')); ?>",
            "search_form": "<?php get_search_form(); ?>",
            "year": "<?php echo date('Y'); ?>",
        }
        return php_functions.get(self.name, f"<?php /* {self.name} */ ?>")


@dataclass
class TemplatePartConfig:
    """Configuration for template part output."""

    format: str = "html"  # html, php, jinja2, handlebars
    include_wrapper: bool = True
    wrapper_tag: str = "header"  # header, footer, nav, aside
    wrapper_classes: List[str] = field(default_factory=list)
    preserve_dynamic: bool = True  # Keep placeholders for dynamic content


class TemplateConverter:
    """
    Converts Elementor Theme Builder templates to various output formats.

    Handles dynamic content widgets:
    - theme-site-logo → Site logo/branding
    - nav-menu / mega-menu → Navigation menus
    - theme-site-title → Site name
    - search-form → Search box
    - theme-page-title → Page/post title
    - theme-post-content → Post content area
    """

    # Mapping of Elementor widgets to dynamic placeholders
    DYNAMIC_WIDGET_MAP = {
        "theme-site-logo": DynamicPlaceholder(
            name="site_logo",
            elementor_type="theme-site-logo",
            default_value='<a href="/" class="navbar-brand">Logo</a>',
        ),
        "theme-site-title": DynamicPlaceholder(
            name="site_title",
            elementor_type="theme-site-title",
            default_value="Site Title",
        ),
        "nav-menu": DynamicPlaceholder(
            name="nav_menu",
            elementor_type="nav-menu",
            default_value='<nav class="navbar-nav"><!-- Menu items --></nav>',
        ),
        "mega-menu": DynamicPlaceholder(
            name="nav_menu",
            elementor_type="mega-menu",
            default_value='<nav class="navbar-nav"><!-- Menu items --></nav>',
        ),
        "search-form": DynamicPlaceholder(
            name="search_form",
            elementor_type="search-form",
            default_value='<form role="search"><input type="search" placeholder="Search..."></form>',
        ),
        "theme-page-title": DynamicPlaceholder(
            name="page_title",
            elementor_type="theme-page-title",
            default_value="Page Title",
        ),
        "theme-post-title": DynamicPlaceholder(
            name="post_title",
            elementor_type="theme-post-title",
            default_value="Post Title",
        ),
        "theme-post-content": DynamicPlaceholder(
            name="post_content",
            elementor_type="theme-post-content",
            default_value="<!-- Post content area -->",
        ),
        "theme-post-featured-image": DynamicPlaceholder(
            name="featured_image",
            elementor_type="theme-post-featured-image",
            default_value='<img src="placeholder.jpg" class="featured-image">',
        ),
    }

    def __init__(self, config: Optional[TemplatePartConfig] = None):
        self.config = config or TemplatePartConfig()
        self.bootstrap_converter = BootstrapConverter(include_metadata=False)
        self.dynamic_placeholders: List[DynamicPlaceholder] = []

    def convert_header(self, template_data: Dict[str, Any]) -> str:
        """
        Convert a header template.

        Args:
            template_data: Elementor template JSON data

        Returns:
            Converted header HTML/template string
        """
        self.config.wrapper_tag = "header"
        self.config.wrapper_classes = ["site-header"]
        return self._convert_template(template_data)

    def convert_footer(self, template_data: Dict[str, Any]) -> str:
        """
        Convert a footer template.

        Args:
            template_data: Elementor template JSON data

        Returns:
            Converted footer HTML/template string
        """
        self.config.wrapper_tag = "footer"
        self.config.wrapper_classes = ["site-footer"]
        return self._convert_template(template_data)

    def convert_sidebar(self, template_data: Dict[str, Any]) -> str:
        """
        Convert a sidebar template.

        Args:
            template_data: Elementor template JSON data

        Returns:
            Converted sidebar HTML/template string
        """
        self.config.wrapper_tag = "aside"
        self.config.wrapper_classes = ["site-sidebar"]
        return self._convert_template(template_data)

    def convert_single(self, template_data: Dict[str, Any]) -> str:
        """
        Convert a single post template.

        Args:
            template_data: Elementor template JSON data

        Returns:
            Converted single post template string
        """
        self.config.wrapper_tag = "article"
        self.config.wrapper_classes = ["post-single"]
        return self._convert_template(template_data)

    def _convert_template(self, template_data: Dict[str, Any]) -> str:
        """
        Convert any template type.

        Args:
            template_data: Elementor template JSON data

        Returns:
            Converted template string
        """
        self.dynamic_placeholders = []

        # Pre-process to mark dynamic widgets
        processed_data = self._preprocess_dynamic_widgets(template_data)

        # Convert using Bootstrap converter
        html = self.bootstrap_converter.convert_fragment(processed_data)

        # Post-process dynamic placeholders based on output format
        html = self._postprocess_placeholders(html)

        # Add wrapper if configured
        if self.config.include_wrapper:
            html = self._add_wrapper(html)

        return html

    def _preprocess_dynamic_widgets(self, data: Any) -> Any:
        """
        Pre-process data to mark dynamic widgets for special handling.

        Args:
            data: Elementor JSON data

        Returns:
            Processed data with dynamic widget markers
        """
        if isinstance(data, dict):
            widget_type = data.get("widgetType", "")

            if widget_type in self.DYNAMIC_WIDGET_MAP:
                # Record the placeholder
                placeholder = self.DYNAMIC_WIDGET_MAP[widget_type]
                self.dynamic_placeholders.append(placeholder)

                # Replace with placeholder marker
                data = dict(data)
                data["_dynamic_placeholder"] = placeholder.name
                data["_dynamic_default"] = placeholder.default_value

            # Process children
            if "elements" in data:
                data["elements"] = [
                    self._preprocess_dynamic_widgets(el)
                    for el in data["elements"]
                ]
            if "settings" in data:
                data["settings"] = self._preprocess_dynamic_widgets(data["settings"])

            return data

        elif isinstance(data, list):
            return [self._preprocess_dynamic_widgets(item) for item in data]

        return data

    def _postprocess_placeholders(self, html: str) -> str:
        """
        Replace placeholder markers with format-specific syntax.

        Args:
            html: HTML with placeholder markers

        Returns:
            HTML with proper placeholder syntax
        """
        for placeholder in self.dynamic_placeholders:
            marker = f"<!-- PLACEHOLDER: {placeholder.name} -->"

            if self.config.format == "jinja2":
                replacement = placeholder.to_jinja2()
            elif self.config.format == "php":
                replacement = placeholder.to_php()
            elif self.config.format == "handlebars":
                replacement = f"{{{{ {placeholder.name} }}}}"
            else:
                # HTML - use default value or comment
                if self.config.preserve_dynamic:
                    replacement = f"<!-- DYNAMIC: {placeholder.name} -->\n{placeholder.default_value}"
                else:
                    replacement = placeholder.default_value

            # Replace any existing markers
            html = html.replace(marker, replacement)

            # Also replace any Site Logo / nav-menu placeholders from Bootstrap converter
            if placeholder.name == "site_logo":
                html = re.sub(
                    r'<a href="/" class="navbar-brand">Site Logo</a>',
                    replacement,
                    html
                )
            elif placeholder.name == "nav_menu":
                html = re.sub(
                    r'<!-- Menu items would be populated from WordPress menu -->',
                    replacement if self.config.preserve_dynamic else "",
                    html
                )

        return html

    def _add_wrapper(self, html: str) -> str:
        """
        Add semantic wrapper element around template content.

        Args:
            html: Inner HTML content

        Returns:
            HTML wrapped in appropriate element
        """
        classes = " ".join(self.config.wrapper_classes) if self.config.wrapper_classes else ""
        class_attr = f' class="{classes}"' if classes else ""

        return f"<{self.config.wrapper_tag}{class_attr}>\n{html}\n</{self.config.wrapper_tag}>"

    def generate_includes_documentation(self) -> str:
        """
        Generate documentation for using the includes.

        Returns:
            Markdown documentation string
        """
        lines = [
            "# Template Includes Usage",
            "",
            "## Available Partials",
            "",
        ]

        if self.config.format == "jinja2":
            lines.extend([
                "### Jinja2 (Jekyll, 11ty, Python)",
                "```html",
                "{% include '_includes/header.html' %}",
                "",
                "<!-- Page content -->",
                "",
                "{% include '_includes/footer.html' %}",
                "```",
            ])
        elif self.config.format == "php":
            lines.extend([
                "### PHP (WordPress Theme)",
                "```php",
                "<?php get_template_part('partials/header'); ?>",
                "",
                "<!-- Page content -->",
                "",
                "<?php get_template_part('partials/footer'); ?>",
                "```",
            ])
        else:
            lines.extend([
                "### HTML (Static)",
                "```html",
                "<!-- For static sites, use SSI or build-time includes -->",
                "<!--#include file=\"_includes/header.html\" -->",
                "",
                "<!-- Page content -->",
                "",
                "<!--#include file=\"_includes/footer.html\" -->",
                "```",
            ])

        lines.extend([
            "",
            "## Dynamic Placeholders",
            "",
            "The following dynamic content placeholders are used:",
            "",
        ])

        for placeholder in self.dynamic_placeholders:
            lines.append(f"- **{placeholder.name}**: {placeholder.elementor_type}")

        return "\n".join(lines)


class TemplatePartGenerator:
    """
    High-level generator for creating all template parts from a site export.

    Orchestrates the conversion of headers, footers, and other template parts
    into a consistent output structure.
    """

    def __init__(self, output_format: str = "html"):
        self.output_format = output_format
        self.converter = TemplateConverter(TemplatePartConfig(format=output_format))

    def generate_all(
        self,
        templates: List[Dict[str, Any]]
    ) -> Dict[str, str]:
        """
        Generate all template parts from a list of templates.

        Args:
            templates: List of template data dicts with 'type' and 'content' keys

        Returns:
            Dict mapping template names to converted content
        """
        output = {}

        for template in templates:
            template_type = template.get("type", "unknown")
            template_data = template.get("document", template.get("content", {}))

            if template_type == "header":
                output["header.html"] = self.converter.convert_header(template_data)
            elif template_type == "footer":
                output["footer.html"] = self.converter.convert_footer(template_data)
            elif template_type == "sidebar":
                output["sidebar.html"] = self.converter.convert_sidebar(template_data)
            elif template_type == "single":
                output["single.html"] = self.converter.convert_single(template_data)
            elif template_type == "archive":
                self.converter.config.wrapper_tag = "main"
                self.converter.config.wrapper_classes = ["archive-content"]
                output["archive.html"] = self.converter._convert_template(template_data)

        # Generate documentation
        output["README.md"] = self.converter.generate_includes_documentation()

        return output

    def generate_base_layout(
        self,
        header_html: str = "",
        footer_html: str = "",
        title: str = "Page Title",
        site_name: str = "Site Name"
    ) -> str:
        """
        Generate a complete base layout template.

        Args:
            header_html: Converted header content
            footer_html: Converted footer content
            title: Page title placeholder
            site_name: Site name

        Returns:
            Complete base layout template
        """
        if self.output_format == "jinja2":
            return self._generate_jinja2_layout(header_html, footer_html, title, site_name)
        elif self.output_format == "php":
            return self._generate_php_layout(header_html, footer_html, title, site_name)
        else:
            return self._generate_html_layout(header_html, footer_html, title, site_name)

    def _generate_html_layout(
        self,
        header_html: str,
        footer_html: str,
        title: str,
        site_name: str
    ) -> str:
        """Generate plain HTML layout."""
        return f"""<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{title} - {site_name}</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
{header_html}

<main class="site-content">
  <!-- Page content here -->
</main>

{footer_html}

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>"""

    def _generate_jinja2_layout(
        self,
        header_html: str,
        footer_html: str,
        title: str,
        site_name: str
    ) -> str:
        """Generate Jinja2 layout template."""
        return f"""{{% extends "base.html" %}}

{{% block head %}}
<title>{{{{ page.title }}}} - {{{{ site.name }}}}</title>
<link rel="stylesheet" href="{{{{ '/assets/styles.css' | url }}}}">
{{% endblock %}}

{{% block header %}}
{header_html}
{{% endblock %}}

{{% block content %}}
<!-- Page content injected here -->
{{{{ content }}}}
{{% endblock %}}

{{% block footer %}}
{footer_html}
{{% endblock %}}"""

    def _generate_php_layout(
        self,
        header_html: str,
        footer_html: str,
        title: str,
        site_name: str
    ) -> str:
        """Generate PHP layout template."""
        return f"""<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

{header_html}

<main class="site-content">
  <?php
  if (have_posts()) :
    while (have_posts()) : the_post();
      the_content();
    endwhile;
  endif;
  ?>
</main>

{footer_html}

<?php wp_footer(); ?>
</body>
</html>"""
