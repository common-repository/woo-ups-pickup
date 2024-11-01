<?php
/**
 * @category UPS
 * @copyright UPS Company
 */
namespace Ups\Admin;

use Ups\Order\Api;
use Ups\Helper\Ups;

class Ajax
{
    public function sendOrderToUps()
    {
        $orderId = isset($_GET['order_id']) ? $_GET['order_id'] : null;
        $redirectTo = $this->getRedirectUrl($orderId);

        if (!$orderId) {
            wp_redirect($redirectTo);
            exit;
        }

        $api = new Api();
        $api->sendOrders(array($orderId));

        wp_redirect($redirectTo);
        exit;
    }

    /**
     *  Send Order to Ups (Create Picking List)
     *
     * @since 1.8.0
     */
    public function sendPickingOrderToUps()
    {
        $orderIds = isset($_GET['order_ids']) ? $_GET['order_ids'] : null;
        $redirectTo = $this->getRedirectUrl($orderIds);

        if (!$orderIds) {
            wp_redirect($redirectTo);
            exit;
        }

        if (!is_array($orderIds)) {
            $orderIds = array($orderIds);
        }

        $api = new Api();
        $api->sendPickingOrders($orderIds);

        wp_redirect($redirectTo);
        exit;
    }

    /**
     *  Print Picking Labels
     *
     * @since 1.8.0
     */
    public function printPickingLabel()
    {
        $orderIds = isset($_GET['order_ids']) ? $_GET['order_ids'] : null;
        $redirectTo = $this->getRedirectUrl($orderIds);

        if (!$orderIds) {
            wp_redirect($redirectTo);
            exit;
        }

        if (!is_array($orderIds)) {
            $orderIds = array($orderIds);
        }

        $format = isset($_GET['format']) ? $_GET['format'] : null;

        $api = new Api();
        echo $api->printPickingLabel($orderIds, $format);
        exit;
    }

    /**
     *  Change Pickup Point For Order
     *
     * @since 1.6.0
     */
    public function changePickupPoint()
    {
        $orderId = isset($_GET['order_id']) ? $_GET['order_id'] : null;
        $redirectTo = $this->getRedirectUrl($orderId);

        if (!$orderId) {
            wp_redirect($redirectTo);
            exit;
        }

        $api = new Api();

        $api->changePickupPoint(array(['orderId' => $orderId, \WC_Shipping_Ups_PickUps_CPT::PICKUP_ORDER_METADATA_FIELD => $_POST['pickups_location2']]));

        wp_redirect($redirectTo);
        exit;
    }

    /**
     *
     * Send Order to UPS and than Print Label
     *
     * @param null $orderIdsAjax
     * @param null $formatAjax
     * @since 1.9.0
     */
    public function sendOrderAndPrintLabel($orderIdsAjax = null, $formatAjax = null){
        if(!$orderIds = $orderIdsAjax) {
            $orderIds = isset($_GET['order_ids']) ? $_GET['order_ids'] : null;
        }
        $redirectTo = $this->getRedirectUrl($orderIds);

        if (!$orderIds) {
            wp_redirect($redirectTo);
            exit;
        }
        if(!$format = $formatAjax) {
            $format = isset($_GET['format']) ? $_GET['format'] : null;
        }

        if (!is_array($orderIds)) {
            $orderIds = array($orderIds);
        }

        $orderIdsParams = http_build_query(array('order_ids' => $orderIds));

        $api = new Api();
        $api->sendOrders($orderIds);

        $printLabelUrl = admin_url('admin-ajax.php?action=ups_print_label&'. $orderIdsParams.'&format='.$format);
        echo '
        <script>
        window.onload = function(){
            const newWin = window.open("'.$printLabelUrl.'");             

            if(!newWin || newWin.closed || typeof newWin.closed=="undefined") 
            { 
                location.href = "'.$printLabelUrl.'";
            }
            
            setTimeout(function(){
                location.href = "'.$redirectTo.'";
            }, 1000)
        }
        </script>';
        exit;
    }

    public function printLabel()
    {
        $orderIds = isset($_GET['order_ids']) ? $_GET['order_ids'] : null;
        $redirectTo = admin_url('edit.php?post_type=shop_order');

        if (!$orderIds) {
            wp_redirect($redirectTo);
            exit;
        }
        $format = isset($_GET['format']) ? $_GET['format'] : null;

        if (!is_array($orderIds)) {
            $orderIds = array($orderIds);
        }

        $api = new Api();
        $api->printLabels($orderIds, $format);
        exit;
    }

    /**
     *
     * Get admin orders page url params
     *
     * @return string
     *
     * @since 1.9.0
     */
    private function getUrlParams(){
        $helper = new Ups();
        return $helper->getAdminOrdersUrlParams();
    }

    /**
     * Send Order as XML to FTP
     *
     * @since 1.10.0
     */
    public function createAndSendXml()
    {
        $orderId = isset($_GET['order_id']) ? $_GET['order_id'] : null;
        $redirectTo = $this->getRedirectUrl($orderId);

        if (!$orderId) {
            wp_redirect($redirectTo);
            exit;
        }

        $api = new Api();
        $api->sendOrdersAsXmlToFtp(array($orderId));

        wp_redirect($redirectTo);
        exit;
    }

    /**
     * @param $orderId
     * @return mixed
     *
     * @since 1.10.6
     */
    private function getRedirectUrl($orderId){
        if(is_array($orderId)){
            $orderId = $orderId[0];
        }
        $urlParams = $this->getUrlParams();
        $redirectPage = isset($_GET['order_page']) && $_GET['order_page'] === '1' && !is_array($orderId) ? 'post.php?post='.$orderId.'&action=edit' : 'edit.php?post_type=shop_order&scroll_to='.$orderId.$urlParams;

        return admin_url($redirectPage);
    }

    /**
     *  Import waybill from Lead Id
     *
     * @since 2.0.0
     */
    public function importWaybills()
    {
        $orderIds = isset($_GET['order_ids']) ? $_GET['order_ids'] : null;
        $redirectTo = $this->getRedirectUrl($orderIds);

        if (!$orderIds) {
            wp_redirect($redirectTo);
            exit;
        }

        if (!is_array($orderIds)) {
            $orderIds = array($orderIds);
        }

        $api = new Api();
        $api->importWaybills($orderIds);

        wp_redirect($redirectTo);
        exit;
    }

    /**
     * @since 2.4.0
     */
    public function getWaybillStatus(){
        $orderIds = isset($_GET['order_ids']) ? $_GET['order_ids'] : null;
        $redirectTo = $this->getRedirectUrl($orderIds);

        if (!$orderIds) {
            wp_redirect($redirectTo);
            exit;
        }

        if (!is_array($orderIds)) {
            $orderIds = array($orderIds);
        }

        $api = new Api();
        $api->getWaybillStatus($orderIds);

        wp_redirect($redirectTo);
        exit;
    }
}
