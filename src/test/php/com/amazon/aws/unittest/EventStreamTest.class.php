<?php namespace com\amazon\aws\unittest;

use com\amazon\aws\api\{Event, EventStream};
use io\streams\MemoryInputStream;
use lang\IllegalStateException;
use test\{Assert, Expect, Test, Values};
use util\{Bytes, Date, UUID};

class EventStreamTest {

  /** Creates a messages from given headers and payload */
  private function message(string $headers, string $payload): string {
    $prelude= pack('NN', strlen($payload) + strlen($headers) + 16, strlen($headers));
    $bytes= (
      $prelude.
      pack('N', sprintf('%u', crc32($prelude))).
      $headers.
      $payload
    );
    return $bytes.pack('N', sprintf('%u', crc32($bytes)));
  }

  /** @return iterable */
  private function values() {
    yield ['true', '00', true];
    yield ['false', '01', false];
    yield ['byte', '02 ff', 0xff];
    yield ['short', '03 01ff', 0x1ff];
    yield ['integer', '04 000007b9', 1977];
    yield ['long', '05 0000000066bfc0c2', 1723842754];
    yield ['bytes', '06 0004 54657374', new Bytes('Test')];
    yield ['string', '07 0004 54657374', 'Test'];
    yield ['timestamp', '08 0000016631c53270', new Date(1538433299)];
    yield ['uuid', '09 3bfdac5c fe6c 4029 83bf c1de7819f531', new UUID('3bfdac5c-fe6c-4029-83bf-c1de7819f531')];
  }

  #[Test]
  public function can_create() {
    new EventStream(new MemoryInputStream(''));
  }

  #[Test]
  public function next() {
    $events= new EventStream(new MemoryInputStream($this->message('', '')));

    Assert::instance(Event::class, $events->next());
    Assert::null($events->next());
  }

  #[Test]
  public function iteration() {
    $events= new EventStream(new MemoryInputStream($this->message('', '')));

    $list= iterator_to_array($events);
    Assert::equals(1, sizeof($list));
    Assert::instance('com.amazon.aws.api.Event[]', $list);
  }

  #[Test, Values(['', 'Test'])]
  public function payload($value) {
    $events= new EventStream(new MemoryInputStream($this->message('', $value)));

    Assert::equals(new Event([], $value), $events->next());
  }

  #[Test, Values([['01 61 00', ['a' => true]], ['01 61 00 01 62 01', ['a' => true, 'b' => false]]])]
  public function headers($encoded, $expected) {
    $message= $this->message(hex2bin(str_replace(' ', '', $encoded)), '');
    $events= new EventStream(new MemoryInputStream($message));

    Assert::equals(new Event($expected, ''), $events->next());
  }

  #[Test, Values(from: 'values')]
  public function header_value_type($kind, $encoded, $expected) {
    $message= $this->message("\004test".hex2bin(str_replace(' ', '', $encoded)), 'Test');
    $events= new EventStream(new MemoryInputStream($message));

    Assert::equals(new Event(['test' => $expected], 'Test'), $events->next());
  }

  #[Test, Expect(class: IllegalStateException::class, message: 'Prelude checksum mismatch')]
  public function malformed_prelude_checksum() {
    $message= $this->message('', '');
    $message[9]= "\x00";

    (new EventStream(new MemoryInputStream($message)))->next();
  }
}