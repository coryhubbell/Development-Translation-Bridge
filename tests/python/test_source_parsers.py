"""JSON source parser tests (roadmap 4.7+ item 1).

Bricks, classic Oxygen, and Elementor 4 Atomic content parse into the
universal element shape and ride the lossless transform path end to end.
"""

import json

import pytest

from translation_bridge.parsers.bricks import BricksParser
from translation_bridge.parsers.oxygen import OxygenParser
from translation_bridge.parsers.elementor4 import Elementor4Parser
from translation_bridge.transforms.registry import ParserRegistry, TransformRegistry


# ---------------------------------------------------------------------------
# Fixtures — native-format samples mirroring the verified schemas
# ---------------------------------------------------------------------------


@pytest.fixture
def bricks_flat_page():
    """Real Bricks 2.x shape: flat list, string parent ids, children id lists."""
    return [
        {"id": "sec1", "name": "section", "parent": 0, "children": ["hd1", "tx1", "bt1"], "settings": {}},
        {"id": "hd1", "name": "heading", "parent": "sec1", "children": [], "settings": {"text": "Bricks Title", "tag": "h2"}},
        {"id": "tx1", "name": "text-basic", "parent": "sec1", "children": [], "settings": {"text": "Some body copy."}},
        {"id": "bt1", "name": "button", "parent": "sec1", "children": [], "settings": {"text": "Read more", "link": {"url": "https://example.com", "newTab": True}}},
    ]


@pytest.fixture
def oxygen_tree_page():
    """Real ct_builder_json shape: nested root tree with options bags."""
    return {
        "id": 0,
        "name": "root",
        "children": [
            {
                "id": 1,
                "name": "ct_section",
                "options": {"ct_id": 1, "selector": "section-1-9", "original": {"padding-top": "80", "gap": "10px"}},
                "children": [
                    {"id": 2, "name": "ct_headline", "options": {"ct_id": 2, "ct_content": "Oxygen Title", "tag": "h3"}, "children": []},
                    {"id": 3, "name": "ct_text_block", "options": {"ct_id": 3, "ct_content": "Oxygen body."}, "children": []},
                    {
                        "id": 4,
                        "name": "ct_link_button",
                        "options": {
                            "ct_id": 4,
                            "ct_content": "Go",
                            "url": "https://example.com",
                            "media": {"tablet": {"original": {"font-size": "14"}}},
                            "original": {"font-size": "18"},
                        },
                        "children": [],
                    },
                ],
            }
        ],
    }


@pytest.fixture
def elementor4_page():
    """Atomic typed-prop shape verified against the elementor repo."""
    return {
        "title": "Test",
        "type": "page",
        "version": "0.4",
        "page_settings": [],
        "content": [
            {
                "id": "aa11", "version": "0.0", "elType": "e-flexbox", "isInner": False,
                "interactions": [], "settings": {}, "editor_settings": {},
                "styles": {
                    "e-s1": {
                        "id": "e-s1", "type": "class", "label": "local",
                        "variants": [
                            {"meta": {"breakpoint": "desktop", "state": None}, "props": {"font-size": "32px"}},
                            {"meta": {"breakpoint": "mobile", "state": None}, "props": {"font-size": "18px"}},
                        ],
                    }
                },
                "elements": [
                    {
                        "id": "bb22", "version": "0.0", "elType": "e-heading", "isInner": True,
                        "interactions": [],
                        "settings": {
                            "title": {"$$type": "html-v3", "value": {"content": {"$$type": "string", "value": "Atomic Title"}}},
                            "tag": {"$$type": "string", "value": "h2"},
                        },
                        "editor_settings": {}, "styles": {}, "elements": [],
                    },
                    {
                        "id": "cc33", "version": "0.0", "elType": "e-button", "isInner": True,
                        "interactions": [],
                        "settings": {
                            "text": {"$$type": "html-v3", "value": {"content": {"$$type": "string", "value": "Press"}}},
                            "link": {
                                "$$type": "link",
                                "value": {
                                    "destination": {"$$type": "url", "value": "https://example.com"},
                                    "isTargetBlank": {"$$type": "boolean", "value": True},
                                },
                            },
                        },
                        "editor_settings": {}, "styles": {}, "elements": [],
                    },
                ],
            }
        ],
    }


# ---------------------------------------------------------------------------
# Bricks
# ---------------------------------------------------------------------------


class TestBricksParser:
    def test_flat_page_parses_to_nested_universal(self, bricks_flat_page):
        doc = BricksParser().parse(bricks_flat_page)
        assert len(doc.elements) == 1
        section = doc.elements[0]
        assert section.el_type == "section"
        assert [c.widget_type for c in section.elements] == ["heading", "text-editor", "button"]

        heading = section.elements[0]
        assert heading.settings["title"] == "Bricks Title"
        assert heading.settings["header_size"] == "h2"

        button = section.elements[2]
        assert button.settings["link"]["url"] == "https://example.com"
        assert button.settings["link"]["is_external"] == "on"

    def test_content_wrapper_and_json_string_accepted(self, bricks_flat_page):
        doc = BricksParser().parse(json.dumps({"content": bricks_flat_page}))
        assert len(doc.elements) == 1

    def test_transforms_to_gutenberg(self, bricks_flat_page):
        doc = BricksParser().parse(bricks_flat_page)
        out = TransformRegistry.get_transform("bricks", "gutenberg")(doc.to_dict())
        assert "wp:heading" in out
        assert "Bricks Title" in out

    def test_analyze_and_extract_content(self, bricks_flat_page):
        parser = BricksParser()
        doc = parser.parse(bricks_flat_page)
        stats = parser.analyze(doc)
        assert stats["sections"] == 1
        assert stats["widgets"] == 3
        content = parser.extract_content(doc)
        assert "Bricks Title" in content["heading"]


# ---------------------------------------------------------------------------
# Classic Oxygen
# ---------------------------------------------------------------------------


class TestOxygenSourceParser:
    def test_root_tree_parses(self, oxygen_tree_page):
        doc = OxygenParser().parse(oxygen_tree_page)
        assert len(doc.elements) == 1
        section = doc.elements[0]
        assert section.el_type == "section"
        assert section.settings["padding-top"] == "80px", "unitless numerics normalize to px"
        assert section.settings["gap"] == "10px"
        assert [c.widget_type for c in section.elements] == ["heading", "text-editor", "button"]

    def test_wrapper_and_flat_shapes_parse(self, oxygen_tree_page):
        wrapped = {"ct_builder_json": oxygen_tree_page}
        assert len(OxygenParser().parse(wrapped).elements) == 1

        flat = [
            {"id": 1, "name": "ct_section", "options": {"ct_id": 1, "ct_parent": 0}},
            {"id": 2, "name": "ct_headline", "options": {"ct_id": 2, "ct_parent": 1, "ct_content": "Flat", "tag": "h2"}},
        ]
        doc = OxygenParser().parse(flat)
        assert doc.elements[0].elements[0].settings["title"] == "Flat"

    def test_shortcode_shape_parses(self):
        shortcodes = (
            "[ct_section ct_options='{\"ct_id\":1,\"ct_parent\":0}']"
            "[ct_headline ct_options='{\"ct_id\":2,\"ct_parent\":1,\"ct_content\":\"Shortcode Title\",\"tag\":\"h2\"}'][/ct_headline]"
            "[/ct_section]"
        )
        doc = OxygenParser().parse(shortcodes)
        assert doc.elements[0].elements[0].settings["title"] == "Shortcode Title"

    def test_media_overrides_canonicalize(self, oxygen_tree_page):
        doc = OxygenParser().parse(oxygen_tree_page)
        button = doc.elements[0].elements[2]
        canonical = button.responsive["styles"]
        assert canonical["tablet"]["default"]["font-size"] == "14px"
        assert canonical["desktop"]["default"]["font-size"] == "18px"

    def test_transforms_to_gutenberg(self, oxygen_tree_page):
        doc = OxygenParser().parse(oxygen_tree_page)
        out = TransformRegistry.get_transform("oxygen", "gutenberg")(doc.to_dict())
        assert "Oxygen Title" in out
        assert "wp:" in out


# ---------------------------------------------------------------------------
# Elementor 4 Atomic
# ---------------------------------------------------------------------------


class TestElementor4SourceParser:
    def test_typed_props_unwrap(self, elementor4_page):
        doc = Elementor4Parser().parse(elementor4_page)
        assert doc.version == "0.4"
        container = doc.elements[0]
        assert container.el_type == "container"

        heading = container.elements[0]
        assert heading.widget_type == "heading"
        assert heading.settings["title"] == "Atomic Title"
        assert heading.settings["header_size"] == "h2"

        button = container.elements[1]
        assert button.settings["text"] == "Press"
        assert button.settings["link"]["url"] == "https://example.com"
        assert button.settings["link"]["is_external"] == "on"

    def test_style_variants_canonicalize(self, elementor4_page):
        doc = Elementor4Parser().parse(elementor4_page)
        canonical = doc.elements[0].responsive["styles"]
        assert canonical["desktop"]["default"]["font-size"] == "32px"
        assert canonical["phone"]["default"]["font-size"] == "18px", "mobile maps to phone"

    def test_bare_node_list_accepted(self, elementor4_page):
        doc = Elementor4Parser().parse(elementor4_page["content"])
        assert len(doc.elements) == 1

    def test_transforms_to_gutenberg(self, elementor4_page):
        doc = Elementor4Parser().parse(elementor4_page)
        out = TransformRegistry.get_transform("elementor4", "gutenberg")(doc.to_dict())
        assert "Atomic Title" in out


# ---------------------------------------------------------------------------
# Registry + CLI wiring
# ---------------------------------------------------------------------------


class TestSourceParserWiring:
    def test_parsers_registered(self):
        assert ParserRegistry.get_parser("bricks") is BricksParser
        assert ParserRegistry.get_parser("oxygen") is OxygenParser
        assert ParserRegistry.get_parser("elementor4") is Elementor4Parser

    def test_transform_pairs_registered(self):
        pairs = TransformRegistry.get_supported_pairs()
        for pair in [
            ("bricks", "gutenberg"),
            ("bricks", "bootstrap"),
            ("oxygen", "gutenberg"),
            ("oxygen", "bootstrap"),
            ("elementor4", "gutenberg"),
            ("elementor4", "bootstrap"),
        ]:
            assert pair in pairs, f"missing transform pair: {pair}"

    def test_cli_resolves_new_parsers(self):
        from translation_bridge.cli import get_parser_for_framework

        assert isinstance(get_parser_for_framework("bricks"), BricksParser)
        assert isinstance(get_parser_for_framework("oxygen"), OxygenParser)
        assert isinstance(get_parser_for_framework("elementor-4"), Elementor4Parser)
        assert isinstance(get_parser_for_framework("elementor4"), Elementor4Parser)
