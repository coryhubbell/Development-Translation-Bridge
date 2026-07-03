"""Responsive breakpoint round-trip tests (Python engine).

Mirrors tests/Unit/ResponsiveRoundTripTest.php: converters must emit full
multi-breakpoint output when an inbound element carries canonical responsive
data under ``element["responsive"]`` (or ``element["metadata"]["responsive"]``).
"""

from translation_bridge.converters.divi5 import Divi5Converter
from translation_bridge.converters.elementor4 import Elementor4Converter
from translation_bridge.converters.oxygen6 import Oxygen6Converter
from translation_bridge.responsive import (
    canonical_to_divi5_wrapper,
    canonical_to_elementor4_variants,
    canonical_to_oxygen6_design,
)

import json


RESPONSIVE_HEADING = {
    "type": "heading",
    "content": "Desktop title",
    "settings": {"level": "h2"},
    "responsive": {
        "fields": {
            "text": {
                "desktop": {"default": "Desktop title", "hover": "Hover title"},
                "tablet": {"default": "Tablet title"},
                "phone": {"default": "Phone title"},
            }
        },
        "styles": {
            "desktop": {"default": {"font-size": "32px"}, "hover": {"color": "#ff0000"}},
            "tablet": {"default": {"font-size": "24px"}},
            "phone": {"default": {"font-size": "18px"}},
        },
    },
}


class TestResponsiveHelpers:
    def test_divi5_wrapper_shape(self):
        wrapper = canonical_to_divi5_wrapper(
            {"desktop": {"default": "A", "hover": "B"}, "phone": {"default": "C"}}
        )
        assert wrapper == {
            "desktop": {"value": "A", "hover": "B"},
            "phone": {"value": "C"},
        }

    def test_elementor4_variants_use_real_breakpoint_keys(self):
        variants = canonical_to_elementor4_variants(
            {"phone": {"default": {"font-size": "18px"}}}
        )
        assert variants == [
            {"meta": {"breakpoint": "mobile", "state": None}, "props": {"font-size": "18px"}}
        ]

    def test_oxygen6_design_renests_dotted_paths(self):
        design = canonical_to_oxygen6_design(
            {
                "desktop": {"default": {"layout.gap": "40px"}},
                "tablet": {"default": {"layout.gap": "24px"}},
            }
        )
        assert design == {
            "layout": {
                "gap": {
                    "breakpoint_base": "40px",
                    "breakpoint_tablet_portrait": "24px",
                }
            }
        }


class TestDivi5Responsive:
    def test_emits_multi_breakpoint_wrappers(self):
        markup = Divi5Converter().convert([RESPONSIVE_HEADING])
        # Attrs are unicode-escaped; parse them back out of the block comment.
        start = markup.index("{")
        end = markup.rindex("}") + 1
        attrs = json.loads(markup[start:end])

        text = attrs["content"]["text"]
        assert text["desktop"]["value"] == "Desktop title"
        assert text["desktop"]["hover"] == "Hover title"
        assert text["tablet"]["value"] == "Tablet title"
        assert text["phone"]["value"] == "Phone title"


class TestElementor4Responsive:
    def test_emits_variant_per_breakpoint_and_state(self):
        nodes = Elementor4Converter().convert_to_list([RESPONSIVE_HEADING])
        styles = nodes[0]["styles"]
        assert styles, "responsive styles must produce a style definition"

        definition = next(iter(styles.values()))
        by_key = {
            (v["meta"]["breakpoint"], v["meta"]["state"]): v["props"]
            for v in definition["variants"]
        }
        assert by_key[("desktop", None)]["font-size"] == "32px"
        assert by_key[("tablet", None)]["font-size"] == "24px"
        assert by_key[("mobile", None)]["font-size"] == "18px"
        assert by_key[("desktop", "hover")]["color"] == "#ff0000"

        # The classes prop must reference the emitted style definition.
        classes = nodes[0]["settings"]["classes"]
        assert classes["$$type"] == "classes"
        assert classes["value"] == list(styles.keys())


class TestOxygen6Responsive:
    def test_emits_breakpoint_leaves_in_design(self):
        payload = Oxygen6Converter().convert_to_dict([RESPONSIVE_HEADING])
        node = payload["tree"]["root"]["children"][0]
        design = node["data"]["properties"]["design"]

        assert design["font-size"]["breakpoint_base"] == "32px"
        assert design["font-size"]["breakpoint_tablet_portrait"] == "24px"
        assert design["font-size"]["breakpoint_phone_portrait"] == "18px"


class TestElementorV3Canonicalization:
    def test_parser_canonicalizes_suffixes(self):
        from translation_bridge.parsers.elementor import ElementorParser

        doc = ElementorParser().parse(
            [
                {
                    "id": "w1",
                    "elType": "widget",
                    "widgetType": "heading",
                    "settings": {
                        "title": "Hi",
                        "align": "left",
                        "align_tablet": "center",
                        "align_mobile": "right",
                        "color_hover": "#ff0000",
                    },
                }
            ]
        )
        element = doc.elements[0].to_dict()
        styles = element["responsive"]["styles"]
        assert styles["tablet"]["default"]["align"] == "center"
        assert styles["phone"]["default"]["align"] == "right"
        assert styles["desktop"]["hover"]["color"] == "#ff0000"

    def test_converter_emits_suffixes(self):
        from translation_bridge.converters.elementor import ElementorConverter
        import json as _json

        out = ElementorConverter().convert(
            [
                {
                    "type": "heading",
                    "content": "Hi",
                    "attributes": {},
                    "responsive": {
                        "styles": {
                            "tablet": {"default": {"align": "center"}},
                            "phone": {"default": {"align": "right"}},
                            "desktop": {"hover": {"color": "#f00"}},
                        }
                    },
                }
            ]
        )
        text = out if isinstance(out, str) else _json.dumps(out)
        assert "align_tablet" in text
        assert "align_mobile" in text
        assert "color_hover" in text


class TestBricksCanonicalization:
    def test_parser_canonicalizes_breakpoint_suffixes(self):
        from translation_bridge.parsers.bricks import BricksParser

        doc = BricksParser().parse(
            [
                {
                    "id": "h1",
                    "name": "heading",
                    "parent": 0,
                    "children": [],
                    "settings": {
                        "text": "Hi",
                        "_typography": {"font-size": "32px"},
                        "_typography:tablet_portrait": {"font-size": "24px"},
                        "_typography:mobile_portrait": {"font-size": "18px"},
                    },
                }
            ]
        )
        styles = doc.elements[0].responsive["styles"]
        assert styles["tablet"]["default"]["_typography"]["font-size"] == "24px"
        assert styles["phone"]["default"]["_typography"]["font-size"] == "18px"

    def test_converter_emits_breakpoint_suffixes(self):
        from translation_bridge.converters.bricks import BricksConverter

        out = BricksConverter().convert(
            [
                {
                    "type": "heading",
                    "settings": {"title": "Hi"},
                    "elType": "widget",
                    "widgetType": "heading",
                    "responsive": {
                        "styles": {
                            "tablet": {"default": {"_typography": {"font-size": "24px"}}},
                            "phone": {"default": {"_typography": {"font-size": "18px"}}},
                        }
                    },
                }
            ]
        )
        assert "_typography:tablet_portrait" in out
        assert "_typography:mobile_portrait" in out

    def test_cross_framework_bricks_to_elementor(self):
        from translation_bridge.parsers.bricks import BricksParser
        from translation_bridge.converters.elementor import ElementorConverter
        import json as _json

        doc = BricksParser().parse(
            [
                {
                    "id": "h1",
                    "name": "heading",
                    "parent": 0,
                    "children": [],
                    "settings": {
                        "text": "Hi",
                        "align": "left",
                        "align:tablet_portrait": "center",
                    },
                }
            ]
        )
        out = ElementorConverter().convert(doc.to_dict())
        text = out if isinstance(out, str) else _json.dumps(out)
        assert "align_tablet" in text, "Bricks tablet override must become an Elementor _tablet suffix"
