<?php
require "../lib/BingImageImporter.php";

$bing = new BingImageImporter();

// Your Key
$bing->setApiKey("YOUR KEY HERE");

// Where the sqlite cache database will live
$bing->cache_sqlite_db = "/tmp/cat_cache.sqlite3";

// Enable cache
$bing->cache_enabled = true;

// Before using cache, set up the table (do this once)
if (!file_exists($bing->cache_sqlite_db)) {
    if ($bing->createCacheTable()) {
        echo "INFO: Created cache table at {$bing->cache_sqlite_db}". PHP_EOL;
    } else {
        echo "ERROR: Failed to create cache table at {$bing->cache_sqlite_db}. Existing...". PHP_EOL;
        exit;
    }
}

// A list of queries that we'll search for, and add to cache
// This might come from a file, or anywhere, really
$states = <<<CAT_STATES
Happy Cat
Sad Cat
Playful Cat
Bad Cat
Good Cat
CAT_STATES;

// Turn our cat state list into an array.
$states = explode("\n", $states);

// With cache enabled, executing searches will automatically cache
// the query. Show the log for each query, to see what happened.
// Execute it a second time to see if the cached versions were loaded
foreach ($states as $state) {
    if (!empty($state)) {
        $state = trim($state);
        
        // Set a new query
        $bing->setQuery($state);
        
        // Get image info, which also stores cache. 
        // @todo Well, getting image info to store cache seems crazy but...
        $image = $bing->getImageInfo();
        echo "INFO: Searched for ". $bing->getQuery() . PHP_EOL;
    }
}

// The log. Will show if cache was stored, or if images were taken from cache
if (!empty($bing->log)) {
    print_r($bing->log);
}
