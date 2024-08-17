<?php namespace com\amazon\aws\api;

class Event {
  public $headers, $value;

  /**
   * Creates a new event
   *
   * @param  [:var] $headers
   * @param  var $value
   */
  public function __construct($headers, $value) {
    $this->headers= $headers;
    $this->value= $value;
  }
}