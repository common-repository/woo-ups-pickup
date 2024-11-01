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

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Order CPT class
 *
 * Handles modifications to the shop order CPT on both View Orders list table and Edit Order screen
 *
 * @since 1.0
 */
class WC_Shipping_Ups_PickUps_CPT {

    /** Pickup lorder metadata field */
	const PICKUP_ORDER_METADATA_FIELD = 'pkps_json';

    /**
     * @var Ups
     */
	protected $helper;

	/**
	 * Add actions/filters for View Orders/Edit Order screen
	 *
	 * @since 1.8.0
	 */
	public function __construct() {

        // HPOS filters
        // Add 'Pickup Locations' orders page column header
        add_filter('manage_woocommerce_page_wc-orders_columns', array($this, 'render_pick_ups_location_column_header'), 20);
        // Add 'Pickup Locations' orders page column content
        add_filter('manage_woocommerce_page_wc-orders_custom_column', array($this, 'render_pick_ups_location_column_content_hpos'), 10, 2);

        // Legacy filters
        // Add 'Pickup Locations' orders page column header
        add_filter('manage_edit-shop_order_columns', array($this, 'render_pick_ups_location_column_header'), 20);
        // Add 'Pickup Locations' orders page column content
        add_filter('manage_shop_order_posts_custom_column', array($this, 'render_pick_ups_location_column_content_legacy'));

		// Add CSS to tweak the 'Pickup Locations' column
//		add_action( 'admin_head',                           array( $this, 'render_pick_ups_location_column_styles' ) );
	}


	/** Listable Columns ******************************************************/


	/**
	 * Adds 'Pickup Locations' column header to 'Orders' page immediately after 'Ship to' column
	 *
	 * @since 1.8.0
	 * @param array $columns
	 * @return array $new_columns
	 */
	public function render_pick_ups_location_column_header( $columns ) {

		$new_columns = array();

		foreach ( $columns as $column_name => $column_info ) {

			$new_columns[ $column_name ] = $column_info;

			if ( 'shipping_address' == $column_name ) {

				$new_columns['pick_ups_location'] = __( 'PickUP Point Location', WC_Ups_PickUps::TEXT_DOMAIN );
			}
		}

		return $new_columns;
	}


    /**
     * Adds 'Pickup Locations' column content to 'Orders' page immediately after 'Order Status' column
     *
     * @param array $column name of column being displayed
     * @throws Exception
     * @since 1.8.0
     */
    public function render_pick_ups_location_column_content_legacy ( $column ){
        global $the_order;

        if ( 'pick_ups_location' === $column ) {
            $this->render_pick_ups_location_column_content($the_order);
        }
    }

    /**
     * Adds 'Pickup Locations' column content to 'Orders' page immediately after 'Order Status' column
     *
     * @param array $column name of column being displayed
     * @param $order
     * @throws Exception
     * @since 1.8.0
     */
    public function render_pick_ups_location_column_content_hpos ( $column, $order ){
        if ( 'pick_ups_location' === $column ) {
            $this->render_pick_ups_location_column_content($order);
        }
    }

    /**
     * @param $order
     * @throws Exception
     * @since 2.7.0
     */
    protected function render_pick_ups_location_column_content ($order){
        $order_id = $order->get_id();
        foreach ( $order->get_shipping_methods() as $shipping_item ) {

            if ( WC_Ups_PickUps::METHOD_ID == $shipping_item['method_id'] ) {
                /**
                 * get json from order meta
                 * if orders placed before plugin update (Version 1.5.0), we check if json is in the item
                 */
                $jsondata = $order->get_meta('pkps_json') ?: wc_get_order_item_meta( $order_id, self::PICKUP_ORDER_METADATA_FIELD);
                $jsondata = str_replace('\\"', '"', $jsondata);
                $jsondata = preg_replace('/\\\"/',"\"", $jsondata);
                $jsondata = preg_replace('/\\\'/',"\'", $jsondata);

                $pkps_order = json_decode($jsondata, false);
                if ($pkps_order) {
                    include( dirname( __FILE__ ) . '/templates/pickup-location-html.php' );
                }
            }
        }
    }

	/**
	 * Adds CSS to style the 'Pickup Locations' column
	 *
	 * @since 1.10.1
	 */
	public function render_pick_ups_location_column_styles() {

		$screen = get_current_screen();
		if ( 'edit-shop_order' === $screen->id || 'shop_order' === $screen->id ) {
		?>
			<style type="text/css">
				.widefat .column-pick_ups_location {
					width: 12%;
                }
                div.pkpinfo{
                    background-color: #eee !important;
                    line-height: 130% !important;
                    border: 2px dashed #ddd !important;
                    border-radius: 4px !important;
                    padding: 5px 10px 5px 5px;
                }
                div.pkpinfo div.pkppoint{
                    font-weight: 600;
                    transform: matrix3d(1, 0, 0, 0, 0, 1.5, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1);
                    margin-bottom: 6px;
                    font-family: 'Segoe UI', Arial;
                }
                div.pkpinfo div.pkpaddress .city{
                    font-weight: 500;
                }
			</style>
		<?php
		}
	}

	/** Helper Methods ********************************************************/


	/**
	 * Gets any order pickup locations from the given order
	 *
	 * @since 1.8.0
	 * @param WC_Order $order the order
	 * @return array of pickup locations, with country, postcode, state, city, address_2, adress_1, company, phone, cost and id properties
	 */
	private function get_order_pick_ups_location( $order ) {

		$pick_ups_location = array();

		foreach ( $order->get_shipping_methods() as $shipping_item ) {

			if ( $this->isPickupUps($shipping_item['method_id']) && isset( $shipping_item['pickups_location'] ) ) {
				$location = maybe_unserialize( $shipping_item['pickups_location'] );
				$pick_ups_location[] = $location;
			}
		}

		return $pick_ups_location;
	}

    /**
     * @param string $methodId
     * @return bool
     *
     * @since 2.3.0
     */
    public function isPickupUps($methodId)
    {
        $pickupsId = 'woo-ups-pickups';
        $len = strlen($pickupsId);

        return $methodId === $pickupsId || (substr($methodId, 0, $len) === $pickupsId);
    }


} // end \WC_Shipping_Ups_Pick_Ups_CPT class
