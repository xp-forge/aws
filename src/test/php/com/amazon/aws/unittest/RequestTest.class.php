<?php namespace com\amazon\aws\unittest;

use com\amazon\aws\api\Resource;
use com\amazon\aws\{ServiceEndpoint, Credentials, S3Key};
use test\{Assert, Test};
use util\Date;

class RequestTest {

  /** Returns a testing endpoint */
  private function endpoint(string $service, array $responses): ServiceEndpoint {
    return (new ServiceEndpoint($service, new Credentials('key', 'secret')))
      ->connecting(function($uri) use($responses) { return new TestConnection($responses); })
    ;
  }

  /** Returns a testing endpoint with a lambda invocation result */
  private function lambda(): ServiceEndpoint {
    return $this->endpoint('lambda', [
      '/2015-03-31/functions/test/invocations' => [
        'HTTP/1.1 200 OK',
        'Content-Type: text/plain',
        '',
        'Testing local'
      ],
    ]);
  }

  #[Test]
  public function invoke_lambda() {
    $response= $this->lambda()
      ->in('eu-central-1')
      ->resource('/2015-03-31/functions/{name}/invocations', ['name' => 'test'])
      ->transmit(['source' => 'local'])
    ;

    Assert::equals(200, $response->status());
    Assert::equals('OK', $response->message());
    Assert::equals(['Content-Type' => 'text/plain'], $response->headers());
    Assert::equals('Testing local', $response->content());
  }

  #[Test]
  public function invoke_lambda_with_version() {
    $response= $this->lambda()
      ->in('eu-central-1')
      ->version('2015-03-31')
      ->resource('/functions/{name}/invocations', ['name' => 'test'])
      ->transmit(['source' => 'local'])
    ;

    Assert::equals(200, $response->status());
    Assert::equals('OK', $response->message());
    Assert::equals(['Content-Type' => 'text/plain'], $response->headers());
    Assert::equals('Testing local', $response->content());
  }

  #[Test]
  public function not_found() {
    $response= $this->endpoint('testing', [])->resource('/gone')->transmit([]);

    Assert::equals(404, $response->status());
    Assert::equals('Not found', $response->message());
    Assert::equals(['Content-Type' => 'text/plain', 'Content-Length' => '20'], $response->headers());
    Assert::equals('File /gone not found', $response->content());
  }

  #[Test]
  public function text_value() {
    Assert::equals('Testing local', $this->lambda()
      ->resource('/2015-03-31/functions/{name}/invocations', ['name' => 'test'])
      ->transmit(['source' => 'local'])
      ->value()
    );
  }

  #[Test]
  public function json_value() {
    $endpoint= $this->endpoint('apigateway', [
      '/%24default' => [
        'HTTP/1.1 200 OK',
        'Content-Type: application/json',
        '',
        '{"statusCode":200}'
      ]
    ]);

    Assert::equals(['statusCode' => 200], $endpoint
      ->resource('/{stage}', ['stage' => '$default'])
      ->transmit(['requestContext' => ['http' => [/* shortened for brevity */]]])
      ->value()
    );
  }

  #[Test]
  public function error_value() {
    Assert::equals('File /gone not found', $this->endpoint('testing', [])
      ->resource('/gone')
      ->transmit([])
      ->value()
    );
  }

  #[Test]
  public function marshals_json_value() {
    $endpoint= $this->endpoint('queue', [
      '/messages' => [
        'HTTP/1.1 202 Accepted',
        '',
      ]
    ]);

    Assert::equals(202, $endpoint
      ->resource('/messages')
      ->transmit(['id' => 6100, 'created' => Date::now()])
      ->status()
    );
  }

  #[Test]
  public function transfer() {
    $file= 'PNG...';
    $s3= $this->endpoint('s3', [
      '/target/upload.png' => [
        'HTTP/1.1 200 OK',
        'Content-Length: 0',
        '',
      ]
    ]);

    $transfer= $s3->in('eu-central-1')->using('bucket')->open('PUT', '/target/upload.png', [
      'x-amz-content-sha256' => hash('sha256', $file),
      'Content-Type'         => 'image/png',
      'Content-Length'       => strlen($file),
    ]);
    $transfer->write($file);
    $response= $transfer->finish();

    Assert::equals(200, $response->status());
    Assert::equals('OK', $response->message());
    Assert::equals(['Content-Length' => '0'], $response->headers());
    Assert::equals('', $response->content());
  }

  #[Test]
  public function transfer_via_s3key_resource() {
    $file= 'PNG...';
    $s3= $this->endpoint('s3', [
      '/target/upload%20file.png' => [
        'HTTP/1.1 200 OK',
        'Content-Length: 0',
        '',
      ]
    ]);

    $transfer= $s3->resource(new S3Key('target', 'upload file.png'))->open('PUT', [
      'x-amz-content-sha256' => hash('sha256', $file),
      'Content-Type'         => 'image/png',
      'Content-Length'       => strlen($file),
    ]);
    $transfer->write($file);
    $response= $transfer->finish();

    Assert::equals(200, $response->status());
    Assert::equals('OK', $response->message());
    Assert::equals(['Content-Length' => '0'], $response->headers());
    Assert::equals('', $response->content());
  }
}