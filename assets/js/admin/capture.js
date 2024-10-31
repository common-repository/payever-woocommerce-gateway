jQuery(
	function($) {
		'use strict';

		const payever_wc_capture = {
			init: function () {
				this.cacheElements();
				this.checkCaptureAllowed();
				this.bindEvents();
			},

			cacheElements: function () {
				this.payeverCapture    = $( 'div.wc-payever-capture' );
				this.$captureAmount    = $( '#capture_amount' );
				this.$qtyInputs        = $( '.payever-partial-item-qty.payever-capture-action .qty-input' );
				this.$captureActionBtn = $( 'button.payever-capture-action' );
			},

			checkCaptureAllowed: function () {
				this.isAllowedAmount = ! this.$captureAmount.is( ':disabled' );
				this.isAllowedQty    = this.$qtyInputs.filter( ':not(:disabled)' ).length > 0;
				this.toggleCaptureAmount();
			},

			toggleCaptureAmount: function () {
				const hasQtyValue = this.$qtyInputs.filter(
					function () {
						return $( this ).val() > 0;
					}
				).length > 0;

				this.$captureAmount.prop( 'disabled', hasQtyValue || ! this.isAllowedAmount );
			},

			bindEvents: function () {
				$( '#woocommerce-order-items' )
					.on( 'click', 'button.wc-payever-capture-button', this.partialCaptureShow.bind( this ) )
					.on( 'click', 'button.payever-capture-action', this.doCapture.bind( this ) )
					.on( 'change keyup', '.payever-partial-item-qty.payever-capture-action .qty-input', this.handleQtyChange.bind( this ) )
					.on( 'click', 'button.cancel-action', this.hideQty.bind( this ) );

				this.payeverCapture.appendTo( '#woocommerce-order-items .inside' );
				this.payeverCapture.on( 'change keyup', '#capture_amount', this.handleCaptureAmountChange.bind( this ) );

				$( '#wc_shipping_provider' ).on(
					'change click',
					function () {
						const value = $( this ).val();
						$( '#provider_custom_row, #tracking_url_row' ).toggle( value === '' );
						$( '#wc_shipping_provider_custom' ).val( value === '' ? '' : value );
					}
				);

				$( '#wc_shipping_provider, #wc_tracking_number' ).on( 'change keypress', this.handleShippingChange.bind( this ) );
			},

			handleQtyChange: function () {
				this.toggleCaptureAmount();
				this.updateCapturedAmount();
				this.toggleCaptureActionBtn();
			},

			handleShippingChange: function () {
				const providerValue = $( '#wc_shipping_provider' ).val();
				$( '#provider_custom_row, #tracking_url_row' ).toggle( providerValue === '' );

				if (providerValue !== '') {
					const url          = $( '#wc_shipping_provider option:selected' ).data( 'url' );
					const tracking_url = url.replace( '%1$s', $( '#wc_tracking_number' ).val() );
					$( '#wc_tracking_url' ).val( tracking_url );
				}
			},

			handleCaptureAmountChange: function () {
				const captureAmountVal    = this.$captureAmount.val();
				const qtyInputs           = $( '.payever-capture-action input.qty-input' );
				const captureAmountAmount = $( 'button .capture-amount .amount' );

				if (captureAmountVal) {
					qtyInputs.val( 0 ).prop( 'disabled', true );
				} else if (this.isAllowedQty) {
					qtyInputs.prop( 'disabled', false );
				}

				const totalAmount = accounting.unformat( captureAmountVal, woocommerce_admin.mon_decimal_point );
				this.$captureActionBtn.prop( 'disabled', ! totalAmount );
				captureAmountAmount.text( this.formatting( totalAmount ) );
			},

			updateCapturedAmount: function () {
				const cost            = this.calculateCost();
				const formattedAmount = this.formatting( cost );

				$( '.capture-amount .amount' ).text( formattedAmount );
				this.$captureAmount.val( formattedAmount );
			},

			calculateCost: function () {
				let cost = 0;
				this.$qtyInputs.each(
					function () {
						cost += $( this ).attr( 'data-item-cost' ) * $( this ).val();
					}
				);
				return cost;
			},

			toggleCaptureActionBtn: function () {
				this.$captureActionBtn.prop( 'disabled', this.calculateCost() === 0 );
			},

			formatting: function (cost) {
				return accounting.formatMoney(
					cost,
					{
						symbol: woocommerce_admin_meta_boxes.currency_format_symbol,
						decimal: woocommerce_admin_meta_boxes.currency_format_decimal_sep,
						thousand: woocommerce_admin_meta_boxes.currency_format_thousand_sep,
						precision: woocommerce_admin_meta_boxes.currency_format_num_decimals,
						format: woocommerce_admin_meta_boxes.currency_format
					}
				);
			},

			hideQty: function () {
				this.$qtyInputs.hide();
			},

			partialCaptureShow: function () {
				this.payeverCapture.slideDown();
				this.$qtyInputs.show();
				$( 'div.wc-order-data-row-toggle' ).not( 'div.wc-payever-capture' ).slideUp();
				$( 'div.wc-order-totals-items' ).slideUp();
			},

			doCapture: function () {
				const data = {
					action: 'payever_capture_item',
					nonce: woocommerce_admin_meta_boxes.payever_capture_item_nonce,
					order_id: woocommerce_admin_meta_boxes.post_id,
					items: [],
					amount: accounting.unformat( this.$captureAmount.val(), woocommerce_admin.mon_decimal_point ),
					comment: $( '#capture_comment' ).val(),
					tracking_number: $( '#wc_tracking_number' ).val(),
					tracking_url: $( '#wc_tracking_url' ).val(),
					shipping_provider: $( '#wc_shipping_provider_custom' ).val(),
					shipping_date: $( '#wc_shipping_date' ).val()
				};

				this.$qtyInputs.each(
					function () {
						const itemId = $( this ).attr( 'data-item-id' );
						const qty    = $( this ).val();

						if (itemId && qty > 0) {
							data.items.push( { item_id: itemId, qty: qty } );
						}
					}
				);

				$( '#woocommerce-order-items' ).block(
					{
						message: null,
						overlayCSS: { background: '#fff', opacity: 0.46 }
					}
				);

				$.ajax(
					{
						url: woocommerce_admin_meta_boxes.ajax_url,
						data: data,
						type: 'POST',
						dataType: 'json'
					}
				).done(
					function (response) {
						if (response.success) {
							window.location.reload();
						} else {
							window.alert( response.data.error );
						}
					}
				).always(
					function () {
						$( '#woocommerce-order-items' ).unblock();
					}
				);
			},
		};

		payever_wc_capture.init();
	}
);
