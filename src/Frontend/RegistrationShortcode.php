<?php

namespace BD\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

class RegistrationShortcode {

	public function __construct() {
		add_shortcode( 'bd_register', array( $this, 'render_registration_page' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	/**
	 * Enqueue registration page styles
	 */
	public function enqueue_styles() {
		// Only load on pages with the shortcode
		global $post;
		if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'bd_register' ) ) {
			wp_enqueue_style(
				'bd-registration',
				BD_PLUGIN_URL . 'assets/css/registration.css',
				array(),
				BD_VERSION
			);
		}
	}

	/**
	 * Render registration page
	 */
	public function render_registration_page( $atts ) {
		// Redirect if already logged in
		if ( is_user_logged_in() ) {
			return '<div class="ll-notice">You are already logged in. <a href="' . home_url( '/my-profile' ) . '">View your profile</a></div>';
		}

		ob_start();
		?>
		
		<div class="ll-registration-page">
			<div class="ll-reg-container">
				
				<!-- Left Side: Benefits -->
				<div class="ll-reg-benefits">
					<div class="ll-reg-logo">
						<?php
						$logo = get_option( 'bd_logo_url' );
						if ( $logo ) :
							?>
							<img src="<?php echo esc_url( $logo ); ?>" alt="Love Livermore">
						<?php else : ?>
							<h2 style="color: white;">Love Livermore</h2>
						<?php endif; ?>
					</div>
					
					<h1>Join Love Livermore</h1>
					<p class="ll-reg-subtitle">Support local businesses and earn recognition for your contributions</p>
					
					<div class="ll-reg-features">
						<div class="ll-feature">
							<span class="ll-feature-icon">🏆</span>
							<div class="ll-feature-text">
								<h3>Earn Badges</h3>
								<p>Get recognized for your reviews and contributions</p>
							</div>
						</div>
						
						<div class="ll-feature">
							<span class="ll-feature-icon">⭐</span>
							<div class="ll-feature-text">
								<h3>Rank Up</h3>
								<p>From Newcomer to Legend - build your reputation</p>
							</div>
						</div>
						
						<div class="ll-feature">
							<span class="ll-feature-icon">📊</span>
							<div class="ll-feature-text">
								<h3>Track Impact</h3>
								<p>See your reviews help local businesses thrive</p>
							</div>
						</div>
						
						<div class="ll-feature">
							<span class="ll-feature-icon">💜</span>
							<div class="ll-feature-text">
								<h3>Get Verified</h3>
								<p>Earn special "Love Livermore Verified" badge</p>
							</div>
						</div>
					</div>
					
					<!-- Badge Preview -->
					<div class="ll-badge-preview">
						<p class="ll-preview-label">Available Badges:</p>
						<div class="ll-badge-grid">
							<span class="ll-badge-mini" title="First Review">⭐ First Review</span>
							<span class="ll-badge-mini" title="Rising Star">🌟 Rising Star</span>
							<span class="ll-badge-mini" title="Local Expert">🏆 Local Expert</span>
							<span class="ll-badge-mini" title="Photo Pro">📸 Photo Pro</span>
							<span class="ll-badge-mini" title="Nicole's Pick">💜 Nicole's Pick</span>
							<span class="ll-badge-mini" title="LL Verified">✓ LL Verified</span>
						</div>
					</div>
				</div>
				
				<!-- Right Side: Registration Form -->
				<div class="ll-reg-form-area">
					<div class="ll-reg-form-card">
						
						<h2>Create Your Account</h2>
						
						<?php
						// Check if coming from review submission
						$from_review     = isset( $_GET['from_review'] ) ? true : false;
						$claimed_reviews = isset( $_GET['claimed'] ) ? absint( $_GET['claimed'] ) : 0;

						if ( $from_review ) {
							echo '<div class="ll-reg-notice ll-notice-success">
                                <p><strong>Review submitted!</strong> Create an account to start earning points and badges.</p>
                            </div>';
						}

						if ( $claimed_reviews > 0 ) {
							echo '<div class="ll-reg-notice ll-notice-success">
                                <p><strong>Success!</strong> You claimed ' . $claimed_reviews . ' review(s) and earned your first points!</p>
                            </div>';
						}

						// Show errors
						if ( isset( $_GET['registration'] ) && $_GET['registration'] === 'failed' ) {
							$error = isset( $_GET['error'] ) ? urldecode( $_GET['error'] ) : 'Registration failed';
							echo '<div class="ll-reg-notice ll-notice-error">
                                <p><strong>Error:</strong> ' . esc_html( $error ) . '</p>
                            </div>';
						}
						?>
						
						<!-- Social Login Buttons -->
						<div class="ll-social-login">
							<?php
							// Nextend Social Login buttons
							if ( function_exists( 'new_social_login_render' ) ) {
								echo do_shortcode( '[nextend_social_login]' );
							}
							?>
						</div>
						
						<div class="ll-divider">
							<span>or</span>
						</div>
						
						<!-- Email Registration Form -->
						<form id="ll-registration-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<?php wp_nonce_field( 'll_register_user', 'll_register_nonce' ); ?>
							<input type="hidden" name="action" value="ll_register_user">
							
							<div class="ll-form-group">
								<label for="ll_username">Username</label>
								<input type="text" 
										id="ll_username" 
										name="username" 
										required 
										autocomplete="username"
										placeholder="Choose a username">
							</div>
							
							<div class="ll-form-group">
								<label for="ll_email">Email</label>
								<input type="email" 
										id="ll_email" 
										name="email" 
										required 
										autocomplete="email"
										placeholder="your@email.com"
										value="<?php echo isset( $_GET['email'] ) ? esc_attr( $_GET['email'] ) : ''; ?>">
								<small class="ll-form-hint">We'll check for any reviews you've already written</small>
							</div>
							
							<div class="ll-form-group">
								<label for="ll_password">Password</label>
								<input type="password" 
										id="ll_password" 
										name="password" 
										required 
										autocomplete="new-password"
										placeholder="At least 8 characters">
							</div>
							
							<div class="ll-form-group ll-checkbox-group">
								<label>
									<input type="checkbox" name="terms" required>
									<span>I agree to the <a href="<?php echo home_url( '/terms' ); ?>" target="_blank">Terms of Service</a></span>
								</label>
							</div>
							
							<button type="submit" class="ll-btn ll-btn-primary ll-btn-large">
								Create Account
							</button>
						</form>
						
						<p class="ll-reg-footer">
							Already have an account? 
							<a href="<?php echo wp_login_url(); ?>">Sign in</a>
						</p>
						
					</div>
				</div>
				
			</div>
		</div>
		
		<?php
		return ob_get_clean();
	}
}