# Translation Bridge Skill

Translation Bridge is a universal page builder translation system that converts content between 10 WordPress page builder frameworks with AI-native capabilities.

## Overview

Translation Bridge v4 introduces a JSON-native transform engine alongside the existing PHP translation system:

| Feature | v3 (translate) | v4 (transform) |
|---------|----------------|----------------|
| Approach | HTML intermediate | JSON-native |
| Metadata | ~42% preserved | 100% preserved |
| Speed | ~30s/page | ~0.5s/page |
| Language | PHP | Python |

## Commands

### v4 Python Commands (JSON-native)

```bash
# Transform a single file
devtb transform <source> <target> <file>

# Transform all files in a directory
devtb transform-site <source> <target> <directory>

# Analyze content structure
devtb analyze <framework> <file>
```

### v3 PHP Commands (HTML intermediate)

```bash
# Translate between frameworks
devtb translate <source> <target> <file>

# Translate to all frameworks
devtb translate-all <source> <file>

# List supported frameworks
devtb list-frameworks

# Validate file format
devtb validate <framework> <file>
```

## Supported Frameworks

1. **Bootstrap 5.3.3** - Clean HTML/CSS
2. **DIVI Builder** - Shortcodes
3. **Elementor** - JSON
4. **Avada Fusion** - HTML
5. **Bricks Builder** - JSON
6. **WPBakery** - Shortcodes
7. **Beaver Builder** - Serialized PHP
8. **Gutenberg** - HTML comments
9. **Oxygen Builder** - JSON
10. **Claude AI** - AI-optimized HTML

## Zone Theory

The v4 transform engine classifies element data into zones:

- **STRUCTURAL**: Layout containers (sections, columns, rows)
- **CONTENT**: User-visible content (text, images, videos)
- **STYLING**: Visual presentation (colors, fonts, spacing)
- **BEHAVIORAL**: Interactive features (animations, triggers)
- **META**: Framework-specific settings (IDs, timestamps)

This enables targeted transformations that preserve all metadata.

## Key Files

- `src/translation_bridge/` - Python v4 module
- `translation-bridge/` - PHP v3 module
- `devtb` - Unified CLI wrapper
- `devtb-php` - PHP CLI (legacy)

## Examples

### Elementor to Bootstrap

```bash
# v4 transform (100% metadata)
devtb transform elementor bootstrap page.json

# v3 translate (42% metadata)
devtb translate elementor bootstrap page.json
```

### Analyze Content

```bash
devtb analyze elementor page.json
```

Output:
```
Structure:
  Total elements: 15
  Sections: 3
  Columns: 5
  Widgets: 7

Zone Analysis:
  Total zones: 23
  Metadata preservation: 100%
```

## API Reference

### Python API

```python
from translation_bridge import TransformEngine, ZoneType
from translation_bridge.parsers.elementor import ElementorParser

# Parse Elementor JSON
parser = ElementorParser()
doc = parser.parse_file("page.json")

# Analyze
stats = parser.analyze(doc)

# Transform
engine = TransformEngine()
result = engine.transform(
    doc.to_dict()["elements"],
    zone_types=[ZoneType.CONTENT],
    transformer=my_transformer
)
```

### REST API

The PHP-based REST API remains available:

```bash
# Single translation
POST /wp-json/devtb/v2/translate

# Batch translation
POST /wp-json/devtb/v2/batch-translate

# Get job status
GET /wp-json/devtb/v2/job/{id}
```

## Testing

```bash
# Run all tests
make test

# Run Python tests only
make test-python

# Run PHP tests only
make test-php
```
