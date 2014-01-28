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
   * @param mixed data to save
   */
  public function save($file, $data);

  /**
   * Add data source to configuration interface.
   *
   * @param string Name of source
   * @param string File location
   */
  public function addDataSource($name, $file_location);
}