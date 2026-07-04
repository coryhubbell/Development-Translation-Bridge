#!/usr/bin/env python3
"""
Translation Bridge v4 CLI - JSON-native transform commands.

Commands:
    transform       Transform a single file between frameworks
    transform-site  Transform all files in a directory or site export
    analyze         Analyze page builder content
    analyze-site    Analyze a complete site export

Usage:
    devtb transform <source> <target> <file> [options]
    devtb transform-site <source> <target> <directory> [options]
    devtb analyze <framework> <file> [options]
    devtb analyze-site <framework> <path> [options]
"""

import argparse
import json
import re
import sys
from pathlib import Path
from typing import Any, Dict, List, Optional

from . import __version__
from .transforms.core import TransformEngine, ZoneType
from .transforms.registry import TransformRegistry, ParserRegistry
from .parsers.elementor import ElementorParser
from .parsers.elementor_site import ElementorSiteParser, ElementorSite


# ANSI color codes
class Colors:
    HEADER = "\033[95m"
    BLUE = "\033[94m"
    CYAN = "\033[96m"
    GREEN = "\033[92m"
    WARNING = "\033[93m"
    FAIL = "\033[91m"
    ENDC = "\033[0m"
    BOLD = "\033[1m"
    UNDERLINE = "\033[4m"


def print_header(text: str) -> None:
    """Print a header line."""
    print(f"\n{Colors.CYAN}{Colors.BOLD}{'=' * 60}{Colors.ENDC}")
    print(f"{Colors.CYAN}{Colors.BOLD}  {text}{Colors.ENDC}")
    print(f"{Colors.CYAN}{Colors.BOLD}{'=' * 60}{Colors.ENDC}\n")


def print_success(text: str) -> None:
    """Print a success message."""
    print(f"{Colors.GREEN}✓ {text}{Colors.ENDC}")


def print_error(text: str) -> None:
    """Print an error message."""
    print(f"{Colors.FAIL}✗ {text}{Colors.ENDC}", file=sys.stderr)


def print_info(text: str) -> None:
    """Print an info message."""
    print(f"{Colors.BLUE}ℹ {text}{Colors.ENDC}")


def print_warning(text: str) -> None:
    """Print a warning message."""
    print(f"{Colors.WARNING}⚠ {text}{Colors.ENDC}")


def get_parser_for_framework(framework: str) -> Optional[Any]:
    """Get the appropriate parser for a framework."""
    framework_lower = framework.lower()
    if framework_lower == "elementor":
        return ElementorParser()
    if framework_lower in ("elementor4", "elementor-4", "elementor-atomic"):
        from .parsers.elementor4 import Elementor4Parser

        return Elementor4Parser()
    if framework_lower == "bricks":
        from .parsers.bricks import BricksParser

        return BricksParser()
    if framework_lower == "oxygen":
        from .parsers.oxygen import OxygenParser

        return OxygenParser()
    if framework_lower in ("oxygen6", "oxygen-6"):
        from .parsers.oxygen6 import Oxygen6Parser

        return Oxygen6Parser()
    if framework_lower in ("divi5", "divi-5"):
        from .parsers.divi5 import Divi5Parser

        return Divi5Parser()
    if framework_lower == "gutenberg":
        from .parsers.gutenberg import GutenbergParser

        return GutenbergParser()
    if framework_lower == "divi":
        from .parsers.divi import DiviParser

        return DiviParser()
    if framework_lower == "wpbakery":
        from .parsers.wpbakery import WPBakeryParser

        return WPBakeryParser()
    if framework_lower == "avada":
        from .parsers.avada import AvadaParser

        return AvadaParser()
    if framework_lower == "kadence":
        from .parsers.kadence import KadenceParser

        return KadenceParser()
    if framework_lower in ("beaver-builder", "beaver"):
        from .parsers.beaver import BeaverParser

        return BeaverParser()
    if framework_lower == "thrive":
        from .parsers.thrive import ThriveParser

        return ThriveParser()
    if framework_lower == "bootstrap":
        from .parsers.bootstrap import BootstrapParser

        return BootstrapParser()
    return None


def get_converter_for_framework(framework: str) -> Optional[Any]:
    """Get the appropriate converter for a target framework.

    Backs the universal route (RFC 5.0 Phase 3): any parsed document can
    convert to any framework without a registered pair.
    """
    framework_lower = framework.lower()
    converters = {
        "elementor": ("elementor", "ElementorConverter"),
        "elementor4": ("elementor4", "Elementor4Converter"),
        "elementor-4": ("elementor4", "Elementor4Converter"),
        "elementor-atomic": ("elementor4", "Elementor4Converter"),
        "bootstrap": ("bootstrap", "BootstrapConverter"),
        "gutenberg": ("gutenberg", "GutenbergConverter"),
        "bricks": ("bricks", "BricksConverter"),
        "oxygen": ("oxygen", "OxygenConverter"),
        "oxygen6": ("oxygen6", "Oxygen6Converter"),
        "oxygen-6": ("oxygen6", "Oxygen6Converter"),
        "divi": ("divi", "DiviConverter"),
        "divi5": ("divi5", "Divi5Converter"),
        "divi-5": ("divi5", "Divi5Converter"),
        "wpbakery": ("wpbakery", "WPBakeryConverter"),
        "avada": ("avada", "AvadaConverter"),
        "kadence": ("kadence", "KadenceConverter"),
        "beaver-builder": ("beaver", "BeaverConverter"),
        "beaver": ("beaver", "BeaverConverter"),
        "thrive": ("thrive", "ThriveConverter"),
    }
    entry = converters.get(framework_lower)
    if entry is None:
        return None
    module_name, class_name = entry
    import importlib

    module = importlib.import_module(f".converters.{module_name}", package=__package__)
    return getattr(module, class_name)()


# Setting keys that carry user-visible content (matches the convention in
# transforms/core.py). Style/layout enums are excluded from fidelity, and
# the exclusion list wins so keys like title_color never count as content.
_CONTENT_KEY_PARTS = (
    "text", "title", "content", "description", "heading", "editor",
    "caption", "label", "alt", "html", "name", "job", "address", "url", "date",
)
_NON_CONTENT_KEY_PARTS = (
    "color", "size", "typography", "weight", "align", "style", "position",
    "gap", "width", "height", "radius", "spacing", "margin", "padding",
    "font", "shadow", "border", "opacity", "hover",
)


def _is_content_key(key: str) -> bool:
    key_lower = key.lower()
    if any(part in key_lower for part in _NON_CONTENT_KEY_PARTS):
        return False
    return any(part in key_lower for part in _CONTENT_KEY_PARTS)


def collect_content_strings(element: Any, out: list) -> None:
    """Collect trimmed content-bearing text values from a universal element tree."""
    if not isinstance(element, dict):
        return
    for key, value in element.get("settings", {}).items():
        if isinstance(value, str) and value.strip() and _is_content_key(key):
            out.append(value.strip())
        elif isinstance(value, dict):
            for sub_key, sub in value.items():
                if isinstance(sub, str) and sub.strip() and _is_content_key(sub_key):
                    out.append(sub.strip())
        elif isinstance(value, list):
            for item in value:
                if isinstance(item, dict):
                    for sub_key, sub in item.items():
                        if isinstance(sub, str) and sub.strip() and _is_content_key(sub_key):
                            out.append(sub.strip())
    for child in element.get("elements", []) or []:
        collect_content_strings(child, out)


def _string_scalars(value: Any, out: list) -> None:
    """Flatten every string scalar in a nested structure (JSON-safe haystack)."""
    if isinstance(value, str):
        out.append(value)
    elif isinstance(value, dict):
        for item in value.values():
            _string_scalars(item, out)
    elif isinstance(value, (list, tuple)):
        for item in value:
            _string_scalars(item, out)


def measure_fidelity(document: Any, output: Any) -> dict:
    """Measure content fidelity of a conversion (RFC 5.0 Phase 3).

    Advisory per-conversion metric: how many of the parsed document's
    content strings appear in the converted output. Structured outputs
    compare against their string scalars directly so JSON escaping never
    reads as content loss.
    """
    needles: list = []
    if isinstance(document, dict):
        for element in document.get("elements", []) or []:
            collect_content_strings(element, needles)
    total = len(needles)
    if total == 0:
        return {"content_total": 0, "content_preserved": 0, "ratio": 1.0}

    if isinstance(output, str):
        raw = output
        # JSON-shaped string outputs (Elementor, Bricks, ...) compare via
        # their decoded string scalars so JSON escaping never reads as loss.
        stripped = output.lstrip()
        if stripped.startswith(("{", "[")):
            try:
                decoded = json.loads(output)
            except (ValueError, TypeError):
                decoded = None
            if isinstance(decoded, (dict, list)):
                parts: list = []
                _string_scalars(decoded, parts)
                raw = "\n".join(parts)
    else:
        parts = []
        _string_scalars(output, parts)
        raw = "\n".join(parts)
    haystack = re.sub(r"<[^>]+>", " ", raw)

    preserved = sum(1 for needle in needles if needle in raw or needle in haystack)
    return {
        "content_total": total,
        "content_preserved": preserved,
        "ratio": round(preserved / total, 4),
    }


def print_fidelity(fidelity: dict) -> None:
    """Print the per-conversion fidelity metric."""
    if fidelity["content_total"] == 0:
        return
    line = (
        f"Fidelity: {fidelity['content_preserved']}/{fidelity['content_total']} "
        f"content strings preserved ({fidelity['ratio'] * 100:.1f}%)"
    )
    if fidelity["ratio"] >= 1.0:
        print_success(line)
    else:
        print_warning(line)


def cmd_transform(args: argparse.Namespace) -> int:
    """Handle the transform command (and its deprecated 'translate' alias)."""
    source = args.source.lower()
    target = args.target.lower()
    input_file = Path(args.file)

    if getattr(args, "command", "") == "translate":
        print_warning("'translate' is deprecated and will be removed in 5.1 — use 'devtb transform'.")
        print_info("Routing through the lossless universal path (RFC 5.0 Phase 3).")

    if not input_file.exists():
        print_error(f"File not found: {input_file}")
        return 1

    print_header(f"Translation Bridge v{__version__} - Transform")
    print_info(f"Source: {source}")
    print_info(f"Target: {target}")
    print_info(f"Input: {input_file}")

    # Get parser for source framework
    parser = get_parser_for_framework(source)
    if parser is None:
        print_error(f"No parser available for framework: {source}")
        print_info("Supported frameworks: elementor")
        return 1

    try:
        # Parse the input file
        if hasattr(parser, "parse_file"):
            doc = parser.parse_file(str(input_file))
        else:
            with open(input_file, "r", encoding="utf-8") as f:
                data = json.load(f)
            doc = parser.parse(data)

        # Initialize transform engine
        engine = TransformEngine()
        doc_dict = doc.to_dict() if hasattr(doc, "to_dict") else doc

        # Get transform function
        transform_fn = TransformRegistry.get_transform(source, target)

        if transform_fn:
            # Use registered transform
            result_data = transform_fn(doc_dict)
        else:
            # Universal route (RFC 5.0 Phase 3): every parsed document is a
            # universal document, so any target converter can consume it.
            # Content extraction remains only as the fidelity-gated fallback.
            result_data = None
            converter = get_converter_for_framework(target)
            if converter is not None:
                print_info(f"No registered pair for {source} → {target}; using the universal route")
                candidate = converter.convert(doc_dict)
                fidelity = measure_fidelity(doc_dict, candidate)
                if fidelity["content_total"] == 0 or fidelity["ratio"] >= 0.5:
                    result_data = candidate
                else:
                    print_warning(
                        f"Universal route preserved only {fidelity['ratio'] * 100:.0f}% of content "
                        f"for {source} → {target}; falling back to content extraction."
                    )
                    print_info("The PHP engine ('devtb translate') supports this pair natively.")
            if result_data is None:
                # Last resort: extract content zones (HTML content extraction).
                if converter is None:
                    print_warning(f"No converter for {target}, extracting content")
                if hasattr(parser, "extract_content"):
                    result_data = parser.extract_content(doc)
                else:
                    result_data = engine.extract_content(doc_dict)

        # Determine output file
        if args.output:
            output_file = Path(args.output)
        else:
            output_file = input_file.parent / f"{input_file.stem}-{target}{get_extension_for_framework(target)}"

        # Determine if output is HTML string or JSON data
        is_html_output = isinstance(result_data, str)

        # Write output
        if args.dry_run:
            print_info("Dry run - would write to: " + str(output_file))
            if is_html_output:
                # Show first 2000 chars of HTML
                preview = result_data[:2000]
                if len(result_data) > 2000:
                    preview += "\n..."
                print(f"\n{preview}")
            else:
                print("\n" + json.dumps(result_data, indent=2, ensure_ascii=False)[:2000] + "...")
        else:
            with open(output_file, "w", encoding="utf-8") as f:
                if is_html_output:
                    f.write(result_data)
                else:
                    json.dump(result_data, f, indent=2, ensure_ascii=False)
            print_success(f"Output written to: {output_file}")

        # Print statistics
        if hasattr(parser, "analyze"):
            stats = parser.analyze(doc)
            print_info(f"Elements processed: {stats.get('total_elements', 'N/A')}")
            print_info(f"Content items: {stats.get('content_items', 'N/A')}")

        # Per-conversion fidelity metric (RFC 5.0 Phase 3).
        print_fidelity(measure_fidelity(doc_dict, result_data))

        print_success("Transformation complete!")

        return 0

    except json.JSONDecodeError as e:
        print_error(f"Invalid JSON in input file: {e}")
        return 1
    except Exception as e:
        print_error(f"Transform failed: {e}")
        if args.debug:
            import traceback
            traceback.print_exc()
        return 1


def cmd_transform_site(args: argparse.Namespace) -> int:
    """Handle the transform-site command."""
    source = args.source.lower()
    target = args.target.lower()
    input_path = Path(args.directory)

    if not input_path.exists():
        print_error(f"Path not found: {input_path}")
        return 1

    print_header(f"Translation Bridge v{__version__} - Transform Site")
    print_info(f"Source: {source}")
    print_info(f"Target: {target}")
    print_info(f"Input: {input_path}")

    # Detect export type
    is_zip = input_path.suffix.lower() == ".zip"
    is_export_kit = _is_export_kit(input_path) if input_path.is_dir() else False

    # Create output directory
    output_dir = Path(args.output_dir) if args.output_dir else input_path.parent / f"converted-{target}"

    if is_zip or is_export_kit:
        # Handle full site export
        return _transform_site_export(
            source, target, input_path, output_dir,
            dry_run=args.dry_run, debug=args.debug
        )
    else:
        # Handle simple directory with JSON files (legacy mode)
        return _transform_site_directory(
            source, target, input_path, output_dir,
            dry_run=args.dry_run, debug=args.debug
        )


def _is_export_kit(dir_path: Path) -> bool:
    """Check if directory is an Elementor Export Kit structure."""
    # Look for characteristic files
    markers = [
        "content.json",
        "site-settings.json",
        "settings.json",
        "manifest.json",
    ]
    for marker in markers:
        if (dir_path / marker).exists():
            return True

    # Check for templates directory
    if (dir_path / "templates").exists():
        return True

    return False


def _transform_site_export(
    source: str,
    target: str,
    input_path: Path,
    output_dir: Path,
    dry_run: bool = False,
    debug: bool = False
) -> int:
    """Transform a complete site export (zip or directory)."""
    print_info("Detected site export format")

    # Parse the site export
    site_parser = ElementorSiteParser()

    try:
        if input_path.suffix.lower() == ".zip":
            print_info("Extracting zip archive...")
            site = site_parser.parse_zip(str(input_path))
        else:
            site = site_parser.parse_directory(str(input_path))

        # Analyze the site
        stats = site_parser.analyze(site)
        print_info(f"Pages: {stats['total_pages']}")
        print_info(f"Templates: {stats['total_templates']}")
        print_info(f"Global colors: {stats['global_colors']}")
        print_info(f"Global fonts: {stats['global_fonts']}")

        if dry_run:
            print_warning("Dry run - no files will be written")
            _preview_site_conversion(site, target)
            return 0

        # Create output structure
        output_dir.mkdir(parents=True, exist_ok=True)
        (output_dir / "_includes").mkdir(exist_ok=True)
        (output_dir / "assets").mkdir(exist_ok=True)

        # Generate global styles
        styles_css = _generate_global_styles(site)
        styles_file = output_dir / "assets" / "styles.css"
        with open(styles_file, "w", encoding="utf-8") as f:
            f.write(styles_css)
        print_success(f"Generated: assets/styles.css")

        # Get the converter
        transform_fn = TransformRegistry.get_transform(source, target)
        if not transform_fn:
            print_error(f"No transform available for {source} → {target}")
            return 1

        # Convert templates (header/footer)
        header = site.get_header()
        if header:
            header_html = transform_fn(header.document.to_dict())
            if isinstance(header_html, str):
                header_file = output_dir / "_includes" / "header.html"
                with open(header_file, "w", encoding="utf-8") as f:
                    f.write(_extract_body_content(header_html))
                print_success(f"Generated: _includes/header.html")

        footer = site.get_footer()
        if footer:
            footer_html = transform_fn(footer.document.to_dict())
            if isinstance(footer_html, str):
                footer_file = output_dir / "_includes" / "footer.html"
                with open(footer_file, "w", encoding="utf-8") as f:
                    f.write(_extract_body_content(footer_html))
                print_success(f"Generated: _includes/footer.html")

        # Convert pages
        success_count = 0
        error_count = 0
        link_map = _build_link_map(site)

        for page in site.pages:
            if page.status != "publish":
                continue

            try:
                page_html = transform_fn(page.document.to_dict())
                if isinstance(page_html, str):
                    # Rewrite internal links
                    page_html = _rewrite_links(page_html, link_map)

                    # Wrap with template includes
                    page_html = _wrap_with_template(page_html, page.title, site)

                    # Determine output filename
                    if page.slug in ["home", "index", "front-page"]:
                        filename = "index.html"
                    else:
                        filename = f"{page.slug}.html"

                    page_file = output_dir / filename
                    with open(page_file, "w", encoding="utf-8") as f:
                        f.write(page_html)
                    print_success(f"Generated: {filename}")
                    success_count += 1
            except Exception as e:
                print_error(f"Failed to convert page '{page.title}': {e}")
                if debug:
                    import traceback
                    traceback.print_exc()
                error_count += 1

        print_header("Site Conversion Complete")
        print_success(f"Pages converted: {success_count}")
        if error_count > 0:
            print_error(f"Pages failed: {error_count}")
        print_info(f"Output directory: {output_dir}")

        return 0 if error_count == 0 else 1

    except Exception as e:
        print_error(f"Site conversion failed: {e}")
        if debug:
            import traceback
            traceback.print_exc()
        return 1
    finally:
        site_parser.cleanup()


def _transform_site_directory(
    source: str,
    target: str,
    input_dir: Path,
    output_dir: Path,
    dry_run: bool = False,
    debug: bool = False
) -> int:
    """Transform a directory of JSON files (legacy mode)."""
    print_info("Processing directory of JSON files")

    # Find all JSON files
    json_files = list(input_dir.glob("**/*.json"))

    if not json_files:
        print_warning("No JSON files found in directory")
        return 0

    print_info(f"Found {len(json_files)} JSON files")

    if not dry_run:
        output_dir.mkdir(parents=True, exist_ok=True)

    success_count = 0
    error_count = 0

    for json_file in json_files:
        relative_path = json_file.relative_to(input_dir)
        print_info(f"Processing: {relative_path}")

        # Create mock args for single file transform
        class FileArgs:
            pass

        file_args = FileArgs()
        file_args.source = source
        file_args.target = target
        file_args.file = str(json_file)
        file_args.output = str(output_dir / relative_path.with_suffix(get_extension_for_framework(target)))
        file_args.dry_run = dry_run
        file_args.debug = debug

        if not dry_run:
            (output_dir / relative_path.parent).mkdir(parents=True, exist_ok=True)

        result = cmd_transform(file_args)
        if result == 0:
            success_count += 1
        else:
            error_count += 1

    print_header("Transform Site Complete")
    print_success(f"Successful: {success_count}")
    if error_count > 0:
        print_error(f"Failed: {error_count}")

    return 0 if error_count == 0 else 1


def _generate_global_styles(site: ElementorSite) -> str:
    """Generate global CSS from site settings."""
    lines = []

    # Add Google Fonts import
    fonts_import = site.settings.fonts.get_google_fonts_import()
    if fonts_import:
        lines.append(fonts_import)
        lines.append("")

    # Add CSS custom properties
    lines.append(site.settings.colors.to_css_variables())
    lines.append("")
    lines.append(site.settings.fonts.to_css_variables())
    lines.append("")

    # Add custom CSS from site settings
    if site.settings.custom_css:
        lines.append("/* Custom CSS from Elementor */")
        lines.append(site.settings.custom_css)

    return "\n".join(lines)


def _extract_body_content(html: str) -> str:
    """Extract content between <body> and </body> tags."""
    import re
    match = re.search(r"<body[^>]*>(.*?)</body>", html, re.DOTALL | re.IGNORECASE)
    if match:
        return match.group(1).strip()
    return html


def _build_link_map(site: ElementorSite) -> Dict[str, str]:
    """Build a mapping of internal links to converted filenames."""
    link_map = {}

    for page in site.pages:
        # Map various URL patterns to the new filename
        old_patterns = [
            f"/{page.slug}/",
            f"/{page.slug}",
            f"?p={page.id}",
            f"/{page.post_type}/{page.slug}/",
        ]

        if page.slug in ["home", "index", "front-page"]:
            new_url = "index.html"
        else:
            new_url = f"{page.slug}.html"

        for pattern in old_patterns:
            link_map[pattern] = new_url

    return link_map


def _rewrite_links(html: str, link_map: Dict[str, str]) -> str:
    """Rewrite internal links to use new filenames."""
    for old_url, new_url in link_map.items():
        html = html.replace(f'href="{old_url}"', f'href="{new_url}"')
        html = html.replace(f"href='{old_url}'", f"href='{new_url}'")
    return html


def _wrap_with_template(body_html: str, title: str, site: ElementorSite) -> str:
    """Wrap page content with full HTML template."""
    # Extract just the body content if it's a full HTML document
    body_content = _extract_body_content(body_html) if "<body" in body_html else body_html

    return f"""<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{title} - {site.settings.site_name}</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
  <!-- Include header -->
  <!-- #include file="_includes/header.html" -->

  <main>
{body_content}
  </main>

  <!-- Include footer -->
  <!-- #include file="_includes/footer.html" -->

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>"""


def _preview_site_conversion(site: ElementorSite, target: str) -> None:
    """Preview what would be generated in a site conversion."""
    print_info("\nSite conversion preview:")
    print_info(f"  Output structure for {target}:")
    print(f"    output/")
    print(f"    ├── index.html")
    for page in site.pages:
        if page.status == "publish" and page.slug not in ["home", "index", "front-page"]:
            print(f"    ├── {page.slug}.html")
    print(f"    ├── _includes/")
    if site.get_header():
        print(f"    │   ├── header.html")
    if site.get_footer():
        print(f"    │   └── footer.html")
    print(f"    └── assets/")
    print(f"        └── styles.css")


def cmd_analyze(args: argparse.Namespace) -> int:
    """Handle the analyze command."""
    framework = args.framework.lower()
    input_file = Path(args.file)

    if not input_file.exists():
        print_error(f"File not found: {input_file}")
        return 1

    print_header(f"Translation Bridge v{__version__} - Analyze")
    print_info(f"Framework: {framework}")
    print_info(f"Input: {input_file}")

    # Get parser for framework
    parser = get_parser_for_framework(framework)

    try:
        # Load and parse the file
        with open(input_file, "r", encoding="utf-8") as f:
            data = json.load(f)

        if parser:
            doc = parser.parse(data)
            if hasattr(parser, "analyze"):
                stats = parser.analyze(doc)
            else:
                engine = TransformEngine()
                stats = engine.analyze(doc.to_dict() if hasattr(doc, "to_dict") else data)
        else:
            # Generic analysis with TransformEngine
            engine = TransformEngine()
            stats = engine.analyze(data)

        # Print analysis results
        print_header("Analysis Results")

        print(f"\n{Colors.BOLD}Structure:{Colors.ENDC}")
        print(f"  Total elements: {stats.get('total_elements', 'N/A')}")
        if "sections" in stats:
            print(f"  Sections: {stats['sections']}")
        if "columns" in stats:
            print(f"  Columns: {stats['columns']}")
        if "widgets" in stats:
            print(f"  Widgets: {stats['widgets']}")

        print(f"\n{Colors.BOLD}Content:{Colors.ENDC}")
        print(f"  Content items: {stats.get('content_items', 'N/A')}")

        if "zones" in stats:
            zones = stats["zones"]
            print(f"\n{Colors.BOLD}Zone Analysis (Zone Theory):{Colors.ENDC}")
            print(f"  Total zones: {zones.get('total_zones', 'N/A')}")
            if "zones_by_type" in zones:
                for zone_type, count in zones["zones_by_type"].items():
                    print(f"    {zone_type}: {count}")
            print(f"  Metadata preservation: {zones.get('metadata_preservation', '100%')}")

        if "widget_types" in stats and stats["widget_types"]:
            print(f"\n{Colors.BOLD}Widget Types:{Colors.ENDC}")
            for wt, count in sorted(stats["widget_types"].items(), key=lambda x: -x[1]):
                print(f"  {wt}: {count}")

        # Show content preview
        if "content_preview" in stats.get("zones", {}):
            preview = stats["zones"]["content_preview"]
            if preview:
                print(f"\n{Colors.BOLD}Content Preview (first 5 items):{Colors.ENDC}")
                for item in preview[:5]:
                    value = str(item.get("value", ""))[:50]
                    if len(str(item.get("value", ""))) > 50:
                        value += "..."
                    print(f"  [{item.get('key', 'unknown')}]: {value}")

        print_success("Analysis complete!")

        # Output to file if requested
        if args.output:
            output_file = Path(args.output)
            with open(output_file, "w", encoding="utf-8") as f:
                json.dump(stats, f, indent=2, default=str)
            print_success(f"Analysis written to: {output_file}")

        return 0

    except json.JSONDecodeError as e:
        print_error(f"Invalid JSON in input file: {e}")
        return 1
    except Exception as e:
        print_error(f"Analysis failed: {e}")
        if args.debug:
            import traceback
            traceback.print_exc()
        return 1


def cmd_analyze_site(args: argparse.Namespace) -> int:
    """Handle the analyze-site command."""
    framework = args.framework.lower()
    input_path = Path(args.path)

    if not input_path.exists():
        print_error(f"Path not found: {input_path}")
        return 1

    print_header(f"Translation Bridge v{__version__} - Analyze Site")
    print_info(f"Framework: {framework}")
    print_info(f"Input: {input_path}")

    try:
        site_parser = ElementorSiteParser()

        # Parse the site export
        if input_path.suffix.lower() == ".zip":
            print_info("Extracting zip archive...")
            site = site_parser.parse_zip(str(input_path))
        else:
            site = site_parser.parse_directory(str(input_path))

        # Get full analysis
        stats = site_parser.analyze(site)

        # Print analysis results
        print_header("Site Analysis Results")

        print(f"\n{Colors.BOLD}Site Overview:{Colors.ENDC}")
        print(f"  Site name: {site.settings.site_name or 'N/A'}")
        print(f"  Total pages: {stats['total_pages']}")
        print(f"  Total templates: {stats['total_templates']}")
        print(f"  Total assets: {stats['total_assets']}")

        print(f"\n{Colors.BOLD}Global Design Tokens:{Colors.ENDC}")
        print(f"  Global colors: {stats['global_colors']}")
        print(f"  Global fonts: {stats['global_fonts']}")
        if site.settings.colors.colors:
            print(f"\n  {Colors.CYAN}Colors:{Colors.ENDC}")
            for key, color_data in list(site.settings.colors.colors.items())[:5]:
                title = color_data.get("title", key)
                color = color_data.get("color", "N/A")
                print(f"    {title}: {color}")

        if stats['menus']:
            print(f"\n{Colors.BOLD}Navigation Menus:{Colors.ENDC}")
            for menu_name in stats['menus']:
                print(f"  - {menu_name}")

        print(f"\n{Colors.BOLD}Pages:{Colors.ENDC}")
        for page_info in stats['pages'][:10]:  # Show first 10
            print(f"  [{page_info['type']}] {page_info['title']} (/{page_info['slug']})")
            print(f"      Elements: {page_info['elements']}, Widgets: {page_info['widgets']}")

        if len(stats['pages']) > 10:
            print(f"  ... and {len(stats['pages']) - 10} more pages")

        print(f"\n{Colors.BOLD}Templates:{Colors.ENDC}")
        for template_info in stats['templates']:
            print(f"  [{template_info['type']}] {template_info['title']}")
            print(f"      Elements: {template_info['elements']}")

        print_success("Site analysis complete!")

        # Output to file if requested
        if args.output:
            output_file = Path(args.output)
            with open(output_file, "w", encoding="utf-8") as f:
                json.dump({
                    "stats": stats,
                    "settings": site.settings.to_dict(),
                }, f, indent=2, default=str)
            print_success(f"Analysis written to: {output_file}")

        return 0

    except Exception as e:
        print_error(f"Site analysis failed: {e}")
        if args.debug:
            import traceback
            traceback.print_exc()
        return 1


def get_extension_for_framework(framework: str) -> str:
    """Get the appropriate file extension for a framework."""
    extensions = {
        "elementor": ".json",
        "bricks": ".json",
        "oxygen": ".json",
        "beaver-builder": ".json",
        "gutenberg": ".html",
        "bootstrap": ".html",
        "divi": ".txt",
        "wpbakery": ".txt",
        "avada": ".html",
        "claude": ".html",
    }
    return extensions.get(framework.lower(), ".json")


def main() -> int:
    """Main entry point for the CLI."""
    parser = argparse.ArgumentParser(
        prog="devtb-py",
        description=f"Translation Bridge v{__version__} - JSON-native transform engine",
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog="""
Examples:
  devtb transform elementor bootstrap page.json
  devtb transform-site elementor bootstrap ./exports/
  devtb analyze elementor page.json

For more information, visit: https://github.com/coryhubbell/development-translation-bridge
        """,
    )
    parser.add_argument(
        "--version", "-v", action="version", version=f"Translation Bridge v{__version__}"
    )

    subparsers = parser.add_subparsers(dest="command", help="Available commands")

    # transform command ('translate' kept as a deprecated alias — RFC 5.0 Phase 3)
    transform_parser = subparsers.add_parser(
        "transform",
        aliases=["translate"],
        help="Transform a file between frameworks",
    )
    transform_parser.add_argument("source", help="Source framework (e.g., elementor)")
    transform_parser.add_argument("target", help="Target framework (e.g., bootstrap)")
    transform_parser.add_argument("file", help="Input file path")
    transform_parser.add_argument("-o", "--output", help="Output file path")
    transform_parser.add_argument(
        "-n", "--dry-run", action="store_true", help="Preview without writing"
    )
    transform_parser.add_argument(
        "-d", "--debug", action="store_true", help="Show debug information"
    )

    # transform-site command
    site_parser = subparsers.add_parser(
        "transform-site", help="Transform all files in a directory"
    )
    site_parser.add_argument("source", help="Source framework (e.g., elementor)")
    site_parser.add_argument("target", help="Target framework (e.g., bootstrap)")
    site_parser.add_argument("directory", help="Input directory path")
    site_parser.add_argument("-o", "--output-dir", help="Output directory path")
    site_parser.add_argument(
        "-n", "--dry-run", action="store_true", help="Preview without writing"
    )
    site_parser.add_argument(
        "-d", "--debug", action="store_true", help="Show debug information"
    )

    # analyze command
    analyze_parser = subparsers.add_parser(
        "analyze", help="Analyze page builder content"
    )
    analyze_parser.add_argument("framework", help="Framework (e.g., elementor)")
    analyze_parser.add_argument("file", help="Input file path")
    analyze_parser.add_argument("-o", "--output", help="Output analysis to file")
    analyze_parser.add_argument(
        "-d", "--debug", action="store_true", help="Show debug information"
    )

    # analyze-site command
    analyze_site_parser = subparsers.add_parser(
        "analyze-site", help="Analyze a complete site export"
    )
    analyze_site_parser.add_argument("framework", help="Framework (e.g., elementor)")
    analyze_site_parser.add_argument("path", help="Site export path (zip file or directory)")
    analyze_site_parser.add_argument("-o", "--output", help="Output analysis to file")
    analyze_site_parser.add_argument(
        "-d", "--debug", action="store_true", help="Show debug information"
    )

    args = parser.parse_args()

    if args.command is None:
        parser.print_help()
        return 0

    if args.command in ("transform", "translate"):
        return cmd_transform(args)
    elif args.command == "transform-site":
        return cmd_transform_site(args)
    elif args.command == "analyze":
        return cmd_analyze(args)
    elif args.command == "analyze-site":
        return cmd_analyze_site(args)
    else:
        parser.print_help()
        return 1


if __name__ == "__main__":
    sys.exit(main())
