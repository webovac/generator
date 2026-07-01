<?php

declare(strict_types=1);

namespace Webovac\Generator\Config;

use Stepapo\Utils\Config;


class Override extends Config
{
	/** @var class-string */ public string $trait;
	public string $method;
}
