<?php namespace com\amazon\aws;

use com\amazon\aws\api\{Resource, Response, SignatureV4};
use peer\http\{HttpConnection, HttpRequest};
use util\data\Marshalling;
use util\log\Traceable;

/**
 * AWS service endpoint
 *
 * @see   https://docs.aws.amazon.com/general/latest/gr/rande.html
 * @test  com.amazon.aws.unittest.ServiceEndpointTest
 * @test  com.amazon.aws.unittest.RequestTest
 */
class ServiceEndpoint implements Traceable {
  private $service, $credentials, $signature, $connections, $marshalling;
  private $region= null;
  private $cat= null;
  private $base= '/';
  private $domain= null;

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
    $this->marshalling= new Marshalling();
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

  /** Sets version to use */
  public function version(string $version): self {
    $this->base= "/{$version}/";
    return $this;
  }

  /** Sets domain or prefix to use */
  public function using(string $domain): self {
    $this->domain= $domain;
    return $this;
  }

  /** @return string */
  public function service() { return $this->service; }

  /** @return com.amazon.aws.Credentials */
  public function credentials() { return $this->credentials; }

  /** @return ?string */
  public function region() { return $this->region; }

  /** @return string */
  public function domain() {
    if (null === $this->domain) {
      return null === $this->region
        ? "{$this->service}.amazonaws.com"
        : "{$this->service}.{$this->region}.amazonaws.com"
      ;
    } else if (false === strpos($this->domain, '.')) {
      return null === $this->region
        ? "{$this->domain}.{$this->service}.amazonaws.com"
        : "{$this->domain}.{$this->service}.{$this->region}.amazonaws.com"
      ;
    } else {
      return $this->domain;
    }
  }

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
    return new Resource($this, $path, $segments, $this->marshalling);
  }

  /** Signs a given target (optionally including parameters) with a given expiry time */
  public function sign(string $target, int $expires= 3600, int $time= null): string {
    $host= $this->domain();
    $region= $this->region ?? '*';

    // Combine target parameters with `X-Amz-*` headers used for signature
    if (false === ($p= strpos($target, '?'))) {
      $params= [];
    } else {
      parse_str(substr($target, $p + 1), $params);
      $target= substr($target, 0, $p);
    }
    $params+= [
      'X-Amz-Algorithm'      => SignatureV4::ALGO,
      'X-Amz-Credential'     => $this->signature->credential($this->service, $region, $time),
      'X-Amz-Date'           => $this->signature->datetime($time),
      'X-Amz-Expires'        => $expires,
      'X-Amz-Security-Token' => $this->credentials->sessionToken(),
      'X-Amz-SignedHeaders'  => 'host',
    ];

    // Next, sign path and query string with the special hash `UNSIGNED-PAYLOAD`,
    // signing only the "Host" header as indicated above.
    $link= $this->base.ltrim($target, '/');
    $signature= $this->signature->sign(
      $this->service,
      $region,
      'GET',
      $link,
      $params,
      'UNSIGNED-PAYLOAD',
      ['Host' => $host],
      $time
    );

    // Finally, append signature parameter to signed link
    $params['X-Amz-Signature']= $signature['signature'];
    return "https://{$host}{$link}?".http_build_query($params, '', '&', PHP_QUERY_RFC3986);
  }

  /**
   * Sends a request and returns the response
   *
   * @throws io.IOException
   */
  public function request(string $method, string $target, array $headers= [], string $payload= null): Response {
    $host= $this->domain();
    $target= $this->base.ltrim($target, '/');
    $conn= ($this->connections)('https://'.$host.$target);
    $conn->setTrace($this->cat);

    // Create and sign request
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

    if (null === $payload) {
      $r= $conn->send($request);
    } else {
      $stream= $conn->open($request);
      $stream->write($payload);
      $r= $conn->finish($stream);
    } 
    return new Response($r->statusCode(), $r->message(), $r->headers(), $r->in(), $this->marshalling);
  }
}