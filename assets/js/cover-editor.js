/**
 * List Cover Editor
 *
 * Handles cover image upload, editing, and video cover integration.
 * Uses Cropper.js for image manipulation with client-side preprocessing.
 *
 * @package BusinessDirectory
 * @since 1.2.0
 */

(function ($) {
	'use strict';

	// Check if bdLists is available
	if (typeof bdLists === 'undefined') {
		console.warn('bdLists not defined, cover editor disabled');
		return;
	}

	/**
	 * Default i18n strings (can be overridden via bdLists.i18n)
	 */
	const defaultStrings = {
		processing: 'Processing...',
		uploading: 'Uploading cover...',
		settingVideo: 'Setting video cover...',
		removing: 'Removing cover...',
		coverUpdated: 'Cover updated!',
		videoCoverSet: 'Video cover set!',
		coverRemoved: 'Cover removed',
		invalidType: 'Please select a JPEG, PNG, or WebP image',
		fileTooLarge: 'Image must be under 10MB',
		processingFailed: 'Failed to process image. Please try another file.',
		uploadFailed: 'Failed to upload cover',
		videoFailed: 'Failed to set video cover. Check that the URL is correct.',
		removeFailed: 'Failed to remove cover',
		invalidVideoUrl: 'Please enter a valid YouTube or Vimeo URL',
		enterVideoUrl: 'Please enter a video URL',
		removeConfirm: 'Remove the cover image? The list will use the first business image instead.',
		cropperLoadFailed: 'Image editor failed to load. Please refresh the page.',
		imageTooSmall: 'Image must be at least 800×450 pixels',
	};

	/**
	 * Get i18n string
	 * @param {string} key String key
	 * @returns {string} Localized string
	 */
	function i18n(key) {
		return (bdLists.i18n && bdLists.i18n[key]) || defaultStrings[key] || key;
	}

	/**
	 * Announce message to screen readers
	 * @param {string} message Message to announce
	 * @param {string} priority 'polite' or 'assertive'
	 */
	function announceToSR(message, priority = 'polite') {
		const $region = $('<div>')
			.attr({
				'role': 'status',
				'aria-live': priority,
				'aria-atomic': 'true',
				'class': 'bd-sr-only'
			})
			.text(message)
			.appendTo('body');

		setTimeout(() => $region.remove(), 3000);
	}

	/**
	 * Cover Editor Module
	 */
	const CoverEditor = {
		// State
		cropper: null,
		originalFile: null,
		originalImage: null,
		listId: null,
		isProcessing: false,
		initialCanvasData: null,
		videoPreviewTimeout: null,
		
		// Custom thumbnail state
		customThumbCropper: null,
		customThumbFile: null,
		useCustomThumb: false,

		// Settings
		settings: {
			aspectRatio: 16 / 9,
			minWidth: 800,
			minHeight: 450,
			maxFileSize: 10 * 1024 * 1024, // 10MB
			maxPreProcessSize: 2400, // Resize to this before editing
			outputQuality: 0.85,
			allowedTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'],
		},

		/**
		 * Initialize the cover editor
		 */
		init: function () {
			this.bindEvents();
			this.injectModal();
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function () {
			const self = this;

			// Open cover editor from edit modal
			$(document).on('click', '.bd-edit-cover-btn, .bd-open-cover-editor', function (e) {
				e.preventDefault();
				e.stopPropagation();

				const listId = $(this).data('list-id') ||
					$(this).closest('[data-list-id]').data('list-id') ||
					$('.bd-edit-list-form').data('list-id');

				// Close the edit list modal first to avoid z-index stacking issues
				const $editModal = $(this).closest('.bd-edit-list-modal');
				if ($editModal.length) {
					$editModal.fadeOut(150, function () {
						self.openEditor(listId);
					});
				} else {
					self.openEditor(listId);
				}
			});

			// File input change
			$(document).on('change', '#bd-cover-file-input', function (e) {
				self.handleFileSelect(e.target.files[0]);
			});

			// Drag and drop
			$(document).on('dragover', '.bd-cover-dropzone', function (e) {
				e.preventDefault();
				$(this).addClass('bd-cover-dragover');
			});

			$(document).on('dragleave drop', '.bd-cover-dropzone', function (e) {
				e.preventDefault();
				$(this).removeClass('bd-cover-dragover');
				if (e.type === 'drop') {
					const files = e.originalEvent.dataTransfer.files;
					if (files.length) {
						self.handleFileSelect(files[0]);
					}
				}
			});

			// Paste image
			$(document).on('paste', '.bd-cover-editor-modal', function (e) {
				const items = e.originalEvent.clipboardData?.items;
				if (!items) return;

				for (let item of items) {
					if (item.type.indexOf('image') !== -1) {
						const file = item.getAsFile();
						self.handleFileSelect(file);
						break;
					}
				}
			});

			// Editor controls
			$(document).on('click', '.bd-cover-rotate-left', () => self.rotate(-90));
			$(document).on('click', '.bd-cover-rotate-right', () => self.rotate(90));
			$(document).on('click', '.bd-cover-flip-h', () => self.flip('horizontal'));
			$(document).on('click', '.bd-cover-flip-v', () => self.flip('vertical'));
			$(document).on('click', '.bd-cover-reset', () => self.resetCrop());
			$(document).on('input', '.bd-cover-zoom-slider', function () {
				self.setZoom(parseFloat(this.value));
			});

			// Tab switching
			$(document).on('click', '.bd-cover-tab', function () {
				self.switchTab($(this).data('tab'));
			});

			// Video URL input (debounced)
			$(document).on('input', '.bd-cover-video-input', function () {
				const url = $(this).val();
				clearTimeout(self.videoPreviewTimeout);
				self.videoPreviewTimeout = setTimeout(() => self.previewVideo(url), 300);
			});

			// Save buttons
			$(document).on('click', '.bd-cover-save-image', () => self.saveImageCover());
			$(document).on('click', '.bd-cover-save-video', () => self.saveVideoCover());
			$(document).on('click', '.bd-cover-remove', () => self.removeCover());

			// Custom thumbnail for video covers
			$(document).on('click', '.bd-upload-custom-thumb', function () {
				$('#bd-custom-thumb-input').trigger('click');
			});

			$(document).on('change', '#bd-custom-thumb-input', function (e) {
				if (e.target.files[0]) {
					self.handleCustomThumbSelect(e.target.files[0]);
				}
			});

			$(document).on('click', '.bd-custom-thumb-rotate-left', () => self.rotateCustomThumb(-90));
			$(document).on('click', '.bd-custom-thumb-rotate-right', () => self.rotateCustomThumb(90));
			$(document).on('click', '.bd-custom-thumb-reset', () => self.resetCustomThumbCrop());
			$(document).on('click', '.bd-custom-thumb-cancel', () => self.cancelCustomThumb());

			// Close modal
			$(document).on('click', '.bd-cover-editor-modal .bd-modal-close, .bd-cover-editor-modal .bd-modal-overlay', function () {
				self.closeEditor();
			});

			// Keyboard shortcuts
			$(document).on('keydown', '.bd-cover-editor-modal', function (e) {
				if (e.key === 'Escape') {
					self.closeEditor();
				}
			});
		},

		/**
		 * Inject modal HTML into page
		 */
		injectModal: function () {
			if ($('.bd-cover-editor-modal').length) return;

			const html = `
				<div class="bd-modal bd-cover-editor-modal" style="display: none; z-index: 2147483648 !important;" role="dialog" aria-modal="true" aria-labelledby="bd-cover-modal-title">
					<div class="bd-modal-overlay" style="z-index: 1;"></div>
					<div class="bd-modal-content bd-cover-modal-content" style="z-index: 2;">
						<div class="bd-modal-header">
							<h3 id="bd-cover-modal-title"><i class="fas fa-image"></i> Edit Cover</h3>
							<button type="button" class="bd-modal-close" aria-label="Close">&times;</button>
						</div>
						
						<div class="bd-modal-body">
							<!-- Tabs -->
							<div class="bd-cover-tabs" role="tablist">
								<button class="bd-cover-tab active" data-tab="image" role="tab" aria-selected="true" aria-controls="bd-panel-image">
									<i class="fas fa-image"></i> Photo
								</button>
								<button class="bd-cover-tab" data-tab="video" role="tab" aria-selected="false" aria-controls="bd-panel-video">
									<i class="fas fa-video"></i> Video
								</button>
							</div>
							
							<!-- Image Tab -->
							<div id="bd-panel-image" class="bd-cover-panel bd-cover-panel-image active" role="tabpanel" aria-labelledby="bd-tab-image">
								<!-- Upload Area -->
								<div class="bd-cover-upload-area">
									<div class="bd-cover-dropzone" tabindex="0" role="button" aria-label="Click or drag to upload image">
										<input type="file" id="bd-cover-file-input" accept="image/*" class="bd-sr-only">
										<i class="fas fa-cloud-upload-alt"></i>
										<p>Drag & drop an image, or <label for="bd-cover-file-input">browse</label></p>
										<span class="bd-cover-hint">JPEG, PNG, WebP • Max 10MB • Min 800×450px</span>
									</div>
								</div>
								
								<!-- Editor Area (hidden until image loaded) -->
								<div class="bd-cover-editor-area" style="display: none;">
									<div class="bd-cover-preview-container">
										<img id="bd-cover-preview" src="" alt="Cover preview">
									</div>
									
									<!-- Controls -->
									<div class="bd-cover-controls">
										<div class="bd-cover-control-group">
											<label for="bd-zoom-slider">Zoom</label>
											<input type="range" id="bd-zoom-slider" class="bd-cover-zoom-slider" min="1" max="3" step="0.1" value="1">
										</div>
										
										<div class="bd-cover-control-buttons">
											<button type="button" class="bd-cover-rotate-left" title="Rotate left" aria-label="Rotate left">
												<i class="fas fa-undo"></i>
											</button>
											<button type="button" class="bd-cover-rotate-right" title="Rotate right" aria-label="Rotate right">
												<i class="fas fa-redo"></i>
											</button>
											<button type="button" class="bd-cover-flip-h" title="Flip horizontal" aria-label="Flip horizontal">
												<i class="fas fa-arrows-alt-h"></i>
											</button>
											<button type="button" class="bd-cover-flip-v" title="Flip vertical" aria-label="Flip vertical">
												<i class="fas fa-arrows-alt-v"></i>
											</button>
											<button type="button" class="bd-cover-reset" title="Reset" aria-label="Reset to original">
												<i class="fas fa-sync"></i>
											</button>
										</div>
									</div>
								</div>
							</div>
							
							<!-- Video Tab -->
							<div id="bd-panel-video" class="bd-cover-panel bd-cover-panel-video" role="tabpanel" aria-labelledby="bd-tab-video">
								<div class="bd-cover-video-input-area">
									<label for="bd-cover-video-url">YouTube or Vimeo URL</label>
									<input type="url" id="bd-cover-video-url" class="bd-cover-video-input" 
										   placeholder="https://youtube.com/watch?v=... or https://vimeo.com/...">
									<span class="bd-cover-hint">Paste a video link to use its thumbnail as the cover</span>
								</div>
								
								<div class="bd-cover-video-preview" style="display: none;">
									<img src="" alt="Video thumbnail" class="bd-cover-video-thumb">
									<div class="bd-cover-video-info">
										<span class="bd-cover-video-platform"></span>
									</div>
								</div>
								
								<!-- Custom thumbnail option - OUTSIDE the preview container -->
								<div class="bd-cover-custom-thumb-option" style="display: none;">
									<p class="bd-cover-hint">Not happy with the auto-generated thumbnail?</p>
									<button type="button" class="bd-btn bd-btn-secondary bd-btn-sm bd-upload-custom-thumb">
										<i class="fas fa-upload"></i> Upload Custom Thumbnail
									</button>
									<input type="file" id="bd-custom-thumb-input" accept="image/jpeg,image/png,image/webp" style="display: none;">
								</div>
								
								<!-- Custom thumbnail editor (appears when uploading custom thumb) -->
								<div class="bd-cover-custom-thumb-editor" style="display: none;">
									<label>Custom Thumbnail</label>
									<div class="bd-cover-cropper-container bd-cover-custom-thumb-cropper">
										<img id="bd-custom-thumb-image" src="" alt="Custom thumbnail">
									</div>
									<div class="bd-cover-toolbar">
										<div class="bd-cover-toolbar-group">
											<button type="button" class="bd-custom-thumb-rotate-left" title="Rotate left">
												<i class="fas fa-undo"></i>
											</button>
											<button type="button" class="bd-custom-thumb-rotate-right" title="Rotate right">
												<i class="fas fa-redo"></i>
											</button>
										</div>
										<div class="bd-cover-toolbar-group">
											<button type="button" class="bd-custom-thumb-reset" title="Reset">
												<i class="fas fa-sync"></i>
											</button>
											<button type="button" class="bd-custom-thumb-cancel" title="Cancel custom thumbnail">
												<i class="fas fa-times"></i> Use Default
											</button>
										</div>
									</div>
								</div>
							</div>
							
							<!-- Current Cover Preview -->
							<div class="bd-cover-current" style="display: none;">
								<label>Current Cover</label>
								<div class="bd-cover-current-preview">
									<img src="" alt="Current cover">
									<button type="button" class="bd-cover-remove" title="Remove cover" aria-label="Remove cover">
										<i class="fas fa-trash"></i>
									</button>
								</div>
							</div>
						</div>
						
						<div class="bd-modal-footer bd-form-actions">
							<button type="button" class="bd-btn bd-btn-secondary bd-modal-close">Cancel</button>
							<button type="button" class="bd-btn bd-btn-primary bd-cover-save-image" style="display: none;">
								<i class="fas fa-check"></i> Save Cover
							</button>
							<button type="button" class="bd-btn bd-btn-primary bd-cover-save-video" style="display: none;">
								<i class="fas fa-check"></i> Use Video Cover
							</button>
						</div>
						
						<!-- Processing Overlay -->
						<div class="bd-cover-processing" style="display: none;" role="alert" aria-live="assertive">
							<div class="bd-cover-processing-content">
								<i class="fas fa-spinner fa-spin"></i>
								<p class="bd-cover-processing-text">${i18n('processing')}</p>
							</div>
						</div>
					</div>
				</div>
			`;

			$('body').append(html);
		},

		/**
		 * Open the editor
		 * @param {number} listId List ID to edit
		 */
		openEditor: function (listId) {
			if (!listId) {
				console.error('CoverEditor: No list ID provided');
				return;
			}

			this.listId = listId;
			this.reset();

			const $modal = $('.bd-cover-editor-modal');
			$modal.fadeIn(200);
			$('body').addClass('bd-modal-open');

			// Load current cover if exists
			this.loadCurrentCover();

			// Focus close button for accessibility
			$modal.find('.bd-modal-close').focus();

			// Announce to screen readers
			announceToSR('Cover editor opened');
		},

		/**
		 * Close the editor
		 * @param {boolean} reopenEditModal - Whether to reopen the edit list modal
		 */
		closeEditor: function (reopenEditModal = true) {
			this.destroyCropper();
			this.reset();

			$('.bd-cover-editor-modal').fadeOut(200, function () {
				// Reopen the edit list modal if it was hidden
				if (reopenEditModal) {
					$('.bd-edit-list-modal').fadeIn(200);
				}
			});
			$('body').removeClass('bd-modal-open');
		},

		/**
		 * Reset editor state
		 */
		reset: function () {
			this.destroyCropper();
			this.originalFile = null;
			this.originalImage = null;
			this.initialCanvasData = null;

			// Reset custom thumbnail state
			if (this.customThumbCropper) {
				this.customThumbCropper.destroy();
				this.customThumbCropper = null;
			}
			this.customThumbFile = null;
			this.useCustomThumb = false;
			$('.bd-cover-custom-thumb-editor').hide();
			$('.bd-cover-custom-thumb-option').hide();
			$('#bd-custom-thumb-input').val('');

			$('.bd-cover-upload-area').show();
			$('.bd-cover-editor-area').hide();
			$('.bd-cover-save-image').hide();
			$('.bd-cover-save-video').hide().html('<i class="fas fa-check"></i> Use Video Cover');
			$('.bd-cover-video-preview').hide();
			$('#bd-cover-file-input').val('');
			$('#bd-cover-video-url').val('');
			$('.bd-cover-zoom-slider').val(1);
		},

		/**
		 * Load current cover for display
		 */
		loadCurrentCover: function () {
			if (!this.listId) return;

			$.ajax({
				url: bdLists.restUrl + 'lists/' + this.listId + '/cover',
				method: 'GET',
				headers: { 'X-WP-Nonce': bdLists.nonce },
				success: (response) => {
					if (response.has_cover) {
						let imageUrl = null;

						if (response.type === 'image' && response.image) {
							imageUrl = response.image.medium;
						} else if (response.video) {
							imageUrl = response.video.thumbnail_url;
						}

						if (imageUrl) {
							$('.bd-cover-current').show();
							$('.bd-cover-current-preview img').attr('src', imageUrl);
						}
					}
				},
				error: (xhr) => {
					console.warn('Could not load current cover:', xhr.responseJSON?.message);
				}
			});
		},

		/**
		 * Switch between tabs
		 * @param {string} tab Tab name ('image' or 'video')
		 */
		switchTab: function (tab) {
			$('.bd-cover-tab').removeClass('active').attr('aria-selected', 'false');
			$('.bd-cover-tab[data-tab="' + tab + '"]').addClass('active').attr('aria-selected', 'true');

			$('.bd-cover-panel').removeClass('active');
			$('.bd-cover-panel-' + tab).addClass('active');

			// Toggle save buttons
			if (tab === 'video') {
				$('.bd-cover-save-image').hide();
				if ($('.bd-cover-video-preview').is(':visible')) {
					$('.bd-cover-save-video').show();
				}
			} else {
				$('.bd-cover-save-video').hide();
				if ($('.bd-cover-editor-area').is(':visible')) {
					$('.bd-cover-save-image').show();
				}
			}
		},

		/**
		 * Handle file selection
		 * @param {File} file Selected file
		 */
		handleFileSelect: async function (file) {
			if (!file) return;

			// Validate file type
			const isHeic = file.name.match(/\.(heic|heif)$/i);
			if (!this.settings.allowedTypes.includes(file.type) && !isHeic) {
				this.showError(i18n('invalidType'));
				return;
			}

			// Validate file size
			if (file.size > this.settings.maxFileSize) {
				this.showError(i18n('fileTooLarge'));
				return;
			}

			this.showProcessing(i18n('processing'));

			try {
				// Convert HEIC if needed
				let processedFile = file;
				if (file.type === 'image/heic' || file.type === 'image/heif' || isHeic) {
					processedFile = await this.convertHeic(file);
				}

				// Pre-process: resize large images
				const preProcessed = await this.preProcessImage(processedFile);

				// Validate minimum dimensions
				if (this.originalImage.width < this.settings.minWidth || 
					this.originalImage.height < this.settings.minHeight) {
					this.showError(i18n('imageTooSmall'));
					this.hideProcessing();
					return;
				}

				// Store original for re-cropping
				this.originalFile = processedFile;

				// Load into editor
				this.loadImageEditor(preProcessed);

			} catch (error) {
				console.error('Image processing error:', error);
				this.showError(i18n('processingFailed'));
			}

			this.hideProcessing();
		},

		/**
		 * Convert HEIC to JPEG
		 * @param {File} file HEIC file
		 * @returns {Promise<File>} Converted JPEG file
		 */
		convertHeic: async function (file) {
			if (typeof heic2any === 'undefined') {
				// Load heic2any dynamically
				await this.loadScript('https://cdnjs.cloudflare.com/ajax/libs/heic2any/0.0.4/heic2any.min.js');
			}

			const converted = await heic2any({
				blob: file,
				toType: 'image/jpeg',
				quality: 0.9
			});

			return new File([converted], file.name.replace(/\.(heic|heif)$/i, '.jpg'), {
				type: 'image/jpeg'
			});
		},

		/**
		 * Pre-process image (resize, strip EXIF)
		 * @param {File} file Image file
		 * @returns {Promise<Blob>} Processed image blob
		 */
		preProcessImage: function (file) {
			return new Promise((resolve, reject) => {
				const img = new Image();
				const canvas = document.createElement('canvas');
				const ctx = canvas.getContext('2d');

				img.onload = () => {
					let { width, height } = img;
					const maxSize = this.settings.maxPreProcessSize;

					// Store original dimensions
					this.originalImage = {
						width: img.naturalWidth,
						height: img.naturalHeight
					};

					// Only resize if larger than max
					if (width > maxSize || height > maxSize) {
						if (width > height) {
							height = Math.round(height * maxSize / width);
							width = maxSize;
						} else {
							width = Math.round(width * maxSize / height);
							height = maxSize;
						}
					}

					canvas.width = width;
					canvas.height = height;

					// Draw image (this strips EXIF data)
					ctx.drawImage(img, 0, 0, width, height);

					canvas.toBlob(
						(blob) => resolve(blob),
						'image/jpeg',
						this.settings.outputQuality
					);

					// Clean up object URL
					URL.revokeObjectURL(img.src);
				};

				img.onerror = () => {
					URL.revokeObjectURL(img.src);
					reject(new Error('Failed to load image'));
				};

				img.src = URL.createObjectURL(file);
			});
		},

		/**
		 * Load image into Cropper.js editor
		 * @param {Blob} blob Image blob
		 */
		loadImageEditor: function (blob) {
			// Check if Cropper.js is available
			if (typeof Cropper === 'undefined') {
				this.showError(i18n('cropperLoadFailed'));
				return;
			}

			const imageUrl = URL.createObjectURL(blob);
			const $preview = $('#bd-cover-preview');

			$preview.attr('src', imageUrl);

			$('.bd-cover-upload-area').hide();
			$('.bd-cover-editor-area').show();
			$('.bd-cover-save-image').show();

			// Wait for image to load then init cropper
			$preview.one('load', () => {
				this.initCropper($preview[0]);
			});
		},

		/**
		 * Initialize Cropper.js
		 * @param {HTMLImageElement} image Image element
		 */
		initCropper: function (image) {
			this.destroyCropper();
			const self = this;

			this.cropper = new Cropper(image, {
				aspectRatio: this.settings.aspectRatio,
				viewMode: 1,
				dragMode: 'move',
				autoCropArea: 1,
				restore: false,
				guides: true,
				center: true,
				highlight: false,
				cropBoxMovable: false,
				cropBoxResizable: false,
				toggleDragModeOnDblclick: false,
				minCropBoxWidth: this.settings.minWidth / 2,
				minCropBoxHeight: this.settings.minHeight / 2,
				ready: function () {
					// Enable zoom slider
					$('.bd-cover-zoom-slider').prop('disabled', false).val(1);
					// Store initial canvas data for zoom calculations
					self.initialCanvasData = this.cropper.getCanvasData();
				},
				zoom: function () {
					// Update slider when zooming via scroll/pinch
					if (self.initialCanvasData) {
						const currentData = self.cropper.getCanvasData();
						const ratio = currentData.width / self.initialCanvasData.width;
						// Clamp to slider range
						const clampedRatio = Math.max(1, Math.min(3, ratio));
						$('.bd-cover-zoom-slider').val(clampedRatio);
					}
				}
			});
		},

		/**
		 * Destroy Cropper instance
		 */
		destroyCropper: function () {
			if (this.cropper) {
				this.cropper.destroy();
				this.cropper = null;
			}
		},

		/**
		 * Rotate image
		 * @param {number} degrees Rotation degrees
		 */
		rotate: function (degrees) {
			if (this.cropper) {
				this.cropper.rotate(degrees);
			}
		},

		/**
		 * Flip image
		 * @param {string} direction 'horizontal' or 'vertical'
		 */
		flip: function (direction) {
			if (!this.cropper) return;

			const data = this.cropper.getData();
			if (direction === 'horizontal') {
				this.cropper.scaleX(data.scaleX === 1 ? -1 : 1);
			} else {
				this.cropper.scaleY(data.scaleY === 1 ? -1 : 1);
			}
		},

		/**
		 * Set zoom level
		 * @param {number} ratio Zoom ratio (1-3)
		 */
		setZoom: function (ratio) {
			if (!this.cropper || !this.initialCanvasData) return;

			// Calculate the target width based on initial width and ratio
			const targetWidth = this.initialCanvasData.width * ratio;
			const currentData = this.cropper.getCanvasData();

			// Calculate zoom ratio relative to current state
			const zoomRatio = targetWidth / currentData.width;

			// Use zoom method with ratio
			this.cropper.zoom(zoomRatio - 1);
		},

		/**
		 * Reset crop to original
		 */
		resetCrop: function () {
			if (this.cropper) {
				this.cropper.reset();
				$('.bd-cover-zoom-slider').val(1);
				// Update initial canvas data after reset
				this.initialCanvasData = this.cropper.getCanvasData();
			}
		},

		/**
		 * Preview video cover
		 * @param {string} url Video URL
		 */
		previewVideo: function (url) {
			if (!url || url.length < 10) {
				$('.bd-cover-video-preview').hide();
				$('.bd-cover-custom-thumb-option').hide();
				$('.bd-cover-save-video').hide();
				return;
			}

			// Parse video URL via API
			$.ajax({
				url: bdLists.restUrl + 'cover/parse-video',
				method: 'POST',
				headers: { 'X-WP-Nonce': bdLists.nonce },
				data: { url: url },
				success: (response) => {
					$('.bd-cover-video-thumb').attr('src', response.thumbnail_url);
					$('.bd-cover-video-platform').text(response.platform === 'youtube' ? 'YouTube' : 'Vimeo');
					$('.bd-cover-video-preview').show();
					$('.bd-cover-custom-thumb-option').show();
					$('.bd-cover-save-video').show();
				},
				error: (xhr) => {
					$('.bd-cover-video-preview').hide();
					$('.bd-cover-custom-thumb-option').hide();
					$('.bd-cover-save-video').hide();
					if (url.length > 15) {
						this.showError(xhr.responseJSON?.message || i18n('invalidVideoUrl'));
					}
				}
			});
		},

		/**
		 * Save image cover
		 */
		saveImageCover: async function () {
			if (!this.cropper || this.isProcessing) return;

			this.isProcessing = true;
			this.showProcessing(i18n('uploading'));

			try {
				// Get cropped canvas
				const canvas = this.cropper.getCroppedCanvas({
					width: 1200,
					height: 675,
					imageSmoothingEnabled: true,
					imageSmoothingQuality: 'high'
				});

				if (!canvas) {
					throw new Error('Failed to generate cropped image');
				}

				// Convert to blob
				const croppedBlob = await new Promise((resolve) => {
					canvas.toBlob(resolve, 'image/jpeg', this.settings.outputQuality);
				});

				// Get crop data
				const cropData = this.cropper.getData();

				// Prepare form data
				const formData = new FormData();
				formData.append('cropped', croppedBlob, 'cover.jpg');

				// Include original for re-cropping (if not too large)
				if (this.originalFile && this.originalFile.size < 5 * 1024 * 1024) {
					formData.append('original', this.originalFile, 'original.jpg');
				}

				// Add crop metadata
				formData.append('crop_x', cropData.x / canvas.width);
				formData.append('crop_y', cropData.y / canvas.height);
				formData.append('crop_width', cropData.width / canvas.width);
				formData.append('crop_height', cropData.height / canvas.height);
				formData.append('crop_zoom', cropData.scaleX || 1);
				formData.append('crop_rotation', cropData.rotate || 0);
				formData.append('source_width', this.originalImage?.width || 0);
				formData.append('source_height', this.originalImage?.height || 0);

				// Upload
				await $.ajax({
					url: bdLists.restUrl + 'lists/' + this.listId + '/cover',
					method: 'POST',
					headers: { 'X-WP-Nonce': bdLists.nonce },
					data: formData,
					processData: false,
					contentType: false
				});

				this.showSuccess(i18n('coverUpdated'));
				announceToSR(i18n('coverUpdated'), 'assertive');
				this.closeEditor(false);

				// Refresh page to show new cover
				setTimeout(() => window.location.reload(), 500);

			} catch (error) {
				console.error('Upload error:', error);
				this.showError(error.responseJSON?.message || i18n('uploadFailed'));
			}

			this.isProcessing = false;
			this.hideProcessing();
		},

		/**
		 * Handle custom thumbnail file selection
		 * @param {File} file Image file
		 */
		handleCustomThumbSelect: function (file) {
			// Validate file type
			const validTypes = ['image/jpeg', 'image/png', 'image/webp'];
			if (!validTypes.includes(file.type)) {
				this.showError(i18n('invalidType'));
				return;
			}

			// Validate file size (10MB max)
			if (file.size > 10 * 1024 * 1024) {
				this.showError(i18n('fileTooLarge'));
				return;
			}

			// Store the file
			this.customThumbFile = file;

			// Show custom thumbnail editor
			$('.bd-cover-video-preview').hide();
			$('.bd-cover-custom-thumb-editor').show();

			// Load into cropper
			const reader = new FileReader();
			reader.onload = (e) => {
				const $img = $('#bd-custom-thumb-image');
				$img.attr('src', e.target.result);

				// Initialize cropper for custom thumbnail
				if (this.customThumbCropper) {
					this.customThumbCropper.destroy();
				}

				$img.on('load', () => {
					this.customThumbCropper = new Cropper($img[0], {
						aspectRatio: 16 / 9,
						viewMode: 1,
						guides: true,
						center: true,
						highlight: true,
						background: true,
						responsive: true,
						restore: false,
						checkCrossOrigin: false,
						checkOrientation: true,
						modal: true,
						autoCropArea: 1,
						dragMode: 'move',
						cropBoxMovable: false,
						cropBoxResizable: false
					});
				}).each(function () {
					if (this.complete) $(this).trigger('load');
				});
			};
			reader.readAsDataURL(file);

			// Update save button to indicate custom thumbnail will be used
			$('.bd-cover-save-video').html('<i class="fas fa-check"></i> Save with Custom Thumbnail');
			this.useCustomThumb = true;
		},

		/**
		 * Rotate custom thumbnail
		 * @param {number} degrees Rotation degrees
		 */
		rotateCustomThumb: function (degrees) {
			if (this.customThumbCropper) {
				this.customThumbCropper.rotate(degrees);
			}
		},

		/**
		 * Reset custom thumbnail crop
		 */
		resetCustomThumbCrop: function () {
			if (this.customThumbCropper) {
				this.customThumbCropper.reset();
			}
		},

		/**
		 * Cancel custom thumbnail and return to default
		 */
		cancelCustomThumb: function () {
			if (this.customThumbCropper) {
				this.customThumbCropper.destroy();
				this.customThumbCropper = null;
			}

			this.customThumbFile = null;
			this.useCustomThumb = false;

			$('.bd-cover-custom-thumb-editor').hide();
			$('.bd-cover-video-preview').show();
			$('.bd-cover-save-video').html('<i class="fas fa-check"></i> Use Video Cover');

			// Clear file input
			$('#bd-custom-thumb-input').val('');
		},

		/**
		 * Save video cover (modified to handle custom thumbnail)
		 */
		saveVideoCover: async function () {
			if (this.isProcessing) return;

			const videoUrl = $('#bd-cover-video-url').val().trim();
			if (!videoUrl) {
				this.showError(i18n('enterVideoUrl'));
				return;
			}

			// Basic client-side validation
			if (!videoUrl.match(/youtube|youtu\.be|vimeo/i)) {
				this.showError(i18n('invalidVideoUrl'));
				return;
			}

			this.isProcessing = true;
			this.showProcessing(i18n('settingVideo'));

			try {
				// First, set the video cover
				await $.ajax({
					url: bdLists.restUrl + 'lists/' + this.listId + '/cover/video',
					method: 'POST',
					headers: {
						'X-WP-Nonce': bdLists.nonce,
						'Content-Type': 'application/json'
					},
					data: JSON.stringify({ video_url: videoUrl }),
					processData: false
				});

				// If using custom thumbnail, upload it
				if (this.useCustomThumb && this.customThumbCropper) {
					this.showProcessing('Uploading custom thumbnail...');

					const canvas = this.customThumbCropper.getCroppedCanvas({
						width: 1200,
						height: 675,
						imageSmoothingEnabled: true,
						imageSmoothingQuality: 'high'
					});

					const croppedBlob = await new Promise((resolve) => {
						canvas.toBlob(resolve, 'image/jpeg', 0.9);
					});

					const cropData = this.customThumbCropper.getData();
					const formData = new FormData();
					formData.append('cropped', croppedBlob, 'custom-thumb.jpg');
					formData.append('crop_x', cropData.x / canvas.width);
					formData.append('crop_y', cropData.y / canvas.height);
					formData.append('crop_width', cropData.width / canvas.width);
					formData.append('crop_height', cropData.height / canvas.height);
					formData.append('crop_zoom', cropData.scaleX || 1);
					formData.append('crop_rotation', cropData.rotate || 0);

					await $.ajax({
						url: bdLists.restUrl + 'lists/' + this.listId + '/cover/video/thumbnail',
						method: 'POST',
						headers: { 'X-WP-Nonce': bdLists.nonce },
						data: formData,
						processData: false,
						contentType: false
					});
				}

				this.showSuccess(i18n('videoCoverSet'));
				announceToSR(i18n('videoCoverSet'), 'assertive');
				this.closeEditor(false);

				setTimeout(() => window.location.reload(), 500);

			} catch (error) {
				const message = error.responseJSON?.message || i18n('videoFailed');
				this.showError(message);
			}

			this.isProcessing = false;
			this.hideProcessing();
		},

		/**
		 * Remove cover
		 */
		removeCover: async function () {
			if (this.isProcessing) return;

			if (!confirm(i18n('removeConfirm'))) {
				return;
			}

			this.isProcessing = true;
			this.showProcessing(i18n('removing'));

			try {
				await $.ajax({
					url: bdLists.restUrl + 'lists/' + this.listId + '/cover',
					method: 'DELETE',
					headers: { 'X-WP-Nonce': bdLists.nonce }
				});

				this.showSuccess(i18n('coverRemoved'));
				announceToSR(i18n('coverRemoved'), 'assertive');
				this.closeEditor(false);

				setTimeout(() => window.location.reload(), 500);

			} catch (error) {
				this.showError(error.responseJSON?.message || i18n('removeFailed'));
			}

			this.isProcessing = false;
			this.hideProcessing();
		},

		/**
		 * Show processing overlay
		 * @param {string} text Processing message
		 */
		showProcessing: function (text) {
			$('.bd-cover-processing-text').text(text || i18n('processing'));
			$('.bd-cover-processing').show();
		},

		/**
		 * Hide processing overlay
		 */
		hideProcessing: function () {
			$('.bd-cover-processing').hide();
		},

		/**
		 * Show error message
		 * @param {string} message Error message
		 */
		showError: function (message) {
			announceToSR(message, 'assertive');
			if (typeof showToast === 'function') {
				showToast(message, 'error');
			} else {
				alert(message);
			}
		},

		/**
		 * Show success message
		 * @param {string} message Success message
		 */
		showSuccess: function (message) {
			if (typeof showToast === 'function') {
				showToast(message);
			}
		},

		/**
		 * Load external script dynamically
		 * @param {string} src Script URL
		 * @returns {Promise} Resolves when loaded
		 */
		loadScript: function (src) {
			return new Promise((resolve, reject) => {
				const script = document.createElement('script');
				script.src = src;
				script.onload = resolve;
				script.onerror = reject;
				document.head.appendChild(script);
			});
		}
	};

	// Initialize when DOM ready
	$(function () {
		CoverEditor.init();
	});

	// Expose for external use (namespaced)
	window.BD = window.BD || {};
	window.BD.CoverEditor = CoverEditor;

})(jQuery);
