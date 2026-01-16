# /tb-verify - Translation Bridge Verify Command

Verify the quality and completeness of a transformation by comparing source and output files.

## Usage

```bash
/tb-verify <source-file> <output-file> [options]
```

## Arguments

- `source-file`: Original source file
- `output-file`: Transformed output file

## Options

- `-v, --verbose`: Show detailed comparison
- `--strict`: Fail on any metadata loss
- `-o, --output <file>`: Save verification report to file

## Examples

```bash
# Basic verification
/tb-verify original.json transformed.html

# Verbose output with detailed comparison
/tb-verify original.json transformed.html --verbose

# Strict mode - fail on any loss
/tb-verify original.json transformed.html --strict
```

## Verification Checks

### 1. Content Integrity
- All text content preserved
- No content truncation
- Character encoding correct

### 2. Structure Preservation
- Element hierarchy maintained
- Section/column counts match
- Widget types correctly mapped

### 3. Metadata Retention
- All settings preserved (v4 transform)
- Custom CSS maintained
- Responsive settings intact

### 4. Zone Coverage
- All zones accounted for
- No orphaned data
- Proper zone classification

## Output

```
Verification Results
====================

Content Integrity: PASS
  - 15/15 content items preserved
  - 0 items truncated

Structure: PASS
  - 3/3 sections
  - 5/5 columns
  - 7/7 widgets

Metadata: PASS
  - 100% settings preserved
  - All custom CSS intact

Zone Coverage: PASS
  - 23/23 zones mapped
  - 0 orphaned zones

Overall: PASS (100% accuracy)
```

## Exit Codes

- `0`: Verification passed
- `1`: Verification failed
- `2`: File not found or invalid

## Use Cases

1. **CI/CD Pipeline**: Automated quality checks
2. **Before Deployment**: Verify production-ready output
3. **Debugging**: Identify transformation issues
4. **Quality Assurance**: Document transformation accuracy

## See Also

- `/tb-transform` - Transform between frameworks
- `/tb-analyze` - Analyze content structure
