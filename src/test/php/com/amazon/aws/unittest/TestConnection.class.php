<?php namespace com\amazon\aws\unittest;

use Closure;
use io\streams\MemoryInputStream;
use peer\http\{HttpConnection, HttpOutputStream, HttpRequest, HttpResponse};

class TestConnection extends HttpConnection {
  private $responses;

  public function __construct($responses) {
    $this->responses= $responses;
    parent::__construct('http://localhost');
  }

  private function error($target) {
    $message= "File {$target} not found";
    return [
      'HTTP/1.1 404 Not found',
      'Content-Type: text/plain',
      'Content-Length: '.strlen($message),
      '',
      $message
    ];
  }

  public function send(HttpRequest $request) {
    $target= rawurldecode($request->target());
    $response= $this->responses[$target] ?? $this->error($target);

    return new HttpResponse(new MemoryInputStream(implode(
      "\r\n",
      $response instanceof Closure ? $response($request) : $response
    )));
  }

  public function open(HttpRequest $request) { return new TestOutputStream($request); }

  public function finish(HttpOutputStream $stream) { return $this->send($stream->request); }
}