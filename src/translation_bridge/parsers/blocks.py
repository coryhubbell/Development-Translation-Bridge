"""
Shared WordPress block-comment tokenizer for block-markup source parsers
(Gutenberg, DIVI 5, Kadence, ...).

Produces the same block dict shape WordPress core's ``parse_blocks()``
returns: ``{"blockName", "attrs", "innerBlocks", "innerHTML"}``, with
nesting resolved via a delimiter stack.
"""

from __future__ import annotations

import json
import re
from typing import Any, Dict, List

_TOKEN_RE = re.compile(
    r"<!--\s+(?P<closer>/)?wp:(?P<name>[a-z0-9/_-]+)"
    r"(?:\s+(?P<attrs>\{.*?\}))?\s+(?P<void>/)?-->",
    re.S,
)


def parse_block_markup(content: str) -> List[Dict[str, Any]]:
    """Tokenize block markup into a nested block tree."""
    root: Dict[str, Any] = {"blockName": None, "attrs": {}, "innerBlocks": [], "innerHTML": ""}
    stack: List[Dict[str, Any]] = [root]
    cursor = 0

    for match in _TOKEN_RE.finditer(content):
        # Text between tokens belongs to the currently open block.
        text = content[cursor : match.start()]
        if text.strip():
            stack[-1]["innerHTML"] += text
        cursor = match.end()

        name = match.group("name")
        if match.group("closer"):
            # Closing delimiter: pop back to the matching opener.
            for i in range(len(stack) - 1, 0, -1):
                if stack[i]["blockName"] == name:
                    del stack[i:]
                    break
            continue

        attrs: Dict[str, Any] = {}
        raw_attrs = match.group("attrs")
        if raw_attrs:
            try:
                decoded = json.loads(raw_attrs)
                if isinstance(decoded, dict):
                    attrs = decoded
            except json.JSONDecodeError:
                pass

        block = {"blockName": name, "attrs": attrs, "innerBlocks": [], "innerHTML": ""}
        stack[-1]["innerBlocks"].append(block)
        if not match.group("void"):
            stack.append(block)

    trailing = content[cursor:]
    if trailing.strip():
        stack[-1]["innerHTML"] += trailing

    for block in _walk(root):
        block["innerHTML"] = block["innerHTML"].strip()

    return root["innerBlocks"]


def _walk(block: Dict[str, Any]):
    yield block
    for child in block["innerBlocks"]:
        yield from _walk(child)


_TAG_RE = re.compile(r"<[^>]+>")


def strip_tags(html: str) -> str:
    """Plain-text extraction from an innerHTML fragment."""
    return _TAG_RE.sub("", html).strip()
