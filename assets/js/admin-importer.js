/**
 * Business Directory - Batch CSV Importer
 *
 * Handles AJAX-based batch processing for large CSV imports.
 * Shows real-time progress and handles errors gracefully.
 *
 * @package BusinessDirectory
 * @since 1.4.0
 */

(function ($) {
	'use strict';

	/**
	 * Batch Importer Controller
	 */
	const BatchImporter = {
		// State
		importId: null,
		batchSize: 25,
		isRunning: false,
		isPaused: false,
		aborted: false,
		retryCount: 0,
		maxRetries: 3,

		// Elements
		$form: null,
		$progressSection: null,
		$progressBar: null,
		$progressText: null,
		$statusLog: null,
		$results: null,
		$pauseBtn: null,
		$cancelBtn: null,

		/**
		 * Initialize the importer
		 */
		init: function () {
			this.$form = $('#bd-batch-import-form');
			this.$progressSection = $('#bd-import-progress');
			this.$progressBar = $('#bd-progress-bar');
			this.$progressText = $('#bd-progress-text');
			this.$statusLog = $('#bd-status-log');
			this.$results = $('#bd-import-results');
			this.$pauseBtn = $('#bd-pause-import');
			this.$cancelBtn = $('#bd-cancel-import');

			if (!this.$form.length) {
				return;
			}

			this.bindEvents();
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function () {
			const self = this;

			this.$form.on('submit', function (e) {
				e.preventDefault();
				self.startImport();
			});

			this.$pauseBtn.on('click', function () {
				self.togglePause();
			});

			this.$cancelBtn.on('click', function () {
				if (confirm(bdBatchImport.i18n.confirmCancel)) {
					self.cancelImport();
				}
			});

			// Warn before leaving during import
			$(window).on('beforeunload', function () {
				if (self.isRunning && !self.aborted) {
					return bdBatchImport.i18n.leaveWarning;
				}
			});
		},

		/**
		 * Start the import process
		 */
		startImport: function () {
			const self = this;
			const formData = new FormData(this.$form[0]);
			formData.append('action', 'bd_batch_upload');
			formData.append('nonce', bdBatchImport.nonce);

			// Reset state
			this.isRunning = true;
			this.isPaused = false;
			this.aborted = false;
			this.retryCount = 0;

			// Show progress section
			this.$form.find('.bd-import-options').addClass('bd-disabled');
			this.$form.find('button[type="submit"]').prop('disabled', true);
			this.$progressSection.removeClass('bd-hidden').show();
			this.$results.addClass('bd-hidden');
			this.$statusLog.empty();
			this.updateProgress(0, 0, 0);
			this.log(bdBatchImport.i18n.uploading, 'info');

			// Upload and initialize
			$.ajax({
				url: bdBatchImport.ajaxUrl,
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				success: function (response) {
					if (response.success) {
						self.importId = response.data.import_id;
						self.batchSize = response.data.batch_size;
						self.log(response.data.message, 'success');
						self.processBatch(response.data.total);
					} else {
						self.handleError(response.data.message);
					}
				},
				error: function (xhr, status, error) {
					self.handleError(bdBatchImport.i18n.uploadError + ': ' + error);
				}
			});
		},

		/**
		 * Process the next batch
		 *
		 * @param {number} total Total rows to process
		 */
		processBatch: function (total) {
			const self = this;

			if (this.aborted) {
				return;
			}

			if (this.isPaused) {
				this.log(bdBatchImport.i18n.paused, 'info');
				return;
			}

			$.ajax({
				url: bdBatchImport.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bd_batch_process',
					nonce: bdBatchImport.nonce,
					import_id: this.importId,
					batch_size: this.batchSize
				},
				success: function (response) {
					if (response.success) {
						const data = response.data;

						// Reset retry count on success
						self.retryCount = 0;

						// Update progress
						self.updateProgress(data.processed, data.total, data.percentage);

						// Log batch results
						self.logBatchResults(data.batch, data.processed);

						if (data.complete) {
							self.completeImport(data);
						} else {
							// Continue to next batch
							self.processBatch(total);
						}
					} else {
						self.handleError(response.data.message || bdBatchImport.i18n.batchError);
					}
				},
				error: function (xhr, status, error) {
					// Retry logic for transient errors (with limit)
					if ((status === 'timeout' || xhr.status >= 500) && self.retryCount < self.maxRetries) {
						self.retryCount++;
						self.log(bdBatchImport.i18n.retrying + ' (' + self.retryCount + '/' + self.maxRetries + ')', 'warning');
						setTimeout(function () {
							self.processBatch(total);
						}, 2000 * self.retryCount); // Exponential backoff
					} else if (self.retryCount >= self.maxRetries) {
						self.handleError(bdBatchImport.i18n.batchError + ': Max retries exceeded');
					} else {
						self.handleError(bdBatchImport.i18n.batchError + ': ' + error);
					}
				},
				timeout: 60000 // 60 second timeout per batch
			});
		},

		/**
		 * Update progress UI
		 *
		 * @param {number} processed Rows processed
		 * @param {number} total     Total rows
		 * @param {number} percent   Percentage complete
		 */
		updateProgress: function (processed, total, percent) {
			this.$progressBar.css('width', percent + '%');
			this.$progressBar.attr('aria-valuenow', percent);
			this.$progressText.text(
				bdBatchImport.i18n.processing
					.replace('%1$d', processed)
					.replace('%2$d', total)
					.replace('%3$d', percent)
			);
		},

		/**
		 * Log batch results
		 *
		 * @param {Object} batch     Batch results
		 * @param {number} processed Total processed so far
		 */
		logBatchResults: function (batch, processed) {
			const parts = [];

			if (batch.imported > 0) {
				parts.push(batch.imported + ' ' + bdBatchImport.i18n.imported);
			}
			if (batch.updated > 0) {
				parts.push(batch.updated + ' ' + bdBatchImport.i18n.updated);
			}
			if (batch.skipped > 0) {
				parts.push(batch.skipped + ' ' + bdBatchImport.i18n.skipped);
			}

			if (parts.length > 0) {
				this.log(
					bdBatchImport.i18n.batchComplete
						.replace('%d', processed) + ': ' + parts.join(', '),
					'info'
				);
			}

			// Log any errors
			if (batch.errors && batch.errors.length > 0) {
				batch.errors.forEach(function (error) {
					this.log(error, 'error');
				}, this);
			}
		},

		/**
		 * Handle import completion
		 *
		 * @param {Object} data Final results data
		 */
		completeImport: function (data) {
			this.isRunning = false;
			this.log(data.message, 'success');

			// Show results summary
			this.showResults(data.results, data.dry_run);

			// Cleanup
			this.cleanup();

			// Re-enable form
			this.$form.find('.bd-import-options').removeClass('bd-disabled');
			this.$form.find('button[type="submit"]').prop('disabled', false);
			this.$pauseBtn.addClass('bd-hidden');
			this.$cancelBtn.addClass('bd-hidden');
		},

		/**
		 * Show final results
		 *
		 * @param {Object} results Import results
		 * @param {boolean} isDryRun Whether this was a preview/dry run
		 */
		showResults: function (results, isDryRun) {
			const $results = this.$results;

			$results.find('.bd-stat-imported .bd-stat-number').text(results.imported);
			$results.find('.bd-stat-updated .bd-stat-number').text(results.updated);
			$results.find('.bd-stat-skipped .bd-stat-number').text(results.skipped);

			// Update labels for dry run mode
			if (isDryRun) {
				$results.find('.bd-stat-imported .bd-stat-label').text(bdBatchImport.i18n.wouldImport);
				$results.find('.bd-stat-updated .bd-stat-label').text(bdBatchImport.i18n.wouldUpdate);
				$results.find('.bd-stat-skipped .bd-stat-label').text(bdBatchImport.i18n.wouldSkip);
				$results.addClass('bd-preview-results');
			} else {
				$results.find('.bd-stat-imported .bd-stat-label').text(bdBatchImport.i18n.importedLabel);
				$results.find('.bd-stat-updated .bd-stat-label').text(bdBatchImport.i18n.updatedLabel);
				$results.find('.bd-stat-skipped .bd-stat-label').text(bdBatchImport.i18n.skippedLabel);
				$results.removeClass('bd-preview-results');
			}

			// Show errors if any
			const $errorsList = $results.find('.bd-errors-list');
			if (results.errors && results.errors.length > 0) {
				const $ul = $('<ul></ul>');
				results.errors.slice(0, 50).forEach(function (error) {
					$ul.append($('<li></li>').text(error));
				});
				$errorsList.empty().append($ul);
				if (results.errors.length > 50) {
					$errorsList.append(
						$('<p><em></em></p>').text(
							bdBatchImport.i18n.moreErrors.replace('%d', results.errors.length - 50)
						)
					);
				}
				$errorsList.removeClass('bd-hidden');
			} else {
				$errorsList.addClass('bd-hidden');
			}

			$results.removeClass('bd-hidden');
		},

		/**
		 * Toggle pause state
		 */
		togglePause: function () {
			this.isPaused = !this.isPaused;

			if (this.isPaused) {
				this.$pauseBtn.text(bdBatchImport.i18n.resume);
				this.log(bdBatchImport.i18n.paused, 'warning');
			} else {
				this.$pauseBtn.text(bdBatchImport.i18n.pause);
				this.log(bdBatchImport.i18n.resuming, 'info');
				this.processBatch();
			}
		},

		/**
		 * Cancel the import
		 */
		cancelImport: function () {
			this.aborted = true;
			this.isRunning = false;
			this.log(bdBatchImport.i18n.cancelled, 'warning');

			// Cleanup
			this.cleanup();

			// Re-enable form
			this.$form.find('.bd-import-options').removeClass('bd-disabled');
			this.$form.find('button[type="submit"]').prop('disabled', false);
			this.$pauseBtn.addClass('bd-hidden');
			this.$cancelBtn.addClass('bd-hidden');
		},

		/**
		 * Handle errors
		 *
		 * @param {string} message Error message
		 */
		handleError: function (message) {
			this.isRunning = false;
			this.log(message, 'error');

			// Re-enable form
			this.$form.find('.bd-import-options').removeClass('bd-disabled');
			this.$form.find('button[type="submit"]').prop('disabled', false);
			this.$pauseBtn.addClass('bd-hidden');
			this.$cancelBtn.addClass('bd-hidden');

			// Cleanup
			this.cleanup();
		},

		/**
		 * Log a message to the status log
		 *
		 * @param {string} message Message text
		 * @param {string} type    Message type (info, success, warning, error)
		 */
		log: function (message, type) {
			const timestamp = new Date().toLocaleTimeString();
			const $entry = $('<div class="bd-log-entry bd-log-' + type + '"></div>');
			const $time = $('<span class="bd-log-time"></span>').text('[' + timestamp + '] ');
			const $msg = $('<span class="bd-log-message"></span>').text(message);
			$entry.append($time).append($msg);
			this.$statusLog.append($entry);
			this.$statusLog.scrollTop(this.$statusLog[0].scrollHeight);
		},

		/**
		 * Cleanup import session
		 */
		cleanup: function () {
			if (!this.importId) {
				return;
			}

			$.ajax({
				url: bdBatchImport.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bd_batch_cleanup',
					nonce: bdBatchImport.nonce,
					import_id: this.importId
				}
			});

			this.importId = null;
		}
	};

	// Initialize on document ready
	$(document).ready(function () {
		BatchImporter.init();
	});

})(jQuery);
