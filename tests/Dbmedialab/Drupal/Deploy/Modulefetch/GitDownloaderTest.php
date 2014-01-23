<?php

use Dbmedialab\Drupal\Deploy\Modulefetch\GitDownloader;

class GitDownloaderTest extends \PHPUnit_Framework_TestCase {
  
  public function testName() {
    $downloader = new GitDownloader;
    $this->assertEquals('git', $downloader->getName());
  }
}