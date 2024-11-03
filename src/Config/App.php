<?php

namespace Webovac\Generator\Config;

use Stepapo\Utils\Attribute\ArrayOfType;
use Stepapo\Utils\Schematic;


class App extends Schematic
{
	public string $namespace = 'App';
	/** @var Component[] */ #[ArrayOfType(Component::class)] public array $components = [];
	/** @var Entity[] */ #[ArrayOfType(Entity::class)] public array $entities = [];
	/** @var Service[] */ #[ArrayOfType(Service::class)] public array $services = [];
	/** @var Module[] */ #[ArrayOfType(Module::class)] public array $modules = [];
}