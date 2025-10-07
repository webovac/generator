<?php

declare(strict_types=1);

namespace Webovac\Generator\File;


class PromotedParameter extends Parameter
{
	public bool $final = false;
	public ?string $visibility = 'public';
	public bool $hide = false;
}