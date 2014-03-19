<?php

namespace Dbmedialab\Drupal\Deploy\Modulefetch\Downloader;


class Git implements ModuleDownloaderInterface {

  protected $download = NULL;
  protected $destination;
  protected $asset_name;


  public function getName() {
    return 'git';
  }

  public function hasRequiredData(array $data) {
    return !empty($data['url']);
  }

  public function get($from, $to) {

    if (is_dir($to)) {
      // If revision is missing, then we need to fetch the latest version of the repository
      if (empty($from['revision'])) {
        $cwd = getcwd();
        chdir($to);
        shell_exec("/usr/bin/env git pull 2>&1");
        chdir($cwd);
        return TRUE;
      }
      // Otherwise we assume this asset is already downloaded.
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

      // Reset to particular revision.
      if (!empty($from['revision'])) {
        
        $cwd = new \SplStack;
        $cwd->push(getcwd());
        chdir($this->destination);

        $commit = escapeshellarg($from['revision']);
        $command = "/usr/bin/env git reset --hard $commit";
        $pipes = [];

        $cmd = proc_open($command, $descriptor, $pipes);
        if (is_resource($cmd)) {
          $error = stream_get_contents($pipes[2]);

          foreach ($descriptor as $pipe => $d) {
            fclose($pipes[$pipe]);
          }

          $cmd_return = proc_close($cmd);

          chdir($cwd->pop());

          if (strpos($error, 'fatal:') !== FALSE) {
            shell_exec("rm -rf $this->destination");
            return FALSE;
          }
        } else {
          throw new \Exception('Failed creating git process.');
        }
      }

      if (!empty($from['patches'])) {
        $this->applyPatches($from['patches']);
      }

      return TRUE;
    }
    throw new \Exception('Failed creating git process.');
  }

  public function applyPatches(array $patches) {

    $context = stream_context_create(['http' => ['method' => 'GET', "user_agent" => 'dpm patch downloaer']]);

    try {
      foreach ($patches as $patch) {

        // download patch
        $file = file_get_contents($patch, FALSE, $context);
        if (!$file) {
          throw new \Exception("Failed downloading patch $patch.");
        }
        $filename = $this->destination . '/' . sha1(basename($patch)) . '.patch';
        file_put_contents($filename, $file);

        //$patch = "patch -p1 --dry-run -s -d $dl_to < $filename";
        $patch = "patch %s %s -s -d %s < %s";
        $test_patch = popen(sprintf($patch, '-p1', '--dry-run', $this->destination, $filename), 'r');
        // If we get output then something has gone wrong
        if (fgets($test_patch)) {
          pclose($test_patch);
          throw new \Exception("Patching of $filename failed.");
        }
        pclose($test_patch);

        shell_exec(sprintf($patch, '-p1', '', $this->destination, $filename));
      }
    }
    catch (\Exception $e) {
      // Remove download directory on error, so after error fixing
      // the next update can go smoothly
      shell_exec("rm -rf $this->destination");
      throw $e;
    }
  }

  public function getDownload() {
    return $this->download;
  }
}
