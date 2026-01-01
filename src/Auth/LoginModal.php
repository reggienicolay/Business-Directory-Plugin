<?php
/**
 * Login Modal
 *
 * Renders login/register modal popup in footer.
 * Triggered by elements with data-bd-login attribute.
 *
 * @package BusinessDirectory
 * @subpackage Auth
 * @version 1.1.0
 */

namespace BD\Auth;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LoginModal
 */
class LoginModal {

	/**
	 * Initialize modal
	 */
	public static function init() {
		add_action( 'wp_footer', array( __CLASS__, 'render_modal' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue assets on frontend
	 */
	public static function enqueue_assets() {
		// Skip admin and login pages.
		if ( is_admin() ) {
			return;
		}

		// Skip if already logged in (modal not needed).
		// Actually, we might need it for logout, so let's keep it.

		wp_enqueue_style(
			'bd-auth',
			BD_PLUGIN_URL . 'assets/css/auth.css',
			array(),
			BD_VERSION
		);

		wp_enqueue_script(
			'bd-auth',
			BD_PLUGIN_URL . 'assets/js/auth.js',
			array( 'jquery' ),
			BD_VERSION,
			true
		);

		wp_localize_script( 'bd-auth', 'bdAuth', LoginShortcode::get_js_data() );
	}

	/**
	 * Render password input with toggle button
	 *
	 * @param string $id          Input ID.
	 * @param string $name        Input name.
	 * @param string $label       Label text.
	 * @param string $placeholder Placeholder text.
	 * @param string $autocomplete Autocomplete attribute.
	 * @param int    $minlength   Minimum length (optional).
	 * @return string
	 */
	private static function render_password_field( $id, $name, $label, $placeholder, $autocomplete = 'current-password', $minlength = 0 ) {
		ob_start();
		?>
		<div class="bd-form-group">
			<label for="<?php echo esc_attr( $id ); ?>">
				<?php echo esc_html( $label ); ?>
			</label>
			<div class="bd-password-wrapper">
				<input type="password" 
					id="<?php echo esc_attr( $id ); ?>" 
					name="<?php echo esc_attr( $name ); ?>" 
					required 
					autocomplete="<?php echo esc_attr( $autocomplete ); ?>"
					<?php if ( $minlength ) : ?>
						minlength="<?php echo esc_attr( $minlength ); ?>"
					<?php endif; ?>
					placeholder="<?php echo esc_attr( $placeholder ); ?>">
				<button type="button" class="bd-password-toggle" aria-label="<?php esc_attr_e( 'Show password', 'business-directory' ); ?>">
					<svg class="bd-icon-eye" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
						<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
						<circle cx="12" cy="12" r="3"></circle>
					</svg>
					<svg class="bd-icon-eye-off" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: none;">
						<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
						<line x1="1" y1="1" x2="23" y2="23"></line>
					</svg>
				</button>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render modal HTML in footer
	 */
	public static function render_modal() {
		// Skip admin pages.
		if ( is_admin() ) {
			return;
		}

		// Get areas for city select.
		$areas = get_terms(
			array(
				'taxonomy'   => 'bd_area',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		?>
		<!-- BD Auth Modal -->
		<div class="bd-modal bd-auth-modal" id="bd-auth-modal" style="display: none;">
			<div class="bd-modal-backdrop"></div>
			<div class="bd-modal-container">
				<div class="bd-modal-content">

					<button type="button" class="bd-modal-close" aria-label="<?php esc_attr_e( 'Close', 'business-directory' ); ?>">
						<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
							<line x1="18" y1="6" x2="6" y2="18"></line>
							<line x1="6" y1="6" x2="18" y2="18"></line>
						</svg>
					</button>

					<!-- Tab Navigation -->
					<div class="bd-auth-tabs">
						<button type="button" class="bd-auth-tab active" data-tab="login">
							<?php esc_html_e( 'Sign In', 'business-directory' ); ?>
						</button>
						<button type="button" class="bd-auth-tab" data-tab="register">
							<?php esc_html_e( 'Create Account', 'business-directory' ); ?>
						</button>
					</div>

					<!-- Messages -->
					<div class="bd-auth-messages"></div>

					<!-- Login Panel -->
					<div class="bd-auth-panel active" data-panel="login">
						<?php echo self::render_social_login(); ?>

						<div class="bd-auth-divider">
							<span><?php esc_html_e( 'or', 'business-directory' ); ?></span>
						</div>

						<form class="bd-auth-form" id="bd-modal-login-form">
							<input type="hidden" name="action" value="bd_ajax_login">
							<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'bd_auth_nonce' ) ); ?>">
							<input type="hidden" name="redirect_to" value="">

							<div class="bd-form-group">
								<label for="bd-modal-login-username">
									<?php esc_html_e( 'Username or Email', 'business-directory' ); ?>
								</label>
								<input type="text" 
									id="bd-modal-login-username" 
									name="username" 
									required 
									autocomplete="username"
									placeholder="<?php esc_attr_e( 'Enter your username or email', 'business-directory' ); ?>">
							</div>

							<?php
							echo self::render_password_field(
								'bd-modal-login-password',
								'password',
								__( 'Password', 'business-directory' ),
								__( 'Enter your password', 'business-directory' ),
								'current-password'
							);
							?>

							<div class="bd-form-row bd-form-row-flex">
								<label class="bd-checkbox-label">
									<input type="checkbox" name="remember" value="1">
									<span><?php esc_html_e( 'Remember me', 'business-directory' ); ?></span>
								</label>
								<a href="#" class="bd-forgot-link" data-show-panel="reset">
									<?php esc_html_e( 'Forgot password?', 'business-directory' ); ?>
								</a>
							</div>

							<button type="submit" class="bd-btn bd-btn-primary bd-btn-full">
								<?php esc_html_e( 'Sign In', 'business-directory' ); ?>
							</button>
						</form>
					</div>

					<!-- Register Panel -->
					<div class="bd-auth-panel" data-panel="register">
						<?php echo self::render_social_login( 'register' ); ?>

						<div class="bd-auth-divider">
							<span><?php esc_html_e( 'or create with email', 'business-directory' ); ?></span>
						</div>

						<form class="bd-auth-form" id="bd-modal-register-form">
							<input type="hidden" name="action" value="bd_ajax_register">
							<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'bd_auth_nonce' ) ); ?>">
							<input type="hidden" name="redirect_to" value="">

							<div class="bd-form-row bd-form-row-2col">
								<div class="bd-form-group">
									<label for="bd-modal-register-username">
										<?php esc_html_e( 'Username', 'business-directory' ); ?>
									</label>
									<input type="text" 
										id="bd-modal-register-username" 
										name="username" 
										required 
										autocomplete="username"
										minlength="3"
										placeholder="<?php esc_attr_e( 'Choose a username', 'business-directory' ); ?>">
								</div>

								<div class="bd-form-group">
									<label for="bd-modal-register-email">
										<?php esc_html_e( 'Email', 'business-directory' ); ?>
									</label>
									<input type="email" 
										id="bd-modal-register-email" 
										name="email" 
										required 
										autocomplete="email"
										placeholder="<?php esc_attr_e( 'your@email.com', 'business-directory' ); ?>">
								</div>
							</div>

							<div class="bd-form-row bd-form-row-2col">
								<?php
								echo self::render_password_field(
									'bd-modal-register-password',
									'password',
									__( 'Password', 'business-directory' ),
									__( '8+ characters', 'business-directory' ),
									'new-password',
									8
								);
								?>

								<div class="bd-form-group">
									<label for="bd-modal-register-city">
										<?php esc_html_e( 'Your City', 'business-directory' ); ?>
									</label>
									<select id="bd-modal-register-city" name="city" class="bd-form-select">
										<option value=""><?php esc_html_e( 'Select city', 'business-directory' ); ?></option>
										<?php if ( ! is_wp_error( $areas ) && ! empty( $areas ) ) : ?>
											<?php foreach ( $areas as $area ) : ?>
												<option value="<?php echo esc_attr( $area->slug ); ?>">
													<?php echo esc_html( $area->name ); ?>
												</option>
											<?php endforeach; ?>
										<?php endif; ?>
										<option value="other"><?php esc_html_e( 'Other', 'business-directory' ); ?></option>
									</select>
								</div>
							</div>

							<div class="bd-form-group">
								<label class="bd-checkbox-label">
									<input type="checkbox" name="terms" value="1" required>
									<span>
										<?php
										printf(
											/* translators: %s: link to terms page */
											esc_html__( 'I agree to the %s', 'business-directory' ),
											'<a href="' . esc_url( home_url( '/terms/' ) ) . '" target="_blank">' .
											esc_html__( 'Terms of Service', 'business-directory' ) . '</a>'
										);
										?>
									</span>
								</label>
							</div>

							<button type="submit" class="bd-btn bd-btn-primary bd-btn-full">
								<?php esc_html_e( 'Create Account', 'business-directory' ); ?>
							</button>
						</form>
					</div>

					<!-- Reset Panel -->
					<div class="bd-auth-panel" data-panel="reset">
						<div class="bd-auth-reset-intro">
							<p>
								<?php esc_html_e( 'Enter your username or email and we\'ll send you a reset link.', 'business-directory' ); ?>
							</p>
						</div>

						<form class="bd-auth-form" id="bd-modal-reset-form">
							<input type="hidden" name="action" value="bd_ajax_reset_password">
							<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'bd_auth_nonce' ) ); ?>">

							<div class="bd-form-group">
								<label for="bd-modal-reset-login">
									<?php esc_html_e( 'Username or Email', 'business-directory' ); ?>
								</label>
								<input type="text" 
									id="bd-modal-reset-login" 
									name="user_login" 
									required 
									placeholder="<?php esc_attr_e( 'Enter your username or email', 'business-directory' ); ?>">
							</div>

							<button type="submit" class="bd-btn bd-btn-primary bd-btn-full">
								<?php esc_html_e( 'Send Reset Link', 'business-directory' ); ?>
							</button>
						</form>

						<p class="bd-auth-back-link">
							<a href="#" data-show-panel="login">
								&larr; <?php esc_html_e( 'Back to Sign In', 'business-directory' ); ?>
							</a>
						</p>
					</div>

				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render social login buttons
	 *
	 * @param string $context Context: login or register.
	 * @return string
	 */
	private static function render_social_login( $context = 'login' ) {
		ob_start();
		?>
		<div class="bd-social-login">
			<?php
			if ( shortcode_exists( 'nextend_social_login' ) ) {
				echo do_shortcode( '[nextend_social_login]' );
			}
			?>
		</div>
		<?php
		return ob_get_clean();
	}
}
