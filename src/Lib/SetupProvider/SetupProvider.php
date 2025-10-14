<?php

namespace Webovac\Generator\Lib\SetupProvider;

use Nette\InvalidArgumentException;
use Webovac\Generator\Config\Command;
use Webovac\Generator\Config\Component;
use Webovac\Generator\Config\Entity;
use Webovac\Generator\Config\Module;
use Webovac\Generator\Config\Service;
use Webovac\Generator\Lib\BuildGenerator\BuildGenerator;
use Webovac\Generator\Lib\BuildModelGenerator\BuildModelGenerator;
use Webovac\Generator\Lib\CommandGenerator\CommandGenerator;
use Webovac\Generator\Lib\ComponentGenerator\ComponentGenerator;
use Webovac\Generator\Lib\ModelGenerator\ModelGenerator;
use Webovac\Generator\Lib\ModuleGenerator\ModuleGenerator;
use Webovac\Generator\Lib\ServiceGenerator\ServiceGenerator;


class SetupProvider
{
	private ?string $lname;
	private ?string $moduleName;

	private string $moduleDir;
	private string $appModelDir;
	private string $buildModelDir;
	private string $appEntityDir;
	private string $buildEntityDir;
	private string $componentDir;
	private string $serviceDir;
	private string $commandDir;

	private string $moduleNamespace;
	private string $buildEntityNamespace;
	private string $appEntityNamespace;
	private string $componentNamespace;
	private string $commandNamespace;
	private string $serviceNamespace;

	private ?string $traitName;
	private ?string $templateName;


	public function __construct(
		private ?string $name,
		private ?Entity $entity,
		private ?Service $service,
		private ?Command $command,
		private ?Component $component,
		private ?Module $module,
		private string $appNamespace,
		private string $appDir,
		private string $buildNamespace,
		private string $buildDir,
	) {
		$this->lname = $this->name ? lcfirst($this->name) : null;
		$this->moduleName = $this->module?->name ?: null;
		$this->moduleDir = "$this->appDir/Module/$module?->name";
		$this->appModelDir = $module ? "{$this->moduleDir}/Model" : "$this->appDir/Model";
		$this->buildModelDir = "$this->buildDir/Model";
		$this->appEntityDir = "$this->appModelDir/$this->name";
		$this->buildEntityDir = "$this->buildModelDir/$this->name";
		$this->serviceDir = $module ? "{$this->moduleDir}/Lib" : "$this->appDir/Lib";
		$this->commandDir = $module ? "{$this->moduleDir}/Command" : "$this->appDir/Command";
		$this->componentDir = $module ? "{$this->moduleDir}/Control" : "$this->appDir/Control";

		if ($this->module) {
			$this->moduleNamespace = "{$this->module->namespace}\\$this->moduleName";
		}
		$this->buildEntityNamespace = "$this->buildNamespace\Model\\$this->name";
		$this->appEntityNamespace = $this->module?->namespace
			? "{$this->module->namespace}\\$this->moduleName\Model\\$this->name"
			: "$this->appNamespace\Model\\$this->name";
		$this->componentNamespace = $this->moduleName
			? "$this->appNamespace\Module\\$this->moduleName\Control\\$this->name"
			: "$this->appNamespace\Control\\$this->name";
		$this->commandNamespace = $this->moduleName
			? "$this->appNamespace\Module\\$this->moduleName\Command"
			: "$this->appNamespace\Command";
		$this->serviceNamespace = $this->moduleName
			? "$this->appNamespace\Module\\$this->moduleName\Lib"
			: "$this->appNamespace\Lib";

		$this->traitName = $this->entity?->withTraits && $this->module ? "{$this->module?->name}$this->name" : $this->name;
		$this->templateName = $this->component?->withTemplateName ? 'default' : $this->lname;
	}


	public function getBuildDir(): string
	{
		return $this->buildDir;
	}


	public function getBuildNamespace(): string
	{
		return $this->buildNamespace;
	}


	public function getBasePath(string $key): string
	{
		return match($key) {
			BuildGenerator::BASE_PRESENTER,
			BuildGenerator::BASE_PRESENTER_TEMPLATE => "$this->buildDir/Presenter",
			BuildGenerator::BASE_TEMPLATE => "$this->buildDir/Control",
			BuildGenerator::TEMPLATE_FACTORY => "$this->buildDir/Lib",
			BuildGenerator::MODEL,
			BuildGenerator::DATA_MODEL => "$this->buildDir/Model",

			ModuleGenerator::MODULE => $this->moduleDir,
			ModuleGenerator::PRESENTER_TRAIT,
			ModuleGenerator::PRESENTER_TEMPLATE_TRAIT => "$this->moduleDir/Presenter",
			ModuleGenerator::TEMPLATE_TRAIT => "$this->moduleDir/Control",
			ModuleGenerator::TEMPLATE_FACTORY_TRAIT => "$this->moduleDir/Lib",
			ModuleGenerator::MAIN_COMPONENT,
			ModuleGenerator::MAIN_FACTORY,
			ModuleGenerator::MAIN_TEMPLATE,
			ModuleGenerator::MAIN_LATTE => "$this->moduleDir/Control/$this->moduleName",
			ModuleGenerator::MODEL_TRAIT,
			ModuleGenerator::DATA_MODEL_TRAIT => "$this->moduleDir/Model",
			ModuleGenerator::DI_EXTENSION,
			ModuleGenerator::CONFIG_NEON => "$this->moduleDir/DI",
			ModuleGenerator::DEFINITION_NEON => "$this->moduleDir/config/definitions",
			ModuleGenerator::MANIPULATION_NEON => "$this->moduleDir/config/manipulations",

			BuildModelGenerator::ENTITY,
			BuildModelGenerator::MAPPER,
			BuildModelGenerator::REPOSITORY,
			BuildModelGenerator::DATA_OBJECT,
			BuildModelGenerator::DATA_REPOSITORY => "$this->buildEntityDir",

			ModelGenerator::ENTITY_TRAIT,
			ModelGenerator::MAPPER_TRAIT,
			ModelGenerator::REPOSITORY_TRAIT,
			ModelGenerator::DATA_OBJECT_TRAIT,
			ModelGenerator::DATA_REPOSITORY_TRAIT,
			ModelGenerator::CONVENTIONS => "$this->appEntityDir",

			ComponentGenerator::TEMPLATE,
			ComponentGenerator::CONTROL,
			ComponentGenerator::FACTORY,
			ComponentGenerator::LATTE,
			ComponentGenerator::DATASET_NEON,
			ComponentGenerator::MENU_NEON => "$this->componentDir/$this->name",

			CommandGenerator::COMMAND => "$this->commandDir",
			ServiceGenerator::SERVICE => "$this->serviceDir",

			default => throw new InvalidArgumentException("'$key' base path is not defined"),
		};
	}


	public function getExtension(string $key): string
	{
		return match($key) {
			BuildGenerator::BASE_PRESENTER,
			BuildGenerator::BASE_PRESENTER_TEMPLATE,
			BuildGenerator::BASE_TEMPLATE,
			BuildGenerator::TEMPLATE_FACTORY,
			BuildGenerator::MODEL,
			BuildGenerator::DATA_MODEL,

			ModuleGenerator::MODULE,
			ModuleGenerator::PRESENTER_TRAIT,
			ModuleGenerator::PRESENTER_TEMPLATE_TRAIT,
			ModuleGenerator::TEMPLATE_TRAIT,
			ModuleGenerator::TEMPLATE_FACTORY_TRAIT,
			ModuleGenerator::MAIN_COMPONENT,
			ModuleGenerator::MAIN_FACTORY,
			ModuleGenerator::MAIN_TEMPLATE,
			ModuleGenerator::MODEL_TRAIT,
			ModuleGenerator::DATA_MODEL_TRAIT,
			ModuleGenerator::DI_EXTENSION,

			BuildModelGenerator::ENTITY,
			BuildModelGenerator::MAPPER,
			BuildModelGenerator::REPOSITORY,
			BuildModelGenerator::DATA_OBJECT,
			BuildModelGenerator::DATA_REPOSITORY,

			ModelGenerator::ENTITY_TRAIT,
			ModelGenerator::MAPPER_TRAIT,
			ModelGenerator::REPOSITORY_TRAIT,
			ModelGenerator::DATA_OBJECT_TRAIT,
			ModelGenerator::DATA_REPOSITORY_TRAIT,
			ModelGenerator::CONVENTIONS,

			ComponentGenerator::TEMPLATE,
			ComponentGenerator::CONTROL,
			ComponentGenerator::FACTORY,

			CommandGenerator::COMMAND,
			ServiceGenerator::SERVICE => "php",

			ModuleGenerator::MAIN_LATTE,
			ComponentGenerator::LATTE => "latte",
			ModuleGenerator::CONFIG_NEON,
			ModuleGenerator::DEFINITION_NEON,
			ModuleGenerator::MANIPULATION_NEON,
			ComponentGenerator::DATASET_NEON,
			ComponentGenerator::MENU_NEON => "neon",

			default => throw new InvalidArgumentException("'$key' extension is not defined"),
		};
	}


	public function getPath(string $key): string
	{
		return "{$this->getBasePath($key)}/{$this->getName($key)}.{$this->getExtension($key)}";
	}


	public function getConfigDir(string $key): ?string
	{
		return __DIR__ . '/../../files/'
			. match($key) {
				BuildGenerator::BASE_PRESENTER,
				BuildGenerator::BASE_PRESENTER_TEMPLATE,
				BuildGenerator::BASE_TEMPLATE,
				BuildGenerator::TEMPLATE_FACTORY,
				BuildGenerator::MODEL,
				BuildGenerator::DATA_MODEL => 'build',

				ModuleGenerator::MODULE,
				ModuleGenerator::PRESENTER_TRAIT,
				ModuleGenerator::PRESENTER_TEMPLATE_TRAIT,
				ModuleGenerator::TEMPLATE_TRAIT,
				ModuleGenerator::TEMPLATE_FACTORY_TRAIT,
				ModuleGenerator::MAIN_COMPONENT,
				ModuleGenerator::MAIN_FACTORY,
				ModuleGenerator::MAIN_TEMPLATE,
				ModuleGenerator::MODEL_TRAIT,
				ModuleGenerator::DATA_MODEL_TRAIT,
				ModuleGenerator::DI_EXTENSION => 'module',

				BuildModelGenerator::ENTITY,
				BuildModelGenerator::MAPPER,
				BuildModelGenerator::REPOSITORY,
				BuildModelGenerator::DATA_OBJECT,
				BuildModelGenerator::DATA_REPOSITORY,

				ModelGenerator::ENTITY_TRAIT,
				ModelGenerator::MAPPER_TRAIT,
				ModelGenerator::REPOSITORY_TRAIT,
				ModelGenerator::DATA_OBJECT_TRAIT,
				ModelGenerator::DATA_REPOSITORY_TRAIT,
				ModelGenerator::CONVENTIONS => 'model',

				ComponentGenerator::TEMPLATE,
				ComponentGenerator::CONTROL,
				ComponentGenerator::FACTORY => 'component',

				CommandGenerator::COMMAND => 'command',
				ServiceGenerator::SERVICE => 'service',

				default => throw new InvalidArgumentException("'$key' config dir is not defined"),
			};
	}


	public function getConfig(string $key): string
	{
		return $this->getConfigDir($key) . "/$key.neon";
	}


	public function getName(string $key): string
	{
		$lmoduleName = $this->module ? lcfirst($this->moduleName) : null;
		return match($key) {
			BuildGenerator::BASE_PRESENTER => 'BasePresenter',
			BuildGenerator::BASE_PRESENTER_TEMPLATE => 'BasePresenterTemplate',
			BuildGenerator::BASE_TEMPLATE => 'BaseTemplate',
			BuildGenerator::TEMPLATE_FACTORY => 'BaseTemplateFactory',
			BuildGenerator::MODEL => 'Orm',
			BuildGenerator::DATA_MODEL => 'DataModel',

			ModuleGenerator::MODULE => $this->moduleName,
			ModuleGenerator::PRESENTER_TRAIT => "{$this->moduleName}Presenter",
			ModuleGenerator::PRESENTER_TEMPLATE_TRAIT => "{$this->moduleName}PresenterTemplate",
			ModuleGenerator::TEMPLATE_TRAIT => "{$this->moduleName}Template",
			ModuleGenerator::TEMPLATE_FACTORY_TRAIT => "{$this->moduleName}TemplateFactory",
			ModuleGenerator::MAIN_COMPONENT => "{$this->moduleName}Control",
			ModuleGenerator::MAIN_FACTORY => "I{$this->moduleName}Control",
			ModuleGenerator::MAIN_TEMPLATE => "{$this->moduleName}Template",
			ModuleGenerator::MAIN_LATTE => "$lmoduleName",
			ModuleGenerator::MODEL_TRAIT => "{$this->moduleName}Orm",
			ModuleGenerator::DATA_MODEL_TRAIT => "{$this->moduleName}DataModel",
			ModuleGenerator::DI_EXTENSION => "{$this->moduleName}Extension",
			ModuleGenerator::CONFIG_NEON => "config",
			ModuleGenerator::DEFINITION_NEON => "001-init",
			ModuleGenerator::MANIPULATION_NEON => "insert.{$this->module->type}.$lmoduleName",

			BuildModelGenerator::ENTITY => $this->name,
			BuildModelGenerator::MAPPER => "{$this->name}Mapper",
			BuildModelGenerator::REPOSITORY => "{$this->name}Repository",
			BuildModelGenerator::DATA_OBJECT => "{$this->name}Data",
			BuildModelGenerator::DATA_REPOSITORY => "{$this->name}DataRepository",

			ModelGenerator::ENTITY_TRAIT => $this->traitName,
			ModelGenerator::MAPPER_TRAIT => "{$this->traitName}Mapper",
			ModelGenerator::REPOSITORY_TRAIT => "{$this->traitName}Repository",
			ModelGenerator::DATA_OBJECT_TRAIT => "{$this->traitName}Data",
			ModelGenerator::DATA_REPOSITORY_TRAIT => "{$this->traitName}DataRepository",
			ModelGenerator::CONVENTIONS => "{$this->name}Conventions",

			ComponentGenerator::TEMPLATE => "{$this->name}Template",
			ComponentGenerator::CONTROL => "{$this->name}Control",
			ComponentGenerator::FACTORY => "I{$this->name}Control",
			ComponentGenerator::LATTE => "{$this->templateName}",
			ComponentGenerator::DATASET_NEON,
			ComponentGenerator::MENU_NEON => "$this->lname",

			CommandGenerator::COMMAND,
			ServiceGenerator::SERVICE => $this->name,

			default => throw new InvalidArgumentException("'$key' name is not defined"),
		};
	}


	public function getNamespace(string $key): string
	{
		return match($key) {
			BuildGenerator::BASE_PRESENTER,
			BuildGenerator::BASE_PRESENTER_TEMPLATE => "$this->buildNamespace\Presenter",
			BuildGenerator::BASE_TEMPLATE => "$this->buildNamespace\Control",
			BuildGenerator::TEMPLATE_FACTORY => "$this->buildNamespace\Lib",
			BuildGenerator::MODEL,
			BuildGenerator::DATA_MODEL => "$this->buildNamespace\Model",

			ModuleGenerator::MODULE => $this->moduleNamespace,
			ModuleGenerator::PRESENTER_TRAIT,
			ModuleGenerator::PRESENTER_TEMPLATE_TRAIT => "$this->moduleNamespace\Presenter",
			ModuleGenerator::TEMPLATE_TRAIT => "$this->moduleNamespace\Control",
			ModuleGenerator::TEMPLATE_FACTORY_TRAIT => "$this->moduleNamespace\Lib",
			ModuleGenerator::MAIN_COMPONENT,
			ModuleGenerator::MAIN_FACTORY,
			ModuleGenerator::MAIN_TEMPLATE => "$this->moduleNamespace\Control\\$this->moduleName",
			ModuleGenerator::MODEL_TRAIT,
			ModuleGenerator::DATA_MODEL_TRAIT => "$this->moduleNamespace\Model",
			ModuleGenerator::DI_EXTENSION => "$this->moduleNamespace\DI",

			BuildModelGenerator::ENTITY,
			BuildModelGenerator::MAPPER,
			BuildModelGenerator::REPOSITORY,
			BuildModelGenerator::DATA_OBJECT,
			BuildModelGenerator::DATA_REPOSITORY => $this->buildEntityNamespace,

			ModelGenerator::ENTITY_TRAIT,
			ModelGenerator::MAPPER_TRAIT,
			ModelGenerator::REPOSITORY_TRAIT,
			ModelGenerator::DATA_OBJECT_TRAIT,
			ModelGenerator::DATA_REPOSITORY_TRAIT,
			ModelGenerator::CONVENTIONS => $this->appEntityNamespace,

			ComponentGenerator::TEMPLATE,
			ComponentGenerator::CONTROL,
			ComponentGenerator::FACTORY => $this->componentNamespace,

			CommandGenerator::COMMAND => $this->commandNamespace,
			ServiceGenerator::SERVICE => $this->serviceNamespace,

			default => throw new InvalidArgumentException("'$key' namespace is not defined"),
		};
	}


	public function getFqn(string $key): string
	{
		return "{$this->getNamespace($key)}\\{$this->getName($key)}";
	}
}