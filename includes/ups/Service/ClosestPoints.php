<?php
namespace Ups\Service;

/**
 * Class ClosestPoints
 * @package Ups\Service
 *
 * @since 2.3.0
 */
class ClosestPoints extends AbstractRestApiService
{

    /**
     * Init Api urls
     */
    public function iniAdditionalData(){
        $apiUrl = $this->_helper->getOption('integration_picking_api_url');
        $this->_apiUrls = [
            'apiClosestPointsUrl' => $apiUrl.'/api/v1/pickups/getclosestpoints'
        ];
    }

    public function getClosestPoints($customerAddress, $settings){
        $accessTokenResponse = $this->getAccessToken();

        if($accessTokenResponse['error']){
            return ['error' => $accessTokenResponse['error']];
        }

        $accessToken = $accessTokenResponse['access_token'];

        $url = $this->_apiUrls['apiClosestPointsUrl'];
        $headers = ['Authorization: Bearer '.$accessToken,'Content-Type: application/json'];

        $pointType = $this->getClosestPointsType($settings['stores_lockers']);

        $data = [
            'city' => $customerAddress['address_city'],
            'street' => $customerAddress['address1'] ?: 1,
            'houseNumber' => '',
            'pointTypes' => $pointType,
            'points' => $settings['pickups_closest_points_number']
        ];

        $addressData = [
            'city' => $data['city'],
            'street' => $data['street'],
            'houseNumber' => ''
        ];

        if($closestPointsFromSession = $this->getClosestPointsFromSession($addressData)){
            $response = $closestPointsFromSession;
        } else {

            if ($this->_helper->isDebugModeEnabled()) {
                $this->filesystem->writeLog('getClosestPoints request: ' . json_encode(array_merge($data, ['url' => $url])));
            }

            $response = json_decode($this->sendRequest('GET', $url, $headers, $data));

            if ($this->_helper->isDebugModeEnabled()) {
                $this->filesystem->writeLog('getClosestPoints response: ' . json_encode($response));
            }

            if ($response->IsSuccessful !== true) {
                $error = $response->ErrorMSG;
                $this->_cache->remove($this->getCacheKey());

                return ['error' => $error];
            }

            $this->setClosestPointsFromSession($response, $addressData);
        }

        $accuracyCodes = $this->getClosestPointsAccuracyCode($settings['pickups_closest_points_accuracy']);
        if(!in_array($response->ResponseCode, $accuracyCodes)){
            return ['error' => 'Pickup points not found'];
        }

        return ['points' => $response->Points];
    }

    private function setClosestPointsFromSession($response, $addressData){
        $responseMerged = ['addressData' => $addressData, 'response' => $response, 'time' => time()];
        $_SESSION['pickups_closest_points'] = json_encode($responseMerged);
    }

    private function getClosestPointsFromSession($addressData){
        if(isset($_SESSION['pickups_closest_points'])){
            $closestPointsSession = json_decode($_SESSION['pickups_closest_points']);

            if($this->closestPointsSettingsHasUpdated($closestPointsSession)){
                return false;
            }

            $sessionAddressData = $closestPointsSession->addressData;

            if ($this->_helper->isDebugModeEnabled()) {
                $this->filesystem->writeLog('getClosestPointsFromSession: ' . $_SESSION['pickups_closest_points']);
                $this->filesystem->writeLog('getClosestPointsFromSession addressData: ' . json_encode($addressData));
            }

            if($sessionAddressData->street === $addressData['street'] && $sessionAddressData->city === $addressData['city'] && $sessionAddressData->houseNumber === $addressData['houseNumber']){
                return $closestPointsSession->response;
            }
        }
        return false;
    }

    private function getClosestPointsAccuracyCode($type){
        $accuracyCodes = [];

        // exact
        $accuracyCodes[] = '100';
        $accuracyCodes[] = '200';
        $accuracyCodes[] = '300';
        $accuracyCodes[] = '400';

        if($type !== 'exact') {
            $accuracyCodes[] = '120';
            $accuracyCodes[] = '125';
            $accuracyCodes[] = '220';
        }

        if($type === 'city') {
            $accuracyCodes[] = '840';
        }

        return $accuracyCodes;
    }

    private function getClosestPointsType($type){
        switch($type){
            case 'stores':
                return 1;
            case 'lockers':
                return 2;
            default:
                return 3;
        }
    }

    private function closestPointsSettingsHasUpdated($closestPointsSession){
        $settingsUpdatedAt = $this->_helper->getSettingsUpdateAt();
        return $closestPointsSession->time < $settingsUpdatedAt;
    }
}
