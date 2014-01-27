<?php

namespace Dbmedialab\Config;

use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Dumper;


class Config implements ConfigInterface {
  
  protected $parser;

  protected $dumper;

  /**
   * Data sources.
   * @type array
   */
  protected $data = array();


  public function __construct(Parser $parser, Dumper $dumper, array $data) {

    $this->parser = $parser;
    $this->dumper = $dumper;
    $this->data = $data;
  }

  public function load($file) {

    if (!file_exists($this->data[$file])) {
      throw new \InvalidArgumentException('Config file does not exist: ' . $this->data[$file]);
    }

    return $this->parser->parse(file_get_contents($this->data[$file]));
  }

  public function save($file, $data) {

    if (!file_exists($this->data[$file])) {
      throw new \InvalidArgumentException('Config file does not exist: ' . $this->data[$file]);
    }

    if (!is_writable($this->data[$file])) {
       throw new \RuntimeException('Base directory ' . $directories['base'] . ' is not writable.');
    }

    $content = $this->dumper->dump($data);
    file_put_contents($this->data[$file], $content, LOCK_EX);
  }
}
