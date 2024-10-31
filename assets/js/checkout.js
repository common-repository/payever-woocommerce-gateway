(function ($) {
	"use strict";

	$( 'body' ).on(
		'change',
		'input[name="payment_method"]',
		function () {
			$( 'body' ).trigger( 'update_checkout' );
		}
	);

	function sendCheckoutNewScrollOffset() {
		const iframe = document.getElementById( 'payever_iframe' )
		if ( iframe ) {
			iframe.contentWindow.postMessage(
				{
					'event': 'sendPayeverCheckoutScrollOffset',
					'scrollTop': window.pageYOffset || document.documentElement.scrollTop,
					'offsetTop': iframe.offsetTop,
					'windowHeight': window.innerHeight,
				},
				window.origin
			);
		}
	}

	if (window.addEventListener) {
		window.addEventListener( "message", onMessagePayever, false );
		window.addEventListener( 'scroll', sendCheckoutNewScrollOffset, false );
		window.addEventListener( 'resize', sendCheckoutNewScrollOffset, false );
	} else if (window.attachEvent) {
		window.attachEvent( "onmessage", onMessagePayever, false );
		window.attachEvent( 'onscroll', sendCheckoutNewScrollOffset, false );
		window.attachEvent( 'onresize', sendCheckoutNewScrollOffset, false );
	}

	function onMessagePayever(event) {
		if ( ! event) {
			return;
		}

		const iframe = document.getElementById( 'payever_iframe' );
		if ( ! iframe ) {
			return;
		}

		const iframeUrl = new URL( iframe.src );
		if ( ! (event.origin === iframeUrl.origin || event.origin === window.origin)) {
			return;
		}

		if ( event.data ) {
			switch ( event.data.event ) {
				case 'payeverCheckoutHeightChanged':
					iframe.style.height = Math.max( 0, parseInt( event.data.value ) );
					break;
				case 'payeverCheckoutScrollOffsetRequested':
					sendCheckoutNewScrollOffset();
			}
		}
	}
})( jQuery );
