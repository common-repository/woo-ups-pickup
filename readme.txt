=== OPSI Israel Domestic Shipments ===
Donate link:
Tags: UPS, UPS Shipping, UPS API,Shipping rates, shipping method, shipping extension,calculator,shipping calculator, tracking, postage, Shipping, WooCommerce
Requires at least: 3.0.1
Tested up to: 6.4.3
Stable tag: 2.6.3
License: GPLv3 or later
License URI:  http://www.gnu.org/licenses/gpl-3.0.html
UPS Israel PickUP Access Points (Stores and Lockers) for WooCommerce. Displays Live Shipping Rates based on the Shipping Address and Cart Content.



== Introduction ==



UPS Israel PickUP Access Points (Stores and Lockers) plugin helps WooCommerce based stores to streamline UPS shipping integration. This plugin helps you to get shipping rates from UPS APIs based on product weight, post code and other relevant details. Based on the postal codes and other parameters, all available shipping services along with the rates are listed for the customers to choose from.



== Description ==



A shipping plugin for WooCommerce that allows the store operator to show local pickup locations and allows the customer to choose the place where to take a purchase.

Appearance of the map can be seen here: https://www.pickuppoint.co.il/#pointsPickup

For this, we use API Google Maps which is located on our server: https://dev.pick-ups.co.il/ or https://pick-ups.co.il



== Integrates WooCommerce to UPS ==



Once this plugin is installed and configured with necessary information (please visit installation section for more info), your WordPress/WooCommerce Shop will be ready to ship using UPS. This plugin will add UPS shipping method as one of the shipping methods in WooCommerce.



== Calculate shipping rates dynamically ==



While checking out, a customer is presented with the available shipping services and the rates based on his/her postal code, product weight and dimensions. Customer can choose the best method that matches his/her requirements and proceed to payment.





== Installation ==



1. Upload the plugin folder to the /wp-content/plugins/ directory.



2. Activate the plugin through the Plugins menu in WordPress.



3. That's all, now  you can configure the plugin.



== Frequently Asked Questions ==



Q: How do I choose the delivery point?

A: To select the nearest delivery point, plugin uses a map. You can see all delivery points by pressing the button "PickUP access Point" on the checkout page. The resulting script will show the delivery points on the map, clicking on which will select the one you prefer.



Q: I pressed the button, but on the map I don't see any delivery points?

A: On the right side on the pop up screen with a map you can find a list of delivery points and choose one that you want from it.



Q: Can I connect this button to my site without installing the plugin

A: Yes, more about this here: https://pickuppoint.co.il/Documentation/



Q: Hey! I live in Antarctica, where can I take my order?

A: Great! You can take your order by choosing one of our pickup locations which are all in Israel. :)



== Screenshots ==


== Changelog ==

= 2.6.3 =
* API Token url fix

= 2.6.2 =
* HPOScompatibility bugfix

= 2.6.1 =
* Add compatibility for HPOS

= 2.6.0 =
* Version compatibility for 8.6.1 and wordpress 6.4.3
* Add debug option for API calls

= 2.5.8 =
* Send shipping address when create waybill

= 2.5.7 =
* change name

= 2.5.6 =
* send home number when creating waybill

= 2.5.4 =
* fix token cache issue

= 2.5.3 =
* fix service token

= 2.5.2 =
* Bugfix for Latest WP & WC versions

= 2.5.1 =
* Closest Points shipping classes logic add to price
* Add option for floor / room for shipping information
* Add additional UPS params for delivery

= 2.4.4 =
* Fix bugs in closest points and send to ups

= 2.4.3 =
* Add option to change the closest points design from radio buttons to dropdown

= 2.4.2 =
* Fix session_start warning for php8

= 2.4.1 =
* Fix session_start not closed error

= 2.4.0 =
* Customer type integration is added
* Create more than 1 package when create new WB
* Closest points customer cache will be cleaned if the settings was updated
* Bugfix when using bulk actions after bulk actions

= 2.3.1 =
* Change RoomNumber param to int

= 2.3.0 =
* Added closest points option for the customer to choose one of recommended closest points
* Changes in param that sent to UPS while creating new waybill

= 2.2.0 =
* Added admin option to change order status after print label
* Remove old print label system (print wb labels)
* Auto scroll to order when page refresh after click on orders ups points

= 2.1.0 =
* Fix thank you page bug
* Open map only if pickup point not selected
* Removed all SOAP Integration
* Added admin option for open map on load/change in checkout page
* Added logs to API Requests and Responses

= 2.0.0 =
* Change ups integration to REST Api
* Weight Order field is added
* Weight and Lead Id columns added in Admin orders grid
* Create waybill from lead id is added

= 1.10.6 =
* Added admin option to change pickup point error validation message
* Show selected pickup point on admin order page
* Added ContactEmail field to ConsigneeAddress Object that send to ups
* Added all UPS order actions inside admin order page

= 1.10.5 =
* Added OnClick event on "Choose PickUp Point" image in checkout page
* Changed deprecated jQuery functions
* Fix text quotes on send to ups actions

= 1.10.4 =
* Google Maps bugfix

= 1.10.3 =
* Added admin option for Google Maps Api Key
* Change upload to ftp function to run through Api

= 1.10.2 =
* removed unused functions
* add custom title for pick up point in customer email
* add admin option: Hide email shipping address for orders with pickup point

= 1.10.1 =
* after new order session bugfix

= 1.10.0 =

* Create & Print Bulk redirect now saves selected filters after redirect
* Create Order XML File and send to FTP with admin options

= 1.9.2 =

* Pickup point text in email bugfix

= 1.9.1 =

* Admin Orders Icons bugfix

= 1.9.0 =
* Moved helper function to Helper Class
* Mass Actions not sending pickup point to mail bugfix
* Added chosen pickup point on thank you page
* Added admin option to send order to ups and print label in one action
* Added admin option to create WB with new field Shipment Instructions
* After send to ups, redirect to last admin orders page location

= 1.8.0 =

* Added admin option to create and print picking list

= 1.7.0 =

* Added admin option to send custom field to UPS
* fix admin orders page bug: when send to ups after search, search is removed
* change admin orders Shipping Tracking link
* add Shipping Tracking Link to user account
* Fix n/a shipping class price 0 bug


= 1.6.0 =

* Added admin option to hide PickUps shipping method if country isn't Israel
* Added admin option to change order pickup point
* Added points field for each product
* Added admin option: Maximum Points Per Order, while Don't Split Shipment is checked if the total cart items pickup points is is more than the maximum, shipping method will be hidden

= 1.5.1 =

* send houseNumber when creating Waybill

= 1.5.0 =

* pkps_json is now saved on order instead of order_item, with fallback to order_item for orders that placed before this change
* Fix for json shows in order items in Admin Panel

= 1.3.8 =

* Added description of shipping method

= 1.3.7 =

* Remove cart_shipping_method_full_label

= 1.3.5 =

* Added filter on admin table for pickup button for the own shipping method only

= 1.3.4 =

* Fix compatablilty with Flexible Checkout Fields for WooCommerce addon - change order to 10000 in woocommerce_checkout_fields filter

== Changelog ==

= 1.3.1 =

* Minor bugs fixed

= 1.3.0 =

* Fixed for check out process

* Translation fixes

= 1.2.7 =



* Fixed a bug of saving pickup point within a database while ordering from mobile devices

* Translation fixes



= 1.2.6 =



* Some mimor bugs has been resolved



= 1.2.5 =



* Fix json_decode problem of parsing on PHP 7.1



= 1.2.4 =



* Resolved Using 'break' outside of a loop or switch structure is invalid on makepot.php



= 1.2.3 =



* Changed : is_woocommerce_active() to ups_is_woocommerce_active()



= 1.2.2 =



* Added : Possibility to remove the information about the delivery point after its selection

* Added : Minor interface changes





= 1.2.1 =



* Added: Selecting a delivery point is now required when ordering

* Fix: Changed references to the actual



= 1.2 =

* First release
