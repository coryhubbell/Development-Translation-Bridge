"""
Shared HTML foundation for HTML-based source parsers (Bootstrap, Thrive).

Builds a DOM-lite tree with the stdlib ``html.parser`` and walks it into
universal elements using a small, overridable rule set:

- ``h1``–``h6`` → heading widgets
- ``p`` → text-editor widgets
- ``img`` → image widgets
- ``a`` with button-ish classes → button widgets
- ``ul``/``ol`` → icon-list widgets
- ``blockquote`` → testimonial widgets (cite → name)
- ``pre``/``code`` → html widgets
- structural tags (``section``/``div``/etc.) with children → containers

Framework-specific parsers supply class hints (e.g. Bootstrap's ``btn``,
Thrive's ``tve_*``) and metadata labels.
"""

from __future__ import annotations

from html.parser import HTMLParser
from typing import Any, Dict, List, Optional

from translation_bridge.parsers.universal import UniversalDocument, UniversalElement

_VOID_TAGS = {"img", "br", "hr", "input", "meta", "link", "source"}
_STRUCTURAL_TAGS = {"section", "div", "article", "main", "header", "footer", "aside", "figure"}
_SKIP_TAGS = {"script", "style", "head", "title"}


class _Node:
    __slots__ = ("tag", "attrs", "children", "text")

    def __init__(self, tag: str, attrs: Dict[str, str]):
        self.tag = tag
        self.attrs = attrs
        self.children: List["_Node"] = []
        self.text = ""


class _TreeBuilder(HTMLParser):
    def __init__(self) -> None:
        super().__init__(convert_charrefs=True)
        self.root = _Node("__root__", {})
        self.stack = [self.root]

    def handle_starttag(self, tag, attrs):
        node = _Node(tag, dict(attrs))
        self.stack[-1].children.append(node)
        if tag not in _VOID_TAGS:
            self.stack.append(node)

    def handle_startendtag(self, tag, attrs):
        self.stack[-1].children.append(_Node(tag, dict(attrs)))

    def handle_endtag(self, tag):
        for i in range(len(self.stack) - 1, 0, -1):
            if self.stack[i].tag == tag:
                del self.stack[i:]
                break

    def handle_data(self, data):
        self.stack[-1].text += data


def _node_text(node: _Node) -> str:
    parts = [node.text]
    for child in node.children:
        parts.append(_node_text(child))
    return "".join(parts).strip()


def _classes(node: _Node) -> List[str]:
    return (node.attrs.get("class") or "").split()


class HTMLSourceParser:
    """Generic HTML → universal parser; subclass for framework hints."""

    framework = "html"
    #: substrings of class names that mark an <a>/<button> as a button.
    button_class_hints = ("btn", "button")
    #: substrings of class names that mark a div as a card/icon-box.
    card_class_hints = ("card",)

    def parse_file(self, file_path: str) -> UniversalDocument:
        with open(file_path, "r", encoding="utf-8") as handle:
            return self.parse(handle.read())

    def parse(self, content: str) -> UniversalDocument:
        if not isinstance(content, str) or not content.strip():
            return UniversalDocument()
        builder = _TreeBuilder()
        builder.feed(content)
        roots: List[UniversalElement] = []
        for child in builder.root.children:
            element = self._walk(child, is_inner=False)
            if element:
                roots.append(element)
        return UniversalDocument(elements=roots, meta={"source_framework": self.framework})

    # ------------------------------------------------------------------

    def _walk(self, node: _Node, is_inner: bool) -> Optional[UniversalElement]:
        tag = node.tag
        if tag in _SKIP_TAGS:
            return None

        widget = self._leaf_widget(node)
        if widget is not None:
            widget.is_inner = is_inner
            return widget

        if tag in _STRUCTURAL_TAGS or tag in ("body", "html"):
            children = [
                el
                for child in node.children
                if (el := self._walk(child, True))
            ]
            direct_text = node.text.strip()
            if not children and not direct_text:
                return None
            if not children and direct_text:
                return UniversalElement(
                    id="", el_type="widget", widget_type="text-editor",
                    settings={"editor": direct_text}, is_inner=is_inner,
                )
            element = UniversalElement(
                id="",
                el_type="section" if tag == "section" and not is_inner else "container",
                settings=self._container_settings(node),
                is_inner=is_inner,
                elements=children,
            )
            return element

        # Inline/unknown tags: surface text as a paragraph if substantial.
        text = _node_text(node)
        if text:
            return UniversalElement(
                id="", el_type="widget", widget_type="text-editor",
                settings={"editor": text}, is_inner=is_inner,
            )
        return None

    def _container_settings(self, node: _Node) -> Dict[str, Any]:
        return {}

    def _leaf_widget(self, node: _Node) -> Optional[UniversalElement]:
        tag = node.tag
        classes = _classes(node)

        if tag in ("h1", "h2", "h3", "h4", "h5", "h6"):
            return UniversalElement(
                id="", el_type="widget", widget_type="heading",
                settings={"title": _node_text(node), "header_size": tag},
            )
        if tag == "p":
            text = _node_text(node)
            if not text:
                return None
            return UniversalElement(
                id="", el_type="widget", widget_type="text-editor",
                settings={"editor": text},
            )
        if tag == "img":
            settings: Dict[str, Any] = {"image": {"url": node.attrs.get("src", "")}}
            if node.attrs.get("alt"):
                settings["image"]["alt"] = node.attrs["alt"]
            return UniversalElement(id="", el_type="widget", widget_type="image", settings=settings)
        if tag in ("a", "button") and (
            tag == "button" or any(h in c for c in classes for h in self.button_class_hints)
        ):
            settings = {"text": _node_text(node)}
            if node.attrs.get("href"):
                settings["link"] = {"url": node.attrs["href"]}
                if node.attrs.get("target") == "_blank":
                    settings["link"]["is_external"] = "on"
            return UniversalElement(id="", el_type="widget", widget_type="button", settings=settings)
        if tag in ("ul", "ol"):
            items = [
                {"text": _node_text(child)}
                for child in node.children
                if child.tag == "li" and _node_text(child)
            ]
            if not items:
                return None
            return UniversalElement(
                id="", el_type="widget", widget_type="icon-list",
                settings={"icon_list": items},
            )
        if tag == "blockquote":
            cite = ""
            for child in node.children:
                if child.tag == "cite":
                    cite = _node_text(child)
            body = _node_text(node).replace(cite, "").strip()
            settings = {"testimonial_content": body}
            if cite:
                settings["testimonial_name"] = cite
            return UniversalElement(
                id="", el_type="widget", widget_type="testimonial", settings=settings
            )
        if tag == "pre" or (tag == "code" and _node_text(node)):
            return UniversalElement(
                id="", el_type="widget", widget_type="html",
                settings={"html": _node_text(node)},
            )
        if tag == "hr":
            return UniversalElement(id="", el_type="widget", widget_type="divider", settings={})
        return None
