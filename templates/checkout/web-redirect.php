<?php

defined( 'ABSPATH' ) || exit;

/** @var string $url */
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" lang="en-US">
<head>
	<?php wp_print_inline_script_tag( "parent.document.location.href='" . esc_url_raw( $url ) . "';" ); ?>
	<noscript>
		<meta charset="UTF-8">
		<meta http-equiv="refresh" content="0; URL=<?php echo wp_json_encode( esc_url_raw( $url ), JSON_HEX_TAG | JSON_HEX_AMP ); ?>">
	</noscript>
	<title>Page Redirection</title>
</head>
<body>
<p>This page has been moved. If you are not redirected within 3 seconds, click <a href="<?php echo esc_url( $url ); ?>">here</a>.</p>
</body>
</html>
