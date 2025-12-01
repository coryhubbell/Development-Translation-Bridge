# 📘 Getting Started with DevelopmentTranslation Bridge

Welcome to DevelopmentTranslation Bridge! This guide will walk you through everything you need to know to start building WordPress sites 10x faster with AI assistance.

## Table of Contents
- [Prerequisites](#prerequisites)
- [Installation](#installation)
- [First Steps](#first-steps)
- [Basic Configuration](#basic-configuration)
- [Creating Your First Feature](#creating-your-first-feature)
- [Development Workflow](#development-workflow)
- [Troubleshooting](#troubleshooting)

---

## Prerequisites

Before you begin, ensure you have:

### Required
- **PHP 7.4+** (8.0+ recommended)
- **WordPress 5.8** or higher
- **MySQL 5.7+** or MariaDB 10.3+
- **Web Server**: Apache or Nginx

### Optional (for development)
- **Node.js 18+** - For Visual Interface development
- **Composer 2.0+** - For dependency management
- **Docker** - For containerized development
- **Claude AI Account** - For AI-powered editing features
- **Git** - For version control

### Quick Setup (CLI Only)

If you just want to use the translation CLI without WordPress:

```bash
git clone https://github.com/coryhubbell/development-translation-bridge.git
cd development-translation-bridge
./setup.sh
./devtb --help
```

---

## Installation

### Method 1: Direct Download (Easiest)

1. **Download the framework:**
   ```bash
   wget https://github.com/coryhubbell/development-translation-bridge/archive/main.zip
   ```

2. **Extract to themes directory:**
   ```bash
   cd /path/to/wordpress/wp-content/themes/
   unzip main.zip
   mv development-translation-bridge-main development-translation-bridge
   ```

3. **Activate the theme:**
   - Go to WordPress Admin → Appearance → Themes
   - Find "DevelopmentTranslation Bridge"
   - Click "Activate"

### Method 2: Git Clone (For Developers)

```bash
# Navigate to themes directory
cd /path/to/wordpress/wp-content/themes/

# Clone the repository
git clone https://github.com/coryhubbell/development-translation-bridge.git

# Enter directory
cd development-translation-bridge

# Install dependencies (optional)
npm install
composer install
```

---

## First Steps

### 1. Verify Installation

After activation, you should see:
- ✅ "DevelopmentTranslation Bridge" as your active theme
- ✅ Bootstrap styling applied to your site
- ✅ New menu locations available
- ✅ Framework options in Customizer

### 2. Set Up Basic Configuration

```php
// In wp-config.php, add these for development:
define('WP_DEBUG', true);
define('DEVTB_DEBUG', true);  // Framework debug mode
define('DEVTB_CACHE', false); // Disable caching during development
```

### 3. Configure Menus

1. Go to **Appearance → Menus**
2. Create a new menu called "Primary Navigation"
3. Add your pages/posts
4. Assign to "Primary Menu" location
5. Save

---

## Creating Your First Feature

### Example: Creating a Team Members Section

#### Step 1: Tell Claude What You Need

```
"Using DevelopmentTranslation Bridge, create a Team Members custom post type 
with name, position, bio, and photo. Display in a Bootstrap card grid."
```

#### Step 2: Implement Claude's Code

```php
// File: ai-patterns/custom-post-types/team-members.php

class DEVTB_Team_Members {
    
    public function __construct() {
        add_action('init', [$this, 'register_post_type']);
        add_shortcode('team_grid', [$this, 'render_team_grid']);
    }
    
    public function register_post_type() {
        register_post_type('team_member', [
            'labels' => [
                'name' => 'Team Members',
                'singular_name' => 'Team Member',
            ],
            'public' => true,
            'has_archive' => true,
            'supports' => ['title', 'editor', 'thumbnail'],
            'menu_icon' => 'dashicons-groups',
            'show_in_rest' => true,
        ]);
    }
    
    public function render_team_grid($atts) {
        $loop = new DEVTB_Loop([
            'post_type' => 'team_member',
            'posts_per_page' => -1
        ]);
        
        ob_start();
        ?>
        <div class="team-grid row">
            <?php while ($loop->have_posts()) : $loop->the_post(); ?>
                <div class="col-md-4 mb-4">
                    <?php devtb_component('card', [
                        'title' => get_the_title(),
                        'content' => get_the_excerpt(),
                        'image' => get_the_post_thumbnail_url()
                    ]); ?>
                </div>
            <?php endwhile; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}

// Initialize
new DEVTB_Team_Members();
```

#### Step 3: Use It

1. Save the file in your theme
2. Include it in `functions.php`
3. Add team members in WordPress Admin
4. Use shortcode: `[team_grid]`

---

## Development Workflow

### Working with Claude

1. **Start with a clear request** mentioning DevelopmentTranslation Bridge
2. **Implement the generated code** in the appropriate location
3. **Test and iterate** with follow-up requests
4. **Export as a plugin** when feature is complete

### File Structure

```
development-translation-bridge/
├── 📁 ai-patterns/      # Your Claude-generated patterns
├── 📁 core/            # Framework core (don't modify)
├── 📁 templates/       # Template files
└── 📁 assets/          # CSS, JS, images
```

---

## Troubleshooting

### Common Issues

#### Bootstrap Not Loading
- Check if another theme/plugin is loading Bootstrap
- Verify file paths in functions.php
- Clear browser cache

#### The Loop Not Working
- Ensure DEVTB_Loop class is loaded
- Check for PHP errors in debug.log
- Verify WordPress query args

#### AJAX Errors
- Check nonce verification
- Verify admin-ajax.php URL
- Look for JavaScript console errors

### Getting Help

- 📖 Read the other guides in `/docs`
- 🐛 [Report issues on GitHub](https://github.com/coryhubbell/development-translation-bridge/issues)
- 💬 Ask Claude for clarification

---

## Next Steps

1. 📗 **[Claude Integration Guide](claude-integration.md)** - Master AI development
2. 📙 **[The Loop Mastery](the-loop.md)** - Advanced WordPress queries
3. 📕 **[Bootstrap Components](bootstrap-components.md)** - UI component library
4. 📓 **[API Development](api-development.md)** - REST API creation

---

**You're ready to build amazing WordPress sites with AI! 🚀**
