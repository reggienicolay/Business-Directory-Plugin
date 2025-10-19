(function($) {
    'use strict';
    
    // Submit Review Form
    $('#bd-submit-review-form').on('submit', function(e) {
        e.preventDefault();
        
        const form = $(this);
        const button = form.find('button[type="submit"]');
        const message = $('#bd-review-message');
        
        button.prop('disabled', true).text('Submitting...');
        message.html('');
        
        const formData = new FormData(this);
        
        if (window.turnstile && bdReview.turnstileSiteKey) {
            formData.append('turnstile_token', turnstile.getResponse());
        }
        
        $.ajax({
            url: bdReview.restUrl + 'submit-review',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', bdReview.nonce);
            },
            success: function(response) {
                message.html('<div class="bd-success">' + response.message + '</div>');
                form[0].reset();
                if (window.turnstile) turnstile.reset();
            },
            error: function(xhr) {
                const error = xhr.responseJSON?.message || 'Submission failed. Please try again.';
                message.html('<div class="bd-error">' + error + '</div>');
                if (window.turnstile) turnstile.reset();
            },
            complete: function() {
                button.prop('disabled', false).text('Submit Review');
            }
        });
    });
    
    // Star rating interaction
    $('.bd-star-rating input').on('change', function() {
        $('.bd-star-rating label').removeClass('selected');
        $(this).parent().find('label').slice($(this).val() - 5).addClass('selected');
    });
    
    // ========================================================================
    // HELPFUL VOTE HANDLER
    // ========================================================================
    
    $(document).on('click', '.bd-helpful-btn', function(e) {
        e.preventDefault();
        
        const $btn = $(this);
        const reviewId = $btn.data('review-id');
        const reviewAuthorId = $btn.data('review-author-id');
        
        // Don't allow if already clicked (check for class)
        if ($btn.hasClass('bd-helpful-voted')) {
            return;
        }
        
        // Disable button immediately
        $btn.prop('disabled', true);
        
        $.ajax({
            url: bdReview.ajaxUrl,
            method: 'POST',
            data: {
                action: 'bd_mark_helpful',
                review_id: reviewId,
                review_author_id: reviewAuthorId,
                nonce: bdReview.helpfulNonce
            },
            success: function(response) {
                if (response.success) {
                    // Update count
                    const $count = $btn.find('.bd-helpful-count');
                    const currentCount = parseInt($count.text()) || 0;
                    $count.text(currentCount + 1);
                    
                    // Mark as voted
                    $btn.addClass('bd-helpful-voted');
                    $btn.find('.bd-helpful-text').text('Helped!');
                    
                    // Add animation
                    $btn.addClass('bd-helpful-animate');
                    setTimeout(function() {
                        $btn.removeClass('bd-helpful-animate');
                    }, 600);
                    
                } else {
                    // Re-enable if there was an error
                    $btn.prop('disabled', false);
                    alert(response.data || 'Could not mark as helpful. Please try again.');
                }
            },
            error: function() {
                $btn.prop('disabled', false);
                alert('An error occurred. Please try again.');
            }
        });
    });
    
})(jQuery);