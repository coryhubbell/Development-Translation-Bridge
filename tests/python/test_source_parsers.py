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


# ---------------------------------------------------------------------------
# Oxygen 6 (tranche 2 — real Breakdance fixture)
# ---------------------------------------------------------------------------


class TestOxygen6SourceParser:
    def test_parses_real_breakdance_fixture(self):
        from translation_bridge.parsers.oxygen6 import Oxygen6Parser

        with open("tests/fixtures/oxygen6/breakdance-element-export.json") as handle:
            doc = Oxygen6Parser().parse(json.load(handle))

        assert doc.elements, "real export must produce elements"
        content = Oxygen6Parser().extract_content(doc)
        assert any("Genre:" in text for texts in content.values() for text in texts)

    def test_parses_tree_root_envelope(self):
        from translation_bridge.parsers.oxygen6 import Oxygen6Parser

        payload = {
            "tree": {
                "root": {
                    "id": 1,
                    "data": {"type": "root", "properties": []},
                    "children": [
                        {
                            "id": 100,
                            "data": {
                                "type": "EssentialElements\\Heading",
                                "properties": {"content": {"content": {"text": "O6", "tags": "h3"}}},
                            },
                            "children": [],
                            "_parentId": 1,
                        }
                    ],
                }
            },
            "_nextNodeId": 101,
        }
        doc = Oxygen6Parser().parse(payload)
        assert doc.elements[0].settings == {"title": "O6", "header_size": "h3"}

    def test_design_breakpoints_canonicalize(self):
        from translation_bridge.parsers.oxygen6 import Oxygen6Parser

        node = {
            "id": 2,
            "data": {
                "type": "EssentialElements\\Heading",
                "properties": {
                    "content": {"content": {"text": "Hi", "tags": "h2"}},
                    "design": {
                        "layout": {"gap": {"breakpoint_base": "40px", "breakpoint_tablet_portrait": "24px"}}
                    },
                },
            },
            "children": [],
        }
        doc = Oxygen6Parser().parse([node])
        styles = doc.elements[0].responsive["styles"]
        assert styles["desktop"]["default"]["layout.gap"] == "40px"
        assert styles["tablet"]["default"]["layout.gap"] == "24px"

    def test_transforms_to_gutenberg(self):
        from translation_bridge.parsers.oxygen6 import Oxygen6Parser

        with open("tests/fixtures/oxygen6/breakdance-element-export.json") as handle:
            doc = Oxygen6Parser().parse(json.load(handle))
        out = TransformRegistry.get_transform("oxygen6", "gutenberg")(doc.to_dict())
        assert "Genre:" in out


# ---------------------------------------------------------------------------
# DIVI 5 (tranche 2)
# ---------------------------------------------------------------------------


DIVI5_MARKUP = (
    '<!-- wp:divi/section {"builderVersion":"5.0.0"} -->'
    '<!-- wp:divi/heading {"content":{"text":{"desktop":{"value":"D5 Title","hover":"Hover Title"},'
    '"tablet":{"value":"Tablet Title"}},"level":{"desktop":{"value":"h3"}}},"builderVersion":"5.0.0"} /-->'
    '<!-- wp:divi/text {"content":{"innerContent":{"desktop":{"value":"\\u003cp\\u003eBody\\u003c/p\\u003e"}}},"builderVersion":"5.0.0"} /-->'
    '<!-- wp:divi/button {"content":{"text":{"desktop":{"value":"Go"}},"url":{"desktop":{"value":"https://x.co"}},"urlNewWindow":true},"builderVersion":"5.0.0"} /-->'
    "<!-- /wp:divi/section -->"
)


class TestDivi5SourceParser:
    def test_parses_verified_markup(self):
        from translation_bridge.parsers.divi5 import Divi5Parser

        doc = Divi5Parser().parse(DIVI5_MARKUP)
        section = doc.elements[0]
        assert section.el_type == "section"
        heading, text, button = section.elements
        assert heading.settings["title"] == "D5 Title"
        assert heading.settings["header_size"] == "h3"
        assert "Body" in text.settings["editor"]
        assert button.settings["link"]["url"] == "https://x.co"

    def test_responsive_wrappers_canonicalize(self):
        from translation_bridge.parsers.divi5 import Divi5Parser

        doc = Divi5Parser().parse(DIVI5_MARKUP)
        fields = doc.elements[0].elements[0].responsive["fields"]
        assert fields["text"]["tablet"]["default"] == "Tablet Title"
        assert fields["text"]["desktop"]["hover"] == "Hover Title"

    def test_legacy_module_content_fallback(self):
        from translation_bridge.parsers.divi5 import Divi5Parser

        legacy = '<!-- wp:divi/heading {"module":{"content":{"text":{"desktop":{"value":"Legacy"}}}},"builderVersion":"5.0.0"} /-->'
        doc = Divi5Parser().parse(legacy)
        assert doc.elements[0].settings["title"] == "Legacy"

    def test_transforms_to_gutenberg(self):
        from translation_bridge.parsers.divi5 import Divi5Parser

        doc = Divi5Parser().parse(DIVI5_MARKUP)
        out = TransformRegistry.get_transform("divi5", "gutenberg")(doc.to_dict())
        assert "D5 Title" in out


# ---------------------------------------------------------------------------
# Gutenberg (tranche 2)
# ---------------------------------------------------------------------------


GUTENBERG_MARKUP = (
    '<!-- wp:heading {"level":2} --><h2 class="wp-block-heading">GB Title</h2><!-- /wp:heading -->'
    "<!-- wp:paragraph --><p>Body text.</p><!-- /wp:paragraph -->"
    '<!-- wp:buttons --><div class="wp-block-buttons">'
    '<!-- wp:button {"url":"https://x.co"} --><div class="wp-block-button">'
    '<a class="wp-block-button__link wp-element-button" href="https://x.co">Press</a></div><!-- /wp:button -->'
    "</div><!-- /wp:buttons -->"
    "<!-- wp:quote --><blockquote class=\"wp-block-quote\"><p>Wisdom.</p><cite>Someone</cite></blockquote><!-- /wp:quote -->"
    "<!-- wp:list --><ul><li>One</li><li>Two</li></ul><!-- /wp:list -->"
)


class TestGutenbergSourceParser:
    def test_parses_core_blocks(self):
        from translation_bridge.parsers.gutenberg import GutenbergParser

        doc = GutenbergParser().parse(GUTENBERG_MARKUP)
        types = [(el.el_type, el.widget_type) for el in doc.elements]
        assert types[0] == ("widget", "heading")
        assert doc.elements[0].settings == {"title": "GB Title", "header_size": "h2"}
        assert doc.elements[1].settings["editor"] == "Body text."

        buttons_wrap = doc.elements[2]
        assert buttons_wrap.el_type == "container"
        button = buttons_wrap.elements[0]
        assert button.settings["text"] == "Press"
        assert button.settings["link"]["url"] == "https://x.co"

        quote = doc.elements[3]
        assert quote.settings["testimonial_content"] == "Wisdom."
        assert quote.settings["testimonial_name"] == "Someone"

        assert doc.elements[4].settings["icon_list"] == [{"text": "One"}, {"text": "Two"}]

    def test_unknown_blocks_preserved(self):
        from translation_bridge.parsers.gutenberg import GutenbergParser

        markup = '<!-- wp:some/custom-block --><div data-x="1">Custom</div><!-- /wp:some/custom-block -->'
        doc = GutenbergParser().parse(markup)
        assert doc.elements[0].widget_type == "html"
        assert "Custom" in doc.elements[0].settings["html"]

    def test_transforms_to_bricks_and_elementor(self):
        from translation_bridge.parsers.gutenberg import GutenbergParser

        doc = GutenbergParser().parse(GUTENBERG_MARKUP)
        bricks = TransformRegistry.get_transform("gutenberg", "bricks")(doc.to_dict())
        assert "GB Title" in bricks
        elementor = TransformRegistry.get_transform("gutenberg", "elementor")(doc.to_dict())
        text = elementor if isinstance(elementor, str) else json.dumps(elementor)
        assert "GB Title" in text


class TestTranche2Wiring:
    def test_parsers_registered(self):
        from translation_bridge.parsers.oxygen6 import Oxygen6Parser
        from translation_bridge.parsers.divi5 import Divi5Parser
        from translation_bridge.parsers.gutenberg import GutenbergParser

        assert ParserRegistry.get_parser("oxygen6") is Oxygen6Parser
        assert ParserRegistry.get_parser("divi5") is Divi5Parser
        assert ParserRegistry.get_parser("gutenberg") is GutenbergParser

    def test_transform_pairs_registered(self):
        pairs = TransformRegistry.get_supported_pairs()
        for pair in [
            ("oxygen6", "gutenberg"),
            ("oxygen6", "bootstrap"),
            ("divi5", "gutenberg"),
            ("divi5", "bootstrap"),
            ("gutenberg", "bootstrap"),
            ("gutenberg", "elementor"),
            ("gutenberg", "bricks"),
        ]:
            assert pair in pairs, f"missing transform pair: {pair}"

    def test_cli_resolves_tranche2_parsers(self):
        from translation_bridge.cli import get_parser_for_framework
        from translation_bridge.parsers.oxygen6 import Oxygen6Parser
        from translation_bridge.parsers.divi5 import Divi5Parser
        from translation_bridge.parsers.gutenberg import GutenbergParser

        assert isinstance(get_parser_for_framework("oxygen-6"), Oxygen6Parser)
        assert isinstance(get_parser_for_framework("divi-5"), Divi5Parser)
        assert isinstance(get_parser_for_framework("gutenberg"), GutenbergParser)
