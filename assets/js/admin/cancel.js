jQuery(
	function($) {
		'use strict';

		const payever_wc_cancel = {
			init: function () {
				this.cacheElements();
				this.bindEvents();
			},

			cacheElements: function () {
				this.payeverCancel    = $( 'div.wc-payever-cancel' );
				this.$cancelAmount    = $( '#cancel_amount' );
				this.$qtyInputs       = $( '.payever-partial-item-qty.payever-cancel-action .qty-input' );
				this.$cancelActionBtn = $( 'button.payever-cancel-action' );
			},

			bindEvents: function () {
				$( '#woocommerce-order-items' )
					.on( 'click', 'button.wc-payever-cancel-button', this.partialCancelShow.bind( this ) )
					.on( 'click', 'button.payever-cancel-action', this.doCancel.bind( this ) )
					.on( 'change keyup', '.payever-partial-item-qty.payever-cancel-action .qty-input', this.handleQtyChange.bind( this ) )
					.on( 'click', 'button.cancel-action', this.hideQty.bind( this ) );

				this.payeverCancel.appendTo( '#woocommerce-order-items .inside' );
			},

			handleQtyChange: function () {
				this.updateCanceldAmount();
				this.toggleCancelActionBtn();
			},

			updateCanceldAmount: function () {
				const cost            = this.calculateCost();
				const formattedAmount = this.formatting( cost );

				$( '.cancel-amount .amount' ).text( formattedAmount );
				this.$cancelAmount.val( formattedAmount );
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

			toggleCancelActionBtn: function () {
				this.$cancelActionBtn.prop( 'disabled', this.calculateCost() === 0 );
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

			partialCancelShow: function () {
				this.payeverCancel.slideDown();
				this.$qtyInputs.show();
				$( 'div.wc-order-data-row-toggle' ).not( 'div.wc-payever-cancel' ).slideUp();
				$( 'div.wc-order-totals-items' ).slideUp();
			},

			doCancel: function () {
				const data = {
					action: 'payever_cancel_item',
					nonce: woocommerce_admin_meta_boxes.payever_cancel_item_nonce,
					debug: JSON.stringify(woocommerce_admin_meta_boxes),
					order_id: woocommerce_admin_meta_boxes.post_id,
					items: []
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

		payever_wc_cancel.init();
	}
);
