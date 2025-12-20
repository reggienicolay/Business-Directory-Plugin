/**
 * TinyMCE Plugin - Feature Picker Button
 * Adds a button to the TinyMCE editor toolbar
 */

(function() {
    'use strict';

    tinymce.PluginManager.add('bd_feature_picker', function(editor, url) {
        
        // Add button to toolbar
        editor.addButton('bd_feature_picker', {
            title: 'Insert Featured Businesses',
            icon: 'dashicons-store',
            image: url.replace('/js/', '/images/') + 'feature-icon.png',
            onclick: function() {
                // Trigger custom event to open modal
                jQuery(document).trigger('bd-feature-picker-open');
            }
        });

        // Alternative: Add menu item
        editor.addMenuItem('bd_feature_picker', {
            text: 'Featured Businesses',
            icon: 'dashicons-store',
            context: 'insert',
            onclick: function() {
                jQuery(document).trigger('bd-feature-picker-open');
            }
        });
    });

})();
