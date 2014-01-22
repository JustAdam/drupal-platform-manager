<?php

namespace Dbmedialab\Drupal\Deploy\Modulefetch;


class DrushDownloader implements ModuleDownloaderInterface {
  //
  protected $download = NULL;

  protected $destination;
  protected $asset_name;


  public function getName() {
    return 'drush';
  }

  public function get($from, $to) {

    //
    // Drush names the download directory as the module name, whereas we need to download it into
    // the directory name specified by $to.

    //
    // If source directory already exists, then this asset (we assume) has already been downloaded.
    if (is_dir($to)) {
      return NULL;
    }

    $this->asset_name = $from['name'];

    // Argument drush expects to download the module
    $dl_module = "{$this->asset_name}-{$from['drupal_core']}-{$from['version']}";

    // Get the base directory for drush to download into
    $this->destination = dirname($to);

    $s_module = escapeshellarg($dl_module);
    $s_to = escapeshellarg($this->destination);

    $command = "/usr/bin/env drush dl $s_module --destination=$s_to --no";
    
    $descriptor = [
      //0 => array("pipe", "r"),
      1 => ["pipe", "w"], // Ignore STDOUT
      2 => ["pipe", "w"]
    ];
    $pipes = [];

    $cmd = proc_open($command, $descriptor, $pipes);
    if (is_resource($cmd)) {
      preg_match("!(success|warning|error)!i", stream_get_contents($pipes[2]), $matches);

      foreach ($descriptor as $pipe => $d) {
        fclose($pipes[$pipe]);
      }

      $cmd_return = proc_close($cmd);

      $return = array_shift($matches);
      $return = strtolower($return);

      switch ($return) {
        case 'success':
          $this->download = $to;

          if (!empty($from['patches'])) {
            $this->applyPatches($from['patches']);  
          }

          // Rename directory from that drush uses to what was asked for.
          rename($this->destination . '/' . $this->asset_name, $to);

          return TRUE;
        break;
        default:
          return FALSE;
      }
    }
    throw new \Exception('Failed creating drush process.');
  }

  // @todo refactoring and strategy abstraction
  public function applyPatches(array $patches) {
    $dl_to = $this->destination . '/' . $this->asset_name;

    $context = stream_context_create(['http' => ['method' => 'GET', "user_agent" => 'drush']]);

    try {
      foreach ($patches as $patch) {

        // download patch
        $file = file_get_contents($patch, FALSE, $context);
        if (!$file) {
          throw new \Exception("Failed downloading patch $patch.");
        }
        $filename = $dl_to . '/' . basename($patch);
        file_put_contents($filename, $file);

        //$patch = "patch -p1 --dry-run -s -d $dl_to < $filename";
        $patch = "patch %s %s -s -d %s < %s";
        $test_patch = popen(sprintf($patch, '-p1', '--dry-run', $dl_to, $filename), 'r');
        // If we get output then something has gone wrong
        if (fgets($test_patch)) {
          pclose($test_patch);
          throw new \Exception("Patching of $filename failed.");
        }
        pclose($test_patch);

        shell_exec(sprintf($patch, '-p1', '', $dl_to, $filename));
      }
    }
    catch (\Exception $e) {
      // Remove download directory on error, so after error fixing
      // the next update can go smoothly
      // @todo execute in native PHP
      shell_exec("rm -rf $dl_to");
      throw $e;
    }
  }

  public function getDownload() {
    return $this->download;
  }
}