<?php

namespace Dbmedialab\Drupal\Deploy\Modulefetch\Downloader;

/**
 * Symlink to another location on the file system.
 * This allows that asset to be updated and managed by another process.
 */
class Symlink implements ModuleDownloaderInterface {

  protected $download = NULL;
  

  public function getName() {
    return 'symlink';
  }

  public function get($from, $to) {

    // If source directory already exists, then this asset (we assume) has already been downloaded.
    if (is_dir($to)) {
      // @todo possibily check if the link actually points where we want (although this shouldn't be a problem due to how we currently name our folders)
      return NULL;
    }

    if (symlink($from['url'], $to)) {
      $this->download = $to;
      return TRUE;
    }
    return FALSE;
  }

  public function applyPatches(array $patches) {
    // not implemented
    return;
  }

  public function getDownload() {
    return $this->download;
  }
}
