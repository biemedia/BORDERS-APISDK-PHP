<?php

class BordersInterface {
	private $publicKey;
	private $privateKey;

	private $secure = false;
	public function isSecure() {
		return $this->secure;
	}
	public function setSecure($secure) {
		$this->secure = (boolean)$secure;
	}

	private $timeout = 60;
	public function getTimeout() {
		return $this->timeout;
	}
	public function setTimeout($timeout) {
		$this->timeout = (int)$timeout;
	}

	public function __construct($publicKey, $privateKey) {
		if(strlen($publicKey) !== 64 || strlen($privateKey) !== 64) {
			throw new BordersAPIException('API keys appear invalid');
		}
		$this->publicKey = $publicKey;
		$this->privateKey = $privateKey;
	}

	public function get($path, $queryParameters = array(), $curlOptions = array()) {
		return $this->sendRequest('GET', $path, $queryParameters, null, $curlOptions);
	}

	public function post($path, $body, $queryParameters = array(), $curlOptions = array()) {
		return $this->sendRequest('POST', $path, $queryParameters, $body, $curlOptions);
	}

	public function put($path, $body, $queryParameters = array(), $curlOptions = array()) {
		// Reserved for uploading files
	}

	public function delete($path, $queryParameters = array(), $curlOptions = array()) {
		return $this->sendRequest('DELETE', $path, $queryParameters, null, $curlOptions);
	}

	protected function sendRequest($method, $path, $queryParameters = array(), $body = null, $userCurlOptions = array()) {
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
		$curlOptions = [
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_CUSTOMREQUEST => $method,
			CURLOPT_URL => $requestURL,
			CURLOPT_HTTPHEADER => ['Content-Type: Application/JSON']
		];
		if($body !== null) {
			$curlOptions[CURLOPT_POSTFIELDS] = $body;
		}
		$curlOptions = $userCurlOptions + $curlOptions;

		curl_setopt_array($CH, $curlOptions);

		$response = curl_exec($CH);
		if($response === false) {
			throw new BordersAPIException(curl_error($CH));
		}
		
		$data = json_decode($response);
		if(!$data) {
			throw new BordersAPIException('Unable to decode response data');
		}
		$response = $data->response;

		return $response;
	}

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