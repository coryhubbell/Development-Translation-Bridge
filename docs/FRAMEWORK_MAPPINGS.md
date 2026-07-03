# 🗺 Framework Mappings - Component Translation Reference

## **How Components Map Between Frameworks**

This guide shows how Translation Bridge maps components across the 14 supported frameworks.

---

## 📋 Universal Component Types

Translation Bridge uses **30+ universal component types** that all frameworks can understand:

| Universal Type | Description | All Frameworks Support |
|---------------|-------------|----------------------|
| `container` | Main layout wrapper | ✅ |
| `row` | Horizontal layout | ✅ |
| `column` | Vertical section | ✅ |
| `heading` | Title/heading text | ✅ |
| `text` | Paragraph/content | ✅ |
| `image` | Images | ✅ |
| `button` | Clickable buttons | ✅ |
| `card` | Card/box component | ✅ |
| `slider` | Image/content slider | ✅ |
| `accordion` | Collapsible content | ✅ |
| `tabs` | Tabbed content | ✅ |
| `testimonial` | Customer testimonial | ✅ |
| `pricing-table` | Pricing display | ✅ |
| `form` | Contact/input forms | ✅ |
| `nav` | Navigation menu | ✅ |
| `icon` | Icon display | ✅ |
| `divider` | Visual separator | ✅ |
| `spacer` | Vertical spacing | ✅ |
| `gallery` | Image gallery | ✅ |
| `video` | Video embed | ✅ |
| `map` | Google Maps | ✅ |
| `counter` | Animated counter | ✅ |
| `progress` | Progress bar | ✅ |
| `social-icons` | Social media links | ✅ |
| `list` | Bullet/numbered list | ✅ |
| `blockquote` | Quoted text | ✅ |
| `alert` | Notification box | ✅ |
| `cta` | Call-to-action | ✅ |
| `portfolio` | Portfolio grid | ✅ |
| `countdown` | Countdown timer | ✅ |

---

## 🔄 Framework-Specific Mappings

### **Button Component**

#### **Bootstrap → All Frameworks**

```html
<!-- Bootstrap Input -->
<button class="btn btn-primary btn-lg">Click Me</button>
```

**Maps to:**

| Framework | Output |
|-----------|--------|
| **DIVI** | `[et_pb_button button_text="Click Me" button_alignment="left"]` |
| **Elementor** | `{"widgetType":"button","settings":{"text":"Click Me","size":"lg"}}` |
| **Avada** | `[fusion_button size="large" type="primary"]Click Me[/fusion_button]` |
| **Bricks** | `{"name":"button","settings":{"text":"Click Me","size":"large"}}` |

#### **Attributes Mapping**

| Bootstrap Class | DIVI | Elementor | Avada | Bricks |
|----------------|------|-----------|-------|--------|
| `btn-primary` | default | `type: "primary"` | `type="primary"` | `buttonStyle: "primary"` |
| `btn-lg` | custom_button="on" | `size: "lg"` | `size="large"` | `size: "large"` |
| `btn-block` | `button_alignment="center"` | `align: "justify"` | `stretch="yes"` | `width: "100%"` |

---

### **Heading Component**

#### **Bootstrap → All Frameworks**

```html
<!-- Bootstrap Input -->
<h2 class="display-4 text-center">Welcome</h2>
```

**Maps to:**

| Framework | Output |
|-----------|--------|
| **DIVI** | `[et_pb_text]<h2 class="text-center">Welcome</h2>[/et_pb_text]` |
| **Elementor** | `{"widgetType":"heading","settings":{"title":"Welcome","tag":"h2"}}` |
| **Avada** | `[fusion_title size="2" content_align="center"]Welcome[/fusion_title]` |
| **Bricks** | `{"name":"heading","settings":{"text":"Welcome","tag":"h2"}}` |

---

### **Card/Box Component**

#### **Bootstrap → All Frameworks**

```html
<!-- Bootstrap Input -->
<div class="card">
  <img src="image.jpg" class="card-img-top">
  <div class="card-body">
    <h5 class="card-title">Title</h5>
    <p class="card-text">Description</p>
    <a href="#" class="btn btn-primary">Read More</a>
  </div>
</div>
```

**Maps to:**

| Framework | Output Structure |
|-----------|-----------------|
| **DIVI** | `et_pb_blurb` (icon box module) |
| **Elementor** | `icon-box` widget |
| **Avada** | `fusion_flip_box` or `fusion_content_box` |
| **Bricks** | `icon-box` element |

---

### **Slider/Carousel Component**

#### **Component Equivalents**

| Universal | Bootstrap | DIVI | Elementor | Avada | Bricks |
|-----------|-----------|------|-----------|-------|--------|
| `slider` | `.carousel` | `et_pb_slider` | `slides` | `fusion_slider` | `carousel` |

**Key Attributes:**

| Feature | Bootstrap | DIVI | Elementor | Avada | Bricks |
|---------|-----------|------|-----------|-------|--------|
| Auto-play | `data-ride="carousel"` | `auto="on"` | `autoplay: "yes"` | `autoplay="yes"` | `settings.autoplay` |
| Speed | `data-interval="3000"` | `auto_speed="3000"` | `autoplay_speed: 3000` | `animation_speed="300"` | `settings.delay` |
| Navigation | `.carousel-control-*` | `show_arrows="on"` | `navigation: "arrows"` | `hide_nav_on_mobile="no"` | `settings.nav` |

---

## 🎨 Layout Structure Mappings

### **Grid System**

#### **Bootstrap 12-Column Grid**

```html
<div class="container">
  <div class="row">
    <div class="col-md-6">Half width</div>
    <div class="col-md-6">Half width</div>
  </div>
</div>
```

#### **DIVI Columns**

```
[et_pb_section]
  [et_pb_row]
    [et_pb_column type="1_2"]Half width[/et_pb_column]
    [et_pb_column type="1_2"]Half width[/et_pb_column]
  [/et_pb_row]
[/et_pb_section]
```

#### **Elementor Sections**

```json
{
  "elType": "section",
  "elements": [{
    "elType": "column",
    "settings": {"_column_size": 50},
    "elements": [...]
  }]
}
```

#### **Avada Containers**

```
[fusion_builder_container]
  [fusion_builder_row]
    [fusion_builder_column type="1_2"]Half[/fusion_builder_column]
    [fusion_builder_column type="1_2"]Half[/fusion_builder_column]
  [/fusion_builder_row]
[/fusion_builder_container]
```

#### **Bricks Layout**

```json
{
  "name": "section",
  "children": [{
    "name": "container",
    "settings": {"width": "50%"}
  }]
}
```

### **Column Size Conversions**

| Bootstrap | DIVI | Elementor | Avada | Bricks |
|-----------|------|-----------|-------|--------|
| `col-12` | `type="4_4"` | `_column_size: 100` | `type="1_1"` | `width: "100%"` |
| `col-6` | `type="1_2"` | `_column_size: 50` | `type="1_2"` | `width: "50%"` |
| `col-4` | `type="1_3"` | `_column_size: 33.33` | `type="1_3"` | `width: "33.33%"` |
| `col-3` | `type="1_4"` | `_column_size: 25` | `type="1_4"` | `width: "25%"` |
| `col-8` | `type="2_3"` | `_column_size: 66.66` | `type="2_3"` | `width: "66.66%"` |

---

## 🎯 Styling & Attributes

### **Color Mapping**

| Concept | Bootstrap | DIVI | Elementor | Avada | Bricks |
|---------|-----------|------|-----------|-------|--------|
| Primary color | `.text-primary` | `text_color="#2ea3f2"` | `color: "primary"` | `color="primary"` | `color: "var(--primary)"` |
| Background | `.bg-primary` | `background_color="#2ea3f2"` | `background_color: "#2ea3f2"` | `background_color="#2ea3f2"` | `backgroundColor: "#2ea3f2"` |

### **Spacing Mapping**

| Bootstrap | DIVI | Elementor | Avada | Bricks |
|-----------|------|-----------|-------|--------|
| `p-3` (padding) | `custom_padding="20px\|20px\|20px\|20px"` | `padding: {top:20,right:20,bottom:20,left:20}` | `padding="20px"` | `padding: "20px"` |
| `m-4` (margin) | `custom_margin="30px\|30px\|30px\|30px"` | `margin: {top:30,right:30,bottom:30,left:30}` | `margin="30px"` | `margin: "30px"` |

### **Typography Mapping**

| Feature | Bootstrap | DIVI | Elementor | Avada | Bricks |
|---------|-----------|------|-----------|-------|--------|
| Font size | `.h1`, `.display-4` | `header_font_size="36px"` | `size: 36` | `font_size="36px"` | `fontSize: "36px"` |
| Font weight | `.fw-bold` | `header_font="Roboto\|700"` | `weight: 700` | `font_weight="700"` | `fontWeight: "700"` |
| Text align | `.text-center` | `text_orientation="center"` | `align: "center"` | `text_align="center"` | `textAlign: "center"` |

---

## 🔄 Special Component Mappings

### **Icon Component**

| Framework | Implementation |
|-----------|---------------|
| **Bootstrap** | `<i class="bi bi-heart"></i>` (Bootstrap Icons) |
| **DIVI** | `[et_pb_blurb use_icon="on" font_icon="||divi||400"]` |
| **Elementor** | `{"widgetType":"icon","settings":{"icon":"fa fa-heart"}}` |
| **Avada** | `[fusion_fontawesome icon="fa-heart"]` |
| **Bricks** | `{"name":"icon","settings":{"icon":"fas fa-heart"}}` |

### **Testimonial Component**

| Framework | Structure |
|-----------|-----------|
| **Bootstrap** | Custom card with blockquote |
| **DIVI** | `et_pb_testimonial` |
| **Elementor** | `testimonial` widget |
| **Avada** | `fusion_testimonial` |
| **Bricks** | `testimonial` element |

**Common Attributes:**
- Author name
- Author image
- Company/title
- Rating (stars)
- Quote text

---

## ⚙️ Advanced Mappings

### **Responsive Settings**

| Concept | Bootstrap | Elementor | Bricks |
|---------|-----------|-----------|--------|
| Hide on mobile | `.d-none .d-md-block` | `hide_mobile: "yes"` | `settings.hide: ["mobile"]` |
| Show only mobile | `.d-block .d-md-none` | `hide_desktop: "yes"` | `settings.hide: ["desktop"]` |
| Column stacking | `.col-12 .col-md-6` | Auto-responsive | `settings.flexDirection: "column"` |

### **Animation Mappings**

| Effect | Bootstrap | DIVI | Elementor | Avada | Bricks |
|--------|-----------|------|-----------|-------|--------|
| Fade in | Custom JS | `animation="fade_in"` | `animation: "fadeIn"` | `animation_type="fadeIn"` | `animation: "fade"` |
| Slide up | Custom JS | `animation="slide"` | `animation: "slideInUp"` | `animation_type="slideInUp"` | `animation: "slide"` |
| Bounce | Custom JS | `animation="bounce"` | `animation: "bounce"` | `animation_type="bounce"` | `animation: "bounce"` |

---

## 📖 Using Mappings

### **Check Supported Elements**

```bash
# List what each framework supports
devtb list-supported-elements bootstrap
devtb list-supported-elements elementor
devtb list-supported-elements avada
```

### **Conversion with Mapping Info**

```bash
# See how components map during conversion
devtb translate --show-mappings bootstrap divi component.html
```

### **Custom Mapping Overrides**

```php
// Advanced: Override default mappings
$translator->set_custom_mapping('button', 'custom-button-type');
```

---

## 🎓 Best Practices

1. **Understand Universal Types** - Learn the 30 universal component types
2. **Framework Strengths** - Use each framework's strengths (see chart above)
3. **Test Mappings** - Always test conversions with `--verbose` flag
4. **Fallback Gracefully** - Unsupported elements use generic fallbacks
5. **Manual Touch-ups** - Some complex custom components need 2-5% manual adjustment

---

## 📞 Need Help?

- Review [Translation Bridge Guide](TRANSLATION_BRIDGE.md)
- Check [Conversion Examples](CONVERSION_EXAMPLES.md)
- Ask in [Discord](https://discord.gg/devtb)

---

<div align="center">

**Framework Mappings** - Understanding the Universal Component Model

Part of DevelopmentTranslation Bridge™ Translation Bridge

</div>
