<?php
/**
 * @category UPS
 * @copyright UPS Company
 */
namespace Ups;

use Ups\Helper\Ups;
use Ups\Order\Api;

class App
{

    protected $helper;

    public function __construct()
    {
        $this->helper = Ups::getInstance();
        $this->registerHook();
    }

    public function registerHook()
    {
        add_action('woocommerce_thankyou', array($this, 'thankYouOrderPageActions'));

        if ($this->helper->isIntegrationActivated()) {
            add_action('woocommerce_order_status_changed', array($this, 'sendOrder'));
        }
    }

    /**
     * @param $order_get_id
     *
     * @since 2.0.0
     */
    public function thankYouOrderPageActions($order_get_id){
        $order = wc_get_order( $order_get_id );
        $this->addWeightToOrder($order);
        $this->addNumberOfPackagesToOrder($order);
        $this->savePickupsJsonOnClosestPoints($order);
        if ($this->helper->isIntegrationActivated()) {
            $this->sendOrder($order_get_id);
        }

        if($this->helper->isSaveOrderAsXmlEnabled()){
            $this->sendOrderAsXmlToFtp($order_get_id);
        }
    }

    /**
     *
     * @param $order
     * @since 2.3.0
     */
    private function savePickupsJsonOnClosestPoints($order){
        $pick_ups_location = $this->helper->getOrderPickupPointJson($order);

        if($pick_ups_location === null || $pick_ups_location === '') {
            $shippingMethods = $order->get_shipping_methods();
            foreach ($shippingMethods as $shippingMethod) {
                if ($this->helper->isPickupUps($shippingMethod->get_method_id()) && $shippingMethodMeta = $shippingMethod->get_meta_data()) {
                    $pkpsJson = json_decode($shippingMethodMeta[0]->value);
                    foreach($pkpsJson as &$item){
                        $item = str_replace("'","", $item);
                        $item = htmlspecialchars($item);
                    }
                    $pkpsJson = json_encode($pkpsJson, JSON_UNESCAPED_UNICODE);
                    $order->update_meta_data('pkps_json', $pkpsJson);
                    $order->save();
                }
            }
        }
    }

    /**
     * @param $order
     *
     * @since 2.0.0
     */
    private function addWeightToOrder($order){
        $defaultWeight = $this->helper->getOption('integration_order_weight_default');
        $order->update_meta_data('ups_order_weight', $defaultWeight);
    }

    /**
     * @param $order
     *
     * @since 2.4.0
     */
    private function addNumberOfPackagesToOrder($order){
        $customerType = get_option('pickups_integration_customer_type');

        if($customerType === Ups::CUSTOMER_TYPE_CREDIT){
            $shippingMethods = $order->get_shipping_methods();
            foreach ($shippingMethods as $shippingMethod) {
                if (!$this->helper->isPickupUps($shippingMethod->get_method_id())) {
                    $order->update_meta_data('ups_order_num_of_packages', 1);
                }
            }
        }
    }

    /**
     * @param int $orderId
     * @return int
     */
    public function sendOrder($orderId)
    {
        $order = wc_get_order($orderId);

        if (!$order->get_meta('ups_sync_flag')
            && $this->helper->isAutomaticMode()
            && $this->helper->isAllowedStatus($order->get_status())) {
            $api = new Api();
            $api->sendOrder($order);
        }
        return $orderId;
    }

    /**
     * Automatic Send Order as XML to FTP
     *
     * @param $orderId
     * @return mixed
     * @since 1.10.0
     */
    public function sendOrderAsXmlToFtp($orderId){
        $order = wc_get_order($orderId);

        if (!$order->get_meta('ups_xml_sent')
            && $this->helper->isSendXmlAutomaticMode()) {
            $api = new Api();
            $api->sendOrdersAsXmlToFtp(array($orderId));
        }
        return $orderId;
    }
}
