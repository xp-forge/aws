<?php namespace com\amazon\aws;

use com\amazon\aws\credentials\{FromEnvironment, FromConfig, FromEcs, Provider};
use util\NoSuchElementException;

/** @test com.amazon.aws.unittest.CredentialProviderTest */
final class CredentialProvider implements Provider {
  private static $raise;
  private $delegates;

  static function __static() {
    self::$raise= new class() implements Provider {
      public function credentials() {
        throw new NoSuchElementException('None of the credential providers returned credentials');
      }
    };
  }

  /** Creates a new provider which queries all the given delegates */
  public function __construct(Provider... $delegates) {
    $this->delegates= $delegates;
  }

  /** @return ?com.amazon.aws.Credentials */
  public function credentials() {
    foreach ($this->delegates as $delegate) {
      if (null !== ($credentials= $delegate->credentials())) return $credentials;
    }
    return null;
  }

  /**
   * Returns default credential provider chain, checking, in the following order:
   * 
   * 1. Environment variables
   * 2. Shared credentials and config files
   * 3. Amazon ECS container credentials
   * 
   * If none of the above provide credentials, an exception is raised when invoking
   * the `credentials()` method.
   *
   * @see    https://docs.aws.amazon.com/sdk-for-kotlin/latest/developer-guide/credential-providers.html
   * @return com.amazon.aws.credentials.Provider
   */
  public static function default() {
    return new self(
      new FromEnvironment(),
      new FromConfig(),
      new FromEcs(),
      self::$raise
    );
  }
}