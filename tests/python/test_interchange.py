"""RFC 5.0 Phase 2 tail — shared component→universal interchange.

The Python mirror of DEVTB_Component::to_universal(): legacy
DEVTB_Component-shaped dicts translate to canonical universal elements in
one place, and the Gutenberg converter consumes them through it instead of
an ad-hoc adapter. The exact-mirror guarantee against the PHP engine is
asserted on real fixtures in test_conformance.py.
"""

from translation_bridge.interchange import (
    component_to_element,
    components_to_document,
)
from translation_bridge.converters.gutenberg import GutenbergConverter


def widget(comp_type, attributes=None, content="", children=None, **extra):
    component = {
        "id": "c1",
        "type": comp_type,
        "attributes": attributes or {},
        "content": content,
        "children": children or [],
    }
    component.update(extra)
    return component


class TestStructuralMapping:
    def test_container_becomes_section(self):
        element = component_to_element(widget("container"))
        assert element["elType"] == "section"
        assert element["settings"] == {}

    def test_row_becomes_container(self):
        assert component_to_element(widget("row"))["elType"] == "container"

    def test_column_stays_column(self):
        assert component_to_element(widget("column"))["elType"] == "column"

    def test_nested_section_becomes_inner_container(self):
        tree = widget("container", children=[widget("section")])
        element = component_to_element(tree)
        inner = element["elements"][0]
        assert inner["elType"] == "container"
        assert inner["isInner"] is True

    def test_children_recurse_with_is_inner(self):
        tree = widget("row", children=[widget("column", children=[widget("heading", content="Hi")])])
        column = component_to_element(tree)["elements"][0]
        heading = column["elements"][0]
        assert column["isInner"] is True
        assert heading["elType"] == "widget"
        assert heading["settings"]["title"] == "Hi"


class TestWidgetVocabulary:
    def test_heading_content_and_level(self):
        element = component_to_element(widget("heading", {"level": 3}, "Title here"))
        assert element["widgetType"] == "heading"
        assert element["settings"] == {"title": "Title here", "header_size": "h3"}

    def test_heading_string_level_passes_through(self):
        settings = component_to_element(widget("heading", {"level": "h4"}, "T"))["settings"]
        assert settings["header_size"] == "h4"

    def test_text_becomes_text_editor(self):
        element = component_to_element(widget("text", {}, "Body copy"))
        assert element["widgetType"] == "text-editor"
        assert element["settings"]["editor"] == "Body copy"

    def test_button_label_url_target(self):
        element = component_to_element(
            widget("button", {"label": "Go", "url": "https://x.test", "target": "_blank"})
        )
        assert element["settings"] == {
            "text": "Go",
            "link": {"url": "https://x.test", "is_external": "on"},
        }

    def test_image_url_and_alt(self):
        settings = component_to_element(
            widget("image", {"image_url": "https://x.test/a.png", "alt_text": "A"})
        )["settings"]
        assert settings["image"] == {"url": "https://x.test/a.png", "alt": "A"}

    def test_testimonial_author_and_job(self):
        settings = component_to_element(
            widget("testimonial", {"author": "Maya Chen", "job_title": "CTO"}, "Loved it.")
        )["settings"]
        assert settings == {
            "testimonial_content": "Loved it.",
            "testimonial_name": "Maya Chen",
            "testimonial_job": "CTO",
        }

    def test_quote_maps_to_testimonial(self):
        element = component_to_element(widget("quote", {"cite": "Ada"}, "Words."))
        assert element["widgetType"] == "testimonial"
        assert element["settings"]["testimonial_name"] == "Ada"

    def test_card_heading_and_description(self):
        settings = component_to_element(
            widget("card", {"heading": "Feature"}, "It does things.")
        )["settings"]
        assert settings == {"title_text": "Feature", "description_text": "It does things."}

    def test_cta_label_and_url(self):
        settings = component_to_element(
            widget("cta", {"heading": "Act now", "label": "Start", "url": "https://x.test"}, "Do it.")
        )["settings"]
        assert settings == {
            "title": "Act now",
            "description": "Do it.",
            "button_text": "Start",
            "link": {"url": "https://x.test"},
        }

    def test_accordion_heading_content_fallback_tab(self):
        settings = component_to_element(
            widget("accordion", {"heading": "Q1"}, "A1")
        )["settings"]
        assert settings["tabs"] == [{"tab_title": "Q1", "tab_content": "A1"}]

    def test_tabs_attribute_passthrough(self):
        tabs = [{"tab_title": "T", "tab_content": "C"}]
        settings = component_to_element(widget("tabs", {"tabs": tabs}))["settings"]
        assert settings["tabs"] == tabs

    def test_counter_number(self):
        settings = component_to_element(
            widget("counter", {"heading": "Users", "number": 500})
        )["settings"]
        assert settings == {"title": "Users", "ending_number": "500"}

    def test_video_url(self):
        settings = component_to_element(
            widget("video", {"url": "https://youtu.be/x"})
        )["settings"]
        assert settings["youtube_url"] == "https://youtu.be/x"

    def test_alert_round_trip_vocabulary(self):
        settings = component_to_element(
            widget("alert", {"heading": "Heads up"}, "Something happened.")
        )["settings"]
        assert settings == {
            "alert_title": "Heads up",
            "alert_description": "Something happened.",
        }

    def test_icon_selected_icon(self):
        settings = component_to_element(widget("icon", {"icon": "fas fa-star"}))["settings"]
        assert settings == {"selected_icon": {"value": "fas fa-star"}}

    def test_gallery_images(self):
        images = [{"url": "https://x.test/1.png", "alt": "one"}]
        settings = component_to_element(widget("gallery", {"images": images}))["settings"]
        assert settings == {"wp_gallery": images}

    def test_list_items(self):
        items = [{"text": "First"}, {"text": "Second"}]
        settings = component_to_element(widget("list", {"items": items}))["settings"]
        assert settings == {"icon_list": items}

    def test_unknown_type_becomes_html_with_content(self):
        element = component_to_element(widget("table", {}, "<table><tr><td>x</td></tr></table>"))
        assert element["widgetType"] == "html"
        assert element["settings"]["html"] == "<table><tr><td>x</td></tr></table>"

    def test_responsive_metadata_carries_through(self):
        responsive = {"styles": {"tablet": {"default": {"font-size": "14px"}}}}
        component = widget("heading", {}, "T", metadata={"responsive": responsive})
        assert component_to_element(component)["responsive"] == responsive


class TestDocumentWrapper:
    def test_components_to_document_shape(self):
        document = components_to_document([widget("heading", {}, "Hello")])
        assert set(document) == {"elements", "version", "title", "meta"}
        assert document["meta"] == {"interchange": "universal"}
        assert document["elements"][0]["settings"]["title"] == "Hello"

    def test_non_dict_entries_are_skipped(self):
        assert components_to_document(["junk", None])["elements"] == []


class TestGutenbergComponentPath:
    """The converter's legacy component path now routes through the mirror."""

    def convert(self, data):
        return GutenbergConverter().convert(data)

    def test_button_component_renders_link(self):
        output = self.convert(widget("button", {"label": "Press", "url": "https://x.test"}))
        assert "wp:buttons" in output
        assert 'href="https://x.test"' in output
        assert ">Press</a>" in output

    def test_heading_component_renders_title_and_level(self):
        output = self.convert(widget("heading", {"level": 3}, "Section Title"))
        assert '<h3 class="wp-block-heading">Section Title</h3>' in output

    def test_image_component_renders_src_and_alt(self):
        output = self.convert(widget("image", {"image_url": "https://x.test/a.png", "alt_text": "A"}))
        assert 'src="https://x.test/a.png"' in output
        assert 'alt="A"' in output

    def test_row_of_columns_renders_columns_block(self):
        tree = widget(
            "row",
            children=[
                widget("column", children=[widget("text", {}, "Left")]),
                widget("column", children=[widget("text", {}, "Right")]),
            ],
        )
        output = self.convert(tree)
        assert "wp:columns" in output
        assert output.count("<!-- wp:column ") >= 2
        assert "Left" in output and "Right" in output

    def test_gallery_component_renders_images(self):
        output = self.convert(
            widget("gallery", {"images": [{"url": "https://x.test/1.png", "alt": "one"}]})
        )
        assert "wp:gallery" in output
        assert 'src="https://x.test/1.png"' in output

    def test_list_component_renders_items(self):
        output = self.convert(widget("list", {"items": [{"text": "Alpha"}, {"text": "Beta"}]}))
        assert "wp:list-item" in output
        assert "<li>Alpha</li>" in output and "<li>Beta</li>" in output

    def test_alert_component_keeps_title_and_body(self):
        output = self.convert(widget("alert", {"heading": "Note"}, "Read this."))
        assert "Note" in output and "Read this." in output

    def test_testimonial_component_renders_quote_with_cite(self):
        output = self.convert(
            widget("testimonial", {"author": "Maya Chen", "job_title": "CTO"}, "Great tool.")
        )
        assert "wp:quote" in output
        assert "<cite>Maya Chen, CTO</cite>" in output

    def test_unknown_component_with_children_stays_grouped(self):
        tree = widget("unknown", children=[widget("text", {}, "Inside")])
        output = self.convert(tree)
        assert "wp:group" in output
        assert "Inside" in output

    def test_unknown_leaf_content_is_preserved_as_html(self):
        output = self.convert(widget("weird-thing", {}, "<em>kept</em>"))
        assert "wp:html" in output
        assert "<em>kept</em>" in output

    def test_widget_shaped_dict_without_eltype_dispatches_directly(self):
        output = self.convert(
            {"widgetType": "heading", "settings": {"title": "Direct", "header_size": "h2"}}
        )
        assert '<h2 class="wp-block-heading">Direct</h2>' in output

    def test_nav_widget_type_from_shared_vocabulary(self):
        output = self.convert(widget("nav"))
        assert "wp:navigation" in output
        assert "devtb-unconverted" not in output
