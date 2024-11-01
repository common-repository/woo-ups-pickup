<?php
namespace Ups\Service;

use Ups\Filesystem;
use Ups\Helper\Ups;

/**
 * Class RestApiService
 * @package Ups\Service
 *
 * @since 1.8.0
 */
class RestApiService
{

    protected $_tokenData = [];
    protected $_apiUrls = [];
    protected $_orderData = [];

    protected $_order;

    protected $_helper;

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @param $order
     */
    public function __construct($order = false)
    {
        $this->_order = $order;
        $this->_helper = new Ups();
        $this->filesystem = new Filesystem();
        $this->init();
    }

    /**
     * init data
     */
    private function init(){
        $data = $this->_helper->isPickingIntegrationData();

        $this->_tokenData = [
            'url' => $data['tokenUrl'],
            'username' => $data['tokenUsername'],
            'password' => $data['tokenPassword'],
            'scope' => $data['tokenScope'],
            'grant_type' => 'password'
        ];

        $this->_apiUrls = [
            'apiSendUrl' => $data['apiSendUrl'],
            'apiPrintUrl' => $data['apiPrintUrl']
        ];
    }

    /**
     *
     * Get Access Token
     *
     * @param string $url
     * @return array
     */
    private function getAccessToken($url = ''){
        $data = $this->_tokenData;
        if(!$url){
            $url = $data['url'];
        }
        unset($data['url']);
        $headers = ['Content-Type: application/x-www-form-urlencoded'];
        $response = json_decode($this->sendRequest('POST', $url, $headers, $data));

        $error = false;
        if(isset($response->error)){
            $error = $response->error .' - '.$response->error_description;
        }
        $accessToken = isset($response->access_token) ? $response->access_token : null;

        if($accessToken === null){
            $error = 'the username or password is incorrect';
        }

        return ['error' => $error, 'access_token' => $accessToken];
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

        $response = json_decode($this->sendRequest('POST', $url, $headers, json_encode($data)));

        if($response->ErrorCode == 0){
            $error = false;
        }else{
            $error = $response->ErrorCode .' - '.$response->ErrorDescription;
        }

        return ['error' => $error];
    }

    /**
     *
     * Print Label (Picking Page)
     *
     * @param $trackingNumbers
     * @param $format
     * @return mixed
     */
    public function printLabel($trackingNumbers, $format){
        $accessTokenResponse = $this->getAccessToken();

        if($accessTokenResponse['error']){
            return $accessTokenResponse['error'];
        }
        $accessToken = $accessTokenResponse['access_token'];

        $url = $this->_apiUrls['apiPrintUrl'];
        $headers = ['Authorization: Bearer '.$accessToken,'Content-Type: application/json'];
        $isA4Format = $format === 'A4' ? 'True' : 'False';

        $data = ['trackingNumbers' => $trackingNumbers, 'isA4Format' => $isA4Format];

        $response = $this->sendRequest('GET', $url, $headers, $data);

        $pdfContent = base64_decode($response, true);

        $error = false;
        if (strpos($pdfContent, '%PDF') !== 0) {
            $error = 'No Label Found';
        }

        return ['error' => $error, 'response' => $pdfContent];
    }

    /**
     * Send Order Xml via Plugins Api
     * @param $file
     * @return bool|mixed|string[]
     *
     * @since 1.10.2
     */
    public function sendFileViaPluginsApi($file){
        $apiTokenUrl = $this->_helper->getOption('send_order_to_ftp_api_token');
        $apiUrl = $this->_helper->getOption('send_order_to_ftp_api_url');
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

    /**
     *
     * Send Rest Api Request
     *
     * @param $method
     * @param $url
     * @param $headers
     * @param bool $data
     * @return bool|string
     */
    private function sendRequest($method, $url, $headers, $data = false)
    {
        $curl = curl_init();

        if($headers) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        }

        if ($method == 'POST') {
            curl_setopt($curl, CURLOPT_POST, 1);

            if ($data) {
                $curlData = is_array($data) ? http_build_query($data) : $data;
                curl_setopt($curl, CURLOPT_POSTFIELDS, $curlData);
            }
        } else if ($data) {
            $url = sprintf('%s?%s', $url, http_build_query($data));
        }

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_ENCODING, 'UTF-8');
        $result = curl_exec($curl);

        curl_close($curl);

        return $result;
    }

    /**
     *  Check UPS Rest Api Username & Password
     *
     * @since 1.10.5
     */
    public function checkUpsRestApiSettings(){
        $fieldsError = [];
        $accessTokenResponse = $this->getAccessToken();

        if($accessTokenResponse['error']){
            $fieldsError[] = 'integration_picking_username';
            $fieldsError[] = 'integration_picking_password';
            $fieldsError[] = 'integration_picking_scope';
        }

        return $fieldsError;
    }
}
