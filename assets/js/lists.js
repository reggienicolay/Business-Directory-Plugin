(function ($) {
	'use strict';

	// ========================================================================
	// UTILITY FUNCTIONS
	// ========================================================================

	/**
	 * Escape HTML to prevent XSS attacks
	 * @param {string} text - Text to escape
	 * @returns {string} - Escaped HTML
	 */
	function escapeHtml(text) {
		if (!text) return "";
		const div = document.createElement("div");
		div.textContent = text;
		return div.innerHTML;
	}

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
			$('.bd-share-modal').fadeOut(200);
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
				// Add new list to the modal (using jQuery to prevent XSS)
				const $lists = $modal.find('.bd-save-lists');
				const $newItem = $('<label class="bd-save-list-item bd-in-list"></label>')
					.append($('<input type="checkbox" checked>').attr('data-list-id', response.list.id))
					.append($('<span class="bd-save-list-title"></span>').text(response.list.title))
					.append($('<span class="bd-save-list-count"></span>').text('1 items'));
				$lists.append($newItem);

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
			$btn.find('.bd-save-icon').html('<i class="fas fa-heart"></i>');
			$btn.find('.bd-save-text').text('Saved');
		} else {
			$btn.removeClass('bd-saved');
			$btn.find('.bd-save-icon').html('<i class="far fa-heart"></i>');
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
				visibility: $form.find('[name="visibility"]:checked').val()
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
 	* Delete list - show confirmation modal
 	*/
	$(document).on('click', '.bd-delete-list-btn', function () {
		const listId = $(this).data('list-id');
		const $card = $(this).closest('.bd-list-card, .bd-single-list-page');

		// Store data for confirmation
		$('.bd-confirm-modal').data('list-id', listId).data('card', $card);
		$('.bd-confirm-title').text('Delete List');
		$('.bd-confirm-message').text('Are you sure you want to delete this list? This cannot be undone.');
		$('.bd-confirm-ok').text('Delete');

		// Show modal
		var $modal = $('.bd-confirm-modal');
		if (!$modal.parent().is('body')) {
			$modal.appendTo('body');
		}
		$modal.fadeIn(200);
		$('body').addClass('bd-modal-open');
	});

	/**
	 * Confirm delete action
	 */
	$(document).on('click', '.bd-confirm-ok', function () {
		const $modal = $(this).closest('.bd-confirm-modal');
		const listId = $modal.data('list-id');
		const $card = $modal.data('card');

		// Close modal
		$modal.fadeOut(200);
		$('body').removeClass('bd-modal-open');

		// Perform delete
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

	/**
	 * Cancel confirmation
	 */
	$(document).on('click', '.bd-confirm-cancel', function () {
		$(this).closest('.bd-confirm-modal').fadeOut(200);
		$('body').removeClass('bd-modal-open');
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
			visibility: $form.find('[name="visibility"]:checked').val()
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

	if (!confirm('Remove this place from the list?')) {
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
				updateListItemCount();
			});
			showToast(response.message);
		},
		error: function (xhr) {
			showToast(xhr.responseJSON?.message || bdLists.strings.error, 'error');
		}
	});
});

/**
 * Update the item count in the hero header
 */
function updateListItemCount() {
	const currentCount = $('.bd-list-items .bd-list-item').length;
	const countText = currentCount === 1 ? '1 place' : currentCount + ' places';
	
	// Update hero meta count (specific class)
	$('.bd-list-count').text(countText);
	
	// Also update the list card count if visible (for card views)
	$('.bd-list-card-count').text(currentCount + ' places');
}

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
				// Build note HTML safely using jQuery (prevents XSS)
				const $noteContent = $('<span></span>')
					.append('<i class="fas fa-comment"></i> "')
					.append($('<span></span>').text(newNote))
					.append('"');
				
				if ($noteDiv.length) {
					$noteDiv.empty().append($noteContent.contents());
				} else {
					const $newNoteDiv = $('<div class="bd-list-item-note"></div>').append($noteContent.contents());
					$item.find('.bd-list-item-content').append($newNoteDiv);
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
	if (typeof Sortable === 'undefined') {
		console.log('Sortable.js not loaded');
		return;
	}

	const $sortable = $('.bd-list-items.bd-sortable');
	if (!$sortable.length) {
		console.log('No sortable container found');
		return;
	}

	Sortable.create($sortable[0], {
		handle: '.bd-list-item-handle',
		animation: 150,
		ghostClass: 'sortable-ghost',
		chosenClass: 'sortable-chosen',
		dragClass: 'sortable-drag',
		onEnd: function () {
			const order = [];
			$sortable.find('.bd-list-item').each(function () {
				order.push(parseInt($(this).data('business-id'), 10));
			});

			const listId = $('.bd-single-list-page').data('list-id');

			// Show saving indicator
			showToast('Saving order...', 'info');

			$.ajax({
				url: bdLists.restUrl + 'lists/' + listId + '/reorder',
				method: 'POST',
				headers: { 'X-WP-Nonce': bdLists.nonce },
				data: { order: order },
				success: function (response) {
					renumberListItems();
					showToast('Order saved!', 'success');
				},
				error: function (xhr) {
					console.error('Reorder failed:', xhr);
					showToast(xhr.responseJSON?.message || 'Failed to save order', 'error');
					// Reload to restore original order
					setTimeout(function() {
						window.location.reload();
					}, 1500);
				}
			});
		}
	});
	
	console.log('Sortable initialized');
}

// ========================================================================
// SHARE MODAL FUNCTIONALITY
// ========================================================================

/**
 * Open share modal
 */
$(document).on('click', '.bd-share-modal-open', function (e) {
	e.preventDefault();

	const listId = $(this).data('list-id');
	let $modal = $('.bd-share-modal[data-list-id="' + listId + '"]');

	// Move modal to body if not already there
	if (!$modal.parent().is('body')) {
		$modal.appendTo('body');
	}

	$modal.fadeIn(200);
	$('body').addClass('bd-modal-open');
});

/**
 * Share modal tab switching
 */
$(document).on('click', '.bd-share-tab', function () {
	const $tab = $(this);
	const $modal = $tab.closest('.bd-share-modal');
	const tabName = $tab.data('tab');

	// Update active tab
	$modal.find('.bd-share-tab').removeClass('bd-share-tab-active');
	$tab.addClass('bd-share-tab-active');

	// Show corresponding pane
	$modal.find('.bd-share-tab-pane').removeClass('bd-share-tab-pane-active');
	$modal.find('.bd-share-tab-pane[data-tab="' + tabName + '"]').addClass('bd-share-tab-pane-active');
});

/**
 * Copy to clipboard functionality
 */
$(document).on('click', '.bd-copy-btn', function () {
	const $btn = $(this);
	const targetId = $btn.data('copy-target');
	const $input = $('#' + targetId);

	copyToClipboard($input.val(), $btn);
});

/**
 * Legacy copy link button (simple share buttons)
 */
$(document).on('click', '.bd-share-copy', function () {
	const url = $(this).data('url');
	copyToClipboard(url, $(this));
});

/**
 * Copy text to clipboard with visual feedback
 */
function copyToClipboard(text, $btn) {
	if (navigator.clipboard && window.isSecureContext) {
		// Modern browsers
		navigator.clipboard.writeText(text).then(function () {
			showCopySuccess($btn);
		}).catch(function () {
			fallbackCopy(text, $btn);
		});
	} else {
		// Fallback for older browsers
		fallbackCopy(text, $btn);
	}
}

/**
 * Fallback copy method using textarea
 */
function fallbackCopy(text, $btn) {
	const $temp = $('<textarea>');
	$temp.css({
		position: 'absolute',
		left: '-9999px'
	});
	$('body').append($temp);
	$temp.val(text).select();

	try {
		document.execCommand('copy');
		showCopySuccess($btn);
	} catch (err) {
		showToast('Failed to copy', 'error');
	}

	$temp.remove();
}

/**
 * Show copy success feedback
 */
function showCopySuccess($btn) {
	const originalHtml = $btn.html();
	const originalWidth = $btn.outerWidth();

	// Preserve button width to prevent layout shift
	$btn.css('min-width', originalWidth + 'px');
	$btn.html('<i class="fas fa-check"></i> Copied!');
	$btn.addClass('bd-copy-success');

	showToast(bdLists.strings.copied || 'Copied to clipboard!');

	setTimeout(function () {
		$btn.html(originalHtml);
		$btn.removeClass('bd-copy-success');
		$btn.css('min-width', '');
	}, 2000);
}

/**
 * Track share clicks for gamification
 */
$(document).on('click', '.bd-share-button', function () {
	const platform = $(this).data('platform');
	const listId = $(this).closest('.bd-share-modal').data('list-id');

	// Fire and forget - don't block the share action
	if (bdLists.isLoggedIn && listId) {
		$.ajax({
			url: bdLists.restUrl + 'lists/' + listId + '/share',
			method: 'POST',
			headers: { 'X-WP-Nonce': bdLists.nonce },
			data: { platform: platform }
		});
	}
});

// ========================================================================
// TOAST NOTIFICATIONS
// ========================================================================

function showToast(message, type = 'success') {
	// Remove existing toasts
	$('.bd-toast').remove();

	let icon;
	if (type === 'success') {
		icon = '<i class="fas fa-check-circle"></i>';
	} else if (type === 'error') {
		icon = '<i class="fas fa-times-circle"></i>';
	} else if (type === 'info') {
		icon = '<i class="fas fa-spinner fa-spin"></i>';
	} else {
		icon = '<i class="fas fa-info-circle"></i>';
	}
	
	// Build toast safely using jQuery to prevent XSS
	const $toast = $('<div class="bd-toast"></div>')
		.addClass('bd-toast-' + type)
		.append($('<span class="bd-toast-icon"></span>').html(icon))
		.append($('<span class="bd-toast-message"></span>').text(message));

	$('body').append($toast);

	// Animate in
	setTimeout(function () {
		$toast.addClass('bd-toast-show');
	}, 10);

	// Auto hide (longer for info/loading toasts to allow for response)
	const hideDelay = type === 'info' ? 5000 : 3000;
	setTimeout(function () {
		$toast.removeClass('bd-toast-show');
		setTimeout(function () {
			$toast.remove();
		}, 300);
	}, hideDelay);
}

// ========================================================================
// PHASE 2: LIST MAP VIEW
// ========================================================================

const ListMap = {
	map: null,
	markers: [],

	init: function () {
		const $toggle = $(".bd-map-toggle");
		if (!$toggle.length) return;

		$toggle.on("click", this.toggleView.bind(this));
	},

	toggleView: function (e) {
		const $btn = $(e.currentTarget);
		const currentView = $btn.data("view");

		if (currentView === "list") {
			this.showMap();
			$btn.data("view", "map");
			$btn.addClass("bd-map-active");
			$btn.find("span").text("List View");
			$btn.find("i").removeClass("fa-map").addClass("fa-list");
		} else {
			this.hideMap();
			$btn.data("view", "list");
			$btn.removeClass("bd-map-active");
			$btn.find("span").text("Map View");
			$btn.find("i").removeClass("fa-list").addClass("fa-map");
		}
	},

	showMap: function () {
		const $mapContainer = $(".bd-list-map-container");
		const $listContainer = $(".bd-list-items-container");

		$listContainer.addClass("bd-hidden");
		$mapContainer.show();

		if (!this.map && typeof L !== "undefined" && window.bdListMapData) {
			this.initializeMap();
		} else if (this.map) {
			setTimeout(() => this.map.invalidateSize(), 100);
		}
	},

	hideMap: function () {
		const $mapContainer = $(".bd-list-map-container");
		const $listContainer = $(".bd-list-items-container");

		$mapContainer.hide();
		$listContainer.removeClass("bd-hidden");
	},

	initializeMap: function () {
		const mapData = window.bdListMapData;
		if (!mapData || !mapData.length) return;

		const lats = mapData.map(item => item.lat);
		const lngs = mapData.map(item => item.lng);
		const centerLat = (Math.min(...lats) + Math.max(...lats)) / 2;
		const centerLng = (Math.min(...lngs) + Math.max(...lngs)) / 2;

		this.map = L.map("bd-list-map").setView([centerLat, centerLng], 12);

		L.tileLayer("https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png", {
			attribution: "&copy; OpenStreetMap, &copy; CARTO",
			maxZoom: 19
		}).addTo(this.map);

		const bounds = [];
		mapData.forEach((item, index) => {
			const marker = this.createMarker(item, index + 1);
			marker.addTo(this.map);
			this.markers.push(marker);
			bounds.push([item.lat, item.lng]);
		});

		if (bounds.length > 1) {
			this.map.fitBounds(bounds, { padding: [40, 40] });
		}
	},

	createMarker: function (item, number) {
		const icon = L.divIcon({
			className: "bd-list-marker",
			html: `<div class="bd-numbered-heart">
					<svg viewBox="0 0 24 24" fill="currentColor">
						<path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
					</svg>
					<span class="bd-marker-number">${number}</span>
				</div>`,
			iconSize: [36, 36],
			iconAnchor: [18, 36],
			popupAnchor: [0, -36]
		});

		const marker = L.marker([item.lat, item.lng], { icon: icon });

		// Escape all user content to prevent XSS
		const safeTitle = escapeHtml(item.title);
		const safePermalink = escapeHtml(item.permalink);
		const safeImage = escapeHtml(item.image);
		const safeCategory = escapeHtml(item.category);
		const safeNote = escapeHtml(item.note);

		let popupHtml = `<a href="${safePermalink}" class="bd-map-popup">`;

		if (item.image) {
			popupHtml += `<img src="${safeImage}" alt="${safeTitle}" class="bd-map-popup-image">`;
		}

		popupHtml += `<div class="bd-map-popup-content">`;
		popupHtml += `<h4 class="bd-map-popup-title">${safeTitle}</h4>`;

		if (item.category) {
			popupHtml += `<div class="bd-map-popup-category">${safeCategory}</div>`;
		}

		if (item.rating > 0) {
			const filledStars = Math.round(item.rating);
			const emptyStars = 5 - filledStars;
			let stars = '<i class="fas fa-star"></i>'.repeat(filledStars);
			stars += '<i class="far fa-star"></i>'.repeat(emptyStars);
			popupHtml += `<div class="bd-map-popup-rating">${stars}</div>`;
		}

		if (item.note) {
			popupHtml += `<div class="bd-map-popup-note">"${safeNote}"</div>`;
		}

		popupHtml += `</div></a>`;

		marker.bindPopup(popupHtml, {
			maxWidth: 280,
			className: "bd-list-popup"
		});

		return marker;
	}
};

// ========================================================================
// PHASE 2: FOLLOW BUTTON
// ========================================================================

const FollowButton = {
	init: function () {
		$(document).on("click", ".bd-follow-btn", this.handleClick.bind(this));
	},

	handleClick: function (e) {
		e.preventDefault();
		const $btn = $(e.currentTarget);

		if ($btn.data("login-required")) {
			window.location.href = bdLists.loginUrl;
			return;
		}

		const listId = $btn.data("list-id");
		const isFollowing = $btn.hasClass("bd-following");

		$btn.prop("disabled", true);

		if (isFollowing) {
			this.unfollowList(listId, $btn);
		} else {
			this.followList(listId, $btn);
		}
	},

	followList: function (listId, $btn) {
		$.ajax({
			url: bdLists.restUrl + "lists/" + listId + "/follow",
			method: "POST",
			headers: { "X-WP-Nonce": bdLists.nonce },
			success: function (response) {
				$btn.addClass("bd-following")
					.removeClass("bd-btn-primary")
					.addClass("bd-btn-secondary");
				$btn.find("i").removeClass("fa-plus").addClass("fa-check");
				$btn.find("span").text(bdLists.strings.following || "Following");
				showToast(response.message || "Now following this list");
			},
			error: function (xhr) {
				showToast(xhr.responseJSON?.message || bdLists.strings.error, "error");
			},
			complete: function () {
				$btn.prop("disabled", false);
			}
		});
	},

	unfollowList: function (listId, $btn) {
		$.ajax({
			url: bdLists.restUrl + "lists/" + listId + "/follow",
			method: "DELETE",
			headers: { "X-WP-Nonce": bdLists.nonce },
			success: function (response) {
				$btn.removeClass("bd-following")
					.removeClass("bd-btn-secondary")
					.addClass("bd-btn-primary");
				$btn.find("i").removeClass("fa-check").addClass("fa-plus");
				$btn.find("span").text(bdLists.strings.follow || "Follow");
				showToast(response.message || "Unfollowed list");
			},
			error: function (xhr) {
				showToast(xhr.responseJSON?.message || bdLists.strings.error, "error");
			},
			complete: function () {
				$btn.prop("disabled", false);
			}
		});
	}
};

// ========================================================================
// PHASE 2: MY LISTS TABS (Following Tab)
// ========================================================================

const ListsTabs = {
	init: function () {
		$(document).on("click", ".bd-lists-tab", this.handleTabClick.bind(this));
	},

	handleTabClick: function (e) {
		const $tab = $(e.currentTarget);
		const tabId = $tab.data("tab");

		$(".bd-lists-tab").removeClass("bd-lists-tab-active");
		$tab.addClass("bd-lists-tab-active");

		$(".bd-lists-tab-content").hide();
		$(`.bd-lists-tab-content[data-tab="${tabId}"]`).show();
	}
};

// ========================================================================
// ADD BUSINESS MODAL
// ========================================================================

const AddBusinessModal = {
	$modal: null,
	listId: null,
	existingItems: [],
	selectedItems: new Set(),
	searchTimeout: null,
	currentRequest: null,
	filtersLoaded: false,

	init: function () {
		this.$modal = $(".bd-add-business-modal");
		if (!this.$modal.length) return;

		this.listId = this.$modal.data("list-id");
		this.existingItems = window.bdListExistingItems || [];

		// Bind events
		$(document).on("click", ".bd-add-business-btn", this.open.bind(this));
		$(document).on("input", ".bd-add-business-search-input", this.handleSearch.bind(this));
		$(document).on("change", ".bd-add-business-filter", this.handleFilterChange.bind(this));
		$(document).on("change", ".bd-add-business-checkbox", this.handleCheckboxChange.bind(this));
		$(document).on("click", ".bd-add-selected-btn", this.addSelectedItems.bind(this));
	},

	/**
	 * Decode HTML entities from API response.
	 * The WordPress API returns pre-encoded strings (e.g., &amp; for &).
	 * We need to decode them for display, not double-encode.
	 */
	decodeHtml: function (text) {
		if (!text) return "";
		const textarea = document.createElement("textarea");
		textarea.innerHTML = text;
		return textarea.value;
	},

	/**
	 * Escape HTML for safe insertion (only for untrusted content)
	 */
	escapeHtml: function (text) {
		if (!text) return "";
		const div = document.createElement("div");
		div.textContent = text;
		return div.innerHTML;
	},

	/**
	 * Get the API base URL
	 */
	getApiBaseUrl: function () {
		// bdLists.restUrl is like "/wp-json/bd/v1/" - we need "/wp-json/"
		return bdLists.restUrl.replace(/bd\/v1\/?$/, "");
	},

	open: function () {
		// Move modal to body if not already there
		if (!this.$modal.parent().is("body")) {
			this.$modal.appendTo("body");
		}

		// Load filters if not already loaded
		if (!this.filtersLoaded) {
			this.loadFilters();
		}

		// Clear previous state
		this.selectedItems.clear();
		this.$modal.find(".bd-add-business-search-input").val("");
		this.$modal.find(".bd-add-business-filter").val(""); // Reset filters
		this.$modal.find(".bd-add-business-list").empty();
		this.$modal.find(".bd-add-business-empty").hide();
		this.updateSelectedCount();

		// Show modal
		this.$modal.fadeIn(200);
		$("body").addClass("bd-modal-open");
		this.$modal.find(".bd-add-business-search-input").focus();

		// Load initial results
		this.search("");
	},

	loadFilters: function () {
		const self = this;

		$.ajax({
			url: this.getApiBaseUrl() + "bd/v1/filters",
			method: "GET",
			success: function (response) {
				// Populate category filter
				const $catFilter = self.$modal.find('[data-filter="categories"]');
				$catFilter.find("option:not(:first)").remove();
				if (response.categories) {
					response.categories.forEach(function (cat) {
						// Decode HTML entities in category names
						const name = self.decodeHtml(cat.name);
						$catFilter.append(
							$("<option>")
								.val(cat.id)  // Use term ID, not slug - API expects integers
								.text(name + " (" + cat.count + ")")
						);
					});
				}

				// Populate area/city filter
				const $areaFilter = self.$modal.find('[data-filter="areas"]');
				$areaFilter.find("option:not(:first)").remove();
				if (response.areas) {
					response.areas.forEach(function (area) {
						// Decode HTML entities in area names
						const name = self.decodeHtml(area.name);
						$areaFilter.append(
							$("<option>")
								.val(area.id)  // Use term ID, not slug - API expects integers
								.text(name + " (" + area.count + ")")
						);
					});
				}

				self.filtersLoaded = true;
			},
			error: function () {
				// Mark as loaded to prevent infinite retry attempts
				self.filtersLoaded = true;
				console.error("Failed to load filters");
			}
		});
	},

	handleSearch: function (e) {
		const query = $(e.target).val();

		// Debounce search
		clearTimeout(this.searchTimeout);
		this.searchTimeout = setTimeout(() => {
			this.search(query);
		}, 300);
	},

	handleFilterChange: function () {
		const query = this.$modal.find(".bd-add-business-search-input").val();
		this.search(query);
	},

	search: function (query) {
		const self = this;
		const $results = this.$modal.find(".bd-add-business-list");
		const $loading = this.$modal.find(".bd-add-business-loading");
		const $empty = this.$modal.find(".bd-add-business-empty");

		// Abort previous request to prevent race conditions
		if (this.currentRequest) {
			this.currentRequest.abort();
		}

		// Get filter values
		const category = this.$modal.find('[data-filter="categories"]').val();
		const area = this.$modal.find('[data-filter="areas"]').val();

		// Build request params
		const params = {
			per_page: 20
		};

		if (query) params.q = query;
		// API expects arrays for filters (FilterHandler uses count())
		if (category) params["categories"] = category;
		if (area) params["areas"] = area;

		// Show loading
		$loading.show();
		$empty.hide();
		$results.empty();

		this.currentRequest = $.ajax({
			url: this.getApiBaseUrl() + "bd/v1/businesses",
			method: "GET",
			data: params,
			success: function (response) {
				$loading.hide();

				if (!response.businesses || response.businesses.length === 0) {
					$empty.show();
					return;
				}

				response.businesses.forEach(function (business) {
					$results.append(self.renderBusinessItem(business));
				});
			},
			error: function (xhr, status) {
				// Don't show error for aborted requests
				if (status === "abort") return;
				
				$loading.hide();
				$empty.find("p").text("Error loading places. Please try again.");
				$empty.show();
			},
			complete: function () {
				self.currentRequest = null;
			}
		});
	},

	renderBusinessItem: function (business) {
		const isInList = this.existingItems.includes(business.id);
		const isSelected = this.selectedItems.has(business.id);

		// Decode HTML entities from API, then escape for safe HTML output
		const title = this.escapeHtml(this.decodeHtml(business.title));
		const city = business.areas && business.areas.length > 0 
			? this.escapeHtml(this.decodeHtml(business.areas[0])) 
			: "";
		const category = business.categories && business.categories.length > 0 
			? this.escapeHtml(this.decodeHtml(business.categories[0])) 
			: "";
		
		const rating = business.rating ? business.rating.toFixed(1) : "";
		const ratingHtml = rating ? `<span class="bd-add-biz-rating"><i class="fas fa-star"></i> ${rating}</span>` : "";

		// Image URL should be safe from API, but escape alt text
		const imageUrl = business.featured_image || "";
		const imageHtml = imageUrl
			? `<img src="${this.escapeHtml(imageUrl)}" alt="${title}">`
			: `<div class="bd-add-biz-no-image"><i class="fas fa-store"></i></div>`;

		return `
			<label class="bd-add-business-item ${isInList ? "bd-in-list" : ""} ${isSelected ? "bd-selected" : ""}">
				<input type="checkbox" 
					class="bd-add-business-checkbox" 
					value="${parseInt(business.id, 10)}"
					${isInList ? "disabled checked" : ""}
					${isSelected ? "checked" : ""}>
				<div class="bd-add-biz-image">${imageHtml}</div>
				<div class="bd-add-biz-info">
					<div class="bd-add-biz-title">${title}</div>
					<div class="bd-add-biz-meta">
						${city ? `<span class="bd-add-biz-city">${city}</span>` : ""}
						${category ? `<span class="bd-add-biz-category">${category}</span>` : ""}
						${ratingHtml}
					</div>
				</div>
				${isInList ? '<span class="bd-add-biz-status"><i class="fas fa-check"></i> In list</span>' : ""}
			</label>
		`;
	},

	handleCheckboxChange: function (e) {
		const $checkbox = $(e.target);
		const businessId = parseInt($checkbox.val(), 10);
		const $item = $checkbox.closest(".bd-add-business-item");

		if ($checkbox.is(":checked")) {
			this.selectedItems.add(businessId);
			$item.addClass("bd-selected");
		} else {
			this.selectedItems.delete(businessId);
			$item.removeClass("bd-selected");
		}

		this.updateSelectedCount();
	},

	updateSelectedCount: function () {
		const count = this.selectedItems.size;
		const $counter = this.$modal.find(".bd-add-business-selected");
		const $btn = this.$modal.find(".bd-add-selected-btn");

		if (count > 0) {
			$counter.find(".bd-selected-count").text(count);
			$counter.show();
			$btn.prop("disabled", false).html(`<i class="fas fa-plus"></i> Add ${count} Selected`);
		} else {
			$counter.hide();
			$btn.prop("disabled", true).html('<i class="fas fa-plus"></i> Add Selected');
		}
	},

	addSelectedItems: function () {
		const self = this;
		const items = Array.from(this.selectedItems);
		
		if (items.length === 0) return;

		const $btn = this.$modal.find(".bd-add-selected-btn");
		$btn.prop("disabled", true).html('<i class="fas fa-spinner fa-spin"></i> Adding...');

		// Add items sequentially to avoid overwhelming the server
		let succeeded = 0;
		let index = 0;

		function addNext() {
			if (index >= items.length) {
				// All done
				self.selectedItems.clear();
				self.updateSelectedCount();
				
				if (succeeded > 0) {
					showToast(`Added ${succeeded} place${succeeded > 1 ? "s" : ""} to list!`);
					// Reload page to show new items
					setTimeout(function () {
						window.location.reload();
					}, 1000);
				} else {
					showToast("Could not add places. They may already be in the list.", "error");
					$btn.prop("disabled", false).html('<i class="fas fa-plus"></i> Add Selected');
				}
				return;
			}

			const businessId = items[index];
			
			$.ajax({
				url: bdLists.restUrl + "lists/" + self.listId + "/items",
				method: "POST",
				headers: { "X-WP-Nonce": bdLists.nonce },
				data: { business_id: businessId },
				success: function () {
					succeeded++;
					// Mark as in list
					self.existingItems.push(businessId);
					self.$modal.find(`.bd-add-business-checkbox[value="${businessId}"]`)
						.prop("disabled", true)
						.prop("checked", true)
						.closest(".bd-add-business-item")
						.addClass("bd-in-list")
						.removeClass("bd-selected")
						.find(".bd-add-biz-info")
						.after('<span class="bd-add-biz-status"><i class="fas fa-check"></i> In list</span>');
				},
				complete: function () {
					index++;
					addNext(); // Process next item
				}
			});
		}

		addNext(); // Start the chain
	}
};

// ========================================================================
// INITIALIZATION
// ========================================================================

$(document).ready(function () {
	initSortable();
	ListMap.init();
	FollowButton.init();
	ListsTabs.init();
	AddBusinessModal.init();
});

}) (jQuery);

