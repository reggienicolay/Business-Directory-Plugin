/**
 * Business Linker - Admin JS for linking businesses to events/venues/organizers
 *
 * @package BusinessDirectory
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        initBusinessSelectors();
    });

    /**
     * Initialize all business selectors on the page
     */
    function initBusinessSelectors() {
        $('.bd-business-selector').each(function() {
            var $selector = $(this);
            var $searchInput = $selector.find('.bd-business-search');
            var $resultsContainer = $selector.find('.bd-business-search-results');
            var $hiddenInput = $selector.find('input[type="hidden"]');
            var $selectedContainer = $selector.find('.bd-business-selected');
            var $selectedName = $selector.find('.bd-business-selected-name');
            var $removeButton = $selector.find('.bd-business-remove');

            var searchTimeout = null;

            // Search input handler
            $searchInput.on('input', function() {
                var query = $(this).val().trim();

                clearTimeout(searchTimeout);

                if (query.length < 2) {
                    $resultsContainer.removeClass('active').empty();
                    return;
                }

                searchTimeout = setTimeout(function() {
                    searchBusinesses(query, $resultsContainer, $hiddenInput, $selectedContainer, $selectedName, $searchInput);
                }, 300);
            });

            // Hide results when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.bd-business-selector').length) {
                    $resultsContainer.removeClass('active');
                }
            });

            // Focus shows results if populated
            $searchInput.on('focus', function() {
                if ($resultsContainer.children().length > 0) {
                    $resultsContainer.addClass('active');
                }
            });

            // Remove button handler
            $removeButton.on('click', function(e) {
                e.preventDefault();
                $hiddenInput.val('');
                $selectedContainer.hide();
                $selectedName.text('');
                $searchInput.val('').focus();
            });
        });
    }

    /**
     * Search for businesses via AJAX
     */
    function searchBusinesses(query, $resultsContainer, $hiddenInput, $selectedContainer, $selectedName, $searchInput) {
        $.ajax({
            url: bdBusinessLinker.ajaxUrl,
            type: 'GET',
            data: {
                action: 'bd_search_businesses',
                nonce: bdBusinessLinker.nonce,
                search: query
            },
            beforeSend: function() {
                $resultsContainer.html('<div class="bd-business-search-loading">Searching...</div>').addClass('active');
            },
            success: function(response) {
                if (response.success && response.data.length > 0) {
                    var html = '';
                    response.data.forEach(function(business) {
                        var meta = [];
                        if (business.city) meta.push(business.city);
                        if (business.category) meta.push(business.category);

                        html += '<div class="bd-business-search-result" data-id="' + business.id + '" data-name="' + escapeHtml(business.name) + '">';
                        html += '<div class="bd-business-search-result-name">' + escapeHtml(business.name) + '</div>';
                        if (meta.length > 0) {
                            html += '<div class="bd-business-search-result-meta">' + escapeHtml(meta.join(' · ')) + '</div>';
                        }
                        html += '</div>';
                    });
                    $resultsContainer.html(html).addClass('active');

                    // Click handler for results
                    $resultsContainer.find('.bd-business-search-result').on('click', function() {
                        var id = $(this).data('id');
                        var name = $(this).data('name');

                        $hiddenInput.val(id);
                        $selectedName.text(name);
                        $selectedContainer.show();
                        $resultsContainer.removeClass('active').empty();
                        $searchInput.val('');
                    });
                } else {
                    $resultsContainer.html('<div class="bd-business-no-results">No businesses found</div>').addClass('active');
                }
            },
            error: function() {
                $resultsContainer.html('<div class="bd-business-no-results">Search failed</div>').addClass('active');
            }
        });
    }

    /**
     * Escape HTML entities
     */
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

})(jQuery);
