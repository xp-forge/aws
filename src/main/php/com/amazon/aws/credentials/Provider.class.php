<?php namespace com\amazon\aws\credentials;

interface Provider {

  /** @return ?com.amazon.aws.Credentials */
  public function credentials();

}