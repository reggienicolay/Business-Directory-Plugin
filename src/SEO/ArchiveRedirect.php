<?php
/**
 * Archive Redirect
 *
 * 301-redirects the default WordPress post-type archive at /places/ (and its
 * paginated variants /places/page/N/) to the canonical browse experience at
 * /explore/. This consolidates SEO equity onto the URLs that are actually
 * linked, indexed, and intended as the user-facing browse surface.
 *
 * Why /places/ exists at all: the bd_business CPT is registered with
 * has_archive => true so individual /places/{slug}/ permalinks work. WordPress
 * automatically creates the bare /places/ archive as a side effect — but it's
 * unlinked, never wired into navigation, and the real browse experience lives
 * at /explore/ (handled by ExploreRouter).
 *
 * Single-business URLs (/places/{slug}/) are NOT redirected. Only the bare
 * archive and its pagination.
 *
 * @package BusinessDirectory
 * @subpackage SEO
 * @since 0.1.14
 */

namespace BD\SEO;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ArchiveRedirect {

	/**
	 * Wire up the redirect.
	 *
	 * Hooked to template_redirect at priority 1 so it runs before the rest of
	 * the template stack but after the main query resolves (so we can call
	 * is_post_type_archive()).
	 */
	public static function init(): void {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_redirect' ), 1 );
	}

	/**
	 * 301 the bd_business archive (and its pagination) to /explore/.
	 */
	public static function maybe_redirect(): void {
		if ( ! is_post_type_archive( 'bd_business' ) ) {
			return;
		}

		// Don't redirect feeds — leave RSS/Atom alone if anything consumes them.
		if ( is_feed() ) {
			return;
		}

		$destination = home_url( '/explore/' );

		/**
		 * Filter the redirect destination.
		 *
		 * Lets satellite plugins or themes route the archive elsewhere if they
		 * have a different canonical browse URL (e.g. a custom landing page).
		 *
		 * @param string $destination Default redirect URL (/explore/).
		 */
		$destination = apply_filters( 'bd_archive_redirect_destination', $destination );

		wp_safe_redirect( esc_url_raw( $destination ), 301, 'Business Directory Archive' );
		exit;
	}
}
