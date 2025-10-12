/**
 * Premium Submission Form JavaScript
 * Handles form submission, media uploads, and interactive elements
 */
(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // ====================================================================
        // MEDIA UPLOAD HANDLING
        // ====================================================================
        
        // Photo upload preview
        $('#bd-photo-upload').on('change', function(e) {
            handleMediaUpload(e.target.files, 'photo');
        });
        
        // Video upload preview
        $('#bd-video-upload').on('change', function(e) {
            handleMediaUpload(e.target.files, 'video');
        });
        
        // Handle media file uploads and create previews
        function handleMediaUpload(files, type) {
            const preview = $('#bd-media-preview');
            
            // Validate file count
            const maxFiles = type === 'photo' ? 10 : 3;
            const currentCount = preview.find('.bd-media-preview-item').length;
            
            if (currentCount + files.length > maxFiles) {
                alert(`You can only upload up to ${maxFiles} ${type}s`);
                return;
            }
            
            Array.from(files).forEach(file => {
                // Validate file size (5MB for photos, 50MB for videos)
                const maxSize = type === 'photo' ? 5 * 1024 * 1024 : 50 * 1024 * 1024;
                if (file.size > maxSize) {
                    alert(`File ${file.name} is too large. Max size: ${maxSize / (1024 * 1024)}MB`);
                    return;
                }
                
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    const mediaItem = $('<div>', {
                        class: 'bd-media-preview-item',
                        'data-file-name': file.name,
                        'data-file-type': type
                    });
                    
                    if (type === 'photo') {
                        mediaItem.append($('<img>', {
                            src: e.target.result,
                            alt: file.name
                        }));
                    } else {
                        mediaItem.append($('<video>', {
                            src: e.target.result,
                            controls: true
                        }));
                    }
                    
                    const removeBtn = $('<button>', {
                        class: 'bd-media-remove',
                        html: '×',
                        type: 'button',
                        click: function(e) {
                            e.preventDefault();
                            mediaItem.remove();
                        }
                    });
                    
                    mediaItem.append(removeBtn);
                    preview.append(mediaItem);
                };
                
                reader.readAsDataURL(file);
            });
        }
        
        // ====================================================================
        // HOURS OF OPERATION TOGGLE
        // ====================================================================
        
        $('.bd-day-label input[type="checkbox"]').on('change', function() {
            const row = $(this).closest('.bd-hours-row');
            const inputs = row.find('.bd-hours-inputs input');
            
            if ($(this).is(':checked')) {
                inputs.prop('disabled', false).css('opacity', '1');
                row.removeClass('bd-disabled');
            } else {
                inputs.prop('disabled', true).css('opacity', '0.5');
                row.addClass('bd-disabled');
            }
        });
        
        // ====================================================================
        // FORM SUBMISSION
        // ====================================================================
        
        $('#bd-submit-business-form').on('submit', function(e) {
            e.preventDefault();
            
            const form = $(this);
            const button = form.find('button[type="submit"]');
            const message = $('#bd-submission-message');
            const originalButtonText = button.html();
            
            // Disable button and show loading state
            button.prop('disabled', true).html(
                '<svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor" style="animation: spin 0.8s linear infinite;">' +
                '<circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="2" fill="none" opacity="0.3"/>' +
                '<path d="M10 2a8 8 0 018 8" stroke="currentColor" stroke-width="2" fill="none"/>' +
                '</svg> Submitting...'
            );
            message.hide().removeClass('success error');
            form.addClass('loading');
            
            // Prepare form data
            const formData = new FormData(this);
            
            // Add Turnstile token if enabled
            if (window.turnstile && bdSubmission.turnstileSiteKey) {
                const token = turnstile.getResponse();
                if (token) {
                    formData.append('turnstile_token', token);
                }
            }
            
            // Add hours data in proper format
            const hours = {};
            $('.bd-hours-row').each(function() {
                const dayCheckbox = $(this).find('.bd-day-label input[type="checkbox"]');
                const dayName = dayCheckbox.attr('name').match(/hours\[(.+?)\]/)[1];
                
                if (dayCheckbox.is(':checked')) {
                    const openTime = $(this).find('input[name*="[open]"]').val();
                    const closeTime = $(this).find('input[name*="[close]"]').val();
                    
                    hours[dayName] = {
                        open: openTime,
                        close: closeTime
                    };
                } else {
                    hours[dayName] = {
                        closed: true
                    };
                }
            });
            
            formData.append('hours', JSON.stringify(hours));
            
            // Submit via AJAX
            $.ajax({
                url: bdSubmission.restUrl + 'submit-business',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', bdSubmission.nonce);
                },
                success: function(response) {
                    // Show success message
                    message
                        .addClass('success')
                        .html(
                            '<strong>🎉 Success!</strong> ' + 
                            (response.message || 'Your business has been submitted and is pending approval. We\'ll notify you once it\'s live!')
                        )
                        .fadeIn();
                    
                    // Reset form
                    form[0].reset();
                    $('#bd-media-preview').empty();
                    
                    // Reset Turnstile
                    if (window.turnstile && bdSubmission.turnstileSiteKey) {
                        turnstile.reset();
                    }
                    
                    // Scroll to message
                    $('html, body').animate({
                        scrollTop: message.offset().top - 100
                    }, 500);
                },
                error: function(xhr) {
                    // Show error message
                    const error = xhr.responseJSON?.message || 'Submission failed. Please check your information and try again.';
                    message
                        .addClass('error')
                        .html('<strong>⚠️ Error:</strong> ' + error)
                        .fadeIn();
                    
                    // Reset Turnstile
                    if (window.turnstile && bdSubmission.turnstileSiteKey) {
                        turnstile.reset();
                    }
                    
                    // Scroll to message
                    $('html, body').animate({
                        scrollTop: message.offset().top - 100
                    }, 500);
                },
                complete: function() {
                    // Re-enable button and restore original text
                    button.prop('disabled', false).html(originalButtonText);
                    form.removeClass('loading');
                }
            });
        });
        
        // ====================================================================
        // DRAG AND DROP SUPPORT (BONUS FEATURE)
        // ====================================================================
        
        $('.bd-upload-box').on('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).addClass('bd-dragover');
        });
        
        $('.bd-upload-box').on('dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('bd-dragover');
        });
        
        $('.bd-upload-box').on('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('bd-dragover');
            
            const files = e.originalEvent.dataTransfer.files;
            const type = $(this).find('input[type="file"]').attr('name').includes('photo') ? 'photo' : 'video';
            
            handleMediaUpload(files, type);
        });
        
    });
    
})(jQuery);

// Add spin animation for loading state
const style = document.createElement('style');
style.textContent = `
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    .bd-upload-box.bd-dragover {
        border-color: var(--bd-copper) !important;
        background: rgba(201, 168, 106, 0.1) !important;
        transform: scale(1.02) !important;
    }
`;
document.head.appendChild(style);