<?php
/**
 * WooCommerce UPS Israel PickUP Access Points (Stores and Lockers)
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@pick-ups.co.il so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade WooCommerce Ups Pickups to newer
 * versions in the future. If you wish to customize WooCommerce Ups Pickups for your
 * needs please refer to https://pick-ups.co.il/ups-pickups
 *
 * @package     WC-Shipping-Ups-Pickups
 * @author      O.P.S.I (International Handling) Ltd
 * @category    Shipping
 * @copyright   Copyright: (c) 2016-2018 O.P.S.I (International Handling) Ltd
 * @license     http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

if (class_exists('WC_Ups_PickUps_Method')) return; // Stop if the class already exists

use Ups\Helper\Ups;

class WC_Ups_PickUps_Method extends WC_Shipping_Method
{

    /** Option name for the pickup locations setting */
    const PICKUP_LOCATIONS_OPTION = 'woocommerce_pick_ups_location';

    /** @var float optional cost */
    private $cost;

    /** @var float discount amount for shipping via this method */
    //private $discount;

    /** @var array Categories of products which can only be locally picked up */
    private $categories;

    /** @var boolean When enabled, only the categories specified can be locally picked up, all other products must be shipped */
    private $categories_pickup_only;

    /** @var array Shipping_classes of products which can only be locally picked up */
    private $shipping_classes;

    /** @var boolean When enabled, only the shipping specified can be locally picked up, all other products must be shipped */
    private $shipping_classes_pickup_only;

    /** @var boolean When enabled shipped all items together */
    private $do_not_split_shipping;

    /** @var array of pickup locations */
    private $pick_ups_location;

    /** @var string When enabled, the pickup location address will be used to calculate tax rather than the customer shipping address.  One of 'yes' or 'no' */
    private $apply_pickups_location_tax;

    /** @var string when enabled the "shipping address" will be hidden during checkout if local pickup plus is enabled.  One of 'yes' or 'no' */
    private $hide_shipping_address;

    /** @var string pickup location styling on checkout, one of 'select' or 'radio' */
    private $checkout_pickups_location_styling;

    /** @var array association between a cart item and order item */
    private $cart_item_to_order_item = array();
    /** @var bool There can be only one! */
    public static $alreadyButtoned = false;
    public static $alreadyErrored = false;

    private $mo;

    /**
     * @var Ups
     */
    protected $helper;


    /**
     * Initialize the local pickup plus shipping method class
     */

    public function __construct()
    {

        $this->id = 'woo-ups-pickups';
        $this->billing_readed = false;
        $this->title = __('Your Shipping Method');

        $this->method_title = __('Pick UPS', WC_Ups_PickUps::TEXT_DOMAIN);

        $this->admin_page_description = __('Local PickUPS is a simple method which allows the customer to pick up their order themselves at a specified pickup location.', WC_Ups_PickUps::TEXT_DOMAIN);

        $this->isActionWoocommerceAfterOrderItemmetaFired = 'false';

        $this->helper = new Ups();

        // Load the settings.
        $this->init();

        // Define user set variables
        foreach ($this->settings as $setting_key => $setting) {
            $this->$setting_key = $setting;
        }

        // Load pickup locations
        $this->load_pick_ups_location();

        if ($this->is_available(array())) {
            // Add actions
            add_action('wp_enqueue_scripts', array($this, 'frontend_scripts'));
            add_action('woocommerce_after_template_part', array($this, 'review_order_shipping_pickups_location'), 10, 4); // WC 2.1+
            add_action('woocommerce_new_order_item', array($this, 'checkout_remove_json_from_shipping_order_item'), 10, 3);
            add_action('woocommerce_checkout_create_order', array($this, 'checkout_add_pickuppoint_json_to_order'), 10, 3);

            // add the local pickup location to the shipping package so that changing it forces a recalc
            add_filter('woocommerce_cart_shipping_packages', array($this, 'update_shipping_packages'));
            add_filter('woocommerce_customer_taxable_address', array($this, 'taxable_address'));

            add_filter('woocommerce_per_product_shipping_skip_free_method_ups_pick_ups', array($this, 'per_product_shipping_skip_free_method'));

            add_action('woocommerce_after_checkout_validation', array($this, 'after_checkout_validation'));

            add_filter('woocommerce_checkout_fields', array($this, 'wc_pickup_custom_override_checkout_fields'), 10000);
        }

        // Add admin actions
        if (is_admin()) {
            /**
             * Check if Ups Integration Settings is Valid
             *
             * @since 1.10.5
             */

            // Admin Order Edit page render pickup location data
            add_action('woocommerce_admin_order_data_after_shipping_address', array($this, 'admin_order_pickups_location'));
            // save shipping method options
            add_action('woocommerce_update_options_shipping_ups_pick_ups', array($this, 'process_admin_options'));
            add_filter('woocommerce_hidden_order_itemmeta', array($this, 'admin_order_hide_itemmeta'));

            add_action( 'woocommerce_update_options_shipping_' . $this->id, function(){
                $this->helper->setSettingsUpdateAt();
            } );

        }

        do_action('wc_shipping_ups_pickups_init', $this);

    }

    /**
     * Display caption and description in list shipping methods
     *
     * @since 1.3.7
     * @param string $full_label the shipping method full label including price
     * @param WC_Shipping_Rate $method the shipping method rate object
     */
    public function cart_shipping_method_full_label($full_label, $method)
    {

        if ($this->id == $method->id && $this->settings["service_description"]) {
            $full_label = $full_label . "<br /><small>" . $this->settings["service_description"] . "</small>";
        }

        return $full_label;
    }

    public function wdm_add_shipping_method_to_order_email($order, $is_admin_email)
    {
        echo '<p><h4>'. __('Shipping', WC_Ups_PickUps::TEXT_DOMAIN) .':</h4> ' . $order->get_shipping_method() . '</p>';
    }

    /**
     * Adds custom admin css stylesheet
     *
     * @since 1.0
     * @param array $columns
     * @return array $new_columns
     */
    public function render_admin_head_styles()
    {
        wp_enqueue_style('woo_ups_pickups_admin_css', dirname(__FILE__) . '/css/style.css', false, WC_Ups_PickUps::VERSION);
    }

    public function calculate_shipping($package = array())
    {

        // default cost
        $cost = $this->cost;
        $label = $this->title;
//		$location_id = isset( $package['pickups_location'] ) && is_numeric( $package['pickups_location'] ) ? $package['pickups_location'] : null;
//		$location    = is_numeric( $location_id ) ? $this->get_pickups_location_by_id( $location_id ) : null;

        // Add shipping class costs.
        $found_shipping_classes = $this->find_shipping_classes($package);

        // calculate lowest cost for shipping classes
        $lowest_class_cost = null;
        foreach ($found_shipping_classes as $shipping_class => $products) {
            if ($shipping_class === 'no_class') {
                $class_cost = $cost;
            } else {
                // Also handles BW compatibility when slugs were used instead of ids.
                $shipping_class_term = get_term_by('slug', $shipping_class, 'product_shipping_class');
                if (!$shipping_class_term && $shipping_class) {
                    continue;
                }
                $class_cost = $this->get_option('class_cost_' . $shipping_class_term->term_id, $cost);
            }

            if ('' === $class_cost) {
                continue;
            }

            if ($lowest_class_cost === null || $lowest_class_cost > $class_cost) {
                $lowest_class_cost = $class_cost;
            }
        }

        if ($lowest_class_cost !== null) {
            $cost = $lowest_class_cost;
        }

        // apply max amount price
        $maxAmountInclDiscount = $this->get_option('max_amount_incl_discount', 'no');
        $subtotal = ($maxAmountInclDiscount !== 'yes') ? $package['cart_subtotal'] : $package['contents_cost'];
        $max_amount = floatval($this->get_option('max_amount', 0));
        $price_max_amount = floatval($this->get_option('price_max_amount', 0));
        if ($max_amount && $subtotal >= $max_amount) {
            $cost = ($cost > $price_max_amount) ? $price_max_amount : $cost;
        }

        if($cost == 0){
            $label .= ': חינם';
        }

        $rate = array(
            'id' => $this->id,
            'label' => $label,
            'cost' => $cost,
        );

        $this->add_rate($rate);
    }

    /**
     * Finds and returns shipping classes and the products with said class.
     *
     * @param mixed $package Package information.
     * @return array
     */
    public function find_shipping_classes($package)
    {
        $found_shipping_classes = array();

        foreach ($package['contents'] as $item_id => $values) {
            if ($values['data']->needs_shipping()) {
                $found_class = $values['data']->get_shipping_class();
                if (!$found_class) {
                    $found_class = 'no_class';
                }

                if (!isset($found_shipping_classes[$found_class])) {
                    $found_shipping_classes[$found_class] = array();
                }

                $found_shipping_classes[$found_class][$item_id] = $values;
            }
        }

        return $found_shipping_classes;
    }


    public function is_available($package)
    {

        if (!$this->is_enabled()) return false;

        if (!empty ($this->shipping_classes) && !empty($package)) {

            list($found_products, $other_products) = $this->get_products_by_allowed_category($package['contents']);

            // there's non-pickup products, and no pickup products, so disable this shipping method for this package
            if (count($other_products) > 0 && 0 == count($found_products)) {

                return false;
            }
        }

        return apply_filters('woocommerce_shipping_' . $this->id . '_is_available', true);
    }

    private function init()
    {
        global $woocommerce;

        $this->init_form_fields();
        $this->init_settings();

        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));

    }

    /**
     * Initialize the form fields that will be displayed on the PickUP Settings page
     *
     */
    public function init_form_fields()
    {

        // get all categories for the multiselect
        $categories = array(0 => __('All Categories', WC_Ups_PickUps::TEXT_DOMAIN));
        $category_terms = get_terms('product_cat', 'orderby=name&hide_empty=0');

        if ($category_terms) {
            foreach ($category_terms as $category_term) {
                $categories[$category_term->term_id] = $category_term->name;
            }
        }
        $shipping_classes = array(0 => __('All Categories', WC_Ups_PickUps::TEXT_DOMAIN));
        $category_terms = get_terms('product_shipping_class', 'orderby=name&hide_empty=0');

        if ($category_terms) {
            foreach ($category_terms as $category_term) {
                $shipping_classes[$category_term->term_id] = $category_term->name;
            }
        }

        $this->form_fields = array(

            'enabled' => array(
                'title' => __('Enable', WC_Ups_PickUps::TEXT_DOMAIN),
                'type' => 'checkbox',
                'label' => __('Enable PickUPS', WC_Ups_PickUps::TEXT_DOMAIN),
                'default' => 'no',
                'description' => __('Enable realtime rates on Cart/Checkout page.', WC_Ups_PickUps::TEXT_DOMAIN),
                'desc_tip' => true
            ),

            'is_default' => array(
                'title' => __('Use as default shipping method', WC_Ups_PickUps::TEXT_DOMAIN),
                'type' => 'checkbox',
                'label' => ' ',
                'default' => 'no'
            ),

            'debug_mode' => array(
                'title' => __('Debug Mode', WC_Ups_PickUps::TEXT_DOMAIN),
                'type' => 'checkbox',
                'label' => ' ',
                'default' => 'no'
            ),

            'api_show_response' => array(
                'title' => __('API Show Response? (For Debug)', WC_Ups_PickUps::TEXT_DOMAIN),
                'type' => 'select',
                'label' => ' ',
                'default' => 'no',
                'description' => 'Select Show Response Option',
                'options' => array(
                    'yes' => __('Yes, Create & Print', WC_Ups_PickUps::TEXT_DOMAIN),
                    'token' => __('Yes, Token', WC_Ups_PickUps::TEXT_DOMAIN),
                    'no' => __('No', WC_Ups_PickUps::TEXT_DOMAIN),
                ),
                'desc_tip' => true
            ),

            'is_first' => array(
                'title' => __('Show this shipping method first in the checkout page', WC_Ups_PickUps::TEXT_DOMAIN),
                'type' => 'checkbox',
                'label' => ' ',
                'default' => 'no'
            ),

            'hide_method_if_country_not_israel' => array(
                'title' => __('Hide Shipping Method If Country Is not Israel', WC_Ups_PickUps::TEXT_DOMAIN),
                'type' => 'checkbox',
                'label' => ' ',
                'default' => 'no'
            ),

            'send_tracking_number' => array(
                'title' => __('Send tracking number on the complete order email', WC_Ups_PickUps::TEXT_DOMAIN),
                'type' => 'checkbox',
                'label' => ' ',
                'default' => 'no'
            ),

            'google_maps_api_key' => array(
                'title' => __('Google Maps API Key', WC_Ups_PickUps::TEXT_DOMAIN),
                'type' => 'text',
                'default' => ''
            ),

            'stores_lockers' => array(
                'title' => __('Pickup Service Options', WC_Ups_PickUps::TEXT_DOMAIN),
                'type' => 'select',
                'description' => 'Select Pickup Service Options',
                'default' => 'all',
                'options' => array(
                    'stores_lockers' => __('Stores & Lockers', WC_Ups_PickUps::TEXT_DOMAIN),
                    'stores' => __('Stores', WC_Ups_PickUps::TEXT_DOMAIN),
                    'lockers' => __('Lockers', WC_Ups_PickUps::TEXT_DOMAIN),
                ),
                'desc_tip' => true
            ),

            'open_map_onload' => array(
                'title' => __('Open Pickup Map Onload', WC_Ups_PickUps::TEXT_DOMAIN),
                'type' => 'select',
                'default' => 'no',
                'options' => array(
                    'no' => __('No', WC_Ups_PickUps::TEXT_DOMAIN),
                    'shipping_method_change' => __('Only on shipping method change', WC_Ups_PickUps::TEXT_DOMAIN),
                    'always' => __('Always, also when page load', WC_Ups_PickUps::TEXT_DOMAIN),
                ),
                'description' => 'Automatically open pickup map on page load',
                'desc_tip' => true
            ),

            'title' => array(
                'title' => __('Title', WC_Ups_PickUps::TEXT_DOMAIN),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', WC_Ups_PickUps::TEXT_DOMAIN),
                'default' => __('Pick UPS', WC_Ups_PickUps::TEXT_DOMAIN),
                'desc_tip' => true
            ),

            'service_description' => array(
                'title' => __('Service Description', WC_Ups_PickUps::TEXT_DOMAIN),
                'type' => 'textarea',
                'description' => __('This controls puts a service description on checkout screen.', WC_Ups_PickUps::TEXT_DOMAIN),
                'default' => '',
                'desc_tip' => true
            ),

            'cost' => array(
                'title' => __('Cost', WC_Ups_PickUps::TEXT_DOMAIN),
                'type' => 'text',
                'description' => __('Default cost excluding tax. Enter an amount, e.g. 2.50, or leave empty for no default cost.  The default cost can be overriden by setting a cost per pickup location below.', WC_Ups_PickUps::TEXT_DOMAIN),
                'default' => '',
            ),

            'hide_shipping_address' => array(
                'title' => __('Hide Shipping Address', WC_Ups_PickUps::TEXT_DOMAIN),
                'type' => 'checkbox',
                'label' => __('Hide all Shipping fields except Billing fields.', WC_Ups_PickUps::TEXT_DOMAIN),
                'default' => 'no',
                'description' => __('Hide fields from Checkout Page.', WC_Ups_PickUps::TEXT_DOMAIN),
                'desc_tip' => true

            ),
            'shipping_classes' => array(
                'title' => __('Shipping classes', WC_Ups_PickUps::TEXT_DOMAIN),
                'type' => 'multiselect',
                'description' => __('Enter one or more shipping classes.', WC_Ups_PickUps::TEXT_DOMAIN),
                'options' => $shipping_classes,
                'class' => 'wc-enhanced-select chosen_select',
                'desc_tip' => true
            ),

            'shipping_classes_pickup_only' => array(
                'title' => __('Shipping classes Only', WC_Ups_PickUps::TEXT_DOMAIN),
                'type' => 'checkbox',
                'label' => __('Allow local pickup only for the shipping classes listed above, when selected, only Pick Ups Shipping Method will be available', WC_Ups_PickUps::TEXT_DOMAIN),
                'default' => 'no',
            ),

            'do_not_split_shipping' => array(
                'title' => __('Don\'t Split Shipment ', WC_Ups_PickUps::TEXT_DOMAIN),
                'type' => 'checkbox',
                'label' => __('Do not split your cart up into separate shipping packages for products that not using selected shipping classes.', WC_Ups_PickUps::TEXT_DOMAIN),
                'default' => 'no',
            ),

            'hide_email_shipping_address' => array(
                'title' => __('Hide Email Shipping Address ', WC_Ups_PickUps::TEXT_DOMAIN),
                'type' => 'checkbox',
                'label' => __('Hide email shipping address for orders with pickup point', WC_Ups_PickUps::TEXT_DOMAIN),
                'default' => 'no',
            ),

            'pickups_customer_email_pickup_point_title' => array(
                'title' => __('Email Pickup Point Title', WC_Ups_PickUps::TEXT_DOMAIN),
                'type' => 'text',
                'default' => '',
            ),

            'pickups_checkout_validation_error_message' => array(
                'title' => __('Pickup Point Validation Message', WC_Ups_PickUps::TEXT_DOMAIN),
                'type' => 'text',
                'description' => __('Checkout pickup point validation message', WC_Ups_PickUps::TEXT_DOMAIN),
                'default' => '',
            ),

            'max_points_for_pickup' => array(
                'title' => __('Max Points For Pickup', WC_Ups_PickUps::TEXT_DOMAIN),
                'type' => 'text',
                'description' => __('The Pick Up Method will be hidden if the total items points in the cart is more than the number in this field', WC_Ups_PickUps::TEXT_DOMAIN),
                'default' => '',
            ),

            'checkout_fields_additional' => array(
                'title' => __('Add Checkout Fields', WC_Ups_PickUps::TEXT_DOMAIN),
                'type' => 'select',
                'default' => 'none',
                'options' => array(
                    'none' => __('None', WC_Ups_PickUps::TEXT_DOMAIN),
                    'floor_and_room' => __('Floor and Room', WC_Ups_PickUps::TEXT_DOMAIN),
                    'room' => __('Room Only', WC_Ups_PickUps::TEXT_DOMAIN),
                    'floor' => __('Floor Only', WC_Ups_PickUps::TEXT_DOMAIN),
                )
            ),
//            'do_not_show_point_delivery_info' => array(
//                'title' => __('Don\'t show details about delivery point ', WC_Ups_PickUps::TEXT_DOMAIN),
//                'type' => 'checkbox',
//                'label' => __('Don\'t show details about delivery pointin checkout page .', WC_Ups_PickUps::TEXT_DOMAIN),
//                'default' => 'no',
//            )
        );

        $this->form_fields['shipment_additional_fields'] = array(
            'title' => __('Shipment Additional Fields', WC_Ups_PickUps::TEXT_DOMAIN),
            'type' => 'multiselect',
            'description' => __('Enter one or more additional fields.', WC_Ups_PickUps::TEXT_DOMAIN),
            'options' => ['IsDDO' => 'IsDDO', 'COD' => 'COD', 'IsUDR' => 'IsUDR', 'IsReturn' => 'IsReturn'],
            'class' => 'wc-enhanced-select chosen_select',
            'desc_tip' => true,
        );

        $this->form_fields['pickups_closest_points'] =array(
            'title' => __('Closest Points', WC_Ups_PickUps::TEXT_DOMAIN),
            'type' => 'title',
            'default' => ''
        );

        $this->form_fields['pickups_closest_points_active'] =array(
            'title' => __('Enable', WC_Ups_PickUps::TEXT_DOMAIN),
            'type' => 'select',
            'default' => 0,
            'options' => array(
                0 => __('No', WC_Ups_PickUps::TEXT_DOMAIN),
                1 => __('Yes', WC_Ups_PickUps::TEXT_DOMAIN),
            )
        );

        if ($this->get_option('pickups_closest_points_active', 0)) {

            $this->form_fields['pickups_closest_points_show_map'] =array(
                'title' => __('Map', WC_Ups_PickUps::TEXT_DOMAIN),
                'type' => 'select',
                'default' => 1,
                'options' => array(
                    0 => __('Hide map', WC_Ups_PickUps::TEXT_DOMAIN),
                    1 => __('Show map', WC_Ups_PickUps::TEXT_DOMAIN),
                    2 => __('Show map only if closest points not found', WC_Ups_PickUps::TEXT_DOMAIN),
                )
            );

            $this->form_fields['pickups_closest_points_select_design'] =array(
                'title' => __('Closest Points Design', WC_Ups_PickUps::TEXT_DOMAIN),
                'type' => 'select',
                'default' => 0,
                'options' => array(
                    0 => __('Original (based on theme design)', WC_Ups_PickUps::TEXT_DOMAIN),
                    1 => __('Dropdown', WC_Ups_PickUps::TEXT_DOMAIN)
                )
            );

            $this->form_fields['pickups_closest_points_number'] =array(
                'title' => __('Number of Closest Points', WC_Ups_PickUps::TEXT_DOMAIN),
                'type' => 'number',
                'default' => $this->get_option('pickups_closest_points_number', '')
            );

            $this->form_fields['pickups_closest_points_title'] =array(
                'title' => __('Prefix Title', WC_Ups_PickUps::TEXT_DOMAIN),
                'type' => 'text',
                'default' => $this->get_option('pickups_closest_points_title', '')
            );

            $this->form_fields['pickups_closest_points_accuracy'] =array(
                'title' => __('Closest Points Accuracy', WC_Ups_PickUps::TEXT_DOMAIN),
                'type' => 'select',
                'default' => 'city',
                'options' => array(
                    'city' => __('עד מרכז עיר', WC_Ups_PickUps::TEXT_DOMAIN),
                    'street' => __('עד מרכז רחוב', WC_Ups_PickUps::TEXT_DOMAIN),
                    'exact' => __('מדוייק', WC_Ups_PickUps::TEXT_DOMAIN)
                )
            );
        }

        if ($shipping_classes) {
            $settings = array(
                'class_costs' => array(
                    'title' => __('Shipping class costs', WC_Ups_PickUps::TEXT_DOMAIN),
                    'type' => 'title',
                    'default' => '',
                    /* translators: %s: Admin shipping settings URL */
                    'description' => sprintf(__('These costs can optionally be added based on the <a href="%s">product shipping class</a>.', WC_Ups_PickUps::TEXT_DOMAIN), admin_url('admin.php?page=wc-settings&tab=shipping&section=classes')),
                )
            );

            foreach ($shipping_classes as $id => $name) {
                if (!$id) {
                    continue;
                }
                $settings['class_cost_' . $id] = array(
                    /* translators: %s: shipping class name */
                    'title' => sprintf(__('"%s" shipping class cost', WC_Ups_PickUps::TEXT_DOMAIN), esc_html($name)),
                    'type' => 'text',
                    'placeholder' => __('N/A', WC_Ups_PickUps::TEXT_DOMAIN),
                    'description' => '',
                    'default' => $this->get_option('class_cost_' . $id, 0),
                    'desc_tip' => true,
                );
            }

            $this->form_fields = array_merge($this->form_fields, $settings);

//            $settings['calculate_type'] = array(
//                'title'   => __( 'Calculation type', WC_Ups_PickUps::TEXT_DOMAIN ),
//                'type'    => 'select',
//                'class'   => 'wc-enhanced-select',
//                'default' => 'class',
//                'options' => array(
//                    'class' => __( 'Per class: Charge shipping for each shipping class individually', WC_Ups_PickUps::TEXT_DOMAIN ),
//                    'order' => __( 'Per order: Charge shipping for the most expensive shipping class', WC_Ups_PickUps::TEXT_DOMAIN ),
//                ),
//            );
        }

        $this->form_fields['max_amount'] =array(
            'title' => __('Max amount', WC_Ups_PickUps::TEXT_DOMAIN),
            'type' => 'text',
            'default' => $this->get_option('max_amount', ''),
            /* translators: %s: Admin shipping settings URL */
        );

        $this->form_fields['max_amount_incl_discount'] =array(
            'title' => __('Max amount - after apply discounts only', WC_Ups_PickUps::TEXT_DOMAIN),
            'label' => '&nbsp;',
            'type' => 'checkbox',
            'default' => $this->get_option('max_amount_incl_discount', 'no'),
        );

        $this->form_fields['price_max_amount'] =array(
            'title' => __('Price for max amount and above', WC_Ups_PickUps::TEXT_DOMAIN),
            'type' => 'text',
            'default' => $this->get_option('price_max_amount', ''),
            /* translators: %s: Admin shipping settings URL */
        );

        $this->form_fields['integration'] =array(
            'title' => __('Order Integration', WC_Ups_PickUps::TEXT_DOMAIN),
            'type' => 'title',
            'default' => ''
            /* translators: %s: Admin shipping settings URL */
        );

        $this->form_fields['integration_active'] =array(
            'title' => __('Enable', WC_Ups_PickUps::TEXT_DOMAIN),
            'type' => 'select',
            'default' => 0,
            'options' => array(
                0 => __('No', WC_Ups_PickUps::TEXT_DOMAIN),
                1 => __('Yes', WC_Ups_PickUps::TEXT_DOMAIN),
            )
        );

        if ($this->get_option('integration_active', 0)) {
            $this->form_fields['integration_api_url'] = array(
                'title' => __('Api Create URL', WC_Ups_PickUps::TEXT_DOMAIN),
                'type' => 'text',
                'default' => 'https://plugins.ship.co.il'
            );

            $this->form_fields['integration_picking_api_url'] = array(
                'title' => __('API Print URL', WC_Ups_PickUps::TEXT_DOMAIN),
                'type' => 'text',
                'default' => 'https://api.ship.co.il'
            );

            $this->form_fields['integration_picking_username'] = array(
                'title' => __('Username', WC_Ups_PickUps::TEXT_DOMAIN),
                'type' => 'text',
                'description' => 'פרטי ההתחברות תקינים',
                'default' => $this->get_option('integration_picking_username', '')
            );

            $this->form_fields['integration_picking_password'] = array(
                'title' => __('Password', WC_Ups_PickUps::TEXT_DOMAIN),
                'type' => 'text',
                'description' => '',
                'default' => $this->get_option('integration_picking_password', '')
            );

            $this->form_fields['integration_picking_scope'] = array(
                'title' => __('Scope', WC_Ups_PickUps::TEXT_DOMAIN),
                'type' => 'text',
                'description' => '',
                'default' => $this->get_option('integration_picking_scope', '')
            );

            $this->form_fields['integration_change_order_status'] = array(
                'title' => __('Change Order Status', WC_Ups_PickUps::TEXT_DOMAIN),
                'type' => 'select',
                'default' => 0,
                'description' => __('Choose to which status the order will be changed after create waybill.', WC_Ups_PickUps::TEXT_DOMAIN),
                'desc_tip' => true,
                'options' => array_merge([0 => 'None'],wc_get_order_statuses()),
            );

            $this->form_fields['integration_get_status_active'] =array(
                'title' => __('Enable Get Shipping Status', WC_Ups_PickUps::TEXT_DOMAIN),
                'description' => __('You can import the shipping status after waybill has been created.', WC_Ups_PickUps::TEXT_DOMAIN),
                'type' => 'select',
                'default' => 0,
                'options' => array(
                    0 => __('No', WC_Ups_PickUps::TEXT_DOMAIN),
                    1 => __('Yes', WC_Ups_PickUps::TEXT_DOMAIN),
                )
            );

            $this->form_fields['integration_picking_active'] =array(
                'title' => __('Enable Picking List', WC_Ups_PickUps::TEXT_DOMAIN),
                'description' => __('You can Create Picking List only after waybill has been created.', WC_Ups_PickUps::TEXT_DOMAIN),
                'type' => 'select',
                'default' => 0,
                'options' => array(
                    0 => __('No', WC_Ups_PickUps::TEXT_DOMAIN),
                    1 => __('Yes', WC_Ups_PickUps::TEXT_DOMAIN),
                )
            );

            $this->form_fields['integration_mode'] = array(
                'title' => __('Integration method', WC_Ups_PickUps::TEXT_DOMAIN),
                'type' => 'select',
                'default' => 'manual',
                'options' => array(
                    'manual' => __('Manual', WC_Ups_PickUps::TEXT_DOMAIN),
                    'automatic' => __('Automatic', WC_Ups_PickUps::TEXT_DOMAIN)
                )
            );

            $this->form_fields['api_statuses'] = array(
                'title' => __('Order Statuses For Automatic Mode', WC_Ups_PickUps::TEXT_DOMAIN),
                'type' => 'multiselect',
                'description' => __('Enter one or more order status.', WC_Ups_PickUps::TEXT_DOMAIN),
                'options' => wc_get_order_statuses(),
                'class' => 'wc-enhanced-select chosen_select',
                'desc_tip' => true,
            );

            $this->form_fields['integration_additional_field'] =array(
                'title' => __('Additional Field (Reference2)', WC_Ups_PickUps::TEXT_DOMAIN),
                'type' => 'select',
                'default' => 0,
                'options' => array(
                    '' => __('None', WC_Ups_PickUps::TEXT_DOMAIN),
                    'order_id' => __('Order ID', WC_Ups_PickUps::TEXT_DOMAIN),
                    'customer_name' => __('Customer Name', WC_Ups_PickUps::TEXT_DOMAIN),
                    'email' => __('Customer E-Mail', WC_Ups_PickUps::TEXT_DOMAIN),
                    'phone_number' => __('Customer Phone Number', WC_Ups_PickUps::TEXT_DOMAIN),
                    'pickup_point_id' => __('Pickup Point ID', WC_Ups_PickUps::TEXT_DOMAIN),
                    'pickup_point_name' => __('Pickup Point Name', WC_Ups_PickUps::TEXT_DOMAIN),
                )
            );

            $this->form_fields['integration_shipment_instructions_field'] = array(
                'title' => __('Shipment Instructions Field', WC_Ups_PickUps::TEXT_DOMAIN),
                'type' => 'select',
                'description' => __('Only 50 first Characters will be sent', WC_Ups_PickUps::TEXT_DOMAIN),
                'default' => 0,
                'options' => array(
                    '' => __('None', WC_Ups_PickUps::TEXT_DOMAIN),
                    'custom_field' => __('Custom Field', WC_Ups_PickUps::TEXT_DOMAIN),
                    'customer_notes' => __('Customer Notes', WC_Ups_PickUps::TEXT_DOMAIN),
                    'custom_field_notes' => __('Custom Field & Customer Notes', WC_Ups_PickUps::TEXT_DOMAIN),
                    'customer_notes_custom' => __('Customer Notes & Custom Field', WC_Ups_PickUps::TEXT_DOMAIN),
                )
            );

            $this->form_fields['integration_shipment_instructions_field_custom'] = array(
                'title' => __('Shipment Instructions Custom Field', WC_Ups_PickUps::TEXT_DOMAIN),
                'type' => 'text',
                'default' => ''
            );

            $this->form_fields['integration_send_and_print_buttons'] =array(
                'title' => __('Enable Send & Print Buttons', WC_Ups_PickUps::TEXT_DOMAIN),
                'type' => 'select',
                'default' => 0,
                'options' => array(
                    0 => __('No', WC_Ups_PickUps::TEXT_DOMAIN),
                    1 => __('Yes', WC_Ups_PickUps::TEXT_DOMAIN),
                )
            );

            $this->form_fields['integration_order_weight_default'] =array(
                'title' => __('Order Weight', WC_Ups_PickUps::TEXT_DOMAIN),
                'type' => 'number',
                'default' => 1
            );
        }

        $this->form_fields['save_order_as_xml'] =array(
            'title' => __('Save Order & Send to Ftp', WC_Ups_PickUps::TEXT_DOMAIN),
            'type' => 'title',
            'default' => ''
        );

        $this->form_fields['save_order_as_xml_active'] =array(
            'title' => __('Enable', WC_Ups_PickUps::TEXT_DOMAIN),
            'description' => __('While Enabled, XML file will be saved at: '.$this->helper->getUpsOrderUploadsFolder().' before send to FTP', WC_Ups_PickUps::TEXT_DOMAIN),
            'type' => 'select',
            'default' => 0,
            'options' => array(
                0 => __('No', WC_Ups_PickUps::TEXT_DOMAIN),
                1 => __('Yes', WC_Ups_PickUps::TEXT_DOMAIN),
            )
        );

        if ($this->get_option('save_order_as_xml_active', 0)) {
            $this->form_fields['save_order_as_xml_automatic'] =array(
                'title' => __('Auto Send', WC_Ups_PickUps::TEXT_DOMAIN),
                'description' => __('Automatic Create & Send XML to Ftp', WC_Ups_PickUps::TEXT_DOMAIN),
                'type' => 'select',
                'default' => 0,
                'options' => array(
                    0 => __('No', WC_Ups_PickUps::TEXT_DOMAIN),
                    1 => __('Yes', WC_Ups_PickUps::TEXT_DOMAIN),
                )
            );

            $this->form_fields['save_order_as_xml_type'] =array(
                'title' => __('Order Type', WC_Ups_PickUps::TEXT_DOMAIN),
                'type' => 'select',
                'default' => 'FD',
                'options' => array(
                    'FD' => __('Full Delivery', WC_Ups_PickUps::TEXT_DOMAIN),
                    'PD' => __('Partial Delivery', WC_Ups_PickUps::TEXT_DOMAIN),
                )
            );

            $this->form_fields['save_order_as_xml_pickup_warehouse_location_address'] = array(
                'title' => __('Pickup Warehouse Location Address', WC_Ups_PickUps::TEXT_DOMAIN),
                'type' => 'text',
                'default' => ''
            );

            $this->form_fields['save_order_as_xml_pickup_warehouse_location_city'] = array(
                'title' => __('Pickup Warehouse Location City', WC_Ups_PickUps::TEXT_DOMAIN),
                'type' => 'text',
                'default' => ''
            );

            $this->form_fields['send_order_to_ftp_path'] = array(
                'title' => __('Customer Folder Name', WC_Ups_PickUps::TEXT_DOMAIN),
                'type' => 'text',
                'description' => '',
                'default' => ''
            );
        }
    }

    /**
     * Setup the local pickup plus admin settings screen
     *
     * Overridden from parent class
     */
    public function admin_options()
    {

        global $wp_version;

        // NOTE:  an index is not specified in the arrayed input elements below as in the original
        //  example I followed because there's a bug with that approach where if you have three
        //  rows with specified indexes 0, 1, 2 and you delete the middle row and add a new
        //  row, the specified indexes will be 0, 2, 2 and only the first and last rows
        //  will be posted.  Letting the array indexes default with [] fixes the bug and should be fine

        $base_country = WC()->countries->get_base_country();
        $base_state = WC()->countries->get_base_state();

        // get the pickup fields.  reserved field names used by this plugin: id, country, cost, note
        $pickup_fields = $this->get_pickup_address_fields($base_country);

        ?>
      <style type="text/css">
        .chzn-choices .search-field {
          min-width: 200px;
        }

        .chzn-choices .search-field input {
          min-width: 100%;
        }

        .chzn-container-multi {
          width: 350px !important;
        }

        .shippingrows tr td:first-child input {
          margin: 0 0 0 8px;
        }

        .shippingrows tr th {
          white-space: nowrap;
        }

        <?php if ( version_compare( $wp_version, '3.8', '>=' ) ) : ?>
        .shippingrows.widefat tr .check-column {
          padding-top: 20px;
        }

        .shippingrows tfoot tr th {
          padding-left: 12px;
        }

        <?php endif; ?>
      </style>
      <div class="notice notice-warning is-dismissible">
          <p><a href="https://wordpress.org/support/plugin/woo-ups-pickup/reviews/" target="_blank">לחצו כאן כדי לדרג אותנו בחנות האפליקציות, זה ממש יעזור!</a></p>
      </div>
      <h3><?php echo $this->method_title; ?></h3>
      <p><?php echo $this->admin_page_description; ?></p>
      <table class="form-table">
          <?php $this->generate_settings_html(); ?>
        <tr valign="top" style="display: none;">
          <th scope="row" class="titledesc"><?php _e('Pickup Locations', WC_Ups_PickUps::TEXT_DOMAIN); ?>:</th>
          <td class="forminp" id="<?php echo $this->id; ?>_pick_ups_location">
            <table class="shippingrows widefat" cellspacing="0">
              <thead>
              <tr>
                <th class="check-column"><input type="checkbox"/></th>
                  <?php
                  foreach ($pickup_fields as $key => $field) {
                      echo "<th>{$field['label']}</th>";
                  }
                  ?>
                <th><?php _e('Cost', WC_Ups_PickUps::TEXT_DOMAIN); ?> <img class="help_tip" width="15" height="15"
                                                                           style="float:none;"
                                                                           data-tip='<?php _e('Cost for this pickup location, enter an amount, eg. 0 or 2.50, or leave empty to use the default cost configured above.', WC_Ups_PickUps::TEXT_DOMAIN) ?>'
                                                                           src="<?php echo WC()->plugin_url(); ?>/assets/images/help.png"/>
                </th>
                <th><?php _e('Notes', WC_Ups_PickUps::TEXT_DOMAIN); ?> <img class="help_tip" width="15" height="15"
                                                                            style="float:none;"
                                                                            data-tip='<?php _e('Free-form notes to be displayed below the pickup location on checkout/receipt.  HTML content is allowed.', WC_Ups_PickUps::TEXT_DOMAIN) ?>'
                                                                            src="<?php echo WC()->plugin_url(); ?>/assets/images/help.png"/>
                </th>
              </tr>
              </thead>
              <tfoot>
              <tr>
                <th colspan="<?php echo count($pickup_fields) + 3 ?>"><a href="#"
                                                                         class="add button"><?php _e('+ Add Pickup Location', WC_Ups_PickUps::TEXT_DOMAIN); ?></a>
                  <a href="#"
                     class="remove button"><?php _e('Delete Pickup Location', WC_Ups_PickUps::TEXT_DOMAIN); ?></a></th>
              </tr>
              </tfoot>
              <tbody class="pick_ups_location">
              <?php

              if ($this->pick_ups_location) foreach ($this->pick_ups_location as $location) {
                  echo '<tr class="pickups_location">
								<td class="check-column" style="width:20px;"><input type="checkbox" name="select" />';
                  echo '<input type="hidden" name="' . $this->id . '_id[]" value="' . $location['id'] . '" />';
                  echo '<input type="hidden" name="' . $this->id . '_country[]" value="' . $location['country'] . '" />';
                  echo '</td>';

                  foreach ($pickup_fields as $key => $field) {
                      echo '<td>';
                      if ('state' == $key) {
                          // handle state field specially
                          if ($states = WC()->countries->get_states($location['country'])) {
                              // state select box
                              echo '<select name="' . $this->id . '_state[]" class="select">';

                              foreach ($states as $key => $value) {
                                  echo '<option';
                                  if ($location['state'] == $key) echo ' selected="selected"';
                                  echo ' value="' . $key . '">' . $value . '</option>';
                              }

                          } else {
                              // state input box
                              echo '<input type="text" value="' . $location['state'] . '" name="' . $this->id . '_state[]" />';
                          }
                      } else {
                          // all other fields
                          echo '<input type="text" name="' . $this->id . '_' . $key . '[]" value="' . $location[$key] . '" placeholder="' . (in_array($key, array('company', 'address_2', 'phone')) ? __('(Optional)', WC_Ups_PickUps::TEXT_DOMAIN) : '') . '" />';
                      }

                      echo '</td>';
                  }
                  echo '<td><input type="text" name="' . $this->id . '_cost[]" value="' . (isset($location['cost']) ? $location['cost'] : '') . '" placeholder="' . __('(Optional)', WC_Ups_PickUps::TEXT_DOMAIN) . '" /></td>';
                  echo '<td><textarea name="' . $this->id . '_note[]" placeholder="' . __('(Optional)', WC_Ups_PickUps::TEXT_DOMAIN) . '">' . (isset($location['note']) ? $location['note'] : '') . '</textarea></td>';
                  echo '</tr>';
              }
              ?>
              </tbody>
            </table>
          </td>
        </tr>
      </table><!--/.form-table-->
      <script type="text/javascript">
        jQuery(document).ready(function ($) {

          $('#<?php echo $this->id; ?>_pick_ups_location a.add').on('click', function () {

            $('<tr class="pickups_location">\
							  <td class="check-column" style="width:20px;"><input type="checkbox" name="select" />\
								<input type="hidden" name="<?php echo $this->id ?>_id[]" value="" />\
								<input type="hidden" name="<?php echo $this->id ?>_country[]" value="<?php echo $base_country; ?>" />\
							  </td>\<?php
                foreach ($pickup_fields as $key => $field) {
                    echo '<td>';
                    if ('state' == $key) {
                        if ($states = WC()->countries->get_states($base_country)) {
                            // state select box
                            echo '<select name="' . $this->id . '_state[]" class="select">';

                            foreach ($states as $key => $value) {
                                echo '<option';
                                if ($base_state == $key) echo ' selected="selected"';
                                echo ' value="' . $key . '">' . $value . '</option>';
                            }

                        } else {
                            // state input box
                            echo '<input type="text" value="' . $base_state . '" name="' . $this->id . '_state[]" />';
                        }
                    } else {
                        echo '<input type="text" name="' . $this->id . '_' . $key . '[]" value="" placeholder="' . (in_array($key, array('company', 'address_2', 'phone')) ? __('(Optional)', WC_Ups_PickUps::TEXT_DOMAIN) : '') . '" />';
                    }

                    echo '</td>';
                }
                ?><td><input type="text" name="<?php echo $this->id ?>_cost[]" value="" placeholder="<?php _e('(Optional)', WC_Ups_PickUps::TEXT_DOMAIN) ?>" /></td>\
							  <td><textarea name="<?php echo $this->id ?>_note[]" placeholder="<?php _e('(Optional)', WC_Ups_PickUps::TEXT_DOMAIN) ?>"></textarea></td>\
							  </tr>').appendTo('#<?php echo $this->id; ?>_pick_ups_location table tbody.pick_ups_location');

            return false;
          });

          // Remove row
          $('#<?php echo $this->id; ?>_pick_ups_location a.remove').on('click', function () {
            var answer = confirm("<?php _e('Delete the selected pickup locations?', WC_Ups_PickUps::TEXT_DOMAIN); ?>");
            if (answer) {
              $('#<?php echo $this->id; ?>_pick_ups_location table tbody tr td.check-column input:checked').each(function (i, el) {
                $(el).closest('tr').remove();
              });
            }
            return false;
          });

        });
      </script>
        <?php
        if($this->helper->getPluginLastInstalledDate()){?>
            <div>Last Installed at: <?= $this->helper->getPluginLastInstalledDate() ?></div>
        <?php }
    }


    public function frontend_scripts()
    {


        if (is_checkout() || is_admin()) {

            $googleMapsApiKey = $this->helper->getOption('google_maps_api_key');

            $handle = '';
            switch ($this->stores_lockers) {
                case 'stores_lockers':
                    $handle = 'stores-lockers';
                    wp_enqueue_script('stores-lockers', plugins_url('/js/stores-lockers.js', __FILE__), array('jquery'), WC_Ups_PickUps::VERSION, 'in_footer');
                    break;
                case 'stores':
                    $handle = 'stores';
                    wp_enqueue_script('stores', plugins_url('/js/stores.js', __FILE__), array('jquery'), WC_Ups_PickUps::VERSION, 'in_footer');
                    break;
                case 'lockers':
                    $handle = 'lockers';
                    wp_enqueue_script('lockers', plugins_url('/js/lockers.js', __FILE__), array('jquery'), WC_Ups_PickUps::VERSION, 'in_footer');
                    break;
            }

            wp_localize_script( $handle, 'data', array('googleMapsApiKey' => $googleMapsApiKey) );

            $openMapOnLoad = $this->helper->getOption('open_map_onload');
            wp_enqueue_script('pickups-scripts', plugins_url('/js/pickups-scripts.js', __FILE__), array('jquery'), WC_Ups_PickUps::VERSION, 'in_footer');
            wp_localize_script( 'pickups-scripts', 'mapData', array('openMapOnLoad' => $openMapOnLoad) );

            if ($this->hide_shipping_address == 'yes') {
                //add_action('woocommerce_checkout_fields', array($this,'wc_pickup_custom_remove_require'/*,10,1*/));
                //add_filter('woocommerce_billing_fields', array($this,'wc_pickup_custom_remove_require'/*,10,1*/));
                wp_enqueue_script('hide-fields-from-checkout', plugins_url('/js/hide-fields-from-checkout.js', __FILE__), array('jquery'), null, 'in_footer');

            } else {
                remove_filter('woocommerce_billing_fields', array($this, 'wc_pickup_custom_remove_require'));
            }

        }

    }

    //remove require if checked option "hide" in admin panel
    public function wc_pickup_custom_remove_require($address_fields)
    {
        $address_fields['billing_email']['required'] = false;

        $address_fields['billing_country']['required'] = false;

        $address_fields['billing_city']['required'] = false;
        $address_fields['billing_state']['required'] = false;
        $address_fields['billing_address_1']['required'] = false;

        $address_fields['billing_address_2']['required'] = false;

        $address_fields['billing_postcode']['required'] = false;
        $address_fields['billing_state']['required'] = false;

        return $address_fields;
    }


    public function wc_pickup_custom_override_checkout_fields($fields)
    {
        $isVirtual = true;
        $cartItems = wc()->cart->cart_contents;
        foreach ($cartItems as $item) {
            $product = $item['data'];
            if (is_callable(array($product, 'is_virtual')) && !$product->is_virtual()) {
                $isVirtual = false;
                break;
            }
        }

        if ($isVirtual) {
            return $fields;
        }

        $fields['billing']['pickups_location1'] = array(
            'label' => __('Number', 'woocommerce'),
            'placeholder' => _x('Please select point', 'placeholder', 'woocommerce'),
            'required' => false,
            'class' => array('form-row-wide'),
            'clear' => true,
            'hidden' => true
        );

        $fields['billing']['pickups_location2'] = array(
            'label' => __('Point', 'woocommerce'),
            'placeholder' => _x('Please select point', 'placeholder', 'woocommerce'),
            'required' => false,
            'class' => array('form-row-wide'),
            'clear' => true,
            'hidden' => true
        );

        return $fields;
    }

    public function update_shipping_packages($packages)
    {
        /**
         * If Products Points Over the Maximum Remove Pick Ups Shipping Method
         * and return available shipping methods
         *
         * @since 1.6.0
         */
        if($this->helper->isPickUpsProductsPointsOverTheMax()){
            return $packages;
        }

        $new_packages = array();

        // adding this filter to support a merchant request to be able to
        // allow both local pickup as well as other shipping methods for
        // the selected categories.  Don't want to expose this to the admin
        // quite yet as the settings page has already grown a bit unwieldly
        // and probably needs to be overhauled

        $allow_other_methods_for_found_categories = apply_filters('wc_shipping_ups_pick_ups_allow_other_methods_categories', false);

        // WC 2.1 allows for multiple shipping packages
        foreach ($packages as $package_index => $package) {

            list($found_products, $other_products) = $this->get_products_by_allowed_category($package['contents']);

            // use the package as-is or split up as necessary
            if (count($other_products) == 0 && count($found_products) > 0) {

                // only local pickup category products so allow only local pickup plus
                if ((!$allow_other_methods_for_found_categories) && $this->shipping_classes_pickup_only == 'yes') {
                    $package['ship_via'] = array($this->id);
                }
                $new_packages[] = $package;

            } elseif (count($found_products) > 0 && count($other_products) > 0) {
                if ($this->do_not_split_shipping == 'yes') {
                    //var_dump();
                    $new_packages[] = $package;
                } else {
                    // both local pickup only and regular products, split up the package

                    // create a package containing only the local pickup plus category or shipping classes products
                    $found_products_package = $package;
                    foreach ($found_products_package['contents'] as $cart_item_key => $cart_item) {
                        if (!isset($found_products[$cart_item['product_id']])) {
                            unset($found_products_package['contents'][$cart_item_key]);
                        }
                    }
                    //unset other shipping methods apart from UPS if checkbox Shipping classes Only enabled
                    if ((!$allow_other_methods_for_found_categories) && $this->shipping_classes_pickup_only == 'yes') {
                        $found_products_package['ship_via'] = array($this->id);

                    }

                    // create a package containing only the non-local pickup plus category products
                    //  if "categories pickup only" is enabled, then local pickup plus will be removed
                    //  as a shipping method option from is_available()

                    $other_products_package = $package;

                    foreach ($other_products_package['contents'] as $cart_item_key => $cart_item) {

                        if (!isset($other_products[$cart_item['product_id']])) {

                            unset($other_products_package['contents'][$cart_item_key]);
                        }
                    }

                    $new_packages[] = $found_products_package;
                    $new_packages[] = $other_products_package;
                }
            } else {


                // otherwise, leave the package as-is

                $new_packages[] = $package;

            }

        }

        // add any chosen pickup locations to the packages
        $chosen_shipping_methods = $this->get_chosen_shipping_methods();

        foreach ($new_packages as $package_index => $package) {
            if (isset($chosen_shipping_methods[$package_index]) && $this->id == $chosen_shipping_methods[$package_index] && $this->has_chosen_pickups_location($package_index)) {
                $new_packages[$package_index]['pickups_location'] = $this->get_chosen_pickups_location_id($package_index);
            }
        }

        return $new_packages;
    }

    public function process_admin_options()
    {

        // take care of the regular configuration fields
        parent::process_admin_options();

        $base_country = WC()->countries->get_base_country();
        $pickup_fields = $this->get_pickup_address_fields($base_country);

        $pick_ups_location = array();
        $posted_fields = array();

        $ids = array();
        // reserved fields
        $countries = isset($_POST[$this->id . '_country']) ? array_map(array($this, 'wc_clean'), $_POST[$this->id . '_country']) : array();
        $costs = isset($_POST[$this->id . '_cost']) ? array_map(array($this, 'wc_clean'), $_POST[$this->id . '_cost']) : array();
        $ids = isset($_POST[$this->id . '_id']) ? array_map(array($this, 'wc_clean'), $_POST[$this->id . '_id']) : array();
        $notes = isset($_POST[$this->id . '_note']) ? array_map('stripslashes_deep', $_POST[$this->id . '_note']) : array();

        // standard fields
        foreach (array_keys($pickup_fields) as $field_name) {
            $posted_fields[$field_name] = isset($_POST[$this->id . '_' . $field_name]) ? array_map(array($this, 'wc_clean'), $_POST[$this->id . '_' . $field_name]) : array();
        }

        // determine the current maximum pickup location id
        $max_id = -1;
        foreach ($this->pick_ups_location as $location) {
            $max_id = max($max_id, $location['id']);
        }

        for ($i = 0, $ix = count($ids); $i < $ix; $i++) {

            // pickup location id
            $id = $ids[$i];

            if (!is_numeric($id)) {
                $id = ++$max_id;
            }

            // reserved fields
            $pickups_location = array(
                'country' => isset($countries[$i]) ? $countries[$i] : null,
                'cost' => isset($costs[$i]) ? $costs[$i] : null,
                'id' => $id,
                'note' => isset($notes[$i]) ? $notes[$i] : null,
            );

            // standard fields
            foreach (array_keys($pickup_fields) as $field_name) {
                $pickups_location[$field_name] = isset($posted_fields[$field_name][$i]) ? $posted_fields[$field_name][$i] : null;
            }

            $pick_ups_location[] = $pickups_location;
        }

        update_option(self::PICKUP_LOCATIONS_OPTION, $pick_ups_location);

        $this->load_pick_ups_location();
    }

    public function taxable_address($address)
    {

        $chosen_shipping_methods = $this->get_chosen_shipping_methods();

        if (in_array($this->id, $chosen_shipping_methods) &&
            'yes' == $this->apply_pickups_location_tax) {

            // there can be only one taxable address, so if there are multiple pickup locations chosen, just use the first one we find
            foreach ($chosen_shipping_methods as $package_index => $shipping_method_id) {

                if ($this->id == $shipping_method_id) {

                    $location = $this->get_pickups_location_by_id($this->get_chosen_pickups_location_id($package_index));

                    if ($location) {
                        // first location
                        return array($location['country'], $location['state'], $location['postcode'], $location['city']);
                    }
                }
            }
        }

        return $address;
    }

    /**
     * @param $template_name
     * @param $template_path
     * @param $located
     * @param $args
     *
     * Render the pickup location selection box on the checkout form
     */
    public function review_order_shipping_pickups_location($template_name, $template_path, $located, $args)
    {
        global $wp_query;
        $is_ajax = defined('WC_DOING_AJAX') && 'update_order_review' === $wp_query->get('wc-ajax');
        if ('cart/cart-shipping.php' == $template_name && (is_checkout() || $is_ajax)) {

            include_once(dirname(__FILE__) . '/templates/pickup-location.php');

            if (!$this->helper->isPickUpsProductsPointsOverTheMax() && $this->id == $args['chosen_method']) {
                include_once(dirname(__FILE__) . '/templates/pickup-button-html.php');
            }

        }
    }

    /**
     * Validate the selected pickup location
     *
     * @param array $posted data from checkout form
     */
    public function after_checkout_validation($posted)
    {
        $shipping_method = $posted['shipping_method'];
        foreach ($shipping_method as $package_index => $shipping_method_id) {
            if ($this->id == $shipping_method_id) {
                if (substr($_POST['pickups_location1'], 0, 4) != ("PKPS" || "PKPL")) {
                    if (!self::$alreadyErrored) {
                        wc_add_notice($this->helper->getUpsPickupValidationErrorMessage(), 'error');
                    }
                    self::$alreadyErrored = true;
                }
            }
        }
    }


    /**
     * @return bool
     *
     *
     * Check the cart items.  If local-pickup-only categories are defined
     * and the selected shipping method is not 'local pickup', an error
     * message is displayed.  If local-pickup-only categories are defined,
     * and other categories are not eligible for local pickup (categories_pickup_only is true),
     * and the selected shipping method is not local pickup and some of
     * the ineligble proucts are in the cart, display an error message to
     * that effect.
     */

    public function woocommerce_check_cart_items()
    {

        // nothing to check or no shipping required, no errors
        if (empty($this->categories) || !WC()->cart->needs_shipping()) {
            return false;
        }

        if (!$this->get_chosen_shipping_methods()) {
            // the current action is called before the the default shipping method is determined,
            //  so in order to ensure our messages are displayed correctly we have to figure out
            //  what the default shipping method will be.

            // avoid infinite loop.  thanks maxrice!
            remove_action('woocommerce_calculate_totals', array($this, 'woocommerce_check_cart_items'));

            WC()->cart->calculate_totals();
        }

        // are any of the selected categories in the cart?
        $has_errors = false;
        $chosen_shipping_methods = $this->get_chosen_shipping_methods();
        $shipping_packages = WC()->shipping()->packages;
        $allow_other_methods_for_found_categories = apply_filters('wc_shipping_ups_pick_ups_allow_other_methods_categories', false);

        foreach ($chosen_shipping_methods as $package_id => $shipping_method_id) {

            if (isset($shipping_packages[$package_id]['contents'])) {
                list($found_products, $other_products) = $this->get_products_by_allowed_category($shipping_packages[$package_id]['contents']);

                if ($this->id == $shipping_method_id) {
                    // local pickup plus shipping method selected
                    if (('yes' == $this->categories_pickup_only || 'yes' == $this->shipping_classes_pickup_only) && count($other_products) > 0) {
                        wc_add_notice(sprintf(__('Some of your cart products are not eligible for local pickup, please remove %s, or select a different shipping method to continue', WC_Ups_PickUps::TEXT_DOMAIN), '<strong>' . implode(', ', $other_products) . '</strong>'), 'error');
                        $has_errors = true;
                    }
                } else {
                    // some other shipping method selected
                    if (count($found_products) > 0 && !$allow_other_methods_for_found_categories) {
                        wc_add_notice(sprintf(__('Some of your cart products are only eligible for local pickup, please remove %s, or change the shipping method to %s to continue', WC_Ups_PickUps::TEXT_DOMAIN), '<strong>' . implode(', ', $found_products) . '</strong>', $this->title), 'error');
                        $has_errors = true;
                    }
                }
            }
        }

        if ($has_errors && is_cart()) {
            // if on the cart page and there are shipping/category errors, force the page to refresh when the shipping method changes so the error message will be updated
            //   technically I could probably just hide the error message via javascript, but reloading the page, while more heavyweight, does seem more robust
            wp_enqueue_script('force-reload-checkout', plugins_url('/js/force-reload-checkout.js', __FILE__), array('jquery'), WC_Ups_PickUps::VERSION, 'in_footer');
        }
    }


    public function checkout_remove_json_from_shipping_order_item($item_id)
    {
        if(wc_get_order_item_meta($item_id, 'pkps_json')) {
            wc_delete_order_item_meta($item_id, 'pkps_json');
        }
    }

    public function checkout_add_pickuppoint_json_to_order($order)
    {
        if($_POST['pickups_location2']){
            $order->update_meta_data('pkps_json', $_POST['pickups_location2']);
        }
    }


    public function checkout_update_order_meta($order_id, $posted)
    {

        // pre WC 2.1 backwards compatibility
        $shipping_method = $posted['shipping_method'];

        if ($this->id == $shipping_method && isset($_POST['pickups_location1'])) {
            $location = $this->get_pickups_location_by_id($_POST['pickups_location'][0]);
            if ($location) {
                $order = wc_get_order( $order_id );
                $order->update_meta_data('_pickups_location', $location);
                WC()->session->set('chosen_pick_ups_location', array($location['id']));
            }
        }
    }


    public function order_pickups_location($order)
    {

        $pick_ups_location = $this->get_order_pick_ups_location($order);

        if (count($pick_ups_location) > 0) {
            echo '<div>';
            echo '<header class="title"><h3>נקודת איסוף PickUPS</h3></header>';
        }

        //foreach ( $pick_ups_location as $pickups_location ) {

        $pickups_location = $pick_ups_location[0];
        $formatted_pickups_location = $this->helper->get_formatted_address_helper($pickups_location);

        echo '<address>';
        echo '<p>' . $formatted_pickups_location . '</p>';
        echo '</address>';

        if (isset($pickups_location['note']) && $pickups_location['note']) {
            echo '<div>' . $pickups_location['note'] . '</div>';
        }
        //}

        if (count($pick_ups_location) > 0) {
            echo '</div>';
        }
    }

    /**
     * @param $order
     * @return array of pickup location in format of PickUP data converted from json string
     *
     */
    private function get_order_pick_ups_location($order)
    {

        $pick_ups_location = array();

        if ($order->has_shipping_method($this->id)) {

            if (!class_exists('WC_Shipping_Ups_PickUps_CPT')) {
                require_once('admin/class-wc-shipping-shipping-ups-pick-ups-shop-order-cpt.php');
            }

            /**
             * get json from order meta
             * if orders placed before plugin update (Version 1.5.0), we check if json is in the item
             */
            $jsondata = $order->get_meta(WC_Shipping_Ups_PickUps_CPT::PICKUP_ORDER_METADATA_FIELD) ?: wc_get_order_item_meta($order->get_id(), WC_Shipping_Ups_PickUps_CPT::PICKUP_ORDER_METADATA_FIELD);
            $jsondata = str_replace('\\"', '"', $jsondata);
            $jsondata = preg_replace('/\\\"/', "\"", $jsondata);
            $jsondata = preg_replace('/\\\'/', "\'", $jsondata);

            $pkps_order = json_decode($jsondata);

            if ($pkps_order) {
                $pick_ups_location[] = $pkps_order;
            }

        }

        return $pick_ups_location;
    }

    /**
     * @param $skip
     * @return bool
     *
     * Compatibility with per product shipping when a per-product shipping price
     * is used along with a free local pickup location
     */

    public function per_product_shipping_skip_free_method($skip)
    {
        return false;
    }


    /** Admin methods ************************************************************/

    /**
     * @param $hidden
     * @return array
     * **
     * Hides the shipping_item_id item meta that we add to associate a line_item
     * to a shipping item
     *
     */
    public function admin_order_hide_itemmeta($hidden)
    {

        $hidden[] = '_shipping_pickups_id';

        return array_merge($hidden, array('_shipping_pickups_id'));
    }


    /**
     * Show PickUP order details on admin order page
     *
     * @since 1.0
     */
    public function admin_order_pickups_location()
    {

        global $post;
        $order = wc_get_order($post->ID);

        if(!$order){
            return;
        }

        if ($order->has_shipping_method($this->id)) {

            $pick_ups_location = $this->get_order_pick_ups_location($order);
            if (count($pick_ups_location) > 0) {
                ?>
              <style type="text/css">
                #order_data a.edit_pickups_location {
                  opacity: 0.4;
                }

                #order_data a.edit_pickups_location:hover, #order_data a.edit_pickups_location:focus {
                  opacity: 1;
                }
              </style>
                <?php
                $pkps_order = $pick_ups_location[0];
                include_once(dirname(__FILE__) . '/admin/templates/pickup-location-html.php');
            } else {
                echo __('Pickup Locations not set', WC_Ups_PickUps::TEXT_DOMAIN);
            }
        }
    }


    /**
     * Admin order update, save pickup location if needed and add an
     * order note to the effect
     *
     *
     */
    public function admin_process_shop_order_meta($post_id, $post)
    {

        $order = wc_get_order($post_id);

        // nothing to do here, shipping method not used, or no pickup location posted
        if (!$order->has_shipping_method($this->id) || !isset($_POST['pickups_location']) || !is_array($_POST['pickups_location'])) {
            return;
        }

        $pick_ups_location = $_POST['pickups_location'];

        if (isset($pick_ups_location[0])) {

            // this indicates that this was an order placed pre WC 2.1 and
            //  now saved in 2.1 so the old style shipping data has been
            //  updated to the new style and we will also update our data structures
            $shipping_methods = $order->get_shipping_methods();

            // get first (and should be only) shipping item id
            list($shipping_item_id) = array_keys($shipping_methods);
            $pickups_location_id = $pick_ups_location[0];

            // simulate the new data structure
            $pick_ups_location = array($shipping_item_id => $pickups_location_id);
            $pickups_location = $this->get_pickups_location_by_id($pickups_location_id);

            // create the shipping_item_id entries to link all order line items to the shipping method
            foreach ($order->get_items() as $order_item_id => $line_item) {
                wc_update_order_item_meta($order_item_id, '_shipping_pickups_id', $shipping_item_id);
                wc_update_order_item_meta($order_item_id, __('Pickup Location', WC_Ups_PickUps::TEXT_DOMAIN), $this->helper->get_formatted_address_helper($pickups_location, true));
            }

            // clean up the old style data
            $order->delete_meta_data('_pickups_location');
        }

        // Normal WC 2.1+ behavior
        foreach ($pick_ups_location as $shipping_item_id => $pickups_location_id) {

            $pickups_location = $this->get_pickups_location_by_id($pickups_location_id);

            // update the shipping item pickup location
            if (wc_update_order_item_meta($shipping_item_id, 'pickups_location', $pickups_location)) {

                // make a note of the change
                $order->add_order_note(
                    sprintf(__('Pickup location changed to %s', WC_Ups_PickUps::TEXT_DOMAIN),
                        $this->helper->get_formatted_address_helper($pickups_location, true)
                    )
                );

            }
        }
    }


    /** Helper methods ******************************************************/


    /**
     * Returns true if this gateway is enabled
     *
     *
     */
    public function is_enabled()
    {
        return 'yes' == $this->enabled;
    }


    /**
     * Returns true if coupons are enabled
     *
     *
     */
    private function coupons_enabled()
    {
        return 'yes' == get_option('woocommerce_enable_coupons');
    }

    /**
     * Returns the array of shipping methods chosen during checkout
     *
     */
    public static function get_chosen_shipping_methods()
    {
        return isset(WC()->session) && WC()->session->get('chosen_shipping_methods') ? WC()->session->get('chosen_shipping_methods') : array();
    }

    /**
     * Returns true if the shipping address should be hidden on checkout when
     * this shipping method is selected
     *
     *
     */
    public function hide_shipping_address()
    {
        return 'yes' == $this->hide_shipping_address;
    }


    public function get_checkout_pickups_location_styling()
    {

        if ($this->checkout_pickups_location_styling) {
            return $this->checkout_pickups_location_styling;
        }

        // default to dropdown for backwards compatibility
        return 'select';
    }


    public function get_chosen_pickups_location_id($package_index)
    {

        $pickups_location_ids = isset(WC()->session) ? WC()->session->get('chosen_pick_ups_location') : array();

        // check for numeric because '0' is a valid location id, whereas null/false/'' are not
        if ((!isset($pickups_location_ids[$package_index]) || !is_numeric($pickups_location_ids[$package_index])) && 1 == count($this->pick_ups_location)) {

            $location = reset($this->pick_ups_location);
            $pickups_location_ids[$package_index] = $location['id'];

            WC()->session->set('chosen_pick_ups_location', $pickups_location_ids);
        }

        return isset($pickups_location_ids[$package_index]) ? $pickups_location_ids[$package_index] : null;
    }


    public function has_chosen_pickups_location($package_index)
    {
        // check for numeric because '0' is a valid location id, whereas null/false/'' are not
        return is_numeric($this->get_chosen_pickups_location_id($package_index));
    }


    private function get_location_cost_range()
    {

        $min_cost = PHP_INT_MAX;
        $max_cost = -1;

        foreach ($this->pick_ups_location as $location) {
            $cost = $this->get_cost_for_location($location);

            $min_cost = min($cost, $min_cost);
            $max_cost = max($cost, $max_cost);
        }

        return array($min_cost, $max_cost);
    }


    private function get_pickups_location_by_id($id)
    {

        foreach ($this->pick_ups_location as $location) {
            if ($location['id'] == $id) {
                return $location;
            }
        }

        return null;
    }


    private function load_pick_ups_location()
    {
        $this->pick_ups_location = array();

        $option = get_option(self::PICKUP_LOCATIONS_OPTION);
        if ($option) {
            $this->pick_ups_location = array_filter((array)$option);
        }
    }


    private function get_pickup_address_fields($country)
    {

        $locale = WC()->countries->get_country_locale();
        $locale = $locale[$country];

        $states = WC()->countries->get_states($country);
        if (is_array($states) && 0 == count($states)) {
            $use_state = false;
        } else {
            $use_state = true;
        }

        $address_fields = WC()->countries->get_address_fields($country, 'shipping_');

        unset($address_fields['shipping_first_name'], $address_fields['shipping_last_name'], $address_fields['shipping_country']);
        $pickup_fields = array();
        foreach ($address_fields as $key => $value) {
            $key = substr($key, 9);  // strip off 'shipping_'
            unset($value['required'], $value['class'], $value['clear'], $value['type'], $value['label_class']);

            if (isset($locale['postcode_before_city']) && $locale['postcode_before_city']) {
                if ('city' == $key) {
                    $pickup_fields['postcode'] = $address_fields['shipping_postcode'];
                } elseif ('postcode' == $key) {
                    // we have already handled this
                    continue;
                }
            }

            if ((!isset($locale[$key]['hidden']) || !$locale[$key]['hidden']) && ('state' != $key || $use_state)) {
                $pickup_fields[$key] = $value;
            }

            // Address 2 label was removed in WC 2.0 for some reason, add it back in for now
            if ('address_2' == $key) {
                $pickup_fields[$key]['label'] = __('Address 2', WC_Ups_PickUps::TEXT_DOMAIN);
            }
        }

        // add a phone field if not already available
        if (!isset($pickup_fields['phone'])) {
            $pickup_fields['phone'] = array(
                'label' => __('Phone', WC_Ups_PickUps::TEXT_DOMAIN),
            );
        }

        return $pickup_fields;
    }


    private function get_cost_for_location($address)
    {

        // default cost, if any
        $cost = $this->cost;

        // overriden by location?
        if (isset($address['cost']) && '' !== $address['cost']) {
            $cost = $address['cost'];
        }

        // turn 0 into empty
        if (0 == $cost) {
            $cost = '';
        }

        return $cost;
    }


    private function get_products_by_allowed_category($contents)
    {

        $found_products = array();
        $other_products = array();


        if (is_array($this->shipping_classes)) {

            foreach ($this->shipping_classes as $shipping_class_id) {
                // once a product has been determined to belong to an eligible shipping classes, keep it eligible regardless of any other categories
                // 0 = "All Shipping Classes"

                foreach ($contents as $item) {
                    if (0 == $shipping_class_id || has_term($shipping_class_id, 'product_shipping_class', $item['product_id'])) {

                        $found_products[$item['product_id']] = $item['data']->get_title();
                        unset($other_products[$item['product_id']]);

                    } elseif (!isset($found_products[$item['product_id']])) {
                        //also keep track of products in the cart that don't match the selected category ids
                        $other_products[$item['product_id']] = $item['data']->get_title();

                    }
                }
            }
        }


        return array($found_products, $other_products);
    }

}
