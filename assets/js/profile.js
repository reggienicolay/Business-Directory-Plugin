/**
 * Unified Profile JavaScript
 *
 * Handles all profile functionality:
 * - Edit form interactions
 * - Copy URL functionality  
 * - Reviews toggle
 * - Badge hover effects
 * - Stats animation
 * - Cover photo parallax
 *
 * Replaces: profile.js, public-profile.js, public-profile-settings.js
 *
 * @package BusinessDirectory
 * @version 3.0.0
 */

(function($) {
	'use strict';

	const BD_Profile = {

		/**
		 * Initialize all profile features
		 */
		init: function() {
			this.bindEvents();
			this.checkMessages();
			this.initReviewsToggle();
			this.initBadgeHover();
			this.initStatsCounter();
			this.initParallaxCover();
			this.initCopyUrl();
			this.initVisibilityRadios();
			this.initCheckboxStyling();
		},

		/**
		 * Bind DOM events
		 */
		bindEvents: function() {
			// Edit profile toggle
			$(document).on('click', '.bd-edit-profile-btn', this.showEditForm);
			$(document).on('click', '.bd-cancel-edit', this.hideEditForm);

			// Form submission
			$(document).on('submit', '#bd-edit-profile-form', this.handleSubmit);

			// Email change
			$(document).on('click', '.bd-change-email-btn', this.showEmailChange);
			$(document).on('click', '.bd-cancel-email-change', this.hideEmailChange);
			$(document).on('click', '.bd-send-verification', this.sendEmailVerification);

			// Smooth scroll for anchor links
			$('a[href^="#bd-"]').on('click', function(e) {
				e.preventDefault();
				var target = $(this.hash);
				if (target.length) {
					$('html, body').animate({
						scrollTop: target.offset().top - 80
					}, 500, 'swing');
				}
			});
		},

		/**
		 * Check for URL messages (email verification results)
		 */
		checkMessages: function() {
			var urlParams = new URLSearchParams(window.location.search);

			if (urlParams.get('email_updated') === '1') {
				this.showMessage('success', 'Email address updated successfully!');
				window.history.replaceState({}, '', window.location.pathname);
			}

			if (urlParams.get('email_error')) {
				var errors = {
					'invalid': 'Invalid verification link.',
					'expired': 'Verification link has expired. Please request a new one.',
					'taken': 'This email address is already in use.'
				};
				this.showMessage('error', errors[urlParams.get('email_error')] || 'Email verification failed.');
				window.history.replaceState({}, '', window.location.pathname);
			}
		},

		// =========================================================================
		// EDIT FORM
		// =========================================================================

		/**
		 * Show edit form
		 */
		showEditForm: function(e) {
			e.preventDefault();
			
			$('#bd-edit-profile-section').slideDown(300);
			$('.bd-edit-profile-btn').hide();
			
			// Scroll to form
			$('html, body').animate({
				scrollTop: $('#bd-edit-profile-section').offset().top - 100
			}, 300);
		},

		/**
		 * Hide edit form
		 */
		hideEditForm: function(e) {
			e.preventDefault();
			
			$('#bd-edit-profile-section').slideUp(300);
			$('.bd-edit-profile-btn').show();
			
			// Clear messages
			$('.bd-edit-profile-messages').empty();
		},

		/**
		 * Handle form submission
		 */
		handleSubmit: function(e) {
			e.preventDefault();

			var $form = $(this);
			var $btn = $form.find('button[type="submit"]');
			var $messages = $form.closest('.bd-edit-profile-card').find('.bd-edit-profile-messages');

			$messages.empty();

			// Show loading
			$btn.addClass('bd-loading').prop('disabled', true);
			var originalText = $btn.html();
			$btn.html('<i class="fa-solid fa-spinner fa-spin"></i> ' + (bdProfile.i18n.saving || 'Saving...'));

			var formData = new FormData($form[0]);

			$.ajax({
				url: bdProfile.ajaxUrl,
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				success: function(response) {
					if (response.success) {
						BD_Profile.showMessage('success', response.data.message, $messages);
						
						// Update display name in header if changed
						if (response.data.updated && response.data.updated.includes('display_name')) {
							var newName = $('#bd-display-name').val();
							$('.bd-profile-name').contents().first().replaceWith(newName + ' ');
						}

						// Hide form after brief delay and reload
						setTimeout(function() {
							window.location.reload();
						}, 1500);
					} else {
						BD_Profile.showMessage('error', response.data.message, $messages);
					}
				},
				error: function() {
					BD_Profile.showMessage('error', bdProfile.i18n.error || 'An error occurred. Please try again.', $messages);
				},
				complete: function() {
					$btn.removeClass('bd-loading').prop('disabled', false);
					$btn.html(originalText);
				}
			});
		},

		// =========================================================================
		// EMAIL CHANGE
		// =========================================================================

		/**
		 * Show email change form
		 */
		showEmailChange: function(e) {
			e.preventDefault();
			$('.bd-change-email-btn').hide();
			$('.bd-email-change-form').slideDown(200);
			$('#bd-new-email').focus();
		},

		/**
		 * Hide email change form
		 */
		hideEmailChange: function(e) {
			e.preventDefault();
			$('.bd-email-change-form').slideUp(200);
			$('.bd-change-email-btn').show();
			$('#bd-new-email').val('');
		},

		/**
		 * Send email verification
		 */
		sendEmailVerification: function(e) {
			e.preventDefault();

			var $btn = $(this);
			var newEmail = $('#bd-new-email').val().trim();
			var $messages = $('.bd-edit-profile-messages');

			if (!newEmail) {
				BD_Profile.showMessage('error', 'Please enter an email address.', $messages);
				return;
			}

			if (!BD_Profile.isValidEmail(newEmail)) {
				BD_Profile.showMessage('error', 'Please enter a valid email address.', $messages);
				return;
			}

			$btn.addClass('bd-loading').prop('disabled', true);
			var originalText = $btn.html();
			$btn.html('<i class="fa-solid fa-spinner fa-spin"></i>');

			$.ajax({
				url: bdProfile.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bd_request_email_change',
					nonce: $('input[name="nonce"]').val(),
					new_email: newEmail
				},
				success: function(response) {
					if (response.success) {
						BD_Profile.showMessage('success', response.data.message, $messages);
						BD_Profile.hideEmailChange({ preventDefault: function() {} });
					} else {
						BD_Profile.showMessage('error', response.data.message, $messages);
					}
				},
				error: function() {
					BD_Profile.showMessage('error', 'An error occurred. Please try again.', $messages);
				},
				complete: function() {
					$btn.removeClass('bd-loading').prop('disabled', false);
					$btn.html(originalText);
				}
			});
		},

		// =========================================================================
		// COPY URL
		// =========================================================================

		/**
		 * Initialize copy URL functionality
		 */
		initCopyUrl: function() {
			var self = this;
			
			$(document).on('click', '.bd-copy-url-btn', function(e) {
				e.preventDefault();
				
				var $btn = $(this);
				var url = $btn.data('url') || $('#bd-public-profile-url').val();
				
				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(url).then(function() {
						self.showCopySuccess($btn);
					}).catch(function() {
						self.fallbackCopy(url, $btn);
					});
				} else {
					self.fallbackCopy(url, $btn);
				}
			});
		},

		/**
		 * Fallback copy for older browsers
		 */
		fallbackCopy: function(text, $btn) {
			var $temp = $('<textarea>');
			$('body').append($temp);
			$temp.val(text).select();
			
			try {
				document.execCommand('copy');
				this.showCopySuccess($btn);
			} catch (err) {
				this.showCopyError($btn);
			}
			
			$temp.remove();
		},

		/**
		 * Show copy success state
		 */
		showCopySuccess: function($btn) {
			var originalHtml = $btn.html();
			
			$btn.addClass('bd-copied');
			$btn.html('<i class="fa-solid fa-check"></i> <span>' + (bdProfile.i18n.copied || 'Copied!') + '</span>');
			
			setTimeout(function() {
				$btn.removeClass('bd-copied');
				$btn.html(originalHtml);
			}, 2000);
		},

		/**
		 * Show copy error state
		 */
		showCopyError: function($btn) {
			var originalHtml = $btn.html();
			
			$btn.html('<i class="fa-solid fa-times"></i> <span>' + (bdProfile.i18n.copyError || 'Error') + '</span>');
			
			setTimeout(function() {
				$btn.html(originalHtml);
			}, 2000);
		},

		// =========================================================================
		// VISIBILITY CONTROLS
		// =========================================================================

		/**
		 * Initialize visibility radio buttons
		 */
		initVisibilityRadios: function() {
			$(document).on('change', '.bd-radio-option input[type="radio"]', function() {
				var $group = $(this).closest('.bd-radio-group');
				$group.find('.bd-radio-option').removeClass('bd-selected');
				$(this).closest('.bd-radio-option').addClass('bd-selected');
			});
		},

		/**
		 * Initialize checkbox styling (fallback for browsers without :has())
		 */
		initCheckboxStyling: function() {
			$(document).on('change', '.bd-checkbox-option input[type="checkbox"]', function() {
				var $option = $(this).closest('.bd-checkbox-option');
				if ($(this).is(':checked')) {
					$option.addClass('bd-checked');
				} else {
					$option.removeClass('bd-checked');
				}
			});
		},

		// =========================================================================
		// REVIEWS TOGGLE
		// =========================================================================

		/**
		 * Initialize reviews collapse/expand
		 */
		initReviewsToggle: function() {
			var $reviewsList = $('.bd-reviews-list');
			var $toggle = $('.bd-reviews-toggle');
			var reviewCount = $reviewsList.find('.bd-review-card').length;

			if (reviewCount <= 3) {
				$toggle.hide();
				$reviewsList.removeClass('bd-collapsed');
				return;
			}

			$reviewsList.addClass('bd-collapsed');

			$toggle.on('click', function() {
				var isCollapsed = $reviewsList.hasClass('bd-collapsed');
				var hiddenCount = reviewCount - 3;

				if (isCollapsed) {
					$reviewsList.removeClass('bd-collapsed');
					$reviewsList.find('.bd-review-card').each(function(index) {
						if (index >= 3) {
							$(this).css({
								opacity: 0,
								transform: 'translateY(20px)'
							}).animate({
								opacity: 1
							}, {
								duration: 300,
								step: function(now, fx) {
									if (fx.prop === 'opacity') {
										$(this).css('transform', 'translateY(' + (20 - (now * 20)) + 'px)');
									}
								}
							});
						}
					});
					
					$toggle.addClass('bd-expanded');
					$toggle.find('span').text(bdProfile.i18n.showLess || 'Show Less');
				} else {
					$reviewsList.addClass('bd-collapsed');
					$toggle.removeClass('bd-expanded');
					$toggle.find('span').text((bdProfile.i18n.showMore || 'Show') + ' ' + hiddenCount + ' More Reviews');

					$('html, body').animate({
						scrollTop: $('.bd-profile-section:has(.bd-reviews-list)').offset().top - 100
					}, 300);
				}
			});
		},

		// =========================================================================
		// BADGE EFFECTS
		// =========================================================================

		/**
		 * Initialize badge hover effects
		 */
		initBadgeHover: function() {
			$('.bd-badge-card, .bd-badge-item').each(function() {
				var $badge = $(this);
				
				$badge.on('mouseenter', function() {
					var rarity = $badge.data('rarity');
					var glowColor = BD_Profile.getGlowColor(rarity);
					
					$badge.css({
						'box-shadow': '0 8px 24px ' + glowColor
					});
				}).on('mouseleave', function() {
					$badge.css({
						'box-shadow': ''
					});
				});
			});
		},

		/**
		 * Get glow color based on badge rarity
		 */
		getGlowColor: function(rarity) {
			var colors = {
				'common': 'rgba(100, 116, 139, 0.2)',
				'rare': 'rgba(59, 130, 246, 0.25)',
				'epic': 'rgba(139, 92, 246, 0.25)',
				'legendary': 'rgba(245, 158, 11, 0.3)',
				'special': 'rgba(19, 52, 83, 0.25)'
			};
			return colors[rarity] || colors['common'];
		},

		// =========================================================================
		// STATS ANIMATION
		// =========================================================================

		/**
		 * Initialize animated stats counter on scroll
		 */
		initStatsCounter: function() {
			var $stats = $('.bd-profile-stats');
			var animated = false;

			if (!$stats.length) return;

			function animateStats() {
				if (animated) return;
				
				var statsTop = $stats.offset().top;
				var windowBottom = $(window).scrollTop() + $(window).height();

				if (windowBottom > statsTop + 50) {
					animated = true;
					
					$stats.find('.bd-stat-value').each(function() {
						var $this = $(this);
						var target = parseInt($this.text().replace(/,/g, ''), 10);
						
						if (isNaN(target) || target === 0) return;

						$({ count: 0 }).animate({ count: target }, {
							duration: 1000,
							easing: 'swing',
							step: function() {
								$this.text(Math.floor(this.count).toLocaleString());
							},
							complete: function() {
								$this.text(target.toLocaleString());
							}
						});
					});
				}
			}

			animateStats();
			$(window).on('scroll', animateStats);
		},

		// =========================================================================
		// PARALLAX
		// =========================================================================

		/**
		 * Initialize subtle parallax effect on cover photo
		 */
		initParallaxCover: function() {
			var $cover = $('.bd-profile-cover');
			
			if (!$cover.length || $cover.hasClass('bd-cover-default')) return;

			$(window).on('scroll', function() {
				var scrollTop = $(window).scrollTop();
				var heroHeight = $('.bd-profile-hero').height();
				
				if (scrollTop < heroHeight) {
					var parallax = scrollTop * 0.3;
					$cover.css('transform', 'translateY(' + parallax + 'px) scale(1.1)');
				}
			});

			$cover.css({
				'transform': 'scale(1.1)',
				'transition': 'transform 0.1s ease-out'
			});
		},

		// =========================================================================
		// UTILITIES
		// =========================================================================

		/**
		 * Show message
		 */
		showMessage: function(type, message, $container) {
			if (!$container || !$container.length) {
				$container = $('.bd-edit-profile-messages');
			}

			var icon = type === 'success'
				? '<i class="fa-solid fa-check-circle"></i>'
				: '<i class="fa-solid fa-exclamation-circle"></i>';

			var html = '<div class="bd-profile-message bd-profile-message-' + type + '">' +
				icon + '<span>' + message + '</span></div>';

			$container.html(html).show();

			if ($container.offset()) {
				$('html, body').animate({
					scrollTop: $container.offset().top - 120
				}, 300);
			}

			if (type === 'success') {
				setTimeout(function() {
					$container.find('.bd-profile-message').fadeOut(300);
				}, 5000);
			}
		},

		/**
		 * Validate email format
		 */
		isValidEmail: function(email) {
			var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
			return re.test(email);
		}
	};

	// Initialize on DOM ready
	$(function() {
		BD_Profile.init();
	});

	// Expose globally
	window.BD_Profile = BD_Profile;

})(jQuery);
