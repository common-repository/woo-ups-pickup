<?php
/**
 * @category UPS
 * @copyright UPS Company
 */
namespace Ups\Order;

use DOMDocument;
use Ups\Filesystem;
use Ups\Helper\Ups;
use Ups\Service\CreateWaybillService;
use Ups\Service\CreateXmlOrderService;
use Ups\Service\GetInformationService;
use Ups\Service\PickingListService;
use Ups\Service\PrintService;
use Ups\Service\ClosestPoints;
use WC_Shipping_Ups_PickUps_CPT;

class Api
{
    const STATUS_SEND_SUCCESS = 1;

    const STATUS_SEND_ERROR = 2;

    /**
     * @var Ups
     */
    protected $helper;

    /**
     * @var Filesystem
     */
    protected $filesystem;

    public function __construct()
    {
        $this->helper = new Ups();
        $this->filesystem = new Filesystem();
    }

    /**
     * @param \WC_Order $order
     * @return bool
     */
    public function sendOrder($order)
    {
        $isPickupUps = false;
        $error = true;
        $shippingMethods = $order->get_shipping_methods();
        foreach ($shippingMethods as $shippingMethod) {
            if ($this->helper->isPickupUps($shippingMethod->get_method_id())) {
                $isPickupUps = true;
                break;
            }
        }

        $service = new CreateWaybillService($order, 'api_create_url_token');

        try {
            $result = $service->createWaybill($isPickupUps);

            if ($result['error']) {
                throw new \Exception($result['error']);
            }

            if($trackingNumber = $result['tracking_number']) {
                $this->saveWaybillOnOrder($order, $trackingNumber);
            }elseif($leadId = $result['lead_id']){
                $order->delete_meta_data('ups_error_message');
                $order->add_meta_data('ups_ship_number_lead_id', $leadId);
                $order->add_order_note(sprintf('Create new lead successful, lead id: %s', $leadId));
            }
            $error = false;
        } catch (\Exception $e) {
            $message = sprintf('Sync to UPS error, message: %s', $e->getMessage());
            $this->saveErrorWaybillOnOrder($order, $message);
        }
        $order->save_meta_data();

        return !$error;
    }

    /**
     * @param array $orderIds
     */
    public function sendOrders($orderIds)
    {
        $filesytem = new Filesystem();
        $sessions = $filesytem->readSession('ups');
        $errors = !empty($sessions['errors']) ? $sessions['errors'] : array();
        $successes = !empty($sessions['successes']) ? $sessions['successes'] : array();
        foreach ($orderIds as $orderId) {
            $order = wc_get_order($orderId);

            if (!$order->get_id()) {
                $errors[] = sprintf(__('Invalid order id %s', \WC_Ups_PickUps::TEXT_DOMAIN), $orderId);
                continue;
            }

            if ($order->get_meta('ups_sync_flag') == self::STATUS_SEND_SUCCESS) {
                $errors[] = sprintf(__('Order #%s already send to UPS', \WC_Ups_PickUps::TEXT_DOMAIN), $orderId);
                continue;
            }

            if ($order->get_meta('ups_ship_number_lead_id')) {
                $errors[] = sprintf(__('Lead already created for Order #%s', \WC_Ups_PickUps::TEXT_DOMAIN), $orderId);
                continue;
            }

            if (!$this->helper->isAllowedStatus($order->get_status())) {
                $errors[] = sprintf(__('Can not send order #%s with status %s', \WC_Ups_PickUps::TEXT_DOMAIN), $orderId, wc_get_order_status_name($order->get_status()));
                continue;
            }

            $result = $this->sendOrder($order);
            if ($result) {
                $successes[] = sprintf(__('order #%s was sent successfully', \WC_Ups_PickUps::TEXT_DOMAIN), $orderId);
            } else {
                $errors[] = sprintf(__('Can not send order #%s into UPS', \WC_Ups_PickUps::TEXT_DOMAIN), $orderId);
            }
        }

        $sessions['errors'] = $errors;
        $sessions['successes'] = $successes;
        $filesytem->writeSession($sessions, 'ups');
    }

    /**
     * @param \WC_Order $order
     * @return bool
     *
     * @since 2.0.0
     */
    public function importWaybill($order)
    {
        $error = true;

        $service = new CreateWaybillService($order, 'api_create_url_token');

        $leadId = $this->helper->getOrderLeadId($order);

        try {
            $result = $service->importWaybillFromLeadId($leadId);

            if ($result['error']) {
                throw new \Exception($result['error']);
            }

            if($trackingNumber = $result['tracking_number']) {
                $this->saveWaybillOnOrder($order, $trackingNumber);
                $error = false;
            }
        } catch (\Exception $e) {
            $message = sprintf('Import waybill error, message: %s', $e->getMessage());

            $this->saveErrorWaybillOnOrder($order, $message);
        }
        $order->save_meta_data();

        return !$error;
    }

    /**
     * @param array $orderIds
     *
     * @since 2.0.0
     */
    public function importWaybills($orderIds)
    {
        $filesytem = new Filesystem();
        $sessions = $filesytem->readSession('ups');
        $errors = !empty($sessions['errors']) ? $sessions['errors'] : array();
        $successes = !empty($sessions['successes']) ? $sessions['successes'] : array();
        foreach ($orderIds as $orderId) {
            $order = wc_get_order($orderId);
            if (!$order->get_id()) {
                $errors[] = sprintf(__('Invalid order id %s', \WC_Ups_PickUps::TEXT_DOMAIN), $orderId);
                continue;
            }

            if ($order->get_meta('ups_sync_flag') == self::STATUS_SEND_SUCCESS) {
                $errors[] = sprintf(__('Order #%s already sent to UPS', \WC_Ups_PickUps::TEXT_DOMAIN), $orderId);
                continue;
            }

            if (!$this->helper->isAllowedStatus($order->get_status())) {
                $errors[] = sprintf(__('Can not import waybill for order #%s with status %s', \WC_Ups_PickUps::TEXT_DOMAIN), $orderId, wc_get_order_status_name($order->get_status()));
                continue;
            }

            $result = $this->importWaybill($order);
            if ($result) {
                $successes[] = sprintf(__('waybill for order #%s was imported successfully', \WC_Ups_PickUps::TEXT_DOMAIN), $orderId);
            } else {
                $errors[] = sprintf(__('Can not import waybill for order #%s', \WC_Ups_PickUps::TEXT_DOMAIN), $orderId);
            }
        }

        $sessions['errors'] = $errors;
        $sessions['successes'] = $successes;
        $filesytem->writeSession($sessions, 'ups');
    }

    /**
     * Send Order to Ups (Create Picking List)
     * @param $order
     * @return bool
     * @since 1.8.0
     */
    public function createPickingList($order)
    {
        $error = true;
        $service = new PickingListService($order);

        try {
            $result = $service->createPickingList();

            if ($result['error']) {
                throw new \Exception($result['error']);
            }

            $order->add_meta_data('ups_sync_picking_flag', self::STATUS_SEND_SUCCESS, true);
            $order->delete_meta_data('ups_picking_error_message');
            $order->add_order_note(sprintf('Create Picking List successful'));

            $error = false;
        } catch (\Exception $e) {
            $message = sprintf('Create Picking List error, message: %s', $e->getMessage());
            $order->add_meta_data('ups_sync_picking_flag', self::STATUS_SEND_ERROR, true);
            $order->add_meta_data('ups_picking_error_message', $message, true);
            $order->add_order_note($message);
        }
        $order->save_meta_data();

        return !$error;
    }

    /**
     * Send Orders to Ups (Create Picking List)
     * @param array $orderIds
     *
     * @since 1.8.0
     */
    public function sendPickingOrders($orderIds)
    {
        $filesytem = new Filesystem();
        $sessions = $filesytem->readSession('ups');
        $errors = !empty($sessions['errors']) ? $sessions['errors'] : array();
        $successes = !empty($sessions['successes']) ? $sessions['successes'] : array();
        foreach ($orderIds as $orderId) {
            $order = wc_get_order($orderId);
            if (!$order->get_id()) {
                $errors[] = sprintf(__('Invalid order id %s', \WC_Ups_PickUps::TEXT_DOMAIN), $orderId);
                continue;
            }

            if (!$order->get_meta('ups_ship_number')) {
                $errors[] = sprintf(__('Can not send order #%s without tracking number', \WC_Ups_PickUps::TEXT_DOMAIN), $orderId);
                continue;
            }

            if ($order->get_meta('ups_sync_picking_flag') == self::STATUS_SEND_SUCCESS) {
                $errors[] = sprintf(__('a Picking List already created for Order #%s', \WC_Ups_PickUps::TEXT_DOMAIN), $orderId);
                continue;
            }

            $result = $this->createPickingList($order);

            if ($result) {
                $successes[] = sprintf(__('Picking List for order #%s was create successfully', \WC_Ups_PickUps::TEXT_DOMAIN), $orderId);
            } else {
                $errors[] = sprintf(__('Can not create picking list for order: #%s', \WC_Ups_PickUps::TEXT_DOMAIN), $orderId);
            }
        }

        $sessions['errors'] = $errors;
        $sessions['successes'] = $successes;
        $filesytem->writeSession($sessions, 'ups');
    }

    /**
     * Print Picking Label
     * @param array $orderIds
     *
     * @param $format
     * @return bool|void
     * @since 1.8.0
     */
    public function printPickingLabel($orderIds, $format)
    {
        $service = new PickingListService();

        $trackingNumbers = [];
        $ordersNeedToBeChangedArray = [];
        foreach ($orderIds as $orderId) {
            $order = wc_get_order($orderId);

            if ($order->get_meta('ups_sync_flag') == self::STATUS_SEND_SUCCESS) {
                $ordersNeedToBeChangedArray[] = $order;
            }

            if ($trackingNumber = $order->get_meta('ups_ship_number')) {
                $trackingNumbers[] = $trackingNumber;
            }
        }

        $trackingNumbers = implode(',', $trackingNumbers);

        try {
            $result = $service->printLabel($trackingNumbers, $format);

            if ($result['error']) {
                throw new \Exception($result['error']);
            }

            $this->changeOrderStatus($ordersNeedToBeChangedArray);

            $output = $result['response'];

        } catch (\Exception $e) {
            $output = false;
        }


        if ($output) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="Picking_List_labels.pdf"');
            header('Content-Transfer-Encoding: binary');
            header('Accept-Ranges: bytes');
            header('Pragma: public');
            ob_clean();
            flush();
            echo $output;

            return;
        }

        return _e('Can not print picking label', \WC_Ups_PickUps::TEXT_DOMAIN);
    }

    public function printLabels($orderIds, $format = null)
    {
        $service = new PrintService();

        $ordersNeedToBeChangedArray = [];
        $trackingNumbers = [];
        $fileName = 'Label_';
        foreach ($orderIds as $orderId) {
            $order = wc_get_order($orderId);

            if ($order->get_meta('ups_sync_flag') == self::STATUS_SEND_SUCCESS) {
                $ordersNeedToBeChangedArray[] = $order;
            }

            if ($trackingNumber = $order->get_meta('ups_ship_number')) {
                $trackingNumbers[] = $trackingNumber;
            }
            $fileName .= $orderId.'_';
        }

        $fileName .= $format.'.pdf';

        $trackingNumbers = implode(',', $trackingNumbers);
        $errorMessage = 'Can not print label';

        try {
            $result = $service->printLabel($trackingNumbers, $format);

            if ($result['error']) {
                throw new \Exception($result['error']);
            }

            $this->changeOrderStatus($ordersNeedToBeChangedArray);

            $output = $result['response'];

        } catch (\Exception $e) {
            $output = false;
            $errorMessage = $e->getMessage();
        }

        if ($output) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="'.$fileName.'"');
            header('Content-Transfer-Encoding: binary');
            header('Accept-Ranges: bytes');
            header('Pragma: public');
            ob_clean();
            flush();
            echo $output;

            return;
        }

        return _e($errorMessage, \WC_Ups_PickUps::TEXT_DOMAIN);
    }

    /**
     *
     * Change Pickup Point For Order
     *
     * @since 1.6.0
     * @param $orderIds
     */
    public function changePickupPoint($orderIds)
    {
        $filesystem = new Filesystem();
        $sessions = $filesystem->readSession('ups');
        $errors = !empty($sessions['errors']) ? $sessions['errors'] : array();
        $successes = !empty($sessions['successes']) ? $sessions['successes'] : array();

        $pkpsField = WC_Shipping_Ups_PickUps_CPT::PICKUP_ORDER_METADATA_FIELD;

        foreach ($orderIds as $orderData) {
            $orderId = $orderData['orderId'];
            $order = wc_get_order($orderId);

            if($this->helper->isShippingMethodIsPickupUps($order) === false){
                $errors[] = sprintf(__('Shipping method is not pick ups', \WC_Ups_PickUps::TEXT_DOMAIN), $orderId);
                continue;
            }

            if (!$order->get_id()) {
                $errors[] = sprintf(__('Invalid order id %s', \WC_Ups_PickUps::TEXT_DOMAIN), $orderId);
                continue;
            }

            if ($order->get_meta('ups_sync_flag') == self::STATUS_SEND_SUCCESS) {
                $errors[] = sprintf(__('Order #%s already sent to UPS', \WC_Ups_PickUps::TEXT_DOMAIN), $orderId);
                continue;
            }

            $order->update_meta_data($pkpsField, $orderData[$pkpsField]);
            $order->save();

            $successes[] = sprintf(__('Pickup point changed for order #%s', \WC_Ups_PickUps::TEXT_DOMAIN), $orderId);
        }

        $sessions['errors'] = $errors;
        $sessions['successes'] = $successes;
        $filesystem->writeSession($sessions, 'ups');
    }

    /**
     * @param array $orderIds
     */
    public function sendOrdersAsXmlToFtp($orderIds)
    {
        $sessions = $this->filesystem->readSession('ups');
        $errors = !empty($sessions['errors']) ? $sessions['errors'] : array();
        $successes = !empty($sessions['successes']) ? $sessions['successes'] : array();
        foreach ($orderIds as $orderId) {
            $order = wc_get_order($orderId);
            if (!$order->get_id()) {
                $errors[] = sprintf(__('Invalid order id %s', \WC_Ups_PickUps::TEXT_DOMAIN), $orderId);
                continue;
            }

            if ($order->get_meta('ups_xml_sent') == self::STATUS_SEND_SUCCESS) {
                $errors[] = sprintf(__('XML for Order #%s already sent', \WC_Ups_PickUps::TEXT_DOMAIN), $orderId);
                continue;
            }

            $result = $this->sendOrderAsXmlToFtp($order);
            if ($result) {
                $order->update_meta_data('ups_xml_sent', self::STATUS_SEND_SUCCESS);
                $successes[] = sprintf(__('XML for order #%s was sent successfully', \WC_Ups_PickUps::TEXT_DOMAIN), $orderId);
            } else {
                $errors[] = sprintf(__('Can not send XML for order #%s', \WC_Ups_PickUps::TEXT_DOMAIN), $orderId);
                $order->update_meta_data('ups_xml_sent', self::STATUS_SEND_ERROR);
            }
        }

        $sessions['errors'] = $errors;
        $sessions['successes'] = $successes;
        $this->filesystem->writeSession($sessions, 'ups');
    }

    /**
     * Send Order as XML to FTP
     *
     * @param \WC_Order $order
     * @return bool
     * @since 1.10.0
     */
    public function sendOrderAsXmlToFtp($order){
        if(!$this->helper->isSaveOrderAsXmlEnabled()){
            return false;
        }

        $xmlData = $this->createXmlOrderDocument($order);

        if($xmlData['errors']){
            return false;
        }

        $xmlFile = $xmlData['filename'];

        $error = true;
        $service = new CreateXmlOrderService($order, 'api_create_url_token');

        try {
            $result = $service->sendFileViaPluginsApi($xmlFile);

            if ($result['error']) {
                throw new \Exception($result['error']);
            }

            if($this->helper->isDebugModeEnabled()) {
                $this->filesystem->writeLog('sendOrderAsXmlToFtp: File sent successfully');
            }

            $error = false;
        } catch (\Exception $e) {
            $order->update_meta_data('ups_xml_sent_errors', $e->getMessage());
            $this->filesystem->writeLog('sendOrderAsXmlToFtp Error: '.$e->getMessage());
        }

        return !$error;
    }

    /**
     *
     * Create XML Order Document
     *
     * @param \WC_Order $order
     * @return array
     * @since 1.10.0
     */
    private function createXmlOrderDocument($order){
        $errors = '';
        if(!$orderId = $order->get_id()){
            $errors .= 'order id not found, ';
        }
        foreach ( $order->get_shipping_methods() as $shipping_item ) {
            $shippingMethodId = $shipping_item->get_method_id() === 'local_pickup' ? 1 : 2;
        }
        $pickupPointId = '';
        $pickupPointData = $this->helper->getOrderPickupPointJson($order);
        if($pickupPointData){
            $pickupPointId = $pickupPointData->iid;

            switch ($pickupPointData->type){
                case 'store':
                    $shippingMethodId = 9;
                    break;
                case 'locker':
                    $shippingMethodId = 10;
                    break;
            }
        }

        $orderCustomerName = $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name();
        if(!trim($orderCustomerName)){
            $errors .= 'customer name not found, ';
        }
        if($shippingMethodId === 1){
            $warehouseAddress = $this->helper->getPickupWarehouseAddress();
            if (!$orderShippingAddress = $warehouseAddress['street']) {
                $errors .= 'warehouse address not found, ';
            }
            if (!$orderCustomerCity = $warehouseAddress['city']) {
                $errors .= 'warehouse city not found, ';
            }
        } else {
            if (!$orderShippingAddress = $order->get_shipping_address_1()) {
                $errors .= 'shipping address not found, ';
            }
            if (!$orderCustomerCity = $order->get_shipping_city()) {
                $errors .= 'city shipping address not found, ';
            }
        }
        if(!$customerHouseNumber = $this->helper->getHouseNumber($orderShippingAddress)){
            $customerHouseNumber = 0;
        }
        if(!$orderCustomerPhone = $order->get_billing_phone()){
            $errors .= 'customer phone not found, ';
        }elseif($pickupPointData && !preg_match('/^0(5[^7])[0-9]{7}$/', $orderCustomerPhone)){
            $errors .= 'customer phone not valid, must be 05X-XXXXXXX, ';
        }
        if(!$customerScope = $this->helper->getUpsCustomerScope()){
            $errors .= 'ups customer id not found, ';
        }
        $customerEmail = $order->get_billing_email();
        $user = get_user_by('email', $customerEmail);

        $userId = null;
        if(isset($user)){
            $userId = $user->ID;
        }

        if(!$userId){
            $userId = 'T1';
        }

        $dom = new DOMDocument('1.0', 'utf-8');
        $dom->formatOutput = true;
        $wpUploadsFolder = $this->helper->getUpsOrderUploadsFolder();
        $filenameDate = date('dmyhi');
        $xmlFileName = $wpUploadsFolder.'SO'.$orderId.$filenameDate.'.xml';

        $xmlRoot = $dom->createElement('Documents');
        $xmlDocument = $dom->createElement('Document');

        if($this->helper->isDebugModeEnabled()) {
            $this->filesystem->writeLog('createXmlOrderDocument: before creating $xmlDocumentDataArray');
        }

        $xmlDocumentDataArray = ['SO' => $orderId,
            'SOS' => $orderId,
            'InpDt' => date('d/m/Y'),
            'ExDelDt' => date('d/m/Y', strtotime('+1 day')),
            'OrdType' => $this->helper->getXmlOrderTypeOption($order),
            'ConCod' => $userId,
            'ConName' => $orderCustomerName,
            'ConStreet' => $orderShippingAddress,
            'ConZip' => $order->get_shipping_postcode(),
            'ConHousNum' => $customerHouseNumber,
            'ConFloorNum' => '',
            'ConRoomNum' => '',
            'ConCity' => $orderCustomerCity,
            'ConTel' => $orderCustomerPhone,
            'ConContName' => $orderCustomerName,
            'ConEmail' => $customerEmail,
            'Swap' => 0,
            'SWAPNOP' => '',
            'SI' => '',
            'DelvIns' => $this->helper->getShipmentInstructions($order),
            'COD' => 0,
            'CodDet' => '',
            'CodVal' => '',
            'SCN' => $customerScope,
            'UDR' => 0,
            'DISPMETH' => $shippingMethodId,
            'PickUPStation' => $pickupPointId];
        foreach($xmlDocumentDataArray as $key => $value){
            $xmlDocumentNewElement = $dom->createElement($key, $value);
            $xmlDocument->appendChild($xmlDocumentNewElement);
        }

        $xmlDocumentLines = $dom->createElement('Document_Lines');

        if($this->helper->isDebugModeEnabled()) {
            $this->filesystem->writeLog('createXmlOrderDocument: after creating $xmlDocumentDataArray');
            $this->filesystem->writeLog('createXmlOrderDocument: before creating $xmlDocumentDataRowArray');
            $this->filesystem->writeLog('createXmlOrderDocument: total items count: ' . count($order->get_items()));
        }

        $i = 0;
        foreach ( $order->get_items() as $orderItem ) {
            $productId = $orderItem->get_product()->get_id();
            $currentProduct = wc_get_product($productId);
            if(!$productSku = $currentProduct->get_sku()){
                $errors .= 'missing SKU for product: '.$productId.', ';
            }

            if($this->helper->isDebugModeEnabled()) {
                $this->filesystem->writeLog('createXmlOrderDocument: product data, productId: ' . $productId . ', productSku: ' . $productSku);
            }
            $xmlDocumentDataRowArray = ['SKU' => $productSku,
                'LineNumber' => $i,
                'Qty' => $orderItem->get_quantity()];

            $xmlDocumentLinesRow = $dom->createElement('row');

            foreach($xmlDocumentDataRowArray as $key => $value){
                $xmlDocumentNewElement = $dom->createElement($key, $value);
                $xmlDocumentLinesRow->appendChild($xmlDocumentNewElement);
            }

            $xmlDocumentLines->appendChild($xmlDocumentLinesRow);

            ++$i;
        }

        if($errors){
            $order->update_meta_data('ups_xml_sent_errors', substr($errors, 0, -2));
            return ['errors' => true];
        }

        if($this->helper->isDebugModeEnabled()) {
            $this->filesystem->writeLog('createXmlOrderDocument: after creating $xmlDocumentDataRowArray');
        }

        $xmlRoot->appendChild($xmlDocument);
        $xmlRoot->appendChild($xmlDocumentLines);

        $dom->appendChild($xmlRoot);

        $dom->save($xmlFileName, LIBXML_NOEMPTYTAG);

        if($this->helper->isDebugModeEnabled()) {
            $this->filesystem->writeLog('createXmlOrderDocument: XML Create Successfully');
        }

        return ['filename' => $xmlFileName];
    }

    private function changeOrderStatus($orders){
        $changeOrderStatus = $this->helper->getOption('integration_change_order_status');

        if($changeOrderStatus) {
            foreach($orders as $order) {
                if(!$order->get_meta('ups_ship_status_changed')) {
                    $order->add_meta_data('ups_ship_status_changed', 'Status changed to '.$changeOrderStatus);
                    $order->update_status($changeOrderStatus);
                }
            }
        }
    }

    public function getClosestPoints($customerAddress, $settings){
        $service = new ClosestPoints(false, 'api_print_url_token');

        try {
            $result = $service->getClosestPoints($customerAddress, $settings);

            if (isset($result['error'])) {
                throw new \Exception($result['error']);
            }

            return $result;

        } catch (\Exception $e) {
            $this->filesystem->writeLog('getClosestPoints Api Error: '.$e->getMessage());
        }
    }

    private function saveWaybillOnOrder($order, $trackingNumber, $type = null){
        $order->add_meta_data('ups_sync_flag', self::STATUS_SEND_SUCCESS, true);
        $order->add_meta_data('ups_ship_number', $trackingNumber);
        $order->add_meta_data('ups_ship_number_timestamp', time());

        if($type === 'import-from-lead'){
            $note = 'Import waybill success, shipment number: %s';
        }else {
            $note = 'Sync to UPS successful, shipment number: %s';
        }

        $order->add_order_note(sprintf($note, $trackingNumber));
    }

    private function saveErrorWaybillOnOrder($order, $message){
        $order->add_meta_data('ups_sync_flag', Api::STATUS_SEND_ERROR, true);
        $order->add_meta_data('ups_error_message', $message, true);
        $order->add_order_note($message);
    }

    /**
     * @param $orderIds
     *
     * @since 2.4.0
     */
    public function getWaybillStatus($orderIds)
    {
        $filesytem = new Filesystem();
        $sessions = $filesytem->readSession('ups');
        $errors = !empty($sessions['errors']) ? $sessions['errors'] : array();
        $successes = !empty($sessions['successes']) ? $sessions['successes'] : array();

        foreach ($orderIds as $orderId) {
            $order = wc_get_order($orderId);
            if (!$order->get_id()) {
                $errors[] = sprintf(__('Invalid order id %s', \WC_Ups_PickUps::TEXT_DOMAIN), $orderId);
                continue;
            }

            if ($order->get_meta('ups_sync_flag') != self::STATUS_SEND_SUCCESS) {
                $errors[] = sprintf(__('Waybill is not available for Order #%s', \WC_Ups_PickUps::TEXT_DOMAIN), $orderId);
                continue;
            }

            if(!$this->helper->isGetWBStatusEnabled($order)){
                $errors[] = sprintf(__('The Status for order #%s already update to his last stage', \WC_Ups_PickUps::TEXT_DOMAIN), $orderId);
                continue;
            }

            if ($this->helper->isImportWBLimitExceeded()) {
                $errors[] = sprintf(__('You have reached the import way bill status limit, please wait a minute and try again', \WC_Ups_PickUps::TEXT_DOMAIN));
                break;
            }

            $result = $this->importWaybillStatus($order);
            if ($result) {
                $successes[] = sprintf(__('waybill status for order #%s was imported successfully', \WC_Ups_PickUps::TEXT_DOMAIN), $orderId);
            } else {
                $errors[] = sprintf(__('Can not get waybill status for order #%s', \WC_Ups_PickUps::TEXT_DOMAIN), $orderId);
            }
        }
        $sessions['errors'] = $errors;
        $sessions['successes'] = $successes;
        $filesytem->writeSession($sessions, 'ups');
    }

    /**
     * @param $order
     * @return bool
     *
     * @since 2.4.0
     */
    public function importWaybillStatus($order)
    {
        $error = true;

        $service = new GetInformationService($order, 'api_print_url_token');

        try {
            $trackingNumber = $order->get_meta('ups_ship_number');
            $result = $service->importWaybillStatus($trackingNumber);

            if ($result['error']) {
                throw new \Exception($result['error']);
            }

            if($wbStatus = $result['wb_status']) {
                $order->add_meta_data('ups_wb_status', $wbStatus, true);
                $order->add_meta_data('ups_wb_status_timestamp', $this->helper->getIsraelTime(), true);
                $order->add_order_note(sprintf('Import waybill status success, status: %s', $wbStatus));
                $error = false;
            }
        } catch (\Exception $e) {
        }
        $order->save_meta_data();

        return !$error;
    }
}
