<?php namespace com\amazon\aws\credentials;

use com\amazon\aws\Credentials;
use io\{File, Path};
use lang\{Environment, IllegalStateException, Throwable};
use peer\http\{HttpConnection, RequestData};
use text\json\{Json, FileInput, FileOutput, StreamInput};

/**
 * SSO credentials
 * 
 * @see   https://docs.aws.amazon.com/general/latest/gr/sso.html
 * @see   https://github.com/xp-forge/aws/issues/15
 * @test  com.amazon.aws.unittest.CredentialProviderTest
 */
class FromSSO extends Provider {
  const JSON= 'application/json';

  public $startUrl, $region, $accountId, $roleName;
  private $cache, $sso, $refresh;
  private $credentials= null;

  /**
   * Creates a new SSO provider
   *
   * @param  string $startUrl
   * @param  string $region
   * @param  string $accountId
   * @param  string $roleName
   * @param  ?string|io.File $cache
   * @param  ?peer.http.HttpConnection $sso
   * @param  ?peer.http.HttpConnection $refresh
   */
  public function __construct($startUrl, $region, $accountId, $roleName, $cache= null, $sso= null, $refresh= null) {
    $this->startUrl= $startUrl;
    $this->region= $region;
    $this->accountId= $accountId;
    $this->roleName= $roleName;
    $this->cache= $cache instanceof File ? $cache : new File(
      new Path(Environment::homeDir(), '.aws', 'sso', 'cache'),
      sha1($cache ?? $startUrl).'.json'
    );

    $this->sso= $sso ?? new HttpConnection("https://portal.sso.{$region}.amazonaws.com/federation/credentials");
    $this->refresh= $refresh ?? new HttpConnection("https://oidc.{$region}.amazonaws.com/token");
  }

  /** @return ?com.amazon.aws.Credentials */
  public function credentials() {
    if (null !== $this->credentials && !$this->credentials->expired()) return $this->credentials;
    if (!$this->cache->exists()) return $this->credentials= null;

    // Read cache, check for its expiration date and whether the access token can be refreshed
    $cache= Json::read(new FileInput($this->cache));
    if (gmdate('Y-m-d\TH:i:s\Z') >= $cache['expiresAt']) {
      if (!isset($cache['refreshToken'])) return $this->credentials= null;

      // Refresh token via OIDC service
      $payload= [
        'clientId'     => $cache['clientId'],
        'clientSecret' => $cache['clientSecret'],
        'refreshToken' => $cache['refreshToken'],
        'grantType'    => 'refresh_token',
      ];
      try {
        $res= $this->refresh->post(new RequestData(Json::of($payload)), ['Content-Type' => self::JSON]);
        $refresh= Json::read(new StreamInput($res->in()));
      } catch (Throwable $t) {
        throw new IllegalStateException("OOIDC refreshing via {$this->refresh->getUrl()->getURL()} failed", $t);
      }

      // Update cache
      $cache['accessToken']= $refresh['accessToken'];
      $cache['refreshToken']= $refresh['refreshToken'];
      $cache['expiresAt']= gmdate('Y-m-d\TH:i:s\Z', time() + $refresh['expiresIn']);
      Json::write($cache, new FileOutput($this->cache));
    }

    // Fetch credentials via SSO
    try {
      $res= $this->sso->get(['role_name' => $this->roleName, 'account_id' => $this->accountId], [
        'x-amz-sso_bearer_token' => $cache['accessToken'],
        'Accept'                 => self::JSON,
      ]);
      $credentials= Json::read(new StreamInput($res->in()))['roleCredentials'];
    } catch (Throwable $t) {
      throw new IllegalStateException("SSO credential provider {$this->sso->getUrl()->getURL()} failed", $t);
    }

    return $this->credentials= new Credentials(
      $credentials['accessKeyId'],
      $credentials['secretAccessKey'],
      $credentials['sessionToken'] ?? null,
      isset($credentials['expiration']) ? (int)($credentials['expiration'] / 1000) : null
    );
  }
}
