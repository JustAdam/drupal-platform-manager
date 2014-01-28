<?php

namespace Dbmedialab\Drupal\Deploy\Modulefetch\Downloader;


class Git implements ModuleDownloaderInterface {

  protected $download = NULL;
  protected $destination;
  protected $asset_name;


  public function getName() {
    return 'git';
  }

  public function get($from, $to) {

    //
    // If source directory already exists, then this asset (we assume) has already been downloaded.
    if (is_dir($to)) {
      return NULL;
    }

    $this->asset_name = $from['name'];
    $this->destination = $to;

    $s_module = escapeshellarg($from['url']);
    $s_to = escapeshellarg($this->destination);

    $descriptor = [
      1 => ["pipe", "w"],
      2 => ["pipe", "w"]
    ];
    $pipes = [];

    $command = "/usr/bin/env git clone -q $s_module $s_to";

    $cmd = proc_open($command, $descriptor, $pipes);
    if (is_resource($cmd)) {
      $error = stream_get_contents($pipes[2]);

      foreach ($descriptor as $pipe => $d) {
        fclose($pipes[$pipe]);
      }

      $cmd_return = proc_close($cmd);

      if (strpos($error, 'fatal:')) {
        return FALSE;
      }

      $this->download = $to;

      if (!empty($from['revision'])) {
        
        $cwd = new \SplStack;
        $cwd->push(getcwd());
        chdir($this->destination);

        // @todo error checking that this was sucessful or not.
        $commit = escapeshellarg($from['revision']);
        shell_exec("/usr/bin/env git reset --hard $commit");

        chdir($cwd->pop());
      }

      return TRUE;
    }
    throw new \Exception('Failed creating git process.');
  }

  public function applyPatches(array $patches) {
    echo 'To be implemeted ...', PHP_EOL;
    print_r($patches);
  }

  public function getDownload() {
    return $this->download;
  }
}
