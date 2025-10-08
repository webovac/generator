<?php

declare(strict_types=1);

namespace Webovac\Generator\Lib\CommandGenerator;

use Stepapo\Utils\Factory;
use Webovac\Generator\Config\Command;
use Webovac\Generator\Config\Module;


interface ICommandGenerator extends Factory
{
	function create(
		Command $command,
		?Module $module = null,
	): CommandGenerator;
}