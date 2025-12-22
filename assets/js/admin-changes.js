/**
 * Admin Change Requests Queue JavaScript
 * Handles approve/reject actions
 */

(function($) {
    'use strict';

    const AdminChanges = {
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            const self = this;

            // Approve button
            $(document).on('click', '.bd-approve-btn', function(e) {
                e.preventDefault();
                const requestId = $(this).data('request-id');
                const card = $(this).closest('.bd-change-card');
                self.approveChanges(requestId, card);
            });

            // Reject button - show modal
            $(document).on('click', '.bd-reject-btn', function(e) {
                e.preventDefault();
                const card = $(this).closest('.bd-change-card');
                card.find('.bd-reject-modal').show();
            });

            // Reject cancel
            $(document).on('click', '.bd-reject-cancel', function() {
                $(this).closest('.bd-reject-modal').hide();
            });

            // Reject confirm
            $(document).on('click', '.bd-reject-confirm', function() {
                const modal = $(this).closest('.bd-reject-modal');
                const card = modal.closest('.bd-change-card');
                const requestId = card.data('request-id');
                const reason = modal.find('.bd-reject-reason').val().trim();

                if (!reason) {
                    alert(bdAdminChanges.i18n.rejectRequired);
                    modal.find('.bd-reject-reason').focus();
                    return;
                }

                modal.hide();
                AdminChanges.rejectChanges(requestId, reason, card);
            });

            // Close modal on escape
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    $('.bd-reject-modal').hide();
                }
            });

            // Close modal on backdrop click
            $(document).on('click', '.bd-reject-modal', function(e) {
                if (e.target === this) {
                    $(this).hide();
                }
            });
        },

        /**
         * Approve changes
         */
        approveChanges: function(requestId, card) {
            const self = this;

            card.addClass('bd-processing');
            card.find('.bd-approve-btn').text(bdAdminChanges.i18n.approving);

            $.ajax({
                url: bdAdminChanges.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bd_approve_changes',
                    nonce: bdAdminChanges.nonce,
                    request_id: requestId
                },
                success: function(response) {
                    if (response.success) {
                        self.showNotice(bdAdminChanges.i18n.approved, 'success');
                        card.addClass('bd-approved');

                        setTimeout(function() {
                            card.remove();
                            self.checkEmpty();
                        }, 500);
                    } else {
                        self.showNotice(response.data.message || bdAdminChanges.i18n.error, 'error');
                        card.removeClass('bd-processing');
                        card.find('.bd-approve-btn').html('<span class="dashicons dashicons-yes"></span> Approve Changes');
                    }
                },
                error: function() {
                    self.showNotice(bdAdminChanges.i18n.error, 'error');
                    card.removeClass('bd-processing');
                    card.find('.bd-approve-btn').html('<span class="dashicons dashicons-yes"></span> Approve Changes');
                }
            });
        },

        /**
         * Reject changes
         */
        rejectChanges: function(requestId, reason, card) {
            const self = this;

            card.addClass('bd-processing');
            card.find('.bd-reject-btn').text(bdAdminChanges.i18n.rejecting);

            $.ajax({
                url: bdAdminChanges.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bd_reject_changes',
                    nonce: bdAdminChanges.nonce,
                    request_id: requestId,
                    reason: reason
                },
                success: function(response) {
                    if (response.success) {
                        self.showNotice(bdAdminChanges.i18n.rejected, 'success');
                        card.addClass('bd-rejected');

                        setTimeout(function() {
                            card.remove();
                            self.checkEmpty();
                        }, 500);
                    } else {
                        self.showNotice(response.data.message || bdAdminChanges.i18n.error, 'error');
                        card.removeClass('bd-processing');
                        card.find('.bd-reject-btn').html('<span class="dashicons dashicons-no"></span> Reject');
                    }
                },
                error: function() {
                    self.showNotice(bdAdminChanges.i18n.error, 'error');
                    card.removeClass('bd-processing');
                    card.find('.bd-reject-btn').html('<span class="dashicons dashicons-no"></span> Reject');
                }
            });
        },

        /**
         * Show admin notice
         */
        showNotice: function(message, type) {
            const noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
            const notice = $(`
                <div class="notice ${noticeClass} is-dismissible">
                    <p>${message}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">Dismiss this notice.</span>
                    </button>
                </div>
            `);

            $('.bd-changes-wrap h1').after(notice);

            // Auto dismiss after 5 seconds
            setTimeout(function() {
                notice.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);

            // Manual dismiss
            notice.find('.notice-dismiss').on('click', function() {
                notice.fadeOut(300, function() {
                    $(this).remove();
                });
            });
        },

        /**
         * Check if list is empty and show empty state
         */
        checkEmpty: function() {
            if ($('.bd-change-card').length === 0) {
                $('.bd-changes-list').html(`
                    <div class="bd-empty-state">
                        <div class="bd-empty-icon">✅</div>
                        <h2>All caught up!</h2>
                        <p>No pending change requests at the moment.</p>
                    </div>
                `);

                // Update stats
                $('.bd-stat-pending .bd-stat-value').text('0');

                // Update menu count badge
                const menuItem = $('a[href*="bd-pending-changes"] .awaiting-mod');
                if (menuItem.length) {
                    menuItem.remove();
                }
            }
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        AdminChanges.init();
    });

})(jQuery);
