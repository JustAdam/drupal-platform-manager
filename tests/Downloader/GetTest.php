<?php

use Dbmedialab\Drupal\Deploy\Modulefetch\Downloader\Get;


class GetTest extends \PHPUnit_Framework_TestCase {
  
  public function testName() {
    $downloader = new Get;
    $this->assertEquals('get', $downloader->getName());
  }

  public function testRequiredData() {
    $downloader = new Get;
    $data = ['url' => 'whatever'];
    $this->assertTrue($downloader->hasRequiredData($data));
  }
}