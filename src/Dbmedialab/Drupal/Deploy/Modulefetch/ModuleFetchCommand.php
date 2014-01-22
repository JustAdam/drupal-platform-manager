<?php

namespace Dbmedialab\Drupal\Deploy\Modulefetch;

use Symfony\Component\Console\Command\Command;

abstract class ModuleFetchCommand extends Command {

  public function __call($name, $args) {
    return call_user_func_array(array($this->getApplication(), $name), $args);
  }
}