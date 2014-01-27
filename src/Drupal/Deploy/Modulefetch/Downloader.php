<?php

namespace Dbmedialab\Drupal\Deploy\Modulefetch;

use Dbmedialab\Drupal\Deploy\Modulefetch\ModuleDownloaderInterface;
 

class Downloader {
  protected $downloaders = [];

  
  public function add(ModuleDownloaderInterface $obj) {
    $this->downloaders[$obj->getName()] = $obj;
  }

  public function get($name) {
    if (!isset($this->downloaders[$name])) {
      throw new \InvalidArgumentException("Downloader $name does not exist.");
    }
    return $this->downloaders[$name];
  }
}