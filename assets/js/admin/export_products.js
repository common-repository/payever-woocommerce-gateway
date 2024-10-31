var payever_export_products = function (page, aggregate) {
	jQuery('#payever_export_products').prop('disabled', true);

	var data = {
		action: 'export_products',
		page: page,
		aggregate: aggregate,
		nonce: PAYEVER_CONTAINER.export_products_nonce
	};

	if (!aggregate) {
		jQuery('#export_status_messages').html('<div class="notice notice-info"><p>' + PAYEVER_CONTAINER.translations["preparing_exporting_products"] + '</p></div>');
	}

	jQuery.ajax({
		url: ajaxurl,
		data: data,
		type: 'POST',
		success: function (response) {
			switch (response.status) {
				case 'in_process':
					jQuery('#export_status_messages').html('<div class="notice notice-info"><p>' + response.message + '</p></div>');
					return export_products(response.next_page, response.aggregate);
				case 'success':
					jQuery('#export_status_messages').html('<div class="notice notice-success"><p>' + response.message + '</p></div>');
					break;
				case 'error':
					jQuery('#export_status_messages').html('<div class="notice notice-error"><p>' + response.message + '</p></div>');
					break;
				default:
					jQuery('#export_status_messages').html('<div class="notice notice-error"><p>' + PAYEVER_CONTAINER.translations["something_went_wrong"] + '</p></div>');
			}

			jQuery('#payever_export_products').prop("disabled", false);
		}
	});
}
jQuery('#payever_export_products').on( "click", function() {
	payever_export_products();
});