<?php
/**
 * The functions that help create a subscription from the form data.
 *
 * @file    wp-chargify/includes/forms/submission.php
 * @package WPChargify
 */

namespace Chargify\Forms\Submission;

use Chargify\Endpoints\Subscription;
use Chargify\Model\ChargifyComponentFactory;
use Chargify\Model\ChargifyComponentPricePointFactory;
use Chargify\Model\ChargifyProduct;
use Chargify\Model\ChargifyProductFactory;
use Chargify\Model\ChargifyProductPricePointFactory;
use CMB2;
use WP_Error;
use function Chargify\Helpers\products\get_product_family_id;

/**
 * Create a subscription, filter all of the form data into an array ready for the subscription submission.
 *
 * @param CMB2 $cmb2 The CMB2 object.
 *
 * @return mixed
 */
function create_subscription( $cmb2 ) {
	$product_handle = get_query_var( 'product_handle' );

	if ( empty( $product_handle ) ) {
		// Filter the Chargify Product handle.
		$product_handle = apply_filters( 'chargify_default_product', $product_handle );
	}

	// If we don't have any post data we can bail.
	if ( empty( $_POST ) ) {
		return false;
	}

	// check required $_POST variables and security nonce.
	if ( ! isset( $_POST['submit-cmb'], $_POST['object_id'], $_POST[ $cmb2->nonce() ] ) || ! wp_verify_nonce( $_POST[ $cmb2->nonce() ], $cmb2->nonce() ) ) { // phpcs:ignore
		return new WP_Error( 'security_fail', __( 'Security check failed.', 'chargify' ) );
	}

	$sanitized_values = $cmb2->get_sanitized_values( $_POST );

	if ( $sanitized_values ) {
		$metafields = apply_filters( 'chargify_signup_metafields', null );

		$chargify_data = [
			'subscription' => [
				'product_handle'         => isset( $sanitized_values['chargify_product_handle'] ) ? $sanitized_values['chargify_product_handle'] : '',
				'customer_attributes'    => [
					'first_name'   => $sanitized_values['chargify_first_name'],
					'last_name'    => $sanitized_values['chargify_last_name'],
					'email'        => $sanitized_values['chargify_email_address'],
					'cc_emails'    => isset( $sanitized_values['chargify_cc_emails'] ) ? $sanitized_values['chargify_cc_emails'] : null,
					'organization' => isset( $sanitized_values['chargify_organisation'] ) ? $sanitized_values['chargify_organisation'] : null,
					'reference'    => isset( $sanitized_values['chargify_billing_reference'] ) ? $sanitized_values['chargify_billing_reference'] : (string) time(),
					'address'      => isset( $sanitized_values['chargify_address_1'] ) ? $sanitized_values['chargify_address_1'] : null,
					'address_2'    => isset( $sanitized_values['chargify_address_2'] ) ? $sanitized_values['chargify_address_2'] : null,
					'city'         => isset( $sanitized_values['chargify_city'] ) ? $sanitized_values['chargify_city'] : null,
					'state'        => isset( $sanitized_values['chargify_state'] ) ? $sanitized_values['chargify_state'] : null,
					'zip'          => isset( $sanitized_values['chargify_zip'] ) ? $sanitized_values['chargify_zip'] : null,
					'country'      => isset( $sanitized_values['chargify_country'] ) ? $sanitized_values['chargify_country'] : null,
					'phone'        => isset( $sanitized_values['chargify_phone'] ) ? $sanitized_values['chargify_phone'] : null,
					'verified'     => isset( $sanitized_values['chargify_verified'] ) ? $sanitized_values['chargify_verified'] : false,
					'tax_exempt'   => isset( $sanitized_values['chargify_tax_exempt'] ) ? $sanitized_values['chargify_tax_exempt'] : false,
					'vat_number'   => isset( $sanitized_values['chargify_vat_number'] ) ? $sanitized_values['chargify_vat_number'] : null,
				],
				'credit_card_attributes' => [
					'first_name'        => isset( $sanitized_values['chargify_billing_first_name'] ) ? $sanitized_values['chargify_billing_first_name'] : null,
					'last_name'         => isset( $sanitized_values['chargify_billing_last_name'] ) ? $sanitized_values['chargify_billing_last_name'] : null,
					'full_number'       => isset( $sanitized_values['chargify_payment_card_number'] ) ? $sanitized_values['chargify_payment_card_number'] : null,
					'expiration_month'  => isset( $sanitized_values['chargify_payment_expiry_month'] ) ? $sanitized_values['chargify_payment_expiry_month'] : null,
					'expiration_year'   => isset( $sanitized_values['chargify_payment_expiry_year'] ) ? $sanitized_values['chargify_payment_expiry_year'] : null,
					'billing_address'   => isset( $sanitized_values['chargify_billing_address_1'] ) ? $sanitized_values['chargify_billing_address_1'] : null,
					'billing_address_2' => isset( $sanitized_values['chargify_billing_address_2'] ) ? $sanitized_values['chargify_billing_address_2'] : null,
					'billing_city'      => isset( $sanitized_values['chargify_billing_city'] ) ? $sanitized_values['chargify_billing_city'] : null,
					'billing_state'     => isset( $sanitized_values['chargify_billing_state'] ) ? $sanitized_values['chargify_billing_state'] : null,
					'billing_zip'       => isset( $sanitized_values['chargify_billing_zip'] ) ? $sanitized_values['chargify_billing_zip'] : null,
					'billing_country'   => isset( $sanitized_values['chargify_billing_country'] ) ? $sanitized_values['chargify_billing_country'] : null,
				],
			],
		];

		// Add other info to the subscription.
		$product_id = isset( $sanitized_values['chargify_product_id'] ) ? $sanitized_values['chargify_product_id'] : false;
		if ( $product_id ) {
			$chargify_data['subscription']['product_id'] = $product_id;
		}

		// Add other info to the subscription.
		$product_price_point_handle = isset( $sanitized_values['chargify_product_price_point_handle'] ) ? $sanitized_values['chargify_product_price_point_handle'] : false;
		if ( $product_price_point_handle ) {
			$chargify_data['subscription']['product_price_point_handle'] = $product_price_point_handle;
		}

		// Add other info to the subscription.
		$chargify_product_price_point_id = isset( $sanitized_values['chargify_product_price_point_id'] ) ? $sanitized_values['chargify_product_price_point_id'] : false;
		if ( $chargify_product_price_point_id ) {
			$chargify_data['subscription']['product_price_point_id'] = $chargify_product_price_point_id;
		}

		// Add other info to the subscription.
		$coupon_code = isset( $sanitized_values['chargify_coupon_code'] ) ? $sanitized_values['chargify_coupon_code'] : false;
		if ( $coupon_code ) {
			$chargify_data['subscription']['coupon_code'] = $coupon_code;
		}

		// Add components to the subscription.
		$component_id = isset( $sanitized_values['chargify_component_id'] ) ? $sanitized_values['chargify_component_id'] : false;
		if ( $component_id ) {
			$chargify_data['subscription']['components'] = [
				'component_id'                 => $component_id,
				'price_point_id'               => isset( $sanitized_values['chargify_component_price_point_id'] ) ? $sanitized_values['chargify_component_price_point_id'] : null,
				'component_price_point_handle' => isset( $sanitized_values['chargify_chargify_component_price_point_handle'] ) ? $sanitized_values['chargify_component_price_point_handle'] : null,
				'allocated_quantity'           => isset( $sanitized_values['chargify_component_allocated_quantity'] ) ? $sanitized_values['chargify_component_allocated_quantity'] : null,
			];
		}

		if ( $metafields ) {
			$chargify_data['subscription']['metafields'] = $metafields;
		}

		$wordpress_data = [
			'username' => $sanitized_values['wordpress_username'],
			'password' => $sanitized_values['wordpress_password'],
		];

		$subscription = Subscription\create_subscription( wp_json_encode( $chargify_data ), $wordpress_data );

	}

	return false;
}

/**
 * A function to register our query parameter for the product handle.
 *
 * @param array $query_vars The query args to append to.
 *
 * @return mixed
 */
function query_vars( $query_vars ) {
	$query_vars[] = 'product_handle';

	return $query_vars;
}

/**
 * Sets the frontend post form field values if form has already been submitted, or value present in GET.
 *
 * @param object $field_args Current field args.
 * @param object $field      Current field object.
 *
 * @return string
 */
function maybe_set_default_value( $field_args, $field ) {
	return maybe_get_field_default_value( $field->id() );
}

/**
 * Sets the frontend post form field values if form has already been submitted, or value present in GET.
 *
 * @param object $field_args Current field args.
 * @param object $field      Current field object.
 *
 * @return string
 */
function maybe_set_default_product_value( $field_args, $field ) {
	return maybe_get_default_product_value( $field->id() );
}

/**
 * Sets the frontend post form field values if form has already been submitted.
 *
 * @param string $meta_key Current field ID / CPT meta key.
 *
 * @return string
 */
function maybe_get_field_default_value( $meta_key ) {

	$value = '';

	if ( ! empty( $_POST[ $meta_key ] ) ) { // phpcs:ignore
		$value = $_POST[ $meta_key ]; // phpcs:ignore
	} elseif ( ! empty( $_GET[ $meta_key ] ) ) { // phpcs:ignore
		$value = $_GET[ $meta_key ]; // phpcs:ignore
	}

	return $value;
}

/**
 * Sets the frontend post form field values if form has already been submitted.
 * Try to gather info from GET or POST first, if not look up by product id or handle.
 *
 * TODO need a reverse lookup method.
 *
 * @param string $meta_key Current field ID / CPT meta key.
 *
 * @return string
 */
function maybe_get_default_product_value( $meta_key ) {

	$value = maybe_get_field_default_value( $meta_key );

	// Bail early if found.
	if ( ! empty( $value ) ) {
		return $value;
	}

	// Get by string method name.
	$method = 'get_' . $meta_key;

	/*
	 * Certain product information can be found from ids or handles, attempt to gather them.
	 */
	$product_id     = maybe_get_field_default_value( 'chargify_product_id' );
	$product_handle = maybe_get_field_default_value( 'chargify_product_handle' );


	// Try with product id or price point handle.
	if ( ! empty( $product_id ) || ! empty( $product_handle ) ) {
		$chargify_product         = false;
		$chargify_product_factory = new ChargifyProductFactory();

		if ( ! empty( $product_id ) ) {
			$chargify_product = $chargify_product_factory->get_by_product_id( $product_id );
		} elseif ( ! empty( $product_handle ) ) {
			$chargify_product = $chargify_product_factory->get_by_product_handle( $product_handle );
		}

		if ( $chargify_product instanceof ChargifyProduct ) {
			if ( method_exists( $chargify_product, $method ) ) {
				$value = $chargify_product->$method();
			}
		}
	}

	// Bail early if found.
	if ( ! empty( $value ) ) {
		return $value;
	}

	$product_price_point_id     = maybe_get_field_default_value( 'chargify_product_price_point_id' );
	$product_price_point_handle = maybe_get_field_default_value( 'chargify_product_price_point_handle' );

	// Try with price point id or price point handle.
	if ( ! empty( $product_price_point_id ) || ! empty( $product_price_point_handle ) ) {
		$product_price_point         = false;
		$product_price_point_factory = new ChargifyProductPricePointFactory();

		if ( ! empty( $product_price_point_point_id ) ) {
			$product_price_point = $product_price_point_factory->get_by_product_price_point_id( $product_price_point_point_id );
		} elseif ( ! empty( $product_price_point_point_handle ) ) {
			$product_price_point = $product_price_point_factory->get_by_product_price_point_handle( $product_price_point_point_handle );
		}

		if ( $product_price_point instanceof ChargifyProduct ) {
			if ( method_exists( $product_price_point, $method ) ) {
				$value = $product_price_point->$method();
			}
		}
	}

	// Bail early if found.
	if ( ! empty( $value ) ) {
		return $value;
	}

	$component_id     = maybe_get_field_default_value( 'chargify_component_id' );
	$component_handle = maybe_get_field_default_value( 'chargify_component_handle' );

	// Try with component id or component handle.
	if ( ! empty( $component_id ) || ! empty( $component_handle ) ) {
		$component         = false;
		$component_factory = new ChargifyComponentFactory();

		if ( ! empty( $component_point_id ) ) {
			$component = $component_factory->get_by_component_id( $component_point_id );
		} elseif ( ! empty( $component_point_handle ) ) {
			$component = $component_factory->get_by_component_handle( $component_point_handle );
		}

		if ( $component instanceof ChargifyProduct ) {
			if ( method_exists( $component, $method ) ) {
				$value = $component->$method();
			}
		}
	}

	// Bail early if found.
	if ( ! empty( $value ) ) {
		return $value;
	}

	$component_price_point_id     = maybe_get_field_default_value( 'chargify_component_price_point_id' );
	$component_price_point_handle = maybe_get_field_default_value( 'chargify_component_price_point_handle' );

	// Try with price point id or price point handle.
	if ( ! empty( $component_price_point_id ) || ! empty( $component_price_point_handle ) ) {
		$component_price_point         = false;
		$component_price_point_factory = new ChargifyComponentPricePointFactory();

		if ( ! empty( $component_price_point_point_id ) ) {
			$component_price_point = $component_price_point_factory->get_by_component_price_point_id( $component_price_point_point_id );
		} elseif ( ! empty( $component_price_point_point_handle ) ) {
			$component_price_point = $component_price_point_factory->get_by_component_price_point_handle( $component_price_point_point_handle );
		}

		if ( $component_price_point instanceof ChargifyProduct ) {
			if ( method_exists( $component_price_point, $method ) ) {
				$value = $component_price_point->$method();
			}
		}
	}

	return $value;
}
