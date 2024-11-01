<?php
/**
 * @category Ups
 * @copyright Ups
 */
namespace Ups\Service;

class InsertPickupDrop extends AbstractService
{
    protected $_rma;

    protected $orderFactory;

    protected $rmaHelper;

    protected $rmaRepository;



    /**
     * @inheritdoc
     */
    public function _getServiceName()
    {
        return 'InsertPickUPDrop';
    }

    /**
     * @inheritdoc
     */
    public function _getServiceUrl()
    {
        return $this->upsHelper->getServiceUrlByCode('ship_wb');
    }

    /**
     * @inheritdoc
     */
    public function _prepareRequest()
    {
        $rma = $this->getRma();
        if (!$rma) {
            return null;
        }

        $orderId = $rma->getOrderId();
        /* @var \Magento\Sales\Model\Order $order */
        $order = $this->orderFactory->create()->load($orderId);

        if (!$order->getId()) {
            return null;
        }

        $shippingAddress = $order->getShippingAddress();

        $customerName = $shippingAddress->getFirstname() .' '. $shippingAddress->getLastname();
        $dropOffInfo = $this->getDroffOpInfo($rma);
        $incrementId = $this->rmaHelper->generateIncrementId($rma);

        $data = [
            'info' => [
                'NumberOfPackages' => 1,
                'ConsigneeAddress' => [
                    'CustomerName' => $customerName,
                    'ContactPerson' => $customerName,
                    'CityName' => $shippingAddress->getCity(),
                    'HouseNumber' => $this->helper->_getHouseNumber($shippingAddress->getStreetLine(1)),
                    'StreetName' => $shippingAddress->getStreetLine(1),
                    'Phone1' => $shippingAddress->getTelephone()
                ],
                'ShipperAddress' => [
                    'CustomerName' => $this->_getField($dropOffInfo, 'title'),
                    'ContactPerson' => $this->_getField($dropOffInfo, 'title'),
                    'CityName' => $this->_getField($dropOffInfo, 'city'),
                    'StreetName' => $this->_getField($dropOffInfo, 'street'),
                    'Phone1' => $this->_getField($dropOffInfo, 'telephone')
                ],
                'PickupPointID' => $this->_getField($dropOffInfo, 'iid'),
                'Reference1' => $incrementId,
                'UseDefaultShipperAddress' => 'true',
                'UseDefaultShipperAddressSpecified' => 'true',
                'PaymentType' => 'PP',
                'Weight' => $this->_calculateWeigh()
            ]
        ];

        return $data;
    }

    /**
     * @param $rma
     * @return array|mixed
     */
    public function getDroffOpInfo($rma)
    {
        $additionalData = $rma->getAdditionalData();
        if ($additionalData) {
            return \Zend_Json::decode($additionalData);
        }

        return [];
    }

    /**
     * @param array $array
     * @param string $fieldId
     * @param null|mixed $default
     * @return mixed
     */
    protected function _getField($array, $fieldId, $default = null)
    {
        return isset($array[$fieldId]) ? $array[$fieldId] : $default;
    }

    /**
     * @inheritdoc
     */
    public function _readResponse($response)
    {
        if (!$response->InsertPickUPDropResult->IsSucceeded) {
            throw new \Exception($response->InsertPickUPDropResult->LastError->OriginalMessage);
        }

        $trackingNumber = !empty($response->InsertPickUPDropResult->TrackingNumber)
            ? $response->InsertPickUPDropResult->TrackingNumber : '';

        $data = [
            'tracking_number' => $trackingNumber
        ];

        return $data;
    }

    /**
     * @return float
     */
    protected function _calculateWeigh()
    {
        $selectedItems = $this->rmaRepository->getSelectedItems();
        $weight = 0;

        if (count($selectedItems)) {
            foreach ($selectedItems as $rmaItem) {
                $weight += $rmaItem->getQtyRequested() * $rmaItem->getWeight();
            }
        }

        return $weight;
    }

    /**
     * @return \Mirasvit\Rma\Model\Rma
     */
    public function getRma()
    {
        return $this->_rma;
    }

    /**
     * @param \Mirasvit\Rma\Model\Rma $rma
     * @return $this
     */
    public function setRma($rma)
    {
        $this->_rma = $rma;

        return $this;
    }
}
