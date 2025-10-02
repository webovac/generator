<?php

declare(strict_types=1);

namespace Webovac\Generator\Config;

use Stepapo\Utils\Attribute\ArrayOfType;
use Stepapo\Utils\Attribute\KeyProperty;
use Stepapo\Utils\Config;


class Entity extends Config
{
	#[KeyProperty] public string $name;
	public bool $withTraits = true;
	public bool $withConventions = false;
	#[ArrayOfType(Implement::class)] public array $entityImplements = [];
	#[ArrayOfType(Implement::class)] public array $repositoryImplements = [];
}