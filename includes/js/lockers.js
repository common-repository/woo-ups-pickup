(function () {

    let apiKeyParam = '';
    try {
        const googleMapsApiKey = data.googleMapsApiKey;

        if (googleMapsApiKey) {
            apiKeyParam = '&gkey='+googleMapsApiKey;
        }
    } catch (e){

    }

    var pkp = document.createElement('script'); pkp.type = 'text/javascript'; pkp.async = true;
    pkp.src = 'https://www.pickuppoint.co.il/api/ups-pickups.sdk.lockers.js?r=2.0'+apiKeyParam;
    var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(pkp, s);
})();
