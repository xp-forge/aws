<?php namespace com\amazon\aws\unittest;

use com\amazon\aws\api\Resource;
use com\amazon\aws\{ServiceEndpoint, Credentials};
use test\{Assert, Before, Test};

class ResourceTest {
  private $endpoint;

  #[Before]
  public function endpoint() {
    $this->endpoint= new ServiceEndpoint('test', new Credentials('key', 'secret'));
  }

  #[Test]
  public function static_path() {
    Assert::equals('/', (new Resource($this->endpoint, '/'))->target);
  }

  #[Test]
  public function with_segments() {
    Assert::equals('/a/b', (new Resource($this->endpoint, '/{0}/{1}', ['a', 'b']))->target);
  }

  #[Test]
  public function named_segment() {
    Assert::equals('/6100', (new Resource($this->endpoint, '/{id}', ['id' => 6100]))->target);
  }
}