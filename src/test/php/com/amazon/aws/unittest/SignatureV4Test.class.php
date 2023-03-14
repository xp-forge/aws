<?php namespace com\amazon\aws\unittest;

use com\amazon\aws\Credentials;
use com\amazon\aws\api\SignatureV4;
use test\{Assert, Before, Test};
use util\Secret;

class SignatureV4Test {
  const USER_AGENT= 'xp-aws/1.0.0 OS/Test/1.0 lang/php/8.2.0';

  #[Before]
  public function credentials() {
    $this->credentials= new Credentials('key', 'secret');
  }

  #[Test]
  public function can_create() {
    new SignatureV4($this->credentials, self::USER_AGENT);
  }

  #[Test]
  public function headers() {
    $signature= new SignatureV4($this->credentials, self::USER_AGENT);
    $date= '20230314T231444Z';
    Assert::equals(
      [
        'Host'             => 'lambda.eu-central-1.amazonaws.com',
        'X-Amz-Date'       => $date,
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
        strtotime($date)
      )
    );
  }
}