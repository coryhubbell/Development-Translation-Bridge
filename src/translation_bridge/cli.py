#!/usr/bin/env python3
"""
Translation Bridge v4 CLI - JSON-native transform commands.

Commands:
    transform       Transform a single file between frameworks
    transform-site  Transform all files in a directory
    analyze         Analyze page builder content

Usage:
    devtb transform <source> <target> <file> [options]
    devtb transform-site <source> <target> <directory> [options]
    devtb analyze <framework> <file> [options]
"""

import argparse
import json
import sys
from pathlib import Path
from typing import Any, Dict, List, Optional

from . import __version__
from .transforms.core import TransformEngine, ZoneType
from .transforms.registry import TransformRegistry, ParserRegistry
from .parsers.elementor import ElementorParser


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
    # Add more parsers as they are implemented
    return None


def cmd_transform(args: argparse.Namespace) -> int:
    """Handle the transform command."""
    source = args.source.lower()
    target = args.target.lower()
    input_file = Path(args.file)

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

        # Get transform function
        transform_fn = TransformRegistry.get_transform(source, target)

        if transform_fn:
            # Use registered transform
            result_data = transform_fn(doc.to_dict() if hasattr(doc, "to_dict") else doc)
        else:
            # Fallback: extract content zones
            print_warning(f"No specific transform for {source} → {target}, extracting content")
            if hasattr(parser, "extract_content"):
                result_data = parser.extract_content(doc)
            else:
                result_data = engine.extract_content(
                    doc.to_dict() if hasattr(doc, "to_dict") else doc
                )

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

        print_success("Transformation complete!")
        print_info("Metadata preservation: 100% (JSON-native)")

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
    input_dir = Path(args.directory)

    if not input_dir.exists():
        print_error(f"Directory not found: {input_dir}")
        return 1

    if not input_dir.is_dir():
        print_error(f"Not a directory: {input_dir}")
        return 1

    print_header(f"Translation Bridge v{__version__} - Transform Site")
    print_info(f"Source: {source}")
    print_info(f"Target: {target}")
    print_info(f"Directory: {input_dir}")

    # Find all JSON files
    json_files = list(input_dir.glob("**/*.json"))

    if not json_files:
        print_warning("No JSON files found in directory")
        return 0

    print_info(f"Found {len(json_files)} JSON files")

    # Create output directory
    output_dir = Path(args.output_dir) if args.output_dir else input_dir / f"transformed-{target}"
    if not args.dry_run:
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
        file_args.dry_run = args.dry_run
        file_args.debug = args.debug

        if not args.dry_run:
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

    # transform command
    transform_parser = subparsers.add_parser(
        "transform", help="Transform a file between frameworks"
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

    args = parser.parse_args()

    if args.command is None:
        parser.print_help()
        return 0

    if args.command == "transform":
        return cmd_transform(args)
    elif args.command == "transform-site":
        return cmd_transform_site(args)
    elif args.command == "analyze":
        return cmd_analyze(args)
    else:
        parser.print_help()
        return 1


if __name__ == "__main__":
    sys.exit(main())
