<?php namespace com\amazon\aws\unittest;

use com\amazon\aws\Credentials;
use com\amazon\aws\api\SignatureV4;
use test\{Assert, Before, Test};
use util\Secret;

class SignatureV4Test {
  const USER_AGENT= 'xp-aws/1.0.0 OS/Test/1.0 lang/php/8.2.0';
  const TEST_TIME= 1678835684;

  #[Before]
  public function credentials() {
    $this->credentials= new Credentials('key', 'secret');
  }

  #[Test]
  public function can_create() {
    new SignatureV4($this->credentials, self::USER_AGENT);
  }

  #[Test]
  public function datetime() {
    $signature= new SignatureV4($this->credentials, self::USER_AGENT);
    Assert::equals(
      '20230314T231444Z',
      $signature->datetime(self::TEST_TIME)
    );
  }

  #[Test]
  public function credential() {
    $signature= new SignatureV4($this->credentials, self::USER_AGENT);
    Assert::equals(
      'key/20230314/us-east-1/s3/aws4_request',
      $signature->credential('s3', 'us-east-1', self::TEST_TIME)
    );
  }

  #[Test]
  public function sign() {
    $signature= new SignatureV4($this->credentials, self::USER_AGENT);
    Assert::equals(
      [
        'credential' => 'key/20230314/us-east-1/s3/aws4_request',
        'headers'    => '',
        'signature'  => '8e14a79dda030f5060e32c9c5839394ded4a1a12019794ea67d5047b933115a9',
      ],
      $signature->sign(
        's3',
        'us-east-1',
        'GET',
        '/folder/resource.txt',
        [],
        hash(SignatureV4::HASH, '{"user":"test"}'),
        [],
        self::TEST_TIME
      )
    );
  }

  #[Test]
  public function sign_with_param() {
    $signature= new SignatureV4($this->credentials, self::USER_AGENT);
    Assert::equals(
      [
        'credential' => 'key/20230314/us-east-1/s3/aws4_request',
        'headers'    => '',
        'signature'  => '6f52c8b9b8f95876b7949ab97d5226b553c7363f734529a0ce02ff0eb254e246',
      ],
      $signature->sign(
        's3',
        'us-east-1',
        'GET',
        '/folder/resource.txt',
        ['force' => 'true'],
        'UNSIGNED-PAYLOAD',
        [],
        self::TEST_TIME
      )
    );
  }
}