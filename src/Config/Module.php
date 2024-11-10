<?php

namespace Webovac\Generator\Config;

use Stepapo\Utils\Attribute\ArrayOfType;
use Stepapo\Utils\Attribute\KeyProperty;
use Stepapo\Utils\Schematic;


class Module extends Schematic
{
	#[KeyProperty] public string $name;
	public string $namespace = 'App\Module';
	public string $type = 'module';
	public bool $isPackage = false;
	public bool $withDIExtension = false;
	public bool $withMigrationGroup = false;
	public bool $withInstallGroups = false;
	public bool $withInstallFile = false;
	/** @var Component[] */ #[ArrayOfType(Component::class)] public array $components = [];
	/** @var Entity[] */ #[ArrayOfType(Entity::class)] public array $entities = [];
	/** @var Service[] */ #[ArrayOfType(Service::class)] public array $services = [];
}