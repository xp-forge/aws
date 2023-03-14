<?php namespace com\amazon\aws\unittest;

use com\amazon\aws\Credentials;
use test\{Assert, Test};
use util\Secret;

class CredentialsTest {

  #[Test]
  public function access_key() {
    Assert::equals('key', (new Credentials('key', 'secret'))->accessKey());
  }

  #[Test]
  public function secret_key() {
    $secret= new Secret('secret');
    Assert::equals($secret, (new Credentials('key', $secret))->secretKey());
  }

  #[Test]
  public function without_session_token() {
    Assert::null((new Credentials('key', 'secret'))->sessionToken());
  }

  #[Test]
  public function with_session_token() {
    Assert::equals('session', (new Credentials('key', 'secret', 'session'))->sessionToken());
  }

  #[Test]
  public function compare_to_itself() {
    $credentials= new Credentials('key', 'secret');
    Assert::equals(0, $credentials->compareTo($credentials));
  }

  #[Test]
  public function compare_to_other_credentials() {
    $credentials= new Credentials('key', 'secret');
    Assert::equals(-1, $credentials->compareTo(new Credentials('other', 'secret')));
  }

  #[Test]
  public function compare_to_another_object() {
    $credentials= new Credentials('key', 'secret');
    Assert::equals(1, $credentials->compareTo($this));
  }
}