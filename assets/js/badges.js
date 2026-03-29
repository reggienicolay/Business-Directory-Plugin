(function ($) {
	'use strict';

	const BadgeSystem = {

		/**
		 * Initialize
		 */
		init: function () {
			this.initHelpfulVotes();
			this.initTooltips();
			this.initBadgeAnimations();
			this.initShareModal();
		},

		/**
		 * Handle helpful vote buttons
		 */
		initHelpfulVotes: function () {
			$( document ).on(
				'click',
				'.bd-review-helpful',
				function (e) {
					e.preventDefault();

					const $button  = $( this );
					const reviewId = $button.data( 'review-id' );

					if ($button.hasClass( 'voted' )) {
						return;
					}

					$.ajax(
						{
							url: bdBadges.ajaxUrl,
							method: 'POST',
							data: {
								action: 'bd_mark_helpful',
								review_id: reviewId,
								nonce: bdBadges.nonce
							},
							beforeSend: function () {
								$button.prop( 'disabled', true );
							},
							success: function (response) {
								if (response.success) {
									$button.addClass( 'voted' );
									$button.html( '👍 Helpful (' + response.data.count + ')' );

									// Show thank you message
									BadgeSystem.showMessage( 'Thanks for your feedback!', 'success' );
								} else {
									BadgeSystem.showMessage( response.data.message || 'Error marking helpful', 'error' );
									$button.prop( 'disabled', false );
								}
							},
							error: function () {
								BadgeSystem.showMessage( 'Error marking helpful', 'error' );
								$button.prop( 'disabled', false );
							}
						}
					);
				}
			);
		},

		/**
		 * Initialize tooltips
		 */
		initTooltips: function () {
			// Tooltips are handled by CSS :hover pseudo-elements
			// This is just a placeholder for any additional tooltip logic
		},

		/**
		 * Animate badges on page load
		 */
		initBadgeAnimations: function () {
			// Add animation class to newly earned badges
			$( '.bd-badge-new' ).each(
				function (index) {
					const $badge = $( this );
					setTimeout(
						function () {
							$badge.addClass( 'animated' );
						},
						index * 100
					);
				}
			);
		},

		/**
		 * Show temporary message
		 */
		showMessage: function (message, type) {
			const $message = $( '<div>' )
				.addClass( 'bd-flash-message bd-flash-' + type )
				.html( message )
				.css(
					{
						position: 'fixed',
						top: '20px',
						right: '20px',
						padding: '15px 20px',
						background: type === 'success' ? '#10b981' : '#ef4444',
						color: 'white',
						borderRadius: '8px',
						boxShadow: '0 4px 12px rgba(0,0,0,0.15)',
						zIndex: 9999,
						fontSize: '14px',
						fontWeight: '600',
						opacity: 0
					}
				);

			$( 'body' ).append( $message );

			$message.animate( { opacity: 1 }, 300 );

			setTimeout(
				function () {
					$message.animate(
						{ opacity: 0 },
						300,
						function () {
							$message.remove();
						}
					);
				},
				3000
			);
		},

		/**
		 * Badge hover effects
		 */
		initBadgeHover: function () {
			$( '.bd-badge-card' ).hover(
				function () {
					$( this ).css( 'transform', 'translateY(-4px)' );
				},
				function () {
					$( this ).css( 'transform', 'translateY(0)' );
				}
			);
		},

		/**
		 * Badge Share Modal
		 */
		initShareModal: function() {
			// Create modal overlay if it doesn't exist
			if (!document.getElementById('bd-badge-share-overlay')) {
				var overlay = document.createElement('div');
				overlay.id = 'bd-badge-share-overlay';
				overlay.className = 'bd-share-modal-overlay';
				overlay.innerHTML = '<div class="bd-share-modal-content"></div>';
				document.body.appendChild(overlay);

				// Close on overlay click
				overlay.addEventListener('click', function(e) {
					if (e.target === overlay) {
						overlay.classList.remove('bd-active');
					}
				});

				// Close on Escape
				document.addEventListener('keydown', function(e) {
					if (e.key === 'Escape') {
						overlay.classList.remove('bd-active');
					}
				});
			}

			// Share trigger buttons
			document.querySelectorAll('.bd-badge-share-trigger').forEach(function(btn) {
				btn.addEventListener('click', function(e) {
					e.preventDefault();
					e.stopPropagation();
					var badgeKey = this.dataset.badgeKey;
					var overlay = document.getElementById('bd-badge-share-overlay');
					var content = overlay.querySelector('.bd-share-modal-content');

					if ( !content ) { return; }

					// Fetch modal content via AJAX
					jQuery.ajax({
						url: bdBadges.ajaxUrl,
						type: 'POST',
						data: {
							action: 'bd_badge_share_modal',
							badge_key: badgeKey,
							nonce: bdBadges.nonce
						},
						success: function(response) {
							if (response.success && response.data && response.data.html) {
								content.innerHTML = response.data.html;
								overlay.classList.add('bd-active');

								// Wire up close button
								var closeBtn = content.querySelector('.bd-share-modal-close');
								if (closeBtn) {
									closeBtn.addEventListener('click', function() {
										overlay.classList.remove('bd-active');
									});
								}

								// Wire up copy link
								var copyBtn = content.querySelector('.bd-copy-link-btn');
								if (copyBtn) {
									copyBtn.addEventListener('click', function() {
										var url = this.dataset.url;
										navigator.clipboard.writeText(url).then(function() {
											copyBtn.innerHTML = '<i class="fas fa-check"></i> Copied!';
											setTimeout(function() {
												copyBtn.innerHTML = '<i class="fas fa-link"></i> Copy Link';
											}, 2000);
										});
									});
								}

								// Track share clicks
								content.querySelectorAll('.bd-share-card-btn[href]').forEach(function(shareBtn) {
									shareBtn.addEventListener('click', function() {
										jQuery.post(bdBadges.ajaxUrl, {
											action: 'bd_track_badge_share',
											badge_key: badgeKey,
											platform: this.textContent.trim().toLowerCase(),
											nonce: bdBadges.nonce
										});
									});
								});
							}
						}
						},
						error: function() {
							BadgeSystem.showMessage('Unable to load share card. Please try again.', 'error');
						}
						});
			});
		},

		/**
		 * Progress bar animations
		 */
		animateProgressBars: function () {
			$( '.bd-progress-fill' ).each(
				function () {
					const $bar  = $( this );
					const width = $bar.css( 'width' );
					$bar.css( 'width', '0' );

					setTimeout(
						function () {
							$bar.css(
								{
									width: width,
									transition: 'width 1s ease-out'
								}
							);
						},
						100
					);
				}
			);
		}
	};

	// Initialize on document ready
	$( document ).ready(
		function () {
			BadgeSystem.init();
			BadgeSystem.initBadgeHover();
			BadgeSystem.animateProgressBars();
		}
	);

})( jQuery );