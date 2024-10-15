<?php namespace com\amazon\aws;

use lang\Value;

/**
 * S3 Keys help construct paths from components
 *
 * @test  com.amazon.aws.unittest.S3KeyTest
 */
class S3Key implements Value {
  private $path;

  /** Creates a new S3 key from given components */
  public function __construct(string... $components) {
    $this->path= ltrim(implode('/', $components), '/');
  }

  /** Returns the path */
  public function path(string $base= ''): string {
    return rtrim($base, '/').'/'.$this->path;
  }

  /** @return string */
  public function __toString() { return '/'.$this->path; }

  /** @return string */
  public function hashCode() { return 'S3'.md5($this->path); }

  /** @return string */
  public function toString() { return nameof($this).'(/'.$this->path.')'; }

  /**
   * Comparison
   *
   * @param  var $value
   * @return int
   */
  public function compareTo($value) {
    return $value instanceof self ? $this->path <=> $value->path : 1;
  }
}