<?php
/**
 * PPDA Kerberos SSO fallback.
 *
 * Served by Apache as the ErrorDocument for the GSSAPI-protected /sso.php
 * endpoint. Clients that cannot present a Kerberos ticket receive this page
 * (HTTP 401) and are sent back to the normal iTop login form.
 */

header('Content-Type: text/html; charset=UTF-8', true, 401);
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta http-equiv="refresh" content="0; url=./pages/UI.php">
	<title>Sign in</title>
	<script>
		window.location.replace('./pages/UI.php');
	</script>
</head>
<body>
	<p>Redirecting you to the sign-in page...</p>
</body>
</html>