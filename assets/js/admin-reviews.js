/**
 * Reviews Admin JavaScript - v2.0
 */

(function ($) {
	'use strict';

	$(document).ready(function () {

		// ==========================================================================
		// SELECT ALL / CHECKBOX HANDLING
		// ==========================================================================

		$('#bd-select-all').on('change', function () {
			$('.bd-review-checkbox').prop('checked', $(this).is(':checked'));
		});

		$(document).on('change', '.bd-review-checkbox', function () {
			var allChecked = $('.bd-review-checkbox').length === $('.bd-review-checkbox:checked').length;
			$('#bd-select-all').prop('checked', allChecked);
		});

		// ==========================================================================
		// SINGLE REVIEW ACTIONS (Quick Buttons)
		// ==========================================================================

		$(document).on('click', '.bd-quick-btn', function (e) {
			e.preventDefault();

			var $btn = $(this);
			var reviewId = $btn.data('review-id');
			var action = $btn.data('action');
			var $row = $('tr[data-review-id="' + reviewId + '"]');

			// Confirm delete
			if (action === 'delete') {
				if (!confirm(bdReviewsAdmin.confirmDelete)) {
					return;
				}
			}

			// Loading state
			$row.addClass('bd-loading');

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
							// Fade out and remove row
							$row.fadeOut(400, function () {
								$(this).remove();
								updateCounts();
							});
						} else {
							// Update status badge
							var newStatus = action === 'approve' ? 'approved' : (action === 'reject' ? 'rejected' : 'pending');
							var $statusCell = $row.find('.bd-status');
							$statusCell
								.removeClass('bd-status-approved bd-status-pending bd-status-rejected')
								.addClass('bd-status-' + newStatus)
								.text(newStatus.charAt(0).toUpperCase() + newStatus.slice(1));

							// Update quick actions visibility
							updateQuickActions($row, newStatus);

							showToast(response.data.message, 'success');

							// Remove success highlight after delay
							setTimeout(function () {
								$row.removeClass('bd-action-success');
							}, 1500);
						}
					} else {
						$row.addClass('bd-action-error');
						showToast(response.data.message || 'An error occurred', 'error');
						setTimeout(function () {
							$row.removeClass('bd-action-error');
						}, 2000);
					}
				},
				error: function () {
					$row.addClass('bd-action-error');
					showToast('An error occurred. Please try again.', 'error');
					setTimeout(function () {
						$row.removeClass('bd-action-error');
					}, 2000);
				},
				complete: function () {
					$row.removeClass('bd-loading');
				}
			});
		});

		/**
		 * Update quick action buttons based on new status
		 */
		function updateQuickActions($row, newStatus) {
			var $actions = $row.find('.bd-quick-actions');
			var reviewId = $row.data('review-id');

			$actions.empty();

			if (newStatus !== 'approved') {
				$actions.append(
					'<button type="button" class="bd-quick-btn bd-quick-approve" ' +
					'data-action="approve" data-review-id="' + reviewId + '" ' +
					'title="Approve">✓</button>'
				);
			}
			if (newStatus !== 'rejected') {
				$actions.append(
					'<button type="button" class="bd-quick-btn bd-quick-reject" ' +
					'data-action="reject" data-review-id="' + reviewId + '" ' +
					'title="Reject">✗</button>'
				);
			}
			$actions.append(
				'<button type="button" class="bd-quick-btn bd-quick-delete" ' +
				'data-action="delete" data-review-id="' + reviewId + '" ' +
				'title="Delete">🗑</button>'
			);
		}

		// ==========================================================================
		// BULK ACTIONS
		// ==========================================================================

		$('#bd-bulk-action-apply').on('click', function () {
			var action = $('#bd-bulk-action-select').val();
			var $checked = $('.bd-review-checkbox:checked');
			var reviewIds = [];

			$checked.each(function () {
				reviewIds.push($(this).val());
			});

			if (!action) {
				showToast('Please select an action.', 'error');
				return;
			}

			if (reviewIds.length === 0) {
				showToast(bdReviewsAdmin.noSelection, 'error');
				return;
			}

			// Confirm for destructive actions
			if (action === 'delete') {
				if (!confirm('Are you sure you want to delete ' + reviewIds.length + ' review(s)? This cannot be undone.')) {
					return;
				}
			} else {
				if (!confirm(bdReviewsAdmin.confirmBulk)) {
					return;
				}
			}

			var $status = $('#bd-bulk-status');
			$status.removeClass('success error').text(bdReviewsAdmin.processing);

			// Loading state for all selected rows
			$checked.closest('tr').addClass('bd-loading');

			$.ajax({
				url: bdReviewsAdmin.ajaxUrl,
				method: 'POST',
				data: {
					action: 'bd_admin_bulk_action',
					bulk_action: action,
					review_ids: reviewIds,
					nonce: bdReviewsAdmin.nonce
				},
				success: function (response) {
					if (response.success) {
						$status.addClass('success').text(response.data.message);

						if (action === 'delete') {
							// Remove deleted rows
							$checked.closest('tr').fadeOut(400, function () {
								$(this).remove();
								updateCounts();
							});
						} else {
							// Update status for all affected rows
							var newStatus = action === 'approve' ? 'approved' : (action === 'reject' ? 'rejected' : 'pending');
							$checked.each(function () {
								var $row = $(this).closest('tr');
								var $statusBadge = $row.find('.bd-status');
								$statusBadge
									.removeClass('bd-status-approved bd-status-pending bd-status-rejected')
									.addClass('bd-status-' + newStatus)
									.text(newStatus.charAt(0).toUpperCase() + newStatus.slice(1));

								updateQuickActions($row, newStatus);
								$row.removeClass('bd-loading').addClass('bd-action-success');

								setTimeout(function () {
									$row.removeClass('bd-action-success');
								}, 1500);
							});
						}

						// Uncheck all
						$('.bd-review-checkbox, #bd-select-all').prop('checked', false);

						showToast(response.data.message, 'success');
					} else {
						$status.addClass('error').text(response.data.message);
						showToast(response.data.message || 'An error occurred', 'error');
					}
				},
				error: function () {
					$status.addClass('error').text('An error occurred');
					showToast('An error occurred. Please try again.', 'error');
				},
				complete: function () {
					$checked.closest('tr').removeClass('bd-loading');

					// Clear status after delay
					setTimeout(function () {
						$status.text('');
					}, 5000);
				}
			});
		});

		// ==========================================================================
		// EXPORT CSV
		// ==========================================================================

		$('#bd-export-reviews').on('click', function (e) {
			e.preventDefault();

			// Create a form and submit it to trigger download
			var $form = $('<form>', {
				method: 'POST',
				action: bdReviewsAdmin.ajaxUrl
			});

			$form.append($('<input>', { type: 'hidden', name: 'action', value: 'bd_admin_export_reviews' }));
			$form.append($('<input>', { type: 'hidden', name: 'nonce', value: bdReviewsAdmin.nonce }));

			$('body').append($form);
			$form.submit();
			$form.remove();

			showToast('Exporting reviews...', 'success');
		});

		// ==========================================================================
		// TOGGLE CONTENT (Show More/Less)
		// ==========================================================================

		$(document).on('click', '.bd-toggle-content', function (e) {
			e.preventDefault();

			var $this = $(this);
			var $container = $this.closest('.bd-review-content');
			var $short = $container.find('.bd-content-short');
			var $full = $container.find('.bd-content-full');

			if ($full.is(':visible')) {
				$full.hide();
				$short.show();
				$this.text('Show more');
			} else {
				$short.hide();
				$full.show();
				$this.text('Show less');
			}
		});

		// ==========================================================================
		// TOAST NOTIFICATIONS
		// ==========================================================================

		function showToast(message, type) {
			type = type || 'info';

			// Remove existing toasts
			$('.bd-admin-toast').remove();

			var $toast = $('<div class="bd-admin-toast ' + type + '">' + message + '</div>');
			$('body').append($toast);

			// Auto-remove after 4 seconds
			setTimeout(function () {
				$toast.fadeOut(300, function () {
					$(this).remove();
				});
			}, 4000);
		}

		// ==========================================================================
		// UPDATE COUNTS (after delete)
		// ==========================================================================

		function updateCounts() {
			// Decrement the displayed count
			var $displayNum = $('.displaying-num');
			if ($displayNum.length) {
				var text = $displayNum.text();
				var match = text.match(/(\d+)/);
				if (match) {
					var count = parseInt(match[1], 10) - 1;
					$displayNum.text(count + ' ' + (count === 1 ? 'review' : 'reviews'));
				}
			}
		}

		// ==========================================================================
		// ROW HOVER EFFECTS (for better UX)
		// ==========================================================================

		// Highlight row when checkbox is checked
		$(document).on('change', '.bd-review-checkbox', function () {
			var $row = $(this).closest('tr');
			if ($(this).is(':checked')) {
				$row.css('background-color', '#f0f6fc');
			} else {
				$row.css('background-color', '');
			}
		});

	});

})(jQuery);
