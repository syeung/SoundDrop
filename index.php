<?php

include('lib/dropio-php/Dropio/Api.php');
include('Mobile_Detect.php');
include('config.inc.php');

require_once('models/config.php');

$detect = new Mobile_Detect();
$docroot = 'http://' . $_SERVER["SERVER_NAME"] . substr($_SERVER["PHP_SELF"], 0, strrpos($_SERVER["PHP_SELF"], '/') + 1);

//Please be sure to copy config.inc.php.sample to config.inc.php
//then add your own $API_KEY in that file
 
Dropio_Api::setKey($API_KEY);
$dropname = $_REQUEST['dropname'];
$rawdisplayname = ((substr($_REQUEST['dropname'], 0, 10) == 'sounddrop_') ? (explode('sounddrop_', $dropname, 2)) : $_REQUEST['dropname']);
$displayname = ((is_array($rawdisplayname)) ? ucwords(strtolower($rawdisplayname[1])) : ucwords(strtolower($rawdisplayname)));

$page = 1;

$lastdrop = $docroot . '?' . $_SERVER['QUERY_STRING'];

setcookie('redirect_to', $lastdrop);

//Set the $dropname to the passed in parameter, or create a new drop with a random name
if(!empty($dropname)){
	$drop = Dropio_Drop::load($dropname);
}else if($_REQUEST['newdrop']){
	if(!isUserLoggedIn()){
		header("Location: login.php");
		die();
	}
	$newdrop = 'sounddrop_'.$_REQUEST['newdrop'];
	$drop = Dropio_Drop::instance($newdrop)->save();
	$dropname = $drop->name;
	$dropmeta = $drop->addNote('it\'s in the description','dropmeta');
	$dropmeta->description = htmlspecialchars('{"dropOwner":"' . $loggedInUser->clean_username . '","isHidden":"true"}');
	$dropmeta->save();
	$bandmeta = $drop->addNote('<br>','Band Details');
	$bandmeta->description = htmlspecialchars('{"bandname":"","hometown":"","bandgenres":"","yearsactive":"","members":"","motto":""}');
	$bandmeta->save();
	header("Location:http://" . $_SERVER["HTTP_HOST"] . $_SERVER["PHP_SELF"] . "?viewmode=".$_REQUEST["viewmode"]."&dropname=". $dropname);
	die('Redirecting');
}else{
	if (isUserLoggedIn()) {
		header("Location: account.php");
		die('Redirecting');
	} else {
	header("Location: home.php");
	die('Redirecting');
	}
}


//Define types available
$alltypes = array("image", "movie", "audio", "document", "other", "note");

//Fetch all assets in the drop into a global $assets variable
$assets = array();
$assetCount = array();
if($_REQUEST['viewmode'] == 'permalink' || $_REQUEST["action"] == "updateasset"){//it's a permalink, just get the requested asset
	$assets[] = $drop->getAsset($_REQUEST['assetid']);
}else{
	while ( ($assetsIn = $drop->getAssets($page)) && $assetsIn->getCount()) {
		foreach ($assetsIn as $assetIn){
			$assets[] = $assetIn;
			$assetCount[$assetIn->type]++;
		}
		$page++;
	}
}

//Fetch list of assets
$notechecked = 'false';
$prevalbum = "BLANK_ALBUM";
$albumlist = array();
foreach ($assets as $name=>$a) {
	$description = $a->description;
	$data = '{}';
	if(json_decode(stripslashes(htmlspecialchars_decode($description)))){
		//we had decodable data in the description. It's metadata!
		$data = stripslashes(htmlspecialchars_decode($description));
	}else if(!empty($description)){
	}
	$jsondata = json_decode($data);

	if($a->name == 'dropmeta' && $jsondata->isHidden && ($jsondata->isHidden == 'true') && $jsondata->dropOwner && $a->type == 'note' && $notechecked == 'false') {
		$owner = $jsondata->dropOwner;
		$_SESSION['owner'] = $owner;
		$notechecked = 'true';
		continue;
	}

	if($a->type == 'image' && $jsondata->isbandlogo && ($jsondata->isbandlogo == 'true')) {
		$logo = GetAssetPreview($a);
		continue;
	}

	if($a->type == 'note' && $a->name == 'band-details') {
		if($jsondata->bandname && $jsondata->bandname != '') {
			$bandname = $jsondata->bandname;
		}
		if($jsondata->hometown && $jsondata->hometown != '') {
			$hometown = $jsondata->hometown;
		}
		if($jsondata->bandgenres && $jsondata->bandgenres != '') {
			$bandgenres = $jsondata->bandgenres;
		}
		if($jsondata->yearsactive && $jsondata->yearsactive != '') {
			$yearsactive = $jsondata->yearsactive;
		}
		if($jsondata->members && $jsondata->members != '') {
			$members = $jsondata->members;
		}
		if($jsondata->motto && $jsondata->motto != '') {
			$motto = $jsondata->motto;
		}
		continue;
	}
	
	//Distinguishing between albums
	if($jsondata->album){
		$a->album = $jsondata->album;

		if($a->type == 'image' && $jsondata->isalbumcover == 'true') {
			${$jsondata->album.'_cover'} = GetAssetPreview($a);
		}

		if($a->type == 'image' && $jsondata->isalbumcover != 'true' && $jsondata->isbandlogo != 'true') {
			if(${$jsondata->album.'_images'} && is_array(${$jsondata->album.'_images'})) {
				array_push(${$jsondata->album.'_images'}, $a);
			} else {
				${$jsondata->album.'_images'} = array ($a);
			}
		}

		if($jsondata->album != $prevalbum){
			$prevalbum = $jsondata->album;

			if(isset(${'album_'.$jsondata->album}) && is_array(${'album_'.$jsondata->album})) {
				array_push(${'album_'.$prevalbum},$a->name);
			} else {
				${'album_'.$jsondata->album} = array ($a->name);
				array_push($albumlist, $jsondata->album);
			}

		} elseif ($jsondata->album == $prevalbum) {
			array_push(${'album_'.$prevalbum},$a->name);
		}

	}

	#populate the albums array (images get their own array to populate)
	if($a->type != 'image') {
		$a->track_number = intval($jsondata->tracknum);
		$asset_array_representation = get_object_vars($a);
		$albums[$jsondata->album]->tracks[] = $asset_array_representation["values"];
	}
}
$_SESSION['albumlist'] = array();
$_SESSION['albumlist'] = $albumlist;

function OrderTracks($track_number) {
	$code = "return strnatcmp(\$a['$track_number'], \$b['$track_number']);";
	return create_function('$a,$b', $code);
}

$order = OrderTracks('track_number');
foreach($albumlist as $album) {
if(is_array($albums[$album]->tracks)) {
	usort($albums[$album]->tracks, $order);
	if($albums) {
		foreach($albums[$album]->tracks as $track) {
			if($track['track_number'] == '0' || empty($track['track_number']) || !(is_numeric($track['track_number']))) {
				$trail = $track;
				array_push($albums[$album]->tracks, $trail);
				array_shift($albums[$album]->tracks);
			} else {
				break;
			}
		}
	}
}
}

//////////////////////
//Asset deletion
if($_REQUEST["action"] == "delete" && $_REQUEST["assetid"]){
	if(isUserLoggedIn() && $loggedInUser->clean_username == $owner) {
		//iterate through assets
		$counter = 0;
		foreach($assets as $a){
			if($a->{$a->primary_key} == $_REQUEST["assetid"]){
				$a->delete();
				//also remove that asset from the local array
				unset($assets[$counter]);
			}
			$counter++;
		}
		header("Location:http://" . $_SERVER["HTTP_HOST"] . $_SERVER["PHP_SELF"] . "?viewmode=".$_REQUEST["viewmode"]."&dropname=". $dropname);
	}
}else if($_REQUEST["action"] == "updateasset" && $_REQUEST["assetid"]){
 	if(isUserLoggedIn()){
	$updated = '';
 	foreach($assets as $a){
		if($a->{$a->primary_key} == $_REQUEST["assetid"]){
			if(json_decode(stripslashes($_REQUEST["metadata"]))){
				$a->description = htmlspecialchars(stripslashes($_REQUEST["metadata"]));
				$updated = $a->save();
			}
		}
	}
	//redirect back to this page after updating
	#header("Location:http://" . $_SERVER["HTTP_HOST"] . $_SERVER["PHP_SELF"] . "?viewmode=".$_REQUEST["viewmode"]."&dropname=". $dropname);
	die(json_encode($a));
	}
}

?>



<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"> 
<html xmlns="http://www.w3.org/1999/xhtml"> 
 
<head> 
	<title>SoundDrop :: <?php echo $displayname; ?></title>
	<style type="text/css">
		#assets{
			
		}
		body {
			background:#ddddff;
			margin:0px;
			padding:0;
			font-size:11px;
			font-family:sans-serif;
		}
		
		
	
	</style>
	
	<?php
	/* 
		################################# 
		### Uploadify uploader script ### 
		#################################  */ 
		?>
		<script type="text/javascript" src="<?php echo $docroot; ?>uploadify/jquery-1.3.2.min.js"></script>
		<script type="text/javascript" src="<?php echo $docroot; ?>uploadify/swfobject.js"></script>
		<script type="text/javascript" src="<?php echo $docroot; ?>uploadify/jquery.uploadify.v2.1.0.min.js"></script>
		<link rel="stylesheet" type="text/css" media="screen, projection" href="uploadify/uploadify.css" />

		<script type="text/javascript">// <![CDATA[

		var jsonforms = '\{\"title\"\:\"\"\,\"album\"\:\"<?php if(($_REQUEST['viewmode'] == 'albums' || empty($_REQUEST['viewmode'])) && $_REQUEST['album'] && ${'album_'.$_REQUEST['album']}) { echo strtolower($_REQUEST['album']); }?>\"\,\"tracknum\"\:\"\"\,\"year\"\:\"\"\,\"genre\"\:\"\"\,\"bpm\"\:\"\"\,\"composer\"\:\"\"\,\"allowdownload\"\:\"true\"\,\"isalbumcover\"\:\"false\"\}'

		$(document).ready(function() {
		$('#uplogo').uploadify({
		'uploader'  : 'uploadify/uploadify.swf',
		'script'    : '<?php echo Dropio_Api::UPLOAD_URL; ?>',
		'multi'     : false,
		'scriptData': {'api_key': '<?php echo $API_KEY; ?>', 'version':'3.0','drop_name': '<?php echo $dropname; ?>','description': '\{\"isbandlogo\"\:\"true\"\}'},
		'cancelImg' : 'uploadify/cancel.png',
		'auto'      : true,
		'onAllComplete' : function(){setTimeout(window.location = '<?php echo "http://" . $_SERVER["HTTP_HOST"] . $_SERVER["PHP_SELF"] . "?viewmode=".$_REQUEST["viewmode"]."&dropname=". $dropname; ?>',3000);}, 
		'folder'    : '/uploads'
		});
		});

		$(document).ready(function() {
		$('#file').uploadify({
		'uploader'  : 'uploadify/uploadify.swf',
		'script'    : '<?php echo Dropio_Api::UPLOAD_URL; ?>',
		'multi'     : true,
		'scriptData': {'api_key': '<?php echo $API_KEY; ?>', 'version':'3.0','drop_name': '<?php echo $dropname; ?>','description': jsonforms},
		'cancelImg' : 'uploadify/cancel.png',
		'auto'      : true,
		'onAllComplete' : function(){setTimeout(window.location = '<?php echo "http://" . $_SERVER["HTTP_HOST"] . $_SERVER["PHP_SELF"] . "?viewmode=".$_REQUEST["viewmode"]."&dropname=". $dropname; if($_REQUEST['album']) { echo "&album=".$_REQUEST['album']; } ?>',3000);}, 
		'folder'    : '/uploads'
		});
		});

		$(document).ready(function() {
		$('#upcover').uploadify({
		'uploader'  : 'uploadify/uploadify.swf',
		'script'    : '<?php echo Dropio_Api::UPLOAD_URL; ?>',
		'multi'     : false,
		'scriptData': {'api_key': '<?php echo $API_KEY; ?>', 'version':'3.0','drop_name': '<?php echo $dropname; ?>','description': '\{\"album\"\:\"<?php echo strtolower(trim($_REQUEST['album'])); ?>\"\,\"isalbumcover\"\:\"true\"\}'},
		'cancelImg' : 'uploadify/cancel.png',
		'auto'      : true,
		'onAllComplete' : function(){setTimeout(window.location = '<?php echo "http://" . $_SERVER["HTTP_HOST"] . $_SERVER["PHP_SELF"] . "?viewmode=".$_REQUEST["viewmode"]."&dropname=". $dropname;  if($_REQUEST['album']) { echo "&album=".$_REQUEST['album']; } ?>',3000);}, 
		'folder'    : '/uploads'
		});
		});
		// ]]></script>
		
		
	
	<?php 
	/* 
		#################### 
		### Audio player ### 
		####################  */ ?>
		<script type="text/javascript" src="<?php echo $docroot; ?>audio-player/audio-player.js"></script>
		<script type="text/javascript">  
            AudioPlayer.setup("audio-player/player.swf", {  
                width: 290,
				transparentpagebg: "yes",
				checkpolicy:"yes"
				
            });  
        </script>
	<?php 
	/* 
		########################## 
		### HTML5 video player ### 
		##########################  */ ?>
		<link rel="stylesheet" href="<?php echo $docroot; ?>video-js/video-js.css" type="text/css" media="screen" title="Video JS" charset="utf-8">
		<script src="<?php echo $docroot; ?>video-js/video.js" type="text/javascript" charset="utf-8"></script>
		<script type="text/javascript" charset="utf-8">
			// If using jQuery
		     $(function(){
		       VideoJS.setup();
		     })
		</script>
	<?php 
	/* 
		############################################################
		### JSON metadata editor (refers to $.toJSON() function) ### 
		############################################################  */ ?>
		<link rel="stylesheet" type="text/css" href="jsoneditor/jsoneditor.css" />
		
		<script type="text/javascript" src="<?php echo $docroot; ?>jsoneditor/jquery.json-2.2.min.js"></script>
		<script type="text/javascript" src="<?php echo $docroot; ?>jsoneditor/jquery.jsoneditor.js"></script> 
		
		<script type="text/javascript">

			function grabMetadata(assetid, type) {
				obj = new Object();
				if(type == 'note') {
					obj['bandname'] = $('#' + assetid + '-bandname').val();
					obj['hometown'] = $('#' + assetid + '-hometown').val();
					obj['bandgenres'] = $('#' + assetid + '-bandgenres').val();
					obj['yearsactive'] = $('#' + assetid + '-yearsactive').val();
					obj['members'] = $('#' + assetid + '-members').val();
					obj['motto'] = $('#' + assetid + '-motto').val();
				} else {
					obj['title'] = $('#' + assetid + '-title').val();
					obj['album'] = $('#' + assetid + '-album').val();
					obj['tracknum'] = $('#' + assetid + '-tracknum').val();
					obj['year'] = $('#' + assetid + '-year').val();
					obj['genre'] = $('#' + assetid + '-genre').val();
					obj['bpm'] = $('#' + assetid + '-bpm').val();
					obj['composer'] = $('#' + assetid + '-composer').val();

					if ($('#' + assetid + '-allowdownload').attr('checked') == true) {
						obj['allowdownload'] = "true";
					} else {
						obj['allowdownload'] = "false";
					}
					 
					if ($('#' + assetid + '-isalbumcover').attr('checked') == true) {
						obj['isalbumcover'] = "true";
					} else {
						obj['isalbumcover'] = "false";
					}
				}
				updateAsset(assetid, obj);
			}


			function updateAsset(assetid, data){
				$('#wrap_je_' + assetid).toggle(400);
				if(assetid != "band-details"){
					var rawjsondata = $.toJSON(data);
					//Remove instances of consecutive multiple spaces
					jsondata = rawjsondata.replace(/ +/g, ' ').toLowerCase();
				} else {
					jsondata = $.toJSON(data).replace(/ +/g, ' ');
				}
				dataobj = {metadata:jsondata,
					assetid:assetid,
					action:'updateasset',
					dropname:'<?php echo $dropname; ?>',
					viewmode:'<?php echo $_REQUEST["viewmode"]; ?>'} ;
				$.ajax({type:'POST',data:dataobj
						,success: function(data) {
				    		alert('Saved metadata for ' + assetid + '.\nRefresh page to see changes.');
				  		},error: function(data) {
				    		alert('Error on ' + assetid);
				  		}});
				delete obj;
			}
		</script>

</head>
<body>
<div id='container' style='<?php if(($_REQUEST['viewmode'] == 'albums' || empty($_REQUEST['viewmode'])) && !($_REQUEST['album']) && !(preg_match('/safari/i',$_SERVER['HTTP_USER_AGENT']))) { ?> width: 1140px; <?php } else if(($_REQUEST['viewmode'] == 'albums' || empty($_REQUEST['viewmode'])) && !($_REQUEST['album']) && (preg_match('/safari/i',$_SERVER['HTTP_USER_AGENT']))) { ?> width: 1190px;<?php } else { ?> width: 900px; <?php } ?> margin: 0 auto; padding: 20px 60px 60px;'>
<?php 

/* 
############################
### Media rendering mode ### 
############################ */ ?>
<?php 
if ($_REQUEST["viewmode"] == 'media' || $_REQUEST["viewmode"] == 'permalink') 
{ ?>
<style type="text/css">
	body{background:url('<?php echo $docroot; ?>images/fancybg.png') #dbdbdb repeat-x;}
	table{border:1px solid #aaaaaa;}
	table th{border-bottom:1px solid black;}
	table td{border-bottom:1px solid #cccccc;padding:10px;}
	.metadata{background:#fff;border:1px solid #aaa;margin:4px;padding:4px;}
	
</style>
<script src='<?php echo $docroot; ?>osflv/AC_RunActiveContent.js' language='javascript'></script>

<div id="assets">
	<?php if ($_REQUEST['viewmode'] == 'permalink') { ?>
			<h2 style="font-size:24px">Viewing the asset <?php echo  $_REQUEST['assetid'] ?> the drop: <?php echo $displayname; ?> </h2>
			<h4><a href="<?php echo $_SERVER['PHP_SELF'] . '?viewmode=albums&dropname='.$dropname; ?>">&laquo;View all albums in this drop</a></h4>
	<?php }else{?>
			<h2 style="font-size:24px">Viewing assets in the drop: <?php echo $displayname; ?> </h2>
			<?php
			if ($logo) {
				echo $logo . "<br><br>";
			}
			?>

			<?php
			if(isUserLoggedIn()) {
				echo "Hello <a href='".$docroot."account.php'>". $loggedInUser->clean_username . "</a><br>";
			}
			?>

			<h4>View assets <a href="<?php echo $_SERVER['PHP_SELF'] . '?viewmode=sorted&dropname='.$dropname; ?>">sorted by type</a> or <a href="<?php echo $_SERVER['PHP_SELF'] . '?viewmode=albums&dropname='.$dropname; ?>">sorted by album</a>.</h4>

	<?php } ?>

		<br />
		<table><tr><th width="225">File Name</th><th width="550">Preview</th><th width="50">Links</th></tr> 
		<?php 
			GetAssetsByType();
		?>
		</table>
	<br /><br />

</div>

	<?php /* 
	#################################
	### Album list rendering mode ###
	################################# */ ?>
<?php
} else if (empty($_REQUEST["viewmode"]) || $_REQUEST["viewmode"] == 'albums') 
{ ?>
<style type="text/css">
	body{background:url('<?php echo $docroot; ?>images/fancybg.png') #dbdbdb repeat-x;}
	table{border:1px solid #aaaaaa;}
	table th{border-bottom:1px solid black;}
	table td{border-bottom:1px solid #cccccc;padding:10px;}
	.metadata{background:#fff;border:1px solid #aaa;margin:4px;padding:4px;}
</style>
<script src='<?php echo $docroot; ?>osflv/AC_RunActiveContent.js' language='javascript'></script>

<div id="assets">
	<?php if($_REQUEST['album']) { echo "<h2 style='font-size:24px'>Viewing album: ".ucwords(strtolower($_REQUEST['album']))."</h2>"; } else { ?>
	<h2 style="font-size:24px">Viewing albums in the drop: <?php echo $displayname; ?> </h2>
	<?php } ?>

	<?php
	if(!$_REQUEST['album']) {
		if($bandname && ($logo || $motto || $hometown || $bandgenres || $yearsactive || $members)) {
		echo "<div style='padding: 5px;width: 23em;border: 1px solid #AAAAAA;'>";

			if($bandname) {
				echo "<h2 style='margin-top: 5px; text-align: center; background: #C2DFFF'>".$bandname."</h2>";
			}

			if($logo || $motto) {
			echo "<div style='text-align:center'>".$logo;
				if($logo) {
					echo "<br>";
				}
				if($motto) {
					echo "<em>&quot;".$motto."&quot;</em>";
				}
			echo "</div><br>";
			}

			if ($hometown || $bandgenres || $yearsactive || $members) {
				if($hometown) { echo "<strong>Origin: </strong>".$hometown."<br>"; }
				if($bandgenres) { echo "<strong>Genres: </strong>".$bandgenres."<br>"; }
				if($yearsactive) { echo "<strong>Years active: </strong>".$yearsactive."<br>"; }
				if($members) { echo "<strong>Members: </strong>".$members."<br>"; }
			}

		echo "</div><br>";
		}
	}
	?>
	<?php
	if(isUserLoggedIn()) {
		echo "Hello <a href='".$docroot."account.php'>". $loggedInUser->clean_username . "</a><br>";
	}
	?>

	<h4>View assets <a href="<?php echo $_SERVER['PHP_SELF'] . '?viewmode=media&dropname='.$dropname; ?>">sorted by date</a> or <a href="<?php echo $_SERVER['PHP_SELF'] . '?viewmode=sorted&dropname='.$dropname; ?>">sorted by type</a>.</h4>

<script language="javascript" type="text/javascript" >
function viewalbum(x) {
if (document.listalbum.selection.value != null) {
	document.location.href = x
}
}

</script>

		<?php if($albumlist) { ?>
		View specific album:
		<form name='listalbum'>
		<select name='selection' onChange="viewalbum(document.listalbum.selection.options[document.listalbum.selection.options.selectedIndex].value)">
			<option value="<?php echo $_SERVER['PHP_SELF']."?viewmode=albums&dropname=".$dropname; ?>">View All</option>
			<?php 
			foreach($albumlist as $album) {
				echo "<option ";
				if($album == $_REQUEST['album']) {
					echo "selected=\"selected\" ";
				}
				echo "value=".$_SERVER['PHP_SELF']."?viewmode=albums&dropname=".$dropname."&album=".$album.">".ucwords($album)."</option>";
			} ?>
		</select>
		</form>
		<br>
		<?php } else {
			echo "<br>There are currently no available albums to view.";
		} ?>

		<?php 
		if($_REQUEST['album']) { 
			$album = strtolower($_REQUEST['album']); ?>
			<?php if(${'album_'.$album}) { ?>
				<h2 style="font-size:20px"><?php echo ucwords($album); ?> </h2>
				<?php
				if(${$album.'_cover'}) {
					echo ${$album.'_cover'}."<br><br>";
				} ?>
				<table><tr><th width="225">File Name</th><th width="550">Preview</th><th width="50">Links</th></tr>
				<?php
					GetAssetsByAlbum($album,'albumview');
				?>
				</table>

				<?php if(${$album.'_images'}) {?>
				<br>
				<h3>Images in this album</h3>
				<table>
				<?php
					DisplayAlbumImages(${$album.'_images'},$album,'albumview');
				?>
				</table>
			<?php } }?>

			<br><br>
		<?php } else { foreach($albumlist as $album){ 
			if(${'album_'.$album}) { ?> 
				<h2 style="font-size:20px"><?php echo ucwords($album); ?></h2>
				<?php
				if(${$album.'_cover'}) {
					echo ${$album.'_cover'}."<br><br>";
				} ?>
				<div style='overflow:hidden'>
				<div style='float:left;'>
				<table style='width:266px'>
				<tr><th>Images</th></tr>
				<?php 
					DisplayAlbumImages(${$album.'_images'},$album);
				?>
				</table>
				</div>

				<div style='float:right'>
				<table><tr><th width="225">File Name</th><th width="550">Preview</th><th width="50">Links</th></tr> 
				<?php 
					GetAssetsByAlbum($album);
				?>
				</table>
				<br>
				<div style="margin: 0px 4px; text-align: right; font-size: 10px;"><em><a href="<?php echo $_SERVER['PHP_SELF'].'?dropname='.$dropname.'&viewmode=albums&album='.$album ?>">See full album...</a></em></div>
				</div>

				</div>
				<?php 
			}
		}
		} ?>

</div>

<?php /*
#####################################
### Sorted (group rendering mode) ###
##################################### */ ?>

<?php
} else if ($_REQUEST["viewmode"] == 'sorted') { ?>
<style type="text/css">
	body{background:url('<?php echo $docroot; ?>images/fancybg.png') #dbdbdb repeat-x;}
	table{border:1px solid #aaaaaa;}
	table th{border-bottom:1px solid black;}
	table td{border-bottom:1px solid #cccccc;padding:10px;}
	.metadata{background:#fff;border:1px solid #aaa;margin:4px;padding:4px;}
</style>
<script src='<?php echo $docroot; ?>osflv/AC_RunActiveContent.js' language='javascript'></script>

<div id="assets">
	<h2 style="font-size:24px">Viewing assets in the drop: <?php echo $displayname ?> </h2>
		<?php
		if($logo) {
			echo $logo . "<br><br>";
		}
		if(isUserLoggedIn()) {
			echo "Hello <a href='".$docroot."account.php'>". $loggedInUser->clean_username . "</a><br>";
		}
		?>
	<h4>View assets	<a href="<?php echo $_SERVER['PHP_SELF'] . '?viewmode=media&dropname='.$dropname; ?>">sorted by date</a> or <a href="<?php echo $_SERVER['PHP_SELF'] . '?viewmode=albums&dropname='.$dropname; ?>">sorted by album</a>.</h4>
		<br />
		<?php
		foreach($alltypes as $type){
			if($assetCount[$type]) { ?>
				<?php if(!isUserLoggedIn() && $loggedInUser->clean_username != $owner && $type == 'note') {
					continue;
				} ?>
				<h2><?php echo PluralizeType($type); ?></h2>
				<table><tr><th width="225">File Name</th><th width="550">Preview</th><th width="50">Links</th></tr>
				<?php
				GetAssetsByType(array($type));
				?>
				</table>
				<?php
			}
		} ?>
	<br /><br />

</div>



<?php } ?>



<?php if($loggedInUser && $loggedInUser->clean_username == $owner && isUserLoggedIn()) {
if(!($_REQUEST['album'])) {
?>
<div id="uploader" style="background:#ffffff;-moz-border-radius:20px;-webkit-border-radius:20px;width:660px;padding:10px 20px 20px 20px;margin-top:30px">

	<h1>Upload album</h1>
	<strong>Name of album:</strong>
	<br>
	<form method="post" action="<?php echo $docroot; ?>uploadalbum.php">
	<input id="upalbum" name="upalbum" type="text" />
	<input name='dropname' value="<?php echo $dropname; ?>" type="hidden" />
	<input name='displayname' value="<?php echo $displayname; ?>" type="hidden" />
	<input type="submit" value="Submit" />
	</form>
	<br>
	<hr />
	<h1>Upload new band logo</h1>
	<input id="uplogo" name="uplogo" type="file" />
	<br>

</div>
<?php }
if(${'album_'.$_REQUEST['album']}) {
?>
<div id="uploader2" style="background:#ffffff;-moz-border-radius:20px;-webkit-border-radius:20px;width:660px;padding:10px 20px 20px 20px;margin-top:30px">
	<h1>Upload new files to this album*</h1>
	<input id="file" name="file" type="file" />
	<br>
	*You can select multiple files with CTRL + Left Click
	<br>
	<hr />
	<h1>Upload new album cover</h1>
	<input id="upcover" name="upcover" type="file" />
	<br>

</div>
<br>
<?php
} } ?>

<br><br>

<?php
if(isUserLoggedIn()) {
	echo "<a href='logout.php'>Log out</a>";
} else {
	echo "<a href='login.php'>Log in</a>";
}

?>

</div>
</body></html>
<?php
function GetAssetPreview($a){
global $detect, $docroot;
if ($a->type == "image"){
	foreach ($a->roles as $name=>$r) { 
		if ($r["name"] == "original_content"){
			$dimensions['width'] = $r["width"];
			$dimensions['height'] = $r["height"];
		}
	}
	foreach ($a->roles as $name=>$r) { 
		if ($r["name"] == "large_thumbnail"){ 
			if ($r["locations"][0]["status"] == "complete"){
				$preview = "<img style='border: 0px; width:";
				$preview .= $r["width"] / 2;
				$preview .= "px;height:";
				$preview .= $r["height"] / 2;
				$preview .= "px;' src=\"";
				$preview .= $r["locations"][0]["file_url"];
				$preview .= "\" alt='";
				$preview .= htmlspecialchars($a->name);
				$preview .= "'>";
				//Original dimensions
				//$preview .= "<br />Original width = " . $dimensions['width'] . ", height = " .$dimensions['height'];
			}
		}
	}
} elseif ($a->type == "audio"){
	foreach ($a->roles as $name=>$r) {  
		if ($r["name"] == "web_preview") {
			if ($r["locations"][0]["status"] == "complete"){
				//play using HTML5 for mobile (webkit and iphone/ipad support)
				if($detect->isMobile()){
					$preview ='<audio src="'.$r["locations"][0]["file_url"].'" controls autobuffer></audio>';
				}else{
				//use an open source flash player for regular web browsers
					$preview ='<p id="ap-'.$a->name.'"></p>  
				<script type="text/javascript">  
				AudioPlayer.embed("ap-'.$a->name.'", 
						{
							soundFile: "'.urlencode($r["locations"][0]["file_url"]).'",
							titles: "'.$a->title.'"
						});  
				</script>';
				}
			}
		}
	}
}elseif ($a->type == "movie"){
	$movie = '';
	//first get the poster image
	$poster = '';
	foreach ($a->roles as $name=>$r) { 
		if ($r["name"] == "large_thumbnail"){
			if ($r["locations"][0]["status"] == "complete"){
				$poster = $r["locations"][0]["file_url"];
			}
		}
	}
	//then get the web-friendly h.264 m4v file and wrap it in an HTML5 player with Flash fallback
	foreach ($a->roles as $name=>$r) { 
		if ($r["name"] == "web_preview") {
			if ($r["locations"][0]["status"] == "complete"){
				$movie = $r["locations"][0]["file_url"];
				$preview = '
				<!-- Begin VideoJS -->
				  <div class="video-js-box">
				    <!-- Using the Video for Everybody Embed Code http://camendesign.com/code/video_for_everybody -->
				    <video class="video-js" width="400" height="325" poster="'.$poster.'" controls preload>
				      <source src="'.$movie.'" type=\'video/mp4; codecs="avc1.42E01E, mp4a.40.2"\'>';
						$preview .= "<object class='vjs-flash-fallback' width='400' height='325'>
						  <param name='allowFullScreen' value='true'>
						  <param name='movie' value='".$docroot."osflv/OSplayer.swf?movie=";
						$preview .= urlencode($movie);
						$preview .= "&btncolor=0x333333&accentcolor=0x31b8e9&txtcolor=0xdddddd&volume=30";
						$preview .= "&previewimage=" . urlencode($poster);
						$preview .= "&autoload=off&vTitle=".urlencode($a->title)."&showTitle=yes'>
						  <embed src='" . $docroot . "osflv/OSplayer.swf?movie=";
						$preview .=  urlencode($movie);
						$preview .= "&btncolor=0x333333&accentcolor=0x31b8e9&txtcolor=0xdddddd&volume=30";
						$preview .= "&previewimage=" . urlencode($poster);
						$preview .= "&autoload=off&vTitle=".urlencode($a->title)."&showTitle=yes' width='400' height='325' allowFullScreen='true' type='application/x-shockwave-flash'>
						 </object>";
				    $preview .= '</video>
				    <p class="vjs-no-video"></p>
				  </div>
				  <!-- End VideoJS -->';
			 //$preview .= "width = " . print_r($r);
				
			}
		}
	}
}elseif ($a->type == "document"){
	foreach ($a->roles as $name=>$r) {  
		if ($r["name"] == "web_preview"){
			if ($r["locations"][0]["status"] == "complete"){
				$docurl = $r["locations"][0]["file_url"];
				if(!$detect->isMobile()){
					$preview .= "<iframe style='width:530px;height:350px' frameborder='0' src=\"http://docs.google.com/viewer?embedded=true&url=";
					$preview .= urlencode($docurl);
					$preview .= "\"></iframe>";
				}else{
					$preview = '<object data="'.$docurl.'" type="application/pdf" width="500" height="375" />';
				}
			}
		}
	}
}elseif ($a->type == "link"){
	$preview = '<a href="'.$a->url.'">' . $a->url . '</a>';
}elseif ($a->type == "note"){
	$preview = $a->roles[0]["contents"];
}else{
	$preview = "<a href='". GetOriginalFileUrl($a) . "'><img src=' " .$docroot . "images/downloaddisk.png' style='border:none' alt='download'/></a>";
	#$preview = h($a->inspect)
}
return $preview;
}
function GetOriginalFileUrl($a){
global $dropname, $API_KEY;
$origfile = "http://api.drop.io";
$origfile .= "/drops/".$dropname."/assets/".$a->name."/download/original?api_key=".$API_KEY;
$origfile .= "&version=3.0";
if ($a->roles[0]["locations"][0]["name"] != "DropioS3"){
	$origfile .= "&location=" . $a->roles[0]["locations"][0]["name"];
}
return $origfile;
}


function GetAssetsByAlbum($album,$display = 'preview'){
global $drop, $assets, $API_KEY, $dropname, $assetCount, $owner, $loggedInUser, $albumlist, $albums ;
$page = 1;

if($display = 'preview') {
	$val = 0;
}

foreach($albums[$album]->tracks as $x) {
	$albums[$album]->tracks = (object) $x;
foreach ($albums[$album] as $name=>$a) {
	if($a->album == $album){
		$origfile = GetOriginalFileUrl($a);
		unset($dimension);
		$dimension = Array();

				$description = $a->description;
				$data = '{}';
				if(json_decode(stripslashes(htmlspecialchars_decode($description)))){
					$data = stripslashes(htmlspecialchars_decode($description));
				}
				$jsondata = json_decode($data);

				$metadata  = "<div class='metadata' id='wrap_je_".$a->name."' style='display:none;'>";
				$metadata .= "<div class='metadata' id='je_".$a->name."' '>";
				$metadata .= "<form><table>";
				$metadata .= "<tr><td>Title:</td><td><input type='text' id='$a->name-title' value='$jsondata->title' /></td></tr>";
				$metadata .= "<tr><td>Album:</td><td><input type='text' id='$a->name-album' value='$jsondata->album' /></td></tr>";
				if($a->type == 'audio') {
					$metadata .= "<tr><td>Track #:</td><td><input type='text' id='$a->name-tracknum' value='$jsondata->tracknum' /></td></tr>";
					$metadata .= "<tr><td>Year:</td><td><input type='text' id='$a->name-year' value='$jsondata->year' /></td></tr>";
					$metadata .= "<tr><td>Genre:</td><td><input type='text' id='$a->name-genre' value='$jsondata->genre' /></td></tr>";
					$metadata .= "<tr><td>BPM:</td><td><input type='text' id='$a->name-bpm' value='$jsondata->bpm' /></td></tr>";
					$metadata .= "<tr><td>Composer:</td><td><input type='text' id='$a->name-composer' value='$jsondata->composer' /></td></tr>";
				} else if($a->type == 'image') {
					$metadata .= "<tr><td>Is Album Cover?:</td><td><input type='checkbox' id='$a->name-isalbumcover' ";
					if($jsondata->isalbumcover == 'true') {
						$metadata .= "checked ";
					}
					$metadata .= "/></td></tr>";
				} else {
					
				}
				$metadata .= "<tr><td>Allow Download?:</td><td><input type='checkbox' id='$a->name-allowdownload' ";
				if($jsondata->allowdownload == 'true') {
					$metadata .= "checked ";
				}
				$metadata .= "/></td></tr></table>";
				$metadata .= '<input type="button" value="Save"';
				$metadata .= " onclick=\"grabMetadata('".$a->name."','noteless');\" />";
				$metadata .= "</form></div></div>";


				if($_REQUEST['title'] && $jsondata->title != $_REQUEST['title']) {
					continue;
				}
				if($_REQUEST['tracknum'] && $jsondata->tracknum != $_REQUEST['tracknum']) {
					continue;
				}
				if($_REQUEST['year'] && $jsondata->year != $_REQUEST['year']) {
					continue;
				}
				if($_REQUEST['genre'] && $jsondata->genre != $_REQUEST['genre']) {
					continue;
				}
				if($_REQUEST['bpm'] && $jsondata->bpm != $_REQUEST['bpm']) {
					continue;
				}
				if($_REQUEST['composer'] && $jsondata->composer != $_REQUEST['composer']) {
					continue;
				}

				if($jsondata->isHidden && ($jsondata->isHidden == 'true')) {
					continue;
				}
				if($jsondata->isalbumcover && $jsondata->isalbumcover== 'true') {
					continue;
				}
				if($jsondata->isbandlogo && ($jsondata->isbandlogo == 'true') && $loggedInUser->clean_username != $owner){
					continue;
				}
				if($a->type == 'note' && $a->name == "band-details" && $a->title == "Band Details" && $loggedInUser->clean_username != $owner){
					continue;
				}

		?>
		<tr>
			<td>
				<strong><?php

				$titlelen = strlen($a->title);
				$maxlen = '30';
				if(!($titlelen > $maxlen)){
					echo substr($a->title, 0, 30);
				} else {
					echo "<span title=\"$a->title\">".substr($a->title, 0, 30)."...</span>";
				}
				?>
				</strong>

			</td>
			<td>
				<?php 


				$preview = GetAssetPreview($a);

				echo $preview; 
				if($a->type == 'audio') {
					echo "<br><br>";
					if($jsondata->title) {
						echo "Title: " . ucwords($jsondata->title) . "<br>";
					}
					if($jsondata->album) {
						echo "Album: " . ucwords($jsondata->album) . "<br>";
					}
					if($jsondata->tracknum) {
						echo "Track #: " . $jsondata->tracknum . "<br>";
					}
					if($jsondata->year) {
						echo "Year: " . $jsondata->year . "<br>";
					}
					if($jsondata->genre) {
						echo "Genre: " . ucwords($jsondata->genre) . "<br>";
					}
					if($jsondata->bpm) {
						echo "BPM: " . $jsondata->bpm . "<br>";
					}
					if($jsondata->composer) {
						echo "Composer: " . ucwords($jsondata->composer) . "<br>";
					}
				}
				
			?>
				<?php if ($metadata){echo $metadata;}?>
				</td>
					<td><?php if ($a->type != "note") {
					if($jsondata->allowdownload != 'false') { ?>
						<a href="<?php echo $origfile; ?>">Download File</a>
					<?php } else {
						if($owner == $loggedInUser->clean_username) { ?>
							<a href="<?php echo $origfile; ?>">Download File</a>
						<?php } else { ?>
							Download locked
					<?php } } } ?>


						<?php
						if(isUserLoggedIn() && ($owner == $loggedInUser->clean_username)) {
							echo "<hr /><a href=" . $_SERVER['PHP_SELF'] . '?dropname=' . $dropname . '&viewmode=' . $_REQUEST['viewmode'] . '&action=delete&assetid=' . $a->name . " onClick=\"javascript:return confirm('Are you sure you want to delete this asset?')\">Delete asset</a><hr />";
							echo "<a href=\"#\" onclick=\"\$('#wrap_je_" . $a->name . "').toggle(400);return false;\">Edit metadata</a>";

						}
						?>



					</td>
				</tr>
					<?php
			$val++;
			if($val == 3) {
				break 2;
			}
		}
	} }
}


function GetAssetsByType($type = array("image", "movie", "audio", "document", "other", "note", "link")){
global $drop, $assets, $API_KEY, $dropname, $assetCount, $owner, $loggedInUser ;
$page = 1;
foreach ($assets as $name=>$a) {
		if(in_array($a->type, $type)){
		$origfile = GetOriginalFileUrl($a);
		unset($dimension);
		$dimension = Array();

				$description = $a->description;
				$data = '{}';
				if(json_decode(stripslashes(htmlspecialchars_decode($description)))){
					$data = stripslashes(htmlspecialchars_decode($description));
				}
				$jsondata = json_decode($data);

				$metadata  = "<div class='metadata' id='wrap_je_".$a->name."' style='display:none;'>";
				$metadata .= "<div class='metadata' id='je_".$a->name."' '>";
				$metadata .= "<form><table>";
				if($a->type == 'note') {
					$metadata .= "<tr><td>Band Name:</td><td><input type='text' id='$a->name-bandname' value='$jsondata->bandname' /></td></tr>";
					$metadata .= "<tr><td>Hometown:</td><td><input type='text' id='$a->name-hometown' value='$jsondata->hometown' /></td></tr>";
					$metadata .= "<tr><td>Genres:</td><td><input type='text' id='$a->name-bandgenres' value='$jsondata->bandgenres' /></td></tr>";
					$metadata .= "<tr><td>Years Active:</td><td><input type='text' id='$a->name-yearsactive' value='$jsondata->yearsactive' /></td></tr>";
					$metadata .= "<tr><td>Members:</td><td><input type='text' id='$a->name-members' value='$jsondata->members' /></td></tr>";
					$metadata .= "<tr><td>Motto:</td><td><input type='text' id='$a->name-motto' value='$jsondata->motto' /></td></tr>";
					$metadata .= "</table>";
					$metadata .= '<input type="button" value="Save"';
					$metadata .= " onclick=\"grabMetadata('".$a->name."','note');\" />";
				} else {
					$metadata .= "<tr><td>Title:</td><td><input type='text' id='$a->name-title' value='$jsondata->title' /></td></tr>";
					$metadata .= "<tr><td>Album:</td><td><input type='text' id='$a->name-album' value='$jsondata->album' /></td></tr>";
					if($a->type == 'audio') {
						$metadata .= "<tr><td>Track #:</td><td><input type='text' id='$a->name-tracknum' value='$jsondata->tracknum' /></td></tr>";
						$metadata .= "<tr><td>Year:</td><td><input type='text' id='$a->name-year' value='$jsondata->year' /></td></tr>";
						$metadata .= "<tr><td>Genre:</td><td><input type='text' id='$a->name-genre' value='$jsondata->genre' /></td></tr>";
						$metadata .= "<tr><td>BPM:</td><td><input type='text' id='$a->name-bpm' value='$jsondata->bpm' /></td></tr>";
						$metadata .= "<tr><td>Composer:</td><td><input type='text' id='$a->name-composer' value='$jsondata->composer' /></td></tr>";
					} else if($a->type == 'image') {
						$metadata .= "<tr><td>Is Album Cover?:</td><td><input type='checkbox' id='$a->name-isalbumcover' ";
						if($jsondata->isalbumcover == 'true') {
							$metadata .= "checked ";
						}
						$metadata .= "/></td></tr>";
					}
					$metadata .= "<tr><td>Allow Download?:</td><td><input type='checkbox' id='$a->name-allowdownload' ";
					if($jsondata->allowdownload == 'true') {
						$metadata .= "checked ";
					}
					$metadata .= "/></td></tr>";
					$metadata .= "</table>";
					$metadata .= '<input type="button" value="Save"';
					$metadata .= " onclick=\"grabMetadata('".$a->name."','noteless');\" />";
				}
				$metadata .= "</form></div></div>";

				if($_REQUEST['title'] && $jsondata->title != $_REQUEST['title']) {
					continue;
				}
				if($_REQUEST['album'] && $jsondata->album != $_REQUEST['album']) {
					continue;
				}
				if($_REQUEST['tracknum'] && $jsondata->tracknum != $_REQUEST['tracknum']) {
					continue;
				}
				if($_REQUEST['year'] && $jsondata->year != $_REQUEST['year']) {
					continue;
				}
				if($_REQUEST['genre'] && $jsondata->genre != $_REQUEST['genre']) {
					continue;
				}
				if($_REQUEST['bpm'] && $jsondata->bpm != $_REQUEST['bpm']) {
					continue;
				}
				if($_REQUEST['composer'] && $jsondata->composer != $_REQUEST['composer']) {
					continue;
				}

				if($jsondata->isHidden && ($jsondata->isHidden == 'true')) {
					continue;
				}
				if($jsondata->isbandlogo && ($jsondata->isbandlogo == 'true') && $loggedInUser->clean_username != $owner){
					continue;
				}
				if($a->type == 'note' && $a->name == "band-details" && $a->title == "Band Details" && $loggedInUser->clean_username != $owner){
					continue;
				}

		?>
		<tr>
			<td>
				<strong><?php

				$titlelen = strlen($a->title);
				$maxlen = '30';
				if(!($titlelen > $maxlen)){
					echo substr($a->title, 0, 30);
				} else {
					echo "<span title=\"$a->title\">".substr($a->title, 0, 30)."...</span>";
				}
				?>
				</strong>

			</td>
			<td>
				<?php 


				$preview = GetAssetPreview($a);
				if($a->type =='document') {
					echo "<div style='text-align:center'>".$preview."</div>"; 
				} else {
					echo $preview;
				}
				if($a->type == 'audio') {
					echo "<br><br>";
					if($jsondata->title) {
						echo "Title: " . ucwords($jsondata->title) . "<br>";
					}
					if($jsondata->album) {
						echo "Album: " . ucwords($jsondata->album) . "<br>";
					}
					if($jsondata->tracknum) {
						echo "Track #: " . $jsondata->tracknum . "<br>";
					}
					if($jsondata->year) {
						echo "Year: " . $jsondata->year . "<br>";
					}
					if($jsondata->genre) {
						echo "Genre: " . ucwords($jsondata->genre) . "<br>";
					}
					if($jsondata->bpm) {
						echo "BPM: " . $jsondata->bpm . "<br>";
					}
					if($jsondata->composer) {
						echo "Composer: " . ucwords($jsondata->composer) . "<br>";
					}
				}

				if($a->type == 'note') {
					if($jsondata->bandname) {
						echo "Band Name: ".$jsondata->bandname . "<br>";
					}
					if($jsondata->hometown) {
						echo "Hometown: ".ucwords($jsondata->hometown) . "<br>";
					}
					if($jsondata->bandgenres) {
						echo "Genres: ".ucwords($jsondata->bandgenres) . "<br>";
					}
					if($jsondata->yearsactive) {
						echo "Years active: ".ucwords($jsondata->yearsactive) . "<br>";
					}
					if($jsondata->members) {
						echo "Members: ".$jsondata->members . "<br>";
					}
					if($jsondata->motto) {
						echo "Motto: ".$jsondata->motto . "<br>";
					}
				}
				
			?>
				<?php if ($metadata){echo $metadata;}?>
				</td>
					<td><?php if ($a->type != "note") {
					if($jsondata->allowdownload != 'false') { ?>
						<a href="<?php echo $origfile; ?>">Download File</a>
					<?php } else {
						if($owner == $loggedInUser->clean_username) { ?>
							<a href="<?php echo $origfile; ?>">Download File</a>
						<?php } else { ?>
							Download locked
					<?php } } } ?>

						<?php
						if(isUserLoggedIn() && ($owner == $loggedInUser->clean_username)) {
						if($a->type != 'note' && $a->name != "band-details" && $a->title != "Band Details"){
							echo "<hr /><a href=" . $_SERVER['PHP_SELF'] . '?dropname=' . $dropname . '&viewmode=' . $_REQUEST['viewmode'] . '&action=delete&assetid=' . $a->{$a->primary_key} . " onClick=\"javascript:return confirm('Are you sure you want to delete this asset?')\">Delete asset</a><hr />";
						}
						echo "<a href=\"#\" onclick=\"\$('#wrap_je_" . $a->{$a->primary_key} . "').toggle(400);return false;\">Edit metadata</a>";

						}
						?>



					</td>
				</tr>
					<?php
		}
		
	}
			
	  
}
function PluralizeType($type){
	if($type == "image"){
		return "Images";
	}elseif($type == "movie"){
		return "Movies";
	}elseif($type == "audio"){
		return "Audio";
	}elseif($type == "document"){
		return "Documents";
	}elseif($type == "note"){
		return "Notes";
	}elseif($type == "link"){
		return "Links";
	}elseif($type == "other"){
		return "Other Files";
	}
}

function DisplayAlbumImages($images,$album,$display = 'preview') {
global $dropname;

if($display == 'preview') {
	if($images) {
		$i = 1;
		foreach($images as $image) {
			$preview = GetAssetPreview($image);
			echo "<tr><td style='text-align:center'>".$preview."</td></tr>";
			$i++;
			if($i == 3) {
				break;
			}
		}
		echo "<tr><td style='text-align:right'><em style='font-size:10px'><a href='".$_SERVER['PHP_SELF']."?dropname=".$dropname."&viewmode=albums&album=".$album."'>See full album...</a></em></td></tr>";
	} else {
		echo "<tr><td style='text-align:center'><em>There are currently no<br>images to display.</em></td></tr>";
	}
} elseif($display == 'albumview') {
	if($images) {
		$i = 0;
		echo "<tr>";
		foreach($images as $image) {
			$preview = GetAssetPreview($image);
			$origfile = GetOriginalFileUrl($image);
			if($i == 2) {
				if(preg_match('/safari/i',$_SERVER['HTTP_USER_AGENT'])) {
					echo "<td style='width: 277px'><a href='".$origfile."'>".$preview."</a></td>";
				} else {
					echo "<td style='width: 265px'><a href='".$origfile."'>".$preview."</a></td>";
				}
				echo '</tr><tr>';
				$i = 0;
				$i++;
			} else {
				if(preg_match('/safari/i',$_SERVER['HTTP_USER_AGENT'])) {
					echo "<td style='width: 277px'><a href='".$origfile."'>".$preview."</a></td>";
				} else {
					echo "<td style='width: 265px'><a href='".$origfile."'>".$preview."</a></td>";
				}
				$i++;
			}
		}
		echo "</tr>";
	}
}
}
?>
