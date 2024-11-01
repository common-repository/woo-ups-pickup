<?php
/**
 * @category Ups
 * @copyright Ups
 */
namespace Ups\Helper;

use Ups\Order\Api;
use Ups\Filesystem;
use Ups\Service\CreateWaybillService;
use WC_Ups_PickUps;

class Ups
{
    const XPATH_LIVE_MODE = 'upsship/setting/live_mode';

    const XPATH_DOMAIN_LIVE = 'upsship/setting/domain_live';

    const XPATH_DOMAIN_TEST = 'upsship/setting/domain_test';

    const XPATH_WSDL_USERNAME = 'upsship/setting/username';

    const XPATH_WSDL_PASSWORD = 'upsship/setting/password';

    const LIVE_DOMAIN = 'https://www.ship.co.il';

    const TEST_DOMAIN = 'https://.beta.ship.co.il';

    const TRACKING_URL = 'https://site.ship.co.il/?trackNumber=';

    const CUSTOMER_TYPE_CREDIT = 'אשראי';
    const CUSTOMER_TYPE_CASH = 'מזומן';

    protected static $instance;

    protected $options = array();

    private $importWbLimitCount = 0;

    /**
     * @var Filesystem
     */
    protected $filesystem;

    public function __construct()
    {
        $this->options = get_option('woocommerce_woo-ups-pickups_settings');
        $this->filesystem = new Filesystem();
    }

    /**
     * @return int
     */
    public function isLiveMode()
    {
        return !$this->getOption('api_testmode', 1);
    }

    /**
     * @return string
     */
    public function getServiceDomain()
    {
        if ($this->isLiveMode()) {
            return self::LIVE_DOMAIN;
        } else {
            return self::TEST_DOMAIN;
        }
    }

    /**
     * @return string
     */
    public function getWsdlUsername()
    {
        return $this->getOption('api_username');
    }

    /**
     * @return string
     */
    public function getWsdlPassword()
    {
        return $this->getOption('api_password');
    }

    /**
     * @param string $methodId
     * @return bool
     */
    public function isPickupUps($methodId)
    {
        $pickupsId = 'woo-ups-pickups';
        $len = strlen($pickupsId);

        return $methodId === $pickupsId || (substr($methodId, 0, $len) === $pickupsId);
    }

    /**
     * Is Shipping Method is Pick Ups
     *
     * @param $order
     * @return bool
     */
    public function isShippingMethodIsPickupUps($order){
        $shippingMethods = $order->get_shipping_methods();
        foreach ($shippingMethods as $shippingMethod) {
            if ($this->isPickupUps($shippingMethod->get_method_id())) {
                return true;
            }
        }
        return false;
    }

    /**
     * Is Shipping Method is Pick Ups (include closest points)
     *
     * @param $order
     * @return bool
     *
     * @since 2.5.0
     */
    public function isShippingMethodIsPickupUpsInclClosestPoints($order){
        $shippingMethods = $order->get_shipping_methods();
        foreach ($shippingMethods as $shippingMethod) {
             if (strpos($shippingMethod->get_method_id(), WC_Ups_PickUps::METHOD_ID) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return bool
     */
    public function isAutomaticMode()
    {
        return $this->getOption('integration_mode') === 'automatic';
    }

    /**
     * @return mixed|null
     */
    public function getAllowedStatuses()
    {
        return $this->getOption('api_statuses');
    }

    public function isAllowedStatus($status)
    {
        $allowedStatuses = $this->getAllowedStatuses();
        $status = 'wc-'. $status;

        return !$allowedStatuses || ($allowedStatuses && in_array($status, $allowedStatuses));
    }

    /**
     * @param string $key
     * @param null $default
     * @return mixed|null
     */
    public function getOption($key, $default = null)
    {
        return isset($this->options[$key]) ? $this->options[$key] : $default;
    }

    /**
     * @return bool
     */
    public function isIntegrationActivated()
    {
        return (bool) $this->getOption('integration_active');
    }

    /**
     * @return bool
     *
     * @since 1.8.0
     */
    public function isPickingIntegrationActivated()
    {
        return (bool) $this->getOption('integration_picking_active');
    }

    /**
     * @return bool
     *
     * @since 1.9.0
     */
    public function isSendAndPrintButtonsEnabled()
    {
        return (bool) $this->getOption('integration_send_and_print_buttons');
    }

    /**
     * @return array
     *
     * @since 1.8.0
     */
    public function isPickingIntegrationData()
    {
        $apiUrl = $this->getOption('integration_picking_api_url');
        $tokenUsername = $this->getOption('integration_picking_username');
        $tokenPassword = $this->getOption('integration_picking_password');
        $tokenScope = $this->getOption('integration_picking_scope');
        $tokenUrl = $apiUrl.'/Token';
        $apiSendUrl = $apiUrl.'/api/v1/shipments/InsertPickingList';
        $apiPrintUrl = $apiUrl.'/api/v1/shipments/PrintWBOrderDetails';

        return [
            'tokenUrl' => $tokenUrl,
            'tokenUsername' => $tokenUsername,
            'tokenPassword' => $tokenPassword,
            'tokenScope' => $tokenScope,
            'apiSendUrl' => $apiSendUrl,
            'apiPrintUrl' => $apiPrintUrl
        ];
    }

    /**
     * @return Ups
     */
    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new Ups();
        }

        return self::$instance;
    }

    /**
     * @param $street
     * @return int|mixed
     */
    public function getHouseNumber($street)
    {
        preg_match('/[0-9]+/i', $street, $matches);

        if (count($matches)) {
            return $matches[0];
        }

        return '';
    }

    /**
     * @param $order
     * @return mixed
     *
     * @since 2.5.6
     */
    public function getShippingHouseNumber($order){
        return $order->get_meta('_shipping_home_number');
    }

    /**
     * @param $order
     * @return string
     *
     * @since 1.7.0
     */
    public function getReference2Data($order){

        $option = $this->getOption('integration_additional_field');

        if ($option === 'customer_name') {
            return $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
        }
        if($option === 'order_id') {
            return $order->get_id();
        }
        if($option === 'email') {
            return $order->get_billing_email();
        }
        if($option === 'phone_number') {
            return $order->get_billing_phone();
        }
        if($option === 'pickup_point_id') {
            $pkps_order = $this->getOrderPickupPointJson($order);
            return $pkps_order !== '' ? $pkps_order->iid : '';
        }
        if($option === 'pickup_point_name') {
            $pkps_order = $this->getOrderPickupPointJson($order);
            return $pkps_order !== '' ? $pkps_order->title : '';
        }

        return '';
    }

    /**
     *
     * Limit Shipment Instruction to 50 Chars
     * @param $order
     * @return string
     *
     * @since 1.9.0
     */
    public function getShipmentInstructions($order){
        return mb_strimwidth($this->getShipmentInstructionsField($order), 0, 50);
    }

    /**
     * @param $text
     * @return string
     *
     * @since 1.10.5
     */
    public function fixTextQuotes($text){
        return str_replace('"', "'", $text);
    }

    /**
     * Get Shipment Instructions Field from admin option
     *
     * @param $order
     * @return string
     *
     * @since 1.9.0
     */
    private function getShipmentInstructionsField($order){

        $shipmentInstructionsField = $this->getOption('integration_shipment_instructions_field');

        if($shipmentInstructionsField == ''){
            return '';
        }

        $orderCustomerNotes = $order->get_customer_note();
        $shipmentInstructionsCustomField = $this->getOption('integration_shipment_instructions_field_custom');

        switch ($shipmentInstructionsField){
            case 'customer_notes':
                return $orderCustomerNotes;
            case 'custom_field':
                return $shipmentInstructionsCustomField;
            case 'custom_field_notes':
                return $shipmentInstructionsCustomField.' '.$orderCustomerNotes;
            case 'customer_notes_custom':
                return $orderCustomerNotes.' '.$shipmentInstructionsCustomField;
        }
    }

    /**
     * @param $order
     * @return mixed
     *
     * @since 1.7.0
     */
    public function getOrderPickupPointJson($order){
        foreach ( $order->get_shipping_methods() as $shipping_item ) {

            if (\WC_Ups_PickUps::METHOD_ID == $shipping_item['method_id']) {
                /**
                 * get json from order meta
                 * if orders placed before plugin update (Version 1.5.0), we check if json is in the item
                 */
                try {
                    $jsondata = $order->get_meta('pkps_json') ?: wc_get_order_item_meta($order->get_id(), 'pkps_json');
                    $jsondata = str_replace('\\"', '"', $jsondata);
                    $jsondata = preg_replace('/\\\"/', "\"", $jsondata);
                    $jsondata = preg_replace('/\\\'/', "\'", $jsondata);

                    return json_decode($jsondata, false);
                } catch (\Exception $e ){
                    return '';
                }
            }
        }

        return '';
    }

    public function get_formatted_address_helper($pkps_order, $one_line = false, $show_cost = false)
    {

        $formatted = __('PickUP Point Location Number: ', WC_Ups_PickUps::TEXT_DOMAIN) . "<b>" . $pkps_order->iid . "</b>";

        if ($one_line) {

            $formatted = str_replace(array('<br/>', '<br />', "\n"), array(', ', ', ', ''), $formatted);

            $formatted .= " " . $pkps_order->title . " ";

            $formatted .= $pkps_order->street . ', ' . $pkps_order->city;

            $formatted .= '('.number_format($pkps_order->dist, 2) . ' ק"מ)';

        } else {
            $formatted .= "<br/>\n" . $pkps_order->title;
            $formatted .= "<br/>\n" . $pkps_order->street . ', ' . $pkps_order->city;
            $formatted .= "<br/>\n" . '('.number_format($pkps_order->dist, 2) . ' ק"מ)';
        }

        return $formatted;
    }

    public function switchTranslation()
    {
        $locale = get_locale();
        $mofile = dirname(WC_UPS_BASE_FILE_PATH) . '/i18n/languages/woo-ups-pickup-'. $locale .'.mo';
        if (!file_exists($mofile)) {
            return;
        }

        unload_textdomain(WC_Ups_PickUps::TEXT_DOMAIN);
        load_textdomain(WC_Ups_PickUps::TEXT_DOMAIN, $mofile);
    }

    /**
     * @return boolean
     */
    public function isPickUpsProductsPointsOverTheMax(){
        global $woocommerce;

        $settings = get_option('woocommerce_woo-ups-pickups_settings');
        $maxPointsForPickup = isset($settings['max_points_for_pickup']) ? $settings['max_points_for_pickup'] : 0;
        if($maxPointsForPickup > 0) {
            $countItemsPickupPoints = 0;
            foreach ($woocommerce->cart->get_cart() as $key => $item) {
                $product = wc_get_product($item['product_id']);

                if ($product) {
                    $productQty = $item['quantity'];
                    $productPickupsPickupPoints = $product->get_meta(\WC_Ups_PickUps::PRODUCT_PICKUPS_PICKUP_POINTS_ATTRIBUTE) ?: 0;
                    $countItemsPickupPoints += $productPickupsPickupPoints * $productQty;
                }
            }

            return $countItemsPickupPoints > $maxPointsForPickup;
        }

        return false;
    }

    /**
     *
     * Get admin orders page url params
     *
     * @return string
     *
     * @since 1.9.0
     */
    public function getAdminOrdersUrlParams(){
        $params = isset($_GET['s']) ? '&s='.$_GET['s'] : '';
        $params .= isset($_GET['paged']) ? '&paged='.$_GET['paged'] : '';
        $params .= isset($_GET['post_status']) ? '&post_status='.$_GET['post_status'] : '';

        return $params;
    }

    /**
     * Get Ups Orders Uploads Folder
     *
     * @return array|string
     *
     * @since 1.10.0
     */
    public function getUpsOrderUploadsFolder(){
        $wpUploadsFolder = wp_upload_dir();

        if ( $wpUploadsFolder['error'] ) {
            $this->filesystem->writeLog('createXmlOrderDocument: wp_upload_dir error: '.$wpUploadsFolder['error']);
        }

        $wpUploadsFolder = $wpUploadsFolder['basedir'] . '/ups-orders/';

        if(!is_dir($wpUploadsFolder)) {
            wp_mkdir_p($wpUploadsFolder);
        }

        if($this->isDebugModeEnabled()) {
            $this->filesystem->writeLog('createXmlOrderDocument: uploads folder: ' . $wpUploadsFolder);
        }

        return $wpUploadsFolder;
    }

    /**
     *
     * Is Save Order As XML and send to FTP Enabled
     *
     * @return bool
     *
     * @since 1.10.0
     */
    public function isSaveOrderAsXmlEnabled(){
        return $this->getOption('save_order_as_xml_active');
    }

    /**
     * Is Auto Create XML and Send to FTP
     *
     * @return mixed|null
     *
     * @since 1.10.0
     */
    public function isSendXmlAutomaticMode(){
        return $this->isSaveOrderAsXmlEnabled() && $this->getOption('save_order_as_xml_automatic');
    }

    /**
     *
     * Is Debug Mode Enabled
     *
     * @return bool
     *
     * @since 1.10.0
     */
    public function isDebugModeEnabled(){
        return $this->getOption('debug_mode') === 'yes';
    }

    /**
     *
     * Is API Show Response Enabled
     * For debug
     *
     * @return bool
     *
     * @since 2.5.9
     */
    public function isApiDebugShowResponseEnabled(){
        return $this->getOption('api_show_response') === 'yes';
    }

    /**
     *
     * Is API Show Response Enabled
     * For debug
     *
     * @return bool
     *
     * @since 2.5.9
     */
    public function isApiDebugShowResponseTokenEnabled(){
        return $this->getOption('api_show_response') === 'token';
    }

    /**
     * get Ups Customer Scope
     *
     * @since 1.10.0
     */
    public function getUpsCustomerScope(){
        if($scope = $this->getOption('integration_picking_scope')){
            return $scope;
        }
        if($scope = $this->getOption('api_username')){
            $scopeArray = explode('.', $scope);
            return $scopeArray[0];
        }
        return false;
    }

    /**
     * Get Xml Order Type Option
     *
     * @param $order
     * @return mixed|null
     * @since 1.10.0
     */
    public function getXmlOrderTypeOption($order){
        if($orderType = $order->get_meta('ups_xml_order_type')){
            return $orderType;
        }
        return $this->getOption('save_order_as_xml_type');
    }

    /**
     * Get Pickup Warehouse Address
     *
     * @since 1.10.0
     */
    public function getPickupWarehouseAddress(){
        return ['street' => $this->getOption('save_order_as_xml_pickup_warehouse_location_address'), 'city' => $this->getOption('save_order_as_xml_pickup_warehouse_location_city')];
    }

    /**
     * @return mixed|null
     *
     * @since 1.10.2
     */
    public function getCustomerEmailTitle(){
        return $this->getOption('pickups_customer_email_pickup_point_title') ?: _n('Pickup Location', 'Pickup Locations', 1, WC_Ups_PickUps::TEXT_DOMAIN);
    }

    /**
     * @return mixed|null
     *
     * @since 1.10.5
     */
    public function getThankYouPagePickupPointTitle(){
        return $this->getOption('pickups_customer_email_pickup_point_title') ?: __('Pickup Point', WC_Ups_PickUps::TEXT_DOMAIN);
    }

    /**
     * @return mixed|null
     *
     * @since 1.10.5
     */
    public function checkUpsApiSettings(){
        $restApi = new CreateWaybillService(false, 'api_create_url_token');
        $settings = $restApi->checkUpsRestApiSettings();
        return ['errors' => $settings['errors'], 'messages' => $settings['messages'], 'customer_type' => $settings['customer_type'] ?? ''];
    }

    /**
     * @return mixed
     *
     * @since 1.10.6
     */
    public function getUpsPickupValidationErrorMessage(){
        return $this->getOption('pickups_checkout_validation_error_message') ?: __('Please select a local pickup location', WC_Ups_PickUps::TEXT_DOMAIN);
    }

    /**
     *
     * Get Order Weight
     * @param $order
     * @return string
     *
     * @since 2.0.0
     */
    public function getOrderWeight($order){
        if($weight = $order->get_meta('ups_order_weight')) {
            return $this->convertNumberToFloat($weight, 2);
        }

        return '';
    }

    /**
     *
     * Get Order Number Of Packages
     * @param $order
     * @return string
     *
     * @since 2.4.0
     */
    public function getOrderNumberOfPackages($order){
        if($numOfPackages = $order->get_meta('ups_order_num_of_packages')) {
            return $numOfPackages > 10 ? 10 : $numOfPackages;
        }

        return 1;
    }

    public function convertNumberToFloat($number, $decimals){
        return number_format((float)$number, $decimals, '.', '');
    }

    /**
     *
     * Get Order Lead Id
     * @param $order
     * @return string
     *
     * @since 2.0.0
     */
    public function getOrderLeadId($order){
        return $order->get_meta('ups_ship_number_lead_id');
    }

    /**
     * Get Pickup Point ID
     * search inside json from order meta
     * if orders placed before plugin Version 1.5.0, we check if json is inside the item
     *
     * @param $order
     * @return mixed|null
     * @since 2.0.0
     */
    public function getPickupPointId($order){
        $json = $order->get_meta('pkps_json') ?: wc_get_order_item_meta($order->get_id(), 'pkps_json');
        if ($json) {
            $json = str_replace('\\"', '"', $json);
            $json = preg_replace('/\\\"/', "\"", $json);
            $json = preg_replace('/\\\'/', "\'", $json);
            $pickupInfo = json_decode($json, true);
            if (!empty($pickupInfo['iid'])) {
                return $pickupInfo['iid'];
            }
        }
        return null;
    }

    /**
     * @return false|string
     *
     * @since 2.3.0
     */
    public function getPluginLastInstalledDate(){
        try {
            $stat = stat(dirname(__DIR__, 3).'/woocommerce-ups-pickups.php');
            return date('Y-m-d H:i', $stat['mtime']);
        } catch (\Exception $e){

        }
        return '';
    }

    /**
     * @param $order
     * @return mixed
     *
     * @since 2.4.0
     */
    public function getOrderWBStatus($order){
        return $order->get_meta('ups_wb_status');
    }

    /**
     * @param $order
     * @param $type
     * @return mixed
     *
     * @since 2.4.0
     */
    public function getOrderWBStatusTime($order, $type){
        if(!$time = $order->get_meta('ups_wb_status_timestamp')){
            return '';
        }

        $time = (int)$time;

        switch($type){
            case 'date':
                return date('d/m/Y', $time);
            case 'datetime':
                return date('d/m/Y H:i:s', $time);
        }
        return $time;
    }

    /**
     *
     * @param null $order
     * @return bool
     * @since 2.4.0
     */
    public function isGetWBStatusEnabled($order = null){
        if(!$this->getOption('integration_get_status_active')){
            return false;
        }

        if($order){
            $wbTimestamp = $order->get_meta('ups_ship_number_timestamp');
            if(!$wbTimestamp){
                return false;
            }

            $daysDiff = (time() - $wbTimestamp) / 86400;
            if($daysDiff > 30){
                return false;
            }

            $wbStatus = $this->getOrderWBStatus($order);

            $isPickupUps = false;
            $shippingMethods = $order->get_shipping_methods();
            foreach ($shippingMethods as $shippingMethod) {
                if ($this->isPickupUps($shippingMethod->get_method_id())) {
                    $isPickupUps = true;
                    break;
                }
            }

            if(!$isPickupUps && $wbStatus === 'נמסר'){
                return false;
            }

            if($isPickupUps && $wbStatus === 'נאסף ע"י הלקוח') {
                return false;
            }
        }

        return true;
    }

    /**
     * @return int|mixed|null
     *
     * @since 2.4.0
     */
    public function getSettingsUpdateAt(){
        if($updateAt = $this->getOption('settings_update_at')){
            return $updateAt;
        }

        return $this->setSettingsUpdateAt();
    }

    /**
     * @return int
     *
     * @since 2.4.0
     */
    public function setSettingsUpdateAt(){
        $currentTime = time();

        $option = get_option('woocommerce_woo-ups-pickups_settings');
        $option['settings_update_at'] = $currentTime;
        update_option('woocommerce_woo-ups-pickups_settings', $option);

        return $currentTime;
    }

    /**
     * @since 2.4.0
     */
    public function isImportWBLimitExceeded(){
        $importWBLimitCount = $this->getImportWbLimitCount();
        return $importWBLimitCount >= 50;
    }

    /**
     * @return int
     *
     * @since 2.4.0
     */
    private function getImportWbLimitCount(){
        return ++$this->importWbLimitCount;
    }

    /**
     * @return int
     *
     * @since 2.4.0
     */
    public function getIsraelTime(){
        try {
            $date = new \DateTime('now', new \DateTimeZone('Asia/Jerusalem'));
            $time = $date->getTimestamp() + $date->getOffset();
        } catch (\Exception $e){
            $this->filesystem->writeLog('getIsraelTime error: '.$e->getMessage());
            $time = time();
        }

        return $time;
    }
}
