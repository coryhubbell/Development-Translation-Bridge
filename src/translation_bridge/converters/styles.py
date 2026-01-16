"""
Translation Bridge v4 - Global Styles Converter.

Extracts and converts global design tokens from Elementor site exports
to CSS custom properties and framework-specific style formats.

Supports:
- Global colors → CSS custom properties
- Global fonts → Google Fonts imports + CSS variables
- Spacing scale → CSS spacing variables
- Container width settings
- Custom CSS passthrough
"""

from typing import Any, Dict, List, Optional, Tuple
from dataclasses import dataclass, field


@dataclass
class ColorToken:
    """Represents a color design token."""

    id: str
    name: str
    value: str
    category: str = "primary"  # primary, secondary, text, accent

    def to_css_variable(self) -> str:
        """Convert to CSS custom property declaration."""
        var_name = self.name.lower().replace(" ", "-").replace("_", "-")
        return f"--color-{var_name}: {self.value};"


@dataclass
class FontToken:
    """Represents a typography design token."""

    id: str
    name: str
    family: str
    weight: str = "400"
    size: str = ""
    line_height: str = ""
    letter_spacing: str = ""

    def to_css_variable(self) -> str:
        """Convert to CSS custom property declaration."""
        var_name = self.name.lower().replace(" ", "-").replace("_", "-")
        return f"--font-{var_name}: '{self.family}', sans-serif;"

    def to_font_face(self) -> str:
        """Generate @font-face or import if web font."""
        # For Google Fonts, return import URL
        return f"{self.family}:wght@{self.weight}"


@dataclass
class SpacingToken:
    """Represents a spacing design token."""

    name: str
    value: str
    unit: str = "px"

    def to_css_variable(self) -> str:
        """Convert to CSS custom property declaration."""
        var_name = self.name.lower().replace(" ", "-")
        return f"--spacing-{var_name}: {self.value}{self.unit};"


@dataclass
class DesignTokens:
    """Collection of all design tokens from a site."""

    colors: List[ColorToken] = field(default_factory=list)
    fonts: List[FontToken] = field(default_factory=list)
    spacing: List[SpacingToken] = field(default_factory=list)
    container_width: int = 1140
    custom_css: str = ""

    def get_color_by_id(self, color_id: str) -> Optional[ColorToken]:
        """Find a color token by ID."""
        for color in self.colors:
            if color.id == color_id:
                return color
        return None

    def get_font_by_id(self, font_id: str) -> Optional[FontToken]:
        """Find a font token by ID."""
        for font in self.fonts:
            if font.id == font_id:
                return font
        return None


class StylesConverter:
    """
    Converts design tokens to various CSS formats.

    Supports output formats:
    - CSS custom properties (default)
    - SCSS variables
    - Tailwind config
    - Bootstrap theme overrides
    """

    # Default spacing scale (matches Elementor defaults)
    DEFAULT_SPACING_SCALE = [
        ("xs", "4"),
        ("sm", "8"),
        ("md", "16"),
        ("lg", "24"),
        ("xl", "32"),
        ("2xl", "48"),
        ("3xl", "64"),
    ]

    def __init__(self):
        self.tokens: Optional[DesignTokens] = None

    def extract_tokens(self, site_settings: Dict[str, Any]) -> DesignTokens:
        """
        Extract design tokens from Elementor site settings.

        Args:
            site_settings: Raw site settings dictionary

        Returns:
            DesignTokens with all extracted tokens
        """
        tokens = DesignTokens()

        # Extract colors
        colors_data = site_settings.get("system_colors", site_settings.get("colors", {}))
        if isinstance(colors_data, dict):
            for key, color_data in colors_data.items():
                if isinstance(color_data, dict):
                    tokens.colors.append(ColorToken(
                        id=color_data.get("_id", key),
                        name=color_data.get("title", key),
                        value=color_data.get("color", "#000000"),
                        category=self._categorize_color(key),
                    ))

        # Extract fonts
        fonts_data = site_settings.get("system_typography", site_settings.get("typography", {}))
        if isinstance(fonts_data, dict):
            for key, font_data in fonts_data.items():
                if isinstance(font_data, dict):
                    tokens.fonts.append(FontToken(
                        id=font_data.get("_id", key),
                        name=font_data.get("title", key),
                        family=font_data.get("font_family", "Inter"),
                        weight=str(font_data.get("font_weight", "400")),
                        size=self._extract_size(font_data.get("font_size", {})),
                        line_height=self._extract_size(font_data.get("line_height", {})),
                        letter_spacing=self._extract_size(font_data.get("letter_spacing", {})),
                    ))

        # Extract spacing scale if present
        spacing_data = site_settings.get("spacing", {})
        if spacing_data:
            for name, value in spacing_data.items():
                tokens.spacing.append(SpacingToken(
                    name=name,
                    value=str(self._extract_size_value(value)),
                    unit=self._extract_size_unit(value),
                ))
        else:
            # Use default spacing scale
            for name, value in self.DEFAULT_SPACING_SCALE:
                tokens.spacing.append(SpacingToken(name=name, value=value))

        # Container width
        container_width = site_settings.get("container_width", {})
        if isinstance(container_width, dict):
            tokens.container_width = container_width.get("size", 1140)
        else:
            tokens.container_width = int(container_width) if container_width else 1140

        # Custom CSS
        tokens.custom_css = site_settings.get("custom_css", "")

        self.tokens = tokens
        return tokens

    def to_css(self, tokens: Optional[DesignTokens] = None) -> str:
        """
        Convert tokens to CSS with custom properties.

        Args:
            tokens: Design tokens (uses self.tokens if None)

        Returns:
            Complete CSS string with variables and imports
        """
        tokens = tokens or self.tokens
        if not tokens:
            return ""

        lines = []

        # Google Fonts import
        fonts_import = self._generate_google_fonts_import(tokens.fonts)
        if fonts_import:
            lines.append(fonts_import)
            lines.append("")

        # CSS custom properties
        lines.append(":root {")
        lines.append("  /* Colors */")
        for color in tokens.colors:
            lines.append(f"  {color.to_css_variable()}")

        lines.append("")
        lines.append("  /* Typography */")
        for font in tokens.fonts:
            lines.append(f"  {font.to_css_variable()}")

        lines.append("")
        lines.append("  /* Spacing */")
        for spacing in tokens.spacing:
            lines.append(f"  {spacing.to_css_variable()}")

        lines.append("")
        lines.append("  /* Layout */")
        lines.append(f"  --container-width: {tokens.container_width}px;")

        lines.append("}")
        lines.append("")

        # Utility classes for colors
        lines.append("/* Color utility classes */")
        for color in tokens.colors:
            var_name = color.name.lower().replace(" ", "-").replace("_", "-")
            lines.append(f".text-{var_name} {{ color: var(--color-{var_name}); }}")
            lines.append(f".bg-{var_name} {{ background-color: var(--color-{var_name}); }}")
            lines.append(f".border-{var_name} {{ border-color: var(--color-{var_name}); }}")

        lines.append("")

        # Typography utility classes
        lines.append("/* Typography utility classes */")
        for font in tokens.fonts:
            var_name = font.name.lower().replace(" ", "-").replace("_", "-")
            lines.append(f".font-{var_name} {{ font-family: var(--font-{var_name}); }}")

        lines.append("")

        # Spacing utility classes
        lines.append("/* Spacing utility classes */")
        for spacing in tokens.spacing:
            var_name = spacing.name.lower().replace(" ", "-")
            lines.append(f".m-{var_name} {{ margin: var(--spacing-{var_name}); }}")
            lines.append(f".p-{var_name} {{ padding: var(--spacing-{var_name}); }}")
            lines.append(f".mt-{var_name} {{ margin-top: var(--spacing-{var_name}); }}")
            lines.append(f".mb-{var_name} {{ margin-bottom: var(--spacing-{var_name}); }}")
            lines.append(f".pt-{var_name} {{ padding-top: var(--spacing-{var_name}); }}")
            lines.append(f".pb-{var_name} {{ padding-bottom: var(--spacing-{var_name}); }}")

        lines.append("")

        # Container
        lines.append("/* Container */")
        lines.append(".container-custom {")
        lines.append("  width: 100%;")
        lines.append("  max-width: var(--container-width);")
        lines.append("  margin-left: auto;")
        lines.append("  margin-right: auto;")
        lines.append("  padding-left: 15px;")
        lines.append("  padding-right: 15px;")
        lines.append("}")
        lines.append("")

        # Custom CSS passthrough
        if tokens.custom_css:
            lines.append("/* Custom CSS from Elementor */")
            lines.append(tokens.custom_css)
            lines.append("")

        return "\n".join(lines)

    def to_scss(self, tokens: Optional[DesignTokens] = None) -> str:
        """
        Convert tokens to SCSS variables.

        Args:
            tokens: Design tokens (uses self.tokens if None)

        Returns:
            SCSS variables string
        """
        tokens = tokens or self.tokens
        if not tokens:
            return ""

        lines = []

        # Google Fonts import
        fonts_import = self._generate_google_fonts_import(tokens.fonts)
        if fonts_import:
            lines.append(fonts_import)
            lines.append("")

        lines.append("// Colors")
        for color in tokens.colors:
            var_name = color.name.lower().replace(" ", "-").replace("_", "-")
            lines.append(f"$color-{var_name}: {color.value};")

        lines.append("")
        lines.append("// Typography")
        for font in tokens.fonts:
            var_name = font.name.lower().replace(" ", "-").replace("_", "-")
            lines.append(f"$font-{var_name}: '{font.family}', sans-serif;")

        lines.append("")
        lines.append("// Spacing")
        for spacing in tokens.spacing:
            var_name = spacing.name.lower().replace(" ", "-")
            lines.append(f"$spacing-{var_name}: {spacing.value}{spacing.unit};")

        lines.append("")
        lines.append("// Layout")
        lines.append(f"$container-width: {tokens.container_width}px;")

        lines.append("")
        lines.append("// Color map for utilities")
        lines.append("$colors: (")
        for color in tokens.colors:
            var_name = color.name.lower().replace(" ", "-").replace("_", "-")
            lines.append(f'  "{var_name}": $color-{var_name},')
        lines.append(");")

        return "\n".join(lines)

    def to_tailwind_config(self, tokens: Optional[DesignTokens] = None) -> str:
        """
        Convert tokens to Tailwind CSS config.

        Args:
            tokens: Design tokens (uses self.tokens if None)

        Returns:
            Tailwind config JavaScript object
        """
        tokens = tokens or self.tokens
        if not tokens:
            return "{}"

        lines = []
        lines.append("module.exports = {")
        lines.append("  theme: {")
        lines.append("    extend: {")

        # Colors
        lines.append("      colors: {")
        for color in tokens.colors:
            var_name = color.name.lower().replace(" ", "-").replace("_", "-")
            lines.append(f"        '{var_name}': '{color.value}',")
        lines.append("      },")

        # Font families
        lines.append("      fontFamily: {")
        for font in tokens.fonts:
            var_name = font.name.lower().replace(" ", "-").replace("_", "-")
            lines.append(f"        '{var_name}': ['{font.family}', 'sans-serif'],")
        lines.append("      },")

        # Spacing
        lines.append("      spacing: {")
        for spacing in tokens.spacing:
            var_name = spacing.name.lower().replace(" ", "-")
            lines.append(f"        '{var_name}': '{spacing.value}{spacing.unit}',")
        lines.append("      },")

        # Container
        lines.append("      maxWidth: {")
        lines.append(f"        'container': '{tokens.container_width}px',")
        lines.append("      },")

        lines.append("    },")
        lines.append("  },")
        lines.append("};")

        return "\n".join(lines)

    def to_bootstrap_overrides(self, tokens: Optional[DesignTokens] = None) -> str:
        """
        Convert tokens to Bootstrap SCSS variable overrides.

        Args:
            tokens: Design tokens (uses self.tokens if None)

        Returns:
            Bootstrap SCSS overrides
        """
        tokens = tokens or self.tokens
        if not tokens:
            return ""

        lines = []
        lines.append("// Bootstrap variable overrides")
        lines.append("// Import this file BEFORE Bootstrap's variables.scss")
        lines.append("")

        # Map colors to Bootstrap's naming convention
        color_mapping = {
            "primary": "$primary",
            "secondary": "$secondary",
            "text": "$body-color",
            "accent": "$info",
        }

        for color in tokens.colors:
            category = color.category
            if category in color_mapping:
                lines.append(f"{color_mapping[category]}: {color.value};")

        lines.append("")

        # Typography
        if tokens.fonts:
            primary_font = tokens.fonts[0] if tokens.fonts else None
            if primary_font:
                lines.append(f'$font-family-base: "{primary_font.family}", sans-serif;')

        lines.append("")
        lines.append(f"$container-max-widths: (")
        lines.append(f"  xl: {tokens.container_width}px")
        lines.append(");")

        return "\n".join(lines)

    def _generate_google_fonts_import(self, fonts: List[FontToken]) -> str:
        """Generate Google Fonts import URL."""
        if not fonts:
            return ""

        families = []
        for font in fonts:
            family_encoded = font.family.replace(" ", "+")
            families.append(f"{family_encoded}:wght@{font.weight}")

        if families:
            families_param = "&family=".join(families)
            return f'@import url("https://fonts.googleapis.com/css2?family={families_param}&display=swap");'

        return ""

    def _categorize_color(self, key: str) -> str:
        """Categorize a color based on its key."""
        key_lower = key.lower()
        if "primary" in key_lower:
            return "primary"
        elif "secondary" in key_lower:
            return "secondary"
        elif "text" in key_lower or "body" in key_lower:
            return "text"
        elif "accent" in key_lower:
            return "accent"
        return "primary"

    def _extract_size(self, value: Any) -> str:
        """Extract size value from various formats."""
        if isinstance(value, dict):
            size = value.get("size", "")
            unit = value.get("unit", "px")
            return f"{size}{unit}" if size else ""
        return str(value) if value else ""

    def _extract_size_value(self, value: Any) -> str:
        """Extract just the numeric size value."""
        if isinstance(value, dict):
            return str(value.get("size", "0"))
        return str(value) if value else "0"

    def _extract_size_unit(self, value: Any) -> str:
        """Extract just the unit from a size value."""
        if isinstance(value, dict):
            return value.get("unit", "px")
        return "px"


def extract_and_convert_styles(
    site_settings: Dict[str, Any],
    output_format: str = "css"
) -> str:
    """
    Convenience function to extract and convert styles in one call.

    Args:
        site_settings: Raw site settings dictionary
        output_format: Output format (css, scss, tailwind, bootstrap)

    Returns:
        Converted styles string
    """
    converter = StylesConverter()
    tokens = converter.extract_tokens(site_settings)

    format_methods = {
        "css": converter.to_css,
        "scss": converter.to_scss,
        "tailwind": converter.to_tailwind_config,
        "bootstrap": converter.to_bootstrap_overrides,
    }

    method = format_methods.get(output_format.lower(), converter.to_css)
    return method(tokens)
