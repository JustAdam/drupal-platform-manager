<?php

use Dbmedialab\Drupal\Deploy\Modulefetch\Downloader\Get;


class GetTest extends \PHPUnit_Framework_TestCase {
  
  public function testName() {
    $downloader = new Get;
    $this->assertEquals('get', $downloader->getName());
  }
}