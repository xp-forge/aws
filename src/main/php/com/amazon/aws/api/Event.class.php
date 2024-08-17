<?php namespace com\amazon\aws\api;

use lang\{IllegalStateException, Value};
use util\{Comparison, Objects};
use text\json\{Json, StringInput};

/** @test com.amazon.aws.unittest.EventTest */
class Event implements Value {
  use Comparison;

  private $source, $headers, $content;

  /**
   * Creates a new event
   *
   * @param  com.amazon.aws.api.EventStream $source
   * @param  [:var] $headers
   * @param  string $content
   */
  public function __construct(EventStream $source, $headers, $content= '') {
    $this->source= $source;
    $this->headers= $headers;
    $this->content= $content;
  }

  /** @return [:var] */
  public function headers() { return $this->headers; }

  /** @return string */
  public function content() { return $this->content; }

  /**
   * Gets a header by name
   *
   * @param  string $name
   * @param  var $default
   * @return var
   */
  public function header($name, $default= null) {
    return $this->headers[$name] ?? $default;
  }

  /**
   * Returns deserialized value, raising an error if the content
   * type is unknown.
   *
   * @param  ?string|lang.Type $type
   * @return var
   * @throws lang.IllegalStateException
   */
  public function value($type= null) {
    switch ($mime= ($this->headers[':content-type'] ?? null)) {
      case 'application/json': $value= Json::read(new StringInput($this->content)); break;
      default: throw new IllegalStateException('Cannot deserialize '.($mime ?? 'without content type'));
    }

    return null === $type || null === $this->source->marshalling
      ? $value
      : $this->source->marshalling->unmarshal($value, $type)
    ;
  }

  /** @return string */
  public function toString() {
    return (
      nameof($this)." {\n".
      '  [headers] '.Objects::stringOf($this->headers, '  ')."\n".
      '  [content] '.$this->content."\n".
      '}'
    );
  }
}