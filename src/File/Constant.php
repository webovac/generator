<?php

declare(strict_types=1);

namespace Webovac\Generator\File;

use Stepapo\Utils\Attribute\ArrayOfType;
use Stepapo\Utils\Attribute\KeyProperty;
use Stepapo\Utils\Config;


class Constant extends Config
{
	#[KeyProperty] public string $name;
	public string $visibility = 'public';
	public ?string $comment;
	public mixed $value;
	public bool $final = false;
	public ?string $type = null;
	/** @var Attribute[] */ #[ArrayOfType(Attribute::class)] public array $attributes = [];
}