<?php

namespace Webovac\Generator\Config;

use Stepapo\Utils\Attribute\KeyProperty;
use Stepapo\Utils\Attribute\ValueProperty;
use Stepapo\Utils\Schematic;


class Implement extends Schematic
{
	#[KeyProperty] public string $class;
	#[ValueProperty] public array $requires = [];
}