(function($) {
    'use strict';
    
    $('#bd-submit-business-form').on('submit', function(e) {
        e.preventDefault();
        
        const form = $(this);
        const button = form.find('button[type="submit"]');
        const message = $('#bd-submission-message');
        
        button.prop('disabled', true).text('Submitting...');
        message.html('');
        
        const formData = new FormData(this);
        const data = {};
        formData.forEach((value, key) => data[key] = value);
        
        if (window.turnstile && bdSubmission.turnstileSiteKey) {
            data.turnstile_token = turnstile.getResponse();
        }
        
        $.ajax({
            url: bdSubmission.restUrl + 'submit-business',
            method: 'POST',
            data: JSON.stringify(data),
            contentType: 'application/json',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', bdSubmission.nonce);
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
                button.prop('disabled', false).text('Submit Business');
            }
        });
    });
    
})(jQuery);
