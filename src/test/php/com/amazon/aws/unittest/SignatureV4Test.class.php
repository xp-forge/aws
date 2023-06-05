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
  public function headers() {
    $signature= new SignatureV4($this->credentials, self::USER_AGENT);
    Assert::equals(
      [
        'Host'             => 'lambda.eu-central-1.amazonaws.com',
        'X-Amz-Date'       => $signature->datetime(self::TEST_TIME),
        'X-Amz-User-Agent' => self::USER_AGENT,
        'Authorization'    => implode(' ', [
          SignatureV4::ALGO,
          'Credential=key/20230314/us-east-1/lambda/aws4_request,',
          'SignedHeaders=host;x-amz-date;x-amz-user-agent,',
          'Signature=6f3c8bf27fe7bb8caf0af2a5e032b806023c8b28683d9946ee9dde9324b7bfe1',
        ]),
      ],
      $signature->headers(
        'lambda',
        'us-east-1',
        'lambda.eu-central-1.amazonaws.com',
        'POST',
        '/2015-03-31/functions/greet/invocations',
        '{"user":"test"}',
        self::TEST_TIME
      )
    );
  }

  #[Test]
  public function headers_with_param() {
    $signature= new SignatureV4($this->credentials, self::USER_AGENT);
    Assert::equals(
      [
        'Host'             => 'lambda.eu-central-1.amazonaws.com',
        'X-Amz-Date'       => $signature->datetime(self::TEST_TIME),
        'X-Amz-User-Agent' => self::USER_AGENT,
        'Authorization'    => implode(' ', [
          SignatureV4::ALGO,
          'Credential=key/20230314/us-east-1/lambda/aws4_request,',
          'SignedHeaders=host;x-amz-date;x-amz-user-agent,',
          'Signature=8c4b20b5ae81f5b25eea692ddba89e6aca52e4b7e533b2bfe199ddf28f1062c4',
        ]),
      ],
      $signature->headers(
        'lambda',
        'us-east-1',
        'lambda.eu-central-1.amazonaws.com',
        'POST',
        '/2015-03-31/functions/greet/invocations?force=true',
        '{"user":"test"}',
        self::TEST_TIME
      )
    );
  }
}