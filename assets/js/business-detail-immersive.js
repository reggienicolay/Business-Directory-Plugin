/**
 * Business Detail - Immersive Layout JavaScript
 *
 * Handles:
 * - Hero parallax scrolling
 * - Save/unsave button toggle
 * - Star rating interaction
 * - Photo lightbox
 * - Share functionality
 * - Sidebar map initialization
 *
 * @package BusinessDirectory
 * @since 0.1.7
 */

(function($) {
	'use strict';

	// Wait for DOM ready
	$(document).ready(function() {
		BDImmersive.init();
	});

	var BDImmersive = {
		// Configuration
		config: {
			parallaxSpeed: 0.35,
			quickBarOffset: 60
		},

		// Initialize all components
		init: function() {
			this.initParallax();
			this.initShareButtons();
			this.initLightbox();
			this.initSidebarMap();
			this.initSmoothScroll();
			this.initQuickBarVisibility();
			this.initPostingAsChange();
			this.initAddPhotoButton();
			// Note: Star ratings use native label→radio behavior + review-form.js
			// Note: Save buttons handled by lists.js
			// Note: Claim button handled by existing claim-form.js
		},

		// =====================================================================
		// PARALLAX SCROLLING - Now handled via CSS background-attachment: fixed
		// =====================================================================
		initParallax: function() {
			// CSS handles the parallax effect now via background-attachment: fixed
			// This provides smoother scrolling and better compatibility with sticky headers
			// Keeping this function stub for potential future enhancements
		},



		// =====================================================================
		// SHARE BUTTONS
		// =====================================================================
		initShareButtons: function() {
			var self = this;

			// Hero share button
			$('.bd-hero-float-btn.bd-share-btn, .bd-btn-pill.bd-share-quick').on('click', function(e) {
				e.preventDefault();
				self.openShareMenu();
			});

			// Share bar buttons
			$('.bd-share-bar .bd-share-btn').on('click', function(e) {
				e.preventDefault();
				var platform = $(this).data('platform');
				self.shareTo(platform);
			});
		},

		openShareMenu: function() {
			var url = window.location.href;
			var title = document.title;

			// Try native share API first
			if (navigator.share) {
				navigator.share({
					title: title,
					url: url
				}).catch(function(err) {
					console.log('Share cancelled:', err);
				});
			} else {
				// Fallback: copy to clipboard
				this.copyToClipboard(url);
			}
		},

		shareTo: function(platform) {
			var url = encodeURIComponent(window.location.href);
			var title = encodeURIComponent(document.title);
			var shareUrl = '';

			switch (platform) {
				case 'facebook':
					shareUrl = 'https://www.facebook.com/sharer/sharer.php?u=' + url;
					break;
				case 'twitter':
					shareUrl = 'https://twitter.com/intent/tweet?url=' + url + '&text=' + title;
					break;
				case 'copy':
					this.copyToClipboard(window.location.href);
					return;
				default:
					return;
			}

			window.open(shareUrl, '_blank', 'width=600,height=400');
		},

		copyToClipboard: function(text) {
			var self = this;
			// Safely get localized string
			var copiedMsg = 'Link copied!';
			if (typeof bdImmersiveVars !== 'undefined' && bdImmersiveVars.strings && bdImmersiveVars.strings.copied) {
				copiedMsg = bdImmersiveVars.strings.copied;
			}
			
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(text).then(function() {
					self.showToast(copiedMsg);
				}).catch(function() {
					// Fallback on clipboard API failure
					self.fallbackCopy(text, copiedMsg);
				});
			} else {
				// Fallback for older browsers
				this.fallbackCopy(text, copiedMsg);
			}
		},

		fallbackCopy: function(text, message) {
			var $temp = $('<input>');
			$('body').append($temp);
			$temp.val(text).select();
			try {
				document.execCommand('copy');
				this.showToast(message);
			} catch(e) {
				this.showToast('Press Ctrl+C to copy');
			}
			$temp.remove();
		},

		showToast: function(message) {
			// Remove any existing toast
			$('.bd-toast').remove();
			
			// Create toast safely (text content, not HTML)
			var $toast = $('<div class="bd-toast"></div>').text(message);
			$toast.css({
				position: 'fixed',
				bottom: '24px',
				left: '50%',
				transform: 'translateX(-50%)',
				background: 'var(--bd-primary-600, #1a3a4a)',
				color: '#fff',
				padding: '12px 24px',
				borderRadius: '8px',
				fontSize: '14px',
				fontWeight: '600',
				boxShadow: '0 4px 16px rgba(0,0,0,0.2)',
				zIndex: 10001,
				opacity: 0,
				transition: 'opacity 0.3s ease'
			});

			$('body').append($toast);
			// Force reflow then animate
			$toast[0].offsetHeight;
			$toast.css('opacity', 1);
			
			setTimeout(function() {
				$toast.css('opacity', 0);
				setTimeout(function() { $toast.remove(); }, 300);
			}, 2500);
		},

		// =====================================================================
		// PHOTO LIGHTBOX
		// =====================================================================
		initLightbox: function() {
			var self = this;
			var currentIndex = 0;
			var photos = window.bdBusinessPhotos || [];

			if (!photos.length) return;

			// Open lightbox triggers
			$('.bd-photo-count-badge, .bd-see-all-photos, .bd-hero-thumb').on('click', function(e) {
				e.preventDefault();
				var index = $(this).data('index') || 0;
				self.openLightbox(index);
			});

			// Close lightbox
			$('.bd-lightbox-close').on('click', function() {
				self.closeLightbox();
			});

			// Navigation
			$('.bd-lightbox-prev').on('click', function() {
				self.navigateLightbox(-1);
			});

			$('.bd-lightbox-next').on('click', function() {
				self.navigateLightbox(1);
			});

			// Keyboard navigation
			$(document).on('keydown.bdLightbox', function(e) {
				if (!$('#bd-lightbox').is(':visible')) return;

				switch (e.keyCode) {
					case 27: // Escape
						self.closeLightbox();
						break;
					case 37: // Left arrow
						self.navigateLightbox(-1);
						break;
					case 39: // Right arrow
						self.navigateLightbox(1);
						break;
				}
			});

			// Click outside to close
			$('#bd-lightbox').on('click', function(e) {
				if ($(e.target).is('#bd-lightbox')) {
					self.closeLightbox();
				}
			});
		},

		openLightbox: function(index) {
			var photos = window.bdBusinessPhotos || [];
			if (!photos.length) return;

			this.currentLightboxIndex = index;
			this.updateLightboxImage();

			$('#bd-lightbox').fadeIn(200);
			$('body').css('overflow', 'hidden');
		},

		closeLightbox: function() {
			$('#bd-lightbox').fadeOut(200);
			$('body').css('overflow', '');
		},

		navigateLightbox: function(direction) {
			var photos = window.bdBusinessPhotos || [];
			if (!photos.length) return;

			this.currentLightboxIndex += direction;

			if (this.currentLightboxIndex < 0) {
				this.currentLightboxIndex = photos.length - 1;
			} else if (this.currentLightboxIndex >= photos.length) {
				this.currentLightboxIndex = 0;
			}

			this.updateLightboxImage();
		},

		updateLightboxImage: function() {
			var photos = window.bdBusinessPhotos || [];
			var photo = photos[this.currentLightboxIndex];

			if (photo) {
				$('#bd-lightbox-image').attr('src', photo.url).attr('alt', photo.alt || '');
				$('#bd-lightbox-counter').text((this.currentLightboxIndex + 1) + ' / ' + photos.length);
			}
		},

		currentLightboxIndex: 0,

		// =====================================================================
		// SIDEBAR MAP
		// =====================================================================
		initSidebarMap: function() {
			var $mapContainer = $('#bd-sidebar-map');
			if (!$mapContainer.length || typeof L === 'undefined') return;

			var lat = parseFloat($mapContainer.data('lat'));
			var lng = parseFloat($mapContainer.data('lng'));

			if (isNaN(lat) || isNaN(lng)) return;

			// Initialize map
			var map = L.map('bd-sidebar-map', {
				scrollWheelZoom: false,
				zoomControl: false,
				dragging: false
			}).setView([lat, lng], 15);

			// Add tile layer
			L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
				attribution: '© OpenStreetMap'
			}).addTo(map);

			// Add marker
			var marker = L.marker([lat, lng]).addTo(map);

			// Handle resize
			setTimeout(function() {
				map.invalidateSize();
			}, 100);
		},

		// =====================================================================
		// SMOOTH SCROLL
		// =====================================================================
		initSmoothScroll: function() {
			$('a[href^="#"]').on('click', function(e) {
				var target = $(this.hash);
				if (target.length) {
					e.preventDefault();
					$('html, body').animate({
						scrollTop: target.offset().top - 80
					}, 500);
				}
			});
		},

		// =====================================================================
		// QUICK BAR VISIBILITY (with throttling for performance)
		// =====================================================================
		initQuickBarVisibility: function() {
			var $quickBar = $('.bd-quick-bar');
			var $hero = $('.bd-immersive-hero');

			if (!$quickBar.length || !$hero.length) return;

			var heroBottom = $hero.offset().top + $hero.outerHeight();
			var ticking = false;

			// Throttled scroll handler using requestAnimationFrame
			function updateQuickBar() {
				var scrollY = window.scrollY || window.pageYOffset;

				if (scrollY > heroBottom - 60) {
					$quickBar.addClass('bd-quick-bar--visible');
				} else {
					$quickBar.removeClass('bd-quick-bar--visible');
				}
				ticking = false;
			}

			$(window).on('scroll.bdQuickBar', function() {
				if (!ticking) {
					window.requestAnimationFrame(updateQuickBar);
					ticking = true;
				}
			});

			// Update heroBottom on resize
			$(window).on('resize.bdQuickBar', function() {
				heroBottom = $hero.offset().top + $hero.outerHeight();
			});
		},

		// =====================================================================
		// POSTING AS CHANGE HANDLER
		// =====================================================================
		initPostingAsChange: function() {
			var $changeBtn = $('.bd-posting-change');
			var $postingBar = $('.bd-posting-as-bar');
			
			if (!$changeBtn.length) return;

			$changeBtn.on('click', function(e) {
				e.preventDefault();
				
				var $bar = $(this).closest('.bd-posting-as-bar');
				var currentName = $bar.find('.bd-posting-name').text();
				
				// Check if already in edit mode
				if ($bar.find('.bd-posting-name-input').length) {
					return;
				}
				
				// Replace name with input (safe - using .val() not HTML injection)
				var $nameSpan = $bar.find('.bd-posting-name');
				var $input = $('<input type="text" class="bd-posting-name-input">').val(currentName).css({
					flex: '1',
					padding: '6px 10px',
					border: '1px solid #ddd',
					borderRadius: '4px',
					fontSize: '14px'
				});
				var $saveBtn = $('<button type="button" class="bd-posting-save">Save</button>').css({
					marginLeft: '8px',
					padding: '6px 12px',
					background: 'var(--bd-primary-600)',
					color: '#fff',
					border: 'none',
					borderRadius: '4px',
					cursor: 'pointer',
					fontSize: '13px'
				});
				
				$nameSpan.replaceWith($input);
				$(this).replaceWith($saveBtn);
				
				$input.focus().select();
				
				// Save handler
				$saveBtn.on('click', function() {
					var newName = $input.val().trim();
					if (newName) {
						// Save via AJAX (update user meta)
						$.ajax({
							url: (typeof bdImmersiveVars !== 'undefined' ? bdImmersiveVars.restUrl : '/wp-json/bd/v1/') + 'users/display-name',
							method: 'POST',
							data: { display_name: newName },
							beforeSend: function(xhr) {
								if (typeof bdImmersiveVars !== 'undefined') {
									xhr.setRequestHeader('X-WP-Nonce', bdImmersiveVars.nonce);
								}
								$saveBtn.prop('disabled', true).text('Saving...');
							},
							success: function() {
								// Update UI (safe - using .text() not HTML injection)
								var $newNameSpan = $('<span class="bd-posting-name"></span>').text(newName);
								var $newChangeBtn = $('<button type="button" class="bd-posting-change">Change</button>');
								
								$input.replaceWith($newNameSpan);
								$saveBtn.replaceWith($newChangeBtn);
								
								// Re-init for new button
								BDImmersive.initPostingAsChange();
							},
							error: function() {
								// Re-enable save button on error
								$saveBtn.prop('disabled', false).text('Save');
								BDImmersive.showToast('Failed to save. Please try again.');
							}
						});
					}
				});
				
				// Enter key to save
				$input.on('keypress', function(e) {
					if (e.which === 13) {
						e.preventDefault();
						$saveBtn.click();
					}
				});
			});
		},

		// =====================================================================
		// ADD PHOTO BUTTON - Scroll to review form and highlight photo upload
		// =====================================================================
		initAddPhotoButton: function() {
			$('.bd-add-photo-btn').on('click', function(e) {
				// Don't prevent default - let it scroll to #write-review
				
				// After scroll, highlight the photo upload section
				setTimeout(function() {
					var $photoSection = $('.bd-photo-upload, .bd-form-row:has(input[type="file"]), #bd-photo-upload, [name="review_photos"]').closest('.bd-form-row');
					
					if ($photoSection.length) {
						// Add highlight effect
						$photoSection.addClass('bd-highlight-pulse');
						
						// Remove highlight after animation
						setTimeout(function() {
							$photoSection.removeClass('bd-highlight-pulse');
						}, 2000);
					}
				}, 600); // Wait for scroll to complete
			});
		}
	};

	// Expose to global scope
	window.BDImmersive = BDImmersive;

})(jQuery);
