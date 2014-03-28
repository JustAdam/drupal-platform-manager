<?php

namespace Dbmedialab\Drupal\Deploy\Modulefetch\Command;

use Dbmedialab\Drupal\Deploy\Modulefetch\Command\ModuleFetch;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @method string getReleaseDirectory()
 * @method array getConfig(string name)
 * @method string getBaseDownloadDirectory()
 * @method void removeState(string type, string name, string state_hash)
 * @method void saveAssetsDownloadState()
 */
class CleanUp extends ModuleFetch {

  /**
   * Number of directories to keep in the releases folder when cleaning up.
   */
  protected $dirs = 5;


  protected function configure() {
    $this
      ->setName('cleanup')
      ->setDescription('Cleanup old releases')
      ->addOption('dirs', NULL, InputOption::VALUE_REQUIRED, 'Number of directories to keep'); 
  }

  protected function execute(InputInterface $input, OutputInterface $output) {


    if ($dirs = $input->getOption('dirs')) {
      $dir = filter_var($dirs, FILTER_VALIDATE_INT);
      if ($dir) {
        $this->dirs = $dir;
      } else {
        $output->writeln("<comment>Invalid option passed to --dirs.  Using to $this->dirs</comment>");
      }
    }

    if (!$this->cleanup($output)) {
      $output->writeln("<comment>No release folders require cleanup.</comment>");
    }
  }

  protected function cleanup(OutputInterface $output) {

    // Recursive directory iterator
    $dir_it = function ($dir) {
      return new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
             );
    };

    // Recursive directory deletion
    $rmdir = function ($dir) use (&$dir_it) {      
      foreach ($dir_it($dir) as $file) {
        if ($file->isLink()) {
          unlink($file->getPathname());
        } elseif ($file->isDir()) {
          rmdir($file->getPathname());
        } else {
          unlink($file->getPathname());
        }
      }
      rmdir($dir);
    };


    $further_cleanup_required = FALSE;
    $release_folder = $this->getReleaseDirectory();    
    $distributions = array_keys($this->getConfig('distribution_info'));

    // First, release directory cleanup
    foreach (new \DirectoryIterator($release_folder) as $file) {
      if ($file->isDot() || !$file->isDir() || $file->isLink()) {
        continue;
      }

      // Remove distributions which no longer exist (have been removed from the config file).
      if (!in_array($file->getBasename(), $distributions)) {
        $rmdir($file->getPathname());

        $further_cleanup_required = TRUE;
      } else {

        // Clean up timestamped release directories within each distribution.
        // The number to keep is specified by --dirs
        $directories = new \SplPriorityQueue;
        foreach (new \DirectoryIterator($file->getPathname()) as $dir) {
          if ($dir->isDot() || !$dir->isDir() || $dir->isLink()) {
            continue;
          }

          // Store directories keeping the last modified at the top.
          $directories->insert($dir->getPathname(), $dir->getCTime());  
        }

        // No further action is required for this directory.
        if ($directories->count() <= $this->dirs) {
          continue;
        }

        $further_cleanup_required = TRUE;
        
        // Timestamped release directories we want to keep
        for ($i = 0; $i < $this->dirs; $i++) {
          $directories->extract();
        }

        // Delete all the others
        $directories->top();
        while ($directories->valid()) {
          $dir = $directories->current();
          $rmdir($dir);
          $directories->next();
        }
      }
    }

    // No release directories were removed so no need to do any further cleanup.
    // (all other assets should be in use)
    if (FALSE == $further_cleanup_required) {
      return FALSE;
    }


    // Get a list of all assets that are in use (in use counts as being linked to
    // from a releases directory).
    $find_symlinks = function($dir) use (&$dir_it) {
      $active_symlinks = [];
      foreach ($dir_it($dir) as $file) {
        // Ignore latest folder symlink
        if ($file->getBasename() != 'latest' && $file->isLink()) {
          $active_symlinks[] = basename($file->getRealPath());
        }
      }
      return array_unique($active_symlinks);
    };

    // Find all assets that are in use
    $active_symlinks = $find_symlinks($release_folder);

    // Get a list of all assets that are downloaded
    $downloads = [];
    $base_download_dir = $this->getBaseDownloadDirectory();
    $d_it = new \DirectoryIterator($base_download_dir);
    foreach ($d_it as $file) {
      if (!$file->isDot() && $file->isDir()) {
        $downloads[] = $file->getBasename();
      }
    }

    // Calculate which asset folders need to be removed from the downloads directory.
    $to_delete = array_diff($downloads, $active_symlinks);
    if (!empty($to_delete)) {
      $assets = [];
      foreach ($to_delete as $dir) {
        $rmdir($base_download_dir . '/' .$dir);

        $parts = explode('-', $dir);
        if (count($parts) == 5) {
          $assets[] = $parts[1] . '-' . $parts[2] . '-' . $parts[3];
        } else {
          $assets[] = $parts[1] . '-' . $parts[2];
        }

        $hash = array_pop($parts);
        $this->removeState($parts[0], $parts[1], $hash);
      }

      $this->saveAssetsDownloadState();

      $output->writeln("<comment>Removed the following assets: " . join(',', $assets) . "</comment>");
    }
    return TRUE;
  }
}
