<?php
/**
 * PickUP location html template
 * 
 * @package     WC-Shipping-Ups-Pickups
 * @author      O.P.S.I (International Handling) Ltd
 * @category    Shipping
 * @copyright   Copyright: (c) 2016-2018 O.P.S.I (International Handling) Ltd
 * @license     http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

?>
<style>
    #pickups_location1_field{
        display: none;
    }
    #pickups_location2_field{
        display: none;
    }
</style>

<?php do_action( 'woocommerce_after_pickup_location_template_html' ); ?>