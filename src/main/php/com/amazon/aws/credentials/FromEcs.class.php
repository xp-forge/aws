<?php namespace com\amazon\aws\credentials;

use com\amazon\aws\Credentials;
use lang\{Environment, IllegalStateException, Throwable};
use peer\URL;
use peer\http\{HttpConnection, HttpRequest};
use text\json\{Json, StreamInput};
use util\Secret;

/**
 * Reads credentials from container credential provider. This credential
 * provider is useful for Amazon Elastic Container Service (Amazon ECS)
 * and Amazon Elastic Kubernetes Service (Amazon EKS) customers. SDKs
 * attempt to load credentials from the specified HTTP endpoint.
 * 
 * @see   https://docs.aws.amazon.com/sdkref/latest/guide/feature-container-credentials.html
 * @test  com.amazon.aws.unittest.CredentialProviderTest
 */
class FromEcs implements Provider {
  const DEFAULT_HOST= 'http://169.254.170.2';

  private $conn, $userAgent;
  private $credentials= null;

  /** @param ?peer.HttpConnection $conn */
  public function __construct($conn= null) {
    $this->conn= $conn ?? new HttpConnection(self::DEFAULT_HOST);
    $this->userAgent= sprintf(
      'xp-aws/1.0.0 OS/%s/%s lang/php/%s',
      php_uname('s'),
      php_uname('r'),
      PHP_VERSION
    );
  }

  /** @return ?com.amazon.aws.Credentials */
  public function credentials() {
    if (null !== $this->credentials && !$this->credentials->expired()) return $this->credentials;

    // Check AWS_CONTAINER_CREDENTIALS_*
    if (null !== ($relative= Environment::variable('AWS_CONTAINER_CREDENTIALS_RELATIVE_URI', null))) {
      $req= $this->conn->create(new HttpRequest());
      $req->setTarget($relative);
    } else if (null !== ($uri= Environment::variable('AWS_CONTAINER_CREDENTIALS_FULL_URI', null))) {
      $req= new HttpRequest(new URL($uri));
    } else {
      return $this->credentials= null;
    }

    // Append authorization from AWS_CONTAINER_AUTHORIZATION_TOKEN_*, if existant
    if (null !== ($file= Environment::variable('AWS_CONTAINER_AUTHORIZATION_TOKEN_FILE', null))) {
      $req->setHeader('Authorization', rtrim(file_get_contents($file), "\r\n"));
    } else if (null !== ($token= Environment::variable('AWS_CONTAINER_AUTHORIZATION_TOKEN', null))) {
      $req->setHeader('Authorization', $token);
    }

    $req->setHeader('User-Agent', $this->userAgent);
    try {
      $res= $this->conn->send($req);
    } catch (Throwable $t) {
      throw new IllegalStateException("Container credential provider {$req->getUrl()->getURL()} failed", $t);
    }

    if (200 !== $res->statusCode()) {
      throw new IllegalStateException("Container credential provider {$req->getUrl()->getURL()} returned unexpected {$res->toString()}");
    }

    $credentials= Json::read(new StreamInput($res->in()));
    return $this->credentials= new Credentials(
      $credentials['AccessKeyId'],
      $credentials['SecretAccessKey'],
      $credentials['Token'] ?? null,
      $credentials['Expiration'] ?? null
    );
  }
}