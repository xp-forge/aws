<?php namespace com\amazon\aws\unittest;

use com\amazon\aws\api\Resource;
use com\amazon\aws\{ServiceEndpoint, Credentials};
use test\{Assert, Test};

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
      ]
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
      '/$default' => [
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
}