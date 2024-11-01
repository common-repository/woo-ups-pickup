(function ($) {

    $.fn.serializeFormJSON = function () {

        var o = {};

        var a = this.serializeArray();

        $.each(a, function () {

            if (o[this.name]) {

                if (!o[this.name].push) {

                    o[this.name] = [o[this.name]];

                }

                o[this.name].push(this.value || '');

            } else {

                o[this.name] = this.value || '';

            }

        });

        return o;

    };

})(jQuery);
jQuery(document).ready(function($){
    $( document.body ).on( 'pickups-before-open', function() {

        var o = new Object();
        o.form = jQuery("form[name='checkout']").serializeFormJSON();
        o.location = new Object();
        o.location.city = o.form.billing_city;
        o.location.street = o.form.billing_street;
        var json = JSON.stringify(o);
        window.PickupsSDK.setDefaults(json);
    });
});

jQuery(document).ready(function($) {

    $(document.body).on('pickups-after-choosen', function (e) {

        const pkps_location = e.originalEvent.detail;

        jQuery("input[name=pickups_location2]").val(JSON.stringify(pkps_location));

        pickup_render_description(pkps_location);
        jQuery('#change-pickup-point-btn').show();
    });
});
function pickup_render_description(pkps_location){
    var html = "<br /><b>" + pkps_location.title + "</b>&nbsp;(" + pkps_location.iid + ")<br />" + pkps_location.city + ", " + pkps_location.street + "<br /><small>" + pkps_location.zip + "</small>";
    jQuery("div.ups-pickups-info").css("line-height", "1em").css("font-weight", "300").css("font-size", "0.9em").html(html);
}
