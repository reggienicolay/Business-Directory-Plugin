(function ($) {
	'use strict';

	$(document).ready(function () {
		// Handle action button clicks.
		$('.bd-action-btn').on('click', function (e) {
			e.preventDefault();

			var $btn = $(this);
			var reviewId = $btn.data('review-id');
			var action = $btn.data('action');
			var $row = $('tr[data-review-id="' + reviewId + '"]');
			var $actionsRow = $('tr.bd-actions-row[data-review-id="' + reviewId + '"]');

			// Confirm delete.
			if (action === 'delete') {
				if (!confirm('Are you sure you want to delete this review? This cannot be undone.')) {
					return;
				}
			}

			// Disable buttons during request.
			$row.addClass('bd-loading');
			$actionsRow.find('.button').prop('disabled', true);

			$.ajax({
				url: bdReviewsAdmin.ajaxUrl,
				method: 'POST',
				data: {
					action: 'bd_admin_review_action',
					review_action: action,
					review_id: reviewId,
					nonce: bdReviewsAdmin.nonce
				},
				success: function (response) {
					if (response.success) {
						$row.addClass('bd-action-success');

						if (action === 'delete') {
							// Fade out and remove row.
							$row.fadeOut(400, function () {
								$(this).remove();
							});
							$actionsRow.fadeOut(400, function () {
								$(this).remove();
							});
						} else {
							// Update status badge.
							var newStatus = action === 'pending' ? 'pending' : action === 'approve' ? 'approved' : 'rejected';
							var $statusCell = $row.find('.bd-status');
							$statusCell
								.removeClass('bd-status-approved bd-status-pending bd-status-rejected')
								.addClass('bd-status-' + newStatus)
								.text(newStatus.charAt(0).toUpperCase() + newStatus.slice(1));

							// Update action buttons.
							updateActionButtons($actionsRow, newStatus);

							// Remove success highlight after delay.
							setTimeout(function () {
								$row.removeClass('bd-action-success');
							}, 1500);
						}
					} else {
						$row.addClass('bd-action-error');
						alert(response.data.message || 'An error occurred');
						setTimeout(function () {
							$row.removeClass('bd-action-error');
						}, 2000);
					}
				},
				error: function () {
					$row.addClass('bd-action-error');
					alert('An error occurred. Please try again.');
					setTimeout(function () {
						$row.removeClass('bd-action-error');
					}, 2000);
				},
				complete: function () {
					$row.removeClass('bd-loading');
					$actionsRow.find('.button').prop('disabled', false);
				}
			});
		});

		/**
		 * Update action buttons based on new status.
		 */
		function updateActionButtons($actionsRow, newStatus) {
			var reviewId = $actionsRow.data('review-id');
			var html = '';

			if (newStatus !== 'approved') {
				html += '<button type="button" class="button button-primary bd-action-btn" data-action="approve" data-review-id="' + reviewId + '">Approve</button>';
			}
			if (newStatus !== 'pending') {
				html += '<button type="button" class="button bd-action-btn" data-action="pending" data-review-id="' + reviewId + '">Set Pending</button>';
			}
			if (newStatus !== 'rejected') {
				html += '<button type="button" class="button bd-action-btn" data-action="reject" data-review-id="' + reviewId + '">Reject</button>';
			}
			html += '<button type="button" class="button button-link-delete bd-action-btn" data-action="delete" data-review-id="' + reviewId + '">Delete</button>';

			$actionsRow.find('.bd-review-actions').html(html);

			// Rebind events to new buttons.
			$actionsRow.find('.bd-action-btn').off('click').on('click', function (e) {
				e.preventDefault();
				$(this).closest('tr').prev().find('.bd-action-btn[data-action="' + $(this).data('action') + '"]').trigger('click');
			});
		}
	});

})(jQuery);
