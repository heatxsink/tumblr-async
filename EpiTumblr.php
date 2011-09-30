<?php
/*
 *  Class to integrate with tumblr's relatively new oauth api.
 *  Authenticated calls are done using OAuth and require access_tokens for a user.
 * 
 *  Full documentation available on github
 *    http://wiki.github.com/heatxsink/tumblr-async
 * 
 *  Based heavily on twitter-async by Jaisen Mathai <jaisen@jmathai.com>
 *  @author Nick Granado <ngranado@gmail.com>
 */

class EpiTumblr extends EpiOAuth {
  
  const EPI_TUMBLR_SIGNATURE_METHOD = 'HMAC-SHA1';
  const EPI_TUMBLR_AUTH_OAUTH = 'oauth';
  protected $requestTokenUrl= 'http://www.tumblr.com/oauth/request_token';
  protected $accessTokenUrl = 'http://www.tumblr.com/oauth/access_token';
  protected $authorizeUrl   = 'http://www.tumblr.com/oauth/authorize';
  protected $apiUrl         = 'http://www.tumblr.com';
  protected $userAgent      = 'EpiTumblr (http://github.com/heatxsink/tumblr-async)';
  protected $isAsynchronous = false;
  
  /* OAuth methods */
  public function delete($endpoint, $params = null) {
    return $this->request('DELETE', $endpoint, $params);
  }
  
  public function get($endpoint, $params = null) {
    return $this->request('GET', $endpoint, $params);
  }

  public function post($endpoint, $params = null) {
    return $this->request('POST', $endpoint, $params);
  }

  public function useAsynchronous($async = true) {
    $this->isAsynchronous = (bool)$async;
  }

  public function __construct($consumer_key = null, $consumer_secret = null, $oauth_access_token_key = null, $oauth_access_token_secret = null) {
    parent::__construct($consumer_key, $consumer_secret, self::EPI_TUMBLR_SIGNATURE_METHOD);
    $this->setToken($oauth_access_token_key, $oauth_access_token_secret);
  }

  public function __call($name, $params = null/*, $username, $password*/) {
    $parts  = explode('_', $name);
    $method = strtoupper(array_shift($parts));
    $parts  = implode('_', $parts);
    $endpoint   = '/' . preg_replace('/[A-Z]|[0-9]+/e', "'/'.strtolower('\\0')", $parts) . '.json';
    /* HACK: this is required for list support that starts with a user id */
    $endpoint = str_replace('//','/',$endpoint);
    $args = !empty($params) ? array_shift($params) : null;

    return $this->request($method, $endpoint, $args);
  }

  private function getApiUrl($endpoint) {
      return "{$this->apiUrl}{$endpoint}";
  }

  private function request($method, $endpoint, $params = null) {
    $url = $this->getUrl($this->getApiUrl($endpoint));
    $response = new EpiTumblrJson(call_user_func(array($this, 'httpRequest'), $method, $url, $params, $this->isMultipart($params)), $this->debug);
    if(!$this->isAsynchronous) {
      $response->responseText;
    }
    return $response;
  }
}

class EpiTumblrJson implements ArrayAccess, Countable, IteratorAggregate {
  private $debug;
  private $__resp;
  public function __construct($response, $debug = false) {
    $this->__resp = $response;
    $this->debug  = $debug;
  }

  // ensure that calls complete by blocking for results, NOOP if already returned
  public function __destruct() {
    $this->responseText;
  }

  // Implementation of the IteratorAggregate::getIterator() to support foreach ($this as $...)
  public function getIterator () {
    if ($this->__obj) {
      return new ArrayIterator($this->__obj);
    } else {
      return new ArrayIterator($this->response);
    }
  }

  // Implementation of Countable::count() to support count($this)
  public function count () {
    return count($this->response);
  }
  
  // Next four functions are to support ArrayAccess interface
  // 1
  public function offsetSet($offset, $value) {
    $this->response[$offset] = $value;
  }

  // 2
  public function offsetExists($offset) {
    return isset($this->response[$offset]);
  }
  
  // 3
  public function offsetUnset($offset) {
    unset($this->response[$offset]);
  }

  // 4
  public function offsetGet($offset) {
    return isset($this->response[$offset]) ? $this->response[$offset] : null;
  }

  public function __get($name) {
    $accessible = array('responseText'=>1,'headers'=>1,'code'=>1);
    $this->responseText = $this->__resp->data;
    $this->headers      = $this->__resp->headers;
    $this->code         = $this->__resp->code;
    
    if(isset($accessible[$name]) && $accessible[$name]) {
      return $this->$name;
    }
    elseif(($this->code < 200 || $this->code >= 400) && !isset($accessible[$name])) {
      EpiTumblrException::raise($this->__resp, $this->debug);
    }
    
    // Call appears ok so we can fill in the response
    $this->response     = json_decode($this->responseText, 1);
    $this->__obj        = json_decode($this->responseText);
    
    if(gettype($this->__obj) === 'object') {
      foreach($this->__obj as $k => $v) {
        $this->$k = $v;
      }
    }
    
    if (property_exists($this, $name)) {
      return $this->$name;
    }
    
    return null;
  }

  public function __isset($name) {
    $value = self::__get($name);
    return !empty($name);
  }
}

class EpiTumblrException extends Exception {
  public static function raise($response, $debug) {
    $message = $response->data;
    switch($response->code) {
      case 400:
        throw new EpiTumblrBadRequestException($message, $response->code);
      case 401:
        throw new EpiTumblrNotAuthorizedException($message, $response->code);
      case 403:
        throw new EpiTumblrForbiddenException($message, $response->code);
      case 404:
        throw new EpiTumblrNotFoundException($message, $response->code);
      case 406:
        throw new EpiTumblrNotAcceptableException($message, $response->code);
      case 420:
        throw new EpiTumblrEnhanceYourCalmException($message, $response->code);
      case 500:
        throw new EpiTumblrInternalServerException($message, $response->code);
      case 502:
        throw new EpiTumblrBadGatewayException($message, $response->code);
      case 503:
        throw new EpiTumblrServiceUnavailableException($message, $response->code);
      default:
        throw new EpiTumblrException($message, $response->code);
    }
  }
}

class EpiTumblrBadRequestException extends EpiTumblrException{}
class EpiTumblrNotAuthorizedException extends EpiTumblrException{}
class EpiTumblrForbiddenException extends EpiTumblrException{}
class EpiTumblrNotFoundException extends EpiTumblrException{}
class EpiTumblrNotAcceptableException extends EpiTumblrException{}
class EpiTumblrEnhanceYourCalmException extends EpiTumblrException{}
class EpiTumblrInternalServerException extends EpiTumblrException{}
class EpiTumblrBadGatewayException extends EpiTumblrException{}
class EpiTumblrServiceUnavailableException extends EpiTumblrException{}
