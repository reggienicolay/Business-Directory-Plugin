/**
 * Auth JavaScript
 *
 * Handles login, register, password reset forms.
 * Modal popup and user dropdown functionality.
 * Includes auth status check for cached pages.
 *
 * @package BusinessDirectory
 * @version 1.2.0
 */

(function($) {
	'use strict';

	const BD_Auth = {

		/**
		 * Initialize
		 */
		init: function() {
			this.bindEvents();
			this.initModal();
			this.initDropdown();
			this.checkMessages();
			this.checkAuthStatus(); // Check auth on page load for cached pages
		},

		/**
		 * Bind form events
		 */
		bindEvents: function() {
			// Form submissions
			$(document).on('submit', '#bd-login-form, #bd-modal-login-form', this.handleLogin);
			$(document).on('submit', '#bd-register-form, #bd-modal-register-form', this.handleRegister);
			$(document).on('submit', '#bd-reset-form, #bd-modal-reset-form', this.handleReset);

			// Tab switching
			$(document).on('click', '.bd-auth-tab', this.switchTab);

			// Panel links (forgot password, back to login)
			$(document).on('click', '[data-show-panel]', this.showPanel);

			// Modal triggers
			$(document).on('click', '[data-bd-login]', this.openModal);

			// Modal close
			$(document).on('click', '.bd-modal-backdrop, .bd-modal-close', this.closeModal);

			// Escape key closes modal
			$(document).on('keydown', function(e) {
				if (e.key === 'Escape') {
					BD_Auth.closeModal();
				}
			});
		},

		/**
		 * Initialize modal
		 */
		initModal: function() {
			// Check if modal should open on page load
			const urlParams = new URLSearchParams(window.location.search);
			if (urlParams.get('login') === 'required') {
				this.openModal();
			}
		},

		/**
		 * Initialize dropdown
		 */
		initDropdown: function() {
			// Toggle dropdown
			$(document).on('click', '.bd-auth-user-toggle', function(e) {
				e.stopPropagation();
				const $toggle = $(this);
				const expanded = $toggle.attr('aria-expanded') === 'true';
				$toggle.attr('aria-expanded', !expanded);
			});

			// Close dropdown on outside click
			$(document).on('click', function(e) {
				if (!$(e.target).closest('.bd-auth-user-wrapper').length) {
					$('.bd-auth-user-toggle').attr('aria-expanded', 'false');
				}
			});
		},

		/**
		 * Check for URL messages
		 */
		checkMessages: function() {
			const urlParams = new URLSearchParams(window.location.search);

			if (urlParams.get('registered') === '1') {
				this.showMessage('success', bdAuth.i18n?.redirecting || 'Account created! Welcome!');
			}

			if (urlParams.get('reset') === 'success') {
				this.showMessage('success', 'Password reset successful. Please sign in.');
			}

			if (urlParams.get('loggedout') === 'true') {
				this.showMessage('success', 'You have been signed out.');
			}
		},

		/**
		 * Check auth status and update header (handles cached pages)
		 */
		checkAuthStatus: function() {
			// Skip on login page
			if (window.location.pathname.includes('/login')) {
				return;
			}

			// Skip if bdAuth is not defined
			if (typeof bdAuth === 'undefined' || !bdAuth.ajaxUrl) {
				return;
			}

			$.ajax({
				url: bdAuth.ajaxUrl,
				type: 'POST',
				data: { action: 'bd_check_auth' },
				success: function(response) {
					if (response.success) {
						if (response.data.logged_in) {
							BD_Auth.showLoggedInHeader(response.data.user, response.data.urls);
						} else {
							BD_Auth.showLoggedOutHeader();
						}
					}
				}
			});
		},

		/**
		 * Update header to logged-in state
		 */
		showLoggedInHeader: function(user, urls) {
			var $loggedOut = $('.bd-auth-logged-out');

			// Only swap if we're showing logged-out state
			if (!$loggedOut.length || !$loggedOut.is(':visible')) {
				return;
			}

			// Get style class from current element
			var styleClass = 'bd-auth-style-default';
			var classList = $loggedOut.attr('class').split(/\s+/);
			classList.forEach(function(cls) {
				if (cls.indexOf('bd-auth-style-') === 0) {
					styleClass = cls;
				}
			});

			// Build logged-in HTML matching HeaderButtons.php output
			var html = '<div class="bd-auth-buttons bd-auth-logged-in ' + styleClass + '">' +
				'<div class="bd-auth-user-wrapper">' +
					'<button type="button" class="bd-auth-user-toggle" aria-expanded="false">' +
						'<span class="bd-auth-avatar">' +
							'<img src="' + user.avatar + '" alt="" width="32" height="32" class="avatar avatar-32 photo">' +
						'</span>' +
						'<span class="bd-auth-name">' + BD_Auth.escapeHtml(user.display_name) + '</span>' +
						'<svg class="bd-auth-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
							'<polyline points="6 9 12 15 18 9"></polyline>' +
						'</svg>' +
					'</button>' +
					'<div class="bd-auth-dropdown">' +
						'<div class="bd-auth-dropdown-header">' +
							'<span class="bd-auth-dropdown-name">' + BD_Auth.escapeHtml(user.full_name) + '</span>' +
							'<span class="bd-auth-dropdown-email">' + BD_Auth.escapeHtml(user.email) + '</span>' +
						'</div>' +
						'<ul class="bd-auth-dropdown-menu">' +
							'<li>' +
								'<a href="' + urls.profile + '">' +
									'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
										'<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>' +
										'<circle cx="12" cy="7" r="4"></circle>' +
									'</svg>' +
									'My Profile' +
								'</a>' +
							'</li>' +
							'<li>' +
								'<a href="' + urls.lists + '">' +
									'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
										'<path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>' +
									'</svg>' +
									'My Lists' +
								'</a>' +
							'</li>' +
							(user.has_businesses ? 
							'<li>' +
								'<a href="' + urls.edit_listing + '">' +
									'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
										'<path d="M12 20h9"></path>' +
										'<path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path>' +
									'</svg>' +
									'My Business' +
											'</a>' +
											'</li>' +
							'<li>' +
								'<a href="' + urls.business_tools + '">' +
									'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
										'<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path>' +
									'</svg>' +
									'Business Tools' +
								'</a>' +
							'</li>' : '') +
							(user.is_admin ? 
							'<li class="bd-auth-dropdown-divider"></li>' +
							'<li>' +
								'<a href="' + urls.admin + '">' +
									'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
										'<circle cx="12" cy="12" r="3"></circle>' +
										'<path d="M12 1v4M12 19v4M4.2 4.2l2.8 2.8M17 17l2.8 2.8"></path>' +
										'<path d="M1 12h4M19 12h4M4.2 19.8l2.8-2.8M17 7l2.8-2.8"></path>' +
									'</svg>' +
									'Admin' +
								'</a>' +
							'</li>' : '') +
							'<li class="bd-auth-dropdown-divider"></li>' +
							'<li>' +
								'<a href="' + urls.logout + '" class="bd-auth-logout-link">' +
									'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
										'<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>' +
										'<polyline points="16 17 21 12 16 7"></polyline>' +
										'<line x1="21" y1="12" x2="9" y2="12"></line>' +
									'</svg>' +
									'Sign Out' +
								'</a>' +
							'</li>' +
						'</ul>' +
					'</div>' +
				'</div>' +
			'</div>';

			// Replace logged-out with logged-in
			$loggedOut.replaceWith(html);

			// Re-init dropdown functionality
			BD_Auth.initDropdown();
		},

		/**
		 * Update header to logged-out state (usually not needed - cached page already shows this)
		 */
		showLoggedOutHeader: function() {
			// Check if we're incorrectly showing logged-in state
			var $loggedIn = $('.bd-auth-logged-in');

			if (!$loggedIn.length || !$loggedIn.is(':visible')) {
				return;
			}

			// Page is cached with logged-in state but user is logged out
			// Reload to get fresh page
			window.location.reload();
		},

		/**
		 * Escape HTML to prevent XSS
		 */
		escapeHtml: function(text) {
			var div = document.createElement('div');
			div.textContent = text;
			return div.innerHTML;
		},

		/**
		 * Get fresh nonce from server (bypasses page cache)
		 */
		getFreshNonce: function() {
			return $.ajax({
				url: bdAuth.ajaxUrl,
				type: 'POST',
				data: { action: 'bd_get_nonce' }
			}).then(function(response) {
				if (response.success && response.data.nonce) {
					return response.data.nonce;
				}
				return null;
			}).catch(function() {
				return null;
			});
		},

		/**
		 * Handle login form
		 */
		handleLogin: function(e) {
			e.preventDefault();

			const $form = $(this);
			const $btn = $form.find('button[type="submit"]');
			const $messages = $form.closest('.bd-auth-panel, .bd-modal-content').find('.bd-auth-messages');

			// Clear messages
			$messages.empty();

			// Show loading
			$btn.addClass('bd-loading').prop('disabled', true);
			$btn.data('original-text', $btn.text());

			// Get fresh nonce first, then submit
			BD_Auth.getFreshNonce().then(function(freshNonce) {
				// Get form data
				const formData = new FormData($form[0]);

				// Use fresh nonce if available
				if (freshNonce) {
					formData.set('nonce', freshNonce);
				}

				// Set redirect_to if empty
				if (!formData.get('redirect_to')) {
					formData.set('redirect_to', bdAuth.redirectTo || window.location.href);
				}

				$.ajax({
					url: bdAuth.ajaxUrl,
					type: 'POST',
					data: formData,
					processData: false,
					contentType: false,
					success: function(response) {
						if (response.success) {
							BD_Auth.showMessage('success', response.data.message, $messages);

							// Redirect after short delay with cache-busting param
							setTimeout(function() {
								var redirectUrl = response.data.redirect || window.location.href;
								// Add cache-busting parameter to force fresh page load
								redirectUrl += (redirectUrl.indexOf('?') > -1 ? '&' : '?') + '_nocache=' + Date.now();
								window.location.href = redirectUrl;
							}, 500);
						} else {
							BD_Auth.showMessage('error', response.data.message, $messages);
							$btn.removeClass('bd-loading').prop('disabled', false);
						}
					},
					error: function() {
						BD_Auth.showMessage('error', bdAuth.i18n?.error || 'An error occurred.', $messages);
						$btn.removeClass('bd-loading').prop('disabled', false);
					}
				});
			});
		},

		/**
		 * Handle register form
		 */
		handleRegister: function(e) {
			e.preventDefault();

			const $form = $(this);
			const $btn = $form.find('button[type="submit"]');
			const $messages = $form.closest('.bd-auth-panel, .bd-modal-content').find('.bd-auth-messages');

			// Clear messages
			$messages.empty();

			// Client-side validation
			const password = $form.find('input[name="password"]').val();
			if (password.length < 8) {
				BD_Auth.showMessage('error', 'Password must be at least 8 characters.', $messages);
				return;
			}

			// Show loading
			$btn.addClass('bd-loading').prop('disabled', true);

			// Get fresh nonce first, then submit
			BD_Auth.getFreshNonce().then(function(freshNonce) {
				// Get form data
				const formData = new FormData($form[0]);

				// Use fresh nonce if available
				if (freshNonce) {
					formData.set('nonce', freshNonce);
				}

				// Set redirect_to if empty
				if (!formData.get('redirect_to')) {
					formData.set('redirect_to', bdAuth.redirectTo || window.location.href);
				}

				$.ajax({
					url: bdAuth.ajaxUrl,
					type: 'POST',
					data: formData,
					processData: false,
					contentType: false,
					success: function(response) {
						if (response.success) {
							BD_Auth.showMessage('success', response.data.message, $messages);

							// Redirect after short delay with cache-busting param
							setTimeout(function() {
								var redirectUrl = response.data.redirect || window.location.href;
								// Add cache-busting parameter to force fresh page load
								redirectUrl += (redirectUrl.indexOf('?') > -1 ? '&' : '?') + '_nocache=' + Date.now();
								window.location.href = redirectUrl;
							}, 1000);
						} else {
							BD_Auth.showMessage('error', response.data.message, $messages);
							$btn.removeClass('bd-loading').prop('disabled', false);
						}
					},
					error: function() {
						BD_Auth.showMessage('error', bdAuth.i18n?.error || 'An error occurred.', $messages);
						$btn.removeClass('bd-loading').prop('disabled', false);
					}
				});
			});
		},

		/**
		 * Handle password reset form
		 */
		handleReset: function(e) {
			e.preventDefault();

			const $form = $(this);
			const $btn = $form.find('button[type="submit"]');
			const $messages = $form.closest('.bd-auth-panel, .bd-modal-content').find('.bd-auth-messages');

			// Clear messages
			$messages.empty();

			// Show loading
			$btn.addClass('bd-loading').prop('disabled', true);

			// Get fresh nonce first, then submit
			BD_Auth.getFreshNonce().then(function(freshNonce) {
				// Get form data
				const formData = new FormData($form[0]);

				// Use fresh nonce if available
				if (freshNonce) {
					formData.set('nonce', freshNonce);
				}

				$.ajax({
					url: bdAuth.ajaxUrl,
					type: 'POST',
					data: formData,
					processData: false,
					contentType: false,
					success: function(response) {
						if (response.success) {
							BD_Auth.showMessage('success', response.data.message, $messages);
							$form[0].reset();
						} else {
							BD_Auth.showMessage('error', response.data.message, $messages);
						}
						$btn.removeClass('bd-loading').prop('disabled', false);
					},
					error: function() {
						BD_Auth.showMessage('error', bdAuth.i18n?.error || 'An error occurred.', $messages);
						$btn.removeClass('bd-loading').prop('disabled', false);
					}
				});
			});
		},

		/**
		 * Switch tab
		 */
		switchTab: function(e) {
			e.preventDefault();

			const $tab = $(this);
			const tab = $tab.data('tab');
			const $container = $tab.closest('.bd-auth-container, .bd-modal-content');

			// Update tabs
			$container.find('.bd-auth-tab').removeClass('active');
			$tab.addClass('active');

			// Update panels
			$container.find('.bd-auth-panel').removeClass('active');
			$container.find('.bd-auth-panel[data-panel="' + tab + '"]').addClass('active');

			// Clear messages
			$container.find('.bd-auth-messages').empty();
		},

		/**
		 * Show specific panel
		 */
		showPanel: function(e) {
			e.preventDefault();

			const panel = $(this).data('show-panel');
			const $container = $(this).closest('.bd-auth-container, .bd-modal-content');

			// Update tabs
			$container.find('.bd-auth-tab').removeClass('active');
			$container.find('.bd-auth-tab[data-tab="' + panel + '"]').addClass('active');

			// Update panels
			$container.find('.bd-auth-panel').removeClass('active');
			$container.find('.bd-auth-panel[data-panel="' + panel + '"]').addClass('active');

			// Clear messages
			$container.find('.bd-auth-messages').empty();
		},

		/**
		 * Open modal
		 */
		openModal: function(e) {
			if (e) {
				e.preventDefault();
			}

			const $trigger = $(this);
			const tab = $trigger.data('tab') || 'login';
			const $modal = $('#bd-auth-modal');

			// Set active tab
			$modal.find('.bd-auth-tab').removeClass('active');
			$modal.find('.bd-auth-tab[data-tab="' + tab + '"]').addClass('active');

			$modal.find('.bd-auth-panel').removeClass('active');
			$modal.find('.bd-auth-panel[data-panel="' + tab + '"]').addClass('active');

			// Set redirect URL
			const redirectTo = $trigger.data('redirect') || window.location.href;
			$modal.find('input[name="redirect_to"]').val(redirectTo);

			// Show modal
			$modal.fadeIn(200);
			$('body').addClass('bd-modal-open');

			// Focus first input
			setTimeout(function() {
				$modal.find('.bd-auth-panel.active input:first').focus();
			}, 200);
		},

		/**
		 * Close modal
		 */
		closeModal: function(e) {
			if (e) {
				e.preventDefault();
			}

			const $modal = $('#bd-auth-modal');
			$modal.fadeOut(200);
			$('body').removeClass('bd-modal-open');

			// Clear messages and reset forms
			$modal.find('.bd-auth-messages').empty();
			$modal.find('form')[0]?.reset();
		},

		/**
		 * Show message
		 */
		showMessage: function(type, message, $container) {
			if (!$container || !$container.length) {
				$container = $('.bd-auth-messages:visible').first();
			}

			if (!$container.length) {
				$container = $('.bd-auth-messages').first();
			}

			const icon = type === 'success'
				? '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>'
				: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>';

			const html = '<div class="bd-auth-message bd-auth-message-' + type + '">' +
				icon + '<span>' + message + '</span></div>';

			$container.html(html).show();

			// Scroll to message
			if ($container.length && $container.offset()) {
				$('html, body').animate({
					scrollTop: $container.offset().top - 100
				}, 300);
			}
		}
	};

	// Initialize on DOM ready
	$(function() {
		BD_Auth.init();
	});

	// Expose globally
	window.BD_Auth = BD_Auth;

})(jQuery);
