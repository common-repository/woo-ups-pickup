<?php
/**
 * @category UPS
 * @copyright UPS Company
 */
namespace Ups\Order\Grid\Column;

use Ups\Helper\Ups;
use Ups\Order\Api;

class WB
{
    const COLUMN_ID = 'shipping_wb';

    /**
     * @param string $column
     * @param int $orderId
     */
    public function render($column, $orderId)
    {
        if ($column !== self::COLUMN_ID) {
            return;
        }

        $html = '';
        $url = \Ups\Helper\Ups::TRACKING_URL;

        $order = wc_get_order($orderId);
        $syncFlag = $order->get_meta('ups_sync_flag');
        if ($syncFlag == Api::STATUS_SEND_SUCCESS) {
            $shipmentNumber = $order->get_meta('ups_ship_number');
            $url .= urlencode($shipmentNumber);
            if ($shipmentNumber) {
                $html .= '<a href="'. $url .'" target="_blank">'. $shipmentNumber .'</a>';
            }
        }

        echo $html;
    }
}
