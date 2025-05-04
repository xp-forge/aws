<?php namespace com\amazon\aws\unittest;

use com\amazon\aws\{ServiceEndpoint, Credentials};
use test\{Assert, Test};

class RequestSigningTest {
  const TEST_TIME= 1678835684;

  /** Executes a given request handler */
  private function execute($handler, $payload= null, $headers= [], $session= null) {
    $headers+= ['User-Agent' => 'xp-aws/1.0.0 OS/Test/1.0 lang/php/8.3.0'];
    return (new ServiceEndpoint('test', new Credentials('key', 'secret', $session)))
      ->connecting(fn($uri) => new TestConnection(['/' => $handler]))
      ->request('GET', '/', $headers, $payload, self::TEST_TIME)
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
  public function authorization_with_payload() {
    $handler= function($request) {
      return ['HTTP/1.1 200 OK', '', $request->headers['Authorization'][0]];
    };

    Assert::equals(
      'AWS4-HMAC-SHA256 Credential=key/20230314/*/test/aws4_request, '.
      'SignedHeaders=host;x-amz-date;x-amz-user-agent, '.
      'Signature=0665b5801ab285d284e1bd0b0be75083768abe1c9bc6920c1d69dfb0765d48c3',
      $this->execute($handler, 'Test')->content()
    );
  }

  #[Test]
  public function authorization_includes_x_amz_header() {
    $handler= function($request) {
      return ['HTTP/1.1 200 OK', '', $request->headers['Authorization'][0]];
    };

    Assert::equals(
      'AWS4-HMAC-SHA256 Credential=key/20230314/*/test/aws4_request, '.
      'SignedHeaders=host;x-amz-copy-source;x-amz-date;x-amz-user-agent, '.
      'Signature=b904172ce6d132a5741cab2c73226c48a77b46886be83381409627a1f02f5822',
      $this->execute($handler, 'Test', ['x-amz-copy-source' => '/bucket/file.txt'])->content()
    );
  }

  #[Test]
  public function authorization_includes_content_type() {
    $handler= function($request) {
      return ['HTTP/1.1 200 OK', '', $request->headers['Authorization'][0]];
    };

    Assert::equals(
      'AWS4-HMAC-SHA256 Credential=key/20230314/*/test/aws4_request, '.
      'SignedHeaders=content-type;host;x-amz-date;x-amz-user-agent, '.
      'Signature=dd3723587bbd8a6249f644d68619a46b8e62d305f414816c7feb7e4ad411dedf',
      $this->execute($handler, 'Test', ['Content-Type' => 'text/plain'])->content()
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
      $this->execute($handler, null, [], 'session')->content()
    );
  }
}