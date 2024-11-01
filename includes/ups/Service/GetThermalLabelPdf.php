<?php
/**
 * @category Ups
 * @copyright Ups
 */
namespace Ups\Rma\Model\Service;

class GetThermalLabelPdf extends AbstractService
{
    protected $_rma;

    /**
     * @inheritdoc
     */
    public function _getServiceName()
    {
        return 'GetThermalLabelPdf';
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

        $data = ['trackingNumber' => $rma->getData('tracking_number')];

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
        if (!$response->GetThermalLabelPdfResult->IsSucceeded) {
            throw new \Exception($response->GetThermalLabelPdfResult->LastError->OriginalMessage);
        }

        return base64_encode($response->GetThermalLabelPdfResult->File);
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