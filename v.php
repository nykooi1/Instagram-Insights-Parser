<?php
//save original with 600px wide, 60% compression
function resize($newWidth, $targetFile, $originalFile, $quality) {
    
    //global $quality;

    $info = getimagesize($originalFile);
    $mime = $info['mime'];

    switch ($mime) {
            case 'image/jpeg':
                    $image_create_func = 'imagecreatefromjpeg';
                    $image_save_func = 'imagejpeg';
                    $new_image_ext = 'jpg';
                    break;

            case 'image/png':
                    $image_create_func = 'imagecreatefrompng';
                    //$image_save_func = 'imagepng';
                    $image_save_func = 'imagejpeg';
                    $new_image_ext = 'jpg';
                    break;

            case 'image/gif':
                    $image_create_func = 'imagecreatefromgif';
                    //$image_save_func = 'imagegif';
                    $image_save_func = 'imagejpeg';
                    $new_image_ext = 'jpg';
                    break;

            default: 
                    throw new Exception('Unknown image type.');
    }

    $img = $image_create_func($originalFile);

    imagefilter($img, IMG_FILTER_BRIGHTNESS, -100);
    imagefilter($img, IMG_FILTER_CONTRAST, -100);    
    
    list($width, $height) = getimagesize($originalFile);

    $newHeight = ($height / $width) * $newWidth;
    $tmp = imagecreatetruecolor($newWidth, $newHeight);
    imagecopyresampled($tmp, $img, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    if(file_exists($targetFile)) {
            unlink($targetFile);
    }
    imagejpeg($tmp, "$targetFile.$new_image_ext"); 
}

//cropX and Y are the starting crop position
//cropW and H are how far you want to crop based on the starting point
function crop($file, $cropX, $cropY, $cropW, $cropH, $newDir){
    
    //global $quality;
    $quality = 100;
    
    $src = imagecreatefromjpeg($file);
    $dest = imagecreatetruecolor($cropW, $cropH);

    list($src_w, $src_h) = getimagesize($file);
    
    //crops the destination image
    imagecopy($dest, $src, 0, 0, $cropX, $cropY, $src_w, $src_h);

    //creates an image, then saves it in the specified location
    //compress to 80 - make it a variable
    imagejpeg($dest, $newDir, $quality); 

    //destroys the copy of the original image
    imagedestroy($src);
    
}
function vCleanString($string){
    $cleanedString = str_replace(".", "", $string);
    $cleanedString = str_replace(",", "", $string);
    $cleanedString = str_replace("K", "", $string);
    return $cleanedString;
}

function vLabel($String) {
	$String = substr($String, 0, 89);
	return preg_replace("/[^a-z0-9-@_\.]/", "", strtolower($String));
}

// Returns a $Size or 13 character (censored) GUID prefixed with $Prefix
function vGUID($Size = null, $Prefix = null) {
    // No vowels prevents spelling censored/bad words
    // No "L" prevents ambiguity with the number "1"
    $Chars = "bcdfghjkmnpqrstvwxyz0123456789"; // 30 -> 810,000 x 2,000 dirs
    $Len = (strlen($Chars) - 1);
    $Size = ($Size < 1 ? 13 : ($Size > 987 ? 987 : $Size));
    $GUID = vLabel($Prefix);
    for ($i = 0; $i < $Size; $i++) { $GUID .= $Chars[mt_rand(0, $Len)]; }
    return $GUID;
}