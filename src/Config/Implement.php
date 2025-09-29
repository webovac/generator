<?php

declare(strict_types=1);

namespace Webovac\Generator\Config;

use Stepapo\Utils\Attribute\KeyProperty;
use Stepapo\Utils\Attribute\ValueProperty;
use Stepapo\Utils\Config;


class Implement extends Config
{
	#[KeyProperty] public string $class;
	#[ValueProperty] public array $requires = [];
}