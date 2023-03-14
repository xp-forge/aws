<?php namespace com\amazon\aws\unittest;

use com\amazon\aws\api\Resource;
use com\amazon\aws\{ServiceEndpoint, Credentials};
use test\{Assert, Test};

class RequestTest {

  /** Returns a testing endpoint */
  private function endpoint(array $responses): ServiceEndpoint {
    return (new ServiceEndpoint('lambda', new Credentials('key', 'secret')))
      ->connecting(function($uri) use($responses) { return new TestConnection($responses); })
    ;
  }

  #[Test]
  public function invoke_lambda() {
    $endpoint= $this->endpoint([
      '/2015-03-31/functions/test/invocations' => [
        'HTTP/1.1 200 OK',
        'Content-Type: text/plain',
        '',
        'Testing local'
      ]
    ]);
    $response= $endpoint
      ->resource('/2015-03-31/functions/{name}/invocations', ['name' => 'test'])
      ->transmit(['source' => 'local'])
    ;

    Assert::equals(200, $response->status());
    Assert::equals('OK', $response->message());
    Assert::equals(['Content-Type' => ['text/plain']], $response->headers());
    Assert::equals('Testing local', $response->content());
  }
}