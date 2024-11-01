<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'Flexible_Shipping_UPS_UPS_RATING_REST_API' ) ) {

	class Flexible_Shipping_UPS_UPS_RATING_REST_API {

		/**
		 * @var array
		 */
		private $settings;

		/**
		 * @var string
		 */
		private $api_host;

		/**
		 * @var string
		 */
		private $production_api_host = 'https://onlinetools.ups.com/rest/Rate';
		/**
		 * @var string
		 */
		private $test_api_host = 'https://wwwcie.ups.com/rest/Rate';

		/**
		 * Flexible_Shipping_UPS_UPS_RATING_REST_API constructor.
		 *
		 * @param array $settings
		 * @param boolean $test_api
		 */
		public function __construct( $settings, $test_api = false ) {
			$this->settings = $settings;
			$this->api_host = $this->production_api_host;
			if ( $test_api ) {
				$this->api_host = $this->test_api_host;
			}
		}

		/**
		 *
		 */
		public function clear_cache() {
		}

		/**
		 * @return object
		 * @throws Exception
		 */
		public function ping() {
			return $this->rate_request( $this->prepare_rate_request_data() );
		}

		/**
		 * @return array
		 */
		public function ups_security_data() {
			return array(
				'UsernameToken' => array(
					'Username' => $this->settings['user_id'],
					'Password' => $this->settings['password'],
				),
				'ServiceAccessToken' => array(
					'AccessLicenseNumber'   => $this->settings['access_key']
				)
			);
		}

		/**
		 * @param string $request_option
		 * @param array $shipper
		 * @param array $ship_to
		 * @param array $ship_from
		 * @param array $service
		 * @param array $package
		 * @param array $shipment_rating_options
		 * @param stdClass $ups_access_point
		 *
		 * @return array
		 */
		public function prepare_rate_request_data(
			$request_option             = 'Shop',
			$shipper                    = array(),
			$ship_to                    = array(),
			$ship_from                  = array(),
			$service                    = array(),
			$package                    = array(),
			$shipment_rating_options    = array(),
			$ups_access_point           = null
		) {
			$data = array(
				'UPSSecurity' => $this->ups_security_data(),
				'RateRequest' => array(
					'Request' => array(
						'RequestOption' => $request_option,
					),
					'Shipment'  => array(
						'Shipper'               => $shipper,
						'ShipTo'                => $ship_to,
						'ShipFrom'              => $ship_from,
						'Service'               => $service,
						'Package'               => $package,
						'ShipmentRatingOptions' => $shipment_rating_options,
					)
				),
			);
			if ( $ups_access_point != null ) {
				$data['RateRequest']['Shipment']['ShipmentIndicationType'] = array( 'Code' => '02' );

				$data['RateRequest']['Shipment']['AlternateDeliveryAddress'] = array();
				$data['RateRequest']['Shipment']['AlternateDeliveryAddress']['Address'] = array(
					'AddressLine1' => $ups_access_point->AddressKeyFormat->AddressLine,
					'City'=> $ups_access_point->AddressKeyFormat->PoliticalDivision2,
					'PostalCode'=>$ups_access_point->AddressKeyFormat->PostcodePrimaryLow,
					'CountryCode'=>$ups_access_point->AddressKeyFormat->CountryCode,
				);
			}
			if ( count( $data['RateRequest']['Shipment']['Service'] ) == 0 ) {
				unset( $data['RateRequest']['Shipment']['Service'] );
			}
			return $data;
		}

		/**
		 * @param array $data
		 *
		 * @return object
		 * @throws Exception
		 */
		public function rate_request( $data ) {
			$response = $this->post( $data );
			if ( is_wp_error( $response ) ) {
				throw new Flexible_Shipping_UPS_UPS_REST_API_Connection_Exception( sprintf( __( 'UPS API connection: %s', 'flexible-shipping-ups' ), $response->get_error_message() ) );
			}
			if ( $response['response']['code'] != 200 ) {
				throw new Exception( sprintf( __( 'UPS API message: %s', 'flexible-shipping-ups' ), $response['response']['message'] ) );
			}
			else {
				$json = json_decode( $response['body'] );
				if ( isset( $json->Fault ) ) {
					if ( isset( $json->Fault->faultcode ) && $json->Fault->faultcode == 'Client' ) {
						$error_detail = $json->Fault->detail->Errors->ErrorDetail;
						if ( is_array( $error_detail ) ) {
							$error_detail = $error_detail[count( $error_detail ) - 1];
						}
						if ( $error_detail->Severity == 'Authentication' ) {
							throw new Flexible_Shipping_UPS_UPS_REST_API_Login_Fault_Exception(  $error_detail->PrimaryErrorCode->Code . ' - ' . $error_detail->PrimaryErrorCode->Description );
						}
						else {
							throw new Flexible_Shipping_UPS_UPS_REST_API_Exception( $error_detail->PrimaryErrorCode->Code . ' - ' .  $error_detail->PrimaryErrorCode->Description );
						}
					}
					if ( isset( $json->Fault->detail ) && isset( $json->Fault->detail->Errors )  && isset( $json->Fault->detail->Errors->ErrorDetail ) ) {
						throw new Flexible_Shipping_UPS_UPS_REST_API_Exception( $json->Fault->detail->Errors->ErrorDetail->PrimaryErrorCode->Code . ' - ' . $json->Fault->detail->Errors->ErrorDetail->PrimaryErrorCode->Description );
					}
					throw new Exception( $json->Fault->faultcode . ' - ' . $json->Fault->faultstring );
				}
			}
			return $json;
		}

		/**
		 * @param $data
		 *
		 * @return array|WP_Error
		 */
		public function post( $data ) {
			$url = $this->api_host;
			$args = array(
				'headers' => array(
					'Access-Control-Allow-Headers'  => 'Origin, X-Requested-With, Content-Type, Accept',
					'Access-Control-Allow-Methods'  => 'POST',
					'Access-Control-Allow-Origin'   => '*',
					'Content-Type'                  => 'application/json',
				),
				'body' => json_encode( $data, JSON_PRETTY_PRINT  ),
			);
			return wp_remote_post( $url, $args );
		}

	}

}

