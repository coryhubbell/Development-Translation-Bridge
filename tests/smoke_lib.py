"""
Shared helpers for the e2e fidelity smoke gates.

Extracted from smoke_gutenberg_e2e.py (the v4.3.4 gate) so every gate uses
the same block-markup analysis and PHP-harness plumbing.
"""

from __future__ import annotations

import re
import subprocess
from pathlib import Path
from typing import List

REPO_ROOT = Path(__file__).resolve().parent.parent


# --- Gutenberg block-markup analysis ---------------------------------------

def strip_block_attrs(blocks: str) -> str:
    """Strip JSON attribute payloads from block comments so a delimiter-only
    stack analysis can ignore nested-brace content."""
    out: List[str] = []
    i = 0
    n = len(blocks)
    while i < n:
        ch = blocks[i]
        if ch == "{" and i > 0 and blocks[i - 1] == " " and out and out[-1].endswith(" "):
            depth = 1
            j = i + 1
            while j < n and depth > 0:
                if blocks[j] == "{":
                    depth += 1
                elif blocks[j] == "}":
                    depth -= 1
                j += 1
            if out and out[-1].endswith(" "):
                out[-1] = out[-1][:-1]
            i = j
            continue
        out.append(ch)
        i += 1
    return "".join(out)


def find_unbalanced_delimiters(blocks: str) -> List[str]:
    """Walk block markup and return any unbalanced opening/closing comments."""
    stripped = strip_block_attrs(blocks)
    token_re = re.compile(r"<!-- (/?)wp:([a-z0-9/-]+)( /)? -->")
    stack: List[str] = []
    problems: List[str] = []
    for m in token_re.finditer(stripped):
        is_closing = bool(m.group(1))
        name = m.group(2)
        is_self_closing = bool(m.group(3))
        if is_self_closing:
            continue
        if is_closing:
            if not stack:
                problems.append(f"closing </wp:{name}> with no opener")
            elif stack[-1] != name:
                problems.append(f"closing </wp:{name}> but top of stack is <wp:{stack[-1]}>")
                stack.pop()
            else:
                stack.pop()
        else:
            stack.append(name)
    for unclosed in stack:
        problems.append(f"unclosed <wp:{unclosed}>")
    return problems


def find_empty_paragraphs(blocks: str) -> int:
    """Count paragraph blocks whose body is literally an empty <p></p>."""
    pattern = re.compile(
        r"<!-- wp:paragraph(?: \{[^}]*\})? -->\s*<p>\s*</p>\s*<!-- /wp:paragraph -->"
    )
    return len(pattern.findall(blocks))


def strip_php_banner(output: str) -> str:
    """Drop the test-bootstrap banner lines PHP harnesses print before their
    real payload (everything before the first line starting with JSON or
    block-markup content)."""
    lines = output.splitlines()
    for i, line in enumerate(lines):
        stripped = line.lstrip()
        if stripped.startswith(("[", "{", "<!--")):
            return "\n".join(lines[i:])
    return output


# --- PHP harness plumbing ---------------------------------------------------

def run_php_harness(php_source: str, harness_name: str) -> str:
    """Write a throwaway PHP harness under tests/, run it, return stdout."""
    harness_path = REPO_ROOT / "tests" / harness_name
    harness_path.write_text(php_source)
    try:
        result = subprocess.run(
            ["php", str(harness_path)],
            capture_output=True,
            text=True,
            cwd=REPO_ROOT,
            timeout=30,
        )
        if result.returncode != 0:
            raise RuntimeError(f"PHP harness failed: {result.stderr}")
        return result.stdout
    finally:
        if harness_path.exists():
            harness_path.unlink()


PHP_PROLOGUE = r"""<?php
define('DEVTB_TESTING', true);
define('DEVTB_ROOT', '{root}');
define('DEVTB_TRANSLATION_BRIDGE', DEVTB_ROOT . '/translation-bridge');

require DEVTB_ROOT . '/vendor/autoload.php';
require DEVTB_ROOT . '/tests/bootstrap.php';
"""


def php_prologue() -> str:
    return PHP_PROLOGUE.format(root=str(REPO_ROOT))
