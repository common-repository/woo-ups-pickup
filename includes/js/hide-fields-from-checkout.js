

jQuery(document).ready(function($){

    $( document.body ).on( 'updated_checkout', function() {

    var methods = new Array();

    $.each( $( '.shipping_method' ), function( index, el ) {

        if ( 'select' == $( el ).prop( 'tagName' ).toLowerCase() ) {

            methods.push( $( el ).val() );

        } else if ( 'input' == $( el ).prop( 'tagName' ).toLowerCase() && 'hidden' == $( el ).prop( 'type' ).toLowerCase() ) {

            methods.push( $( el ) .val() );

        } else if ( 'input' == $( el ).prop( 'tagName' ).toLowerCase() && 'radio' == $( el ).prop( 'type' ).toLowerCase() ) {

            if ( $( el ).is( ':checked' ) ) {

                methods.push( $( el ).val() );

            }

        }

    } );



    // local pickup plus only for shipping methods?

    var localPickupPlusOnly = true;

    $.each( methods, function( index, method ) {

        if ( 'woo-ups-pickups' != method ) {

            localPickupPlusOnly = false;

            return false; // break the loop

        }

    } );



    if ( localPickupPlusOnly /*&& ! $( '#ship-to-different-address-checkbox' ).prop( 'checked' )*/ ) {

        // only local pickup plus is being used, hide the shipping address fields

        //$( '#shiptobilling, #ship-to-different-address' ).hide();

        //$( '#shiptobilling, #ship-to-different-address' ).parent().find( 'h3' ).hide();

        //$( '.shipping_address' ).hide();

        $('.col-1').toggleClass('col-1 col');

        $('.col-2').hide();

        //$('.col-2,#billing_postcode_field,#billing_state_field,#billing_city_field,#billing_country_field,#billing_address_1_field,#billing_address_2_field,#billing_company_field').removeClass();​​​​​

        //$('.col-2,#billing_postcode_field,#billing_state_field,#billing_city_field,#billing_country_field,#billing_address_1_field,#billing_address_2_field,#billing_company_field').hide();


    } else {

        // some other shipping method is being used, show the shipping address fields

       // $( '#shiptobilling, #ship-to-different-address' ).show();

       // $( '#shiptobilling, #ship-to-different-address' ).parent().find( 'h3' ).show();

        $('.col').toggleClass('col col-1');

        $('.col-2,#billing_postcode_field,#billing_state_field,#billing_city_field,#billing_country_field,#billing_address_1_field,#billing_address_2_field,#billing_company_field').show();

        if ( ( $( '#shiptobilling input' ).length > 0 && ! $( '#shiptobilling input' ).is( ':checked' ) ) || $( '#ship-to-different-address input' ).is( ':checked' ) ) {

            $( '.shipping_address' ).show();

        }


    }

} );
});
