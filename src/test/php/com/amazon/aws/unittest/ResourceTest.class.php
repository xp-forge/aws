<?php namespace com\amazon\aws\unittest;

use com\amazon\aws\api\Resource;
use com\amazon\aws\{ServiceEndpoint, Credentials};
use lang\ElementNotFoundException;
use test\{Assert, Before, Expect, Test, Values};

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

  #[Test, Expect(class: ElementNotFoundException::class, message: 'No such segment "id"')]
  public function missing_segment() {
    new Resource($this->endpoint, '/{id}');
  }

  #[Test, Values([['Test', '"Test"'], [['a' => 'b', 'c' => 'd'], '{"a":"b","c":"d"}']])]
  public function serialize_json($payload, $expected) {
    Assert::equals($expected, (new Resource($this->endpoint, '/'))->serialize($payload, 'application/json'));
  }

  #[Test, Values([[['a' => 'b', 'c' => 'd'], 'a=b&c=d']])]
  public function serialize_rfc1738($payload, $expected) {
    Assert::equals($expected, (new Resource($this->endpoint, '/'))->serialize($payload, 'application/x-www-form-urlencoded'));
  }

  #[Test, Values([['Test', 'Test']])]
  public function serialize_text($payload, $expected) {
    Assert::equals($expected, (new Resource($this->endpoint, '/'))->serialize($payload, 'text/plain'));
  }
}