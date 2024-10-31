function getOrderUpdateStatus() {
    jQuery.ajax({
        url: Payever_Pending_Preloader.ajax_url,
        data: {
            action: 'payever_get_status_url',
            nonce: Payever_Pending_Preloader.nonce,
            order_key: Payever_Pending_Preloader.order_key,
            paymentId: Payever_Pending_Preloader.payment_id,
        },
        type: 'GET',
        dataType: 'json',
        success: function (response) {
            if (response.success && response.data.url) {
                window.location.href = response.data.url;
            } else {
                setTimeout(getOrderUpdateStatus, 10000);
            }
        },
        error: function (xhr, status, error) {
            jQuery('.pe-checkout-bootstrap').hide();
            jQuery('#payever_message').html("Error: " + error);
        }
    });
}

jQuery(document).ready(function () {
    getOrderUpdateStatus();
});
