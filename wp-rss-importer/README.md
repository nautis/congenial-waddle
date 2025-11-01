# WP RSS Importer

A WordPress plugin that imports content from RSS, Atom, and other syndication feeds with advanced filtering and category management capabilities.

## Features

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

1. Go to **RSS Importer > Add New**
2. Enter a name for your feed source
3. Enter the **Feed URL** (RSS or Atom feed URL)
4. Set options:
   - **Limit**: Maximum number of items to import (0 = unlimited)
   - **Keyword Filter**: Only import items containing this keyword
5. Assign a **Category** (optional)
6. Click **Publish**

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

## Styling

The plugin includes CSS designed to integrate with the Mura theme. The styles are responsive and can be customized by:

1. Editing `/public/css/public.css` in the plugin folder
2. Or overriding styles in your theme's CSS file

### Main CSS Classes

- `.wp-rss-aggregator-items` - Container for all feed items
- `.feed-item` - Individual feed item
- `.feed-item-thumbnail` - Featured image
- `.feed-item-title` - Item title
- `.feed-item-meta` - Author and source info
- `.feed-item-excerpt` - Content excerpt
- `.feed-item-footer` - Read more link and date
- `.wp-rss-aggregator-pagination` - Pagination controls

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

Make sure you're using the actual RSS/Atom feed URL (e.g., `https://example.com/feed`), not the website URL.

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

### Version 1.0.0
- Initial release
- Feed source management
- Category system
- Keyword filtering
- Automatic imports via WP Cron
- Shortcode with multiple parameters
- Mura theme integration
