<?php

require '../lib/BingImageImporter.php';

$bing = new BingImageImporter();

// Your Key
$bing->setApiKey('YOUR KEY HERE');

// We'll search for a 'Happy Cat' image
$bing->setQuery('Happy Cat');

// Get image info, by default a random image
$image = $bing->getImageInfo();

// Example output
echo '<img src="'.$image['url'].'" width="'.$image['width'].'" height="'.$image['height'].'"/>'.PHP_EOL;

// See what information is available
print_r($image);
