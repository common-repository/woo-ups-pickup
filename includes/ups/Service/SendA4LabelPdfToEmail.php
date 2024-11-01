<?php
/**
 * @category Ups
 * @copyright Ups
 */
namespace Ups\Service;

class SendA4LabelPdfToEmail extends AbstractService
{
    protected $_rma;

    /**
     * @inheritdoc
     */
    public function _getServiceName()
    {
        return 'SendA4LabelPdfToEmail';
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

        $data = [
            'criteria' => [
                'CustomerNumber' => 0,
                'TrackNumber' => $rma->getData('tracking_number'),
                'Email' => $rma->getData('ups_mail_to'),
            ]
        ];

        return $data;
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
        if (!$response->SendA4LabelPdfToEmailResult->IsSucceeded) {
            throw new \Exception($response->SendA4LabelPdfToEmailResult->LastError->OriginalMessage);
        }

        return true;
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