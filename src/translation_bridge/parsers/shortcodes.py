"""
Shared WordPress shortcode tokenizer for shortcode-based source parsers
(DIVI 4, WPBakery, Avada).

Produces nested shortcode dicts: ``{"tag", "attrs", "children",
"content"}``. Nesting is resolved with a delimiter stack; text between an
opening tag and its first child (or closing tag) becomes ``content``.
Self-closing usage (``[tag /]`` or an opener with no matching closer at its
level) yields a leaf.
"""

from __future__ import annotations

import re
from typing import Any, Dict, List

_TOKEN_RE = re.compile(
    r"\[(?P<closer>/)?(?P<tag>[a-zA-Z0-9_]+)(?P<attrs>[^\]]*?)(?P<void>/)?\]"
)

_ATTR_RE = re.compile(r'([a-zA-Z0-9_-]+)\s*=\s*(?P<q>["\'])(?P<val>.*?)(?P=q)', re.S)


def parse_attrs(raw: str) -> Dict[str, str]:
    """Parse shortcode attribute strings into a dict."""
    return {m.group(1): m.group("val") for m in _ATTR_RE.finditer(raw)}


def parse_shortcodes(content: str, tag_prefixes: tuple) -> List[Dict[str, Any]]:
    """Tokenize shortcode markup into a nested tree.

    Only tags starting with one of ``tag_prefixes`` participate; everything
    else is treated as literal text/content.
    """
    root: Dict[str, Any] = {"tag": None, "attrs": {}, "children": [], "content": ""}
    stack: List[Dict[str, Any]] = [root]
    cursor = 0

    for match in _TOKEN_RE.finditer(content):
        tag = match.group("tag")
        if not tag.startswith(tag_prefixes):
            continue

        text = content[cursor : match.start()]
        if text.strip():
            stack[-1]["content"] += text
        cursor = match.end()

        if match.group("closer"):
            for i in range(len(stack) - 1, 0, -1):
                if stack[i]["tag"] == tag:
                    del stack[i:]
                    break
            continue

        node = {
            "tag": tag,
            "attrs": parse_attrs(match.group("attrs") or ""),
            "children": [],
            "content": "",
        }
        stack[-1]["children"].append(node)
        # Self-closing usage: explicit `/]`, or no matching closer anywhere
        # ahead (WPBakery/DIVI leaves often omit closers).
        if not match.group("void") and f"[/{tag}]" in content[match.end():]:
            stack.append(node)

    trailing = content[cursor:]
    if trailing.strip():
        stack[-1]["content"] += trailing

    def clean(node: Dict[str, Any]) -> None:
        node["content"] = node["content"].strip()
        for child in node["children"]:
            clean(child)

    clean(root)
    return root["children"]
