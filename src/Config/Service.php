<?php

namespace Webovac\Generator\Config;

use Stepapo\Utils\Attribute\KeyProperty;
use Stepapo\Utils\Config;


class Service extends Config
{
	#[KeyProperty] public string $name;
}