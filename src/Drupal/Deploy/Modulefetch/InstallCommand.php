<?php

namespace Dbmedialab\Drupal\Deploy\Modulefetch;

use Dbmedialab\Drupal\Deploy\Modulefetch\ModuleFetchCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
 

class InstallCommand extends ModuleFetchCommand {
  //

  protected function configure() {
    $this
      ->setName('install')
      ->setDescription('Initial setup and download.'); 
  }

  protected function execute(InputInterface $input, OutputInterface $output) {

    if ($this->isInstalled()) {
      $output->writeln('<error>install has already been run.</error>');
      return;
    }

    if ($this->setupDirectories()) {
      $output->writeln('<info>Directories set up complete.</info>');
    }

    $this->downloadEverything($output);
  }

  protected function setupDirectories() {
    
    $directories = $this->getConfig('directories');

    if (!file_exists($directories['base'])) {
      throw new \InvalidArgumentException('Base directory ' . $directories['base'] . ' doesn\'t exist');
    }

    if (!is_writable($directories['base'])) {
      throw new \RuntimeException('Base directory ' . $directories['base'] . ' is not writable.');
    }

    $cwd = new \SplStack();

    $cwd->push(getcwd());
    chdir($directories['base']);

    mkdir($directories['releases']);
    mkdir($directories['downloads']);
    
    chdir($cwd->pop());

    return TRUE;
  }

  protected function downloadEverything(OutputInterface $output) {
    $command = $this->find('update');
    $input = new ArrayInput(array('command' => 'update'));
    return $command->run($input, $output);
  }
}
