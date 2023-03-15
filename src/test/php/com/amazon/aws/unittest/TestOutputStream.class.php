<?php namespace com\amazon\aws\unittest;

use peer\http\HttpOutputStream;

class TestOutputStream extends HttpOutputStream {
  public $request;
  public $bytes= '';

  /** @param peer.http.HttpRequest $header */
  public function __construct($request) { $this->request= $request; }

  /** @param string $bytes */
  public function write($bytes) { $this->bytes.= $bytes; }

}