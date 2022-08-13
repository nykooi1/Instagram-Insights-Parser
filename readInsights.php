<?php 
include('v.php');

use thiagoalessio\TesseractOCR\TesseractOCR;

//include('insights.php');
$quality = 100;
$quality60 = 60;

if(isset($_GET["url"]) && isset($_GET["account"]) && isset($_GET["insightsCount"])){
    
    $url = $_GET["url"];

    $accountName = $_GET["account"];

    $insightsCount = $_GET["insightsCount"];

    readInsights($url, $accountName, $insightsCount);    
}

function insightsRead($resizedFileCut, $filetype, $insightsCount){
    require_once __DIR__ . '/tesseract/vendor/autoload.php';
    
    //file of the orginial resized image
    $originalFilePath = "images/resized/". $resizedFileCut . $filetype;
   
    //file of new image without 200px at top
    $newFilePath = "images/tmp/". $resizedFileCut. "-topCropped" . $filetype;
    
    //gets the width and height of the orginial resized image
    list($width, $height) = getimagesize($originalFilePath);
    
    crop($originalFilePath, 0, 200, 900, ($height - 200), $newFilePath);
    
    $strings = explode("\n", (new TesseractOCR($newFilePath))->run());
    
    $categoryDataRow = "";
    
    for($i = 0; $i < sizeof($strings); $i++){
       if(strlen(trim($strings[$i])) == 0){
            unset($strings[$i]);
            continue;
        } 
    }
    
    $strings = array_values($strings);
    
    $index = 0;
    
    foreach($strings as $string){
        if(strstr($string, "Interactions") || strstr($string, "Actions")){
            $categoryDataRow = $strings[$index - 1];
            break;        
        }
        $index++;
    }
    
    $categoryData = explode(" ", $categoryDataRow);
    
    $numFound = 0;
    $kIndex = 0;
    
    foreach($categoryData as $data){
        $dataCopy = vCleanString($data);
        //if it contains K
        if(!is_numeric($dataCopy)){
            break; 
        } else {
            //check if it has K and it is greater than 2...
            //only have to worry about greater than 2 because of the . appearing in 3+ digits
            if(strstr($data, "K") && strlen($data) > 2){
                $kIndex = $numFound; 
                //call increment crop
                incrementCrop($resizedFileCut, $filetype, $insightsCount);
                //readCategories
                $filePath = "images/tmp/croppedInsights/" . $resizedFileCut . "-" . $kIndex . $filetype;
                $categorySliceData = json_decode(shell_exec('export GOOGLE_APPLICATION_CREDENTIALS="key2.json" && php interactionQuery.php ' . $filePath));
                if($categorySliceData != null){
                    foreach($categorySliceData as $data){
                        $dataCopy = vCleanString($data);
                        if(is_numeric($dataCopy)){
                            $categoryData[$kIndex] = $data;
                            break;
                        } else {
                            $categoryData[$kIndex] = "NULL";
                            break;
                        }
                    }
                } else {
                    $categoryData[$kIndex] = "NULL";
                }
            }
            $numFound++;
        }
    }
    
    if($numFound == $insightsCount){
        $categoryDataSorted = array();
        if($insightsCount == 3){
            $categoryDataSorted["likes"] = $categoryData[0];      
            $categoryDataSorted["comments"] = $categoryData[1];
            $categoryDataSorted["shares"] = "NA";
            $categoryDataSorted["saves"] = $categoryData[2]; 
        } else if($insightsCount == 4){
            $categoryDataSorted["likes"] = $categoryData[0];
            $categoryDataSorted["comments"] = $categoryData[1]; 
            $categoryDataSorted["shares"] = $categoryData[2]; 
            $categoryDataSorted["saves"] = $categoryData[3]; 
        }    
        unlink($newFilePath);
        return $categoryDataSorted;
    } else {
        unlink($newFilePath);
        incrementCrop($resizedFileCut, $filetype, $insightsCount);
        return readCategories($resizedFileCut, $insightsCount, $filetype);
    }
}

function incrementCrop($resizedFileCut, $filetype, $insightsCount){
    require_once __DIR__ . '/tesseract/vendor/autoload.php';
    
    $cropped = false;
    $startingY = 200;
    $fileCounter = 0;
    
    while($cropped == false){
        
        //file of the orginial resized image
        $originalFilePath = "images/resized/".$resizedFileCut . $filetype;
       
        //file of new increment cropped images
        $newFilePath = "images/tmp/". $resizedFileCut. "-". $fileCounter . $filetype;
        
        //gets the width and height of the orginial resized image
        list($width, $height) = getimagesize($originalFilePath);
    
        //crop off top 200px and store it in the newFilePath
        crop($originalFilePath, 0, $startingY, 900, ($height - $startingY), $newFilePath);
        
        //user tesseract to read if posted on or am / pm is still in the image
        $strings = explode("\n", (new TesseractOCR($newFilePath))->run());
        
        $json = array();
        
        foreach($strings as $string){
            $String = strtoupper($string);
            //if it's just a blank line
            //continue to next String
            if(strlen(trim($String)) == 0){
                continue;
            }
            //If the string contains POSTED ON , AM , or PM
            //Continue cropping down additional 50px
            //Else crop the insights into 3rd or 4ths
            if(substr($String, 0, 9) == "POSTED ON" || substr($String, -2) == "PM" || substr($String,-2) == "AM" || $String == "11:01 .ULL ? E)"){
                $continueCrop = true;
                break;
            }else{
                $continueCrop = false;
            }
        }
        if($continueCrop == true){
                $fileCounter += 1;
                $startingY += 50;
        }else{
            //needs the px cut off from tp
            //needs the insightscount 
            //needs the filetype
            insightsCrop($startingY, $insightsCount, $resizedFileCut, $filetype);
            $cropped = true;
            for($i = 0; $i <= $fileCounter; $i++){
               unlink("images/tmp/". $resizedFileCut. "-". $i . $filetype);
            }
        }
    }
}

function insightsCrop($startingY, $insightsCount, $resizedFileCut, $filetype){
    //gets the resized image size
    list($width, $height) = getimagesize("images/resized/" . $resizedFileCut . $filetype);
    //if it is being cut into 4ths
    if($insightsCount == 4){
        $startingX = 0;
    } else if($insightsCount == 3){ // if it is being cut into 3rds
        $startingX = 230; 
    }
    //create the specified amount of image slices
    for($i = 0; $i < $insightsCount; $i++){
        $cropped34File = "images/tmp/croppedInsights/" . $resizedFileCut . "-" . $i . $filetype;
        $cropped34FileCut = "images/tmp/croppedInsights/" . $resizedFileCut . "-" . $i;
        crop("images/resized/" . $resizedFileCut . $filetype, $startingX, $startingY, 225, 300, $cropped34File);
        $startingX += 225;
    }
}

function readCategories($resizedFileCut, $insightsCount, $filetype){
    //unsorted data (index array)
    $categoryData = [];
    $categoryDataCounter = 0;
    //read each slice and pull the first number from each one
    for($i = 0; $i < $insightsCount; $i++){
        $filePath = "images/tmp/croppedInsights/" . $resizedFileCut . "-" . $i . $filetype;
        $categorySliceData = json_decode(shell_exec('export GOOGLE_APPLICATION_CREDENTIALS="key2.json" && php interactionQuery.php ' . $filePath));
        if($categorySliceData != null){
            foreach($categorySliceData as $data){
                $dataCopy = vCleanString($data);
                if(is_numeric($dataCopy)){
                    $categoryData[$categoryDataCounter] = $data;
                    //unlink($filePath);
                    $categoryDataCounter++;
                    break;
                } else {
                    $categoryData[$categoryDataCounter] = "NULL";
                    $categoryDataCounter++;
                    break;
                }
            }
        } else {
            $categoryData[$categoryDataCounter] = "NULL";
            $categoryDataCounter++;    
        }
    }
    //sorted data (associative array)
    //if there's a dot remove it 
    $categoryDataSorted = [];
    //sorts the data depending on the number of categories explicitly stated
    if($insightsCount == 3){
        $categoryDataSorted["likes"] = $categoryData[0];      
        $categoryDataSorted["comments"] = $categoryData[1];
        $categoryDataSorted["shares"] = "NA";
        $categoryDataSorted["saves"] = $categoryData[2]; 
    } else if($insightsCount == 4){
        $categoryDataSorted["likes"] = $categoryData[0];
        $categoryDataSorted["comments"] = $categoryData[1]; 
        $categoryDataSorted["shares"] = $categoryData[2]; 
        $categoryDataSorted["saves"] = $categoryData[3]; 
    }
    for($i = 0; $i < $insightsCount; $i++){
       unlink("images/tmp/croppedInsights/" . $resizedFileCut . "-" . $i . $filetype); 
    }
    return $categoryDataSorted;

}

/* ************************** TESSERACT  ************************** */

function tesseractRead($resizedFileCut, $filetype){
    $strings = explode("\n", (new TesseractOCR("images/resized/" . $resizedFileCut . $filetype))->run());
    
    $json = array();
    
    $tesseractData = array();
    
    $keywords = [
        "profilevisits",
        "notfollowing",
        "follows",
        "reach",
        "impressions",
        "fromhome",
        "fromhashtags",
        "fromprofile",
        "fromother",
        "getdirections",
        "calls",
        "explore",
        "replies",
        "websiteclicks",
        "fromlocation"
        ];
        
    $keywordsFound = array();
        
    foreach($keywords as $keyword){
        $keywordsFound[$keyword] = false;        
    }
    
    $impressionsFound = false;
    
    foreach($strings as $string){
        $String = strtoupper($string);
        if(strlen(trim($String)) == 0){
            continue;
        } 
        // profile visits
        if(substr($String, 0, 14) == "PROFILE VISITS"){
            $tesseractData["profilevisits"] = substr($String, 15);
            $keywordsFound["profilevisits"] = true;
        }
        //visit profile
        if(substr($String, 0, 13) == "VISIT PROFILE" ){
            $tesseractData["profilevisits"] = substr($String, 14);
            $keywordsFound["profilevisits"] = true;
        }
        //not following
        if(substr($String, -21) == "WEREN'T FOLLOWING YOU"){ 
            $tesseractData["notfollowing"] = substr($String, 0, -22);
            $keywordsFound["notfollowing"] = true;
        }
        //follows
        if(substr($String, 0, 7) == "FOLLOWS"){
            $tesseractData["follows"] = substr($String, 8);    
            $keywordsFound["follows"] = true;
        }
        //reach
        if(substr($String, 0, 5) == "REACH"){
            $tesseractData["reach"] = substr($String, 6);    
            $keywordsFound["reach"] = true;
        }
        //impressions
        if(substr($String, 0, 11) == "IMPRESSIONS" && $impressionsFound == false){
            $tesseractData["impressions"] = substr($String, 12);    
            $keywordsFound["impressions"] = true;
            $impressionsFound = true;
        }
        //from home
        if(substr($String, 0, 10) == "FROM HOME "){
            $tesseractData["fromhome"] = substr($String, 10);  
            $keywordsFound["fromhome"] = true;
        }
        //fromhashtags
        if(substr($String, 0, 13) == "FROM HASHTAGS"){
            $tesseractData["fromhashtags"] = substr($String, 14);    
            $keywordsFound["fromhashtags"] = true;
        }
        //from profile
        if(substr($String, 0, 12) == "FROM PROFILE"){
            $tesseractData["fromprofile"] = substr($String, 13);    
            $keywordsFound["fromprofile"] = true;
        }
        //fromhashtags
        if(substr($String, 0, 10) == "FROM OTHER"){
            $tesseractData["fromother"] = substr($String, 11);    
            $keywordsFound["fromother"] = true;
        }
        //fromhashtags
        if(substr($String, 0, 14) == "GET DIRECTIONS"){
            $tesseractData["getdirections"] = substr($String, 15);    
            $keywordsFound["getdirections"] = true;
        }
        //calls
        if(substr($String, 0, 5) == "CALLS"){
            $tesseractData["calls"] = substr($String, 6);    
            $keywordsFound["calls"] = true;
        }
        //fromexplore
        if(substr($String, 0, 12) == "FROM EXPLORE"){
            $tesseractData["fromexplore"] = substr($String, 13);    
            $keywordsFound["fromexplore"] = true;
        }
        //fromexplore
        if(substr($String, 0, 13) == "FROM LOCATION"){
            $tesseractData["fromlocation"] = substr($String, 14);    
            $keywordsFound["fromlocation"] = true;
        }
        //explore
        if(substr($String, 0, 7) == "EXPLORE"){
            $tesseractData["explore"] = substr($String, 8);    
            $keywordsFound["explore"] = true;
        }
        //replies
        if(substr($String, 0, 7) == "REPLIES"){
            $tesseractData["replies"] = substr($String, 8);    
            $keywordsFound["replies"] = true;
        }
        //website clicks
        if(substr($String, 0, 14) == "WEBSITE CLICKS"){
            $tesseractData["websiteclicks"] = substr($String, 15);    
            $keywordsFound["websiteclicks"] = true;
        }
        //website clicks
        if(substr($String, 0, 13) == "VISIT WEBSITE"){
            $tesseractData["websiteclicks"] = substr($String, 14);    
            $keywordsFound["websiteclicks"] = true;
        }
    }
    
    $missingData = array();
    
    foreach($keywordsFound as $keyword => $value){
        if($value == false){
            $missingData[$keyword] = "0";    
        }
    }
    return array_merge($tesseractData, $missingData);
}

//parent function
function readInsights($url, $accountName, $insightsCount){
    
    global $quality;
    
    global $quality60;
    
    require_once __DIR__ . '/tesseract/vendor/autoload.php';
    
    $path_parts = pathinfo($url);
    
    //without filetype at the end
    $orgFilenameCut = $path_parts['filename'];
    
    //filetype
    $filetype = ".jpg";
    
    //Reads the date
    $insightsDataJSON = shell_exec('export GOOGLE_APPLICATION_CREDENTIALS="key2.json" && php interactionQuery.php ' . $url);
    
    $insightsData = json_decode($insightsDataJSON);
    
    $postedOnData = array();
    
    foreach($insightsData as $data){
        
        if($data == "on"){
            
            $dateIndex = array_search($data, $insightsData); 
            
            $date = $insightsData[$dateIndex + 1]; 
            $time = $insightsData[$dateIndex + 2];  
            
            $imageTime = strtotime($date . $time);
    
            $postedOnData["date"] = $date . $time;
            $postedOnData["time"] = $imageTime;
            
            break;
        }
    }
    
    $date = date('m-d-Y-Hi', $imageTime);
    
    //resized file without filetype
    $resizedFileCut = $date . "-" . $accountName . "-resized";
    
    //resize the orginial image to 900 with contrast and store to images/resized/
    //resize function automatically adds the filetype to the end
    //(width , path of resized file, path of file to be resized)
    resize(900, "images/resized/" . $resizedFileCut, $url, $quality);
    resize(600, "accounts/" . $accountName . "/" . $resizedFileCut, $url, $quality60);
    $mergedData = array_merge($postedOnData, insightsRead($resizedFileCut, $filetype, $insightsCount));  
    $mergedData = array_merge($mergedData, tesseractRead($resizedFileCut, $filetype));
    unlink("images/resized/" . $resizedFileCut . $filetype);    
    echo json_encode($mergedData);
    return json_encode($mergedData);
}
$url = "http://c9.noah.kim/insightsReader/images/eureka-2019-09-18.PNG";
$accountName = "eureka";
$insightsCount = 4;

readInsights($url, $accountName, $insightsCount);

