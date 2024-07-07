<?php namespace com\amazon\aws\credentials;

use io\File;

class IniFile {
  private $file;

  /** @param string|io.File|io.Path $file */
  public function __construct($file) {
    $this->file= $file instanceof File ? $file : new File($file);
  }

  /** @return ?int */
  public function modified() {
    return $this->file->exists() ? $this->file->lastModified() : null;
  }

  /** @return [:[:string]] */
  public function sections() {
    return $this->file->exists() ? parse_ini_file($this->file->getURI(), true, INI_SCANNER_RAW) : [];
  }
}