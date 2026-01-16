# /tb-analyze - Translation Bridge Analyze Command

Analyze page builder content to understand its structure, content zones, and metadata without making any changes.

## Usage

```bash
/tb-analyze <framework> <file> [options]
```

## Arguments

- `framework`: Source framework (elementor, bricks, oxygen, etc.)
- `file`: Input file path (JSON or HTML)

## Options

- `-o, --output <file>`: Save analysis results to file
- `-d, --debug`: Show detailed debug information

## Examples

```bash
# Analyze an Elementor page
/tb-analyze elementor page.json

# Save analysis to file
/tb-analyze elementor page.json -o analysis.json
```

## Output

The analyze command returns:

### Structure Analysis
- Total elements count
- Sections, columns, and widgets breakdown
- Widget type distribution

### Zone Analysis (Zone Theory)
- Total zones identified
- Zones by type (structural, content, styling, behavioral, meta)
- Metadata preservation potential

### Content Preview
- First 5 content items with paths
- Content types identified

## Example Output

```
Structure:
  Total elements: 15
  Sections: 3
  Columns: 5
  Widgets: 7

Zone Analysis (Zone Theory):
  Total zones: 23
    structural: 8
    content: 7
    styling: 5
    behavioral: 2
    meta: 1
  Metadata preservation: 100%

Widget Types:
  heading: 3
  text-editor: 2
  button: 2

Content Preview:
  [title]: Welcome to Our Site
  [editor]: <p>This is the main content...</p>
```

## Use Cases

1. **Before Migration**: Understand what content will be transformed
2. **Quality Check**: Verify all content is being detected
3. **Debugging**: Identify why certain elements aren't converting correctly
4. **Documentation**: Generate content inventories

## See Also

- `/tb-transform` - Transform between frameworks
- `/tb-verify` - Verify transformation quality
