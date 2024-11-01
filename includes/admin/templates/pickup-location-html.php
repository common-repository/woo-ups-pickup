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

do_action( 'woocommerce_before_pickup_location_template_html' ); ?>
<div class="pkpinfo">
    <img src="<?= plugin_dir_url( dirname(__DIR__) ) ?>assets/logo_pickuppoint_small.png" />
    <div class="pkppoint"><?php echo $pkps_order->iid;?></div>
    <div class="pkptitle"><?php echo $pkps_order->title;?></div>
    <div class="pkpaddress"><span class="street"><?php echo $pkps_order->street;?></span>, <span class="city"><?php echo $pkps_order->city;?></span></div>
    <div style="text-decoration: underline"><?php if(isset($pkps_order->closest_points)): ?>נבחרה נקודת פיקאפ קרובה<?php endif; ?></div>
</div>

<?php do_action( 'woocommerce_after_pickup_location_template_html' ); ?>
