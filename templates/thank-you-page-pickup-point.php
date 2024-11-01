<?php do_action( 'ups_pickups_thank_you_page_before_pickup_point' ); ?>
<h2 class="woocommerce-order-details__title"><?= $pickupPointTitle ?></h2>

<table class="woocommerce-table woocommerce-table--order-details shop_table order_details">
    <tbody>
        <tr><td><?= $pickupPointHtml ?></td></tr>
    </tbody>
</table>
<?php do_action( 'ups_pickups_thank_you_page_after_pickup_point' ); ?>
