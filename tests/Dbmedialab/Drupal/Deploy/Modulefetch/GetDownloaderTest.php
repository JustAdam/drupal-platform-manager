<?php

use Dbmedialab\Drupal\Deploy\Modulefetch\GetDownloader;

class GetDownloaderTest extends \PHPUnit_Framework_TestCase {
  
  public function testName() {
    $downloader = new GetDownloader;
    $this->assertEquals('get', $downloader->getName());
  }
}