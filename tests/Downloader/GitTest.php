<?php

use Dbmedialab\Drupal\Deploy\Modulefetch\Downloader\Git;


class GitTest extends \PHPUnit_Framework_TestCase {
  
  public function testName() {
    $downloader = new Git;
    $this->assertEquals('git', $downloader->getName());
  }

  public function testRequiredData() {
    $downloader = new Git;
    $data = ['url' => 'whatever'];
    $this->assertTrue($downloader->hasRequiredData($data));
  }
}