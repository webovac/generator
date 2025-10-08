<?php

declare(strict_types=1);

namespace Webovac\Generator\Lib\ModelGenerator;

use Stepapo\Utils\Factory;
use Webovac\Generator\Config\Entity;
use Webovac\Generator\Config\Module;


interface IModelGenerator extends Factory
{
	function create(
		Entity $entity,
		?Module $module = null,
	): ModelGenerator;
}