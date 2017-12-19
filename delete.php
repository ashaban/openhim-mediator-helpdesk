<?php
require_once __DIR__ . "/vendor/autoload.php";
$questions = (new MongoDB\Client)->{"helpdesk"}->questions;
		$date = date("dmy");
		$regex = new MongoDB\BSON\Regex("^$date");
		$res = $questions->findOne(array("ticket_number"=>"19121710"));
echo $res["reporter_rapidpro_id"];
?>
