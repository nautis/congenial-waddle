/**
 * Admin JavaScript for WP RSS Importer
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Add confirmation to fetch all feeds button
        $('input[name="fetch_all_feeds"]').on('click', function(e) {
            if (!confirm('Are you sure you want to fetch all feeds now? This may take a while.')) {
                e.preventDefault();
                return false;
            }
        });

        // Validate feed URL field
        $('#feed_url').on('blur', function() {
            var url = $(this).val();
            if (url && !isValidUrl(url)) {
                alert('Please enter a valid feed URL (e.g., https://example.com/feed)');
            }
        });

        function isValidUrl(string) {
            try {
                new URL(string);
                return true;
            } catch (_) {
                return false;
            }
        }
    });

})(jQuery);
