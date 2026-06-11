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
  private $config, $shared, $profile;
  private $modified= null;
  private $provider= null;

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
    $this->shared= new IniFile($credentials
      ?? Environment::variable('AWS_SHARED_CREDENTIALS_FILE', null)
      ?? new Path($this->homeDir(), '.aws', 'credentials')
    );
    $this->profile= $profile ?? Environment::variable('AWS_PROFILE', 'default');
  }

  /** @return string */
  private function homeDir() {
    return Environment::variable(['HOME', 'USERPROFILE', 'LAMBDA_TASK_ROOT'], DIRECTORY_SEPARATOR);
  }

  /** @return void */
  private function load() {
    $modified= [$this->config->modified(), $this->shared->modified()];
    if ($modified === $this->modified) return;

    // If either of the above change, merge configuration and memoize along with modification date
    $this->modified= $modified;
    $this->provider= null;
    $sections= $this->config->sections();
    foreach ($this->shared->sections() as $profile => $values) {
      if (isset($sections[$profile])) {
        $sections[$profile]+= $values;
      } else {
        $sections[$profile]= $values;
      }
    }

    $section= $sections['default' === $this->profile ? 'default' : "profile {$this->profile}"] ?? [];
    if ($accessKey= $section['aws_access_key_id'] ?? null) {
      $this->provider= new FromGiven(new Credentials(
        $accessKey,
        new Secret($section['aws_secret_access_key']),
        $section['aws_session_token'] ?? null
      ));
    } else if (($session= $section['sso_session'] ?? null) && ($sso= $sections["sso-session {$session}"] ?? null)) {
      $this->provider= new FromSSO(
        $sso['sso_start_url'],
        $sso['sso_region'] ?? $section['region'] ?? Environment::variable('AWS_REGION'),
        $section['sso_account_id'],
        $section['sso_role_name'],
        $session
      );
    } else if ($url= $section['sso_start_url'] ?? null) {
      $this->provider= new FromSSO(
        $url,
        $section['sso_region'] ?? $section['region'] ?? Environment::variable('AWS_REGION'),
        $section['sso_account_id'],
        $section['sso_role_name'],
        null
      );
    }
  }

  /**
   * Returns the SSO provider, supporting config with and without SSO session.
   *
   * @see    https://github.com/xp-forge/aws/issues/14
   * @return ?com.amazon.aws.FromSSO
   */
  public function sso() {
    $this->load();
    return $this->provider instanceof FromSSO ? $this->provider : null;
  }

  /** @return ?com.amazon.aws.Credentials */
  public function credentials() {
    $this->load();
    return $this->provider ? $this->provider->credentials() : null;
  }
}