<?php
namespace Ups\Service;

/**
 * Class GetInformationService
 * @package Ups\Service
 *
 * @since 2.4.0
 */
class GetInformationService extends AbstractRestApiService
{

    /**
     * Init Api urls
     */
    public function iniAdditionalData(){
        $apiUrl = $this->_helper->getOption('integration_picking_api_url');
        $this->_apiUrls = [
            'apiWBStatus' => $apiUrl.'/api/v1/shipments/wb-status'
        ];
    }

    /**
     * Import Waybill Status
     *
     * @param $trackingNumber
     * @return array
     */
    public function importWaybillStatus($trackingNumber){
        $accessTokenResponse = $this->getAccessToken();

        if($accessTokenResponse['error']){
            return ['error' => $accessTokenResponse['error']];
        }

        $accessToken = $accessTokenResponse['access_token'];

        $url = $this->_apiUrls['apiWBStatus'];
        $headers = ['Authorization: Bearer '.$accessToken,'Content-Type: application/json'];

        $data = ['trackingNumber' => $trackingNumber];

        if($this->_helper->isDebugModeEnabled()) {
            $this->filesystem->writeLog('importWaybillStatus request: ' . json_encode(array_merge($data, ['url' => $url])));
        }

        $response = json_decode($this->sendRequest('GET', $url, $headers, $data));

        if($this->_helper->isDebugModeEnabled()) {
            $this->filesystem->writeLog('importWaybillStatus response: ' . json_encode($response));
        }

        $wbStatus = '';
        if(isset($response->Status)){
            $error = false;
            $wbStatus = $response->Status;
        }else{
            $error = 'WB Status Not Found';
            $this->_cache->remove($this->getCacheKey());
        }

        return ['error' => $error, 'wb_status' => $wbStatus];
    }
}
