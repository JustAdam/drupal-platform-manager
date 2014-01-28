<?php

namespace Dbmedialab\Drupal\Deploy\Modulefetch\Downloader;


class Get implements ModuleDownloaderInterface {

  protected $download = NULL;
  protected $destination;
  protected $asset_name;


  public function getName() {
    return 'get';
  }

  public function get($from, $to) {

    //
    // If source directory already exists, then this asset (we assume) has already been downloaded.
    if (is_dir($to)) {
      return NULL;
    }

    $this->asset_name = $from['name'];
    $this->destination = $to;

    $s_url = escapeshellarg($from['url']);
    $local_file = $to . '-' . basename($from['url']);
    $s_local_file = escapeshellarg($local_file);

    $descriptor = [
      1 => ["pipe", "w"],
      2 => ["pipe", "w"]
    ];
    $pipes = [];

    $command = "/usr/bin/env wget -nv $s_url -O $s_local_file";

    $cmd = proc_open($command, $descriptor, $pipes);
    if (is_resource($cmd)) {
      $response = stream_get_contents($pipes[2]);

      $success = strpos($response, "-> \"$local_file\"");

      foreach ($descriptor as $pipe => $d) {
        fclose($pipes[$pipe]);
      }

      $cmd_return = proc_close($cmd);

      if ($success > 1) {

        $this->download = $to;

        $this->applyPatches([$local_file]);

        return TRUE;
      } else {
        return FALSE;
      }
    }
    throw new \Exception('Failed creating wget process.');
  }

  /**
   * Hijacking the apply patches method to handle tarball extraction.
   * Assuming that most files downloaded with this class will be tarballs
   * with ready made code that does not need altering.
   * @todo implement better archive extraction pattern.
   */
  public function applyPatches(array $patches) {

    $file = $patches[0];
  
    $finfo = new \finfo(FILEINFO_MIME_TYPE);
    $type = $finfo->file($file);

    $s_file = escapeshellarg($file);
    $destination = dirname($this->destination);
    $s_destination = escapeshellarg($destination);

    switch ($type) {
      case 'application/zip':
        $command = "/usr/bin/env unzip $s_file -d $s_destination";
        $regexp = "!creating: $destination/(.*?)/!";
      break;
      case 'application/x-gzip':
        $command = "/usr/bin/env tar xvfz $s_file -C $s_destination";
        $regexp = "!^(.*?)/!";
      break;
      default:
        echo "Mime type $type not found", PHP_EOL;
        return;
    }

    if ($command) {

      $output = shell_exec($command);

      // Remove archive file
      unlink($file);

      // Try to find possible output directory
      preg_match($regexp, $output, $matches);

      if (!empty($matches[1]) && is_dir($destination . '/' . $matches[1])) {
        rename($destination . '/' . $matches[1], $this->destination);
      } else {
        throw new \Exception("Unable to find extraction directory.");
      }
    }
  }

  public function getDownload() {
    return $this->download;
  }
}
