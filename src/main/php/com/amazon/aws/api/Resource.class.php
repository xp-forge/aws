<?php namespace com\amazon\aws\api;

use lang\ElementNotFoundException;
use text\json\Json;
use util\data\Marshalling;

/** @test com.amazon.aws.unittest.ResourceTest */
class Resource {
  private $endpoint, $marshalling;
  public $target= '';

  /**
   * Creates a new resource on a given endpoint
   *
   * @param  com.amazon.aws.ServiceEndpoint $endpoint
   * @param  string $path
   * @param  string[]|[:string] $segments
   * @param  ?util.data.Marshalling $marshalling
   * @throws lang.ElementNotFoundException
   */
  public function __construct($endpoint, $path, $segments= [], $marshalling= null) {
    $this->endpoint= $endpoint;
    $this->marshalling= $marshalling ?? new Marshalling();

    $l= strlen($path);
    $offset= 0;
    do {
      $b= strcspn($path, '{', $offset);
      $this->target.= substr($path, $offset, $b);
      $offset+= $b;
      if ($offset >= $l) break;

      $e= strcspn($path, '}', $offset);
      $name= substr($path, $offset + 1, $e - 1);
      if (!isset($segments[$name])) {
        throw new ElementNotFoundException('No such segment "'.$name.'"');
      }

      $segment= $segments[$name];
      $this->target.= rawurlencode($segment);
      $offset+= $e + 1;
    } while ($offset < $l);
  }

  /**
   * Transmits a given payload and returns the response
   *
   * @param  var $payload
   * @return com.amazon.aws.api.Response
   */
  public function transmit($payload) {
    return $this->endpoint->request(
      'POST',
      $this->target,
      ['Content-Type' => 'application/json'],
      Json::of($this->marshalling->marshal($payload))
    );
  }
}