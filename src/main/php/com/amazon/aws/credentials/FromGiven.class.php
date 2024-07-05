<?php namespace com\amazon\aws\credentials;

use com\amazon\aws\Credentials;

/** @test com.amazon.aws.unittest.CredentialProviderTest */
class FromGiven extends Provider {
  private $credentials;

  public function __construct(Credentials $credentials) {
    $this->credentials= $credentials;
  }

   /** @return ?com.amazon.aws.Credentials */
  public function credentials() { return $this->credentials; }

}