<?php

declare(strict_types=1);

namespace Webovac\Generator\Lib\ComponentGenerator;

use Stepapo\Utils\Factory;
use Webovac\Generator\Config\Component;
use Webovac\Generator\Config\Module;


interface IComponentGenerator extends Factory
{
	function create(
		Component $component,
		?Module $module = null,
	): ComponentGenerator;
}