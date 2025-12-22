/**
 * Business Linker - Block Editor Panel
 *
 * Adds a "Linked Business" panel to the Gutenberg sidebar
 * for Events, Venues, and Organizers.
 *
 * @package BusinessDirectory
 */

(function(wp) {
    const { registerPlugin } = wp.plugins;
    const { PluginDocumentSettingPanel } = wp.editPost;
    const { SelectControl, Spinner, ExternalLink } = wp.components;
    const { useSelect, useDispatch } = wp.data;
    const { useState, useEffect } = wp.element;

    /**
     * Business Linker Panel Component
     */
    const BusinessLinkerPanel = () => {
        const [isLoading, setIsLoading] = useState(false);

        // Get current post type
        const postType = useSelect((select) => {
            return select('core/editor').getCurrentPostType();
        }, []);

        // Only show for Events, Venues, Organizers
        const allowedTypes = ['tribe_events', 'tribe_venue', 'tribe_organizer'];
        if (!allowedTypes.includes(postType)) {
            return null;
        }

        // Get current meta value
        const linkedBusiness = useSelect((select) => {
            const meta = select('core/editor').getEditedPostAttribute('meta');
            return meta ? (meta.bd_linked_business || 0) : 0;
        }, []);

        // Get edit post dispatch
        const { editPost } = useDispatch('core/editor');

        // Handle selection change
        const handleChange = (value) => {
            const businessId = parseInt(value, 10) || 0;
            editPost({ meta: { bd_linked_business: businessId } });
        };

        // Get business options from localized data
        const options = window.bdBusinessOptions || [
            { value: 0, label: '— Select a Business —' }
        ];

        // Get selected business info
        const selectedBusiness = options.find(opt => opt.value === linkedBusiness);

        // Determine help text based on post type
        let helpText = '';
        if (postType === 'tribe_events') {
            helpText = 'Link this event to a business from the directory.';
        } else if (postType === 'tribe_venue') {
            helpText = 'Events at this venue will automatically link to this business.';
        } else if (postType === 'tribe_organizer') {
            helpText = 'Events by this organizer can link to this business.';
        }

        return wp.element.createElement(
            PluginDocumentSettingPanel,
            {
                name: 'bd-linked-business',
                title: 'Linked Business',
                className: 'bd-linked-business-panel'
            },
            wp.element.createElement(
                SelectControl,
                {
                    label: 'Select Business',
                    value: linkedBusiness,
                    options: options,
                    onChange: handleChange,
                    help: helpText
                }
            ),
            linkedBusiness > 0 && wp.element.createElement(
                'p',
                { style: { marginTop: '10px' } },
                wp.element.createElement(
                    ExternalLink,
                    {
                        href: '/wp-admin/post.php?post=' + linkedBusiness + '&action=edit',
                        style: { fontSize: '12px' }
                    },
                    'Edit Business →'
                )
            )
        );
    };

    // Register the plugin
    registerPlugin('bd-business-linker', {
        render: BusinessLinkerPanel,
        icon: 'store'
    });

})(window.wp);
