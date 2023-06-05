<?php namespace com\amazon\aws\unittest;

use com\amazon\aws\api\Resource;
use com\amazon\aws\{ServiceEndpoint, Credentials};
use test\{Assert, Before, Test, Values};

class ServiceEndpointTest {
  private $credentials;

  #[Before]
  public function initialize() {
    $this->credentials= new Credentials('key', 'secret', 'session');
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

  #[Test]
  public function global_domain() {
    Assert::equals(
      'iam.amazonaws.com',
      (new ServiceEndpoint('iam', $this->credentials))->domain()
    );
  }

  #[Test]
  public function region_domain() {
    Assert::equals(
      'lambda.eu-central-1.amazonaws.com',
      (new ServiceEndpoint('lambda', $this->credentials))->in('eu-central-1')->domain()
    );
  }

  #[Test, Values(['id', 'id.execute-api.eu-central-1.amazonaws.com'])]
  public function use_domain_or_prefix($domain) {
    Assert::equals(
      'id.execute-api.eu-central-1.amazonaws.com',
      (new ServiceEndpoint('execute-api', $this->credentials))
        ->in('eu-central-1')
        ->using($domain)
        ->domain()
    );
  }

  #[Test]
  public function sign_link() {
    $uri= (new ServiceEndpoint('s3', $this->credentials))
      ->using('bucket')
      ->sign('/folder/resource.txt', 3600, strtotime('20230314T231444Z'))
    ;

    Assert::equals(
      'https://bucket.s3.amazonaws.com/folder/resource.txt'.
      '?X-Amz-Algorithm=AWS4-HMAC-SHA256'.
      '&X-Amz-Credential=key%2F20230314%2F%2A%2Fs3%2Faws4_request'.
      '&X-Amz-Date=20230314T231444Z'.
      '&X-Amz-Expires=3600'.
      '&X-Amz-Security-Token=session'.
      '&X-Amz-SignedHeaders=host'.
      '&X-Amz-Signature=ebb6a81bd3ad8e2bfc968bdec6b97d18693e05e9117e637629a4642396ce6b1c',
      $uri
    );
  }

  #[Test]
  public function sign_link_with_param() {
    $uri= (new ServiceEndpoint('s3', $this->credentials))
      ->using('bucket')
      ->sign('/folder/resource.txt?response-content-disposition=inline', 3600, strtotime('20230606T231444Z'))
    ;

    Assert::equals(
      'https://bucket.s3.amazonaws.com/folder/resource.txt'.
      '?X-Amz-Algorithm=AWS4-HMAC-SHA256'.
      '&X-Amz-Credential=key%2F20230606%2F%2A%2Fs3%2Faws4_request'.
      '&X-Amz-Date=20230606T231444Z'.
      '&X-Amz-Expires=3600'.
      '&X-Amz-Security-Token=session'.
      '&X-Amz-SignedHeaders=host'.
      '&response-content-disposition=inline'.
      '&X-Amz-Signature=589d505a193a066ed7c7aaef1d1abdeba57ec01d7f0e0326fc54711597ed8119',
      $uri
    );
  }
}