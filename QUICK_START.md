# ⚡ WordPress Bootstrap Claude - Quick Start Guide

**Get started in 60 seconds with copy-paste examples**

---

## 🚀 Installation (30 seconds)

```bash
# Clone the repository
git clone https://github.com/coryhubbell/wordpress-bootstrap-claude.git

# Navigate to directory
cd wordpress-bootstrap-claude

# You're ready! No build step required for basic usage.
```

---

## 💬 Your First Translation (30 seconds)

### Example 1: Bootstrap → Elementor

**Input File: `hero.html`**
```html
<div class="container">
  <div class="row">
    <div class="col-lg-6">
      <h1 class="display-4">Welcome</h1>
      <p class="lead">Your amazing tagline here</p>
      <a href="#" class="btn btn-primary btn-lg">Get Started</a>
    </div>
  </div>
</div>
```

**Command:**
```bash
wpbc translate bootstrap elementor hero.html
```

**Output: `hero.json`** (Elementor JSON)
```json
[
  {
    "id": "abc12345",
    "elType": "section",
    "settings": {...},
    "elements": [...]
  }
]
```

**✅ Done!** Import this JSON into Elementor.

---

## 🔥 Real-World Examples

### 1. **Agency Client Migration**

**Scenario:** Client has an Elementor site, wants to move to DIVI

```bash
# Export Elementor page
# (Use Elementor's export feature to get JSON)

# Convert to DIVI
wpbc translate elementor divi elementor-page.json > divi-layout.txt

# Import into DIVI
# (Paste divi-layout.txt into DIVI Library)
```

**Time Saved:** 40 hours → 2 minutes

---

### 2. **Developer Workflow**

**Scenario:** You code in Bootstrap, client uses Avada

```bash
# Build your component in Bootstrap (fast development)
# File: pricing-table.html

# Convert to Avada
wpbc translate bootstrap avada pricing-table.html > pricing-table-avada.txt

# Give client the Avada shortcodes
```

**Why this works:** Code in what you know, deliver in what they need.

---

### 3. **Framework Testing**

**Scenario:** Which framework loads fastest for your design?

```bash
# Create in one framework
wpbc translate elementor bootstrap design.json > design.html
wpbc translate elementor divi design.json > design-divi.txt
wpbc translate elementor avada design.json > design-avada.txt
wpbc translate elementor bricks design.json > design-bricks.json

# Test performance of each
# (Bootstrap typically wins for speed)
```

---

## 🎯 Common Commands

### Basic Translation
```bash
# Syntax: wpbc translate [from] [to] [file]

wpbc translate bootstrap divi component.html
wpbc translate elementor bricks page.json
wpbc translate avada bootstrap section.txt
wpbc translate divi elementor module.txt
wpbc translate bricks avada element.json
```

### Batch Translation
```bash
# Convert entire directory
wpbc batch-translate bootstrap elementor components/

# Convert with pattern matching
wpbc batch-translate divi avada sections/*.txt

# Organized output
wpbc batch-translate elementor bootstrap pages/*.json --output=converted/
```

### Advanced Options
```bash
# Preserve IDs
wpbc translate bootstrap elementor --preserve-ids hero.html

# Pretty print JSON
wpbc translate divi elementor --pretty module.txt

# Verbose output
wpbc translate avada bricks --verbose element.txt
```

---

## 🤖 With Claude AI Integration

If you have Claude Code installed:

```bash
# Start Claude Code
claude-code

# Then use natural language:
```

**Claude AI Prompts:**

```
"Convert all my Bootstrap components to Elementor"

"Take this DIVI page and create versions for all 5 frameworks"

"Show me which framework gives the best performance for this design"

"Fix this conversion - the spacing is off"

"Batch convert everything in /components to Bricks"

"Explain what this Avada fusion_builder shortcode does"
```

---

## 📊 Supported Conversions

All 20 combinations work:

| From | To | Example |
|------|----|----|
| Bootstrap | DIVI | `wpbc translate bootstrap divi file.html` |
| Bootstrap | Elementor | `wpbc translate bootstrap elementor file.html` |
| Bootstrap | Avada | `wpbc translate bootstrap avada file.html` |
| Bootstrap | Bricks | `wpbc translate bootstrap bricks file.html` |
| DIVI | Bootstrap | `wpbc translate divi bootstrap file.txt` |
| DIVI | Elementor | `wpbc translate divi elementor file.txt` |
| DIVI | Avada | `wpbc translate divi avada file.txt` |
| DIVI | Bricks | `wpbc translate divi bricks file.txt` |
| Elementor | Bootstrap | `wpbc translate elementor bootstrap file.json` |
| Elementor | DIVI | `wpbc translate elementor divi file.json` |
| Elementor | Avada | `wpbc translate elementor avada file.json` |
| Elementor | Bricks | `wpbc translate elementor bricks file.json` |
| Avada | Bootstrap | `wpbc translate avada bootstrap file.txt` |
| Avada | DIVI | `wpbc translate avada divi file.txt` |
| Avada | Elementor | `wpbc translate avada elementor file.txt` |
| Avada | Bricks | `wpbc translate avada bricks file.txt` |
| Bricks | Bootstrap | `wpbc translate bricks bootstrap file.json` |
| Bricks | DIVI | `wpbc translate bricks divi file.json` |
| Bricks | Elementor | `wpbc translate bricks elementor file.json` |
| Bricks | Avada | `wpbc translate bricks avada file.json` |

---

## 🛠 Troubleshooting

### "Command not found: wpbc"

```bash
# Make the CLI executable
chmod +x wpbc

# Use with ./
./wpbc translate bootstrap divi file.html

# Or add to PATH
export PATH=$PATH:$(pwd)
```

### "Invalid JSON" Error

```bash
# Validate your JSON first
cat file.json | python -m json.tool

# Or use online validator
# https://jsonlint.com
```

### "Conversion accuracy low"

```bash
# Check if element is supported
wpbc list-supported-elements [framework]

# Use verbose mode to see what's happening
wpbc translate --verbose bootstrap divi file.html

# Some complex custom elements may need manual tweaking
```

---

## 📚 Framework-Specific Tips

### **Bootstrap** (HTML/CSS)
- Input format: `.html` files
- Clean, semantic HTML
- Best for developers who code

**Example Input:**
```html
<div class="card">
  <div class="card-body">
    <h5 class="card-title">Title</h5>
    <p class="card-text">Content</p>
  </div>
</div>
```

### **DIVI** (Shortcodes)
- Input format: `.txt` files with shortcodes
- Nested bracket structure
- Best for visual designers

**Example Input:**
```
[et_pb_section][et_pb_row][et_pb_column type="4_4"]
[et_pb_text]Content here[/et_pb_text]
[/et_pb_column][/et_pb_row][/et_pb_section]
```

### **Elementor** (JSON)
- Input format: `.json` files
- Export from Elementor template library
- Best for third-party integrations

**Example Input:**
```json
[
  {
    "id": "abc123",
    "elType": "section",
    "elements": [...]
  }
]
```

### **Avada Fusion** (Shortcodes)
- Input format: `.txt` files
- Advanced shortcode syntax
- Best for complex animations

**Example Input:**
```
[fusion_builder_container]
[fusion_builder_row]
[fusion_builder_column type="1_1"]
Content
[/fusion_builder_column]
[/fusion_builder_row]
[/fusion_builder_container]
```

### **Bricks** (JSON)
- Input format: `.json` files
- Modern JSON structure
- Best for performance

**Example Input:**
```json
[
  {
    "id": "brxe00001",
    "name": "section",
    "settings": {...}
  }
]
```

---

## 🎓 Learning Path

### **Day 1: Basics**
1. ✅ Install WordPress Bootstrap Claude
2. ✅ Run first translation (Bootstrap → Elementor)
3. ✅ Understand input/output formats

### **Week 1: Intermediate**
1. Convert actual project component
2. Try all 5 frameworks
3. Use batch translation
4. Integrate Claude AI

### **Month 1: Advanced**
1. Migrate complete client site
2. Create custom conversion workflows
3. Optimize for performance
4. Build reusable component library

---

## 💡 Pro Tips

### **Tip 1: Start Simple**
Convert one button first, not a whole page. Master basics before complex layouts.

### **Tip 2: Keep Originals**
Always keep original files. Translation is non-destructive but backups never hurt.

### **Tip 3: Framework Strengths**
- Code fast in Bootstrap
- Design pretty in DIVI
- Integrate easily with Elementor
- Animate beautifully in Avada
- Optimize performance with Bricks

### **Tip 4: Batch Processing**
Use `batch-translate` for 10+ files. Way faster than one-by-one.

### **Tip 5: Claude AI for Complex**
For complex conversions or custom elements, ask Claude AI to help refine the output.

---

## 📞 Getting Help

### **Documentation**
- Full docs: [docs/](docs/)
- Translation guide: [docs/TRANSLATION_BRIDGE.md](docs/TRANSLATION_BRIDGE.md)
- Loop guide: [docs/LOOP_GUIDE.md](docs/LOOP_GUIDE.md)

### **Community**
- Discord: [discord.gg/wpbc](https://discord.gg/wpbc)
- GitHub Issues: [github.com/coryhubbell/wordpress-bootstrap-claude/issues](https://github.com/coryhubbell/wordpress-bootstrap-claude/issues)

### **Quick Answers**
```bash
# List all commands
wpbc help

# Get help for specific command
wpbc help translate

# Show version
wpbc version

# List supported elements
wpbc list-supported-elements elementor
```

---

## 🚀 Next Steps

1. **Try Your First Conversion**
   ```bash
   wpbc translate bootstrap elementor your-file.html
   ```

2. **Join the Community**
   - ⭐ Star the repo: [github.com/coryhubbell/wordpress-bootstrap-claude](https://github.com/coryhubbell/wordpress-bootstrap-claude)
   - 💬 Join Discord: [discord.gg/wpbc](https://discord.gg/wpbc)

3. **Read Full Docs**
   - [README.md](README.md) - Complete overview
   - [docs/](docs/) - In-depth guides

---

## ✨ You're Ready!

You now know enough to:
- ✅ Convert between any framework
- ✅ Migrate client sites in minutes
- ✅ Work with any page builder
- ✅ Save 40+ hours per project

**Start translating and never be locked into one framework again!**

---

<div align="center">

**WordPress Bootstrap Claude™ - The Framework That Changes Everything**

Built with ❤️ by the WordPress community

</div>
