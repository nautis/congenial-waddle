# WP RSS Importer

A WordPress plugin that aggregates content from multiple sources including RSS/Atom feeds, WordPress REST API, and the NY Times Article Search API.

## Features

- **Multiple Feed Types**
  - RSS/Atom feeds
  - WordPress REST API (import from any WordPress site)
  - NY Times Article Search API

- **Smart Importing**
  - Keyword filtering to import only relevant content
  - Duplicate detection prevents re-importing existing items
  - Configurable import limits per source
  - Category assignment for imported items

- **Automated Fetching**
  - WP-Cron scheduled imports
  - Manual fetch option from admin
  - Per-source fetch status tracking

- **Admin Interface**
  - Custom post type for managing feed sources
  - Feed status dashboard showing last fetch time and errors
  - Easy configuration with meta boxes

## Requirements

- WordPress 5.0+
- PHP 7.2+
- NY Times API key (only if using NY Times source)

## Installation

```bash
cd /path/to/wordpress/wp-content/plugins/
git clone https://github.com/nautis/congenial-waddle.git wp-rss-importer
```

Or download and upload the `wp-rss-importer` folder to `/wp-content/plugins/`.

Activate through the WordPress admin panel.

## Configuration

### Adding a Feed Source

1. Go to **Feed Sources → Add New**
2. Enter a title for the source
3. Select the feed type:
   - **RSS/Atom Feed**: Enter the feed URL
   - **WordPress REST API**: Enter the site URL (automatically uses `/wp-json/wp/v2/posts`)
   - **NY Times API**: Enter your API key and search query

4. Configure import options:
   - **Limit**: Maximum items to import per fetch
   - **Keyword Filter**: Only import items containing this keyword
   - **Category**: Assign imported items to a category

5. Publish the feed source

### NY Times API Setup

1. Get an API key from [NYT Developer Portal](https://developer.nytimes.com/)
2. Create a new Feed Source with type "NY Times API"
3. Enter your API key
4. Configure search query (default: "watch OR watches")
5. Optionally filter by section, desk, or collection:
   - Section: `Style` or `Technology`
   - Desk: `desk:Foreign`
   - Collection: `collection:/spotlight/podcasts`

### Manual Fetch

Go to **Feed Sources → Fetch Feeds Now** to manually trigger imports for all active sources.

## Imported Content

Imported items are stored as a custom post type (`feed_item`) with:

- Original title and content
- Source attribution
- Original permalink (stored as meta)
- Import timestamp
- Source feed reference

## Hooks & Filters

```php
// Modify content before saving
add_filter('wp_rss_importer_item_content', function($content, $item, $source_id) {
    return $content;
}, 10, 3);

// Modify title before saving
add_filter('wp_rss_importer_item_title', function($title, $item, $source_id) {
    return $title;
}, 10, 3);
```

## File Structure

```
wp-rss-importer/
├── wp-rss-importer.php          # Main plugin file
├── includes/
│   ├── class-wp-rss-importer.php    # Core plugin class
│   ├── class-feed-importer.php      # RSS/Atom importer
│   ├── class-wp-api-importer.php    # WordPress API importer
│   ├── class-nyt-api-importer.php   # NY Times API importer
│   ├── class-cron.php               # Scheduled imports
│   ├── class-admin.php              # Admin interface
│   ├── class-post-types.php         # Custom post types
│   ├── class-permalink-handler.php  # URL handling
│   ├── class-activator.php          # Activation hooks
│   └── class-deactivator.php        # Deactivation hooks
├── admin/
│   ├── css/admin.css
│   └── js/admin.js
└── public/
    └── css/public.css
```

## License

GPL-2.0+

## Changelog

### 1.1.0
- Added WordPress REST API support
- Added NY Times Article Search API support
- Improved admin interface with feed type selection
- Added date filtering for NY Times imports

### 1.0.0
- Initial release
- RSS/Atom feed importing
- Keyword filtering
- Cron-based automatic fetching
