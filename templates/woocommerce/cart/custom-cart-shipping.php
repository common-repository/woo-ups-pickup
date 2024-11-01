<?php
/**
 * Shipping Methods Display
 *
 * In 2.1 we show methods per package. This allows for multiple methods per order if so desired.
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/cart/cart-shipping.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce/Templates
 * @version 3.6.0
 */

defined( 'ABSPATH' ) || exit;

$formatted_destination    = isset( $formatted_destination ) ? $formatted_destination : WC()->countries->get_formatted_address( $package['destination'], ', ' );
$has_calculated_shipping  = ! empty( $has_calculated_shipping );
$show_shipping_calculator = ! empty( $show_shipping_calculator );
$calculator_text          = '';
?>
<tr class="woocommerce-shipping-totals shipping">
	<th><?php echo wp_kses_post( $package_name ); ?></th>
	<td data-title="<?php echo esc_attr( $package_name ); ?>">
		<?php if ( $available_methods ) : ?>

        <?php
        if ( 1 === count( $available_methods ) ) :

            $method = current( $available_methods );

            echo wp_kses_post( wc_cart_totals_shipping_method_label( $method ) );
            ?>
            <input type="hidden" name="shipping_method[<?php echo esc_attr( $index ); ?>]"
                   data-index="<?php echo esc_attr( $index ); ?>"
                   id="shipping_method_<?php echo esc_attr( $index ); ?>"
                   value="<?php echo esc_attr( $method->id ); ?>" class="shipping_method"/>

        <?php else : ?>

            <?php
            $closestPointsShippingArray = [];
            $closestPointsShippingOptions = '';
            $dropdownSelected = '';
            foreach ( $available_methods as $key => $method ) :
                if(strpos($method->id, 'woo-ups-pickups-') === false){
                    continue;
                }

                $closestPointsShippingArray[] = $method->id;

                $selected = selected( $method->id, $chosen_method, false ) ?: '';
                if($selected){
                    $dropdownSelected = 'checked="checked"';
                }
                $closestPointsShippingOptions .= '<option value="'.esc_attr( $method->id ).'" '.$selected.'>'.wp_kses_post( wc_cart_totals_shipping_method_label( $method ) ).'</option>';

            endforeach; ?>

            <?php if($closestPointsShippingOptions):

                $selectIndex = 'ups_'.$index;
                ?>
                <?php
                printf( '<input type="radio" name="shipping_method[%1$s]" id="shipping_method_closest_points" value="ups_closest_points_select" class="shipping_method" %2$s />', $selectIndex, $dropdownSelected ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                printf( '<label for="shipping_method_closest_points" style="margin-left: 5px;">%1$s</label>', 'נקודות איסוף קרובות לכתובת' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                ?>

                <select name="shipping_method[<?php echo esc_attr( $index ); ?>]"
                        data-index="<?php echo esc_attr( $index ); ?>"
                        id="shipping_method_<?php echo esc_attr( $index ); ?>" class="shipping_method">
                        <option value="" <?= $selected ? '' : 'selected'; ?> disabled>בחר נקודה לאיסוף</option>
                        <?= $closestPointsShippingOptions ?>
                </select>
            <?php endif; ?>
            <ul id="shipping_method" class="woocommerce-shipping-methods">
                <?php foreach ( $available_methods as $method ) :

                    $hideOption = in_array($method->id, $closestPointsShippingArray) ? 'style="display: none !important;"' : '';
                    ?>
                    <li <?= $hideOption ?>>
                        <?php
                        if ( 1 < count( $available_methods ) ) {
                            printf( '<input type="radio" name="shipping_method[%1$d]" data-index="%1$d" id="shipping_method_%1$d_%2$s" value="%3$s" class="shipping_method" %4$s />', $index, esc_attr( sanitize_title( $method->id ) ), esc_attr( $method->id ), checked( $method->id, $chosen_method, false ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        } else {
                            printf( '<input type="hidden" name="shipping_method[%1$d]" data-index="%1$d" id="shipping_method_%1$d_%2$s" value="%3$s" class="shipping_method" />', $index, esc_attr( sanitize_title( $method->id ) ), esc_attr( $method->id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        }
                        printf( '<label for="shipping_method_%1$s_%2$s">%3$s</label>', $index, esc_attr( sanitize_title( $method->id ) ), wc_cart_totals_shipping_method_label( $method ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        do_action( 'woocommerce_after_shipping_rate', $method, $index );
                        ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

            <?php if ( is_cart() ) : ?>
				<p class="woocommerce-shipping-destination">
					<?php
					if ( $formatted_destination ) {
						// Translators: $s shipping destination.
						printf( esc_html__( 'Shipping to %s.', 'woocommerce' ) . ' ', '<strong>' . esc_html( $formatted_destination ) . '</strong>' );
						$calculator_text = esc_html__( 'Change address', 'woocommerce' );
					} else {
						echo wp_kses_post( apply_filters( 'woocommerce_shipping_estimate_html', __( 'Shipping options will be updated during checkout.', 'woocommerce' ) ) );
					}
					?>
				</p>
			<?php endif; ?>
			<?php
		elseif ( ! $has_calculated_shipping || ! $formatted_destination ) :
			echo wp_kses_post( apply_filters( 'woocommerce_shipping_may_be_available_html', __( 'Enter your address to view shipping options.', 'woocommerce' ) ) );
		elseif ( ! is_cart() ) :
			echo wp_kses_post( apply_filters( 'woocommerce_no_shipping_available_html', __( 'There are no shipping options available. Please ensure that your address has been entered correctly, or contact us if you need any help.', 'woocommerce' ) ) );
		else :
			// Translators: $s shipping destination.
			echo wp_kses_post( apply_filters( 'woocommerce_cart_no_shipping_available_html', sprintf( esc_html__( 'No shipping options were found for %s.', 'woocommerce' ) . ' ', '<strong>' . esc_html( $formatted_destination ) . '</strong>' ) ) );
			$calculator_text = esc_html__( 'Enter a different address', 'woocommerce' );
		endif;
		?>

		<?php if ( $show_package_details ) : ?>
			<?php echo '<p class="woocommerce-shipping-contents"><small>' . esc_html( $package_details ) . '</small></p>'; ?>
		<?php endif; ?>

		<?php if ( $show_shipping_calculator ) : ?>
			<?php woocommerce_shipping_calculator( $calculator_text ); ?>
		<?php endif; ?>
	</td>
</tr>
<script>
    jQuery(document).ready(function(){
        const $selectShippingMethod = jQuery('select.shipping_method');
        const $radioShippingMethod = jQuery('input[type="radio"].shipping_method');
        const $shippingMethodClosestPoints = jQuery('#shipping_method_closest_points');
        $selectShippingMethod.on('change', function () {
            jQuery("input[name='shipping_method[" + jQuery(this).attr('data-index') + "]'][value='" + jQuery(this).val() + "']").attr('checked', 'checked');

            $radioShippingMethod.prop('checked', false);
            $shippingMethodClosestPoints.prop('checked', true);
        })

        $radioShippingMethod.on('change', function () {
            if (jQuery(this).val() === 'ups_closest_points_select') {
                $selectShippingMethod.val(jQuery('select.shipping_method option:nth-child(2)').val()).trigger('change');
            } else {
                $shippingMethodClosestPoints.prop('checked', false);
            }
        })
    });
</script>
<style>
    .woocommerce-shipping-totals select.shipping_method {
        max-width: 220px;
    }
</style>