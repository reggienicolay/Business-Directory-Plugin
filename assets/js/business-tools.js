/**
 * Business Owner Tools JavaScript
 * Dashboard interactions, modals, and AJAX handlers
 */

(function($) {
	'use strict';

	// Toast notifications
	const Toast = {
		container: null,

		init: function() {
			if (!this.container) {
				this.container = $('<div class="bd-tools-toast"></div>');
				$('body').append(this.container);
			}
		},

		show: function(message, type = 'success', duration = 3000) {
			this.init();
			
			this.container
				.removeClass('bd-toast-visible bd-toast-success bd-toast-error')
				.addClass('bd-toast-' + type)
				.text(message);

			setTimeout(() => {
				this.container.addClass('bd-toast-visible');
			}, 10);

			setTimeout(() => {
				this.container.removeClass('bd-toast-visible');
			}, duration);
		}
	};

	// Modal management
	const Modal = {
		open: function(modalId) {
			$('#bd-' + modalId).addClass('bd-modal-open');
			$('body').css('overflow', 'hidden');
		},

		close: function(modal) {
			$(modal).removeClass('bd-modal-open');
			$('body').css('overflow', '');
		},

		init: function() {
			// Open modal buttons
			$(document).on('click', '.bd-tools-open-modal', function(e) {
				e.preventDefault();
				const modalId = $(this).data('modal');
				const businessId = $(this).data('business');
				
				Modal.open(modalId);
				Modal.loadContent(modalId, businessId);
			});

			// Close modal buttons
			$(document).on('click', '.bd-tools-modal-close', function() {
				Modal.close($(this).closest('.bd-tools-modal'));
			});

			// Close on overlay click
			$(document).on('click', '.bd-tools-modal', function(e) {
				if (e.target === this) {
					Modal.close(this);
				}
			});

			// Close on ESC
			$(document).on('keydown', function(e) {
				if (e.key === 'Escape') {
					$('.bd-tools-modal.bd-modal-open').each(function() {
						Modal.close(this);
					});
				}
			});
		},

		loadContent: function(modalId, businessId) {
			switch(modalId) {
				case 'widget-modal':
					Widget.init(businessId);
					break;
				case 'qr-modal':
					QRCode.init(businessId);
					break;
				case 'badge-modal':
					Badge.init(businessId);
					break;
				case 'email-modal':
					Email.init(businessId);
					break;
			}
		}
	};

	// Widget generator
	const Widget = {
		businessId: null,

		init: function(businessId) {
			this.businessId = businessId;
			this.generateCode();
			this.bindEvents();
		},

		bindEvents: function() {
			const self = this;

			// Style change
			$('#bd-widget-modal input[name="widget_style"]').off('change').on('change', function() {
				self.generateCode();
			});

			// Theme change
			$('#widget-theme').off('change').on('change', function() {
				self.generateCode();
			});

			// Reviews count change
			$('#widget-reviews').off('change').on('change', function() {
				self.generateCode();
			});

			// Save domains
			$('#widget-domains').off('blur').on('blur', function() {
				self.saveDomains();
			});
		},

		generateCode: function() {
			const style = $('input[name="widget_style"]:checked').val() || 'compact';
			const theme = $('#widget-theme').val() || 'light';
			const reviews = $('#widget-reviews').val() || 5;

			$.ajax({
				url: bdTools.ajaxUrl,
				method: 'POST',
				data: {
					action: 'bd_get_widget_code',
					nonce: bdTools.nonce,
					business_id: this.businessId,
					style: style,
					theme: theme,
					reviews: reviews
				},
				success: function(response) {
					if (response.success) {
						$('#widget-code').val(response.data.code);
						Widget.updatePreview(style, theme);
					}
				}
			});
		},

		updatePreview: function(style, theme) {
			// Simple preview based on style
			let previewHtml = '';
			const themeClass = theme === 'dark' ? 'ltv-dark' : 'ltv-light';
			
			switch(style) {
				case 'compact':
					previewHtml = `<div class="ltv-preview-widget ${themeClass}" style="padding: 20px; border-radius: 8px; text-align: center; ${theme === 'dark' ? 'background: #1a3a4a; color: #fff;' : 'background: #fff; border: 1px solid #a8c4d4;'}">
						<div style="color: #f59e0b; margin-bottom: 8px;">★★★★★</div>
						<div style="font-weight: bold; margin-bottom: 8px;">4.8 (127 reviews)</div>
						<button style="background: #1a3a4a; color: #fff; border: none; padding: 8px 16px; border-radius: 6px;">Write a Review</button>
						<div style="margin-top: 12px; font-size: 12px; opacity: 0.7;">📍 LoveTriValley</div>
					</div>`;
					break;
				case 'carousel':
					previewHtml = `<div class="ltv-preview-widget ${themeClass}" style="padding: 20px; border-radius: 8px; ${theme === 'dark' ? 'background: #1a3a4a; color: #fff;' : 'background: #fff; border: 1px solid #a8c4d4;'}">
						<div style="font-style: italic; margin-bottom: 12px;">"Amazing service and great food!"</div>
						<div style="color: #f59e0b;">★★★★★</div>
						<div style="font-size: 14px; opacity: 0.8; margin-bottom: 12px;">— Sarah M.</div>
						<div style="display: flex; justify-content: space-between; align-items: center;">
							<span>● ○ ○</span>
							<button style="background: #1a3a4a; color: #fff; border: none; padding: 8px 16px; border-radius: 6px; font-size: 13px;">Write a Review</button>
						</div>
					</div>`;
					break;
				case 'list':
					previewHtml = `<div class="ltv-preview-widget ${themeClass}" style="padding: 20px; border-radius: 8px; ${theme === 'dark' ? 'background: #1a3a4a; color: #fff;' : 'background: #fff; border: 1px solid #a8c4d4;'}">
						<div style="border-bottom: 1px solid ${theme === 'dark' ? '#1e4258' : '#a8c4d4'}; padding-bottom: 12px; margin-bottom: 12px;">
							<strong>Joe's Coffee</strong>
							<div style="font-size: 14px;"><span style="color: #f59e0b;">★★★★★</span> 4.8 · 127 reviews</div>
						</div>
						<div style="font-size: 14px; padding: 8px 0; border-bottom: 1px solid ${theme === 'dark' ? '#1e4258' : '#f0f5f8'};">
							<span style="color: #f59e0b;">★★★★★</span> "Best cold brew!" <span style="opacity: 0.6;">— Sarah</span>
						</div>
						<div style="font-size: 14px; padding: 8px 0;">
							<span style="color: #f59e0b;">★★★★☆</span> "Love this place" <span style="opacity: 0.6;">— Mike</span>
						</div>
					</div>`;
					break;
			}

			$('#widget-preview').html(previewHtml);
		},

		saveDomains: function() {
			const domains = $('#widget-domains').val();

			$.ajax({
				url: bdTools.ajaxUrl,
				method: 'POST',
				data: {
					action: 'bd_save_widget_domains',
					nonce: bdTools.nonce,
					business_id: this.businessId,
					domains: domains
				},
				success: function(response) {
					if (response.success) {
						Toast.show(bdTools.i18n.copied || 'Domains saved!', 'success');
					}
				}
			});
		}
	};

	// QR Code generator
	const QRCode = {
		businessId: null,

		init: function(businessId) {
			this.businessId = businessId;
			this.generatePreview();
			this.bindEvents();
		},

		bindEvents: function() {
			const self = this;

			// Type change
			$('#bd-qr-modal input[name="qr_type"]').off('change').on('change', function() {
				self.generatePreview();
			});

			// Download buttons
			$('.bd-download-qr').off('click').on('click', function() {
				const format = $(this).data('format');
				self.download(format);
			});
		},

		generatePreview: function() {
			const type = $('input[name="qr_type"]:checked').val() || 'review';
			const self = this;

			$('#qr-preview-image').html('<p>Generating...</p>');

			$.ajax({
				url: bdTools.ajaxUrl,
				method: 'POST',
				data: {
					action: 'bd_generate_qr',
					nonce: bdTools.nonce,
					business_id: this.businessId,
					type: type,
					format: 'png'
				},
				success: function(response) {
					if (response.success) {
						$('#qr-preview-image').html('<img src="' + response.data.file_url + '" alt="QR Code">');
						$('#qr-url').text(response.data.qr_url);
						self.currentData = response.data;
					}
				},
				error: function() {
					$('#qr-preview-image').html('<p>Failed to generate QR code</p>');
				}
			});
		},

		download: function(format) {
			const type = $('input[name="qr_type"]:checked').val() || 'review';
			const self = this;

			$.ajax({
				url: bdTools.ajaxUrl,
				method: 'POST',
				data: {
					action: 'bd_generate_qr',
					nonce: bdTools.nonce,
					business_id: this.businessId,
					type: type,
					format: format
				},
				success: function(response) {
					if (response.success && response.data.file_url) {
						// Open download in new tab
						window.open(response.data.file_url, '_blank');
						Toast.show(bdTools.i18n.downloadReady || 'Download ready!', 'success');
					}
				}
			});
		}
	};

	// Badge generator
	const Badge = {
		businessId: null,

		init: function(businessId) {
			this.businessId = businessId;
			this.generateCode();
			this.bindEvents();
		},

		bindEvents: function() {
			const self = this;

			// Style change
			$('#bd-badge-modal input[name="badge_style"]').off('change').on('change', function() {
				self.generateCode();
			});

			// Size change
			$('#badge-size').off('change').on('change', function() {
				self.generateCode();
			});

			// Download buttons
			$('.bd-download-badge').off('click').on('click', function() {
				const format = $(this).data('format');
				self.download(format);
			});
		},

		generateCode: function() {
			const style = $('input[name="badge_style"]:checked').val() || 'rating';
			const size = $('#badge-size').val() || 'medium';

			$.ajax({
				url: bdTools.ajaxUrl,
				method: 'POST',
				data: {
					action: 'bd_get_badge_code',
					nonce: bdTools.nonce,
					business_id: this.businessId,
					style: style,
					size: size
				},
				success: function(response) {
					if (response.success) {
						$('#badge-code').val(response.data.code);
					}
				}
			});
		},

		download: function(format) {
			const style = $('input[name="badge_style"]:checked').val() || 'rating';
			
			// Construct badge URL
			const baseUrl = bdTools.restUrl.replace('/bd/v1/', '');
			const badgeUrl = baseUrl + '/badge/' + this.businessId + '?style=' + style;
			
			window.open(badgeUrl, '_blank');
			Toast.show(bdTools.i18n.downloadReady || 'Badge ready!', 'success');
		}
	};

	// Email preferences
	const Email = {
		businessId: null,

		init: function(businessId) {
			this.businessId = businessId;
			this.bindEvents();
		},

		bindEvents: function() {
			const self = this;

			// Save preferences
			$('.bd-save-email-prefs').off('click').on('click', function() {
				self.save();
			});

			// Send test email
			$('.bd-send-test-email').off('click').on('click', function() {
				self.sendTest();
			});
		},

		save: function() {
			$.ajax({
				url: bdTools.ajaxUrl,
				method: 'POST',
				data: {
					action: 'bd_update_email_prefs',
					nonce: bdTools.nonce,
					business_id: this.businessId,
					enabled: $('#email-enabled').is(':checked'),
					email: $('#email-address').val(),
					include_reviews: $('#email-reviews').is(':checked'),
					include_tips: $('#email-tips').is(':checked')
				},
				success: function(response) {
					if (response.success) {
						Toast.show(response.data.message, 'success');
					} else {
						Toast.show(response.data.message, 'error');
					}
				}
			});
		},

		sendTest: function() {
			Toast.show('Sending test email...', 'success');

			$.ajax({
				url: bdTools.ajaxUrl,
				method: 'POST',
				data: {
					action: 'bd_send_test_stats_email',
					nonce: bdTools.nonce,
					business_id: this.businessId
				},
				success: function(response) {
					if (response.success) {
						Toast.show(response.data.message, 'success');
					} else {
						Toast.show(response.data.message, 'error');
					}
				}
			});
		}
	};

	// Business selector
	const BusinessSelector = {
		init: function() {
			$('#bd-business-select').on('change', function() {
				const businessId = $(this).val();
				
				// Hide all panels
				$('.bd-tools-business-panel').hide();
				
				// Show selected panel
				$('.bd-tools-business-panel[data-business-id="' + businessId + '"]').show();
			});
		}
	};

	// Copy to clipboard
	const Clipboard = {
		init: function() {
			$(document).on('click', '.bd-copy-code', function() {
				const targetId = $(this).data('target');
				const $target = $('#' + targetId);
				
				if ($target.length) {
					$target.select();
					
					if (navigator.clipboard) {
						navigator.clipboard.writeText($target.val()).then(function() {
							Toast.show(bdTools.i18n.copied || 'Copied!', 'success');
						});
					} else {
						document.execCommand('copy');
						Toast.show(bdTools.i18n.copied || 'Copied!', 'success');
					}
				}
			});
		}
	};

	// Initialize on DOM ready
	$(function() {
		Modal.init();
		BusinessSelector.init();
		Clipboard.init();
	});

	// Expose for external use
	window.BDTools = {
		Toast: Toast,
		Modal: Modal,
		Widget: Widget,
		QRCode: QRCode,
		Badge: Badge,
		Email: Email
	};

})(jQuery);
