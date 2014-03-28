<?php

namespace Dbmedialab\Drupal\Deploy\Modulefetch\Config;

interface ConfigInterface {
  /**
   * Load and parse a file.
   *
   * @param string file location
   * @return mixed
   */
  public function load($file);

  /**
   * Save data to file.
   *
   * @param String file location
   * @param string $file
   * @param mixed data to save
   * @return void
   */
  public function save($file, $data);

  /**
   * Add data source to configuration interface.
   *
   * @param string Name of source
   * @param string File location
   * @return void
   */
  public function addDataSource($name, $file_location);
}