<?php namespace com\amazon\aws\unittest;

class Error {
  private $kind, $message;

  public function __construct(string $kind, string $message) {
    $this->kind= $kind;
    $this->message= $message;
  }
}