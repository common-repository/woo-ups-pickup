<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly

use Ups\Helper\Ups;

class WC_UPS_Order_Print
{

    // WC_UPS_Domestic_Print The single instance of the class

    protected static $_instance = null;

    //Default properties

    public static $plugin_url;
    public static $plugin_path;
    public static $plugin_basefile;
    public static $plugin_basefile_path;
    public static $plugin_text_domain;

    /**
     * @var Ups
     */
    protected $helper;

    private function define_constants()
    {

        self::$plugin_basefile_path = WC_UPS_BASE_FILE_PATH;
        self::$plugin_basefile = plugin_basename(__FILE__);
        self::$plugin_url = plugin_dir_url(self::$plugin_basefile);
        self::$plugin_path = trailingslashit(dirname(self::$plugin_basefile_path));
        self::$plugin_text_domain = trim(dirname(self::$plugin_basefile));
    }

    // WC_UPS_Domestic_Print - Main instance

    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    //WC_UPS_Domestic_Print constructor.

    public function __construct()
    {
        if ($this->checkOldPluginActivated()) {
            return ;
        }
        $this->define_constants();

        $this->helper = new Ups();

        register_activation_hook(self::$plugin_basefile_path, array($this, 'ups_print_on_activation'));
        //load admin scripts
        if (is_admin()) {
            add_action('wp_ajax_ups_woocommerce_printwb', array($this, 'ups_woocommerce_printwb'));
            //add_action('admin_init', array($this, 'ups_woocommerce_download'));
        }
        // Add 'WB' column in 'Orders' admin page
//			add_filter('manage_edit-shop_order_columns', array ($this,'ups_print_WB_addColumn'));

        // Add printed status to combo box in order view
        add_filter('wc_order_statuses', array($this, 'ups_print_add_awaiting_shipment_to_order_statuses'));

        // Add 'Printed' count label in 'Orders' admin page
        add_action('init', array($this, 'ups_print_register_awaiting_shipment_order_status'));

        // Add 'Print Failed' status to combo box in order view
        add_filter('wc_order_statuses', array($this, 'ups_print_add_failed_order_statuses'));

        // Add 'Print Failed' count label in 'Orders' admin page
        add_filter('init', array($this, 'ups_print_register_failed_order_statuses'));

        // Handle translation
//            add_action('plugins_loaded', array($this, 'ups_print_load_textdomain'));

        //Add button style
        add_filter('woocommerce_admin_order_actions_end', array($this, 'ups_print_add_button'));
        add_action('admin_enqueue_scripts', array($this, 'ups_print_load_custom_wp_admin_style'));
        add_action('init', array($this, 'wuspo_update_tracking_in_DB'));
        add_action('woocommerce_email_order_details', array($this, 'addTrackingNumberToEmail'), 100, 4);
        add_filter('woocommerce_email_order_meta_fields', array($this, 'add_pickup_location_to_email'), 10, 3);
    }

    public function ups_print_load_custom_wp_admin_style()
    {
        wp_register_style('ups_print_css', plugin_dir_url(__FILE__) . 'css/ups-print-style.css', false, '1.2.1');
        wp_enqueue_style('ups_print_css');
    }

    public function ups_print_on_activation()
    {
        //Check Woocommerce
        if (!class_exists('woocommerce')) {
            deactivate_plugins(WC_UPS_PLUGIN_DIR);
            wp_die(__('Sorry, you need to activate woocommerce first.', WC_Ups_PickUps::TEXT_DOMAIN));
        }
        //Load localization files
        //Add in MySQL column 'WB'
        global $wpdb;

        $woocom_table = $wpdb->prefix . 'woocommerce_order_items';

        $wpdb->query("SHOW COLUMNS FROM `$woocom_table` LIKE 'wb'");
        $checkColumn = $wpdb->num_rows;
        if (!$checkColumn) {
            $wpdb->query("ALTER TABLE $woocom_table ADD wb varchar(255) NOT NULL DEFAULT 0");
        }
        return true;

    }

    // add button to orders page
    public function ups_print_add_button()
    {
        global $post;
        echo '<a class="button tips ups-print ups-print-icon" data-tip="Print Only Tnis Order: # ' . $post->ID . ' !" alt="WB-Print-Label" data-orderid="' . $post->ID . '">UPS</a>';
    }

    public function ups_print_load_textdomain()
    {
        // Load language files from the wp-content/languages/plugins folder
        $mo_file = WP_LANG_DIR . '/plugins/' . self::$plugin_text_domain . '-' . get_locale() . '.mo';
        if (is_readable($mo_file)) {
            load_textdomain(self::$plugin_text_domain, $mo_file);
        }
        // Otherwise load them from the plugin folder
        load_plugin_textdomain(self::$plugin_text_domain, false, dirname(self::$plugin_basefile) . '/languages/');
    }


    // Add 'WB' column in 'Orders' admin page
    public function ups_print_WB_addColumn($columns)
    {
        $columns['shipping_wb'] = 'WB';

        return $columns;
    }

    // Add count label in 'Orders' admin page
    public function ups_print_register_awaiting_shipment_order_status()
    {
        register_post_status('wc-printed', array(
            'label' => __('Printed', WC_Ups_PickUps::TEXT_DOMAIN),
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Printed <span class="count">(%s)</span>', 'Printed <span class="count">(%s)</span>', WC_Ups_PickUps::TEXT_DOMAIN)
        ));
    }

    // Add 'Printed' status to combo box in order view
    public function ups_print_add_awaiting_shipment_to_order_statuses($order_statuses)
    {

        $order_statuses['wc-printed'] = __('Printed', WC_Ups_PickUps::TEXT_DOMAIN);

        return $order_statuses;

    }

    // Add 'Print Failed' status to combo box in order view
    public function ups_print_add_failed_order_statuses($order_statuses)
    {
        $order_statuses['wc-printfailed'] = _x('Print Failed', 'WooCommerce Order status', WC_Ups_PickUps::TEXT_DOMAIN);
        return $order_statuses;
    }

    // Add 'Print Failed' count label in 'Orders' admin page
    public function ups_print_register_failed_order_statuses()
    {
        register_post_status('wc-printfailed', array(
            'label' => _x('Print Failed', 'WooCommerce Order status', WC_Ups_PickUps::TEXT_DOMAIN),
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Print Failed (%s)', 'print failed (%s)', WC_Ups_PickUps::TEXT_DOMAIN)
        ));
    }


    public function wuspo_update_tracking_in_DB()
    {
        if (!empty($_GET['orderid']) && $_GET['trackingid'] && $_GET['status']) {
            ob_clean();

            global $wpdb;
//                global $woocommerce;

            $woocom_table = $wpdb->prefix . 'woocommerce_order_items';
            $orderid = intval($_GET['orderid']);
            $trackingid = sanitize_text_field($_GET['trackingid']);
            $status = sanitize_text_field($_GET['status']);

            $statuses = wc_get_order_statuses();
            $result = $wpdb->update($woocom_table,
                array('wb' => $trackingid),
                array('order_id' => $orderid),
                array('%s'), array('%d'));

            $order = new WC_Order($orderid);
            print_r($order->status);
            $order->update_status($status, 'Update status to ' . $statuses[$orderid] . ' for tracking number ' . $trackingid);
            $order->save();

            $update_res = array();
            $update_res['success'] = ($result === false) ? 'false' : 'true';
            $update_res['orderid'] = $orderid;
            $update_res['wb'] = $trackingid;
            $update_res['status'] = $statuses[$status];
            $update_res['last_error'] = ($result === false) ? $wpdb->last_error : '';

            $update_res_json = json_encode($update_res, JSON_PRETTY_PRINT);


            echo $update_res_json;
            echo json_encode($statuses, JSON_PRETTY_PRINT);
            http_response_code(201);
            exit;
        }
    }

    public function ups_woocommerce_printwb()
    {
        if (!isset($_POST['ids']) || $_POST['ids'] == '') {
            echo 'Sorry, something went wrong!';
            wp_die();
        }

        $ids = isset($_POST['ids']) ? (array)$_POST['ids'] : array();
        $ids = array_map(null, $ids);

        $Orders = array();
        $i = 1;
        foreach ($ids as $id) {

            $total_item_amount = 0;
            $numberOfpackages = 1;

            $the_order = wc_get_order($id);
            $jsondata = "";
            foreach ($the_order->get_shipping_methods() as $shipping_item) {

                if ($this->helper->isPickupUps($shipping_item['method_id'])) {
                    /**
                     * get json from order meta
                     * if orders placed before plugin update (Version 1.5.0), we check if json is in the item
                     */
                    $jsondata = $the_order->get_meta('pkps_json') ?: wc_get_order_item_meta($id, 'pkps_json');
                }
            }

            $the_order = new WC_Order($id);
            // Calculate weight
            if (sizeof($the_order->get_items()) > 0) {
                $weight = 0;
                foreach ($the_order->get_items() as $item) {
                    if ($item['product_id'] > 0) {
                        $_product = $the_order->get_product_from_item($item);
                        if (!$_product->is_virtual()) {
                            $weight += $_product->get_weight() * $item['qty'];
                        }
                    }
                } // foreach
            } // if

            if ($the_order->get_meta('_shipping_first_name') == '') {
                $billing_first_name = $the_order->get_meta('_billing_first_name');
                $billing_last_name = $the_order->get_meta('_billing_last_name');
                $billing_company = $the_order->get_meta('_billing_company');
                $billing_address = $the_order->get_meta('_billing_address_1');
                $billing_address2 = $the_order->get_meta('_billing_address_2');
                $billing_city = $the_order->get_meta('_billing_city');
                $billing_postcode = $the_order->get_meta('_billing_postcode');
                $billing_country = $the_order->get_meta('_billing_country');
                $billing_state = $the_order->get_meta('_billing_state');
                $billing_email = $the_order->get_meta('_billing_email');
                $billing_phone = $the_order->get_meta('_billing_phone');
            } else {
                $billing_first_name = $the_order->get_meta('_shipping_first_name');
                $billing_last_name = $the_order->get_meta('_shipping_last_name');
                $billing_address = $the_order->get_meta('_shipping_address_1');
                $billing_address2 = $the_order->get_meta('_shipping_address_2');
                $billing_city = $the_order->get_meta('_shipping_city');
                $billing_postcode = $the_order->get_meta('_shipping_postcode');
                $billing_country = $the_order->get_meta('_shipping_country');
                $billing_state = $the_order->get_meta('_shipping_state');
                $billing_email = $the_order->get_meta('_shipping_email');
                $billing_phone = $the_order->get_meta('_shipping_phone');

                if ($billing_phone == '')
                    $billing_phone = $the_order->get_meta('_billing_phone');
            }
            $Orders['Orders'][$i]['ConsigneeAddress']['City'] = $billing_city;
            $Orders['Orders'][$i]['ConsigneeAddress']['ContactName'] = $billing_first_name . ' ' . $billing_last_name;
            $Orders['Orders'][$i]['ConsigneeAddress']['HouseNumber'] = $billing_address;
            $Orders['Orders'][$i]['ConsigneeAddress']['PhoneNumber'] = $billing_phone;
            $Orders['Orders'][$i]['ConsigneeAddress']['Street'] = $billing_address2;
            $Orders['Orders'][$i]['ConsigneeAddress']['Email'] = $billing_email;
            $Orders['Orders'][$i]['PKP'] = $jsondata;
            $Orders['Orders'][$i]['OrderID'] = $id;
            $Orders['Orders'][$i]['Weight'] = $weight;
            $Orders['Orders'][$i]['NumberOfPackages'] = $numberOfpackages;//$numberOfpackages; Allways one
            $i++;
        }

        $json = json_encode($Orders, JSON_UNESCAPED_UNICODE);
        print_r($json);
        $file = self::$plugin_path . 'orders.ship';

        // Delete any previous file
        if (file_exists($file)) {
            unlink($file);
        }
        //Write file with to server
        file_put_contents($file, $json);
    }

    public function ups_woocommerce_download()
    {
        if (!empty($_GET['filename'])) {

            $name_pre = 'orders';
            $name_end = 'ship';
            $name = (self::$plugin_path . $name_pre . '.' . $name_end);

            header('Content-Description: File Transfer');
            header('Content-Type: application/force-download');
            header("Content-Disposition: attachment; filename=\"" . $name_pre . filesize($name) . '_' . date('d_m_Y__H_i_s') . '.' . $name_end . "\";");
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            //header('Content-Length: ' . filesize($name));


            if (ob_get_contents()) ob_end_clean();
            flush();
            readfile($name); //showing the path to the server where the file is to be download
            unlink($name); //delete file from server
            exit;
        }
    }

    public function checkOldPluginActivated()
    {
        return class_exists('WC_UPS_Domestic_Print');
    }

    /**
     * @param \WC_Order $order
     * @param bool $sent_to_admin
     * @param bool $plain_text
     * @param \WC_Email $email
     */
    public function addTrackingNumberToEmail($order, $sent_to_admin, $plain_text, $email)
    {
        $settings = get_option('woocommerce_woo-ups-pickups_settings');
        if (!(isset($settings['send_tracking_number'])) || $settings['send_tracking_number'] !== 'yes') {
            return;
        }

        if (!$email || $email->id !== 'customer_completed_order') {
            return;
        }

        $shipmentNumber = $order->get_meta('ups_ship_number');
        if (!$shipmentNumber) {
            return;
        }

        $template = __DIR__ . '/templates/email/tracking-number.php';
        if (is_file($template)) {
            include $template;
        }
    }

    /**
     * Add order meta to email templates.
     *
     * @param $fields
     * @param bool $sent_to_admin (default: false)
     * @param mixed $order
     * @return mixed
     * @since 1.9.0
     */
    public function add_pickup_location_to_email($fields, $sent_to_admin, $order)
    {
        $this->helper->switchTranslation();
        $pick_ups_location = $this->helper->getOrderPickupPointJson($order);

        $filesystem = new \Ups\Filesystem();
        $filesystem->writeLog('add_pickup_location_to_email'.json_encode($pick_ups_location));

        if ($pick_ups_location !== '') {
            if($pick_ups_location === null){
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

                $pick_ups_location = $this->helper->getOrderPickupPointJson($order);
            }
            $fields['pickup_location_point'] = array(
                'label' => $this->helper->getCustomerEmailTitle(),
                'type' => 'text',
                'value' => $this->helper->get_formatted_address_helper($pick_ups_location, true),
            );
        }
        return $fields;
    }
}

add_action('plugins_loaded', 'ups_order_print_init');
function ups_order_print_init()
{
    // Global for backwards compatibility
    $GLOBALS['wuspo'] = WC_UPS_Order_Print::instance();;
}

