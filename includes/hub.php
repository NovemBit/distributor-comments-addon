<?php
/**
 * Actions performed in hub
 *
 * @package distributor-comments
 */

namespace DT\NbAddon\Comments\Hub;

/**
 * Setup actions
 */
function setup() {
	add_action(
		'init',
		function () {
			add_action( 'dt_post_subscription_created', __NAMESPACE__ . '\initial_push', 10, 4 );
			add_action( 'comment_post', __NAMESPACE__ . '\on_comment_insert', 10, 2 );
			add_action( 'edit_comment', __NAMESPACE__ . '\on_comment_update', 10, 2 );
			add_action( 'trash_comment', __NAMESPACE__ . '\on_comment_delete', 10, 2 );
		}
	);
}

/**
 * Push comments on initial push
 *
 * @param int    $post_id Pushed post ID.
 * @param int    $remote_post_id Remote post ID.
 * @param string $signature Generated signature for subscription.
 * @param string $target_url Target url to push to.
 */
function initial_push( $post_id, $remote_post_id, $signature, $target_url ) {

	$comments_count = get_comments_number( $post_id );
	if ( $comments_count > 0 ) {
		handle_initial_push( $post_id, $remote_post_id, $signature, $target_url, true );
	}
}


/**
 * Handle comments pushing
 *
 * @param int    $post_id Pushed post ID.
 * @param int    $remote_post_id Remote post ID.
 * @param string $signature Generated signature for subscription.
 * @param string $target_url Target url to push to.
 * @param bool   $allow_termination Whether run "apply filters" to allow to terminate function execution or not
 *
 * @return void
 */
function handle_initial_push( $post_id, $remote_post_id, $signature, $target_url, $allow_termination = false ) {
	if( true === $allow_termination ) {
		/**
		 * Add possibility to perform comments pushing
		 *
		 * @param bool      true            Whether to oudh comment.
		 * @param int    $post_id Pushed post ID.
		 * @param int    $remote_post_id Remote post ID.
		 * @param string $signature Generated signature for subscription.
		 * @param string $target_url Target url to push to.
		 */
		$allow_comments_update = apply_filters( 'dt_allow_comments_initial_push', true, $post_id, $remote_post_id, $signature, $target_url );
		if ( false === $allow_comments_update ) {
			return;
		}
	}

	$args         = array(
		'post_id' => $post_id,
	);
	$all_comments = get_comments( $args );
	$comments     = [];
	foreach ( $all_comments as $comment ) {
		$comments[] = array(
			'comment_data' => $comment,
			'comment_meta' => get_comment_meta( $comment->comment_ID ),
		);
	}
	$post_body = [
		'post_id'      => $remote_post_id,
		'signature'    => $signature,
		'comment_data' => $comments,
	];
	$request   = wp_remote_post(
		untrailingslashit( $target_url ) . '/wp/v2/distributor/comments/insert',
		[
			'timeout' => 60,
			/**
			 * Filter the arguments sent to the remote server during a comments insert.
			 *
			 * @param  array  $post_body The request body to send.
			 * @param  int $post      Parent post id of comments that is being pushed.
			 */
			'body'    => apply_filters( 'dt_comments_push_args', $post_body, $post_id ),
		]
	);
	if ( ! is_wp_error( $request ) ) {
		$response_code = wp_remote_retrieve_response_code( $request );

		$result = json_decode( wp_remote_retrieve_body( $request ) );
	} else {
		$result = $request;
	}

	return $result;
}

/**
 * On comment add / update push to destinations
 *
 * @param int  $comment_id Created / updated comment ID.
 * @param bool $approved Is comment approved?
 */
function on_comment_insert( $comment_id, $approved, true ) {
	if ( ! $approved ) {
		return;
	}
	$comment   = get_comment( $comment_id, 'ARRAY_A' );
	$parent_id = $comment['comment_post_ID'];
	handle_update( $parent_id, $comment_id );
}

/**
 * Triggered when comment updated
 *
 * @param int   $comment_id Updated comment ID.
 * @param array $data Array containing comment data
 */
function on_comment_update( $comment_id, $data ) {
	if ( ! $data['comment_approved'] ) {
		return;
	}
	$parent_id = $data['comment_post_ID'];
	handle_update( $parent_id, $comment_id );
}


/**
 * Handle comment update / insert pushing to destinations
 *
 * @param int       $post_id Comment's parent post ID.
 * @param int|array $comment Array or single comment ID.
 * @param bool      $allow_termination Whether run "apply filters" to allow to terminate function execution or not
 *
 * @return void
 */
function handle_update( $post_id, $comment, $allow_termination = false ) {
	$subscriptions = get_post_meta( $post_id, 'dt_subscriptions', true );
	if ( empty( $subscriptions ) ) {
		return;
	}

	if ( true === $allow_termination ) { //phpcs:ignore
		/**
		 * Add possibility to perform comments update in bg
		 *
		 * @param bool      true            Whether to oudh comment.
		 * @param int    $post_id Pushed post ID.
		 * @param WP_Post $comment Comment object.
		 */
		$allow_comments_push = apply_filters( 'dt_allow_comments_update', true, $post_id, $comment );
		if ( ! $allow_comments_push ) {
			return;
		}
	}

	$comments_data = [];
	if ( is_array( $comment ) ) {
		foreach ( $comment as $id ) {
			$comments_data['comment_data'] = get_comment( $id, 'ARRAY_A' );
			$comments_data['comment_meta'] = get_comment_meta( $id );
		}
	} else {
		$comments_data['comment_data']     = get_comment( $comment, 'ARRAY_A' );
		$comments_data['comment_meta'] = get_comment_meta( $comment );
	}
	$result = [];
	foreach ( $subscriptions as $subscription_key => $subscription_id ) {
		$signature      = get_post_meta( $subscription_id, 'dt_subscription_signature', true );
		$remote_post_id = get_post_meta( $subscription_id, 'dt_subscription_remote_post_id', true );
		$target_url     = get_post_meta( $subscription_id, 'dt_subscription_target_url', true );

		if ( empty( $signature ) || empty( $remote_post_id ) || empty( $target_url ) ) {
			continue;
		}

		$post_body = [
			'post_id'      => $remote_post_id,
			'signature'    => $signature,
			'comment_data' => $comments_data,
		];
		$request   = wp_remote_post(
			untrailingslashit( $target_url ) . '/wp/v2/distributor/comments/update',
			[
				'timeout' => 60,
				/**
				 * Filter the arguments sent to the remote server during a comments update.
				 *
				 * @param  array  $post_body The request body to send.
				 * @param  int $post      Parent post id of comments that is being pushed.
				 */
				'body'    => apply_filters( 'dt_comments_subscription_args', $post_body, $post_id ),
			]
		);
		if ( ! is_wp_error( $request ) ) {
			$response_code = wp_remote_retrieve_response_code( $request );

			$result[ $post_id ][ $subscription_id ] = json_decode( wp_remote_retrieve_body( $request ) );
		} else {
			$result[ $post_id ][ $subscription_id ] = $request;
		}
	}
	return $result;
}


/**
 * Hook on post delete
 *
 * @param int        $comment_id Comment ID.
 * @param WP_Comment $comment    The comment to be deleted.
 */
function on_comment_delete( $comment_id, $comment ) {
	handle_delete( $comment_id );
}

/**
 * Delete comments in destinations
 *
 * @param int $comment_id Deleted comment ID.
 */
function handle_delete( $comment_id ) {
	$comment       = get_comment( $comment_id );
	$subscriptions = get_post_meta( $comment->comment_post_ID, 'dt_subscriptions', true );
	if ( empty( $subscriptions ) ) {
		return false;
	}
	$result = [];
	foreach ( $subscriptions as $subscription_key => $subscription_id ) {
		$signature      = get_post_meta( $subscription_id, 'dt_subscription_signature', true );
		$remote_post_id = get_post_meta( $subscription_id, 'dt_subscription_remote_post_id', true );
		$target_url     = get_post_meta( $subscription_id, 'dt_subscription_target_url', true );

		if ( empty( $signature ) || empty( $target_url ) || empty( $remote_post_id ) ) {
			continue;
		}
		$post_body = [
			'post_id'      => $remote_post_id,
			'signature'    => $signature,
			'comment_data' => $comment_id,
		];
		$request   = wp_remote_post(
			untrailingslashit( $target_url ) . '/wp/v2/distributor/comments/delete',
			[
				'timeout' => 60,
				/**
				 * Filter the arguments sent to the remote server during a comment delete.
				 *
				 * @param  array  $post_body The request body to send.
				 * @param  int $post      Comment that is being deleted.
				 */
				'body'    => apply_filters( 'dt_comment_delete_post_args', $post_body, $comment->comment_post_ID ),
			]
		);
		if ( ! is_wp_error( $request ) ) {
			$response_code = wp_remote_retrieve_response_code( $request );
			$headers       = wp_remote_retrieve_headers( $request );

			$result[ $subscription_id ] = json_decode( wp_remote_retrieve_body( $request ) );
		} else {
			$result[ $subscription_id ] = $request;
		}
	}
}
