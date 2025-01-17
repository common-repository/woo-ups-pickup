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

do_action( 'woocommerce_before_pickup_button_html' ); ?>
<tr class="pickups_location" onclick="window.PickupsSDK.onClick();return;" style="cursor: pointer;">
    <th colspan="1"><?php echo __("Service powered of PickUP", WC_Ups_PickUps::TEXT_DOMAIN) ?><br /><div class="ups-pickups-checked"><?php echo $this->settings["service_description"] ?></div> </th>
    <td class="update_totals_on_change">
        <div class="ups-pickups-desc"><?php echo __("Click here to select your PickUP location", WC_Ups_PickUps::TEXT_DOMAIN) ?></div>
        <div onclick="window.PickupsSDK.onClick();return;" class="ups-pickups ups-pickups-48" data-provider="as453ffadfgds"></div>
        <div class="ups-pickups-info"></div>
    </td>
</tr>
<?php do_action( 'woocommerce_after_pickup_button_html' ); ?>
