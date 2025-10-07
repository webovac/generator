<?php

namespace Webovac\Generator\File;

use Stepapo\Utils\Attribute\KeyProperty;
use Stepapo\Utils\Config;


class Name extends Config
{
	#[KeyProperty] public string $name;
	public bool $hide = false;
}