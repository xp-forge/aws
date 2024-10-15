<?php namespace com\amazon\aws\api;

use com\amazon\aws\{Credentials, S3Key};
use peer\http\HttpRequest;

/**
 * Signing AWS API requests, version 4
 *
 * @test com.amazon.aws.unittest.SignatureV4Test
 * @see  https://docs.aws.amazon.com/general/latest/gr/create-signed-request.html
 */
class SignatureV4 {
  const HASH= 'sha256';
  const ALGO= 'AWS4-HMAC-SHA256';

  const NO_PAYLOAD= 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855'; // hash('sha256', '')
  const UNSIGNED= 'UNSIGNED-PAYLOAD';

  private $credentials;

  /** Creates a new signature */
  public function __construct(Credentials $credentials) {
    $this->credentials= $credentials;
  }

  /** Returns date and time formatted according to spec in UTC */
  public function datetime($time= null): string {
    return gmdate('Ymd\THis\Z', $time ?? time());
  }

  /** Returns credential including scope for a given service, region and time */
  public function credential(string $service, string $region, $time= null): string {
    return sprintf(
      '%s/%s/%s/%s/aws4_request',
      $this->credentials->accessKey(),
      gmdate('Ymd', $time ?? time()),
      $region,
      $service
    );
  }

  /** @return ?string */
  public function securityToken() {
    return $this->credentials->sessionToken();
  }

  /** URI-encode a given path */
  public function encoded(string $path): string {
    return strtr(rawurlencode($path), ['%2F' => '/']);
  }

  /** Returns a signature */
  public function sign(
    string $service,
    string $region,
    string $method,
    string $target,
    array $params,
    string $contentHash,
    array $headers= [],
    $time= null
  ): array {
    $requestDate= $this->datetime($time);

    // Create a canonical request using the URI-encoded version of the path
    if ($params) {
      ksort($params);
      $query= http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    } else {
      $query= '';
    }
    $canonical= "{$method}\n{$this->encoded($target)}\n{$query}\n";

    // Header names must use lowercase characters and must appear in alphabetical order.
    $sorted= [];
    foreach ($headers as $name => $value) {
      $sorted[strtolower($name)]= $value;
    }
    ksort($sorted);
    foreach ($sorted as $name => $value) {
      $canonical.= "{$name}:{$value}\n";
    }
    $headerList= implode(';', array_keys($sorted));
    $canonical.= "\n{$headerList}\n{$contentHash}";

    // Step 2: Create a hash of the canonical request
    $hashed= hash(self::HASH, $canonical);

    // Step 3: Create a string to sign
    $date= substr($requestDate, 0, 8);
    $credentialScope= "{$date}/{$region}/{$service}/aws4_request";
    $toSign= self::ALGO."\n{$requestDate}\n{$credentialScope}\n{$hashed}";

    // Step 4: Calculate the signature
    $dateHash= hash_hmac(self::HASH, $date, 'AWS4'.$this->credentials->secretKey()->reveal(), true);
    $regionHash= hash_hmac(self::HASH, $region, $dateHash, true);
    $serviceHash= hash_hmac(self::HASH, $service, $regionHash, true);
    $signingHash= hash_hmac(self::HASH, 'aws4_request', $serviceHash, true);

    return [
      'credential' => $this->credentials->accessKey().'/'.$credentialScope,
      'headers'    => $headerList,
      'signature'  => hash_hmac(self::HASH, $toSign, $signingHash)
    ];
  }
}