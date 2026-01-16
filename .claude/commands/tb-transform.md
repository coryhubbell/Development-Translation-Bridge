# /tb-transform - Translation Bridge Transform Command

Transform page builder content using the v4 JSON-native engine with 100% metadata preservation.

## Usage

```bash
/tb-transform <source> <target> <file> [options]
```

## Arguments

- `source`: Source framework (elementor, bricks, oxygen, etc.)
- `target`: Target framework (bootstrap, elementor, divi, etc.)
- `file`: Input file path (JSON or HTML)

## Options

- `-o, --output <file>`: Specify output file path
- `-n, --dry-run`: Preview transformation without writing
- `-d, --debug`: Show detailed debug information

## Examples

```bash
# Transform Elementor JSON to Bootstrap
/tb-transform elementor bootstrap page.json

# Transform with custom output path
/tb-transform elementor bootstrap page.json -o output/page.html

# Preview transformation
/tb-transform elementor bootstrap page.json --dry-run
```

## How It Works

The transform command uses Zone Theory to classify element data:

1. **STRUCTURAL zones**: Layout containers (sections, columns)
2. **CONTENT zones**: User-visible text and media
3. **STYLING zones**: Colors, fonts, spacing
4. **BEHAVIORAL zones**: Animations and interactions
5. **META zones**: Framework-specific settings

Transformations only modify relevant zones while preserving all other data losslessly.

## Comparison with translate

| Feature | transform (v4) | translate (v3) |
|---------|---------------|----------------|
| Engine | Python/JSON-native | PHP/HTML intermediate |
| Metadata | 100% preserved | ~42% preserved |
| Speed | ~0.5s/page | ~30s/page |
| Best for | JSON formats | All frameworks |

## See Also

- `/tb-analyze` - Analyze content without transforming
- `/tb-verify` - Verify transformation quality
