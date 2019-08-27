<?php
/**
 * Handle custom REST routes
 *
 * @package distributor-comments
 */

namespace DT\NbAddon\Comments\Api;

/**
 * Setup actions
 */
function setup() {
	add_action(
		'rest_api_init',
		__NAMESPACE__ . '\register_rest_routes'
	);

}

/**
 * Register REST routes
 */
function register_rest_routes() {
	// We'll insert all post comments on initial distribution
	register_rest_route(
		'wp/v2',
		'/distributor/comments/insert',
		[
			'methods'             => 'POST',
			'args'                => [
				'post_id'      => [
					'required'          => true,
					'validate_callback' => function( $param, $request, $key ) {
						return is_numeric( $param );
					},
				],
				'signature'    => [
					'required'          => true,
					'validate_callback' => function( $param, $request, $key ) {
						return is_string( $param );
					},
				],
				'comment_data' => [
					'required'          => true,
					'validate_callback' => function( $param, $request, $key ) {
						return is_array( $param );
					},
				],
			],
			'callback'            => __NAMESPACE__ . '\insert_comments',
			'permission_callback' => function () {
				return true;
			},
		]
	);

	// Endpoint will receive data on comment update
	register_rest_route(
		'wp/v2',
		'/distributor/comments/update',
		[
			'methods'             => 'POST',
			'args'                => [
				'post_id'      => [
					'required'          => true,
					'validate_callback' => function( $param, $request, $key ) {
						return is_numeric( $param );
					},
				],
				'signature'    => [
					'required'          => true,
					'validate_callback' => function( $param, $request, $key ) {
						return is_string( $param );
					},
				],
				'comment_data' => [
					'required'          => true,
					'validate_callback' => function( $param, $request, $key ) {
						return is_array( $param );
					},
				],
			],
			'callback'            => __NAMESPACE__ . '\update_comments',
			'permission_callback' => function () {
				return true;
			},
		]
	);

	// Endpoint will receive data on comment delete
	register_rest_route(
		'wp/v2',
		'/distributor/comments/delete',
		[
			'methods'             => 'POST',
			'args'                => [
				'post_id'      => [
					'required'          => true,
					'validate_callback' => function( $param, $request, $key ) {
						return is_numeric( $param );
					},
				],
				'signature'    => [
					'required'          => true,
					'validate_callback' => function( $param, $request, $key ) {
						return is_string( $param );
					},
				],
				'comment_data' => [
					'required'          => true,
					'validate_callback' => function( $param, $request, $key ) {
						return is_numeric( $param );
					},
				],
			],
			'callback'            => __NAMESPACE__ . '\delete_comments',
			'permission_callback' => function () {
				return true;
			},
		]
	);

	// Endpoint will receive data on trashing a comment
	register_rest_route(
		'wp/v2',
		'/distributor/comments/trash',
		[
			'methods'             => 'POST',
			'args'                => [
				'post_id'      => [
					'required'          => true,
					'validate_callback' => function( $param, $request, $key ) {
						return is_numeric( $param );
					},
				],
				'signature'    => [
					'required'          => true,
					'validate_callback' => function( $param, $request, $key ) {
						return is_string( $param );
					},
				],
				'comment_data' => [
					'required'          => true,
					'validate_callback' => function( $param, $request, $key ) {
						return is_numeric( $param );
					},
				],
			],
			'callback'            => __NAMESPACE__ . '\trash_comments',
			'permission_callback' => function () {
				return true;
			},
		]
	);

	// Endpoint will receive data on un-trashing a comment
	register_rest_route(
		'wp/v2',
		'/distributor/comments/untrash',
		[
			'methods'             => 'POST',
			'args'                => [
				'post_id'      => [
					'required'          => true,
					'validate_callback' => function( $param, $request, $key ) {
						return is_numeric( $param );
					},
				],
				'signature'    => [
					'required'          => true,
					'validate_callback' => function( $param, $request, $key ) {
						return is_string( $param );
					},
				],
				'comment_data' => [
					'required'          => true,
					'validate_callback' => function( $param, $request, $key ) {
						return is_numeric( $param );
					},
				],
				'comment_status' => [
					'required'          => true,
					'validate_callback' => function( $param, $request, $key ) {
						return is_string( $param );
					},
				],
			],
			'callback'            => __NAMESPACE__ . '\untrash_comments',
			'permission_callback' => function () {
				return true;
			},
		]
	);
}

/**
 * Handle initial comments insert
 *
 * @param \WP_REST_Request $request WP_REST_Request instance.
 * @return array
 */
function insert_comments( \WP_REST_Request $request ) {
	$post_id          = $request->get_param( 'post_id' );
	$signature        = $request->get_param( 'signature' );
	$comment_data     = $request->get_param( 'comment_data' );
	$is_valid_request = \DT\NbAddon\Comments\Utils\validate_request( $post_id, $signature );
	if ( true !== $is_valid_request ) {
		return $is_valid_request;
	}
		// Get current comment counting defer status.
		$defer_status = wp_defer_comment_counting();
		// Set counting defer to false.
		wp_defer_comment_counting( true );
		// Process comments.
	$result = \DT\NbAddon\Comments\Utils\process_comments( $comment_data, $post_id, true );

		// apply deferred counts.
		wp_defer_comment_counting( false );

		/**
			 * Action fired after comments processed in destination
			 *
			 * @since 1.4.2
			 * @param WP_Post         $post    Updated post object.
			 */
			do_action( 'dt_after_comments_processed', $post_id );

		// Set initial defer status.
		wp_defer_comment_counting( $defer_status );
	return $result;
}

/**
 * Handle comments update
 *
 * @param \WP_REST_Request $request WP_REST_Request instance.
 * @return array
 */
function update_comments( \WP_REST_Request $request ) {
	$post_id          = $request->get_param( 'post_id' );
	$signature        = $request->get_param( 'signature' );
	$comment_data     = $request->get_param( 'comment_data' );
	$is_valid_request = \DT\NbAddon\Comments\Utils\validate_request( $post_id, $signature );
	if ( true !== $is_valid_request ) {
		return $is_valid_request;
	}

	// Get current comment counting defer status.
	$defer_status = wp_defer_comment_counting();
	// Set counting defer to false.
	wp_defer_comment_counting( true );
	// Process comments.
	$result = \DT\NbAddon\Comments\Utils\is_assoc( $comment_data ) ?
					\DT\NbAddon\Comments\Utils\process_comments( [ $comment_data ], $post_id )
						:
					\DT\NbAddon\Comments\Utils\process_comments( $comment_data, $post_id );

	// apply deferred counts.
	wp_defer_comment_counting( false );

	/**
		 * Action fired after comments processed in destination
		 *
		 * @since 1.4.2
		 * @param WP_Post         $post    Updated post object.
		 */
		do_action( 'dt_after_comments_processed', $post_id );

	// Set initial defer status.
	wp_defer_comment_counting( $defer_status );
	return $result;
}

/**
 * Trash comments that trashed in source
 *
 * @param \WP_REST_Request $request WP_REST_Request instance.
 *
 * @return array
 */
function trash_comments( \WP_REST_Request $request ) {
	$post_id          = $request->get_param( 'post_id' );
	$signature        = $request->get_param( 'signature' );
	$comment_data     = $request->get_param( 'comment_data' );
	$is_valid_request = \DT\NbAddon\Comments\Utils\validate_request( $post_id, $signature );
	if ( true !== $is_valid_request ) {
		return $is_valid_request;
	}
	// Get current comment counting defer status.
	$defer_status = wp_defer_comment_counting();
	// Set counting defer to false.
	wp_defer_comment_counting( true );
	$result = \DT\NbAddon\Comments\Utils\trash_comments( $post_id, $comment_data );

	// apply deferred counts.
	wp_defer_comment_counting( false );

	// Set initial defer status.
	wp_defer_comment_counting( $defer_status );
	return $result;
}

/**
 * Un-trash comments that un-trashed in source
 *
 * @param \WP_REST_Request $request WP_REST_Request instance.
 * @return array
 */
function untrash_comments( \WP_REST_Request $request ) {
	$post_id          = $request->get_param( 'post_id' );
	$signature        = $request->get_param( 'signature' );
	$comment_data     = $request->get_param( 'comment_data' );
	$comment_status   = $request->get_param( 'comment_status' );
	$is_valid_request = \DT\NbAddon\Comments\Utils\validate_request( $post_id, $signature );
	if ( true !== $is_valid_request ) {
		return $is_valid_request;
	}
	// Get current comment counting defer status.
	$defer_status = wp_defer_comment_counting();
	// Set counting defer to false.
	wp_defer_comment_counting( true );
	$result = \DT\NbAddon\Comments\Utils\untrash_comments( $post_id, $comment_data, $comment_status );

	// apply deferred counts.
	wp_defer_comment_counting( false );

	// Set initial defer status.
	wp_defer_comment_counting( $defer_status );
	return $result;
}

/**
 * Delete comments that deleted in source
 *
 * @param \WP_REST_Request $request WP_REST_Request instance.
 * @return array
 */
function delete_comments( \WP_REST_Request $request ) {
	$post_id          = $request->get_param( 'post_id' );
	$signature        = $request->get_param( 'signature' );
	$comment_data     = $request->get_param( 'comment_data' );
	$is_valid_request = \DT\NbAddon\Comments\Utils\validate_request( $post_id, $signature );
	if ( true !== $is_valid_request ) {
		return $is_valid_request;
	}
	// Get current comment counting defer status.
	$defer_status = wp_defer_comment_counting();
	// Set counting defer to false.
	wp_defer_comment_counting( true );
	$result = \DT\NbAddon\Comments\Utils\delete_comments( $post_id, $comment_data );

	// apply deferred counts.
	wp_defer_comment_counting( false );

	// Set initial defer status.
	wp_defer_comment_counting( $defer_status );
	return $result;
}
