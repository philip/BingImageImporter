<?php
/**
 * When using composer, do this instead
 * require __DIR__ . "/../vendor/autoload.php";
*/
require __DIR__.'/../src/BingImageSearch.php';

$bing = new Philip\BingImageSearch();

// Your Key
$bing->setApiKey('YOUR API KEY HERE');

// Let's enable cache
$bing->enableCache('/tmp/bing_result_cache.sqlite3');

// A list of queries that we'll search for, and add to cache
// This might come from a file, or anywhere, really
$catmoods = <<<CAT_MOODS
Happy Cat
Sad Cat
Playful Cat
Bad Cat
Good Cat
CAT_MOODS;

// Turn our cat state list into an array.
$queries = explode("\n", $catmoods);

// With cache enabled, executing searches will automatically cache
// the query. Show the log for each query, to see what happened.
// Execute it a second time to see if the cached versions were loaded
foreach ($queries as $query) {
    if (!empty($query)) {
        $query = trim($query);

        // Set a new query
        $bing->setQuery($query);

        // Fetch image, which also stores cache (as cache is enabled)
        $bing->fetch();
        echo 'INFO: Searched for '.$bing->getQuery().PHP_EOL;
    }
}

// Let's show the log, if present
if (!empty($bing->log)) {
    print_r($bing->log);
}
