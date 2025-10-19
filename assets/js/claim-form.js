/**
 * Claim Form Modal JavaScript
 * Handles form submission and file uploads
 */
(function ($) {
	'use strict';

	let selectedFiles = [];

	$( document ).ready(
		function () {

			// Open modal when claim button is clicked
			$( document ).on(
				'click',
				'.bd-claim-btn, .bd-btn[href*="Claim"]',
				function (e) {
					e.preventDefault();
					openModal();
				}
			);

			// Close modal
			$( '.bd-claim-close, .bd-claim-modal-overlay' ).on(
				'click',
				function () {
					closeModal();
				}
			);

			// Close on ESC key
			$( document ).on(
				'keydown',
				function (e) {
					if (e.key === 'Escape' && $( '#bd-claim-modal' ).hasClass( 'active' )) {
						closeModal();
					}
				}
			);

			// Handle file selection
			$( '#claim-proof-files' ).on(
				'change',
				function (e) {
					handleFileSelection( e.target.files );
				}
			);

			// Form submission
			$( '#bd-claim-form' ).on(
				'submit',
				function (e) {
					e.preventDefault();
					submitClaim();
				}
			);

		}
	);

	/**
	 * Open modal
	 */
	function openModal() {
		$( '#bd-claim-modal' ).fadeIn(
			200,
			function () {
				$( this ).addClass( 'active' );
			}
		);
		$( 'body' ).css( 'overflow', 'hidden' );
	}

	/**
	 * Close modal
	 */
	function closeModal() {
		$( '#bd-claim-modal' ).removeClass( 'active' );
		setTimeout(
			function () {
				$( '#bd-claim-modal' ).fadeOut( 200 );
				$( 'body' ).css( 'overflow', '' );
			},
			300
		);
	}

	/**
	 * Handle file selection
	 */
	function handleFileSelection(files) {
		const maxFiles = 5;
		const maxSize  = 5 * 1024 * 1024; // 5MB

		// Convert FileList to array and add to selected files
		Array.from( files ).forEach(
			function (file) {
				// Check file count
				if (selectedFiles.length >= maxFiles) {
					showMessage( 'error', 'Maximum 5 files allowed' );
					return;
				}

				// Check file size
				if (file.size > maxSize) {
					showMessage( 'error', `${file.name} is too large. Maximum 5MB per file.` );
					return;
				}

				// Check file type
				const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf',
									'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
				if ( ! allowedTypes.includes( file.type )) {
					showMessage( 'error', `${file.name} is not a supported file type.` );
					return;
				}

				selectedFiles.push( file );
			}
		);

		renderFileList();
	}

	/**
	 * Render file list
	 */
	function renderFileList() {
		const $fileList = $( '#bd-file-list' );
		$fileList.empty();

		if (selectedFiles.length === 0) {
			return;
		}

		selectedFiles.forEach(
			function (file, index) {
				const $fileItem = $(
					`
					< div class = "bd-file-item" >
					< span class     = "bd-file-name" >
						< svg width  = "16" height = "16" viewBox = "0 0 16 16" fill = "currentColor" >
							< path d = "M9 0H3a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V5l-4-5zM8 5V1l4 4H8z" / >
						< / svg >
						${file.name}
					< / span >
					< button type    = "button" class = "bd-file-remove" data - index = "${index}" >
						< svg width  = "16" height = "16" viewBox = "0 0 16 16" fill = "currentColor" >
							< path d = "M4 4l8 8M12 4l-8 8" / >
						< / svg >
					< / button >
					< / div >
					`
				);

				$fileList.append( $fileItem );
			}
		);

		// Handle file removal
		$( '.bd-file-remove' ).on(
			'click',
			function () {
				const index = $( this ).data( 'index' );
				selectedFiles.splice( index, 1 );
				renderFileList();
			}
		);
	}

	/**
	 * Submit claim
	 */
	function submitClaim() {
		const $form      = $( '#bd-claim-form' );
		const $submitBtn = $form.find( 'button[type="submit"]' );

		// Validate form
		if ( ! $form[0].checkValidity()) {
			$form[0].reportValidity();
			return;
		}

		// Check if relationship is selected
		if ( ! $( 'input[name="relationship"]:checked' ).length) {
			showMessage( 'error', 'Please select your relationship to the business.' );
			return;
		}

		// Show loading
		$form.addClass( 'loading' );
		$submitBtn.prop( 'disabled', true );
		showMessage( '', '' ); // Clear previous messages

		// Prepare form data
		const formData = new FormData();

		// Add form fields
		formData.append( 'business_id', bdClaimForm.businessId );
		formData.append( 'claimant_name', $( '#claim-name' ).val() );
		formData.append( 'claimant_email', $( '#claim-email' ).val() );
		formData.append( 'claimant_phone', $( '#claim-phone' ).val() );
		formData.append( 'relationship', $( 'input[name="relationship"]:checked' ).val() );
		formData.append( 'message', $( 'textarea[name="message"]' ).val() );

		// Add Turnstile token if available
		if (bdClaimForm.turnstileSiteKey && typeof turnstile !== 'undefined') {
			const token = turnstile.getResponse();
			if (token) {
				formData.append( 'turnstile_token', token );
			}
		}

		// Add files
		selectedFiles.forEach(
			function (file) {
				formData.append( 'proof_files[]', file );
			}
		);

		// Submit via REST API
		$.ajax(
			{
				url: bdClaimForm.restUrl + 'claim',
				method: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				beforeSend: function (xhr) {
					xhr.setRequestHeader( 'X-WP-Nonce', bdClaimForm.nonce );
				},
				success: function (response) {
					if (response.success) {
						showMessage( 'success', response.message || 'Thank you! Your claim has been submitted and is being reviewed.' );

						// Clear form
						$form[0].reset();
						selectedFiles = [];
						renderFileList();

						// Close modal after 3 seconds
						setTimeout(
							function () {
								closeModal();

								// Update the claim button to show pending status
								$( '.bd-claim-btn, .bd-btn[href*="Claim"]' ).each(
									function () {
										$( this ).replaceWith(
											'<button class="bd-btn bd-btn-secondary" disabled>' +
											'<svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">' +
											'<path d="M10 0C4.5 0 0 4.5 0 10s4.5 10 10 10 10-4.5 10-10S15.5 0 10 0zm-1 15l-5-5 1.4-1.4L9 12.2l5.6-5.6L16 8l-7 7z"/>' +
											'</svg>' +
											'Claim Pending Review' +
											'</button>'
										);
									}
								);
							},
							3000
						);
					} else {
						showMessage( 'error', response.message || 'An error occurred. Please try again.' );
					}
				},
				error: function (xhr) {
					let errorMessage = 'An error occurred. Please try again.';

					if (xhr.responseJSON && xhr.responseJSON.message) {
						errorMessage = xhr.responseJSON.message;
					} else if (xhr.responseText) {
						try {
							const response = JSON.parse( xhr.responseText );
							errorMessage   = response.message || errorMessage;
						} catch (e) {
							// Keep default error message
						}
					}

					showMessage( 'error', errorMessage );
				},
				complete: function () {
					$form.removeClass( 'loading' );
					$submitBtn.prop( 'disabled', false );

					// Reset Turnstile
					if (bdClaimForm.turnstileSiteKey && typeof turnstile !== 'undefined') {
						turnstile.reset();
					}
				}
			}
		);
	}

	/**
	 * Show message
	 */
	function showMessage(type, message) {
		const $messageDiv = $( '#bd-claim-message' );

		if ( ! message) {
			$messageDiv.hide().removeClass( 'success error' ).html( '' );
			return;
		}

		$messageDiv
			.removeClass( 'success error' )
			.addClass( type )
			.html( message )
			.show();

		// Scroll to message
		if (type === 'error') {
			$messageDiv[0].scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
		}
	}

})( jQuery );