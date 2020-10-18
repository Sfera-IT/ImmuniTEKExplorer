<?php
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

/*
// Read static JSON data
$static = json_decode(file_get_contents("static.json"), true);

// Read computed JSON data
$currentRaw = file_get_contents("./data2/current.json");
//$current = json_decode($currentRaw, true);
*/
// Read template
$html = file_get_contents("index.template.html");

//$html = str_replace("\"{@data}\"", $currentRaw, $html);

//$html = str_replace("{@chart_tek_dataset}", json_encode($current["charts"]["tek"]["datasets"]), $html);

//$totKeys = $current["countries"]["it"]["totKeys"];

echo $html;

?>