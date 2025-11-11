# 🔄 The WordPress Loop Guide

**Master WordPress's most powerful feature with WordPress Bootstrap Claude's enhanced Loop system**

---

## 🎯 What is The Loop?

The Loop is WordPress's mechanism for displaying posts. It's called "The Loop" because it loops through each post in your database and displays it according to your template.

**Traditional WordPress Loop:**
```php
if (have_posts()) :
    while (have_posts()) : the_post();
        // Display post content
    endwhile;
endif;
```

**WPBC Enhanced Loop:**
```php
$loop = new WPBC_Loop(['posts_per_page' => 10]);
while ($loop->have_posts()) : $loop->the_post();
    $loop->render_template('card');
endwhile;
```

---

## ⚡ Quick Start

### Basic Loop - Display Recent Posts

```php
<?php
// Create a new loop instance
$loop = new WPBC_Loop([
    'posts_per_page' => 6
]);

// Check if posts exist
if ($loop->have_posts()) : ?>
    <div class="row">
        <?php while ($loop->have_posts()) : $loop->the_post(); ?>
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <?php if (has_post_thumbnail()) : ?>
                        <img src="<?php the_post_thumbnail_url('medium'); ?>" 
                             class="card-img-top" alt="<?php the_title(); ?>">
                    <?php endif; ?>
                    <div class="card-body">
                        <h5 class="card-title"><?php the_title(); ?></h5>
                        <p class="card-text"><?php the_excerpt(); ?></p>
                        <a href="<?php the_permalink(); ?>" class="btn btn-primary">Read More</a>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
<?php endif;
$loop->reset(); // Always reset after custom loops
?>
```

---

## 🚀 WPBC_Loop Class Features

### Enhanced Constructor Options

```php
$loop = new WPBC_Loop([
    // Standard WP_Query arguments
    'post_type' => 'post',
    'posts_per_page' => 10,
    'orderby' => 'date',
    'order' => 'DESC',
    
    // WPBC enhancements
    'cache' => true,              // Enable query caching
    'cache_time' => 3600,         // Cache for 1 hour
    'template' => 'card',         // Default template to use
    'container' => 'row',         // Wrapper class
    'item_class' => 'col-md-4'    // Item wrapper class
]);
```

### Available Methods

```php
// Check for posts
if ($loop->have_posts()) { }

// Get post count
$count = $loop->found_posts();

// Current post position
$position = $loop->current_post();

// Render with template
$loop->render_template('card', ['show_excerpt' => true]);

// Get the WP_Query object
$query = $loop->get_query();

// Reset loop
$loop->reset();
```

---

## 📚 Common Loop Patterns

### 1. Display Custom Post Type

```php
<?php
// Display Products
$products = new WPBC_Loop([
    'post_type' => 'product',
    'posts_per_page' => 12,
    'orderby' => 'menu_order',
    'order' => 'ASC'
]);

if ($products->have_posts()) : ?>
    <div class="products-grid row">
        <?php while ($products->have_posts()) : $products->the_post(); ?>
            <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                <div class="card product-card h-100">
                    <img src="<?php the_post_thumbnail_url('medium'); ?>" 
                         class="card-img-top" alt="<?php the_title(); ?>">
                    <div class="card-body">
                        <h5 class="card-title"><?php the_title(); ?></h5>
                        <p class="price h4 text-primary">$<?php the_field('price'); ?></p>
                        <p class="card-text"><?php the_excerpt(); ?></p>
                    </div>
                    <div class="card-footer bg-transparent">
                        <a href="<?php the_permalink(); ?>" class="btn btn-primary w-100">
                            View Details
                        </a>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
<?php endif;
$products->reset();
?>
```

### 2. Featured Posts Slider

```php
<?php
// Get featured posts
$featured = new WPBC_Loop([
    'meta_key' => 'featured',
    'meta_value' => 'yes',
    'posts_per_page' => 5
]);

if ($featured->have_posts()) : ?>
    <div id="featuredCarousel" class="carousel slide" data-bs-ride="carousel">
        <div class="carousel-inner">
            <?php 
            $i = 0;
            while ($featured->have_posts()) : $featured->the_post(); 
            ?>
                <div class="carousel-item <?php echo $i === 0 ? 'active' : ''; ?>">
                    <img src="<?php the_post_thumbnail_url('full'); ?>" 
                         class="d-block w-100" alt="<?php the_title(); ?>">
                    <div class="carousel-caption d-none d-md-block">
                        <h2><?php the_title(); ?></h2>
                        <p><?php echo wp_trim_words(get_the_excerpt(), 20); ?></p>
                        <a href="<?php the_permalink(); ?>" class="btn btn-primary">
                            Read More
                        </a>
                    </div>
                </div>
            <?php 
            $i++;
            endwhile; 
            ?>
        </div>
        <button class="carousel-control-prev" type="button" 
                data-bs-target="#featuredCarousel" data-bs-slide="prev">
            <span class="carousel-control-prev-icon"></span>
        </button>
        <button class="carousel-control-next" type="button" 
                data-bs-target="#featuredCarousel" data-bs-slide="next">
            <span class="carousel-control-next-icon"></span>
        </button>
    </div>
<?php endif;
$featured->reset();
?>
```

### 3. Related Posts

```php
<?php
// Get current post categories
$categories = wp_get_post_categories(get_the_ID());

// Query related posts
$related = new WPBC_Loop([
    'category__in' => $categories,
    'post__not_in' => [get_the_ID()],
    'posts_per_page' => 3,
    'orderby' => 'rand'
]);

if ($related->have_posts()) : ?>
    <section class="related-posts mt-5">
        <h3>Related Articles</h3>
        <div class="row">
            <?php while ($related->have_posts()) : $related->the_post(); ?>
                <div class="col-md-4 mb-4">
                    <article class="card h-100">
                        <?php if (has_post_thumbnail()) : ?>
                            <img src="<?php the_post_thumbnail_url('medium'); ?>" 
                                 class="card-img-top" alt="<?php the_title(); ?>">
                        <?php endif; ?>
                        <div class="card-body">
                            <h5 class="card-title">
                                <a href="<?php the_permalink(); ?>" class="text-decoration-none">
                                    <?php the_title(); ?>
                                </a>
                            </h5>
                            <p class="card-text">
                                <?php echo wp_trim_words(get_the_excerpt(), 15); ?>
                            </p>
                        </div>
                        <div class="card-footer bg-transparent">
                            <small class="text-muted"><?php echo get_the_date(); ?></small>
                        </div>
                    </article>
                </div>
            <?php endwhile; ?>
        </div>
    </section>
<?php endif;
$related->reset();
?>
```

### 4. Posts with Pagination

```php
<?php
// Get current page
$paged = (get_query_var('paged')) ? get_query_var('paged') : 1;

// Create paginated loop
$blog = new WPBC_Loop([
    'posts_per_page' => 9,
    'paged' => $paged
]);

if ($blog->have_posts()) : ?>
    <div class="row">
        <?php while ($blog->have_posts()) : $blog->the_post(); ?>
            <div class="col-md-4 mb-4">
                <?php $blog->render_template('card'); ?>
            </div>
        <?php endwhile; ?>
    </div>
    
    <!-- Pagination -->
    <nav aria-label="Posts navigation">
        <ul class="pagination justify-content-center">
            <?php
            $big = 999999999;
            $pages = paginate_links([
                'base' => str_replace($big, '%#%', esc_url(get_pagenum_link($big))),
                'format' => '?paged=%#%',
                'current' => $paged,
                'total' => $blog->max_num_pages,
                'type' => 'array',
                'prev_text' => '&laquo;',
                'next_text' => '&raquo;'
            ]);
            
            if ($pages) {
                foreach ($pages as $page) {
                    $active = strpos($page, 'current') ? ' active' : '';
                    echo '<li class="page-item' . $active . '">';
                    echo str_replace('page-numbers', 'page-link', $page);
                    echo '</li>';
                }
            }
            ?>
        </ul>
    </nav>
<?php endif;
$blog->reset();
?>
```

---

## 🎯 Advanced Queries

### Meta Query - Posts with Custom Fields

```php
<?php
// Get products on sale
$sale_products = new WPBC_Loop([
    'post_type' => 'product',
    'meta_query' => [
        'relation' => 'AND',
        [
            'key' => 'on_sale',
            'value' => 'yes',
            'compare' => '='
        ],
        [
            'key' => 'sale_price',
            'value' => 0,
            'compare' => '>',
            'type' => 'NUMERIC'
        ]
    ]
]);
?>
```

### Tax Query - Posts in Specific Categories

```php
<?php
// Get posts from multiple categories
$category_posts = new WPBC_Loop([
    'post_type' => 'post',
    'tax_query' => [
        [
            'taxonomy' => 'category',
            'field' => 'slug',
            'terms' => ['news', 'updates', 'announcements'],
            'operator' => 'IN'
        ]
    ]
]);
?>
```

### Date Query - Recent Posts

```php
<?php
// Posts from last 30 days
$recent = new WPBC_Loop([
    'date_query' => [
        [
            'after' => '30 days ago',
            'inclusive' => true
        ]
    ],
    'posts_per_page' => 10
]);
?>
```

### Author Query

```php
<?php
// Posts by specific author
$author_posts = new WPBC_Loop([
    'author_name' => 'john-doe',
    'posts_per_page' => 5
]);

// Or by author ID
$author_posts = new WPBC_Loop([
    'author' => 123,
    'posts_per_page' => 5
]);
?>
```

### Search Query

```php
<?php
// Search results
$search = new WPBC_Loop([
    's' => get_search_query(),
    'posts_per_page' => 20,
    'post_type' => 'any'
]);

if ($search->have_posts()) : ?>
    <h2>Search Results for: <?php echo get_search_query(); ?></h2>
    <p>Found <?php echo $search->found_posts(); ?> results</p>
    
    <div class="search-results">
        <?php while ($search->have_posts()) : $search->the_post(); ?>
            <article class="search-result mb-4">
                <h3>
                    <a href="<?php the_permalink(); ?>">
                        <?php the_title(); ?>
                    </a>
                </h3>
                <p><?php the_excerpt(); ?></p>
                <small>
                    <?php echo get_post_type_object(get_post_type())->labels->singular_name; ?>
                    | <?php the_date(); ?>
                </small>
            </article>
        <?php endwhile; ?>
    </div>
<?php else : ?>
    <p>No results found for "<?php echo get_search_query(); ?>"</p>
<?php endif;
$search->reset();
?>
```

---

## 💡 Performance Optimization

### 1. Query Caching

```php
<?php
// Enable caching for expensive queries
$cached_loop = new WPBC_Loop([
    'post_type' => 'product',
    'posts_per_page' => 100,
    'meta_key' => 'featured',
    'orderby' => 'meta_value_num',
    'cache' => true,           // Enable caching
    'cache_time' => 3600        // Cache for 1 hour
]);
?>
```

### 2. Optimize Database Queries

```php
<?php
// Only get the fields you need
$optimized = new WPBC_Loop([
    'fields' => 'ids',                      // Only get post IDs
    'no_found_rows' => true,                // Skip pagination count
    'update_post_meta_cache' => false,      // Skip meta cache
    'update_post_term_cache' => false       // Skip term cache
]);

// Then get specific data as needed
foreach ($optimized->posts as $post_id) {
    echo get_the_title($post_id);
}
?>
```

### 3. Lazy Loading

```php
<?php
$lazy_loop = new WPBC_Loop(['posts_per_page' => 20]);

while ($lazy_loop->have_posts()) : $lazy_loop->the_post(); ?>
    <div class="lazy-load-item">
        <img data-src="<?php the_post_thumbnail_url(); ?>" 
             class="lazyload" 
             alt="<?php the_title(); ?>">
        <h3><?php the_title(); ?></h3>
    </div>
<?php endwhile;
$lazy_loop->reset();
?>

<script>
// Initialize lazy loading
document.addEventListener("DOMContentLoaded", function() {
    const lazyImages = document.querySelectorAll('.lazyload');
    const imageObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.classList.remove('lazyload');
                imageObserver.unobserve(img);
            }
        });
    });
    
    lazyImages.forEach(img => imageObserver.observe(img));
});
</script>
```

---

## 🔄 AJAX Loop Loading

### Load More Posts

```php
<!-- Initial posts container -->
<div id="posts-container" class="row">
    <?php
    $ajax_loop = new WPBC_Loop(['posts_per_page' => 6]);
    while ($ajax_loop->have_posts()) : $ajax_loop->the_post(); ?>
        <div class="col-md-4 mb-4">
            <?php $ajax_loop->render_template('card'); ?>
        </div>
    <?php endwhile; ?>
</div>

<?php if ($ajax_loop->max_num_pages > 1) : ?>
    <button id="load-more" class="btn btn-primary" 
            data-page="1" 
            data-max="<?php echo $ajax_loop->max_num_pages; ?>">
        Load More Posts
    </button>
<?php endif;
$ajax_loop->reset();
?>

<script>
jQuery(document).ready(function($) {
    $('#load-more').on('click', function() {
        const button = $(this);
        const page = button.data('page');
        const max = button.data('max');
        
        button.text('Loading...').prop('disabled', true);
        
        $.ajax({
            url: wpbc_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'load_more_posts',
                page: page + 1,
                nonce: wpbc_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#posts-container').append(response.data.html);
                    button.data('page', page + 1);
                    
                    if (page + 1 >= max) {
                        button.hide();
                    } else {
                        button.text('Load More').prop('disabled', false);
                    }
                }
            }
        });
    });
});
</script>
```

---

## 🎨 Loop Templates

### Creating Reusable Templates

Create template files in `/templates/loops/`:

**templates/loops/card.php:**
```php
<div class="card h-100">
    <?php if (has_post_thumbnail()) : ?>
        <img src="<?php the_post_thumbnail_url('medium'); ?>" 
             class="card-img-top" 
             alt="<?php the_title_attribute(); ?>">
    <?php endif; ?>
    <div class="card-body">
        <h5 class="card-title"><?php the_title(); ?></h5>
        <?php if (isset($args['show_excerpt']) && $args['show_excerpt']) : ?>
            <p class="card-text"><?php the_excerpt(); ?></p>
        <?php endif; ?>
        <?php if (isset($args['show_meta']) && $args['show_meta']) : ?>
            <p class="text-muted small">
                <i class="bi bi-calendar"></i> <?php the_date(); ?>
                <i class="bi bi-person ms-2"></i> <?php the_author(); ?>
            </p>
        <?php endif; ?>
    </div>
    <?php if (isset($args['show_button']) && $args['show_button']) : ?>
        <div class="card-footer bg-transparent">
            <a href="<?php the_permalink(); ?>" class="btn btn-primary">
                <?php echo isset($args['button_text']) ? $args['button_text'] : 'Read More'; ?>
            </a>
        </div>
    <?php endif; ?>
</div>
```

**Using the template:**
```php
<?php
$loop = new WPBC_Loop(['posts_per_page' => 6]);

while ($loop->have_posts()) : $loop->the_post();
    $loop->render_template('card', [
        'show_excerpt' => true,
        'show_meta' => true,
        'show_button' => true,
        'button_text' => 'View Post'
    ]);
endwhile;

$loop->reset();
?>
```

---

## 🚀 Quick Copy Patterns

### Blog Grid
```php
$blog = new WPBC_Loop(['posts_per_page' => 9]);
echo '<div class="row">';
while ($blog->have_posts()) : $blog->the_post();
    echo '<div class="col-md-4 mb-4">';
    $blog->render_template('card');
    echo '</div>';
endwhile;
echo '</div>';
$blog->reset();
```

### List View
```php
$list = new WPBC_Loop(['posts_per_page' => 10]);
echo '<div class="list-group">';
while ($list->have_posts()) : $list->the_post();
    echo '<a href="' . get_permalink() . '" class="list-group-item list-group-item-action">';
    echo '<h5>' . get_the_title() . '</h5>';
    echo '<p>' . get_the_excerpt() . '</p>';
    echo '<small>' . get_the_date() . '</small>';
    echo '</a>';
endwhile;
echo '</div>';
$list->reset();
```

### Masonry Grid
```php
$masonry = new WPBC_Loop(['posts_per_page' => 12]);
echo '<div class="row" data-masonry=\'{"percentPosition": true }\'>';
while ($masonry->have_posts()) : $masonry->the_post();
    echo '<div class="col-sm-6 col-lg-4 mb-4">';
    $masonry->render_template('card');
    echo '</div>';
endwhile;
echo '</div>';
$masonry->reset();
```

---

## 🐛 Common Issues & Solutions

### Issue: Loop Not Resetting
```php
// Always reset custom loops
$custom_loop->reset();
// or
wp_reset_postdata();
```

### Issue: Wrong Post Data
```php
// Make sure to call the_post()
while ($loop->have_posts()) : 
    $loop->the_post(); // Don't forget this!
    // Your code here
endwhile;
```

### Issue: Memory Issues with Large Queries
```php
// Use pagination instead of -1
$loop = new WPBC_Loop([
    'posts_per_page' => 50,  // Not -1
    'paged' => get_query_var('paged')
]);
```

### Issue: Duplicate Content
```php
// Exclude already shown posts
$shown_ids = [1, 2, 3]; // Track shown post IDs
$loop = new WPBC_Loop([
    'post__not_in' => $shown_ids
]);
```

---

## 📚 Loop Helper Functions

### wpbc_loop() - Quick Loop Helper
```php
// Shorthand for simple loops
wpbc_loop(['posts_per_page' => 5], 'card');

// Equivalent to:
$loop = new WPBC_Loop(['posts_per_page' => 5]);
while ($loop->have_posts()) : $loop->the_post();
    $loop->render_template('card');
endwhile;
$loop->reset();
```

### wpbc_query_posts() - Get Posts Array
```php
// Get posts as array
$posts = wpbc_query_posts([
    'post_type' => 'product',
    'posts_per_page' => 10
]);

foreach ($posts as $post) {
    echo $post->post_title;
}
```

---

## 🎯 Best Practices

1. **Always reset custom loops** to avoid conflicts
2. **Use specific queries** - avoid posts_per_page => -1
3. **Cache expensive queries** with transients
4. **Optimize queries** - only get what you need
5. **Use pagination** for large result sets
6. **Sanitize user input** in queries
7. **Check if posts exist** before outputting wrapper HTML

---

## 📖 Quick Reference

| Query Parameter | Purpose | Example |
|----------------|---------|---------|
| `post_type` | Post type to query | `'post'`, `'page'`, `'product'` |
| `posts_per_page` | Number of posts | `10`, `-1` (all) |
| `orderby` | Sort posts by | `'date'`, `'title'`, `'menu_order'` |
| `order` | Sort direction | `'DESC'`, `'ASC'` |
| `meta_key` | Custom field key | `'price'`, `'featured'` |
| `meta_value` | Custom field value | `'yes'`, `100` |
| `paged` | Current page | `get_query_var('paged')` |
| `offset` | Skip posts | `3` (skip first 3) |
| `post__in` | Specific posts | `[1, 2, 3]` |
| `post__not_in` | Exclude posts | `[4, 5, 6]` |
| `author` | Author ID | `1` |
| `category_name` | Category slug | `'news'` |
| `tag` | Tag slug | `'featured'` |
| `s` | Search term | `'wordpress'` |

---

## 🚀 Next Steps

1. **Practice** with different loop types
2. **Create** custom templates in `/templates/loops/`
3. **Optimize** your queries for performance
4. **Combine** with AJAX for dynamic loading
5. **Read** the full [WordPress Query documentation](https://developer.wordpress.org/reference/classes/wp_query/)

---

**You now have mastery over The Loop - WordPress's most powerful feature! 🎉**

*Part of WordPress Bootstrap Claude Framework - Optimized for Claude AI Development*
