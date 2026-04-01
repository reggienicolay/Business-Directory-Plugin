<?php

namespace BD\Notifications;

if ( ! defined( 'ABSPATH' ) ) {
	exit; }

class Email {

	public static function notify_new_submission( $submission_id ) {
		$submission = \BD\DB\SubmissionsTable::get( $submission_id );

		if ( ! $submission ) {
			error_log( '[BD Email] Cannot send notification — submission #' . $submission_id . ' not found in database.' );
			return false;
		}

		$to      = self::get_notification_emails();
		$subject = sprintf(
		// translators: Placeholder for dynamic value.
			__( '[%s] New Business Submission', 'business-directory' ),
			get_bloginfo( 'name' )
		);

		$moderate_url = admin_url( 'admin.php?page=bd-pending-submissions' );

		$message = sprintf(
			"New business submission:\n\nBusiness: %s\nSubmitted by: %s (%s)\n\nModerate: %s",
			$submission['business_data']['title'] ?? 'Untitled',
			$submission['submitter_name'] ?? 'Anonymous',
			$submission['submitter_email'] ?? '',
			$moderate_url
		);

		$sent = wp_mail( $to, $subject, $message );

		if ( ! $sent ) {
			error_log( '[BD Email] wp_mail() failed for submission #' . $submission_id . '. Recipients: ' . ( is_array( $to ) ? implode( ', ', $to ) : $to ) );
		} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[BD Email] Notification sent for submission #' . $submission_id . ' to ' . ( is_array( $to ) ? implode( ', ', $to ) : $to ) );
		}

		return $sent;
	}

	public static function notify_new_review( $review_id ) {
		global $wpdb;

		$reviews_table = $wpdb->prefix . 'bd_reviews';
		$review        = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $reviews_table WHERE id = %d",
				$review_id
			),
			ARRAY_A
		);

		if ( ! $review ) {
			error_log( '[BD Email] Cannot send review notification — review #' . $review_id . ' not found in database.' );
			return false;
		}

		$business = get_post( $review['business_id'] );

		$to      = self::get_notification_emails();
		$subject = sprintf(
		// translators: Placeholder for dynamic value.
			__( '[%s] New Review', 'business-directory' ),
			get_bloginfo( 'name' )
		);

		$moderate_url = admin_url( 'admin.php?page=bd-pending-reviews' );

		$message = sprintf(
			"New review submitted:\n\nBusiness: %s\nRating: %d/5\nReviewer: %s\n\nModerate: %s",
			$business->post_title,
			$review['rating'],
			$review['author_name'] ?? 'Anonymous',
			$moderate_url
		);

		$sent = wp_mail( $to, $subject, $message );

		if ( ! $sent ) {
			error_log( '[BD Email] wp_mail() failed for review #' . $review_id . '. Recipients: ' . ( is_array( $to ) ? implode( ', ', $to ) : $to ) );
		}

		return $sent;
	}

	private static function get_notification_emails() {
		$emails = get_option( 'bd_notification_emails', '' );

		if ( empty( $emails ) ) {
			return get_option( 'admin_email' );
		}

		return array_map( 'trim', explode( ',', $emails ) );
	}
}
