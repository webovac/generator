<?php

declare(strict_types=1);

namespace Webovac\Generator\File;

use Stepapo\Utils\Attribute\ArrayOfType;
use Stepapo\Utils\Attribute\KeyProperty;
use Stepapo\Utils\Attribute\ToArray;
use Stepapo\Utils\Config;


class Property extends Config
{
	#[KeyProperty] public string $name;
	public ?string $visibility = 'public';
	#[ToArray] public array $comments = [];
	public bool $hasValue = false;
	public mixed $value;
	public bool $final = false;
	public bool $static = false;
	public ?string $type = null;
	public bool $nullable = false;
	public bool $abstract = false;
	/** @var Attribute[] */ #[ArrayOfType(Attribute::class)] public array $attributes = [];
	public bool $hide = false;
}