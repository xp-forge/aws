<?php namespace com\amazon\aws\unittest;

use util\Date;

class Result {

  /** @type bool */
  public $success;

  /** @type util.Date */
  public $date;

  public function __construct(bool $success, Date $date) {
    $this->success= $success;
    $this->date= $date;
  }
}