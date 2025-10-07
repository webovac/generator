<?php

declare(strict_types=1);

namespace Webovac\Generator;

use Webovac\Generator\Config\Module;
use Webovac\Generator\Lib\Writer;


class ModuleGenerator
{
	private string $name;
	private string $lname;
	private string $namespace;
	private string $mainControl;
	private string $mainControlInterface;
	private Writer $writer;
	private FileGenerator $fileGenerator;


	public function __construct(
		private Module $module,
	) {
		$this->name = $this->module->name;
		$this->lname = lcfirst($this->name);
		$this->namespace = "{$this->module->namespace}\\$this->name";
		$this->mainControl = "$this->namespace\Control\\$this->name\\{$this->name}Control";
		$this->mainControlInterface = "$this->namespace\Control\\$this->name\I{$this->name}Control";
		$this->writer = new Writer;
		$this->fileGenerator = new FileGenerator;
	}


	public function createModule(string $path): void
	{
		$this->fileGenerator->write($path, __DIR__ . '/files/module/module.neon', [
			'name' => $this->name,
			'namespace' => $this->namespace,
			'hideDefinition' => !$this->module->withInstallGroups,
			'hideManipulation' => !$this->module->withMigrationGroup,
			'getModuleName.body' => "return '$this->lname';",
			'getDefinitionGroup.body' => <<<EOT
return new DefinitionGroup($this->name::getModuleName(), $this->name::class, [Core::getModuleName()]);
EOT,
			'getManipulationGroup.body' => <<<EOT
return [
	'' => new ManipulationGroup('', '', []),
];
EOT,
		]);
	}


	public function createPresenterTrait(string $path): void
	{
		$this->fileGenerator->write($path, __DIR__ . '/files/module/presenterTrait.neon', [
			'name' => "{$this->name}Presenter",
			'namespace' => "$this->namespace\Presenter",
			'mainControl' => $this->mainControl,
			'mainControlInterface' => $this->mainControlInterface,
			'lname' => $this->lname,
			'injectStartupMethod.name' => "inject{$this->name}Startup",
			'injectStartupMethod.body' => <<<EOT
\$this->onStartup[] = function () {
	
};
EOT,
			'createComponentMethod.name' => "createComponent$this->name",
			'createComponentMethod.body' => "return \$this->$this->lname->create(\$this->entity);",
		]);
	}


	public function createPresenterTemplateTrait(string $path): void
	{
		$this->fileGenerator->write($path, __DIR__ . '/files/module/presenterTemplateTrait.neon', [
			'name' => "{$this->name}PresenterTemplate",
			'namespace' => "$this->namespace\Presenter",
		]);
	}


	public function createTemplateTrait(string $path): void
	{
		$this->fileGenerator->write($path, __DIR__ . '/files/module/templateTrait.neon', [
			'name' => "{$this->name}Template",
			'namespace' => "$this->namespace\Control",
		]);
	}


	public function createTemplateFactoryTrait(string $path): void
	{
		$this->fileGenerator->write($path, __DIR__ . '/files/module/templateFactoryTrait.neon', [
			'name' => "{$this->name}TemplateFactory",
			'namespace' => "$this->namespace\Lib",
			'injectCreateMethod.name' => "inject{$this->name}Create",
			'injectCreateMethod.body' => <<<EOT
\$this->onCreate[] = function (Template \$template) {
	if (\$template instanceof BaseTemplate) {

	}
};
EOT,
		]);
	}


	public function createMainComponent(string $path): void
	{
		$this->fileGenerator->write($path, __DIR__ . '/files/module/mainComponent.neon', [
			'name' => "{$this->name}Control",
			'namespace' => "$this->namespace\Control\\$this->name",
			'renderMethod.body' => "\$this->template->render(__DIR__ . '/$this->lname.latte');",
		]);
	}


	public function createMainFactory(string $path): void
	{
		$this->fileGenerator->write($path, __DIR__ . '/files/module/mainFactory.neon', [
			'name' => "I{$this->name}Control",
			'namespace' => "$this->namespace\Control\\$this->name",
			'createMethod.returnType' => "$this->namespace\Control\\$this->name\\{$this->name}Control",
		]);
	}


	public function createMainTemplate(string $path): void
	{
		$this->fileGenerator->write($path, __DIR__ . '/files/module/mainTemplate.neon', [
			'name' => "{$this->name}Template",
			'namespace' => "$this->namespace\Control\\$this->name",
		]);
	}


	public function createModelTrait(string $path): void
	{
		$this->fileGenerator->write($path, __DIR__ . '/files/module/modelTrait.neon', [
			'name' => "{$this->name}Orm",
			'namespace' => "$this->namespace\Model",
		]);
	}


	public function createDataModelTrait(string $path): void
	{
		$this->fileGenerator->write($path, __DIR__ . '/files/module/dataModelTrait.neon', [
			'name' => "{$this->name}DataModel",
			'namespace' => "$this->namespace\Model",
		]);
	}


	public function createDIExtension(string $path): void
	{
		$this->fileGenerator->write($path, __DIR__ . '/files/module/diExtension.neon', [
			'name' => "{$this->name}Extension",
			'namespace' => "$this->namespace\DI",
		]);
	}


	public function createMainLatte(string $path): void
	{
		$latte = <<<EOT
{templateType $this->namespace\Control\\$this->name\\{$this->name}Template}

EOT;
		$this->writer->write($path, $latte);
	}


	public function createConfigNeon(string $path): void
	{
		$neon = <<<EOT
services:

EOT;
		$this->writer->write($path, $neon);
	}


	public function createInstallNeon(string $path, string $type): void
	{
		if ($type === 'module') {
			$neon = <<<EOT
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

EOT;
		} else if ($type === 'web') {
			$neon = <<<EOT
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

EOT;
		}
		$this->writer->write($path, $neon);
	}


	public function updateBasePresenter(string $path): void
	{
		$this->updateFile($path, "$this->namespace\Presenter\\{$this->name}Presenter");
	}


	public function updateBasePresenterTemplate(string $path): void
	{
		$this->updateFile($path, "$this->namespace\Presenter\\{$this->name}PresenterTemplate");
	}


	public function updateBaseTemplate(string $path): void
	{
		$this->updateFile($path, "$this->namespace\Control\\{$this->name}Template");
	}


	public function updateTemplateFactory(string $path): void
	{
		$this->updateFile($path, "$this->namespace\Lib\\{$this->name}TemplateFactory");
	}


	public function updateModel(string $path): void
	{
		$this->updateFile($path, "$this->namespace\Model\\{$this->name}Orm");
	}


	public function updateDataModel(string $path): void
	{
		$this->updateFile($path, "$this->namespace\Model\\{$this->name}DataModel");
	}


	private function updateFile(string $path, string $trait, array $implements = []): void
	{
		$this->writer->updateFile($path, $trait, $implements);
	}
}
