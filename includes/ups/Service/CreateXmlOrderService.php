<?php
namespace Ups\Service;

/**
 * Class CreateXmlOrderService
 * @package Ups\Service
 *
 * @since 2.0.0
 */
class CreateXmlOrderService extends AbstractRestApiService
{

    /**
     * Init Api urls
     */
    public function iniAdditionalData(){
        $apiUrl = $this->_helper->getOption('integration_api_url');

        $this->_apiUrls = [
            'apiUrl' => $apiUrl
        ];
    }

    /**
     * Send Order Xml via Plugins Api
     * @param $file
     * @return bool|mixed|string[]
     *
     * @since 1.10.2
     */
    public function sendFileViaPluginsApi($file){
        $apiUrl = $this->_apiUrls['apiUrl'].'/api/v1/upload/';
        $apiTokenUrl = $this->_apiUrls['apiUrl'].'/token';
        $customerFolder = $this->_helper->getOption('send_order_to_ftp_path');

        if(!$apiTokenUrl || !$apiUrl || !$customerFolder){
            return ['error' => 'Api Login Data is missing'];
        }

        $accessTokenResponse = $this->getAccessToken($apiTokenUrl);

        if($accessTokenResponse['error']){
            return $accessTokenResponse;
        }
        $accessToken = $accessTokenResponse['access_token'];

        $url = $apiUrl.$customerFolder;

        $fileName = basename($file);
        $fileSize = filesize($fileName);

        if($this->_helper->isDebugModeEnabled()) {
            $this->filesystem->writeLog('sendFileViaPluginsApi request: ' . json_encode(['url' => $url, 'accessToken' => $accessToken]));
        }

        $headers = ['Authorization: Bearer '.$accessToken,'Content-Type: multipart/form-data'];

        $postFields = [
            'name' => new \CurlFile($file, 'application/xml', $fileName)
        ];

        $ch = curl_init();
        $options = array(
            CURLOPT_URL => $url,
            CURLOPT_HEADER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_INFILESIZE => $fileSize,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_VERBOSE => true
        );

        curl_setopt_array($ch, $options);
        curl_exec($ch);
        $error_no = curl_errno($ch);
        $resultInfo = curl_getinfo($ch);

        if ($error_no == 0) {
            if(isset($resultInfo['http_code']) && $resultInfo['http_code'] != '200'){
                return ['error' => 'error in PluginsApi'];
            }
            return true;
        } else {
            return ['error' => 'sendOrderXmlToFtp: FTP connection error: '.$error_no];
        }
    }
}
