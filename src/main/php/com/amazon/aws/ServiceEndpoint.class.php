<?php namespace com\amazon\aws;

use com\amazon\aws\api\{Resource, Response, SignatureV4};
use peer\http\{HttpConnection, HttpRequest};
use util\log\Traceable;

/**
 * AWS service endpoint
 *
 * @see   https://docs.aws.amazon.com/general/latest/gr/rande.html
 * @test  com.amazon.aws.unittest.ServiceEndpointTest
 * @test  com.amazon.aws.unittest.RequestTest
 */
class ServiceEndpoint implements Traceable {
  private $service, $credentials, $connections;
  private $region= null;
  private $cat= null;

  /**
   * Creates a new AWS endpoint
   * 
   * @param  string $service Service code
   * @param  com.amazon.aws.Credentials $credentials
   */
  public function __construct($service, Credentials $credentials) {
    $this->service= $service;
    $this->credentials= $credentials;
    $this->signature= new SignatureV4($credentials, sprintf(
      'xp-aws/1.0.0 OS/%s/%s lang/php/%s',
      php_uname('s'),
      php_uname('r'),
      PHP_VERSION
    ));
    $this->connections= function($uri) { return new HttpConnection($uri); };
  }

  /** Sets region code */
  public function in(string $region): self {
    $this->region= $region;
    return $this;
  }

  /** Sets global region */
  public function global(): self {
    $this->region= null;
    return $this;
  }

  /** @return string */
  public function service() { return $this->service; }

  /** @return com.amazon.aws.Credentials */
  public function credentials() { return $this->credentials; }

  /** @return ?string */
  public function region() { return $this->region; }

  /**
   * Sets a log category for debugging
   *
   * @param  ?util.log.LogCategory $cat
   * @return void
   */
  public function setTrace($cat) {
    $this->cat= $cat;
  }

  /**
   * Specify a connection function, which gets passed a URI and returns a
   * `HttpConnection` instance.
   *
   * @param  function(var): peer.http.HttpConnection $connections
   * @return self
   */
  public function connecting($connections) {
    $this->connections= cast($connections, 'function(var): peer.http.HttpConnection');
    return $this;
  }

  /**
   * Returns a new resource consisting of path including
   * optional placeholders and replacement segments.
   * 
   * @throws lang.ElementNotFoundException
   */
  public function resource(string $path, array $segments= []): Resource {
    return new Resource($this, $path, $segments);
  }

  /**
   * Sends a request and returns the response
   *
   * @throws io.IOException
   */
  public function request(string $method, string $target, array $headers= [], string $payload= null): Response {
    $host= null === $this->region
      ? "{$this->service}.amazonaws.com"
      : "{$this->service}.{$this->region}.amazonaws.com"
    ;

    // Ensure target path always starts with a forward slash
    if ('/' !== ($target[0] ?? '')) $target= '/'.$target;

    // Create and sign request
    $conn= ($this->connections)('https://'.$host.$target);
    $request= $conn->create(new HttpRequest());
    $request->setMethod($method);
    $request->setTarget($target);
    $request->addHeaders($headers + ['Content-Length' => strlen($payload)]);
    $request->addHeaders($this->signature->headers(
      $this->service,
      $this->region ?? '*',
      $host,
      $method,
      $target,
      $payload ?? ''
    ));

    $conn->setTrace($this->cat);
    if (null === $payload) {
      $r= $conn->send($request);
    } else {
      $stream= $conn->open($request);
      $stream->write($payload);
      $r= $conn->finish($stream);
    } 
    return new Response($r->statusCode(), $r->message(), $r->headers(), $r->in());
  }
}