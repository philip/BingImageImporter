# BingImageSearch
Fetch images using the Bing Image Search API. 

# The API Key
To use the Bing Image search, you need a Bing API key. The free version allows 5,000 queries per month. To get this key, go to http://www.bing.com/toolbox/bingsearchapi and request the key. It's a painless process. It's part of the "Windows Azure Marketplace".

This key can be used for other search types too (e.g., web) but we're using it to perform image searches.

Under "Account Information" you'll see a "Primary Account Key", which is your Bing API Key.

# Example
Several examples live within the `examples/` directory, but here's another based on `cat.php`:

```php
<?php
require __DIR__ . "/../src/BingImageSearch.php";
$bing = new Philip\BingImageSearch();

// Settings
$bing->setApiKey("YOUR KEY HERE");
$bing->setQuery("Happy Cat");

// Let's retrieve the first image, as JSON
$image_json = $bing->pickFirstImage();

// Output image as HTML
$html = $bing->outputImageHtml($image_json, 'main');
echo $html;
```
# Requirements
The Bing API Key, and PHP with the JSON extension enabled (it is by default). Optionally, the SQLite3 extension is used to cache results.

# Installation
## Using Composer

```shell
$ composer require philip/bingimagesearch
```

## Or, simply download it and use:

```shell
$ wget https://github.com/philip/BingImageSearch/archive/master.zip
$ unzip master.zip
$ mv BingImageSearch-master BingImageSearch
$ cd BingImageSearch
$ php examples/cat.php
```
Execution will fail until you set the Bing API Key. Future versions will make that easier, but for now you must edit the code. In the above example, that means adding the API Key to `cat.php`.
