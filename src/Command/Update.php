<?php

namespace Dbmedialab\Drupal\Deploy\Modulefetch\Command;

use Dbmedialab\Drupal\Deploy\Modulefetch\Command\ModuleFetch;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class Update extends ModuleFetch {

  protected $active_release_folder;

  protected $distribution_core;

  //
  protected function configure() {
    $this
      ->setName('update')
      ->setDescription('Update modules (according to latest changes)')
      ->addArgument('distributions', InputArgument::IS_ARRAY, 'Distribution(s) to use.')
      ->addOption('create-release', NULL, InputOption::VALUE_NONE, 'Create a new release only');
      // --rebuild-asset=name
  }

  // @todo check if installed or not (- can't update without being installed)
  protected function execute(InputInterface $input, OutputInterface $output) {

    if ($distributions = $input->getArgument('distributions')) {
      $loaded = $this->getDistributions();
      foreach ($distributions as $distribution) {
        if (!in_array($distribution, $loaded)) {
          throw new \InvalidArgumentException("Supplied argument is not a valid distribution.");
        }
      }
      unset($loaded);
      unset($distribution);
    } else {
      $distributions = $this->getDistributions();
    }

    $create_release_only = $input->getOption('create-release');
    foreach ($distributions as $distribution) {

      // Set the Drupal core version being used by this distribution
      $this->distribution_core = $this->getAssets('info', $distribution)['main_version'];

      if ($create_release_only) {
        $this->createRelease($output, $distribution);
      } else {
        if ($this->updateAssets($output, $distribution)) {
          $this->createRelease($output, $distribution);
        }
      }
    }
  }

  /**
   * Update asset configuration with default data if it is missing
   */
  private function updateAssetDefaultData($type, $name, $data) {
    $data['name'] = $name;
    // If no core is specified then we default to that specified in the core config.yml
    if (empty($data['drupal_core'])) {
      $data['drupal_core'] = $this->distribution_core;
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

  /**
   * @param OutputInterface
   * @param string Distribution name
   */
  protected function updateAssets(OutputInterface $output, $distribution) {
    //
    $output->writeln("<comment>Updating distribution <info>$distribution</info></comment>");

    $saved = FALSE;
    $progress = $this->getHelperSet()->get('progress');


    foreach ($this->asset_types as $type) {
      $assets = $this->getAssets($type, $distribution);

      if (empty($assets)) {
        $output->writeln("  <comment>Skipping <info>$type</info> as it contains 0 assets</comment>");
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
          $output->writeln("  <error>$type: $name is missing required version</error>");
          continue;
        } elseif ($data['method'] == 'git' && empty($data['url'])) {
          $output->writeln("  <error>$type: $name is missing required url</error>");
          continue;
        } elseif ($data['method'] == 'get' && empty($data['url'])) {
          $output->writeln("  <error>$type: $name is missing required url</error>");
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
        $output->writeln("  <comment>No <info>$type</info> require updating</comment>");
        continue;
      }

      $output->writeln("  <options=bold>Downloading $type</options=bold>");

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
        $output->writeln("  <error>Failed downloading the following $type: " . join(', ', $errors) . '</error>');
      }
    }


    if (TRUE === $saved) {
      $this->saveAssetsDownloadState();
    }

    // Only return true if no errors were encountered.  If there is an error we don't want to build a release.
    return empty($errors) && TRUE === $saved;
  }

  protected function createRelease(OutputInterface $output, $distribution) {

    $output->writeln("<options=bold>Creating release for <info>$distribution</info></options=bold>");


    $cwd = new \Splstack();
    $release_dir = $this->getReleaseDirectory();
    $cwd->push(getcwd());
    chdir($release_dir);

    // Create distribution folder if it does not yet exist
    if (!file_exists($distribution)) {
      mkdir($distribution);
    }
    $cwd->push(getcwd());
    chdir($distribution);
    $release_dir .= '/' . $distribution;

    // Timestamped folder name for this release
    $dir = (string) microtime(TRUE); //date('YmdGis');
    mkdir($dir);

    $cwd->push(getcwd());
    chdir($dir);
    $this->active_release_folder = getcwd();


    // Get Distribution build info
    $d_b_info = $this->getAssets('info', $distribution);

    // Build core drupal site.
    $assets = $this->getAssets('core', $distribution);
    $data = $this->updateAssetDefaultData('core', 'drupal', $assets['drupal']);
    $data['hash'] = $this->genStateHash($data);

    $core_folder = $this->getDownloadToLocation($data);

    foreach (new \DirectoryIterator($core_folder) as $file) {
      if ($file->isDot()) {
        continue;
      }

      symlink($file->getPathname(), $file->getFilename());
    }

    // Recreate sites directory local to this build.
    unlink('sites');

    mkdir('sites');
    $cwd->push(getcwd());
    chdir('sites');

    // Link to override files (these we usually be Drupal files which are kept outside of core and in another repo)
    if (!empty($d_b_info['overrides'])) {
      foreach ($d_b_info['overrides'] as $link => $file) {
        symlink($file, $link);
      }  
    }

    // Add sites which are using this distro.
    foreach ($d_b_info['site_building'] as $site => $info) {
      symlink($info['source'], $site);
    }

    mkdir('all');
    $cwd->push(getcwd());
    chdir('all');


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

    // Create all asset directories (within sites/all)
    $directories = $this->getConfig('directories');
    foreach ($this->asset_types as $type) {
      if ('core' == $type) {
        continue;
      }
      // Set up directories for this asset.
      $asset_setup($directories[$type]);
      // 
      $assets = $this->getAssets($type, $distribution);
      if (empty($assets)) {
        $output->writeln("  <comment>Skipping $type as it is empty</comment>");
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

          $link = $asset_dirs[$directory] . '/' . $name;
          if (!symlink($asset_folder, $link)) {
            throw new \Exception("Failed creating symlink for $name ($asset_folder to $link)");
          }

        } else {
          $errors[] = $name;
        }
      }

      if (!empty($errors)) {
        $output->writeln("  <error>The following $type were not included in the release: " . join(', ', $errors) . '</error>');
      }
    }

    // Symlink to latest release folder
    if (file_exists($release_dir  . '/latest')) {
      unlink($release_dir  . '/latest');
    }
    symlink($this->active_release_folder, $release_dir  . '/latest');

    $output->writeln("  at: <comment>$this->active_release_folder</comment>");

    chdir($cwd->bottom());
  }
}
