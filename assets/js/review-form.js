(function($) {
    'use strict';
    
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
    
})(jQuery);
