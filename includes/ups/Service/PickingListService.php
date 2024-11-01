<?php
namespace Ups\Service;

/**
 * Class PickingListService
 * @package Ups\Service
 *
 * @since 2.0.0
 */
class PickingListService extends AbstractRestApiService
{
    /**
     * Init Api urls
     */
    public function iniAdditionalData(){
        $data = $this->_helper->isPickingIntegrationData();

        $this->_apiUrls = [
            'apiSendUrl' => $data['apiSendUrl'],
            'apiPrintUrl' => $data['apiPrintUrl']
        ];
    }

    /**
     *
     * Create Picking List
     *
     * @return array|mixed
     */
    public function createPickingList(){
        $accessTokenResponse = $this->getAccessToken();

        if($accessTokenResponse['error']){
            return ['error' => $accessTokenResponse['error']];
        }

        $accessToken = $accessTokenResponse['access_token'];

        $url = $this->_apiUrls['apiSendUrl'];
        $headers = ['Authorization: Bearer '.$accessToken,'Content-Type: application/json'];

        $upsShipNumber = $this->_order->get_meta('ups_ship_number');
        $orderId = $this->_order->get_id();

        $data = ['TrackNO' => $upsShipNumber, 'Ref1' => $orderId];

        $itemsErrors = '';
        foreach ( $this->_order->get_items() as $item ){
            $itemData = $item->get_data();
            $product = $item->get_product();
            $productId = $product->get_id();

            if(!$productName = $itemData['name']){
                $itemsErrors = 'Missing Name for product ID: '.$productId.' ';
                continue;
            }
            if(!$productSku = $product->get_sku()){
                $itemsErrors = 'Missing SKU for product: '.$productName.' (ID: '.$productId.') ';
                continue;
            }
            if(!$productQuantity = $itemData['quantity']){
                $itemsErrors = 'no quantity for product: '.$productName.' (ID: '.$productId.') ';
                continue;
            }

            $data['Items'][] = [
                'SKU1' => $productSku,
                'SKU2' => $product->get_meta(\WC_Ups_PickUps::PRODUCT_PICKING_LIST_BARCODE_ATTRIBUTE),
                'Description' => $productName,
                'Quantity' => $productQuantity,
                'Location' => $product->get_meta(\WC_Ups_PickUps::PRODUCT_PICKING_LIST_LOCATION_ATTRIBUTE),
                'WH' => '',
                'Remarks' => $product->get_meta(\WC_Ups_PickUps::PRODUCT_PICKING_LIST_REMARKS_ATTRIBUTE),
            ];
        }

        if($itemsErrors !== ''){
            return ['error' => $itemsErrors];
        }

        if($this->_helper->isDebugModeEnabled()) {
            $this->filesystem->writeLog('createPickingList request: ' . json_encode(array_merge($data, ['url' => $url])));
        }

        $response = json_decode($this->sendRequest('POST', $url, $headers, json_encode($data)));

        if($this->_helper->isDebugModeEnabled()) {
            $this->filesystem->writeLog('createPickingList response: ' . json_encode($response));
        }

        if($response->ErrorCode == 0){
            $error = false;
        }else{
            $error = $response->ErrorCode .' - '.$response->ErrorDescription;
            $this->_cache->remove($this->getCacheKey());
        }

        return ['error' => $error];
    }

    public function preparePrintRequest($trackingNumbers, $format){
        $isA4Format = $format === 'A4' ? 'True' : 'False';

        return ['trackingNumbers' => $trackingNumbers, 'isA4Format' => $isA4Format];
    }
}
