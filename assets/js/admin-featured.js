/**
 * Featured Businesses Admin JavaScript
 *
 * Handles drag-to-reorder, search modal, and AJAX operations.
 *
 * @package BusinessDirectory
 */

(function ($) {
	'use strict';

	const FeaturedAdmin = {
		// State
		searchTimer: null,
		currentCount: 0,

		/**
		 * Initialize
		 */
		init: function () {
			this.currentCount = $('#bd-featured-list .bd-featured-item').length;
			this.initSortable();
			this.bindEvents();
		},

		/**
		 * Initialize jQuery UI Sortable
		 */
		initSortable: function () {
			const self = this;

			$('#bd-featured-list').sortable({
				items: '.bd-featured-item', // Only sort actual items, not empty state
				handle: '.bd-featured-handle',
				placeholder: 'bd-featured-item ui-sortable-placeholder',
				axis: 'y',
				cursor: 'grabbing',
				tolerance: 'pointer',
				update: function (event, ui) {
					self.saveOrder();
					self.updatePositionNumbers();
				}
			});
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function () {
			const self = this;

			// Add button - open modal
			$('#bd-add-featured').on('click', function () {
				if ($(this).is(':disabled')) {
					self.showToast(bdFeatured.strings.maxReached, 'error');
					return;
				}
				self.openModal();
			});

			// Close modal
			$('.bd-featured-modal-close').on('click', function () {
				self.closeModal();
			});

			// Close modal on backdrop click
			$('#bd-featured-modal').on('click', function (e) {
				if ($(e.target).is('#bd-featured-modal')) {
					self.closeModal();
				}
			});

			// Close on Escape key
			$(document).on('keydown', function (e) {
				if (e.key === 'Escape' && $('#bd-featured-modal').is(':visible')) {
					self.closeModal();
				}
			});

			// Search input
			$('#bd-featured-search').on('input', function () {
				const query = $(this).val().trim();
				clearTimeout(self.searchTimer);

				if (query.length < 2) {
					$('#bd-featured-results').html(
						'<p class="bd-featured-results-hint">' + bdFeatured.strings.searchPlaceholder + '</p>'
					);
					return;
				}

				$('#bd-featured-results').html(
					'<p class="bd-featured-results-loading">Searching...</p>'
				);

				self.searchTimer = setTimeout(function () {
					self.searchBusinesses(query);
				}, 300);
			});

			// Add business from search results (delegated)
			$(document).on('click', '.bd-featured-result-add', function () {
				const $btn = $(this);
				const businessId = $btn.data('id');
				self.addBusiness(businessId, $btn);
			});

			// Remove business
			$(document).on('click', '.bd-featured-remove', function () {
				const $btn = $(this);
				const businessId = $btn.data('id');
				self.removeBusiness(businessId, $btn);
			});
		},

		/**
		 * Open the add modal
		 */
		openModal: function () {
			$('#bd-featured-modal').fadeIn(200);
			$('#bd-featured-search').val('').focus();
			$('#bd-featured-results').html(
				'<p class="bd-featured-results-hint">Type to search for businesses</p>'
			);
			$('body').css('overflow', 'hidden');
		},

		/**
		 * Close the add modal
		 */
		closeModal: function () {
			$('#bd-featured-modal').fadeOut(200);
			$('body').css('overflow', '');
		},

		/**
		 * Search businesses via AJAX
		 */
		searchBusinesses: function (query) {
			const self = this;

			$.ajax({
				url: bdFeatured.ajaxUrl,
				method: 'POST',
				data: {
					action: 'bd_featured_search',
					nonce: bdFeatured.nonce,
					search: query
				},
				success: function (response) {
					if (response.success) {
						self.renderSearchResults(response.data.businesses);
					} else {
						self.showToast(response.data.message || bdFeatured.strings.error, 'error');
					}
				},
				error: function () {
					self.showToast(bdFeatured.strings.error, 'error');
				}
			});
		},

		/**
		 * Render search results
		 */
		renderSearchResults: function (businesses) {
			const self = this;
			const $results = $('#bd-featured-results');

			if (!businesses || businesses.length === 0) {
				$results.html(
					'<div class="bd-featured-no-results">' +
					'<span class="dashicons dashicons-search"></span>' +
					'<p>' + bdFeatured.strings.noResults + '</p>' +
					'</div>'
				);
				return;
			}

			let html = '';
			businesses.forEach(function (business) {
				html += '<div class="bd-featured-result-item">';
				html += '<div class="bd-featured-result-thumb">';
				if (business.thumbnail) {
					html += '<img src="' + self.escapeHtml(business.thumbnail) + '" alt="">';
				} else {
					html += '<span class="dashicons dashicons-store"></span>';
				}
				html += '</div>';
				html += '<div class="bd-featured-result-info">';
				html += '<div class="bd-featured-result-title">' + self.escapeHtml(business.title) + '</div>';
				if (business.city) {
					html += '<div class="bd-featured-result-city">' + self.escapeHtml(business.city) + '</div>';
				}
				html += '</div>';
				html += '<button type="button" class="button button-primary bd-featured-result-add" data-id="' + business.id + '">Add</button>';
				html += '</div>';
			});

			$results.html(html);
		},

		/**
		 * Add a business to featured list
		 */
		addBusiness: function (businessId, $btn) {
			const self = this;

			if (this.currentCount >= bdFeatured.maxFeatured) {
				this.showToast(bdFeatured.strings.maxReached, 'error');
				return;
			}

			$btn.prop('disabled', true).text(bdFeatured.strings.adding);

			$.ajax({
				url: bdFeatured.ajaxUrl,
				method: 'POST',
				data: {
					action: 'bd_featured_add',
					nonce: bdFeatured.nonce,
					business_id: businessId
				},
				success: function (response) {
					if (response.success) {
						// Remove from search results
						$btn.closest('.bd-featured-result-item').fadeOut(200, function () {
							$(this).remove();
						});

						// Add to featured list
						self.addItemToList(response.data.business);
						self.currentCount = response.data.count;
						self.updateCount();
						self.updatePositionNumbers();
						self.showToast(response.data.message, 'success');

						// Disable add button if max reached
						if (self.currentCount >= bdFeatured.maxFeatured) {
							$('#bd-add-featured').prop('disabled', true);
						}
					} else {
						$btn.prop('disabled', false).text('Add');
						self.showToast(response.data.message || bdFeatured.strings.error, 'error');
					}
				},
				error: function () {
					$btn.prop('disabled', false).text('Add');
					self.showToast(bdFeatured.strings.error, 'error');
				}
			});
		},

		/**
		 * Add item HTML to the list
		 */
		addItemToList: function (business) {
			const $list = $('#bd-featured-list');
			const position = this.currentCount;

			// Remove empty state if present
			$list.find('.bd-featured-empty').remove();

			let html = '<li class="bd-featured-item" data-id="' + business.id + '" style="display: none;">';
			html += '<span class="bd-featured-handle" title="Drag to reorder">';
			html += '<span class="dashicons dashicons-menu"></span>';
			html += '</span>';
			html += '<span class="bd-featured-position">' + position + '</span>';
			html += '<div class="bd-featured-thumb">';
			if (business.thumbnail) {
				html += '<img src="' + this.escapeHtml(business.thumbnail) + '" alt="">';
			} else {
				html += '<span class="dashicons dashicons-store"></span>';
			}
			html += '</div>';
			html += '<div class="bd-featured-info">';
			html += '<strong class="bd-featured-title">';
			html += '<a href="' + this.escapeHtml(business.editUrl) + '" target="_blank">' + this.escapeHtml(business.title) + '</a>';
			html += '</strong>';
			html += '<span class="bd-featured-meta">';
			html += this.escapeHtml(business.city || '');
			if (business.category) {
				html += ' &bull; ' + this.escapeHtml(business.category);
			}
			html += '</span>';
			html += '</div>';
			html += '<button type="button" class="bd-featured-remove" data-id="' + business.id + '" title="Remove from featured">';
			html += '<span class="dashicons dashicons-no-alt"></span>';
			html += '</button>';
			html += '</li>';

			$list.append(html);
			$list.find('.bd-featured-item[data-id="' + business.id + '"]').fadeIn(300);
			
			// Refresh sortable so new item is draggable
			$list.sortable('refresh');
		},

		/**
		 * Remove a business from featured list
		 */
		removeBusiness: function (businessId, $btn) {
			const self = this;
			const $item = $btn.closest('.bd-featured-item');

			$btn.prop('disabled', true);

			$.ajax({
				url: bdFeatured.ajaxUrl,
				method: 'POST',
				data: {
					action: 'bd_featured_remove',
					nonce: bdFeatured.nonce,
					business_id: businessId
				},
				success: function (response) {
					if (response.success) {
						$item.fadeOut(300, function () {
							$(this).remove();
							self.updatePositionNumbers();

							// Show empty state if no items left
							if ($('#bd-featured-list .bd-featured-item').length === 0) {
								$('#bd-featured-list').html(
									'<li class="bd-featured-empty">' +
									'<span class="dashicons dashicons-info-outline"></span>' +
									'No featured businesses yet. Click "Add Business" to get started.' +
									'</li>'
								);
							}
						});

						self.currentCount = response.data.count;
						self.updateCount();
						self.showToast(response.data.message, 'success');

						// Re-enable add button
						$('#bd-add-featured').prop('disabled', false);
					} else {
						$btn.prop('disabled', false);
						self.showToast(response.data.message || bdFeatured.strings.error, 'error');
					}
				},
				error: function () {
					$btn.prop('disabled', false);
					self.showToast(bdFeatured.strings.error, 'error');
				}
			});
		},

		/**
		 * Save the current order via AJAX
		 */
		saveOrder: function () {
			const self = this;
			const order = [];

			$('#bd-featured-list .bd-featured-item').each(function () {
				order.push($(this).data('id'));
			});

			$.ajax({
				url: bdFeatured.ajaxUrl,
				method: 'POST',
				data: {
					action: 'bd_featured_reorder',
					nonce: bdFeatured.nonce,
					order: order
				},
				success: function (response) {
					if (response.success) {
						self.showToast(response.data.message, 'success');
					} else {
						self.showToast(response.data.message || bdFeatured.strings.error, 'error');
					}
				},
				error: function () {
					self.showToast(bdFeatured.strings.error, 'error');
				}
			});
		},

		/**
		 * Update position numbers after reorder
		 */
		updatePositionNumbers: function () {
			$('#bd-featured-list .bd-featured-item').each(function (index) {
				$(this).find('.bd-featured-position').text(index + 1);
			});
		},

		/**
		 * Update the count display
		 */
		updateCount: function () {
			$('.bd-featured-count').text(
				this.currentCount + ' of ' + bdFeatured.maxFeatured + ' featured'
			);
		},

		/**
		 * Show toast notification
		 */
		showToast: function (message, type) {
			type = type || 'success';

			const $toast = $('#bd-featured-toast');
			$toast.removeClass('bd-toast-success bd-toast-error')
				.addClass('bd-toast-' + type)
				.text(message)
				.fadeIn(200);

			setTimeout(function () {
				$toast.fadeOut(200);
			}, 3000);
		},

		/**
		 * Escape HTML entities
		 */
		escapeHtml: function (str) {
			if (!str) return '';
			const div = document.createElement('div');
			div.textContent = str;
			return div.innerHTML;
		}
	};

	// Initialize on document ready
	$(document).ready(function () {
		FeaturedAdmin.init();
	});

})(jQuery);
