/**
 * Cover Moderation Admin Script
 *
 * @package BusinessDirectory
 * @since 1.2.0
 */

(function ($) {
	'use strict';

	$(function () {
		// Remove cover
		$(document).on('click', '.bd-remove-cover', function () {
			const $card = $(this).closest('.bd-cover-card');
			const listId = $card.data('list-id');

			if (!confirm('Remove this cover? The list will revert to using its first business image.')) {
				return;
			}

			$card.addClass('processing');

			$.ajax({
				url: bdCoverMod.ajaxUrl,
				method: 'POST',
				data: {
					action: 'bd_moderate_cover',
					nonce: bdCoverMod.nonce,
					list_id: listId,
					mod_action: 'remove'
				},
				success: function (response) {
					if (response.success) {
						$card.addClass('removed').fadeOut(300, function () {
							$(this).remove();
							
							// Check if grid is empty
							if ($('.bd-cover-card').length === 0) {
								$('.bd-covers-grid').html(
									'<div class="bd-no-covers"><p>No covers found matching this filter.</p></div>'
								);
							}
						});
					} else {
						alert('Error: ' + (response.data || 'Could not remove cover'));
						$card.removeClass('processing');
					}
				},
				error: function () {
					alert('Network error. Please try again.');
					$card.removeClass('processing');
				}
			});
		});
	});

})(jQuery);
