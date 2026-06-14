<?php

declare(strict_types=1);

namespace Webovac\Generator\Config;

use Stepapo\Utils\Attribute\KeyProperty;
use Stepapo\Utils\Attribute\ValueProperty;
use Stepapo\Utils\Config;


class Requirement extends Config
{
	#[KeyProperty] public string $tag;
	#[ValueProperty] public string $method;
}