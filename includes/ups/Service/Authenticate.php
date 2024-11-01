<?php
/**
 * @category UPS
 * @copyright UPS Company
 */
namespace Ups\Service;

class Authenticate extends AbstractService
{
    protected function _getServiceName()
    {
        return 'Login';
    }

    public function _prepareRequest()
    {
        return array(
            'username' => $this->helper->getWsdlUsername(),
            'password' => $this->helper->getWsdlPassword()
        );
    }

    /**
     * @inheritdoc
     */
    public function _readResponse($response, $client)
    {
        $serviceName = $this->_getServiceName();
        $authCookie = '';
        if (isset($response->{$serviceName . 'Result'}) && $response->{$serviceName . 'Result'} == 'true') {
            $authCookie = $client->_cookies[self::AUTH_COOKIE_NAME][0];
        }

        return $authCookie;
    }
}