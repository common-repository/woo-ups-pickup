<?php
/**
 * @category Ups
 * @copyright Ups
 */
namespace Ups\Service;

class InsertPickupsShipment extends AbstractService
{
    /**
     * @var \WC_Order
     */
    protected $order;

    /**
     * @inheritdoc
     */
    public function _getServiceName()
    {
        return 'InsertPickupsShipment';
    }

    /**
     * @inheritdoc
     */
    public function _getServiceUrl()
    {
        return $this->helper->getServiceUrlByCode('ship_wb');
    }

    /**
     * @param \WC_Order $order
     */
    public function setOrder($order)
    {
        $this->order = $order;
    }

    /**
     * @inheritdoc
     */
    public function _prepareRequest()
    {
        $order = $this->order;
        if (!$order  instanceof \WC_Order) {
            return null;
        }
        $customerName = $order ->get_shipping_first_name() . ' ' . $order->get_shipping_last_name();

        if (!class_exists('WC_Shipping_Ups_PickUps_CPT')) {
            $filePath = implode(DIRECTORY_SEPARATOR, array(
                WP_PLUGIN_DIR,
                WC_UPS_PLUGIN_DIR,
                'includes/admin/class-wc-shipping-shipping-ups-pick-ups-shop-order-cpt.php'
            ));

            require_once $filePath;
        }
        $pickupPointId = null;

        /**
         * get json from order meta
         * if orders placed before plugin update (Version 1.5.0), we check if json is in the item
         */
        $json = $order->get_meta(\WC_Shipping_Ups_PickUps_CPT::PICKUP_ORDER_METADATA_FIELD) ?: wc_get_order_item_meta($order->get_id(), \WC_Shipping_Ups_PickUps_CPT::PICKUP_ORDER_METADATA_FIELD);
        if ($json) {
            $json = str_replace('\\"', '"', $json);
            $json = preg_replace('/\\\"/', "\"", $json);
            $json = preg_replace('/\\\'/', "\'", $json);
            $pickupInfo = json_decode($json, true);
            if (!empty($pickupInfo['iid'])) {
                $pickupPointId = $pickupInfo['iid'];
            }
        }

        $data = [
            'info' => [
                'NumberOfPackages' => 1,
                'ConsigneeAddress' => [
                    'ContactPerson' => $customerName,
                    'CustomerName' => $customerName,
                    'CityName' => $order->get_shipping_city(),
                    'HouseNumber' => $this->helper->_getHouseNumber($order->get_shipping_address_1()),
                    'StreetName' => $order->get_shipping_address_1(),
                    'Phone1' => $order->get_billing_phone()
                ],
                'PickupPointID' => $pickupPointId,
                'Reference1' => $order->get_id(),
                'UseDefaultShipperAddress' => 'true',
                'Weight' => $this->_calculateWeigh()
            ]
        ];

        return $data;
    }

    /**
     * @inheritdoc
     */
    protected function _readResponse($response, $client)
    {
        if (!$response->InsertPickupsShipmentResult->IsSucceeded) {
            throw new \Exception($response->InsertPickupsShipmentResult->LastError->OriginalMessage);
        }

        $trackingNumber = !empty($response->InsertPickupsShipmentResult->TrackingNumber)
            ? $response->InsertPickupsShipmentResult->TrackingNumber : '';

        if (!$trackingNumber) {
            throw new \Exception(__('Invalid shipment number', \WC_Ups_PickUps::TEXT_DOMAIN));
        }

        return [
            'tracking_number' => $trackingNumber
        ];
    }

    /**
     * @return float
     */
    protected function _calculateWeigh()
    {
        // always send weigh 0
        $weight = 0;

        return $weight;
    }
}
