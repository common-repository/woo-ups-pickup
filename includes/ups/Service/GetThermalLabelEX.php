<?php
/**
 * @category UPS
 * @copyright UPS Company
 */
namespace Ups\Service;

class GetThermalLabelEX extends AbstractService
{
    const TYPE_A4 = 'A4';

    const TYPE_THERMAL = 'Thermal';

    protected $labelFormat;

    protected $trackingNumbers = array();

    protected function _getServiceName()
    {
        return 'GetThermalLabelEX'; // TODO: Change the autogenerated stub
    }

    protected function _getServiceUrl()
    {
        return $this->helper->getServiceUrlByCode('ship_wb');
    }

    protected function _prepareRequest()
    {
        $trackingNumbers = $this->getTrackingNumbers();
        if (!$trackingNumbers) {
            return [];
        }

        $data = [
            'criteria' => [
//                'TrackingNumber' => $this->getTrackingNumber(),
                'Copies' => 1,
                'AutoPrint' => 1,
                'Type' => 'Hebrew',
                'LabelFormat' => $this->getLabelFormat(),
                'TrackingNumbers' => $trackingNumbers
            ]
        ];

        return $data;
    }

    protected function _readResponse($response, $client)
    {
        if (!$response->GetThermalLabelEXResult->IsSucceeded) {
            throw new \Exception($response->GetThermalLabelEXResult->LastError->OriginalMessage);
        }

        return $response->GetThermalLabelEXResult->File;
    }

    /**
     * @param int $format
     * @return GetThermalLabelEX
     */
    public function setLabelFormat($format)
    {
        if (in_array($format, array(self::TYPE_A4, self::TYPE_THERMAL))) {
            $this->labelFormat = $format;
        } else {
            // default is Thermal
            $this->labelFormat = self::TYPE_THERMAL;
        }

        return $this;
    }

    /**
     * @return int
     */
    public function getLabelFormat()
    {
        switch ($this->labelFormat) {
            case self::TYPE_A4:
                $format = self::TYPE_A4;
                break;

            case self::TYPE_THERMAL:
            default:
                $format = self::TYPE_THERMAL;
                break;
        }

        return $format;
    }

    /**
     * @param string $trackingNumber
     * @return $this
     */
    public function addTrackingNumber($trackingNumber)
    {
        $trackingNumber = trim($trackingNumber);

        if ($trackingNumber) {
            $this->trackingNumbers[] = $trackingNumber;
        }

        return $this;
    }

    /**
     * @return array
     */
    public function getTrackingNumbers()
    {
        return $this->trackingNumbers;
    }
}