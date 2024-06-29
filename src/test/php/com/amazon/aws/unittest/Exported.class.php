<?php namespace com\amazon\aws\unittest;

use lang\{Closeable, Environment};

class Exported implements Closeable {
  private $restore= [];

  /** @param [:string] $variables */
  public function __construct($variables) {
    foreach ($variables as $name => $value) {
      $this->restore[$name]= Environment::variable($name, null);
    }
    Environment::export($variables);
  }

  /** @return void */
  public function close() {
    Environment::export($this->restore);
    $this->restore= [];
  }
}