<?php

namespace Webovac\Generator\Config;

use Stepapo\Utils\Attribute\KeyProperty;
use Stepapo\Utils\Schematic;


class Command extends Schematic
{
	#[KeyProperty] public string $name;
}