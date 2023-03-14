<?php namespace com\amazon\aws\api;

use io\streams\{InputStream, Streams};
use lang\{Value, IllegalStateException};
use text\json\{Json, StreamInput};
use util\{Comparison, Objects};

/** @test com.amazon.aws.unittest.ResponseTest */
class Response implements Value {
  use Comparison;

  private $status, $message, $stream;
  private $headers= [], $lookup= [];

  /** Creates a new response */
  public function __construct(int $status, string $message, array $headers, InputStream $stream) {
    $this->status= $status;
    $this->message= $message;
    $this->stream= $stream;

    foreach ($headers as $name => $value) {
      $lookup= strtolower($name);
      if (isset($this->lookup[$lookup])) {
        $this->headers[$this->lookup[$lookup]][]= $value;
      } else {
        $this->headers[$name]= (array)$value;
        $this->lookup[$lookup]= $name;
      }
    }
  }

  /** @return int */
  public function status() { return $this->status; }

  /** @return string */
  public function message() { return $this->message; }

  /**
   * Gets a header by name. Performs a case-insensitive lookup.
   *
   * @param  string $name
   * @param  var $default
   * @return var
   */
  public function header($name, $default= null) {
    $lookup= strtolower($name);
    return isset($this->lookup[$lookup])
      ? implode(', ', $this->headers[$this->lookup[$lookup]])
      : $default
    ;
  }

  /** @return [:string|string[]] */
  public function headers() {
    $r= [];
    foreach ($this->headers as $name => $header) {
      $r[$name]= 1 === sizeof($header) ? $header[0] : $header;
    }
    return $r;
  }

  /** @return io.streams.InputStream */
  public function stream() { return $this->stream; }

  /** @return string */
  public function content() { return Streams::readAll($this->stream); }

  /**
   * Returns deserialized value, raising an error if the content
   * type is unknown.
   *
   * @return var
   * @throws lang.IllegalStateException
   */
  public function value() {
    switch ($type= ($this->headers['Content-Type'][0] ?? null)) {
      case 'application/json': return Json::read(new StreamInput($this->stream));
      case 'text/plain': return Streams::readAll($this->stream);
    }
    throw new IllegalStateException('Cannot deserialize '.($type ?? 'without content type'));
  }

  /**
   * Returns result, raising an error for non-2XX status codes or
   * if the returned content type is unknown.
   *
   * @return var
   * @throws lang.IllegalStateException
   */
  public function result() {
    if ($this->status >= 200 && $this->status < 300) return $this->value();

    throw new IllegalStateException(sprintf(
      '%d %s does not indicate a successful response',
      $this->status,
      $this->message
    ));
  }

  /**
   * Returns error, raising an error for non-error status codes or
   * if the returned content type is unknown.
   *
   * @return var
   * @throws lang.IllegalStateException
   */
  public function error() {
    if ($this->status >= 400) return $this->value();

    throw new IllegalStateException(sprintf(
      '%d %s does not indicate an error response',
      $this->status,
      $this->message
    ));
  }

  /** @return string */
  public function toString() {
    return nameof($this).'<'.$this->status.' '.$this->message.'>@'.Objects::stringOf($this->headers);
  }
}