<?php namespace com\amazon\aws\unittest;

use com\amazon\aws\{ServiceEndpoint, Credentials};
use test\{Assert, Test};

class RequestSigningTest {
  const TEST_TIME= 1678835684;

  /** Executes a given request handler */
  private function execute($handler, $session= null) {
    return (new ServiceEndpoint('test', new Credentials('key', 'secret', $session)))
      ->connecting(function($uri) use($handler) { return new TestConnection(['/' => $handler]); })
      ->request('GET', '/', ['User-Agent' => 'xp-aws/1.0.0 OS/Test/1.0 lang/php/8.3.0'], null, self::TEST_TIME)
    ;
  }

  #[Test]
  public function host() {
    $handler= function($request) {
      return ['HTTP/1.1 200 OK', '', $request->headers['Host'][0]];
    };

    Assert::equals('test.amazonaws.com', $this->execute($handler)->content());
  }

  #[Test]
  public function amz_date() {
    $handler= function($request) {
      return ['HTTP/1.1 200 OK', '', $request->headers['X-Amz-Date'][0]];
    };

    Assert::equals('20230314T231444Z', $this->execute($handler)->content());
  }

  #[Test]
  public function amz_user_agent() {
    $handler= function($request) {
      return ['HTTP/1.1 200 OK', '', $request->headers['X-Amz-User-Agent'][0]];
    };

    Assert::matches(
      '/xp-aws\/[0-9.]+ OS\/.+\/.+ lang\/php\/[0-9.]+/',
      $this->execute($handler)->content()
    );
  }

  #[Test]
  public function authorization() {
    $handler= function($request) {
      return ['HTTP/1.1 200 OK', '', $request->headers['Authorization'][0]];
    };

    Assert::equals(
      'AWS4-HMAC-SHA256 Credential=key/20230314/*/test/aws4_request, '.
      'SignedHeaders=host;x-amz-date;x-amz-user-agent, '.
      'Signature=252720f9823080fb461b7a311b52ce4dea9ed7e28c16cfa288054737e388786f',
      $this->execute($handler)->content()
    );
  }

  #[Test]
  public function authorization_with_session() {
    $handler= function($request) {
      return ['HTTP/1.1 200 OK', '', $request->headers['Authorization'][0]];
    };

    Assert::equals(
      'AWS4-HMAC-SHA256 Credential=key/20230314/*/test/aws4_request, '.
      'SignedHeaders=host;x-amz-date;x-amz-security-token;x-amz-user-agent, '.
      'Signature=c026606276b2854bcf04371f086c1e6339dbd01ce3ac04da51566521e7afc87f',
      $this->execute($handler, 'session')->content()
    );
  }
}