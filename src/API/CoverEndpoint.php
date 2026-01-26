<?php
/**
 * Cover REST API Endpoint
 *
 * REST API endpoints for list cover media operations.
 *
 * @package BusinessDirectory
 * @since 1.2.0
 */

namespace BD\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BD\Lists\CoverManager;
use BD\Lists\ListManager;

class CoverEndpoint {

	/**
	 * Initialize REST routes
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register REST routes
	 */
	public static function register_routes() {
		$namespace = 'bd/v1';

		// Upload/set cover image
		register_rest_route(
			$namespace,
			'/lists/(?P<id>\d+)/cover',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'upload_cover' ),
					'permission_callback' => array( __CLASS__, 'check_user_logged_in' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'validate_callback' => function ( $param ) {
								return is_numeric( $param );
							},
						),
					),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( __CLASS__, 'recrop_cover' ),
					'permission_callback' => array( __CLASS__, 'check_user_logged_in' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'validate_callback' => function ( $param ) {
								return is_numeric( $param );
							},
						),
					),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( __CLASS__, 'remove_cover' ),
					'permission_callback' => array( __CLASS__, 'check_user_logged_in' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'validate_callback' => function ( $param ) {
								return is_numeric( $param );
							},
						),
					),
				),
			)
		);

		// Set video cover
		register_rest_route(
			$namespace,
			'/lists/(?P<id>\d+)/cover/video',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'set_video_cover' ),
				'permission_callback' => array( __CLASS__, 'check_user_logged_in' ),
				'args'                => array(
					'id'        => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
					'video_url' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'esc_url_raw',
					),
				),
			)
		);

		// Upload custom thumbnail for video cover
		register_rest_route(
			$namespace,
			'/lists/(?P<id>\d+)/cover/video/thumbnail',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'upload_video_thumbnail' ),
				'permission_callback' => array( __CLASS__, 'check_user_logged_in' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
				),
			)
		);

		// Get cover data
		register_rest_route(
			$namespace,
			'/lists/(?P<id>\d+)/cover',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_cover' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id' => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					),
				),
			)
		);

		// Parse video URL (helper endpoint)
		register_rest_route(
			$namespace,
			'/cover/parse-video',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'parse_video_url' ),
				'permission_callback' => array( __CLASS__, 'check_user_logged_in' ),
				'args'                => array(
					'url' => array(
						'required' => true,
						'type'     => 'string',
					),
				),
			)
		);
	}

	/**
	 * Permission callback
	 */
	public static function check_user_logged_in() {
		return is_user_logged_in();
	}

	/**
	 * Upload cover image
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function upload_cover( $request ) {
		$list_id = absint( $request['id'] );
		$user_id = get_current_user_id();

		// Get uploaded files
		$files = $request->get_file_params();

		if ( empty( $files['cropped'] ) ) {
			return new \WP_Error(
				'no_file',
				'No cropped image provided',
				array( 'status' => 400 )
			);
		}

		// Get crop data from body
		$body      = $request->get_json_params();
		$crop_data = array();

		if ( ! empty( $body['crop_data'] ) ) {
			$crop_data = $body['crop_data'];
		} else {
			// Try form data
			$crop_data = array(
				'x'             => $request->get_param( 'crop_x' ),
				'y'             => $request->get_param( 'crop_y' ),
				'width'         => $request->get_param( 'crop_width' ),
				'height'        => $request->get_param( 'crop_height' ),
				'zoom'          => $request->get_param( 'crop_zoom' ),
				'rotation'      => $request->get_param( 'crop_rotation' ),
				'flip_h'        => $request->get_param( 'crop_flip_h' ),
				'flip_v'        => $request->get_param( 'crop_flip_v' ),
				'source_width'  => $request->get_param( 'source_width' ),
				'source_height' => $request->get_param( 'source_height' ),
			);
		}

		// Optional original file for re-cropping support
		$original_file = ! empty( $files['original'] ) ? $files['original'] : null;

		$result = CoverManager::upload_cover( $list_id, $user_id, $files['cropped'], $crop_data, $original_file );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Re-crop existing cover
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function recrop_cover( $request ) {
		$list_id = absint( $request['id'] );
		$user_id = get_current_user_id();

		$files = $request->get_file_params();

		if ( empty( $files['cropped'] ) ) {
			return new \WP_Error(
				'no_file',
				'No cropped image provided',
				array( 'status' => 400 )
			);
		}

		$body      = $request->get_json_params();
		$crop_data = $body['crop_data'] ?? array();

		$result = CoverManager::recrop_cover( $list_id, $user_id, $crop_data, $files['cropped'] );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Remove cover
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function remove_cover( $request ) {
		$list_id = absint( $request['id'] );
		$user_id = get_current_user_id();

		$result = CoverManager::remove_cover( $list_id, $user_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Set video cover
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function set_video_cover( $request ) {
		$list_id = absint( $request['id'] );
		$user_id = get_current_user_id();

		// Get video_url from params (handles both JSON body and form data)
		$video_url = $request->get_param( 'video_url' );

		// If not found in params, try JSON body directly
		if ( empty( $video_url ) ) {
			$json      = $request->get_json_params();
			$video_url = isset( $json['video_url'] ) ? $json['video_url'] : '';
		}

		if ( empty( $video_url ) ) {
			return new \WP_Error(
				'missing_url',
				'Please enter a valid YouTube or Vimeo URL',
				array( 'status' => 400 )
			);
		}

		$result = CoverManager::set_video_cover( $list_id, $user_id, $video_url );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Upload custom thumbnail for video cover
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function upload_video_thumbnail( $request ) {
		$list_id = absint( $request['id'] );
		$user_id = get_current_user_id();

		$files = $request->get_file_params();

		if ( empty( $files['cropped'] ) ) {
			return new \WP_Error(
				'no_file',
				'No thumbnail image provided',
				array( 'status' => 400 )
			);
		}

		// Get crop data
		$crop_data = array(
			'x'             => $request->get_param( 'crop_x' ),
			'y'             => $request->get_param( 'crop_y' ),
			'width'         => $request->get_param( 'crop_width' ),
			'height'        => $request->get_param( 'crop_height' ),
			'zoom'          => $request->get_param( 'crop_zoom' ),
			'rotation'      => $request->get_param( 'crop_rotation' ),
			'source_width'  => $request->get_param( 'source_width' ),
			'source_height' => $request->get_param( 'source_height' ),
		);

		$result = CoverManager::upload_video_thumbnail( $list_id, $user_id, $files['cropped'], $crop_data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Get cover data for a list
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function get_cover( $request ) {
		$list_id = absint( $request['id'] );

		$list = ListManager::get_list( $list_id );

		if ( ! $list ) {
			return new \WP_Error(
				'not_found',
				'List not found',
				array( 'status' => 404 )
			);
		}

		// Check visibility
		$current_user_id = get_current_user_id();
		if ( 'private' === $list['visibility'] && (int) $list['user_id'] !== $current_user_id ) {
			return new \WP_Error(
				'forbidden',
				'This list is private',
				array( 'status' => 403 )
			);
		}

		$cover_data = CoverManager::get_cover_data( $list );

		return rest_ensure_response( $cover_data );
	}

	/**
	 * Parse video URL helper
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function parse_video_url( $request ) {
		$url = $request->get_param( 'url' );

		$result = CoverManager::parse_video_url( $url );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Add thumbnail URL
		$result['thumbnail_url'] = CoverManager::get_video_thumbnail_url( $result['platform'], $result['id'] );
		$result['embed_url']     = CoverManager::get_video_embed_url( $result['platform'], $result['id'] );

		return rest_ensure_response( $result );
	}
}

// Initialize
CoverEndpoint::init();
