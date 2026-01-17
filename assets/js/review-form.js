(function ($) {
	'use strict';

	// ========================================================================
	// UTILITY FUNCTIONS
	// ========================================================================

	/**
	 * Escape HTML to prevent XSS.
	 *
	 * @param {string} text - Text to escape.
	 * @return {string} Escaped text.
	 */
	function escapeHtml(text) {
		if (!text) {
			return '';
		}
		const div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	}

	/**
	 * Show a message in the form message area.
	 *
	 * @param {jQuery}  $container - Message container element.
	 * @param {string}  message    - Message text.
	 * @param {string}  type       - 'success' or 'error'.
	 */
	function showMessage($container, message, type) {
		if (!$container || !$container.length) {
			return;
		}

		const $message = $('<div></div>')
			.addClass('bd-' + type)
			.text(message);
		$container.empty().append($message);

		// Scroll to message if visible.
		const offset = $container.offset();
		if (offset && offset.top) {
			$('html, body').animate({
				scrollTop: offset.top - 100
			}, 300);
		}
	}

	// ========================================================================
	// CONSTANTS (match server-side limits)
	// ========================================================================

	const LIMITS = {
		minContent: 10,
		maxContent: 5000,
		maxTitle: 200,
		maxName: 100,
		maxPhotos: 3,
		maxFileSize: 5 * 1024 * 1024 // 5MB
	};

	// ========================================================================
	// SUBMIT REVIEW FORM
	// ========================================================================

	$('#bd-submit-review-form').on('submit', function (e) {
		e.preventDefault();

		const form = $(this);
		const button = form.find('button[type="submit"]');
		const $message = $('#bd-review-message');
		const originalButtonText = button.text();

		// Clear previous messages and error states.
		$message.empty();
		form.find('.bd-field-error').removeClass('bd-field-error');
		form.find('.bd-inline-error').remove();

		// Client-side validation.
		const validationError = validateForm(form);
		if (validationError) {
			showMessage($message, validationError.message, 'error');
			highlightFieldError(validationError.field, validationError.message);
			return;
		}

		// Disable button and show loading state.
		button.prop('disabled', true).text('Submitting...');

		const formData = new FormData(this);

		// Add Turnstile token if enabled.
		if (window.turnstile && bdReview.turnstileSiteKey) {
			const token = turnstile.getResponse();
			if (!token) {
				showMessage($message, 'Please complete the CAPTCHA verification.', 'error');
				button.prop('disabled', false).text(originalButtonText);
				return;
			}
			formData.append('turnstile_token', token);
		}

		$.ajax({
			url: bdReview.restUrl + 'submit-review',
			method: 'POST',
			data: formData,
			processData: false,
			contentType: false,
			beforeSend: function (xhr) {
				xhr.setRequestHeader('X-WP-Nonce', bdReview.nonce);
			},
			success: function (response) {
				// Use safe text insertion to prevent XSS.
				showMessage($message, response.message || 'Thank you! Your review has been submitted.', 'success');

				// Reset form.
				form[0].reset();

				// Remove photo previews.
				form.find('.bd-photo-preview').remove();

				// Reset Turnstile.
				if (window.turnstile) {
					turnstile.reset();
				}

				// Reset star rating visual state.
				form.find('.bd-star-rating label').removeClass('selected');

				// If user changed nickname, update the display.
				const newNickname = formData.get('bd_display_name');
				if (newNickname && bdReview.isLoggedIn) {
					updateNicknameDisplay(newNickname);
					// Hide the editor.
					$('#bd-nickname-editor').slideUp(200);
					$('#bd-change-nickname-btn').show();
				}
			},
			error: function (xhr) {
				// Safely extract and display error message.
				let errorMessage = 'Submission failed. Please try again.';

				if (xhr.responseJSON && xhr.responseJSON.message) {
					errorMessage = xhr.responseJSON.message;
				} else if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
					errorMessage = xhr.responseJSON.data.message;
				}

				showMessage($message, errorMessage, 'error');

				// Reset Turnstile on error.
				if (window.turnstile) {
					turnstile.reset();
				}
			},
			complete: function () {
				button.prop('disabled', false).text(originalButtonText);
			}
		});
	});

	/**
	 * Validate form before submission.
	 *
	 * @param {jQuery} form - Form element.
	 * @return {object|null} Object with {message, field} or null if valid.
	 */
	function validateForm(form) {
		// Clear previous error highlights
		form.find('.bd-field-error').removeClass('bd-field-error');
		form.find('.bd-inline-error').remove();

		// Rating is required.
		if (!form.find('input[name="rating"]:checked').length) {
			return {
				message: 'Please select a rating.',
				field: form.find('.bd-star-rating')
			};
		}

		// For anonymous users, validate name and email.
		if (!bdReview.isLoggedIn) {
			const $nameField = form.find('#author_name');
			const $emailField = form.find('#author_email');
			const name = ($nameField.val() || '').trim();
			const email = ($emailField.val() || '').trim();

			if (!name) {
				return {
					message: 'Please enter your name.',
					field: $nameField
				};
			}
			if (name.length > LIMITS.maxName) {
				return {
					message: 'Name must be less than ' + LIMITS.maxName + ' characters.',
					field: $nameField
				};
			}
			if (!email) {
				return {
					message: 'Please enter your email.',
					field: $emailField
				};
			}
			if (!isValidEmail(email)) {
				return {
					message: 'Please enter a valid email address.',
					field: $emailField
				};
			}
		}

		// Content validation.
		const $contentField = form.find('#content');
		const content = ($contentField.val() || '').trim();
		if (!content) {
			return {
				message: 'Please write your review.',
				field: $contentField
			};
		}
		if (content.length < LIMITS.minContent) {
			return {
				message: 'Review must be at least ' + LIMITS.minContent + ' characters.',
				field: $contentField
			};
		}
		if (content.length > LIMITS.maxContent) {
			return {
				message: 'Review must be less than ' + LIMITS.maxContent + ' characters.',
				field: $contentField
			};
		}

		// Title validation (optional but has max length).
		const $titleField = form.find('#title');
		const title = ($titleField.val() || '').trim();
		if (title.length > LIMITS.maxTitle) {
			return {
				message: 'Title must be less than ' + LIMITS.maxTitle + ' characters.',
				field: $titleField
			};
		}

		// Photo validation.
		const photos = form.find('#photos')[0];
		if (photos && photos.files && photos.files.length > 0) {
			if (photos.files.length > LIMITS.maxPhotos) {
				return {
					message: 'Maximum ' + LIMITS.maxPhotos + ' photos allowed.',
					field: form.find('#photos')
				};
			}

			for (let i = 0; i < photos.files.length; i++) {
				const file = photos.files[i];

				// Check file size.
				if (file.size > LIMITS.maxFileSize) {
					return {
						message: 'File "' + escapeHtml(file.name) + '" exceeds 5MB limit.',
						field: form.find('#photos')
					};
				}

				// Check file type (with null safety).
				const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
				if (!file.type || !allowedTypes.includes(file.type)) {
					return {
						message: 'File "' + escapeHtml(file.name) + '" is not a valid image type.',
						field: form.find('#photos')
					};
				}
			}
		}

		// Nickname validation for logged-in users.
		if (bdReview.isLoggedIn) {
			const $nicknameField = form.find('#bd_display_name');
			if ($nicknameField.length && $nicknameField.is(':visible')) {
				const nickname = ($nicknameField.val() || '').trim();
				if (nickname && nickname.length > LIMITS.maxName) {
					return {
						message: 'Display name must be less than ' + LIMITS.maxName + ' characters.',
						field: $nicknameField
					};
				}
			}
		}

		return null;
	}

	/**
	 * Highlight a field with error state and shake animation.
	 *
	 * @param {jQuery} $field - Field to highlight.
	 * @param {string} message - Error message to show.
	 */
	function highlightFieldError($field, message) {
		if (!$field || !$field.length) {
			return;
		}

		// Add error class
		$field.addClass('bd-field-error');

		// Add shake animation
		$field.addClass('bd-shake');
		setTimeout(function() {
			$field.removeClass('bd-shake');
		}, 500);

		// Add inline error message below field
		const $row = $field.closest('.bd-form-row');
		if ($row.length && !$row.find('.bd-inline-error').length) {
			$row.append('<p class="bd-inline-error">' + escapeHtml(message) + '</p>');
		}

		// Scroll to field
		const offset = $field.offset();
		if (offset && offset.top) {
			$('html, body').animate({
				scrollTop: offset.top - 120
			}, 300);
		}

		// Focus field if it's an input/textarea
		if ($field.is('input, textarea')) {
			$field.focus();
		}
	}

	/**
	 * Simple email validation.
	 *
	 * @param {string} email - Email to validate.
	 * @return {boolean} True if valid.
	 */
	function isValidEmail(email) {
		const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
		return re.test(email);
	}

	// ========================================================================
	// STAR RATING INTERACTION
	// ========================================================================

	$('.bd-star-rating input').on('change', function () {
		const $container = $('.bd-star-rating');
		const val = parseInt($(this).val(), 10);

		$container.find('label').removeClass('selected');

		// Only apply if valid rating 1-5.
		if (val >= 1 && val <= 5) {
			$(this).parent().find('label').slice(val - 5).addClass('selected');
		}

		// Clear any error state when user selects a rating
		$container.removeClass('bd-field-error bd-shake');
		$container.closest('.bd-form-row').find('.bd-inline-error').remove();
	});

	// ========================================================================
	// CLEAR ERRORS ON INPUT
	// ========================================================================

	$('#bd-submit-review-form').on('input', 'input, textarea', function () {
		const $field = $(this);
		$field.removeClass('bd-field-error bd-shake');
		$field.closest('.bd-form-row').find('.bd-inline-error').remove();
	});

	// ========================================================================
	// NICKNAME TOGGLE (for logged-in users)
	// ========================================================================

	$(document).on('click', '#bd-change-nickname-btn', function (e) {
		e.preventDefault();

		const $editor = $('#bd-nickname-editor');
		const $btn = $(this);

		if ($editor.is(':visible')) {
			// Hide editor.
			$editor.slideUp(200);
			$btn.text('Change');
		} else {
			// Show editor.
			$editor.slideDown(200, function () {
				$editor.find('input').focus();
			});
			$btn.text('Cancel');
		}
	});

	/**
	 * Update nickname display after successful submission.
	 *
	 * @param {string} newNickname - New display name.
	 */
	function updateNicknameDisplay(newNickname) {
		const $displayName = $('.bd-user-display-name');
		if ($displayName.length) {
			$displayName.text(newNickname);
		}

		// Also update the input field for future edits.
		const $nicknameInput = $('#bd_display_name');
		if ($nicknameInput.length) {
			$nicknameInput.val(newNickname);
		}
	}

	// ========================================================================
	// HELPFUL VOTE HANDLER
	// ========================================================================

	$(document).on('click', '.bd-helpful-btn', function (e) {
		e.preventDefault();

		const $btn = $(this);
		const reviewId = $btn.data('review-id');
		const reviewAuthorId = $btn.data('review-author-id');

		// Don't allow if already voted.
		if ($btn.hasClass('bd-helpful-voted') || $btn.prop('disabled')) {
			return;
		}

		// Disable button immediately.
		$btn.prop('disabled', true);

		$.ajax({
			url: bdReview.ajaxUrl,
			method: 'POST',
			data: {
				action: 'bd_mark_helpful',
				review_id: reviewId,
				review_author_id: reviewAuthorId,
				nonce: bdReview.helpfulNonce
			},
			success: function (response) {
				if (response.success) {
					// Update count with server-returned value (not local increment).
					const $count = $btn.find('.bd-helpful-count');
					$count.text(response.data.count);

					// Mark as voted.
					$btn.addClass('bd-helpful-voted');
					$btn.find('.bd-helpful-text').text('Helped!');

					// Add animation.
					$btn.addClass('bd-helpful-animate');
					setTimeout(function () {
						$btn.removeClass('bd-helpful-animate');
					}, 600);

				} else {
					// Re-enable if there was an error.
					$btn.prop('disabled', false);

					// Safe alert - use text content.
					const errorMsg = (response.data && response.data.message) ?
						response.data.message :
						'Could not mark as helpful. Please try again.';
					alert(errorMsg);
				}
			},
			error: function () {
				$btn.prop('disabled', false);
				alert('An error occurred. Please try again.');
			}
		});
	});

	// ========================================================================
	// PHOTO PREVIEW (optional enhancement)
	// ========================================================================

	$('#photos').on('change', function () {
		const $input = $(this);
		const files = this.files;
		const $preview = $input.siblings('.bd-photo-preview');

		// Remove existing preview.
		$preview.remove();

		if (!files || files.length === 0) {
			return;
		}

		// Check file count.
		if (files.length > LIMITS.maxPhotos) {
			alert('Maximum ' + LIMITS.maxPhotos + ' photos allowed. Only the first ' + LIMITS.maxPhotos + ' will be uploaded.');
		}

		// Create preview container.
		const $newPreview = $('<div class="bd-photo-preview"></div>');

		// Show previews (up to max).
		const previewCount = Math.min(files.length, LIMITS.maxPhotos);
		for (let i = 0; i < previewCount; i++) {
			const file = files[i];

			// Validate file type (with null safety).
			if (!file.type || !file.type.startsWith('image/')) {
				continue;
			}

			// Create thumbnail.
			const reader = new FileReader();
			reader.onload = function (e) {
				const $img = $('<img>')
					.attr('src', e.target.result)
					.attr('alt', 'Preview')
					.addClass('bd-photo-thumbnail');
				$newPreview.append($img);
			};
			reader.readAsDataURL(file);
		}

		$input.after($newPreview);
	});

	// ========================================================================
	// REVIEW PHOTO LIGHTBOX (click to expand)
	// ========================================================================

	$(document).on('click', '.bd-review-photos img', function (e) {
		e.preventDefault();

		const $img = $(this);
		const fullSrc = $img.attr('src').replace('-150x150', '').replace('-300x300', '');
		const $lightbox = $('#bd-lightbox');

		if ($lightbox.length) {
			// Use existing page lightbox
			$('#bd-lightbox-image').attr('src', fullSrc);
			$('#bd-lightbox-counter').text('');
			$lightbox.fadeIn(200);

			// Hide nav buttons for single image
			$lightbox.find('.bd-lightbox-prev, .bd-lightbox-next').hide();
		} else {
			// Fallback: open in new tab
			window.open(fullSrc, '_blank');
		}
	});

})(jQuery);
