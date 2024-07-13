<?php namespace com\amazon\aws\credentials;

use com\amazon\aws\Credentials;
use io\Path;
use lang\Environment;
use util\Secret;

/**
 * Reads credentials from AWS config files
 * 
 * @see   https://docs.aws.amazon.com/sdkref/latest/guide/file-format.html
 * @see   https://docs.aws.amazon.com/sdkref/latest/guide/file-location.html
 * @test  com.amazon.aws.unittest.CredentialProviderTest
 */
class FromConfig extends Provider {
  private $config, $credentials, $profile;
  private $modified= null;
  private $sections= null;

  /**
   * Creates a new configuration source. Checks the `AWS_SHARED_CREDENTIALS_FILE`
   * environment variables and known shared credentials file locations if file is
   * omitted. Checks `AWS_PROFILE` environment variable if profile is omitted, using
   * `default` otherwise.
   * 
   * @param  ?string|io.File $config
   * @param  ?string|io.File $credentials
   * @param  ?string $profile
   */
  public function __construct($config= null, $credentials= null, $profile= null) {
    $this->config= new IniFile($config
      ?? Environment::variable('AWS_CONFIG_FILE', null)
      ?? new Path($this->homeDir(), '.aws', 'config')
    );
    $this->credentials= new IniFile($credentials
      ?? Environment::variable('AWS_SHARED_CREDENTIALS_FILE', null)
      ?? new Path($this->homeDir(), '.aws', 'credentials')
    );
    $this->profile= $profile ?? Environment::variable('AWS_PROFILE', 'default');
  }

  /** @return string */
  private function homeDir() {
    return Environment::variable(['HOME', 'USERPROFILE', 'LAMBDA_TASK_ROOT'], DIRECTORY_SEPARATOR);
  }

  /** @return ?com.amazon.aws.Credentials */
  public function credentials() {
    $modified= max($this->config->modified(), $this->credentials->modified());

    // Edge case: Neither file exists
    if (null === $modified) return null;

    // Merge configuration and memoize along with modification date
    if ($modified >= $this->modified) {
      $this->modified= $modified;
      $this->sections= $this->config->sections();
      foreach ($this->credentials->sections() as $profile => $values) {
        if (isset($config[$profile])) {
          $this->sections[$profile]+= $values;
        } else {
          $this->sections[$profile]= $values;
        }
      }
    }

    $section= $this->sections['default' === $this->profile ? 'default' : "profile {$this->profile}"] ?? null;
    if ($accessKey= $section['aws_access_key_id'] ?? null) {
      return new Credentials(
        $accessKey,
        new Secret($section['aws_secret_access_key']),
        $section['aws_session_token'] ?? null
      );
    }

    if ($sso= $section['sso_start_url'] ?? null) {
      $provider= new FromSSO(
        $sso,
        $section['sso_region'] ?? $section['region'] ?? Environment::variable('AWS_REGION'),
        $section['sso_account_id'],
        $section['sso_role_name']
      );
      return $provider->credentials();
    }

    return null;
  }
}