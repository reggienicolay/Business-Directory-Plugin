/**
 * Feature Picker - Admin UI for selecting businesses
 */

(function($) {
	'use strict';

	// State
	var state = {
		selected: [],        // Array of {id, title}
		categories: [],      // Available categories
		currentPage: 1,
		totalPages: 1,
		loading: false
	};

	// DOM Elements
	var $modal, $results, $selected, $preview, $insertBtn;

	/**
	 * Initialize
	 */
	function init() {
		$modal = $('#bd-feature-picker-modal');
		$results = $('#bd-picker-results');
		$selected = $('#bd-picker-selected');
		$preview = $('#bd-picker-shortcode-preview');
		$insertBtn = $('.bd-picker-insert');

		// Bind events
		$('#bd-feature-picker-btn').on('click', openModal);
		$('.bd-picker-close, .bd-picker-cancel, .bd-picker-backdrop').on('click', closeModal);
		$('#bd-picker-search-btn').on('click', searchBusinesses);
		$('#bd-picker-search').on('keypress', function(e) {
			if (e.which === 13) {
				e.preventDefault();
				searchBusinesses();
			}
		});
		$('#bd-picker-category').on('change', searchBusinesses);
		$('#bd-picker-layout').on('change', updateLayoutOptions);
		$('#bd-picker-layout, #bd-picker-columns, #bd-picker-cta').on('change keyup', updatePreview);
		$insertBtn.on('click', insertShortcode);

		// Delegate clicks on result items
		$results.on('click', '.bd-picker-result-item', toggleSelection);

		// Delegate remove clicks on selected tags
		$selected.on('click', '.bd-tag-remove', removeSelected);

		// Enable drag-to-reorder on selected items
		makeSortable();
	}

	/**
	 * Open modal
	 */
	function openModal() {
		$modal.show();
		state.selected = [];
		updateSelectedUI();
		updatePreview();
		loadCategories();
		searchBusinesses();
	}

	/**
	 * Close modal
	 */
	function closeModal() {
		$modal.hide();
		state.selected = [];
		$results.html('<p class="bd-picker-loading">Loading businesses...</p>');
	}

	/**
	 * Load categories for filter dropdown
	 */
	function loadCategories() {
		// Categories come with search results
	}

	/**
	 * Search businesses
	 */
	function searchBusinesses() {
		if (state.loading) return;
		state.loading = true;

		var query = $('#bd-picker-search').val().trim();
		var category = $('#bd-picker-category').val();

		$results.html('<p class="bd-picker-loading">Searching...</p>');

		var apiUrl = bdFeaturePicker.apiUrl + 'feature/search';
		var params = {
			per_page: 30,
			page: 1
		};

		if (query) params.q = query;
		if (category) params.category = category;

		// For local requests, add nonce
		var ajaxOptions = {
			url: apiUrl,
			data: params,
			method: 'GET',
			dataType: 'json'
		};

		if (bdFeaturePicker.isLocal) {
			ajaxOptions.beforeSend = function(xhr) {
				xhr.setRequestHeader('X-WP-Nonce', bdFeaturePicker.nonce);
			};
		}

		$.ajax(ajaxOptions)
			.done(function(response) {
				renderResults(response);
				if (response.categories && response.categories.length > 0) {
					updateCategoryDropdown(response.categories);
				}
			})
			.fail(function(xhr, status, error) {
				$results.html('<p class="bd-picker-no-results">Failed to load businesses. ' + error + '</p>');
			})
			.always(function() {
				state.loading = false;
			});
	}

	/**
	 * Update category dropdown
	 */
	function updateCategoryDropdown(categories) {
		var $dropdown = $('#bd-picker-category');
		var currentVal = $dropdown.val();

		// Only update if empty
		if ($dropdown.find('option').length <= 1) {
			categories.forEach(function(cat) {
				$dropdown.append(
					$('<option>')
						.val(cat.id)
						.text(cat.name + ' (' + cat.count + ')')
				);
			});
		}

		if (currentVal) {
			$dropdown.val(currentVal);
		}
	}

	/**
	 * Render search results
	 */
	function renderResults(response) {
		if (!response.businesses || response.businesses.length === 0) {
			$results.html('<p class="bd-picker-no-results">No businesses found. Try a different search.</p>');
			return;
		}

		var html = '';
		response.businesses.forEach(function(biz) {
			var isSelected = state.selected.some(function(s) { return s.id === biz.id; });
			var selectedClass = isSelected ? ' selected' : '';

			html += '<div class="bd-picker-result-item' + selectedClass + '" data-id="' + biz.id + '" data-title="' + escapeHtml(biz.title) + '">';
			
			// Thumbnail
			if (biz.thumbnail) {
				html += '<img src="' + biz.thumbnail + '" alt="" class="bd-picker-result-thumb">';
			} else {
				html += '<div class="bd-picker-result-thumb" style="display:flex;align-items:center;justify-content:center;">🏢</div>';
			}

			// Info
			html += '<div class="bd-picker-result-info">';
			html += '<p class="bd-picker-result-title">' + escapeHtml(biz.title) + '</p>';
			html += '<div class="bd-picker-result-meta">';
			if (biz.category) {
				html += '<span class="bd-picker-result-category">' + escapeHtml(biz.category) + '</span>';
			}
			if (biz.rating > 0) {
				html += '<span class="bd-picker-result-rating">★ ' + biz.rating.toFixed(1) + '</span>';
			}
			html += '<span class="bd-picker-result-id">ID: ' + biz.id + '</span>';
			html += '</div>';
			html += '</div>';

			// Check indicator
			html += '<div class="bd-picker-result-check"></div>';

			html += '</div>';
		});

		$results.html(html);
	}

	/**
	 * Toggle selection of a business
	 */
	function toggleSelection() {
		var $item = $(this);
		var id = parseInt($item.data('id'), 10);
		var title = $item.data('title');

		var existingIndex = state.selected.findIndex(function(s) { return s.id === id; });

		if (existingIndex > -1) {
			// Remove
			state.selected.splice(existingIndex, 1);
			$item.removeClass('selected');
		} else {
			// Add
			state.selected.push({ id: id, title: title });
			$item.addClass('selected');
		}

		updateSelectedUI();
		updatePreview();
	}

	/**
	 * Remove from selected
	 */
	function removeSelected(e) {
		e.stopPropagation();
		var $tag = $(this).closest('.bd-picker-selected-tag');
		var id = parseInt($tag.data('id'), 10);

		state.selected = state.selected.filter(function(s) { return s.id !== id; });

		// Update result item if visible
		$results.find('.bd-picker-result-item[data-id="' + id + '"]').removeClass('selected');

		updateSelectedUI();
		updatePreview();
	}

	/**
	 * Update selected UI
	 */
	function updateSelectedUI() {
		$('#bd-picker-selected-count').text('(' + state.selected.length + ')');

		if (state.selected.length === 0) {
			$selected.html('<p class="bd-picker-empty">No businesses selected. Click on businesses above to add them.</p>');
			$('.bd-picker-reorder-hint').hide();
			$insertBtn.prop('disabled', true);
		} else {
			var html = '';
			state.selected.forEach(function(item) {
				html += '<div class="bd-picker-selected-tag" data-id="' + item.id + '">';
				html += '<span class="bd-tag-title">' + escapeHtml(item.title) + '</span>';
				html += '<button type="button" class="bd-tag-remove">&times;</button>';
				html += '</div>';
			});
			$selected.html(html);
			$('.bd-picker-reorder-hint').show();
			$insertBtn.prop('disabled', false);

			// Re-init sortable
			makeSortable();
		}
	}

	/**
	 * Make selected tags sortable
	 */
	function makeSortable() {
		if (typeof $.fn.sortable === 'function') {
			$selected.sortable({
				items: '.bd-picker-selected-tag',
				cursor: 'grabbing',
				tolerance: 'pointer',
				update: function() {
					// Update state order
					var newOrder = [];
					$selected.find('.bd-picker-selected-tag').each(function() {
						var id = parseInt($(this).data('id'), 10);
						var item = state.selected.find(function(s) { return s.id === id; });
						if (item) newOrder.push(item);
					});
					state.selected = newOrder;
					updatePreview();
				}
			});
		}
	}

	/**
	 * Update layout options visibility
	 */
	function updateLayoutOptions() {
		var layout = $('#bd-picker-layout').val();

		if (layout === 'card') {
			$('.bd-picker-columns-option').show();
		} else {
			$('.bd-picker-columns-option').hide();
		}
	}

	/**
	 * Update shortcode preview
	 */
	function updatePreview() {
		var ids = state.selected.map(function(s) { return s.id; }).join(',');
		var layout = $('#bd-picker-layout').val();
		var columns = $('#bd-picker-columns').val();
		var ctaText = $('#bd-picker-cta').val().trim();

		var shortcode = '[bd_feature';

		if (state.selected.length === 1) {
			shortcode += ' id="' + ids + '"';
		} else if (state.selected.length > 1) {
			shortcode += ' ids="' + ids + '"';
		}

		shortcode += ' layout="' + layout + '"';

		if (layout === 'card' && columns !== '3') {
			shortcode += ' columns="' + columns + '"';
		}

		if (ctaText && ctaText !== 'View Details') {
			shortcode += ' cta_text="' + ctaText + '"';
		}

		// Add source if configured and cross-site
		if (bdFeaturePicker.sourceUrl) {
			shortcode += ' source="' + bdFeaturePicker.sourceUrl + '"';
		}

		shortcode += ']';

		$preview.text(shortcode);
	}

	/**
	 * Insert shortcode into editor
	 */
	function insertShortcode() {
		var shortcode = $preview.text();

		// Try to insert into TinyMCE
		if (typeof tinymce !== 'undefined' && tinymce.activeEditor && !tinymce.activeEditor.isHidden()) {
			tinymce.activeEditor.execCommand('mceInsertContent', false, shortcode);
		} 
		// Fallback to text editor
		else if (typeof QTags !== 'undefined') {
			QTags.insertContent(shortcode);
		}
		// Fallback to Gutenberg
		else if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch) {
			var block = wp.blocks.createBlock('core/shortcode', { text: shortcode });
			wp.data.dispatch('core/block-editor').insertBlocks(block);
		}
		// Last resort: copy to clipboard
		else {
			copyToClipboard(shortcode);
			alert('Shortcode copied to clipboard:\n\n' + shortcode);
		}

		closeModal();
	}

	/**
	 * Copy to clipboard fallback
	 */
	function copyToClipboard(text) {
		var $temp = $('<textarea>');
		$('body').append($temp);
		$temp.val(text).select();
		document.execCommand('copy');
		$temp.remove();
	}

	/**
	 * Escape HTML
	 */
	function escapeHtml(text) {
		var div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	}

	// Initialize on document ready
	$(document).ready(init);

})(jQuery);
