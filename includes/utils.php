<?php
/**
 * Helper functions for comments processing
 *
 * @package distributor-comments
 */

namespace DT\NbAddon\Comments\Utils;

/**
 * Get comment in destination using original comment id
 *
 * @param int $original_id Original comment id.
 * @return null|int
 */
function get_comment_from_original_id( $original_id ) {
	global $wpdb;
	return $wpdb->get_var( "SELECT comment_id from $wpdb->commentmeta WHERE meta_key = 'dt_original_comment_id' AND meta_value = '$original_id'" ); //phpcs:ignore
}

/**
 * Check if comment already distributed in destination and get it's id
 *
 * @param int $original_comment_id Original comment id.
 * @param int $post_id Original post id.
 * @return null|int
 */
function get_existing_comment_id( $original_comment_id, $post_id ) {
	$comment_id = get_comment_from_original_id( $original_comment_id );
	if ( empty( $comment_id ) ) {
		return null;
	}
	$original_post_id = \get_comment_meta( $comment_id, 'dt_original_post_id', true );

	if ( $post_id == $original_post_id ) { //phpcs:ignore
		return $comment_id;
	}
	return null;
}


/**
 * Set / update meta for given comment
 *
 * @param int   $comment_id Comment ID.
 * @param array $meta Array of meta as key => value.
 */
function set_comment_meta( $comment_id, $meta ) {
	$existing_meta    = get_comment_meta( $comment_id );
	$blacklisted_meta = array();

	foreach ( $meta as $meta_key => $meta_values ) {
		if ( in_array( $meta_key, $blacklisted_meta, true ) ) {
			continue;
		}

		foreach ( $meta_values as $meta_placement => $meta_value ) {
			/* Even if previous value is NULL, we don't need to add new one. */
			$has_prev_value = isset( $existing_meta[ $meta_key ] ) && is_array( $existing_meta[ $meta_key ] ) && array_key_exists( $meta_placement, $existing_meta[ $meta_key ] ) ? true : false;
			if ( $has_prev_value ) {
				$prev_value = maybe_unserialize( $existing_meta[ $meta_key ][ $meta_placement ] );
			}

			if ( ! is_array( $meta_value ) ) {
				$meta_value = maybe_unserialize( $meta_value );

			}

			if ( $has_prev_value ) {
				update_comment_meta( $comment_id, $meta_key, $meta_value, $prev_value );
			} else {
				add_comment_meta( $comment_id, $meta_key, $meta_value );
			}
		}
	}

}


/**
 * Process comments
 *
 * @param array $comments Array of comments.
 * @param int   $post_id Post ID.
 * @param bool  $initial_insert Whether if processing initial insert to avoid additional checks, default false.
 * @param array $res Result array, needs to be passed as param because of recursion, default [].
 * @return array Array containing results.
 */
function process_comments( $comments, $post_id, $initial_insert = false, $res = [] ) {
	$suspend = array();
	foreach ( $comments as $comment ) {
		$comment_data   = $comment['comment_data'];
		$comment_meta   = $comment['comment_meta'];
        $comment_parent = 0 == $comment_data['comment_parent'] ? 0 : get_existing_comment_id( $comment_data['comment_parent'], $comment_data['comment_post_ID'] ); //phpcs:ignore
		if ( 0 !== $comment_parent && empty( $comment_parent ) ) {
			$suspend[] = $comment;
			continue;
		}
		$new_comment = array(
			'comment_post_ID'      => $post_id,
			'comment_author'       => $comment_data['comment_author'],
			'comment_author_email' => $comment_data['comment_author_email'],
			'comment_author_url'   => $comment_data['comment_author_url'],
			'comment_author_IP'    => $comment_data['comment_author_IP'],
			'comment_date'         => $comment_data['comment_date'],
			'comment_date_gmt'     => $comment_data['comment_date_gmt'],
			'comment_content'      => $comment_data['comment_content'],
			'comment_karma'        => $comment_data['comment_karma'],
			'comment_approved'     => $comment_data['comment_approved'],
			'comment_agent'        => $comment_data['comment_agent'],
			'comment_type'         => $comment_data['comment_type'],
			'user_id'              => 0,
			'comment_parent'       => $comment_parent,
		);
		if ( false === $initial_insert ) {

			$existing_comment_id = get_existing_comment_id( $comment_data['comment_ID'], $comment_data['comment_post_ID'] );

			if ( ! empty( $existing_comment_id ) ) {
				$new_comment['comment_ID'] = $existing_comment_id;
				wp_update_comment( $new_comment );
				$comment_id = $existing_comment_id;
			} else {
				/**
			 * Action fired before inserting new comment in destination
			 *
			 * @since 1.4.2
			 */
				do_action( 'dt_before_insert_comment' );
				$comment_id = wp_insert_comment( $new_comment );
			}
		} else {
			/**
			 * Action fired before inserting new comment in destination
			 *
			 * @since 1.4.2
			 */
			do_action( 'dt_before_insert_comment' );
			$comment_id = wp_insert_comment( $new_comment );
		}
		if ( ! is_wp_error( $comment_id ) ) {
			set_comment_meta( $comment_id, $comment_meta );
			update_comment_meta( $comment_id, 'dt_original_post_id', $comment_data['comment_post_ID'] );
			update_comment_meta( $comment_id, 'dt_original_comment_id', $comment_data['comment_ID'] );
			$res['success'][] = $comment_id;
		} else {
			$res['fail'][] = $comment_id->get_error_message;
		}
	}

	if ( ! empty( $suspend ) && count( $suspend ) < count( $comments ) ) {
		process_comments( $suspend, $post_id, $initial_insert );
	}
	return $res;
}



/**
 * Validate request by signature
 *
 * @param int    $post_id Parent post ID in destination.
 * @param string $signature Subscription signature for post.
 *
 * @return bool|WP_Error
 */
function validate_request( $post_id, $signature ) {
	$post = get_post( $post_id );
	if ( empty( $post ) ) {
		return new \WP_Error( 'rest_post_invalid_id', esc_html__( 'Invalid post ID.', 'distributor-wc' ), array( 'status' => 404 ) );
	}

	$valid_signature = get_post_meta( $post_id, 'dt_subscription_signature', true ) === $signature;
	if ( ! $valid_signature ) {
		return new \WP_Error( 'rest_post_invalid_subscription', esc_html__( 'No subscription for that post', 'distributor-wc' ), array( 'status' => 400 ) );
	}
	return true;
}


/**
 * Check if array is associative
 *
 * @param array $arr Array to check
 * @return bool
 */
function is_assoc( array $arr ) {
	if ( array() === $arr ) { return false;
	}
	return array_keys( $arr ) !== range( 0, count( $arr ) - 1 );
}


/**
 * Trash comments
 *
 * @param int       $post_id Post ID.
 * @param int|array $comments Array of comments ids.
 *
 * @return array
 */
function trash_comments( $post_id, $comments ) {
	$res = [];
	if ( ! is_array( $comments ) ) {
		$comments = [ $comments ];
	}
	foreach ( $comments as $comment ) {
		$id = get_comment_from_original_id( $comment, $post_id );
		if ( ! empty( $id ) ) {
			if ( wp_trash_comment( $id ) ) {
				$res['success'][] = $comment;
			} else {
				$res['fail'][] = $comment;
			}
		}
	}
	return $res;
}

/**
 * Perform comments deleting
 *
 * @param int       $post_id Post ID.
 * @param int|array $comments Array of comments ids.
 * @param string    comment_status Comment status to set it explicitly
 *
 * @return array
 */
function untrash_comments( $post_id, $comments, $comment_status ) {
	$res = [];
	if ( ! is_array( $comments ) ) {
		$comments = [ $comments ];
	}
	foreach ( $comments as $comment ) {
		$id = get_comment_from_original_id( $comment, $post_id );
		if ( ! empty( $id ) ) {
			if ( wp_untrash_comment( $id ) ) {
				wp_set_comment_status( $id, $comment_status );
				$res['success'][] = $comment;
			} else {
				$res['fail'][] = $comment;
			}
		}
	}
	return $res;
}

/**
 * Perform comments deleting
 *
 * @param int       $post_id Post ID.
 * @param int|array $comments Array of comments ids.
 *
 * @return array
 */
function delete_comments( $post_id, $comments ) {
	$res = [];
	if ( ! is_array( $comments ) ) {
		$comments = [ $comments ];
	}
	foreach ( $comments as $comment ) {
		$id = get_comment_from_original_id( $comment, $post_id );
		if ( ! empty( $id ) ) {
			if ( wp_delete_comment( $id, true ) ) {
				$res['success'][] = $comment;
			} else {
				$res['fail'][] = $comment;
			}
		}
	}
	return $res;
}
