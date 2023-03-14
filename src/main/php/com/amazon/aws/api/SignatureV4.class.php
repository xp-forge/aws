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
    $requestDate= gmdate('Ymd\THis\Z', $time ?? time());
    $contentHash= hash(self::HASH, $payload);
    $headers= [
      'Host'             => $host,
      'X-Amz-Date'       => $requestDate,
      'X-Amz-User-Agent' => $this->userAgent,
    ];

    if (null !== ($session= $this->credentials->sessionToken())) {
      $headers['X-Amz-Security-Token']= $session;
    }

    // Step 1: Create a canonical request using the URI-encoded version of the path
    $path= strtr(rawurlencode($target), ['%2F' => '/']);
    $query= '';
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

    return $headers + ['Authorization' => sprintf(
      '%s Credential=%s/%s, SignedHeaders=%s, Signature=%s',
      self::ALGO,
      $this->credentials->accessKey(),
      $credentialScope,
      $headerList,
      hash_hmac(self::HASH, $toSign, $signingHash),
    )];
  }
}