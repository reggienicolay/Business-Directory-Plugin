/**
 * Guides Admin JavaScript
 *
 * Handles AJAX interactions for the Guides admin page.
 *
 * @package BusinessDirectory
 * @version 1.0.0
 */

(function($) {
	'use strict';

	/**
	 * Initialize Guides Admin
	 */
	function init() {
		bindEvents();
	}

	/**
	 * Bind event handlers
	 */
	function bindEvents() {
		// Remove guide
		$(document).on('click', '.bd-remove-guide', handleRemoveGuide);

		// Order change
		$(document).on('change', '.bd-guide-order', handleOrderChange);
	}

	/**
	 * Handle remove guide click
	 *
	 * @param {Event} e Click event.
	 */
	function handleRemoveGuide(e) {
		e.preventDefault();

		var $link = $(this);
		var userId = $link.data('user-id');
		var $row = $link.closest('tr');

		if (!confirm('Are you sure you want to remove this user as a Guide? They will no longer appear on the Guides page.')) {
			return;
		}

		$.ajax({
			url: bdGuidesAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'bd_toggle_guide_status',
				nonce: bdGuidesAdmin.nonce,
				user_id: userId,
				toggle_action: 'remove'
			},
			beforeSend: function() {
				$row.css('opacity', '0.5');
			},
			success: function(response) {
				if (response.success) {
					$row.fadeOut(300, function() {
						$(this).remove();
						// Check if no guides left
						if ($('#bd-guides-sortable tr').length === 0) {
							$('.bd-guides-list table').replaceWith(
								'<div class="bd-no-guides"><p>No guides yet. Add your first guide above!</p></div>'
							);
						}
					});
				} else {
					alert(response.data.message || 'An error occurred.');
					$row.css('opacity', '1');
				}
			},
			error: function() {
				alert('An error occurred. Please try again.');
				$row.css('opacity', '1');
			}
		});
	}

	/**
	 * Handle order change
	 *
	 * @param {Event} e Change event.
	 */
	function handleOrderChange(e) {
		var $input = $(this);
		var userId = $input.closest('tr').data('user-id');
		var newOrder = $input.val();

		// Debounce the update
		clearTimeout($input.data('timer'));
		$input.data('timer', setTimeout(function() {
			updateGuideOrder(userId, newOrder);
		}, 500));
	}

	/**
	 * Update guide order via AJAX
	 *
	 * @param {number} userId  User ID.
	 * @param {number} order   New order value.
	 */
	function updateGuideOrder(userId, order) {
		$.ajax({
			url: bdGuidesAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'bd_update_guide_order',
				nonce: bdGuidesAdmin.nonce,
				user_id: userId,
				order: order
			},
			success: function(response) {
				if (response.success) {
					// Visual feedback
					var $row = $('tr[data-user-id="' + userId + '"]');
					$row.addClass('updated');
					setTimeout(function() {
						$row.removeClass('updated');
					}, 1000);
				}
			}
		});
	}

	// Initialize on document ready
	$(document).ready(init);

})(jQuery);
