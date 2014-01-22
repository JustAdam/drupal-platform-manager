<?php

namespace Dbmedialab\Drupal\Deploy\Modulefetch;

use Symfony\Component\Console\Command\Command;

abstract class ModuleFetchCommand extends Command {

  protected $asset_types = ['modules', 'libraries', 'themes'];
  

  public function __call($name, $args) {
    return call_user_func_array(array($this->getApplication(), $name), $args);
  }
}