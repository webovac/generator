<?php

declare(strict_types=1);

namespace Webovac\Generator\File;

use Nette\PhpGenerator\ClassType;
use Stepapo\Utils\Attribute\ArrayOfType;
use Stepapo\Utils\Config;


class File extends Config
{
	public string $name;
	public string $namespace;
	public string $type = ClassType::class;
	public ?string $comment = null;
	public ?string $extends = null;
	/** @var string[] */ public array $uses = [];
	/** @var string[] */ public array $implements = [];
	/** @var Attribute[] */ #[ArrayOfType(Attribute::class)] public array $attributes = [];
	/** @var Constant[] */ #[ArrayOfType(Constant::class)] public array $constants = [];
	/** @var Property[] */ #[ArrayOfType(Property::class)] public array $properties = [];
	/** @var Method[] */ #[ArrayOfType(Method::class)] public array $methods = [];
}