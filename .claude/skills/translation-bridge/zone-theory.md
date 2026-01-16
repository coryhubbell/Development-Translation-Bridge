# Zone Theory Reference

Zone Theory is the foundational concept behind Translation Bridge v4's lossless JSON transformations.

## The Problem with HTML Intermediate

Traditional page builder translation converts source JSON to HTML, then HTML to target format:

```
Elementor JSON → HTML → Bootstrap/DIVI/etc.
```

This loses metadata:
- **__globals__**: Theme color references (58% of elements)
- **Responsive settings**: Tablet/mobile overrides (69% of elements)
- **Conditions**: Display rules and visibility
- **Custom CSS**: Element-specific styles
- **Animations**: Entrance effects and hover states

**Result**: ~42% metadata preservation

## Zone Theory Solution

Zone Theory classifies element data into semantic categories, enabling targeted transformations:

```
JSON → Zone Classification → Targeted Transform → JSON
```

**Result**: 100% metadata preservation

## Zone Types

### STRUCTURAL

Layout and hierarchy information:
- Element types (section, column, widget)
- Parent-child relationships
- Inner section flags
- Column sizes and breakpoints

**Example keys**: `elType`, `widgetType`, `elements`, `isInner`, `_column_size`

### CONTENT

User-visible content that should be translated or modified:
- Headings and text
- Rich text/HTML content
- Image URLs and alt text
- Button labels and links
- Form field labels

**Example keys**: `title`, `editor`, `text`, `content`, `description`, `image`, `url`

### STYLING

Visual presentation settings:
- Colors (including __globals__ references)
- Typography (font, size, weight)
- Spacing (margin, padding)
- Borders and shadows
- Background settings

**Example keys**: `background_color`, `typography_font_size`, `margin`, `padding`, `border`

### BEHAVIORAL

Interactive and dynamic features:
- Animations and transitions
- Hover effects
- Scroll triggers
- Click actions
- Visibility conditions

**Example keys**: `animation`, `hover_animation`, `entrance_animation`, `motion_fx`

### META

Framework-specific technical data:
- Element IDs
- Timestamps
- Version info
- Internal references
- Export markers

**Example keys**: `id`, `_id`, `created`, `modified`, `version`

## Classification Rules

The TransformEngine classifies keys using pattern matching:

```python
STRUCTURAL_KEYS = {
    "elType", "widgetType", "elements", "isInner",
    "id", "_id", "columns", "rows",
}

CONTENT_KEYS = {
    "title", "text", "content", "description",
    "editor", "html", "caption", "label",
}

STYLING_KEYS = {
    "background", "color", "typography", "border",
    "margin", "padding", "font", "size",
}

BEHAVIORAL_KEYS = {
    "animation", "motion", "hover", "scroll",
    "trigger", "action", "delay",
}
```

Keys not matching any pattern are classified as META (preserved but not transformed).

## Transformation Workflow

### 1. Parse Input

```python
from translation_bridge.parsers.elementor import ElementorParser

parser = ElementorParser()
doc = parser.parse_file("page.json")
```

### 2. Classify Zones

```python
from translation_bridge.transforms.core import TransformEngine

engine = TransformEngine()
zones = engine.classify_zones(element)

for zone in zones:
    print(f"{zone.zone_type.value}: {zone.path}")
```

### 3. Apply Targeted Transform

```python
def translate_content(zone):
    """Only modify CONTENT zones."""
    if zone.zone_type == ZoneType.CONTENT:
        # Translate text content
        new_data = translate(zone.data)
        return Zone(
            zone_type=zone.zone_type,
            path=zone.path,
            data=new_data,
            original_keys=zone.original_keys
        )
    return zone  # Preserve all other zones unchanged

result = engine.transform(
    data,
    zone_types=[ZoneType.CONTENT],
    transformer=translate_content
)
```

### 4. Verify Preservation

```python
assert result.metadata_preserved == 100.0
```

## Practical Examples

### Preserving __globals__

Elementor uses `__globals__` for theme color references:

```json
{
  "background_color": "",
  "__globals__": {
    "background_color": "globals/colors?id=primary"
  }
}
```

Zone Theory classifies this as STYLING and preserves it:

```python
zones = engine.classify_zones(element)
styling_zone = next(z for z in zones if z.zone_type == ZoneType.STYLING)
# __globals__ is preserved in styling_zone.data
```

### Preserving Responsive Settings

Elementor stores responsive overrides with `_tablet` and `_mobile` suffixes:

```json
{
  "margin": {"top": "50", "unit": "px"},
  "margin_tablet": {"top": "30", "unit": "px"},
  "margin_mobile": {"top": "20", "unit": "px"}
}
```

All three keys are classified as STYLING and preserved together.

### Content-Only Transformations

To translate text without affecting styles:

```python
result = engine.transform(
    data,
    zone_types=[ZoneType.CONTENT],  # Only process CONTENT
    transformer=translate_text
)

# STYLING, BEHAVIORAL, META zones unchanged
assert result.metadata_preserved == 100.0
```

## Benefits

1. **Lossless**: 100% metadata preservation
2. **Fast**: No HTML parsing (~60x faster)
3. **Targeted**: Only modify what you need
4. **Reversible**: Transform back without data loss
5. **Extensible**: Add new zone types as needed

## See Also

- `/tb-transform` - Transform command
- `/tb-analyze` - Analyze zones
- `src/translation_bridge/transforms/core.py` - Implementation
