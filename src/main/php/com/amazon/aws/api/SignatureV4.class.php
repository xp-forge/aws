<?php namespace com\amazon\aws\api;

use com\amazon\aws\Credentials;
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

  private $credentials, $userAgent;

  /** Creates a new signature */
  public function __construct(Credentials $credentials, string $userAgent) {
    $this->credentials= $credentials;
    $this->userAgent= $userAgent;
  }

  /** Returns date and time formatted according to spec in UTC */
  public function datetime(int $time= null): string {
    return gmdate('Ymd\THis\Z', $time ?? time());
  }

  /** Returns credential including scope for a given service, region and time */
  public function credential(string $service, string $region, int $time= null): string {
    return sprintf(
      '%s/%s/%s/%s/aws4_request',
      $this->credentials->accessKey(),
      gmdate('Ymd', $time ?? time()),
      $region,
      $service
    );
  }

  /** Returns a signature */
  public function sign(
    string $service,
    string $region,
    string $method,
    string $target,
    string $contentHash,
    array $headers= [],
    int $time= null
  ): array {
    $requestDate= $this->datetime($time);

    // Step 1: Create a canonical request using the URI-encoded version of the path
    if (false === ($p= strpos($target, '?'))) {
      $query= '';
    } else {
      $query= substr($target, $p + 1);
      $target= substr($target, 0, $p);
    }
    $path= strtr(rawurlencode($target), ['%2F' => '/']);
    $canonical= "{$method}\n{$path}\n{$query}\n";

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

  /** Returns signature headers */
  public function headers(
    string $service,
    string $region,
    string $host,
    string $method,
    string $target,
    string $payload,
    int $time= null
  ): array {

    // Compile headers from given host and time including our user agent
    $headers= [
      'Host'             => $host,
      'X-Amz-Date'       => $this->datetime($time),
      'X-Amz-User-Agent' => $this->userAgent,
    ];

    // Automatically include session token if available
    if (null !== ($session= $this->credentials->sessionToken())) {
      $headers['X-Amz-Security-Token']= $session;
    }

    // Calculate signature, then return headers including authorization
    $signature= $this->sign($service, $region, $method, $target, hash(self::HASH, $payload), $headers, $time);
    return $headers + ['Authorization' => sprintf(
      '%s Credential=%s, SignedHeaders=%s, Signature=%s',
      self::ALGO,
      $signature['credential'],
      $signature['headers'],
      $signature['signature']
    )];
  }
}