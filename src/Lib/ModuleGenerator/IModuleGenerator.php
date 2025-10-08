<?php

declare(strict_types=1);

namespace Webovac\Generator\Lib\ModuleGenerator;

use Stepapo\Utils\Factory;
use Webovac\Generator\Config\Module;


interface IModuleGenerator extends Factory
{
	function create(Module $module): ModuleGenerator;
}