jQuery(document).ready(function ($) {

	// Add print option to combobox
	$('select[name="action"]').append($("<option>").attr('value','print_wb_labels').text(upsPrintData.print_wb_labels));
	$('select[name="action2"]').append($("<option>").attr('value','print_wb_labels').text(upsPrintData.print_wb_labels));

	// Add wbs to table
	/*
	for (i = 0; i < upsPrintData.customer_data.length; i++) {

		var orderid = upsPrintData.customer_data[i].order_id;
		var trackingcode = upsPrintData.customer_data[i].wb;
		//console.log(orderid);
		$("#post-"+orderid).find( ".shipping_wb" ).html("<a href="+"https://wwwapps.ups.com/WebTracking/processRequest?TypeOfInquiryNumber=T&InquiryNumber1="+trackingcode+">"+trackingcode+"</a>");
	}
	*/

	// Select check and click
	$(".bulkactions select").change(function(){

		var ClickImageId = (this.value);

		if(ClickImageId=="print_wb_labels"){

			var checkedValue = null ;// note this

			var arr = [];

			var inputElements;

			jQuery(".wrap form table tr th input").each(function(){

				inputElements = this.value;

				if(this.checked){

					checkedValue = this.value;

					arr.push(checkedValue);

				}

			});

			$.ajax(
				{

					type: "POST",

					url : upsPrintData.ajax_urls.dest_url ,

					data: {
						ids:arr,

						action: 'ups_woocommerce_printwb',

						nonce : upsPrintData.nonce
							},

					success: function (result) {
                        var milliseconds = new Date().getTime();
						window.location = window.location.href+'&filename=order.ship&version=' + milliseconds;

					}

				}); // ajax

		} // if

	}); // change
	$('.ups-print').click(function(){
        var order_id = $(this).data('orderid');
        var arra	= [];//Sometimes you have to do strange things
        arra.push(order_id);

        console.log("Post data to the server...", upsPrintData.ajax_urls.dest_url, arra );
		$.ajax(
			{
				type: "POST",
				url : upsPrintData.ajax_urls.dest_url ,
				data: {
					ids:arra,
					action: 'ups_woocommerce_printwb',
					nonce : upsPrintData.nonce
				},

				success: function(result) {
                    // console.log(window.location.href+'&filename=order.ship', "result", result);
                    var milliseconds = new Date().getTime();
                    window.location = window.location.href+'&filename=order.ship&version=' + milliseconds;
				}

			});
	});


});