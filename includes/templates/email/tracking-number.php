<?php
/**
 * @category UPS
 * @copyright UPS Company
 */
$url = 'https://site.ship.co.il?';
$url .= http_build_query(array(
    'trackNumber' => $shipmentNumber
));
?>
<table id="ups-tracking-number" cellspacing="0" cellpadding="0"
       style="width: 100%; vertical-align: top; margin-bottom: 40px; padding:0;" border="0">
    <tbody>
    <tr>
        <td style="text-align:right; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; border:0; padding:0;"
            valign="top" width="50%">
            <h2><?php _e('UPS Tracking Number', \WC_Ups_PickUps::TEXT_DOMAIN) ?></h2>

            <address class="address">
                <a href="<?php echo $url ?>" target="_blank"><?php echo $shipmentNumber ?></a>
            </address>
        </td>
    </tr>
    </tbody>
</table>
