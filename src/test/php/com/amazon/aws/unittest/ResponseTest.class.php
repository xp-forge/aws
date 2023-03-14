<?php namespace com\amazon\aws\unittest;

use com\amazon\aws\api\Response;
use io\streams\MemoryInputStream;
use test\{Assert, Test};

class ResponseTest {

  /** Creates an HTTP response */
  private function response(): Response {
    return new Response(200, 'OK', ['Content-Type' => ['text/plain']], new MemoryInputStream('Test'));
  }

  #[Test]
  public function status() {
    Assert::equals(200, $this->response()->status());
  }

  #[Test]
  public function message() {
    Assert::equals('OK', $this->response()->message());
  }

  #[Test]
  public function headers() {
    Assert::equals(['Content-Type' => 'text/plain'], $this->response()->headers());
  }

  #[Test]
  public function header() {
    Assert::equals('text/plain', $this->response()->header('Content-Type'));
  }
}