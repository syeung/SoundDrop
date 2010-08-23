<?php
	/*
		UserCake Version: 1.4
		http://usercake.com
		
		Developed by: Adam Davis
	*/
	include("models/config.php");
	
	//Log the user out
	if(isUserLoggedIn()) $loggedInUser->userLogOut();

	if(!empty($websiteUrl)) 
	{
		$add_http = "";
		
		if(strpos($websiteUrl,"http://") === false)
		{
			$add_http = "http://";
		}


		$redirect = ($_COOKIE['redirect_to'] ? $_COOKIE['redirect_to'] : 'index.php');
	
//		header("Location: ".$add_http.$websiteUrl);
		header("Location: ".$redirect);
		die();
	}
	else
	{
		header("Location: http://".$_SERVER['HTTP_HOST']);
		die();
	}	
?>


