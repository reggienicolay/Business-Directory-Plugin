<?php
/**
 * Badge Share Card
 *
 * Renders branded badge share card for social sharing preview and modal.
 * Port of ShareCard component from badge-v10.jsx.
 *
 * @package BusinessDirectory
 * @subpackage Social
 */

namespace BD\Social;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BD\Gamification\BadgeSystem;
use BD\Gamification\BadgeSVG;

class BadgeShareCard {

	/**
	 * Render a share card for a badge.
	 *
	 * @param string $badge_key Badge key.
	 * @param int    $user_id   User who earned the badge.
	 * @return string HTML string.
	 */
	public static function render( string $badge_key, int $user_id = 0 ): string {
		$badge = BadgeSystem::BADGES[ $badge_key ] ?? null;
		if ( ! $badge ) {
			return '';
		}

		$rarity = $badge['rarity'] ?? 'common';
		$m      = BadgeSVG::get_material( $rarity );
		$dark   = ( 'special' === $rarity );
		$warm   = ( 'legendary' === $rarity );

		// User info.
		$user_name   = '';
		$user_initial = '?';
		$earned_date  = '';
		if ( $user_id ) {
			$user        = get_userdata( $user_id );
			$user_name   = $user ? $user->display_name : '';
			$user_initial = strtoupper( substr( $user_name, 0, 1 ) );
			// Get earned date from badge awards if available.
			$earned_date = self::get_earned_date( $badge_key, $user_id );
		}

		// Background gradient based on rarity.
		if ( $dark ) {
			$bg = 'linear-gradient(165deg, #0a1a22, #133453, #0f2530)';
		} elseif ( $warm ) {
			$bg = 'linear-gradient(165deg, #fffbeb, #fef3c7, #fde68a)';
		} else {
			$bg = 'linear-gradient(165deg, #fafafa, #f2f2f2, #fafafa)';
		}

		// Text colors.
		$header_color = $dark ? '#C9A227' : ( $warm ? '#9A7B1A' : $m['textColor'] );
		$title_color  = $dark ? '#f5c522' : ( $warm ? '#78350f' : '#1e293b' );
		$desc_color   = $dark ? '#7a9eb8' : '#64748b';
		$pill_bg      = $dark ? 'rgba(201,162,39,0.1)' : 'rgba(0,0,0,0.04)';
		$pill_border  = $dark ? 'rgba(201,162,39,0.18)' : 'rgba(0,0,0,0.06)';
		$pill_dot     = $dark ? '#C9A227' : ( $warm ? '#d4a830' : $m['textColor'] );
		$pill_text    = $dark ? '#C9A227' : ( $warm ? '#92400e' : $m['textColor'] );
		$divider      = $dark ? 'rgba(201,162,39,0.1)' : 'rgba(0,0,0,0.06)';
		$avatar_bg    = $dark ? 'rgba(201,162,39,0.1)' : ( $warm ? 'rgba(154,123,26,0.06)' : '#f1f5f9' );
		$avatar_color = $dark ? '#C9A227' : ( $warm ? '#92400e' : '#64748b' );
		$avatar_border = $dark ? 'rgba(201,162,39,0.18)' : 'rgba(0,0,0,0.06)';
		$name_color   = $dark ? '#f0f7fa' : '#1e293b';
		$date_color   = $dark ? '#7a9eb8' : '#94a3b8';

		ob_start();
		?>
		<div class="bd-share-card" style="width: 100%; max-width: 400px; border-radius: 28px; overflow: hidden; background: <?php echo esc_attr( $bg ); ?>; box-shadow: 0 28px 72px <?php echo esc_attr( $m['glow'] ); ?>, 0 4px 20px rgba(0,0,0,0.1);">
			<div style="padding: 40px 32px 32px; text-align: center; position: relative;">

				<!-- Header -->
				<div style="font-size: 11px; font-weight: 700; letter-spacing: 4px; color: <?php echo esc_attr( $header_color ); ?>; opacity: 0.5; margin-bottom: 28px; text-transform: uppercase;">
					Love TriValley
				</div>

				<!-- Badge -->
				<div style="display: flex; justify-content: center; margin-bottom: 24px;">
					<?php echo BadgeSVG::render( $badge_key, array( 'size' => 180, 'earned' => true, 'animate' => false ) ); ?>
				</div>

				<!-- Badge Name -->
				<div style="font-size: 26px; font-weight: 800; color: <?php echo esc_attr( $title_color ); ?>; margin-bottom: 8px; letter-spacing: -0.5px;">
					<?php echo esc_html( $badge['name'] ); ?>
				</div>

				<!-- Description -->
				<div style="font-size: 15px; color: <?php echo esc_attr( $desc_color ); ?>; line-height: 1.55; max-width: 280px; margin: 0 auto 22px;">
					<?php echo esc_html( $badge['description'] ); ?>
				</div>

				<!-- Rarity Pill -->
				<div style="display: inline-flex; align-items: center; gap: 8px; padding: 7px 18px; border-radius: 24px; background: <?php echo esc_attr( $pill_bg ); ?>; border: 1px solid <?php echo esc_attr( $pill_border ); ?>;">
					<span style="width: 8px; height: 8px; border-radius: 50%; background: <?php echo esc_attr( $pill_dot ); ?>;"></span>
					<span style="font-size: 12px; font-weight: 700; color: <?php echo esc_attr( $pill_text ); ?>;">
						<?php echo esc_html( $m['rarity'] ); ?> &middot; +<?php echo (int) ( $badge['points'] ?? 0 ); ?> pts
					</span>
				</div>

				<?php if ( $user_id && $user_name ) : ?>
				<!-- User Attribution -->
				<div style="margin-top: 26px; padding-top: 20px; border-top: 1px solid <?php echo esc_attr( $divider ); ?>; display: flex; align-items: center; justify-content: center; gap: 12px;">
					<div style="width: 36px; height: 36px; border-radius: 50%; background: <?php echo esc_attr( $avatar_bg ); ?>; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 700; color: <?php echo esc_attr( $avatar_color ); ?>; border: 1px solid <?php echo esc_attr( $avatar_border ); ?>;">
						<?php echo esc_html( $user_initial ); ?>
					</div>
					<div style="text-align: left;">
						<div style="font-size: 14px; font-weight: 600; color: <?php echo esc_attr( $name_color ); ?>;">
							<?php echo esc_html( $user_name ); ?>
						</div>
						<?php if ( $earned_date ) : ?>
						<div style="font-size: 11px; color: <?php echo esc_attr( $date_color ); ?>;">
							Earned <?php echo esc_html( $earned_date ); ?>
						</div>
						<?php endif; ?>
					</div>
				</div>
				<?php endif; ?>

			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render the full share modal content (card + share buttons).
	 *
	 * @param string $badge_key Badge key.
	 * @param int    $user_id   User ID.
	 * @return string HTML string.
	 */
	public static function render_modal( string $badge_key, int $user_id = 0 ): string {
		$badge = BadgeSystem::BADGES[ $badge_key ] ?? null;
		if ( ! $badge ) {
			return '';
		}

		$share_url = add_query_arg(
			array(
				'badge'   => $badge_key,
				'user_id' => $user_id,
			),
			home_url( '/badges/' )
		);

		$share_text = sprintf(
			'I just earned the "%s" badge on Love TriValley! %s',
			$badge['name'],
			$badge['description']
		);

		ob_start();
		?>
		<div class="bd-share-modal" style="position: relative;">
			<button class="bd-share-modal-close" aria-label="Close">&times;</button>
			<div style="padding: 32px 24px; text-align: center;">
				<div style="font-size: 12px; color: #7a9eb8; margin-bottom: 16px; font-weight: 600;">Share Your Achievement</div>
				<?php echo self::render( $badge_key, $user_id ); ?>

				<!-- Share Buttons -->
				<div style="display: flex; gap: 10px; justify-content: center; margin-top: 24px; flex-wrap: wrap;">
					<a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo rawurlencode( $share_url ); ?>&quote=<?php echo rawurlencode( $share_text ); ?>"
					   target="_blank" rel="noopener" class="bd-share-card-btn" style="background: #1877f2; color: white; padding: 10px 20px; border-radius: 12px; text-decoration: none; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;">
						<i class="fab fa-facebook-f"></i> Facebook
					</a>
					<a href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo rawurlencode( $share_url ); ?>"
					   target="_blank" rel="noopener" class="bd-share-card-btn" style="background: #0a66c2; color: white; padding: 10px 20px; border-radius: 12px; text-decoration: none; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;">
						<i class="fab fa-linkedin-in"></i> LinkedIn
					</a>
					<button class="bd-share-card-btn bd-copy-link-btn" data-url="<?php echo esc_attr( $share_url ); ?>"
					   style="background: rgba(255,255,255,0.06); color: #a8c4d4; padding: 10px 20px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.1); font-size: 13px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
						<i class="fas fa-link"></i> Copy Link
					</button>
				</div>

				<div style="font-size: 10px; color: #64748b; margin-top: 16px;">
					+15 points for sharing!
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get the date a user earned a badge.
	 */
	private static function get_earned_date( string $badge_key, int $user_id ): string {
		global $wpdb;
		$table = $wpdb->prefix . 'bd_badge_awards';

		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return '';
		}

		$date = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT awarded_at FROM {$table} WHERE user_id = %d AND badge_key = %s LIMIT 1",
				$user_id,
				$badge_key
			)
		);

		if ( ! $date ) {
			return '';
		}

		$timestamp = strtotime( $date );
		if ( ! $timestamp ) {
			return '';
		}

		return date_i18n( 'M j, Y', $timestamp );
	}
}
