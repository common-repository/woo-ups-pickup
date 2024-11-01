<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'Flexible_Shipping_UPS_Constans' ) ) {

	class Flexible_Shipping_UPS_Constans {

		/**
		 * @var array
		 */
		private static $services = null;

		/**
		 * @return array
		 */
		public static function get_services() {
			if ( empty( Flexible_Shipping_UPS_Constans::$services ) ) {
				Flexible_Shipping_UPS_Constans::$services = array(
					'all'   => array(
						'96' => __( 'UPS Worldwide Express Freight', 'flexible-shipping-ups' ),
						'71' => __( 'UPS Worldwide Express Freight Midday', 'flexible-shipping-ups' ),
					),
					'other' => array(
						'07' => __( 'UPS Express', 'flexible-shipping-ups' ),
						'11' => __( 'UPS Standard', 'flexible-shipping-ups' ),
						'08' => __( 'UPS Worldwide Expedited', 'flexible-shipping-ups' ),
						'54' => __( 'UPS Worldwide Express Plus', 'flexible-shipping-ups' ),
						'65' => __( 'UPS Worldwide Saver', 'flexible-shipping-ups' ),
					),
					'PR' => array( // Puerto Rico
						'02' => __( 'UPS 2nd Day Air', 'flexible-shipping-ups' ),
						'03' => __( 'UPS Ground', 'flexible-shipping-ups' ),
						'01' => __( 'UPS Next Day Air', 'flexible-shipping-ups' ),
						'14' => __( 'UPS Next Day Air Early', 'flexible-shipping-ups' ),
						'08' => __( 'UPS Worldwide Expedited', 'flexible-shipping-ups' ),
						'07' => __( 'UPS Worldwide Express', 'flexible-shipping-ups' ),
						'54' => __( 'UPS Worldwide Express Plus', 'flexible-shipping-ups' ),
						'65' => __( 'UPS Worldwide Saver', 'flexible-shipping-ups' ),
					),
					'PL' => array( // Poland
						'70' => __( 'UPS Access Point Economy', 'flexible-shipping-ups' ),
						'83' => __( 'UPS Today Dedicated Courrier', 'flexible-shipping-ups' ),
						'85' => __( 'UPS Today Express', 'flexible-shipping-ups' ),
						'86' => __( 'UPS Today Express Saver', 'flexible-shipping-ups' ),
						'82' => __( 'UPS Today Standard', 'flexible-shipping-ups' ),
						'08' => __( 'UPS Expedited', 'flexible-shipping-ups' ),
						'07' => __( 'UPS Express', 'flexible-shipping-ups' ),
						'54' => __( 'UPS Express Plus', 'flexible-shipping-ups' ),
						'65' => __( 'UPS Express Saver', 'flexible-shipping-ups' ),
						'11' => __( 'UPS Standard', 'flexible-shipping-ups' ),
					),
					'MX' => array( // Mexico
						'70' => __( 'UPS Access Point Economy', 'flexible-shipping-ups' ),
						'08' => __( 'UPS Expedited', 'flexible-shipping-ups' ),
						'07' => __( 'UPS Express', 'flexible-shipping-ups' ),
						'11' => __( 'UPS Standard', 'flexible-shipping-ups' ),
						'54' => __( 'UPS Worldwide Express Plus', 'flexible-shipping-ups' ),
						'65' => __( 'UPS Worldwide Saver', 'flexible-shipping-ups' ),
					),
					'EU' => array( // European Union
						'70' => __( 'UPS Access Point Economy', 'flexible-shipping-ups' ),
						'08' => __( 'UPS Expedited', 'flexible-shipping-ups' ),
						'07' => __( 'UPS Express', 'flexible-shipping-ups' ),
						'11' => __( 'UPS Standard', 'flexible-shipping-ups' ),
						'54' => __( 'UPS Worldwide Express Plus', 'flexible-shipping-ups' ),
						'65' => __( 'UPS Worldwide Saver', 'flexible-shipping-ups' ),
					),
					'CA' => array( // Canada
						'02' => __( 'UPS Expedited', 'flexible-shipping-ups' ),
						'13' => __( 'UPS Express Saver', 'flexible-shipping-ups' ),
						'12' => __( 'UPS 3 Day Select', 'flexible-shipping-ups' ),
						'70' => __( 'UPS Access Point Economy', 'flexible-shipping-ups' ),
						'01' => __( 'UPS Express', 'flexible-shipping-ups' ),
						'14' => __( 'UPS Express Early', 'flexible-shipping-ups' ),
						'65' => __( 'UPS Express Saver', 'flexible-shipping-ups' ),
						'11' => __( 'UPS Standard', 'flexible-shipping-ups' ),
						'08' => __( 'UPS Worldwide Expedited', 'flexible-shipping-ups' ),
						'07' => __( 'UPS Worldwide Express', 'flexible-shipping-ups' ),
						'54' => __( 'UPS Worldwide Express Plus', 'flexible-shipping-ups' ),
					),
					'US' => array( // USA
						'11' => __( 'UPS Standard', 'flexible-shipping-ups' ),
						'07' => __( 'UPS Worldwide Express', 'flexible-shipping-ups' ),
						'08' => __( 'UPS Worldwide Expedited', 'flexible-shipping-ups' ),
						'54' => __( 'UPS Worldwide Express Plus', 'flexible-shipping-ups' ),
						'65' => __( 'UPS Worldwide Saver', 'flexible-shipping-ups' ),
						'02' => __( 'UPS 2nd Day Air', 'flexible-shipping-ups' ),
						'59' => __( 'UPS 2nd Day Air A.M.', 'flexible-shipping-ups' ),
						'12' => __( 'UPS 3 Day Select', 'flexible-shipping-ups' ),
						'03' => __( 'UPS Ground', 'flexible-shipping-ups' ),
						'01' => __( 'UPS Next Day Air', 'flexible-shipping-ups' ),
						'14' => __( 'UPS Next Day Air Early', 'flexible-shipping-ups' ),
						'13' => __( 'UPS Next Day Air Saver', 'flexible-shipping-ups' ),
					),
				);
			}

			return Flexible_Shipping_UPS_Constans::$services;
		}

		/**
		 * @param string $country_code
		 *
		 * @return array
		 */
		public static function get_services_for_country( $country_code ) {
			$services = Flexible_Shipping_UPS_Constans::get_services();
			$services_for_country = array();
			if ( isset( $services[$country_code] ) ) {
				foreach ( $services[$country_code] as $service => $service_name ) {
					$services_for_country[$service] = $service_name;
				}
			}
			$eu_countries = WC()->countries->get_european_union_countries();
			if ( $country_code != 'PL' && in_array( $country_code, $eu_countries ) ) {
				foreach ( $services['EU'] as $service => $service_name ) {
					$services_for_country[$service] = $service_name;
				}
			}
			if ( count( $services_for_country ) == 0 ) {
				foreach ( $services['other'] as $service => $service_name ) {
					$services_for_country[$service] = $service_name;
				}
			}
			foreach ( $services['all'] as $service => $service_name ) {
				$services_for_country[$service] = $service_name;
			}
			return $services_for_country;
		}

	}

}
