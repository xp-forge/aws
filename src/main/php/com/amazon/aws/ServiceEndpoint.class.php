<?php namespace com\amazon\aws;

use com\amazon\aws\api\{Resource, Response, SignatureV4, Transfer};
use lang\IllegalArgumentException;
use peer\http\{HttpConnection, HttpRequest};
use util\data\Marshalling;
use util\log\Traceable;

/**
 * AWS service endpoint
 *
 * @see   https://docs.aws.amazon.com/general/latest/gr/rande.html
 * @test  com.amazon.aws.unittest.ServiceEndpointTest
 * @test  com.amazon.aws.unittest.RequestTest
 * @test  com.amazon.aws.unittest.RequestSigningTest
 */
class ServiceEndpoint implements Traceable {
  private $service, $auth, $userAgent, $connections, $marshalling;
  private $region= null;
  private $cat= null;
  private $base= '/';
  private $domain= null;

  /**
   * Creates a new AWS endpoint
   * 
   * @param  string $service Service code
   * @param  com.amazon.aws.Credentials|com.amazon.aws.CredentialProvider|(function(): com.amazon.aws.Credentials) $auth
   */
  public function __construct($service, $auth) {
    $this->service= $service;

    if ($auth instanceof CredentialProvider) {
      $this->auth= $auth->orThrow();
    } else if ($auth instanceof Credentials || is_callable($auth)) {
      $this->auth= $auth;
    } else {
      throw new IllegalArgumentException('Expected Credentials, CredentialProvider or a function, have '.typeof($auth));
    }

    $this->userAgent= sprintf(
      'xp-aws/1.0.0 OS/%s/%s lang/php/%s',
      php_uname('s'),
      php_uname('r'),
      PHP_VERSION
    );
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
  public function credentials() {
    return $this->auth instanceof Credentials ? $this->auth : cast(($this->auth)(), Credentials::class);
  }

  /** @return ?string */
  public function region() { return $this->region; }

  /** @return string */
  public function domain() {
    if (null === $this->domain) {
      return null === $this->region
        ? "{$this->service}.amazonaws.com"
        : "{$this->service}.{$this->region}.amazonaws.com"
      ;
    } else if ('.' === $this->domain[strlen($this->domain) - 1]) {
      return null === $this->region
        ? "{$this->domain}amazonaws.com"
        : "{$this->domain}{$this->region}.amazonaws.com"
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
   * Returns a new resource consisting of path including optional placeholders
   * and replacement segments.
   * 
   * @param  string|com.amazon.aws.S3Key $path
   * @throws lang.ElementNotFoundException
   */
  public function resource($path, array $segments= []): Resource {
    return new Resource($this, $path, $segments, $this->marshalling);
  }

  /**
   * Extracts path, encoded and params from a given target. Handles S3 keys, which do
   * not double-encode the path component in the canonical request.
   *
   * @see    https://github.com/aws/aws-sdk-php/pull/633
   * @param  com.amazon.aws.api.SignatureV4 $signature
   * @param  string|com.amazon.aws.S3Key $target
   * @return var[]
   */
  private function target($signature, $target) {
    if ($target instanceof S3Key) {
      $path= $target->path($this->base);
      return [$path, $signature->encoded($path), []];
    } else if (false === ($p= strpos($target, '?'))) {
      $path= $path= $this->base.ltrim($target, '/');
      return [$path, $path, []];
    } else {
      parse_str(substr($target, $p + 1), $params);
      $path= $this->base.ltrim(substr($target, 0, $p), '/');
      return [$path, $path, $params];
    }
  }

  /** Signs a given target (optionally including parameters) with a given expiry time */
  public function sign($target, int $expires= 3600, $time= null): string {
    $signature= new SignatureV4($this->credentials());
    list($path, $encoded, $params)= $this->target($signature, $target);

    $host= $this->domain();
    $region= $this->region ?? '*';

    // Combine target parameters with `X-Amz-*` headers used for signature
    $params+= [
      'X-Amz-Algorithm'      => SignatureV4::ALGO,
      'X-Amz-Credential'     => $signature->credential($this->service, $region, $time),
      'X-Amz-Date'           => $signature->datetime($time),
      'X-Amz-Expires'        => $expires,
      'X-Amz-Security-Token' => $signature->securityToken(),
      'X-Amz-SignedHeaders'  => 'host',
    ];

    // Next, sign path and query string with the special hash `UNSIGNED-PAYLOAD`,
    // signing only the "Host" header as indicated above.
    $signature= $signature->sign(
      $this->service,
      $region,
      'GET',
      $path,
      $params,
      SignatureV4::UNSIGNED,
      ['Host' => $host],
      $time
    );

    // Finally, append signature parameter to signed link
    $params['X-Amz-Signature']= $signature['signature'];
    return "https://{$host}{$encoded}?".http_build_query($params, '', '&', PHP_QUERY_RFC3986);
  }

  /**
   * Opens a request and returns a `Transfer` instance for writing data to
   *
   * @throws io.IOException
   */
  public function open(string $method, $target, array $headers, $hash= null, $time= null): Transfer {
    $signature= new SignatureV4($this->credentials());
    list($path, $encoded, $params)= $this->target($signature, $target);

    $host= $this->domain();
    $conn= ($this->connections)('https://'.$host.$encoded);
    $conn->setTrace($this->cat);

    // Create and sign request
    $request= $conn->create(new HttpRequest());
    $request->setMethod($method);
    $request->setTarget($encoded);
    $request->addHeaders($headers);

    // Compile headers from given host and time including our user agent
    $signed= [
      'Host'             => $host,
      'X-Amz-Date'       => $signature->datetime($time),
      'X-Amz-User-Agent' => $headers['User-Agent'] ?? $this->userAgent,
    ];

    // Automatically include security token if available
    if (null !== ($token= $signature->securityToken())) {
      $signed['X-Amz-Security-Token']= $token;
    }

    // Include Content-Type and any x-amz-* headers
    foreach ($headers as $name => $value) {
      if (0 === strncasecmp($name, 'X-Amz-', 6) || 0 === strncasecmp($name, 'Content-Type', 12)) {
        $signed[$name]= $value;
      }
    }

    // Calculate signature, then add headers including authorization
    $signature= $signature->sign(
      $this->service,
      $this->region ?? '*',
      $method,
      $path,
      $params,
      $hash ?? $headers['x-amz-content-sha256'] ?? SignatureV4::NO_PAYLOAD,
      $signed,
      $time
    );
    $request->addHeaders($signed + ['Authorization' => sprintf(
      '%s Credential=%s, SignedHeaders=%s, Signature=%s',
      SignatureV4::ALGO,
      $signature['credential'],
      $signature['headers'],
      $signature['signature']
    )]);

    return new Transfer($conn, $request, $this->marshalling);
  }

  /**
   * Sends a request and returns the response
   *
   * @throws io.IOException
   */
  public function request(string $method, $target, array $headers= [], $payload= null, $time= null): Response {
    if (null === $payload) {
      $transfer= $this->open($method, $target, $headers + ['Content-Length' => 0], SignatureV4::NO_PAYLOAD, $time);
    } else {
      $transfer= $this->open(
        $method,
        $target,
        $headers + ['Content-Length' => strlen($payload)],
        hash(SignatureV4::HASH, $payload),
        $time
      );
      $transfer->write($payload);
    }
    return $transfer->finish();
  }
}