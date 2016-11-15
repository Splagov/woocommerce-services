<?php

// No direct access please
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WOOCOMMERCE_CONNECT_SERVER_URL' ) ) {
	define( 'WOOCOMMERCE_CONNECT_SERVER_URL', 'https://api.woocommerce.com/' );
}

if ( ! class_exists( 'WC_Connect_API_Client' ) ) {

	class WC_Connect_API_Client {

		/**
		 * @var WC_Connect_Services_Validator
		 */
		protected $validator;

		/**
		 * @var WC_Connect_Loader
		 */
		protected $wc_connect_loader;

		public function __construct(
			WC_Connect_Service_Schemas_Validator $validator,
			WC_Connect_Loader $wc_connect_loader
		) {

			$this->validator = $validator;
			$this->wc_connect_loader = $wc_connect_loader;

		}

		/**
		 * Requests the available services for this site from the Connect for WooCommerce Server
		 *
		 * @return array|WP_Error
		 */
		public function get_service_schemas() {
			$response_body = $this->request( 'POST', '/services' );

			if ( is_wp_error( $response_body ) ) {
				return $response_body;
			}

			$result = $this->validator->validate_service_schemas( $response_body );
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			return $response_body;
		}

		/**
		 * Validates the settings for a given service with the Connect for WooCommerce Server
		 *
		 * @param $service_slug
		 * @param $service_settings
		 *
		 * @return bool|WP_Error
		 */
		public function validate_service_settings( $service_slug, $service_settings ) {

			// Make sure the service slug only contains underscores or letters
			if ( 1 === preg_match( '/[^a-z_]/i', $service_slug ) ) {
				return new WP_Error( 'invalid_service_slug', 'Invalid Connect for WooCommerce service slug provided' );
			}

			return $this->request( 'POST', "/services/{$service_slug}/settings", array( 'service_settings' => $service_settings ) );
		}

		/**
		 * Gets shipping rates (for checkout) from the Connect for WooCommerce Server
		 *
		 * @param $services All settings for all services we want rates for
		 * @param $package Package provided to WC_Shipping_Method::calculate_shipping()
		 * @return object|WP_Error
		 */
		public function get_shipping_rates( $services, $package, $boxes ) {

			// First, build the contents array
			// each item needs to specify quantity, weight, length, width and height
			$contents = array();
			foreach ( $package['contents'] as $item_id => $values ) {

				$product_id = absint( $values['data']->id );
				$product = wc_get_product( $product_id );

				if ( $values['quantity'] > 0 && $product->needs_shipping() ) {

					if ( ! $product->has_weight() ) {
						return new WP_Error(
							'product_missing_weight',
							sprintf( "Product ( ID: %d ) did not include a weight. Shipping rates cannot be calculated.", $product_id )
						);
					}

					$height = 0;
					$length = 0;
					$weight = $product->get_weight();
					$width = 0;

					if ( $product->has_dimensions() ) {
						$height = $product->get_height();
						$length = $product->get_length();
						$width  = $product->get_width();
					}

					$contents[] = array(
						'height' => $height,
						'product_id' => isset( $values['data']->variation_id ) ? $values['data']->variation_id : $values['data']->id,
						'length' => $length,
						'quantity' => $values['quantity'],
						'weight' => $weight,
						'width' => $width
					);
				}
			}

			if ( empty( $contents ) ) {
				return new WP_Error( 'nothing_to_ship', 'No shipping rate could be calculated. No items in the package are shippable.' );
			}

			// Then, make the request
			$body = array(
				'contents' => $contents,
				'destination' => $package['destination'],
				'services' => $services,
				'boxes' => $boxes
			);

			return $this->request( 'POST', '/shipping/rates', $body );
		}

		public function send_shipping_label_request( $body ) {
			return $this->request( 'POST', '/shipping/label', $body );
		}

		public function send_address_normalization_request( $body ) {
			return $this->request( 'POST', '/shipping/address/normalize', $body );
		}

		/**
		 * Asks the Connect for WooCommerce server for an array of payment methods
		 *
		 * @return mixed|WP_Error
		 */
		public function get_payment_methods() {
			return $this->request( 'POST', '/payment/methods' );
		}

		/**
		 * Gets shipping rates (for labels) from the Connect for WooCommerce Server
		 *
		 * @param array $request - array(
		 *	'packages' => array(
		 *		array(
		 * 			'id' => 'box_1',
		 *			'height' => 10,
		 *			'length' => 10,
		 *			'width' => 10,
		 *			'weight' => 10,
		 *		),
		 *		array(
		 *			'id' => 'box_2',
		 *			'box_id' => 'medium_flat_box_top',
		 *			'weight' => 5,
		 *		),
		 *		...
		 * 	),
		 *	'carrier' => 'usps',
		 *	'origin' => array(
		 *		'address' => '132 Hawthorne St',
		 *		'address_2' => '',
		 *		'city' => 'San Francisco',
		 *		'state' => 'CA',
		 *		'postcode' => '94107',
		 *		'country' => 'US',
		 *	),
		 *	'destination' => array(
		 *		'address' => '1550 Snow Creek Dr',
		 *		'address_2' => '',
		 *		'city' => 'Park City',
		 *		'state' => 'UT',
		 *		'postcode' => '84060',
		 *		'country' => 'US',
		 *	),
		 * )
		 * @return object|WP_Error
		 */
		public function get_label_rates( $request ) {
			return $this->request( 'POST', '/shipping/label/rates', $request );
		}

		/**
		 * Gets a PDF with the set of dummy labels specified in the request
		 *
		 * @param $request
		 * @return object|WP_Error
		 */
		public function get_labels_preview_pdf( $request ) {
			return $this->request( 'POST', 'shipping/labels/preview', $request );
		}

		/**
		 * Gets a PDF with the requested shipping labels in it
		 *
		 * @param $request
		 * @return object|WP_Error
		 */
		public function get_labels_print_pdf( $request ) {
			return $this->request( 'POST', 'shipping/labels/print', $request );
		}

		/**
		 * Gets the shipping label status (refund status, tracking code, etc)
		 *
		 * @param $label_id integer
		 * @return object|WP_Error
		 */
		public function get_label_status( $label_id ) {
			return $this->request( 'GET', '/shipping/label/' . $label_id );
		}

		/**
		 * Request a refund for a given shipping label
		 *
		 * @param $label_id integer
		 * @return object|WP_Error
		 */
		public function send_shipping_label_refund_request( $label_id ) {
			return $this->request( 'POST', '/shipping/label/' . $label_id . '/refund' );
		}

		/**
		 * Tests the connection to the Connect for WooCommerce Server
		 *
		 * @return true|WP_Error
		 */
		public function auth_test() {
			return $this->request( 'GET', '/connection/test' );
		}

		/**
		 * Sends a request to the Connect for WooCommerce Server
		 *
		 * @param $method
		 * @param $path
		 * @param $body
		 * @return mixed|WP_Error
		 */
		protected function request( $method, $path, $body = array() ) {

			// TODO - incorporate caching for repeated identical requests
			if ( ! class_exists( 'Jetpack_Data' ) ) {
				return new WP_Error( 'jetpack_data_class_not_found', 'Unable to send request to Connect for WooCommerce server. Jetpack_Data was not found.' );
			}

			if ( ! method_exists( 'Jetpack_Data', 'get_access_token' ) ) {
				return new WP_Error( 'jetpack_data_get_access_token_not_found', 'Unable to send request to Connect for WooCommerce server. Jetpack_Data does not implement get_access_token.' );
			}

			if ( ! is_array( $body ) ) {
				return new WP_Error(
					'request_body_should_be_array',
					'Unable to send request to Connect for WooCommerce server. Body must be an array.'
				);
			}

			$url = trailingslashit( WOOCOMMERCE_CONNECT_SERVER_URL );
			$url = apply_filters( 'wc_connect_server_url', $url );
			$url = trailingslashit( $url ) . ltrim( $path, '/' );

			// Add useful system information to requests that contain bodies
			if ( in_array( $method, array( 'POST', 'PUT' ) ) ) {
				$body = $this->request_body( $body );
				$body = wp_json_encode( apply_filters( 'wc_connect_api_client_body', $body ) );

				if ( ! $body ) {
					return new WP_Error(
						'unable_to_json_encode_body',
						'Unable to encode body for request to Connect for WooCommerce server.'
					);
				}
			}

			$headers = $this->request_headers();
			if ( is_wp_error( $headers ) ) {
				return $headers;
			}

			$args = array(
				'headers' => $headers,
				'method' => $method,
				'body' => $body,
				'redirection' => 0,
				'compress' => true,
				'timeout' => 20,
			);
			$args = apply_filters( 'wc_connect_request_args', $args );

			$response = wp_remote_request( $url, $args );
			$response_code = wp_remote_retrieve_response_code( $response );

			// If the received response is not JSON, return the raw response
			$content_type = wp_remote_retrieve_header( $response, 'content-type' );
			if ( false === strpos( $content_type, 'application/json' ) ) {
				if ( 200 != $response_code ) {
					return new WP_Error(
						'wcc_server_error',
						sprintf( 'Error: The Connect for WooCommerce server returned HTTP code: %d', $response_code )
					);
				}
				return $response;
			}

			$response_body = wp_remote_retrieve_body( $response );
			if ( ! empty( $response_body ) ) {
				$response_body = json_decode( $response_body );
			}

			if ( 200 != $response_code ) {
				if ( empty( $response_body ) ) {
					return new WP_Error(
						'wcc_server_empty_response',
						sprintf(
							'Error: The Connect for WooCommerce server returned ( %d ) and an empty response body.',
							$response_code
						)
					);
				}

				$error   = property_exists( $response_body, 'error' ) ? $response_body->error : '';
				$message = property_exists( $response_body, 'message' ) ? $response_body->message : '';
				$data    = property_exists( $response_body, 'data' ) ? $response_body->data : '';

				return new WP_Error(
					'wcc_server_error_response',
					sprintf(
						'Error: The Connect for WooCommerce server returned: %s %s ( %d )',
						$error,
						$message,
						$response_code
					),
					$data
				);
			}

			return $response_body;
		}

		/**
		 * Adds useful WP/WC/WCC information to request bodies
		 *
		 * @param array $initial_body
		 * @return array
		 */
		protected function request_body( $initial_body = array() ) {
			$default_body = array(
				'settings' => array(),
			);
			$body = array_merge( $default_body, $initial_body );

			// Add interesting fields to the body of each request
			$body['settings'] = wp_parse_args( $body['settings'], array(
				'store_guid' => $this->get_guid(),
				'base_city' => WC()->countries->get_base_city(),
				'base_country' => WC()->countries->get_base_country(),
				'base_state' => WC()->countries->get_base_state(),
				'currency' => get_woocommerce_currency(),
				'dimension_unit' => strtolower( get_option( 'woocommerce_dimension_unit' ) ),
				'jetpack_version' => JETPACK__VERSION,
				'wc_version' => WC()->version,
				'weight_unit' => strtolower( get_option( 'woocommerce_weight_unit' ) ),
				'wp_version' => get_bloginfo( 'version' ),
				'last_services_update' => get_option( 'wc_connect_services_last_update', 0 ),
				'last_heartbeat' => get_option( 'wc_connect_last_heartbeat', 0 ),
				'last_rate_request' => get_option( 'wc_connect_last_rate_request', 0 ),
				'active_services' => $this->wc_connect_loader->get_active_services(),
				'disable_stats' => Jetpack::is_staging_site(),
			) );

			return $body;
		}

		/**
		 * Generates headers for our request to the Connect for WooCommerce Server
		 *
		 * @return array|WP_Error
		 */
		protected function request_headers() {
			$authorization = $this->authorization_header();
			if ( is_wp_error( $authorization ) ) {
				return $authorization;
			}

			$headers = array();
			$lang = strtolower( str_replace( '_', '-', get_locale() ) );
			$headers['Accept-Language'] = $lang;
			$headers['Content-Type'] = 'application/json; charset=utf-8';
			$headers['Accept'] = 'application/vnd.woocommerce-connect.v1';
			$headers['Authorization'] = $authorization;
			return $headers;
		}

		protected function authorization_header() {
			$token = Jetpack_Data::get_access_token( 0 );
			$token = apply_filters( 'wc_connect_jetpack_access_token', $token );
			if ( ! $token || empty( $token->secret ) ) {
				return new WP_Error( 'missing_token', 'Unable to send request to Connect for WooCommerce server. Jetpack Token is missing' );
			}

			if ( false === strpos( $token->secret, '.' ) ) {
				return new WP_Error( 'invalid_token', 'Unable to send request to Connect for WooCommerce server. Jetpack Token is malformed.' );
			}

			list( $token_key, $token_secret ) = explode( '.', $token->secret );
			$token_key = sprintf( '%s:%d:%d', $token_key, JETPACK__API_VERSION, $token->external_user_id );
			$time_diff = (int)Jetpack_Options::get_option( 'time_diff' );
			$timestamp = time() + $time_diff;
			$nonce = wp_generate_password( 10, false );

			$signature = $this->request_signature( $token_key, $token_secret, $timestamp, $nonce, $time_diff );
			if ( is_wp_error( $signature ) ) {
				return $signature;
			}

			$auth = array(
				'token' => $token_key,
				'timestamp' => $timestamp,
				'nonce' => $nonce,
				'signature' => $signature,
			);

			$header_pieces = array();
			foreach ( $auth as $key => $value ) {
				$header_pieces[] = sprintf( '%s="%s"', $key, $value );
			}

			$authorization = 'X_JP_Auth ' . join( ' ', $header_pieces );
			return $authorization;
		}

		protected function request_signature( $token_key, $token_secret, $timestamp, $nonce, $time_diff ) {
			$local_time = $timestamp - $time_diff;
			if ( $local_time < time() - 600 || $local_time > time() + 300 ) {
				return new WP_Error( 'invalid_signature', 'Unable to send request to Connect for WooCommerce server. The timestamp generated for the signature is too old.' );
			}

			$normalized_request_string = join( "\n", array(
					$token_key,
					$timestamp,
					$nonce
				) ) . "\n";

			return base64_encode( hash_hmac( 'sha1', $normalized_request_string, $token_secret, true ) );
		}

		private function get_guid() {
			$guid = get_option( 'wc_connect_store_guid', false );
			if ( false === $guid ) {
				$guid = $this->generate_guid();
				update_option( 'wc_connect_store_guid', $guid );
			}

			return $guid;
		}

		/**
		 * Generates a GUID.
		 * This code is based of a snippet found in https://github.com/alixaxel/phunction,
		 * which was referenced in http://php.net/manual/en/function.com-create-guid.php
		 *
		 * @return string
		 */
		private function generate_guid() {
			return strtolower( sprintf( '%04X%04X-%04X-%04X-%04X-%04X%04X%04X',
				mt_rand( 0, 65535 ),
				mt_rand( 0, 65535 ),
				mt_rand( 0, 65535 ),
				mt_rand( 16384, 20479 ),
				mt_rand( 32768, 49151 ),
				mt_rand( 0, 65535 ),
				mt_rand( 0, 65535 ),
				mt_rand( 0, 65535 )
			) );
		}
	}

}
