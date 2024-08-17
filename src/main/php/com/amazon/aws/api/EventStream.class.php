<?php namespace com\amazon\aws\api;

use IteratorAggregate, Traversable;
use io\streams\InputStream;
use lang\IllegalStateException;
use util\{Bytes, Date, UUID};

/**
 * Amazon event stream, mime type `application/vnd.amazon.eventstream`.
 *
 * @see   https://docs.aws.amazon.com/AmazonS3/latest/API/RESTSelectObjectAppendix.html
 * @see   com.amazon.aws.api.Response::events()
 * @test  com.amazon.aws.unittest.EventStreamTest
 */
class EventStream implements IteratorAggregate {
  const FALSE = 1;
  const TRUE = 0;
  const BYTE = 2;
  const SHORT = 3;
  const INTEGER = 4;
  const LONG = 5;
  const BYTES = 6;
  const STRING = 7;
  const TIMESTAMP = 8;
  const UUID = 9;

  private $in;

  /** Creates a new instance */
  public function __construct(InputStream $in) {
    $this->in= $in;
  }

  /**
   * Reads a given number of bytes
   *
   * @param  int $length
   * @return string
   */
  private function read($length) {
    $chunk= '';
    do {
      $chunk.= $this->in->read($length - strlen($chunk));
    } while (strlen($chunk) < $length && $this->in->available());
    return $chunk; 
  }

  /**
   * Parse headers from a given buffer
   *
   * @param  string $buffer
   * @return [:var] $headers
   */
  private function headers($buffer) {
    $headers= [];
    $offset= 0;
    $length= strlen($buffer);
    while ($offset < $length) {
      $l= ord($buffer[$offset++]);
      $header= substr($buffer, $offset, $l);
      $offset+= $l;

      $t= ord($buffer[$offset++] ?? "\xff");
      switch ($t) {
        case self::TRUE:
          $value= true;
          break;

        case self::FALSE:
          $value= false;
          break;

        case self::BYTE:
          $value= ord($buffer[$offset++]);
          break;

        case self::SHORT:
          $value= unpack('n', $buffer, $offset)[1];
          $offset+= 2;
          break;

        case self::INTEGER:
          $value= unpack('N', $buffer, $offset)[1];
          $offset+= 4;
          break;

        case self::LONG:
          $value= unpack('J', $buffer, $offset)[1];
          $offset+= 8;
          break;

        case self::BYTES:
          $l= unpack('n', $buffer, $offset)[1];
          $offset+= 2;
          $value= new Bytes(substr($buffer, $offset, $l));
          $offset+= $l;
          break;

        case self::STRING:
          $l= unpack('n', $buffer, $offset)[1];
          $offset+= 2;
          $value= substr($buffer, $offset, $l);
          $offset+= $l;
          break;

        case self::TIMESTAMP:
          $t= unpack('J', $buffer, $offset)[1];
          $value= new Date((int)($t / 1000));
          $offset+= 8;
          break;

        case self::UUID:
          $value= new UUID(new Bytes(substr($buffer, $offset, 16)));
          $offset+= 16;
          break;

        default: throw new IllegalStateException('Unhandled type #'.$t);
      }

      $headers[$header]= $value;
    }

    return $headers;
  }

  /**
   * Returns next event in stream or `null` if there is none left
   *
   * @return ?com.amazon.aws.api.Event
   * @throws lang.IllegalStateException for checksum mismatches
   */
  public function next() {
    if (!$this->in->available()) return null;

    $hash= hash_init('crc32b');
    $buffer= $this->read(12);
    hash_update($hash, $buffer);

    $prelude= unpack('Ntotal/Nheaders/Nchecksum', $buffer);
    if (sprintf('%u', crc32(substr($buffer, 0, 8))) !== (string)$prelude['checksum']) {
      throw new IllegalStateException('Prelude checksum mismatch');
    }

    $buffer= $this->read($prelude['headers']);
    $headers= $this->headers($buffer);
    hash_update($hash, $buffer);

    $buffer= $this->read($prelude['total'] - $prelude['headers'] - 16);
    hash_update($hash, $buffer);

    $checksum= unpack('N', $this->read(4))[1];
    if (hexdec(hash_final($hash)) !== $checksum) {
      throw new IllegalStateException('Payload checksum mismatch');
    }

    return new Event($headers, $buffer);
  }

  /**
   * Streams `com.amazon.aws.api.Event` instances
   * 
   * @throws lang.IllegalStateException for checksum mismatches
   */
  public function getIterator(): Traversable {
    while (null !== ($next= $this->next())) {
      yield $next;
    }
  }
}