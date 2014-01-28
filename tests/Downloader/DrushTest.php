<?php

use Dbmedialab\Drupal\Deploy\Modulefetch\Downloader\Drush;


class DrushTest extends \PHPUnit_Framework_TestCase {
  
  public function testName() {
    $downloader = new Drush;
    $this->assertEquals('drush', $downloader->getName());
  }
}