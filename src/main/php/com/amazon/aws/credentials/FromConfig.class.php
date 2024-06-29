<?php namespace com\amazon\aws\credentials;

use com\amazon\aws\Credentials;
use io\{File, Path};
use lang\Environment;
use util\Secret;

/**
 * Reads credentials from AWS config files
 * 
 * @see   https://docs.aws.amazon.com/sdkref/latest/guide/file-format.html
 * @see   https://docs.aws.amazon.com/sdkref/latest/guide/file-location.html
 * @test  com.amazon.aws.unittest.CredentialProviderTest
 */
class FromConfig implements Provider {
  private $file, $profile;
  private $modified= null;
  private $credentials;

  /**
   * Creates a new configuration source. Checks the `AWS_SHARED_CREDENTIALS_FILE`
   * environment variables and known shared credentials file locations if file is
   * omitted. Checks `AWS_PROFILE` environment variable if profile is omitted, using
   * `default` otherwise.
   * 
   * @param  ?string|io.File $file
   * @param  ?string $profile
   */
  public function __construct($file= null, $profile= null) {
    if (null === $file) {
      $this->file= new File(Environment::variable('AWS_SHARED_CREDENTIALS_FILE', null)
        ?? new Path(Environment::homeDir(), '.aws', 'credentials')
      );
    } else if ($file instanceof File) {
      $this->file= $file;
    } else {
      $this->file= new File($file);
    }

    $this->profile= $profile ?? Environment::variable('AWS_PROFILE', 'default');
  }

  /** @return ?com.amazon.aws.Credentials */
  public function credentials() {
    if (!$this->file->exists()) return $this->credentials= null;

    // Only read the underlying file if its modification time has changed
    $modified= $this->file->lastModified();
    if ($modified >= $this->modified) {
      $this->modified= $modified;

      // Either check "profile [...]" or the default section
      $config= parse_ini_file($this->file->getURI(), true, INI_SCANNER_RAW);
      $section= $config['default' === $this->profile ? 'default' : "profile {$this->profile}"] ?? null;

      $this->credentials= null === $section ? null : new Credentials(
        $section['aws_access_key_id'],
        new Secret($section['aws_secret_access_key']),
        $section['aws_session_token'] ?? null
      );
    }
    return $this->credentials;
  }
}