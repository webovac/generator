<?php

declare(strict_types=1);

namespace Webovac\Generator\Lib\PropertyGenerator;

use Stepapo\Model\Definition\Config\Table;
use Stepapo\Utils\Factory;


interface IPropertyGenerator extends Factory
{
	function create(string $name, Table $table): PropertyGenerator;
}