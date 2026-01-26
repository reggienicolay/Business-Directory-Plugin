/**
 * Video Lightbox for List Covers
 *
 * Displays video covers in a modal lightbox with iframe player.
 * Supports YouTube (privacy-enhanced) and Vimeo.
 *
 * @package BusinessDirectory
 * @since 1.2.0
 */

(function ($) {
	'use strict';

	/**
	 * Allowed video embed domains (security whitelist)
	 */
	const ALLOWED_DOMAINS = Object.freeze([
		'www.youtube-nocookie.com',
		'youtube-nocookie.com',
		'www.youtube.com',
		'youtube.com',
		'player.vimeo.com',
		'vimeo.com'
	]);

	/**
	 * Validate URL is from allowed domain
	 * @param {string} url URL to validate
	 * @returns {boolean} True if valid
	 */
	function isAllowedUrl(url) {
		try {
			const parsed = new URL(url);
			return ALLOWED_DOMAINS.some(domain => 
				parsed.hostname === domain || parsed.hostname.endsWith('.' + domain)
			);
		} catch (e) {
			return false;
		}
	}

	/**
	 * Announce to screen readers
	 * @param {string} message Message to announce
	 */
	function announceToSR(message) {
		const $region = $('<div>')
			.attr({
				'role': 'status',
				'aria-live': 'polite',
				'class': 'bd-sr-only'
			})
			.text(message)
			.appendTo('body');

		setTimeout(() => $region.remove(), 3000);
	}

	const VideoLightbox = {
		$modal: null,
		$iframe: null,
		previousFocus: null,

		/**
		 * Initialize the lightbox
		 */
		init: function () {
			this.createModal();
			this.bindEvents();
		},

		/**
		 * Create the modal HTML
		 */
		createModal: function () {
			if ($('#bd-video-lightbox').length) return;

			const html = `
				<div id="bd-video-lightbox" class="bd-video-lightbox" style="display: none;" 
					 role="dialog" aria-modal="true" aria-label="Video player">
					<div class="bd-video-lightbox-overlay"></div>
					<div class="bd-video-lightbox-container">
						<button type="button" class="bd-video-lightbox-close" aria-label="Close video">
							<i class="fas fa-times"></i>
						</button>
						<div class="bd-video-lightbox-content">
							<div class="bd-video-lightbox-player">
								<iframe 
									src="" 
									frameborder="0" 
									allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
									allowfullscreen
									title="Video player"
									sandbox="allow-scripts allow-same-origin allow-presentation">
								</iframe>
							</div>
						</div>
					</div>
				</div>
			`;

			$('body').append(html);
			this.$modal = $('#bd-video-lightbox');
			this.$iframe = this.$modal.find('iframe');
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function () {
			const self = this;

			// Click on video cover play button
			$(document).on('click', '.bd-list-cover-video-play, .bd-nl-play-overlay', function (e) {
				e.preventDefault();
				e.stopPropagation();

				const $cover = $(this).closest('[data-video-embed]');
				const embedUrl = $cover.data('video-embed');

				if (embedUrl) {
					self.open(embedUrl);
				}
			});

			// Click on video cover image (if has embed data)
			$(document).on('click', '.bd-list-cover[data-video-embed]', function (e) {
				// Don't trigger if clicking a link inside
				if ($(e.target).closest('a').length) return;
				// Don't double-trigger from play button
				if ($(e.target).closest('.bd-list-cover-video-play').length) return;

				e.preventDefault();
				const embedUrl = $(this).data('video-embed');
				if (embedUrl) {
					self.open(embedUrl);
				}
			});

			// Close button
			$(document).on('click', '.bd-video-lightbox-close, .bd-video-lightbox-overlay', function () {
				self.close();
			});

			// Escape key
			$(document).on('keydown', function (e) {
				if (e.key === 'Escape' && self.$modal && self.$modal.is(':visible')) {
					self.close();
				}
			});

			// Tab trap for accessibility
			$(document).on('keydown', '#bd-video-lightbox', function (e) {
				if (e.key !== 'Tab') return;

				const $focusable = self.$modal.find('button, [href], iframe').filter(':visible');
				const $first = $focusable.first();
				const $last = $focusable.last();

				if (e.shiftKey && document.activeElement === $first[0]) {
					e.preventDefault();
					$last.focus();
				} else if (!e.shiftKey && document.activeElement === $last[0]) {
					e.preventDefault();
					$first.focus();
				}
			});
		},

		/**
		 * Open the lightbox with a video
		 *
		 * @param {string} embedUrl Video embed URL
		 */
		open: function (embedUrl) {
			// Security: Validate embed URL is from allowed domains
			if (!isAllowedUrl(embedUrl)) {
				console.error('VideoLightbox: Invalid embed domain:', embedUrl);
				return;
			}

			// Store current focus for restoration
			this.previousFocus = document.activeElement;

			// Add autoplay to URL
			const separator = embedUrl.includes('?') ? '&' : '?';
			const autoplayUrl = embedUrl + separator + 'autoplay=1';

			this.$iframe.attr('src', autoplayUrl);
			this.$modal.fadeIn(200);
			$('body').addClass('bd-lightbox-open');

			// Focus close button for accessibility
			this.$modal.find('.bd-video-lightbox-close').focus();

			// Announce to screen readers
			announceToSR('Video player opened. Press Escape to close.');

			// Track video play for analytics
			if (typeof gtag === 'function') {
				gtag('event', 'video_play', {
					event_category: 'List Cover',
					event_label: embedUrl
				});
			}
		},

		/**
		 * Close the lightbox
		 */
		close: function () {
			this.$iframe.attr('src', '');
			this.$modal.fadeOut(200);
			$('body').removeClass('bd-lightbox-open');

			// Restore focus
			if (this.previousFocus) {
				this.previousFocus.focus();
				this.previousFocus = null;
			}

			// Announce to screen readers
			announceToSR('Video player closed');
		}
	};

	// Initialize on DOM ready
	$(function () {
		VideoLightbox.init();
	});

	// Expose globally (namespaced)
	window.BD = window.BD || {};
	window.BD.VideoLightbox = VideoLightbox;

})(jQuery);
