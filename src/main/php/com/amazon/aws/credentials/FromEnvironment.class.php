<?php namespace com\amazon\aws\credentials;

use com\amazon\aws\Credentials;
use lang\Environment;
use util\Secret;

/**
 * Loads credentials from the `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`,
 * and (if present) `AWS_SESSION_TOKEN` environment variables
 *
 * @see   https://docs.aws.amazon.com/sdkref/latest/guide/environment-variables.html
 * @test  com.amazon.aws.unittest.CredentialProviderTest
 */
class FromEnvironment implements Provider {

  /** @return ?com.amazon.aws.Credentials */
  public function credentials() {
    if (null === ($accessKey= Environment::variable('AWS_ACCESS_KEY_ID', null))) return null;

    return new Credentials(
      $accessKey,
      new Secret(Environment::variable('AWS_SECRET_ACCESS_KEY')),
      Environment::variable('AWS_SESSION_TOKEN', null)
    );
  }
}