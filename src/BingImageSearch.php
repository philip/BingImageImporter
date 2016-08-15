<?php
namespace Philip;

/*
* Return a somewhat random image from Bing images, using their API
*
* @package  BingImageSearch
* @author   Philip Olson <http://github.com/philip>
* @link     https://github.com/philip/BingImageSearch
* @version  0.3.0 (beta)
*
* @todo Adjust code to follow proper PHP-FIG coding standards
* @todo Adjust code to follow "PHP The Right Way"
*
* @example examples/cat.php Example usage that displays a cat
* @example examples/import.php Example usage of caching multiple entries
*
* Example:
* * Initiate the class
*    $obj = new Philip\BingImageSearch();

* * Set the API KEY
* * You get this from http://www.bing.com/toolbox/bingsearchapi
*    $obj->setApiKey("s8f8j8ajf8sjf8js8jsa9fuasf");

* * Search bing images for a happy cat
*    $obj->setQuery("Happy Cat");

** Fetch a random image, return the JSON result
*    $image_json = $bing->pickRandomImage();

* * Output the image binary, you might save it as a .jpg file
* * Unless you know what this means, this is likely not what you want
* * Outputs main image by default, pass in 'thumbnail' as 2nd arg otherwise
*    $obj->outputImageBinary($image_json);

* * Output HTML markup to display image
* * Outputs main image by default, pass in 'thumbnail' as 2nd arg otherwise
*    $obj->outputImageHtml(image_json);
*/
class BingImageSearch
{
    /**
     * Your Bing API Key. Set with the setApiKey() method.
     * The Bing Image API interface provides this key.
     *
     * @var string Your Bing API Key, required
     */
    private $bing_api_key = '';

    /**
     * Cache SQLite DB file path.
     *
     * @var array SQL for cache query
     */
    private $cache_sqlite_db = '';

    /**
     * Enable or disable caching,.
     *
     * @var bool true to enable, or false to disable (default)
     */
    private $cache_enabled = false;

    /**
     * Only output images that are stored in cache.
     *
     * @var bool true to enable, or false to disable (default)
     */
    private $cache_required = false;

    /**
     * Search query to determine the type of image returned. Set using setQuery().
     * By default, a "Happy Kitten" image is returned.
     *
     * @var string Image search query, defaults ot "Happy Kitten"
     */
    private $query = 'Happy Kitten';

    /**
     * Log.
     *
     * @var array Logged information
     */
    public $log = array();


    /**
     * Base URL for the bing request.
     *
     * @var string Bing search URL
     */
    const BING_BASEURL = 'https://api.datamarket.azure.com/Bing/Search/v1/';

    /**
     * Version of this class, as x.y.z.
     *
     * @var string Class version
     */
    const VERSION = '0.3.0';

    /**
     * Constructor checks for required PHP extensions: Curl and JSON
     * Emits fatal E_USER_ERROR if extension is not present.
     */
    public function __construct()
    {
        if (!function_exists('json_decode')) {
            trigger_error('The JSON extension is required', E_USER_ERROR);
        }
        if ($this->cache_enabled && !class_exists('\SQLite3')) {
            trigger_error('The SQLite3 extension is required when cache is enabled', E_USER_ERROR);
        }
    }

    /**
     * Sets the search query, namely $this->query.
     *
     * @param string $query Search string for bing image search
     */
    public function setQuery($query)
    {
        $this->query = trim($query);
    }

    /**
     * Get the search query, namely $this->query.
     *
     * @param void
     *
     * @return string Search string for bing image search
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * Enable cache. If sqlite db does not exist, create it.
     * Sets internal cache_enabled var to true.
     *
     * @param $filepath Path to the sqlite db file
     *
     * @return boolean True on success, false on failure
     */
    public function enableCache($filepath) {
        $this->cache_sqlite_db = $filepath;
        if (!file_exists($filepath)) {
            $this->log[] = "Cache table did not exist, will attempt to create it now";
            if (!$this->createCacheTable($filepath)) {
                $this->log[] = "I could not create the cache table at $filepath";
                return false;
            }
        }
        $this->cache_enabled = true;
        return true;
    }

    /**
     * Disable cache. Sets internal cache_enabled var to false.
     *
     * @param void
     *
     * @return void
     */
    public function disableCache() {
        $this->cache_enabled = false;
    }

    /**
     * Whether to require cache.
     *
     * @param boolean $toggle True to require cache, otherwise false (default)
     *
     * @return void
     */
    public function requireCache($toggle = false) {
        $this->cache_required = (bool) $toggle;
    }
    
    /**
     * Fetch result from Bing site
     * Requires API key to be set
     *
     * @param void
     *
     * @return string Result (JSON) from the search, or false on failure
     */
    private function fetchRemote() {
        if (empty($this->bing_api_key)) {
            $this->log[] = "Required Bing API key not set, cannot fetchRemote";
            return false;
        }
        $auth = base64_encode($this->bing_api_key.":".$this->bing_api_key);
        $context = array(
            'http' => array(
                'request_fulluri' => true,
                'ignore_errors' => true,
                'header' => "Authorization: Basic $auth",
            ),
        );
        $context = stream_context_create($context);
        $query = '?$format=json&Query=%27'. urlencode($this->query).'%27';

        $contents = file_get_contents(self::BING_BASEURL.'Image'.$query, 0, $context);
        if (empty($contents)) {
            return false;
        }
        return $contents;
    }

    /**
     * Fetch local cached Bing result
     *
     * @param void
     *
     * @return string Local result from the search on success, false it not available
     */
    private function fetchLocal() {
        if (empty($this->cache_sqlite_db)) {
            $this->log[] = "Cache db does not exist, cannot fetchLocal";
            return false;
        }
        $db = new \SQLite3($this->cache_sqlite_db);
        $sql = "SELECT result FROM bing_results WHERE query = '".$db->escapeString($this->query) . "'";
        $res = $db->querySingle($sql);
        if (!$res) {
            $this->log[] = "A fetchLocal() query ($sql) did not find a result";
            return false;
        }
        return $res;
    }

    /**
     * Fetch bing search result, either local or remote (depending on other settings)
     *
     * @param void
     *
     * @return mixed Local or remote bing result from the search on success, false if not available
     */
    public function fetch() {
        if (empty($this->query)) {
            $this->log[] = "Query was not defined. Pass one in";
            return false;
        }

        if ($this->cache_enabled) {
            $this->log[] = "Attempted to get cached image with {$this->query}";
            $tmp = $this->fetchLocal($this->query);
            if ($tmp) {
                $this->log[] = "Found cache for query {$this->query}";
                return $tmp;
            } else {
                $this->log[] = "Did not have cache for {$this->query}, performing a remote search now";
            }
        }

        if ($this->cache_required) {
            $this->log[] = 'Cache is required, yet query not found in cache. Exiting.';
            return false;
        }

        $c = $this->fetchRemote();
        if ($c) {
            if ($this->cache_enabled) {
                $this->log[] = "Attempted to insert cached result for query {$this->query}";
                $this->cacheResult($this->query, $c);
            }
            return $c;
        }
        return false;
    }

    /**
     * Output image binary
     * 
     * @param $method Method for choosing image, choices include:
     *   'random' to choose a random image from the result
     *   'first' to choose the first image from the result
     *
     * @param $size Which size to return, defaults to main
     *   'main' returns the main image
     *   'thumbnail' returns the thumbnail version of the image
     * 
     * @return string Image, in binary form
     */
    public function outputImageBinary($result, $type = 'main') {
        if ($type !== 'main' && $type !== 'thumbnail') {
            $this->log[] = "Must choose either main or thumbnail, not $type";
            return false;
        }
        if (headers_sent($file, $line)) {
            trigger_error("Header information was already sent, so cannot output image. See $file $line.", E_USER_ERROR);
            return false;
        }
        $imageinfo = json_decode($result, true);
        if ($type === 'thumbnail') {
            $ct  = $imageinfo['Thumbnail']['ContentType'];
            $url = $imageinfo['Thumbnail']['MediaUrl'];
        } else {
            $ct  = $imageinfo['ContentType'];
            $url = $imageinfo['MediaUrl'];
        }
        header('Content-Type: '. $ct);
        readfile($url);
    }

    /**
     * Output HTML markup to view remote image
     * 
     * @param $method Method for choosing image, choices include:
     *   'random' to choose a random image from the result
     *   'first' to choose the first image from the result
     *
     * @param $size Which size to return, defaults to main
     *   'main' returns the main image
     *   'thumbnail' returns the thumbnail version of the image
     * 
     * @return string Image, in binary form
     */
    public function outputImageHtml($result, $type = 'main') {
        if ($type !== 'main' && $type !== 'thumbnail') {
            $this->log[] = "Must choose either main or thumbnail, not $type";
            return false;
        }
        $imageinfo = json_decode($result, true);
        if ($type === 'thumbnail') {
            $width = $imageinfo['Thumbnail']['Width'];
            $height = $imageinfo['Thumbnail']['Height'];
            $url = $imageinfo['Thumbnail']['MediaUrl'];
        } else {
            $width = $imageinfo['Width'];
            $height = $imageinfo['Height'];
            $url = $imageinfo['MediaUrl'];
        }
        return '<img src="'.$url.'" width="'.$width.'" height="'.$height.'"/>'.PHP_EOL;
    }

    /**
     * Pick a rand image from a result.
     * Also fetches a new result if one is not passed in
     * 
     * @param $result string Image result
     *
     * @return array Image information on success, false on failure
     */
    public function pickRandomImage($result = "") {
        if (empty($result)) {
            $result = $this->fetch();
        }
        $images = json_decode($result, true);
        $images = $images['d']['results'];
        if (!empty($images)) {
            $key  = array_rand($images);
            return json_encode($images[$key]);
        }
        return false;
    }

    /**
     * Pick first image from a result
     * Also fetches a new result if one is not passed in
     * 
     * @param $result string Image result
     *
     * @return array Image information on success, false on failure
     */
    public function pickFirstImage($result = "") {
        if (empty($result)) {
            $result = $this->fetch();
        }
        $images = json_decode($result, true);
        if (!empty($images['d']['results'][0])) {
            return json_encode($images['d']['results'][0]);
        }
        return false;
    }

    /**
     * Sets the Bing API Key.
     *
     * @param string $key The bing API key
     */
    public function setApiKey($key)
    {
        $this->bing_api_key = trim($key);
    }

    /**
     * Create Cache table.
     *
     * @todo check if file exists?
     * @todo add error handling
     *
     * @return true on success, false on failure
     */
    public function createCacheTable($filepath)
    {
        if (empty($filepath)) {
            return false;
        }
        $db = new \SQLite3($filepath);
        $sql = '
            CREATE TABLE bing_results 
                (id integer primary key autoincrement, query TEXT, result TEXT, last_updated DATE)';
        if (!$db->query($sql)) {
            return false;
        }
        return true;
    }

     /**
     * Save cache results to an SQLite database.
     *
     * @todo check if file exists?
     * @todo add error handling
     * @todo check if cache value exists, overwrite?
     * @todo add update (REPLACE INTO) support, update last_updated accordingly
     *
     * @return true on success, false on failure
     */
    public function cacheResult($query, $result)
    {
        if (empty($this->cache_sqlite_db)) {
            return false;
        }
        $db = new \SQLite3($this->cache_sqlite_db);
        $sql = "INSERT INTO bing_results (id, query, result, last_updated) VALUES (NULL, :query, :result, datetime('NOW'))";

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':query', $query, SQLITE3_TEXT);
        $stmt->bindValue(':result', $result, SQLITE3_TEXT);
        if ($stmt->execute()) {
            return true;
        }
        return false;
    }
}
