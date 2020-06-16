<?php
namespace Chargify\Customers;

/**
 * A function to check to see if the user exists. If they do update them.
 * If not, create a new user in WordPress.
 *
 * @param $data
 * @return bool|false|\WP_Error
 */
function maybe_update_customer( $data ) {
	$user_id = email_exists( $data['customer']['email'] );

	# If the user doesn't exist then we should create them.
	if ( false === $user_id ) {
		$user_id = create_customer( $data );
		return $user_id;
	}

	if ( ! is_wp_error( $user_id ) ) {
		$user_id = update_customer( $user_id, $data );
		return $user_id;
	}

	# Return the WP_Error.
	return $user_id;
}

/**
 * A function to update the WordPress user with the new data that Chargify sent.
 *
 * @param $user_id
 * @param $data
 * @return int|\WP_Error
 */
function update_customer( $user_id, $data ) {
	$user_id = wp_update_user(
		[
			'ID'              => absint( $user_id ),
			'user_email'      => sanitize_email( $data['customer']['email'] ),
			'first_name'      => sanitize_text_field( $data['customer']['first_name'] ),
			'last_name'       => sanitize_text_field( $data['customer']['last_name'] ),
		]
	);

	return $user_id;
}

/**
 * A function to create a new WordPress user with the data from Chargify.
 *
 * @param $data
 * @return int|\WP_Error
 */
function create_customer( $data ) {
	$user_id = wp_insert_user(
		[
			'user_email'      => sanitize_email( $data['customer']['email'] ),
			'user_login'      => sanitize_email( $data['customer']['email'] ),
			'first_name'      => sanitize_text_field( $data['customer']['first_name'] ),
			'last_name'       => sanitize_text_field( $data['customer']['last_name'] ),
			'user_registered' => sanitize_text_field( $data['customer']['created_at'] ),
			'user_pass'       => apply_filters( 'chargify_generate_password', wp_generate_password() ),
			'role'            => 'chargify_user'
		]
	);

	return $user_id;
}
