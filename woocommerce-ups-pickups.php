<?php
/**
* Plugin Name: OPSI Israel Domestic Shipments
* Plugin URI: https://pickuppoint.co.il/Documentation/WP
* Description: A shipping plugin for WooCommerce that allows the store operator to define local pickup locations, which the customer can then choose from when making a purchase.
* Version: 2.6.3
* Author: O.P.S.I (International Handling) Ltd
* Author URI: https://pickuppoint.co.il
* License: GPL3
* License URI: http://www.gnu.org/licenses/gpl-3.0.html
* Text Domain: woo-ups-pickup
* Domain Path: /i18n/languages
* WC requires at least: 3.0.0
* WC tested up to: 8.6.1
*
* Copyright: (c) 2016-2018 O.P.S.I (International Handling) Ltd
*
* WooCommerce UPS PickUP is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 2 of the License, or
* any later version.
*
* WooCommerce UPS Israel PickUP Access Points (Stores and Lockers) is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with WooCommerce UPS Israel PickUP Access Points (Stores and Lockers). If not, see  https://www.gnu.org/licenses/gpl-2.0.html.
*
* @package     WC-Shipping-Ups-Pickups
* @author      O.P.S.I (International Handling) Ltd
* @category    Shipping
* @copyright   Copyright: (c) 2016-2018 O.P.S.I (International Handling) Ltd
* @license     http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
*/

use Ups\Admin;
use Ups\App;
use Ups\Filesystem;
use Ups\Helper\Ups;
use Ups\Order\Api;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if( !class_exists('WC_Ups_PickUps') )
{
	/**
	 * Main WC_Ups_PickUps Class
	 *
	 * @since 1.0
	 */
	class WC_Ups_PickUps {

		/** plugin version number */
        const VERSION = '2.6.3';

        const CLOSEST_POINTS_SELECT_DESIGN_DROPDOWN = '1';

		/** shipping method id */
        const METHOD_ID = 'woo-ups-pickups';

		/** plugin text domain */
		const TEXT_DOMAIN = 'woo-ups-pickup';

		/** shipping method class name */
		const METHOD_CLASS_NAME = 'WC_Ups_PickUps_Method';

        /** Product PickUps Pickup Points Attribute */
        const PRODUCT_PICKUPS_PICKUP_POINTS_ATTRIBUTE = 'product_pickups_pickup_points_attribute';
        const PRODUCT_PICKING_LIST_BARCODE_ATTRIBUTE = 'product_picking_list_barcode_attribute';
        const PRODUCT_PICKING_LIST_LOCATION_ATTRIBUTE = 'product_picking_list_location_attribute';
        const PRODUCT_PICKING_LIST_REMARKS_ATTRIBUTE = 'product_picking_list_remarks_attribute';

		const MY_ORDERS_ACTIONS_TRACK_BUTTON_SLUG = 'track_shipping_number';

        /** @var WC_Ups_PickUps_Method single instance of this plugin */
		protected static $instance;

        /**
         * @var Ups
         */
		protected $helper;

        /**
         * Setup main plugin class
         *
         * @since 1.0
         * @see WC_Ups_PickUps::__construct()
         */
		public function __construct() {
		    global $woocommerce;

			$this->id = 'wc_shipping_ups_pickups';
			$this->text_domain = 'woo-ups-pickup';
			$this->includes();

            add_action( 'plugins_loaded', array( $this, 'init' ), 0 );
            add_action( 'plugins_loaded', array( $this, 'init_session' ), 0 );
			add_action( 'woocommerce_shipping_init', array( $this, 'load_class') );
			add_filter( 'woocommerce_shipping_methods', array( $this, 'load_method') );
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'wupus_plugin_action_links' ) );
            add_filter( 'woocommerce_my_account_my_orders_actions', array($this, 'display_shipping_tracking_button'), 10, 2);
            add_action( 'woocommerce_after_account_orders', array($this, 'shipping_tracking_button_in_new_tab'));

            /**
             * Pickup Point Shipping Method Disabled Observer
             *
             * @since 1.6.0
             */
            add_filter('woocommerce_package_rates', array($this, 'pickup_point_shipping_method_disabled_observer'), 10, 2);

            /**
             * Add Pickup Point on Thank you page
             *
             * @since 1.9.0
             */
            add_action( 'woocommerce_order_details_after_order_table', array( $this, 'display_pickup_point_on_thank_you_page' ), 10, 1 );


            /**
             * Auto Select PickUps shipping method
             *
             * @since 1.9.0
             */
            add_action( 'woocommerce_before_cart', array($this, 'auto_select_pickups_shipping_method') );
            add_action( 'woocommerce_before_checkout_form', array($this, 'auto_select_pickups_shipping_method') );
            add_action( 'woocommerce_new_order', array($this, 'shipping_method_change_observer'), 50, 3 );

            /**
             * Change Pickup Point Admin Page
             *
             * @since 1.6.0
             */
            $this->admin_page_change_pickup_point();

            /**
             * Override WooCommerce Templates
             *
             * @since 1.10.2
             */
            add_filter( 'woocommerce_locate_template', array($this, 'pickups_woocommerce_override_templates'), 1, 3 );


            /**
             * Change Pickup Closest Points Select Design
             *
             * @since 2.4.3
             */
            add_filter( 'wc_get_template', array($this, 'change_pickups_closest_points_select_design'));

            /**
             * Add Checkout fields
             *
             * @since 2.5.0
             */
            add_filter( 'woocommerce_default_address_fields' , array($this, 'woocommerce_checkout_add_checkout_fields') );
            add_action( 'woocommerce_checkout_order_processed', array($this, 'woocommerce_checkout_update_fields'), 10, 2 );

            /**
             * Add Admin Order Custom Fields for shipment
             *
             * @since 2.5.0
             */
            add_action( 'woocommerce_admin_order_data_after_order_details' , array($this, 'woocommerce_admin_order_custom_fields_shipment') );
            add_action( 'woocommerce_process_shop_order_meta', array($this, 'woocommerce_admin_order_custom_fields_shipment_save') );
        }


        /**
         * Init WC_Ups_PickUps when WordPress Initializes.
         *
         * @since 1.8.9
         */
        public function init() {
            // Set up localization.
			$this->load_textdomain();
			$this->create_product_attributes();

            $this->helper = new Ups();
        }

        /**
         *
         * @since 2.3.0
         */
        public function init_session(){
            if (!headers_sent() && ! session_id() ) {
                session_start([
                    'read_and_close' => true,
                ]);
            }
        }

        /**
         * Create Product Attributes
         * @since 1.6.0
         */
        private function create_product_attributes(){

            $attributes = [
                [
                    'action' => 'woocommerce_product_options_shipping',
                    'id' => self::PRODUCT_PICKUPS_PICKUP_POINTS_ATTRIBUTE,
                    'label' => 'Pickup Points',
                    'description' => 'Ups Pickup - Product Pickup Points'
                ],
                [
                    'action' => 'woocommerce_product_options_inventory_product_data',
                    'id' => self::PRODUCT_PICKING_LIST_BARCODE_ATTRIBUTE,
                    'label' => 'Barcode',
                    'description' => 'Ups Pickup - Picking List Barcode'
                ],
                [
                    'action' => 'woocommerce_product_options_inventory_product_data',
                    'id' => self::PRODUCT_PICKING_LIST_LOCATION_ATTRIBUTE,
                    'label' => 'Picking Location',
                    'description' => 'Ups Pickup - Picking List Location'
                ],
                [
                    'action' => 'woocommerce_product_options_inventory_product_data',
                    'id' => self::PRODUCT_PICKING_LIST_REMARKS_ATTRIBUTE,
                    'label' => 'Item Remarks',
                    'description' => 'Ups Pickup - Picking List Item Remarks'
                ]
            ];

            foreach($attributes as $attribute) {

                /**
                 * Add Product Attribute
                 */
                add_action($attribute['action'], static function() use ($attribute) {
                    $args = array(
                        'id' => $attribute['id'],
                        'label' => __($attribute['label'], WC_Ups_PickUps::TEXT_DOMAIN),
                        'desc_tip' => true,
                        'description' => __($attribute['description'], WC_Ups_PickUps::TEXT_DOMAIN),
                    );
                    woocommerce_wp_text_input($args);
                });

                /**
                 * Save Product Attribute
                 * @param $post_id
                 */
                add_action('woocommerce_process_product_meta', static function($post_id) use ($attribute)
                {
                    $product = wc_get_product($post_id);
                    $title = isset($_POST[$attribute['id']]) ? $_POST[$attribute['id']] : '';
                    $product->update_meta_data($attribute['id'], sanitize_text_field($title));
                    $product->save();
                });
            }
        }

        /**
		 * Loads the plugin language files.
		 *
		 * @since  1.0
		 * @access public
		 *
		 * @return void
		 */
		public function load_textdomain() {

			// Set filter for languages directory
			$lang_dir = basename( dirname( __FILE__ ) ) . '/i18n/languages/';
			$lang_dir = apply_filters( 'pickups_languages_directory', $lang_dir );

			// Traditional WordPress plugin locale filter.
			$locale = is_admin() && function_exists( 'get_user_locale' ) ? get_user_locale() : get_locale();
			$locale = apply_filters( 'plugin_locale', $locale, WC_Ups_PickUps::TEXT_DOMAIN );

            unload_textdomain( WC_Ups_PickUps::TEXT_DOMAIN );
			load_textdomain( WC_Ups_PickUps::TEXT_DOMAIN, WP_LANG_DIR . '/woo-ups-pickup/woocommerce-shipping-ups-pick-ups-' . $locale . '.mo' );
            load_plugin_textdomain( WC_Ups_PickUps::TEXT_DOMAIN, false, $lang_dir );
        }

		/**
         * Loads Shipping Method classes
         *
         * @since  1.0
         */
		public function load_class() {

			include_once ( plugin_dir_path( __FILE__ ) . 'includes/class-wc-shipping-ups-pickup.php') ;
            $this->ups_pickups_method = new WC_Ups_PickUps_Method();
            add_filter('woocommerce_cart_shipping_method_full_label', array($this->ups_pickups_method, 'cart_shipping_method_full_label'), 10, 3);
        }

        /**
         * Include required files
         *
         * @since 1.8.0
         */
		private function includes() {

            if ( is_admin() )
            {
				require_once( plugin_dir_path( __FILE__ ) . 'includes/admin/class-wc-shipping-shipping-ups-pick-ups-shop-order-cpt.php' );
                new WC_Shipping_Ups_PickUps_CPT();
            }
        }

        /**
         * Add the Shipping Method to WooCommerce
         *
         * @since 1.0
         * @param array array of shipping method class names or objects
         * @return array of shipping method class names or objects
         */
		public function load_method( $methods ) {
            $methods['WC_Ups_PickUps_Method'] = 'WC_Ups_PickUps_Method';
			return $methods;
		}

        /**
         * Show action links on the plugin screen.
         *
         * @param   mixed $links Plugin Action links.
         * @return  array
         */
		public function wupus_plugin_action_links( $actions ) {
			$custom_actions = array();
			$custom_actions['configure'] = sprintf( '<a href="%s">%s</a>', admin_url( 'admin.php?page=wc-settings&tab=shipping&section=' . strtolower( self::METHOD_CLASS_NAME  ) ), __( 'Configure', $this->text_domain ) );
			$custom_actions['docs'] = sprintf( '<a href="%s">%s</a>', 'https://pickuppoint.co.il/Documentation/WP', __( 'Docs', $this->text_domain ) );
            $custom_actions['support'] = sprintf( '<a href="%s">%s</a>', 'https://pickuppoint.co.il/', __( 'Support', $this->text_domain ) );

			return array_merge( $custom_actions, $actions );
		}

        /**
         * Disable Ups Pickups Shipping Method
         * If Customer Selected Country Isn't IL
         *
         * @since 1.6.0
         */
        public function pickup_point_shipping_method_disabled_observer($available_shipping_methods){
            global $woocommerce;
            $settings = get_option('woocommerce_woo-ups-pickups_settings');

            /**
             * Remove Pick Ups If Set "Hide Method If Country Not Israel"
             * in Admin Panel
             */
            if(isset($settings['hide_method_if_country_not_israel']) && $settings['hide_method_if_country_not_israel'] === 'yes') {
                $customer_country = $woocommerce->customer->get_shipping_country();
                if ($customer_country !== 'IL') {
                    unset($available_shipping_methods[self::METHOD_ID]);
                }
            }

            /**
             * Remove Pick Ups If Points Over the Maximum
             */
            if($this->helper->isPickUpsProductsPointsOverTheMax()){
                unset($available_shipping_methods[self::METHOD_ID]);
            }

            return $available_shipping_methods;
        }

        /**
         * Change Pickup Point Admin Page
         *
         * @since 1.6.0
         */
        private function admin_page_change_pickup_point(){
            function add_change_pickup_point_menu() {
                add_submenu_page(
                    null,
                    __( 'Change Pickup Point' ),
                    __( 'Change Pickup Point' ),
                    'manage_options',
                    'change-pickup-point',
                    'change_pickup_point_page'
                );
            }

            add_action( 'admin_menu', 'add_change_pickup_point_menu' );

            if(isset($_GET['page']) && $_GET['page'] === 'change-pickup-point') {
                add_action('admin_enqueue_scripts', function () {
                    $settings = get_option('woocommerce_woo-ups-pickups_settings');
                    $stores_lockers = $settings['stores_lockers'] ?: 'stores_lockers';
                    $googleMapsApiKey = $this->helper->getOption('google_maps_api_key');

                    $handle = '';


                    switch ($stores_lockers) {
                        case 'stores_lockers':
                            $handle = 'stores-lockers';
                            wp_enqueue_script('stores-lockers', plugins_url('/includes/js/stores-lockers.js', __FILE__), array('jquery'), WC_Ups_PickUps::VERSION, 'in_footer');
                            break;
                        case 'stores':
                            $handle = 'stores';
                            wp_enqueue_script('stores', plugins_url('/includes/js/stores.js', __FILE__), array('jquery'), WC_Ups_PickUps::VERSION, 'in_footer');
                            break;
                        case 'lockers':
                            $handle = 'lockers';
                            wp_enqueue_script('lockers', plugins_url('/includes/js/lockers.js', __FILE__), array('jquery'), WC_Ups_PickUps::VERSION, 'in_footer');
                            break;
                    }

                    wp_localize_script( $handle, 'data', array('googleMapsApiKey' => $googleMapsApiKey) );

                    wp_enqueue_script('admin-change-pickup-point', plugins_url('/includes/js/admin-change-pickup-point.js', __FILE__), array('jquery'), WC_Ups_PickUps::VERSION, 'in_footer');

                });
            }

            function change_pickup_point_page() {
                $orderId = $_GET['order_id'];

                $orderPage = isset($_GET['order_page']) ? $_GET['order_page'] : '';

                $changePickupPointUrl = admin_url('admin-ajax.php?action=change_pickup_point&order_page='.$orderPage.'&order_id='. $orderId);
                ?>
                <h2>
                    <?php esc_html_e( 'Change Pickup Point For Order #'.$orderId, WC_Ups_PickUps::TEXT_DOMAIN); ?>
                </h2>

                <form class="change-pickup-point-form" method="POST" action="<?= $changePickupPointUrl ?>">
                    <input type="hidden" name="pickups_location1" id="pickups_location1" value="" />
                    <input type="hidden" name="pickups_location2" id="pickups_location2" value="" />

                    <div class="ups-pickups-desc"><?php echo __("Click here to select your PickUP location", WC_Ups_PickUps::TEXT_DOMAIN) ?></div>
                    <div onclick="window.PickupsSDK.onClick();return;" class="ups-pickups ups-pickups-48" data-provider="as453ffadfgds"></div>
                    <div class="ups-pickups-info"></div>
                    <input type="submit" class="button-primary" id="change-pickup-point-btn" value="<?= _e('Change Pickup Point', WC_Ups_PickUps::TEXT_DOMAIN) ?>" />
                </form>
                <?php
            }
        }

        /**
         *
         * Display Tracking Button on My Orders
         *
         * @param $actions
         * @param $order
         * @return mixed
         *
         * @since 1.7.0
         */
        public function display_shipping_tracking_button($actions, $order){

            if ( $shipmentNumber = $order->get_meta('ups_ship_number') ) {
                $action_slug = self::MY_ORDERS_ACTIONS_TRACK_BUTTON_SLUG;

                $url = Ups::TRACKING_URL.$shipmentNumber;

                $actions[$action_slug] = array(
                    'url' => $url,
                    'name' => __('Track UPS', self::TEXT_DOMAIN),
                );
            }
            return $actions;
        }


        /**
         * Open Shipping Tracking Button in new tab
         *
         * @since 1.7.0
         */
        public function shipping_tracking_button_in_new_tab() {
            $action_slug = self::MY_ORDERS_ACTIONS_TRACK_BUTTON_SLUG;
            ?>
            <script>
                jQuery(function($){
                    $('a.<?php echo $action_slug; ?>').each( function(){
                        $(this).attr('target','_blank');
                    })
                });
            </script>
            <?php
        }

        /**
         * Add Pickup Point on Thank you page
         *
         * @param $order
         * @since 1.9.0
         */
        public function display_pickup_point_on_thank_you_page( $order ) {
            $pickupPoint = $this->helper->getOrderPickupPointJson($order);
            if ( $pickupPoint !== '' ) {
                $pickupPointHtml = $this->helper->get_formatted_address_helper($pickupPoint);
                $pickupPointTitle = $this->helper->getThankYouPagePickupPointTitle();
                include_once __DIR__ . '/templates/thank-you-page-pickup-point.php';
            }
        }

        /**
         * Auto Select PickUps shipping method
         *
         * @since 1.9.0
         */
        public function auto_select_pickups_shipping_method(){
            $settings = get_option('woocommerce_woo-ups-pickups_settings');

            if(isset($settings['is_default']) && $settings['is_default'] === 'yes' && !WC()->session->get('chosen_shipping_methods_default_ups_is_set')) {
                $shippingPackages = WC()->session->get('shipping_for_package_0');
                if(is_array($shippingPackages) && isset($shippingPackages['rates'])) {
                    $rates = $shippingPackages['rates'];
                    foreach ($rates as $key => $rate) {
                        if ($rate->method_id === 'woo-ups-pickups') {
                            WC()->session->set('chosen_shipping_methods_default_ups_is_set', true);
                            WC()->session->set('chosen_shipping_methods', array($rate->id));
                            return;
                        }
                    }
                }
            }
        }

        /**
         * Clean session after Place Order
         *
         * @since 1.9.0
         */
        public function shipping_method_change_observer(){
            if(isset(WC()->session)){
                WC()->session->set('chosen_shipping_methods_default_ups_is_set', false);
            }
        }

        /**
         * Override WooCommerce Templates
         *
         * @param $template
         * @param $template_name
         * @param $template_path
         * @return string
         *
         * @since 1.10.2
         */
        function pickups_woocommerce_override_templates( $template, $template_name, $template_path ) {
            global $woocommerce;
            $_template = $template;
            if ( ! $template_path ) {
                $template_path = $woocommerce->template_url;
            }

            $plugin_path  = untrailingslashit( plugin_dir_path( __FILE__ ) )  . '/templates/woocommerce/';

            $template = locate_template(
                array(
                    $template_path . $template_name,
                    $template_name
                )
            );

            if( ! $template && file_exists( $plugin_path . $template_name ) ) {
                $template = $plugin_path . $template_name;
            }

            if ( ! $template ) {
                $template = $_template;
            }

            return $template;
        }

        /**
         * Change Pickup Closest Points Select Design
         *
         * @param $template
         * @return string
         *
         * @since 2.4.3
         */
        function change_pickups_closest_points_select_design($template){
            if($this->is_closest_points_select_design_is_default()){
                return $template;
            }

            if(strpos($template, 'cart/cart-shipping.php') !== false){
                $template = WP_PLUGIN_DIR . '/woo-ups-pickup/templates/woocommerce/cart/custom-cart-shipping.php';
            }
            return $template;
        }

        /**
         * @return bool
         *
         * @since 2.4.3
         */
        function is_closest_points_select_design_is_default(){
            $settings = get_option('woocommerce_woo-ups-pickups_settings');

            $closestPointsIsActive = isset($settings['pickups_closest_points_active']) && $settings['pickups_closest_points_active'] === '1';
            $closestPointsDesignIsDefault = empty($settings['pickups_closest_points_select_design']) || (isset($settings['pickups_closest_points_select_design']) && $settings['pickups_closest_points_select_design'] !== WC_Ups_PickUps::CLOSEST_POINTS_SELECT_DESIGN_DROPDOWN);

            return !$closestPointsIsActive || $closestPointsDesignIsDefault;
        }

        /**
         * @param $fields
         * @return mixed
         *
         * @since 2.5.0
         */
        public function woocommerce_checkout_add_checkout_fields( $fields ) {
            try {
                $settings = get_option('woocommerce_woo-ups-pickups_settings');
                $checkoutFieldsAdditional = isset($settings['checkout_fields_additional']) ? $settings['checkout_fields_additional'] : 0;

                if($checkoutFieldsAdditional === 'floor' || $checkoutFieldsAdditional === 'floor_and_room') {
                    $fields['floor_num'] = array(
                        'type' => 'number',
                        'class' => array('address-field shipping-pickups-hide'),
                        'label' => __('Floor Number'),
                        'required' => false,
                    );
                }
                if($checkoutFieldsAdditional === 'room' || $checkoutFieldsAdditional === 'floor_and_room') {
                    $fields['room_num'] = array(
                        'type' => 'number',
                        'class' => array('address-field shipping-pickups-hide'),
                        'label' => __('Room Number'),
                        'required' => false,
                    );
                }
            } catch (\Exception $e){

            }
            return $fields;
        }

        /**
         * @param $order_id
         * @param $posted
         * @since 2.5.0
         */
        public function woocommerce_checkout_update_fields($order_id, $posted){
            $order = wc_get_order( $order_id );

            if(isset($posted['shipping_floor_num'])) {
                $order->update_meta_data('ups_order_shipping_floor_num', wc_clean($posted['shipping_floor_num']));
            }
            if(isset($posted['shipping_room_num'])) {
                $order->update_meta_data('ups_order_shipping_room_num', wc_clean($posted['shipping_room_num']));
            }
        }

        /**
         * @param $order
         *
         * @since 2.5.0
         */
        public function woocommerce_admin_order_custom_fields_shipment($order){
            if(!$isPickups = $this->helper->isShippingMethodIsPickupUpsInclClosestPoints($order)){

                $customerType = get_option('pickups_integration_customer_type');

                if($customerType === Ups::CUSTOMER_TYPE_CREDIT){
                    $syncFlag = $order->get_meta('ups_sync_flag');
                    $settings = get_option('woocommerce_woo-ups-pickups_settings');

                    $shipmentAdditionalFields = isset($settings['shipment_additional_fields']) ? $settings['shipment_additional_fields'] : false;
                    if($shipmentAdditionalFields && count($shipmentAdditionalFields) > 0){
                        $isActiveIsDDO = in_array('IsDDO', $shipmentAdditionalFields);
                        $isActiveCOD = in_array('COD', $shipmentAdditionalFields);
                        $isActiveIsUDR = in_array('IsUDR', $shipmentAdditionalFields);
                        $isActiveIsReturn = in_array('IsReturn', $shipmentAdditionalFields);
                        ?>
                        <br class="clear" />
                        <h3>Additional UPS Fields <?php if($syncFlag != Api::STATUS_SEND_SUCCESS){ ?><a href="#" class="edit_address">Edit</a><?php } ?></h3>
                        <?php
                        $is_ddo = $order->get_meta( 'ups_is_ddo' );
                        $cod_details = $order->get_meta( 'ups_cod_details' );
                        $cod_value = $order->get_meta( 'ups_cod_value' );
                        $is_udr = $order->get_meta( 'ups_is_udr' );
                        $is_return = $order->get_meta( 'ups_is_return' );
                        ?>
                        <div class="address">
                            <?php if($isActiveIsDDO){ ?><p><strong>IsDDO:</strong> <?php echo $is_ddo === '1' ? 'Yes' : ($is_ddo === '0' ? 'No' : '') ?></p><?php } ?>
                            <?php if($isActiveCOD){ ?><p><strong>CODDetails:</strong> <?php echo esc_html( $cod_details ) ?></p>
                            <p><strong>CODValue:</strong> <?php echo esc_html( $cod_value ) ?></p><?php } ?>
                            <?php if($isActiveIsUDR){ ?><p><strong>IsUDR:</strong> <?php echo $is_udr === '1' ? 'Yes' : ($is_udr ? 'No' : '') ?></p><?php } ?>
                            <?php if($isActiveIsReturn){ ?><p><strong>SWAP:</strong> <?php echo $is_return === '1' ? 'Yes' : ($is_return ? 'No' : '') ?></p><?php } ?>
                        </div>
                        <?php if($syncFlag != Api::STATUS_SEND_SUCCESS){ ?>
                        <div class="edit_address">
                            <?php

                            if($isActiveIsDDO) {
                                woocommerce_wp_radio(array(
                                    'id' => 'ups_is_ddo',
                                    'label' => 'IsDDO',
                                    'value' => $is_ddo,
                                    'options' => array(
                                        '0' => 'No',
                                        '1' => 'Yes'
                                    ),
                                    'style' => 'width:16px',
                                    'wrapper_class' => 'form-field-wide'
                                ));
                            }

                            if($isActiveCOD) {
                                woocommerce_wp_text_input(array(
                                    'id' => 'ups_cod_details',
                                    'label' => 'CODDetails',
                                    'value' => $cod_details,
                                    'custom_attributes' => array( 'maxlength' => '50' ),
                                    'wrapper_class' => 'form-field-wide'
                                ));

                                woocommerce_wp_text_input(array(
                                    'id' => 'ups_cod_value',
                                    'label' => 'CODValue',
                                    'value' => $cod_value,
                                    'type' => 'number',
                                    'custom_attributes' => array( 'step' => '0.1', 'max' => '50000', 'min' => '0' ),
                                    'wrapper_class' => 'form-field-wide',
                                    'desc_tip' => false,
                                    'description' => 'איסוף התשלום במזומן מוגבל עד 5,000 ש"ח ובצ\'ק עד 50,000 ש"ח.',
                                ));
                            }

                            if($isActiveIsUDR) {
                                woocommerce_wp_radio(array(
                                    'id' => 'ups_is_udr',
                                    'label' => 'IsUDR',
                                    'value' => $is_udr,
                                    'options' => array(
                                        '0' => 'No',
                                        '1' => 'Yes'
                                    ),
                                    'style' => 'width:16px',
                                    'wrapper_class' => 'form-field-wide'
                                ));
                            }

                            if($isActiveIsReturn) {
                                woocommerce_wp_radio(array(
                                    'id' => 'ups_is_return',
                                    'label' => 'SWAP',
                                    'value' => $is_return,
                                    'options' => array(
                                        '0' => 'No',
                                        '1' => 'Yes'
                                    ),
                                    'style' => 'width:16px',
                                    'wrapper_class' => 'form-field-wide'
                                ));
                            }

                            ?>
                        </div>
                        <?php
                        }
                    }
                }
            }
        }

        /**
         * @param $order_id
         *
         * @since 2.5.0
         */
        public function woocommerce_admin_order_custom_fields_shipment_save($order_id){
            $order = wc_get_order( $order_id );

            if(isset($_POST[ 'ups_is_ddo' ])){
                $order->update_meta_data('ups_is_ddo', wc_clean( $_POST[ 'ups_is_ddo' ] ) );
            }
            if(isset($_POST[ 'ups_cod_details' ])){
                $order->update_meta_data('ups_cod_details', wc_clean( $_POST[ 'ups_cod_details' ] ) );
            }
            if(isset($_POST[ 'ups_cod_value' ])){
                $order->update_meta_data('ups_cod_value', wc_clean( $_POST[ 'ups_cod_value' ] ) );
            }
            if(isset($_POST[ 'ups_is_udr' ])){
                $order->update_meta_data('ups_is_udr', wc_clean( $_POST[ 'ups_is_udr' ] ) );
            }
            if(isset($_POST[ 'ups_is_return' ])){
                $order->update_meta_data('ups_is_return', wc_clean( $_POST[ 'ups_is_return' ] ) );
            }
        }

        /**
         * Main PickUP Instance, ensures only one instance is/can be loaded
         *
         * @since 1.0
         * @return WC_Ups_PickUps
         */
		public static function instance() {

			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		public function pickUpsAddClosestPoints(&$rates, $customerAddress, $settings){
            if($this->helper->isPickUpsProductsPointsOverTheMax()){
                return false;
            }

            $foundClosestPoints = false;
            $api = new Api();
            $apiResponse = $api->getClosestPoints($customerAddress, $settings);

            if(isset($apiResponse['points'])){
                $settings = get_option('woocommerce_woo-ups-pickups_settings');
                $methodPrice = $settings['cost'];
                $methodTitlePrefix = $settings['pickups_closest_points_title'] !== '' ? $settings['pickups_closest_points_title'].' - ' : '';

                $closestPointsArray = [];
                $closestPointsCount = count($apiResponse['points']);

                // loop through response points in desc order
                for($i = $closestPointsCount - 1; $i >= 0; --$i){
                    $point = $apiResponse['points'][$i];
                    $pointId = $point->PointID;
                    $id = 'woo-ups-pickups-'.$pointId;

                    $closestPointsArray[$pointId] = clone $rates['woo-ups-pickups'];

                    $pointName = $point->PointName;
                    $pointStreet = $point->StreetName. ' '.$point->HouseNumber;
                    $pointDescription = $pointStreet.', '.$point->CityName;
                    $pointDistance = '('.number_format($point->Distance, 2).' ק"מ)';

                    $methodLabel = $methodTitlePrefix . $pointName.' '.$pointDescription.' '.$pointDistance;

                    $pointData = ['iid' => $pointId, 'title' => $pointName, 'street' => $pointStreet, 'city' => $point->CityName, 'dist' => $point->Distance, 'closest_points' => true];

                    $maxAmount = $settings['max_amount'];
                    $maxPrice = $settings['price_max_amount'];

                    $maxAmountInclDiscount = $settings['max_amount_incl_discount'];

                    $cartTotal = ($maxAmountInclDiscount === 'yes') ?  WC()->cart->get_cart_contents_total() : WC()->cart->get_subtotal();

                    $this->calculate_shipping_class_price($methodPrice, $settings);

                    if($maxAmount > 0 && $cartTotal >= $maxAmount){
                        $methodPrice = $maxPrice;
                    }

                    if($methodPrice == 0){
                        $methodLabel .= ': חינם';
                    }

                    $closestPointsArray[$pointId]->set_id($id);
                    $closestPointsArray[$pointId]->set_cost($methodPrice);
                    $closestPointsArray[$pointId]->set_label($methodLabel);
                    $closestPointsArray[$pointId]->add_meta_data('point_data',json_encode($pointData));

                    $rates = array_merge(array($id => $closestPointsArray[$pointId]), $rates);

                    $foundClosestPoints = true;
                }
            }

            return $foundClosestPoints;
        }

        /**
         * @param $methodPrice
         * @param $settings
         *
         * @since 2.5.0
         */
        private function calculate_shipping_class_price(&$methodPrice, $settings){
            $lowest_shipping_class_cost = null;
            $class_cost = null;
            //$shipping_classes_id_array = $settings['shipping_classes'];

            foreach(WC()->cart->get_shipping_packages() as $package){
                foreach($package['contents'] as $content){
                    $shippingClassId = $content['data']->get_shipping_class_id();
                    /*
                    if(!in_array($shippingClassId,$shipping_classes_id_array)){
                        continue;
                    }*/

                    if($shippingClassId != 0) {
                        $class_cost = $settings['class_cost_' . $shippingClassId];
                    }

                    if(!$class_cost){
                        continue;
                    }

                    if ($lowest_shipping_class_cost === null || $lowest_shipping_class_cost > $class_cost) {
                        $lowest_shipping_class_cost = $class_cost;
                    }
                }
            }

            if($lowest_shipping_class_cost !== null){
                $methodPrice = $lowest_shipping_class_cost;
            }
        }
	}


    /**
     * Returns the One True Instance of PickUP
     *
     * @since 1.10.0
     * @return WC_Ups_PickUps
     */
	function wc_ups_pickups() {
		return WC_Ups_PickUps::instance();
    }

    $GLOBALS['wc_ups_pickups'] = wc_ups_pickups();
}

function wc_ups_shipping_order($rates, $package)
{
    $settings = get_option('woocommerce_woo-ups-pickups_settings');
    if ((isset($settings['is_first'])) && $settings['is_first'] === 'yes'
        && array_key_exists('woo-ups-pickups', $rates)
    ) {
        $rates = array('woo-ups-pickups' => $rates['woo-ups-pickups']) + $rates;
    }

    if(isset($settings['pickups_closest_points_active']) && $settings['pickups_closest_points_active'] === '1' && WC()->customer->get_shipping_country() === 'IL' && isset($rates['woo-ups-pickups'])){
        $customerAddress = [
            'address1' => WC()->customer->get_shipping_address_1(),
            'address2' => WC()->customer->get_shipping_address_2(),
            'address_city' => WC()->customer->get_shipping_city()
        ];

        $pointsNumber = $settings['pickups_closest_points_number'];

        $foundClosestPoints = false;
        if($customerAddress['address_city'] !== '' && $customerAddress['address1'] !== '' && $pointsNumber > 0) {
            $foundClosestPoints = $GLOBALS['wc_ups_pickups']->pickUpsAddClosestPoints($rates, $customerAddress, $settings);
        }

        if($settings['pickups_closest_points_show_map'] == '0' || $settings['pickups_closest_points_show_map'] == '2' && $foundClosestPoints){
            unset($rates['woo-ups-pickups']);
        }
    }

    return $rates;
}

add_filter('woocommerce_package_rates', 'wc_ups_shipping_order', 10, 2);

add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );

define('WC_UPS_BASE_FILE_PATH', __FILE__);
define('WC_UPS_PLUGIN_DIR', basename(__DIR__));

require_once __DIR__ . '/includes/ups/autoload.php';
require_once __DIR__ . '/includes/woocommerce-ups-ship-print-orders.php';

if (is_admin()) {
    // admin init
    $_upsAdmin = new Admin();
} else {
    $_upsAdmin = new App();
}
