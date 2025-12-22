(function ($) {
	'use strict';

	// ========================================================================
	// SAVE TO LIST FUNCTIONALITY
	// ========================================================================
	
	/**
	 * Open save modal - moves modal to body to avoid transform issues
	 */
	$(document).on('click', '.bd-save-btn', function (e) {
		e.preventDefault();
		e.stopPropagation();

		const $btn = $(this);
		const $wrapper = $btn.closest('.bd-save-wrapper');
		const businessId = $wrapper.data('business-id');

		// First try to find modal in wrapper, then in body (if already moved)
		let $modal = $wrapper.find('.bd-save-modal');

		// If modal was already moved to body, find it there by business ID
		if (!$modal.length) {
			$modal = $('body > .bd-save-modal').filter(function () {
				return $(this).data('business-id') == businessId;
			});
		}

		// Check if login required
		if ($btn.data('login-required')) {
			if (confirm(bdLists.strings.loginRequired + '\n\nWould you like to log in now?')) {
				window.location.href = bdLists.loginUrl;
			}
			return;
		}

		// Close other modals first
		$('.bd-save-modal').hide();
		$('body').removeClass('bd-modal-open');

		// Toggle modal
		if ($modal.data('is-open')) {
			$modal.fadeOut(200);
			$modal.data('is-open', false);
			$('body').removeClass('bd-modal-open');
		} else {
			// Move modal to body if not already moved
			if (!$modal.data('moved-to-body')) {
				$modal.data('original-wrapper', $wrapper);
				$modal.data('business-id', businessId);
				$modal.appendTo('body');
				$modal.data('moved-to-body', true);
			}

			$modal.fadeIn(200);
			$modal.data('is-open', true);
			$('body').addClass('bd-modal-open');
		}
	});

	/**
	 * Close save modal
	 */
	$(document).on('click', '.bd-save-modal-close', function () {
		const $modal = $(this).closest('.bd-save-modal');
		$modal.fadeOut(200);
		$modal.data('is-open', false);
		$('body').removeClass('bd-modal-open');
	});

	// Close modal when clicking outside
	$(document).on('click', function (e) {
		if (!$(e.target).closest('.bd-save-modal, .bd-save-btn').length) {
			$('.bd-save-modal').fadeOut(200).data('is-open', false);
			$('body').removeClass('bd-modal-open');
		}
	});

	// Close on Escape key
	$(document).on('keydown', function (e) {
		if (e.key === 'Escape') {
			$('.bd-save-modal').fadeOut(200).data('is-open', false);
			$('.bd-modal').fadeOut(200);
			$('body').removeClass('bd-modal-open');
		}
	});

	/**
	 * Toggle list checkbox (add/remove from list)
	 */
	$(document).on('change', '.bd-save-list-item input[type="checkbox"]', function () {
		const $checkbox = $(this);
		const $modal = $checkbox.closest('.bd-save-modal');
		const businessId = $modal.data('business-id');
		const listId = $checkbox.data('list-id');
		const isChecked = $checkbox.is(':checked');

		$checkbox.prop('disabled', true);

		if (isChecked) {
			// Add to list
			$.ajax({
				url: bdLists.restUrl + 'lists/' + listId + '/items',
				method: 'POST',
				headers: { 'X-WP-Nonce': bdLists.nonce },
				data: { business_id: businessId },
				success: function (response) {
					$checkbox.closest('.bd-save-list-item').addClass('bd-in-list');
					updateSaveButtonState($modal);
					showToast(response.message || bdLists.strings.saved);
				},
				error: function (xhr) {
					$checkbox.prop('checked', false);
					showToast(xhr.responseJSON?.message || bdLists.strings.error, 'error');
				},
				complete: function () {
					$checkbox.prop('disabled', false);
				}
			});
		} else {
			// Remove from list
			$.ajax({
				url: bdLists.restUrl + 'lists/' + listId + '/items/' + businessId,
				method: 'DELETE',
				headers: { 'X-WP-Nonce': bdLists.nonce },
				success: function (response) {
					$checkbox.closest('.bd-save-list-item').removeClass('bd-in-list');
					updateSaveButtonState($modal);
					showToast(response.message || bdLists.strings.removed);
				},
				error: function (xhr) {
					$checkbox.prop('checked', true);
					showToast(xhr.responseJSON?.message || bdLists.strings.error, 'error');
				},
				complete: function () {
					$checkbox.prop('disabled', false);
				}
			});
		}
	});

	/**
	 * Toggle create list form
	 */
	$(document).on('click', '.bd-create-list-toggle', function () {
		const $form = $(this).siblings('.bd-create-list-form');
		$form.slideToggle(200);
		$form.find('.bd-new-list-title').focus();
	});

	/**
	 * Create new list and add business (from save modal)
	 */
	$(document).on('click', '.bd-save-modal .bd-create-list-btn', function () {
		const $btn = $(this);
		const $modal = $btn.closest('.bd-save-modal');
		const $input = $btn.siblings('.bd-new-list-title');
		const businessId = $modal.data('business-id');
		const listTitle = $input.val().trim();

		if (!listTitle) {
			$input.focus();
			return;
		}

		$btn.prop('disabled', true).text('Creating...');

		$.ajax({
			url: bdLists.restUrl + 'lists/quick-save',
			method: 'POST',
			headers: { 'X-WP-Nonce': bdLists.nonce },
			data: {
				business_id: businessId,
				new_list: listTitle
			},
			success: function (response) {
				// Add new list to the modal
				const $lists = $modal.find('.bd-save-lists');
				const newItem = `
					<label class="bd-save-list-item bd-in-list">
						<input type="checkbox" checked data-list-id="${response.list.id}">
						<span class="bd-save-list-title">${response.list.title}</span>
						<span class="bd-save-list-count">1 items</span>
					</label>
				`;
				$lists.append(newItem);

				// Clear input and hide form
				$input.val('');
				$btn.closest('.bd-create-list-form').slideUp(200);

				updateSaveButtonState($modal);
				showToast(response.message);
			},
			error: function (xhr) {
				showToast(xhr.responseJSON?.message || bdLists.strings.error, 'error');
			},
			complete: function () {
				$btn.prop('disabled', false).text('Create & Save');
			}
		});
	});

	/**
	 * Update save button state based on list selections
	 */
	function updateSaveButtonState($modal) {
		const $wrapper = $modal.data('original-wrapper');
		if (!$wrapper) return;

		const $btn = $wrapper.find('.bd-save-btn');
		const hasChecked = $modal.find('.bd-save-list-item.bd-in-list').length > 0;

		if (hasChecked) {
			$btn.addClass('bd-saved');
			$btn.find('.bd-save-icon').text('❤️');
			$btn.find('.bd-save-text').text('Saved');
		} else {
			$btn.removeClass('bd-saved');
			$btn.find('.bd-save-icon').text('🤍');
			$btn.find('.bd-save-text').text('Save');
		}
	}

	// ========================================================================
	// MY LISTS PAGE FUNCTIONALITY
	// ========================================================================

	/**
	 * Open create list modal - moves modal to body to avoid z-index issues
	 */
	$(document).on('click', '.bd-create-list-open', function () {
		var $modal = $('.bd-create-list-modal');

		// Move modal to body if not already there
		if (!$modal.parent().is('body')) {
			$modal.appendTo('body');
		}

		$modal.fadeIn(200);
		$('body').addClass('bd-modal-open');
		$modal.find('#bd-list-title').focus();
	});

	/**
	 * Close modals
	 */
	$(document).on('click', '.bd-modal-close, .bd-modal-overlay', function () {
		$(this).closest('.bd-modal').fadeOut(200);
		$('body').removeClass('bd-modal-open');
	});

	/**
	 * Create list form submit (from My Lists page modal)
	 */
	$(document).on('submit', '.bd-create-list-modal .bd-create-list-form', function (e) {
		e.preventDefault();

		const $form = $(this);
		const $btn = $form.find('button[type="submit"]');

		$btn.prop('disabled', true).text('Creating...');

		$.ajax({
			url: bdLists.restUrl + 'lists',
			method: 'POST',
			headers: { 'X-WP-Nonce': bdLists.nonce },
			data: {
				title: $form.find('[name="title"]').val(),
				description: $form.find('[name="description"]').val(),
				visibility: $form.find('[name="visibility"]').val()
			},
			success: function (response) {
				showToast(response.message);
				// Reload page to show new list
				window.location.reload();
			},
			error: function (xhr) {
				showToast(xhr.responseJSON?.message || bdLists.strings.error, 'error');
				$btn.prop('disabled', false).text('Create List');
			}
		});
	});

	/**
	 * Delete list
	 */
	$(document).on('click', '.bd-delete-list-btn', function () {
		const listId = $(this).data('list-id');

		if (!confirm('Are you sure you want to delete this list? This cannot be undone.')) {
			return;
		}

		const $card = $(this).closest('.bd-list-card, .bd-single-list-page');

		$.ajax({
			url: bdLists.restUrl + 'lists/' + listId,
			method: 'DELETE',
			headers: { 'X-WP-Nonce': bdLists.nonce },
			success: function (response) {
				showToast(response.message);

				if ($card.hasClass('bd-single-list-page')) {
					// Redirect to my lists
					window.location.href = window.location.pathname.replace(/\/[^\/]*\/?$/, '/my-lists/');
				} else {
					// Remove card with animation
					$card.fadeOut(300, function () {
						$(this).remove();
					});
				}
			},
			error: function (xhr) {
				showToast(xhr.responseJSON?.message || bdLists.strings.error, 'error');
			}
		});
	});

	// ========================================================================
	// SINGLE LIST PAGE FUNCTIONALITY
	// ========================================================================

	/**
	 * Open edit list modal - moves modal to body to avoid z-index issues
	 */
	$(document).on('click', '.bd-edit-list-btn', function () {
		var $modal = $('.bd-edit-list-modal');

		// Move modal to body if not already there
		if (!$modal.parent().is('body')) {
			$modal.appendTo('body');
		}

		$modal.fadeIn(200);
		$('body').addClass('bd-modal-open');
	});

	/**
	 * Edit list form submit
	 */
	$(document).on('submit', '.bd-edit-list-form', function (e) {
		e.preventDefault();

		const $form = $(this);
		const listId = $form.data('list-id');
		const $btn = $form.find('button[type="submit"]');

		$btn.prop('disabled', true).text('Saving...');

		$.ajax({
			url: bdLists.restUrl + 'lists/' + listId,
			method: 'PUT',
			headers: { 'X-WP-Nonce': bdLists.nonce },
			data: {
				title: $form.find('[name="title"]').val(),
				description: $form.find('[name="description"]').val(),
				visibility: $form.find('[name="visibility"]').val()
			},
			success: function (response) {
				showToast(response.message);
				// Reload to show changes
				window.location.reload();
			},
			error: function (xhr) {
				showToast(xhr.responseJSON?.message || bdLists.strings.error, 'error');
				$btn.prop('disabled', false).text('Save Changes');
			}
		});
	});

	/**
	 * Remove item from list
	 */
	$(document).on('click', '.bd-remove-item-btn', function () {
		const $item = $(this).closest('.bd-list-item');
		const businessId = $item.data('business-id');
		const listId = $('.bd-single-list-page').data('list-id');

		if (!confirm('Remove this business from the list?')) {
			return;
		}

		$.ajax({
			url: bdLists.restUrl + 'lists/' + listId + '/items/' + businessId,
			method: 'DELETE',
			headers: { 'X-WP-Nonce': bdLists.nonce },
			success: function (response) {
				$item.fadeOut(300, function () {
					$(this).remove();
					renumberListItems();
				});
				showToast(response.message);
			},
			error: function (xhr) {
				showToast(xhr.responseJSON?.message || bdLists.strings.error, 'error');
			}
		});
	});

	/**
	 * Renumber list items after removal
	 */
	function renumberListItems() {
		$('.bd-list-items .bd-list-item').each(function (index) {
			$(this).find('.bd-list-item-number').text(index + 1);
		});
	}

	/**
	 * Edit item note
	 */
	$(document).on('click', '.bd-edit-note-btn', function () {
		const $item = $(this).closest('.bd-list-item');
		const $noteDiv = $item.find('.bd-list-item-note');
		const currentNote = $noteDiv.find('i').length ? $noteDiv.text().replace(/^"|"$/g, '').trim() : '';

		const newNote = prompt('Add a note about this place:', currentNote);

		if (newNote === null) return; // Cancelled

		const businessId = $item.data('business-id');
		const listId = $('.bd-single-list-page').data('list-id');

		$.ajax({
			url: bdLists.restUrl + 'lists/' + listId + '/items/' + businessId,
			method: 'PUT',
			headers: { 'X-WP-Nonce': bdLists.nonce },
			data: { note: newNote },
			success: function (response) {
				if (newNote) {
					if ($noteDiv.length) {
						$noteDiv.html('<i class="fas fa-comment"></i> "' + newNote + '"');
					} else {
						$item.find('.bd-list-item-content').append(
							'<div class="bd-list-item-note"><i class="fas fa-comment"></i> "' + newNote + '"</div>'
						);
					}
				} else {
					$noteDiv.remove();
				}
				showToast(response.message);
			},
			error: function (xhr) {
				showToast(xhr.responseJSON?.message || bdLists.strings.error, 'error');
			}
		});
	});

	/**
	 * Drag and drop reordering (if Sortable.js is available)
	 */
	function initSortable() {
		if (typeof Sortable === 'undefined') return;

		const $sortable = $('.bd-list-items.bd-sortable');
		if (!$sortable.length) return;

		Sortable.create($sortable[0], {
			handle: '.bd-list-item-handle',
			animation: 150,
			onEnd: function () {
				const order = [];
				$sortable.find('.bd-list-item').each(function () {
					order.push($(this).data('business-id'));
				});

				const listId = $('.bd-single-list-page').data('list-id');

				$.ajax({
					url: bdLists.restUrl + 'lists/' + listId + '/reorder',
					method: 'POST',
					headers: { 'X-WP-Nonce': bdLists.nonce },
					contentType: 'application/json',
					data: JSON.stringify({ order: order }),
					success: function () {
						renumberListItems();
					}
				});
			}
		});
	}

	// ========================================================================
	// SHARE FUNCTIONALITY
	// ========================================================================

	/**
	 * Copy link to clipboard
	 */
	$(document).on('click', '.bd-share-copy', function () {
		const url = $(this).data('url');

		if (navigator.clipboard) {
			navigator.clipboard.writeText(url).then(function () {
				showToast('Link copied to clipboard!');
			});
		} else {
			// Fallback
			const $temp = $('<input>');
			$('body').append($temp);
			$temp.val(url).select();
			document.execCommand('copy');
			$temp.remove();
			showToast('Link copied to clipboard!');
		}
	});

	// ========================================================================
	// TOAST NOTIFICATIONS
	// ========================================================================

	function showToast(message, type = 'success') {
		// Remove existing toasts
		$('.bd-toast').remove();

		const $toast = $(`
			<div class="bd-toast bd-toast-${type}">
				<span class="bd-toast-icon">${type === 'success' ? '✓' : '✕'}</span>
				<span class="bd-toast-message">${message}</span>
			</div>
		`);

		$('body').append($toast);

		// Animate in
		setTimeout(function () {
			$toast.addClass('bd-toast-show');
		}, 10);

		// Auto hide
		setTimeout(function () {
			$toast.removeClass('bd-toast-show');
			setTimeout(function () {
				$toast.remove();
			}, 300);
		}, 3000);
	}

	// ========================================================================
	// INITIALIZATION
	// ========================================================================

	$(document).ready(function () {
		initSortable();
	});

})(jQuery);
