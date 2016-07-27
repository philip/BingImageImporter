<?php
/*
* Return a somewhat random image from Bing images, using their API
*
* @package  BingImageImporter
* @author   Philip Olson <http://github.com/philip>
* @link     https://github.com/philip/FantasySportsTools
* @version  0.1.0 (beta)
*
* @todo Adjust code to follow proper PHP-FIG coding standards
* @todo Adjust code to follow "PHP The Right Way"
*
* Examples:
* * Initiate the class
*    $obj = new BingImageImporter();

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
class BingImageImporter {

	/**
	* Maximum image width of returned images. If exceeded, the 
	* thumbnail is returned instead
	*
	* @var integer Maximum width of returned image, defaults to 300.
	*/
	public $maxwidth = 300;

	/**
	* Maximum number of image results to choose from. Useful when returning
	* a random image. For example, set to 10 to pick one of the first 10 results.
	* Useful if later results aren't as relavent. Default = 10.
	*
	* @var integer Maximum number of images to check, defaults to 10.
	*/
	public $maxcheck = 10;

	/**
	* Method of search to perform and return.
	* Set to "random" to return a random result, otherwise the first result is returned.
	*
	* @var string Image search method, defaults to 'random'
	*/
	public $method   = "random"; // Enter 'random' for random result, else the first result is returned

	/**
	* Search query to determine the type of image returned. Set using setQuery().
	* By default, a "Happy Kitten" image is returned.
	*
	* @var string Image search query, defaults ot "Happy Kitten"
	*/
	private $query = "Happy Kitten";

	/**
	* Your Bing API Key. Set with the setApiKey() method.
	* The Bing Image API interface provides this key.
	*
	* @var string Your Bing API Key, required
	*/
	private $bing_api_key = "";

	/**
	* Format of the request made to bing.
	* At present, the only option is "json".
	*
	* @var string Bing search format, defaults to 'json'
	*/
	private $bing_search_format = "json";

	/**
	* Base URL for the bing request.
	*
	* @var string Bing search URL
	*/
	const BING_BASEURL = "https://api.datamarket.azure.com/Bing/Search/";

	/**
	* Bing search type. Only "Image" makes sense at present, but if desired, you
	* could adjust the code to use something else, such as "Web".
	*
	* @var string Bing search type
	*/
	const BING_SEARCH_TYPE = "Image";

	/**
	* Version of this class, as x.y.z
	*
	* @var string  Class version
	*/
	const VERSION = "0.1.0";
	
	/**
	* Constructor checks for required PHP extensions: Curl and JSON
	* Emits fatal E_USER_ERROR if extension is not present
	*
	* @return void
	*/
	function __construct() {
		if (!function_exists("curl_exec")) {
			trigger_error("The Curl extension is required", E_USER_ERROR);
		}
		if (!function_exists("json_decode")) {
			trigger_error("The JSON extension is required", E_USER_ERROR);
		}
	}
	
	/**
	* Sets the search query, namely $this->query
	*
	* @param string $query Search string for bing image search
	* 
	* @return void
	*/
	public function setQuery($query) {
		$this->query = $query;
	}

	/**
	* Sets the search URL, namely $this->url
	* 
	* @return boolean true on success, false on failure
	*/
	private function setSearchUrl() {
		if (empty($this->query)) {
			trigger_error("A search query was not present", E_USER_WARNING);
			return false;
		}
		/* @todo Confirm this is correct/ideal */
		$url  = self::BING_BASEURL . self::BING_SEARCH_TYPE;
		$url .= '?$format='. $this->bing_search_format .'&Query=%27' . urlencode($this->query) . '%27';
		$this->url = $url;
		return true;
	}
	
	/**
	* Execute a bing image search query
	* 
	* @return array Image information for the returned result, or false on failure
	*/
	private function executeQuery() {

		if (empty($this->bing_api_key)) {
			trigger_error('You must set the BING API KEY, similar to "$obj->setApiKey("s8f8j8ajf8sjf8js8jsa9fuasf");"', E_USER_ERROR);
		}
		$this->setSearchUrl();

		/* @todo Confirm this is correct/ideal */
		/* @todo Add error handling */
		$process = curl_init($this->url);
		curl_setopt($process, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($process, CURLOPT_USERPWD,  $this->bing_api_key . ':' . $this->bing_api_key);
		curl_setopt($process, CURLOPT_TIMEOUT, 15);
		curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
		$response = curl_exec($process);
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
				$max = $rcount -1;
			} else {
				$max = $this->maxcheck;
			}
			$pick = rand(0, $max);
		} else {
			$pick = 0;
		}

		foreach( $response->d->results as $k => $result ) {
			if ($k !== $pick) {
				continue;
			}

			/* @todo Consider using gd or imagemagick to adjust image */
			/* @todo Check width and height */
			if ($result->Width > $this->maxwidth) {
				$info = array(
					'url' => $result->Thumbnail->MediaUrl,
					'content-type' => $result->Thumbnail->ContentType,
					'extension' => pathinfo($result->Thumbnail->MediaUrl, PATHINFO_EXTENSION),
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
			return $this->imageinfo = $info;
		}
		return false;
	}
	
	/**
	* Outputs a returned image, in binary form.
	* This sets header(), so has limited use cases. If headers were already
	* sent, then an E_USER_ERROR level error is generated.
	*
	* @return void on success, otherwise false
	*/
	public function outputImage() {
		if (empty($this->imageinfo) && !$this->executeQuery()) {
			return false;
		}
		if (headers_sent($file, $line)) {
			trigger_error("Header information was already sent, so cannot output image. See $file $line.", E_USER_ERROR);
			return false;
		}
		header('Content-Type: '. $this->imageinfo['content-type']);
		readfile($this->imageinfo['url']);
	}
	
	/**
	* Gets image information from a returned result.
	*
	* @return array Image information on success, false on failure
	*/
	public function getImageInfo() {
		if (empty($this->imageinfo) && !$this->executeQuery()) {
			return false;
		}
		return $this->imageinfo;
	}

	/**
	* Sets the Bing API Key
	*
	* @param string $key The bing API key
	*
	* @return void
	*/
	public function setApiKey($key) {
		$this->bing_api_key = trim($key);
	}

	/**
	* Sets the search format. Only "json" is available at this time,
	* so this method is useless.
	*
	* @param string $format The desired type, defaults to "json"
	*
	* @return void
	*/
	public function setSearchFormat($format) {
		$this->bing_search_format = trim($format);
	}
}