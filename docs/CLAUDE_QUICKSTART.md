# 🤖 Claude AI Quickstart Guide

**Get up and running with Claude AI + DevelopmentTranslation Bridge in under 5 minutes!**

---

## 🎯 What is This?

This framework is **specifically designed** to work with Claude AI. You describe what you want in plain English, and Claude generates production-ready WordPress code using the framework's optimized patterns.

---

## ⚡ Quick Setup

### Prerequisites
1. ✅ WordPress site with this theme activated
2. ✅ Access to Claude AI (claude.ai or API)
3. ✅ Basic WordPress knowledge

### Magic Phrase
**Always start your Claude requests with:**
```
"Using DevelopmentTranslation Bridge framework..."
```

This tells Claude to use the framework's patterns and Bootstrap 5.3.3 components.

---

## 🚀 Instant Code Generation

### Copy-Paste Templates for Claude

#### 1. Create a Custom Post Type
```
Using DevelopmentTranslation Bridge, create a [PostType] custom post type with:
- Fields: [field1, field2, field3]
- Taxonomies: [categories/tags]
- Display: Bootstrap card grid with [X] columns
- Features: Featured image, excerpt, [additional features]
```

**Example:**
```
Using DevelopmentTranslation Bridge, create an Events custom post type with:
- Fields: event_date, event_time, location, ticket_price
- Taxonomies: event_category, event_tags
- Display: Bootstrap card grid with 3 columns
- Features: Featured image, excerpt, AJAX filtering by category
```

#### 2. Add AJAX Functionality
```
Using DevelopmentTranslation Bridge, add AJAX [functionality] that:
- Triggers on: [user action]
- Performs: [server action]
- Returns: [data/HTML]
- Updates: [page element]
- Uses: Bootstrap [component] for display
```

**Example:**
```
Using DevelopmentTranslation Bridge, add AJAX load more posts that:
- Triggers on: button click
- Performs: loads next 6 posts
- Returns: HTML cards
- Updates: #posts-container
- Uses: Bootstrap spinner for loading state
```

#### 3. Create a Page Template
```
Using DevelopmentTranslation Bridge, create a [template name] page template with:
- Layout: [hero/header/sidebar configuration]
- Sections: [section1, section2, section3]
- Components: Bootstrap [components list]
- Data: Display [post type/data source]
- Responsive: [specific breakpoint behavior]
```

**Example:**
```
Using DevelopmentTranslation Bridge, create a Homepage template with:
- Layout: Full-width hero, 3-column features, 2-column content
- Sections: Hero slider, Services cards, Recent posts, Newsletter
- Components: Bootstrap carousel, cards, accordion
- Data: Display latest 6 posts and featured services
- Responsive: Stack columns on mobile, side-by-side on desktop
```

#### 4. Build a Form
```
Using DevelopmentTranslation Bridge, create a [form type] form with:
- Fields: [field list with types]
- Validation: [client/server side rules]
- Submission: [AJAX/standard]
- Success action: [email/save to database/redirect]
- Styling: Bootstrap floating labels with validation states
```

**Example:**
```
Using DevelopmentTranslation Bridge, create a contact form with:
- Fields: name (text), email (email), phone (tel), message (textarea)
- Validation: All required, email format check
- Submission: AJAX with nonce verification
- Success action: Email to admin and save to database
- Styling: Bootstrap floating labels with validation states
```

#### 5. REST API Endpoint
```
Using DevelopmentTranslation Bridge, create a REST API endpoint:
- Route: /wp-json/devtb/v1/[endpoint]
- Method: [GET/POST/PUT/DELETE]
- Authentication: [public/user/admin]
- Parameters: [param1, param2]
- Returns: [JSON structure]
- Cache: [yes/no, duration]
```

**Example:**
```
Using DevelopmentTranslation Bridge, create a REST API endpoint:
- Route: /wp-json/devtb/v1/products
- Method: GET
- Authentication: public
- Parameters: category (optional), per_page (default: 10)
- Returns: JSON array of products with id, title, price, image
- Cache: yes, 1 hour
```

---

## 💡 Pro Tips for Better Results

### 1. Be Specific
❌ **Vague:** "Make a slider"
✅ **Specific:** "Using DevelopmentTranslation Bridge, create a testimonials slider with autoplay, 5-second intervals, fade transition, showing client name, company, and review text"

### 2. Mention Components
Always specify which Bootstrap components you want:
- Cards, Modals, Accordions, Tabs
- Carousel, Offcanvas, Tooltips
- Alerts, Badges, Progress bars

### 3. Include Responsive Behavior
Tell Claude how it should work on different devices:
```
"...with 3 columns on desktop, 2 on tablet, 1 on mobile"
```

### 4. Specify Data Sources
Be clear about where data comes from:
```
"...pulling from custom post type 'products' with ACF field 'price'"
```

### 5. Request Security Features
Always ask for security when needed:
```
"...with nonce verification, user capability check, and data sanitization"
```

---

## 🔥 Power Commands

### Complete Features
```
Using DevelopmentTranslation Bridge, create a complete [feature] system including:
1. Custom post type with fields
2. Admin interface
3. Frontend display with Bootstrap components
4. AJAX filtering and pagination
5. REST API endpoint
6. Shortcode for embedding
```

### Convert to Plugin
```
Using DevelopmentTranslation Bridge, convert the [feature name] we just created 
into a standalone WordPress plugin that:
- Works with any theme
- Maintains Bootstrap compatibility
- Includes activation/deactivation hooks
- Has its own settings page
```

### Performance Optimization
```
Using DevelopmentTranslation Bridge, optimize the [feature] for performance:
- Add caching with transients
- Implement lazy loading
- Minimize database queries
- Add loading states
- Optimize images
```

### Add Gutenberg Block
```
Using DevelopmentTranslation Bridge, create a Gutenberg block for [feature] with:
- Block name: [name]
- Controls: [list of controls]
- Preview: Live preview in editor
- Output: Bootstrap [component]
- Attributes: [list of customizable attributes]
```

---

## 📝 Real-World Examples

### Example 1: E-commerce Product Catalog
```
Using DevelopmentTranslation Bridge, create a product catalog with:
- Products custom post type
- Fields: price, SKU, gallery, stock status, features list
- Categories and tags taxonomies
- Bootstrap card grid display with quick view modal
- AJAX filtering by price range and category
- Add to cart functionality
- REST API for product data
```

### Example 2: Team Directory
```
Using DevelopmentTranslation Bridge, create a team directory with:
- Team Members custom post type
- Fields: position, department, bio, social links, skills
- Departments taxonomy
- Bootstrap cards with flip animation on hover
- Filterable by department with isotope
- Modal popup with full bio
- Org chart visualization option
```

### Example 3: Event Management
```
Using DevelopmentTranslation Bridge, create an event management system:
- Events custom post type with date, time, venue, capacity
- Registration form with AJAX submission
- Attendee counter with live updates
- Calendar view using Bootstrap components
- Email notifications for registrations
- Export attendee list to CSV
- QR code generation for tickets
```

---

## 🎨 Claude + Bootstrap 5.3.3

### Dark Mode Components
```
Using DevelopmentTranslation Bridge, create a [component] with dark mode support:
- Auto-detect system preference
- Toggle switch in navbar
- Smooth transitions
- Saves user preference
```

### Modern Layouts
```
Using DevelopmentTranslation Bridge, create a layout using:
- CSS Grid with Bootstrap utilities
- Floating labels on forms
- Offcanvas navigation for mobile
- Sticky sidebar with scrollspy
```

---

## 🐛 Troubleshooting

### Claude's Code Seems Incomplete?
Add: `"Please provide the complete implementation including all functions and files"`

### Wrong Bootstrap Version?
Specify: `"Using DevelopmentTranslation Bridge with Bootstrap 5.3.3..."`

### Missing Security?
Request: `"Include proper WordPress security with nonces, capability checks, and data sanitization"`

### Need Documentation?
Ask: `"Include inline documentation and usage instructions"`

---

## 🚄 Speed Run: 0 to Feature in 60 Seconds

1. **Open Claude AI**
2. **Copy this template:**
```
Using DevelopmentTranslation Bridge, create a testimonials showcase with:
- Testimonials custom post type
- Fields: client_name, company, rating (1-5), testimonial_text
- Display: Bootstrap carousel with auto-rotation
- Shows: 3 testimonials at once on desktop, 1 on mobile
- Include: Star rating display, company logo upload
- Add: Schema markup for SEO
```
3. **Paste to Claude**
4. **Copy generated code**
5. **Implement in your theme**
6. **Done! ✨**

---

## 📚 Framework Helpers

When Claude generates code, it uses these framework functions:

```php
// The Loop - Enhanced version
$loop = new DEVTB_Loop($args);

// Components - Bootstrap components
devtb_component('card', $data);

// AJAX - Easy AJAX handling
devtb_ajax('action_name', 'callback_function');

// API - REST endpoints
devtb_api_route('/endpoint', 'handler_function');

// Bootstrap Classes - Future-proof
DEVTB_Bootstrap::container('fluid');
DEVTB_Bootstrap::col(6, 'md');
DEVTB_Bootstrap::button('primary', 'lg');
```

---

## 💬 Magic Phrases That Work

### Getting Started
- "Using DevelopmentTranslation Bridge, create..."
- "Using the DEVTB_Loop class, display..."
- "Using Bootstrap 5.3.3 components, build..."

### Enhancements
- "Add dark mode support to..."
- "Make this mobile-responsive with..."
- "Optimize for performance by..."
- "Add accessibility features including..."

### Conversions
- "Convert this to a shortcode..."
- "Make this a reusable component..."
- "Turn this into a plugin..."
- "Create a Gutenberg block version..."

---

## 🎯 Quick Reference Card

| Task | Claude Command Start |
|------|---------------------|
| Custom Post Type | "Using DevelopmentTranslation Bridge, create a CPT..." |
| AJAX Feature | "Add AJAX functionality using devtb_ajax..." |
| Page Template | "Create a page template with Bootstrap layout..." |
| REST API | "Create a REST endpoint using devtb_api_route..." |
| Form | "Build a form with Bootstrap validation..." |
| Component | "Create a reusable component using devtb_component..." |
| Widget | "Create a WordPress widget with Bootstrap styling..." |
| Shortcode | "Create a shortcode that outputs Bootstrap..." |
| Block | "Create a Gutenberg block with Bootstrap..." |

---

## 🚀 Next Steps

1. **Try It Now:** Copy any template above and paste into Claude
2. **Read Full Docs:** Check `/docs/claude-integration.md` for advanced techniques
3. **Join Community:** Share your creations and get help
4. **Contribute:** Submit your patterns back to the framework

---

## 🔗 Quick Links

- **Framework Repo:** https://github.com/coryhubbell/development-translation-bridge
- **Claude AI:** https://claude.ai
- **Bootstrap Docs:** https://getbootstrap.com/docs/5.3/
- **WordPress Codex:** https://developer.wordpress.org/

---

## ⚠️ Remember

1. **Always include** "Using DevelopmentTranslation Bridge" in your prompts
2. **Be specific** about what you want
3. **Test generated code** in a development environment first
4. **Check security** - ensure nonces and sanitization are included
5. **Customize output** - Claude's code is a starting point

---

## 🎉 You're Ready!

You now have everything you need to build WordPress features at lightning speed with Claude AI and DevelopmentTranslation Bridge framework!

**Start building something amazing right now!** 🚀

---

*Last updated: Bootstrap 5.3.3 | WordPress 6.4 | Claude Compatible*
