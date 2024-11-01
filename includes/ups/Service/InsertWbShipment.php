<?php
/**
 * @category Ups
 * @copyright Ups
 */
namespace Ups\Service;

class InsertWbShipment extends AbstractService
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
        return 'InsertWbShipment';
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

        $data = [
            'info' => [
                'NumberOfPackages' => 1,
                'ConsigneeAddress' => [
                    'CustomerName' => $customerName,
                    'CityName' => $order->get_shipping_city(),
                    'HouseNumber' => $this->helper->_getHouseNumber($order->get_shipping_address_1()),
                    'StreetName' => $order->get_shipping_address_1(),
                    'Phone1' => $order->get_billing_phone()
                ],
                'Reference1' => $order->get_id(),
                'UseDefaultShipperAddress' => 'true',
                'PaymentType' => 'PP',
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
        if (!$response->InsertWbShipmentResult->IsSucceeded) {
            throw new \Exception($response->InsertWbShipmentResult->LastError->OriginalMessage);
        }

        $trackingNumber = !empty($response->InsertWbShipmentResult->TrackingNumber)
            ? $response->InsertWbShipmentResult->TrackingNumber : '';

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
