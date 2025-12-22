(function ($) {
	'use strict';

	/**
	 * Feature list
	 */
	$(document).on('click', '.bd-feature-btn', function () {
		const $btn = $(this);
		const listId = $btn.data('list-id');
		const $row = $btn.closest('tr');

		$btn.prop('disabled', true);

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
					$btn.removeClass('bd-feature-btn').addClass('bd-unfeature-btn');
					$btn.html('⭐').attr('title', 'Remove Featured');

					// Add star to title
					const $title = $row.find('.column-title strong');
					if (!$title.find('.bd-featured-star').length) {
						$title.prepend('<span class="bd-featured-star" title="Featured">⭐</span>');
					}

					showAdminNotice(response.data.message, 'success');
				} else {
					showAdminNotice(response.data.message || 'Action failed', 'error');
				}
			},
			error: function () {
				showAdminNotice('An error occurred', 'error');
			},
			complete: function () {
				$btn.prop('disabled', false);
			}
		});
	});

	/**
	 * Unfeature list
	 */
	$(document).on('click', '.bd-unfeature-btn', function () {
		const $btn = $(this);
		const listId = $btn.data('list-id');
		const $row = $btn.closest('tr');

		$btn.prop('disabled', true);

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
					$btn.removeClass('bd-unfeature-btn').addClass('bd-feature-btn');
					$btn.html('☆').attr('title', 'Feature');

					// Remove star from title
					$row.find('.bd-featured-star').remove();

					showAdminNotice(response.data.message, 'success');
				} else {
					showAdminNotice(response.data.message || 'Action failed', 'error');
				}
			},
			error: function () {
				showAdminNotice('An error occurred', 'error');
			},
			complete: function () {
				$btn.prop('disabled', false);
			}
		});
	});

	/**
	 * Delete list
	 */
	$(document).on('click', '.bd-delete-btn', function () {
		const $btn = $(this);
		const listId = $btn.data('list-id');
		const $row = $btn.closest('tr');

		if (!confirm('Are you sure you want to delete this list? This action cannot be undone.')) {
			return;
		}

		$btn.prop('disabled', true);

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

						// Check if table is empty
						if ($('.bd-lists-table tbody tr').length === 0) {
							$('.bd-lists-table tbody').html(
								'<tr><td colspan="7" class="bd-no-items">No lists found.</td></tr>'
							);
						}
					});

					showAdminNotice(response.data.message, 'success');
				} else {
					showAdminNotice(response.data.message || 'Delete failed', 'error');
					$btn.prop('disabled', false);
				}
			},
			error: function () {
				showAdminNotice('An error occurred', 'error');
				$btn.prop('disabled', false);
			}
		});
	});

	/**
	 * Show admin notice
	 */
	function showAdminNotice(message, type) {
		const noticeClass = type === 'success' ? 'notice-success' : 'notice-error';

		// Remove existing notices
		$('.bd-admin-notice').remove();

		const $notice = $(`
			<div class="notice ${noticeClass} is-dismissible bd-admin-notice">
				<p>${message}</p>
				<button type="button" class="notice-dismiss">
					<span class="screen-reader-text">Dismiss this notice.</span>
				</button>
			</div>
		`);

		$('.bd-lists-admin h1').after($notice);

		// Auto dismiss after 3 seconds
		setTimeout(function () {
			$notice.fadeOut(300, function () {
				$(this).remove();
			});
		}, 3000);

		// Manual dismiss
		$notice.find('.notice-dismiss').on('click', function () {
			$notice.fadeOut(300, function () {
				$(this).remove();
			});
		});
	}

})(jQuery);
