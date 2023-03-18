<?php namespace com\amazon\aws\unittest;

use com\amazon\aws\api\Response;
use io\streams\MemoryInputStream;
use lang\{IllegalStateException, XPClass};
use test\{Assert, Expect, Test, Values};
use util\Date;

class ResponseTest {
  const STATUS= [200 => 'OK', 204 => 'No content', 404 => 'Not found'];

  /** Creates an HTTP response */
  private function response(int $status= 200, string $content= '', string $type= null): Response {
    return new Response(
      $status,
      self::STATUS[$status],
      $type ? ['Content-Type' => [$type]] : [],
      new MemoryInputStream($content)
    );
  }

  #[Test, Values([200, 404])]
  public function status($status) {
    Assert::equals($status, $this->response($status)->status());
  }

  #[Test, Values([200, 404])]
  public function message($status) {
    Assert::equals(self::STATUS[$status], $this->response($status)->message());
  }

  #[Test]
  public function headers() {
    Assert::equals(['Content-Type' => 'text/plain'], $this->response(200, 'Test', 'text/plain')->headers());
  }

  #[Test]
  public function header() {
    Assert::equals('text/plain', $this->response(200, 'Test', 'text/plain')->header('Content-Type'));
  }

  #[Test]
  public function read_from_stream() {
    Assert::equals('Test', $this->response(200, 'Test', 'text/plain')->stream()->read());
  }

  #[Test]
  public function read_content_into_string() {
    Assert::equals('Test', $this->response(200, 'Test', 'text/plain')->content());
  }

  #[Test]
  public function access_text_result() {
    Assert::equals('Test', $this->response(200, 'Test', 'text/plain')->result());
  }

  #[Test]
  public function access_json_result() {
    Assert::equals(['ok' => true], $this->response(200, '{"ok":true}', 'application/json')->result());
  }

  #[Test, Expect(class: IllegalStateException::class, message: '404 Not found does not indicate a successful response')]
  public function cannot_access_error_as_result() {
    $this->response(404, 'File not found', 'text/plain')->result();
  }

  #[Test]
  public function access_error() {
    Assert::equals('File not found', $this->response(404, 'File not found', 'text/plain')->error());
  }

  #[Test, Expect(class: IllegalStateException::class, message: '200 OK does not indicate an error response')]
  public function cannot_access_result_as_error() {
    $this->response(200, 'Test', 'text/plain')->error();
  }

  #[Test, Expect(class: IllegalStateException::class, message: 'Cannot deserialize without content type')]
  public function cannot_deserialize_result_without_content_type() {
    $this->response(204)->result();
  }

  #[Test, Values(eval: '[Result::class, new XPClass(Result::class)]')]
  public function cast_result($type) {
    $payload= '{"success":true,"date":"2023-03-18T14:49:47+0100"}';
    Assert::equals(
      new Result(true, new Date('2023-03-18T14:49:47+0100')),
      $this->response(200, $payload, 'application/json')->result($type)
    );
  }

  #[Test, Values(eval: '[Error::class, new XPClass(Error::class)]')]
  public function cast_error($type) {
    $payload= '{"kind":"IO_0002","message":"Not found"}';
    Assert::equals(
      new Error('IO_0002', 'Not found'),
      $this->response(404, $payload, 'application/json')->error($type)
    );
  }
}