<?php

namespace Webovac\Generator\Config;

use Stepapo\Utils\Attribute\ArrayOfType;
use Stepapo\Utils\Attribute\KeyProperty;
use Stepapo\Utils\Schematic;


class Entity extends Schematic
{
	#[KeyProperty] public string $name;
	public bool $withTraits = true;
	public bool $withConventions = false;
	#[ArrayOfType(Implement::class)] public array $entityImplements = [];
	#[ArrayOfType(Implement::class)] public array $repositoryImplements = [];
}