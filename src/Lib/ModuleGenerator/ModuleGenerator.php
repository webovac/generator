<?php

declare(strict_types=1);

namespace Webovac\Generator\Lib\ModuleGenerator;

use Webovac\Generator\Config\Module;
use Webovac\Generator\Lib\BaseGenerator;
use Webovac\Generator\Lib\BuildGenerator\BuildGenerator;
use Webovac\Generator\Lib\SetupProvider\ISetupProvider;


class ModuleGenerator extends BaseGenerator
{
	public const string MODULE = 'module';
	public const string PRESENTER_TRAIT = 'presenterTrait';
	public const string PRESENTER_TEMPLATE_TRAIT = 'presenterTemplateTrait';
	public const string TEMPLATE_TRAIT = 'templateTrait';
	public const string TEMPLATE_FACTORY_TRAIT = 'templateFactoryTrait';
	public const string MAIN_COMPONENT = 'mainComponent';
	public const string MAIN_FACTORY = 'mainFactory';
	public const string MAIN_TEMPLATE = 'mainTemplate';
	public const string MODEL_TRAIT = 'modelTrait';
	public const string DATA_MODEL_TRAIT = 'dataModelTrait';
	public const string DI_EXTENSION = 'diExtension';
	public const string MAIN_LATTE = 'mainLatte';
	public const string CONFIG_NEON = 'configNeon';
	public const string DEFINITION_NEON = 'definitionNeon';
	public const string MANIPULATION_NEON = 'manipulationNeon';

	private string $name;
	private string $lname;


	public function __construct(
		private Module $module,
		ISetupProvider $setupProviderFactory,
	) {
		$this->name = $this->module->name;
		$this->lname = lcfirst($this->name);
		$this->setupProvider = $setupProviderFactory->create(
			module: $this->module,
		);
	}
	
	
	public function generate(): void
	{
		if (!$this->module->isPackage) {
			$this->createModule();
			$this->createPresenterTrait();
			$this->createPresenterTemplateTrait();
			$this->createTemplateTrait();
			$this->createTemplateFactoryTrait();
			$this->createMainComponent();
			$this->createMainFactory();
//			$this->generateMainTemplate();
			$this->createMainLatte();
			if ($this->module->entities) {
				$this->createDataModelTrait();
				$this->createModelTrait();
			}
			if ($this->module->withDIExtension) {
				$this->createDIExtension();
				$this->createConfigNeon();
			}
			if ($this->module->withInstallFile) {
				$this->createManipulationNeon();
			}
			if ($this->module->withMigrationGroup) {
				$this->createDefinitionNeon();
			}
		}
	}


	public function remove(): void
	{
		$this->writer->remove($this->setupProvider->getBasePath(self::MODULE));
	}


	public function updateBuild(): void
	{
		$this->updateFile(BuildGenerator::BASE_PRESENTER, ModuleGenerator::PRESENTER_TRAIT);
		$this->updateFile(BuildGenerator::BASE_PRESENTER_TEMPLATE, ModuleGenerator::PRESENTER_TEMPLATE_TRAIT);
		$this->updateFile(BuildGenerator::BASE_TEMPLATE, ModuleGenerator::TEMPLATE_TRAIT);
		$this->updateFile(BuildGenerator::TEMPLATE_FACTORY, ModuleGenerator::TEMPLATE_FACTORY_TRAIT);
		if ($this->module->entities) {
			$this->updateFile(BuildGenerator::MODEL, ModuleGenerator::MODEL_TRAIT);
			$this->updateFile(BuildGenerator::DATA_MODEL, ModuleGenerator::DATA_MODEL_TRAIT);
		}
	}


	private function createModule(): void
	{
		$this->write(self::MODULE, [
			'hideDefinition' => !$this->module->withInstallGroups,
			'hideManipulation' => !$this->module->withMigrationGroup,
			'getModuleName.body' => /* language=PHP */ "return '$this->lname';",
			'getDefinitionGroup.body' => <<<PHP
return new DefinitionGroup($this->name::getModuleName(), $this->name::class, [Core::getModuleName()]);
PHP,
			'getManipulationGroup.body' => <<<PHP
return [
	'' => new ManipulationGroup('', '', []),
];
PHP,
		]);
	}


	private function createPresenterTrait(): void
	{
		$this->write(self::PRESENTER_TRAIT, [
			'mainControl' => $this->setupProvider->getFqn(self::MAIN_COMPONENT),
			'mainControlInterface' => $this->setupProvider->getFqn(self::MAIN_FACTORY),
			'lname' => $this->lname,
			'injectStartupMethod.name' => "inject{$this->name}Startup",
			'injectStartupMethod.body' => <<<PHP
\$this->onStartup[] = function () {
	
};
PHP,
			'createComponentMethod.name' => "createComponent$this->name",
			'createComponentMethod.body' => /* language=PHP */ "return \$this->$this->lname->create(\$this->entity);",
		]);
	}


	private function createPresenterTemplateTrait(): void
	{
		$this->write(self::PRESENTER_TEMPLATE_TRAIT);
	}


	private function createTemplateTrait(): void
	{
		$this->write(self::TEMPLATE_TRAIT);
	}


	private function createTemplateFactoryTrait(): void
	{
		$this->write(self::TEMPLATE_FACTORY_TRAIT, [
			'injectCreateMethod.name' => "inject{$this->name}Create",
			'injectCreateMethod.body' => <<<PHP
\$this->onCreate[] = function (Template \$template) {
	if (\$template instanceof BaseTemplate) {

	}
};
PHP,
		]);
	}


	private function createMainComponent(): void
	{
		$this->write(self::MAIN_COMPONENT, [
			'renderMethod.body' => /* language=PHP */ "\$this->template->render(__DIR__ . '/$this->lname.latte');",
		]);
	}


	private function createMainFactory(): void
	{
		$this->write(self::MAIN_FACTORY, [
			'createMethod.returnType' => $this->setupProvider->getFqn(self::MAIN_COMPONENT),
		]);
	}


	private function createMainTemplate(): void
	{
		$this->write(self::MAIN_TEMPLATE);
	}


	private function createModelTrait(): void
	{
		$this->write(self::MODEL_TRAIT);
	}


	private function createDataModelTrait(): void
	{
		$this->write(self::DATA_MODEL_TRAIT);
	}


	private function createDIExtension(): void
	{
		$this->write(self::DI_EXTENSION);
	}


	private function createMainLatte(): void
	{
		$path = $this->setupProvider->getPath(self::MAIN_LATTE);
		$latte = <<<PHP
{templateType {$this->setupProvider->getFqn(self::MAIN_TEMPLATE)}}

PHP;
		$this->writer->write($path, $latte);
	}


	private function createConfigNeon(): void
	{
		$path = $this->setupProvider->getPath(self::CONFIG_NEON);
		$neon = <<<NEON
services:

NEON;
		$this->writer->write($path, $neon);
	}


	private function createDefinitionNeon(): void
	{
		$path = $this->setupProvider->getPath(self::DEFINITION_NEON);
		$this->writer->write($path, );
	}


	private function createManipulationNeon(): void
	{
		$path = $this->setupProvider->getPath(self::MANIPULATION_NEON);
		if ($this->module->type === 'module') {
			$neon = <<<NEON
class: Build\Model\Module\ModuleData
items:
	$this->name:
		name: $this->name
		homePage: $this->name:Home
		icon:
		translations:
			cs: [title: $this->name, basePath: $this->lname, description: '']
			en: [title: $this->name, basePath: $this->lname, description: '']
		pages:
			$this->name:Home:
				icon: 
				translations:
					cs: [title: $this->name, path: , content: '<h1>$this->name</h1>']
					en: [title: $this->name, path: , content: '<h1>$this->name</h1>']
		tree:
			$this->name:Home:

NEON;
		} else if ($this->module->type === 'web') {
			$neon = <<<NEON
class: Build\Model\Web\WebData
items:
	$this->lname:
		host: %host%
		code: $this->lname
		homePage: Home
		color: ''
		complementaryColor: ''
		iconBackgroundColor: ''
		layout: default 
		translations:
			cs: [title: $this->name]
			en: [title: $this->name]
		pages:
			Home:
				icon: 
				translations:
					cs: [title: $this->name, path: , content: '<h1>$this->name</h1>']
					en: [title: $this->name, path: en, content: '<h1>$this->name</h1>']
		webModules: [Admin, Auth]
		tree:
			Home:

NEON;
		}
		$this->writer->write($path, $neon);
	}
}
