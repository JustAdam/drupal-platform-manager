<?php

use Dbmedialab\Drupal\Deploy\Modulefetch\DrushDownloader;

class DrushDownloaderTest extends \PHPUnit_Framework_TestCase {
  
  public function testName() {
    $downloader = new DrushDownloader;
    $this->assertEquals('drush', $downloader->getName());
  }
}