<?php

namespace Google\Cloud\Samples\Vision;

require __DIR__ . '/google/vendor/autoload.php';

use Google\Cloud\Vision\V1\ImageAnnotatorClient;

$filename = $argv[1];

$path = $filename;

function detect_text2($path){
    $A = [];
    
    $imageAnnotator = new ImageAnnotatorClient();

    # annotate the image
    $image = file_get_contents($path);
    $response = $imageAnnotator->textDetection($image);
    $texts = $response->getTextAnnotations();
    
   // printf('%d texts found:' . PHP_EOL, count($texts));
    foreach ($texts as $text) {
        $A[] = $text->getDescription();
        //print($text->getDescription() . PHP_EOL);
    }
    
    array_shift($A);
    
    echo json_encode($A);
    
    $imageAnnotator->close();
}

detect_text2($path);