/**
 * Edit Listing Frontend JavaScript
 * Handles form submission, photo uploads, drag/drop, and validation
 */

(function($) {
    'use strict';

    const EditListing = {
        form: null,
        businessId: null,
        isDirty: false,
        photoGrid: null,
        maxPhotos: 10,

        /**
         * Initialize
         */
        init: function() {
            this.form = $('#bd-edit-listing-form');
            if (!this.form.length) return;

            this.businessId = this.form.data('business-id');
            this.photoGrid = $('#bd-photo-grid');

            this.bindEvents();
            this.initDragDrop();
            this.initHoursToggle();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            const self = this;

            // Form submission
            this.form.on('submit', function(e) {
                e.preventDefault();
                self.submitForm();
            });

            // Track changes
            this.form.on('change input', 'input, textarea, select', function() {
                self.isDirty = true;
            });

            // Warn on page leave
            $(window).on('beforeunload', function() {
                if (self.isDirty) {
                    return bdEditListing.i18n.confirmLeave;
                }
            });

            // Photo add button
            $('#bd-photo-add').on('click', function() {
                self.openMediaLibrary();
            });

            // Photo remove
            this.photoGrid.on('click', '.bd-photo-remove', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).closest('.bd-photo-item').remove();
                self.updatePhotoOrder();
                self.isDirty = true;
            });
        },

        /**
         * Initialize hours toggle (closed checkbox)
         */
        initHoursToggle: function() {
            $('.bd-hours-closed input[type="checkbox"]').on('change', function() {
                const times = $(this).closest('.bd-hours-inputs').find('.bd-hours-times');
                if ($(this).is(':checked')) {
                    times.addClass('bd-hidden');
                } else {
                    times.removeClass('bd-hidden');
                }
            });
        },

        /**
         * Initialize drag and drop for photos
         */
        initDragDrop: function() {
            const self = this;
            let draggedItem = null;

            this.photoGrid.on('dragstart', '.bd-photo-item', function(e) {
                draggedItem = this;
                $(this).addClass('bd-dragging');
                e.originalEvent.dataTransfer.effectAllowed = 'move';
            });

            this.photoGrid.on('dragend', '.bd-photo-item', function() {
                $(this).removeClass('bd-dragging');
                draggedItem = null;
                self.updatePhotoOrder();
            });

            this.photoGrid.on('dragover', '.bd-photo-item', function(e) {
                e.preventDefault();
                e.originalEvent.dataTransfer.dropEffect = 'move';
            });

            this.photoGrid.on('dragenter', '.bd-photo-item', function(e) {
                e.preventDefault();
                if (this !== draggedItem) {
                    $(this).addClass('bd-drag-over');
                }
            });

            this.photoGrid.on('dragleave', '.bd-photo-item', function() {
                $(this).removeClass('bd-drag-over');
            });

            this.photoGrid.on('drop', '.bd-photo-item', function(e) {
                e.preventDefault();
                $(this).removeClass('bd-drag-over');

                if (draggedItem && this !== draggedItem) {
                    const items = self.photoGrid.children('.bd-photo-item');
                    const fromIndex = items.index(draggedItem);
                    const toIndex = items.index(this);

                    if (fromIndex < toIndex) {
                        $(this).after(draggedItem);
                    } else {
                        $(this).before(draggedItem);
                    }

                    self.updatePhotoOrder();
                    self.isDirty = true;
                }
            });

            // Make items draggable
            this.photoGrid.find('.bd-photo-item').attr('draggable', true);
        },

        /**
         * Update photo order after drag/drop
         */
        updatePhotoOrder: function() {
            const items = this.photoGrid.children('.bd-photo-item');

            // Update featured badge
            items.find('.bd-photo-badge').remove();
            if (items.length > 0) {
                items.first().prepend('<span class="bd-photo-badge">' + 'Featured' + '</span>');
            }
        },

        /**
         * Open WordPress media library
         */
        openMediaLibrary: function() {
            const self = this;
            const currentCount = this.photoGrid.children('.bd-photo-item').length;

            if (currentCount >= this.maxPhotos) {
                this.showToast(bdEditListing.i18n.maxPhotos, 'error');
                return;
            }

            const frame = wp.media({
                title: 'Select Photos',
                button: { text: 'Add Photos' },
                multiple: true,
                library: { type: 'image' }
            });

            frame.on('select', function() {
                const attachments = frame.state().get('selection').toJSON();
                const remaining = self.maxPhotos - self.photoGrid.children('.bd-photo-item').length;

                attachments.slice(0, remaining).forEach(function(attachment) {
                    self.addPhotoItem(attachment.id, attachment.sizes.medium?.url || attachment.url);
                });

                self.updatePhotoOrder();
                self.isDirty = true;
            });

            frame.open();
        },

        /**
         * Add a photo item to the grid
         */
        addPhotoItem: function(id, url) {
            const html = `
                <div class="bd-photo-item" data-id="${id}" draggable="true">
                    <img src="${url}" alt="">
                    <input type="hidden" name="photos[]" value="${id}">
                    <button type="button" class="bd-photo-remove" data-id="${id}">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                        </svg>
                    </button>
                    <div class="bd-photo-drag-handle">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M11 18c0 1.1-.9 2-2 2s-2-.9-2-2 .9-2 2-2 2 .9 2 2zm-2-8c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0-6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm6 4c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/>
                        </svg>
                    </div>
                </div>
            `;

            $('#bd-photo-add').before(html);
        },

        /**
         * Submit the form
         */
        submitForm: function() {
            const self = this;

            // Validation
            if (!this.validateForm()) {
                return;
            }

            // Get form data
            const formData = new FormData(this.form[0]);
            formData.append('action', 'bd_submit_listing_changes');

            // Sync TinyMCE
            if (typeof tinyMCE !== 'undefined' && tinyMCE.get('bd-description')) {
                tinyMCE.get('bd-description').save();
                formData.set('description', $('#bd-description').val());
            }

            // Disable submit button
            const submitBtns = this.form.find('.bd-submit-changes');
            submitBtns.prop('disabled', true).html(
                '<span class="bd-spinner"></span> ' + bdEditListing.i18n.saving
            );

            $.ajax({
                url: bdEditListing.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        self.isDirty = false;
                        self.showToast(response.data.message, 'success');

                        // Show success notice
                        self.form.prepend(`
                            <div class="bd-edit-notice bd-edit-notice-success">
                                <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M10 0C4.5 0 0 4.5 0 10s4.5 10 10 10 10-4.5 10-10S15.5 0 10 0zm-2 15l-5-5 1.41-1.41L8 12.17l7.59-7.59L17 6l-9 9z"/>
                                </svg>
                                <span>${response.data.message}</span>
                            </div>
                        `);

                        // Scroll to top
                        $('html, body').animate({ scrollTop: self.form.offset().top - 100 }, 500);
                    } else {
                        self.showToast(response.data.message || bdEditListing.i18n.error, 'error');
                    }
                },
                error: function() {
                    self.showToast(bdEditListing.i18n.error, 'error');
                },
                complete: function() {
                    submitBtns.prop('disabled', false).html(
                        '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/></svg> Submit for Review'
                    );
                }
            });
        },

        /**
         * Validate form
         */
        validateForm: function() {
            let isValid = true;

            // Check required title
            const title = this.form.find('#bd-title').val().trim();
            if (!title) {
                this.showToast('Business name is required.', 'error');
                this.form.find('#bd-title').focus();
                return false;
            }

            // Check at least one category
            const categories = this.form.find('input[name="categories[]"]:checked');
            if (categories.length === 0) {
                this.showToast(bdEditListing.i18n.selectCategory, 'error');
                return false;
            }

            return isValid;
        },

        /**
         * Show toast notification
         */
        showToast: function(message, type) {
            const container = $('#bd-edit-messages');
            const toast = $(`<div class="bd-toast bd-toast-${type}">${message}</div>`);

            container.append(toast);

            setTimeout(function() {
                toast.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        EditListing.init();
    });

})(jQuery);
