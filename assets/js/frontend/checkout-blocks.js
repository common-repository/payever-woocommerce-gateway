window.PayeverBlocksCheckout = {
    init: function() {
        this.loadPaymentMethods();
    },

    loadPaymentMethods: function() {
        const settings = window.wc.wcSettings.getSetting('payever_gateway_data', {});
        settings.forEach(item => {
            if ( ! item.is_enabled ) {
                return;
            }

            const title = window.wp.htmlEntities.decodeEntities(item.title);
            const label = () => {
                let img = '';
                if (item.icon) {
                    img = Object(window.wp.element.createElement)('img', {
                        src: item.icon,
                        alt: item.title,
                        className: 'payever_icon'
                    });
                }

                return Object(window.wp.element.createElement)('span', { className: 'payever-payment-item' }, title, img);
            }
            const content = () => {
                let content = null;
                if (item.description) {
                    const desc = window.wp.htmlEntities.decodeEntities(item.description || '');
                    content = Object(window.wp.element.createElement)('span', { className: 'payever-payment-description' }, desc)
                }

                return content;
            };

            const Block_Gateway = {
                name: item.id,
                label: Object(window.wp.element.createElement)(label, null),
                content: Object(window.wp.element.createElement)(content, null),
                edit: Object(window.wp.element.createElement)(content, null),
                canMakePayment: function ( data ) {
                    return new Promise(function (resolve, reject) {
                        let result = window.PayeverBlocksCheckout.isVisible( item, data );
                        resolve(result);
                    })
                },
                ariaLabel: title,
                supports: {
                    features: item.supports,
                },
            };
            window.wc.wcBlocksRegistry.registerPaymentMethod(Block_Gateway);
        });
    },

    isVisible: function ( item, data ) {
        let currency = data.cartTotals.currency_code;
        let cartTotal = parseInt(data.cartTotals.total_price,10) / 100;
        let billingAddress = data.billingAddress;
        let shippingAddress = data.shippingAddress;

        if ( ! item.is_enabled ) {
            return false;
        }

        // Check is hidden
        if ( item.is_hidden ) {
            return false;
        }

        // Validate payment rules
        if ( ! this.isPaymentAvailableByCartAmount( cartTotal, item.min_amount, item.max_amount ) ) {
            return false;
        }

        if ( ! this.isPaymentAvailableByOptions( currency, item.currencies ) ) {
            return false;
        }

        if ( ! this.isPaymentAvailableByOptions( billingAddress.country, item.countries ) ) {
            return false;
        }

        if ( ! this.isPaymentAvailableByOptions( shippingAddress.country, item.countries ) ) {
            return false;
        }

        if ( item.is_diff_address && this.isAddressDifferent( billingAddress, shippingAddress ) ) {
            return false;
        }

        return true;
    },

    isPaymentAvailableByCartAmount: function ( cartAmount, minAllowed, maxAllowed ) {
        if ( 0 < cartAmount
            && 0 < maxAllowed
            && maxAllowed < cartAmount
            || minAllowed >= 0
            && minAllowed > cartAmount
        ) {
            return false;
        }

        return true;
    },

    /**
     * Checks if a payment is available based on the given options.
     *
     * @param value The payment value to check.
     * @param options The available payment options.
     * @returns {boolean} Returns true if the payment is available, false otherwise.
     */
    isPaymentAvailableByOptions: function ( value, options ) {
        if ( ! options.includes( value ) && ! options.includes( 'any' ) ) {
            return false;
        }

        return true;
    },

    /**
     * Checks if the billing and shipping addresses are different based on specified fields.
     *
     * @param billingAddress The billing address
     * @param shippingAddress The shipping address
     * @returns {boolean} Returns true if the addresses are different, false otherwise
     */
    isAddressDifferent: function ( billingAddress, shippingAddress ) {
        const checkFields = [
            'country',
            'postcode',
            'state',
            'city',
            'address_1',
            'address_2',
            'first_name',
            'last_name',
        ];

        let isDifferent = false;
        checkFields.forEach( function ( field, index ) {
            let shippingVal = shippingAddress[field];
            let billingVal  = billingAddress[field];

            if ( 'postcode' === field ) {
                shippingVal = shippingVal.replace(/\s/g, '');
                billingVal = billingVal.replace(/\s/g, '');
            }

            if ( shippingVal && billingVal && billingVal !== shippingVal ) {
                isDifferent = true;
            }
        } );

        return isDifferent;
    }
}

window.PayeverBlocksCheckout.init();
