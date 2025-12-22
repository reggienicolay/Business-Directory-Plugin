/**
 * Social Sharing JavaScript
 * Handles share button clicks, tracking, and user feedback
 */

(function($) {
	'use strict';

	// Toast notification system
	const Toast = {
		container: null,
		timeout: null,

		init: function() {
			this.container = document.getElementById('bd-share-toast');
			if (!this.container) {
				this.container = document.createElement('div');
				this.container.id = 'bd-share-toast';
				this.container.className = 'bd-share-toast';
				this.container.setAttribute('aria-live', 'polite');
				document.body.appendChild(this.container);
			}
		},

		show: function(message, type = 'success', duration = 3000) {
			if (!this.container) this.init();

			// Clear any existing timeout
			if (this.timeout) {
				clearTimeout(this.timeout);
			}

			// Remove existing classes
			this.container.className = 'bd-share-toast';
			
			// Set content and type
			this.container.textContent = message;
			this.container.classList.add('bd-toast-' + type);

			// Show toast
			setTimeout(() => {
				this.container.classList.add('bd-toast-visible');
			}, 10);

			// Hide after duration
			this.timeout = setTimeout(() => {
				this.hide();
			}, duration);
		},

		hide: function() {
			if (this.container) {
				this.container.classList.remove('bd-toast-visible');
			}
		}
	};

	// Share tracking
	const ShareTracker = {
		track: function(type, objectId, platform) {
			if (!bdShare || !bdShare.restUrl) {
				console.warn('Share tracking not configured');
				return Promise.resolve({ success: false });
			}

			return fetch(bdShare.restUrl + 'share/track', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': bdShare.nonce
				},
				body: JSON.stringify({
					type: type,
					object_id: objectId,
					platform: platform
				})
			})
			.then(response => response.json())
			.catch(error => {
				console.error('Share tracking error:', error);
				return { success: false };
			});
		}
	};

	// Copy to clipboard
	const Clipboard = {
		copy: function(text) {
			// Modern approach
			if (navigator.clipboard && navigator.clipboard.writeText) {
				return navigator.clipboard.writeText(text);
			}

			// Fallback for older browsers
			return new Promise((resolve, reject) => {
				const textarea = document.createElement('textarea');
				textarea.value = text;
				textarea.style.position = 'fixed';
				textarea.style.left = '-9999px';
				document.body.appendChild(textarea);
				textarea.select();

				try {
					document.execCommand('copy');
					resolve();
				} catch (err) {
					reject(err);
				} finally {
					document.body.removeChild(textarea);
				}
			});
		}
	};

	// Share button handlers
	const ShareButtons = {
		init: function() {
			// Delegate click events
			$(document).on('click', '.bd-share-btn', this.handleClick.bind(this));
		},

		handleClick: function(e) {
			const $btn = $(e.currentTarget);
			const platform = $btn.data('platform');
			const shareUrl = $btn.data('share-url');
			const shareText = $btn.data('share-text');
			const shareTitle = $btn.data('share-title');

			// Get container data
			const $container = $btn.closest('.bd-share-buttons');
			const shareType = $container.data('share-type') || 'business';
			const objectId = $container.data('object-id') || 0;

			// Handle different platforms
			if (platform === 'copy_link' || platform === 'nextdoor') {
				e.preventDefault();
				this.handleCopyLink(shareUrl, shareType, objectId, platform);
				return;
			}

			if (platform === 'email') {
				// Email links work normally, just track
				this.trackShare(shareType, objectId, platform);
				return;
			}

			// External share links - open in popup
			if (platform === 'facebook' || platform === 'linkedin') {
				e.preventDefault();
				const href = $btn.attr('href');
				this.openSharePopup(href, platform);
				this.trackShare(shareType, objectId, platform);
			}
		},

		handleCopyLink: function(url, type, objectId, platform) {
			Clipboard.copy(url)
				.then(() => {
					Toast.show(bdShare.i18n.copied || 'Link copied!', 'success');
					this.trackShare(type, objectId, platform);
				})
				.catch(() => {
					// Show modal with copy input as fallback
					this.showCopyModal(url);
				});
		},

		openSharePopup: function(url, platform) {
			const width = 600;
			const height = 400;
			const left = (window.innerWidth - width) / 2;
			const top = (window.innerHeight - height) / 2;

			window.open(
				url,
				'share_' + platform,
				`width=${width},height=${height},left=${left},top=${top},menubar=no,toolbar=no,location=no,status=no`
			);
		},

		trackShare: function(type, objectId, platform) {
			ShareTracker.track(type, objectId, platform)
				.then(response => {
					if (response.success && response.points_awarded > 0) {
						Toast.show(response.message, 'points', 4000);
					} else if (response.success && response.limit_reached) {
						Toast.show(response.message, 'success');
					} else if (response.success) {
						Toast.show(bdShare.i18n.shareSuccess || 'Thanks for sharing!', 'success');
					}
				});
		},

		showCopyModal: function(url) {
			// Create modal if doesn't exist
			let $modal = $('#bd-copy-modal');
			if (!$modal.length) {
				$modal = $(`
					<div id="bd-copy-modal" class="bd-share-modal-overlay">
						<div class="bd-share-modal">
							<button class="bd-share-modal-close">&times;</button>
							<h3>Copy Link</h3>
							<p>Copy the link below to share:</p>
							<div class="bd-share-copy-input-wrapper">
								<input type="text" class="bd-share-copy-input" readonly>
								<button class="bd-share-copy-btn">Copy</button>
							</div>
						</div>
					</div>
				`);
				$('body').append($modal);

				// Modal events
				$modal.on('click', '.bd-share-modal-close', function() {
					$modal.removeClass('bd-modal-visible');
				});

				$modal.on('click', function(e) {
					if (e.target === this) {
						$modal.removeClass('bd-modal-visible');
					}
				});

				$modal.on('click', '.bd-share-copy-btn', function() {
					const $input = $modal.find('.bd-share-copy-input');
					$input.select();
					document.execCommand('copy');
					$(this).text('Copied!').addClass('bd-copied');
					setTimeout(() => {
						$(this).text('Copy').removeClass('bd-copied');
					}, 2000);
				});
			}

			// Set URL and show
			$modal.find('.bd-share-copy-input').val(url);
			$modal.addClass('bd-modal-visible');
		}
	};

	// Share prompts (after review submission, badge earned)
	const SharePrompts = {
		init: function() {
			this.checkForPrompts();
		},

		checkForPrompts: function() {
			// Check URL for share prompt triggers
			const urlParams = new URLSearchParams(window.location.search);

			if (urlParams.get('review_submitted')) {
				this.showReviewSharePrompt();
			}

			if (urlParams.get('badge_earned')) {
				const badgeKey = urlParams.get('badge_earned');
				this.showBadgeSharePrompt(badgeKey);
			}
		},

		showReviewSharePrompt: function() {
			// Find review success message and append share prompt
			const $successMessage = $('.bd-success, .bd-review-success');
			if (!$successMessage.length) return;

			const $prompt = $(`
				<div class="bd-share-prompt">
					<h4>🎉 Thanks for your review!</h4>
					<p>Share it with friends and earn bonus points</p>
					<div class="bd-share-buttons bd-share-compact" 
						 data-share-type="review" 
						 data-object-id="0"
						 data-share-url="${window.location.href}">
						<div class="bd-share-buttons-list">
							${this.renderShareButtons()}
						</div>
						<span class="bd-share-points-hint">+10 points</span>
					</div>
				</div>
			`);

			$successMessage.after($prompt);
		},

		showBadgeSharePrompt: function(badgeKey) {
			// This would typically be shown in a modal or notification
			// For now, show as toast with share link
			Toast.show('🏆 You earned a badge! Share to get +15 points', 'success', 5000);
		},

		renderShareButtons: function() {
			const url = encodeURIComponent(window.location.href);
			const text = encodeURIComponent('Check this out!');

			return `
				<a href="https://www.facebook.com/sharer/sharer.php?u=${url}&quote=${text}" 
				   class="bd-share-btn bd-share-facebook" 
				   data-platform="facebook"
				   data-share-url="${window.location.href}"
				   target="_blank">
					<i class="fab fa-facebook-f"></i>
				</a>
				<a href="https://www.linkedin.com/sharing/share-offsite/?url=${url}" 
				   class="bd-share-btn bd-share-linkedin" 
				   data-platform="linkedin"
				   data-share-url="${window.location.href}"
				   target="_blank">
					<i class="fab fa-linkedin-in"></i>
				</a>
				<a href="#copy" 
				   class="bd-share-btn bd-share-nextdoor" 
				   data-platform="nextdoor"
				   data-share-url="${window.location.href}">
					<i class="fas fa-home"></i>
				</a>
				<a href="#copy" 
				   class="bd-share-btn bd-share-copy_link" 
				   data-platform="copy_link"
				   data-share-url="${window.location.href}">
					<i class="fas fa-link"></i>
				</a>
			`;
		}
	};

	// Native Share API support (for mobile)
	const NativeShare = {
		isSupported: function() {
			return navigator.share !== undefined;
		},

		share: function(data) {
			if (!this.isSupported()) {
				return Promise.reject(new Error('Native share not supported'));
			}

			return navigator.share({
				title: data.title,
				text: data.text,
				url: data.url
			});
		}
	};

	// Floating sidebar
	const FloatingSidebar = {
		sidebar: null,
		triggerOffset: 300, // Show after scrolling 300px

		init: function() {
			this.sidebar = document.querySelector('.bd-share-floating-sidebar');
			if (!this.sidebar) return;

			// Check initial state
			this.checkVisibility();

			// Listen for scroll
			let ticking = false;
			window.addEventListener('scroll', () => {
				if (!ticking) {
					window.requestAnimationFrame(() => {
						this.checkVisibility();
						ticking = false;
					});
					ticking = true;
				}
			});
		},

		checkVisibility: function() {
			if (!this.sidebar) return;

			const scrollY = window.scrollY || window.pageYOffset;
			
			if (scrollY > this.triggerOffset) {
				this.sidebar.classList.add('bd-floating-visible');
			} else {
				this.sidebar.classList.remove('bd-floating-visible');
			}
		}
	};

	// Mobile share bar
	const MobileShareBar = {
		bar: null,

		init: function() {
			// Only on single business pages
			if (!document.querySelector('.bd-share-buttons')) return;
			
			// Check if we're on mobile
			if (window.innerWidth > 1024) return;

			// Add body class for padding
			document.body.classList.add('bd-has-share-bar');
		}
	};

	// Initialize on DOM ready
	$(function() {
		Toast.init();
		ShareButtons.init();
		SharePrompts.init();
		FloatingSidebar.init();
		MobileShareBar.init();

		// Add native share button on mobile if supported
		if (NativeShare.isSupported()) {
			$('.bd-share-buttons').each(function() {
				const $container = $(this);
				const url = $container.data('share-url');
				
				// Could add a "More..." button that triggers native share
				// For now, the existing buttons work on mobile
			});
		}
	});

	// Expose for external use
	window.BDShare = {
		Toast: Toast,
		ShareTracker: ShareTracker,
		Clipboard: Clipboard,
		NativeShare: NativeShare
	};

})(jQuery);
