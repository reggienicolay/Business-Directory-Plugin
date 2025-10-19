/**
 * Admin Claims Queue JavaScript
 */
(function ($) {
	'use strict';

	let currentClaimId = null;

	$( document ).ready(
		function () {

			// Approve button click
			$( '.bd-approve-btn' ).on(
				'click',
				function () {
					currentClaimId = $( this ).data( 'claim-id' );
					$( '#bd-approve-modal' ).fadeIn( 200 );
				}
			);

			// Reject button click
			$( '.bd-reject-btn' ).on(
				'click',
				function () {
					currentClaimId = $( this ).data( 'claim-id' );
					$( '#bd-reject-modal' ).fadeIn( 200 );
				}
			);

			// Modal close buttons
			$( '.bd-modal-close, .bd-modal-cancel' ).on(
				'click',
				function () {
					$( '.bd-modal' ).fadeOut( 200 );
					currentClaimId = null;
					$( '#bd-approve-notes' ).val( '' );
					$( '#bd-reject-notes' ).val( '' );
				}
			);

			// Close modal on background click
			$( '.bd-modal' ).on(
				'click',
				function (e) {
					if ($( e.target ).hasClass( 'bd-modal' )) {
						$( this ).fadeOut( 200 );
						currentClaimId = null;
					}
				}
			);

			// Confirm Approve
			$( '.bd-confirm-approve' ).on(
				'click',
				function () {
					if ( ! currentClaimId) {
						return;
					}

					const notes = $( '#bd-approve-notes' ).val();
					const $card = $( `.bd - claim - card[data - claim - id = "${currentClaimId}"]` );

					// Show loading state
					$card.addClass( 'loading' );
					$( this ).prop( 'disabled', true ).text( 'Approving...' );

					$.ajax(
						{
							url: bdClaimsAdmin.ajaxUrl,
							method: 'POST',
							data: {
								action: 'bd_approve_claim_ajax',
								claim_id: currentClaimId,
								notes: notes,
								nonce: bdClaimsAdmin.nonce
							},
							success: function (response) {
								if (response.success) {
									// Hide modal
									$( '#bd-approve-modal' ).fadeOut( 200 );

									// Show success message
									showNotice( 'success', '✓ Claim approved successfully! User has been notified.' );

									// Remove card with animation
									$card.slideUp(
										400,
										function () {
											$( this ).remove();
											updatePendingCount();
											checkEmptyState();
										}
									);
								} else {
									showNotice( 'error', 'Error: ' + (response.data || 'Failed to approve claim') );
									$card.removeClass( 'loading' );
								}
							},
							error: function () {
								showNotice( 'error', 'Network error. Please try again.' );
								$card.removeClass( 'loading' );
							},
							complete: function () {
								$( '.bd-confirm-approve' ).prop( 'disabled', false ).text( 'Confirm Approval' );
								currentClaimId = null;
							}
						}
					);
				}
			);

			// Confirm Reject
			$( '.bd-confirm-reject' ).on(
				'click',
				function () {
					if ( ! currentClaimId) {
						return;
					}

					const notes = $( '#bd-reject-notes' ).val().trim();

					if ( ! notes) {
						alert( 'Please provide a reason for rejection.' );
						return;
					}

					const $card = $( `.bd - claim - card[data - claim - id = "${currentClaimId}"]` );

					// Show loading state
					$card.addClass( 'loading' );
					$( this ).prop( 'disabled', true ).text( 'Rejecting...' );

					$.ajax(
						{
							url: bdClaimsAdmin.ajaxUrl,
							method: 'POST',
							data: {
								action: 'bd_reject_claim_ajax',
								claim_id: currentClaimId,
								notes: notes,
								nonce: bdClaimsAdmin.nonce
							},
							success: function (response) {
								if (response.success) {
									// Hide modal
									$( '#bd-reject-modal' ).fadeOut( 200 );

									// Show info message
									showNotice( 'info', 'Claim rejected. Claimant has been notified.' );

									// Remove card with animation
									$card.slideUp(
										400,
										function () {
											$( this ).remove();
											updatePendingCount();
											checkEmptyState();
										}
									);
								} else {
									showNotice( 'error', 'Error: ' + (response.data || 'Failed to reject claim') );
									$card.removeClass( 'loading' );
								}
							},
							error: function () {
								showNotice( 'error', 'Network error. Please try again.' );
								$card.removeClass( 'loading' );
							},
							complete: function () {
								$( '.bd-confirm-reject' ).prop( 'disabled', false ).text( 'Confirm Rejection' );
								$( '#bd-reject-notes' ).val( '' );
								currentClaimId = null;
							}
						}
					);
				}
			);

			// Helper: Show notice
			function showNotice(type, message) {
				const $notice = $(
					'<div>',
					{
						class: `notice notice - ${type} is - dismissible`,
						html: ` < p > ${message} < / p > `
					}
				);

				$( '.bd-claims-admin h1' ).after( $notice );

				// Auto-dismiss after 5 seconds
				setTimeout(
					function () {
						$notice.fadeOut(
							400,
							function () {
								$( this ).remove();
							}
						);
					},
					5000
				);
			}

			// Helper: Update pending count in menu
			function updatePendingCount() {
				const remainingCount = $( '.bd-claim-card' ).length;

				// Update stat card
				$( '.bd-stat-value' ).first().text( remainingCount );

				// Update menu badge
				if (remainingCount > 0) {
					$( '.pending-count' ).text( remainingCount );
				} else {
					$( '.awaiting-mod' ).remove();
				}
			}

			// Helper: Check if empty and show empty state
			function checkEmptyState() {
				if ($( '.bd-claim-card' ).length === 0) {
					$( '.bd-claims-list' ).html(
						`
						< div class = "bd-empty-state" >
						< div class = "bd-empty-icon" > ✅ < / div >
						< h2 > All caught up ! < / h2 >
						< p > No pending claim requests at the moment.< / p >
						< / div >
						`
					);
				}
			}

			// Keyboard shortcuts
			$( document ).on(
				'keydown',
				function (e) {
					// ESC to close modals
					if (e.key === 'Escape') {
						$( '.bd-modal:visible' ).fadeOut( 200 );
						currentClaimId = null;
					}
				}
			);
		}
	);

})( jQuery );

// AJAX handlers for WordPress
jQuery( document ).ready(
	function ($) {

		// Register AJAX approve handler
		$( document ).on(
			'click',
			'.bd-confirm-approve',
			function () {
				// Handled by main script above
			}
		);

	}
);

// Add AJAX actions to PHP
// This needs to be added to the ClaimsQueue.php __construct:
/*
add_action('wp_ajax_bd_approve_claim_ajax', [$this, 'ajax_approve']);
add_action('wp_ajax_bd_reject_claim_ajax', [$this, 'ajax_reject']);
*/