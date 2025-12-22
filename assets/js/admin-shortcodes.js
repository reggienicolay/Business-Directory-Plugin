(function ($) {
	'use strict';

	$(document).ready(function () {

		/**
		 * Copy to clipboard functionality
		 */
		function copyToClipboard(text, $element) {
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(text).then(function () {
					showCopied($element);
				}).catch(function () {
					fallbackCopy(text, $element);
				});
			} else {
				fallbackCopy(text, $element);
			}
		}

		/**
		 * Fallback copy method for older browsers
		 */
		function fallbackCopy(text, $element) {
			var $temp = $('<textarea>');
			$('body').append($temp);
			$temp.val(text).select();
			try {
				document.execCommand('copy');
				showCopied($element);
			} catch (err) {
				console.error('Copy failed:', err);
			}
			$temp.remove();
		}

		/**
		 * Show copied feedback
		 */
		function showCopied($element) {
			var $btn = $element.hasClass('bd-copy-btn') ? $element : $element.siblings('.bd-copy-btn');
			
			if ($btn.length) {
				$btn.addClass('copied');
				$btn.find('.dashicons').removeClass('dashicons-clipboard').addClass('dashicons-yes');
				
				setTimeout(function () {
					$btn.removeClass('copied');
					$btn.find('.dashicons').removeClass('dashicons-yes').addClass('dashicons-clipboard');
				}, 2000);
			}

			// Also flash the code element
			$element.css('background', '#d4edda');
			setTimeout(function () {
				$element.css('background', '');
			}, 500);
		}

		/**
		 * Click handlers for copy buttons
		 */
		$('.bd-copy-btn').on('click', function (e) {
			e.preventDefault();
			var $code = $(this).siblings('.bd-example-code');
			var text = $code.data('copy') || $code.text();
			copyToClipboard(text, $(this));
		});

		/**
		 * Click on code to copy
		 */
		$('.bd-example-code, .bd-shortcode-tag').on('click', function (e) {
			e.preventDefault();
			var text = $(this).data('copy') || $(this).text();
			copyToClipboard(text, $(this));
		});

		/**
		 * Smooth scroll for navigation
		 */
		$('.bd-nav-item').on('click', function (e) {
			e.preventDefault();
			var target = $(this).attr('href');
			$('html, body').animate({
				scrollTop: $(target).offset().top - 50
			}, 400);
		});

	});

})(jQuery);
