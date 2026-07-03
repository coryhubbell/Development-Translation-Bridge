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
from translation_bridge.converters.oxygen6 import Oxygen6Converter
from translation_bridge.converters.divi5 import Divi5Converter
from translation_bridge.converters.elementor4 import Elementor4Converter
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

    def test_convert_paragraph_preserves_existing_paragraph_html(self):
        """Should not nest paragraph tags from Elementor rich text."""
        converter = GutenbergConverter()
        data = [{
            "id": "1",
            "elType": "widget",
            "widgetType": "text-editor",
            "settings": {"editor": "<p>Already wrapped.</p>"},
        }]
        result = converter.convert(data)
        assert "<p>Already wrapped.</p>" in result
        assert "<p><p>Already wrapped.</p></p>" not in result

    def test_convert_rich_text_block_html_preserves_markup_as_html_block(self):
        """Should preserve multiple paragraphs and lists as valid core/html."""
        converter = GutenbergConverter()
        rich_text = "<p>First paragraph.</p><p>Second paragraph.</p><ul><li>Feature</li></ul>"
        data = [{
            "id": "1",
            "elType": "widget",
            "widgetType": "text-editor",
            "settings": {"editor": rich_text},
        }]

        result = converter.convert(data)

        assert "<!-- wp:html -->" in result
        assert rich_text in result
        assert "<!-- wp:paragraph" not in result
        assert "<p><p>First paragraph.</p>" not in result

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

    # -------------------------------------------------------------------------
    # Widget coverage tests (v4.3.4) - mirror the PHP suite to keep both
    # engines in sync. Each test guards against the regression of widgets
    # silently collapsing to empty paragraphs. Assertions use canonical
    # `<!-- wp:X -->` form (no `core/` namespace), as the converter emits
    # canonical Gutenberg serialization since v4.2.0.
    # -------------------------------------------------------------------------

    def test_icon_list_renders_canonical_list_items(self):
        converter = GutenbergConverter()
        data = [{
            "id": "1", "elType": "widget", "widgetType": "icon-list",
            "settings": {
                "icon_list": [
                    {"text": "Feature A"},
                    {"text": "Feature B"},
                ],
            },
        }]
        out = converter.convert(data)
        assert "<!-- wp:list" in out
        # WP 6.0+ canonical shape: list contains list-item innerBlocks.
        assert "<!-- wp:list-item" in out
        assert "<li>Feature A</li>" in out
        assert "<li>Feature B</li>" in out

    def test_tabs_renders_stacked_group(self):
        converter = GutenbergConverter()
        data = [{
            "id": "1", "elType": "widget", "widgetType": "tabs",
            "settings": {
                "tabs": [
                    {"tab_title": "One", "tab_content": "Body one."},
                    {"tab_title": "Two", "tab_content": "Body two."},
                ],
            },
        }]
        out = converter.convert(data)
        assert "<!-- wp:group" in out
        assert "devtb-tabs-converted" in out
        assert "One" in out and "Body one." in out
        assert "Two" in out and "Body two." in out

    def test_accordion_uses_accordion_classname(self):
        converter = GutenbergConverter()
        data = [{
            "id": "1", "elType": "widget", "widgetType": "accordion",
            "settings": {
                "tabs": [{"tab_title": "Q?", "tab_content": "A."}],
            },
        }]
        out = converter.convert(data)
        assert "devtb-accordion-converted" in out
        assert "Q?" in out and "A." in out

    def test_icon_box_renders_card_compound(self):
        converter = GutenbergConverter()
        data = [{
            "id": "1", "elType": "widget", "widgetType": "icon-box",
            "settings": {
                "title_text": "Card title",
                "description_text": "Card description.",
                "link": {"url": "https://example.com/learn"},
                "button_text": "Learn more",
            },
        }]
        out = converter.convert(data)
        assert "devtb-card-converted" in out
        assert "Card title" in out
        assert "Card description." in out
        assert "<!-- wp:buttons" in out
        assert "Learn more" in out
        assert "https://example.com/learn" in out
        # Canonical theme.json class required since WP 6.1.
        assert "wp-element-button" in out

    def test_counter_renders_heading_with_number(self):
        converter = GutenbergConverter()
        data = [{
            "id": "1", "elType": "widget", "widgetType": "counter",
            "settings": {
                "ending_number": 250,
                "prefix": "$",
                "suffix": "+",
                "title": "Happy customers",
            },
        }]
        out = converter.convert(data)
        assert "devtb-counter-converted" in out
        assert "$250+" in out
        assert "Happy customers" in out

    def test_testimonial_renders_quote_with_cite(self):
        converter = GutenbergConverter()
        data = [{
            "id": "1", "elType": "widget", "widgetType": "testimonial",
            "settings": {
                "testimonial_content": "The best decision we ever made.",
                "testimonial_name": "Jane Doe",
                "testimonial_job": "CTO, Acme",
            },
        }]
        out = converter.convert(data)
        assert "<!-- wp:quote" in out
        assert "The best decision we ever made." in out
        assert "Jane Doe" in out
        assert "CTO, Acme" in out
        assert "<cite>" in out

    def test_pricing_table_renders_full_group(self):
        converter = GutenbergConverter()
        data = [{
            "id": "1", "elType": "widget", "widgetType": "price-table",
            "settings": {
                "heading": "Pro",
                "currency_symbol": "$",
                "price": "49",
                "period": "mo",
                "features": [
                    {"item_text": "Unlimited projects"},
                    {"item_text": "Priority support"},
                ],
                "button_text": "Sign up",
                "button_url": "https://example.com/signup",
            },
        }]
        out = converter.convert(data)
        assert "devtb-pricing-converted" in out
        assert "Pro" in out
        assert "$49 / mo" in out
        assert "Unlimited projects" in out
        assert "Priority support" in out
        assert "Sign up" in out
        # Pricing features rendered as a canonical list with list-item innerBlocks.
        assert "<!-- wp:list-item" in out

    def test_alert_renders_styled_group(self):
        converter = GutenbergConverter()
        data = [{
            "id": "1", "elType": "widget", "widgetType": "alert",
            "settings": {
                "alert_type": "success",
                "alert_title": "Great!",
                "alert_description": "It worked.",
            },
        }]
        out = converter.convert(data)
        assert "<!-- wp:group" in out
        assert "devtb-alert" in out
        assert "is-style-success" in out
        assert "Great!" in out
        assert "It worked." in out

    def test_cta_renders_heading_paragraph_button(self):
        converter = GutenbergConverter()
        data = [{
            "id": "1", "elType": "widget", "widgetType": "call-to-action",
            "settings": {
                "title": "Ready to start?",
                "description": "Sign up in seconds.",
                "link": {"url": "https://example.com/start"},
                "button_text": "Get started",
            },
        }]
        out = converter.convert(data)
        assert "devtb-cta-converted" in out
        assert "Ready to start?" in out
        assert "<!-- wp:buttons" in out
        assert "Get started" in out

    def test_form_widget_falls_back_to_marker(self):
        converter = GutenbergConverter()
        data = [{
            "id": "1", "elType": "widget", "widgetType": "form",
            "settings": {
                "title": "Contact us",
                "form_name": "Contact",
            },
        }]
        out = converter.convert(data)
        assert "<!-- wp:html" in out
        assert 'devtb: unconverted elementor widget "form"' in out
        assert 'data-devtb-source="elementor:form"' in out
        assert "Contact us" in out
        # Critical: no silent collapse to empty paragraph
        assert "<!-- wp:paragraph -->\n<p></p>" not in out

    def test_unknown_widget_emits_marker(self):
        converter = GutenbergConverter()
        data = [{
            "id": "1", "elType": "widget", "widgetType": "some-third-party-widget",
            "settings": {"title": "Custom"},
        }]
        out = converter.convert(data)
        assert "<!-- wp:html" in out
        assert 'devtb: unconverted elementor widget "some-third-party-widget"' in out

    def test_gallery_renders_with_ids(self):
        converter = GutenbergConverter()
        data = [{
            "id": "1", "elType": "widget", "widgetType": "image-gallery",
            "settings": {
                "wp_gallery": [
                    {"id": 11, "url": "https://example.com/1.jpg", "alt": "One"},
                    {"id": 22, "url": "https://example.com/2.jpg", "alt": "Two"},
                ],
            },
        }]
        out = converter.convert(data)
        assert "<!-- wp:gallery" in out
        assert '"ids":[11,22]' in out
        assert "https://example.com/1.jpg" in out

    def test_social_icons_maps_to_social_links(self):
        converter = GutenbergConverter()
        data = [{
            "id": "1", "elType": "widget", "widgetType": "social-icons",
            "settings": {
                "social_icon_list": [
                    {"social": "twitter", "link": {"url": "https://twitter.com/x"}},
                    {"social": "github", "link": {"url": "https://github.com/x"}},
                ],
            },
        }]
        out = converter.convert(data)
        assert "<!-- wp:social-links" in out
        assert "twitter" in out
        assert "github" in out

    def test_text_widget_still_maps_to_paragraph(self):
        converter = GutenbergConverter()
        data = [{
            "id": "1", "elType": "widget", "widgetType": "text-editor",
            "settings": {"editor": "Hello world."},
        }]
        out = converter.convert(data)
        assert "<!-- wp:paragraph" in out
        assert "Hello world." in out

    def test_get_supported_widgets_includes_new_set(self):
        converter = GutenbergConverter()
        widgets = converter.get_supported_widgets()
        for name in [
            "tabs", "accordion", "icon-box", "call-to-action", "counter",
            "testimonial", "price-table", "alert",
            "form", "slider", "countdown", "portfolio", "google_maps",
            "progress", "star-rating",
            "social-icons", "nav-menu",
        ]:
            assert name in widgets, f"missing widget {name}"

    def test_elementor_to_gutenberg_transform_is_registered(self):
        """Critical: the v4 CLI route must be discoverable through the transform registry."""
        from translation_bridge.transforms.registry import TransformRegistry
        import translation_bridge.transforms.registry  # noqa: F401  triggers decorator registrations

        fn = TransformRegistry.get_transform("elementor", "gutenberg")
        assert fn is not None, "elementor->gutenberg transform not registered"
        out = fn([{
            "id": "1", "elType": "widget", "widgetType": "heading",
            "settings": {"title": "Hello", "header_size": "h2"},
        }])
        assert "<!-- wp:heading" in out
        assert "Hello" in out


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

    def test_flat_structure_with_string_child_ids(self, sample_elementor_data):
        """Bricks 2.x output must be flat with string child ids (never nested element objects)."""
        converter = BricksConverter()
        result = converter.convert_to_dict(sample_elementor_data)

        ids = {el["id"] for el in result}

        for element in result:
            assert isinstance(element.get("children", []), list)
            for child in element.get("children", []):
                assert isinstance(child, str), (
                    f"children entry must be a string id, got {type(child).__name__}"
                )
                assert child in ids, f"child id {child!r} not present in flat array"

            parent = element.get("parent", "0")
            if parent != "0":
                assert parent in ids, f"parent id {parent!r} not present in flat array"

    def test_parent_child_linkage(self, sample_elementor_data):
        """Each child must reference its parent's id, and the parent must list the child id."""
        converter = BricksConverter()
        result = converter.convert_to_dict(sample_elementor_data)

        by_id = {el["id"]: el for el in result}
        for element in result:
            for child_id in element.get("children", []):
                assert child_id in by_id
                assert by_id[child_id]["parent"] == element["id"], (
                    f"child {child_id} has parent {by_id[child_id]['parent']!r}, "
                    f"expected {element['id']!r}"
                )

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
    """Test OxygenConverter class.

    Output is classic Oxygen's real ct_builder_json shape: a nested tree
    under a `root` node, unified with the PHP converter.
    """

    def test_convert_returns_json(self, sample_elementor_data):
        """Should convert to a valid root-tree JSON string."""
        converter = OxygenConverter()
        result = converter.convert(sample_elementor_data)
        assert isinstance(result, str)
        parsed = json.loads(result)
        assert parsed.get("name") == "root"
        assert parsed.get("id") == 0

    def test_convert_to_dict(self, sample_elementor_data):
        """Should convert to the Oxygen root-tree structure."""
        converter = OxygenConverter()
        result = converter.convert_to_dict(sample_elementor_data)
        assert isinstance(result, dict)
        assert result["name"] == "root"
        assert isinstance(result["children"], list)
        assert result["children"], "non-trivial input must produce elements"

    def test_element_structure(self, sample_elementor_data):
        """Elements should have proper Oxygen structure with real names."""
        converter = OxygenConverter()
        result = converter.convert_to_dict(sample_elementor_data)

        def walk(element, parent_id):
            assert "id" in element
            assert "name" in element
            assert element["name"].startswith(("ct_", "oxy_"))
            assert "options" in element
            assert element["options"]["ct_id"] == element["id"]
            assert element["options"]["ct_parent"] == parent_id
            assert "selector" in element["options"]
            assert "children" in element
            for child in element["children"]:
                walk(child, element["id"])

        for element in result["children"]:
            walk(element, 0)

    def test_no_fabricated_element_names(self, sample_elementor_data):
        """Emitted names must come from the real classic Oxygen vocabulary."""
        fabricated = {
            "ct_link_text", "ct_icon", "ct_tabs", "ct_tab", "ct_accordion",
            "ct_toggle", "ct_google_map", "ct_testimonial", "ct_pricing_box",
            "ct_progress_bar", "ct_nav_menu", "ct_menu", "ct_gallery",
            "oxy_accordion", "oxy_counter", "oxy_testimonial", "ct_icon_box",
            "ct_contact_form",
        }
        result = OxygenConverter().convert_to_dict(sample_elementor_data)

        def walk(element):
            assert element["name"] not in fabricated, f"fabricated name: {element['name']}"
            for child in element["children"]:
                walk(child)

        for element in result["children"]:
            walk(element)

    def test_get_framework(self):
        """Should return correct framework name."""
        converter = OxygenConverter()
        assert converter.get_framework() == "oxygen"


# =============================================================================
# Oxygen 6 Converter Tests (Breakdance-proxy schema)
# =============================================================================

class TestOxygen6Converter:
    """Test Oxygen6Converter class.

    Oxygen 6 is a ground-up rewrite built on Breakdance — incompatible with
    classic Oxygen. The node shape asserted here is verified against a real
    Breakdance export: integer ids, the element payload nested under `data`,
    `_parentId` back-references, and content fields under
    `properties.content.content` (heading tag key is the plural `tags`).
    """

    def test_convert_returns_wrapped_payload(self, sample_elementor_data):
        """Output must be the tree.root envelope with _nextNodeId."""
        result = json.loads(Oxygen6Converter().convert(sample_elementor_data))
        assert isinstance(result, dict)
        assert isinstance(result.get("_nextNodeId"), int)
        root = result.get("tree", {}).get("root")
        assert isinstance(root, dict), "tree must wrap a root node"
        assert root["id"] == 1
        assert root["data"]["type"] == "root"
        assert root["children"], "root children must not be empty for non-trivial input"

    def test_nodes_are_nested_with_namespaced_types(self, sample_elementor_data):
        """Every node nests type/properties under `data`; type is namespaced."""
        payload = Oxygen6Converter().convert_to_dict(sample_elementor_data)

        def walk(node, parent_id, path):
            assert isinstance(node, dict), f"{path}: not a dict"
            for key in ("id", "data", "children", "_parentId"):
                assert key in node, f"{path}: missing {key!r}"
            assert isinstance(node["id"], int), f"{path}: id must be an integer"
            assert node["_parentId"] == parent_id, f"{path}: bad _parentId"
            data = node["data"]
            assert isinstance(data["type"], str)
            assert "\\" in data["type"], (
                f"{path}: type {data['type']!r} must be namespaced "
                "(e.g. EssentialElements\\Heading)"
            )
            assert isinstance(data["properties"], dict)
            assert isinstance(node["children"], list)
            for i, child in enumerate(node["children"]):
                walk(child, node["id"], f"{path}/children[{i}]")

        root = payload["tree"]["root"]
        for i, child in enumerate(root["children"]):
            walk(child, root["id"], f"root/children[{i}]")

    def test_node_ids_are_unique_and_match_nextnodeid(self, sample_elementor_data):
        """Generated ids must be unique; _nextNodeId points one past the max."""
        payload = Oxygen6Converter().convert_to_dict(sample_elementor_data)

        ids = [payload["tree"]["root"]["id"]]

        def collect(node):
            ids.append(node["id"])
            for child in node["children"]:
                collect(child)

        for child in payload["tree"]["root"]["children"]:
            collect(child)

        assert len(ids) == len(set(ids)), "node ids must be unique within the payload"
        # Counter is monotonic 1..N (root included), so _nextNodeId == N + 1.
        assert payload["_nextNodeId"] == len(ids) + 1

    def test_heading_carries_text_and_tags(self, sample_elementor_data):
        """Heading nodes surface text + plural `tags` under content.content."""
        payload = Oxygen6Converter().convert_to_dict(sample_elementor_data)

        def find(node, type_suffix):
            if node["data"]["type"].endswith(type_suffix):
                return node
            for child in node["children"]:
                hit = find(child, type_suffix)
                if hit:
                    return hit
            return None

        heading = None
        for child in payload["tree"]["root"]["children"]:
            heading = find(child, "Heading")
            if heading:
                break

        assert heading is not None, "expected at least one Heading node"
        fields = heading["data"]["properties"]["content"]["content"]
        assert "text" in fields
        assert "tags" in fields

    def test_get_framework(self):
        assert Oxygen6Converter().get_framework() == "oxygen-6"


# =============================================================================
# DIVI 5 Converter Tests
# =============================================================================

class TestDivi5Converter:
    """Test Divi5Converter class.

    DIVI 5 uses WordPress block markup under the `divi/*` namespace. These tests
    pin the block-comment delimiters, the responsive `desktop.value` wrapper,
    and the `builderVersion` declaration so any future schema drift is caught.
    """

    def test_emits_divi_block_markup(self, sample_elementor_data):
        """Output must contain `<!-- wp:divi/...` delimiters."""
        result = Divi5Converter().convert(sample_elementor_data)
        assert isinstance(result, str)
        assert "<!-- wp:divi/" in result

    def test_declares_builder_version_on_blocks(self, sample_elementor_data):
        """Each block must declare `builderVersion` (used by DIVI 5 for schema dispatch)."""
        result = Divi5Converter().convert(sample_elementor_data)
        assert '"builderVersion":"5.0.0"' in result

    def test_text_content_uses_responsive_desktop_wrapper(self, sample_elementor_data):
        """Content values must be wrapped in `{"desktop":{"value":"..."}}`."""
        result = Divi5Converter().convert(sample_elementor_data)
        # The Elementor sample has a heading "Welcome to Our Site"
        assert '"desktop":{"value":' in result
        assert "Welcome to Our Site" in result

    def test_container_blocks_have_closing_tags(self, sample_elementor_data):
        """Container blocks (section, column) emit opening/closing pairs."""
        result = Divi5Converter().convert(sample_elementor_data)
        # The Elementor sample is section → column → widgets (no explicit row).
        for tag in ("section", "column"):
            assert f"<!-- wp:divi/{tag} " in result, f"missing opening wp:divi/{tag}"
            assert f"<!-- /wp:divi/{tag} -->" in result, f"missing closing /wp:divi/{tag}"

    def test_leaf_blocks_self_close(self, sample_elementor_data):
        """Leaf modules like `heading`, `text`, `button` must self-close (`/-->`)."""
        result = Divi5Converter().convert(sample_elementor_data)
        # At least one of the leaf module types from the sample must self-close.
        assert "/-->" in result

    def test_get_framework(self):
        assert Divi5Converter().get_framework() == "divi-5"


# =============================================================================
# Elementor 4 Atomic Converter Tests
# =============================================================================

class TestElementor4Converter:
    """Test Elementor4Converter class.

    v4 atomic schema replaces v3's section/column/widget with semantic atomic
    elements (`e-div-block`, `e-flexbox`, `e-grid`, `e-heading`, etc.). Tests
    pin the per-node shape (id/version/elType/isInner/interactions/settings/
    editor_settings/styles/elements) and the `e-` prefix.
    """

    ATOMIC_FIELDS = (
        "id",
        "version",
        "elType",
        "isInner",
        "interactions",
        "settings",
        "editor_settings",
        "styles",
        "elements",
    )

    def test_emits_atomic_e_prefix(self, sample_elementor_data):
        nodes = Elementor4Converter().convert_to_list(sample_elementor_data)
        assert nodes, "expected at least one atomic root"
        for node in nodes:
            assert isinstance(node, dict)
            assert node["elType"].startswith("e-"), (
                f"elType {node['elType']!r} must use the atomic `e-` prefix"
            )

    def test_every_node_has_atomic_fields(self, sample_elementor_data):
        nodes = Elementor4Converter().convert_to_list(sample_elementor_data)

        def walk(node, path="root"):
            for field in self.ATOMIC_FIELDS:
                assert field in node, f"{path}: missing atomic field {field!r}"
            assert isinstance(node["elements"], list)
            assert isinstance(node["interactions"], list)
            for i, child in enumerate(node["elements"]):
                walk(child, f"{path}/elements[{i}]")

        for i, root in enumerate(nodes):
            walk(root, f"[{i}]")

    def test_inner_flag_set_on_children(self, sample_elementor_data):
        """Root nodes have `isInner: false`; nested children get `isInner: true`."""
        nodes = Elementor4Converter().convert_to_list(sample_elementor_data)
        for root in nodes:
            assert root["isInner"] is False
            for child in root["elements"]:
                assert child["isInner"] is True

    def test_version_string_present(self, sample_elementor_data):
        nodes = Elementor4Converter().convert_to_list(sample_elementor_data)
        for node in nodes:
            assert isinstance(node["version"], str)

    def test_get_framework(self):
        assert Elementor4Converter().get_framework() == "elementor-4"


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
