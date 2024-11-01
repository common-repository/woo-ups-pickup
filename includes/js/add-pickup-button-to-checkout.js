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

    $(document.body).on('pickups-after-choosen', function (e, data) {

        pkps_location = e.originalEvent.detail;

        console.log("pickups-after-choosen catched event", pkps_location);

        jQuery("input[name=pickups_location1]").val(pkps_location.iid);

        jQuery("input[name=pickups_location2]").val(JSON.stringify(pkps_location));

        console.log(jQuery("input[name=pickups_location1]").val());
        pickup_render_description(pkps_location);

    });
});
// Pickup location chosen select on page load and checkout updated
jQuery(document).ready(function($) {

    $(document.body).on('updated_checkout', function () {

        var obj = jQuery("input[name=pickups_location2]");
        console.log("updated_checkout for pickup has been raised...", obj);
        if ( typeof(obj) != "undefined"){
            var json = obj.val();
            if ( typeof(json) != "undefined" && json.length > 0 ){
                var o = JSON.parse(json);
                if ( o != "undefined"){
                    pickup_render_description(o);
                }
            }
            
        }   

    });
});
function pickup_render_description(pkps_location){
    var html = "<br /><b>" + pkps_location.title + "</b>&nbsp;(" + pkps_location.iid + ")<br />" + pkps_location.city + ", " + pkps_location.street + "<br /><small>" + pkps_location.zip + "</small>";
    jQuery("div.ups-pickups-info").css("line-height", "1em").css("font-weight", "300").css("font-size", "0.9em").html(html);
}