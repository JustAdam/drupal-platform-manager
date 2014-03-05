<?php

namespace Dbmedialab\Drupal\Deploy\Modulefetch\Command;

use Symfony\Component\Console\Command\Command;

abstract class ModuleFetch extends Command {

  protected $asset_types = ['core', 'modules', 'libraries', 'themes'];
  

  public function __call($name, $args) {
    return call_user_func_array(array($this->getApplication(), $name), $args);
  }
}