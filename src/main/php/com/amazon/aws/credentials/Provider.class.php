<?php namespace com\amazon\aws\credentials;

abstract class Provider {

  /** @return ?com.amazon.aws.Credentials */
  public abstract function credentials();

  /** Invokeable implementation */
  public function __invoke() { return $this->credentials(); }
}