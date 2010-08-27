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

<body style='background:#f8f8f8'>

<div id='container' style='display:block; margin-left:auto; margin-right:auto; margin-top: 50px; text-align:center; width: 300px'>
<h1 style='margin-top: 0px; margin-bottom: 5px; text-decoration: underline'>SoundDrop</h1>
<span style='font-size:12px;'>Powered by Drop.io RMB</span>
<em style='font-size:300px'>&#9834</em>
<br>
<?php if (isUserLoggedIn()) { ?>
	<a href='account.php'>Account Page</a>
<?php } else { ?>
	<a href='login.php'>Log In Here</a>
<?php } ?>
</div>


</body>
