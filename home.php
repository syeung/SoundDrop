<?php


include('lib/dropio-php/Dropio/Api.php');
include('Mobile_Detect.php');
include('config.inc.php');

require_once('models/config.php');

$detect = new Mobile_Detect();
$docroot = 'http://' . $_SERVER["SERVER_NAME"] . substr($_SERVER["PHP_SELF"], 0, strrpos($_SERVER["PHP_SELF"], '/') + 1);

?>

<head>
	<title>SoundDrop - Powered by Drop.io RMB</title>
</head>

<body>


<h2>Homepage eventually goes here</h2>
<br><br>
<?php if (isUserLoggedIn()) { ?>
	<a href='account.php'>Account Page</a>
<?php } else { ?>
	<a href='login.php'>Log In Here</a>
<?php } ?>



</body>
