# BingImageImporter
Fetch images using the Bing Search API. By default, a random image is retrieved, based on your search query. 

# The API Key
To use the Bing Image search, you need a Bing API key. The free version allows 5,000 queries per month. To get this key, go to http://www.bing.com/toolbox/bingsearchapi and request the key. It's a painless process. It's part of the "Windows Azure Marketplace".

This key can be used for other search types too (e.g., web) but we're using it to perform image searches.

Under "Account Information" you'll see a "Primary Account Key", which is your Bing API Key.

# Example
Several examples live within the examples/ directory, but here another based on cat.php:

```php
<?php
require "../lib/BingImageImporter.php";
$bing = new BingImageImporter();

$bing->setApiKey("YOUR KEY HERE");
$bing->setQuery("Happy Cat");

$image = $bing->getImageInfo();

// An image of a happy cat
echo '<img src="'.  $image['url'] . '" width="'. $image['width'] . '" height="'. $image['height'] . '"/>';
```

# Requirements
The Bing API Key, and PHP with the JSON and Curl extensions enabled. Optionally, the SQLite3 extension is used to cache results.


