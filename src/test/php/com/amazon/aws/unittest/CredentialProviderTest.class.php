<?php namespace com\amazon\aws\unittest;

use com\amazon\aws\credentials\{FromGiven, FromEnvironment, FromConfig, FromEcs, Provider};
use com\amazon\aws\{Credentials, CredentialProvider};
use io\{TempFile, IOException};
use lang\IllegalStateException;
use test\{Assert, Expect, Test, Values};
use util\NoSuchElementException;

class CredentialProviderTest {

  /**
   * Returns ECS credentials response
   *
   * @param  int $expireIn Number of seconds
   * @param  string $token
   * @return string[]
   */
  private function ecsCredentials($expireIn= 3600, $token= '"session"') {
    return [
      'HTTP/1.1 200 OK',
      'Content-Type: application/json',
      '',
      '{
        "AccessKeyId": "key",
        "SecretAccessKey": "secret",
        "Token": '.$token.',
        "Expiration": "'.gmdate('Y-m-d\TH:i:s\Z', time() + $expireIn).'"
      }'
    ];
  }

  #[Test]
  public function given() {
    $credentials= new Credentials('key', 'secret');
    Assert::equals($credentials, (new FromGiven($credentials))->credentials());
  }

  #[Test, Values([[[], null], [['AWS_SESSION_TOKEN' => 'token'], 'token']])]
  public function in_environment_with($session, $token) {
    $env= ['AWS_ACCESS_KEY_ID' => 'key', 'AWS_SECRET_ACCESS_KEY' => 'secret'] + $session;
    with (new Exported($env), function() use($token) {
      $credentials= (new FromEnvironment())->credentials();

      Assert::equals('key', $credentials->accessKey());
      Assert::equals('secret', $credentials->secretKey()->reveal());
      Assert::equals($token, $credentials->sessionToken());
    });
  }

  #[Test]
  public function not_in_environment() {
    with (new Exported(['AWS_ACCESS_KEY_ID' => null]), function() {
      Assert::null((new FromEnvironment())->credentials());
    });
  }

  #[Test, Values([['', null], ['aws_session_token = token', 'token']])]
  public function from_config_with($session, $token) {
    $file= (new TempFile())->containing(
      "[default]\n".
      "aws_access_key_id = key\n".
      "aws_secret_access_key = secret\n".
      "{$session}\n"
    );
    $credentials= (new FromConfig($file))->credentials();

    Assert::equals('key', $credentials->accessKey());
    Assert::equals('secret', $credentials->secretKey()->reveal());
    Assert::equals($token, $credentials->sessionToken());
  }

  #[Test, Values(['default', 'test'])]
  public function from_config_using_profile($profile) {
    $file= (new TempFile())->containing(
      "[default]\n".
      "aws_access_key_id = default\n".
      "aws_secret_access_key = default-secret\n".
      "[profile test]\n".
      "aws_access_key_id = test\n".
      "aws_secret_access_key = test-secret\n"
    );
    $credentials= (new FromConfig($file, $profile))->credentials();

    Assert::equals($profile, $credentials->accessKey());
    Assert::equals($profile.'-secret', $credentials->secretKey()->reveal());
  }

  #[Test]
  public function from_config_uses_shared_credentials_file() {
    $file= (new TempFile())->containing(
      "[default]\n".
      "aws_access_key_id = key\n".
      "aws_secret_access_key = secret\n"
    );
    with (new Exported(['AWS_SHARED_CREDENTIALS_FILE' => $file->getURI()]), function() {
      $credentials= (new FromConfig())->credentials();

      Assert::equals('key', $credentials->accessKey());
      Assert::equals('secret', $credentials->secretKey()->reveal());
    });
  }

  #[Test]
  public function from_config_uses_environment_if_profile_omitted() {
    with (new Exported(['AWS_PROFILE' => 'test']), function() {
      $file= (new TempFile())->containing(
        "[profile test]\n".
        "aws_access_key_id = test\n".
        "aws_secret_access_key = test-secret\n"
      );
      $credentials= (new FromConfig($file))->credentials();

      Assert::equals('test', $credentials->accessKey());
      Assert::equals('test-secret', $credentials->secretKey()->reveal());
    });
  }

  #[Test]
  public function from_config_ignored_environment_if_profile_passed() {
    with (new Exported(['AWS_PROFILE' => 'default']), function() {
      $file= (new TempFile())->containing(
        "[profile test]\n".
        "aws_access_key_id = test\n".
        "aws_secret_access_key = test-secret\n"
      );
      $credentials= (new FromConfig($file, 'test'))->credentials();

      Assert::equals('test', $credentials->accessKey());
      Assert::equals('test-secret', $credentials->secretKey()->reveal());
    });
  }

  #[Test]
  public function from_config_using_non_existant_profile() {
    $file= (new TempFile())->containing(
      "[default]\n".
      "aws_access_key_id = default\n".
      "aws_secret_access_key = default\n"
    );
    Assert::null((new FromConfig($file, 'test'))->credentials());
  }

  #[Test]
  public function from_non_existant_config() {
    Assert::null((new FromConfig('/file-does-not-exist'))->credentials());
  }

  #[Test]
  public function from_config_cached() {
    $file= (new TempFile())->containing(
      "[default]\n".
      "aws_access_key_id = key\n".
      "aws_secret_access_key = secret\n"
    );
    $provider= new FromConfig($file);
    $first= $provider->credentials();
    $second= $provider->credentials();

    Assert::equals($first, $second);
  }

  #[Test]
  public function from_config_cache_checks_for_modifications() {
    $file= (new TempFile())->containing(
      "[default]\n".
      "aws_access_key_id = key\n".
      "aws_secret_access_key = secret\n"
    );
    $provider= new FromConfig($file);
    $first= $provider->credentials();

    $file->containing(
      "[default]\n".
      "aws_access_key_id = modifed\n".
      "aws_secret_access_key = secret\n"
    );
    $second= $provider->credentials();

    Assert::notEquals($first, $second);
  }

  #[Test, Values([['/get-credentials', null], [null, 'http://localhost/get-credentials']])]
  public function ecs_api($relative, $full) {
    $env= [
      'AWS_CONTAINER_CREDENTIALS_RELATIVE_URI' => $relative,
      'AWS_CONTAINER_CREDENTIALS_FULL_URI'     => $full,
    ];
    with (new Exported($env), function() {
      $conn= new TestConnection(['/get-credentials' => $this->ecsCredentials()]);
      $credentials= (new FromEcs($conn))->credentials();

      Assert::equals('key', $credentials->accessKey());
      Assert::equals('secret', $credentials->secretKey()->reveal());
      Assert::equals('session', $credentials->sessionToken());
    });
  }

  #[Test, Values([['null', null], ['"session"', 'session']])]
  public function ecs_api_with($token, $session) {
    $env= ['AWS_CONTAINER_CREDENTIALS_RELATIVE_URI' => '/get-credentials'];
    with (new Exported($env), function() use($token, $session) {
      $conn= new TestConnection(['/get-credentials' => $this->ecsCredentials(0, $token)]);
      $credentials= (new FromEcs($conn))->credentials();

      Assert::equals('key', $credentials->accessKey());
      Assert::equals('secret', $credentials->secretKey()->reveal());
      Assert::equals($session, $credentials->sessionToken());
    });
  }

  #[Test, Values(['Basic Test', "Basic Test\n", "Basic Test\r", "Basic Test\r\n"])]
  public function ecs_api_with_authorization_file($contents) {
    $file= (new TempFile())->containing($contents);
    $env= [
      'AWS_CONTAINER_CREDENTIALS_RELATIVE_URI' => '/get-credentials',
      'AWS_CONTAINER_CREDENTIALS_FULL_URI'     => null,
      'AWS_CONTAINER_AUTHORIZATION_TOKEN_FILE' => $file->getURI(),
      'AWS_CONTAINER_AUTHORIZATION_TOKEN'      => null,
    ];
    with (new Exported($env), function() {
      $conn= new TestConnection([
        '/get-credentials' => function($req) {
          return in_array('Basic Test', $req->headers['Authorization'] ?? [])
            ? $this->ecsCredentials()
            : ['HTTP/1.1 403', '']
          ;
        }
      ]);
      Assert::equals('key', (new FromEcs($conn))->credentials()->accessKey());
    });
  }

  #[Test]
  public function ecs_api_with_authorization() {
    $env= [
      'AWS_CONTAINER_CREDENTIALS_RELATIVE_URI' => '/get-credentials',
      'AWS_CONTAINER_CREDENTIALS_FULL_URI'     => null,
      'AWS_CONTAINER_AUTHORIZATION_TOKEN_FILE' => null,
      'AWS_CONTAINER_AUTHORIZATION_TOKEN'      => 'Basic Test',
    ];
    with (new Exported($env), function() {
      $conn= new TestConnection([
        '/get-credentials' => function($req) {
          return in_array('Basic Test', $req->headers['Authorization'] ?? [])
            ? $this->ecsCredentials()
            : ['HTTP/1.1 403', '']
          ;
        }
      ]);

      Assert::equals('key', (new FromEcs($conn))->credentials()->accessKey());
    });
  }

  #[Test]
  public function ecs_environment_variables_not_present() {
    $env= ['AWS_CONTAINER_CREDENTIALS_RELATIVE_URI' => null, 'AWS_CONTAINER_CREDENTIALS_FULL_URI' => null];
    with (new Exported($env), function() {
      Assert::null((new FromEcs())->credentials());
    });
  }

  #[Test, Expect(class: IllegalStateException::class, message: '/returned unexpected/')]
  public function ecs_api_error_raises_exception() {
    with (new Exported(['AWS_CONTAINER_CREDENTIALS_RELATIVE_URI' => '/get-credentials']), function() {
      $conn= new TestConnection(['/get-credentials' => ['HTTP/1.1 403', '']]);
      (new FromEcs($conn))->credentials();
    });
  }

  #[Test, Expect(class: IllegalStateException::class, message: '/failed/')]
  public function ecs_api_failing_raises_exception() {
    with (new Exported(['AWS_CONTAINER_CREDENTIALS_RELATIVE_URI' => '/get-credentials']), function() {
      $conn= new TestConnection(['/get-credentials' => function($req) {
        throw new IOException('Connection failed');
      }]);
      (new FromEcs($conn))->credentials();
    });
  }

  #[Test]
  public function ecs_api_cached() {
    with (new Exported(['AWS_CONTAINER_CREDENTIALS_RELATIVE_URI' => '/get-credentials']), function() {
      $conn= new TestConnection(['/get-credentials' => function() {
        static $times= 0;
        return 0 === $times++ ? $this->ecsCredentials() : ['HTTP/1.1 500', ''];
      }]);
      $provider= new FromEcs($conn);
      $first= $provider->credentials();
      $second= $provider->credentials();

      Assert::equals($first, $second);
    });
  }

  #[Test]
  public function ecs_api_cache_checks_for_expiration() {
    with (new Exported(['AWS_CONTAINER_CREDENTIALS_RELATIVE_URI' => '/get-credentials']), function() {
      $conn= new TestConnection(['/get-credentials' => function() {
        static $times= 0;
        return 0 === $times++ ? $this->ecsCredentials(-1) : ['HTTP/1.1 500', ''];
      }]);
      $provider= new FromEcs($conn);
      $provider->credentials();

      Assert::throws(IllegalStateException::class, function() use($provider) {
        $provider->credentials();
      });
    });
  }

  #[Test]
  public function chain_returns_null_when_empty() {
    Assert::null((new CredentialProvider())->credentials());
  }

  #[Test]
  public function chain_returns_given() {
    $credentials= new Credentials('key', 'secret');
    Assert::equals($credentials, (new CredentialProvider(new FromGiven($credentials)))->credentials());
  }

  #[Test]
  public function chain_returns_first_non_null() {
    $env= ['AWS_ACCESS_KEY_ID' => null, 'AWS_SECRET_ACCESS_KEY' => null];
    with (new Exported($env), function() {
      $credentials= new Credentials('key', 'secret');
      $chain= new CredentialProvider(new FromEnvironment(), new FromGiven($credentials));
      Assert::equals($credentials, $chain->credentials());
    });
  }

  #[Test]
  public function default_provider_chain() {
    $env= ['AWS_ACCESS_KEY_ID' => 'key', 'AWS_SECRET_ACCESS_KEY' => 'secret'];
    with (new Exported($env), function() {
      Assert::equals('key', CredentialProvider::default()->credentials()->accessKey());
    });
  }

  #[Test, Expect(NoSuchElementException::class)]
  public function default_provider_chain_raises_when_no_credentials_are_provided() {
    $env= [
      'AWS_ACCESS_KEY_ID'                      => null,
      'AWS_SECRET_ACCESS_KEY'                  => null,
      'AWS_SHARED_CREDENTIALS_FILE'            => '/file-does-not-exist',
      'AWS_CONTAINER_CREDENTIALS_RELATIVE_URI' => null,
      'AWS_CONTAINER_CREDENTIALS_FULL_URI'     => null,
      'AWS_CONTAINER_AUTHORIZATION_TOKEN_FILE' => null,
      'AWS_CONTAINER_AUTHORIZATION_TOKEN'      => null,
    ];
    with (new Exported($env), function() {
      CredentialProvider::default()->credentials();
    });
  }
}