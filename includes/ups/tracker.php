<?php

/**
 * Class Flexible_Shipping_UPS_Tracker
 */
class Flexible_Shipping_UPS_Tracker {

	const OPTION_VALUE_NO  = 'no';
	const OPTION_VALUE_YES = 'yes';

	/**
	 * Hooks.
	 */
	public function hooks() {
		add_filter( 'wpdesk_tracker_data', array( $this, 'wpdesk_tracker_data_ups' ), 11 );
		add_filter( 'wpdesk_tracker_notice_screens', array( $this, 'wpdesk_tracker_notice_screens' ) );
		add_filter( 'wpdesk_track_plugin_deactivation', array( $this, 'wpdesk_track_plugin_deactivation' ) );

		add_filter( 'plugin_action_links_flexible-shipping-ups/flexible-shipping-ups.php', array( $this, 'plugin_action_links' ), 9 );
		add_action( 'activated_plugin', array( $this, 'activated_plugin' ), 10, 2 );
	}

	/**
	 * Track plugin deactivation action.
	 *
	 * @param array $plugins Plugins.
	 *
	 * @return mixed
	 */
	public function wpdesk_track_plugin_deactivation( array $plugins ) {
		$plugins['flexible-shipping-ups/flexible-shipping-ups.php'] = 'flexible-shipping-ups/flexible-shipping-ups.php';
		return $plugins;
	}

	/**
	 * Prepare default plugin data.
	 *
	 * @param Flexible_Shipping_UPS_Shipping_Method $flexible_shipping_ups Shipping method.
	 *
	 * @return array
	 */
	private function prepare_default_plugin_data( Flexible_Shipping_UPS_Shipping_Method $flexible_shipping_ups ) {
		if ( $flexible_shipping_ups->custom_origin() ) {
			$origin_country = $flexible_shipping_ups->get_option( 'origin_country', 'not set' );
		} else {
			$woocommerce_default_country = explode( ':', get_option( 'woocommerce_default_country', '' ) );
			if ( ! empty( $woocommerce_default_country[0] ) ) {
				$origin_country = $woocommerce_default_country[0];
			} else {
				$origin_country = 'not set';
			}
		}
		$plugin_data = array(
			'custom_origin'     => $flexible_shipping_ups->get_option( 'custom_origin', self::OPTION_VALUE_NO ),
			'shipping_methods'  => 0,
			'custom_services'   => 0,
			'negotiated_rates'  => 0,
			'insurance_option'  => 0,
			'fallback'          => 0,
			'access_point'      => 0,
			'access_point_only' => 0,
			'origin_country'    => $origin_country,
			'shipping_zones'    => array(),
			'ups_services'      => array(),
		);
		return $plugin_data;
	}

	/**
	 * Append data for shipping method.
	 *
	 * @param array                                 $plugin_data Plugin data.
	 * @param WC_Shipping_Zone                      $zone Shipping zone.
	 * @param Flexible_Shipping_UPS_Shipping_Method $shipping_method Shipping method.
	 *
	 * @return array
	 */
	private function append_data_for_shipping_method(
		array $plugin_data,
		WC_Shipping_Zone $zone,
		Flexible_Shipping_UPS_Shipping_Method $shipping_method
	) {

		$plugin_data['shipping_zones'][] = $zone->get_zone_name();
		$plugin_data['shipping_methods']++;
		if ( self::OPTION_VALUE_YES === $shipping_method->get_instance_option( 'custom_services', self::OPTION_VALUE_NO ) ) {
			$plugin_data['custom_services']++;
			$enabled_services = $shipping_method->get_enabled_services();
			foreach ( $enabled_services as $enabled_service_code => $enabled_service ) {
				if ( empty( $plugin_data['ups_services'][ $enabled_service_code ] ) ) {
					$plugin_data['ups_services'][ $enabled_service_code ] = 0;
				}
				$plugin_data['ups_services'][ $enabled_service_code ]++;
			}
		}
		if ( self::OPTION_VALUE_YES === $shipping_method->get_instance_option( 'negotiated_rates', self::OPTION_VALUE_NO ) ) {
			$plugin_data['negotiated_rates']++;
		}
		if ( self::OPTION_VALUE_YES === $shipping_method->get_instance_option( 'insurance', self::OPTION_VALUE_NO ) ) {
			$plugin_data['insurance_option']++;
		}
		if ( self::OPTION_VALUE_YES === $shipping_method->get_instance_option( 'fallback', self::OPTION_VALUE_NO ) ) {
			$plugin_data['fallback']++;
		}
		$access_point_option = $shipping_method->get_instance_option( 'access_point', Flexible_Shipping_UPS_Shipping_Method::DO_NOT_ADD_ACCESS_POINTS_TO_RATES );
		if ( Flexible_Shipping_UPS_Shipping_Method::ADD_ACCESS_POINTS_TO_RATES === $access_point_option ) {
			$plugin_data['access_point']++;
		}
		if ( Flexible_Shipping_UPS_Shipping_Method::ADD_ONLY_ACCESS_POINTS_TO_RATES === $access_point_option ) {
			$plugin_data['access_point_only']++;
		}

		return $plugin_data;
	}

	/**
	 * Add plugin data tracker.
	 *
	 * @param array $data Data.
	 *
	 * @return array
	 */
	public function wpdesk_tracker_data_ups( array $data ) {
		$shipping_methods = WC()->shipping()->get_shipping_methods();
		if ( isset( $shipping_methods['flexible_shipping_ups'] ) ) {
			/**
			 * IDE type hint.
			 *
			 * @var Flexible_Shipping_UPS_Shipping_Method $flexible_shipping_ups
			 */
			$flexible_shipping_ups = $shipping_methods['flexible_shipping_ups'];

			$plugin_data = $this->prepare_default_plugin_data( $flexible_shipping_ups );

			$shipping_zones    = WC_Shipping_Zones::get_zones();
			$shipping_zones[0] = array( 'zone_id' => 0 );
			/**
			 * IDE type hint.
			 *
			 * @var WC_Shipping_Zone $zone
			 */
			foreach ( $shipping_zones as $zone_data ) {
				$zone             = new WC_Shipping_Zone( $zone_data['zone_id'] );
				$shipping_methods = $zone->get_shipping_methods( true );
				/**
				 * IDE type hint.
				 *
				 * @var WC_Shipping_Method $shipping_method
				 */
				foreach ( $shipping_methods as $shipping_method ) {
					if ( $shipping_method->id == 'flexible_shipping_ups' ) {
						$plugin_data = $this->append_data_for_shipping_method( $plugin_data, $zone, $shipping_method );
					}
				}
			}

			$data['flexible_shipping_ups'] = $plugin_data;
		}
		return $data;
	}

	public function wpdesk_tracker_notice_screens( $screens ) {
		$current_screen = get_current_screen();
		if ( $current_screen->id == 'woocommerce_page_wc-settings' ) {
			if ( isset( $_GET['tab'] ) && $_GET['tab'] == 'shipping' && isset( $_GET['section'] ) && $_GET['section'] == 'flexible_shipping_ups' ) {
				$screens[] = $current_screen->id;
			}
		}
		return $screens;
	}

	public function plugin_action_links( $links ) {
		if ( !wpdesk_tracker_enabled() || apply_filters( 'wpdesk_tracker_do_not_ask', false ) ) {
			return $links;
		}
		$options = get_option('wpdesk_helper_options', array() );
		if ( !is_array( $options ) ) {
			$options = array();
		}
		if ( empty( $options['wpdesk_tracker_agree'] ) ) {
			$options['wpdesk_tracker_agree'] = '0';
		}
		$plugin_links = array();
		if ( $options['wpdesk_tracker_agree'] == '0' ) {
			$opt_in_link = admin_url( 'admin.php?page=wpdesk_tracker&plugin=flexible-shipping-ups/flexible-shipping-ups.php' );
			$plugin_links[] = '<a href="' . $opt_in_link . '">' . __( 'Opt-in', 'flexible-shipping-ups' ) . '</a>';
		}
		else {
			$opt_in_link = admin_url( 'plugins.php?wpdesk_tracker_opt_out=1&plugin=flexible-shipping-ups/flexible-shipping-ups.php' );
			$plugin_links[] = '<a href="' . $opt_in_link . '">' . __( 'Opt-out', 'flexible-shipping-ups' ) . '</a>';
		}
		return array_merge( $plugin_links, $links );
	}

	public function activated_plugin( $plugin, $network_wide ) {
		if ( $network_wide ) {
			return;
		}
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}
		if ( !wpdesk_tracker_enabled() ) {
			return;
		}
		if ( $plugin == 'flexible-shipping-ups/flexible-shipping-ups.php' ) {
			$options = get_option('wpdesk_helper_options', array() );
			if ( empty( $options ) ) {
				$options = array();
			}
			if ( empty( $options['wpdesk_tracker_agree'] ) ) {
				$options['wpdesk_tracker_agree'] = '0';
			}
			$wpdesk_tracker_skip_plugin = get_option( 'wpdesk_tracker_skip_flexible_shipping_ups', '0' );
			if ( $options['wpdesk_tracker_agree'] == '0' && $wpdesk_tracker_skip_plugin == '0' ) {
				update_option( 'wpdesk_tracker_notice', '1' );
				update_option( 'wpdesk_tracker_skip_flexible_shipping_ups', '1' );
				if ( !apply_filters( 'wpdesk_tracker_do_not_ask', false ) ) {
					wp_redirect( admin_url( 'admin.php?page=wpdesk_tracker&plugin=flexible-shipping-ups/flexible-shipping-ups.php' ) );
					exit;
				}
			}
		}
	}

}

if ( !function_exists( 'wpdesk_tracker_enabled' ) ) {
	function wpdesk_tracker_enabled() {
		$tracker_enabled = true;
		if ( !empty( $_SERVER['SERVER_ADDR'] ) && $_SERVER['SERVER_ADDR'] == '127.0.0.1' ) {
			$tracker_enabled = false;
		}
		return apply_filters( 'wpdesk_tracker_enabled', $tracker_enabled );
		// add_filter( 'wpdesk_tracker_enabled', '__return_true' );
		// add_filter( 'wpdesk_tracker_do_not_ask', '__return_true' );
	}
}
