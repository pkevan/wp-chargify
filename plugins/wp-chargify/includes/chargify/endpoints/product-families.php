<?php
namespace Chargify\Chargify\Endpoints\Product_Families;

use Chargify\Helpers\Options;

/**
 * A function to request all of the product families that are in Chargify.
 *
 * @return string|array
 */
function get_product_families() {
	$endpoint = Options\get_subdomain() . '/product_families.json';
	$headers  = Options\get_headers();
	$request  = wp_safe_remote_get( $endpoint, $headers );
	$body     = wp_remote_retrieve_body( $request );

	# Anything other than a 200 code is an error so let's bail.
	if ( 200 !== wp_remote_retrieve_response_code( $request ) ) {
		return wp_remote_retrieve_response_message( $request );
	}

	$json = json_decode( $body, true );

	foreach ( $json as $family ) {
		$rows[] = $family['product_family'];
	}

	return $rows;
}

/**
 * A function to loop over the product families and get the products contained in that family.
 *
 * @return array|string
 */
function get_products() {
	$product_families = get_product_families();

	# If we haven't got an array then we have an error to return.
	if ( ! is_array( $product_families ) ) {
		return $product_families;
	}

	$product_ids = wp_list_pluck( $product_families, 'id' );

	$headers  = Options\get_headers();

	foreach ( $product_ids as $product ) {
		$endpoint = Options\get_subdomain() . "/product_families/$product/products.json";
		$request  = wp_safe_remote_get( $endpoint, $headers );
		$body     = wp_remote_retrieve_body( $request );

		# Anything other than a 200 code is an error so let's bail.
		if ( 200 !== wp_remote_retrieve_response_code( $request ) ) {
			return wp_remote_retrieve_response_message( $request );
		}

		$json = json_decode( $body, true );

		foreach ( $json as $family ) {
			$rows[] = $family['product'];
		}
	}

	# Save all the products to an option
	update_option( 'chargify_products_all', $rows, false );

	return $rows;
}

function get_product( $id ) {
		$headers  = Options\get_headers();

		$endpoint = Options\get_subdomain() . "/products/$id.json";
		$request  = wp_safe_remote_get( $endpoint, $headers );
		$body     = wp_remote_retrieve_body( $request );

		# Anything other than a 200 code is an error so let's bail.
		if ( 200 !== wp_remote_retrieve_response_code( $request ) ) {
			return wp_remote_retrieve_response_message( $request );
		}


		$product = json_decode( $body, true );

	return $product;
}
