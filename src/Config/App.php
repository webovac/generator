<?php

namespace Webovac\Generator\Config;

use Stepapo\Utils\Attribute\ArrayOfType;
use Stepapo\Utils\Config;


class App extends Config
{
	public string $namespace = 'App';
	/** @var Component[] */ #[ArrayOfType(Component::class)] public array $components = [];
	/** @var Entity[] */ #[ArrayOfType(Entity::class)] public array $entities = [];
	/** @var Service[] */ #[ArrayOfType(Service::class)] public array $services = [];
	/** @var Command[] */ #[ArrayOfType(Command::class)] public array $commands = [];
	/** @var Module[] */ #[ArrayOfType(Module::class)] public array $modules = [];
}