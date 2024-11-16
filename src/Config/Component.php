<?php

namespace Webovac\Generator\Config;

use Stepapo\Utils\Attribute\KeyProperty;
use Stepapo\Utils\Schematic;


class Component extends Schematic
{
	#[KeyProperty] public string $name;
	public ?string $entityName = null;
	public bool $withTemplateName = false;
	public ?string $type = null;
}