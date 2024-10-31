var payever_chat = function(e) {
	window.zESettings = {analytics: false};

	var s = document.createElement('script');
	s.src = 'https://static.zdassets.com/ekr/snippet.js?key=775ae07f-08ee-400e-b421-c190d7836142';
	s.id = 'ze-snippet';
	s.onload = function () {
		window['zE'] && window['zE']('webWidget', 'open');
		window['zE'] && window['zE']('webWidget:on', 'open', function () {
			e.target.innerText = PAYEVER_CONTAINER.translations["chat_with_us"];
		});
	};
	document.head.appendChild(s);

	e.target.innerText = PAYEVER_CONTAINER.translations["loading_chat"];
	e.preventDefault();

	return false;
}
jQuery('#pe_chat_btn').on( "click", function(e) {
	payever_chat(e);
});