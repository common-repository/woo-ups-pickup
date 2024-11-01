(function ($) {
    $(document).ready(function () {

        function refreshPage(time){
            setTimeout(function(){
                location.reload()
            }, time);
        }

        $('#actions select[name="wc_order_action"]').on('change', function(){
            const currentAction = $(this).val();
            let newTab = false;
            switch (currentAction) {
                case 'ups_print_a4':
                case 'ups_print_thermal':
                case 'ups_print_picking_a4':
                case 'ups_print_picking_thermal':
                case 'ups_send_and_print_label_a4':
                case 'ups_send_and_print_label_thermal':
                    newTab = true;
                    break;
            }

            if(newTab){
                $('form#post').attr('target','_blank');
            }else{
                $('form#post').removeAttr('target');
            }
        })

        $('.button.wc-reload').on('click', function(){
            refreshPage(2000);
        });

        $('.print-button').on('click', function(){
            const orderId = $(this).parents('tr').find('.check-column input[type="checkbox"]').val();

            setTimeout(function(){
                let url = window.location.href;
                if (url.indexOf('?') > -1){
                    url += '&scroll_to='+orderId
                }else{
                    url += '?scroll_to='+orderId
                }
                window.location.href = url;
            }, 2000);
        });

        const scrollToOrder = findGetParameter('scroll_to');
        if(scrollToOrder){
            let $postElement = $('#order-'+scrollToOrder);
            if($postElement.length < 1){
                $postElement = $('#post-'+scrollToOrder);
            }
            $('html, body').animate({
                scrollTop: $postElement.offset().top + ($postElement.height() * 2)
            }, 900);
        }

        function findGetParameter(parameterName) {
            let result = null,
                tmp = [];
            const items = location.search.substr(1).split("&");
            for (let index = 0; index < items.length; index++) {
                tmp = items[index].split("=");
                if (tmp[0] === parameterName) result = decodeURIComponent(tmp[1]);
            }
            return result;
        }

        $('#posts-filter, #wc-orders-filter').on('submit', function (e) {
            const action = $('#bulk-action-selector-top').val();

            if (action === 'ups_print_a4' || action === 'ups_print_thermal' || action === 'ups_print_picking_a4'  || action === 'ups_print_picking_thermal' || action === 'ups_send_and_print_label_a4'  || action === 'ups_send_and_print_label_thermal') {
                e.preventDefault();
                let posts = $('.wc-orders-list-table input[name="id[]"]:checked');
                if(posts.length < 1){
                    posts = $('input[name="post[]"]:checked');
                }
                if (!posts.length) {
                    alert('Please choose least 1 order to print');
                    return;
                }

                let format;
                switch (action) {
                    case 'ups_print_thermal':
                    case 'ups_print_picking_thermal':
                    case 'ups_send_and_print_label_thermal':
                        format = 'Thermal'
                        break;
                    default:
                        format = 'A4'
                        break;
                }
                const postIds = [];
                posts.each(function () {
                    postIds.push($(this).val());
                })

                let ajaxAction;
                switch (action) {
                    case 'ups_print_picking_a4':
                    case 'ups_print_picking_thermal':
                        ajaxAction = 'ups_picking_print_label';
                        break;
                    case 'ups_send_and_print_label_a4':
                    case 'ups_send_and_print_label_thermal':
                        ajaxAction = 'ups_send_and_print_label';
                        break;
                    default:
                        ajaxAction = 'ups_print_label'
                        break;
                }

                const url = ajaxurl + '?' + $.param({
                    action: ajaxAction,
                    order_ids: postIds,
                    format: format
                })

                if(ajaxAction === 'ups_send_and_print_label') {
                    location.href = url+location.search.split('&').filter(function(val){ return val.includes('s=') || val.includes('post_status=') || val.includes('post_type=') || val.includes('paged='); }).join('&').replace('?', '&');
                }else{
                    const newTab = window.open(url, '_blank')
                    newTab.focus();

                    refreshPage(5000);
                }
            }
        })
    })
})(jQuery)
