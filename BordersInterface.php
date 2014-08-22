<?php

/**
 * The BordersInterface class allows for quick setup and execution of API calls to the BORDERS API system
 */
class BordersInterface {
	/**
	 * @var $publicKey A string that contains the public portion of your API credentials
	 */
	private $publicKey;

	/**
	 * @var $privateKey A string that contains the private portion of your API credentials
	 */
	private $privateKey;


	/**
	 * @var $secure Determines weather SSL or HTTP will be used.  When true, SSL will be used
	 */
	private $secure = false;

	/**
	 * Returns weather or not an SSL connection will be made.
	 *
	 * @return boolean
	 */
	public function isSecure() {
		return $this->secure;
	}

	/**
	 * Sets weather or not a secure connection should be made using SSL
	 *
	 * @param boolean $secure
	 *
	 * @return void
	 */
	public function setSecure($secure) {
		$this->secure = (boolean)$secure;
	}


	/**
	 * @var $timeout The number of seconds until a request expires
	 */
	private $timeout = 60;

	/**
	 * Returns the number of seconds used for the timeout
	 *
	 * @return int
	 */
	public function getTimeout() {
		return $this->timeout;
	}

	/**
	 * Sets the number of seconds to use for the timeout
	 *
	 * @param int $timeout The number of seconds
	 *
	 * @return void
	 */
	public function setTimeout($timeout) {
		$this->timeout = (int)$timeout;
	}

	/**
	 * Builds the BordersInterface by taking your API credentials
	 *
	 * @param string $publicKey The 64 character public portion of your API credentials
	 * @param string $privateKey The 64 character private portion of your API credentials
	 *
	 * @return void
	 *
	 * @throws BordersAPIException When your keys do not appear to be valid
	 */
	public function __construct($publicKey, $privateKey) {
		if(strlen($publicKey) !== 64 || strlen($privateKey) !== 64) {
			throw new BordersAPIException('API keys appear invalid');
		}
		$this->publicKey = $publicKey;
		$this->privateKey = $privateKey;
	}

	/**
	 * Submits a GET request to the BORDERS API system
	 *
	 * @param string $path The path you are requesting from the API
	 * @param array $queryParameters An array of key => value pairs for the query string
	 * @param array $CURLOptions An array of user CURL option overrides to be used by the curl_setopt_array(handle, array) function
	 *
	 * @return Object Returns the json_decode(d) object from the response.
	 * @see documentation.borders.biemedia.com/API/response_object
	 *
	 * @throws BordersAPIException When there is something wrong with the request or response
	 */
	public function get($path, $queryParameters = array(), $CURLOptions = array()) {
		return $this->sendRequest('GET', $path, $queryParameters, null, $CURLOptions);
	}

	/**
	 * Submits a POST request to the BORDERS API system
	 *
	 * @param string $path The path you are requesting from the API
	 * @param array $body An array of key => value pairs to be json_encode(d) as the body
	 * @param array $queryParameters An array of key => value pairs for the query string
	 * @param array $CURLOptions An array of user CURL option overrides to be used by the curl_setopt_array(handle, array) function
	 *
	 * @return Object Returns the json_decode(d) object from the response.
	 * @see documentation.borders.biemedia.com/API/response_object
	 *
	 * @throws BordersAPIException When there is something wrong with the request or response
	 */
	public function post($path, $body, $queryParameters = array(), $CURLOptions = array()) {
		return $this->sendRequest('POST', $path, $queryParameters, $body, $CURLOptions);
	}

	public function put($path, $body, $queryParameters = array(), $CURLOptions = array()) {
		// Reserved for uploading files
	}

	/**
	 * Submits a POST request to the BORDERS API system
	 *
	 * @param string $path The path you are requesting from the API
	 * @param array $body An array of key => value pairs to be json_encode(d) as the body
	 * @param array $queryParameters An array of key => value pairs for the query string
	 * @param array $CURLOptions An array of user CURL option overrides to be used by the curl_setopt_array(handle, array) function
	 *
	 * @return Object Returns the json_decode(d) object from the response.
	 * @see documentation.borders.biemedia.com/API/response_object
	 *
	 * @throws BordersAPIException When there is something wrong with the request or response
	 */
	public function delete($path, $queryParameters = array(), $CURLOptions = array()) {
		return $this->sendRequest('DELETE', $path, $queryParameters, null, $CURLOptions);
	}

	/**
	 * Builds and sends the request to the BORDERS API system
	 * 
	 * @param string $method The capitalized HTTP method used to make the request. For example: GET, POST, DELETE
	 * @param string $path The path being requested from the BORDERS API system
	 * @param array $queryParameters An array of key => value pairs for the query string
	 * @param array $body An array of key => value pairs that will be json_encode(d) to form the body of the request.  May be null
	 * @param array $userCURLOptions An array of user defined overrides/extra CURL options to be used with the CURL request.
	 *
	 * @return Object Returns the json_decode(d) object from the response.
	 * @see documentation.borders.biemedia.com/API/response_object
	 *
	 * @throws BordersAPIException When there is something wrong with the request or response
	 */
	protected function sendRequest($method, $path, $queryParameters = array(), $body = null, $userCURLOptions = array()) {
		$requestURL = 'api.Borders.biemedia.com';
		$requestURL = ($this->secure) ? 'https://' . $requestURL : 'http://' . $requestURL;

		// Check query parameters
		$queryParameters['public_key'] = (isset($queryParameters['public_key'])) ?: $this->publicKey;
		$queryParameters['expires'] = (isset($queryParameters['expires'])) ?: time() + $this->timeout;
		unset($queryParameters['signature']);  // Make sure we didn't already sign the request

		// Check body
		if($body !== null) {
			if(!is_array($body)) {
				throw new BordersAPIException('Body should be in array format');
			} else {
				$body = (isset($body['request'])) ?: ['request' => $body];
			}

			$body = json_encode($body, JSON_PRETTY_PRINT);
		}

		// Make sure the path starts with a /
		if(substr($path, 0, 1) !== '/') {
			$path = '/' . $path;
		}

		$queryParameters['signature'] = $this->generateSignature($method, $path, $queryParameters, (string)$body);

		// Build the request URL
		$requestURL .= $path . '?';
		$first = true;
		foreach($queryParameters as $key => $value) {
			if($first) {
				$first = false;
			} else {
				$requestURL .= '&';
			}

			$requestURL .= $key . '=' . urlencode($value);
		}

		$CH = curl_init();
		$CURLOptions = [
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_CUSTOMREQUEST => $method,
			CURLOPT_URL => $requestURL,
			CURLOPT_HTTPHEADER => ['Content-Type: Application/JSON']
		];
		if($body !== null) {
			$CURLOptions[CURLOPT_POSTFIELDS] = $body;
		}
		$CURLOptions = $userCURLOptions + $CURLOptions;

		curl_setopt_array($CH, $CURLOptions);

		$response = curl_exec($CH);
		if($response === false) {
			throw new BordersAPIException(curl_error($CH));
		}
		
		$data = json_decode($response);
		if(!$data) {
			throw new BordersAPIException('Unable to decode response data');
		}
		if(!property_exists($data, 'response')) {
			throw new BordersAPIException('Improper response data');
		}
		$response = $data->response;

		return $response;
	}

	/**
	 * Generates the signature for the request
	 *
	 * @param string $method The capitalized HTTP method used for the request
	 * @param string $path The path being requested
	 * @param array $queryParameters An array of key => value pairs that are going to be used in the query string
	 * @param string $body The body of the request.  May be a blank string
	 *
	 * @return string
	 */
	private function generateSignature($method, $path, $queryParameters, $body) {
		$signature = $method . $this->publicKey . $this->privateKey . $path;
		foreach($queryParameters as $key => $value) {
			if($key == 'signature') {
				continue;
			}
			$signature .= $key . $value;
		}
		$signature .= $body;
		$signature = hash('SHA256', $signature);
		$signature = base64_encode($signature);
		$signature = rtrim($signature, '+=');

		return $signature;
	}
}

class BordersAPIException extends Exception {}