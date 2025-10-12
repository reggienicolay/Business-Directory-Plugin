/**
 * Business Detail Page Scripts
 */
(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Remove the old template back button if it exists
        $('.bd-back-to-directory').remove();
        
        // Create modern back link with SVG arrow
        const backLink = $('<a>', {
            href: '/business-directory/',
            class: 'bd-back-link',
            html: '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M8 0L6.6 1.4 12.2 7H0v2h12.2l-5.6 5.6L8 16l8-8z" transform="rotate(180 8 8)"/></svg> Back to directory'
        });
        
        // Insert at the top of the content wrapper or page content
        if ($('.bd-business-detail-wrapper').length) {
            $('.bd-business-detail-wrapper').prepend(backLink);
        } else if ($('.page-content').length) {
            $('.page-content').prepend(backLink);
        } else if ($('.entry-content').length) {
            $('.entry-content').parent().prepend(backLink);
        } else if ($('.site-main').length) {
            $('.site-main').prepend(backLink);
        }
        
        // Smart back navigation - go back if came from directory
        backLink.on('click', function(e) {
            if (document.referrer && document.referrer.includes('/business-directory')) {
                e.preventDefault();
                window.history.back();
            }
        });
    });
    
})(jQuery);
