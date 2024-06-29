<?php namespace com\amazon\aws\api;

class Transfer {
  private $conn, $stream, $marshalling;

  /**
   * Creates a new transfer
   *
   * @see    com.amazon.aws.ServiceEndpoint::open()
   * @param  peer.http.HttpConnection $conn
   * @param  peer.http.HttpRequest $request
   * @param  util.data.Marshalling $marshalling
   */
  public function __construct($conn, $request, $marshalling) {
    $this->conn= $conn;
    $this->stream= $conn->open($request);
    $this->marshalling= $marshalling;
  }

  /**
   * Writes bytes
   *
   * @param  string $bytes
   * @return void
   */
  public function write($bytes) {
    $this->stream->write($bytes);
  }

  /** Finishes transfer and returns response */
  public function finish(): Response {
    $r= $this->conn->finish($this->stream);
    return new Response($r->statusCode(), $r->message(), $r->headers(), $r->in(), $this->marshalling);
  }
}