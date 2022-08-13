<?php
include 'v.php';

$quality = 80;

//$Filename = basename($_FILES["uploadfile"]["name"]);
$origFilename = "eureka-2019-09-15.PNG";
$origFilenameCut = substr($origFilename, 0, -4);
$fileType = ".jpg";

$Path = "images/";

$PathFile = $Path . $origFilenameCut . ".jpg";

/**
 * resize image to standard 900px width
 * adds .jpg
 */
 
//resize(900, "images/resized/" . $filename, 'images/' . $origFilename);
resize(900, "images/" . $origFilenameCut, 'images/' . $origFilename);

$insightsDataJSON = shell_exec('export GOOGLE_APPLICATION_CREDENTIALS="key2.json" && php interactionQuery.php ' . $PathFile);

$insightsData = json_decode($insightsDataJSON);

print_r($insightsDataJSON);

//print_r($insightsData);

$accountName = "testing";

foreach($insightsData as $data){
    
    if($data == "on"){
        
        $dateIndex = array_search($data, $insightsData); 
        
        $date = $insightsData[$dateIndex + 1]; 
        $time = $insightsData[$dateIndex + 2];  
        
        $imageTime = strtotime($date . $time);
    
    }
}

$year = date('y', $imageTime);
$month = date('m', $imageTime);
$date = date('d', $imageTime);
$hour = date('H', $imageTime);
$minute = date('i', $imageTime);

$filename = $accountName . "-" . $year . "-" . $month . "-" . $date . "-" . $hour . $minute;

rename($PathFile, "images/resized/" . $filename . $fileType);

/**
 * sets how many slices to crop (3 or 4)
 */
$type = "Insight"; //stores the type of post (will be user inserted)
$Count = 0; //stores number of numbers found between "Posted" and "Interactions / Actions"
$Start = false; //tells whether to start counting or not
//loops through all the insight data
foreach($insightsData as $insight){
    //if true, turn on counter
    if($insight == "Posted"){
        $Start = true;
    }
    //stop counting
    if ($insight == "Interactions" || $insight == "Actions") {
        break; 
    }
    //start counting
    if($Start && is_numeric(vCleanString($insight))){
        $Count += 1;
    }
}
echo $Count;

function vCleanString($string){
    $cleanedString = str_replace(".", "", $string);
    $cleanedString = str_replace(",", "", $string);
    $cleanedString = str_replace("K", "", $string);
    return $cleanedString;
}

/**
 * Crops the image into 3rds / 4ths to get individual insight data points (likes, shares, saves, comments)
 */
function insightsCrop($filename, $insightsCount, $fileType){
    //gets the resized image size
    list($width, $height) = getimagesize("images/resized/" . $filename . $fileType);
    //if it is being cut into 4ths
    if($insightsCount == 4){
        $startingX = 0;
    } else if($insightsCount == 3){ // if it is being cut into 3rds
        $startingX = 230; 
    }
    //create the specified amount of image slices
    for($i = 0; $i < $insightsCount; $i++){
        $cropped34File = "images/tmp/croppedInsights/" . $filename . "-" . $i . $fileType;
        crop("images/resized/". $filename . $fileType, $startingX, 250, 280, ($height - 250), $cropped34File);
        $startingX += 280;
    }
}

//reads each slice and pulls the "likes", "comments", "shares", and "saves"
function readCategories($filename, $count, $fileType){
    //unsorted data (index array)
    $categoryData = [];
    $categoryDataCounter = 0;
    //read each slice and pull the first number from each one
    for($i = 0; $i < $count; $i++){
        $filePath = "images/tmp/croppedInsights/" . $filename . "-" . $i . $fileType;
        $categorySliceData = json_decode(shell_exec('export GOOGLE_APPLICATION_CREDENTIALS="key2.json" && php interactionQuery.php ' . $filePath));
        //print_r($categorySliceData);
        foreach($categorySliceData as $data){
            $dataCopy = vCleanString($data);
            if(is_numeric($dataCopy)){
                $categoryData[$categoryDataCounter] = $data;
                //unlink($filePath);
                $categoryDataCounter++;
                break;
            }
        }
    }
    //sorted data (associative array)
    $categoryDataSorted = [];
    //sorts the data depending on the number of categories explicitly stated
    if($count == 3){
        $categoryDataSorted["likes"] = $categoryData[0];      
        $categoryDataSorted["comments"] = $categoryData[1];
        $categoryDataSorted["shares"] = "NA";
        $categoryDataSorted["saves"] = $categoryData[2]; 
    } else if($count == 4){
        $categoryDataSorted["likes"] = $categoryData[0];
        $categoryDataSorted["comments"] = $categoryData[1]; 
        $categoryDataSorted["shares"] = $categoryData[2]; 
        $categoryDataSorted["saves"] = $categoryData[3]; 
    }
    return $categoryDataSorted;
}

insightsCrop($filename, $Count, $fileType);

$interactionData = [];
//converts the insightsData array to a string
$insightsDataString = implode("|", $insightsData);

//finds the percent that "weren't following you"
if (strpos($insightsDataString, "weren't|following")) { 
    $Blob = strstr($insightsDataString, "weren't|following", true);
    $Percent = substr($Blob, strrpos($Blob, "%") -2, 3);
}

$interactionData["notfollowing"] = $Percent;

function cropColumns($filename, $fileType){
    list($width, $height) = getimagesize("images/resized/" . $filename . $fileType);
    crop("images/resized/". $filename . $fileType, 0, 250, 350, ($height - 250), "images/tmp/croppedInsights/" . $filename . "-column-0" . $fileType);
    crop("images/resized/". $filename . $fileType, 820, 600, 204, ($height - 600), "images/tmp/croppedInsights/" . $filename . "-column-1" . $fileType);
    //list($width2) = getimagesize("images/tmp/croppedInsights/" . $filename . "-column-1" . $fileType);
    //resize($width2 * 2, "images/tmp/croppedInsights/" . $filename . "-column-1", "images/tmp/croppedInsights/" . $filename . "-column-1" . $fileType);
}

function readColumns($filename, $type, $fileType){
    $columnData = [];
    for($i = 0; $i <= 1; $i++){
        $columnData[$i] = json_decode(shell_exec('export GOOGLE_APPLICATION_CREDENTIALS="key2.json" && php interactionQuery.php ' . "images/tmp/croppedInsights/" . $filename . "-column-" . $i . $fileType));
    }  
    $keywords = ["ProfileVisits", "Calls", "Explore", "Replies", "FromProfile", "VisitWebsite", "WebsiteClicks", "Impressions", "Follows", "Directions", "Reach", "FromHome", "Other", "FromHashtags", "Location", "Explore"];
    $formattedKeywords = [];
    $formattedCounter = 0;
    $formattedKeywords["ProfileVisits"] = "profilevisits";
    $formattedKeywords["Calls"] = "calls";
    $formattedKeywords["Explore"] = "explore";
    $formattedKeywords["Replies"] = "replies";
    $formattedKeywords["FromProfile"] = "fromprofile";
    $formattedKeywords["VisitWebsite"] = "websitevisits";
    $formattedKeywords["WebsiteClicks"] = "websiteclicks";  
    $formattedKeywords["Impressions"] = "impressions"; 
    $formattedKeywords["Follows"] = "follows";
    $formattedKeywords["Directions"] = "getdirections";
    $formattedKeywords["Reach"] = "reach";
    $formattedKeywords["FromHome"] = "fromhome";
    $formattedKeywords["FromHashtags"] = "fromhashtags";
    $formattedKeywords["Location"] = "fromlocation";
    $formattedKeywords["Other"] = "fromother";
    $formattedKeywords["Explore"] = "fromexplore";
        
    $cleanedCounter = 0;
    $columnDataCleaned = [];
    for($i = 0; $i < count($columnData[0]); $i++){
        //compares the current word to all the keywords
        foreach($keywords as $keyword){
            //if the current word mathes a keyword, exit this searching loop and set found to true
            if((($i > 0) && (($columnData[0][$i - 1] . $columnData[0][$i]) == "ProfileVisits")) || (($i > 0) && (($columnData[0][$i - 1] . $columnData[0][$i]) == "VisitProfile"))){
                $columnDataCleaned[$cleanedCounter] = "ProfileVisits";
                $cleanedCounter++;
                break;
            }
            if(($i > 0) && (($columnData[0][$i - 1] . $columnData[0][$i]) == "FromProfile")){
                $columnDataCleaned[$cleanedCounter] = "FromProfile";
                $cleanedCounter++;
                break;
            }
            if(($i > 0) && (($columnData[0][$i - 1] . $columnData[0][$i]) == "FromHome")){
                $columnDataCleaned[$cleanedCounter] = "FromHome";
                $cleanedCounter++;
                break;
            }
            if(($i > 0) && (($columnData[0][$i - 1] . $columnData[0][$i]) == "FromHashtags")){
                $columnDataCleaned[$cleanedCounter] = "FromHashtags";
                $cleanedCounter++;
                break;
            }
            if(($i > 0) && (($columnData[0][$i - 1] . $columnData[0][$i]) == "VisitWebsite")){
                $columnDataCleaned[$cleanedCounter] = "VisitWebsite";
                $cleanedCounter++;
                break;
            }
            if(($i > 0) && (($columnData[0][$i - 1] . $columnData[0][$i]) == "WebsiteClicks")){
                $columnDataCleaned[$cleanedCounter] = "WebsiteClicks";
                $cleanedCounter++;
                break;
            }
            if($columnData[0][$i] == $keyword){
                $columnDataCleaned[$cleanedCounter] = $columnData[0][$i];
                $cleanedCounter++;
                break;
            }
        }  
    }
    $numberCounter = 0;
    $cleanedColumn2 = [];
    
    foreach($columnData[1] as $data){
        if(is_numeric(str_replace(",", "", $data))){
            $cleanedColumn2[$numberCounter] = $data;
            $numberCounter++;        
        }
    }
    echo "CLEANED COLUMN 2:\n";
    print_r($cleanedColumn2);
    $columnDataFound = [];
    foreach($keywords as $keyword){
        $columnDataFound[$keyword] = false;
    }
    $columnDataSorted = [];
    echo "COLUMN DATA SORTED:\n";
    print_r($columnDataCleaned);
    
    if($numberCounter < sizeof($columnDataCleaned)){
        echo "\n\nCANNOT READ IMAGE (cannot read data value)\n\n**EXIT PROGRAM**\n\n";
        exit;
    }
    
    if($numberCounter > sizeof($columnDataCleaned)){
        $cleanedColumn2 = array_slice($cleanedColumn2, 1);    
    }
    
    $sortingCounter = 0;
    foreach($columnDataCleaned as $data){
        foreach($keywords as $keyword){
            if($data == $keyword){
                $columnDataSorted[$formattedKeywords[$data]] = $cleanedColumn2[$sortingCounter];
                $columnDataFound[$data] = true;
                break;
            }  
        }
        $sortingCounter++;
    }
    
    //print_r($columnDataFound);
    foreach($keywords as $keyword){
        if($columnDataFound[$keyword] == false){
            $columnDataSorted[$formattedKeywords[$keyword]] = 0;
        }
    }
    
    //unlink("images/tmp/croppedInsights/" . $filename . "-column-0"  . $fileType);
    unlink("images/tmp/croppedInsights/" . $filename . "-column-1"  . $fileType);
    return $columnDataSorted;
}

cropColumns($filename, $fileType);

$totalData = array_merge(readCategories($filename, $Count, $fileType), $interactionData);
$totalData = array_merge($totalData, readColumns($filename, "promotion", $fileType));
print_r($totalData);

?>