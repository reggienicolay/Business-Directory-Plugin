/**
 * Quick Filter Directory JavaScript
 *
 * Handles AJAX filtering, tag interactions, map integration,
 * and business card rendering for the Quick Filter layout.
 *
 * @package BusinessDirectory
 */

(function ($) {
	'use strict';

	/**
	 * Quick Filter Controller
	 */
	const QuickFilters = {

		// Current filter state
		state: {
			category: null,
			area: null,
			tags: [],
			priceLevel: [],
			minRating: null,
			openNow: false,
			search: '',
			sort: 'featured',
			view: 'list',
			page: 1,
			lat: null,
			lng: null
		},

		// Data from PHP
		data: {},

		// Debounce timer
		searchTimer: null,

		// Track active AJAX request for tag filtering
		tagFilterRequest: null,

		// Map instance
		map: null,
		markers: [],
		markerCluster: null,

		/**
		 * Read URL parameters and apply to state
		 */
		readUrlParams: function() {
			const params = new URLSearchParams(window.location.search);
			
			// Category (experience) - by slug or ID
			if (params.has('category')) {
				const categoryValue = params.get('category');
				// Try slug first
				let $categoryBtn = $('.bd-qf-experience-btn').filter(function() {
					return $(this).data('category-slug') === categoryValue;
				});
				// Fall back to ID
				if (!$categoryBtn.length) {
					$categoryBtn = $('.bd-qf-experience-btn').filter(function() {
						return String($(this).data('category-id')) === categoryValue;
					});
				}
				if ($categoryBtn.length) {
					this.state.category = $categoryBtn.data('category-id');
					$categoryBtn.addClass('active');
				}
			}
			
			// Area - by slug or ID
			if (params.has('area')) {
				const areaValue = params.get('area');
				// Try slug first
				let $areaOption = $('#bd-qf-area option').filter(function() {
					return $(this).data('slug') === areaValue;
				});
				// Fall back to ID (value)
				if (!$areaOption.length) {
					$areaOption = $('#bd-qf-area option[value="' + areaValue + '"]');
				}
				if ($areaOption.length && $areaOption.val()) {
					this.state.area = parseInt($areaOption.val(), 10);
					$('#bd-qf-area').val($areaOption.val());
				}
			}
			
			// Tags (comma-separated slugs or IDs)
			if (params.has('tags')) {
				const tagValues = params.get('tags').split(',');
				const self = this;
				tagValues.forEach(function(value) {
					// Try slug first
					let $tagBtn = $('.bd-qf-tag-btn').filter(function() {
						return $(this).data('tag-slug') === value;
					});
					// Fall back to ID
					if (!$tagBtn.length) {
						$tagBtn = $('.bd-qf-tag-btn').filter(function() {
							return String($(this).data('tag-id')) === value;
						});
					}
					if ($tagBtn.length) {
						const tagId = $tagBtn.data('tag-id');
						self.state.tags.push(tagId);
						$tagBtn.addClass('active');
					}
				});
			}
			
			// Search
			if (params.has('search') || params.has('q')) {
				this.state.search = params.get('search') || params.get('q');
				$('#bd-qf-search').val(this.state.search);
			}
			
			// Sort
			if (params.has('sort')) {
				this.state.sort = params.get('sort');
				$('#bd-qf-sort').val(this.state.sort);
			}
		},

		/**
		 * Update URL with current filter state (without page reload)
		 */
		updateUrl: function() {
			const params = new URLSearchParams();
			
			// Category - prefer slug
			if (this.state.category) {
				const $activeCategory = $('.bd-qf-experience-btn.active');
				if ($activeCategory.length) {
					const slug = $activeCategory.data('category-slug');
					params.set('category', slug || this.state.category);
				}
			}
			
			// Area - prefer slug
			if (this.state.area) {
				const $selectedArea = $('#bd-qf-area option:selected');
				if ($selectedArea.length) {
					const slug = $selectedArea.data('slug');
					params.set('area', slug || this.state.area);
				}
			}
			
			// Tags - prefer slugs
			if (this.state.tags.length > 0) {
				const tagSlugs = [];
				this.state.tags.forEach(function(tagId) {
					const $tag = $('.bd-qf-tag-btn[data-tag-id="' + tagId + '"]');
					if ($tag.length) {
						const slug = $tag.data('tag-slug');
						tagSlugs.push(slug || tagId);
					}
				});
				if (tagSlugs.length > 0) {
					params.set('tags', tagSlugs.join(','));
				}
			}
			
			// Search
			if (this.state.search) {
				params.set('search', this.state.search);
			}
			
			// Sort (only if not default)
			if (this.state.sort && this.state.sort !== 'featured') {
				params.set('sort', this.state.sort);
			}
			
			// Build new URL
			const newUrl = params.toString() 
				? window.location.pathname + '?' + params.toString()
				: window.location.pathname;
			
			// Update browser URL without reload
			window.history.replaceState({}, '', newUrl);
		},

		/**
		 * Initialize
		 */
		init: function () {
			// Parse data from PHP
			const $dataEl = $('#bd-qf-data');
			if ($dataEl.length) {
				try {
					this.data = JSON.parse($dataEl.text());
				} catch (e) {
					console.error('Failed to parse Quick Filter data:', e);
				}
			}

			// Read URL parameters and apply to state
			this.readUrlParams();

			// If category was set from URL, filter tags for that category
			if (this.state.category) {
				this.filterTagsForCategory(this.state.category);
			}

			this.bindEvents();
			this.initMap();
			this.loadBusinesses();
			this.updateActiveFilters();
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function () {
			const self = this;

			// Experience buttons (Categories)
			$(document).on('click', '.bd-qf-experience-btn', function () {
				const $btn = $(this);
				const categoryId = $btn.data('category-id');

				if ($btn.hasClass('active')) {
					// Deselecting category - show ALL tags again
					$btn.removeClass('active');
					self.state.category = null;
					self.showAllTags();
				} else {
					// Selecting a new category
					$('.bd-qf-experience-btn').removeClass('active');
					$btn.addClass('active');
					self.state.category = categoryId;
					self.filterTagsForCategory(categoryId);
				}

				self.state.page = 1;
				self.loadBusinesses();
				self.updateActiveFilters();
			});

			// Tag buttons
			$(document).on('click', '.bd-qf-tag-btn', function () {
				const $btn = $(this);
				const tagId = $btn.data('tag-id');

				if ($btn.hasClass('active')) {
					$btn.removeClass('active');
					self.state.tags = self.state.tags.filter(function (t) {
						return t !== tagId;
					});
				} else {
					$btn.addClass('active');
					self.state.tags.push(tagId);
				}

				self.state.page = 1;
				self.loadBusinesses();
				self.updateActiveFilters();
			});

			// Experiences/Categories expand (for mobile)
			$('#bd-qf-experiences-expand').on('click', function () {
				const $experiences = $(this).closest('.bd-qf-experiences');
				$experiences.toggleClass('expanded');
				const isExpanded = $experiences.hasClass('expanded');
				$(this).html(isExpanded ? 'Show Less <i class="fas fa-chevron-up"></i>' : 'See All <i class="fas fa-chevron-down"></i>');
			});

			// Tags toggle (expand/collapse) - expand vertically
			$('#bd-qf-tags-toggle').on('click', function () {
				const $row = $(this).closest('.bd-qf-tags-row');
				$row.toggleClass('expanded');
				
				// Update the "more" button text
				const $moreBtn = $('#bd-qf-tags-more');
				if ($row.hasClass('expanded')) {
					$moreBtn.text('Show Less');
				} else {
					// Count only tags hidden by limit, not by category
					const hiddenCount = $('.bd-qf-tag-btn.bd-qf-tag-hidden:not(.bd-qf-tag-category-hidden)').length;
					$moreBtn.text('+' + hiddenCount + ' more');
				}
			});

			// Tags "more" button - also expands vertically
			$('#bd-qf-tags-more').on('click', function () {
				const $row = $(this).closest('.bd-qf-tags-row');
				$row.toggleClass('expanded');
				
				if ($row.hasClass('expanded')) {
					$(this).text('Show Less');
				} else {
					// Count only tags hidden by limit, not by category
					const hiddenCount = $('.bd-qf-tag-btn.bd-qf-tag-hidden:not(.bd-qf-tag-category-hidden)').length;
					$(this).text('+' + hiddenCount + ' more');
				}
			});

			// Search input (debounced)
			$('#bd-qf-search').on('input', function () {
				const query = $(this).val();
				clearTimeout(self.searchTimer);
				self.searchTimer = setTimeout(function () {
					self.state.search = query;
					self.state.page = 1;
					self.loadBusinesses();
				}, 300);
			});

			// Area dropdown
			$('#bd-qf-area').on('change', function () {
				const areaId = $(this).val();
				self.state.area = areaId ? parseInt(areaId, 10) : null;
				self.state.page = 1;
				self.loadBusinesses();
				self.updateActiveFilters();
			});

			// Sort dropdown
			$('#bd-qf-sort').on('change', function () {
				self.state.sort = $(this).val();
				self.state.page = 1;
				self.loadBusinesses();
			});

			// View toggle (grid, list, split)
			// On desktop (1024px+), split is default. On mobile/tablet, list is default.
			var wasDesktop = window.innerWidth >= 1024;
			
			function setDefaultView() {
				const isDesktop = window.innerWidth >= 1024;
				const $mainContent = $('#bd-qf-main-content');
				
				// Detect if we crossed the breakpoint
				if (isDesktop && !wasDesktop) {
					// Went from mobile/tablet TO desktop - restore split view
					self.state.view = 'split';
					$mainContent.removeClass('bd-qf-no-split');
				} else if (!isDesktop && wasDesktop) {
					// Went from desktop TO mobile/tablet - switch to list
					if (self.state.view === 'split') {
						self.state.view = 'list';
					}
					$mainContent.addClass('bd-qf-no-split');
				}
				
				// Update button states
				$('.bd-qf-view-btn').removeClass('active');
				$('.bd-qf-view-btn[data-view="' + self.state.view + '"]').addClass('active');
				
				// Remember current state for next resize
				wasDesktop = isDesktop;
				
				// Resize map
				setTimeout(function() {
					if (self.map) {
						self.map.invalidateSize();
					}
				}, 100);
			}

			// Set initial view based on screen size
			if (wasDesktop) {
				self.state.view = 'split';
				$('.bd-qf-view-btn').removeClass('active');
				$('.bd-qf-view-btn[data-view="split"]').addClass('active');
			} else {
				self.state.view = 'list';
				$('.bd-qf-view-btn').removeClass('active');
				$('.bd-qf-view-btn[data-view="list"]').addClass('active');
				$('#bd-qf-main-content').addClass('bd-qf-no-split');
			}

			// Handle window resize
			let resizeTimer;
			$(window).on('resize', function() {
				clearTimeout(resizeTimer);
				resizeTimer = setTimeout(function() {
					setDefaultView();
					// Always invalidate map on resize
					if (self.map) {
						self.map.invalidateSize();
					}
				}, 250);
			});

			$('.bd-qf-view-btn').on('click', function () {
				const view = $(this).data('view');
				$('.bd-qf-view-btn').removeClass('active');
				$(this).addClass('active');
				self.state.view = view;
				
				// Toggle split/no-split class on main content container
				const $mainContent = $('#bd-qf-main-content');
				if (view === 'split') {
					$mainContent.removeClass('bd-qf-no-split');
				} else {
					$mainContent.addClass('bd-qf-no-split');
				}
				
				// Invalidate map size after layout change
				setTimeout(function() {
					if (self.map) {
						self.map.invalidateSize();
					}
				}, 100);
				
				self.renderView();
			});

			// Card hover - highlight corresponding map marker
			$(document).on('mouseenter', '.bd-qf-card', function() {
				const businessId = $(this).data('business-id');
				// Find the marker and highlight it
				self.markers.forEach(function(marker) {
					if (marker.businessId === businessId) {
						// Pan map to marker (only in split view)
						if (self.state.view === 'split' && self.map) {
							self.map.panTo(marker.getLatLng(), { animate: true });
						}
						// Add highlight class to marker
						if (marker._icon) {
							$(marker._icon).addClass('bd-marker-highlight');
						}
					}
				});
			});

			$(document).on('mouseleave', '.bd-qf-card', function() {
				// Remove highlight from all markers
				self.markers.forEach(function(marker) {
					if (marker._icon) {
						$(marker._icon).removeClass('bd-marker-highlight');
					}
				});
			});

			// More filters button
			$('#bd-qf-more-filters').on('click', function () {
				const $modal = $('#bd-qf-filters-modal');
				$modal.attr('style', '').addClass('bd-qf-modal-visible');
				$('body').addClass('bd-qf-modal-open');
			});

			// Close filters modal function
			function closeFiltersModal() {
				$('#bd-qf-filters-modal').removeClass('bd-qf-modal-visible');
				$('body').removeClass('bd-qf-modal-open');
				// Add display:none after transition
				setTimeout(function() {
					if (!$('#bd-qf-filters-modal').hasClass('bd-qf-modal-visible')) {
						$('#bd-qf-filters-modal').attr('style', 'display: none;');
					}
				}, 200);
			}

			// Close filters modal
			$('.bd-qf-filters-modal-close').on('click', closeFiltersModal);

			// Click outside modal to close
			$('#bd-qf-filters-modal').on('click', function (e) {
				if ($(e.target).is('#bd-qf-filters-modal')) {
					closeFiltersModal();
				}
			});
			
			// ESC key to close modal
			$(document).on('keydown', function(e) {
				if (e.key === 'Escape' && $('#bd-qf-filters-modal').hasClass('bd-qf-modal-visible')) {
					closeFiltersModal();
				}
			});

			// Price buttons
			$(document).on('click', '.bd-qf-price-btn', function () {
				$(this).toggleClass('active');
			});

			// Rating buttons
			$(document).on('click', '.bd-qf-rating-btn', function () {
				$('.bd-qf-rating-btn').removeClass('active');
				$(this).addClass('active');
			});

			// Apply filters
			$('.bd-qf-filters-apply').on('click', function () {
				// Collect price levels
				self.state.priceLevel = [];
				$('.bd-qf-price-btn.active').each(function () {
					self.state.priceLevel.push($(this).data('price'));
				});

				// Get rating
				const $activeRating = $('.bd-qf-rating-btn.active');
				self.state.minRating = $activeRating.length ? parseInt($activeRating.data('rating'), 10) : null;

				// Open now
				self.state.openNow = $('#bd-qf-open-now').is(':checked');

				// Update button state
				const hasFilters = self.state.priceLevel.length > 0 || self.state.minRating || self.state.openNow;
				$('#bd-qf-more-filters').toggleClass('has-filters', hasFilters);

				self.state.page = 1;
				self.loadBusinesses();
				self.updateActiveFilters();
				
				// Close modal
				$('#bd-qf-filters-modal').removeClass('bd-qf-modal-visible');
				$('body').removeClass('bd-qf-modal-open');
				setTimeout(function() {
					if (!$('#bd-qf-filters-modal').hasClass('bd-qf-modal-visible')) {
						$('#bd-qf-filters-modal').attr('style', 'display: none;');
					}
				}, 200);
			});

			// Clear modal filters
			$('.bd-qf-filters-clear').on('click', function () {
				$('.bd-qf-price-btn').removeClass('active');
				$('.bd-qf-rating-btn').removeClass('active');
				$('#bd-qf-open-now').prop('checked', false);
			});

			// Clear all filters
			$('#bd-qf-clear-all').on('click', function () {
				self.clearAllFilters();
			});

			// Remove individual filter pill
			$(document).on('click', '.bd-qf-pill-close, .bd-qf-active-pill button', function (e) {
				e.stopPropagation();
				const $pill = $(this).closest('.bd-qf-active-pill');
				const filterType = $pill.data('filter-type');
				const filterValue = $pill.data('filter-value');

				self.removeFilter(filterType, filterValue);
			});

			// Save to list button handler for dynamically rendered cards
			// Since these cards don't have the pre-rendered modal, we need to fetch lists via AJAX
			$(document).on('click', '.bd-qf-card .bd-save-btn', function (e) {
				e.preventDefault();
				e.stopPropagation();

				const $btn = $(this);
				const $wrapper = $btn.closest('.bd-save-wrapper');
				const businessId = $wrapper.data('business-id');

				// Check if user is logged in
				const isLoggedIn = $('body').hasClass('logged-in') || $('#wpadminbar').length > 0;

				if (!isLoggedIn) {
					self.showToast('Please log in to save to a list');
					setTimeout(function() {
						window.location.href = '/wp-login.php?redirect_to=' + encodeURIComponent(window.location.href);
					}, 1500);
					return;
				}

				// Check if modal already exists for this business
				let $modal = $('#bd-qf-save-modal');
				
				if (!$modal.length) {
					// Create the shared modal
					const modalHtml = `
						<div id="bd-qf-save-modal" class="bd-qf-save-modal" style="display:none;">
							<div class="bd-qf-save-modal-content">
								<div class="bd-qf-save-modal-header">
									<h4>Save to List</h4>
									<button type="button" class="bd-qf-save-modal-close"><i class="fas fa-times"></i></button>
								</div>
								<div class="bd-qf-save-modal-body">
									<div class="bd-qf-save-loading">
										<i class="fas fa-spinner fa-spin"></i> Loading your lists...
									</div>
									<div class="bd-qf-save-lists"></div>
									<div class="bd-qf-save-create">
										<input type="text" class="bd-qf-save-new-list" placeholder="Create new list...">
										<button type="button" class="bd-qf-save-create-btn">Create</button>
									</div>
								</div>
							</div>
						</div>
					`;
					$('body').append(modalHtml);
					$modal = $('#bd-qf-save-modal');
				}

				// Store business ID on modal
				$modal.data('business-id', businessId);

				// Show modal and fetch lists
				$modal.fadeIn(200);
				$('body').addClass('bd-qf-modal-open');

				// Fetch user's lists via REST API
				const $listsContainer = $modal.find('.bd-qf-save-lists');
				const $loading = $modal.find('.bd-qf-save-loading');
				
				$loading.show();
				$listsContainer.empty();


				// Get user's lists
				$.ajax({
					url: bdQuickFilters.restUrl + 'lists',
					method: 'GET',
					headers: { 'X-WP-Nonce': bdQuickFilters.nonce },
					success: function(response) {
						$loading.hide();
						
						// API returns { lists: [...], total: X }
						var lists = [];
						if (response && response.lists && Array.isArray(response.lists)) {
							lists = response.lists;
						} else if (Array.isArray(response)) {
							lists = response;
						}
						
						
						if (lists.length > 0) {
							// Render each list
							for (var i = 0; i < lists.length; i++) {
								var list = lists[i];
								
								var listHtml = '<label class="bd-qf-save-list-item">' +
									'<input type="checkbox" data-list-id="' + list.id + '">' +
									'<span class="bd-qf-save-list-title">' + (list.title || 'Untitled') + '</span>' +
									'<span class="bd-qf-save-list-count">' + (list.item_count || 0) + ' items</span>' +
									'</label>';
								
								$listsContainer.append(listHtml);
							}
						} else {
							$listsContainer.html('<p class="bd-qf-save-empty">No lists yet. Create one below!</p>');
						}
					},
					error: function(xhr) {
						$loading.hide();
						console.error('Lists API error:', xhr.status, xhr.responseText);
						$listsContainer.html('<p class="bd-qf-save-error">Could not load lists. Please try again.</p>');
					}
				});
			});

			// Close save modal
			$(document).on('click', '.bd-qf-save-modal-close', function() {
				$('#bd-qf-save-modal').fadeOut(200);
				$('body').removeClass('bd-qf-modal-open');
			});

			// Click outside to close
			$(document).on('click', '#bd-qf-save-modal', function(e) {
				if ($(e.target).is('#bd-qf-save-modal')) {
					$(this).fadeOut(200);
					$('body').removeClass('bd-qf-modal-open');
				}
			});

			// Toggle list checkbox
			$(document).on('change', '.bd-qf-save-list-item input[type="checkbox"]', function() {
				const $checkbox = $(this);
				const $item = $checkbox.closest('.bd-qf-save-list-item');
				const $modal = $checkbox.closest('.bd-qf-save-modal');
				const businessId = $modal.data('business-id');
				const listId = $checkbox.data('list-id');
				const isChecked = $checkbox.is(':checked');

				$item.toggleClass('bd-in-list', isChecked);

				// Update via REST API
				// POST /lists/{id}/items to add, DELETE /lists/{id}/items/{bid} to remove
				const url = isChecked 
					? bdQuickFilters.restUrl + 'lists/' + listId + '/items'
					: bdQuickFilters.restUrl + 'lists/' + listId + '/items/' + businessId;
				
				const ajaxConfig = {
					url: url,
					method: isChecked ? 'POST' : 'DELETE',
					headers: { 'X-WP-Nonce': bdQuickFilters.nonce },
					success: function() {
						self.showToast(isChecked ? 'Added to list!' : 'Removed from list');
						// Update heart icon based on whether business is in ANY list
						self.updateHeartIcon(businessId);
					},
					error: function() {
						self.showToast('Error updating list', 'error');
						$checkbox.prop('checked', !isChecked);
						$item.toggleClass('bd-in-list', !isChecked);
					}
				};

				// Add business_id for POST request
				if (isChecked) {
					ajaxConfig.data = { business_id: businessId };
				}

				$.ajax(ajaxConfig);
			});

			// Create new list
			$(document).on('click', '.bd-qf-save-create-btn', function() {
				const $modal = $(this).closest('.bd-qf-save-modal');
				const $input = $modal.find('.bd-qf-save-new-list');
				const listTitle = $input.val().trim();
				const businessId = $modal.data('business-id');

				if (!listTitle) {
					$input.focus();
					return;
				}

				const $btn = $(this);
				$btn.prop('disabled', true).text('Creating...');

				// Use quick-save endpoint to create list and add business in one call
				$.ajax({
					url: bdQuickFilters.restUrl + 'lists/quick-save',
					method: 'POST',
					headers: { 'X-WP-Nonce': bdQuickFilters.nonce },
					data: {
						business_id: businessId,
						new_list: listTitle
					},
					success: function(response) {
						$input.val('');
						const $listsContainer = $modal.find('.bd-qf-save-lists');
						$listsContainer.find('.bd-qf-save-empty').remove();
						
						const newList = response.list || response;
						$listsContainer.append(`
							<label class="bd-qf-save-list-item bd-in-list">
								<input type="checkbox" data-list-id="${newList.id}" checked>
								<span class="bd-qf-save-list-title">${self.escapeHtml(newList.title)}</span>
								<span class="bd-qf-save-list-count">1 items</span>
							</label>
						`);
						
						// Update heart icon
						self.updateHeartIcon(businessId);
						
						self.showToast('List created and saved!');
					},
					error: function(xhr) {
						self.showToast(xhr.responseJSON?.message || 'Error creating list', 'error');
					},
					complete: function() {
						$btn.prop('disabled', false).text('Create');
					}
				});
			});
		},

		/**
		 * Show a toast notification
		 */
		showToast: function (message) {
			// Remove existing toast
			$('.bd-qf-toast').remove();

			const $toast = $('<div class="bd-qf-toast">' + this.escapeHtml(message) + '</div>');
			$('body').append($toast);

			setTimeout(function () {
				$toast.addClass('show');
			}, 10);

			setTimeout(function () {
				$toast.removeClass('show');
				setTimeout(function () {
					$toast.remove();
				}, 300);
			}, 2500);
		},

		/**
		 * Initialize Leaflet map
		 */
		initMap: function () {
			const self = this;
			const $mapContainer = $('#bd-qf-map');

			if (!$mapContainer.length || typeof L === 'undefined') {
				return;
			}

			// Default center (Tri-Valley area)
			const defaultLat = 37.6819;
			const defaultLng = -121.7680;

			this.map = L.map('bd-qf-map').setView([defaultLat, defaultLng], 11);

			L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
				attribution: '© OpenStreetMap contributors'
			}).addTo(this.map);

			// Try to get user location
			if (navigator.geolocation) {
				navigator.geolocation.getCurrentPosition(
					function (position) {
						self.state.lat = position.coords.latitude;
						self.state.lng = position.coords.longitude;
						self.map.setView([self.state.lat, self.state.lng], 12);
						self.loadBusinesses();
					},
					function () {
						// Use default location
					}
				);
			}
		},

		/**
		 * Update map markers
		 */
		updateMapMarkers: function (businesses) {
			const self = this;

			// Check if map exists first
			if (!this.map) {
				return;
			}

			// Clear existing markers/cluster
			if (this.markerCluster) {
				this.map.removeLayer(this.markerCluster);
			}
			this.markers.forEach(function (marker) {
				self.map.removeLayer(marker);
			});
			this.markers = [];

			if (!businesses || !businesses.length) {
				return;
			}

			const bounds = [];

			// Check if MarkerClusterGroup is available
			const useCluster = typeof L.markerClusterGroup === 'function';
			if (useCluster) {
				this.markerCluster = L.markerClusterGroup({
					iconCreateFunction: function(cluster) {
						const count = cluster.getChildCount();
						let size = 'small';
						if (count > 10) size = 'medium';
						if (count > 25) size = 'large';
						
						return L.divIcon({
							html: '<div class="bd-cluster bd-cluster-' + size + '"><span>' + count + '</span></div>',
							className: 'bd-cluster-icon',
							iconSize: [size === 'small' ? 36 : size === 'medium' ? 44 : 52, size === 'small' ? 36 : size === 'medium' ? 44 : 52]
						});
					},
					maxClusterRadius: 50,
					spiderfyOnMaxZoom: true,
					showCoverageOnHover: false
				});
			}

			businesses.forEach(function (business) {
				// Location data is nested in business.location object
				const loc = business.location;
				if (loc && loc.lat && loc.lng) {
					// Create custom heart icon
					const heartIcon = L.divIcon({
						className: 'bd-heart-icon',
						html: '<div class="bd-heart-marker">' +
							'<svg viewBox="0 0 24 24" fill="currentColor">' +
							'<path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>' +
							'</svg>' +
							'</div>',
						iconSize: [32, 32],
						iconAnchor: [16, 32],
						popupAnchor: [0, -32]
					});

					const marker = L.marker([loc.lat, loc.lng], { icon: heartIcon })
						.bindPopup(self.createPopupContent(business), {
							maxWidth: 250,
							minWidth: 200,
							className: 'bd-qf-map-popup'
						});

					// Store business ID on marker for hover interactions
					marker.businessId = business.id;

					// Hover interactions (highlight corresponding card)
					marker.on('mouseover', function() {
						$('.bd-qf-card[data-business-id="' + business.id + '"]').addClass('bd-qf-card-highlight');
					});
					marker.on('mouseout', function() {
						$('.bd-qf-card[data-business-id="' + business.id + '"]').removeClass('bd-qf-card-highlight');
					});

					if (useCluster) {
						self.markerCluster.addLayer(marker);
					} else {
						marker.addTo(self.map);
					}

					self.markers.push(marker);
					bounds.push([loc.lat, loc.lng]);
				}
			});

			// Add cluster to map
			if (useCluster && this.markerCluster) {
				this.map.addLayer(this.markerCluster);
			}

			// Fit map to markers
			if (bounds.length > 0) {
				try {
					self.map.fitBounds(bounds, { padding: [50, 50], maxZoom: 14 });
				} catch (e) {
				}
			}
		},

		/**
		 * Create popup content for map marker
		 */
		createPopupContent: function (business) {
			let html = '<div class="bd-qf-popup" style="min-width:180px;padding:8px 4px;">';
			html += '<div style="font-weight:700;font-size:14px;margin-bottom:6px;line-height:1.3;">' + this.escapeHtml(this.decodeHtml(business.title)) + '</div>';
			if (business.rating > 0) {
				html += '<div style="font-size:13px;color:#666;margin-bottom:8px;">' + business.rating + ' ★ (' + (business.review_count || 0) + ' reviews)</div>';
			}
			if (business.location && business.location.city) {
				html += '<div style="font-size:12px;color:#888;margin-bottom:8px;"><i class="fas fa-map-marker-alt" style="color:#2CB1BC;margin-right:4px;"></i>' + this.escapeHtml(business.location.city) + '</div>';
			}
			html += '<a href="' + this.escapeHtml(business.permalink) + '" style="display:inline-block;padding:8px 16px;background:#1a3a4a;color:#fff;border-radius:6px;font-size:13px;font-weight:600;text-decoration:none;">View Details</a>';
			html += '</div>';
			return html;
		},

		/**
		 * Load businesses via REST API
		 */
		loadBusinesses: function () {
			const self = this;
			const $container = $('#bd-qf-businesses');

			$container.html('<p class="bd-qf-loading"><i class="fas fa-spinner fa-spin"></i> Loading...</p>');

			// Build query params
			const params = {
				per_page: 20,
				page: this.state.page
			};

			if (this.state.category) {
				params.categories = this.state.category;
			}

			if (this.state.area) {
				params.areas = this.state.area;
			}

			if (this.state.tags.length > 0) {
				params.tags = this.state.tags.join(',');
			}

			if (this.state.priceLevel.length > 0) {
				params.price_level = this.state.priceLevel.join(',');
			}

			if (this.state.minRating) {
				params.min_rating = this.state.minRating;
			}

			if (this.state.openNow) {
				params.open_now = 1;
			}

			if (this.state.search) {
				params.q = this.state.search;
			}

			if (this.state.lat && this.state.lng) {
				params.lat = this.state.lat;
				params.lng = this.state.lng;
			}

			// Handle sorting
			if (this.state.sort === 'featured') {
				params.sort = 'name'; // Default sort, we'll reorder client-side
			} else {
				params.sort = this.state.sort;
			}

			// Make API request
			$.ajax({
				url: bdQuickFilters.restUrl + 'businesses',
				method: 'GET',
				data: params,
				beforeSend: function (xhr) {
					xhr.setRequestHeader('X-WP-Nonce', bdQuickFilters.nonce);
				},
				success: function (response) {
					let businesses = response.businesses || response || [];

					// Featured first sorting
					if (self.state.sort === 'featured' && self.data.featuredIds && self.data.featuredIds.length > 0) {
						businesses = self.sortFeaturedFirst(businesses);
					}

					self.currentBusinesses = businesses;
					self.renderBusinesses(businesses);
					self.updateResultsCount(response.total || businesses.length);
					self.updateMapMarkers(businesses);
					
					// Update URL with current filters
					self.updateUrl();
				},
				error: function (xhr, status, error) {
					console.error('Failed to load businesses:', error);
					$container.html('<div class="bd-qf-no-results"><i class="fas fa-exclamation-circle"></i><p>Failed to load businesses. Please try again.</p></div>');
				}
			});
		},

		/**
		 * Sort businesses with featured first
		 */
		sortFeaturedFirst: function (businesses) {
			const featuredIds = this.data.featuredIds || [];

			if (!featuredIds.length) {
				return businesses;
			}

			const featured = [];
			const regular = [];

			businesses.forEach(function (business) {
				const index = featuredIds.indexOf(business.id);
				if (index !== -1) {
					business._featuredIndex = index;
					featured.push(business);
				} else {
					regular.push(business);
				}
			});

			// Sort featured by their order in featuredIds
			featured.sort(function (a, b) {
				return a._featuredIndex - b._featuredIndex;
			});

			return featured.concat(regular);
		},

		/**
		 * Render businesses
		 */
		renderBusinesses: function (businesses) {
			const $container = $('#bd-qf-businesses');

			if (!businesses || businesses.length === 0) {
				$container.html(
					'<div class="bd-qf-no-results">' +
					'<i class="fas fa-search"></i>' +
					'<p>' + bdQuickFilters.strings.noResults + '</p>' +
					'</div>'
				);
				return;
			}

			this.currentBusinesses = businesses;
			this.renderView();
		},

		/**
		 * Render current view (grid, list, or split)
		 */
		renderView: function () {
			const $container = $('#bd-qf-businesses');
			const businesses = this.currentBusinesses || [];

			if (!businesses.length) {
				return;
			}

			// Remove old view classes
			$container.removeClass('bd-qf-view-grid bd-qf-view-list bd-qf-view-split');
			
			// In split view, we use list-style cards but don't add the class
			// The split view CSS handles the card layout
			if (this.state.view === 'split') {
				$container.addClass('bd-qf-view-split');
			} else {
				$container.addClass('bd-qf-view-' + this.state.view);
			}

			let html = '';
			const self = this;

			businesses.forEach(function (business) {
				html += self.renderCard(business);
			});

			$container.html(html);

			// Trigger lists.js to bind events to new save buttons
			// Some implementations use reinit, others use init, others use event delegation
			if (typeof bdLists !== 'undefined') {
				if (typeof bdLists.reinit === 'function') {
					bdLists.reinit();
				} else if (typeof bdLists.init === 'function') {
					bdLists.init();
				}
			}
			// Also trigger a custom event that lists.js might listen for
			$(document).trigger('bd:cards-rendered');
		},

		/**
		 * Render a single business card
		 */
		renderCard: function (business) {
			const self = this;
			const tags = business.tags || [];
			const tagsHtml = tags.slice(0, 3).map(function (tag) {
				return '<span class="bd-qf-card-tag">' + self.escapeHtml(self.decodeHtml(tag.name)) + '</span>';
			}).join('');

			const isFeatured = this.data.featuredIds && this.data.featuredIds.indexOf(business.id) !== -1;

			let html = '<article class="bd-qf-card' + (isFeatured ? ' bd-qf-featured' : '') + '" data-business-id="' + business.id + '">';

			// Image section
			html += '<div class="bd-qf-card-image"';
			if (business.featured_image) {
				html += '>';
				html += '<img src="' + this.escapeHtml(business.featured_image) + '" alt="' + this.escapeHtml(this.decodeHtml(business.title)) + '" loading="lazy">';
			} else {
				html += ' style="background: linear-gradient(135deg, #1a3a4a 0%, #0f2a3a 100%);">';
			}

			// Distance badge
			if (business.distance && business.distance.display) {
				html += '<span class="bd-qf-card-distance">' + this.escapeHtml(business.distance.display) + '</span>';
			}

			// Save button (heart icon)
			html += '<div class="bd-qf-card-save">';
			html += this.renderSaveButton(business.id);
			html += '</div>';

			html += '</div>'; // End image

			// Content section
			html += '<div class="bd-qf-card-content">';

			// Title
			html += '<h3 class="bd-qf-card-title">';
			html += '<a href="' + this.escapeHtml(business.permalink) + '">' + this.escapeHtml(this.decodeHtml(business.title)) + '</a>';
			html += '</h3>';

			// Rating
			html += '<div class="bd-qf-card-rating">';
			if (business.rating > 0) {
				html += '<div class="bd-qf-card-stars">' + this.renderStars(business.rating) + '</div>';
				html += '<span class="bd-qf-card-rating-value">' + business.rating + '</span>';
				html += '<span class="bd-qf-card-review-count">(' + (business.review_count || 0) + ')</span>';
			} else {
				html += '<span class="bd-qf-card-no-reviews">No reviews yet</span>';
			}
			html += '</div>';

			// Category
			if (business.categories && business.categories.length > 0) {
				html += '<span class="bd-qf-card-category">' + this.escapeHtml(this.decodeHtml(business.categories[0])) + '</span>';
			}

			// Location
			if (business.areas && business.areas.length > 0) {
				html += '<div class="bd-qf-card-location">';
				html += '<i class="fas fa-map-marker-alt"></i> ';
				html += this.escapeHtml(this.decodeHtml(business.areas[0]));
				html += '</div>';
			}

			// Excerpt/Description (shows in list view)
			if (business.excerpt) {
				const cleanExcerpt = business.excerpt.replace(/<[^>]*>/g, '').substring(0, 150);
				html += '<p class="bd-qf-card-excerpt">' + this.escapeHtml(this.decodeHtml(cleanExcerpt));
				if (business.excerpt.length > 150) {
					html += '...';
				}
				html += '</p>';
			}

			// Tags
			if (tagsHtml) {
				html += '<div class="bd-qf-card-tags">' + tagsHtml + '</div>';
			}

			html += '</div>'; // End content

			// CTA - outside content div for grid layout
			html += '<a href="' + this.escapeHtml(business.permalink) + '" class="bd-qf-card-cta">';
			html += 'View Details <i class="fas fa-arrow-right"></i>';
			html += '</a>';

			html += '</article>';

			return html;
		},

		/**
		 * Update heart icon based on list membership
		 */
		updateHeartIcon: function(businessId) {
			const $wrapper = $('.bd-save-wrapper[data-business-id="' + businessId + '"]');
			const hasAnyList = $('#bd-qf-save-modal .bd-qf-save-list-item.bd-in-list').length > 0;
			$wrapper.find('.bd-save-btn i').toggleClass('far', !hasAnyList).toggleClass('fas', hasAnyList);
		},

		/**
		 * Render save/heart button
		 * Uses the same markup as lists.js expects so it can bind its event handlers
		 */
		renderSaveButton: function (businessId) {
			// Output the standard bd-save-wrapper markup that lists.js expects
			// lists.js will handle all the modal logic when user clicks
			let html = '<div class="bd-save-wrapper" data-business-id="' + businessId + '">';
			html += '<button type="button" class="bd-save-btn" title="Save to List">';
			html += '<i class="far fa-heart"></i>';
			html += '</button>';
			html += '</div>';

			return html;
		},

		/**
		 * Render star rating SVGs
		 */
		renderStars: function (rating) {
			let html = '';
			const fullStars = Math.floor(rating);
			const hasHalf = rating - fullStars >= 0.5;

			for (let i = 1; i <= 5; i++) {
				if (i <= fullStars) {
					// Full star (teal)
					html += '<svg width="14" height="14" viewBox="0 0 24 24" fill="#2CB1BC"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>';
				} else if (i === fullStars + 1 && hasHalf) {
					// Half star - generate unique ID once and reuse
					const halfId = 'half-' + Math.random().toString(36).substr(2, 9);
					html += '<svg width="14" height="14" viewBox="0 0 24 24"><defs><linearGradient id="' + halfId + '"><stop offset="50%" stop-color="#2CB1BC"/><stop offset="50%" stop-color="#d1d5db"/></linearGradient></defs><path fill="url(#' + halfId + ')" d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>';
				} else {
					// Empty star
					html += '<svg width="14" height="14" viewBox="0 0 24 24" fill="#d1d5db"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>';
				}
			}

			return html;
		},

		/**
		 * Update results count display
		 */
		updateResultsCount: function (count) {
			const text = count === 1
				? bdQuickFilters.strings.resultCount
				: bdQuickFilters.strings.resultsCount.replace('%d', count);

			$('#bd-qf-results-count').text(text);
		},

		/**
		 * Update active filters display
		 */
		updateActiveFilters: function () {
			const $container = $('#bd-qf-active-filters');
			const $clearAll = $('#bd-qf-clear-all');
			let html = '';
			let filterCount = 0;

			// Category filter
			if (this.state.category) {
				const category = this.data.categories.find(function (c) {
					return c.id === this.state.category;
				}.bind(this));
				if (category) {
					html += this.renderFilterPill('category', this.state.category, category.name);
					filterCount++;
				}
			}

			// Area filter
			if (this.state.area) {
				const area = this.data.areas.find(function (a) {
					return a.id === this.state.area;
				}.bind(this));
				if (area) {
					html += this.renderFilterPill('area', this.state.area, area.name);
					filterCount++;
				}
			}

			// Tag filters
			this.state.tags.forEach(function (tagId) {
				const tag = this.data.tags.find(function (t) {
					return t.id === tagId;
				});
				if (tag) {
					html += this.renderFilterPill('tag', tagId, tag.name);
					filterCount++;
				}
			}.bind(this));

			// Price level filters
			this.state.priceLevel.forEach(function (price) {
				html += this.renderFilterPill('price', price, price);
				filterCount++;
			}.bind(this));

			// Rating filter
			if (this.state.minRating) {
				html += this.renderFilterPill('rating', this.state.minRating, this.state.minRating + '+ Stars');
				filterCount++;
			}

			// Open now filter
			if (this.state.openNow) {
				html += this.renderFilterPill('openNow', true, 'Open Now');
				filterCount++;
			}

			$container.html(html);

			// Show/hide clear all button
			if (filterCount > 0) {
				$clearAll.show().find('.bd-qf-filter-count').text(filterCount);
			} else {
				$clearAll.hide();
			}
		},

		/**
		 * Render a filter pill
		 * Note: label comes from our data which is already sanitized by PHP
		 */
		renderFilterPill: function (type, value, label) {
			// Create text node to safely decode any HTML entities from API
			const temp = document.createElement('textarea');
			temp.innerHTML = label;
			const decodedLabel = temp.value;
			
			return '<span class="bd-qf-active-pill" data-filter-type="' + type + '" data-filter-value="' + value + '">' +
				'<span class="bd-qf-pill-label">' + this.escapeHtml(decodedLabel) + '</span>' +
				'<button type="button" class="bd-qf-pill-close"><i class="fas fa-times"></i></button>' +
				'</span>';
		},

		/**
		 * Remove a single filter
		 */
		removeFilter: function (type, value) {
			switch (type) {
				case 'category':
					this.state.category = null;
					$('.bd-qf-experience-btn').removeClass('active');
					this.showAllTags(); // Reset tag visibility
					break;

				case 'area':
					this.state.area = null;
					$('#bd-qf-area').val('');
					break;

				case 'tag':
					this.state.tags = this.state.tags.filter(function (t) {
						return t !== parseInt(value, 10);
					});
					$('.bd-qf-tag-btn[data-tag-id="' + value + '"]').removeClass('active');
					break;

				case 'price':
					this.state.priceLevel = this.state.priceLevel.filter(function (p) {
						return p !== value;
					});
					$('.bd-qf-price-btn[data-price="' + value + '"]').removeClass('active');
					break;

				case 'rating':
					this.state.minRating = null;
					$('.bd-qf-rating-btn').removeClass('active');
					break;

				case 'openNow':
					this.state.openNow = false;
					$('#bd-qf-open-now').prop('checked', false);
					break;
			}

			// Update "More Filters" button state
			const hasModalFilters = this.state.priceLevel.length > 0 || this.state.minRating || this.state.openNow;
			$('#bd-qf-more-filters').toggleClass('has-filters', hasModalFilters);

			this.state.page = 1;
			this.loadBusinesses();
			this.updateActiveFilters();
		},

		/**
		 * Clear all filters
		 */
		clearAllFilters: function () {
			// Reset state
			this.state.category = null;
			this.state.area = null;
			this.state.tags = [];
			this.state.priceLevel = [];
			this.state.minRating = null;
			this.state.openNow = false;
			this.state.search = '';
			this.state.page = 1;

			// Reset UI
			$('.bd-qf-experience-btn').removeClass('active');
			$('.bd-qf-tag-btn').removeClass('active');
			$('.bd-qf-price-btn').removeClass('active');
			$('.bd-qf-rating-btn').removeClass('active');
			$('#bd-qf-area').val('');
			$('#bd-qf-search').val('');
			$('#bd-qf-open-now').prop('checked', false);
			$('#bd-qf-more-filters').removeClass('has-filters');

			// Show all tags again (reset category filtering)
			this.showAllTags();

			this.loadBusinesses();
			this.updateActiveFilters();
		},

		/**
		 * Filter and reorder tags based on selected category
		 * Hides tags that aren't associated with businesses in the category
		 *
		 * @param {number} categoryId - Category ID to filter by
		 */
		filterTagsForCategory: function (categoryId) {
			const self = this;
			const $tagsList = $('#bd-qf-tags-list');
			const $tagsRow = $('.bd-qf-tags-row');

			// Abort any pending request to prevent race conditions
			if (this.tagFilterRequest && this.tagFilterRequest.readyState !== 4) {
				this.tagFilterRequest.abort();
			}

			// Add loading state
			$tagsRow.addClass('bd-qf-tags-loading');

			// Get tags for this category via AJAX
			this.tagFilterRequest = $.ajax({
				url: bdQuickFilters.ajaxUrl,
				method: 'POST',
				data: {
					action: 'bd_get_category_tags',
					category_id: categoryId,
					nonce: bdQuickFilters.nonce
				},
				success: function (response) {
					$tagsRow.removeClass('bd-qf-tags-loading');

					if (response.success && response.data.tags) {
						// Build Set of tag IDs for O(1) lookup
						const categoryTagIds = response.data.tags.map(function (t) {
							return t.id;
						});
						const categoryTagSet = new Set(categoryTagIds);

						const $buttons = $tagsList.find('.bd-qf-tag-btn');
						let visibleCount = 0;
						const tagsToDeselect = [];

						// First pass: collect data without DOM manipulation
						const buttonData = $buttons.map(function () {
							const $btn = $(this);
							const tagId = parseInt($btn.data('tag-id'), 10);
							const isInCategory = categoryTagSet.has(tagId);
							const isActive = $btn.hasClass('active');

							if (!isInCategory && isActive) {
								tagsToDeselect.push(tagId);
							}
							if (isInCategory) {
								visibleCount++;
							}

							return {
								element: this,
								$btn: $btn,
								tagId: tagId,
								isInCategory: isInCategory,
								sortIndex: isInCategory ? categoryTagIds.indexOf(tagId) : 999999
							};
						}).get();

						// Batch DOM updates: update classes
						buttonData.forEach(function (item) {
							if (item.isInCategory) {
								item.$btn.removeClass('bd-qf-tag-category-hidden');
							} else {
								item.$btn.addClass('bd-qf-tag-category-hidden').removeClass('active');
							}
						});

						// Update state for deselected tags
						if (tagsToDeselect.length > 0) {
							self.state.tags = self.state.tags.filter(function (t) {
								return tagsToDeselect.indexOf(t) === -1;
							});
						}

						// Sort visible tags by category relevance
						const sorted = buttonData
							.filter(function (item) { return item.isInCategory; })
							.sort(function (a, b) { return a.sortIndex - b.sortIndex; });

						// Batch DOM reorder using document fragment
						const fragment = document.createDocumentFragment();
						sorted.forEach(function (item) {
							fragment.appendChild(item.element);
						});
						$tagsList[0].appendChild(fragment);

						// Update the "+X more" button count
						self.updateTagsMoreButton();

						// Show message if no tags match this category
						self.updateNoTagsMessage(visibleCount === 0);
					}
				},
				error: function (xhr, status) {
					// Don't show error for aborted requests
					if (status !== 'abort') {
						$tagsRow.removeClass('bd-qf-tags-loading');
						console.error('Failed to fetch category tags');
					}
				}
			});
		},

		/**
		 * Show all tags (reset category filtering)
		 * Called when user deselects a category
		 */
		showAllTags: function () {
			const $tagsList = $('#bd-qf-tags-list');

			// Abort any pending category filter request
			if (this.tagFilterRequest && this.tagFilterRequest.readyState !== 4) {
				this.tagFilterRequest.abort();
			}

			// Remove loading state if present
			$('.bd-qf-tags-row').removeClass('bd-qf-tags-loading');

			// Remove category-hidden class from all tags
			$tagsList.find('.bd-qf-tag-btn').removeClass('bd-qf-tag-category-hidden');

			// Remove "no tags" message if present
			this.updateNoTagsMessage(false);

			// Recalculate the "+X more" button
			this.updateTagsMoreButton();
		},

		/**
		 * Update the "+X more" button based on visible (non-category-hidden) tags
		 */
		updateTagsMoreButton: function () {
			const $tagsList = $('#bd-qf-tags-list');
			const $moreBtn = $('#bd-qf-tags-more');
			const $tagsRow = $('.bd-qf-tags-row');

			// Get only tags that aren't hidden by category filter
			const $visibleTags = $tagsList.find('.bd-qf-tag-btn').not('.bd-qf-tag-category-hidden');
			const maxVisible = 8; // Number of tags to show before collapsing

			let hiddenByLimit = 0;

			// Apply the 8-tag visibility limit
			$visibleTags.each(function (index) {
				const $btn = $(this);
				if (index >= maxVisible) {
					// Hide due to limit (but keep in DOM)
					$btn.addClass('bd-qf-tag-hidden');
					hiddenByLimit++;
				} else {
					// Show (within limit)
					$btn.removeClass('bd-qf-tag-hidden');
				}
			});

			// Update or hide the "more" button
			if (hiddenByLimit > 0) {
				$moreBtn.show().text('+' + hiddenByLimit + ' more');
			} else {
				$moreBtn.hide();
			}

			// If tags are expanded, make sure to show all visible tags
			if ($tagsRow.hasClass('expanded')) {
				$visibleTags.removeClass('bd-qf-tag-hidden');
				$moreBtn.text('Show Less');
			}
		},

		/**
		 * Show/hide the "no tags available" message
		 *
		 * @param {boolean} show - Whether to show the message
		 */
		updateNoTagsMessage: function (show) {
			const $tagsList = $('#bd-qf-tags-list');
			const $existingMsg = $tagsList.find('.bd-qf-no-tags-message');

			if (show) {
				if (!$existingMsg.length) {
					$tagsList.append(
						'<span class="bd-qf-no-tags-message">' +
						'<i class="fas fa-info-circle"></i> ' +
						'No tags for this category' +
						'</span>'
					);
				}
			} else {
				$existingMsg.remove();
			}
		},

		/**
		 * Decode HTML entities (for API data that may contain encoded chars)
		 */
		decodeHtml: function (text) {
			if (!text) return '';
			const temp = document.createElement('textarea');
			temp.innerHTML = text;
			return temp.value;
		},

		/**
		 * Escape HTML to prevent XSS
		 */
		escapeHtml: function (text) {
			if (!text) return '';
			const div = document.createElement('div');
			div.textContent = text;
			return div.innerHTML;
		}
	};

	/**
	 * Initialize on document ready
	 */
	$(document).ready(function () {
		if ($('.bd-qf-wrapper').length) {
			QuickFilters.init();
		}
	});

})(jQuery);
