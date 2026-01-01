<?php
/**
 * QR Code Generator
 *
 * Generates QR codes for businesses with tracking.
 *
 * @package BusinessDirectory
 */

namespace BD\BusinessTools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class QRGenerator
 */
class QRGenerator {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Register QR redirect endpoint.
		add_action( 'init', array( $this, 'register_rewrite_rules' ) );
		add_action( 'template_redirect', array( $this, 'handle_qr_redirect' ) );

		// REST endpoint for QR generation.
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );

		// Flush rewrite rules on plugin activation or when needed.
		add_action( 'admin_init', array( $this, 'maybe_flush_rules' ) );
	}

	/**
	 * Flush rewrite rules if needed.
	 */
	public function maybe_flush_rules() {
		if ( get_option( 'bd_qr_rules_flushed' ) !== '1.0' ) {
			flush_rewrite_rules();
			update_option( 'bd_qr_rules_flushed', '1.0' );
		}
	}

	/**
	 * Register rewrite rules for QR tracking.
	 */
	public function register_rewrite_rules() {
		add_rewrite_rule(
			'^qr/([a-zA-Z0-9]+)/?$',
			'index.php?bd_qr_code=$matches[1]',
			'top'
		);
		add_rewrite_tag( '%bd_qr_code%', '([a-zA-Z0-9]+)' );
	}

	/**
	 * Handle QR code redirect.
	 */
	public function handle_qr_redirect() {
		$qr_code = get_query_var( 'bd_qr_code' );

		if ( ! $qr_code ) {
			return;
		}

		// Decode QR code.
		$data = $this->decode_qr_code( $qr_code );

		if ( ! $data || ! isset( $data['business_id'] ) ) {
			wp_safe_redirect( home_url() );
			exit;
		}

		// Track the scan.
		$this->track_scan( $data['business_id'], $data['type'] ?? 'listing' );

		// Redirect to destination.
		$url = $this->get_destination_url( $data['business_id'], $data['type'] ?? 'listing' );
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes() {
		register_rest_route(
			'bd/v1',
			'/qr/generate',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_generate' ),
				'permission_callback' => function () {
					return is_user_logged_in();
				},
				'args'                => array(
					'business_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'type'        => array(
						'required'          => false,
						'type'              => 'string',
						'default'           => 'review',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'format'      => array(
						'required'          => false,
						'type'              => 'string',
						'default'           => 'png',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// REST-based redirect endpoint (fallback for when rewrite rules don't work).
		register_rest_route(
			'bd/v1',
			'/qr/go/(?P<code>[a-zA-Z0-9]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_redirect' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'code' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * REST endpoint: Handle QR redirect.
	 *
	 * @param \WP_REST_Request $request Request object.
	 */
	public function rest_redirect( $request ) {
		$code = $request->get_param( 'code' );
		$data = $this->decode_qr_code( $code );

		if ( ! $data || ! isset( $data['business_id'] ) ) {
			wp_safe_redirect( home_url() );
			exit;
		}

		// Track the scan.
		$this->track_scan( $data['business_id'], $data['type'] ?? 'listing' );

		// Redirect to destination.
		$url = $this->get_destination_url( $data['business_id'], $data['type'] ?? 'listing' );
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * REST endpoint: Generate QR code.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function rest_generate( $request ) {
		$business_id = $request->get_param( 'business_id' );
		$type        = $request->get_param( 'type' );
		$format      = $request->get_param( 'format' );

		$result = self::generate( $business_id, $type, $format );

		if ( ! $result ) {
			return new \WP_REST_Response(
				array( 'error' => 'Failed to generate QR code' ),
				500
			);
		}

		return new \WP_REST_Response( $result );
	}

	/**
	 * Generate QR code.
	 *
	 * @param int    $business_id Business ID.
	 * @param string $type        QR type (review, listing).
	 * @param string $format      Output format (png, svg, pdf).
	 * @return array|false Result array or false.
	 */
	public static function generate( $business_id, $type = 'review', $format = 'png' ) {
		$business = get_post( $business_id );
		if ( ! $business || ! in_array( $business->post_type, array( 'bd_business', 'business' ), true ) ) {
			return false;
		}

		// Generate QR code string.
		$qr_code = self::encode_qr_code( $business_id, $type );

		// Use REST endpoint URL (more reliable than rewrite rules).
		$qr_url = rest_url( 'bd/v1/qr/go/' . $qr_code );

		// Get destination URL for preview.
		$destination = self::get_destination_url( $business_id, $type );

		// Generate QR image.
		switch ( $format ) {
			case 'svg':
				$file_url = self::generate_svg( $qr_url, $business_id, $type );
				break;

			case 'pdf':
				$file_url = self::generate_pdf( $qr_url, $business, $type );
				break;

			case 'png':
			default:
				$file_url = self::generate_png( $qr_url, $business_id, $type );
				break;
		}

		return array(
			'qr_url'      => $qr_url,
			'destination' => $destination,
			'file_url'    => $file_url,
			'format'      => $format,
			'business'    => $business->post_title,
		);
	}

	/**
	 * Encode QR code data.
	 *
	 * @param int    $business_id Business ID.
	 * @param string $type        QR type.
	 * @return string Encoded string.
	 */
	private static function encode_qr_code( $business_id, $type ) {
		// Simple encoding: base36(business_id) + type_char.
		$type_char = 'r'; // review.
		if ( 'listing' === $type ) {
			$type_char = 'l';
		} elseif ( 'menu' === $type ) {
			$type_char = 'm';
		}

		return base_convert( (string) $business_id, 10, 36 ) . $type_char;
	}

	/**
	 * Decode QR code data.
	 *
	 * @param string $code QR code string.
	 * @return array|false Decoded data or false.
	 */
	private function decode_qr_code( $code ) {
		if ( strlen( $code ) < 2 ) {
			return false;
		}

		$type_char    = substr( $code, -1 );
		$business_b36 = substr( $code, 0, -1 );

		$business_id = base_convert( $business_b36, 36, 10 );

		$type = 'listing';
		if ( 'r' === $type_char ) {
			$type = 'review';
		} elseif ( 'm' === $type_char ) {
			$type = 'menu';
		}

		return array(
			'business_id' => (int) $business_id,
			'type'        => $type,
		);
	}

	/**
	 * Get destination URL for QR type.
	 *
	 * @param int    $business_id Business ID.
	 * @param string $type        QR type.
	 * @return string URL.
	 */
	private static function get_destination_url( $business_id, $type ) {
		$base_url = get_permalink( $business_id );

		switch ( $type ) {
			case 'review':
				return $base_url . '#write-review';

			case 'menu':
				return $base_url . '#menu';

			case 'listing':
			default:
				return $base_url;
		}
	}

	/**
	 * Track QR scan.
	 *
	 * @param int    $business_id Business ID.
	 * @param string $type        QR type.
	 */
	private function track_scan( $business_id, $type ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bd_qr_scans';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			$table,
			array(
				'business_id' => $business_id,
				'qr_type'     => $type,
				'ip_address'  => $this->get_client_ip(),
				'user_agent'  => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
				'referrer'    => isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '',
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Generate PNG QR code.
	 *
	 * @param string $data        Data to encode.
	 * @param int    $business_id Business ID.
	 * @param string $type        QR type.
	 * @return string File URL.
	 */
	private static function generate_png( $data, $business_id, $type ) {
		// Return direct URL to QR service - browser loads it directly.
		// This avoids server-side HTTP requests which may be blocked.
		$size = 300;
		return 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size . '&data=' . rawurlencode( $data );
	}

	/**
	 * Generate SVG QR code.
	 *
	 * @param string $data        Data to encode.
	 * @param int    $business_id Business ID.
	 * @param string $type        QR type.
	 * @return string File URL.
	 */
	private static function generate_svg( $data, $business_id, $type ) {
		// For SVG, we'll generate a simple QR code.
		// In production, you'd use a library like BaconQrCode.
		// For now, redirect to PNG endpoint.
		return self::generate_png( $data, $business_id, $type );
	}

	/**
	 * Generate PDF with QR code.
	 *
	 * @param string   $qr_url   QR code URL.
	 * @param \WP_Post $business Business post.
	 * @param string   $type     QR type.
	 * @return string File URL.
	 */
	private static function generate_pdf( $qr_url, $business, $type ) {
		$upload_dir = wp_upload_dir();
		$qr_dir     = $upload_dir['basedir'] . '/bd-qr-codes/';
		$filename   = 'qr-' . $business->ID . '-' . $type . '.pdf';
		$filepath   = $qr_dir . $filename;
		$file_url   = $upload_dir['baseurl'] . '/bd-qr-codes/' . $filename;

		// First generate the PNG QR code.
		$png_url = self::generate_png( $qr_url, $business->ID, $type );

		// Create simple PDF using HTML2PDF approach.
		$site_name = get_bloginfo( 'name' );

		$action_text = __( 'Leave us a review', 'business-directory' );
		if ( 'listing' === $type ) {
			$action_text = __( 'View our listing', 'business-directory' );
		}

		// Generate HTML for PDF.
		$html = self::get_pdf_template( $business->post_title, $png_url, $action_text, $site_name );

		// Save HTML as a downloadable file (simple approach).
		// In production, use TCPDF or mPDF for proper PDF generation.
		$html_filename = 'qr-' . $business->ID . '-' . $type . '.html';
		$html_filepath = $qr_dir . $html_filename;
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $html_filepath, $html );

		return $upload_dir['baseurl'] . '/bd-qr-codes/' . $html_filename;
	}

	/**
	 * Get PDF template HTML.
	 *
	 * @param string $business_name Business name.
	 * @param string $qr_image_url  QR image URL.
	 * @param string $action_text   Action text.
	 * @param string $site_name     Site name.
	 * @return string HTML.
	 */
	private static function get_pdf_template( $business_name, $qr_image_url, $action_text, $site_name ) {
		return '<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<title>QR Code - ' . esc_html( $business_name ) . '</title>
	<style>
		@page { size: A6 portrait; margin: 10mm; }
		body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; text-align: center; margin: 0; padding: 20px; }
		.container { border: 2px solid #1a3a4a; border-radius: 12px; padding: 30px 20px; max-width: 300px; margin: 0 auto; }
		.header { font-size: 24px; color: #1a3a4a; margin-bottom: 20px; }
		.qr-code { margin: 20px 0; }
		.qr-code img { width: 200px; height: 200px; }
		.action-text { font-size: 16px; color: #5d7a8c; margin: 20px 0 10px; }
		.branding { font-size: 14px; color: #7a9eb8; border-top: 1px solid #a8c4d4; padding-top: 15px; margin-top: 20px; }
		.print-btn { margin-top: 20px; padding: 10px 20px; background: #1a3a4a; color: white; border: none; border-radius: 6px; cursor: pointer; }
		@media print { .print-btn { display: none; } }
	</style>
</head>
<body>
	<div class="container">
		<div class="header">❤️ ' . esc_html( $action_text ) . '</div>
		<div class="qr-code">
			<img src="' . esc_url( $qr_image_url ) . '" alt="QR Code">
		</div>
		<div class="action-text">Scan to ' . esc_html( strtolower( $action_text ) ) . '</div>
		<div class="branding">📍 ' . esc_html( $site_name ) . '</div>
	</div>
	<button class="print-btn" onclick="window.print()">Print This</button>
</body>
</html>';
	}

	/**
	 * Get client IP address.
	 *
	 * @return string IP address.
	 */
	private function get_client_ip() {
		$ip_keys = array(
			'HTTP_CF_CONNECTING_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_REAL_IP',
			'REMOTE_ADDR',
		);

		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				if ( strpos( $ip, ',' ) !== false ) {
					$ip = trim( explode( ',', $ip )[0] );
				}
				return $ip;
			}
		}

		return '0.0.0.0';
	}
}
