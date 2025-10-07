<?php

declare(strict_types=1);

namespace Webovac\Generator\File;

use Stepapo\Utils\Attribute\ArrayOfType;
use Stepapo\Utils\Attribute\KeyProperty;
use Stepapo\Utils\Attribute\ToArray;
use Stepapo\Utils\Config;


class Parameter extends Config
{
	#[KeyProperty] public string $name;
	public ?string $type = null;
	public bool $nullable = false;
	public bool $hasValue = false;
	public mixed $value = null;
	#[ToArray] public array $comments = [];
	/** @var Attribute[] */ #[ArrayOfType(Attribute::class)] public array $attributes = [];
	public bool $hide = false;
}