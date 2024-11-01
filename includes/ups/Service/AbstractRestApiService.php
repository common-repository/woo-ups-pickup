<?php
namespace Ups\Service;

use Ups\Filesystem;
use Ups\Helper\Ups;
use Ups\Cache;

/**
 * Class AbstractRestApiService
 * @package Ups\Service
 *
 * @since 2.0.0
 */
abstract class AbstractRestApiService
{

    protected $_tokenData = [];
    protected $_apiUrls = [];

    protected $_order;

    protected $_helper;

    protected $_cache;

    protected $_cacheKey = 'api_print_url_token';

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @param bool $order
     * @param null $cacheKey
     */
    public function __construct($order = false, $cacheKey = null)
    {
        $this->_order = $order;
        $this->_helper = new Ups();
        $this->_cache = new Cache();
        $this->filesystem = new Filesystem();
        if($cacheKey){
            $this->_cacheKey = $cacheKey;
        }
        $this->initData();
        $this->iniAdditionalData();
    }

    /**
     * init data
     */
    public function initData(){
        $data = $this->_helper->isPickingIntegrationData();

        $this->_tokenData = [
            'url' => $data['tokenUrl'],
            'username' => $data['tokenUsername'],
            'password' => $data['tokenPassword'],
            'scope' => $data['tokenScope'],
            'grant_type' => 'password'
        ];
    }

    /**
     *
     * Init Api Urls
     *
     * @return mixed
     */
    abstract public function iniAdditionalData();

    /**
     *
     * Get Access Token
     *
     * @param string $url
     * @return array
     */
    public function getAccessToken($url = ''){

        if($accessToken = $this->_cache->get($this->getCacheKey())){
            return ['error' => false, 'access_token' => $accessToken];
        }

        $data = $this->_tokenData;
        if(!$url){
            $url = $data['url'];
        }
        $url = str_replace('/Token','/token',$url);
        unset($data['url']);
        $headers = ['Content-Type: application/x-www-form-urlencoded'];
        $response = json_decode($this->sendRequest('POST', $url, $headers, $data));

        $error = false;
        if(isset($response->error)){
            $error = $response->error .' - '.$response->error_description;
        }
        $accessToken = isset($response->access_token) ? $response->access_token : null;

        if($accessToken !== null) {
            $this->_cache->save($this->getCacheKey(), $accessToken);
        }else{
            $error = 'the username or password is incorrect';
        }

        return ['error' => $error, 'access_token' => $accessToken];
    }

    /**
     *
     * Print Label
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

        $data = $this->preparePrintRequest($trackingNumbers, $format);

        if($this->_helper->isDebugModeEnabled()) {
            $this->filesystem->writeLog('printLabel request: ' . json_encode(array_merge($data, ['url' => $url])));
        }

        $response = $this->sendRequest('GET', $url, $headers, $data);

        $jsonResponse = json_decode($response);
        $pdfResponse = gettype($jsonResponse) === 'object' ? $jsonResponse->FileByteArray : $response;

        $error = false;

        if(isset($jsonResponse->Message)){
            $error = $jsonResponse->Message;

            $this->_cache->remove($this->getCacheKey());
        }

        $pdfContent = base64_decode($pdfResponse, true);
        if (!$error && strpos($pdfContent, '%PDF') !== 0) {
            $error = 'No Label Found';
        }

        if($this->_helper->isDebugModeEnabled() && $error) {
            $this->filesystem->writeLog('printLabel response: ' . json_encode($response));
        }

        return ['error' => $error, 'response' => $pdfContent];
    }

    /**
     *
     * Get Customer Type
     *
     * @param $accessToken
     * @return mixed
     *
     * @since 2.4.0
     */
    public function getCustomerType($accessToken){
        $url = $this->_helper->getOption('integration_api_url').'/api/v1/easyship/is-credit-customer';
        $headers = ['Authorization: Bearer '.$accessToken,'Content-Type: application/json'];


        $response = json_decode($this->sendRequest('GET', $url, $headers));

        if($this->_helper->isDebugModeEnabled()) {
            $this->filesystem->writeLog('getCustomerType response: ' . json_encode($response));
        }

        $customerType = $response->IsCreditDomestic === true ? Ups::CUSTOMER_TYPE_CREDIT : Ups::CUSTOMER_TYPE_CASH;

        return ['error' => false, 'response' => $customerType];
    }

    /**
     *
     * Prepare Print Request
     *
     * @param $trackingNumbers
     * @param $format
     * @return array
     */
    public function preparePrintRequest($trackingNumbers, $format){

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
    public function sendRequest($method, $url, $headers, $data = false)
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
        curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($curl, CURLOPT_ENCODING, 'UTF-8');
        $result = curl_exec($curl);

        if (is_admin() && ((!$this->isUrlTokenRelated($url) && $this->_helper->isApiDebugShowResponseEnabled()) || $this->_helper->isApiDebugShowResponseTokenEnabled())) {
            $errno = curl_errno($curl);
            $error = curl_error($curl);

            // Get the HTTP status code
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

            echo "Url: " . $url . "\n";
            echo "Data: ";
            print_r(isset($curlData) ? $curlData : $data);
            echo "\n";
            // Output the error information
            echo "cURL Error Number: " . $errno . "\n";
            echo "cURL Error: " . $error . "\n";
            echo "HTTP Status Code: " . $httpCode . "\n";

            echo "Response: "; print_r($result);

            curl_close($curl);
            exit;
        }

        curl_close($curl);

        return $result;
    }

    /**
     * Url is Token related
     * Token or Is Credit Customer
     *
     * @param $url
     * @return bool
     * @since 2.5.9
     */
    private function isUrlTokenRelated($url){
        return strpos(strtolower($url), 'token') !== false || strpos(strtolower($url), 'is-credit-customer') !== false;
    }

    /**
     *  Check UPS Rest Api Username & Password
     *
     * @since 1.10.5
     */
    public function checkUpsRestApiSettings(){
        $fieldsError = [];
        $fieldsMessages = [];
        $customerType = '';

        $accessTokenResponse = $this->getAccessToken();

        if($accessTokenResponse['error']){
            $fieldsError[] = 'integration_picking_username';
            $fieldsError[] = 'integration_picking_password';
            $fieldsError[] = 'integration_picking_scope';
        } else {
            $accessToken = $accessTokenResponse['access_token'];

            $customerTypeResponse = $this->getCustomerType($accessToken);
            $customerType = $customerTypeResponse['response'];

            $fieldsMessages = [
                ['key' => 'integration_picking_username', 'value' => '<br /> סוג לקוח: ' . $customerType]
            ];
        }

        update_option('pickups_integration_customer_type', $customerType);

        return ['errors' => $fieldsError, 'messages' => $fieldsMessages, 'customer_type' => $customerType];
    }

    /**
     * @return null
     * @since 2.1.0
     */
    public function getCacheKey(){
        return $this->_cacheKey;
    }
}
