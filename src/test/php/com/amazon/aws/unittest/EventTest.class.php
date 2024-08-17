<?php namespace com\amazon\aws\unittest;

use com\amazon\aws\api\{Event, EventStream};
use io\streams\MemoryInputStream;
use lang\IllegalStateException;
use test\{Assert, Before, Expect, Test, Values};

class EventTest {
  private $source;

  #[Before]
  public function source() {
    $this->source= new EventStream(new MemoryInputStream(''));
  }

  #[Test]
  public function can_create() {
    new Event($this->source, [], '');
  }

  #[Test, Values([[[]], [[':content-type' => 'text/plain']]])]
  public function headers($value) {
    Assert::equals($value, (new Event($this->source, $value))->headers());
  }

  #[Test]
  public function header() {
    $event= new Event($this->source, [':content-type' => 'text/plain']);
    Assert::equals('text/plain', $event->header(':content-type'));
  }

  #[Test]
  public function non_existant_header() {
    $event= new Event($this->source, []);
    Assert::null($event->header(':event-type'));
  }

  #[Test]
  public function non_existant_header_default() {
    $event= new Event($this->source, []);
    Assert::equals('other', $event->header(':event-type', 'other'));
  }

  #[Test, Values(['', 'Test'])]
  public function content($value) {
    Assert::equals($value, (new Event($this->source, [], $value))->content());
  }

  #[Test]
  public function json_value() {
    $event= new Event($this->source, [':content-type' => 'application/json'], '{"key":"value"}');
    Assert::equals(['key' => 'value'], $event->value());
  }

  #[Test]
  public function text_value() {
    $event= new Event($this->source, [':content-type' => 'text/plain'], 'Test');
    Assert::equals('Test', $event->value());
  }

  #[Test, Expect(class: IllegalStateException::class, message: 'Cannot deserialize text/html')]
  public function unhandled_content_type() {
    (new Event($this->source, [':content-type' => 'text/html'], '<html>...</html>'))->value();
  }
}