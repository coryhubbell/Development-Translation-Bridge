# Test Fixtures

This directory contains representative test fixtures for the supported page builder frameworks in DevelopmentTranslation Bridge.

## Fixture Files

| Framework | File | Format | Description |
|-----------|------|--------|-------------|
| Bootstrap | `bootstrap/simple-page.html` | HTML | Clean Bootstrap 5.3 HTML |
| Elementor | `elementor/simple-page.json` | JSON | Elementor widget JSON structure |
| DIVI | `divi/simple-page.txt` | Shortcodes | DIVI Builder shortcodes |
| Gutenberg | `gutenberg/simple-page.html` | Block HTML | WordPress block editor markup |
| WPBakery | `wpbakery/simple-page.txt` | Shortcodes | WPBakery/Visual Composer shortcodes |
| Beaver Builder | `beaver-builder/simple-page.dat` | Serialized PHP | Beaver Builder module data |
| Oxygen | `oxygen/simple-page.json` | JSON | Oxygen Builder JSON structure |
| Bricks | `bricks/simple-page.json` | JSON | Bricks Builder JSON format |
| Avada | `avada/simple-page.txt` | Shortcodes | Avada Fusion Builder shortcodes |
| Claude | `claude/simple-page.html` | HTML | AI-optimized HTML with `data-claude-editable` attributes |

## Fixture Content Structure

Each fixture represents a comprehensive landing page with the following sections:

1. **Hero Section**
   - Main headline (H1)
   - Subheading/description
   - Primary and secondary CTA buttons

2. **Features Section**
   - Section title (H2)
   - Three-column feature grid
   - Each feature: icon, title (H3), description

3. **Testimonials Section**
   - Section title (H2)
   - Three testimonial cards
   - Each testimonial: quote, author name, role/company

4. **CTA/Contact Section**
   - Section title (H2)
   - Description text
   - Contact form (name, email, message fields)

5. **Footer Section**
   - Four-column link grid (Company, Product, Support, Legal)
   - Copyright notice

## Usage in Tests

### Loading a Fixture

```php
$fixture_path = DEVTB_ROOT . '/tests/fixtures/bootstrap/simple-page.html';
$content = file_get_contents($fixture_path);
```

### Using with Translation Bridge

```php
use DEVTB\TranslationBridge\Core\DEVTB_Translator;

$translator = new DEVTB_Translator();
$bootstrap_html = file_get_contents('tests/fixtures/bootstrap/simple-page.html');

// Translate Bootstrap to Elementor
$elementor_json = $translator->translate($bootstrap_html, 'bootstrap', 'elementor');
```

### Test Example

```php
/**
 * @dataProvider frameworkPairsProvider
 */
public function testFixtureTranslation(string $source, string $target) {
    $source_file = "tests/fixtures/{$source}/simple-page.*";
    $files = glob($source_file);

    if (empty($files)) {
        $this->markTestSkipped("No fixture found for {$source}");
    }

    $content = file_get_contents($files[0]);
    $result = $this->translator->translate($content, $source, $target);

    $this->assertNotEmpty($result, "Translation from {$source} to {$target} should produce output");
}
```

## Adding New Fixtures

When adding a new fixture:

1. **Create the directory** if it doesn't exist:
   ```bash
   mkdir tests/fixtures/new-framework
   ```

2. **Create the fixture file** with appropriate extension:
   - `.html` for HTML-based formats
   - `.json` for JSON-based formats
   - `.txt` for shortcode-based formats
   - `.dat` for serialized PHP formats

3. **Include all standard sections** (hero, features, testimonials, CTA, footer)

4. **Use realistic content** that matches the framework's actual format

5. **Update this README** with the new framework entry

## Format Reference

### Bootstrap HTML
Clean, semantic HTML using Bootstrap 5.3 classes:
```html
<section class="hero bg-primary text-white py-5">
    <div class="container">
        <h1 class="display-4">Title</h1>
    </div>
</section>
```

### DIVI Shortcodes
```
[et_pb_section][et_pb_row][et_pb_column type="1_1"]
[et_pb_text]Content[/et_pb_text]
[/et_pb_column][/et_pb_row][/et_pb_section]
```

### Elementor JSON
```json
{
    "id": "element-id",
    "elType": "widget",
    "widgetType": "heading",
    "settings": { "title": "Heading" }
}
```

### Gutenberg Blocks
```html
<!-- wp:heading {"level":1} -->
<h1>Heading</h1>
<!-- /wp:heading -->
```

### Claude AI HTML
```html
<h1 data-claude-editable="title" data-claude-type="heading">
    Editable Title
</h1>
```

## Validation

Fixtures are validated during CI/CD to ensure:

- File exists and is readable
- Content is valid for the framework format
- All standard sections are present
- No syntax errors in shortcodes/JSON

## Related Documentation

- [Translation Bridge Documentation](../../docs/TRANSLATION_BRIDGE.md)
- [Framework Mappings](../../docs/FRAMEWORK_MAPPINGS.md)
- [Contributing Guide](../../CONTRIBUTING.md)
