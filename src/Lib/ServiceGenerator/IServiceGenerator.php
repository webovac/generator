<?php

declare(strict_types=1);

namespace Webovac\Generator\Lib\ServiceGenerator;

use Stepapo\Utils\Factory;
use Webovac\Generator\Config\Module;
use Webovac\Generator\Config\Service;


interface IServiceGenerator extends Factory
{
	function create(
		Service $service,
		?Module $module = null,
	): ServiceGenerator;
}