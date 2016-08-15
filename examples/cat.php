<?php
/**
 * When using composer, do this instead
 * require __DIR__ . "/../vendor/autoload.php";
*/
require __DIR__.'/../src/BingImageSearch.php';

$bing = new Philip\BingImageSearch();

// Your Key
$bing->setApiKey('YOUR API KEY HERE');

// Enable cache, and choose location
$bing->enableCache('/tmp/bing_result_cache.sqlite3');

// We'll search for a 'Happy Cat' image
$bing->setQuery('Happy Cat');

// Choose a random image
$image_json = $bing->pickRandomImage();

// Output image as HTML
$html = $bing->outputImageHtml($image_json, 'main');
echo $html;

// Let's show the log, if present
if (!empty($bing->log)) {
    print_r($bing->log);
}
