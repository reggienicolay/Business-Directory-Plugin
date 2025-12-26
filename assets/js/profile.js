/**
 * Profile Page JavaScript
 *
 * Handles profile editing form and interactions.
 *
 * @package BusinessDirectory
 * @version 1.0.0
 */

(function($) {
	'use strict';

	const BD_Profile = {

		/**
		 * Initialize
		 */
		init: function() {
			this.bindEvents();
			this.checkMessages();
		},

		/**
		 * Bind events
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
		},

		/**
		 * Check for URL messages (email verification results)
		 */
		checkMessages: function() {
			const urlParams = new URLSearchParams(window.location.search);

			if (urlParams.get('email_updated') === '1') {
				this.showMessage('success', 'Email address updated successfully!');
				// Clean URL
				window.history.replaceState({}, '', window.location.pathname);
			}

			if (urlParams.get('email_error')) {
				const errors = {
					'invalid': 'Invalid verification link.',
					'expired': 'Verification link has expired. Please request a new one.',
					'taken': 'This email address is already in use.'
				};
				this.showMessage('error', errors[urlParams.get('email_error')] || 'Email verification failed.');
				window.history.replaceState({}, '', window.location.pathname);
			}
		},

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

			const $form = $(this);
			const $btn = $form.find('button[type="submit"]');
			const $messages = $form.closest('.bd-edit-profile-card').find('.bd-edit-profile-messages');

			// Clear previous messages
			$messages.empty();

			// Show loading
			$btn.addClass('bd-loading').prop('disabled', true);
			const originalText = $btn.html();
			$btn.html('<i class="fa-solid fa-spinner fa-spin"></i> Saving...');

			// Get form data
			const formData = new FormData($form[0]);

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
							const newName = $('#bd-display-name').val();
							$('.bd-profile-info h1').text(newName);
						}

						// Hide form after brief delay
						setTimeout(function() {
							BD_Profile.hideEditForm({ preventDefault: function() {} });
							// Reload to show updated data
							window.location.reload();
						}, 1500);
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

			const $btn = $(this);
			const newEmail = $('#bd-new-email').val().trim();
			const $messages = $('.bd-edit-profile-messages');

			if (!newEmail) {
				BD_Profile.showMessage('error', 'Please enter an email address.', $messages);
				return;
			}

			// Basic email validation
			if (!BD_Profile.isValidEmail(newEmail)) {
				BD_Profile.showMessage('error', 'Please enter a valid email address.', $messages);
				return;
			}

			// Show loading
			$btn.addClass('bd-loading').prop('disabled', true);
			const originalText = $btn.html();
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

		/**
		 * Show message
		 */
		showMessage: function(type, message, $container) {
			if (!$container || !$container.length) {
				$container = $('.bd-edit-profile-messages');
			}

			const icon = type === 'success'
				? '<i class="fa-solid fa-check-circle"></i>'
				: '<i class="fa-solid fa-exclamation-circle"></i>';

			const html = '<div class="bd-profile-message bd-profile-message-' + type + '">' +
				icon + '<span>' + message + '</span></div>';

			$container.html(html).show();

			// Scroll to message
			if ($container.offset()) {
				$('html, body').animate({
					scrollTop: $container.offset().top - 120
				}, 300);
			}

			// Auto-hide success messages
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
			const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
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
