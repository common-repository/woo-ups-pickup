<?php
/**
 * @category UPS
 * @copyright UPS Company
 */
namespace Ups\Order\Grid\Column;

use Ups\Helper\Ups;
use Ups\Order\Api;
use Ups\Service\PrintService;

class Actions
{
    const COLUMN_ID = 'ups_actions';

    protected $helper;

    public function __construct()
    {
        $this->helper = new Ups();
    }

    /**
     * @param string $column
     * @param int $orderId
     */
    public function render($column, $orderId)
    {
        if ($column !== self::COLUMN_ID) {
            return;
        }

        $order = wc_get_order($orderId);
        $syncFlag = $order->get_meta('ups_sync_flag');
        $syncPickingFlag = $order->get_meta('ups_sync_picking_flag');
        $isOrderHasLeadId = $order->get_meta('ups_ship_number_lead_id');
        $urlParams = $this->helper->getAdminOrdersUrlParams();

        $html = '<div class="column-wc_actions">';
        if (!$syncFlag || $syncFlag == Api::STATUS_SEND_ERROR) {
            if ($syncFlag == Api::STATUS_SEND_ERROR) {
                $html .= '<p>' . $order->get_meta('ups_error_message') . '</p>';
            }

            /**
             * Add Change Pickup Point Button
             *
             * @since 1.6.0
             */
            if($this->isPickupUps($order)) {
                $changePickupPointUrl = admin_url('admin.php?page=change-pickup-point&order_id=' . $order->get_id());
                $html .= sprintf(
                    '<a href="%s" class="%s" aria-label="%3$s" title="%3$s">%3$s</a>',
                    $changePickupPointUrl,
                    implode(' ', array('button', 'wc-action-button', 'change-pickup-point')),
                    __('Change Pickup Point', \WC_Ups_PickUps::TEXT_DOMAIN)
                );
            }

            $sendToUpsUrl = admin_url('admin-ajax.php?action=ups_sync_order&order_id='. $order->get_id().$urlParams);
            $html .= sprintf(
                '<a href="%s" class="%s" aria-label="%3$s" title="%3$s">%3$s</a>',
                $sendToUpsUrl,
                implode(' ', array('button', 'wc-action-button', 'sync-to-ups')),
                __('Send To UPS', \WC_Ups_PickUps::TEXT_DOMAIN)
            );

            if($isOrderHasLeadId) {
                $html .= sprintf(
                    '<a href="%s" class="%s" aria-label="%3$s" title="%3$s">%3$s</a>',
                    admin_url('admin-ajax.php?action=ups_import_waybills&order_ids=' . $order->get_id() . $urlParams),
                    implode(' ', array('button', 'wc-action-button', 'import-waybill')),
                    __('Import Waybills', \WC_Ups_PickUps::TEXT_DOMAIN)
                );
            }

            if($this->helper->isSendAndPrintButtonsEnabled()) {
                $sendAndPrintLabelUrl = admin_url('admin-ajax.php?action=ups_send_and_print_label&order_ids=' . $order->get_id().$urlParams);
                $sendAndPrintLabelUrl = add_query_arg('format', PrintService::TYPE_A4, $sendAndPrintLabelUrl);
                $html .= sprintf(
                    '<a href="%s" class="%s" aria-label="%3$s" title="%3$s">%3$s</a>',
                    $sendAndPrintLabelUrl,
                    implode(' ', array('button', 'wc-action-button', 'sync-and-print-a4')),
                    __('Send Order & Print Label A4', \WC_Ups_PickUps::TEXT_DOMAIN)
                );

                $sendAndPrintLabelUrl = admin_url('admin-ajax.php?action=ups_send_and_print_label&order_ids=' . $order->get_id().$urlParams);
                $sendAndPrintLabelUrl = add_query_arg('format', PrintService::TYPE_THERMAL, $sendAndPrintLabelUrl);
                $html .= sprintf(
                    '<a href="%s" class="%s" aria-label="%3$s" title="%3$s">%3$s</a>',
                    $sendAndPrintLabelUrl,
                    implode(' ', array('button', 'wc-action-button', 'sync-and-print-thermal')),
                    __('Send Order & Print Label Thermal', \WC_Ups_PickUps::TEXT_DOMAIN)
                );
            }
        } elseif ($syncFlag == Api::STATUS_SEND_SUCCESS) {

            if($this->helper->isPickingIntegrationActivated() && $order->get_meta('ups_ship_number')) {
                if ($order->get_meta('ups_sync_picking_flag') == Api::STATUS_SEND_SUCCESS) {
                    $pickingUrl = admin_url('admin-ajax.php?action=ups_picking_print_label&order_ids=' . $order->get_id());
                    $pickingUrl = add_query_arg('format', PrintService::TYPE_A4, $pickingUrl);
                    $html .= sprintf('<p><a href="%s" target="_blank" class="print-button">%s</a></p>', $pickingUrl, __('Print List & WB A4', \WC_Ups_PickUps::TEXT_DOMAIN));
                    $pickingUrl = add_query_arg('format', PrintService::TYPE_THERMAL, $pickingUrl);
                    $html .= sprintf('<p><a href="%s" target="_blank" class="print-button">%s</a></p>', $pickingUrl, __('Print List & WB Label', \WC_Ups_PickUps::TEXT_DOMAIN));
                } else {
                    if ($syncPickingFlag == Api::STATUS_SEND_ERROR) {
                        $html .= '<p style="font-weight: bold;">' . $order->get_meta('ups_picking_error_message') . '</p>';
                    }

                    $pickingUrl = admin_url('admin-ajax.php?action=ups_picking_send_order&order_ids=' . $order->get_id() . $urlParams);
                    $html .= sprintf('<p><a href="%s">%s</a></p>', $pickingUrl, __('Create Picking List', \WC_Ups_PickUps::TEXT_DOMAIN));
                }
            }

            $pdfUrl = admin_url('admin-ajax.php?action=ups_print_label&order_ids=' . $order->get_id());
            $pdfUrl = add_query_arg('format', PrintService::TYPE_A4, $pdfUrl);
            $html .= sprintf('<p><a href="%s" target="_blank" class="print-button">%s</a></p>', $pdfUrl, __('Print WB A4', \WC_Ups_PickUps::TEXT_DOMAIN));
            $pdfUrl = add_query_arg('format', PrintService::TYPE_THERMAL, $pdfUrl);
            $html .= sprintf('<p><a href="%s" target="_blank" class="print-button">%s</a></p>', $pdfUrl, __('Print WB Label', \WC_Ups_PickUps::TEXT_DOMAIN));

            if($this->helper->isGetWBStatusEnabled($order)) {
                $statusUrl = admin_url('admin-ajax.php?action=ups_get_wb_status&order_ids=' . $order->get_id());
                $html .= sprintf('<p><a href="%s">%s</a></p>', $statusUrl, __('Get WB Status', \WC_Ups_PickUps::TEXT_DOMAIN));
            }
        }

        if($this->helper->isSaveOrderAsXmlEnabled()) {
            if($order->get_meta('ups_xml_sent') == Api::STATUS_SEND_SUCCESS) {
                $html .= '<p style="font-weight: 500;color: #4bb543;">✔ Sent to ftp</p>';
            }elseif($order->get_meta('ups_xml_sent') != Api::STATUS_SEND_SUCCESS) {
                $sendToUpsUrl = admin_url('admin-ajax.php?action=ups_create_and_send_xml&order_id=' . $order->get_id() . $urlParams);
                $html .= sprintf(
                    '<a href="%s" class="%s" aria-label="%3$s" title="%3$s">%3$s</a>',
                    $sendToUpsUrl,
                    implode(' ', array('button', 'wc-action-button', 'send-xml-file')),
                    __('Create & Send XML', \WC_Ups_PickUps::TEXT_DOMAIN)
                );

                if ($upsXmlErrors = $order->get_meta('ups_xml_sent_errors')) {
                    $html .= '<p style="font-weight: bold;">Create XML Error: <br />' . $upsXmlErrors . '</p>';
                }
            }
        }

        $html .= '</div>';

        echo $html;
    }

    /**
     * Is Shipping Method is Pick Ups
     *
     * @since 1.6.0
     * @param $order
     * @return bool
     */
    private function isPickupUps($order){
        return $this->helper->isShippingMethodIsPickupUps($order);
    }
}
