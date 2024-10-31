(function() {
	document.addEventListener("DOMContentLoaded", (event) => {
		var script = document.createElement('script');
		script.src = PAYEVER_CONTAINER.widget_src;

		if (PAYEVER_CONTAINER.widget_is_variable_product) {
			jQuery('body').on('found_variation', function (event, variation) {
				var price = parseFloat(variation.display_price);
				var reference = 'prod_' + variation.variation_id;

				PayeverPaymentWidgetLoader.init(
					'.payever-widget-finexp',
					null,
					{
						amount: price,
						reference: reference,
						cart: [{
							name: PAYEVER_CONTAINER.widget_product_name,
							description: variation.variation_description,
							identifier: '' + variation.variation_id,
							amount: price,
							price: price,
							quantity: 1,
							thumbnail: variation.image.url,
							unit: 'EACH'
						}]
					}
				);
			});
		} else {
			script.onload = function () {
				PayeverPaymentWidgetLoader.init(
					".payever-widget-finexp"
				);
			};
		}
		document.head.appendChild(script);
	});
})();