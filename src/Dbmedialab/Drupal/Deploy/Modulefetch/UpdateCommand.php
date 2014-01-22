<?php

namespace Dbmedialab\Drupal\Deploy\Modulefetch;

use Dbmedialab\Drupal\Deploy\Modulefetch\ModuleFetchCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class UpdateCommand extends ModuleFetchCommand {

  protected $active_release_folder;


  //
  protected function configure() {
    $this
      ->setName('update')
      ->setDescription('Update modules (according to latest changes)')
      ->addOption('create-release', NULL, InputOption::VALUE_NONE, 'Create a new release only'); 
      // --rebuild-asset=name
  }

  // @todo check if installed or not (- can't update without being installed)
  protected function execute(InputInterface $input, OutputInterface $output) {

    if ($input->getOption('create-release')) {
      $this->createRelease($output);
    } else {
      if ($this->updateAssets($output)) {
        $this->createRelease($output);
      }
    }
  }

  /**
   * Update asset configuration with default data if it is missing
   */
  private function updateAssetDefaultData($type, $name, $data) {
    $data['name'] = $name;
    if (empty($data['drupal_core'])) {
      $data['drupal_core'] = $this->getConfig('drupal_core');  
    }
    $data['type'] = $type;
    if (empty($data['method'])) {
      $data['method'] = $this->getConfig('method');
    }

    if (isset($data['patches']) && !is_array($data['patches'])) {
      $p = [];
      $p[] = $data['patches'];
      $data['patches'] = $p;
      unset($p);
    }

    return $data;
  }

  // @todo add support for having multiple versions of a module
  protected function updateAssets(OutputInterface $output) {
    //
    $saved = FALSE;

    $progress = $this->getHelperSet()->get('progress');

    foreach ($this->asset_types as $type) {
      $assets = $this->getAssets($type);

      if (empty($assets)) {
        $output->writeln("<comment>Skipping $type as it contains 0 assets</comment>");
        continue;
      }

      // Figure out what needs to be downloaded.
      //
      // Downloading an asset will create a unique directory based on the options passed to the download.
      // This means an asset will have a different installation directory for each time update is run when it has a different configuration.
      // Thus there will be multiple directories for the same asset each time:
      // - the version number increments (or decrement)
      // - a patch is added (or removed)
      // - download method is changed
      // This criteria is kept in a hash generated by $app->genStateHash() and is stored in the state file
      $to_download = array();
      foreach ($assets as $name => $data) {

        // Populate data correctly.
        $data = $this->updateAssetDefaultData($type, $name, $data);

        // Required value checks for particular download methods
        // @todo move logic to the relevant downloader class. (->hasRequiredValues($from))
        if ($data['method'] == 'drush' && empty($data['version'])) {
          $output->writeln("<error>$type: $name is missing required version</error>");
          continue;
        } elseif ($data['method'] == 'git' && empty($data['url'])) {
          $output->writeln("<error>$type: $name is missing required url</error>");
          continue;
        } elseif ($data['method'] == 'get' && empty($data['url'])) {
          $output->writeln("<error>$type: $name is missing required url</error>");
          continue;
        }
   

        $current_state = $this->getState($type, $name);
        $new_state = $this->genStateHash($data);

        if (!$this->stateExists($new_state, $current_state)) {
          // Temp variable to pass to generate download location.
          // $data is later passed to genStateHash() which doesn't use the hash variable
          $loc_info = $data;
          $loc_info['hash'] = $new_state;
          $to_download[$name]['to'] = $this->getDownloadToLocation($loc_info);
          $to_download[$name]['from'] = $data;
          unset($loc_info);
        } 
      }

      if (empty($to_download)) {
        $output->writeln("<comment>No $type require updating</comment>");
        continue;
      }

      $output->writeln("<options=bold>Downloading $type</options=bold>");

      $progress->start($output, count($to_download));
      $progress->display();

      $errors = array();
      $success[$type] = array();
      foreach ($to_download as $name => $data) {
        //
        $downloader = $this->getDownloader($data['from']['method']);
        $status = $downloader->get($data['from'], $data['to']);

        // If directory already exists (NULL) then we assume this asset version is already
        // downloaded, but has been lost from the current state.  So we add it back.
        if ($status === TRUE || $status === NULL) {
          $this->updateState($type, $name, $data['from']);
          $saved = TRUE;
        } else {
          $errors[] = $name;
        }

        $progress->advance();
      }

      $progress->finish();

      if (!empty($errors)) {
        $output->writeln("<error>Failed downloading the following $type: " . join(', ', $errors) . '</error>');
      }
    }


    if (TRUE === $saved) {
      $this->saveAssetsDownloadState();
    }

    // Only return true if no errors were encountered.  If there is an error we don't want to build a release.
    return empty($errors) && TRUE === $saved;
  }

  protected function createRelease(OutputInterface $output) {

    $output->writeln('<options=bold>Creating release</options=bold>');

    $cwd = new \Splstack();
    $release_dir = $this->getReleaseDirectory();
    $cwd->push(getcwd());
    chdir($release_dir);

    // Timestamped folder name for this release
    $dir = (string) microtime(TRUE); //date('YmdGis');
    mkdir($dir);

    $cwd->push(getcwd());
    chdir($dir);
    $this->active_release_folder = getcwd();


    $asset_setup = function($asset) use (&$cwd) {
      //
      mkdir($asset['base']);

      if (!empty($asset['subdir'])) {
        $cwd->push(getcwd());
        chdir($asset['base']);

        foreach ($asset['subdir'] as $directory) {
          mkdir($directory);
        }

        chdir($cwd->pop());
      }
    };


    $directories = $this->getConfig('directories');

    foreach ($this->asset_types as $type) {
      // Set up directories for this asset.
      $asset_setup($directories[$type]);
      // 
      $assets = $this->getAssets($type);
      if (empty($assets)) {
        $output->writeln("<comment>Skipping $type as it is empty</comment>");
        continue;
      }

      $errors = [];

      foreach ($assets as $name => $data) {
        // Check if asset is actually installed before adding it to the build.
        if ($this->getState($type, $name)) {

          $data = $this->updateAssetDefaultData($type, $name, $data);
          $data['hash'] = $this->genStateHash($data);

          $asset_folder = $this->getDownloadToLocation($data);
          if (!is_dir($asset_folder)) {
            //$this->deleteState($type, $name);
            throw new \DomainException("Asset folder for $name does not exists ($asset_folder)");
          }

          $asset_dirs = $this->getAssetDirectory($type);
          $directory = isset($data['subdir']) ? $data['subdir'] : 'base';
          if (!isset($asset_dirs[$directory])) {
            throw new \RuntimeException("Asset $name specifies directory of $directory but it is not defined.");
          }

          $link = $this->active_release_folder . '/' . $asset_dirs[$directory] . '/' . $name;
          if (!symlink($asset_folder, $link)) {
            throw new \Exception("Failed creating symlink for $name ($asset_folder to $link)");
          }

        } else {
          $errors[] = $name;
        }
      }

      if (!empty($errors)) {
        $output->writeln("<error>The following $type were not included in the release: " . join(', ', $errors) . '</error>');
      }
    }

    // Symlink to latest release folder
    if (file_exists($release_dir  . '/latest')) {
      unlink($release_dir  . '/latest');
    }
    symlink($this->active_release_folder, $release_dir  . '/latest');

    $output->writeln(" at: <comment>$this->active_release_folder</comment>");

    chdir($cwd->bottom());
  }
}
