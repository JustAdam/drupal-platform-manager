<?php

namespace Dbmedialab\Drupal\Deploy\Modulefetch;

use Symfony\Component\Console\Application;
use Dbmedialab\Drupal\Deploy\Modulefetch\Config\ConfigInterface;
use Dbmedialab\Drupal\Deploy\Modulefetch\Downloader\Downloader;


class ModuleFetch extends Application {
  //

  const NAME = 'Drupal Platform Manager';
  const VERSION = '0.5';

  private $pid_file = 'modulefetch.pid';
  private $base_directory;

  /**
   * Configuration object.  Includes parser and dumper.
   * @see ConfigReaderInterface
   */
  protected $config;

  /**
   * Core app configuration data.
   */
  protected $config_data = [];

  /**
   * Asset distribution data (all distributions)
   */
  protected $asset_data = [];

  /**
   * Current download state of all assets.
   */
  protected $state_data = [];


  /**
   * Module download manager.
   * @see Downloader
   * @see ModuleDownloaderInterface
   */
  protected $downloader;


  public function __construct(ConfigInterface $config, Downloader $downloader) {
    // Lock file - only one instance of the app to run at a time.
    $this->writePIDFile();

    parent::__construct(self::NAME, self::VERSION);

    // Load core configuration data
    $this->loadConfigData($config);
    // Load all site and distrubution data
    $this->loadDistributionData();

    // Set available downloaders
    $this->downloader = $downloader;
  }

  private function writePIDFile() {
    // How is this going to work in practise on a multiuser (and one install) system 
    // with users installing into different directories ....
    $this->pid_file = getcwd() . '/' . $this->pid_file;
    if (file_exists($this->pid_file)) {
      throw new \RuntimeException('pid file for application still exists.  Perhaps I am already running, or didn\'t shutdownn correctly last time');
    }
    file_put_contents($this->pid_file, getmypid());
  }

  /**
   * @see getConfig()
   */
  private function loadConfigData(ConfigInterface $config) {
    $this->config = $config;
    $this->config_data = $this->config->load('config');
    $this->state_data = $this->config->load('state');

    $directories = $this->getConfig('directories');

    // Check for core items required from the config file.
    // @todo should probably also check for changes here ....
    if (empty($directories['base']) 
        || empty($directories['releases'])
        || empty($directories['downloads'])
        || empty($directories['modules']['base'])) {
      throw new \RuntimeException('Directory locations are not properly configured (require releases, downloada, modules->base).');
    }

    // Store location of base directory, so commands can move up and down the directory
    // structure without breaking core path name getters.
    $this->base_directory = realpath($directories['base']);
  }

  /**
   * @see getAssets()
   */
  private function loadDistributionData() {
    // Load site info
    $s_d_tmp = $this->config->load('sites');
    if (empty($s_d_tmp) && !is_array($s_d_tmp)) {
      throw new \RuntimeException('No site information found');
    }
    // Reorganise site data by distribution.  This will be later added to the asset data array.
    $site_data = [];
    foreach ($s_d_tmp as $name => $info) {
      if (empty($info['distribution']) || empty($info['source']) || empty($info['document_root'])) {
        throw new \RuntimeException("Site configuration for $name is missing required values.");
      }
      $site_data[$info['distribution']][$name] = $info;
    }
    unset($s_d_tmp);

    // Load asset info
    $asset_info = $this->getConfig('distribution_info');
    if (empty($asset_info)) {
      throw new \RuntimeException('No distribution information found.');
    }

    // Load information for each asset distribution
    foreach ($asset_info as $name => $info) {
      if (empty($info['buildfile'])) {
        throw new \RuntimeException("Distribution $name is missing buildfile info");
      }

      $this->config->addDataSource($name, $info['buildfile']);
      $asset = $this->config->load($name);

      $assets = $asset['assets'];
      if (!empty($asset['core']['version'])) {
        // Set main core version number (7.x).  Required for drush downloads
        $info['main_version'] = preg_replace("!(\d{1,3})$!", 'x', $asset['core']['version']);
        $assets['core']['drupal'] = $asset['core'];  
      } else {
        throw new \RuntimeException("core settings are missing from distribution $name");
      }
      $this->asset_data[$name] = $assets;
      $info['site_building'] = $site_data[$name];
      $this->asset_data[$name]['info'] = $info;
    }
  }

  public function __destruct() {
    // Not yet intercepting ctrl+c ...
    unlink($this->pid_file);
  }

  public function getDistributions() {
    return array_keys($this->asset_data);
  }

  public function getConfigObj() {
    return $this->config;
  }

  public function getDownloader($downloader) {
    return $this->downloader->get($downloader);
  }

  /**
   * @param string $var
   */
  public function getConfig($var) {
    return $this->config_data['core'][$var];
  }

  public function getAssets($type, $distribution) {
    return $this->asset_data[$distribution][$type];
  }

  /**
   * @param string $type
   * @param string $name
   */
  public function getState($type, $name) {
    return isset($this->state_data[$type][$name]) ? $this->state_data[$type][$name] : NULL;
  }

  public function updateState($type, $name, array $data) {
    $hash = $this->genStateHash($data);
    $search = isset($this->state_data[$type][$name]) ? $this->state_data[$type][$name] : [];
    if (!$this->stateExists($hash, $search)) {
      $this->state_data[$type][$name][] = $hash;
    }
  }

  public function removeState($type, $name, $state) {
    if (isset($this->state_data[$type][$name])) {
      $key = array_search($state, $this->state_data[$type][$name]);
      if ($key !== FALSE) {
        unset($this->state_data[$type][$name][$key]);
        if (empty($this->state_data[$type][$name])) {
          unset($this->state_data[$type][$name]);
        } else {
          // Reorder keys from 0 onwards
          $this->state_data[$type][$name] = array_values($this->state_data[$type][$name]);
        }
      }
    }
  }

  public function stateExists($new_state, $current_state) {
    if (!is_array($current_state)) {
      $current_state = [];
    }
    return array_search($new_state, $current_state) !== FALSE;
  }

  public function saveAssetsDownloadState() {
    $current_state = $this->state_data;
    $current_state['dpm_core_settings']['save_time'] = time();

    $this->getConfigObj()->save('state', $current_state);
  }

  public function genStateHash(array $data) {
    $join = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($data));
    $str = '';
    foreach ($join as $key => $piece) {
      // Ignore keys that are not relevant to hash generation (local info)
      if ($key === 'subdir' || $key === 'hash') {
        continue;
      }
      $str .= $piece;
    }
    return sha1($str);
  }

  public function getBaseDownloadDirectory() {
    return $this->base_directory . '/' . $this->getConfig('directories')['downloads'];
  }

  public function getReleaseDirectory() {
    return $this->base_directory . '/' . $this->getConfig('directories')['releases'];
  }

  public function getAssetDirectory($type) {

    $directories = $this->getConfig('directories');
    if (!isset($directories[$type])) {
      throw new \InvalidArgumentException('Asset directory for ' . $type . ' is unknown.');
    }

    $dirs = [];
    if (!empty($directories[$type]['subdir'])) {
      foreach ($directories[$type]['subdir'] as $name => $directory)  {
        $dirs[$name] = $directories[$type]['base'] . '/' . $directory;
      }
    } else {
      $dirs['base'] = $directories[$type]['base'];
    }

    return $dirs;
  }

  public function getDownloadToLocation(array $data) {
    $download_to = $this->getBaseDownloadDirectory() . '/' . "{$data['type']}-{$data['name']}-{$data['drupal_core']}";
    if (!empty($data['version'])) {
      $download_to .= "-{$data['version']}";
    }
    if (!empty($data['hash'])) {
      $download_to .= "-{$data['hash']}"; 
    }
    return $download_to;
  }

  public function isInstalled() {
    $state = $this->getState('dpm_core_settings', 'save_time');
    return !empty($state);
  }
}
