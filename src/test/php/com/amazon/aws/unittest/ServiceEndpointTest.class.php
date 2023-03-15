<?php namespace com\amazon\aws\unittest;

use com\amazon\aws\api\Resource;
use com\amazon\aws\{ServiceEndpoint, Credentials};
use test\{Assert, Before, Test};

class ServiceEndpointTest {
  private $credentials;

  #[Before]
  public function initialize() {
    $this->credentials= new Credentials('key', 'secret');
  }

  #[Test]
  public function can_create() {
    new ServiceEndpoint('lambda', $this->credentials);
  }

  #[Test]
  public function service() {
    Assert::equals('lambda', (new ServiceEndpoint('lambda', $this->credentials))->service());
  }

  #[Test]
  public function credentials() {
    Assert::equals($this->credentials, (new ServiceEndpoint('lambda', $this->credentials))->credentials());
  }

  #[Test]
  public function region_null_by_default() {
    Assert::null((new ServiceEndpoint('lambda', $this->credentials))->region());
  }

  #[Test]
  public function in_region() {
    Assert::equals('eu-central-1', (new ServiceEndpoint('lambda', $this->credentials))
      ->in('eu-central-1')
      ->region()
    );
  }

  #[Test]
  public function in_global() {
    Assert::equals(null, (new ServiceEndpoint('lambda', $this->credentials))
      ->global()
      ->region()
    );
  }

  #[Test]
  public function resource() {
    Assert::instance(Resource::class, (new ServiceEndpoint('lambda', $this->credentials))
      ->resource('/2015-03-31/functions/{name}/invocations', ['name' => 'test'])
    );
  }

  #[Test]
  public function version_resource() {
    Assert::instance(Resource::class, (new ServiceEndpoint('lambda', $this->credentials))
      ->version('2015-03-31')
      ->resource('/functions/{name}/invocations', ['name' => 'test'])
    );
  }
}