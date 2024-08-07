<?php namespace com\amazon\aws;

use lang\Value;
use util\Secret;

/** @test com.amazon.aws.unittest.CredentialsTest */
class Credentials implements Value {
  private $accessKey, $secretKey, $sessionToken, $expiration;

  /**
   * Creates a new instance
   *
   * @param  string $accessKey
   * @param  string|util.Secret $secretKey
   * @param  ?string $sessionToken
   * @param  ?int|string $expiration
   */
  public function __construct($accessKey, $secretKey, $sessionToken= null, $expiration= null) {
    $this->accessKey= $accessKey;
    $this->secretKey= $secretKey instanceof Secret ? $secretKey : new Secret($secretKey);
    $this->sessionToken= $sessionToken;
    $this->expiration= null === $expiration || is_int($expiration) ? $expiration : strtotime($expiration);
  }

  /** @return string */
  public function accessKey() { return $this->accessKey; }

  /** @return util.Secret */
  public function secretKey() { return $this->secretKey; }

  /** @return ?string */
  public function sessionToken() { return $this->sessionToken; }

  /** @return ?int */
  public function expiration() { return $this->expiration; }

  /** @return string */
  public function hashCode() {
    return 'C'.sha1($this->accessKey.$this->secretKey->reveal().$this->sessionToken);
  }

  /**
   * Check whether these credentials have expired
   *
   * @return bool
   */
  public function expired() {
    return null !== $this->expiration && $this->expiration <= time();
  }

  /** @return string */
  public function toString() {
    return sprintf(
      '%s(accessKey: %s, secretKey: %s%s%s)',
      nameof($this),
      $this->accessKey,
      str_repeat('*', strlen($this->secretKey->reveal())),
      null === $this->sessionToken ? '' : ', sessionToken: '.$this->sessionToken,
      null === $this->expiration ? '' : ', expiration: '.date('Y-m-d H:i:s', $this->expiration)
    );
  }

  /**
   * Comparison
   *
   * @param  var $value
   * @return int
   */
  public function compareTo($value) {
    if (!($value instanceof self)) return 1;

    $r= $this->accessKey <=> $value->accessKey;
    if (0 !== $r) return $r;

    $r= $this->sessionToken <=> $value->sessionToken;
    if (0 !== $r) return $r;

    return $this->secretKey->equals($value->secretKey) ? 0 : 1;
  }
}