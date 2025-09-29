<?php

declare(strict_types=1);

namespace Webovac\Generator\Config;

use Stepapo\Utils\Attribute\KeyProperty;
use Stepapo\Utils\Config;


class Component extends Config
{
	#[KeyProperty] public string $name;
	public ?string $entity = null;
	public bool $withTemplateName = false;
	public ?string $type = null;
}