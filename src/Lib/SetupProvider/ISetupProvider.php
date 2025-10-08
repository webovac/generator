<?php

namespace Webovac\Generator\Lib\SetupProvider;

use Stepapo\Utils\Factory;
use Webovac\Generator\Config\Command;
use Webovac\Generator\Config\Component;
use Webovac\Generator\Config\Entity;
use Webovac\Generator\Config\Module;
use Webovac\Generator\Config\Service;


interface ISetupProvider extends Factory
{
	function create(
		?string $name = null,
		?Entity $entity = null,
		?Service $service = null,
		?Command $command = null,
		?Component $component = null,
		?Module $module = null,
	): SetupProvider;
}