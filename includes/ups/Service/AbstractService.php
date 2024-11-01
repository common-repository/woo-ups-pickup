<?php
/**
 * @category Ups
 * @copyright Ups
 */
namespace Ups\Service;

use Ups\Cache;
use Ups\Filesystem;
use Ups\Helper\Ups;

abstract class AbstractService
{
    const AUTH_COOKIE_NAME = '.ASPXFORMSAUTH';

    const CACHE_KEY = 'upsship_athenticate_cookie';

    protected $cache;

    protected $filesystem;

    protected $helper;

    public function __construct()
    {
        $this->cache = new Cache();
        $this->filesystem = new Filesystem();
        $this->helper = new Ups();
    }

    /**
     * @return string|null
     */
    public function login()
    {
        $authCookie = $this->cache->get(self::CACHE_KEY);

        if ($authCookie) {
            return $this->isLoggedIn($authCookie);
        }

        $wsdl = $this->helper->getServiceUrlByCode('authenticate');

        if (!$wsdl) {
            return false;
        }

        $serviceName = 'Login';
        $client = new \SoapClient($wsdl, [
            'trace' => 1,
            'features' => SOAP_SINGLE_ELEMENT_ARRAYS,
            'connection_timeout' => 10
        ]);
        $request = [
            'username' => $this->helper->getWsdlUsername(),
            'password' => $this->helper->getWsdlPassword()
        ];

        $log = 'Call authenticate: ' . json_encode($request);
        try {
            $response = $client->__soapCall($serviceName, [$request]);
            $log .= PHP_EOL . '> Response: ' . $client->__getLastResponse();
            if (isset($response->{$serviceName . 'Result'}) && $response->{$serviceName . 'Result'} == 'true') {
                $authCookie = $client->_cookies[self::AUTH_COOKIE_NAME][0];
                $this->cache->save(self::CACHE_KEY, $authCookie);
            }
        } catch(\Exception $e) {
            $log .= '> Error: '. $e->getMessage();
        }

        $this->filesystem->writeLog($log);

        return $authCookie;
    }

    public function isLoggedIn($authCookie)
    {
        $wsdl = $this->helper->getServiceUrlByCode('authenticate');

        if (!$wsdl) {
            return false;
        }

        $serviceName = 'IsLoggedIn';
        $client = new \SoapClient($wsdl, [
            'trace' => 1,
            'features' => SOAP_SINGLE_ELEMENT_ARRAYS,
            'connection_timeout' => 10
        ]);
        $client->__setCookie(self::AUTH_COOKIE_NAME, $authCookie);

        try {
            $response = $client->__soapCall($serviceName, []);
            if (isset($response->{$serviceName . 'Result'}) && $response->{$serviceName . 'Result'} == 'true') {
                return $authCookie;
            }

            $this->cache->remove(self::CACHE_KEY);

            return $this->login();
        } catch(\Exception $e) {
            return false;
        }
    }

    /**
     * executes the request, main entry point of the process
     */
    public function execute()
    {
        $result = [];
        $serviceName = $this->_getServiceName();
        $serviceUrl = $this->_getServiceUrl();

        if ($serviceUrl && $serviceName && $authCookie = $this->login()) {
            $request = $this->_prepareRequest();
            if (!$request) {
                throw new \Exception(__('Invalid request data'));
            }

            $client = new \SoapClient($serviceUrl, [
                'soap_version' => SOAP_1_1,
                'trace' => 1,
                'features' => SOAP_SINGLE_ELEMENT_ARRAYS,
                'connection_timeout' => 10
            ]);
            $client->__setCookie(self::AUTH_COOKIE_NAME, $authCookie);
            $log = sprintf('Call %s: %s', $serviceName, json_encode($request));
            try {
                $response = $client->__soapCall($serviceName, [$request]);
                $result['error'] = false;
                $result['response'] = $this->_readResponse($response, $client);

                $log .= PHP_EOL . sprintf('> Response: %s', $client->__getLastResponse());
            } catch(\Exception $e) {
                $result['error'] = true;
                $result['message'] = $e->getMessage();

                if (!empty($response)) {
                    $errorCode = $response->{$this->_getServiceName() . 'Result'}->LastError->ErrorCode;
                    // expired authenticate
                    if ($errorCode == 10) {
                        $this->cache->remove(self::CACHE_KEY);
                    }
                }
                $log .= PHP_EOL . sprintf('> Error: %s', $e->getMessage());
            }

            $this->filesystem->writeLog($log);
        }

        return $result;
    }

    /**
     * @return string
     */
    protected function _getServiceUrl()
    {
        return '';
    }

    /**
     * @return string
     */
    protected function _getServiceName()
    {
        return '';
    }

    /**
     * @param array $response
     * @param \SoapClient $client
     * @return mixed
     */
    abstract protected function _readResponse($response, $client);

    /**
     * @return array
     */
    abstract protected function _prepareRequest();
}