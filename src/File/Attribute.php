<?php

declare(strict_types=1);

namespace Webovac\Generator\File;

use Stepapo\Utils\Attribute\KeyProperty;
use Stepapo\Utils\Attribute\ValueProperty;
use Stepapo\Utils\Config;


class Attribute extends Config
{
	#[KeyProperty] public string $name;
	/** @var mixed[] */ #[ValueProperty] public array $args = [];
}