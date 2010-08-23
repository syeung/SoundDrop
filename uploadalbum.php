<?php


include('lib/dropio-php/Dropio/Api.php');
include('Mobile_Detect.php');
include('config.inc.php');

require_once('models/config.php');

$detect = new Mobile_Detect();
$docroot = 'http://' . $_SERVER["SERVER_NAME"] . substr($_SERVER["PHP_SELF"], 0, strrpos($_SERVER["PHP_SELF"], '/') + 1);

$owner = $_SESSION['owner'];
$dropname = $_POST['dropname'];
$displayname = $_POST['displayname'];
$newalbum = strtolower(trim($_POST['upalbum']));
$albumlist = $_SESSION['albumlist'];

if($owner && $owner == $loggedInUser->clean_username && isUserLoggedIn()) {
?>

<html><head>
<title>New Album Upload</title>
<style type="text/css">
	body{background:url('<?php echo $docroot; ?>images/fancybg.png') #dbdbdb repeat-x;}
	table{border:1px solid #aaaaaa;}
	table th{border-bottom:1px solid black;}
	table td{border-bottom:1px solid #cccccc;padding:10px;}
	.metadata{background:#fff;border:1px solid #aaa;margin:4px;padding:4px;}
</style>
<script src='<?php echo $docroot; ?>osflv/AC_RunActiveContent.js' language='javascript'></script>
</head>
<body>


<script type="text/javascript" src="<?php echo $docroot; ?>uploadify/jquery-1.3.2.min.js"></script>
<script type="text/javascript" src="<?php echo $docroot; ?>uploadify/swfobject.js"></script>
<script type="text/javascript" src="<?php echo $docroot; ?>uploadify/jquery.uploadify.v2.1.0.min.js"></script>
<link rel="stylesheet" type="text/css" media="screen, projection" href="uploadify/uploadify.css" />

<script type="text/javascript">
		var rawjsonforms = "\{\"title\"\:\"\"\,\"album\"\:\"<?php echo $newalbum; ?>\"\,\"tracknum\"\:\"\"\,\"year\"\:\"\"\,\"genre\"\:\"\"\,\"bpm\"\:\"\"\,\"composer\"\:\"\"\,\"allowdownload\"\:\"true\"\,\"featured3\"\:\"set me as \'true\' without quotes\"\,\"isalbumcover\"\:\"set me as \'true\' without quotes\"\}"
		var jsonforms = rawjsonforms.replace(/ +/g, ' ');

		$(document).ready(function() {
		$('#upalbum').uploadify({
		'uploader'  : 'uploadify/uploadify.swf',
		'script'    : '<?php echo Dropio_Api::UPLOAD_URL; ?>',
		'multi'     : true,
		'scriptData': {'api_key': '<?php echo $API_KEY; ?>', 'version':'3.0','drop_name': '<?php echo $dropname; ?>','description': jsonforms},
		'cancelImg' : 'uploadify/cancel.png',
		'auto'      : true,
		'onAllComplete' : function(){setTimeout(window.location = '<?php echo "http://" . $_SERVER["HTTP_HOST"] . "?viewmode=".$_REQUEST["viewmode"]."&dropname=". $dropname; ?>',3000);}, 
		'folder'    : '/uploads'
		});
		});

</script>
<div style='display:block;margin-left:auto;margin-right:auto;width:500px;margin-top:150px'>

<?php
if($dropname && $displayname) {
	echo "<h1>Drop ".$displayname."</h1>";
} ?>

<div style="display:block;margin-left:auto;margin-right:auto;background:#ffffff;-moz-border-radius:20px;-webkit-border-radius:20px;padding:0px 20px 20px 20px;">

<?php
$albumfound = 'false';
if($newalbum && $newalbum != '') {
	echo "<h2 style='padding-top:15px'>";
	foreach($albumlist as $album) {
		if($album == $newalbum) {
			$albumfound = 'true';
			break;
		}
	}
	if($albumfound == 'true') {
		echo "Adding to album: ";
	} elseif ($albumfound == 'false') {
		echo "New album: ";
	}
	echo ucwords($newalbum);
	echo "</h2>"; ?>
	<input type='file' name='upalbum' id='upalbum' />
	<br>
	<hr/>
	<span style="font-size:10pt">*You can select multiple files with CTRL + Left click.</span>
<?php
} else {
	echo "<h2>NO ALBUM NAME GIVEN</h2>";
}
?>

</div>
<br>
<a href="javascript:history.go(-1)">Go Back</a>
</div>
</body>
</html>

<?php
} else {
	if($_COOKIE['redirect_to']) {
		header('Location: '.$_COOKIE['redirect_to']);
	} else {
		header("Location: http://".$_SERVER['HTTP_HOST']);
	}
}
