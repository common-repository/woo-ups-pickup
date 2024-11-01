<?php
namespace Ups\Service;

/**
 * Class CreateWaybillService
 * @package Ups\Service
 *
 * @since 2.0.0
 */
class CreateWaybillService extends AbstractRestApiService
{
    /**
     * Init Api urls
     */
    public function iniAdditionalData(){
        $apiUrl = $this->_helper->getOption('integration_api_url');
        $apiWBUrl = $apiUrl.'/api/v1/shipment/insert-domestic-wb-by-customer';
        $apiLeadUrl = $apiUrl.'/api/v1/easyship/get-leads-track-numbers';
        $apiToken = $apiUrl.'/token';

        $this->_apiUrls = [
            'apiWBUrl' => $apiWBUrl,
            'apiLeadUrl' => $apiLeadUrl
        ];

        $this->_tokenData['url'] = $apiToken;
    }

    /**
     *
     * Create Picking List
     *
     * @param $isPickup
     * @return array|mixed
     */
    public function createWaybill($isPickup){
        $accessTokenResponse = $this->getAccessToken();

        if($accessTokenResponse['error']){
            return ['error' => $accessTokenResponse['error']];
        }

        $accessToken = $accessTokenResponse['access_token'];

        $url = $this->_apiUrls['apiWBUrl'];
        $headers = ['Authorization: Bearer '.$accessToken,'Content-Type: application/json'];

        $order = $this->_order;

        if($this->_helper->getOrderLeadId($order)){
            return ['error' => 'Order already sent to UPS'];
        }

        $data = $this->_prepareCreateWaybillRequest($isPickup);


        if($this->_helper->isDebugModeEnabled()) {
            $this->filesystem->writeLog('createWaybill request: ' . json_encode(array_merge($data, ['url' => $url])));
        }


        $apiResponse = $this->sendRequest('POST', $url, $headers, json_encode($data));
        $response = json_decode($apiResponse);

        if($this->_helper->isDebugModeEnabled()) {
            $this->filesystem->writeLog('createWaybill response: ' . json_encode($response));
        }

        $tracking_number = '';
        $lead_id = '';
        if(empty($response)){
            $error = 'Api Return Empty Response';
        }elseif(isset($response->Message)){
            $error = $response->Message;
            $this->_cache->remove($this->getCacheKey());
        }elseif($response->ErrorCode > 0) {
            $error = $response->ErrorCode . ' - ' . $response->ErrorMessage;
        }else{
            $error = false;
            $tracking_number = $response->TrackingNumber;
            $lead_id = $response->LeadId;
        }

        return ['error' => $error, 'tracking_number' => $tracking_number, 'lead_id' => $lead_id];
    }

    /**
     *
     * Create Picking List
     *
     * @param $leadId
     * @return array|mixed
     */
    public function importWaybillFromLeadId($leadId){
        $accessTokenResponse = $this->getAccessToken();

        if($accessTokenResponse['error']){
            return ['error' => $accessTokenResponse['error']];
        }

        $accessToken = $accessTokenResponse['access_token'];

        $url = $this->_apiUrls['apiLeadUrl'];
        $headers = ['Authorization: Bearer '.$accessToken];

        $data = ['model.leadIds' => $leadId];

        if($this->_helper->isDebugModeEnabled()) {
            $this->filesystem->writeLog('importWaybillFromLeadId request: ' . json_encode(array_merge($data, ['url' => $url])));
        }

        $response = json_decode($this->sendRequest('GET', $url, $headers, $data));

        if($this->_helper->isDebugModeEnabled()) {
            $this->filesystem->writeLog('importWaybillFromLeadId response: ' . json_encode($response));
        }

        $tracking_number = '';
        if(empty($response)){
            $error = 'Api Return Empty Response';
        }elseif($response->Message){
            $error = $response->Message;
            $this->_cache->remove($this->getCacheKey());
        }elseif($response->ErrorCode > 0) {
            $error = $response->ErrorCode . ' - ' . $response->ErrorMessage;
        }else{
            $error = false;

            $tracking_number = $response[0]->TrackNumber;
        }

        return ['error' => $error, 'tracking_number' => $tracking_number];
    }

    protected function _prepareCreateWaybillRequest($isPickup){
        $order = $this->_order;

        if (!$order instanceof \WC_Order) {
            return null;
        }

        $customerName = $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name();

        $pickupPointId = $this->_helper->getPickupPointId($order);

        $reference2StringLimit = 36;

        $roomNumber = $order->get_meta('ups_order_shipping_room_num');
        $floorNumber = $order->get_meta('ups_order_shipping_floor_num');

        $data = [
            'NumberOfPackages' => $this->_helper->getOrderNumberOfPackages($order),
            'ConsigneeAddress' => [
                'ContactPerson' => $customerName,
                'CustomerName' => $customerName,
                'CityName' => $this->_helper->fixTextQuotes($order->get_shipping_city()),
                'HouseNumber' => $this->_helper->getShippingHouseNumber($order),
                'RoomNumber' => !empty($roomNumber) ? $roomNumber : $this->_helper->getHouseNumber($order->get_shipping_address_2()),
                'Floor' => !empty($floorNumber) ? $floorNumber : '',
                'StreetName' => $this->_helper->fixTextQuotes($order->get_shipping_address_1()),
                'Phone1' => $order->get_shipping_phone() ?: $order->get_billing_phone(),
                'Phone2' => $order->get_shipping_phone() ?: $order->get_billing_phone(),
                'ContactEmail' => $order->get_billing_email(),
                'ZipCode' => $order->get_shipping_postcode(),
                'Instructions' => $this->_helper->getShipmentInstructions($order),
                'LocationDescription' => $this->_helper->getShipmentInstructions($order)
            ],
            'Reference1' => $order->get_id(),
            'Reference2' => mb_substr($this->_helper->getReference2Data($order), 0, $reference2StringLimit, 'utf-8'),
            'UseDefaultShipperAddress' => true,
            'Weight' => $this->_helper->getOrderWeight($order),
            'ProcessName' => 10
        ];

        /**
         * @since 2.5.0
         */
        try {
            if (!$this->_helper->isShippingMethodIsPickupUpsInclClosestPoints($order)) {
                if($is_ddo = $order->get_meta('ups_is_ddo')){
                    $data['IsDDO'] = !!$is_ddo;
                }
                if($cod_details = $order->get_meta('ups_cod_details')){
                    $data['CODDetails'] = $cod_details;
                }
                if($cod_value = $order->get_meta('ups_cod_value')){
                    $data['CODValue'] = $cod_value;
                }
                if($is_udr = $order->get_meta('ups_is_udr')){
                    $data['IsUDR'] = !!$is_udr;
                }
                if($is_return = $order->get_meta('ups_is_return')){
                    $data['IsReturn'] = !!$is_return;
                }
            }
        } catch (\Exception $e){
            $this->filesystem->writeLog('_prepareCreateWaybillRequest additional ups fields error: '. $e->getMessage());
        }

        if($isPickup){
            $data['PickupPointID'] = $pickupPointId;
        }

        return $data;
    }
}
