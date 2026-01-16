"""
Translation Bridge v4 - Elementor Site Export Parser.

Parses complete Elementor site exports (Export Kit) containing multiple pages,
global settings, templates, and assets for full site conversion.

Elementor Export Kit structure:
    export/
    ├── content.json          # All pages/posts
    ├── site-settings.json    # Global settings (colors, fonts)
    ├── templates/            # Theme templates
    │   ├── header.json
    │   ├── footer.json
    │   └── single.json
    └── wp-content/           # Assets (images, fonts)
        └── uploads/
"""

import json
import zipfile
from dataclasses import dataclass, field
from pathlib import Path
from typing import Any, Dict, List, Optional, Union
import tempfile
import shutil

from .elementor import ElementorParser, ElementorDocument, ElementorElement


@dataclass
class ElementorPage:
    """Represents a single page in the site export."""

    id: int
    title: str
    slug: str
    post_type: str  # page, post, etc.
    status: str  # publish, draft
    document: ElementorDocument
    meta: Dict[str, Any] = field(default_factory=dict)

    def to_dict(self) -> Dict[str, Any]:
        """Convert to dictionary format."""
        return {
            "id": self.id,
            "title": self.title,
            "slug": self.slug,
            "post_type": self.post_type,
            "status": self.status,
            "document": self.document.to_dict(),
            "meta": self.meta,
        }


@dataclass
class ElementorTemplate:
    """Represents a theme template (header, footer, etc.)."""

    id: str
    type: str  # header, footer, single, archive, etc.
    title: str
    conditions: List[Dict[str, Any]]  # Display conditions
    document: ElementorDocument

    def to_dict(self) -> Dict[str, Any]:
        """Convert to dictionary format."""
        return {
            "id": self.id,
            "type": self.type,
            "title": self.title,
            "conditions": self.conditions,
            "document": self.document.to_dict(),
        }


@dataclass
class GlobalColors:
    """Global color settings."""

    colors: Dict[str, Dict[str, str]] = field(default_factory=dict)
    # Example: {"primary": {"_id": "abc123", "color": "#e94560", "title": "Primary"}}

    def get_color(self, color_id: str) -> Optional[str]:
        """Get color value by ID."""
        for key, color_data in self.colors.items():
            if color_data.get("_id") == color_id:
                return color_data.get("color")
        return None

    def to_css_variables(self) -> str:
        """Convert to CSS custom properties."""
        lines = [":root {"]
        for key, color_data in self.colors.items():
            color_value = color_data.get("color", "")
            title = color_data.get("title", key)
            var_name = title.lower().replace(" ", "-")
            lines.append(f"  --color-{var_name}: {color_value};")
        lines.append("}")
        return "\n".join(lines)


@dataclass
class GlobalFonts:
    """Global typography settings."""

    fonts: Dict[str, Dict[str, Any]] = field(default_factory=dict)
    # Example: {"primary": {"_id": "abc123", "font_family": "Poppins", "font_weight": "600"}}

    def get_font_family(self, font_id: str) -> Optional[str]:
        """Get font family by ID."""
        for key, font_data in self.fonts.items():
            if font_data.get("_id") == font_id:
                return font_data.get("font_family")
        return None

    def get_google_fonts_import(self) -> str:
        """Generate Google Fonts import URL."""
        families = set()
        for key, font_data in self.fonts.items():
            family = font_data.get("font_family", "")
            weight = font_data.get("font_weight", "400")
            if family:
                families.add(f"{family}:wght@{weight}")

        if families:
            families_param = "|".join(families).replace(" ", "+")
            return f'@import url("https://fonts.googleapis.com/css2?family={families_param}&display=swap");'
        return ""

    def to_css_variables(self) -> str:
        """Convert to CSS custom properties."""
        lines = [":root {"]
        for key, font_data in self.fonts.items():
            family = font_data.get("font_family", "sans-serif")
            title = font_data.get("title", key)
            var_name = title.lower().replace(" ", "-")
            lines.append(f"  --font-{var_name}: '{family}', sans-serif;")
        lines.append("}")
        return "\n".join(lines)


@dataclass
class SiteSettings:
    """Global site settings."""

    site_name: str = ""
    site_description: str = ""
    colors: GlobalColors = field(default_factory=GlobalColors)
    fonts: GlobalFonts = field(default_factory=GlobalFonts)
    custom_css: str = ""
    default_generic_fonts: str = "Sans-serif"
    container_width: int = 1140
    container_padding: int = 0
    page_title_selector: str = "h1.entry-title"
    lightbox_enable_counter: bool = True
    lightbox_enable_fullscreen: bool = True

    def to_dict(self) -> Dict[str, Any]:
        """Convert to dictionary format."""
        return {
            "site_name": self.site_name,
            "site_description": self.site_description,
            "colors": self.colors.colors,
            "fonts": self.fonts.fonts,
            "custom_css": self.custom_css,
            "container_width": self.container_width,
        }


@dataclass
class ElementorSite:
    """Represents a complete Elementor site export."""

    pages: List[ElementorPage] = field(default_factory=list)
    templates: List[ElementorTemplate] = field(default_factory=list)
    settings: SiteSettings = field(default_factory=SiteSettings)
    assets: Dict[str, str] = field(default_factory=dict)  # asset_id -> url mapping
    menus: Dict[str, List[Dict[str, Any]]] = field(default_factory=dict)

    def get_page_by_slug(self, slug: str) -> Optional[ElementorPage]:
        """Get a page by its slug."""
        for page in self.pages:
            if page.slug == slug:
                return page
        return None

    def get_template_by_type(self, template_type: str) -> Optional[ElementorTemplate]:
        """Get a template by its type (header, footer, etc.)."""
        for template in self.templates:
            if template.type == template_type:
                return template
        return None

    def get_header(self) -> Optional[ElementorTemplate]:
        """Get the header template."""
        return self.get_template_by_type("header")

    def get_footer(self) -> Optional[ElementorTemplate]:
        """Get the footer template."""
        return self.get_template_by_type("footer")

    def to_dict(self) -> Dict[str, Any]:
        """Convert to dictionary format."""
        return {
            "pages": [p.to_dict() for p in self.pages],
            "templates": [t.to_dict() for t in self.templates],
            "settings": self.settings.to_dict(),
            "assets": self.assets,
            "menus": self.menus,
        }


class ElementorSiteParser:
    """
    Parser for complete Elementor site exports.

    Handles Export Kit zip files or extracted export directories.
    Parses all pages, templates, global settings, and asset references.
    """

    def __init__(self):
        self.element_parser = ElementorParser()
        self._temp_dir: Optional[Path] = None

    def parse_zip(self, zip_path: str) -> ElementorSite:
        """
        Parse an Elementor Export Kit zip file.

        Args:
            zip_path: Path to the .zip export file

        Returns:
            ElementorSite with all parsed content
        """
        zip_file = Path(zip_path)
        if not zip_file.exists():
            raise FileNotFoundError(f"Export file not found: {zip_path}")

        if not zip_file.suffix.lower() == ".zip":
            raise ValueError(f"Expected .zip file, got: {zip_file.suffix}")

        # Extract to temporary directory
        self._temp_dir = Path(tempfile.mkdtemp(prefix="elementor_export_"))

        try:
            with zipfile.ZipFile(zip_file, "r") as zf:
                zf.extractall(self._temp_dir)

            return self.parse_directory(str(self._temp_dir))
        finally:
            # Cleanup will happen in __del__ or manually
            pass

    def parse_directory(self, dir_path: str) -> ElementorSite:
        """
        Parse an extracted Elementor export directory.

        Args:
            dir_path: Path to the export directory

        Returns:
            ElementorSite with all parsed content
        """
        export_dir = Path(dir_path)
        if not export_dir.exists():
            raise FileNotFoundError(f"Export directory not found: {dir_path}")

        if not export_dir.is_dir():
            raise ValueError(f"Not a directory: {dir_path}")

        site = ElementorSite()

        # Parse site settings first (needed for global color/font resolution)
        site.settings = self._parse_settings(export_dir)

        # Parse content (pages and posts)
        site.pages = self._parse_content(export_dir)

        # Parse templates
        site.templates = self._parse_templates(export_dir)

        # Parse menus
        site.menus = self._parse_menus(export_dir)

        # Collect asset references
        site.assets = self._collect_assets(export_dir)

        return site

    def _parse_settings(self, export_dir: Path) -> SiteSettings:
        """Parse site-settings.json for global settings."""
        settings = SiteSettings()

        # Try different possible file names
        settings_files = [
            "site-settings.json",
            "settings.json",
            "global-settings.json",
        ]

        for filename in settings_files:
            settings_file = export_dir / filename
            if settings_file.exists():
                with open(settings_file, "r", encoding="utf-8") as f:
                    data = json.load(f)

                settings.site_name = data.get("blogname", data.get("site_name", ""))
                settings.site_description = data.get("blogdescription", data.get("site_description", ""))
                settings.custom_css = data.get("custom_css", "")

                # Parse global colors
                if "system_colors" in data:
                    settings.colors = GlobalColors(colors=data["system_colors"])
                elif "colors" in data:
                    settings.colors = GlobalColors(colors=data["colors"])

                # Parse global fonts
                if "system_typography" in data:
                    settings.fonts = GlobalFonts(fonts=data["system_typography"])
                elif "typography" in data:
                    settings.fonts = GlobalFonts(fonts=data["typography"])

                # Container settings
                settings.container_width = data.get("container_width", {}).get("size", 1140)

                break

        return settings

    def _parse_content(self, export_dir: Path) -> List[ElementorPage]:
        """Parse content.json for all pages and posts."""
        pages = []

        # Try different possible file names
        content_files = [
            "content.json",
            "pages.json",
            "manifest.json",
        ]

        for filename in content_files:
            content_file = export_dir / filename
            if content_file.exists():
                with open(content_file, "r", encoding="utf-8") as f:
                    data = json.load(f)

                # Handle different export formats
                if isinstance(data, list):
                    items = data
                elif isinstance(data, dict):
                    items = data.get("content", data.get("pages", data.get("posts", [])))
                else:
                    continue

                for item in items:
                    page = self._parse_page_item(item)
                    if page:
                        pages.append(page)

                break

        # Also check for individual page files
        pages_dir = export_dir / "content"
        if pages_dir.exists():
            for page_file in pages_dir.glob("*.json"):
                with open(page_file, "r", encoding="utf-8") as f:
                    data = json.load(f)
                page = self._parse_page_item(data)
                if page:
                    pages.append(page)

        return pages

    def _parse_page_item(self, item: Dict[str, Any]) -> Optional[ElementorPage]:
        """Parse a single page item from the content."""
        if not isinstance(item, dict):
            return None

        # Get page ID and basic info
        page_id = item.get("id", item.get("ID", 0))
        title = item.get("title", item.get("post_title", "Untitled"))
        slug = item.get("slug", item.get("post_name", f"page-{page_id}"))
        post_type = item.get("post_type", item.get("type", "page"))
        status = item.get("status", item.get("post_status", "publish"))

        # Get Elementor content
        elementor_data = item.get("_elementor_data", item.get("content", item.get("elements", [])))

        # Parse the Elementor data
        if isinstance(elementor_data, str):
            try:
                elementor_data = json.loads(elementor_data)
            except json.JSONDecodeError:
                elementor_data = []

        if not elementor_data:
            return None

        document = self.element_parser.parse(elementor_data)

        return ElementorPage(
            id=page_id,
            title=title,
            slug=slug,
            post_type=post_type,
            status=status,
            document=document,
            meta=item.get("meta", {}),
        )

    def _parse_templates(self, export_dir: Path) -> List[ElementorTemplate]:
        """Parse theme builder templates."""
        templates = []

        # Check templates directory
        templates_dir = export_dir / "templates"
        if templates_dir.exists():
            for template_file in templates_dir.glob("*.json"):
                with open(template_file, "r", encoding="utf-8") as f:
                    data = json.load(f)

                template = self._parse_template_item(data, template_file.stem)
                if template:
                    templates.append(template)

        # Also check for theme-builder.json
        theme_builder_file = export_dir / "theme-builder.json"
        if theme_builder_file.exists():
            with open(theme_builder_file, "r", encoding="utf-8") as f:
                data = json.load(f)

            template_list = data if isinstance(data, list) else data.get("templates", [])
            for item in template_list:
                template = self._parse_template_item(item)
                if template:
                    templates.append(template)

        return templates

    def _parse_template_item(self, item: Dict[str, Any], default_type: str = "") -> Optional[ElementorTemplate]:
        """Parse a single template item."""
        if not isinstance(item, dict):
            return None

        template_id = item.get("id", item.get("_id", ""))
        template_type = item.get("type", item.get("template_type", default_type))
        title = item.get("title", item.get("post_title", f"{template_type.title()} Template"))
        conditions = item.get("conditions", [])

        # Get Elementor content
        elementor_data = item.get("_elementor_data", item.get("content", item.get("elements", [])))

        if isinstance(elementor_data, str):
            try:
                elementor_data = json.loads(elementor_data)
            except json.JSONDecodeError:
                elementor_data = []

        if not elementor_data:
            return None

        document = self.element_parser.parse(elementor_data)

        return ElementorTemplate(
            id=str(template_id),
            type=template_type,
            title=title,
            conditions=conditions,
            document=document,
        )

    def _parse_menus(self, export_dir: Path) -> Dict[str, List[Dict[str, Any]]]:
        """Parse navigation menus."""
        menus = {}

        # Check for menus.json
        menus_file = export_dir / "menus.json"
        if menus_file.exists():
            with open(menus_file, "r", encoding="utf-8") as f:
                data = json.load(f)

            if isinstance(data, dict):
                for menu_name, menu_items in data.items():
                    menus[menu_name] = menu_items if isinstance(menu_items, list) else []
            elif isinstance(data, list):
                menus["primary"] = data

        return menus

    def _collect_assets(self, export_dir: Path) -> Dict[str, str]:
        """Collect asset references from the export."""
        assets = {}

        # Check wp-content/uploads
        uploads_dir = export_dir / "wp-content" / "uploads"
        if uploads_dir.exists():
            for asset_file in uploads_dir.rglob("*"):
                if asset_file.is_file():
                    relative_path = asset_file.relative_to(export_dir)
                    # Use relative path as key and full path as value
                    assets[str(relative_path)] = str(asset_file)

        # Also check assets directory
        assets_dir = export_dir / "assets"
        if assets_dir.exists():
            for asset_file in assets_dir.rglob("*"):
                if asset_file.is_file():
                    relative_path = asset_file.relative_to(export_dir)
                    assets[str(relative_path)] = str(asset_file)

        return assets

    def analyze(self, site: ElementorSite) -> Dict[str, Any]:
        """
        Analyze an Elementor site export and return statistics.

        Args:
            site: Parsed ElementorSite

        Returns:
            Dictionary with analysis results
        """
        stats = {
            "total_pages": len(site.pages),
            "total_templates": len(site.templates),
            "total_assets": len(site.assets),
            "pages": [],
            "templates": [],
            "global_colors": len(site.settings.colors.colors),
            "global_fonts": len(site.settings.fonts.fonts),
            "menus": list(site.menus.keys()),
        }

        # Analyze each page
        for page in site.pages:
            page_stats = self.element_parser.analyze(page.document)
            stats["pages"].append({
                "title": page.title,
                "slug": page.slug,
                "type": page.post_type,
                "elements": page_stats.get("total_elements", 0),
                "widgets": page_stats.get("widgets", 0),
            })

        # Analyze each template
        for template in site.templates:
            template_stats = self.element_parser.analyze(template.document)
            stats["templates"].append({
                "title": template.title,
                "type": template.type,
                "elements": template_stats.get("total_elements", 0),
            })

        return stats

    def cleanup(self):
        """Clean up temporary files."""
        if self._temp_dir and self._temp_dir.exists():
            shutil.rmtree(self._temp_dir)
            self._temp_dir = None

    def __del__(self):
        """Cleanup on destruction."""
        self.cleanup()
