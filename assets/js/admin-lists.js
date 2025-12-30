(function ($) {
	'use strict';

	/**
	 * Show admin notice
	 */
	function showAdminNotice(message, type) {
		type = type || 'success';
		
		// Remove existing notices
		$('.bd-lists-admin .bd-admin-notice').remove();
		
		var notice = $('<div class="notice bd-admin-notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
		$('.bd-lists-admin h1').after(notice);
		
		// Auto-dismiss after 3 seconds
		setTimeout(function() {
			notice.fadeOut(300, function() {
				$(this).remove();
			});
		}, 3000);
	}

	/**
	 * Feature list
	 */
	$(document).on('click', '.bd-feature-btn', function () {
		var $btn = $(this);
		var listId = $btn.data('list-id');
		var $row = $btn.closest('tr');

		$btn.addClass('loading').prop('disabled', true);

		$.ajax({
			url: bdListsAdmin.ajaxUrl,
			method: 'POST',
			data: {
				action: 'bd_admin_list_action',
				list_action: 'feature',
				list_id: listId,
				nonce: bdListsAdmin.nonce
			},
			success: function (response) {
				if (response.success) {
					// Update button
					$btn.removeClass('bd-feature-btn loading').addClass('bd-unfeature-btn bd-featured');
					$btn.find('i').removeClass('far').addClass('fas');
					$btn.attr('title', 'Remove Featured');

					// Add star to title
					var $titleWrap = $row.find('.bd-list-title-wrap');
					if (!$titleWrap.find('.bd-featured-star').length) {
						$titleWrap.prepend('<span class="bd-featured-star" title="Featured"><i class="fas fa-star"></i></span>');
					}

					showAdminNotice(response.data.message);
				} else {
					showAdminNotice(response.data.message || 'Action failed', 'error');
				}
			},
			error: function () {
				showAdminNotice('An error occurred', 'error');
			},
			complete: function () {
				$btn.removeClass('loading').prop('disabled', false);
			}
		});
	});

	/**
	 * Unfeature list
	 */
	$(document).on('click', '.bd-unfeature-btn', function () {
		var $btn = $(this);
		var listId = $btn.data('list-id');
		var $row = $btn.closest('tr');

		$btn.addClass('loading').prop('disabled', true);

		$.ajax({
			url: bdListsAdmin.ajaxUrl,
			method: 'POST',
			data: {
				action: 'bd_admin_list_action',
				list_action: 'unfeature',
				list_id: listId,
				nonce: bdListsAdmin.nonce
			},
			success: function (response) {
				if (response.success) {
					// Update button
					$btn.removeClass('bd-unfeature-btn bd-featured loading').addClass('bd-feature-btn');
					$btn.find('i').removeClass('fas').addClass('far');
					$btn.attr('title', 'Feature This List');

					// Remove star from title
					$row.find('.bd-featured-star').remove();

					showAdminNotice(response.data.message);
				} else {
					showAdminNotice(response.data.message || 'Action failed', 'error');
				}
			},
			error: function () {
				showAdminNotice('An error occurred', 'error');
			},
			complete: function () {
				$btn.removeClass('loading').prop('disabled', false);
			}
		});
	});

	/**
	 * Delete list
	 */
	$(document).on('click', '.bd-delete-btn', function () {
		var $btn = $(this);
		var listId = $btn.data('list-id');
		var $row = $btn.closest('tr');

		if (!confirm('Are you sure you want to delete this list? This action cannot be undone.')) {
			return;
		}

		$btn.addClass('loading').prop('disabled', true);

		$.ajax({
			url: bdListsAdmin.ajaxUrl,
			method: 'POST',
			data: {
				action: 'bd_admin_list_action',
				list_action: 'delete',
				list_id: listId,
				nonce: bdListsAdmin.nonce
			},
			success: function (response) {
				if (response.success) {
					$row.fadeOut(300, function () {
						$(this).remove();
						
						// Check if table is now empty
						if ($('.bd-lists-table tbody tr').length === 0) {
							$('.bd-lists-table tbody').html(
								'<tr><td colspan="12" class="bd-no-items">' +
								'<i class="fas fa-inbox"></i>No lists found.</td></tr>'
							);
						}
					});
					showAdminNotice(response.data.message);
				} else {
					showAdminNotice(response.data.message || 'Action failed', 'error');
				}
			},
			error: function () {
				showAdminNotice('An error occurred', 'error');
			},
			complete: function () {
				$btn.removeClass('loading').prop('disabled', false);
			}
		});
	});

	/**
	 * Select all checkbox
	 */
	$('#bd-select-all').on('change', function () {
		var isChecked = $(this).prop('checked');
		$('.bd-list-checkbox').prop('checked', isChecked);
	});

	/**
	 * Update select-all state when individual checkboxes change
	 */
	$(document).on('change', '.bd-list-checkbox', function () {
		var total = $('.bd-list-checkbox').length;
		var checked = $('.bd-list-checkbox:checked').length;
		
		$('#bd-select-all').prop('checked', total === checked);
		$('#bd-select-all').prop('indeterminate', checked > 0 && checked < total);
	});

	/**
	 * Bulk action apply
	 */
	$('#bd-bulk-apply').on('click', function () {
		var action = $('#bd-bulk-action').val();
		var $checkedBoxes = $('.bd-list-checkbox:checked');
		var listIds = [];

		$checkedBoxes.each(function () {
			listIds.push($(this).val());
		});

		if (!action) {
			showAdminNotice('Please select a bulk action', 'warning');
			return;
		}

		if (listIds.length === 0) {
			showAdminNotice('Please select at least one list', 'warning');
			return;
		}

		// Confirm delete action
		if (action === 'delete') {
			if (!confirm('Are you sure you want to delete ' + listIds.length + ' list(s)? This action cannot be undone.')) {
				return;
			}
		}

		var $btn = $(this);
		var $status = $('.bd-bulk-status');
		
		$btn.prop('disabled', true).text('Processing...');
		$status.removeClass('error').text('');

		$.ajax({
			url: bdListsAdmin.ajaxUrl,
			method: 'POST',
			data: {
				action: 'bd_admin_bulk_action',
				bulk_action: action,
				list_ids: listIds,
				nonce: bdListsAdmin.nonce
			},
			success: function (response) {
				if (response.success) {
					$status.text(response.data.message);
					
					// Reload page to show changes
					setTimeout(function() {
						window.location.reload();
					}, 1000);
				} else {
					$status.addClass('error').text(response.data.message || 'Action failed');
				}
			},
			error: function () {
				$status.addClass('error').text('An error occurred');
			},
			complete: function () {
				$btn.prop('disabled', false).text('Apply');
			}
		});
	});

	/**
	 * Auto-submit on filter change
	 */
	$('.bd-filter-select').on('change', function () {
		// Optional: auto-submit when filters change
		// $(this).closest('form').submit();
	});

})(jQuery);
