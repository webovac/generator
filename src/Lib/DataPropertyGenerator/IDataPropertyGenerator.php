<?php

declare(strict_types=1);

namespace Webovac\Generator\Lib\DataPropertyGenerator;

use Stepapo\Model\Definition\Config\Table;
use Stepapo\Utils\Factory;


interface IDataPropertyGenerator extends Factory
{
	function create(string $name, Table $table): DataPropertyGenerator;
}