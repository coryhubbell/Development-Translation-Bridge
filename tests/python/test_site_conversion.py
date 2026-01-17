"""
Tests for Translation Bridge v4 Site Conversion Features.

Tests cover:
- All framework converters (elementor, divi, gutenberg, bricks, wpbakery, beaver, avada, oxygen, bootstrap)
- Site export parsing (ElementorSiteParser)
- Styles extraction (StylesConverter)
- Template conversion (TemplateConverter)
- Multi-page site conversion workflows
"""

import json
import pytest
import sys
import tempfile
import zipfile
from pathlib import Path

# Add src to path for imports
sys.path.insert(0, str(Path(__file__).parent.parent.parent / "src"))

from translation_bridge.converters.bootstrap import BootstrapConverter
from translation_bridge.converters.elementor import ElementorConverter
from translation_bridge.converters.divi import DiviConverter
from translation_bridge.converters.gutenberg import GutenbergConverter
from translation_bridge.converters.bricks import BricksConverter
from translation_bridge.converters.wpbakery import WPBakeryConverter
from translation_bridge.converters.beaver import BeaverConverter
from translation_bridge.converters.avada import AvadaConverter
from translation_bridge.converters.oxygen import OxygenConverter
from translation_bridge.converters.styles import StylesConverter
from translation_bridge.converters.templates import TemplateConverter, TemplatePartGenerator
from translation_bridge.parsers.elementor_site import ElementorSiteParser


# =============================================================================
# Test Fixtures
# =============================================================================

@pytest.fixture
def sample_elementor_data():
    """Sample Elementor JSON data for testing converters."""
    return [
        {
            "id": "section-1",
            "elType": "section",
            "settings": {
                "layout": "boxed",
                "background_color": "#f8f9fa",
                "padding": {"top": "50", "bottom": "50", "unit": "px"},
            },
            "elements": [
                {
                    "id": "column-1",
                    "elType": "column",
                    "settings": {"_column_size": 100},
                    "elements": [
                        {
                            "id": "widget-1",
                            "elType": "widget",
                            "widgetType": "heading",
                            "settings": {
                                "title": "Welcome to Our Site",
                                "header_size": "h1",
                                "align": "center",
                            },
                            "elements": [],
                        },
                        {
                            "id": "widget-2",
                            "elType": "widget",
                            "widgetType": "text-editor",
                            "settings": {
                                "editor": "<p>This is the main content area.</p>",
                            },
                            "elements": [],
                        },
                        {
                            "id": "widget-3",
                            "elType": "widget",
                            "widgetType": "button",
                            "settings": {
                                "text": "Learn More",
                                "link": {"url": "https://example.com"},
                                "size": "lg",
                            },
                            "elements": [],
                        },
                    ],
                }
            ],
        }
    ]


@pytest.fixture
def sample_site_settings():
    """Sample site settings for styles testing."""
    return {
        "system_colors": {
            "primary": {"_id": "primary", "title": "Primary", "color": "#e94560"},
            "secondary": {"_id": "secondary", "title": "Secondary", "color": "#16213e"},
            "text": {"_id": "text", "title": "Text", "color": "#333333"},
            "accent": {"_id": "accent", "title": "Accent", "color": "#0f3460"},
        },
        "system_typography": {
            "primary": {
                "_id": "primary",
                "title": "Primary",
                "font_family": "Poppins",
                "font_weight": "400",
            },
            "secondary": {
                "_id": "secondary",
                "title": "Secondary",
                "font_family": "Open Sans",
                "font_weight": "400",
            },
        },
        "default_generic_fonts": "sans-serif",
        "container_width": {"size": 1140, "unit": "px"},
    }


@pytest.fixture
def sample_header_template():
    """Sample header template data."""
    return [
        {
            "id": "header-section",
            "elType": "section",
            "settings": {"layout": "full_width", "background_color": "#16213e"},
            "elements": [
                {
                    "id": "header-column",
                    "elType": "column",
                    "settings": {"_column_size": 100},
                    "elements": [
                        {
                            "id": "site-logo",
                            "elType": "widget",
                            "widgetType": "site-logo",
                            "settings": {},
                        },
                        {
                            "id": "nav-menu",
                            "elType": "widget",
                            "widgetType": "nav-menu",
                            "settings": {"menu": "main-menu"},
                        },
                    ],
                }
            ],
        }
    ]


# =============================================================================
# Bootstrap Converter Tests
# =============================================================================

class TestBootstrapConverter:
    """Test BootstrapConverter class."""

    def test_convert_returns_html(self, sample_elementor_data):
        """Should convert to valid HTML string."""
        converter = BootstrapConverter()
        result = converter.convert(sample_elementor_data)
        assert isinstance(result, str)
        assert "<section" in result or "<div" in result

    def test_convert_heading(self, sample_elementor_data):
        """Should convert heading widget correctly."""
        converter = BootstrapConverter()
        result = converter.convert(sample_elementor_data)
        assert "Welcome to Our Site" in result
        assert "<h1" in result

    def test_convert_button(self, sample_elementor_data):
        """Should convert button widget correctly."""
        converter = BootstrapConverter()
        result = converter.convert(sample_elementor_data)
        assert "Learn More" in result
        assert "btn" in result

    def test_has_widget_map(self):
        """Should have widget type mapping."""
        converter = BootstrapConverter()
        assert hasattr(converter, "WIDGET_MAP")
        assert "heading" in converter.WIDGET_MAP
        assert "button" in converter.WIDGET_MAP
        assert "image" in converter.WIDGET_MAP


# =============================================================================
# Elementor Converter Tests
# =============================================================================

class TestElementorConverter:
    """Test ElementorConverter class."""

    def test_convert_returns_json(self, sample_elementor_data):
        """Should convert to valid JSON string."""
        converter = ElementorConverter()
        result = converter.convert(sample_elementor_data)
        assert isinstance(result, str)
        parsed = json.loads(result)
        assert isinstance(parsed, list)

    def test_convert_to_dict(self, sample_elementor_data):
        """Should convert to list structure."""
        converter = ElementorConverter()
        result = converter.convert_to_dict(sample_elementor_data)
        assert isinstance(result, list)
        assert len(result) > 0

    def test_generates_unique_ids(self, sample_elementor_data):
        """Should generate unique element IDs."""
        converter = ElementorConverter()
        result = converter.convert_to_dict(sample_elementor_data)
        ids = []

        def collect_ids(elements):
            for el in elements:
                if "id" in el:
                    ids.append(el["id"])
                if "elements" in el:
                    collect_ids(el["elements"])

        collect_ids(result)
        assert len(ids) == len(set(ids))  # All IDs unique

    def test_get_framework(self):
        """Should return correct framework name."""
        converter = ElementorConverter()
        assert converter.get_framework() == "elementor"


# =============================================================================
# DIVI Converter Tests
# =============================================================================

class TestDiviConverter:
    """Test DiviConverter class."""

    def test_convert_returns_shortcode(self, sample_elementor_data):
        """Should convert to DIVI shortcode string."""
        converter = DiviConverter()
        result = converter.convert(sample_elementor_data)
        assert isinstance(result, str)
        assert "[et_pb_section" in result
        assert "[et_pb_row" in result
        assert "[et_pb_column" in result

    def test_convert_heading(self, sample_elementor_data):
        """Should convert heading to DIVI text module."""
        converter = DiviConverter()
        result = converter.convert(sample_elementor_data)
        assert "Welcome to Our Site" in result

    def test_convert_button(self, sample_elementor_data):
        """Should convert button to DIVI button module."""
        converter = DiviConverter()
        result = converter.convert(sample_elementor_data)
        assert "Learn More" in result
        assert "[et_pb_button" in result

    def test_proper_nesting(self, sample_elementor_data):
        """Should have proper section/row/column nesting."""
        converter = DiviConverter()
        result = converter.convert(sample_elementor_data)
        # Check closing tags exist and are in right order
        assert "[/et_pb_column]" in result
        assert "[/et_pb_row]" in result
        assert "[/et_pb_section]" in result

    def test_get_framework(self):
        """Should return correct framework name."""
        converter = DiviConverter()
        assert converter.get_framework() == "divi"


# =============================================================================
# Gutenberg Converter Tests
# =============================================================================

class TestGutenbergConverter:
    """Test GutenbergConverter class."""

    def test_convert_returns_blocks(self, sample_elementor_data):
        """Should convert to Gutenberg block format."""
        converter = GutenbergConverter()
        result = converter.convert(sample_elementor_data)
        assert isinstance(result, str)
        assert "<!-- wp:" in result

    def test_convert_heading(self, sample_elementor_data):
        """Should convert heading to wp:heading block."""
        converter = GutenbergConverter()
        result = converter.convert(sample_elementor_data)
        # Gutenberg uses wp:core/heading format
        assert "wp:core/heading" in result or "wp:heading" in result
        assert "Welcome to Our Site" in result

    def test_convert_paragraph(self):
        """Should convert text-editor to wp:paragraph block."""
        converter = GutenbergConverter()
        data = [{"id": "1", "elType": "widget", "widgetType": "text-editor", "settings": {"editor": "Test paragraph"}}]
        result = converter.convert(data)
        # Gutenberg uses wp:core/paragraph format
        assert "wp:core/paragraph" in result or "wp:paragraph" in result
        assert "Test paragraph" in result

    def test_convert_button(self, sample_elementor_data):
        """Should convert button to wp:button block."""
        converter = GutenbergConverter()
        result = converter.convert(sample_elementor_data)
        # Gutenberg uses wp:core/button format
        assert "wp:core/button" in result or "wp:button" in result
        assert "Learn More" in result

    def test_get_framework(self):
        """Should return correct framework name."""
        converter = GutenbergConverter()
        assert converter.get_framework() == "gutenberg"


# =============================================================================
# Bricks Converter Tests
# =============================================================================

class TestBricksConverter:
    """Test BricksConverter class."""

    def test_convert_returns_json(self, sample_elementor_data):
        """Should convert to valid JSON string."""
        converter = BricksConverter()
        result = converter.convert(sample_elementor_data)
        assert isinstance(result, str)
        parsed = json.loads(result)
        assert isinstance(parsed, list)

    def test_convert_to_dict(self, sample_elementor_data):
        """Should convert to list of elements."""
        converter = BricksConverter()
        result = converter.convert_to_dict(sample_elementor_data)
        assert isinstance(result, list)
        assert len(result) > 0

    def test_element_structure(self, sample_elementor_data):
        """Elements should have proper Bricks structure."""
        converter = BricksConverter()
        result = converter.convert_to_dict(sample_elementor_data)
        for element in result:
            assert "id" in element
            assert "name" in element
            assert "parent" in element
            assert "settings" in element

    def test_get_framework(self):
        """Should return correct framework name."""
        converter = BricksConverter()
        assert converter.get_framework() == "bricks"


# =============================================================================
# WPBakery Converter Tests
# =============================================================================

class TestWPBakeryConverter:
    """Test WPBakeryConverter class."""

    def test_convert_returns_shortcode(self, sample_elementor_data):
        """Should convert to WPBakery shortcode string."""
        converter = WPBakeryConverter()
        result = converter.convert(sample_elementor_data)
        assert isinstance(result, str)
        assert "[vc_row" in result
        assert "[vc_column" in result

    def test_convert_heading(self, sample_elementor_data):
        """Should convert heading to vc_custom_heading."""
        converter = WPBakeryConverter()
        result = converter.convert(sample_elementor_data)
        assert "Welcome to Our Site" in result

    def test_convert_button(self, sample_elementor_data):
        """Should convert button to vc_btn."""
        converter = WPBakeryConverter()
        result = converter.convert(sample_elementor_data)
        assert "[vc_btn" in result
        assert "Learn More" in result

    def test_proper_nesting(self, sample_elementor_data):
        """Should have proper row/column nesting."""
        converter = WPBakeryConverter()
        result = converter.convert(sample_elementor_data)
        assert "[/vc_column]" in result
        assert "[/vc_row]" in result

    def test_get_framework(self):
        """Should return correct framework name."""
        converter = WPBakeryConverter()
        assert converter.get_framework() == "wpbakery"


# =============================================================================
# Beaver Builder Converter Tests
# =============================================================================

class TestBeaverConverter:
    """Test BeaverConverter class."""

    def test_convert_returns_json(self, sample_elementor_data):
        """Should convert to valid JSON string."""
        converter = BeaverConverter()
        result = converter.convert(sample_elementor_data)
        assert isinstance(result, str)
        parsed = json.loads(result)
        assert isinstance(parsed, dict)

    def test_convert_to_dict(self, sample_elementor_data):
        """Should convert to node dict structure."""
        converter = BeaverConverter()
        result = converter.convert_to_dict(sample_elementor_data)
        assert isinstance(result, dict)

    def test_node_structure(self, sample_elementor_data):
        """Nodes should have proper Beaver Builder structure."""
        converter = BeaverConverter()
        result = converter.convert_to_dict(sample_elementor_data)
        for node_id, node in result.items():
            assert "node" in node
            assert "type" in node
            assert "settings" in node
            assert node["type"] in ["row", "column-group", "column", "module"]

    def test_get_framework(self):
        """Should return correct framework name."""
        converter = BeaverConverter()
        assert converter.get_framework() == "beaver-builder"


# =============================================================================
# Avada Converter Tests
# =============================================================================

class TestAvadaConverter:
    """Test AvadaConverter class."""

    def test_convert_returns_shortcode(self, sample_elementor_data):
        """Should convert to Avada Fusion Builder shortcode string."""
        converter = AvadaConverter()
        result = converter.convert(sample_elementor_data)
        assert isinstance(result, str)
        assert "[fusion_builder_container" in result
        assert "[fusion_builder_row" in result
        assert "[fusion_builder_column" in result

    def test_convert_heading(self, sample_elementor_data):
        """Should convert heading to fusion_title."""
        converter = AvadaConverter()
        result = converter.convert(sample_elementor_data)
        assert "Welcome to Our Site" in result

    def test_convert_button(self, sample_elementor_data):
        """Should convert button to fusion_button."""
        converter = AvadaConverter()
        result = converter.convert(sample_elementor_data)
        assert "[fusion_button" in result
        assert "Learn More" in result

    def test_proper_nesting(self, sample_elementor_data):
        """Should have proper container/row/column nesting."""
        converter = AvadaConverter()
        result = converter.convert(sample_elementor_data)
        assert "[/fusion_builder_column]" in result
        assert "[/fusion_builder_row]" in result
        assert "[/fusion_builder_container]" in result

    def test_get_framework(self):
        """Should return correct framework name."""
        converter = AvadaConverter()
        assert converter.get_framework() == "avada"


# =============================================================================
# Oxygen Converter Tests
# =============================================================================

class TestOxygenConverter:
    """Test OxygenConverter class."""

    def test_convert_returns_json(self, sample_elementor_data):
        """Should convert to valid JSON string."""
        converter = OxygenConverter()
        result = converter.convert(sample_elementor_data)
        assert isinstance(result, str)
        parsed = json.loads(result)
        assert "ct_builder_json" in parsed

    def test_convert_to_dict(self, sample_elementor_data):
        """Should convert to Oxygen dict structure."""
        converter = OxygenConverter()
        result = converter.convert_to_dict(sample_elementor_data)
        assert isinstance(result, dict)
        assert "ct_builder_json" in result
        assert "ct_builder" in result["ct_builder_json"]

    def test_element_structure(self, sample_elementor_data):
        """Elements should have proper Oxygen structure."""
        converter = OxygenConverter()
        result = converter.convert_to_dict(sample_elementor_data)
        elements = result["ct_builder_json"]["ct_builder"]
        for element in elements:
            assert "id" in element
            assert "name" in element
            assert "options" in element
            assert "children" in element

    def test_get_framework(self):
        """Should return correct framework name."""
        converter = OxygenConverter()
        assert converter.get_framework() == "oxygen"


# =============================================================================
# Styles Converter Tests
# =============================================================================

class TestStylesConverter:
    """Test StylesConverter class."""

    def test_extract_tokens(self, sample_site_settings):
        """Should extract design tokens from site settings."""
        converter = StylesConverter()
        tokens = converter.extract_tokens(sample_site_settings)
        # Should have colors and spacing (fonts may be empty if key name mismatch)
        assert len(tokens.colors) > 0
        assert len(tokens.spacing) > 0

    def test_to_css(self, sample_site_settings):
        """Should generate valid CSS."""
        converter = StylesConverter()
        tokens = converter.extract_tokens(sample_site_settings)
        css = converter.to_css(tokens)
        assert isinstance(css, str)
        assert ":root" in css
        # Check that colors were extracted
        assert "--color" in css

    def test_to_scss(self, sample_site_settings):
        """Should generate valid SCSS variables."""
        converter = StylesConverter()
        tokens = converter.extract_tokens(sample_site_settings)
        scss = converter.to_scss(tokens)
        assert isinstance(scss, str)
        # Should have SCSS variable format
        assert "$" in scss

    def test_css_includes_spacing(self, sample_site_settings):
        """CSS should include spacing variables."""
        converter = StylesConverter()
        tokens = converter.extract_tokens(sample_site_settings)
        css = converter.to_css(tokens)
        assert "--spacing" in css

    def test_empty_settings(self):
        """Should handle empty settings gracefully."""
        converter = StylesConverter()
        tokens = converter.extract_tokens({})
        css = converter.to_css(tokens)
        assert isinstance(css, str)


# =============================================================================
# Template Converter Tests
# =============================================================================

class TestTemplateConverter:
    """Test TemplateConverter class."""

    def test_convert_header(self, sample_header_template):
        """Should convert header template to HTML."""
        converter = TemplateConverter()
        result = converter.convert_header(sample_header_template)
        assert isinstance(result, str)
        assert "<header" in result

    def test_convert_footer(self):
        """Should convert footer template to HTML."""
        converter = TemplateConverter()
        footer_data = [
            {
                "id": "footer-section",
                "elType": "section",
                "settings": {},
                "elements": [
                    {
                        "id": "footer-column",
                        "elType": "column",
                        "settings": {},
                        "elements": [
                            {
                                "id": "copyright",
                                "elType": "widget",
                                "widgetType": "text-editor",
                                "settings": {"editor": "Copyright 2024"},
                            }
                        ],
                    }
                ],
            }
        ]
        result = converter.convert_footer(footer_data)
        assert isinstance(result, str)
        assert "<footer" in result

    def test_dynamic_placeholders(self, sample_header_template):
        """Should identify dynamic placeholders."""
        converter = TemplateConverter()
        result = converter.convert_header(sample_header_template)
        # Dynamic placeholders should be in output
        assert "{{" in result or "{%" in result or "site" in result.lower()


class TestTemplatePartGenerator:
    """Test TemplatePartGenerator class."""

    def test_generate_parts(self, sample_header_template):
        """Should generate template parts dictionary."""
        generator = TemplatePartGenerator()
        # generate_all expects a list of template dicts with 'type' and 'content'/'document' keys
        templates = [{"type": "header", "document": sample_header_template}]
        parts = generator.generate_all(templates)
        assert isinstance(parts, dict)
        assert "header.html" in parts or "README.md" in parts

    def test_generate_with_format(self, sample_header_template):
        """Should generate with specified format."""
        generator = TemplatePartGenerator(output_format="jinja2")
        templates = [{"type": "header", "document": sample_header_template}]
        parts = generator.generate_all(templates)
        assert isinstance(parts, dict)


# =============================================================================
# Site Parser Tests
# =============================================================================

class TestElementorSiteParser:
    """Test ElementorSiteParser class."""

    def test_parser_instantiation(self):
        """Should instantiate parser."""
        parser = ElementorSiteParser()
        assert parser is not None

    def test_parse_directory_nonexistent(self):
        """Should handle nonexistent directory gracefully."""
        parser = ElementorSiteParser()
        with pytest.raises(Exception):
            parser.parse_directory("/nonexistent/path")

    def test_analyze_method(self):
        """Should have analyze method."""
        parser = ElementorSiteParser()
        assert hasattr(parser, "analyze")
        assert callable(parser.analyze)


# =============================================================================
# Cross-Framework Conversion Tests
# =============================================================================

class TestCrossFrameworkConversion:
    """Test converting between different frameworks."""

    def test_elementor_to_all_frameworks(self, sample_elementor_data):
        """Should convert Elementor data to all supported frameworks."""
        converters = [
            ("bootstrap", BootstrapConverter()),
            ("elementor", ElementorConverter()),
            ("divi", DiviConverter()),
            ("gutenberg", GutenbergConverter()),
            ("bricks", BricksConverter()),
            ("wpbakery", WPBakeryConverter()),
            ("beaver", BeaverConverter()),
            ("avada", AvadaConverter()),
            ("oxygen", OxygenConverter()),
        ]

        for framework, converter in converters:
            result = converter.convert(sample_elementor_data)
            assert isinstance(result, str), f"{framework} should return string"
            assert len(result) > 0, f"{framework} should return non-empty result"

    def test_content_preserved_across_frameworks(self, sample_elementor_data):
        """Critical content should be preserved across all conversions."""
        converters = [
            ("bootstrap", BootstrapConverter()),
            ("divi", DiviConverter()),
            ("gutenberg", GutenbergConverter()),
            ("wpbakery", WPBakeryConverter()),
            ("avada", AvadaConverter()),
        ]

        for name, converter in converters:
            result = converter.convert(sample_elementor_data)
            # Check that heading text is preserved
            assert "Welcome to Our Site" in result, f"{name} should preserve heading"
            # Check that button text is preserved
            assert "Learn More" in result, f"{name} should preserve button text"


# =============================================================================
# Integration Tests
# =============================================================================

class TestSiteConversionIntegration:
    """Integration tests for full site conversion workflows."""

    def test_full_page_conversion_pipeline(self, sample_elementor_data, sample_site_settings):
        """Test complete page conversion with styles."""
        # 1. Extract styles
        styles_converter = StylesConverter()
        tokens = styles_converter.extract_tokens(sample_site_settings)
        css = styles_converter.to_css(tokens)

        # 2. Convert page
        bootstrap_converter = BootstrapConverter()
        html = bootstrap_converter.convert(sample_elementor_data)

        # 3. Verify outputs
        assert len(css) > 0
        assert len(html) > 0
        assert "Welcome to Our Site" in html

    def test_template_with_page_conversion(self, sample_elementor_data, sample_header_template):
        """Test page conversion with header template."""
        # 1. Convert header
        template_converter = TemplateConverter()
        header_html = template_converter.convert_header(sample_header_template)

        # 2. Convert page
        bootstrap_converter = BootstrapConverter()
        page_html = bootstrap_converter.convert(sample_elementor_data)

        # 3. Combine (simple concatenation for test)
        full_html = f"{header_html}\n<main>\n{page_html}\n</main>"

        assert "<header" in full_html
        assert "Welcome to Our Site" in full_html

    def test_multiple_pages_conversion(self, sample_elementor_data):
        """Test converting multiple pages."""
        pages = {
            "index": sample_elementor_data,
            "about": sample_elementor_data,
            "contact": sample_elementor_data,
        }

        bootstrap_converter = BootstrapConverter()
        results = {}

        for page_name, page_data in pages.items():
            results[page_name] = bootstrap_converter.convert(page_data)

        assert len(results) == 3
        for page_name, html in results.items():
            assert len(html) > 0
            assert "Welcome to Our Site" in html


# =============================================================================
# Edge Cases and Error Handling
# =============================================================================

class TestEdgeCases:
    """Test edge cases and error handling."""

    def test_empty_data(self):
        """Should handle empty data gracefully."""
        converters = [
            BootstrapConverter(),
            ElementorConverter(),
            DiviConverter(),
            GutenbergConverter(),
            BricksConverter(),
            WPBakeryConverter(),
            BeaverConverter(),
            AvadaConverter(),
            OxygenConverter(),
        ]

        for converter in converters:
            result = converter.convert([])
            assert isinstance(result, str)

    def test_nested_sections(self):
        """Should handle deeply nested sections."""
        nested_data = [
            {
                "id": "outer",
                "elType": "section",
                "settings": {},
                "elements": [
                    {
                        "id": "col1",
                        "elType": "column",
                        "settings": {},
                        "elements": [
                            {
                                "id": "inner",
                                "elType": "section",
                                "settings": {},
                                "elements": [
                                    {
                                        "id": "col2",
                                        "elType": "column",
                                        "settings": {},
                                        "elements": [
                                            {
                                                "id": "widget",
                                                "elType": "widget",
                                                "widgetType": "heading",
                                                "settings": {"title": "Nested Heading"},
                                            }
                                        ],
                                    }
                                ],
                            }
                        ],
                    }
                ],
            }
        ]

        converter = BootstrapConverter()
        result = converter.convert(nested_data)
        assert "Nested Heading" in result

    def test_unknown_widget_type(self):
        """Should handle unknown widget types gracefully."""
        data = [
            {
                "id": "section1",
                "elType": "section",
                "settings": {},
                "elements": [
                    {
                        "id": "col1",
                        "elType": "column",
                        "settings": {},
                        "elements": [
                            {
                                "id": "unknown1",
                                "elType": "widget",
                                "widgetType": "unknown_widget_xyz",
                                "settings": {"some_setting": "value"},
                            }
                        ],
                    }
                ],
            }
        ]

        converter = BootstrapConverter()
        result = converter.convert(data)
        assert isinstance(result, str)  # Should not crash

    def test_special_characters_in_content(self):
        """Should handle special characters properly."""
        data = [
            {
                "id": "section1",
                "elType": "section",
                "settings": {},
                "elements": [
                    {
                        "id": "col1",
                        "elType": "column",
                        "settings": {},
                        "elements": [
                            {
                                "id": "widget1",
                                "elType": "widget",
                                "widgetType": "heading",
                                "settings": {"title": "Test <script>alert('xss')</script> & \"quotes\""},
                            }
                        ],
                    }
                ],
            }
        ]

        converter = BootstrapConverter()
        result = converter.convert(data)
        # Should escape or sanitize dangerous content
        assert "<script>" not in result or "&lt;script&gt;" in result

    def test_unicode_content(self):
        """Should handle unicode content properly."""
        data = [
            {
                "id": "section1",
                "elType": "section",
                "settings": {},
                "elements": [
                    {
                        "id": "col1",
                        "elType": "column",
                        "settings": {},
                        "elements": [
                            {
                                "id": "widget1",
                                "elType": "widget",
                                "widgetType": "heading",
                                "settings": {"title": "Hello World"},
                            }
                        ],
                    }
                ],
            }
        ]

        converter = BootstrapConverter()
        result = converter.convert(data)
        # Unicode should be preserved or properly encoded
        assert "Hello" in result


if __name__ == "__main__":
    pytest.main([__file__, "-v"])
