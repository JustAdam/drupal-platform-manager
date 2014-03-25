<?php

use Dbmedialab\Drupal\Deploy\Modulefetch\Downloader\Symlink;


class SymlinkTest extends \PHPUnit_Framework_TestCase {
  
  public function testName() {
    $downloader = new Symlink;
    $this->assertEquals('symlink', $downloader->getName());
  }

  public function testRequiredData() {
    $downloader = new Symlink;
    $data = ['url' => 'whatever'];
    $this->assertTrue($downloader->hasRequiredData($data));
  }
}