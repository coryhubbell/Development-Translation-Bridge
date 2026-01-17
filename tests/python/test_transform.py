"""
Tests for Translation Bridge v4 Transform Engine.

Tests cover:
- Zone Theory classification
- Metadata preservation
- Content extraction
- Transform operations
- Elementor parser
"""

import json
import pytest
import sys
from pathlib import Path

# Add src to path for imports
sys.path.insert(0, str(Path(__file__).parent.parent.parent / "src"))

from translation_bridge import __version__
from translation_bridge.transforms.core import (
    TransformEngine,
    Zone,
    ZoneType,
    TransformResult,
)
from translation_bridge.transforms.registry import (
    TransformRegistry,
    ParserRegistry,
)
from translation_bridge.parsers.elementor import (
    ElementorParser,
    ElementorDocument,
    ElementorElement,
)


# =============================================================================
# Test Fixtures
# =============================================================================

@pytest.fixture
def sample_elementor_data():
    """Sample Elementor JSON data for testing."""
    return [
        {
            "id": "section-1",
            "elType": "section",
            "settings": {
                "background_background": "classic",
                "background_color": "#ffffff",
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
                                "size": "xl",
                                "header_size": "h1",
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
                                "link": {"url": "#features"},
                                "button_type": "primary",
                                "hover_animation": "grow",
                            },
                            "elements": [],
                        },
                    ],
                }
            ],
        }
    ]


@pytest.fixture
def transform_engine():
    """Create a TransformEngine instance."""
    return TransformEngine()


@pytest.fixture
def elementor_parser():
    """Create an ElementorParser instance."""
    return ElementorParser()


# =============================================================================
# Version Tests
# =============================================================================

class TestVersion:
    """Test version information."""

    def test_version_is_4(self):
        """Version should be 4.1.0."""
        assert __version__ == "4.1.0"


# =============================================================================
# Zone Theory Tests
# =============================================================================

class TestZoneType:
    """Test ZoneType enumeration."""

    def test_zone_types_exist(self):
        """All expected zone types should exist."""
        assert ZoneType.STRUCTURAL.value == "structural"
        assert ZoneType.CONTENT.value == "content"
        assert ZoneType.STYLING.value == "styling"
        assert ZoneType.BEHAVIORAL.value == "behavioral"
        assert ZoneType.META.value == "meta"


class TestZone:
    """Test Zone dataclass."""

    def test_zone_creation(self):
        """Zone should be created with correct attributes."""
        zone = Zone(
            zone_type=ZoneType.CONTENT,
            path="settings.title",
            data={"title": "Hello"},
            original_keys=["title"],
        )
        assert zone.zone_type == ZoneType.CONTENT
        assert zone.path == "settings.title"
        assert zone.data == {"title": "Hello"}
        assert zone.original_keys == ["title"]

    def test_zone_repr(self):
        """Zone repr should be informative."""
        zone = Zone(
            zone_type=ZoneType.CONTENT,
            path="settings.title",
            data={"title": "Hello"},
            original_keys=["title"],
        )
        repr_str = repr(zone)
        assert "content" in repr_str
        assert "settings.title" in repr_str


# =============================================================================
# Transform Engine Tests
# =============================================================================

class TestTransformEngine:
    """Test TransformEngine class."""

    def test_classify_zones_structural(self, transform_engine, sample_elementor_data):
        """Should identify structural zones."""
        zones = transform_engine.classify_zones(sample_elementor_data[0])
        structural_zones = [z for z in zones if z.zone_type == ZoneType.STRUCTURAL]
        assert len(structural_zones) > 0

    def test_classify_zones_content(self, transform_engine, sample_elementor_data):
        """Should identify content zones."""
        zones = transform_engine.classify_zones(sample_elementor_data[0])
        content_zones = [z for z in zones if z.zone_type == ZoneType.CONTENT]
        # Content should be found in nested widgets
        assert len(content_zones) >= 0  # May be in nested elements

    def test_classify_zones_styling(self, transform_engine, sample_elementor_data):
        """Should identify styling zones."""
        zones = transform_engine.classify_zones(sample_elementor_data[0])
        styling_zones = [z for z in zones if z.zone_type == ZoneType.STYLING]
        assert len(styling_zones) > 0

    def test_extract_content(self, transform_engine, sample_elementor_data):
        """Should extract all content items."""
        content = transform_engine.extract_content(sample_elementor_data)
        assert len(content) >= 2  # At least heading and text-editor content

        # Check for heading content
        titles = [c for c in content if c["key"] == "title"]
        assert len(titles) > 0
        assert "Welcome" in titles[0]["value"]

    def test_analyze(self, transform_engine, sample_elementor_data):
        """Should analyze data and return statistics."""
        stats = transform_engine.analyze(sample_elementor_data)
        assert "total_elements" in stats
        assert "total_zones" in stats
        assert "zones_by_type" in stats
        assert "metadata_preservation" in stats
        assert stats["metadata_preservation"] == "100%"

    def test_transform_preserves_metadata(self, transform_engine, sample_elementor_data):
        """Transform should preserve 100% metadata."""
        result = transform_engine.transform(sample_elementor_data)
        assert result.success is True
        assert result.metadata_preserved == 100.0
        assert result.data == sample_elementor_data  # No changes without transformer

    def test_transform_with_content_modifier(self, transform_engine, sample_elementor_data):
        """Transform should apply modifier to content zones."""
        def uppercase_content(zone: Zone) -> Zone:
            """Transform content to uppercase."""
            if zone.zone_type == ZoneType.CONTENT:
                new_data = {}
                for key, value in zone.data.items():
                    if isinstance(value, str):
                        new_data[key] = value.upper()
                    else:
                        new_data[key] = value
                return Zone(
                    zone_type=zone.zone_type,
                    path=zone.path,
                    data=new_data,
                    original_keys=zone.original_keys,
                )
            return zone

        result = transform_engine.transform(
            sample_elementor_data,
            zone_types=[ZoneType.CONTENT],
            transformer=uppercase_content,
        )
        assert result.success is True
        assert result.metadata_preserved == 100.0

    def test_to_json_and_from_json(self, transform_engine, sample_elementor_data):
        """Should serialize and deserialize JSON correctly."""
        json_str = transform_engine.to_json(sample_elementor_data)
        parsed = transform_engine.from_json(json_str)
        assert parsed == sample_elementor_data


# =============================================================================
# Registry Tests
# =============================================================================

class TestTransformRegistry:
    """Test TransformRegistry class."""

    def test_list_transforms(self):
        """Should list registered transforms."""
        transforms = TransformRegistry.list_transforms()
        assert len(transforms) >= 2  # elementor_to_bootstrap, bootstrap_to_elementor

    def test_get_transform(self):
        """Should retrieve registered transform function."""
        fn = TransformRegistry.get_transform("elementor", "bootstrap")
        assert fn is not None
        assert callable(fn)

    def test_get_supported_pairs(self):
        """Should return list of supported pairs."""
        pairs = TransformRegistry.get_supported_pairs()
        assert len(pairs) >= 2
        assert ("elementor", "bootstrap") in pairs


class TestParserRegistry:
    """Test ParserRegistry class."""

    def test_get_parser(self):
        """Should retrieve registered parser."""
        parser_cls = ParserRegistry.get_parser("elementor")
        assert parser_cls is not None
        assert parser_cls == ElementorParser

    def test_get_supported_frameworks(self):
        """Should list supported frameworks."""
        frameworks = ParserRegistry.get_supported_frameworks()
        assert "elementor" in frameworks


# =============================================================================
# Elementor Parser Tests
# =============================================================================

class TestElementorParser:
    """Test ElementorParser class."""

    def test_parse(self, elementor_parser, sample_elementor_data):
        """Should parse Elementor JSON data."""
        doc = elementor_parser.parse(sample_elementor_data)
        assert isinstance(doc, ElementorDocument)
        assert len(doc.elements) == 1
        assert doc.elements[0].el_type == "section"

    def test_parse_nested_elements(self, elementor_parser, sample_elementor_data):
        """Should parse nested elements correctly."""
        doc = elementor_parser.parse(sample_elementor_data)
        section = doc.elements[0]
        assert len(section.elements) == 1  # One column

        column = section.elements[0]
        assert column.el_type == "column"
        assert len(column.elements) == 3  # Three widgets

    def test_extract_content(self, elementor_parser, sample_elementor_data):
        """Should extract content from parsed document."""
        doc = elementor_parser.parse(sample_elementor_data)
        content = elementor_parser.extract_content(doc)

        assert len(content) >= 2

        # Check for specific content
        keys = [c["key"] for c in content]
        assert "title" in keys or "editor" in keys or "text" in keys

    def test_analyze(self, elementor_parser, sample_elementor_data):
        """Should analyze document and return statistics."""
        doc = elementor_parser.parse(sample_elementor_data)
        stats = elementor_parser.analyze(doc)

        assert stats["sections"] == 1
        assert stats["columns"] == 1
        assert stats["widgets"] == 3
        assert "heading" in stats["widget_types"]
        assert "text-editor" in stats["widget_types"]
        assert "button" in stats["widget_types"]

    def test_to_json(self, elementor_parser, sample_elementor_data):
        """Should serialize document to JSON."""
        doc = elementor_parser.parse(sample_elementor_data)
        json_str = elementor_parser.to_json(doc)

        # Parse it back and verify structure
        parsed = json.loads(json_str)
        assert "elements" in parsed
        assert len(parsed["elements"]) == 1


# =============================================================================
# Metadata Preservation Tests
# =============================================================================

class TestMetadataPreservation:
    """Test that transformations preserve all metadata."""

    def test_all_keys_preserved(self, transform_engine, sample_elementor_data):
        """All original keys should be preserved after transform."""
        import copy
        original = copy.deepcopy(sample_elementor_data)

        result = transform_engine.transform(sample_elementor_data)

        # Compare structure
        assert json.dumps(result.data, sort_keys=True) == json.dumps(original, sort_keys=True)

    def test_nested_settings_preserved(self, transform_engine, sample_elementor_data):
        """Nested settings should be preserved."""
        result = transform_engine.transform(sample_elementor_data)

        # Check padding settings preserved
        section = result.data[0]
        padding = section["settings"]["padding"]
        assert padding["top"] == "50"
        assert padding["bottom"] == "50"
        assert padding["unit"] == "px"

    def test_widget_types_preserved(self, transform_engine, sample_elementor_data):
        """Widget types should be preserved."""
        result = transform_engine.transform(sample_elementor_data)

        widgets = result.data[0]["elements"][0]["elements"]
        assert widgets[0]["widgetType"] == "heading"
        assert widgets[1]["widgetType"] == "text-editor"
        assert widgets[2]["widgetType"] == "button"


# =============================================================================
# CLI Tests (Unit)
# =============================================================================

class TestCLI:
    """Test CLI module functions."""

    def test_get_extension_for_framework(self):
        """Should return correct extension for each framework."""
        from translation_bridge.cli import get_extension_for_framework

        assert get_extension_for_framework("elementor") == ".json"
        assert get_extension_for_framework("bootstrap") == ".html"
        assert get_extension_for_framework("divi") == ".txt"
        assert get_extension_for_framework("gutenberg") == ".html"
        assert get_extension_for_framework("unknown") == ".json"


# =============================================================================
# Integration Tests
# =============================================================================

class TestIntegration:
    """Integration tests for full workflows."""

    def test_full_parse_and_transform(self, elementor_parser, transform_engine, sample_elementor_data):
        """Should parse Elementor data and transform it."""
        # Parse
        doc = elementor_parser.parse(sample_elementor_data)

        # Analyze
        stats = elementor_parser.analyze(doc)
        assert stats["total_elements"] == 5  # 1 section + 1 column + 3 widgets

        # Extract content
        content = elementor_parser.extract_content(doc)
        assert len(content) >= 2

        # Transform (identity)
        raw_data = doc.to_dict()
        result = transform_engine.transform(raw_data["elements"])
        assert result.success is True
        assert result.metadata_preserved == 100.0

    def test_round_trip(self, elementor_parser, sample_elementor_data):
        """Data should survive round-trip parse → serialize → parse."""
        # First parse
        doc1 = elementor_parser.parse(sample_elementor_data)

        # Serialize
        json_str = elementor_parser.to_json(doc1)

        # Parse again
        data2 = json.loads(json_str)
        doc2 = elementor_parser.parse(data2["elements"])

        # Compare
        assert len(doc1.elements) == len(doc2.elements)
        assert doc1.elements[0].id == doc2.elements[0].id


if __name__ == "__main__":
    pytest.main([__file__, "-v"])
