<?php

declare(strict_types=1);

namespace Webovac\Generator\Lib\BuildModelGenerator;

use Stepapo\Utils\Factory;
use Webovac\Generator\Config\Entity;
use Webovac\Generator\Config\Module;


interface IBuildModelGenerator extends Factory
{
	function create(
		Entity $entity,
		?Module $module = null,
	): BuildModelGenerator;
}