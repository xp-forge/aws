<?php namespace com\amazon\aws\credentials;

use com\amazon\aws\Credentials;
use io\{File, Path};
use lang\{Environment, IllegalStateException, Throwable};
use peer\http\HttpConnection;
use text\json\{Json, FileInput, StreamInput};

/**
 * SSO credentials
 * 
 * @see   https://docs.aws.amazon.com/general/latest/gr/sso.html
 * @test  com.amazon.aws.unittest.CredentialProviderTest
 */
class FromSSO extends Provider {
  public $startUrl, $region, $accountId, $roleName;
  private $cache, $conn;
  private $credentials= null;

  /**
   * Creates a new SSO provider
   *
   * @param  string $startUrl
   * @param  string $region
   * @param  string $accountId
   * @param  string $roleName
   * @param  ?string|io.File $cache
   * @param  ?peer.http.HttpConnection $conn
   */
  public function __construct($startUrl, $region, $accountId, $roleName, $cache= null, $conn= null) {
    $this->startUrl= $startUrl;
    $this->region= $region;
    $this->accountId= $accountId;
    $this->roleName= $roleName;
    $this->cache= $cache instanceof File ? $cache : new File(
      new Path(Environment::homeDir(), '.aws', 'sso', 'cache'),
      sha1($cache ?? $startUrl).'.json'
    );

    $this->conn= $conn ?? new HttpConnection("https://portal.sso.{$region}.amazonaws.com/federation/credentials");
  }

  /** @return ?com.amazon.aws.Credentials */
  public function credentials() {
    if (null !== $this->credentials && !$this->credentials->expired()) return $this->credentials;
    if (!$this->cache->exists()) return $this->credentials= null;

    // Read cache, check for its expiration date
    $cache= Json::read(new FileInput($this->cache));
    if (gmdate('Y-m-d\TH:i:s\Z') >= $cache['expiresAt']) return $this->credentials= null;

    // Fetch credentials via SSO
    try {
      $res= $this->conn->get(['role_name' => $this->roleName, 'account_id' => $this->accountId], [
        'x-amz-sso_bearer_token' => $cache['accessToken'],
        'Accept'                 => 'application/json',
      ]);
    } catch (Throwable $t) {
      throw new IllegalStateException("SSO credential provider {$conn->getUrl()->getURL()} failed", $t);
    }

    $credentials= Json::read(new StreamInput($res->in()))['roleCredentials'];
    return $this->credentials= new Credentials(
      $credentials['accessKeyId'],
      $credentials['secretAccessKey'],
      $credentials['sessionToken'] ?? null,
      isset($credentials['expiration']) ? (int)($credentials['expiration'] / 1000) : null
    );
  }
}
