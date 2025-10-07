<?php

declare(strict_types=1);

namespace Webovac\Generator\File;

use Stepapo\Utils\Attribute\ArrayOfType;
use Stepapo\Utils\Attribute\KeyProperty;
use Stepapo\Utils\Attribute\ToArray;
use Stepapo\Utils\Config;


class Method extends Config
{
	#[KeyProperty] public string $name;
	public bool $final = false;
	public bool $static = false;
	public bool $abstract = false;
	public ?string $visibility = 'public';
	#[ToArray] public array $body = [];
	public ?string $returnType = null;
	public bool $returnNullable = false;
	#[ToArray] public array $comments = [];
	/** @var Attribute[] */ #[ArrayOfType(Attribute::class)] public array $attributes = [];
	/** @var Parameter[] */ #[ArrayOfType(Parameter::class)] public array $parameters = [];
	/** @var PromotedParameter[] */ #[ArrayOfType(PromotedParameter::class)] public array $promotedParameters = [];
	public bool $hide = false;
}