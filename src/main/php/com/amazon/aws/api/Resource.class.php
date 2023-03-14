<?php namespace com\amazon\aws\api;

use lang\ElementNotFoundException;
use text\json\Json;

class Resource {
  private $endpoint, $target;

  public function __construct($endpoint, $path, $segments) {
    $this->endpoint= $endpoint;
    $this->target= $this->resolve($path, $segments);
  }

  /**
   * Resolves segments in resource
   *
   * @param  string $resource
   * @param  [:string] $segments
   * @return string
   */
  private function resolve($resource, $segments) {
    $l= strlen($resource);
    $target= '';
    $offset= 0;
    do {
      $b= strcspn($resource, '{', $offset);
      $target.= substr($resource, $offset, $b);
      $offset+= $b;
      if ($offset >= $l) break;

      $e= strcspn($resource, '}', $offset);
      $name= substr($resource, $offset + 1, $e - 1);
      if (!isset($segments[$name])) {
        throw new ElementNotFoundException('No such segment "'.$name.'"');
      }

      $segment= $segments[$name];
      $target.= rawurlencode($segment);
      $offset+= $e + 1;
    } while ($offset < $l);

    return $target;
  }

  public function transmit($payload) {
    return $this->endpoint->request(
      'POST',
      $this->target,
      ['Content-Type' => 'application/json'],
      Json::of($payload)
    );
  }
}