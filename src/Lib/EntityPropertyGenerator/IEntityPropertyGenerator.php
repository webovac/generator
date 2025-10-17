<?php

declare(strict_types=1);

namespace Webovac\Generator\Lib\EntityPropertyGenerator;

use Stepapo\Model\Definition\Config\Table;
use Stepapo\Utils\Factory;


interface IEntityPropertyGenerator extends Factory
{
	function create(string $name, Table $table): EntityPropertyGenerator;
}