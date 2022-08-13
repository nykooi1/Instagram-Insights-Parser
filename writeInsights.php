<?php
include "readInsights.php";

if(isset($_GET["url"]) && isset($_GET["account"]) && isset($_GET["insightsCount"])){
    $url = $_GET["url"];
    $accountName = $_GET["account"];
    $insightsCount = $_GET["insightsCount"];
}

//reads insights data, then writes the data to a json file
function writeInsights($url, $accountName, $insightsCount){
    $mergedData = readInsights($url, $accountName, $insightsCount); 
    
    $mergedDataDecoded = json_decode($mergedData);
    
    $postedOnTime = $mergedDataDecoded->time;
    
    $date = date('m-d-Y-Hi', $postedOnTime);
    
    $filename =   $date . "-" . $accountName . ".json";
    
    if(!file_exists('accounts/' . $accountName)) {
        mkdir('accounts/' . $accountName, 0777, true);
    }
    
    $jsonFile = fopen("accounts/" . $accountName . "/" . $filename, "w") or die("Unable to open file!");
    $mergedData = json_encode($mergedData);
    fwrite($jsonFile, $mergedData);
    fclose($jsonFile);
}

$url = "http://c9.noah.kim/insightsReader/images/eureka-2019-09-19.PNG";
$accountName = "eureka";
$insightsCount = 4;
writeInsights($url, $accountName, $insightsCount);
