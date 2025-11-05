# WP RSS Importer

A WordPress plugin that imports content from RSS, Atom, and other syndication feeds with advanced filtering and category management capabilities.

## Features

- **Multiple Feed Types**: Support for RSS/Atom feeds AND WordPress REST API
- **Feed Source Management**: Easy-to-use admin interface for adding, editing, and deleting feed sources
- **Automatic Imports**: Scheduled automatic fetching using WordPress Cron
- **Category System**: Organize feed sources with custom categories
- **Keyword Filtering**: Filter feed items by keywords in title or content
- **Custom Post Types**: Stores feed items and sources as WordPress custom post types
- **Featured Images**: Automatically imports and sets featured images from feeds
- **Rich Content**: Imports title, author, excerpt (250 characters), and source link
- **Flexible Shortcode**: Display feeds anywhere with customizable parameters
- **Mura Theme Integration**: Styled to integrate seamlessly with Mura theme

## Installation

1. Download or clone this repository
2. Upload the `wp-rss-importer` folder to your `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Navigate to 'RSS Importer' in your WordPress admin menu

## Usage

### Adding a Feed Source

#### RSS/Atom Feed

1. Go to **RSS Importer > Add New**
2. Enter a name for your feed source
3. Select **Feed Type**: RSS/Atom Feed
4. Enter the **Feed URL** (RSS or Atom feed URL, e.g., `https://example.com/feed`)
5. Set options:
   - **Limit**: Maximum number of items to import (0 = unlimited)
   - **Keyword Filter**: Only import items containing this keyword
6. Assign a **Category** (optional)
7. Click **Publish**

#### WordPress REST API

For WordPress sites without RSS feeds or for better data quality:

1. Go to **RSS Importer > Add New**
2. Enter a name for your feed source
3. Select **Feed Type**: WordPress REST API
4. Enter the **Site URL** (base WordPress site URL, e.g., `https://revolutionwatch.com`)
   - The plugin automatically appends `/wp-json/wp/v2/posts`
5. Set options:
   - **Limit**: Maximum number of items to import (0 = unlimited, max 100 per request)
   - **Keyword Filter**: Only import items containing this keyword
6. Assign a **Category** (optional)
7. Click **Publish**

**WordPress API Advantages:**
- ✅ Better featured image handling (full resolution, multiple sizes)
- ✅ Richer metadata (categories, tags, custom taxonomies)
- ✅ More reliable author information
- ✅ JSON format (cleaner than XML parsing)
- ✅ Works with sites that don't provide RSS feeds
- ✅ Access to Yoast SEO metadata for better excerpts

### Displaying Feed Items

Use the `[wp-rss-aggregator]` shortcode to display feed items on any page or post.

#### Basic Usage

```
[wp-rss-aggregator]
```

This displays all imported feed items.

#### Shortcode Parameters

| Parameter | Description | Example |
|-----------|-------------|---------|
| `sources` | Comma-separated feed source IDs | `sources="55,56"` |
| `feeds` | Comma-separated feed source slugs | `feeds="feed-one,feed-two"` |
| `exclude` | Comma-separated source IDs to exclude | `exclude="26,14"` |
| `limit` | Number of items to display | `limit="10"` |
| `category` | Category slug(s) | `category="sports"` or `category="sports,tech"` |
| `pagination` | Enable/disable pagination | `pagination="on"` (default) or `pagination="off"` |
| `page` | Starting page number | `page="2"` |
| `cols` | Number of grid columns | `cols="4"` (default), `cols="1"`, `cols="2"`, or `cols="3"` |

#### Examples

**Display 10 items from specific sources:**
```
[wp-rss-aggregator sources="55,56" limit="10"]
```

**Display items from a specific category:**
```
[wp-rss-aggregator category="sports"]
```

**Display items from multiple categories:**
```
[wp-rss-aggregator category="sports,entertainment"]
```

**Display 5 items without pagination:**
```
[wp-rss-aggregator limit="5" pagination="off"]
```

**Display items in 2-column grid:**
```
[wp-rss-aggregator cols="2" limit="6"]
```

**Display items in 3-column grid:**
```
[wp-rss-aggregator cols="3" category="tech" limit="9"]
```

**Display items in 4-column grid (default):**
```
[wp-rss-aggregator cols="4" category="reviews"]
```

**Display items in single column (full width):**
```
[wp-rss-aggregator cols="1" limit="5"]
```

### Using in Theme Files

You can also call the shortcode from within your theme's PHP files:

```php
<?php echo do_shortcode('[wp-rss-aggregator limit="8" category="sports"]'); ?>
```

### Manual Fetch

To manually fetch feeds:

1. Go to **RSS Importer > Fetch Feeds Now**
2. Click **Fetch All Feeds Now** button
3. Or click **Fetch Now** next to individual feed sources

### Settings

Configure global settings at **RSS Importer > Settings**:

- **Update Interval**: How often feeds are automatically fetched (Hourly, Twice Daily, Daily)
- **Global Item Limit**: Default limit for all feeds (individual feed limits override this)

## Feed Item Display

Each feed item displays:

- **Featured Image**: Automatically imported from the feed
- **Title**: Linked to the original source
- **Author**: Original author name (if available)
- **Source**: Feed source name
- **Excerpt**: First 250 characters of content
- **Date**: Publication date
- **Read More Link**: Direct link to original article

## Mura Theme Integration

This plugin is **perfectly integrated** with the Mura theme. The output HTML structure is **identical** to Mura's native posts, meaning:

- ✅ Feed items look exactly like regular posts
- ✅ All Mura theme styles automatically apply
- ✅ Grid layouts work perfectly (cols-1, cols-2, cols-3, cols-4)
- ✅ No custom CSS conflicts
- ✅ Theme customizer changes automatically affect feed items

### How It Works

The plugin outputs the exact same HTML structure as Mura's `template-parts/post/content.php`:

```html
<div class="content-area post-grid cols-4 grid">
  <article class="post-123 post type-post has-post-thumbnail article thumbnail-landscape default">
    <div class="formats-key"></div>
    <div class="post-inner">
      <div class="thumbnail-wrapper">
        <figure class="post-thumbnail">...</figure>
      </div>
      <div class="entry-wrapper">
        <header class="entry-header">
          <h3 class="entry-title">...</h3>
        </header>
        <div class="entry-content excerpt">...</div>
      </div>
    </div>
  </article>
</div>
```

Since this matches Mura's structure exactly, all of Mura's existing CSS automatically styles the feed items. The plugin includes minimal CSS (only for pagination styling).

## Styling & Customization

**No custom styling needed!** Since the plugin outputs the exact same HTML as Mura posts, all styling is handled by your theme.

If you want to customize feed items specifically:

1. Use Mura's theme customizer to change colors, fonts, and spacing globally
2. Add custom CSS targeting `.post-grid` or `.article` classes
3. The plugin's CSS file (`/public/css/public.css`) only handles pagination styling

### Mura Classes Used

Feed items use standard Mura classes (no plugin-specific classes needed):

- `.content-area` - Main container
- `.post-grid` - Grid container
- `.cols-1`, `.cols-2`, `.cols-3`, `.cols-4` - Column layouts
- `.grid` - Grid layout mode
- `.article` - Individual article/post
- `.post-inner` - Inner container
- `.thumbnail-wrapper` - Image wrapper
- `.post-thumbnail` - Featured image
- `.entry-wrapper` - Content wrapper
- `.entry-header` - Header section
- `.entry-title` - Title
- `.entry-content` - Content area
- `.excerpt` - Excerpt text

All these classes are already styled by Mura, so feed items look identical to regular posts.

## Automatic Updates

The plugin automatically fetches feeds based on the schedule set in Settings. By default, feeds are fetched hourly. WordPress Cron handles the scheduling.

## Technical Details

### Custom Post Types

- `feed_source` - Stores feed source information
- `feed_item` - Stores imported feed items

### Taxonomies

- `feed_category` - Categories for organizing feed sources

### Meta Fields

**Feed Sources:**
- `_feed_url` - The RSS/Atom feed URL
- `_feed_limit` - Item limit for this source
- `_keyword_filter` - Keyword filter
- `_last_fetch` - Last fetch timestamp
- `_last_error` - Last error message
- `_last_import_count` - Number of items imported in last fetch

**Feed Items:**
- `_source_permalink` - Original article URL
- `_source_author` - Original author name
- `_source_id` - ID of the feed source

## Troubleshooting

### Feeds Not Updating

1. Check **RSS Importer > Feed Sources** and verify the last fetch time
2. Check for errors in the "Feed Status" meta box
3. Try manually fetching from **RSS Importer > Fetch Feeds Now**
4. Ensure WordPress Cron is working properly

### Invalid Feed URL

**For RSS/Atom feeds:** Make sure you're using the actual RSS/Atom feed URL (e.g., `https://example.com/feed`), not the website URL.

**For WordPress API:** Use the base site URL (e.g., `https://example.com`), not the full API endpoint. The plugin automatically adds `/wp-json/wp/v2/posts`.

### WordPress API Not Working

1. Verify the site is running WordPress (check by visiting `https://example.com/wp-json/`)
2. Some sites may have the REST API disabled - try the RSS feed instead
3. Check the error message in the "Feed Status" meta box for details
4. Ensure the site's REST API is publicly accessible (not requiring authentication)

### Featured Images Not Importing

- Ensure the feed includes images (in content or enclosures)
- Check your server has permission to download remote images
- Verify the `wp-content/uploads` folder is writable

## Requirements

- WordPress 5.0 or higher
- PHP 7.0 or higher

## Support

For issues, questions, or feature requests, please visit the [GitHub repository](https://github.com/nautis/congenial-waddle).

## License

This plugin is licensed under GPL-2.0+.

## Changelog

### Version 1.1.0
- **NEW**: WordPress REST API support for importing from WordPress sites
- Enhanced feed type selection (RSS/Atom or WordPress API)
- Better featured image handling for WordPress API sources
- Improved excerpt generation using Yoast SEO metadata when available
- Better error messages for API-related issues

### Version 1.0.0
- Initial release
- Feed source management
- Category system
- Keyword filtering
- Automatic imports via WP Cron
- Shortcode with multiple parameters
- Mura theme integration
