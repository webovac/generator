<?php

namespace Webovac\Generator\Config;

use Stepapo\Utils\Attribute\KeyProperty;
use Stepapo\Utils\Schematic;


class Entity extends Schematic
{
	#[KeyProperty] public string $name;
	public bool $withTraits = false;
	public bool $withConventions = false;
}