/**
 * List Collaborators JavaScript
 * 
 * Handles invite modal, user search, notifications, and join flow.
 * 
 * @package BusinessDirectory
 */

(function($) {
    'use strict';

    // =========================================================================
    // COLLABORATORS MODAL
    // =========================================================================

    const CollaboratorsModal = {
        listId: null,
        inviteUrl: null,
        inviteMode: 'approval',

        init: function() {
            // Open modal button
            $(document).on('click', '.bd-manage-collaborators-btn', this.open.bind(this));
            
            // Close modal
            $(document).on('click', '.bd-collab-modal-close, .bd-collab-modal-overlay', this.close.bind(this));
            
            // Tab switching
            $(document).on('click', '.bd-collab-tab', this.switchTab.bind(this));
            
            // Copy invite link
            $(document).on('click', '.bd-copy-invite-link', this.copyInviteLink.bind(this));
            
            // Share buttons
            $(document).on('click', '.bd-invite-share-btn', this.shareInvite.bind(this));
            
            // Regenerate link
            $(document).on('click', '.bd-regenerate-link', this.regenerateLink.bind(this));
            
            // Invite mode toggle
            $(document).on('change', 'input[name="invite_mode"]', this.updateInviteMode.bind(this));
            
            // User search
            $(document).on('input', '.bd-user-search-input', this.debounce(this.searchUsers.bind(this), 300));
            
            // Add collaborator from search
            $(document).on('click', '.bd-add-user-btn', this.addCollaborator.bind(this));
            
            // Add recent collaborator
            $(document).on('click', '.bd-recent-collab-btn', this.addRecentCollaborator.bind(this));
            
            // Remove collaborator
            $(document).on('click', '.bd-remove-collab-btn', this.removeCollaborator.bind(this));
            
            // Approve request
            $(document).on('click', '.bd-approve-request-btn', this.approveRequest.bind(this));
            
            // Deny request
            $(document).on('click', '.bd-deny-request-btn', this.denyRequest.bind(this));
        },

        open: function(e) {
            e.preventDefault();
            this.listId = $(e.currentTarget).data('list-id');
            
            // Show modal
            this.renderModal();
            $('.bd-collab-modal').addClass('bd-modal-active');
            $('body').addClass('bd-modal-open');
            
            // Load data
            this.loadCollaborators();
            this.loadInviteLink();
            this.loadRecentCollaborators();
        },

        close: function(e) {
            // Close when clicking overlay, close button, or icon inside close button
            const $target = $(e.target);
            if ($target.hasClass('bd-collab-modal-overlay') || 
                $target.hasClass('bd-collab-modal-close') ||
                $target.closest('.bd-collab-modal-close').length > 0) {
                $('.bd-collab-modal').removeClass('bd-modal-active');
                $('body').removeClass('bd-modal-open');
            }
        },

        renderModal: function() {
            // Remove existing modal
            $('.bd-collab-modal').remove();
            
            const modal = `
                <div class="bd-collab-modal">
                    <div class="bd-collab-modal-overlay"></div>
                    <div class="bd-collab-modal-content">
                        <button class="bd-collab-modal-close" aria-label="Close">
                            <i class="fas fa-times"></i>
                        </button>
                        
                        <h2 class="bd-collab-modal-title">
                            <i class="fas fa-user-plus"></i> Invite Collaborators
                        </h2>
                        
                        <!-- Tabs -->
                        <div class="bd-collab-tabs">
                            <button class="bd-collab-tab active" data-tab="invite">
                                <i class="fas fa-link"></i> Invite Link
                            </button>
                            <button class="bd-collab-tab" data-tab="add">
                                <i class="fas fa-user-plus"></i> Add Member
                            </button>
                            <button class="bd-collab-tab" data-tab="manage">
                                <i class="fas fa-users"></i> Manage
                                <span class="bd-collab-count"></span>
                            </button>
                            <button class="bd-collab-tab" data-tab="requests">
                                <i class="fas fa-inbox"></i> Requests
                                <span class="bd-pending-count"></span>
                            </button>
                        </div>
                        
                        <!-- Tab Content -->
                        <div class="bd-collab-tab-content">
                            <!-- Invite Link Tab -->
                            <div class="bd-collab-pane active" data-pane="invite">
                                <p class="bd-collab-description">
                                    Share this link to invite people to collaborate on your list.
                                </p>
                                
                                <div class="bd-invite-link-box">
                                    <input type="text" class="bd-invite-link-input" readonly>
                                    <button class="bd-copy-invite-link" title="Copy link">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                                
                                <div class="bd-invite-share-buttons">
                                    <button class="bd-invite-share-btn" data-platform="sms" title="Text">
                                        <i class="fas fa-comment-sms"></i>
                                    </button>
                                    <button class="bd-invite-share-btn" data-platform="whatsapp" title="WhatsApp">
                                        <i class="fab fa-whatsapp"></i>
                                    </button>
                                    <button class="bd-invite-share-btn" data-platform="messenger" title="Messenger">
                                        <i class="fab fa-facebook-messenger"></i>
                                    </button>
                                    <button class="bd-invite-share-btn" data-platform="email" title="Email">
                                        <i class="fas fa-envelope"></i>
                                    </button>
                                </div>
                                
                                <div class="bd-invite-settings">
                                    <h4><i class="fas fa-cog"></i> Link Settings</h4>
                                    <label class="bd-invite-mode-option">
                                        <input type="radio" name="invite_mode" value="approval" checked>
                                        <span class="bd-radio-custom"></span>
                                        <span>Requests require your approval</span>
                                    </label>
                                    <label class="bd-invite-mode-option">
                                        <input type="radio" name="invite_mode" value="auto">
                                        <span class="bd-radio-custom"></span>
                                        <span>Anyone with link joins automatically</span>
                                    </label>
                                    <label class="bd-invite-mode-option">
                                        <input type="radio" name="invite_mode" value="disabled">
                                        <span class="bd-radio-custom"></span>
                                        <span>Disable link invites</span>
                                    </label>
                                </div>
                                
                                <button class="bd-regenerate-link">
                                    <i class="fas fa-sync-alt"></i> Generate New Link
                                </button>
                                <p class="bd-regenerate-note">This will invalidate the current link.</p>
                            </div>
                            
                            <!-- Add Member Tab -->
                            <div class="bd-collab-pane" data-pane="add">
                                <p class="bd-collab-description">
                                    Search for existing members to add directly.
                                </p>
                                
                                <div class="bd-user-search-box">
                                    <i class="fas fa-search"></i>
                                    <input type="text" class="bd-user-search-input" 
                                           placeholder="Search by name...">
                                </div>
                                
                                <div class="bd-user-search-results"></div>
                                
                                <div class="bd-recent-collaborators">
                                    <h4>Recent Collaborators</h4>
                                    <div class="bd-recent-collab-list"></div>
                                </div>
                            </div>
                            
                            <!-- Manage Tab -->
                            <div class="bd-collab-pane" data-pane="manage">
                                <div class="bd-collaborators-list"></div>
                                <p class="bd-collab-limit-note">
                                    <span class="bd-current-count">0</span> / 10 collaborators
                                </p>
                            </div>
                            
                            <!-- Requests Tab -->
                            <div class="bd-collab-pane" data-pane="requests">
                                <div class="bd-pending-requests-list"></div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            $('body').append(modal);
        },

        switchTab: function(e) {
            const $tab = $(e.currentTarget);
            const tabId = $tab.data('tab');
            
            // Update active tab
            $('.bd-collab-tab').removeClass('active');
            $tab.addClass('active');
            
            // Update active pane
            $('.bd-collab-pane').removeClass('active');
            $(`.bd-collab-pane[data-pane="${tabId}"]`).addClass('active');
        },

        loadInviteLink: function() {
            $.ajax({
                url: bdLists.restUrl + 'lists/' + this.listId + '/invite',
                method: 'GET',
                headers: { 'X-WP-Nonce': bdLists.nonce },
                success: (response) => {
                    this.inviteUrl = response.url;
                    this.inviteMode = response.mode;
                    
                    $('.bd-invite-link-input').val(response.url);
                    $(`input[name="invite_mode"][value="${response.mode}"]`).prop('checked', true);
                },
                error: () => {
                    showToast('Could not load invite link', 'error');
                }
            });
        },

        copyInviteLink: function() {
            const url = $('.bd-invite-link-input').val();
            
            if (navigator.clipboard) {
                navigator.clipboard.writeText(url).then(() => {
                    showToast('Link copied to clipboard!');
                    $('.bd-copy-invite-link i').removeClass('fa-copy').addClass('fa-check');
                    setTimeout(() => {
                        $('.bd-copy-invite-link i').removeClass('fa-check').addClass('fa-copy');
                    }, 2000);
                });
            } else {
                // Fallback
                const $input = $('.bd-invite-link-input');
                $input.select();
                document.execCommand('copy');
                showToast('Link copied!');
            }
        },

        shareInvite: function(e) {
            const platform = $(e.currentTarget).data('platform');
            const url = this.inviteUrl;
            const text = "I'd like to invite you to collaborate on my list!";
            
            let shareUrl;
            
            switch (platform) {
                case 'sms':
                    shareUrl = `sms:?body=${encodeURIComponent(text + ' ' + url)}`;
                    break;
                case 'whatsapp':
                    shareUrl = `https://wa.me/?text=${encodeURIComponent(text + ' ' + url)}`;
                    break;
                case 'messenger':
                    shareUrl = `fb-messenger://share?link=${encodeURIComponent(url)}`;
                    break;
                case 'email':
                    shareUrl = `mailto:?subject=${encodeURIComponent('Collaborate on my list')}&body=${encodeURIComponent(text + '\n\n' + url)}`;
                    break;
            }
            
            if (shareUrl) {
                window.open(shareUrl, '_blank');
            }
        },

        regenerateLink: function() {
            if (!confirm('This will invalidate the current link. Anyone using the old link will need the new one. Continue?')) {
                return;
            }
            
            $.ajax({
                url: bdLists.restUrl + 'lists/' + this.listId + '/invite/regenerate',
                method: 'POST',
                headers: { 'X-WP-Nonce': bdLists.nonce },
                success: (response) => {
                    this.inviteUrl = response.url;
                    $('.bd-invite-link-input').val(response.url);
                    showToast(response.message);
                },
                error: () => {
                    showToast('Could not regenerate link', 'error');
                }
            });
        },

        updateInviteMode: function(e) {
            const mode = $(e.target).val();
            
            $.ajax({
                url: bdLists.restUrl + 'lists/' + this.listId + '/invite/settings',
                method: 'PUT',
                headers: { 'X-WP-Nonce': bdLists.nonce },
                data: { mode: mode },
                success: (response) => {
                    this.inviteMode = mode;
                    showToast(response.message);
                },
                error: () => {
                    showToast('Could not update settings', 'error');
                }
            });
        },

        loadCollaborators: function() {
            $.ajax({
                url: bdLists.restUrl + 'lists/' + this.listId + '/collaborators',
                method: 'GET',
                headers: { 'X-WP-Nonce': bdLists.nonce },
                success: (response) => {
                    this.renderCollaborators(response.collaborators);
                    this.renderPendingRequests(response.pending);
                    
                    // Update counts
                    $('.bd-collab-count').text(response.count > 0 ? response.count : '');
                    $('.bd-pending-count').text(response.pending_count > 0 ? response.pending_count : '');
                    $('.bd-current-count').text(response.count);
                }
            });
        },

        renderCollaborators: function(collaborators) {
            const $list = $('.bd-collaborators-list');
            
            if (collaborators.length === 0) {
                $list.html(`
                    <div class="bd-collab-empty">
                        <i class="fas fa-users"></i>
                        <p>No collaborators yet</p>
                        <p class="bd-empty-hint">Share your invite link to add people!</p>
                    </div>
                `);
                return;
            }
            
            let html = '';
            collaborators.forEach(collab => {
                html += `
                    <div class="bd-collab-item" data-user-id="${collab.user_id}">
                        <img src="${collab.avatar_url}" alt="${collab.display_name}" class="bd-collab-avatar">
                        <div class="bd-collab-info">
                            <span class="bd-collab-name">${collab.display_name}</span>
                            <span class="bd-collab-role">${collab.role}</span>
                        </div>
                        <button class="bd-remove-collab-btn" data-user-id="${collab.user_id}" title="Remove">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `;
            });
            
            $list.html(html);
        },

        renderPendingRequests: function(requests) {
            const $list = $('.bd-pending-requests-list');
            
            if (requests.length === 0) {
                $list.html(`
                    <div class="bd-collab-empty">
                        <i class="fas fa-inbox"></i>
                        <p>No pending requests</p>
                    </div>
                `);
                return;
            }
            
            let html = '';
            requests.forEach(req => {
                html += `
                    <div class="bd-request-item" data-user-id="${req.user_id}">
                        <img src="${req.avatar_url}" alt="${req.display_name}" class="bd-collab-avatar">
                        <div class="bd-collab-info">
                            <span class="bd-collab-name">${req.display_name}</span>
                            <span class="bd-request-time">Requested to join</span>
                        </div>
                        <div class="bd-request-actions">
                            <button class="bd-approve-request-btn" data-user-id="${req.user_id}" title="Approve">
                                <i class="fas fa-check"></i>
                            </button>
                            <button class="bd-deny-request-btn" data-user-id="${req.user_id}" title="Deny">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                `;
            });
            
            $list.html(html);
        },

        searchUsers: function(e) {
            const search = $(e.target).val().trim();
            const $results = $('.bd-user-search-results');
            
            if (search.length < 2) {
                $results.html('');
                return;
            }
            
            $.ajax({
                url: bdLists.restUrl + 'users/search',
                method: 'GET',
                headers: { 'X-WP-Nonce': bdLists.nonce },
                data: { 
                    search: search,
                    list_id: this.listId
                },
                success: (response) => {
                    if (response.users.length === 0) {
                        $results.html('<p class="bd-no-results">No users found</p>');
                        return;
                    }
                    
                    let html = '';
                    response.users.forEach(user => {
                        html += `
                            <div class="bd-user-result">
                                <img src="${user.avatar_url}" alt="${user.display_name}" class="bd-user-avatar">
                                <span class="bd-user-name">${user.display_name}</span>
                                <button class="bd-add-user-btn" data-user-id="${user.id}" data-name="${user.display_name}">
                                    <i class="fas fa-plus"></i> Add
                                </button>
                            </div>
                        `;
                    });
                    
                    $results.html(html);
                }
            });
        },

        loadRecentCollaborators: function() {
            $.ajax({
                url: bdLists.restUrl + 'users/me/recent-collaborators',
                method: 'GET',
                headers: { 'X-WP-Nonce': bdLists.nonce },
                success: (response) => {
                    const $list = $('.bd-recent-collab-list');
                    
                    if (response.users.length === 0) {
                        $list.html('<p class="bd-no-recent">No recent collaborators</p>');
                        return;
                    }
                    
                    let html = '';
                    response.users.forEach(user => {
                        html += `
                            <button class="bd-recent-collab-btn" data-user-id="${user.user_id}" 
                                    data-name="${user.display_name}" title="${user.display_name}">
                                <img src="${user.avatar_url}" alt="${user.display_name}">
                            </button>
                        `;
                    });
                    
                    $list.html(html);
                }
            });
        },

        addCollaborator: function(e) {
            const $btn = $(e.currentTarget);
            const userId = $btn.data('user-id');
            const name = $btn.data('name');
            
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
            
            $.ajax({
                url: bdLists.restUrl + 'lists/' + this.listId + '/collaborators',
                method: 'POST',
                headers: { 'X-WP-Nonce': bdLists.nonce },
                data: { 
                    user_id: userId,
                    role: 'contributor'
                },
                success: (response) => {
                    showToast(response.message);
                    $btn.closest('.bd-user-result').fadeOut();
                    this.loadCollaborators();
                    
                    // Clear search
                    $('.bd-user-search-input').val('');
                    $('.bd-user-search-results').html('');
                },
                error: (xhr) => {
                    showToast(xhr.responseJSON?.message || 'Could not add collaborator', 'error');
                    $btn.prop('disabled', false).html('<i class="fas fa-plus"></i> Add');
                }
            });
        },

        addRecentCollaborator: function(e) {
            const $btn = $(e.currentTarget);
            const userId = $btn.data('user-id');
            const name = $btn.data('name');
            
            $.ajax({
                url: bdLists.restUrl + 'lists/' + this.listId + '/collaborators',
                method: 'POST',
                headers: { 'X-WP-Nonce': bdLists.nonce },
                data: { 
                    user_id: userId,
                    role: 'contributor'
                },
                success: (response) => {
                    showToast(response.message);
                    this.loadCollaborators();
                },
                error: (xhr) => {
                    showToast(xhr.responseJSON?.message || 'Could not add collaborator', 'error');
                }
            });
        },

        removeCollaborator: function(e) {
            const $btn = $(e.currentTarget);
            const userId = $btn.data('user-id');
            const $item = $btn.closest('.bd-collab-item');
            const name = $item.find('.bd-collab-name').text();
            
            if (!confirm(`Remove ${name} from this list?`)) {
                return;
            }
            
            $.ajax({
                url: bdLists.restUrl + 'lists/' + this.listId + '/collaborators/' + userId,
                method: 'DELETE',
                headers: { 'X-WP-Nonce': bdLists.nonce },
                success: () => {
                    $item.fadeOut(() => {
                        $item.remove();
                        this.loadCollaborators();
                    });
                    showToast('Collaborator removed');
                },
                error: () => {
                    showToast('Could not remove collaborator', 'error');
                }
            });
        },

        approveRequest: function(e) {
            const $btn = $(e.currentTarget);
            const userId = $btn.data('user-id');
            const $item = $btn.closest('.bd-request-item');
            
            $btn.prop('disabled', true);
            
            $.ajax({
                url: bdLists.restUrl + 'lists/' + this.listId + '/requests/' + userId + '/approve',
                method: 'POST',
                headers: { 'X-WP-Nonce': bdLists.nonce },
                success: (response) => {
                    $item.fadeOut();
                    showToast(response.message);
                    this.loadCollaborators();
                },
                error: () => {
                    showToast('Could not approve request', 'error');
                    $btn.prop('disabled', false);
                }
            });
        },

        denyRequest: function(e) {
            const $btn = $(e.currentTarget);
            const userId = $btn.data('user-id');
            const $item = $btn.closest('.bd-request-item');
            
            $btn.prop('disabled', true);
            
            $.ajax({
                url: bdLists.restUrl + 'lists/' + this.listId + '/requests/' + userId + '/deny',
                method: 'POST',
                headers: { 'X-WP-Nonce': bdLists.nonce },
                success: () => {
                    $item.fadeOut();
                    this.loadCollaborators();
                },
                error: () => {
                    showToast('Could not deny request', 'error');
                    $btn.prop('disabled', false);
                }
            });
        },

        debounce: function(func, wait) {
            let timeout;
            return function(...args) {
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(this, args), wait);
            };
        }
    };


    // =========================================================================
    // NOTIFICATIONS
    // =========================================================================

    const ListNotifications = {
        $bell: null,
        $dropdown: null,

        init: function() {
            // Only init if user is logged in
            if (!bdLists.isLoggedIn) return;
            
            // Render notification bell
            this.renderBell();
            
            // Toggle dropdown
            $(document).on('click', '.bd-notif-bell', this.toggleDropdown.bind(this));
            
            // Close on outside click
            $(document).on('click', (e) => {
                if (!$(e.target).closest('.bd-list-notifications').length) {
                    this.$dropdown?.removeClass('active');
                }
            });
            
            // Mark as read
            $(document).on('click', '.bd-notif-item', this.markAsRead.bind(this));
            
            // Mark all as read
            $(document).on('click', '.bd-mark-all-read', this.markAllRead.bind(this));
            
            // Load initial count
            this.loadNotifications();
            
            // Poll every 60 seconds
            setInterval(() => this.loadNotifications(), 60000);
        },

        renderBell: function() {
            // Add bell to header or lists page
            const $target = $('.bd-lists-header .bd-lists-header-content');
            if ($target.length) {
                $target.append(`
                    <div class="bd-list-notifications">
                        <button class="bd-notif-bell" title="Notifications">
                            <i class="fas fa-bell"></i>
                            <span class="bd-notif-badge" style="display: none;">0</span>
                        </button>
                        <div class="bd-notif-dropdown">
                            <div class="bd-notif-header">
                                <span>Notifications</span>
                                <button class="bd-mark-all-read">Mark all read</button>
                            </div>
                            <div class="bd-notif-list"></div>
                        </div>
                    </div>
                `);
                this.$bell = $('.bd-notif-bell');
                this.$dropdown = $('.bd-notif-dropdown');
            }
        },

        loadNotifications: function() {
            $.ajax({
                url: bdLists.restUrl + 'users/me/list-notifications',
                method: 'GET',
                headers: { 'X-WP-Nonce': bdLists.nonce },
                success: (response) => {
                    this.updateBadge(response.unread_count);
                    this.renderNotifications(response.notifications);
                }
            });
        },

        updateBadge: function(count) {
            const $badge = $('.bd-notif-badge');
            if (count > 0) {
                $badge.text(count > 9 ? '9+' : count).show();
            } else {
                $badge.hide();
            }
        },

        renderNotifications: function(notifications) {
            const $list = $('.bd-notif-list');
            
            if (notifications.length === 0) {
                $list.html('<p class="bd-notif-empty">No notifications</p>');
                return;
            }
            
            let html = '';
            notifications.slice(0, 10).forEach(notif => {
                const icon = this.getNotifIcon(notif.type);
                const message = this.getNotifMessage(notif);
                const unreadClass = notif.read ? '' : 'bd-notif-unread';
                
                html += `
                    <div class="bd-notif-item ${unreadClass}" data-id="${notif.id}" data-list-id="${notif.list_id}">
                        <span class="bd-notif-icon">${icon}</span>
                        <div class="bd-notif-content">
                            <p class="bd-notif-message">${message}</p>
                            <span class="bd-notif-time">${this.timeAgo(notif.created_at)}</span>
                        </div>
                    </div>
                `;
            });
            
            $list.html(html);
        },

        getNotifIcon: function(type) {
            const icons = {
                'added_to_list': '<i class="fas fa-user-plus"></i>',
                'join_request': '<i class="fas fa-hand-paper"></i>',
                'request_approved': '<i class="fas fa-check-circle"></i>',
                'collaborator_joined': '<i class="fas fa-user-check"></i>',
                'item_added': '<i class="fas fa-plus-circle"></i>'
            };
            return icons[type] || '<i class="fas fa-bell"></i>';
        },

        getNotifMessage: function(notif) {
            const messages = {
                'added_to_list': `${notif.actor_name} added you to "${notif.list_title}"`,
                'join_request': `${notif.actor_name} wants to collaborate on "${notif.list_title}"`,
                'request_approved': `You're now a collaborator on "${notif.list_title}"`,
                'collaborator_joined': `${notif.actor_name} joined "${notif.list_title}"`,
                'item_added': `${notif.actor_name} added a business to "${notif.list_title}"`
            };
            return messages[notif.type] || 'New notification';
        },

        toggleDropdown: function(e) {
            e.stopPropagation();
            this.$dropdown.toggleClass('active');
        },

        markAsRead: function(e) {
            const $item = $(e.currentTarget);
            const notifId = $item.data('id');
            const listId = $item.data('list-id');
            
            // Mark as read
            if ($item.hasClass('bd-notif-unread')) {
                $.ajax({
                    url: bdLists.restUrl + 'users/me/list-notifications/' + notifId + '/read',
                    method: 'POST',
                    headers: { 'X-WP-Nonce': bdLists.nonce },
                    success: (response) => {
                        $item.removeClass('bd-notif-unread');
                        this.updateBadge(response.unread_count);
                    }
                });
            }
            
            // Navigate to list (optional)
            // window.location.href = bdLists.myListsUrl + '?list_id=' + listId;
        },

        markAllRead: function(e) {
            e.stopPropagation();
            
            $.ajax({
                url: bdLists.restUrl + 'users/me/list-notifications/read-all',
                method: 'POST',
                headers: { 'X-WP-Nonce': bdLists.nonce },
                success: () => {
                    $('.bd-notif-item').removeClass('bd-notif-unread');
                    this.updateBadge(0);
                }
            });
        },

        timeAgo: function(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const seconds = Math.floor((now - date) / 1000);
            
            if (seconds < 60) return 'Just now';
            if (seconds < 3600) return Math.floor(seconds / 60) + 'm ago';
            if (seconds < 86400) return Math.floor(seconds / 3600) + 'h ago';
            if (seconds < 604800) return Math.floor(seconds / 86400) + 'd ago';
            return date.toLocaleDateString();
        }
    };


    // =========================================================================
    // JOIN PAGE
    // =========================================================================

    const JoinPage = {
        init: function() {
            // Check if on join page - support both old and new URL formats
            const urlParams = new URLSearchParams(window.location.search);
            
            // New format: ?list=slug&join=token
            let slug = urlParams.get('list');
            let token = urlParams.get('join');
            
            // Fallback to old format: ?bd_join_list=slug&token=token
            if (!slug || !token) {
                slug = urlParams.get('bd_join_list');
                token = urlParams.get('token');
            }
            
            if (slug && token) {
                this.showJoinModal(slug, token);
            }
            
            // Handle join button
            $(document).on('click', '.bd-join-list-btn', this.joinList.bind(this));
            
            // Handle login button inside join modal - close join modal so auth modal can open
            $(document).on('click', '.bd-join-login-btn, .bd-join-register a[data-bd-login]', function() {
                $('.bd-join-modal').fadeOut(200);
            });
        },

        showJoinModal: function(slug, token) {
            // Validate token first
            $.ajax({
                url: bdLists.restUrl + 'lists/' + slug,
                method: 'GET',
                success: (list) => {
                    this.renderJoinModal(list, slug, token);
                },
                error: () => {
                    showToast('This invite link is invalid or has expired.', 'error');
                }
            });
        },

        renderJoinModal: function(list, slug, token) {
            const modal = `
                <div class="bd-join-modal bd-modal-active">
                    <div class="bd-join-modal-overlay"></div>
                    <div class="bd-join-modal-content">
                        <div class="bd-join-icon">
                            <i class="fas fa-handshake"></i>
                        </div>
                        <h2>You're Invited!</h2>
                        <p class="bd-join-description">
                            You've been invited to collaborate on:
                        </p>
                        <div class="bd-join-list-preview">
                            <h3>${list.title}</h3>
                            <p>by ${list.author?.display_name || 'Unknown'} • ${list.item_count} places</p>
                        </div>
                        
                        ${bdLists.isLoggedIn ? `
                            <button class="bd-btn bd-btn-primary bd-join-list-btn" 
                                    data-slug="${slug}" data-token="${token}">
                                <i class="fas fa-user-plus"></i> Join as Collaborator
                            </button>
                            <p class="bd-join-note">
                                You'll be able to add places and notes to this list.
                            </p>
                        ` : `
                            <p class="bd-join-login-prompt">
                                Please log in to collaborate on this list.
                            </p>
                            <button class="bd-btn bd-btn-primary bd-join-login-btn" 
                                    data-bd-login="true" 
                                    data-tab="login"
                                    data-redirect="${window.location.href}">
                                <i class="fas fa-sign-in-alt"></i> Log In
                            </button>
                            <p class="bd-join-register">
                                Don't have an account? 
                                <a href="#" data-bd-login="true" data-tab="register" data-redirect="${window.location.href}">Sign up</a>
                            </p>
                        `}
                    </div>
                </div>
            `;
            
            $('body').append(modal);
        },

        joinList: function(e) {
            const $btn = $(e.currentTarget);
            const slug = $btn.data('slug');
            const token = $btn.data('token');
            
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Joining...');
            
            $.ajax({
                url: bdLists.restUrl + 'lists/join',
                method: 'POST',
                headers: { 'X-WP-Nonce': bdLists.nonce },
                data: { slug: slug, token: token },
                success: (response) => {
                    if (response.status === 'joined') {
                        showToast(response.message);
                        $('.bd-join-modal-content').html(`
                            <div class="bd-join-success">
                                <i class="fas fa-check-circle"></i>
                                <h2>You're In!</h2>
                                <p>${response.message}</p>
                                <a href="${bdLists.myListsUrl}" class="bd-btn bd-btn-primary">
                                    View My Lists
                                </a>
                            </div>
                        `);
                    } else if (response.status === 'pending') {
                        $('.bd-join-modal-content').html(`
                            <div class="bd-join-pending">
                                <i class="fas fa-clock"></i>
                                <h2>Request Sent!</h2>
                                <p>${response.message}</p>
                                <button class="bd-btn bd-btn-secondary" onclick="$('.bd-join-modal').remove()">
                                    Close
                                </button>
                            </div>
                        `);
                    }
                },
                error: (xhr) => {
                    showToast(xhr.responseJSON?.message || 'Could not join list', 'error');
                    $btn.prop('disabled', false).html('<i class="fas fa-user-plus"></i> Join as Collaborator');
                }
            });
        }
    };


    // =========================================================================
    // COLLABORATIVE LIST INDICATORS
    // =========================================================================

    const CollabIndicators = {
        init: function() {
            // Add collaborator avatars to list cards
            this.addAvatarsToCards();
            
            // Add "Manage Collaborators" button to list edit modal
            this.enhanceEditModal();
        },

        addAvatarsToCards: function() {
            // This would be rendered server-side, but we can enhance client-side
            $('.bd-list-card[data-has-collaborators="true"]').each(function() {
                const $card = $(this);
                const collabCount = $card.data('collab-count');
                
                if (collabCount > 0 && !$card.find('.bd-collab-avatars').length) {
                    $card.find('.bd-list-meta').append(`
                        <span class="bd-collab-indicator">
                            <i class="fas fa-users"></i> +${collabCount}
                        </span>
                    `);
                }
            });
        },

        enhanceEditModal: function() {
            // When list edit modal opens, add collaborators button
            $(document).on('bd-edit-modal-opened', function(e, listId) {
                const $modal = $('.bd-edit-list-modal');
                if (!$modal.find('.bd-manage-collaborators-btn').length) {
                    $modal.find('.bd-modal-footer').prepend(`
                        <button type="button" class="bd-btn bd-btn-secondary bd-manage-collaborators-btn" 
                                data-list-id="${listId}">
                            <i class="fas fa-user-plus"></i> Collaborators
                        </button>
                    `);
                }
            });
        }
    };


    // =========================================================================
    // TOAST HELPER (if not already defined)
    // =========================================================================

    function showToast(message, type = 'success') {
        if (typeof window.showToast === 'function') {
            window.showToast(message, type);
            return;
        }
        
        // Fallback toast
        $('.bd-toast').remove();
        const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
        const $toast = $(`
            <div class="bd-toast bd-toast-${type}">
                <i class="fas ${icon}"></i>
                <span>${message}</span>
            </div>
        `);
        
        $('body').append($toast);
        setTimeout(() => $toast.addClass('bd-toast-visible'), 10);
        setTimeout(() => {
            $toast.removeClass('bd-toast-visible');
            setTimeout(() => $toast.remove(), 300);
        }, 3000);
    }


    // =========================================================================
    // INITIALIZE
    // =========================================================================

    $(document).ready(function() {
        CollaboratorsModal.init();
        ListNotifications.init();
        JoinPage.init();
        CollabIndicators.init();
    });

})(jQuery);
