<?php namespace com\amazon\aws\api;

use lang\{ElementNotFoundException, IllegalArgumentException};
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
   * Serialize a given payload
   *
   * @param  var $payload
   * @param  string $type
   * @return string
   * @throws lang.IllegalArgumentException
   */
  public function serialize($payload, $type) {
    switch ($type) {
      case 'application/json': return Json::of($payload);
      case 'application/x-www-form-urlencoded': return http_build_query($payload, '', '&', PHP_QUERY_RFC1738);
      default: throw new IllegalArgumentException('Cannot serialize to '.$type);
    }
  }

  /**
   * Transmits a given payload and returns the response using the
   * given mime type, which defaults to `application/json`.
   *
   * @param  var $payload
   * @param  string $type
   * @return com.amazon.aws.api.Response
   */
  public function transmit($payload, $type= 'application/json') {
    return $this->endpoint->request(
      'POST',
      $this->target,
      ['Content-Type' => $type],
      $this->serialize($this->marshalling->marshal($payload), $type)
    );
  }
}