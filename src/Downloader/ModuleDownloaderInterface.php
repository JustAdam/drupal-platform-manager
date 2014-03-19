<?php

namespace Dbmedialab\Drupal\Deploy\Modulefetch\Downloader;

interface ModuleDownloaderInterface {
  /**
   * Return friendly name for the class.
   * @return string
   */
  function getName();
  /**
   * Return whether the asset array has all the required data to carry
   * out a sucessful download.
   * @param asset data array
   * @return boolean
   */
  function hasRequiredData(array $data);
  /**
   * Fetch asset from a resource.
   * @param from 
   * @param string Directory to download into.
   * @return boolean
   * @todo look at implentation of $from
   */
  function get($from, $base_directory_to);

  /**
   * Return location to downloaded object.
   * @return string
   */
  function getDownload();

  /**
   * List of patches to apply to the download.
   * @param array
   */
  function applyPatches(array $patches);
}