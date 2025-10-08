<?php

declare(strict_types=1);

namespace Webovac\Generator\Lib\BuildModelGenerator;

use Stepapo\Utils\Factory;
use Webovac\Generator\Config\Entity;


interface IBuildModelGenerator extends Factory
{
	function create(
		Entity $entity,
	): BuildModelGenerator;
}