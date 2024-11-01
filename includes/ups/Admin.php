<?php
/**
 * @category UPS
 * @copyright UPS Company
 */
namespace Ups;

use Ups\Admin\Ajax;
use Ups\Cache;
use Ups\Helper\Ups;
use Ups\Order\Api;
use Ups\Order\Grid\Column\Actions;
use Ups\Order\Grid\Column\WB;
use Ups\Service\PrintService;
use WC_Ups_PickUps;

class Admin
{
    const COLUMN_LEAD_ID = 'ups_lead_id';
    const COLUMN_ORDER_WEIGHT = 'ups_order_weight';
    const COLUMN_WB_STATUS = 'ups_wb_status';

    /**
     * @var Ups
     */
    protected $helper;

    /**
     * @var \Ups\Cache
     */
    protected $_cache;

    public function __construct()
    {
        $this->helper = Ups::getInstance();
        $this->_cache = new Cache();
        $this->registerHook();
    }

    public function registerHook()
    {
        // HPOS filters
        add_filter('manage_woocommerce_page_wc-orders_columns', array($this, 'registerUpsColumn'));
        add_filter('manage_woocommerce_page_wc-orders_custom_column', array($this, 'renderUpsColumnHpos'), 10, 2);
        // Legacy filters
        add_filter('manage_edit-shop_order_columns', array($this, 'registerUpsColumn'));
        add_filter('manage_shop_order_posts_custom_column', array($this, 'renderUpsColumn'), 10, 2);

        /**
         * Show Pickup Point on Admin Order Page
         *
         * @since 1.10.6
         */
        add_action('woocommerce_before_order_itemmeta', array($this, 'showPickupPointOnAdminOrderPage'), 10, 3);

        if($this->helper->isSaveOrderAsXmlEnabled()){
            add_action('wp_ajax_ups_create_and_send_xml', array($this, 'createAndSendXml'));
            add_action('woocommerce_order_actions', array($this, 'addOrderActionsForXmlOrderType') );
            add_action( 'woocommerce_order_action_wc_ups_xml_pd_order_action', array($this, 'setXmlOrderTypePD') );
            add_action( 'woocommerce_order_action_wc_ups_xml_fd_order_action', array($this, 'setXmlOrderTypeFD') );
            add_action( 'woocommerce_order_action_wc_ups_xml_auto_order_action', array($this, 'setXmlOrderTypeAuto') );
        }

        if (!$this->helper->isIntegrationActivated()) {
            // remove feature api integration
            return;
        }


        // actions
        add_action('admin_enqueue_scripts', array($this, 'registerStyle'));
        add_action('wp_ajax_ups_picking_send_order', array($this, 'sendPickingOrderToUps'));
        add_action('wp_ajax_ups_picking_print_label', array($this, 'printPickingLabel'));
        add_action('wp_ajax_ups_sync_order', array($this, 'sendOrderToUps'));
        add_action('wp_ajax_change_pickup_point', array($this, 'changePickupPoint'));
        add_action('wp_ajax_ups_print_label', array($this, 'printLabel'));
        add_action('wp_ajax_ups_send_and_print_label', array($this, 'sendOrderAndPrintLabel'));
        add_action('wp_ajax_ups_import_waybills', array($this, 'importWaybills'));
        add_action('wp_ajax_ups_clean_json_from_old_version', array($this, 'cleanPkpsJsonFromOldVersion'));
        add_action('wp_ajax_ups_get_wb_status', array($this, 'getWaybillStatus'));
        add_action('admin_notices', array($this, 'showBulkActionNotices'));
        add_action('admin_footer', array($this, 'addScriptsToPickUPSSettings'));

        // filters
        // HPOS filters
        add_filter('manage_woocommerce_page_wc-orders_columns', array($this, 'registerUpsColumn'));
        add_filter('manage_woocommerce_page_wc-orders_custom_column', array($this, 'renderUpsColumnHpos'), 10, 2);
        add_filter('bulk_actions-woocommerce_page_wc-orders', array($this, 'registerBulkAction'));
        add_filter('handle_bulk_actions-woocommerce_page_wc-orders', array($this, 'handleBulkAction'), 10, 3);

        // Legacy filters
        add_filter('manage_edit-shop_order_columns', array($this, 'registerUpsColumn'));
        add_filter('manage_shop_order_posts_custom_column', array($this, 'renderUpsColumn'), 10, 2);
        add_filter('bulk_actions-edit-shop_order', array($this, 'registerBulkAction'));
        add_filter('handle_bulk_actions-edit-shop_order', array($this, 'handleBulkAction'), 10, 3);

        /**
         * UPS Actions on Admin Order Page
         *
         * @since 1.10.6
         */
        add_action( 'woocommerce_order_actions', array($this, 'registerBulkAction') );
        add_action( 'woocommerce_order_action_sync_order_to_ups', array($this, 'orderPageAction') );
        add_action( 'woocommerce_order_action_ups_print_a4', array($this, 'orderPageAction') );
        add_action( 'woocommerce_order_action_ups_print_thermal', array($this, 'orderPageAction') );
        add_action( 'woocommerce_order_action_ups_create_picking_list', array($this, 'orderPageAction') );
        add_action( 'woocommerce_order_action_ups_print_picking_a4', array($this, 'orderPageAction') );
        add_action( 'woocommerce_order_action_ups_print_picking_thermal', array($this, 'orderPageAction') );
        add_action( 'woocommerce_order_action_ups_send_and_print_label_a4', array($this, 'orderPageAction') );
        add_action( 'woocommerce_order_action_ups_send_and_print_label_thermal', array($this, 'orderPageAction') );
        add_action( 'woocommerce_order_action_ups_create_and_send_xml', array($this, 'orderPageAction') );
        add_action( 'woocommerce_order_action_ups_import_waybills', array($this, 'orderPageAction') );
        add_action( 'woocommerce_order_action_ups_change_pickup_point', array($this, 'orderPageAction') );
        add_action( 'woocommerce_order_action_ups_get_wb_status', array($this, 'orderPageAction') );
    }

    /**
     * UPS Actions on Admin Order Page
     *
     * @param $order
     * @since 1.10.6
     */
    public function orderPageAction($order){
        $currentAction = current_action();
        $currentAction = str_replace('woocommerce_order_action_', '', $currentAction);

        $actionUrl = '';
        $urlParams = '';

        switch ($currentAction){
            case 'sync_order_to_ups':
                $urlParams = '&action=ups_sync_order&order_id=' . $order->get_id();
                break;
            case 'ups_import_waybills':
                $urlParams = '&action=ups_import_waybills&order_ids=' . $order->get_id();
                break;
            case 'ups_print_a4':
                $urlParams = '&action=ups_print_label&format='.PrintService::TYPE_A4.'&order_ids=' . $order->get_id();
                break;
            case 'ups_print_thermal':
                $urlParams = '&action=ups_print_label&format='.PrintService::TYPE_THERMAL.'&order_ids=' . $order->get_id();
                break;
            case 'ups_create_picking_list':
                $urlParams = '&action=ups_picking_send_order&order_ids=' . $order->get_id();
                break;
            case 'ups_print_picking_a4':
                $urlParams = '&action=ups_picking_print_label&format='.PrintService::TYPE_A4.'&order_ids=' . $order->get_id();
                break;
            case 'ups_print_picking_thermal':
                $urlParams = '&action=ups_picking_print_label&format='.PrintService::TYPE_THERMAL.'&order_ids=' . $order->get_id();
                break;
            case 'ups_send_and_print_label_a4':
                $urlParams = '&action=ups_send_and_print_label&format='.PrintService::TYPE_A4.'&order_ids=' . $order->get_id();
                break;
            case 'ups_send_and_print_label_thermal':
                $urlParams = '&action=ups_send_and_print_label&format='.PrintService::TYPE_THERMAL.'&order_ids=' . $order->get_id();
                break;
            case 'ups_create_and_send_xml':
                $urlParams = '&action=ups_create_and_send_xml&order_id=' . $order->get_id();
                break;
            case 'ups_change_pickup_point':
                $actionUrl = admin_url('admin.php?page=change-pickup-point&order_page=1&order_id=' . $order->get_id());
                break;
            case 'ups_get_wb_status':
                $urlParams = '&action=ups_get_wb_status&order_ids=' . $order->get_id();
                break;
        }

        if($actionUrl || $urlParams) {
            if(!$actionUrl) {
                $actionUrl = admin_url('admin-ajax.php?order_page=1' . $urlParams);
            }

            header("Location: " . $actionUrl);
            die();
        }
    }

    /**
     * Ups Integration Settings Script
     * Check if Ups Integration Settings is Valid
     *
     * @since 2.0.0
     */
    private function isUpsIntegrationSettingsValid(){
        $upsApiSettings = $this->helper->checkUpsApiSettings();
        $script = '';

        if(isset($upsApiSettings['errors']) && count($upsApiSettings['errors']) > 0) {
            $upsSettingsErrorListHtml = '';
            foreach($upsApiSettings['errors'] as $key => $error){
                $upsSettingsErrorListHtml .= 'const $fieldElm_'.$key.' = jQuery(\'#woocommerce_woo-ups-pickups_'.$error.'\');$fieldElm_'.$key.'.addClass(\'error\').siblings(\'.description\').addClass(\'error\').text(\'פרטי ההתחברות לא נכונים\');';
            }

            $upsSettingsErrorListHtml .= 'jQuery(\'#woocommerce_woo-ups-pickups_shipment_additional_fields\').parents(\'tr\').hide()';

            $script = 'window.onload = function(){
                                '.$upsSettingsErrorListHtml.'
                                jQuery(\'html, body\').animate({
                                scrollTop: $fieldElm_0.offset().top - 150
                            }, 300);
                        }';
        }else if(isset($upsApiSettings['messages']) && count($upsApiSettings['messages']) > 0) {
            $upsSettingsMessageListHtml = '';
            foreach($upsApiSettings['messages'] as $key => $message){
                $upsSettingsMessageListHtml .= 'const $fieldElm_'.$key.' = jQuery(\'#woocommerce_woo-ups-pickups_'.$message['key'].'\');$fieldElm_'.$key.'.siblings(\'.description\').append(\''.$message['value'].'\');';
            }

            if($upsApiSettings['customer_type'] !== Ups::CUSTOMER_TYPE_CREDIT){
                $upsSettingsMessageListHtml .= 'jQuery(\'#woocommerce_woo-ups-pickups_shipment_additional_fields\').parents(\'tr\').hide()';
            }

            $script = 'window.onload = function(){
                                '.$upsSettingsMessageListHtml.'
                        }';
        }

        return ['script' => $script];
    }

    /**
     *
     * Add scripts to Pick UPS settings page
     *
     * @since 2.0.0
     */
    public function addScriptsToPickUPSSettings(){
        if(isset($_GET['page']) && $_GET['page'] === 'wc-settings' &&
            isset($_GET['tab']) && $_GET['tab'] === 'shipping' &&
            isset($_GET['section']) && $_GET['section'] === 'woo-ups-pickups'
        ){

            $this->removeTokenCacheOnSettingsUpdate();

            echo '<script>';

            echo 'jQuery(\'#woocommerce_woo-ups-pickups_integration_order_weight_default\').attr(\'step\', 0.01);';

            if(($isUpsIntegrationSettingsValid = $this->isUpsIntegrationSettingsValid()) && $isUpsIntegrationSettingsValid['script']){
                echo $isUpsIntegrationSettingsValid['script'];
            }

            echo '</script>';
        }
    }

    /**
     * @since 2.1.0
     */
    private function removeTokenCacheOnSettingsUpdate(){
        if(count($_POST) > 0){
            $this->_cache->cleanCache();
        }
    }

    /**
     *
     * remove pkps_json from order_itemmeta table
     *
     * @since 2.0.0
     */
    public function cleanPkpsJsonFromOldVersion(){
        foreach(wc_get_orders(array('limit' => 10000)) as $order){
            foreach ( $order->get_shipping_methods() as $shipping_item ) {

                if ( WC_Ups_PickUps::METHOD_ID == $shipping_item['method_id'] ) {
                    $badMeta = wc_get_order_item_meta($order->get_id(), 'pkps_json');

                    if(!$goodMeta = $order->get_meta('pkps_json')) {
                        $order->update_meta_data('pkps_json', $badMeta);
                    }
                }
                wc_delete_order_item_meta( $order->get_id(), 'pkps_json');
            }
        }
        wp_redirect('admin.php?page=wc-settings&tab=shipping&section=woo-ups-pickups');
        exit;
    }

    /**
     * Register admin style
     */
    public function registerStyle()
    {
        wp_enqueue_style('ups-admin-style', plugins_url(WC_UPS_PLUGIN_DIR. '/includes/css/admin.css', WC_UPS_PLUGIN_DIR));
        wp_enqueue_script('ups-admin-print-label', plugins_url(WC_UPS_PLUGIN_DIR. '/includes/js/print-label.js', WC_UPS_PLUGIN_DIR), '', WC_Ups_PickUps::VERSION);
    }

    /**
     * @param array $columns
     * @return array
     */
    public function registerUpsColumn($columns)
    {
        if (is_array($columns)) {
            $columns[WB::COLUMN_ID] = __('WB', WC_Ups_PickUps::TEXT_DOMAIN);
            if ($this->helper->isIntegrationActivated()) {
                if($this->helper->isGetWBStatusEnabled()) {
                    $columns[self::COLUMN_WB_STATUS] = __('WB Status', WC_Ups_PickUps::TEXT_DOMAIN);
                }
                $columns[self::COLUMN_LEAD_ID] = __('Lead ID', WC_Ups_PickUps::TEXT_DOMAIN);
                $columns[self::COLUMN_ORDER_WEIGHT] = __('Order Weight', WC_Ups_PickUps::TEXT_DOMAIN);
                $columns[Actions::COLUMN_ID] = __('Ups Actions', WC_Ups_PickUps::TEXT_DOMAIN);
            }

        }

        return $columns;
    }

    /**
     * @param $column
     * @param $order
     * @since 2.7.0
     */
    public function renderUpsColumnHpos($column, $order){
        $orderId = $order->get_id();
        $this->renderUpsColumn($column, $orderId);
    }

    /**
     * @param string $column
     * @param int $orderId
     */
    public function renderUpsColumn($column, $orderId)
    {
        switch (true) {
            case $column === Actions::COLUMN_ID:
                $renderer = new Actions();
                break;

            case $column === WB::COLUMN_ID:
                $renderer = new WB();
                break;

            case $column === self::COLUMN_WB_STATUS:
                $renderer = '';
                echo $this->getColumnWBStatusContent($orderId);
                break;

            case $column === self::COLUMN_LEAD_ID:
                $renderer = '';
                echo $this->getColumnLeadIdContent($orderId);
                break;

            case $column === self::COLUMN_ORDER_WEIGHT:
                $renderer = '';
                echo $this->getColumnOrderWeightContent($orderId);
                break;

            default:
                $renderer = null;
                break;
        }

        if (!$renderer) {
            return;
        }

        return $renderer->render($column, $orderId);
    }

    public function sendOrderToUps()
    {
        $ajax = new Ajax();

        return $ajax->sendOrderToUps();
    }

    /**
     *  Send Order to Ups (Create Picking List)
     *
     * @since 1.8.0
     */
    public function sendPickingOrderToUps()
    {
        $ajax = new Ajax();

        return $ajax->sendPickingOrderToUps();
    }

    /**
     *  Print Picking Label
     *
     * @since 1.8.0
     */
    public function printPickingLabel(){
        $ajax = new Ajax();

        return $ajax->printPickingLabel();
    }

    /**
     *  Change Pickup Point For Order
     *
     * @since 1.6.0
     */
    public function changePickupPoint(){
        $ajax = new Ajax();

        return $ajax->changePickupPoint();
    }

    /**
     * Send Order to UPS and than Print Label
     *
     * @since 1.9.0
     */
    public function sendOrderAndPrintLabel(){
        $ajax = new Ajax();
        return $ajax->sendOrderAndPrintLabel();
    }

    public function printLabel()
    {
        $ajax = new Ajax();

        return $ajax->printLabel();
    }

    /**
     * Send Order as XML to FTP
     *
     * @since 1.10.0
     */
    public function createAndSendXml()
    {
        $ajax = new Ajax();

        return $ajax->createAndSendXml();
    }

    /**
     *  Import Waybills
     *
     * @since 2.0.0
     */
    public function importWaybills()
    {
        $ajax = new Ajax();

        return $ajax->importWaybills();
    }

    /**
     * @param array $actions
     * @return array
     */
    public function registerBulkAction($actions)
    {
        global $theorder;
        $showSendOrderToUps = true;
        $showPrintLabels = true;
        $showSendAndPrint = true;
        $showCreatePickingList = true;
        $showPrintPickingList = true;

        if($theorder){
            $showSendOrderToUps = false;
            $showPrintLabels = false;
            $showSendAndPrint = false;
            $showCreatePickingList = false;
            $showPrintPickingList = false;
            $syncFlag = $theorder->get_meta('ups_sync_flag');
            $syncPickingFlag = $theorder->get_meta('ups_sync_picking_flag');
            $upsXmlSentFlag = $theorder->get_meta('ups_xml_sent');

            if (!$syncFlag || $syncFlag == Api::STATUS_SEND_ERROR) {
                if ($this->helper->isShippingMethodIsPickupUps($theorder)) {
                    $actions['ups_change_pickup_point'] = __('Change Pickup Point', WC_Ups_PickUps::TEXT_DOMAIN);
                }

                $showSendOrderToUps = true;
                $showSendAndPrint = true;
            } elseif ($syncFlag == Api::STATUS_SEND_SUCCESS) {
                $showPrintLabels = true;
                if($syncPickingFlag == Api::STATUS_SEND_SUCCESS) {
                    $showPrintPickingList = true;
                }else{
                    $showCreatePickingList = true;
                }
            }

            if($this->helper->isSaveOrderAsXmlEnabled() && $upsXmlSentFlag != Api::STATUS_SEND_SUCCESS) {
                $actions['ups_create_and_send_xml'] = __('Create & Send XML', WC_Ups_PickUps::TEXT_DOMAIN);
            }
        }

        if($showSendOrderToUps){
            $actions['sync_order_to_ups'] = __('Send Order(s) to UPS', WC_Ups_PickUps::TEXT_DOMAIN);
            if(!$theorder || $this->helper->getOrderLeadId($theorder)) {
                $actions['ups_import_waybills'] = __('Import Waybill', WC_Ups_PickUps::TEXT_DOMAIN);
            }
        }
        if($showPrintLabels) {
            $actions['ups_print_a4'] = __('UPS Print A4 Labels', WC_Ups_PickUps::TEXT_DOMAIN);
            $actions['ups_print_thermal'] = __('UPS Print Thermal Labels', WC_Ups_PickUps::TEXT_DOMAIN);
        }

        if($this->helper->isPickingIntegrationActivated()) {
            if($showCreatePickingList) {
                $actions['ups_create_picking_list'] = __('UPS Create Picking List', WC_Ups_PickUps::TEXT_DOMAIN);
            }
            if($showPrintPickingList) {
                $actions['ups_print_picking_a4'] = __('UPS Print List & WB A4', WC_Ups_PickUps::TEXT_DOMAIN);
                $actions['ups_print_picking_thermal'] = __('UPS Print List & WB Labels', WC_Ups_PickUps::TEXT_DOMAIN);
            }
        }

        if($showSendAndPrint && $this->helper->isSendAndPrintButtonsEnabled()){
            $actions['ups_send_and_print_label_a4'] = __('UPS Send & Print WB A4', WC_Ups_PickUps::TEXT_DOMAIN);
            $actions['ups_send_and_print_label_thermal'] = __('UPS Send & Print WB Labels', WC_Ups_PickUps::TEXT_DOMAIN);
        }

        if($this->helper->isGetWBStatusEnabled()) {
            $actions['ups_get_wb_status'] = __('UPS Get WB Status', WC_Ups_PickUps::TEXT_DOMAIN);
        }

        return $actions;
    }

    /**
     * @param string $redirectTo
     * @param string $doAction
     * @param array $postIds
     * @return string
     */
    public function handleBulkAction($redirectTo, $doAction, $postIds)
    {
        if (!in_array($doAction, array('sync_order_to_ups', 'ups_print_a4', 'ups_print_thermal', 'ups_create_picking_list', 'ups_print_picking_a4', 'ups_print_picking_thermal', 'ups_import_waybills', 'ups_get_wb_status'))) {
            return $redirectTo;
        }

        $api = new Api();
        switch ($doAction) {
            case 'sync_order_to_ups':
                $api->sendOrders($postIds);
                break;

            case 'ups_import_waybills':
                $api->importWaybills($postIds);
                break;

            case 'ups_print_a4':
                $api->printLabels($postIds, PrintService::TYPE_A4);
                exit;
                break;

            case 'ups_print_thermal':
                $api->printLabels($postIds, PrintService::TYPE_THERMAL);
                exit;
                break;

            case 'ups_create_picking_list':
                $api->sendPickingOrders($postIds);
                break;

            case 'ups_print_picking_a4':
                $api->printPickingLabel($postIds, PrintService::TYPE_A4);
                exit;
                break;

            case 'ups_print_picking_thermal':
                $api->printPickingLabel($postIds, PrintService::TYPE_THERMAL);
                exit;
                break;
            case 'ups_get_wb_status':
                $api->getWaybillStatus($postIds);
                break;
        }

        return $redirectTo;
    }

    public function showBulkActionNotices()
    {
        $filesystem = new Filesystem();
        $sessions = $filesystem->readSession('ups');
        $errors = !empty($sessions['errors']) ? $sessions['errors'] : array();
        $successes = !empty($sessions['successes']) ? $sessions['successes'] : array();

        $html = '';
        if (count($errors)) {
            $html .= '<div class="error fade">';
            foreach ($errors as $message) {
                $html .= '<p>'. $message .'</p>';
            }
            $html .= '</div>';
        }
        if (count($successes)) {
            $html .= '<div class="updated fade">';
            foreach ($successes as $message) {
                $html .= '<p>'. $message .'</p>';
            }
            $html .= '</div>';
        }

        $sessions['errors'] = array();
        $sessions['successes'] = array();
        $filesystem->writeSession($sessions, 'ups');

        echo $html;
    }

    /**
     *
     * Add Admin Order Actions for XML Order Type
     *
     * @param $actions
     * @return mixed
     *
     * @since 1.10.0
     */
    public function addOrderActionsForXmlOrderType($actions){
        global $theorder;

        $order = wc_get_order($theorder->get_id());

        if($order->get_meta('ups_xml_sent') != Api::STATUS_SEND_SUCCESS) {

            if($order->get_meta('ups_xml_order_type') !== '') {
                $actions['wc_ups_xml_auto_order_action'] = __('Set XML Order Type Automatic', WC_Ups_PickUps::TEXT_DOMAIN);
            }
            if($order->get_meta('ups_xml_order_type') !== 'FD') {
                $actions['wc_ups_xml_fd_order_action'] = __('Set XML Order Type: Full Delivery', WC_Ups_PickUps::TEXT_DOMAIN);
            }
            if($order->get_meta('ups_xml_order_type') !== 'PD') {
                $actions['wc_ups_xml_pd_order_action'] = __('Set XML Order Type: Partial Delivery', WC_Ups_PickUps::TEXT_DOMAIN);
            }
        }
        return $actions;

    }

    /**
     *
     * Set Xml Order Type Auto
     *
     * @param \WC_Order $order
     * @return mixed
     *
     * @since 1.10.0
     */
    public function setXmlOrderTypeAuto($order){
        $order->delete_meta_data('ups_xml_order_type' );
    }

    /**
     *
     * Set Xml Order Type Partial Delivery
     *
     * @param \WC_Order $order
     * @return mixed
     *
     * @since 1.10.0
     */
    public function setXmlOrderTypePd($order){
        $order->update_meta_data('ups_xml_order_type', 'PD');
    }

    /**
     *
     * Set Xml Order Type Full Delivery
     *
     * @param \WC_Order $order
     * @return mixed
     *
     * @since 1.10.0
     */
    public function setXmlOrderTypeFd($order){
        $order->update_meta_data('ups_xml_order_type', 'FD' );
    }

    /**
     * Show Pickup Point on Admin Order Page
     *
     * @param $item_id
     * @param $item
     * @since 1.10.6
     */
    public function showPickupPointOnAdminOrderPage($item_id, $item) {
        $order = $item->get_order();
        if($item->get_type() === 'shipping' && $this->helper->isPickupUps($item->get_method_id())){
            if ($pkps_order = $this->helper->getOrderPickupPointJson($order)) {
                include_once(realpath(dirname(__FILE__) . '/..') . '/admin/templates/pickup-location-html.php');
            }
        }
    }

    /**
     * @param $orderId
     * @return string
     * @since 2.0.0
     */
    private function getColumnOrderWeightContent($orderId){
        $order = wc_get_order($orderId);
        $orderWeight = $this->helper->getOrderWeight($order);
        return $orderWeight ? $orderWeight.'Kg' : '';
    }

    /**
     * @param $orderId
     * @return string
     * @since 2.0.0
     */
    private function getColumnLeadIdContent($orderId){
        $order = wc_get_order($orderId);
        return $this->helper->getOrderLeadId($order);
    }

    /**
     * @param $orderId
     *
     * @since 2.4.0
     */
    private function getColumnWBStatusContent($orderId){
        $order = wc_get_order($orderId);
        $output = '<strong>'.$this->helper->getOrderWBStatus($order).'</strong>';
        if($date = $this->helper->getOrderWBStatusTime($order, 'date')){
            $output .= '<br><small>הסטטוס נמשך לאחרונה ב: '.$this->helper->getOrderWBStatusTime($order, 'datetime').'</small>';
        }

        return $output;
    }

    /**
     * @return mixed
     *
     * @since 2.4.0
     */
    public function getWaybillStatus()
    {
        $ajax = new Ajax();

        return $ajax->getWaybillStatus();
    }
}
