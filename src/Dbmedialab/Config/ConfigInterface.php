<?php

namespace Dbmedialab\Config;

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
}