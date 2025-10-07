<?php

declare(strict_types=1);

namespace Webovac\Generator\File;

use Stepapo\Utils\Attribute\ArrayOfType;
use Stepapo\Utils\Attribute\KeyProperty;
use Stepapo\Utils\Config;


class Property extends Config
{
	#[KeyProperty] public string $name;
	public string $visibility = 'public';
	public ?string $comment;
	public mixed $value = null;
	public bool $final = false;
	public ?string $type = null;
	public bool $nullable = false;
	public bool $abstract = false;
	/** @var Attribute[] */ #[ArrayOfType(Attribute::class)] public array $attributes = [];
}