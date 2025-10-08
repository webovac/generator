<?php

declare(strict_types=1);

namespace Webovac\Generator\Lib\BuildGenerator;

use Stepapo\Utils\Factory;


interface IBuildGenerator extends Factory
{
	function create(): BuildGenerator;
}