<?php

declare(strict_types=1);

namespace Webovac\Generator\File;

use Stepapo\Utils\Attribute\ArrayOfType;
use Stepapo\Utils\Attribute\KeyProperty;
use Stepapo\Utils\Config;


class Parameter extends Config
{
	#[KeyProperty] public string $name;
	public ?string $type = null;
	public bool $nullable = false;
	public bool $hasDefaultValue = false;
	public mixed $defaultValue = null;
	/** @var Attribute[] */ #[ArrayOfType(Attribute::class)] public array $attributes = [];
}