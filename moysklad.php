<?php
	error_reporting(E_ALL);
	ini_set("display_errors", 1);

	include_once('../values/moysklad.php');
	include_once('../class/moysklad.php');
	include_once('../class/sql.php');
	
	$DB = new sql($host,$user,$pass,$data);
	
	$moysklad = new moysklad($_ENV['token']);
	
	$moysklad->get_store();
	$moysklad->set_db($DB);
	$moysklad->create_items();
	$moysklad->get_created_item();