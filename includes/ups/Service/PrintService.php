<?php
namespace Ups\Service;

/**
 * Class PickingListService
 * @package Ups\Service
 *
 * @since 2.1.0
 */
class PrintService extends AbstractRestApiService
{

    const TYPE_A4 = 'A4';

    const TYPE_THERMAL = 'Thermal';

    /**
     * Init Api urls
     */
    public function iniAdditionalData(){
        $apiUrl = $this->_helper->getOption('integration_picking_api_url');
        $this->_apiUrls = [
            'apiPrintUrl' => $apiUrl.'/api/v1/shipments/PrintWBOrderDetails'
        ];
    }

    public function preparePrintRequest($trackingNumbers, $format){
        $isA4Format = $format === 'A4' ? 'True' : 'False';
        return ['trackingNumbers' => $trackingNumbers, 'isA4Format' => $isA4Format, 'printPickingList' => 'false'];
    }
}
