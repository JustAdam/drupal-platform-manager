<?php

namespace Dbmedialab\Drupal\Deploy\Modulefetch\Config;

use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Dumper;


class Config implements ConfigInterface {
  
  protected $parser;

  protected $dumper;

  /**
   * Data sources.
   * @type array
   */
  protected $data = [];


  public function __construct(Parser $parser, Dumper $dumper) {

    $this->parser = $parser;
    $this->dumper = $dumper;
  }

  public function addDataSource($name, $file_location) {
    $this->data[$name] = $file_location;
  }

  public function load($file) {

    $this->fileExists($file);

    return $this->parser->parse(file_get_contents($this->data[$file]));
  }

  public function save($file, $data) {

    $this->fileExists($file);

    if (!is_writable($this->data[$file])) {
       throw new \RuntimeException('File ' . $this->data[$file] . ' is not writable.');
    }

    $content = $this->dumper->dump($data);
    file_put_contents($this->data[$file], $content, LOCK_EX);
  }

  protected function fileExists($file) {
    if (empty($this->data[$file])) {
      throw new \InvalidArgumentException('Config file for ' . $file .  ' does not exist.');
    }

    if (!file_exists($this->data[$file])) {
      throw new \InvalidArgumentException('Config file does not exist: ' . $this->data[$file]);
    }
  }
}
