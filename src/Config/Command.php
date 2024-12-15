<?php

namespace Webovac\Generator\Config;

use Stepapo\Utils\Attribute\KeyProperty;
use Stepapo\Utils\Config;


class Command extends Config
{
	#[KeyProperty] public string $name;
}