<?php namespace com\amazon\aws\unittest;

use com\amazon\aws\S3Key;
use test\{Assert, Test, Values};

class S3KeyTest {

  #[Test]
  public function empty() {
    Assert::equals('/', (new S3Key())->path());
  }

  #[Test]
  public function single() {
    Assert::equals('/test', (new S3Key('test'))->path());
  }

  #[Test]
  public function composed() {
    Assert::equals('/target/test', (new S3Key('target', 'test'))->path());
  }

  #[Test, Values(['/base', '/base/'])]
  public function based($base) {
    Assert::equals('/base/test', (new S3Key('test'))->path($base));
  }

  #[Test]
  public function string_cast() {
    Assert::equals('/test', (string)new S3Key('test'));
  }

  #[Test]
  public function string_representation() {
    Assert::equals('com.amazon.aws.S3Key(/target/test)', (new S3Key('target', 'test'))->toString());
  }

  #[Test]
  public function comparison() {
    $fixture= new S3Key('b-test');

    Assert::equals(0, $fixture->compareTo(new S3Key('b-test')));
    Assert::equals(1, $fixture->compareTo(new S3Key('a-test')));
    Assert::equals(-1, $fixture->compareTo(new S3Key('c-test')));
  }
}