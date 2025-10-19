<?php
namespace BD\Forms;

/**
 * Claim Request Form
 * Modal form for claiming business listings
 */
class ClaimRequest {

	public function __construct() {
		add_action( 'wp_footer', array( $this, 'render_modal' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue assets on single business pages
	 */
	public function enqueue_assets() {
		if ( ! is_singular( 'bd_business' ) && ! is_singular( 'business' ) ) {
			return;
		}

		// Enqueue styles
		wp_enqueue_style(
			'bd-claim-form',
			BD_PLUGIN_URL . 'assets/css/claim-form.css',
			array( 'bd-business-detail' ), // Depends on existing business detail CSS
			BD_VERSION
		);

		// Enqueue scripts
		wp_enqueue_script(
			'bd-claim-form',
			BD_PLUGIN_URL . 'assets/js/claim-form.js',
			array( 'jquery' ),
			BD_VERSION,
			true
		);

		// Localize script
		wp_localize_script(
			'bd-claim-form',
			'bdClaimForm',
			array(
				'restUrl'          => rest_url( 'bd/v1/' ),
				'nonce'            => wp_create_nonce( 'wp_rest' ),
				'businessId'       => get_the_ID(),
				'businessName'     => get_the_title(),
				'turnstileSiteKey' => get_option( 'bd_turnstile_site_key', '' ),
			)
		);

		// Load Turnstile if enabled
		$site_key = get_option( 'bd_turnstile_site_key' );
		if ( ! empty( $site_key ) ) {
			wp_enqueue_script( 'turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', array(), null, true );
		}
	}

	/**
	 * Render modal HTML in footer
	 */
	public function render_modal() {
		if ( ! is_singular( 'bd_business' ) && ! is_singular( 'business' ) ) {
			return;
		}

		$business_id    = get_the_ID();
		$business_title = get_the_title();

		// Check if already claimed
		$claimed_by = get_post_meta( $business_id, 'bd_claimed_by', true );
		if ( $claimed_by ) {
			return; // Don't show modal if already claimed
		}

		$turnstile_enabled = ! empty( get_option( 'bd_turnstile_site_key' ) );
		?>
		
		<!-- Claim Request Modal -->
		<div id="bd-claim-modal" class="bd-claim-modal" style="display: none;">
			<div class="bd-claim-modal-overlay"></div>
			<div class="bd-claim-modal-content">
				
				<!-- Modal Header -->
				<div class="bd-claim-modal-header">
					<div class="bd-claim-icon">
						<svg width="32" height="32" viewBox="0 0 32 32" fill="currentColor">
							<path d="M16 2C9.4 2 4 7.4 4 14c0 7 12 16 12 16s12-9 12-16c0-6.6-5.4-12-12-12zm0 16c-2.2 0-4-1.8-4-4s1.8-4 4-4 4 1.8 4 4-1.8 4-4 4z"/>
						</svg>
					</div>
					<h2>Claim This Business</h2>
					<p class="bd-claim-subtitle"><?php echo esc_html( $business_title ); ?></p>
					<button type="button" class="bd-claim-close" aria-label="Close">
						<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
							<line x1="18" y1="6" x2="6" y2="18"/>
							<line x1="6" y1="6" x2="18" y2="18"/>
						</svg>
					</button>
				</div>
				
				<!-- Modal Body -->
				<form id="bd-claim-form" class="bd-claim-form">
					<input type="hidden" name="business_id" value="<?php echo esc_attr( $business_id ); ?>">
					
					<!-- Your Information -->
					<div class="bd-claim-section">
						<h3>Your Information</h3>
						
						<div class="bd-claim-field">
							<label for="claim-name">Full Name <span class="required">*</span></label>
							<input type="text" id="claim-name" name="claimant_name" required 
									placeholder="John Smith">
						</div>
						
						<div class="bd-claim-field">
							<label for="claim-email">Email Address <span class="required">*</span></label>
							<input type="email" id="claim-email" name="claimant_email" required 
									placeholder="john@example.com">
						</div>
						
						<div class="bd-claim-field">
							<label for="claim-phone">Phone Number (Optional)</label>
							<input type="tel" id="claim-phone" name="claimant_phone" 
									placeholder="(555) 123-4567">
						</div>
					</div>
					
					<!-- Relationship to Business -->
					<div class="bd-claim-section">
						<h3>Your Relationship <span class="required">*</span></h3>
						<div class="bd-claim-radio-group">
							<label class="bd-claim-radio">
								<input type="radio" name="relationship" value="owner" required>
								<span class="bd-radio-label">
									<strong>I'm the owner</strong>
									<small>I own this business</small>
								</span>
							</label>
							
							<label class="bd-claim-radio">
								<input type="radio" name="relationship" value="manager">
								<span class="bd-radio-label">
									<strong>I'm the manager</strong>
									<small>I manage day-to-day operations</small>
								</span>
							</label>
							
							<label class="bd-claim-radio">
								<input type="radio" name="relationship" value="authorized">
								<span class="bd-radio-label">
									<strong>I'm authorized to manage</strong>
									<small>I have permission from the owner</small>
								</span>
							</label>
						</div>
					</div>
					
					<!-- Proof of Ownership -->
					<div class="bd-claim-section">
						<h3>Proof of Ownership</h3>
						<p class="bd-claim-help">Upload documents that verify you own or manage this business (business license, utility bill, tax documents, etc.)</p>
						
						<div class="bd-claim-file-upload">
							<input type="file" id="claim-proof-files" name="proof_files[]" 
									accept="image/*,.pdf,.doc,.docx" multiple 
									style="display: none;">
							
							<button type="button" class="bd-file-upload-btn" onclick="document.getElementById('claim-proof-files').click()">
								<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
									<path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"/>
								</svg>
								Choose Files
							</button>
							
							<div id="bd-file-list" class="bd-file-list"></div>
							<p class="bd-file-hint">Maximum 5 files, 5MB each (JPG, PNG, PDF, DOC)</p>
						</div>
					</div>
					
					<!-- Additional Message -->
					<div class="bd-claim-section">
						<h3>Additional Information (Optional)</h3>
						<textarea name="message" rows="4" 
									placeholder="Add any additional information that helps verify your claim..."></textarea>
					</div>
					
					<!-- Turnstile Captcha -->
					<?php if ( $turnstile_enabled ) : ?>
					<div class="bd-claim-section">
						<div class="cf-turnstile" data-sitekey="<?php echo esc_attr( get_option( 'bd_turnstile_site_key' ) ); ?>"></div>
					</div>
					<?php endif; ?>
					
					<!-- Submit Button -->
					<div class="bd-claim-actions">
						<button type="submit" class="bd-btn bd-btn-primary bd-btn-large">
							<svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
								<path d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"/>
							</svg>
							Submit Claim Request
						</button>
					</div>
					
					<!-- Messages -->
					<div id="bd-claim-message" class="bd-claim-message"></div>
				</form>
				
			</div>
		</div>
		
		<?php
	}
}