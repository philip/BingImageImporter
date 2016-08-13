<?php
/*
* Return a somewhat random image from Bing images, using their API
*
* @package  BingImageSearch
* @author   Philip Olson <http://github.com/philip>
* @link     https://github.com/philip/BingImageSearch
* @version  0.2.0 (beta)
*
* @todo Adjust code to follow proper PHP-FIG coding standards
* @todo Adjust code to follow "PHP The Right Way"
*
* @example examples/cat.php Example usage that displays a cat
*
* Examples:
* * Initiate the class
*    $obj = new BingImageSearch();

* * Set the API KEY
* * You get this from http://www.bing.com/toolbox/bingsearchapi
*    $obj->setApiKey("s8f8j8ajf8sjf8js8jsa9fuasf");

* * Search bing images for a happy cat
*    $obj->setQuery("Happy Cat");

* * Output the image binary, you might save it as a .jpg file
* * Unless you know what this means, this is likely not what you want
*    $obj->outputImage();

* * Output the image information, you might download the image, or link to it
*    $info = $obj->getImageInfo();
*    echo $info['url'];
*    print_r($info);
*/
class BingImageSearch
{
    /**
     * Maximum image width of returned images. If exceeded, the 
     * thumbnail is returned instead.
     *
     * @var int Maximum width of returned image, defaults to 300.
     */
    public $maxwidth = 300;

    /**
     * Maximum number of image results to choose from. Useful when returning
     * a random image. For example, set to 10 to pick one of the first 10 results.
     * Useful if later results aren't as relavent. Default = 10.
     *
     * @var int Maximum number of images to check, defaults to 10.
     */
    public $maxcheck = 10;

    /**
     * Method of search to perform and return.
     * Set to "random" to return a random result, otherwise the first result is returned.
     *
     * @var string Image search method, defaults to 'random'
     */
    public $method = 'random'; // Enter 'random' for random result, else the first result is returned

    /**
     * Search query to determine the type of image returned. Set using setQuery().
     * By default, a "Happy Kitten" image is returned.
     *
     * @var string Image search query, defaults ot "Happy Kitten"
     */
    private $query = 'Happy Kitten';

    /**
     * Your Bing API Key. Set with the setApiKey() method.
     * The Bing Image API interface provides this key.
     *
     * @var string Your Bing API Key, required
     */
    private $bing_api_key = '';

    /**
     * Format of the request made to bing.
     * At present, the only option is "json".
     *
     * @var string Bing search format, defaults to 'json'
     */
    private $bing_search_format = 'json';

    /**
     * Cache SQL.
     *
     * @var array SQL for cache query
     */
    public $cache_sql = array();

    /**
     * Cache SQLite DB file path.
     *
     * @var array SQL for cache query
     */
    public $cache_sqlite_db = '/tmp/player_cache.sqlite3';

    /**
     * Enable or disable caching,.
     *
     * @var bool true to enable, or false to disable (default)
     */
    public $cache_enabled = false;

    /**
     * Only output images that are stored in cache.
     *
     * @var bool true to enable, or false to disable (default)
     */
    public $cache_required = false;

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
    const BING_BASEURL = 'https://api.datamarket.azure.com/Bing/Search/';

    /**
     * Bing search type. Only "Image" makes sense at present, but if desired, you
     * could adjust the code to use something else, such as "Web".
     *
     * @var string Bing search type
     */
    const BING_SEARCH_TYPE = 'Image';

    /**
     * Version of this class, as x.y.z.
     *
     * @var string Class version
     */
    const VERSION = '0.2.0';

    /**
     * Constructor checks for required PHP extensions: Curl and JSON
     * Emits fatal E_USER_ERROR if extension is not present.
     */
    public function __construct()
    {
        if (!function_exists('curl_exec')) {
            trigger_error('The Curl extension is required', E_USER_ERROR);
        }
        if (!function_exists('json_decode')) {
            trigger_error('The JSON extension is required', E_USER_ERROR);
        }
        if ($this->cache_enabled && !class_exists('SQLite3')) {
            trigger_error('The SQLite3 extension is required when cache is enabled');
        }
    }

    /**
     * Sets the search query, namely $this->query.
     *
     * @param string $query Search string for bing image search
     */
    public function setQuery($query)
    {
        $this->query = $query;
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
     * Sets the search URL, namely $this->url.
     * 
     * @return bool true on success, false on failure
     */
    private function setSearchUrl()
    {
        if (empty($this->query)) {
            trigger_error('A search query was not present', E_USER_WARNING);

            return false;
        }
        /* @todo Confirm this is correct/ideal */
        $url = self::BING_BASEURL.self::BING_SEARCH_TYPE;
        $url .= '?$format='.$this->bing_search_format.'&Query=%27'.urlencode($this->query).'%27';
        $this->url = $url;

        return true;
    }

    /**
     * Execute a bing image search query.
     * 
     * @return array Image information for the returned result, or false on failure
     */
    private function executeQuery()
    {

        // @todo This error handling must be improved... hints that mean a key is likely valid? length?
        if (empty($this->bing_api_key)) {
            trigger_error('You must set the BING API KEY, similar to "$obj->setApiKey("s8f8j8ajf8sjf8js8jsa9fuasf");"', E_USER_ERROR);
        }

        if ($this->cache_enabled) {
            $this->log[] = "Attempted to get cached image with {$this->query}";
            $tmp = $this->getCachedImage($this->query);
            if ($tmp) {
                $this->log[] = "Found cached image for query {$this->query}";
                $this->imageinfo = $tmp;

                return $tmp;
            } else {
                $this->log[] = "Did not have cache for {$this->query}, performed a bing search instead";
            }
        }

        if ($this->cache_required) {
            $this->log[] = 'Cache is required';

            return false;
        }

        $this->setSearchUrl();

        /* @todo Confirm this is correct/ideal */
        /* @todo Add error handling */
        $process = curl_init($this->url);
        curl_setopt($process, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($process, CURLOPT_USERPWD,  $this->bing_api_key.':'.$this->bing_api_key);
        curl_setopt($process, CURLOPT_TIMEOUT, 15);
        curl_setopt($process, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($process);

        // @todo This error handling must be improved
        if (false !== strpos($response, 'authorization type you provided is not supported')) {
            trigger_error('Bing is not set up correctly, check your API key', E_USER_ERROR);
        }

        /* @todo We have a format setting, but assume json... */
        $response = json_decode($response);

        /* @todo Not sure if this is possible, but it can't hurt */
        if (empty($response)) {
            return false;
        }

        // Number of results from the image search
        $rcount = count($response->d->results);

        /* @todo Do something useful here, such as showing a default image */
        if ($rcount === 0) {
            return false;
        }

        /* @todo Allow return of multiple image results, as opposed to just one */
        if ($this->method === 'random') {
            if ($rcount < $this->maxcheck) {
                $max = $rcount - 1;
            } else {
                $max = $this->maxcheck;
            }
            $pick = rand(0, $max);
        } else {
            $pick = 0;
        }
        if ($this->cache_enabled) {
            $this->log[] = "Inserted cached image for query {$this->query}";
            $this->cacheImages($response->d->results);
            $this->saveCache();
        }

        foreach ($response->d->results as $k => $result) {
            if ($k !== $pick) {
                continue;
            }

            /* @todo Consider using gd or imagemagick to adjust image */
            /* @todo Check width and height */
            if ($result->Width > $this->maxwidth) {
                $info = array(
                    'url' => $result->Thumbnail->MediaUrl,
                    'content-type' => $result->Thumbnail->ContentType,
                    'filesize' => $result->Thumbnail->FileSize,
                    'width' => $result->Thumbnail->Width,
                    'height' => $result->Thumbnail->Height,
                );
            } else {
                $info = array(
                    'url' => $result->MediaUrl,
                    'content-type' => $result->ContentType,
                    'extension' => pathinfo($result->MediaUrl, PATHINFO_EXTENSION),
                    'filesize' => $result->FileSize,
                    'width' => $result->Width,
                    'height' => $result->Height,
                );
            }
            break;
        }

        return $this->imageinfo = $info;
    }

    /**
     * Outputs a returned image, in binary form.
     * This sets header(), so has limited use cases. If headers were already
     * sent, then an E_USER_ERROR level error is generated.
     */
    public function outputImage()
    {
        if (!$this->executeQuery()) {
            return false;
        }
        if (headers_sent($file, $line)) {
            trigger_error("Header information was already sent, so cannot output image. See $file $line.", E_USER_ERROR);

            return false;
        }
        header('Content-Type: '.$this->imageinfo['content-type']);
        readfile($this->imageinfo['url']);
    }

    /**
     * Gets image information from a returned result.
     *
     * @return array Image information on success, false on failure
     */
    public function getImageInfo()
    {
        if (!$this->executeQuery()) {
            return false;
        }

        return $this->imageinfo;
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
     * Sets the search format. Only "json" is available at this time,
     * so this method is useless.
     *
     * @param string $format The desired type, defaults to "json"
     */
    public function setSearchFormat($format)
    {
        $this->bing_search_format = trim($format);
    }

    /**
     * Returned desired image, whether it's the thumbnail or
     * original image.
     *
     * @param array $info The image information
     *
     * @return Desired image information
     */
    public function returnDesiredImage($info)
    {
        if ($info['width'] > $this->maxwidth) {
            $_tmp = array(
                'url' => $info['thumb_url'],
                'content-type' => $info['thumb_ct'],
                'filesize' => $info['thumb_filesize'],
                'width' => $info['thumb_width'],
                'height' => $info['thumb_height'],
            );
        } else {
            $_tmp = array(
                'url' => $info['url'],
                'content-type' => $info['ct'],
                'filesize' => $info['filesize'],
                'width' => $info['width'],
                'height' => $info['height'],
            );
        }

        return $_tmp;
    }

    /**
     * Create Cache table.
     *
     * @todo check if file exists?
     * @todo add error handling
     *
     * @return true on success, false on failure
     */
    public function createCacheTable()
    {
        if (empty($this->cache_sqlite_db)) {
            return false;
        }
        $db = new SQLite3($this->cache_sqlite_db);
        // @todo Table structure was written in haste, could be optimized
        $sql = '
		CREATE TABLE fantasy_player_images (
		id integer primary key autoincrement, query TEXT, rid INT, thumb_url TEXT, 
		thumb_ct TEXT, thumb_filesize INT, thumb_width INT, 
		thumb_height INT, url TEXT, ct TEXT, ext VARCHAR(10), filesize INT, width INT, height INT)';
        $db->query($sql);

        return true;
    }

    /**
     * Cache a query by saving the results.
     *
     * @todo Check image widths, return accordingly
     *
     * @return array Cached image information on success, false if not cached or failed
     */
    public function getCachedImage($query)
    {
        if (empty($this->cache_sqlite_db)) {
            return false;
        }
        $max = $this->maxcheck;

        $db = new SQLite3($this->cache_sqlite_db);
        $sql = "SELECT * FROM fantasy_player_images WHERE query = '".$db->escapeString($query)."' AND rid < $max ORDER BY RANDOM() LIMIT 1";
        $res = $db->query($sql);
        if (!$res) {
            $this->log[] = "Query ($sql) Failed";

            return false;
        }
        $row = $res->fetchArray(SQLITE3_ASSOC);
        if ($row) {
            return $this->returnDesiredImage($row);
        }
        $this->log[] = 'Cached image did not exist';

        return false;
    }

    /**
     * Cache a query by saving the results.
     *
     * @todo Save images locally to disk? Or in binary form? Legality?
     *
     * @return array SQL query
     */
    public function cacheImages($results)
    {
        if (empty($this->cache_sqlite_db)) {
            return false;
        }
        $db = new SQLite3($this->cache_sqlite_db);
        foreach ($results as $k => $result) {
            $values = array(
                $this->query,
                $k,
                $result->Thumbnail->MediaUrl,
                $result->Thumbnail->ContentType,
                $result->Thumbnail->FileSize,
                $result->Thumbnail->Width,
                $result->Thumbnail->Height,
                $result->MediaUrl,
                $result->ContentType,
                pathinfo($result->MediaUrl, PATHINFO_EXTENSION),
                $result->FileSize,
                $result->Width,
                $result->Height,
            );
            $sql[$k] = '
				INSERT INTO fantasy_player_images
				(id, query, rid, thumb_url, thumb_ct, thumb_filesize, thumb_width, thumb_height, url, ct, ext, filesize, width, height)
				VALUES (NULL, ';
            foreach ($values as $value) {
                $sql[$k] .= "'".$db->escapeString($value)."',";
            }
            $sql[$k] = trim($sql[$k], ',');
            $sql[$k] .= ')';
        }
        if (count($sql) < 1) {
            return false;
        }
        $this->cache_sql = $sql;

        return true;
    }

    /**
     * Save cache results to an SQLite database.
     *
     * @todo check if file exists?
     * @todo add error handling
     * @todo check if cache value exists, overwrite?
     *
     * @return true on success, false on failure
     */
    public function saveCache()
    {
        if (empty($this->cache_sqlite_db)) {
            return false;
        }
        $db = new SQLite3($this->cache_sqlite_db);
        foreach ($this->cache_sql as $k => $query) {
            $db->query($query);
        }

        return true;
    }
}
