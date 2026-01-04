/**
 * SSO Client-Side Handler
 *
 * Minimal client-side support for SSO.
 * The redirect chain handles all authentication server-side.
 *
 * @package BusinessDirectory
 * @version 2.1.2
 */

(function($) {
    'use strict';

    window.BDSSO = {
        /**
         * Initialize SSO
         */
        init: function() {
            if (typeof bdSSO === 'undefined' || !bdSSO.enabled) {
                return;
            }

            // SSO is handled via server-side redirect chain
            // This script provides the bdSSO object for any future client-side needs
        },

        /**
         * Check if origin is from our network
         *
         * @param {string} origin
         * @return {boolean}
         */
        isNetworkOrigin: function(origin) {
            // Development: .local domains
            if (origin.indexOf('.local') !== -1) {
                return true;
            }
            // Production: Love TriValley network
            var domains = [
                'lovetrivalley.com',
                'lovedublin.com',
                'lovesanramon.com',
                'lovedanville.com',
                'lovepleasanton.com',
                'lovelivermore.com'
            ];
            for (var i = 0; i < domains.length; i++) {
                if (origin.indexOf(domains[i]) !== -1) {
                    return true;
                }
            }
            return false;
        }
    };

    // Initialize on DOM ready
    $(document).ready(function() {
        BDSSO.init();
    });

})(jQuery);
