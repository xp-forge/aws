<?php namespace com\amazon\aws;

use com\amazon\aws\credentials\{FromEnvironment, FromConfig, FromEcs, Provider};
use util\NoSuchElementException;

/** @test com.amazon.aws.unittest.CredentialProviderTest */
final class CredentialProvider extends Provider {
  private $delegates;

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

  /** @return self */
  public function orThrow() {
    $throwing= self::throwing();
    if (in_array($throwing, $this->delegates)) return $this;

    $clone= clone $this;
    $clone->delegates[]= $throwing;
    return $clone;
  }

  /** Returns a credential provider which throws an exception */
  public static function throwing(): Provider {
    static $throwing= null;

    return $throwing??= new class() extends Provider {
      public function credentials() {
        throw new NoSuchElementException('None of the credential providers returned credentials');
      }
    };
  }

  /** Returns a credential provider which never provides any credentials */
  public static function none(): Provider {
    static $none= null;

    return $none??= new class() extends Provider {
      public function credentials() {
        return null;
      }
    };
  }

  /**
   * Returns default credential provider chain, checking, in the following order:
   *
   * 1. Environment variables
   * 2. Shared credentials and config files
   * 3. Amazon ECS container credentials
   *
   * If none of the above provide credentials, an exception is thrown when invoking
   * the `credentials()` method.
   *
   * @see    https://docs.aws.amazon.com/sdk-for-kotlin/latest/developer-guide/credential-providers.html
   */
  public static function default(): Provider {
    return new self(
      new FromEnvironment(),
      new FromConfig(),
      new FromEcs(),
      self::throwing()
    );
  }
}